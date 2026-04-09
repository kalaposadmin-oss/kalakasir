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

        DB::table('materials')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'name' => 'Gula Pasir',
                'unit' => 'kg',
                'unit_cost' => 15000,
                'supplier_id' => null,
                'is_active' => true,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
                'deleted_at' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'name' => 'Bahan Tidak Aktif',
                'unit' => 'kg',
                'unit_cost' => 10000,
                'supplier_id' => null,
                'is_active' => false,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
                'deleted_at' => now('UTC'),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $otherTenant->id,
                'name' => 'Bahan Tenant Lain',
                'unit' => 'kg',
                'unit_cost' => 20000,
                'supplier_id' => null,
                'is_active' => true,
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
            ->assertJsonPath('data.materials.0.name', 'Gula Pasir')
            ->assertJsonMissing(['name' => 'Bahan Tenant Lain'])
            ->assertJsonMissing(['name' => 'Bahan Tidak Aktif']);
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

        $this->withHeaders($headers)
            ->getJson('/api/v1/materials/'.$materialId)
            ->assertOk()
            ->assertJsonPath('material.id', $materialId)
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

        $this->assertSoftDeleted('materials', [
            'id' => $materialId,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertDatabaseHas('materials', [
            'id' => $materialId,
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
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

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/materials')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }
}