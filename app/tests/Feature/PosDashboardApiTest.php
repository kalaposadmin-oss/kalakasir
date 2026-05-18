<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class PosDashboardApiTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_cashier_can_load_pos_dashboard_top_products_and_low_stock(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier-pos-dashboard@example.test', 'Kasir Dashboard');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $todayAt = CarbonImmutable::parse('2026-02-27 09:00:00', 'UTC');

        DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('id', $fixture['product_id'])
            ->update([
                'name' => 'Roti Coklat',
                'sku' => 'ROTI-COKLAT',
                'min_stock' => 5,
            ]);

        $secondProductId = (string) Str::uuid();
        $thirdProductId = (string) Str::uuid();

        DB::table('products')->insert([
            [
                'id' => $secondProductId,
                'tenant_id' => $tenant->id,
                'sku' => 'ROTI-KEJU',
                'name' => 'Roti Keju',
                'price' => 12000,
                'hpp_average' => 6000,
                'image_url' => null,
                'is_active' => true,
                'track_stock' => true,
                'min_stock' => 6,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'created_at' => $todayAt,
                'updated_at' => $todayAt,
                'deleted_at' => null,
            ],
            [
                'id' => $thirdProductId,
                'tenant_id' => $tenant->id,
                'sku' => 'ROTI-MANIS',
                'name' => 'Roti Manis',
                'price' => 9000,
                'hpp_average' => 4000,
                'image_url' => null,
                'is_active' => true,
                'track_stock' => true,
                'min_stock' => 4,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'created_at' => $todayAt,
                'updated_at' => $todayAt,
                'deleted_at' => null,
            ],
        ]);

        DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $fixture['location_id'])
            ->where('product_id', $fixture['product_id'])
            ->update(['qty_on_hand' => 12]);

        DB::table('inventory_balances')->insert([
            [
                'tenant_id' => $tenant->id,
                'location_id' => $fixture['location_id'],
                'product_id' => $secondProductId,
                'qty_on_hand' => 2,
            ],
            [
                'tenant_id' => $tenant->id,
                'location_id' => $fixture['location_id'],
                'product_id' => $thirdProductId,
                'qty_on_hand' => 1,
            ],
        ]);

        $saleId = (string) Str::uuid();
        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 42000,
            'discount' => 0,
            'tax' => 0,
            'total' => 42000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'void_approved_by' => null,
            'void_reason' => null,
            'voided_at' => null,
            'created_at' => $todayAt,
            'transaction_date' => '2026-02-27',
        ]);

        DB::table('sale_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty' => 3,
                'price' => 10000,
                'discount' => 0,
                'hpp_snapshot' => 5000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleId,
                'product_id' => $secondProductId,
                'product_variant_id' => null,
                'qty' => 1,
                'price' => 12000,
                'discount' => 0,
                'hpp_snapshot' => 6000,
            ],
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/pos/dashboard?top_limit=3&low_stock_limit=2');

        $response->assertOk()
            ->assertJsonPath('data.orders_today', 1)
            ->assertJsonPath('data.sales_today', 42000)
            ->assertJsonPath('data.top_products.0.name', 'Roti Coklat')
            ->assertJsonPath('data.top_products.0.qty', 3)
            ->assertJsonPath('data.low_stock_items.0.name', 'Roti Manis')
            ->assertJsonPath('data.low_stock_items.0.qty_on_hand', 1)
            ->assertJsonPath('data.low_stock_items.1.name', 'Roti Keju')
            ->assertJsonPath('data.low_stock_items.1.qty_on_hand', 2);
    }
}
