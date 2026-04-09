<?php

namespace Tests\Feature;

use App\Services\InventoryValuationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class InventoryValueAuthorityTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    public function test_inventory_summary_uses_average_cost_state_instead_of_product_hpp_average(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'inventory-average-authority@example.com', 'Admin Inventory Average');
        \DB::table('tenants')->where('id', $tenant->id)->update([
            'plan' => 'P',
            'inventory_valuation_method' => 'average',
        ]);
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        \DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('id', $fixture['product_id'])
            ->update(['hpp_average' => 9999]);

        \DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => 100,
            'average_cost' => 1234.56,
            'last_cost' => 1234.56,
            'updated_at' => CarbonImmutable::parse('2026-02-27 09:00:00', 'UTC'),
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/reports/inventory/summary?preset=custom&start_date=2026-02-27&end_date=2026-02-27');

        $response->assertOk()
            ->assertJsonPath('total_value', 123456);
    }

    public function test_inventory_summary_sums_fifo_layers_across_variant_buckets_without_fallback_cost(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'inventory-fifo-authority@example.com', 'Admin Inventory FIFO');
        \DB::table('tenants')->where('id', $tenant->id)->update([
            'plan' => 'P',
            'inventory_valuation_method' => 'fifo',
        ]);
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $variantA = (string) Str::uuid();
        $variantB = (string) Str::uuid();

        \DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('id', $fixture['product_id'])
            ->update(['hpp_average' => 9999]);

        \DB::table('product_variants')->insert([
            [
                'id' => $variantA,
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'sku' => 'SKU-001-VAR-A',
                'barcode' => null,
                'name' => 'Varian A',
                'attributes' => json_encode(['name' => 'A']),
                'price_override' => null,
                'is_active' => true,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ],
            [
                'id' => $variantB,
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'sku' => 'SKU-001-VAR-B',
                'barcode' => null,
                'name' => 'Varian B',
                'attributes' => json_encode(['name' => 'B']),
                'price_override' => null,
                'is_active' => true,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ],
        ]);

        $valuation = app(InventoryValuationService::class);
        $valuation->receive(
            tenantId: $tenant->id,
            productId: $fixture['product_id'],
            productVariantId: $variantA,
            locationId: $fixture['location_id'],
            qty: 3,
            unitCost: 1000,
            sourceType: 'seed',
            sourceId: (string) Str::uuid(),
            sourceItemId: null,
            at: CarbonImmutable::parse('2026-02-26 08:00:00', 'UTC'),
        );
        $valuation->receive(
            tenantId: $tenant->id,
            productId: $fixture['product_id'],
            productVariantId: $variantB,
            locationId: $fixture['location_id'],
            qty: 2,
            unitCost: 2500,
            sourceType: 'seed',
            sourceId: (string) Str::uuid(),
            sourceItemId: null,
            at: CarbonImmutable::parse('2026-02-26 09:00:00', 'UTC'),
        );

        $this->assertSame(8000.0, $valuation->inventoryValueForScope(
            tenantId: $tenant->id,
            locationId: $fixture['location_id']
        ));

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson(sprintf(
                '/api/v1/reports/inventory/summary?preset=custom&start_date=2026-02-27&end_date=2026-02-27&location_id=%s',
                $fixture['location_id']
            ));

        $response->assertOk()
            ->assertJsonPath('total_value', 8000);
    }
}
