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

        $this->assertContains('web_access:backoffice,admin|owner|superadmin,settings.staff_basic', $middleware);
        $this->assertNotContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('users'));
        $this->assertStringContainsString('Manajemen Pengguna', $contents);
        $this->assertStringContainsString('/backoffice/dashboard', $contents);
        $this->assertStringContainsString('Tambah pengguna baru', $contents);
        $this->assertStringContainsString('Role dan Hak Akses', $contents);
        $this->assertStringContainsString('add-user-quick', $contents);
    }

    public function test_tax_promo_route_and_view_expose_tax_and_promo_sections(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/settings/tax-promo', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/tax-promo.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.tax', $middleware);
        $this->assertNotContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('tax-promo'));
        $this->assertStringContainsString('Pajak dan Promo', $contents);
        $this->assertStringContainsString('/backoffice/dashboard', $contents);
        $this->assertStringContainsString('Pengaturan Pajak', $contents);
        $this->assertStringContainsString('Manajemen Promo', $contents);
        $this->assertStringContainsString('Tambah Pajak Baru', $contents);
    }

    public function test_menu_drawer_uses_live_settings_keys_for_users_and_tax_entries(): void
    {
        $contents = file_get_contents(resource_path('views/partials/menu-drawer.blade.php'));

        $this->assertStringContainsString("canFeature('settings.staff_basic')", $contents);
        $this->assertStringContainsString("canFeature('settings.tax')", $contents);
        $this->assertStringNotContainsString('settings.roles_permissions', $contents);
        $this->assertStringNotContainsString('settings.promo_default', $contents);
    }

    public function test_observability_route_and_view_expose_health_and_anomaly_sections(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/settings/observability', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/observability.blade.php'));

        $this->assertContains('web_access:backoffice,admin|owner|superadmin,settings.observability', $middleware);
        $this->assertTrue(view()->exists('observability'));
        $this->assertStringContainsString('Observability', $contents);
        $this->assertStringContainsString('/backoffice/dashboard', $contents);
        $this->assertStringNotContainsString('/backoffice/settings/inventory-valuation', $contents);
        $this->assertStringContainsString('Queue Health', $contents);
        $this->assertStringContainsString('Anomaly Summary', $contents);
        $this->assertStringContainsString('Production Batch Variance', $contents);
        $this->assertStringContainsString('production-batch-variance-filter', $contents);
        $this->assertStringContainsString('anomaly-context-box', $contents);
        $this->assertStringContainsString('reference_id', $contents);
        $this->assertStringContainsString('observability-branch-filter', $contents);
        $this->assertStringContainsString('observability-location-filter', $contents);
        $this->assertStringContainsString('window.PosUi.getBootstrap', $contents);
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

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,finance.summary_basic', $middleware);
        $this->assertTrue(view()->exists('finance-summary'));
        $this->assertStringContainsString('Laporan Keuangan', $contents);
        $this->assertStringContainsString('/backoffice/dashboard', $contents);
        $this->assertStringContainsString('Laba Rugi', $contents);
        $this->assertStringContainsString('Neraca', $contents);
        $this->assertStringContainsString('net-profit', $contents);
        $this->assertStringContainsString('balance-section', $contents);
        $this->assertStringContainsString('report-finality-banner', $contents);
        $this->assertStringContainsString('Belum Final', $contents);
        $this->assertStringContainsString('closing_status', $contents);
        $this->assertStringContainsString("canAccessFeature(\$financeSummaryTenant, 'reports.download_pdf')", $contents);
        $this->assertStringContainsString('finance-summary-download-upgrade-profit', $contents);
    }

    public function test_bank_accounts_route_and_view_expose_cash_and_bank_management(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/settings/chart-of-accounts', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/chart-of-accounts.blade.php'));

        $this->assertContains('web_access:backoffice,admin|owner|superadmin,finance.bank_accounts', $middleware);
        $this->assertTrue(view()->exists('chart-of-accounts'));
        $this->assertStringContainsString('Akun Bank & Kas', $contents);
        $this->assertStringContainsString('Tambah Akun', $contents);
        $this->assertStringContainsString('Akun Kas &amp; Bank', $contents);
    }

    public function test_payment_settings_route_unifies_pos_accounts_and_customer_instructions(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/settings/payment-settings', 'GET'));
        $postRoute = Route::getRoutes()->match(Request::create('/backoffice/settings/payment-settings', 'POST'));
        $middleware = $route->gatherMiddleware();
        $postMiddleware = $postRoute->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/payment-settings.blade.php'));
        $menuContents = file_get_contents(resource_path('views/partials/menu-drawer.blade.php'));

        $this->assertContains('web_access:backoffice,admin|owner|superadmin,finance.bank_accounts', $middleware);
        $this->assertContains('web_access:backoffice,admin|owner|superadmin,finance.bank_accounts', $postMiddleware);
        $this->assertTrue(view()->exists('payment-settings'));
        $this->assertStringContainsString('Pembayaran & Rekening', $contents);
        $this->assertStringContainsString('name="cash_account_id"', $contents);
        $this->assertStringContainsString('name="bank_account_id"', $contents);
        $this->assertStringContainsString('Instruksi transfer dan QRIS', $contents);
        $this->assertStringContainsString('/backoffice/settings/chart-of-accounts', $contents);
        $this->assertStringContainsString('Pembayaran & Rekening', $menuContents);
        $this->assertStringNotContainsString("'label' => 'Akun Kas & Bank'", $menuContents);
    }

    public function test_opening_balance_route_and_view_expose_draft_workflow(): void
    {
        $route = Route::getRoutes()->match(Request::create('/admin/opening-balance', 'GET'));
        $draftRoute = Route::getRoutes()->match(Request::create('/admin/opening-balance', 'POST'));
        $historyRoute = Route::getRoutes()->match(Request::create('/admin/opening-balance/history', 'GET'));
        $middleware = $route->gatherMiddleware();
        $draftMiddleware = $draftRoute->gatherMiddleware();
        $historyMiddleware = $historyRoute->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/opening-balance.blade.php'));
        $menuContents = file_get_contents(resource_path('views/partials/menu-drawer.blade.php'));

        $this->assertContains('canonical_portal_host:backoffice', $middleware);
        $this->assertContains('web_access:backoffice,admin|owner|superadmin,finance.bank_accounts', $middleware);
        $this->assertContains('canonical_portal_host:backoffice', $draftMiddleware);
        $this->assertContains('web_access:backoffice,admin|owner|superadmin,finance.bank_accounts', $draftMiddleware);
        $this->assertContains('canonical_portal_host:backoffice', $historyMiddleware);
        $this->assertContains('web_access:backoffice,admin|owner|superadmin,finance.bank_accounts', $historyMiddleware);
        $this->assertTrue(view()->exists('opening-balance'));
        $this->assertStringContainsString('Saldo Awal', $contents);
        $this->assertStringContainsString('Kas Masuk untuk transaksi setelah aplikasi aktif', $contents);
        $this->assertStringContainsString('/admin/opening-balance', $contents);
        $this->assertStringContainsString('/admin/opening-balance/current', $contents);
        $this->assertStringContainsString('/admin/opening-balance/history', $contents);
        $this->assertStringContainsString('/admin/opening-balance/validate', $contents);
        $this->assertStringContainsString('/admin/opening-balance/post', $contents);
        $this->assertStringContainsString('/admin/opening-balance/${encodeURIComponent(state.session.id)}/stock/${encodeURIComponent(state.editingStockLineId)}', $contents);
        $this->assertStringContainsString('/admin/opening-balance/${encodeURIComponent(state.session.id)}/fund/${encodeURIComponent(state.editingFundLineId)}', $contents);
        $this->assertStringContainsString('/admin/opening-balance/${encodeURIComponent(state.session.id)}/stock/${encodeURIComponent(lineId)}', $contents);
        $this->assertStringContainsString('/admin/opening-balance/${encodeURIComponent(state.session.id)}/fund/${encodeURIComponent(lineId)}', $contents);
        $this->assertStringContainsString('/api/v1/bootstrap', $contents);
        $this->assertStringContainsString('/api/v1/chart-of-accounts', $contents);
        $this->assertStringContainsString('/api/v1/products?lite=1', $contents);
        $this->assertStringContainsString('Saldo Awal', $menuContents);
        $this->assertStringContainsString('/admin/opening-balance', $menuContents);
    }

    public function test_inventory_method_basic_route_and_view_expose_method_selection_controls(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/settings/inventory-method', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/inventory-method-basic.blade.php'));

        $this->assertContains('web_access:backoffice,admin|owner|superadmin,settings.inventory_method_basic', $middleware);
        $this->assertNotContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('inventory-method-basic'));
        $this->assertStringContainsString('Metode Persediaan Dasar', $contents);
        $this->assertStringContainsString('FIFO', $contents);
        $this->assertStringContainsString('Average Cost', $contents);
        $this->assertStringContainsString('window.PosUi.getBootstrap', $contents);
        $this->assertStringContainsString('/api/v1/settings/inventory-valuation-method', $contents);
    }

    public function test_lock_periods_route_and_view_expose_period_creation_and_toggle_controls(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/finance/lock-periods', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/lock-periods.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,finance.lock_period', $middleware);
        $this->assertNotContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('lock-periods'));
        $this->assertStringContainsString('Tutup Buku Bulanan', $contents);
        $this->assertStringContainsString('/backoffice/finance/summary', $contents);
        $this->assertStringContainsString('Langkah Tutup Buku', $contents);
        $this->assertStringContainsString('closing-assistant-banner', $contents);
        $this->assertStringContainsString('Cara pakai checklist', $contents);
        $this->assertStringContainsString('Yang harus dilakukan:', $contents);
        $this->assertStringContainsString('Sudah saya cek', $contents);
        $this->assertStringContainsString('Centang semua langkah', $contents);
        $this->assertStringContainsString('close-status', $contents);
        $this->assertStringContainsString('Sedang mengecek ringkasan tutup buku', $contents);
        $this->assertStringContainsString('Sedang menutup periode dan menyimpan snapshot saldo', $contents);
        $this->assertStringContainsString('Berhasil tutup buku', $contents);
        $this->assertStringContainsString('/api/v1/lock-periods/assistant', $contents);
        $this->assertStringContainsString('Tutup Periode', $contents);
        $this->assertStringContainsString('/api/v1/lock-periods/preview', $contents);
        $this->assertStringContainsString('/api/v1/lock-periods/close', $contents);
        $this->assertStringContainsString("document.querySelector('meta[name=\"csrf-token\"]')", $contents);
        $this->assertStringContainsString("'X-CSRF-TOKEN': csrfToken", $contents);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $contents);
        $this->assertStringContainsString('headers: writeHeaders', $contents);
        $this->assertStringContainsString('/reopen', $contents);
        $this->assertStringContainsString('Data dicek:', $contents);
        $this->assertStringContainsString('Kalau salah:', $contents);
        $this->assertStringContainsString('step.action_label', $contents);
        $this->assertStringContainsString('Daftar Periode Akuntansi', $contents);
        $this->assertStringContainsString('create-period', $contents);
        $this->assertStringContainsString('toggle-checkbox', $contents);
    }
}
