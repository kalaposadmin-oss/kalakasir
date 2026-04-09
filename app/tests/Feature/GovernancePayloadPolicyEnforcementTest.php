<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class GovernancePayloadPolicyEnforcementTest extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_cashier_void_with_approval_required_is_blocked_by_policy(): void
    {
        [$this->tenant, $this->user] = $this->bootApiActor('cashier', 'cashier-governance@example.com', 'Kasir Governance');

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/sales/test-sale-id/void', [
                'reason_code' => 'V01',
                'requested_by_role' => 'cashier',
                'approval_required' => true,
                'source_module' => 'mobile-sync',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        $details = $response->json('details', []);
        $this->assertArrayHasKey('approval_required', $details);
    }

    public function test_finance_snapshot_rejects_requested_role_mismatch(): void
    {
        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-governance-mismatch@example.com', 'Admin Governance');

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/finance-control-daily/snapshot', [
                'requested_by_role' => 'cashier',
                'approval_required' => false,
                'source_module' => 'mobile-sync',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        $details = $response->json('details', []);
        $this->assertArrayHasKey('requested_by_role', $details);
    }

    public function test_finance_snapshot_accepts_governance_payload_and_echoes_meta(): void
    {
        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-governance-ok@example.com', 'Admin Governance OK');

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/finance-control-daily/snapshot', [
                'requested_by_role' => 'admin',
                'approval_required' => false,
                'source_module' => 'mobile-sync',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'snapshotted')
            ->assertJsonPath('governance.source_module', 'mobile-sync')
            ->assertJsonPath('governance.requested_by_role', 'admin')
            ->assertJsonPath('governance.approval_required', false)
            ->assertJsonPath('governance.actor_role', 'admin');
    }
}
