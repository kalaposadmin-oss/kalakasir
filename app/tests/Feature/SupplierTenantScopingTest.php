<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SupplierController;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class SupplierTenantScopingTest extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant] = $this->bootApiActor('warehouse', 'warehouse-supplier-scope@example.com', 'Warehouse Supplier Scope');
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_index_returns_only_active_tenant_suppliers_and_balances(): void
    {
        $otherTenant = Tenant::query()->create([
            'name' => 'Tenant B',
            'timezone' => 'UTC',
        ]);

        DB::table('suppliers')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'name' => 'Alpha Supplier',
                'balance' => 150000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'name' => 'Beta Supplier',
                'balance' => 0,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $otherTenant->id,
                'name' => 'Cross Tenant Supplier',
                'balance' => 900000,
            ],
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/suppliers');

        $response->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('total_balance', 150000)
            ->assertJsonCount(2, 'suppliers')
            ->assertJsonPath('suppliers.0.name', 'Alpha Supplier')
            ->assertJsonPath('suppliers.0.balance', 150000)
            ->assertJsonPath('suppliers.0.status', 'active')
            ->assertJsonPath('suppliers.1.name', 'Beta Supplier')
            ->assertJsonPath('suppliers.1.balance', 0)
            ->assertJsonPath('suppliers.1.status', 'inactive')
            ->assertJsonMissing(['name' => 'Cross Tenant Supplier']);
    }

    public function test_index_returns_tenant_not_resolved_when_context_is_missing(): void
    {
        TenantContext::clear();

        $response = app(SupplierController::class)->index();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame([
            'code' => 'TENANT_NOT_RESOLVED',
            'message' => 'Tenant tidak ditemukan.',
            'details' => [],
        ], $response->getData(true));
    }

    public function test_admin_can_create_supplier_inline_for_active_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-supplier-create@example.com', 'Admin Supplier Create');
        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'M']);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/suppliers', [
                'name' => 'Supplier Baru Langsung',
            ]);

        $response->assertCreated()
            ->assertJsonPath('supplier.name', 'Supplier Baru Langsung')
            ->assertJsonPath('supplier.balance', 0)
            ->assertJsonPath('supplier.status', 'inactive');

        $this->assertDatabaseHas('suppliers', [
            'tenant_id' => $tenant->id,
            'name' => 'Supplier Baru Langsung',
            'balance' => 0,
        ]);
    }
}