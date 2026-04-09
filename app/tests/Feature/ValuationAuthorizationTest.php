<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class ValuationAuthorizationTest extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_cashier_cannot_access_valuation_rebuild_status(): void
    {
        [$this->tenant, $this->user] = $this->bootApiActor('cashier', 'cashier-valuation@example.com', 'Cashier Valuation');

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/settings/valuation-rebuild-status');

        $response->assertStatus(403)
            ->assertJson([
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_cashier_cannot_access_valuation_reports_and_reconciliation(): void
    {
        [$this->tenant, $this->user] = $this->bootApiActor('cashier', 'cashier-valuation2@example.com', 'Cashier Valuation 2');

        $headers = $this->tenantHeaders($this->tenant->id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/settings/valuation-reports')
            ->assertStatus(403)
            ->assertJson([
                'code' => 'FORBIDDEN',
            ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/valuation/reconciliation')
            ->assertStatus(403)
            ->assertJson([
                'code' => 'FORBIDDEN',
            ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/settings/valuation-reports/export-cancel', [
                'request_id' => (string) Str::uuid(),
            ])
            ->assertStatus(403)
            ->assertJson([
                'code' => 'FORBIDDEN',
            ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/settings/valuation-rebuild-cancel', [
                'request_id' => (string) Str::uuid(),
            ])
            ->assertStatus(403)
            ->assertJson([
                'code' => 'FORBIDDEN',
            ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/settings/valuation-rebuild-retry', [
                'request_id' => (string) Str::uuid(),
            ])
            ->assertStatus(403)
            ->assertJson([
                'code' => 'FORBIDDEN',
            ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/settings/valuation-reports/export-retry', [
                'request_id' => (string) Str::uuid(),
            ])
            ->assertStatus(403)
            ->assertJson([
                'code' => 'FORBIDDEN',
            ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/settings/valuation-audit-logs')
            ->assertStatus(403)
            ->assertJson([
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_owner_can_access_valuation_rebuild_status(): void
    {
        [$this->tenant, $this->user] = $this->bootApiActor('owner', 'owner-valuation@example.com', 'Owner Valuation');
        $this->enableValuationAccess($this->tenant->id);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/settings/valuation-rebuild-status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'locked',
                'meta' => [
                    'status',
                    'started_at',
                    'finished_at',
                    'message',
                    'request_id',
                    'actor_id',
                    'trigger',
                ],
            ]);
    }

    public function test_admin_can_access_valuation_rebuild_status(): void
    {
        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-valuation@example.com', 'Admin Valuation');
        $this->enableValuationAccess($this->tenant->id);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/settings/valuation-rebuild-status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'locked',
                'meta' => [
                    'status',
                ],
            ]);
    }

        public function test_owner_can_access_valuation_reports_and_reconciliation(): void
        {
            [$this->tenant, $this->user] = $this->bootApiActor('owner', 'owner-valuation2@example.com', 'Owner Valuation 2');
            $this->enableValuationAccess($this->tenant->id);

            $headers = $this->tenantHeaders($this->tenant->id);

            $this->withHeaders($headers)
                ->getJson('/api/v1/settings/valuation-reports')
                ->assertStatus(200)
                ->assertJsonStructure([
                    'reports',
                ]);

            $this->withHeaders($headers)
                ->getJson('/api/v1/valuation/reconciliation')
                ->assertStatus(200)
                ->assertJsonStructure([
                    'summary',
                ]);

            $this->withHeaders($headers)
                ->getJson('/api/v1/settings/valuation-reports/export-status/'.(string) Str::uuid())
                ->assertStatus(404);

            $this->withHeaders($headers)
                ->getJson('/api/v1/settings/valuation-audit-logs')
                ->assertStatus(200)
                ->assertJsonStructure([
                    'logs',
                    'count',
                ]);
        }

        public function test_admin_can_access_valuation_reports_and_reconciliation(): void
        {
            [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-valuation2@example.com', 'Admin Valuation 2');
            $this->enableValuationAccess($this->tenant->id);

            $headers = $this->tenantHeaders($this->tenant->id);

            $this->withHeaders($headers)
                ->getJson('/api/v1/settings/valuation-reports')
                ->assertStatus(200)
                ->assertJsonStructure([
                    'reports',
                ]);

            $this->withHeaders($headers)
                ->getJson('/api/v1/valuation/reconciliation')
                ->assertStatus(200)
                ->assertJsonStructure([
                    'summary',
                ]);

            $this->withHeaders($headers)
                ->getJson('/api/v1/settings/valuation-audit-logs')
                ->assertStatus(200)
                ->assertJsonStructure([
                    'logs',
                    'count',
                ]);
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
}
