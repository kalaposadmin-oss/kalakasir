<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class MaterialManagementApiTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant] = $this->bootApiActor('admin', 'admin-materials@example.test', 'Admin Materials');
        DB::table('tenants')->where('id', $this->tenant->id)->update(['plan' => 'M']);
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_index_returns_active_tenant_materials_only(): void
    {
        $otherTenant = Tenant::query()->create([
            'name' => 'Tenant Other Materials',
            'timezone' => 'UTC',
            'plan' => 'S',
        ]);

        DB::table('products')->insert([
            [
                'id' => (string) Str::uuid(),
                'legacy_material_id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'sku' => 'RM-GULA-PASIR',
                'name' => 'Gula Pasir',
                'price' => 0,
                'hpp_average' => 15000,
                'unit' => 'kg',
                'supplier_id' => null,
                'is_active' => true,
                'track_stock' => true,
                'product_mode' => 'add_on',
                'production_mode' => 'none',
                'item_type' => 'raw_material',
                'variants' => json_encode([]),
                'bom_items' => json_encode([]),
                'bundle_items' => json_encode([]),
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
                'deleted_at' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'legacy_material_id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'sku' => 'RM-INACTIVE',
                'name' => 'Bahan Tidak Aktif',
                'price' => 0,
                'hpp_average' => 10000,
                'unit' => 'kg',
                'supplier_id' => null,
                'is_active' => false,
                'track_stock' => true,
                'product_mode' => 'add_on',
                'production_mode' => 'none',
                'item_type' => 'raw_material',
                'variants' => json_encode([]),
                'bom_items' => json_encode([]),
                'bundle_items' => json_encode([]),
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
                'deleted_at' => now('UTC'),
            ],
            [
                'id' => (string) Str::uuid(),
                'legacy_material_id' => (string) Str::uuid(),
                'tenant_id' => $otherTenant->id,
                'sku' => 'RM-OTHER-TENANT',
                'name' => 'Bahan Tenant Lain',
                'price' => 0,
                'hpp_average' => 20000,
                'unit' => 'kg',
                'supplier_id' => null,
                'is_active' => true,
                'track_stock' => true,
                'product_mode' => 'add_on',
                'production_mode' => 'none',
                'item_type' => 'raw_material',
                'variants' => json_encode([]),
                'bom_items' => json_encode([]),
                'bundle_items' => json_encode([]),
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
                'deleted_at' => null,
            ],
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/materials');

        $response->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.limit', 20)
            ->assertJsonPath('meta.source', 'products.raw_material')
            ->assertJsonPath('data.materials.0.name', 'Gula Pasir')
            ->assertJsonMissing(['name' => 'Bahan Tenant Lain'])
            ->assertJsonMissing(['name' => 'Bahan Tidak Aktif']);
    }

    public function test_index_exposes_products_that_use_the_material_in_recipe(): void
    {
        $rawMaterialId = (string) Str::uuid();
        $finishedProductId = (string) Str::uuid();

        DB::table('products')->insert([
            [
                'id' => $rawMaterialId,
                'legacy_material_id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'sku' => 'RM-TERIGU',
                'name' => 'Terigu',
                'price' => 0,
                'hpp_average' => 12000,
                'unit' => 'kg',
                'supplier_id' => null,
                'is_active' => true,
                'track_stock' => true,
                'product_mode' => 'add_on',
                'production_mode' => 'none',
                'item_type' => 'raw_material',
                'variants' => json_encode([]),
                'bom_items' => json_encode([]),
                'bundle_items' => json_encode([]),
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
                'deleted_at' => null,
            ],
            [
                'id' => $finishedProductId,
                'legacy_material_id' => null,
                'tenant_id' => $this->tenant->id,
                'sku' => 'FG-ROTI',
                'name' => 'Roti Manis',
                'price' => 15000,
                'hpp_average' => 0,
                'unit' => 'pcs',
                'supplier_id' => null,
                'is_active' => true,
                'track_stock' => true,
                'product_mode' => 'finished_good',
                'production_mode' => 'batch_production',
                'item_type' => null,
                'variants' => json_encode([]),
                'bom_items' => json_encode([
                    [
                        'product_id' => $rawMaterialId,
                        'product_variant_id' => null,
                        'qty' => 1.25,
                    ],
                ]),
                'bundle_items' => json_encode([]),
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
                'deleted_at' => null,
            ],
        ]);

        $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/materials')
            ->assertOk()
            ->assertJsonPath('data.materials.0.name', 'Terigu')
            ->assertJsonPath('data.materials.0.used_in_count', 1)
            ->assertJsonPath('data.materials.0.used_in_products.0', 'Roti Manis');
    }

    public function test_store_show_update_and_delete_material(): void
    {
        $supplierId = (string) Str::uuid();
        DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $this->tenant->id,
            'name' => 'Supplier Utama',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        $headers = $this->tenantHeaders($this->tenant->id);

        $store = $this->withHeaders($headers)
            ->postJson('/api/v1/materials', [
                'name' => 'Susu Cair',
                'unit' => 'liter',
                'unit_cost' => 22000,
                'supplier_id' => $supplierId,
            ]);

        $store->assertCreated()
            ->assertJsonPath('material.name', 'Susu Cair')
            ->assertJsonPath('material.unit', 'liter')
            ->assertJsonPath('material.unit_cost', 22000)
            ->assertJsonPath('material.supplier_id', $supplierId);

        $materialId = (string) $store->json('material.id');
        $productId = (string) $store->json('material.product_id');

        $this->withHeaders($headers)
            ->getJson('/api/v1/materials/'.$materialId)
            ->assertOk()
            ->assertJsonPath('material.id', $materialId)
            ->assertJsonPath('material.product_id', $productId)
            ->assertJsonPath('material.name', 'Susu Cair');

        $this->withHeaders($headers)
            ->putJson('/api/v1/materials/'.$materialId, [
                'name' => 'Susu Full Cream',
                'unit' => 'liter',
                'unit_cost' => 25000,
                'supplier_id' => $supplierId,
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('material.name', 'Susu Full Cream')
            ->assertJsonPath('material.unit_cost', 25000);

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/materials/'.$materialId)
            ->assertOk()
            ->assertJsonPath('message', 'Bahan berhasil dihapus.');

        $this->assertSoftDeleted('products', [
            'id' => $productId,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertDatabaseMissing('materials', [
            'id' => $materialId,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
            'item_type' => 'raw_material',
            'product_mode' => 'add_on',
            'unit' => 'liter',
            'supplier_id' => $supplierId,
        ]);
    }

    public function test_store_rejects_supplier_from_another_tenant(): void
    {
        $otherTenant = Tenant::query()->create([
            'name' => 'Tenant Foreign Supplier',
            'timezone' => 'UTC',
            'plan' => 'S',
        ]);

        $foreignSupplierId = (string) Str::uuid();
        DB::table('suppliers')->insert([
            'id' => $foreignSupplierId,
            'tenant_id' => $otherTenant->id,
            'name' => 'Supplier Foreign',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/materials', [
                'name' => 'Coklat Bubuk',
                'unit' => 'kg',
                'unit_cost' => 80000,
                'supplier_id' => $foreignSupplierId,
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['supplier_id'],
            ]);
    }

    public function test_non_admin_role_cannot_manage_materials(): void
    {
        [$tenant] = $this->bootApiActor('warehouse', 'warehouse-materials@example.test', 'Warehouse Materials');
        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'M']);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/materials')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_plan_s_cannot_access_materials_even_with_valid_role(): void
    {
        DB::table('tenants')->where('id', $this->tenant->id)->update(['plan' => 'S']);

        $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/materials')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FEATURE_NOT_ALLOWED');
    }
}