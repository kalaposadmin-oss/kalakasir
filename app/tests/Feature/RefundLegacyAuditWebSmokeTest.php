<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RefundLegacyAuditWebSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_legacy_audit_page_renders_core_sections(): void
    {
        $route = Route::getRoutes()->match(\Illuminate\Http\Request::create('/backoffice/settings/refund-legacy-audit', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/refund-legacy-audit.blade.php'));

        $this->assertContains('web_access:backoffice,admin|owner|superadmin', $middleware);
        $this->assertNotContains('web_access:backoffice,admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('refund-legacy-audit'));
        $this->assertStringContainsString('Audit Refund Legacy', $contents);
        $this->assertStringContainsString('btn-refresh', $contents);
        $this->assertStringContainsString('btn-export', $contents);
        $this->assertStringContainsString('btn-remediate-selected', $contents);
        $this->assertStringContainsString('btn-remediate-filter', $contents);
        $this->assertStringContainsString('btn-remediate-row', $contents);
        $this->assertStringContainsString('select-all-resolvable', $contents);
        $this->assertStringContainsString('row-selector', $contents);
        $this->assertStringContainsString('legacy-badge', $contents);
        $this->assertStringContainsString('history-list', $contents);
        $this->assertStringContainsString('btn-history-export', $contents);
        $this->assertStringContainsString('btn-history-prev', $contents);
        $this->assertStringContainsString('btn-history-next', $contents);
        $this->assertStringContainsString('history-actor-filter', $contents);
        $this->assertStringContainsString('history-date-from-filter', $contents);
        $this->assertStringContainsString('history-date-to-filter', $contents);
        $this->assertStringContainsString('btn-prev-page', $contents);
        $this->assertStringContainsString('btn-next-page', $contents);
        $this->assertStringContainsString('/api/v1/settings/refund-legacy-audit/history', $contents);
        $this->assertStringContainsString('/api/v1/settings/refund-legacy-audit/history/export', $contents);
        $this->assertStringContainsString('branch-filter', $contents);
        $this->assertStringContainsString('/api/v1/branches', $contents);
        $this->assertStringContainsString('date-from-filter', $contents);
        $this->assertStringContainsString('date-to-filter', $contents);
        $this->assertStringContainsString('refund-legacy-list', $contents);
    }
}