<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WebDashboardSmokeTest extends TestCase
{
    public function test_pos_dashboard_route_and_view_expose_core_actions(): void
    {
        $route = Route::getRoutes()->match(Request::create('/pos/dashboard', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/pos-dashboard.blade.php'));
        $menuDrawerContents = file_get_contents(resource_path('views/partials/menu-drawer.blade.php'));

        $this->assertContains('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('pos-dashboard'));
        $this->assertStringContainsString('Dashboard Kasir', $contents);
        $this->assertStringContainsString('/pos/checkout', $contents);
        $this->assertStringContainsString('/pos/history', $contents);
        $this->assertStringContainsString('/pos/shifts/open', $contents);
        $this->assertStringContainsString('/pos/shifts/close', $contents);
        $this->assertStringContainsString('$posPortalOriginPath = $menuCurrentPath !== \'/\' ? normalize_pos_home_url($menuCurrentPath) : $posHomeHref;', $menuDrawerContents);
        $this->assertStringContainsString('\'href\' => $posBackofficeHref(\'/backoffice/products\')', $menuDrawerContents);
        $this->assertStringContainsString('\'href\' => $posBackofficeHref(\'/backoffice/dashboard\')', $menuDrawerContents);
    }

    public function test_backoffice_dashboard_route_and_view_expose_primary_navigation(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/dashboard', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/backoffice-dashboard.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('backoffice-dashboard'));
        $this->assertStringContainsString('Beranda Operasional', $contents);
        $this->assertStringContainsString('Pusat Akses Cepat', $contents);
        $this->assertStringContainsString('backoffice-app-tile', $contents);
        $this->assertStringContainsString('/portal/switch/pos?next=/pos/checkout', $contents);
        $this->assertStringContainsString('/backoffice/reports/inventory', $contents);
        $this->assertStringContainsString('/backoffice/settings/subscription', $contents);
        $this->assertStringContainsString('/backoffice/reports/sales', $contents);
    }

    public function test_inventory_report_route_and_view_expose_export_and_audit_sections(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/reports/inventory', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/reports-inventory.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,reports.inventory_view', $middleware);
        $this->assertTrue(view()->exists('reports-inventory'));
        $this->assertStringContainsString('Laporan Persediaan', $contents);
        $this->assertStringContainsString('/api/v1/reports/inventory/export/csv', $contents);
        $this->assertStringContainsString('/api/v1/reports/inventory/export/pdf', $contents);
        $this->assertStringContainsString('Audit Trail Operasional', $contents);
        $this->assertStringContainsString('Preview Reorder', $contents);
    }

    public function test_subscription_current_route_and_view_expose_management_links(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/settings/subscription', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/subscription-current.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.subscription_billing', $middleware);
        $this->assertTrue(view()->exists('subscription-current'));
        $this->assertStringContainsString('Langganan & Billing', $contents);
        $this->assertStringContainsString('/backoffice/settings/subscription/change', $contents);
        $this->assertStringContainsString('/backoffice/settings/subscription/features', $contents);
        $this->assertStringContainsString('/backoffice/settings/subscription/history', $contents);
        $this->assertStringContainsString('/backoffice/settings/subscription/invoices', $contents);
    }

    public function test_subscription_invoices_route_and_view_expose_detail_and_reconciliation_actions(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/settings/subscription/invoices', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/subscription-invoices.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.subscription_billing', $middleware);
        $this->assertTrue(view()->exists('subscription-invoices'));
        $this->assertStringContainsString('Invoice & Pembayaran', $contents);
        $this->assertStringContainsString('status-filter', $contents);
        $this->assertStringContainsString('/api/v1/subscription/invoices/', $contents);
        $this->assertStringContainsString('btn-mark-paid', $contents);
        $this->assertStringContainsString('Download PDF', $contents);
    }
}
