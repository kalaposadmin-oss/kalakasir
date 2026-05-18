<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyMenuFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_legacy_settings_menu_hides_premium_items(): void
    {
        [, $user] = $this->createWebActor('admin', 'starter-legacy-menu@example.test', 'S');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/settings');

        $response->assertOk();
        $response->assertSee('Toko');
        $response->assertSee('Pembayaran &amp; Rekening', false);
        $response->assertDontSee('Valuation Persediaan');
        $response->assertDontSee('Audit Valuation');
        $response->assertDontSee('Observability');
        $response->assertDontSee('Manajemen Staff');
        $response->assertDontSee('Pengaturan Transaksi');
    }

    public function test_pro_legacy_settings_menu_shows_premium_items(): void
    {
        [, $user] = $this->createWebActor('admin', 'pro-legacy-menu@example.test', 'P');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/settings');

        $response->assertOk();
        $response->assertSee('Pembayaran &amp; Rekening', false);
        $response->assertSee('Valuation Persediaan');
        $response->assertSee('Audit Valuation');
        $response->assertSee('Observability');
        $response->assertSee('Manajemen Staff');
        $response->assertSee('Pengaturan Transaksi');
    }

    public function test_legacy_purchases_menu_keeps_shared_purchase_entries_visible_for_starter_plan(): void
    {
        [, $user] = $this->createWebActor('owner', 'starter-purchases-menu@example.test', 'S');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/purchases');

        $response->assertOk();
        $response->assertSee('Daftar Pembelian');
        $response->assertSee('Buat Pembelian Baru');
        $response->assertSee('Penerimaan Barang');
        $response->assertDontSee('Retur Pembelian');
    }

    public function test_legacy_purchases_menu_shows_purchase_return_for_m_plan(): void
    {
        [, $user] = $this->createWebActor('owner', 'm-purchases-menu@example.test', 'M');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/purchases');

        $response->assertOk();
        $response->assertSee('Retur Pembelian');
    }

    public function test_legacy_management_menu_hides_customer_and_loyalty_for_starter_plan(): void
    {
        [, $user] = $this->createWebActor('owner', 'starter-management-menu@example.test', 'S');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/management');

        $response->assertOk();
        $response->assertSee('Barang atau Jasa');
        $response->assertDontSee('Pelanggan');
        $response->assertDontSee('Marketing');
    }

    public function test_legacy_management_menu_shows_customer_and_loyalty_for_m_plan(): void
    {
        [, $user] = $this->createWebActor('owner', 'm-management-menu@example.test', 'M');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/management');

        $response->assertOk();
        $response->assertSee('Pelanggan');
        $response->assertSee('Marketing');
    }

    public function test_legacy_management_and_finance_menu_hides_lock_period_for_starter_plan(): void
    {
        [, $user] = $this->createWebActor('owner', 'starter-lock-period-menu@example.test', 'S');

        $management = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/management');

        $management->assertOk();
        $management->assertDontSee('Hutang dan Piutang');

        $finance = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/finance');

        $finance->assertOk();
        $finance->assertDontSee('Riwayat Transaksi Kas/Bank');
    }

    public function test_legacy_management_and_finance_menu_shows_lock_period_for_pro_plan(): void
    {
        [, $user] = $this->createWebActor('owner', 'pro-lock-period-menu@example.test', 'P');

        $management = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/management');

        $management->assertOk();
        $management->assertSee('Hutang dan Piutang');

        $finance = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/finance');

        $finance->assertOk();
        $finance->assertSee('Riwayat Transaksi Kas/Bank');
    }

    private function createWebActor(string $role, string $email, string $plan = 'P', string $name = 'Legacy Menu User'): array
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
