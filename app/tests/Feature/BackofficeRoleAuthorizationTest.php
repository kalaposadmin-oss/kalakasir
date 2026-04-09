<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class BackofficeRoleAuthorizationTest extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    public function test_cashier_cannot_access_inventory_report_api(): void
    {
        [$tenant] = $this->bootApiActor('cashier', 'cashier-backoffice-report@example.com', 'Cashier Backoffice');
        $tenant->forceFill(['plan' => 'P'])->save();

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/reports/inventory/summary?preset=today')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_warehouse_can_access_inventory_report_api(): void
    {
        [$tenant] = $this->bootApiActor('warehouse', 'warehouse-backoffice-report@example.com', 'Warehouse Backoffice');
        $tenant->forceFill(['plan' => 'P'])->save();

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/reports/inventory/summary?preset=today')
            ->assertOk();
    }

    public function test_cashier_cannot_access_inventory_alerts_and_stock_card_api(): void
    {
        [$tenant] = $this->bootApiActor('cashier', 'cashier-inventory-read-blocked@example.com', 'Cashier Inventory Read');
        $tenant->forceFill(['plan' => 'P'])->save();

        $headers = $this->tenantHeaders($tenant->id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/inventory-alerts')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->withHeaders($headers)
            ->getJson('/api/v1/stock-card')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_warehouse_can_access_inventory_alerts_and_stock_card_api(): void
    {
        [$tenant] = $this->bootApiActor('warehouse', 'warehouse-inventory-read@example.com', 'Warehouse Inventory Read');
        $tenant->forceFill(['plan' => 'P'])->save();

        $headers = $this->tenantHeaders($tenant->id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/inventory-alerts')
            ->assertOk();

        $this->withHeaders($headers)
            ->getJson('/api/v1/stock-card')
            ->assertOk();
    }

    public function test_cashier_cannot_access_stock_transfer_read_api(): void
    {
        [$tenant] = $this->bootApiActor('cashier', 'cashier-transfer-read@example.com', 'Cashier Transfer');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/stock-transfers')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_warehouse_can_access_stock_transfer_read_api(): void
    {
        [$tenant] = $this->bootApiActor('warehouse', 'warehouse-transfer-read@example.com', 'Warehouse Transfer');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/stock-transfers')
            ->assertOk();
    }

    public function test_warehouse_cannot_mutate_stock_adjustment_or_transfer(): void
    {
        [$tenant] = $this->bootApiActor('warehouse', 'warehouse-mutate-blocked@example.com', 'Warehouse Mutate');

        $headers = $this->tenantHeaders($tenant->id);

        $this->withHeaders($headers)
            ->postJson('/api/v1/stock-adjustments', [
                'location_id' => 'test-location',
                'reason' => 'Test',
                'items' => [],
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->withHeaders($headers)
            ->postJson('/api/v1/stock-transfers', [
                'from_branch_id' => 'from-branch',
                'to_branch_id' => 'to-branch',
                'items' => [],
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_warehouse_can_access_product_supplier_and_purchase_read_api(): void
    {
        [$tenant] = $this->bootApiActor('warehouse', 'warehouse-shared-read@example.com', 'Warehouse Shared Read');

        $headers = $this->tenantHeaders($tenant->id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/products')
            ->assertOk();

        $this->withHeaders($headers)
            ->getJson('/api/v1/suppliers')
            ->assertOk();

        $this->withHeaders($headers)
            ->getJson('/api/v1/purchases')
            ->assertOk();
    }

    public function test_non_admin_roles_cannot_mutate_product_or_purchase_api(): void
    {
        foreach ([
            ['cashier', 'cashier-mutate-product-purchase@example.com'],
            ['warehouse', 'warehouse-mutate-product-purchase@example.com'],
            ['supervisor', 'supervisor-mutate-product-purchase@example.com'],
        ] as [$role, $email]) {
            [$tenant] = $this->bootApiActor($role, $email, ucfirst($role).' Mutate');

            $headers = $this->tenantHeaders($tenant->id);

            $this->withHeaders($headers)
                ->postJson('/api/v1/products', [
                    'name' => 'Produk Uji',
                    'sku' => 'SKU-UJI',
                    'price' => 10000,
                ])
                ->assertStatus(403)
                ->assertJsonPath('code', 'FORBIDDEN');

            $this->withHeaders($headers)
                ->postJson('/api/v1/purchases', [
                    'supplier_id' => 'supplier-test',
                    'items' => [],
                ])
                ->assertStatus(403)
                ->assertJsonPath('code', 'FORBIDDEN');
        }
    }

    public function test_owner_can_mutate_product_api(): void
    {
        [$tenant] = $this->bootApiActor('owner', 'owner-mutate-product@example.com', 'Owner Mutate');

        $headers = $this->tenantHeaders($tenant->id);

        $this->withHeaders($headers)
            ->postJson('/api/v1/products', [
                'name' => 'Produk Uji Owner',
                'sku' => 'SKU-UJI-OWNER',
                'price' => 10000,
            ])
            ->assertCreated();
    }

    public function test_warehouse_cannot_access_admin_only_support_read_api(): void
    {
        [$tenant] = $this->bootApiActor('warehouse', 'warehouse-admin-read-blocked@example.com', 'Warehouse Admin Read');

        $headers = $this->tenantHeaders($tenant->id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/users')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->withHeaders($headers)
            ->getJson('/api/v1/chart-of-accounts')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->withHeaders($headers)
            ->getJson('/api/v1/finance-control-daily?date=2026-02-27')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->withHeaders($headers)
            ->get('/api/v1/finance-control-daily/export?from=2026-02-27&to=2026-02-27')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_admin_can_access_admin_only_support_read_api(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin-support-read@example.com', 'Admin Support Read');

        $headers = $this->tenantHeaders($tenant->id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/users')
            ->assertOk();

        $this->withHeaders($headers)
            ->getJson('/api/v1/chart-of-accounts')
            ->assertOk();

        $this->withHeaders($headers)
            ->getJson('/api/v1/finance-control-daily?date=2026-02-27')
            ->assertOk();
    }
}
