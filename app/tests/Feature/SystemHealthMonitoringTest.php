<?php

namespace Tests\Feature;

use App\Models\SystemHealthEvent;
use App\Services\SystemHealthEventService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class SystemHealthMonitoringTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_record_or_bump_reuses_open_event_and_creates_occurrences(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'system-health-admin@example.test');

        $service = app(SystemHealthEventService::class);
        $service->recordOrBump('sales_cost_validate_failed', [
            'tenant_id' => $tenant->id,
            'entity_type' => 'sales_cost_scope',
            'entity_id' => 'scope-a',
            'summary' => 'validate failed',
            'payload' => ['scope' => ['tenant_id' => $tenant->id]],
            'dedupe_key' => 'sales_cost_validate_failed:scope-a',
        ]);
        $service->recordOrBump('sales_cost_validate_failed', [
            'tenant_id' => $tenant->id,
            'entity_type' => 'sales_cost_scope',
            'entity_id' => 'scope-a',
            'summary' => 'validate failed again',
            'payload' => ['scope' => ['tenant_id' => $tenant->id], 'attempt' => 2],
            'dedupe_key' => 'sales_cost_validate_failed:scope-a',
        ]);

        $this->assertSame(1, SystemHealthEvent::query()->count());
        $this->assertSame(2, DB::table('system_health_event_occurrences')->count());
        $this->assertSame(2, (int) SystemHealthEvent::query()->value('occurrence_count'));
    }

    public function test_critical_event_triggers_email_and_slack_alert(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'system-health-alert@example.test');

        config()->set('system_health.alerts.enabled', true);
        config()->set('system_health.alerts.email_enabled', true);
        config()->set('system_health.alerts.email_to', ['ops@example.test']);
        config()->set('system_health.alerts.slack_enabled', true);
        config()->set('system_health.alerts.slack_webhook_url', 'https://alerts.example.test/system-health');

        Mail::shouldReceive('raw')->once();
        Http::fake([
            'https://alerts.example.test/*' => Http::response(['ok' => true], 200),
        ]);

        $event = app(SystemHealthEventService::class)->recordOrBump('sales_cost_missing_aggregate', [
            'tenant_id' => $tenant->id,
            'entity_type' => 'sale',
            'entity_id' => 'sale-1',
            'summary' => 'missing aggregate',
            'payload' => ['sale_id' => 'sale-1'],
            'dedupe_key' => 'sales_cost_missing_aggregate:sale-1',
        ]);

        Http::assertSentCount(1);
        $this->assertNotNull($event->fresh()->last_alerted_at);
    }

    public function test_sales_cost_scan_command_creates_monitoring_event(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'system-health-scan@example.test');
        $branchId = $this->seedBranch($tenant->id);
        $shiftId = $this->seedShift($tenant->id, $branchId, $user->id);
        $this->seedCompletedSale($tenant->id, $branchId, $shiftId, $user->id, [
            'id' => '00000000-0000-0000-0000-000000000111',
            'cogs_total' => 0,
        ]);

        $this->artisan('system-health:scan-sales-cost', [
            '--tenant' => $tenant->id,
        ])->assertExitCode(1);

        $this->assertDatabaseHas('system_health_events', [
            'tenant_id' => $tenant->id,
            'event_name' => 'sales_cost_missing_aggregate',
            'status' => 'open',
        ]);
    }

    public function test_finance_summary_block_records_strict_reader_event(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'system-health-reader@example.test');
        $branchId = $this->seedBranch($tenant->id);
        $shiftId = $this->seedShift($tenant->id, $branchId, $user->id);
        $this->seedCompletedSale($tenant->id, $branchId, $shiftId, $user->id, [
            'id' => '00000000-0000-0000-0000-000000000112',
            'cogs_total' => 0,
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=today');

        $response->assertStatus(409)
            ->assertJsonPath('code', 'SALES_COST_AGGREGATE_REQUIRED');

        $this->assertDatabaseHas('system_health_events', [
            'tenant_id' => $tenant->id,
            'event_name' => 'strict_reader_blocked',
            'entity_id' => 'finance-summary',
        ]);
    }

    public function test_platform_summary_api_returns_unresolved_counts(): void
    {
        [$tenant] = $this->bootApiActor('superadmin', 'system-health-superadmin@example.test');

        app(SystemHealthEventService::class)->record('sales_cost_missing_aggregate', [
            'tenant_id' => $tenant->id,
            'entity_type' => 'sale',
            'entity_id' => 'sale-a',
            'summary' => 'missing aggregate',
            'payload' => ['sale_id' => 'sale-a'],
            'dedupe_key' => 'sales_cost_missing_aggregate:sale-a',
        ]);
        app(SystemHealthEventService::class)->record('strict_reader_blocked', [
            'tenant_id' => $tenant->id,
            'entity_type' => 'report_endpoint',
            'entity_id' => 'analytics',
            'summary' => 'analytics blocked',
            'payload' => ['endpoint' => 'analytics'],
            'dedupe_key' => 'strict_reader_blocked:analytics',
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/platform/system-health/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.unresolved_count', 2)
            ->assertJsonPath('data.by_severity.critical', 1)
            ->assertJsonPath('data.by_severity.high', 1)
            ->assertJsonPath('data.affected_tenant_count', 1);
    }

    public function test_platform_resolve_endpoint_closes_event(): void
    {
        [$tenant] = $this->bootApiActor('superadmin', 'system-health-resolve@example.test');

        $event = app(SystemHealthEventService::class)->record('sales_cost_validate_failed', [
            'tenant_id' => $tenant->id,
            'entity_type' => 'sales_cost_scope',
            'entity_id' => 'scope-x',
            'summary' => 'validate failed',
            'payload' => ['scope' => ['tenant_id' => $tenant->id]],
            'dedupe_key' => 'sales_cost_validate_failed:scope-x',
        ]);

        $response = $this->withHeaders(array_merge($this->tenantHeaders($tenant->id), [
            'Idempotency-Key' => 'resolve-'.$event->id,
        ]))->postJson('/api/v1/platform/system-health/events/'.$event->id.'/resolve', [
            'reason' => 'scan clean',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.action', 'system_health_event_resolved');

        $this->assertDatabaseHas('system_health_events', [
            'id' => $event->id,
            'status' => 'resolved',
        ]);
    }

    private function seedBranch(string $tenantId): string
    {
        $id = (string) Str::uuid();
        DB::table('branches')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'name' => 'Main Branch',
        ]);

        return $id;
    }

    private function seedShift(string $tenantId, string $branchId, string $userId): string
    {
        $id = (string) Str::uuid();
        DB::table('shifts')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'opened_at' => CarbonImmutable::now('UTC')->subHour(),
            'opening_cash' => 0,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        return $id;
    }

    private function seedCompletedSale(string $tenantId, string $branchId, string $shiftId, string $userId, array $overrides = []): string
    {
        $id = $overrides['id'] ?? (string) Str::uuid();
        DB::table('sales')->insert(array_merge([
            'id' => $id,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'shift_id' => $shiftId,
            'customer_id' => null,
            'subtotal' => 10000,
            'discount' => 0,
            'tax' => 0,
            'total' => 10000,
            'cogs_total' => 0,
            'status' => 'completed',
            'idempotency_key' => 'sale-'.$id,
            'created_by' => $userId,
            'voided_by' => null,
            'created_at' => CarbonImmutable::now('UTC'),
            'transaction_date' => CarbonImmutable::now('UTC')->toDateString(),
        ], $overrides));

        return $id;
    }
}