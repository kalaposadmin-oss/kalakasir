<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\LegacyWebRedirectController;
use App\Support\NavigationRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NavigationArchitectureEnforcementTest extends TestCase
{
    public function test_all_non_legacy_web_routes_use_area_prefixes(): void
    {
        $allowed = array_keys(config('navigation_redirects', []));
        $allowed = array_merge($allowed, [
            '/up',
            '/sanctum/csrf-cookie',
            '/',
            '/register',
            '/register/step-1',
            '/register/step-2',
            '/register/step-3',
            '/register/step-4',
            '/register/review',
            '/register/complete',
            '/order',
            '/order/review',
            '/order/submit',
            '/order/success/{orderCode}',
            '/order/monitor',
            '/monitor/orders',
            '/monitor/orders/data',
            '/admin-order/{code}',
            '/admin-order/{code}/pin',
            '/admin-order/{code}/edit',
            '/admin-order/{code}/shipping',
            '/admin-order/{code}/final-review',
            '/admin-order/{code}/final-review/send-payment-message',
            '/admin-order/{code}/mark-paid',
            '/admin-order/{code}/complete',
            '/admin-order/{code}/cancel',
            '/menu',
            '/menu/{section}',
            '/backoffice',
            '/login',
            '/logout',
            '/portal/switch/{portal}',
            '/sales-detail',
            '/receipt-preview',
            '/sales-history',
            '/kasir',
            '/payment-method',
            '/sales-void-refund',
            '/shift/open',
            '/shift/close',
            '/shift/close-summary',
            '/products/new',
            '/products/detail',
            '/purchase-returns',
            '/receiving',
            '/receiving/action',
            '/products/{product}/edit',
            '/categories/new',
            '/categories/{category}/edit',
            '/branches/edit',
            '/purchases',
            '/purchases/new',
            '/purchases/detail',
            '/stock-transfers',
            '/production',
            '/production-batches',
            '/production-batches/{batch}',
            '/stock-card',
            '/reports/inventory',
            '/stock-adjustment',
            '/analytics',
            '/customers',
            '/suppliers',
            '/materials',
            '/products',
            '/categories',
            '/settings/chart-of-accounts',
            '/settings/chart-of-accounts/{account}',
            '/settings/bank-accounts/{account}',
            '/stock-alerts',
            '/settings/tax-promo',
            '/settings/inventory-valuation',
            '/settings/inventory-valuation-audit',
            '/settings/observability',
            '/settings/payment-settings',
            '/finance-summary',
            '/lock-periods',
            '/loyalty',
            '/pos',
            '/pos/login',
            '/backoffice/login',
            '/superadmin',
            '/platform/dashboard',
            '/platform/system-health',
            '/platform/system-health/incidents',
            '/platform/system-health/incidents/{eventId}',
            '/platform/system-health/tenants',
            '/storage/{path}',
        ]);

        foreach (app('router')->getRoutes() as $route) {
            $uri = '/'.ltrim($route->uri(), '/');

            if (in_array($uri, $allowed, true)) {
                continue;
            }

            if ($this->usesController($route->getActionName(), LegacyWebRedirectController::class)) {
                continue;
            }

            if (str_starts_with($uri, '/api/') || str_starts_with($uri, '/sanctum/')) {
                continue;
            }

            $this->assertTrue(
                str_starts_with($uri, '/pos/')
                || str_starts_with($uri, '/admin/')
                || str_starts_with($uri, '/backoffice/')
                || str_starts_with($uri, '/superadmin/'),
                "Route [{$uri}] is outside the enforced area prefixes."
            );
        }
    }

    private function usesController(string $action, string $controller): bool
    {
        return str_starts_with($action, $controller.'@');
    }

    public function test_navigation_registry_items_are_valid(): void
    {
        foreach (config('navigation.items', []) as $key => $item) {
            $this->assertSame($key, $item['key']);
            $this->assertArrayHasKey('area', $item);
            $this->assertArrayHasKey('route', $item);
            $this->assertArrayHasKey('roles', $item);
            $this->assertArrayHasKey('feature', $item);
            $this->assertStringStartsWith('/'.$item['area'].'/', $item['route']);
        }
    }

    public function test_pos_drawer_registry_has_no_backoffice_leak(): void
    {
        $sections = NavigationRegistry::forArea('pos', 'owner', static fn (?string $feature): bool => true);

        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $this->assertFalse(
                    str_starts_with($item['href'], '/backoffice/'),
                    "POS drawer leaked backoffice link [{$item['href']}]."
                );
            }
        }
    }

    public function test_generic_drawer_partial_does_not_hardcode_backoffice_links(): void
    {
        $contents = file_get_contents(resource_path('views/partials/drawer.blade.php'));

        $this->assertStringContainsString('NavigationRegistry::forArea', $contents);
        $this->assertStringNotContainsString('/backoffice/', $contents);
    }

    public function test_named_routes_used_by_registry_resolve_to_expected_uris(): void
    {
        $cases = [
            '/pos/products',
            '/pos/inventory',
            '/pos/purchases',
            '/backoffice/settings/observability',
        ];

        foreach ($cases as $uri) {
            $route = Route::getRoutes()->match(Request::create($uri, 'GET'));

            $this->assertSame($uri, '/'.$route->uri());
        }
    }
}
