<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WebSettingsFinanceSmokeTest extends TestCase
{
    public function test_users_route_and_view_expose_staff_management_actions(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/users', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/users.blade.php'));

        $this->assertContains('web_access:backoffice,admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('users'));
        $this->assertStringContainsString('Manajemen Pengguna', $contents);
        $this->assertStringContainsString('/backoffice/dashboard', $contents);
        $this->assertStringContainsString('Tambah Pengguna Baru', $contents);
        $this->assertStringContainsString('Kelola Pengguna', $contents);
        $this->assertStringContainsString('add-user-quick', $contents);
    }

    public function test_tax_promo_route_and_view_expose_tax_and_promo_sections(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/settings/tax-promo', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/tax-promo.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('tax-promo'));
        $this->assertStringContainsString('Pajak dan Promo', $contents);
        $this->assertStringContainsString('/backoffice/dashboard', $contents);
        $this->assertStringContainsString('Pengaturan Pajak', $contents);
        $this->assertStringContainsString('Manajemen Promo', $contents);
        $this->assertStringContainsString('Tambah Pajak Baru', $contents);
    }

    public function test_observability_route_and_view_expose_health_and_anomaly_sections(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/settings/observability', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/observability.blade.php'));

        $this->assertContains('web_access:backoffice,admin|owner|superadmin,settings.observability', $middleware);
        $this->assertTrue(view()->exists('observability'));
        $this->assertStringContainsString('Observability', $contents);
        $this->assertStringContainsString('/backoffice/settings/inventory-valuation', $contents);
        $this->assertStringContainsString('Queue Health', $contents);
        $this->assertStringContainsString('Anomaly Summary', $contents);
        $this->assertStringContainsString('Production Batch Variance', $contents);
        $this->assertStringContainsString('production-batch-variance-filter', $contents);
        $this->assertStringContainsString('anomaly-context-box', $contents);
        $this->assertStringContainsString('reference_id', $contents);
        $this->assertStringContainsString('observability-branch-filter', $contents);
        $this->assertStringContainsString('observability-location-filter', $contents);
        $this->assertStringContainsString('/api/v1/bootstrap', $contents);
        $this->assertStringContainsString('branch_id', $contents);
        $this->assertStringContainsString('location_id', $contents);
        $this->assertStringContainsString('filter-type', $contents);
        $this->assertStringContainsString('Anomaly Actions', $contents);
        $this->assertStringContainsString('btn-refresh', $contents);
    }

    public function test_finance_summary_route_and_view_expose_profit_and_balance_sections(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/finance/summary', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/finance-summary.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('finance-summary'));
        $this->assertStringContainsString('Laporan Keuangan', $contents);
        $this->assertStringContainsString('/backoffice/dashboard', $contents);
        $this->assertStringContainsString('Laba Rugi', $contents);
        $this->assertStringContainsString('Neraca', $contents);
        $this->assertStringContainsString('net-profit', $contents);
        $this->assertStringContainsString('balance-section', $contents);
    }

    public function test_lock_periods_route_and_view_expose_period_creation_and_toggle_controls(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/finance/lock-periods', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/lock-periods.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('lock-periods'));
        $this->assertStringContainsString('Penguncian Periode', $contents);
        $this->assertStringContainsString('/backoffice/finance/summary', $contents);
        $this->assertStringContainsString('Tambah Periode Baru', $contents);
        $this->assertStringContainsString('Daftar Periode Akuntansi', $contents);
        $this->assertStringContainsString('create-period', $contents);
        $this->assertStringContainsString('toggle-checkbox', $contents);
    }
}