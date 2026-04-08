<?php

namespace Tests\Feature;

use App\Services\RefundService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class VariantProductInitialStockRefundLifecycleTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_variant_product_initial_stock_checkout_and_partial_refund_keep_bucket_isolation_consistent(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+variant-refund-lifecycle@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        DB::table('tenants')
            ->where('id', $tenant->id)
            ->update(['inventory_valuation_method' => 'fifo']);

        $variantA = (string) Str::uuid();
        $variantB = (string) Str::uuid();

        $createResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/products', [
                'name' => 'Produk Refund Lifecycle Variant',
                'sku' => 'LC-REF-VAR-001',
                'price' => 25000,
                'hpp_average' => 12000,
                'track_stock' => true,
                'location_id' => $fixture['location_id'],
                'variants' => [
                    ['id' => $variantA, 'sku' => 'LC-REF-VAR-A', 'name' => 'Variant A'],
                    ['id' => $variantB, 'sku' => 'LC-REF-VAR-B', 'name' => 'Variant B'],
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
        $checkoutPayload['subtotal'] = 50000;
        $checkoutPayload['total'] = 50000;
        $checkoutPayload['payments'][0]['amount'] = 50000;
        $checkoutPayload['items'] = [[
            'product_id' => $productId,
            'product_variant_id' => $variantA,
            'location_id' => $fixture['location_id'],
            'qty' => 2,
            'price' => 25000,
            'discount' => 0,
        ]];

        $saleId = $this->createSale($tenant->id, $checkoutPayload);
        $saleItem = DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->first();

        $this->assertNotNull($saleItem);
        $saleItemId = (string) $saleItem->id;
        $this->assertSame(11000.0, (float) $saleItem->hpp_snapshot);

        $this->assertVariantBucketState($tenant->id, $productId, $variantA, $fixture['location_id'], 3.0, 11000.0);
        $this->assertVariantBucketState($tenant->id, $productId, $variantB, $fixture['location_id'], 3.0, 15000.0);
        $this->assertNoNullVariantBucketArtifacts($tenant->id, $productId, $fixture['location_id']);

        $saleAllocation = $this->allocationQuery($tenant->id, $saleId, $saleItemId, $productId, $variantA)->first();
        $this->assertNotNull($saleAllocation);
        $this->assertSame(2.0, (float) $saleAllocation->qty);
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
            'qty_out' => 2,
        ]);

        $this->assertSame(0, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $productId)
            ->where('reference_id', $saleId)
            ->where('location_id', $fixture['location_id'])
            ->where('reference_type', 'sale')
            ->whereNull('product_variant_id')
            ->count());

        TenantContext::set($tenant->fresh());

        $refundId = app(RefundService::class)->handle([
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'reason' => 'Refund partial variant lifecycle',
            'items' => [
                [
                    'sale_item_id' => $saleItemId,
                    'product_id' => $productId,
                    'product_variant_id' => $variantA,
                    'qty' => 1,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $fixture['cash_account_id'],
                    'payment_method_id' => $fixture['payment_method_id'],
                    'method' => 'cash',
                    'amount' => 25000,
                ],
            ],
        ], $user->id);

        $this->assertNotSame('', $refundId);

        $this->assertVariantBucketState($tenant->id, $productId, $variantA, $fixture['location_id'], 4.0, 11000.0);
        $this->assertVariantBucketState($tenant->id, $productId, $variantB, $fixture['location_id'], 3.0, 15000.0);
        $this->assertNoNullVariantBucketArtifacts($tenant->id, $productId, $fixture['location_id']);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $productId,
            'product_variant_id' => $variantA,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'refund',
            'reference_id' => $refundId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 1,
            'qty_out' => 0,
        ]);

        $this->assertSame(0, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $productId)
            ->where('reference_id', $refundId)
            ->where('location_id', $fixture['location_id'])
            ->where('reference_type', 'refund')
            ->whereNull('product_variant_id')
            ->count());

        $refundItem = DB::table('refund_items')
            ->where('tenant_id', $tenant->id)
            ->where('refund_id', $refundId)
            ->where('sale_item_id', $saleItemId)
            ->first();

        $this->assertNotNull($refundItem);
        $this->assertSame(1.0, (float) $refundItem->qty);
        $this->assertSame(11000.0, (float) $refundItem->hpp_snapshot);
        $this->assertSame(11000.0, (float) $refundItem->refunded_cost_total);

        $allocationAfterRefund = $this->allocationQuery($tenant->id, $saleId, $saleItemId, $productId, $variantA)->first();
        $this->assertNotNull($allocationAfterRefund);
        $this->assertSame(2.0, (float) $allocationAfterRefund->qty);
        $this->assertSame(1.0, (float) $allocationAfterRefund->reversed_qty);
        $this->assertNull($allocationAfterRefund->reversed_at);

        $this->assertSame(1, $this->allocationQuery($tenant->id, $saleId, $saleItemId, $productId, $variantA)
            ->whereRaw('qty > reversed_qty')
            ->count());

        $saleItemFresh = DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('id', $saleItemId)
            ->first();

        $this->assertNotNull($saleItemFresh);
        $this->assertSame(11000.0, (float) $saleItemFresh->hpp_snapshot);
    }

    private function createSale(string $tenantId, array $payload): string
    {
        $payload['idempotency_key'] = (string) Str::uuid();

        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/checkout', $payload);

        $response->assertCreated();

        return (string) $response->json('sale_id');
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
        string $variantId
    ) {
        return DB::table('inventory_issue_allocations')
            ->where('tenant_id', $tenantId)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId);
    }
}