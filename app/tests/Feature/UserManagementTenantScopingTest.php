<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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

        $tenant->forceFill(['plan' => 'M'])->save();
        $foreignTenant->forceFill(['plan' => 'M'])->save();

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

        $tenant->forceFill(['plan' => 'M'])->save();
        $foreignTenant->forceFill(['plan' => 'M'])->save();

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

    public function test_store_creates_login_ready_cashier_user(): void
    {
        [$tenant, $owner] = $this->bootApiActor('owner', 'owner+create-login-cashier@example.test');

        $tenant->forceFill(['plan' => 'M'])->save();
        $this->actingAs($owner, 'sanctum');

        $rolesResponse = $this->getJson('/api/v1/users', $this->tenantHeaders($tenant->id));
        $rolesResponse->assertOk();

        $cashierRoleId = collect($rolesResponse->json('roles'))
            ->firstWhere('name', 'Kasir')['id'] ?? null;

        $this->assertNotNull($cashierRoleId);

        $createResponse = $this->postJson('/api/v1/users', [
            'name' => 'Kasir Login Siap',
            'email' => '  Kasir.Login.Siap@example.test  ',
            'password' => 'secret123',
            'role_id' => $cashierRoleId,
            'status' => 'active',
        ], $this->tenantHeaders($tenant->id));

        $createResponse->assertCreated();
        $createdUserId = $createResponse->json('user.id');

        $this->assertDatabaseHas('users', [
            'id' => $createdUserId,
            'tenant_id' => $tenant->id,
            'email' => 'kasir.login.siap@example.test',
        ]);
        $this->assertDatabaseHas('user_roles', [
            'tenant_id' => $tenant->id,
            'user_id' => $createdUserId,
            'role_id' => $cashierRoleId,
        ]);

        Auth::guard('web')->logout();

        $loginResponse = $this->post('/pos/login', [
            'email' => 'kasir.login.siap@example.test',
            'password' => 'secret123',
        ]);

        $loginResponse->assertRedirect();
        $loginResponse->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs(
            \App\Models\User::query()->where('email', 'kasir.login.siap@example.test')->first(),
            'web'
        );
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
