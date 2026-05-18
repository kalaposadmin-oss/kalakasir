<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class InventoryOperationalReportsTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    public function test_inventory_summary_uses_same_period_for_movement_and_audit_sections(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'inventory-summary@example.com', 'Admin Inventory');
        $tenant->forceFill(['plan' => 'P'])->save();

        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $insideAt = CarbonImmutable::parse('2026-02-27 09:00:00', 'UTC');
        $outsideAt = CarbonImmutable::parse('2026-02-20 09:00:00', 'UTC');

        \DB::table('stock_ledger')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'location_id' => $fixture['location_id'],
                'reference_type' => 'purchase_receive',
                'reference_id' => (string) Str::uuid(),
                'reference_item_id' => null,
                'qty_in' => 10,
                'qty_out' => 0,
                'balance_after' => 110,
                'created_at' => $insideAt,
                'created_by' => $user->id,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'location_id' => $fixture['location_id'],
                'reference_type' => 'sale',
                'reference_id' => (string) Str::uuid(),
                'reference_item_id' => null,
                'qty_in' => 0,
                'qty_out' => 4,
                'balance_after' => 106,
                'created_at' => $insideAt->addHour(),
                'created_by' => $user->id,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'location_id' => $fixture['location_id'],
                'reference_type' => 'stock_adjustment',
                'reference_id' => (string) Str::uuid(),
                'reference_item_id' => null,
                'qty_in' => 0,
                'qty_out' => 2,
                'balance_after' => 104,
                'created_at' => $outsideAt,
                'created_by' => $user->id,
            ],
        ]);

        \DB::table('anomalies')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'severity' => 'high',
                'type' => 'negative_stock_attempt',
                'reference_id' => (string) Str::uuid(),
                'payload_json' => json_encode(['source' => 'inventory-test']),
                'detected_at' => $insideAt,
                'resolved_at' => null,
                'resolved_by' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'severity' => 'low',
                'type' => 'invalid_transaction_date_attempt',
                'reference_id' => (string) Str::uuid(),
                'payload_json' => json_encode(['source' => 'inventory-test']),
                'detected_at' => $outsideAt,
                'resolved_at' => null,
                'resolved_by' => null,
            ],
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/reports/inventory/summary?preset=custom&start_date=2026-02-27&end_date=2026-02-27');

        $response->assertOk()
            ->assertJsonPath('movement_range.in', 10)
            ->assertJsonPath('movement_range.out', 4)
            ->assertJsonPath('movement_range.net', 6)
            ->assertJsonCount(2, 'recent_movements')
            ->assertJsonCount(1, 'audit_trail')
            ->assertJsonPath('audit_trail.0.event_type', 'anomaly_detected')
            ->assertJsonPath('audit_trail.0.entity_type', 'anomaly');
    }

    public function test_stock_card_summary_respects_range_and_type_filter(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'stock-card-summary@example.com', 'Admin Stock Card');
        $tenant->forceFill(['plan' => 'P'])->save();
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        \DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('id', $fixture['product_id'])
            ->update(['unit' => 'kg']);
        $insideAt = CarbonImmutable::parse('2026-02-27 10:00:00', 'UTC');

        \DB::table('stock_ledger')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'location_id' => $fixture['location_id'],
                'reference_type' => 'purchase_receive',
                'reference_id' => (string) Str::uuid(),
                'reference_item_id' => null,
                'qty_in' => 12,
                'qty_out' => 0,
                'balance_after' => 112,
                'created_at' => $insideAt,
                'created_by' => $user->id,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'location_id' => $fixture['location_id'],
                'reference_type' => 'sale',
                'reference_id' => (string) Str::uuid(),
                'reference_item_id' => null,
                'qty_in' => 0,
                'qty_out' => 3,
                'balance_after' => 109,
                'created_at' => $insideAt->addHour(),
                'created_by' => $user->id,
            ],
        ]);

        $secondaryLocationId = (string) Str::uuid();
        $variantA = (string) Str::uuid();

        \DB::table('locations')->insert([
            'id' => $secondaryLocationId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'name' => 'Gudang Satelit',
            'is_default' => false,
        ]);

        \DB::table('product_variants')->insert([
            'id' => $variantA,
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'sku' => 'SKU-VAR-A',
            'barcode' => null,
            'name' => 'Varian A',
            'attributes' => json_encode(['warna' => 'merah']),
            'price_override' => null,
            'is_active' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        \DB::table('inventory_balances')->insert([
            [
                'tenant_id' => $tenant->id,
                'location_id' => $secondaryLocationId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty_on_hand' => 15,
            ],
            [
                'tenant_id' => $tenant->id,
                'location_id' => $fixture['location_id'],
                'product_id' => $fixture['product_id'],
                'product_variant_id' => $variantA,
                'qty_on_hand' => 7,
            ],
        ]);

        \DB::table('inventory_cost_states')->updateOrInsert([
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'location_id' => $fixture['location_id'],
            'product_variant_id' => null,
        ], [
            'id' => (string) Str::uuid(),
            'qty_on_hand' => 109,
            'average_cost' => 1234.56,
            'last_cost' => 1500,
            'updated_at' => now('UTC'),
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson(sprintf(
                '/api/v1/stock-card?product_id=%s&location_id=%s&preset=custom&start_date=2026-02-27&end_date=2026-02-27&type=sale',
                $fixture['product_id'],
                $fixture['location_id']
            ));

        $response->assertOk()
            ->assertJsonPath('product.unit', 'kg')
            ->assertJsonCount(2, 'product_location_balances')
            ->assertJsonCount(2, 'product_variant_balances')
            ->assertJsonPath('cost_state.average_cost', 1234.56)
            ->assertJsonPath('cost_state.stock_value', 134567.04)
            ->assertJsonPath('summary.total_in', 0)
            ->assertJsonPath('summary.total_out', 3)
            ->assertJsonPath('summary.movement_count', 1)
            ->assertJsonCount(1, 'ledger')
            ->assertJsonPath('ledger.0.reference_type', 'sale');

        $this->assertSame('Gudang Satelit', $response->json('product_location_balances.1.name'));
        $this->assertSame('Varian A', $response->json('product_variant_balances.1.name'));
        $this->assertNotEmpty($response->json('last_cost_source.reference_id'));
    }

    public function test_inventory_summary_respects_location_filter_for_summary_recent_movements_and_export(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'inventory-location-summary@example.com', 'Admin Inventory Location');
        $tenant->forceFill(['plan' => 'P'])->save();

        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $insideAt = CarbonImmutable::parse('2026-02-27 09:00:00', 'UTC');
        $secondaryLocationId = (string) Str::uuid();
        $secondaryProductId = (string) Str::uuid();

        \DB::table('locations')->insert([
            'id' => $secondaryLocationId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'name' => 'Outlet 2',
            'is_default' => false,
        ]);

        \DB::table('products')->insert([
            'id' => $secondaryProductId,
            'tenant_id' => $tenant->id,
            'sku' => 'SKU-002',
            'name' => 'Produk B',
            'price' => 12000,
            'hpp_average' => 6000,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => CarbonImmutable::now('UTC'),
            'updated_at' => CarbonImmutable::now('UTC'),
            'deleted_at' => null,
        ]);

        \DB::table('inventory_balances')->insert([
            [
                'tenant_id' => $tenant->id,
                'location_id' => $secondaryLocationId,
                'product_id' => $secondaryProductId,
                'qty_on_hand' => 40,
            ],
        ]);

        \DB::table('stock_ledger')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'location_id' => $fixture['location_id'],
                'reference_type' => 'purchase_receive',
                'reference_id' => (string) Str::uuid(),
                'reference_item_id' => null,
                'qty_in' => 10,
                'qty_out' => 0,
                'balance_after' => 110,
                'created_at' => $insideAt,
                'created_by' => $user->id,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'product_id' => $secondaryProductId,
                'product_variant_id' => null,
                'location_id' => $secondaryLocationId,
                'reference_type' => 'sale',
                'reference_id' => (string) Str::uuid(),
                'reference_item_id' => null,
                'qty_in' => 0,
                'qty_out' => 7,
                'balance_after' => 33,
                'created_at' => $insideAt->addHour(),
                'created_by' => $user->id,
            ],
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson(sprintf(
                '/api/v1/reports/inventory/summary?preset=custom&start_date=2026-02-27&end_date=2026-02-27&location_id=%s',
                $fixture['location_id']
            ));

        $response->assertOk()
            ->assertJsonPath('location_id', $fixture['location_id'])
            ->assertJsonPath('sku_count', 1)
            ->assertJsonPath('movement_range.in', 10)
            ->assertJsonPath('movement_range.out', 0)
            ->assertJsonCount(1, 'recent_movements')
            ->assertJsonPath('recent_movements.0.location_name', 'Main Warehouse');

        $exportResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->get(sprintf(
                '/api/v1/reports/inventory/export/csv?preset=custom&start_date=2026-02-27&end_date=2026-02-27&location_id=%s',
                $fixture['location_id']
            ));

        $exportResponse->assertOk();
        $csv = $exportResponse->streamedContent();
        $this->assertStringContainsString("SKU,1", $csv);
        $this->assertStringContainsString("\"Masuk 7 Hari\",10", $csv);
        $this->assertStringContainsString("\"Keluar 7 Hari\",0", $csv);
    }

    public function test_inventory_reports_exclude_foreign_tenant_inventory_movements_and_exports(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'inventory-tenant-scope@example.com', 'Admin Inventory Scope');
        $tenant->forceFill(['plan' => 'P'])->save();

        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        \DB::table('products')
            ->where('id', $fixture['product_id'])
            ->update([
                'name' => 'Produk Tenant Aktif',
                'sku' => 'SKU-AKTIF',
                'min_stock' => 10,
            ]);

        \DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $fixture['location_id'])
            ->where('product_id', $fixture['product_id'])
            ->update([
                'qty_on_hand' => 4,
            ]);

        $insideAt = CarbonImmutable::parse('2026-02-27 09:00:00', 'UTC');
        $activeAnomalyId = (string) Str::uuid();

        \DB::table('stock_ledger')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'purchase_receive',
            'reference_id' => (string) Str::uuid(),
            'reference_item_id' => null,
            'qty_in' => 6,
            'qty_out' => 0,
            'balance_after' => 10,
            'created_at' => $insideAt,
            'created_by' => $user->id,
        ]);

        \DB::table('anomalies')->insert([
            'id' => $activeAnomalyId,
            'tenant_id' => $tenant->id,
            'severity' => 'medium',
            'type' => 'negative_stock_attempt',
            'reference_id' => (string) Str::uuid(),
            'payload_json' => json_encode([
                'source' => 'tenant-active-source',
                'product_id' => $fixture['product_id'],
                'location_id' => $fixture['location_id'],
                'requested_qty' => 2,
            ]),
            'detected_at' => $insideAt->addMinutes(30),
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        [$otherTenant, $otherUser] = $this->bootApiActor('admin', 'inventory-tenant-scope-other@example.com', 'Admin Inventory Scope Other');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);
        $this->actingAs($user, 'sanctum');

        \DB::table('products')
            ->where('id', $otherFixture['product_id'])
            ->update([
                'name' => 'Produk Tenant Lain',
                'sku' => 'SKU-LAWAN',
                'min_stock' => 50,
            ]);

        \DB::table('inventory_balances')
            ->where('tenant_id', $otherTenant->id)
            ->where('location_id', $otherFixture['location_id'])
            ->where('product_id', $otherFixture['product_id'])
            ->update([
                'qty_on_hand' => 1,
            ]);

        \DB::table('stock_ledger')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'product_id' => $otherFixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $otherFixture['location_id'],
            'reference_type' => 'sale',
            'reference_id' => (string) Str::uuid(),
            'reference_item_id' => null,
            'qty_in' => 0,
            'qty_out' => 99,
            'balance_after' => 1,
            'created_at' => $insideAt->addHour(),
            'created_by' => $otherUser->id,
        ]);

        \DB::table('anomalies')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'severity' => 'high',
            'type' => 'negative_stock_attempt',
            'reference_id' => (string) Str::uuid(),
            'payload_json' => json_encode([
                'source' => 'tenant-foreign-source',
                'product_id' => $otherFixture['product_id'],
                'location_id' => $otherFixture['location_id'],
                'requested_qty' => 99,
            ]),
            'detected_at' => $insideAt->addMinutes(45),
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        $summaryResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/reports/inventory/summary?preset=custom&start_date=2026-02-27&end_date=2026-02-27');

        $summaryResponse->assertOk()
            ->assertJsonPath('sku_count', 1)
            ->assertJsonPath('low_stock_count', 1)
            ->assertJsonPath('movement_range.in', 6)
            ->assertJsonPath('movement_range.out', 0)
            ->assertJsonCount(1, 'recent_movements')
            ->assertJsonPath('recent_movements.0.product_name', 'Produk Tenant Aktif')
            ->assertJsonCount(1, 'audit_trail')
            ->assertJsonPath('audit_trail.0.event_type', 'anomaly_detected')
            ->assertJsonPath('low_stock_items.0.name', 'Produk Tenant Aktif');

        $this->assertStringContainsString('tenant-active-source', (string) $summaryResponse->json('audit_trail.0.summary'));
        $this->assertStringNotContainsString('Produk Tenant Lain', json_encode($summaryResponse->json(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('tenant-foreign-source', json_encode($summaryResponse->json(), JSON_THROW_ON_ERROR));

        $lowStockResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/reports/inventory/low-stock?limit=10');

        $lowStockResponse->assertOk()
            ->assertJsonPath('data.items.0.name', 'Produk Tenant Aktif')
            ->assertJsonCount(1, 'data.items');

        $exportResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->get('/api/v1/reports/inventory/export/csv?preset=custom&start_date=2026-02-27&end_date=2026-02-27');

        $exportResponse->assertOk();
        $csv = $exportResponse->streamedContent();

        $this->assertStringContainsString("SKU,1", $csv);
        $this->assertStringContainsString("\"Masuk 7 Hari\",6", $csv);
        $this->assertStringContainsString('Produk Tenant Aktif', $csv);
        $this->assertStringNotContainsString('Produk Tenant Lain', $csv);
    }

    public function test_stock_card_summary_and_ledger_respect_location_filter(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'stock-card-location@example.com', 'Admin Stock Card Location');
        $tenant->forceFill(['plan' => 'P'])->save();
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $insideAt = CarbonImmutable::parse('2026-02-27 10:00:00', 'UTC');
        $secondaryLocationId = (string) Str::uuid();

        \DB::table('locations')->insert([
            'id' => $secondaryLocationId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'name' => 'Gudang Satelit',
            'is_default' => false,
        ]);

        \DB::table('inventory_balances')->insert([
            'tenant_id' => $tenant->id,
            'location_id' => $secondaryLocationId,
            'product_id' => $fixture['product_id'],
            'qty_on_hand' => 15,
        ]);

        \DB::table('stock_ledger')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'location_id' => $fixture['location_id'],
                'reference_type' => 'purchase_receive',
                'reference_id' => (string) Str::uuid(),
                'reference_item_id' => null,
                'qty_in' => 9,
                'qty_out' => 0,
                'balance_after' => 109,
                'created_at' => $insideAt,
                'created_by' => $user->id,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'location_id' => $secondaryLocationId,
                'reference_type' => 'sale',
                'reference_id' => (string) Str::uuid(),
                'reference_item_id' => null,
                'qty_in' => 0,
                'qty_out' => 5,
                'balance_after' => 10,
                'created_at' => $insideAt->addHour(),
                'created_by' => $user->id,
            ],
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson(sprintf(
                '/api/v1/stock-card?product_id=%s&location_id=%s&preset=custom&start_date=2026-02-27&end_date=2026-02-27',
                $fixture['product_id'],
                $secondaryLocationId
            ));

        $response->assertOk()
            ->assertJsonPath('location.id', $secondaryLocationId)
            ->assertJsonPath('location.name', 'Gudang Satelit')
            ->assertJsonPath('balance', 15)
            ->assertJsonPath('summary.total_in', 0)
            ->assertJsonPath('summary.total_out', 5)
            ->assertJsonPath('summary.movement_count', 1)
            ->assertJsonCount(1, 'ledger')
            ->assertJsonPath('ledger.0.location_id', $secondaryLocationId)
            ->assertJsonPath('ledger.0.reference_type', 'sale');
    }

    public function test_stock_card_summary_and_ledger_respect_variant_filter(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'stock-card-variant@example.com', 'Admin Stock Card Variant');
        $tenant->forceFill(['plan' => 'P'])->save();
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $variantA = (string) Str::uuid();
        $variantB = (string) Str::uuid();
        $insideAt = CarbonImmutable::parse('2026-02-27 10:00:00', 'UTC');

        \DB::table('product_variants')->insert([
            [
                'id' => $variantA,
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'sku' => 'SKU-VAR-A',
                'barcode' => null,
                'name' => 'Varian A',
                'attributes' => json_encode(['warna' => 'merah']),
                'price_override' => null,
                'is_active' => true,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ],
            [
                'id' => $variantB,
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'sku' => 'SKU-VAR-B',
                'barcode' => null,
                'name' => 'Varian B',
                'attributes' => json_encode(['warna' => 'biru']),
                'price_override' => null,
                'is_active' => true,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ],
        ]);

        \DB::table('inventory_balances')->insert([
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['product_id'],
            'product_variant_id' => $variantA,
            'qty_on_hand' => 13,
        ]);

        \DB::table('stock_ledger')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => $variantA,
                'location_id' => $fixture['location_id'],
                'reference_type' => 'sale',
                'reference_id' => (string) Str::uuid(),
                'reference_item_id' => null,
                'qty_in' => 0,
                'qty_out' => 2,
                'balance_after' => 13,
                'created_at' => $insideAt,
                'created_by' => $user->id,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => $variantB,
                'location_id' => $fixture['location_id'],
                'reference_type' => 'sale',
                'reference_id' => (string) Str::uuid(),
                'reference_item_id' => null,
                'qty_in' => 0,
                'qty_out' => 5,
                'balance_after' => 21,
                'created_at' => $insideAt->addHour(),
                'created_by' => $user->id,
            ],
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson(sprintf(
                '/api/v1/stock-card?product_id=%s&product_variant_id=%s&location_id=%s&preset=custom&start_date=2026-02-27&end_date=2026-02-27&type=sale',
                $fixture['product_id'],
                $variantA,
                $fixture['location_id']
            ));

        $response->assertOk()
            ->assertJsonPath('selected_variant_id', $variantA)
            ->assertJsonPath('product_variants.0.id', $variantA)
            ->assertJsonPath('balance', 13)
            ->assertJsonPath('summary.total_in', 0)
            ->assertJsonPath('summary.total_out', 2)
            ->assertJsonPath('summary.movement_count', 1)
            ->assertJsonCount(1, 'ledger')
            ->assertJsonPath('ledger.0.product_variant_id', $variantA)
            ->assertJsonPath('ledger.0.reference_type', 'sale');
    }

    public function test_inventory_summary_returns_normalized_operational_audit_trail(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'inventory-audit@example.com', 'Admin Audit');
        $tenant->forceFill(['plan' => 'P'])->save();
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $managerId = (string) Str::uuid();
        \DB::table('users')->insert([
            'id' => $managerId,
            'tenant_id' => $tenant->id,
            'name' => 'Supervisor Audit',
            'email' => 'supervisor-audit@example.com',
            'password' => Hash::make('secret123'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => CarbonImmutable::now('UTC'),
            'updated_at' => CarbonImmutable::now('UTC'),
            'deleted_at' => null,
        ]);

        $periodAt = CarbonImmutable::parse('2026-02-27 11:00:00', 'UTC');
        $toBranchId = (string) Str::uuid();
        $saleId = (string) Str::uuid();
        $refundId = (string) Str::uuid();
        $purchaseId = (string) Str::uuid();
        $transferId = (string) Str::uuid();
        $adjustmentId = (string) Str::uuid();
        $supplierId = (string) Str::uuid();
        $secondaryProductId = (string) Str::uuid();

        \DB::table('branches')->insert([
            'id' => $toBranchId,
            'tenant_id' => $tenant->id,
            'name' => 'Cabang Timur',
        ]);

        \DB::table('products')->insert([
            'id' => $secondaryProductId,
            'tenant_id' => $tenant->id,
            'sku' => 'SKU-AUDIT-02',
            'name' => 'Produk Audit B',
            'price' => 18000,
            'hpp_average' => 9000,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => CarbonImmutable::now('UTC'),
            'updated_at' => CarbonImmutable::now('UTC'),
            'deleted_at' => null,
        ]);

        \DB::table('stock_adjustments')->insert([
            'id' => $adjustmentId,
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'location_id' => $fixture['location_id'],
            'reference_no' => 'ADJ-001',
            'reason_code' => 'OPNAME',
            'status' => 'posted',
            'reason' => 'Opname rutin',
            'notes' => 'Selisih fisik ditemukan',
            'qty' => 2,
            'type' => 'out',
            'balance_before' => 100,
            'balance_after' => 98,
            'created_by' => $user->id,
            'created_at' => $periodAt->subHour(),
            'approved_by' => $managerId,
            'approved_at' => $periodAt,
            'posted_at' => $periodAt->addMinutes(15),
            'cancelled_by' => null,
            'cancelled_at' => null,
        ]);

        \DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $tenant->id,
            'name' => 'Supplier Audit',
            'balance' => 0,
            'created_at' => CarbonImmutable::now('UTC'),
        ]);

        \DB::table('purchases')->insert([
            'id' => $purchaseId,
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplierId,
            'location_id' => $fixture['location_id'],
            'total' => 120000,
            'status' => 'received',
            'notes' => 'PO audit',
            'payment_method' => 'tempo',
            'due_date' => null,
            'created_at' => $periodAt->subDays(1),
            'approved_at' => $periodAt->subMinutes(30),
            'approved_by' => $managerId,
            'received_at' => $periodAt->addMinutes(10),
            'received_by' => $user->id,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ]);

        \DB::table('purchase_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'purchase_id' => $purchaseId,
            'product_id' => $secondaryProductId,
            'qty' => 8,
            'price' => 15000,
        ]);

        \DB::table('stock_transfers')->insert([
            'id' => $transferId,
            'tenant_id' => $tenant->id,
            'from_branch_id' => $fixture['branch_id'],
            'to_branch_id' => $toBranchId,
            'from_location_id' => $fixture['location_id'],
            'to_location_id' => $fixture['location_id'],
            'status' => 'received',
            'reference' => 'TRF-001',
            'created_by' => $user->id,
            'created_at' => $periodAt->subHours(2),
            'approved_at' => $periodAt->subMinutes(45),
            'approved_by' => $managerId,
            'shipped_at' => $periodAt->subMinutes(15),
            'shipped_by' => $user->id,
            'received_at' => $periodAt->addMinutes(20),
            'received_by' => $managerId,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ]);

        \DB::table('stock_transfer_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'stock_transfer_id' => $transferId,
            'product_id' => $secondaryProductId,
            'qty' => 5,
        ]);

        \DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'customer_id' => null,
            'subtotal' => 30000,
            'discount' => 0,
            'tax' => 0,
            'total' => 30000,
            'status' => 'voided',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => $user->id,
            'void_approved_by' => $managerId,
            'void_reason' => 'Barang salah input',
            'voided_at' => $periodAt->addMinutes(30),
            'created_at' => $periodAt->subHours(3),
        ]);

        \DB::table('sale_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $secondaryProductId,
            'qty' => 2,
            'price' => 15000,
            'discount' => 0,
            'hpp_snapshot' => 9000,
        ]);

        \DB::table('refunds')->insert([
            'id' => $refundId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'branch_id' => $fixture['branch_id'],
            'created_by' => $managerId,
            'total' => 15000,
            'status' => 'completed',
            'reason' => 'Retur pelanggan',
            'created_at' => $periodAt->addMinutes(40),
        ]);

        \DB::table('refund_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'refund_id' => $refundId,
            'product_id' => $secondaryProductId,
            'qty' => 1,
            'amount' => 15000,
        ]);

        \DB::table('stock_adjustment_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'stock_adjustment_id' => $adjustmentId,
            'product_id' => $secondaryProductId,
            'system_qty' => 10,
            'physical_qty' => 8,
            'difference_qty' => -2,
            'unit_cost_snapshot' => 9000,
        ]);

        \DB::table('anomalies')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'severity' => 'high',
            'type' => 'negative_stock_attempt',
            'reference_id' => $saleId,
            'payload_json' => json_encode([
                'source' => 'audit-test',
                'product_id' => $secondaryProductId,
                'location_id' => $fixture['location_id'],
                'requested_qty' => 3,
            ]),
            'detected_at' => $periodAt->addMinutes(50),
            'resolved_at' => $periodAt->addMinutes(55),
            'resolved_by' => $managerId,
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/reports/inventory/summary?preset=custom&start_date=2026-02-27&end_date=2026-02-27');

        $response->assertOk();
        $auditTrail = collect($response->json('audit_trail'));

        $this->assertTrue($auditTrail->contains(fn (array $row) => ($row['event_type'] ?? null) === 'stock_adjustment_posted'
            && ($row['actor_name'] ?? null) === 'Supervisor Audit'
            && str_contains((string) ($row['summary'] ?? ''), 'Produk Audit B')
            && str_contains((string) ($row['summary'] ?? ''), 'Selisih -2')));
        $this->assertTrue($auditTrail->contains(fn (array $row) => ($row['event_type'] ?? null) === 'purchase_received'
            && ($row['title'] ?? null) === 'Pembelian diterima'
            && str_contains((string) ($row['summary'] ?? ''), '1 item')
            && str_contains((string) ($row['summary'] ?? ''), 'Qty 8')
            && str_contains((string) ($row['summary'] ?? ''), 'Produk Audit B')));
        $this->assertTrue($auditTrail->contains(fn (array $row) => ($row['event_type'] ?? null) === 'stock_transfer_received'
            && ($row['entity_label'] ?? null) === 'Transfer TRF-001'
            && str_contains((string) ($row['summary'] ?? ''), 'Qty 5')
            && str_contains((string) ($row['summary'] ?? ''), 'Produk Audit B')));
        $this->assertTrue($auditTrail->contains(fn (array $row) => ($row['event_type'] ?? null) === 'sale_voided'
            && str_contains((string) ($row['summary'] ?? ''), 'Total Rp30.000')
            && str_contains((string) ($row['summary'] ?? ''), 'Produk Audit B')));
        $this->assertTrue($auditTrail->contains(fn (array $row) => ($row['event_type'] ?? null) === 'refund_created'
            && ($row['actor_name'] ?? null) === 'Supervisor Audit'
            && str_contains((string) ($row['summary'] ?? ''), 'Total Rp15.000')
            && str_contains((string) ($row['summary'] ?? ''), 'Sale '.substr($saleId, 0, 8))));
        $this->assertTrue($auditTrail->contains(fn (array $row) => ($row['event_type'] ?? null) === 'anomaly_detected'
            && ($row['severity'] ?? null) === 'high'
            && str_contains((string) ($row['summary'] ?? ''), 'Produk Produk Audit B')
            && str_contains((string) ($row['summary'] ?? ''), 'Qty 3')
            && str_contains((string) ($row['summary'] ?? ''), 'Sumber audit-test')));
        $this->assertTrue($auditTrail->contains(fn (array $row) => ($row['event_type'] ?? null) === 'anomaly_resolved'
            && str_contains((string) ($row['summary'] ?? ''), 'Produk Produk Audit B')));
    }

    public function test_inventory_summary_enriches_production_batch_anomaly_audit_trail(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'inventory-production-batch-audit@example.com', 'Admin Produksi');
        $tenant->forceFill(['plan' => 'P'])->save();

        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $periodAt = CarbonImmutable::parse('2026-02-27 13:00:00', 'UTC');
        $batchId = (string) Str::uuid();

        DB::table('production_batches')->insert([
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'recipe_version' => 2,
            'reference_no' => 'PB-AUDIT-001',
            'status' => 'posted',
            'notes' => 'Batch audit produksi',
            'created_by' => $user->id,
            'created_at' => $periodAt->subHour(),
            'updated_at' => $periodAt->subHour(),
            'transaction_date' => '2026-02-27',
            'expected_output_qty' => 10,
            'actual_output_qty' => 12,
            'yield_variance_qty' => 2,
            'yield_variance_pct' => 20,
            'variance_reason' => 'Oven lebih efisien',
            'posted_at' => $periodAt->subMinutes(50),
            'posted_by' => $user->id,
            'idempotency_key' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancel_reason' => null,
        ]);

        DB::table('production_batch_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'production_batch_id' => $batchId,
                'line_type' => 'input',
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty' => 20,
                'planned_qty' => 20,
                'actual_qty' => 20,
                'uom' => 'pcs',
                'unit_cost_snapshot' => 0,
                'created_at' => $periodAt->subHour(),
                'updated_at' => $periodAt->subHour(),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'production_batch_id' => $batchId,
                'line_type' => 'output',
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty' => 10,
                'planned_qty' => 10,
                'actual_qty' => 12,
                'uom' => 'pcs',
                'unit_cost_snapshot' => 0,
                'created_at' => $periodAt->subHour(),
                'updated_at' => $periodAt->subHour(),
            ],
        ]);

        DB::table('anomalies')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'severity' => 'high',
            'type' => 'production_batch_yield_variance',
            'reference_id' => $batchId,
            'payload_json' => json_encode([
                'source' => 'production_batch_post',
                'product_id' => $fixture['product_id'],
                'branch_id' => $fixture['branch_id'],
                'location_id' => $fixture['location_id'],
                'actual_output_qty' => 12,
                'yield_variance_qty' => 2,
                'yield_variance_pct' => 20,
                'variance_reason' => 'Oven lebih efisien',
                'resolved_note' => 'Sudah dicek supervisor',
            ]),
            'detected_at' => $periodAt->subMinutes(40),
            'resolved_at' => $periodAt->subMinutes(20),
            'resolved_by' => $user->id,
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/reports/inventory/summary?preset=custom&start_date=2026-02-27&end_date=2026-02-27&audit_category=anomaly');

        $response->assertOk();
        $auditTrail = collect($response->json('audit_trail'));

        $this->assertTrue($auditTrail->contains(fn (array $row) => ($row['event_type'] ?? null) === 'anomaly_detected'
            && ($row['reference_type'] ?? null) === 'production_batch'
            && ($row['title'] ?? null) === 'Yield variance batch produksi'
            && str_contains((string) ($row['summary'] ?? ''), 'Batch PB-AUDIT-001')
            && str_contains((string) ($row['summary'] ?? ''), 'Cabang')
            && ! str_contains((string) ($row['summary'] ?? ''), 'Resolve Sudah dicek supervisor')));

        $this->assertTrue($auditTrail->contains(fn (array $row) => ($row['event_type'] ?? null) === 'anomaly_resolved'
            && ($row['reference_type'] ?? null) === 'production_batch'
            && str_contains((string) ($row['summary'] ?? ''), 'Batch PB-AUDIT-001')
            && str_contains((string) ($row['summary'] ?? ''), 'Resolve Sudah dicek supervisor')));
    }

    public function test_inventory_summary_filters_audit_trail_by_selected_category(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'inventory-audit-category@example.com', 'Admin Audit Category');
        $tenant->forceFill(['plan' => 'P'])->save();

        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $managerId = $user->id;
        $periodAt = CarbonImmutable::parse('2026-02-27 11:00:00', 'UTC');
        $supplierId = (string) Str::uuid();
        $saleId = (string) Str::uuid();

        \DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $tenant->id,
            'name' => 'Supplier Filter',
            'balance' => 0,
            'created_at' => CarbonImmutable::now('UTC'),
        ]);

        \DB::table('stock_adjustments')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'location_id' => $fixture['location_id'],
            'reference_no' => 'ADJ-FILTER',
            'reason_code' => 'OPNAME',
            'status' => 'posted',
            'reason' => 'Penyesuaian filter',
            'notes' => null,
            'qty' => 1,
            'type' => 'out',
            'balance_before' => 20,
            'balance_after' => 19,
            'created_by' => $user->id,
            'created_at' => $periodAt->subHour(),
            'approved_by' => $managerId,
            'approved_at' => $periodAt->subMinutes(50),
            'posted_at' => $periodAt->subMinutes(45),
            'cancelled_by' => null,
            'cancelled_at' => null,
        ]);

        \DB::table('purchases')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplierId,
            'location_id' => $fixture['location_id'],
            'total' => 55000,
            'status' => 'received',
            'notes' => null,
            'payment_method' => 'cash',
            'due_date' => null,
            'created_at' => $periodAt->subDays(1),
            'approved_at' => $periodAt->subMinutes(40),
            'approved_by' => $managerId,
            'received_at' => $periodAt->subMinutes(30),
            'received_by' => $user->id,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ]);

        \DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'customer_id' => null,
            'subtotal' => 30000,
            'discount' => 0,
            'tax' => 0,
            'total' => 30000,
            'status' => 'voided',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => $user->id,
            'void_approved_by' => $managerId,
            'void_reason' => 'Void filter',
            'voided_at' => $periodAt->subMinutes(20),
            'created_at' => $periodAt->subHours(2),
        ]);

        \DB::table('anomalies')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'severity' => 'high',
            'type' => 'negative_stock_attempt',
            'reference_id' => $saleId,
            'payload_json' => json_encode(['source' => 'category-filter-test']),
            'detected_at' => $periodAt->subMinutes(10),
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        $salesResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/reports/inventory/summary?preset=custom&start_date=2026-02-27&end_date=2026-02-27&audit_category=sales');

        $salesResponse->assertOk()
            ->assertJsonPath('audit_category', 'sales');

        $salesTrail = collect($salesResponse->json('audit_trail'));
        $this->assertTrue($salesTrail->isNotEmpty());
        $this->assertTrue($salesTrail->every(fn (array $row) => ($row['category'] ?? null) === 'sales'));
        $this->assertTrue(collect($salesResponse->json('audit_categories'))->contains(fn (array $row) => ($row['value'] ?? null) === 'sales' && ($row['selected'] ?? false) === true));

        $anomalyResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/reports/inventory/summary?preset=custom&start_date=2026-02-27&end_date=2026-02-27&audit_category=anomaly');

        $anomalyResponse->assertOk()
            ->assertJsonPath('audit_category', 'anomaly')
            ->assertJsonCount(1, 'audit_trail')
            ->assertJsonPath('audit_trail.0.category', 'anomaly')
            ->assertJsonPath('audit_trail.0.event_type', 'anomaly_detected');

        $allResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/reports/inventory/summary?preset=custom&start_date=2026-02-27&end_date=2026-02-27');

        $allResponse->assertOk();
        $allCategories = collect($allResponse->json('audit_trail'))->pluck('category')->unique()->values()->all();

        $this->assertContains('inventory', $allCategories);
        $this->assertContains('purchase', $allCategories);
        $this->assertContains('sales', $allCategories);
        $this->assertContains('anomaly', $allCategories);
    }

    public function test_inventory_alerts_exclude_foreign_tenant_usage_and_supplier_context(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'inventory-alerts-scope@example.com', 'Admin Alerts Scope');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        \DB::table('products')
            ->where('id', $fixture['product_id'])
            ->update([
                'name' => 'Produk Alert Aktif',
                'sku' => 'ALERT-AKTIF',
                'min_stock' => 10,
            ]);

        \DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $fixture['location_id'])
            ->where('product_id', $fixture['product_id'])
            ->update([
                'qty_on_hand' => 4,
            ]);

        $activeSupplierId = (string) Str::uuid();
        $activePurchaseId = (string) Str::uuid();
        $activeSaleId = (string) Str::uuid();
        $now = CarbonImmutable::now('UTC');

        \DB::table('suppliers')->insert([
            'id' => $activeSupplierId,
            'tenant_id' => $tenant->id,
            'name' => 'Supplier Aktif',
            'balance' => 0,
            'created_at' => $now,
        ]);

        \DB::table('purchases')->insert([
            'id' => $activePurchaseId,
            'tenant_id' => $tenant->id,
            'supplier_id' => $activeSupplierId,
            'location_id' => $fixture['location_id'],
            'total' => 50000,
            'status' => 'received',
            'notes' => null,
            'payment_method' => 'cash',
            'due_date' => null,
            'created_at' => $now->subDays(5),
            'approved_at' => null,
            'approved_by' => null,
            'received_at' => null,
            'received_by' => null,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ]);

        \DB::table('purchase_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'purchase_id' => $activePurchaseId,
            'product_id' => $fixture['product_id'],
            'qty' => 5,
            'price' => 10000,
        ]);

        \DB::table('sales')->insert([
            'id' => $activeSaleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'customer_id' => null,
            'subtotal' => 20000,
            'discount' => 0,
            'tax' => 0,
            'total' => 20000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'created_at' => $now->subDays(2),
        ]);

        \DB::table('sale_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $activeSaleId,
            'product_id' => $fixture['product_id'],
            'qty' => 2,
            'price' => 10000,
            'discount' => 0,
            'hpp_snapshot' => 5000,
        ]);

        [$otherTenant, $otherUser] = $this->bootApiActor('admin', 'inventory-alerts-scope-other@example.com', 'Admin Alerts Scope Other');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);
        $this->actingAs($user, 'sanctum');

        \DB::table('products')
            ->where('id', $otherFixture['product_id'])
            ->update([
                'name' => 'Produk Alert Tenant Lain',
                'sku' => 'ALERT-LAWAN',
                'min_stock' => 99,
            ]);

        \DB::table('inventory_balances')
            ->where('tenant_id', $otherTenant->id)
            ->where('location_id', $otherFixture['location_id'])
            ->where('product_id', $otherFixture['product_id'])
            ->update([
                'qty_on_hand' => 1,
            ]);

        $foreignSupplierId = (string) Str::uuid();
        $foreignPurchaseId = (string) Str::uuid();
        $foreignSaleId = (string) Str::uuid();

        \DB::table('suppliers')->insert([
            'id' => $foreignSupplierId,
            'tenant_id' => $otherTenant->id,
            'name' => 'Supplier Tenant Lain',
            'balance' => 0,
            'created_at' => $now,
        ]);

        \DB::table('purchases')->insert([
            'id' => $foreignPurchaseId,
            'tenant_id' => $otherTenant->id,
            'supplier_id' => $foreignSupplierId,
            'location_id' => $otherFixture['location_id'],
            'total' => 990000,
            'status' => 'received',
            'notes' => null,
            'payment_method' => 'cash',
            'due_date' => null,
            'created_at' => $now->subDay(),
            'approved_at' => null,
            'approved_by' => null,
            'received_at' => null,
            'received_by' => null,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ]);

        \DB::table('purchase_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'purchase_id' => $foreignPurchaseId,
            'product_id' => $otherFixture['product_id'],
            'qty' => 99,
            'price' => 10000,
        ]);

        \DB::table('sales')->insert([
            'id' => $foreignSaleId,
            'tenant_id' => $otherTenant->id,
            'branch_id' => $otherFixture['branch_id'],
            'shift_id' => $otherFixture['shift_id'],
            'customer_id' => null,
            'subtotal' => 3000000,
            'discount' => 0,
            'tax' => 0,
            'total' => 3000000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $otherUser->id,
            'created_at' => $now->subDay(),
        ]);

        \DB::table('sale_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'sale_id' => $foreignSaleId,
            'product_id' => $otherFixture['product_id'],
            'qty' => 300,
            'price' => 10000,
            'discount' => 0,
            'hpp_snapshot' => 5000,
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/inventory-alerts');

        $response->assertOk()
            ->assertJsonPath('total_alerts', 1)
            ->assertJsonCount(1, 'alerts')
            ->assertJsonPath('alerts.0.id', $fixture['product_id'])
            ->assertJsonPath('alerts.0.name', 'Produk Alert Aktif')
            ->assertJsonPath('alerts.0.sku', 'ALERT-AKTIF')
            ->assertJsonPath('alerts.0.supplier_name', 'Supplier Aktif')
            ->assertJsonPath('alerts.0.stock_now', 4)
            ->assertJsonPath('alerts.0.avg_daily_usage', 0.07);

        $payload = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Produk Alert Tenant Lain', $payload);
        $this->assertStringNotContainsString('Supplier Tenant Lain', $payload);
        $this->assertStringNotContainsString('ALERT-LAWAN', $payload);
    }
}
