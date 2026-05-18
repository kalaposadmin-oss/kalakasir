<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class InventoryBootstrapInventoryPayloadTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_bootstrap_includes_inventory_payload_for_inventory_page(): void
    {
        [$tenant, $user] = $this->bootApiActor('warehouse', 'warehouse-inventory-bootstrap@example.test', 'Warehouse Inventory');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/bootstrap?include=inventory');

        $response->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonCount(1, 'inventory')
            ->assertJsonPath('products.0.id', $fixture['product_id'])
            ->assertJsonPath('products.0.track_stock', true)
            ->assertJsonPath('inventory.0.product_id', $fixture['product_id'])
            ->assertJsonPath('inventory.0.location_id', $fixture['location_id'])
            ->assertJsonPath('inventory.0.qty_on_hand', 100);
    }
}