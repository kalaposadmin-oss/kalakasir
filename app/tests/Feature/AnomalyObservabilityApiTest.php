<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class AnomalyObservabilityApiTest extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_cashier_cannot_access_anomaly_observability_endpoints(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier-anomaly@example.com', 'Cashier Anomaly');

        $anomalyId = (string) Str::uuid();
        \DB::table('anomalies')->insert([
            'id' => $anomalyId,
            'tenant_id' => $tenant->id,
            'severity' => 'high',
            'type' => 'void_spike',
            'reference_id' => null,
            'payload_json' => json_encode(['foo' => 'bar'], JSON_UNESCAPED_SLASHES),
            'detected_at' => now('UTC'),
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        $headers = $this->tenantHeaders($tenant->id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/anomalies')
            ->assertStatus(403)
            ->assertJson(['code' => 'FORBIDDEN']);

        $this->withHeaders($headers)
            ->postJson('/api/v1/anomalies/'.$anomalyId.'/resolve', [
                'note' => 'test',
            ])
            ->assertStatus(403)
            ->assertJson(['code' => 'FORBIDDEN']);
    }

    public function test_admin_can_list_and_resolve_anomaly_for_own_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-anomaly@example.com', 'Admin Anomaly');

        $anomalyId = (string) Str::uuid();
        \DB::table('anomalies')->insert([
            'id' => $anomalyId,
            'tenant_id' => $tenant->id,
            'severity' => 'high',
            'type' => 'void_spike',
            'reference_id' => null,
            'payload_json' => json_encode(['foo' => 'bar'], JSON_UNESCAPED_SLASHES),
            'detected_at' => now('UTC'),
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        $headers = $this->tenantHeaders($tenant->id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/anomalies?resolved=0&limit=10')
            ->assertStatus(200)
            ->assertJsonStructure([
                'count',
                'anomalies',
            ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/anomalies/'.$anomalyId.'/resolve', [
                'note' => 'resolved by test',
            ])
            ->assertStatus(200)
            ->assertJson([
                'status' => 'resolved',
            ]);

        $this->assertDatabaseHas('anomalies', [
            'id' => $anomalyId,
            'tenant_id' => $tenant->id,
            'resolved_by' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'event_type' => 'anomaly_resolved',
            'reference_type' => 'anomaly',
            'reference_id' => $anomalyId,
            'actor_id' => $user->id,
        ]);

        $payload = json_decode((string) DB::table('audit_logs')
            ->where('tenant_id', $tenant->id)
            ->where('event_type', 'anomaly_resolved')
            ->where('reference_id', $anomalyId)
            ->value('payload_json'), true);

        $this->assertSame('resolved by test', $payload['resolved_note'] ?? null);
    }

    public function test_admin_can_filter_production_batch_variance_anomaly_by_type(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin-batch-variance-observability@example.com', 'Admin Batch Variance');

        \DB::table('anomalies')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'severity' => 'high',
                'type' => 'production_batch_yield_variance',
                'reference_id' => 'batch-variance-001',
                'payload_json' => json_encode([
                    'reference_no' => 'PB-001',
                    'expected_output_qty' => 10,
                    'actual_output_qty' => 12,
                    'yield_variance_qty' => 2,
                    'yield_variance_pct' => 20,
                    'variance_reason' => 'Oven lebih efisien',
                ], JSON_UNESCAPED_SLASHES),
                'detected_at' => now('UTC'),
                'resolved_at' => null,
                'resolved_by' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'severity' => 'high',
                'type' => 'void_spike',
                'reference_id' => null,
                'payload_json' => json_encode(['foo' => 'bar'], JSON_UNESCAPED_SLASHES),
                'detected_at' => now('UTC'),
                'resolved_at' => null,
                'resolved_by' => null,
            ],
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/anomalies?type=production_batch_yield_variance&resolved=0&limit=10')
            ->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('anomalies.0.type', 'production_batch_yield_variance')
            ->assertJsonPath('anomalies.0.reference_id', 'batch-variance-001');
    }

    public function test_admin_can_filter_production_batch_variance_anomaly_by_reference_id(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin-batch-variance-reference@example.com', 'Admin Batch Variance Reference');

        \DB::table('anomalies')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'severity' => 'high',
                'type' => 'production_batch_yield_variance',
                'reference_id' => 'batch-variance-001',
                'payload_json' => json_encode(['reference_no' => 'PB-001'], JSON_UNESCAPED_SLASHES),
                'detected_at' => now('UTC'),
                'resolved_at' => null,
                'resolved_by' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'severity' => 'high',
                'type' => 'production_batch_yield_variance',
                'reference_id' => 'batch-variance-002',
                'payload_json' => json_encode(['reference_no' => 'PB-002'], JSON_UNESCAPED_SLASHES),
                'detected_at' => now('UTC'),
                'resolved_at' => null,
                'resolved_by' => null,
            ],
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/anomalies?type=production_batch_yield_variance&reference_id=batch-variance-002&resolved=0&limit=10')
            ->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('anomalies.0.reference_id', 'batch-variance-002');
    }

    public function test_admin_can_filter_anomaly_by_branch_and_location_payload(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin-batch-variance-scope@example.com', 'Admin Batch Variance Scope');

        $branchA = (string) Str::uuid();
        $branchB = (string) Str::uuid();
        $locationA = (string) Str::uuid();
        $locationB = (string) Str::uuid();

        DB::table('branches')->insert([
            ['id' => $branchA, 'tenant_id' => $tenant->id, 'name' => 'Branch A'],
            ['id' => $branchB, 'tenant_id' => $tenant->id, 'name' => 'Branch B'],
        ]);

        DB::table('locations')->insert([
            ['id' => $locationA, 'tenant_id' => $tenant->id, 'branch_id' => $branchA, 'name' => 'Location A', 'is_default' => true],
            ['id' => $locationB, 'tenant_id' => $tenant->id, 'branch_id' => $branchB, 'name' => 'Location B', 'is_default' => false],
        ]);

        DB::table('anomalies')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'severity' => 'high',
                'type' => 'production_batch_yield_variance',
                'reference_id' => 'batch-variance-scope-a',
                'payload_json' => json_encode(['branch_id' => $branchA, 'location_id' => $locationA], JSON_UNESCAPED_SLASHES),
                'detected_at' => now('UTC'),
                'resolved_at' => null,
                'resolved_by' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'severity' => 'high',
                'type' => 'production_batch_yield_variance',
                'reference_id' => 'batch-variance-scope-b',
                'payload_json' => json_encode(['branch_id' => $branchB, 'location_id' => $locationB], JSON_UNESCAPED_SLASHES),
                'detected_at' => now('UTC'),
                'resolved_at' => null,
                'resolved_by' => null,
            ],
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/anomalies?type=production_batch_yield_variance&branch_id='.$branchA.'&location_id='.$locationA.'&resolved=0&limit=10')
            ->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('anomalies.0.reference_id', 'batch-variance-scope-a');
    }
}
