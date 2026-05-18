<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackofficeMenuDrawerFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_backoffice_dashboard_hides_premium_menu_drawer_entries(): void
    {
        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        [, $user] = $this->createWebActor('admin', 'starter-drawer@example.test', 'S');

        $response = $this->from('http://office.kalakasir.test/backoffice/login')
            ->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('http://office.kalakasir.test/backoffice/dashboard');

        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertSee('Akun Kas &amp; Bank', false);
        $response->assertSee('Metode Persediaan', false);
        $response->assertDontSee('Valuation Lanjutan');
        $response->assertDontSee('Audit Valuation');
        $response->assertDontSee('Observability');
        $response->assertDontSee('Integrasi');
    }

    public function test_starter_backoffice_dashboard_hides_materials_menu_drawer_entry(): void
    {
        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        [, $user] = $this->createWebActor('admin', 'starter-materials-drawer@example.test', 'S');

        $response = $this->from('http://office.kalakasir.test/backoffice/login')
            ->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('http://office.kalakasir.test/backoffice/dashboard');

        $response->assertOk();
        $response->assertDontSee('Bahan Baku');
    }

    public function test_m_plan_backoffice_dashboard_shows_materials_menu_drawer_entry(): void
    {
        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        [, $user] = $this->createWebActor('admin', 'm-materials-drawer@example.test', 'M');

        $response = $this->from('http://office.kalakasir.test/backoffice/login')
            ->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('http://office.kalakasir.test/backoffice/dashboard');

        $response->assertOk();
        $response->assertSee('Bahan Baku');
    }

    public function test_pro_backoffice_dashboard_shows_premium_menu_drawer_entries(): void
    {
        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        [, $user] = $this->createWebActor('admin', 'pro-drawer@example.test', 'P');

        $response = $this->from('http://office.kalakasir.test/backoffice/login')
            ->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('http://office.kalakasir.test/backoffice/dashboard');

        $response->assertOk();
        $response->assertSee('Akun Kas &amp; Bank', false);
        $response->assertSee('Valuation Lanjutan');
        $response->assertSee('Audit Valuation');
        $response->assertSee('Observability');
        $response->assertSee('Integrasi');
    }

    private function createWebActor(string $role, string $email, string $plan = 'P', string $name = 'Drawer User'): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant '.Str::upper(Str::random(4)),
            'timezone' => 'Asia/Jakarta',
            'inventory_valuation_method' => 'average',
            'plan' => $plan,
        ]);

        $userId = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $userId,
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('secret123'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $user = User::withoutGlobalScopes()->findOrFail($userId);

        $roleId = (string) Str::uuid();

        DB::table('roles')->insert([
            'id' => $roleId,
            'tenant_id' => $tenant->id,
            'name' => $role,
        ]);

        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $roleId,
        ]);

        return [$tenant, $user];
    }
}