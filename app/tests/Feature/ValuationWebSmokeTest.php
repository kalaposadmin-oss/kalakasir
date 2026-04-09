<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ValuationWebSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_valuation_settings_page_renders_core_sections(): void
    {
        $route = Route::getRoutes()->match(\Illuminate\Http\Request::create('/backoffice/settings/inventory-valuation', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/inventory-valuation-settings.blade.php'));

        $this->assertContains('web_access:backoffice,admin|owner|superadmin,settings.inventory_valuation', $middleware);
        $this->assertTrue(view()->exists('inventory-valuation-settings'));
        $this->assertStringContainsString('Valuation Persediaan', $contents);
        $this->assertStringContainsString('btn-rebuild', $contents);
        $this->assertStringContainsString('btn-export', $contents);
        $this->assertStringContainsString('btn-rebuild-retry', $contents);
        $this->assertStringContainsString('btn-export-retry', $contents);
    }

    public function test_inventory_valuation_audit_page_renders_core_sections(): void
    {
        $route = Route::getRoutes()->match(\Illuminate\Http\Request::create('/backoffice/settings/inventory-valuation-audit', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/inventory-valuation-audit.blade.php'));

        $this->assertContains('web_access:backoffice,admin|owner|superadmin,settings.inventory_valuation', $middleware);
        $this->assertTrue(view()->exists('inventory-valuation-audit'));
        $this->assertStringContainsString('Audit Valuation', $contents);
        $this->assertStringContainsString('btn-refresh', $contents);
        $this->assertStringContainsString('log-list', $contents);
    }

    public function test_settings_menu_contains_valuation_entries(): void
    {
        $contents = file_get_contents(resource_path('views/inventory-valuation-settings.blade.php'));

        $this->assertStringContainsString('Valuation Persediaan', $contents);
        $this->assertStringContainsString('Buka Dashboard Audit Valuation', $contents);
        $this->assertStringContainsString('/backoffice/settings/inventory-valuation-audit', $contents);
    }
}
