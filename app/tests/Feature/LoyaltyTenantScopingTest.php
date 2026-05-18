<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class LoyaltyTenantScopingTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_loyalty_index_excludes_foreign_tier_member_and_sales(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+loyalty-primary@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+loyalty-foreign@example.test');
        $tenant->forceFill(['plan' => 'M'])->save();

        $this->actingAs($user, 'sanctum');

        $ownBranchId = $this->createBranch($tenant, 'Cabang Loyalty');
        $foreignBranchId = $this->createBranch($foreignTenant, 'Cabang Loyalty Asing');

        $this->createTier($tenant, 'Silver', 0);
        $this->createTier($tenant, 'Gold', 50);
        $foreignTierId = $this->createTier($foreignTenant, 'Platinum Asing', 999);

        $ownCustomerId = $this->createCustomer($tenant, 'Member Aktif');
        $foreignCustomerId = $this->createCustomer($foreignTenant, 'Member Asing');

        $ownSaleId = $this->createSale($tenant, $ownBranchId, $ownCustomerId, 75000);
        $foreignLeakSaleId = $this->createSale($foreignTenant, $foreignBranchId, $ownCustomerId, 999000);

        $response = $this->getJson('/api/v1/loyalty', $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonMissing(['id' => $foreignTierId])
            ->assertJsonMissing(['id' => $foreignCustomerId])
            ->assertJsonMissing(['name' => 'Platinum Asing'])
            ->assertJsonMissing(['id' => $foreignLeakSaleId])
            ->assertJsonPath('members.0.id', $ownCustomerId)
            ->assertJsonPath('members.0.transactions', 1)
            ->assertJsonPath('members.0.points', 75)
            ->assertJsonPath('members.0.tier', 'Silver');
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

    private function createTier(Tenant $tenant, string $name, int $minPoints): string
    {
        $tierId = (string) Str::uuid();

        DB::table('loyalty_tiers')->insert([
            'id' => $tierId,
            'tenant_id' => $tenant->id,
            'name' => $name,
            'min_points' => $minPoints,
            'color' => null,
        ]);

        return $tierId;
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

    private function createSale(Tenant $tenant, string $branchId, string $customerId, float $total): string
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
            'created_at' => now('UTC'),
        ]);

        return $saleId;
    }
}