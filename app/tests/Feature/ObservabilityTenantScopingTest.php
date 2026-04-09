<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class ObservabilityTenantScopingTest extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant] = $this->bootApiActor('admin', 'admin-observability@example.com', 'Admin Observability');
        DB::table('tenants')->where('id', $this->tenant->id)->update(['plan' => 'P']);
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_observability_health_redacts_global_queue_metrics_and_keeps_tenant_scoped_analytics(): void
    {
        $otherTenant = Tenant::query()->create([
            'name' => 'Tenant B',
            'timezone' => 'UTC',
            'plan' => 'P',
        ]);

        $tenantBranchId = (string) Str::uuid();
        $otherBranchId = (string) Str::uuid();
        DB::table('branches')->insert([
            [
                'id' => $tenantBranchId,
                'tenant_id' => $this->tenant->id,
                'name' => 'Main Branch',
            ],
            [
                'id' => $otherBranchId,
                'tenant_id' => $otherTenant->id,
                'name' => 'Other Branch',
            ],
        ]);

        $now = CarbonImmutable::now('UTC');

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'GlobalJob'], JSON_UNESCAPED_SLASHES),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now->timestamp,
            'created_at' => $now->timestamp,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'FailedGlobalJob'], JSON_UNESCAPED_SLASHES),
            'exception' => 'RuntimeException',
            'failed_at' => $now->subMinute(),
        ]);

        DB::table('anomalies')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'severity' => 'high',
                'type' => 'void_spike',
                'reference_id' => null,
                'payload_json' => json_encode(['scope' => 'own'], JSON_UNESCAPED_SLASHES),
                'detected_at' => $now->subHour(),
                'resolved_at' => null,
                'resolved_by' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'severity' => 'low',
                'type' => 'adjustment_spike',
                'reference_id' => null,
                'payload_json' => json_encode(['scope' => 'own'], JSON_UNESCAPED_SLASHES),
                'detected_at' => $now->subMinutes(30),
                'resolved_at' => $now->subMinutes(5),
                'resolved_by' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $otherTenant->id,
                'severity' => 'medium',
                'type' => 'void_spike',
                'reference_id' => null,
                'payload_json' => json_encode(['scope' => 'other'], JSON_UNESCAPED_SLASHES),
                'detected_at' => $now->subHour(),
                'resolved_at' => null,
                'resolved_by' => null,
            ],
        ]);

        DB::table('sales')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'branch_id' => $tenantBranchId,
                'shift_id' => (string) Str::uuid(),
                'register_id' => null,
                'customer_id' => null,
                'subtotal' => 10000,
                'discount' => 0,
                'tax' => 0,
                'total' => 10000,
                'status' => 'completed',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => null,
                'voided_by' => null,
                'created_at' => $now->subMinutes(5),
                'transaction_date' => '2026-02-27',
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $otherTenant->id,
                'branch_id' => $otherBranchId,
                'shift_id' => (string) Str::uuid(),
                'register_id' => null,
                'customer_id' => null,
                'subtotal' => 20000,
                'discount' => 0,
                'tax' => 0,
                'total' => 20000,
                'status' => 'completed',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => null,
                'voided_by' => null,
                'created_at' => $now->subHour(),
                'transaction_date' => '2026-02-27',
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $otherTenant->id,
                'branch_id' => $otherBranchId,
                'shift_id' => (string) Str::uuid(),
                'register_id' => null,
                'customer_id' => null,
                'subtotal' => 21000,
                'discount' => 0,
                'tax' => 0,
                'total' => 21000,
                'status' => 'completed',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => null,
                'voided_by' => null,
                'created_at' => $now->subMinutes(59),
                'transaction_date' => '2026-02-27',
            ],
        ]);

        DB::table('daily_sales_summaries')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'date' => '2026-02-27',
                'total_sales' => 10000,
                'total_tax' => 0,
                'total_discount' => 0,
                'total_cost' => 5000,
                'transaction_count' => 1,
                'updated_at' => $now->subMinutes(6),
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $otherTenant->id,
                'date' => '2026-02-27',
                'total_sales' => 41000,
                'total_tax' => 0,
                'total_discount' => 0,
                'total_cost' => 20500,
                'transaction_count' => 2,
                'updated_at' => $now->subHours(2),
            ],
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/observability/health?preset=custom&start_date=2026-02-27&end_date=2026-02-27');

        $response->assertOk()
            ->assertJsonStructure([
                'queue' => ['connection', 'depth', 'failed_jobs_5m'],
                'summary_lag' => ['p95_seconds'],
                'anomalies' => ['count_by_severity', 'count_by_type', 'resolve_ratio_by_type'],
                'detectors' => ['metrics'],
            ])
            ->assertJsonPath('queue.connection', config('queue.default'))
            ->assertJsonPath('queue.depth', null)
            ->assertJsonPath('queue.failed_jobs_5m', null)
            ->assertJsonPath('summary_lag.p95_seconds', 60)
            ->assertJsonPath('anomalies.count_by_severity.high', 1)
            ->assertJsonPath('anomalies.count_by_severity.low', 1)
            ->assertJsonMissingPath('anomalies.count_by_severity.medium')
            ->assertJsonPath('anomalies.count_by_type.void_spike', 1)
            ->assertJsonPath('anomalies.count_by_type.adjustment_spike', 1)
            ->assertJsonPath('anomalies.resolve_ratio_by_type.adjustment_spike', 1);
    }
}