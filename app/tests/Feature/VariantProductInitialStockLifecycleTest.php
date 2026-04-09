<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class VariantProductInitialStockLifecycleTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_variant_product_initial_stock_checkout_and_void_keep_bucket_isolation_consistent(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+variant-lifecycle@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        DB::table('tenants')
            ->where('id', $tenant->id)
            ->update(['inventory_valuation_method' => 'fifo']);

        $variantA = (string) Str::uuid();
        $variantB = (string) Str::uuid();

        $createResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/products', [
                'name' => 'Produk Lifecycle Variant',
                'sku' => 'LC-VAR-001',
                'price' => 25000,
                'hpp_average' => 12000,
                'track_stock' => true,
                'location_id' => $fixture['location_id'],
                'variants' => [
                    ['id' => $variantA, 'sku' => 'LC-VAR-A', 'name' => 'Variant A'],
                    ['id' => $variantB, 'sku' => 'LC-VAR-B', 'name' => 'Variant B'],
                ],
                'initial_stock_lines' => [
                    ['product_variant_id' => $variantA, 'qty' => 5, 'unit_cost' => 11000],
                    ['product_variant_id' => $variantB, 'qty' => 3, 'unit_cost' => 15000],
                ],
            ]);

        $createResponse->assertCreated();
        $productId = (string) $createResponse->json('product.id');

        $this->assertVariantBucketState($tenant->id, $productId, $variantA, $fixture['location_id'], 5.0, 11000.0);
        $this->assertVariantBucketState($tenant->id, $productId, $variantB, $fixture['location_id'], 3.0, 15000.0);
        $this->assertNoNullVariantBucketArtifacts($tenant->id, $productId, $fixture['location_id']);

        $checkoutPayload = $this->checkoutPayload($fixture, '2026-02-27');
        $checkoutPayload['subtotal'] = 25000;
        $checkoutPayload['total'] = 25000;
        $checkoutPayload['payments'][0]['amount'] = 25000;
        $checkoutPayload['items'] = [[
            'product_id' => $productId,
            'product_variant_id' => $variantA,
            'location_id' => $fixture['location_id'],
            'qty' => 1,
            'price' => 25000,
            'discount' => 0,
        ]];

        $saleId = $this->createSale($tenant->id, $checkoutPayload);
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        $this->assertVariantBucketState($tenant->id, $productId, $variantA, $fixture['location_id'], 4.0, 11000.0);
        $this->assertVariantBucketState($tenant->id, $productId, $variantB, $fixture['location_id'], 3.0, 15000.0);
        $this->assertNoNullVariantBucketArtifacts($tenant->id, $productId, $fixture['location_id']);

        $saleAllocation = $this->allocationQuery($tenant->id, $saleId, $saleItemId, $productId, $variantA, 'sale')->first();
        $this->assertNotNull($saleAllocation);
        $this->assertSame(1.0, (float) $saleAllocation->qty);
        $this->assertSame(0.0, (float) $saleAllocation->reversed_qty);
        $this->assertNull($saleAllocation->reversed_at);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $productId,
            'product_variant_id' => $variantA,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 0,
            'qty_out' => 1,
        ]);

        $this->assertSame(0, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $productId)
            ->where('location_id', $fixture['location_id'])
            ->where('reference_id', $saleId)
            ->whereNull('product_variant_id')
            ->count());

        $this->voidSale($tenant->id, $saleId)
            ->assertOk()
            ->assertJsonPath('status', 'voided');

        $this->assertVariantBucketState($tenant->id, $productId, $variantA, $fixture['location_id'], 5.0, 11000.0);
        $this->assertVariantBucketState($tenant->id, $productId, $variantB, $fixture['location_id'], 3.0, 15000.0);
        $this->assertNoNullVariantBucketArtifacts($tenant->id, $productId, $fixture['location_id']);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $productId,
            'product_variant_id' => $variantA,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'void',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 1,
            'qty_out' => 0,
        ]);

        $allocationAfterVoid = $this->allocationQuery($tenant->id, $saleId, $saleItemId, $productId, $variantA, 'sale')->first();
        $this->assertNotNull($allocationAfterVoid);
        $this->assertSame(1.0, (float) $allocationAfterVoid->qty);
        $this->assertSame(1.0, (float) $allocationAfterVoid->reversed_qty);
        $this->assertNotNull($allocationAfterVoid->reversed_at);

        $this->assertSame(0, $this->allocationQuery($tenant->id, $saleId, $saleItemId, $productId, $variantA, 'sale')
            ->whereRaw('qty > reversed_qty')
            ->count());
    }

    private function createSale(string $tenantId, array $payload): string
    {
        $payload['idempotency_key'] = (string) Str::uuid();

        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/checkout', $payload);

        $response->assertCreated();

        return (string) $response->json('sale_id');
    }

    private function voidSale(string $tenantId, string $saleId)
    {
        return $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/sales/'.$saleId.'/void', [
                'reason_code' => 'V01',
            ]);
    }

    private function assertVariantBucketState(
        string $tenantId,
        string $productId,
        string $variantId,
        string $locationId,
        float $expectedQty,
        float $expectedAverageCost
    ): void {
        $balanceQty = (float) DB::table('inventory_balances')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->where('location_id', $locationId)
            ->value('qty_on_hand');

        $this->assertSame($expectedQty, $balanceQty);

        $state = DB::table('inventory_cost_states')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->where('location_id', $locationId)
            ->first();

        $this->assertNotNull($state);
        $this->assertSame($expectedQty, (float) $state->qty_on_hand);
        $this->assertSame($expectedAverageCost, (float) $state->average_cost);

        $layerQty = (float) DB::table('inventory_layers')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->where('location_id', $locationId)
            ->sum('qty_remaining');

        $this->assertSame($expectedQty, $layerQty);
    }

    private function assertNoNullVariantBucketArtifacts(string $tenantId, string $productId, string $locationId): void
    {
        $this->assertSame(0, DB::table('inventory_balances')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->whereNull('product_variant_id')
            ->count());

        $this->assertSame(0, DB::table('inventory_cost_states')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->whereNull('product_variant_id')
            ->count());

        $this->assertSame(0, DB::table('inventory_layers')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->whereNull('product_variant_id')
            ->count());
    }

    private function allocationQuery(
        string $tenantId,
        string $saleId,
        string $saleItemId,
        string $productId,
        string $variantId,
        string $referenceType
    ) {
        return DB::table('inventory_issue_allocations')
            ->where('tenant_id', $tenantId)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId);
    }
}