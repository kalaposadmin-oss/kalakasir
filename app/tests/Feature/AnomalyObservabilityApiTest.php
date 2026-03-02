<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
    }
}
