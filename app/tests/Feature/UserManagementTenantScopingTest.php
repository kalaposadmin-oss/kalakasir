<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class UserManagementTenantScopingTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_index_excludes_foreign_tenant_staff_and_sessions(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+staff-primary@example.test');
        [$foreignTenant, $foreignUser] = $this->bootApiActor('owner', 'owner+staff-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $primaryBranchId = $this->createBranch($tenant, 'Cabang Utama');
        $foreignBranchId = $this->createBranch($foreignTenant, 'Cabang Asing');

        DB::table('shifts')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'branch_id' => $primaryBranchId,
                'user_id' => $user->id,
                'status' => 'open',
                'opening_cash' => 100000,
                'opened_at' => now('UTC'),
                'closed_at' => null,
                'closing_cash' => 0,
                'system_cash_total' => 0,
                'difference' => 0,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $foreignTenant->id,
                'branch_id' => $foreignBranchId,
                'user_id' => $foreignUser->id,
                'status' => 'open',
                'opening_cash' => 100000,
                'opened_at' => now('UTC'),
                'closed_at' => null,
                'closing_cash' => 0,
                'system_cash_total' => 0,
                'difference' => 0,
            ],
        ]);

        $response = $this->getJson('/api/v1/users', $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('total_staff', 1)
            ->assertJsonPath('active_sessions', 1)
            ->assertJsonMissing(['id' => $foreignUser->id]);
    }

    public function test_update_returns_404_for_foreign_tenant_user(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+update-primary@example.test');
        [$foreignTenant, $foreignUser] = $this->bootApiActor('owner', 'owner+update-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $response = $this->putJson('/api/v1/users/'.$foreignUser->id, [
            'name' => 'Updated Foreign User',
            'email' => $foreignUser->email,
            'password' => '',
            'status' => 'active',
        ], $this->tenantHeaders($tenant->id));

        $response->assertStatus(404);

        $this->assertDatabaseHas('users', [
            'id' => $foreignUser->id,
            'tenant_id' => $foreignTenant->id,
            'name' => $foreignUser->name,
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
}