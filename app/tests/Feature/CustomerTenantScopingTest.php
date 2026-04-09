<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class CustomerTenantScopingTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_index_excludes_foreign_customer_and_foreign_recent_sales(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+customer-primary@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+customer-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $ownBranchId = $this->createBranch($tenant, 'Cabang Aktif');
        $foreignBranchId = $this->createBranch($foreignTenant, 'Cabang Asing');

        $ownCustomerId = $this->createCustomer($tenant, 'Customer Aktif');
        $foreignCustomerId = $this->createCustomer($foreignTenant, 'Customer Asing');

        $ownSaleId = $this->createSale(
            tenant: $tenant,
            branchId: $ownBranchId,
            customerId: $ownCustomerId,
            total: 50000,
            createdAt: now('UTC')->subMinute()->toDateTimeString(),
        );

        $foreignLeakSaleId = $this->createSale(
            tenant: $foreignTenant,
            branchId: $foreignBranchId,
            customerId: $ownCustomerId,
            total: 99000,
            createdAt: now('UTC')->toDateTimeString(),
        );

        $response = $this->getJson('/api/v1/customers', $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonMissing(['id' => $foreignCustomerId])
            ->assertJsonMissing(['id' => $foreignLeakSaleId, 'total' => 99000.0])
            ->assertJsonPath('customers.0.id', $ownCustomerId)
            ->assertJsonPath('customers.0.recent_sales.0.id', $ownSaleId)
            ->assertJsonPath('customers.0.recent_sales.0.total', 50000)
            ->assertJsonPath('customers.0.order_count', 1)
            ->assertJsonPath('customers.0.total_spent', 50000);
    }

    public function test_lite_index_returns_minimal_scoped_customer_options(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+customer-lite@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+customer-lite-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $ownCustomerId = $this->createCustomer($tenant, 'Member Lite');
        $foreignCustomerId = $this->createCustomer($foreignTenant, 'Member Asing');

        $response = $this->getJson('/api/v1/customers?lite=1&q=lite&limit=10', $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('customers.0.id', $ownCustomerId)
            ->assertJsonPath('customers.0.name', 'Member Lite')
            ->assertJsonPath('filters.limit', 10)
            ->assertJsonCount(1, 'customers');

        $payload = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($foreignCustomerId, $payload);
        $this->assertStringNotContainsString('recent_sales', $payload);
        $this->assertStringNotContainsString('total_spent', $payload);
    }

    private function createBranch(Tenant $tenant, string $name): string
    {
        $branchId = (string) Str::uuid();

        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenant->id,
            'name' => $name,
        ]);

        return $branchId;
    }

    private function createCustomer(Tenant $tenant, string $name): string
    {
        $customerId = (string) Str::uuid();

        DB::table('customers')->insert([
            'id' => $customerId,
            'tenant_id' => $tenant->id,
            'name' => $name,
            'phone' => null,
            'email' => null,
            'address' => null,
            'created_at' => now('UTC'),
        ]);

        return $customerId;
    }

    private function createSale(Tenant $tenant, string $branchId, string $customerId, float $total, string $createdAt): string
    {
        $saleId = (string) Str::uuid();

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'shift_id' => (string) Str::uuid(),
            'customer_id' => $customerId,
            'subtotal' => $total,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'status' => 'completed',
            'idempotency_key' => 'sale-'.Str::lower(Str::random(10)),
            'created_by' => null,
            'voided_by' => null,
            'created_at' => $createdAt,
        ]);

        return $saleId;
    }
}