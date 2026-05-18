<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalWebAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kalakasir-testing-views'.DIRECTORY_SEPARATOR.Str::uuid();
        File::ensureDirectoryExists($compiledPath);
        config(['view.compiled' => str_replace('\\', '/', $compiledPath)]);
        config([
            'platform.portals.pos_hosts' => [],
            'platform.portals.backoffice_hosts' => [],
        ]);
        Auth::guard('web')->logout();
        TenantContext::clear();
    }

    public function test_generic_login_redirects_cashier_to_pos_dashboard(): void
    {
        [$tenant, $user] = $this->createWebActor('cashier', 'cashier@example.test');

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/pos/dashboard');
        $response->assertSessionHas('auth_portal', 'pos');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_generic_login_redirects_admin_to_backoffice_dashboard(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin@example.test');

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/backoffice/dashboard');
        $response->assertSessionHas('auth_portal', 'backoffice');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_generic_login_redirects_owner_to_backoffice_dashboard(): void
    {
        [, $user] = $this->createWebActor('owner', 'owner@example.test');

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/backoffice/dashboard');
        $response->assertSessionHas('auth_portal', 'backoffice');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_generic_login_on_pos_host_keeps_owner_in_pos_portal(): void
    {
        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        [, $user] = $this->createWebActor('owner', 'owner-pos-host@example.test');

        $response = $this->from('http://pos.kalakasir.test/login')
            ->post('http://pos.kalakasir.test/login', [
                'email' => $user->email,
                'password' => 'secret123',
            ]);

        $response->assertRedirect('/pos/dashboard');
        $response->assertSessionHas('auth_portal', 'pos');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_pos_login_on_bare_host_redirects_to_configured_pos_host(): void
    {
        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        $this->get('http://127.0.0.1:8000/pos/login?next=%2Fpos%2Fcheckout')
            ->assertRedirect('http://pos.kalakasir.test:8000/pos/login?next=%2Fpos%2Fcheckout');
    }

    public function test_pos_login_post_on_bare_host_redirects_with_307_to_configured_pos_host(): void
    {
        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        [, $user] = $this->createWebActor('owner', 'owner-pos-bare-host@example.test');

        $this->post('http://127.0.0.1:8000/pos/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertStatus(307)
            ->assertRedirect('http://pos.kalakasir.test:8000/pos/login');
    }

    public function test_generic_login_on_backoffice_host_keeps_owner_in_backoffice_portal(): void
    {
        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        [, $user] = $this->createWebActor('owner', 'owner-backoffice-host@example.test');

        $response = $this->from('http://office.kalakasir.test/login')
            ->post('http://office.kalakasir.test/login', [
                'email' => $user->email,
                'password' => 'secret123',
            ]);

        $response->assertRedirect('/backoffice/dashboard');
        $response->assertSessionHas('auth_portal', 'backoffice');
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_guest_is_redirected_directly_to_login_from_auth_protected_portal_switch_route(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Guest Login',
            'timezone' => 'Asia/Jakarta',
            'inventory_valuation_method' => 'average',
            'plan' => 'P',
        ]);

        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Owner Guest Login',
            'email' => 'owner-guest-login@example.test',
            'password' => bcrypt('secret123'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->get('/portal/switch/backoffice')
            ->assertRedirect('/');
    }

    public function test_guest_is_redirected_directly_to_onboarding_from_auth_protected_portal_switch_route_when_tenant_has_no_users(): void
    {
        Tenant::query()->create([
            'name' => 'Tenant Onboarding Redirect',
            'timezone' => 'Asia/Jakarta',
            'inventory_valuation_method' => 'average',
            'plan' => 'P',
        ]);

        $this->get('/portal/switch/pos')
            ->assertRedirect('/register/step-1');
    }

    public function test_pos_host_overrides_stale_backoffice_session_for_pos_pages(): void
    {
        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        [, $user] = $this->createWebActor('owner', 'owner-pos-session-override@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('http://pos.kalakasir.test/pos/dashboard');

        $response->assertOk();
        $response->assertSessionHas('auth_portal', 'pos');
    }

    public function test_backoffice_host_overrides_stale_pos_session_for_backoffice_pages(): void
    {
        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        [, $user] = $this->createWebActor('owner', 'owner-backoffice-session-override@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('http://office.kalakasir.test/backoffice/dashboard');

        $response->assertOk();
        $response->assertSessionHas('auth_portal', 'backoffice');
    }

    public function test_authenticated_cashier_is_redirected_to_pos_when_opening_backoffice_login(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-backoffice-login-redirect@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/backoffice/login');

        $response->assertRedirect('/pos/dashboard');
        $response->assertSessionHas('auth_portal', 'pos');
    }

    public function test_authenticated_owner_can_open_pos_login_and_stay_in_pos_portal(): void
    {
        [, $user] = $this->createWebActor('owner', 'owner-pos-login-redirect@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/pos/login');

        $response->assertRedirect('/pos/dashboard');
        $response->assertSessionHas('auth_portal', 'pos');
    }

    public function test_authenticated_cashier_on_backoffice_host_falls_back_to_pos_dashboard(): void
    {
        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        [, $user] = $this->createWebActor('cashier', 'cashier-generic-backoffice-host@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('http://office.kalakasir.test/login');

        $response->assertRedirect('/pos/dashboard');
        $response->assertSessionHas('auth_portal', 'pos');
    }

    public function test_portal_switch_on_pos_host_redirects_to_backoffice_host(): void
    {
        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        [, $user] = $this->createWebActor('owner', 'owner-switch-host@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('http://pos.kalakasir.test/portal/switch/backoffice?next=/backoffice/dashboard');

        $response->assertRedirect('http://office.kalakasir.test/backoffice/dashboard');
        $response->assertSessionHas('auth_portal', 'backoffice');
    }

    public function test_portal_switch_rejects_cross_portal_next_path(): void
    {
        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        [, $user] = $this->createWebActor('owner', 'owner-switch-sanitize@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('http://office.kalakasir.test/portal/switch/pos?next=/backoffice/dashboard');

        $response->assertRedirect('http://pos.kalakasir.test/pos/dashboard');
        $response->assertSessionHas('auth_portal', 'pos');
    }

    public function test_portal_switch_on_backoffice_host_redirects_to_pos_host(): void
    {
        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        [, $user] = $this->createWebActor('owner', 'owner-switch-backoffice-host@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('http://office.kalakasir.test/portal/switch/pos?next=/pos/history');

        $response->assertRedirect('http://pos.kalakasir.test/pos/history');
        $response->assertSessionHas('auth_portal', 'pos');
    }

    public function test_cashier_cannot_switch_to_backoffice_portal(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-switch-backoffice-denied@example.test');

        try {
            $this->withoutExceptionHandling()
                ->actingAs($user)
                ->withSession(['auth_portal' => 'pos'])
                ->get('/portal/switch/backoffice?next=/backoffice/dashboard');

            $this->fail('Cashier should not be able to switch into backoffice portal.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_legacy_first_user_without_user_roles_is_recovered_as_owner_for_backoffice(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Legacy Theo',
            'timezone' => 'Asia/Jakarta',
            'inventory_valuation_method' => 'average',
            'plan' => 'P',
        ]);

        $userId = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $userId,
            'tenant_id' => $tenant->id,
            'name' => 'Theo',
            'email' => 'theo@kasir.com',
            'password' => bcrypt('secret123'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'theo@kasir.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/backoffice/dashboard');
        $response->assertSessionHas('auth_portal', 'backoffice');

        $ownerRoleId = DB::table('roles')
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Owner')
            ->value('id');

        $this->assertNotNull($ownerRoleId);
        $this->assertDatabaseHas('user_roles', [
            'tenant_id' => $tenant->id,
            'user_id' => $userId,
            'role_id' => $ownerRoleId,
        ]);
    }

    public function test_generic_login_does_not_leak_tenant_context_between_roles(): void
    {
        [, $cashier] = $this->createWebActor('cashier', 'cashier-sequence@example.test');
        [, $admin] = $this->createWebActor('admin', 'admin-sequence@example.test');

        $this->from('/login')->post('/login', [
            'email' => $cashier->email,
            'password' => 'secret123',
        ])->assertRedirect('/pos/dashboard');

        $this->post('/logout')->assertRedirect('/');

        $this->from('/login')->post('/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ])->assertRedirect('/backoffice/dashboard');
    }

    public function test_get_logout_logs_user_out_without_csrf_form_roundtrip(): void
    {
        [, $owner] = $this->createWebActor('owner', 'owner-logout-get@example.test');

        $this->from('/login')->post('/login', [
            'email' => $owner->email,
            'password' => 'secret123',
        ])->assertRedirect('/backoffice/dashboard');

        $this->get('/logout')->assertRedirect('/');

        $this->assertGuest('web');
    }

    public function test_legacy_menu_uses_portal_relative_back_link_instead_of_absolute_root(): void
    {
        [, $admin] = $this->createWebActor('admin', 'admin-menu-backlink@example.test');

        $this->from('/backoffice/login')->post('/backoffice/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ])->assertRedirect('/backoffice/dashboard');

        $this->get('/menu/settings')
            ->assertOk()
            ->assertSee('/backoffice/dashboard', false)
            ->assertDontSee('href="http://127.0.0.1:8000/backoffice', false);
    }

    public function test_cashier_cannot_login_via_backoffice_login(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-backoffice@example.test');

        $response = $this->from('/backoffice/login')->post('/backoffice/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/backoffice/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_generic_login_is_rate_limited_after_repeated_failures(): void
    {
        config([
            'services.security.web_login.max_attempts' => 2,
            'services.security.web_login.lockout_seconds' => 60,
        ]);

        [, $user] = $this->createWebActor('owner', 'owner-rate-limit@example.test');

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $blocked = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $blocked->assertRedirect('/login');
        $blocked->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_failed_generic_login_updates_failed_attempt_counter_and_audit_log(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-login-audit@example.test');

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'tenant_id' => $tenant->id,
            'failed_login_attempts' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'user',
            'reference_id' => $user->id,
            'event_type' => 'web_login_failed',
        ]);
    }

    public function test_successful_generic_login_clears_failed_login_counter(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-login-reset@example.test');

        DB::table('users')
            ->where('id', $user->id)
            ->update(['failed_login_attempts' => 3]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect('/backoffice/dashboard');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'tenant_id' => $tenant->id,
            'failed_login_attempts' => 0,
        ]);
    }

    public function test_cashier_can_access_pos_entry_route_with_pos_portal_session(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-pos@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos');

        $response->assertRedirect('/pos/dashboard');
    }

    public function test_cashier_with_open_shift_enters_pos_at_checkout(): void
    {
        [$tenant, $user] = $this->createWebActor('cashier', 'cashier-pos-open-shift@example.test');
        $this->openShiftFor($tenant, $user);

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos');

        $response->assertRedirect('/pos/checkout');
    }

    public function test_pos_back_links_use_checkout_as_home_when_shift_is_open(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-pos-back-open-shift@example.test');
        $this->openShiftFor($tenant, $user);

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/products?from=%2Fpos%2Fdashboard');

        $response->assertOk();
        $response->assertSee('href="/pos/checkout"', false);
        $response->assertDontSee('href="/pos/dashboard"', false);
    }

    public function test_cashier_without_open_shift_is_redirected_to_shift_open_before_checkout(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-no-shift@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/checkout');

        $response->assertRedirect('/pos/shifts/open?from=%2Fpos%2Fcheckout');
    }

    public function test_shift_open_page_explains_default_branch_setup_for_new_tenants(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-shift-open-copy@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/shifts/open');

        $response->assertOk();
        $response->assertSee('Cabang Operasional');
        $response->assertSee('Saat pertama daftar, sistem biasanya membuat 1 cabang utama otomatis untuk toko Anda.');
    }

    public function test_cashier_with_open_shift_can_access_checkout(): void
    {
        [$tenant, $user] = $this->createWebActor('cashier', 'cashier-open-shift@example.test');
        $branchId = (string) Str::uuid();

        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        DB::table('shifts')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'opened_at' => now('UTC')->subHour(),
            'closed_at' => null,
            'opening_cash' => 0,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/checkout');

        $response->assertOk();
        $response->assertSee('Kasir POS - Checkout Utama');
    }

    public function test_cashier_is_forbidden_from_backoffice_when_portal_is_backoffice(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-blocked@example.test');

        try {
            $this->withoutExceptionHandling()
                ->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/dashboard');

            $this->fail('Cashier should not be able to access backoffice dashboard.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_pos_dashboard_keeps_menu_button_visible(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-dashboard-menu@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/dashboard');

        $response->assertOk();
        $response->assertSee('data-menu-trigger', false);
    }

    public function test_backoffice_user_is_redirected_back_when_accessing_pos_without_switching(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-portal@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/pos/checkout');

        $response->assertRedirect('/backoffice/dashboard');
    }

    public function test_admin_can_switch_from_backoffice_to_pos_and_keep_next_target(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-switch@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/portal/switch/pos?next=/pos/checkout');

        $response->assertRedirect('/pos/checkout');
        $response->assertSessionHas('auth_portal', 'pos');
    }

    public function test_cashier_cannot_switch_to_backoffice(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-switch@example.test');

        try {
            $this->withoutExceptionHandling()
                ->actingAs($user)
                ->withSession(['auth_portal' => 'pos'])
                ->get('/portal/switch/backoffice');

            $this->fail('Cashier should not be able to switch to backoffice.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_owner_pos_dashboard_no_longer_shows_owner_mode_shortcuts(): void
    {
        [, $user] = $this->createWebActor('owner', 'owner-pos-shortcuts@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/dashboard');

        $response->assertOk();
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('Tetap di alur POS, lalu buka alat manajemen saat diperlukan.');
    }

    public function test_owner_pos_dashboard_does_not_render_owner_shortcut_links(): void
    {
        [, $user] = $this->createWebActor('owner', 'owner-pos-dashboard-shortcuts@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/dashboard');

        $response->assertOk();
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('Tetap di alur POS, lalu buka alat manajemen saat diperlukan.');
    }

    public function test_owner_pos_sales_history_no_longer_shows_owner_shortcuts(): void
    {
        [, $user] = $this->createWebActor('owner', 'owner-pos-history-shortcuts@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/history');

        $response->assertOk();
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('Pantau transaksi POS, lalu lompat cepat ke katalog, stok, atau pengaturan toko.');
    }

    public function test_backoffice_products_page_can_return_to_pos_dashboard_when_opened_from_pos(): void
    {
        [, $user] = $this->createWebActor('owner', 'owner-products-back-pos@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/products?from=%2Fpos%2Fdashboard');

        $response->assertOk();
        $response->assertSee('/portal/switch/pos?next=%2Fpos%2Fdashboard', false);
    }

    public function test_backoffice_product_create_page_can_return_to_pos_dashboard_when_opened_directly_from_pos(): void
    {
        [, $user] = $this->createWebActor('owner', 'owner-product-create-back-pos@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/products/new?from=%2Fpos%2Fdashboard');

        $response->assertOk();
        $response->assertSee('/portal/switch/pos?next=%2Fpos%2Fdashboard', false);
    }

    public function test_plan_s_backoffice_product_create_hides_bom_editor_and_plan_m_shows_it(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-product-create-bom-plan-s-m@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'S']);

        $starter = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/products/new');

        $starter->assertOk();
        $starter->assertSee('id="bom-locked-notice"', false);
        $starter->assertSee('id="bom-editor-card"', false);
        $starter->assertSee('p-4 space-y-4 hidden" id="bom-editor-card"', false);

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'M']);

        $medium = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/products/new');

        $medium->assertOk();
        $medium->assertSee('id="bom-locked-notice"', false);
        $medium->assertSee('id="bom-editor-card"', false);
        $medium->assertDontSee('p-4 space-y-4 hidden" id="bom-editor-card"', false);
    }

    public function test_plan_s_backoffice_product_edit_compiles_bom_gate_false_and_plan_m_compiles_true(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-product-edit-bom-plan-s-m@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'S']);

        $starter = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/products/product-001/edit');

        $starter->assertOk();
        $starter->assertSee('const canManageRecipe = false;', false);
        $starter->assertSee('id="edit-bom-card"', false);
        $starter->assertSee('id="edit-bom-locked"', false);

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'M']);

        $medium = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/products/product-001/edit');

        $medium->assertOk();
        $medium->assertSee('const canManageRecipe = true;', false);
        $medium->assertSee('id="edit-bom-card"', false);
        $medium->assertSee('id="edit-bom-locked"', false);
    }

    public function test_product_edit_page_injects_recipe_output_qty_into_initial_product_payload(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-product-edit-recipe-output@example.test');

        DB::table('products')->insert([
            'id' => 'product-bom-001',
            'tenant_id' => $tenant->id,
            'category_id' => null,
            'supplier_id' => null,
            'legacy_material_id' => null,
            'sku' => 'ROTI-001',
            'name' => 'Roti Uji',
            'price' => 10000,
            'hpp_average' => 0,
            'track_stock' => true,
            'min_stock' => 0,
            'unit' => null,
            'image_url' => null,
            'is_active' => true,
            'product_mode' => 'bom',
            'production_mode' => 'batch_production',
            'item_type' => 'finished_good',
            'recipe_output_qty' => 10,
            'bom_items' => json_encode([]),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/products/product-bom-001/edit');

        $response->assertOk();
        $response->assertSee('"recipe_output_qty":10', false);
        $response->assertSee('const loadedProduct = productId', false);
    }

    public function test_raw_material_product_can_be_saved_without_selling_price(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-raw-material-no-price@example.test');

        $response = $this->actingAs($user)
            ->withHeaders(['X-Tenant-Id' => $tenant->id])
            ->postJson('/api/v1/products', [
                'name' => 'Tepung Premium',
                'sku' => 'RAW-TEPUNG-001',
                'unit' => 'kg',
                'item_type' => 'raw_material',
                'product_mode' => 'add_on',
                'production_mode' => 'none',
                'track_stock' => true,
                'initial_stock' => 0,
                'price' => null,
                'hpp_average' => 12500,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('product.item_type', 'raw_material');
        $response->assertJsonPath('product.price', 0);

        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenant->id,
            'sku' => 'RAW-TEPUNG-001',
            'item_type' => 'raw_material',
            'price' => 0,
        ]);
    }

    public function test_backoffice_inventory_page_can_return_to_pos_dashboard_when_opened_from_pos(): void
    {
        [, $user] = $this->createWebActor('owner', 'owner-inventory-back-pos@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/inventory?from=%2Fpos%2Fdashboard');

        $response->assertOk();
        $response->assertSee('/portal/switch/pos?next=%2Fpos%2Fdashboard', false);
    }

    public function test_cashier_pos_sales_history_does_not_expose_management_shortcuts(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-pos-history-no-shortcuts@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/history');

        $response->assertOk();
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('/portal/switch/backoffice?next=/backoffice/products', false);
    }

    public function test_owner_pos_shift_open_no_longer_shows_management_shortcuts(): void
    {
        [, $user] = $this->createWebActor('owner', 'owner-pos-shift-open-shortcuts@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/shifts/open');

        $response->assertOk();
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('Buka shift dulu, lalu tetap punya jalur cepat ke katalog, stok, dan pengaturan toko.');
    }

    public function test_cashier_can_access_pos_sales_detail_wrapper(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-detail@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/sales/sale-001?from=%2Fpos%2Fhistory');

        $response->assertOk();
        $response->assertSee('/pos/history', false);
        $response->assertSee('/pos/sales/sale-001/void-refund', false);
        $response->assertSee('id="void-refund-link"', false);
    }

    public function test_owner_pos_sales_detail_no_longer_shows_management_shortcuts(): void
    {
        [, $user] = $this->createWebActor('owner', 'owner-pos-detail-shortcuts@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/sales/sale-041?from=%2Fpos%2Fhistory');

        $response->assertOk();
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('Butuh tindakan manajemen tanpa keluar dari detail transaksi.');
    }

    public function test_cashier_pos_sales_detail_does_not_expose_management_shortcuts(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-pos-detail-no-shortcuts@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/sales/sale-042?from=%2Fpos%2Fhistory');

        $response->assertOk();
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('/portal/switch/backoffice?next=/backoffice/reports/sales', false);
    }

    public function test_admin_can_access_backoffice_sales_detail_wrapper_without_void_action(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-detail@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/sales/sale-002?from=%2Fbackoffice%2Freports%2Fsales');

        $response->assertOk();
        $response->assertSee('/backoffice/reports/sales', false);
        $response->assertDontSee('id="void-button"', false);
        $response->assertDontSee('id="void-refund-link"', false);
    }

    public function test_cashier_can_access_pos_void_refund_wrapper(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-void@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/sales/sale-010/void-refund?from=%2Fpos%2Fsales%2Fsale-010');

        $response->assertOk();
        $response->assertSee('/pos/sales/sale-010', false);
        $response->assertSee('/pos/history?from=%2Fpos%2Fsales%2Fsale-010', false);
        $response->assertSee('PROSES REFUND');
        $response->assertSee('Alokasi Payout Refund');
        $response->assertSee('Reset Otomatis');
        $response->assertSee('/pos/dashboard', false);
        $response->assertSee($this->ownerBackofficeShortcut('/pos/sales/sale-010/void-refund', '/backoffice/inventory'), false);
        $response->assertSee($this->ownerBackofficeShortcut('/pos/sales/sale-010/void-refund', '/backoffice/finance/summary'), false);
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('/portal/switch/backoffice?next=/backoffice/reports/sales', false);
    }

    public function test_owner_pos_void_refund_no_longer_shows_management_shortcuts(): void
    {
        [, $user] = $this->createWebActor('owner', 'owner-pos-void-shortcuts@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/sales/sale-043/void-refund?from=%2Fpos%2Fsales%2Fsale-043');

        $response->assertOk();
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('Tetap proses void di POS, lalu buka laporan atau stok bila butuh investigasi.');
    }

    public function test_backoffice_portal_cannot_open_pos_void_refund_wrapper_without_switching(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-void@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/pos/sales/sale-011/void-refund');

        $response->assertRedirect('/backoffice/dashboard');
    }

    public function test_cashier_can_access_pos_receipt_wrapper(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-receipt@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/sales/sale-012/receipt?from=%2Fpos%2Fpayment-method');

        $response->assertOk();
        $response->assertSee('/pos/payment-method', false);
        $response->assertSee('/pos/checkout', false);
    }

    public function test_owner_pos_receipt_no_longer_shows_management_shortcuts(): void
    {
        [, $user] = $this->createWebActor('owner', 'owner-pos-receipt-shortcuts@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/sales/sale-044/receipt?from=%2Fpos%2Fpayment-method');

        $response->assertOk();
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('Setelah transaksi selesai, lanjut cek laporan atau atur katalog bila perlu.');
    }

    public function test_cashier_pos_receipt_does_not_expose_management_shortcuts(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-pos-receipt-no-shortcuts@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/sales/sale-045/receipt?from=%2Fpos%2Fpayment-method');

        $response->assertOk();
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('/portal/switch/backoffice?next=/backoffice/reports/sales', false);
    }

    public function test_payment_method_uses_context_back_url_from_sales_detail(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-payment-context@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/payment-method?from=%2Fpos%2Fsales%2Fsale-040');

        $response->assertOk();
        $response->assertSee('/pos/sales/sale-040', false);
    }

    public function test_owner_pos_payment_method_no_longer_shows_management_shortcuts(): void
    {
        [, $user] = $this->createWebActor('owner', 'owner-pos-payment-shortcuts@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/payment-method?from=%2Fpos%2Fsales%2Fsale-046');

        $response->assertOk();
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('Akses cepat ke katalog, stok, laporan, dan preferensi saat pembayaran berjalan.');
    }

    public function test_cashier_pos_payment_method_does_not_expose_management_shortcuts(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-pos-payment-no-shortcuts@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/payment-method?from=%2Fpos%2Fsales%2Fsale-047');

        $response->assertOk();
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('/portal/switch/backoffice?next=/backoffice/products', false);
    }

    public function test_admin_can_access_backoffice_purchase_detail_wrapper(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-purchase-detail@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/purchases/purchase-001?from=%2Fbackoffice%2Fsuppliers');

        $response->assertOk();
        $response->assertSee('/backoffice/purchases', false);
    }

    public function test_admin_can_access_backoffice_inventory_stock_card_wrapper(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-stock-card@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/inventory/products/product-001/stock-card?from=%2Fbackoffice%2Freports%2Finventory');

        $response->assertOk();
        $response->assertSee('/backoffice/reports/inventory', false);
    }

    public function test_warehouse_can_access_backoffice_inventory_report_page(): void
    {
        [, $user] = $this->createWebActor('warehouse', 'warehouse-access@example.test');

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/reports/inventory')
            ->assertOk();
    }

    public function test_warehouse_can_access_legacy_product_supplier_and_receiving_read_pages(): void
    {
        [, $user] = $this->createWebActor('warehouse', 'warehouse-read-pages@example.test');

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/products')
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/products/product-001?from=%2Fbackoffice%2Fproducts')
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/suppliers')
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/purchases/purchase-001?from=%2Fbackoffice%2Fsuppliers')
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/receiving/purchase-001?from=%2Fbackoffice%2Freceiving')
            ->assertOk();
    }

    public function test_warehouse_can_access_support_backoffice_read_pages_without_admin_mutation_cta(): void
    {
        [, $user] = $this->createWebActor('warehouse', 'warehouse-support-pages@example.test');

        $categories = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/categories');
        $categories->assertOk();
        $categories->assertDontSee('/backoffice/categories/new', false);
        $categories->assertDontSee('/backoffice/categories/${item.id}/edit', false);

        $branches = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/branches');
        $branches->assertOk();
        $branches->assertDontSee('/backoffice/branches/edit', false);
        $branches->assertDontSee('data-branch-id="${branch.id}"', false);

        $customers = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/customers');
        $customers->assertOk();
        $customers->assertDontSee('person_add', false);

        $loyalty = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/loyalty');
        $loyalty->assertOk();
        $loyalty->assertDontSee('Kelola Tier', false);
        $loyalty->assertDontSee('Tambah Member Baru', false);

        $taxPromo = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/settings/tax-promo');
        $taxPromo->assertOk();
        $taxPromo->assertDontSee('Tambah Pajak Baru', false);
        $taxPromo->assertDontSee('Buat Promo', false);

        $lockPeriods = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/finance/lock-periods');
        $lockPeriods->assertOk();
        $lockPeriods->assertDontSee('Buat Periode Baru', false);

        $dashboard = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/dashboard');
        $dashboard->assertOk();
        $dashboard->assertDontSee('/backoffice/purchases/new', false);

        $subscription = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/settings/subscription');
        $subscription->assertOk();
        $subscription->assertDontSee('/backoffice/settings/subscription/change', false);

        $subscriptionInvoices = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/settings/subscription/invoices');
        $subscriptionInvoices->assertOk();
        $subscriptionInvoices->assertDontSee('Mark Paid', false);

        $integrations = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/settings/integrations');
        $integrations->assertOk();
        $integrations->assertDontSee('Hubungkan', false);
        $integrations->assertDontSee('Simpan Perubahan', false);
        $integrations->assertDontSee('Putuskan', false);

        $preferences = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/settings/preferences');
        $preferences->assertOk();
        $preferences->assertDontSee('Ganti Logo', false);
        $preferences->assertDontSee('Simpan Perubahan', false);

        $marketingOnlineStore = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/marketing/online-store');
        $marketingOnlineStore->assertOk();
        $marketingOnlineStore->assertDontSee('Sinkron Sekarang', false);
        $marketingOnlineStore->assertDontSee('+ Tambah Saluran', false);

        $marketingReferral = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/marketing/referral');
        $marketingReferral->assertOk();
        $marketingReferral->assertDontSee('Buat Kode Baru', false);
    }

    public function test_warehouse_can_access_legacy_inventory_operation_pages(): void
    {
        [, $user] = $this->createWebActor('warehouse', 'warehouse-legacy-inventory@example.test');

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/inventory')
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/inventory/stock-adjustment')
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/inventory/stock-transfers')
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/inventory/production-batches')
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/inventory/production-batches/batch-001')
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/reports/inventory/stock-alerts')
            ->assertOk();
    }

    public function test_plan_s_cannot_access_materials_or_production_pages(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-plan-s-production-blocked@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'S']);

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/materials')
            ->assertStatus(403);

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/materials')
            ->assertStatus(403);

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/inventory/production-batches')
            ->assertStatus(403);
    }

    public function test_non_admin_backoffice_roles_are_forbidden_from_admin_only_legacy_management_pages(): void
    {
        foreach ([
            ['warehouse', 'warehouse-admin-pages-blocked@example.test'],
            ['supervisor', 'supervisor-admin-pages-blocked@example.test'],
        ] as [$role, $email]) {
            [, $user] = $this->createWebActor($role, $email);

            foreach ([
                '/backoffice/products/new',
                '/backoffice/products/product-001/edit',
                '/backoffice/purchases/returns',
                '/backoffice/receiving/purchase-001/action',
                '/backoffice/categories/new',
                '/backoffice/categories/category-001/edit',
                '/backoffice/branches/edit',
                '/backoffice/branches/branch-001/edit',
                '/backoffice/users',
                '/backoffice/settings/chart-of-accounts',
                '/backoffice/settings/chart-of-accounts/account-001',
                '/backoffice/settings/bank-accounts/account-001',
            ] as $path) {
                try {
                    $this->withoutExceptionHandling()
                        ->actingAs($user)
                        ->withSession(['auth_portal' => 'backoffice'])
                        ->get($path);

                    $this->fail($role.' should not be able to access '.$path);
                } catch (HttpException $exception) {
                    $this->assertSame(403, $exception->getStatusCode());
                }
            }

            try {
                $this->withoutExceptionHandling()
                    ->actingAs($user)
                    ->withSession(['auth_portal' => 'pos'])
                    ->get('/pos/materials');

                $this->fail($role.' should not be able to access /pos/materials');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }

    public function test_subscription_change_page_requires_high_privilege_backoffice_role(): void
    {
        foreach ([
            ['warehouse', 'warehouse-subscription-change-blocked@example.test'],
            ['supervisor', 'supervisor-subscription-change-blocked@example.test'],
        ] as [$role, $email]) {
            [, $user] = $this->createWebActor($role, $email);

            try {
                $this->withoutExceptionHandling()
                    ->actingAs($user)
                    ->withSession(['auth_portal' => 'backoffice'])
                    ->get('/backoffice/settings/subscription/change');

                $this->fail($role.' should not be able to access subscription change page.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        [, $admin] = $this->createWebActor('admin', 'admin-subscription-change@example.test');
        $this->actingAs($admin)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/settings/subscription/change')
            ->assertOk();

        [, $owner] = $this->createWebActor('owner', 'owner-subscription-change@example.test');
        $this->actingAs($owner)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/settings/subscription/change')
            ->assertOk();
    }

    public function test_warehouse_is_forbidden_from_backoffice_sales_report_pages(): void
    {
        [, $user] = $this->createWebActor('warehouse', 'warehouse-sales-blocked@example.test');

        try {
            $this->withoutExceptionHandling()
                ->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/reports/sales');

            $this->fail('Warehouse should not be able to access backoffice sales report.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_cashier_is_forbidden_from_inventory_operation_pages(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-inventory-pages-blocked@example.test');

        foreach ([
            '/backoffice/inventory',
            '/backoffice/inventory/stock-adjustment',
            '/backoffice/inventory/stock-transfers',
            '/backoffice/inventory/production-batches',
            '/backoffice/inventory/production-batches/batch-001',
            '/backoffice/reports/inventory',
            '/backoffice/reports/inventory/stock-alerts',
        ] as $path) {
            try {
                $this->withoutExceptionHandling()
                    ->actingAs($user)
                    ->withSession(['auth_portal' => 'backoffice'])
                    ->get($path);

                $this->fail('Cashier should not be able to access '.$path);
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }

    public function test_cashier_is_forbidden_from_legacy_backoffice_product_material_and_supplier_pages(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-legacy-backoffice-blocked@example.test');

        foreach ([
            '/backoffice/products',
            '/backoffice/products/product-001',
            '/backoffice/materials',
            '/backoffice/suppliers',
            '/backoffice/purchases/purchase-001',
            '/backoffice/receiving/purchase-001',
        ] as $path) {
            try {
                $this->withoutExceptionHandling()
                    ->actingAs($user)
                    ->withSession(['auth_portal' => 'backoffice'])
                    ->get($path);

                $this->fail('Cashier should not be able to access '.$path);
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        try {
            $this->withoutExceptionHandling()
                ->actingAs($user)
                ->withSession(['auth_portal' => 'pos'])
                ->get('/pos/materials');

            $this->fail('Cashier should not be able to access /pos/materials');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_supervisor_can_access_backoffice_sales_report_and_sale_detail(): void
    {
        [, $user] = $this->createWebActor('supervisor', 'supervisor-sales@example.test');

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/reports/sales')
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/sales/sale-201?from=%2Fbackoffice%2Freports%2Fsales')
            ->assertOk();
    }

    public function test_supervisor_is_forbidden_from_observability_and_valuation_audit_pages(): void
    {
        [, $user] = $this->createWebActor('supervisor', 'supervisor-audit-blocked@example.test');

        try {
            $this->withoutExceptionHandling()
                ->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/settings/observability');

            $this->fail('Supervisor should not be able to access observability page.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        try {
            $this->withoutExceptionHandling()
                ->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/settings/inventory-valuation');

            $this->fail('Supervisor should not be able to access valuation settings page.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        try {
            $this->withoutExceptionHandling()
                ->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/settings/inventory-valuation-audit');

            $this->fail('Supervisor should not be able to access valuation audit page.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_admin_can_access_observability_and_valuation_audit_pages(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-audit-pages@example.test');

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/settings/inventory-valuation')
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/settings/observability')
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/settings/inventory-valuation-audit')
            ->assertOk();

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/settings/refund-legacy-audit')
            ->assertOk();
    }

    public function test_admin_on_plan_s_is_forbidden_from_observability_page(): void
    {
        [$tenant, $user] = $this->createWebActor('admin', 'admin-observability-plan-s@example.test');
        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'S']);

        try {
            $this->withoutExceptionHandling()
                ->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/settings/observability');

            $this->fail('Plan S admin should not be able to access observability page.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_admin_on_plan_s_is_forbidden_from_valuation_and_integrations_pages(): void
    {
        [$tenant, $user] = $this->createWebActor('admin', 'admin-valuation-plan-s@example.test');
        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'S']);

        foreach ([
            '/backoffice/settings/inventory-valuation',
            '/backoffice/settings/inventory-valuation-audit',
            '/backoffice/settings/integrations',
        ] as $path) {
            try {
                $this->withoutExceptionHandling()
                    ->actingAs($user)
                    ->withSession(['auth_portal' => 'backoffice'])
                    ->get($path);

                $this->fail('Plan S admin should not be able to access '.$path);
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }

    public function test_admin_on_plan_s_can_access_basic_inventory_method_page(): void
    {
        [$tenant, $user] = $this->createWebActor('admin', 'admin-inventory-method-plan-s@example.test');
        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'S']);

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/settings/inventory-method')
            ->assertOk();
    }

    public function test_plan_s_finance_summary_hides_pdf_affordance(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-finance-summary-plan-s@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'S']);

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/finance/summary');

        $response->assertOk();
        $response->assertDontSee('Unduh PDF');
        $response->assertSee('PDF tersedia mulai paket Medium');
        $response->assertSee('id="finance-summary-download-upgrade-profit"', false);
    }

    public function test_plan_s_backoffice_sales_report_hides_export_actions(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-sales-report-plan-s@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'S']);

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/reports/sales');

        $response->assertOk();
        $response->assertDontSee('Ekspor CSV');
        $response->assertDontSee('Unduh Laporan PDF/Excel');
        $response->assertDontSee('Unduh PDF');
        $response->assertSee('Export CSV tersedia mulai paket Medium');
        $response->assertSee('Download PDF/Excel tersedia mulai paket Medium.');
        $response->assertDontSee('id="report-modal"', false);
    }

    public function test_plan_s_pos_history_hides_export_action(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-pos-history-plan-s@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'S']);

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/history');

        $response->assertOk();
        $response->assertDontSee('id="export-button"', false);
        $response->assertSee('Export CSV tersedia mulai paket Medium');
    }

    public function test_admin_on_plan_s_basic_inventory_method_page_uses_subscription_upgrade_cta(): void
    {
        [$tenant, $user] = $this->createWebActor('admin', 'admin-inventory-method-upgrade-plan-s@example.test');
        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'S']);

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/settings/inventory-method');

        $response->assertOk();
        $response->assertSee('/backoffice/settings/subscription/change', false);
        $response->assertDontSee('/backoffice/settings/inventory-valuation', false);
        $response->assertSee('Upgrade untuk Valuation Lanjutan');
    }

    public function test_admin_on_plan_s_can_access_refund_legacy_audit_page(): void
    {
        [$tenant, $user] = $this->createWebActor('admin', 'admin-refund-legacy-plan-s@example.test');
        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'S']);

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/settings/refund-legacy-audit')
            ->assertOk();
    }

    public function test_admin_with_past_due_subscription_is_forbidden_from_valuation_pages(): void
    {
        [$tenant, $user] = $this->createWebActor('admin', 'admin-valuation-past-due@example.test');

        DB::table('subscriptions')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'plan' => 'P',
            'status' => 'past_due',
            'started_at' => now('UTC')->subMonth(),
            'current_period_start' => now('UTC')->subMonth(),
            'current_period_end' => now('UTC')->addMonth(),
            'grace_ends_at' => now('UTC')->addDay(),
            'is_current' => true,
            'created_at' => now('UTC')->subMonth(),
            'updated_at' => now('UTC')->subDay(),
        ]);

        foreach ([
            '/backoffice/settings/inventory-valuation',
            '/backoffice/settings/inventory-valuation-audit',
        ] as $path) {
            try {
                $this->withoutExceptionHandling()
                    ->actingAs($user)
                    ->withSession(['auth_portal' => 'backoffice'])
                    ->get($path);

                $this->fail('Past due admin should not be able to access '.$path);
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }

    public function test_admin_can_access_backoffice_product_detail_wrapper(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-product-detail@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/products/product-001?from=%2Fbackoffice%2Fproducts');

        $response->assertOk();
        $response->assertSee('/backoffice/products', false);
        $response->assertSee('const productDetailPortalPrefix = "\/backoffice";', false);
        $response->assertSee('inventory/products/${encodeURIComponent(productId)}/stock-card', false);
        $response->assertSee('const productId = "product-001"', false);
    }

    public function test_admin_can_access_pos_product_detail_wrapper(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-pos-product-detail@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/products/product-001?from=%2Fpos%2Fproducts');

        $response->assertOk();
        $response->assertSee('const productDetailPortalPrefix = "\/pos";', false);
        $response->assertSee('inventory/products/${encodeURIComponent(productId)}/stock-card', false);
        $response->assertSee('const productId = "product-001"', false);
    }

    public function test_plan_m_backoffice_product_detail_hides_stock_card_shortcut(): void
    {
        [$tenant, $user] = $this->createWebActor('admin', 'admin-product-detail-plan-m@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'M']);

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/products/product-001?from=%2Fbackoffice%2Fproducts');

        $response->assertOk();
        $response->assertDontSee('Riwayat Mutasi Stok');
        $response->assertDontSee('id="stock-history-btn"', false);
        $response->assertDontSee('/backoffice/inventory/products/${encodeURIComponent(productId)}/stock-card', false);
    }

    public function test_plan_m_cannot_access_stock_card_web_pages(): void
    {
        [$tenant, $user] = $this->createWebActor('admin', 'admin-stock-card-plan-m@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'M']);

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/reports/inventory/stock-card')
            ->assertStatus(403);

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/inventory/products/product-001/stock-card?from=%2Fbackoffice%2Fproducts%2Fproduct-001')
            ->assertStatus(403);
    }

    public function test_plan_m_backoffice_reports_page_hides_stock_card_shortcuts(): void
    {
        [$tenant, $user] = $this->createWebActor('admin', 'admin-reports-plan-m@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'M']);

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/reports');

        $response->assertOk();
        $response->assertDontSee('Audit Kartu Stok');
        $response->assertDontSee('>Kartu Stok<', false);
        $response->assertDontSee('/backoffice/reports/inventory/stock-card', false);
    }

    public function test_plan_s_menu_sections_hide_plan_gated_legacy_entries(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-menu-plan-s@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'S']);

        $management = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/management');

        $management->assertOk();
        $management->assertSee('Supplier');
        $management->assertDontSee('Pelanggan');
        $management->assertDontSee('Marketing');
        $management->assertDontSee('Diskon, Pajak, dan Biaya');

        $purchases = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/purchases');

        $purchases->assertOk();
        $purchases->assertDontSee('Retur Pembelian');

        $reports = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/reports');

        $reports->assertOk();
        $reports->assertSee('/reports/inventory', false);
        $reports->assertDontSee('Kartu Stok');
        $reports->assertDontSee('/stock-card', false);

        $settings = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/settings');

        $settings->assertOk();
        $settings->assertDontSee('Manajemen Staff');
        $settings->assertDontSee('Valuation Persediaan');
        $settings->assertDontSee('Audit Valuation');
        $settings->assertDontSee('Observability');
    }

    public function test_plan_s_owner_pos_dashboard_keeps_owner_shortcuts_removed(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-pos-plan-s-shortcuts@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'S']);

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/dashboard');

        $response->assertOk();
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('Tetap di alur POS, lalu buka alat manajemen saat diperlukan.');
    }

    public function test_plan_m_owner_pos_dashboard_keeps_owner_shortcuts_removed(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-pos-plan-m-shortcuts@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'M']);

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/dashboard');

        $response->assertOk();
        $response->assertDontSee('Mode Owner');
        $response->assertDontSee('Tetap di alur POS, lalu buka alat manajemen saat diperlukan.');
    }

    public function test_plan_m_menu_settings_hide_pro_only_entries_but_keep_medium_features(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-menu-plan-m@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'M']);

        $settings = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/menu/settings');

        $settings->assertOk();
        $settings->assertSee('Manajemen Staff');
        $settings->assertSee('Pengaturan Transaksi');
        $settings->assertDontSee('Valuation Persediaan');
        $settings->assertDontSee('Audit Valuation');
        $settings->assertDontSee('Observability');
    }

    public function test_plan_m_production_batch_pages_hide_observability_shortcuts(): void
    {
        [$tenant, $user] = $this->createWebActor('admin', 'admin-production-observability-plan-m@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'M']);

        $list = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/inventory/production-batches');

        $list->assertOk();
        $list->assertDontSee('/backoffice/settings/observability', false);

        $detail = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/inventory/production-batches/batch-001');

        $detail->assertOk();
        $detail->assertDontSee('Buka Observability');
        $detail->assertDontSee('/backoffice/settings/observability', false);
    }

    public function test_admin_and_owner_can_access_product_material_and_purchase_management_pages(): void
    {
        foreach ([
            ['admin', 'admin-management-pages@example.test'],
            ['owner', 'owner-management-pages@example.test'],
        ] as [$role, $email]) {
            [, $user] = $this->createWebActor($role, $email);

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/products/new')
                ->assertOk();

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/products/product-001/edit')
                ->assertOk();

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/materials')
                ->assertOk();

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'pos'])
                ->get('/pos/materials')
                ->assertOk();

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/purchases/new')
                ->assertOk();

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/purchases/returns')
                ->assertOk();

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/categories/new')
                ->assertOk();

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/categories/category-001/edit')
                ->assertOk();

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/branches/edit')
                ->assertOk();

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/branches/branch-001/edit')
                ->assertOk();

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/users')
                ->assertOk();

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/settings/chart-of-accounts')
                ->assertOk();

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/settings/chart-of-accounts/account-001')
                ->assertOk();

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/settings/bank-accounts/account-001')
                ->assertOk();

            $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get('/backoffice/settings/subscription/change')
                ->assertOk();
        }
    }

    public function test_owner_can_manage_team_via_user_management_api(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'owner-user-api@example.test');

        $rolesResponse = $this->actingAs($user)
            ->withHeaders(['X-Tenant-Id' => $tenant->id])
            ->getJson('/api/v1/users');

        $rolesResponse->assertOk();
        $kasirRoleId = collect($rolesResponse->json('roles'))
            ->firstWhere('name', 'Kasir')['id'] ?? null;

        $this->assertNotNull($kasirRoleId);

        $createResponse = $this->actingAs($user)
            ->withHeaders(['X-Tenant-Id' => $tenant->id])
            ->postJson('/api/v1/users', [
                'name' => 'Kasir Owner Managed',
                'email' => 'kasir-owner-managed@example.test',
                'password' => 'secret123',
                'role_id' => $kasirRoleId,
                'status' => 'active',
            ]);

        $createResponse->assertCreated();
        $createdUserId = $createResponse->json('user.id');

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'id' => $createdUserId,
            'email' => 'kasir-owner-managed@example.test',
        ]);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-Id' => $tenant->id])
            ->putJson('/api/v1/users/'.$createdUserId, [
                'name' => 'Kasir Owner Updated',
                'email' => 'kasir-owner-managed@example.test',
                'password' => '',
                'role_id' => $kasirRoleId,
                'status' => 'inactive',
            ])
            ->assertOk();

        $this->assertSoftDeleted('users', [
            'id' => $createdUserId,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_owner_can_open_pos_user_management_with_role_rules_and_csrf_header(): void
    {
        [, $user] = $this->createWebActor('owner', 'owner-pos-users-page@example.test');

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/users')
            ->assertOk()
            ->assertSee('Manajemen Pengguna')
            ->assertSee('Role dan Hak Akses')
            ->assertSee('Tambah user')
            ->assertSee('X-CSRF-TOKEN', false);
    }

    public function test_admin_can_access_backoffice_receiving_action_wrapper(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-receiving-action@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/receiving/purchase-001/action?from=%2Fbackoffice%2Freceiving%2Fpurchase-001');

        $response->assertOk();
        $response->assertSee('/backoffice/receiving/purchase-001', false);
    }

    public function test_admin_can_access_backoffice_receiving_list_wrapper(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-receiving-list@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/receiving');

        $response->assertOk();
        $response->assertSee('/backoffice/purchases', false);
        $response->assertSee('id="purchase-list"', false);
    }

    public function test_admin_can_access_backoffice_receiving_detail_wrapper(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-receiving-detail@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/receiving/purchase-001?from=%2Fbackoffice%2Freceiving');

        $response->assertOk();
        $response->assertSee('/backoffice/receiving', false);
        $response->assertSee('${portalPrefix}/receiving/${encodeURIComponent(selectedPurchaseId)}/action?from=', false);
        $response->assertSee('const currentPath = "\\/backoffice\\/receiving\\/purchase-001"', false);
    }

    public function test_admin_can_access_backoffice_branch_edit_wrapper(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-branch-edit@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/branches/branch-001/edit?from=%2Fbackoffice%2Fbranches');

        $response->assertOk();
        $response->assertSee('/backoffice/branches', false);
        $response->assertSee('let branchId = "branch-001"', false);
    }

    public function test_backoffice_dashboard_menu_lists_important_management_shortcuts_for_admin(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-menu-shortcuts@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/dashboard');

        $response->assertOk();
        $response->assertSee('Tambah Produk');
        $response->assertSee('Toko & Cabang');
        $response->assertSee('Akun Kas & Bank');
        $response->assertSee('Langganan');
    }

    public function test_admin_product_create_page_keeps_menu_button_visible(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-product-create-menu@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/products/new');

        $response->assertOk();
        $response->assertSee('data-menu-trigger', false);
    }

    public function test_cashier_can_access_pos_shift_summary_detail_wrapper(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-shift-summary@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/shifts/shift-001/summary?from=%2Fpos%2Fshifts%2Fsummary');

        $response->assertOk();
        $response->assertSee('/pos/shifts/summary', false);
        $response->assertSee('const shiftId = "shift-001"', false);
    }

    public function test_pos_shift_summary_page_keeps_menu_button_visible(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-shift-summary-menu@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/shifts/summary');

        $response->assertOk();
        $response->assertSee('data-menu-trigger', false);
    }

    public function test_pos_sales_history_shift_tab_links_to_canonical_shift_summary(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-shift-history-link@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/history');

        $response->assertOk();
        $response->assertSee('/pos/shifts/${encodeURIComponent(row.id)}/summary?from=', false);
        $response->assertDontSee('/shift/close-summary?shift_id=', false);
    }

    public function test_shift_close_redirects_to_canonical_shift_summary_path(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-shift-close-link@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/shifts/close');

        $response->assertOk();
        $response->assertSee('/pos/shifts/${encodeURIComponent(shiftId)}/summary?from=', false);
        $response->assertDontSee('/shift/close-summary?shift_id=', false);
    }

    public function test_legacy_pos_route_redirects_to_canonical_pos_route(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-legacy@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/kasir');

        $response->assertRedirect('/pos/checkout');
    }

    public function test_legacy_backoffice_route_redirects_to_canonical_backoffice_route(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-legacy@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/products');

        $response->assertRedirect('/backoffice/products');
    }

    public function test_legacy_purchase_detail_redirects_to_canonical_backoffice_route(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-legacy-purchase@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/purchases/detail?purchase_id=purchase-020&from=%2Fbackoffice%2Fsuppliers');

        $response->assertRedirect('/backoffice/purchases/purchase-020?from=%2Fbackoffice%2Fsuppliers');
    }

    public function test_legacy_pos_purchase_detail_redirects_to_canonical_receiving_route(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-legacy-pos-purchase@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/purchases/850f465e-f041-421d-b162-5b049da4de2d?from=%2Fpos%2Fpurchases');

        $response->assertRedirect('/pos/receiving/850f465e-f041-421d-b162-5b049da4de2d?from=%2Fpos%2Fpurchases');
    }

    public function test_legacy_pos_purchase_returns_page_remains_accessible(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-legacy-pos-purchase-returns@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/purchases/returns');

        $response->assertOk();
        $response->assertSee('Retur Pembelian');
    }

    public function test_legacy_product_detail_redirects_to_canonical_backoffice_route(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-legacy-product@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/products/detail?product_id=product-020&from=%2Fbackoffice%2Fproducts');

        $response->assertRedirect('/backoffice/products/product-020?from=%2Fbackoffice%2Fproducts');
    }

    public function test_legacy_stock_card_redirects_to_canonical_backoffice_route(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-legacy-stock@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/stock-card?product_id=product-020&from=%2Fbackoffice%2Fproducts%2Fdetail');

        $response->assertRedirect('/backoffice/inventory/products/product-020/stock-card?from=%2Fbackoffice%2Fproducts%2Fdetail');
    }

    public function test_legacy_stock_card_redirects_to_canonical_pos_route(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-legacy-pos-stock@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/stock-card?product_id=product-020&from=%2Fpos%2Fproducts%2Fdetail');

        $response->assertRedirect('/pos/inventory/products/product-020/stock-card?from=%2Fpos%2Fproducts%2Fdetail');
    }

    public function test_legacy_receiving_action_redirects_to_canonical_backoffice_route(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-legacy-receiving@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/receiving/action?purchase_id=purchase-030&from=%2Fbackoffice%2Freceiving');

        $response->assertRedirect('/backoffice/receiving/purchase-030/action?from=%2Fbackoffice%2Freceiving');
    }

    public function test_legacy_receiving_redirects_to_canonical_backoffice_detail_route(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-legacy-receiving-detail@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/receiving?purchase_id=purchase-031&from=%2Fbackoffice%2Fpurchases');

        $response->assertRedirect('/backoffice/receiving/purchase-031?from=%2Fbackoffice%2Fpurchases');
    }

    public function test_backoffice_receiving_list_query_redirects_to_canonical_detail_route(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-receiving-query@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/backoffice/receiving?purchase_id=purchase-032&from=%2Fbackoffice%2Freceiving');

        $response->assertRedirect('/backoffice/receiving/purchase-032?from=%2Fbackoffice%2Freceiving');
    }

    public function test_legacy_branch_edit_redirects_to_canonical_backoffice_route(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-legacy-branch@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/branches/edit?branch_id=branch-020&from=%2Fbackoffice%2Fbranches');

        $response->assertRedirect('/backoffice/branches/branch-020/edit?from=%2Fbackoffice%2Fbranches');
    }

    public function test_shift_summary_with_legacy_query_redirects_to_canonical_pos_route(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-legacy-shift@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/shift/close-summary?shift_id=shift-020&from=%2Fpos%2Fshifts%2Fsummary');

        $response->assertRedirect('/pos/shifts/shift-020/summary?from=%2Fpos%2Fshifts%2Fsummary');
    }

    public function test_pos_shift_summary_list_redirects_query_detail_to_canonical_route(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-pos-shift-query@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/pos/shifts/summary?shift_id=shift-021&from=%2Fpos%2Fdashboard');

        $response->assertRedirect('/pos/shifts/shift-021/summary?from=%2Fpos%2Fdashboard');
    }

    public function test_legacy_sales_detail_redirects_to_canonical_pos_route(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-legacy-detail@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/sales-detail?sale_id=sale-003&from=%2Fpos%2Fhistory');

        $response->assertRedirect('/pos/sales/sale-003?from=%2Fpos%2Fhistory');
    }

    public function test_legacy_sales_detail_prefers_explicit_backoffice_context_over_session(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-legacy-detail@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/sales-detail?sale_id=sale-004&from=%2Fbackoffice%2Freports%2Fsales');

        $response->assertRedirect('/backoffice/sales/sale-004?from=%2Fbackoffice%2Freports%2Fsales');
    }

    public function test_legacy_sales_void_refund_redirects_to_canonical_pos_route(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-legacy-void@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/sales-void-refund?sale_id=sale-020&from=%2Fpos%2Fsales%2Fsale-020');

        $response->assertRedirect('/pos/sales/sale-020/void-refund?from=%2Fpos%2Fsales%2Fsale-020');
    }

    public function test_legacy_sales_void_refund_prefers_explicit_backoffice_context_over_pos_session(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-legacy-void-backoffice@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/sales-void-refund?sale_id=sale-022&from=%2Fbackoffice%2Freports%2Fsales');

        $response->assertRedirect('/backoffice/reports/sales?from=%2Fbackoffice%2Freports%2Fsales');
    }

    public function test_legacy_sales_void_refund_without_sale_id_uses_portal_specific_fallback(): void
    {
        [, $cashier] = $this->createWebActor('cashier', 'cashier-legacy-void-fallback@example.test');
        [, $admin] = $this->createWebActor('admin', 'admin-legacy-void-fallback@example.test');

        $this->actingAs($cashier)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/sales-void-refund?from=%2Fpos%2Fhistory')
            ->assertRedirect('/pos/void-refund?from=%2Fpos%2Fhistory');

        auth('web')->logout();

        $this->actingAs($admin)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/sales-void-refund?from=%2Fbackoffice%2Freports%2Fsales')
            ->assertRedirect('/backoffice/reports/sales?from=%2Fbackoffice%2Freports%2Fsales');
    }

    public function test_legacy_receipt_preview_redirects_to_canonical_pos_receipt_route(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-legacy-receipt@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/receipt-preview?sale_id=sale-021&from=%2Fpos%2Fsales%2Fsale-021');

        $response->assertRedirect('/pos/sales/sale-021/receipt?from=%2Fpos%2Fsales%2Fsale-021');
    }

    public function test_legacy_receipt_preview_stays_in_pos_flow_even_with_backoffice_context(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-legacy-receipt-backoffice@example.test');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/receipt-preview?sale_id=sale-023&from=%2Fbackoffice%2Freports%2Fsales');

        $response->assertRedirect('/pos/sales/sale-023/receipt?from=%2Fbackoffice%2Freports%2Fsales');
    }

    public function test_legacy_sales_history_redirects_based_on_active_portal(): void
    {
        [, $cashier] = $this->createWebActor('cashier', 'cashier-history@example.test');
        [, $admin] = $this->createWebActor('admin', 'admin-history@example.test');

        $this->actingAs($cashier)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/sales-history')
            ->assertRedirect('/pos/history');

        auth('web')->logout();

        $this->actingAs($admin)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/sales-history')
            ->assertRedirect('/backoffice/reports/sales');
    }

    public function test_legacy_sales_history_preserves_query_string_for_active_portal(): void
    {
        [, $cashier] = $this->createWebActor('cashier', 'cashier-history-query@example.test');
        [, $admin] = $this->createWebActor('admin', 'admin-history-query@example.test');

        $cashierResponse = $this->actingAs($cashier)
            ->withSession(['auth_portal' => 'pos'])
            ->get('/sales-history?from=%2Fmenu%2Fsales&filter=today');

        $cashierResponse->assertRedirect();
        $cashierLocation = $cashierResponse->headers->get('Location');
        $this->assertSame('/pos/history', parse_url((string) $cashierLocation, PHP_URL_PATH));

        parse_str((string) parse_url((string) $cashierLocation, PHP_URL_QUERY), $cashierQuery);
        $this->assertEquals([
            'from' => '/menu/sales',
            'filter' => 'today',
        ], $cashierQuery);

        auth('web')->logout();

        $adminResponse = $this->actingAs($admin)
            ->withSession(['auth_portal' => 'backoffice'])
            ->get('/sales-history?from=%2Fbackoffice%2Fdashboard&filter=today');

        $adminResponse->assertRedirect();
        $adminLocation = $adminResponse->headers->get('Location');
        $this->assertSame('/backoffice/reports/sales', parse_url((string) $adminLocation, PHP_URL_PATH));

        parse_str((string) parse_url((string) $adminLocation, PHP_URL_QUERY), $adminQuery);
        $this->assertEquals([
            'from' => '/backoffice/dashboard',
            'filter' => 'today',
        ], $adminQuery);
    }

    public function test_legacy_wrapper_fallback_routes_preserve_query_without_entity_ids(): void
    {
        [, $admin] = $this->createWebActor('admin', 'admin-legacy-fallbacks@example.test');
        [, $cashier] = $this->createWebActor('cashier', 'cashier-legacy-fallbacks@example.test');

        $backofficeCases = [
            '/reports/inventory?from=%2Fbackoffice%2Freports' => '/backoffice/reports/inventory?from=%2Fbackoffice%2Freports',
            '/stock-card?from=%2Fbackoffice%2Freports%2Finventory' => '/backoffice/reports/inventory/stock-card?from=%2Fbackoffice%2Freports%2Finventory',
            '/products/detail?from=%2Fbackoffice%2Fproducts' => '/backoffice/products/detail?from=%2Fbackoffice%2Fproducts',
            '/receiving/action?from=%2Fbackoffice%2Freceiving' => '/backoffice/receiving/action?from=%2Fbackoffice%2Freceiving',
            '/purchases/detail?from=%2Fbackoffice%2Fpurchases' => '/backoffice/purchases/detail?from=%2Fbackoffice%2Fpurchases',
            '/branches/edit?from=%2Fbackoffice%2Fbranches' => '/backoffice/branches/edit?from=%2Fbackoffice%2Fbranches',
        ];

        foreach ($backofficeCases as $source => $target) {
            $this->actingAs($admin)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get($source)
                ->assertRedirect($target);

            auth('web')->logout();
        }

        $posCases = [
            '/shift/close-summary?from=%2Fpos%2Fdashboard' => '/pos/shifts/summary?from=%2Fpos%2Fdashboard',
            '/receipt-preview?from=%2Fpos%2Fsales' => '/pos/receipt-preview?from=%2Fpos%2Fsales',
        ];

        foreach ($posCases as $source => $target) {
            $this->actingAs($cashier)
                ->withSession(['auth_portal' => 'pos'])
                ->get($source)
                ->assertRedirect($target);

            auth('web')->logout();
        }
    }

    public function test_remaining_legacy_pos_wrappers_redirect_to_canonical_routes(): void
    {
        [, $user] = $this->createWebActor('cashier', 'cashier-legacy-pos-wrappers@example.test');

        $cases = [
            '/payment-method?from=%2Fpos%2Fsales%2Fsale-060' => '/pos/payment-method?from=%2Fpos%2Fsales%2Fsale-060',
            '/shift/open?from=%2Fpos%2Fdashboard' => '/pos/shifts/open?from=%2Fpos%2Fdashboard',
            '/shift/close?from=%2Fpos%2Fdashboard' => '/pos/shifts/close?from=%2Fpos%2Fdashboard',
        ];

        foreach ($cases as $source => $target) {
            $response = $this->actingAs($user)
                ->withSession(['auth_portal' => 'pos'])
                ->get($source);

            $response->assertRedirect($target);
        }
    }

    public function test_remaining_legacy_backoffice_wrappers_redirect_to_canonical_routes(): void
    {
        [, $user] = $this->createWebActor('admin', 'admin-legacy-backoffice-wrappers@example.test');

        $cases = [
            '/inventory?from=%2Fbackoffice%2Fdashboard' => '/backoffice/inventory?from=%2Fbackoffice%2Fdashboard',
            '/settings/chart-of-accounts?from=%2Fbackoffice%2Fdashboard' => '/backoffice/settings/chart-of-accounts?from=%2Fbackoffice%2Fdashboard',
            '/settings/chart-of-accounts/account-001?from=%2Fbackoffice%2Fsettings%2Fchart-of-accounts' => '/backoffice/settings/chart-of-accounts/account-001?from=%2Fbackoffice%2Fsettings%2Fchart-of-accounts',
            '/settings/bank-accounts/account-002?from=%2Fbackoffice%2Fsettings%2Fchart-of-accounts' => '/backoffice/settings/bank-accounts/account-002?from=%2Fbackoffice%2Fsettings%2Fchart-of-accounts',
            '/stock-adjustment?from=%2Fbackoffice%2Finventory' => '/backoffice/inventory/stock-adjustment?from=%2Fbackoffice%2Finventory',
            '/analytics?from=%2Fmenu%2Freports' => '/backoffice/dashboard?from=%2Fmenu%2Freports',
            '/products/new?from=%2Fbackoffice%2Fproducts' => '/backoffice/products/new?from=%2Fbackoffice%2Fproducts',
            '/products/product-021/edit?from=%2Fbackoffice%2Fproducts' => '/backoffice/products/product-021/edit?from=%2Fbackoffice%2Fproducts',
            '/categories?from=%2Fbackoffice%2Fdashboard' => '/backoffice/categories?from=%2Fbackoffice%2Fdashboard',
            '/categories/new?from=%2Fbackoffice%2Fcategories' => '/backoffice/categories/new?from=%2Fbackoffice%2Fcategories',
            '/categories/category-001/edit?from=%2Fbackoffice%2Fcategories' => '/backoffice/categories/category-001/edit?from=%2Fbackoffice%2Fcategories',
            '/customers?from=%2Fbackoffice%2Fdashboard' => '/backoffice/customers?from=%2Fbackoffice%2Fdashboard',
            '/suppliers?from=%2Fbackoffice%2Fdashboard' => '/backoffice/suppliers?from=%2Fbackoffice%2Fdashboard',
            '/branches?from=%2Fbackoffice%2Fdashboard' => '/backoffice/branches?from=%2Fbackoffice%2Fdashboard',
            '/purchases/new?from=%2Fbackoffice%2Fsuppliers' => '/backoffice/purchases/new?from=%2Fbackoffice%2Fsuppliers',
            '/purchase-returns?from=%2Fbackoffice%2Fpurchases%2Fpurchase-010' => '/backoffice/purchases/returns?from=%2Fbackoffice%2Fpurchases%2Fpurchase-010',
            '/stock-alerts?from=%2Fbackoffice%2Freports%2Finventory' => '/backoffice/reports/inventory/stock-alerts?from=%2Fbackoffice%2Freports%2Finventory',
            '/users?from=%2Fbackoffice%2Fdashboard' => '/backoffice/users?from=%2Fbackoffice%2Fdashboard',
            '/settings/tax-promo?from=%2Fbackoffice%2Fdashboard' => '/backoffice/settings/tax-promo?from=%2Fbackoffice%2Fdashboard',
            '/settings/inventory-valuation?from=%2Fbackoffice%2Fdashboard' => '/backoffice/settings/inventory-valuation?from=%2Fbackoffice%2Fdashboard',
            '/settings/inventory-valuation-audit?from=%2Fbackoffice%2Fsettings%2Finventory-valuation' => '/backoffice/settings/inventory-valuation-audit?from=%2Fbackoffice%2Fsettings%2Finventory-valuation',
            '/settings/observability?from=%2Fbackoffice%2Fdashboard' => '/backoffice/settings/observability?from=%2Fbackoffice%2Fdashboard',
            '/finance-summary?from=%2Fbackoffice%2Fdashboard' => '/backoffice/finance/summary?from=%2Fbackoffice%2Fdashboard',
            '/lock-periods?from=%2Fbackoffice%2Ffinance%2Fsummary' => '/backoffice/finance/lock-periods?from=%2Fbackoffice%2Ffinance%2Fsummary',
            '/stock-transfers?from=%2Fbackoffice%2Finventory' => '/backoffice/inventory/stock-transfers?from=%2Fbackoffice%2Finventory',
            '/production-batches?from=%2Fbackoffice%2Finventory' => '/backoffice/inventory/production-batches?from=%2Fbackoffice%2Finventory',
            '/production-batches/batch-001?from=%2Fbackoffice%2Finventory%2Fproduction-batches' => '/backoffice/inventory/production-batches/batch-001?from=%2Fbackoffice%2Finventory%2Fproduction-batches',
            '/loyalty?from=%2Fbackoffice%2Fdashboard' => '/backoffice/loyalty?from=%2Fbackoffice%2Fdashboard',
        ];

        foreach ($cases as $source => $target) {
            $response = $this->actingAs($user)
                ->withSession(['auth_portal' => 'backoffice'])
                ->get($source);

            $response->assertRedirect($target);
        }
    }

    private function ownerBackofficeShortcut(string $currentPath, string $targetPath): string
    {
        $separator = str_contains($targetPath, '?') ? '&' : '?';

        return '/portal/switch/backoffice?next='.rawurlencode($targetPath.$separator.'from='.rawurlencode($currentPath));
    }

    private function openShiftFor(Tenant $tenant, User $user): void
    {
        $branchId = (string) Str::uuid();

        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        DB::table('shifts')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'opened_at' => now('UTC')->subHour(),
            'closed_at' => null,
            'opening_cash' => 0,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);
    }

    private function createWebActor(string $role, string $email, string $name = 'Portal User'): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant '.Str::upper(Str::random(4)),
            'timezone' => 'Asia/Jakarta',
            'inventory_valuation_method' => 'average',
            'plan' => 'P',
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
