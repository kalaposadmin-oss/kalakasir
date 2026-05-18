<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class PurchaseReceiveVariantInventoryBalanceTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_purchase_receive_posts_variant_specific_inventory_bucket_and_ledger(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-variant-receive@example.test', 'Admin Variant Receive');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $supplierId = (string) Str::uuid();
        $variantProductId = (string) Str::uuid();
        $variantId = (string) Str::uuid();

        DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $tenant->id,
            'name' => 'Supplier Variant',
            'balance' => 0,
            'created_at' => CarbonImmutable::now('UTC'),
        ]);

        DB::table('products')->insert([
            'id' => $variantProductId,
            'tenant_id' => $tenant->id,
            'sku' => 'VAR-001',
            'name' => 'Produk Varian',
            'price' => 25000,
            'hpp_average' => 12000,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => CarbonImmutable::now('UTC'),
            'updated_at' => CarbonImmutable::now('UTC'),
            'deleted_at' => null,
        ]);

        DB::table('product_variants')->insert([
            'id' => $variantId,
            'tenant_id' => $tenant->id,
            'product_id' => $variantProductId,
            'sku' => 'VAR-001-A',
            'barcode' => null,
            'name' => 'Varian A',
            'attributes' => json_encode(['size' => 'A']),
            'price_override' => null,
            'is_active' => true,
            'created_at' => CarbonImmutable::now('UTC'),
            'updated_at' => CarbonImmutable::now('UTC'),
        ]);

        $createResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'location_id' => $fixture['location_id'],
                'payment_method' => 'tempo',
                'purchase_mode' => 'po_short',
                'status' => 'submitted',
                'items' => [
                    [
                        'product_id' => $variantProductId,
                        'qty' => 5,
                        'price' => 10000,
                    ],
                ],
            ]);

        $createResponse->assertCreated();
        $purchaseId = (string) $createResponse->json('purchase_id');

        DB::table('purchase_items')
            ->where('tenant_id', $tenant->id)
            ->where('purchase_id', $purchaseId)
            ->update([
                'product_variant_id' => $variantId,
            ]);

        $detailResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/purchases/'.$purchaseId);

        $detailResponse->assertOk()
            ->assertJsonPath('purchase.location_id', $fixture['location_id'])
            ->assertJsonPath('purchase.location_name', 'Main Warehouse')
            ->assertJsonPath('items.0.product_variant_id', $variantId)
            ->assertJsonPath('items.0.product_variant_name', 'Varian A');

        $receiveResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'transaction_date' => CarbonImmutable::now('UTC')->toDateString(),
                'items' => [
                    [
                        'product_id' => $variantProductId,
                        'qty' => 5,
                    ],
                ],
            ]);

        $receiveResponse->assertOk()
            ->assertJsonPath('status', 'received')
            ->assertJsonPath('received_total', 50000);

        $this->assertSame(1, DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $fixture['location_id'])
            ->where('product_id', $variantProductId)
            ->where('product_variant_id', $variantId)
            ->count());

        $this->assertSame(0, DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $fixture['location_id'])
            ->where('product_id', $variantProductId)
            ->whereNull('product_variant_id')
            ->count());

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $variantProductId,
            'product_variant_id' => $variantId,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'purchase',
            'reference_id' => $purchaseId,
            'qty_in' => 5,
            'qty_out' => 0,
        ]);
    }
}