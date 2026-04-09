<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ValuationReportController;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class ValuationReportTenantScopingTest extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    private Tenant $tenant;

    /** @var array<int, string> */
    private array $createdReportPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant] = $this->bootApiActor('admin', 'admin-valuation-report-scope@example.com', 'Admin Valuation Scope');
        $this->enableValuationAccess($this->tenant->id);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdReportPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_export_status_requires_resolved_tenant_context_before_request_lookup(): void
    {
        $requestId = $this->seedExportRequest($this->tenant->id);
        TenantContext::clear();

        $response = app(ValuationReportController::class)->exportStatus(
            Request::create('/api/v1/settings/valuation-reports/export-status/'.$requestId, 'GET'),
            $requestId,
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('TENANT_NOT_RESOLVED', $response->getData(true)['code'] ?? null);
    }

    public function test_download_keeps_404_concealment_for_missing_tenant_context(): void
    {
        $filename = $this->createTenantReportFile($this->tenant->id, 'scope-fixture.csv');
        TenantContext::clear();

        $this->expectException(NotFoundHttpException::class);

        app(ValuationReportController::class)->download(
            Request::create('/api/v1/settings/valuation-reports/'.$filename, 'GET'),
            $filename,
        );
    }

    public function test_download_keeps_other_tenant_report_hidden(): void
    {
        $filename = $this->createTenantReportFile($this->tenant->id, 'scope-fixture.csv');

        [$otherTenant] = $this->bootApiActor('admin', 'admin-valuation-report-other@example.com', 'Admin Valuation Other');
        $this->enableValuationAccess($otherTenant->id);

        $this->withHeaders($this->tenantHeaders($otherTenant->id))
            ->get('/api/v1/settings/valuation-reports/'.$filename)
            ->assertStatus(404);
    }

    public function test_other_tenant_cannot_access_or_mutate_export_request(): void
    {
        $requestId = $this->seedExportRequest($this->tenant->id, [
            'status' => 'failed',
            'finished_at' => now('UTC')->toIso8601String(),
        ]);

        [$otherTenant] = $this->bootApiActor('admin', 'admin-valuation-report-cross@example.com', 'Admin Valuation Cross');
        $this->enableValuationAccess($otherTenant->id);

        $headers = $this->tenantHeaders($otherTenant->id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/settings/valuation-reports/export-status/'.$requestId)
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->withHeaders($headers)
            ->postJson('/api/v1/settings/valuation-reports/export-cancel', [
                'request_id' => $requestId,
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->withHeaders($headers)
            ->postJson('/api/v1/settings/valuation-reports/export-retry', [
                'request_id' => $requestId,
            ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'REQUEST_NOT_FOUND');
    }

    private function enableValuationAccess(string $tenantId): void
    {
        DB::table('tenants')->where('id', $tenantId)->update(['plan' => 'P']);
        DB::table('subscriptions')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'plan' => 'P',
            'status' => 'active',
            'started_at' => now('UTC')->subMonth(),
            'expires_at' => now('UTC')->addMonth(),
            'current_period_start' => now('UTC')->subMonth(),
            'current_period_end' => now('UTC')->addMonth(),
            'is_current' => true,
            'created_at' => now('UTC')->subMonth(),
            'updated_at' => now('UTC'),
        ]);
    }

    private function seedExportRequest(string $tenantId, array $overrides = []): string
    {
        $requestId = (string) Str::uuid();

        Cache::put('valuation:export:request:'.$requestId, [
            'status' => 'queued',
            'request_id' => $requestId,
            'tenant_id' => $tenantId,
            'actor_id' => null,
            'queued_at' => now('UTC')->toIso8601String(),
            'params' => [
                'from' => '2026-02-01',
                'to' => '2026-02-27',
                'sku_prefix' => 'SKU-',
                'limit' => 25,
            ],
            ...$overrides,
        ], 60 * 60);

        return $requestId;
    }

    private function createTenantReportFile(string $tenantId, string $suffix): string
    {
        $dir = storage_path('app/reports');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filename = 'valuation-comparison-'.$tenantId.'-'.Str::slug(pathinfo($suffix, PATHINFO_FILENAME));
        $extension = pathinfo($suffix, PATHINFO_EXTENSION) ?: 'csv';
        $filename .= '.'.$extension;
        $path = $dir.DIRECTORY_SEPARATOR.$filename;

        file_put_contents($path, "sku,delta\nSKU-001,0\n");
        $this->createdReportPaths[] = $path;

        return $filename;
    }
}