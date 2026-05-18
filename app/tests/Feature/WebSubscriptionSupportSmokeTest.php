<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WebSubscriptionSupportSmokeTest extends TestCase
{
    public function test_subscription_history_route_and_view_expose_audit_navigation_and_paging_controls(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/settings/subscription/history', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/subscription-history.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.subscription_billing', $middleware);
        $this->assertTrue(view()->exists('subscription-history'));
        $this->assertStringContainsString('Riwayat Langganan', $contents);
        $this->assertStringContainsString('Audit Trail', $contents);
        $this->assertStringContainsString('btn-prev', $contents);
        $this->assertStringContainsString('btn-next', $contents);
        $this->assertStringContainsString('/api/v1/subscription/audit-logs', $contents);
    }

    public function test_subscription_change_route_and_view_expose_preview_and_submit_controls(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/settings/subscription/change', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/subscription-change.blade.php'));

        $this->assertContains('web_access:backoffice,admin|owner|superadmin,settings.subscription_billing', $middleware);
        $this->assertTrue(view()->exists('subscription-change'));
        $this->assertStringContainsString('Ubah Paket Langganan', $contents);
        $this->assertStringContainsString('Preview Dampak', $contents);
        $this->assertStringContainsString('btn-submit', $contents);
        $this->assertStringContainsString('/api/v1/subscription/impact-preview', $contents);
        $this->assertStringContainsString('/api/v1/subscription/plan', $contents);
    }

    public function test_subscription_features_route_and_view_expose_filters_and_entitlement_summary(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/settings/subscription/features', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/subscription-features.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.subscription_billing', $middleware);
        $this->assertTrue(view()->exists('subscription-features'));
        $this->assertStringContainsString('Entitlement', $contents);
        $this->assertStringContainsString('btn-apply', $contents);
        $this->assertStringContainsString('locked-only', $contents);
        $this->assertStringContainsString('summary', $contents);
        $this->assertStringContainsString('/api/v1/subscription/entitlements', $contents);
    }
}