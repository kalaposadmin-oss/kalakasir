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
        $tenant->forceFill(['plan' => 'M'])->save();

        $this->actingAs($user, 'sanctum');

        $ownBranchId = $this->createBranch($tenant, 'Cabang Aktif');
        $foreignBranchId = $this->createBranch($foreignTenant, 'Cabang Asing');

        $ownCustomerId = $this->createCustomer($tenant, 'Customer Aktif', 'Jl. Utama 1');
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
            ->assertJsonPath('customers.0.total_spent', 50000)
            ->assertJsonPath('customers.0.address', 'Jl. Utama 1');
    }

    public function test_lite_index_returns_minimal_scoped_customer_options(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+customer-lite@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+customer-lite-foreign@example.test');
        $tenant->forceFill(['plan' => 'M'])->save();

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

    public function test_pos_customer_store_creates_scoped_customer_with_contact_fields(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+customer-store@example.test');
        $tenant->forceFill(['plan' => 'M'])->save();

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/pos/customers', [
            'name' => 'Pelanggan Baru',
            'phone' => '08123456789',
            'address' => 'Jl. Mawar No. 10',
        ], $this->tenantHeaders($tenant->id));

        $response->assertCreated()
            ->assertJsonPath('customer.name', 'Pelanggan Baru')
            ->assertJsonPath('customer.phone', '08123456789')
            ->assertJsonPath('customer.address', 'Jl. Mawar No. 10');

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $tenant->id,
            'name' => 'Pelanggan Baru',
            'phone' => '08123456789',
            'address' => 'Jl. Mawar No. 10',
        ]);
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

    private function createCustomer(Tenant $tenant, string $name, ?string $address = null): string
    {
        $customerId = (string) Str::uuid();

        DB::table('customers')->insert([
            'id' => $customerId,
            'tenant_id' => $tenant->id,
            'name' => $name,
            'phone' => null,
            'email' => null,
            'address' => $address,
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
