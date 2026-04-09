<?php

namespace Tests\Feature;

use App\Jobs\ExportValuationComparisonJob;
use App\Jobs\RunValuationRebuildJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class ValuationAsyncOperationsTest extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-async-valuation@example.com', 'Admin Async Valuation');
        DB::table('tenants')->where('id', $this->tenant->id)->update(['plan' => 'P']);
        DB::table('subscriptions')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
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

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_trigger_rebuild_queues_job_and_status_reflects_lock(): void
    {
        Queue::fake();

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/settings/valuation-rebuild', [
                'trigger' => 'test-suite',
                'idempotency_key' => 'rebuild-test-suite',
            ]);

        $response->assertStatus(202)
            ->assertJson([
                'status' => 'queued',
            ]);

        Queue::assertPushed(RunValuationRebuildJob::class, 1);

        $status = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/settings/valuation-rebuild-status');

        $status->assertStatus(200)
            ->assertJson([
                'locked' => true,
            ]);

        $status->assertJsonPath('meta.runtime_ms', fn ($value) => is_int($value) || is_float($value));

        $duplicate = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/settings/valuation-rebuild', [
                'trigger' => 'test-suite',
                'idempotency_key' => 'rebuild-test-suite',
            ]);

        $duplicate->assertStatus(423);
    }

    public function test_cancel_rebuild_marks_request_cancelled(): void
    {
        Queue::fake();

        $queued = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/settings/valuation-rebuild', [
                'trigger' => 'test-cancel',
            ]);

        $requestId = (string) $queued->json('request_id');

        $cancel = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/settings/valuation-rebuild-cancel', [
                'request_id' => $requestId,
                'reason' => 'cancel from test',
            ]);

        $cancel->assertStatus(200)
            ->assertJson([
                'status' => 'cancelled',
                'request_id' => $requestId,
            ]);

        $status = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/settings/valuation-rebuild-status');

        $status->assertStatus(200)
            ->assertJsonPath('locked', false)
            ->assertJsonPath('meta.status', 'cancelled')
            ->assertJsonPath('meta.request_id', $requestId)
            ->assertJsonPath('meta.runtime_ms', fn ($value) => is_int($value) || is_float($value));

        $retry = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/settings/valuation-rebuild-retry', [
                'request_id' => $requestId,
            ]);

        $retry->assertStatus(202)
            ->assertJson([
                'status' => 'queued',
                'retried_from' => $requestId,
            ]);
    }

    public function test_cancel_export_marks_request_cancelled_when_still_queued(): void
    {
        Queue::fake();

        $queued = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/settings/valuation-reports/export', [
                'limit' => 10,
            ]);

        $queued->assertStatus(202);
        Queue::assertPushed(ExportValuationComparisonJob::class, 1);

        $requestId = (string) $queued->json('request_id');

        $cancel = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/settings/valuation-reports/export-cancel', [
                'request_id' => $requestId,
                'reason' => 'cancel export from test',
            ]);

        $cancel->assertStatus(200)
            ->assertJson([
                'status' => 'cancelled',
                'request_id' => $requestId,
            ]);

        $status = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/settings/valuation-reports/export-status/'.$requestId);

        $status->assertStatus(200)
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('runtime_ms', fn ($value) => is_int($value) || is_float($value));

        $retry = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/settings/valuation-reports/export-retry', [
                'request_id' => $requestId,
            ]);

        $retry->assertStatus(202)
            ->assertJson([
                'status' => 'queued',
                'retried_from' => $requestId,
            ]);
    }

    public function test_smoke_cycle_queue_cancel_and_status_consistency(): void
    {
        Queue::fake();

        $headers = $this->tenantHeaders($this->tenant->id);

        $rebuildQueued = $this->withHeaders($headers)->postJson('/api/v1/settings/valuation-rebuild', [
            'trigger' => 'smoke-cycle',
        ]);
        $rebuildQueued->assertStatus(202);
        $rebuildRequestId = (string) $rebuildQueued->json('request_id');

        $this->withHeaders($headers)->postJson('/api/v1/settings/valuation-rebuild-cancel', [
            'request_id' => $rebuildRequestId,
        ])->assertStatus(200)->assertJsonPath('status', 'cancelled');

        $this->withHeaders($headers)->getJson('/api/v1/settings/valuation-rebuild-status')
            ->assertStatus(200)
            ->assertJsonPath('meta.status', 'cancelled');

        $exportQueued = $this->withHeaders($headers)->postJson('/api/v1/settings/valuation-reports/export', [
            'limit' => 10,
        ]);
        $exportQueued->assertStatus(202);
        $exportRequestId = (string) $exportQueued->json('request_id');

        $this->withHeaders($headers)->postJson('/api/v1/settings/valuation-reports/export-cancel', [
            'request_id' => $exportRequestId,
        ])->assertStatus(200)->assertJsonPath('status', 'cancelled');

        $this->withHeaders($headers)->getJson('/api/v1/settings/valuation-reports/export-status/'.$exportRequestId)
            ->assertStatus(200)
            ->assertJsonPath('status', 'cancelled');
    }
}
