<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class SubscriptionCoreApiTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-sub-core@example.test', 'Admin Subscription Core');
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_plan_change_supports_idempotency_key(): void
    {
        $headers = array_merge($this->tenantHeaders($this->tenant->id), [
            'X-Idempotency-Key' => 'plan-change-core-1',
        ]);

        $first = $this->withHeaders($headers)
            ->patchJson('/api/v1/subscription/plan', [
                'plan' => 'M',
                'reason' => 'Upgrade demi kebutuhan reporting bulanan inti.',
            ]);

        $second = $this->withHeaders($headers)
            ->patchJson('/api/v1/subscription/plan', [
                'plan' => 'M',
                'reason' => 'Upgrade demi kebutuhan reporting bulanan inti.',
            ]);

        $first->assertStatus(200)
            ->assertJsonPath('plan', 'M');

        $second->assertStatus(200)
            ->assertJsonPath('idempotent_replay', true)
            ->assertJsonPath('plan', 'M');

        $this->assertDatabaseCount('subscription_audit_logs', 1);
    }

    public function test_noop_plan_change_returns_no_changes_without_audit_log(): void
    {
        DB::table('tenants')->where('id', $this->tenant->id)->update(['plan' => 'M']);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->patchJson('/api/v1/subscription/plan', [
                'plan' => 'M',
                'reason' => 'Tidak ada perubahan plan karena kebutuhan tetap sama.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'no_changes')
            ->assertJsonPath('noop', true)
            ->assertJsonPath('changed', false)
            ->assertJsonPath('scheduled', false);

        $this->assertDatabaseCount('subscription_audit_logs', 0);
    }

    public function test_impact_preview_returns_feature_and_proration_summary(): void
    {
        DB::table('tenants')->where('id', $this->tenant->id)->update(['plan' => 'M']);
        DB::table('subscriptions')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'plan' => 'M',
            'status' => 'active',
            'started_at' => now('UTC')->subDays(10),
            'current_period_start' => now('UTC')->subDays(10),
            'current_period_end' => now('UTC')->addDays(20),
            'is_current' => true,
            'created_at' => now('UTC')->subDays(10),
            'updated_at' => now('UTC')->subDay(),
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/subscription/impact-preview?target_plan=P&mode=immediate');

        $response->assertStatus(200)
            ->assertJsonPath('current_plan', 'M')
            ->assertJsonPath('target_plan', 'P')
            ->assertJsonPath('mode', 'immediate')
            ->assertJsonPath('period_definition', 'actual_subscription_period')
            ->assertJsonPath('proration_rounding', 'round_2_half_up')
            ->assertJsonPath('proration.amount', 133333.33)
            ->assertJsonStructure([
                'features_added',
                'features_removed',
                'proration' => ['amount', 'currency', 'ratio_remaining'],
            ]);
    }

    public function test_downgrade_can_be_scheduled_end_of_period(): void
    {
        DB::table('tenants')->where('id', $this->tenant->id)->update(['plan' => 'P']);
        $subscriptionId = (string) Str::uuid();
        DB::table('subscriptions')->insert([
            'id' => $subscriptionId,
            'tenant_id' => $this->tenant->id,
            'plan' => 'P',
            'status' => 'active',
            'started_at' => now('UTC')->subDays(5),
            'current_period_start' => now('UTC')->subDays(5),
            'current_period_end' => now('UTC')->addDays(25),
            'is_current' => true,
            'created_at' => now('UTC')->subDays(5),
            'updated_at' => now('UTC')->subDay(),
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->patchJson('/api/v1/subscription/plan', [
                'plan' => 'S',
                'mode' => 'end_of_period',
                'reason' => 'Menurunkan plan di akhir periode berjalan.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('changed', false)
            ->assertJsonPath('scheduled', true)
            ->assertJsonPath('mode', 'end_of_period')
            ->assertJsonPath('plan', 'P')
            ->assertJsonPath('requested_plan', 'S');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscriptionId,
            'plan' => 'P',
            'pending_plan' => 'S',
            'pending_change_mode' => 'end_of_period',
        ]);
    }

    public function test_scheduled_request_replaces_existing_pending_plan(): void
    {
        DB::table('tenants')->where('id', $this->tenant->id)->update(['plan' => 'P']);
        $subscriptionId = (string) Str::uuid();
        DB::table('subscriptions')->insert([
            'id' => $subscriptionId,
            'tenant_id' => $this->tenant->id,
            'plan' => 'P',
            'pending_plan' => 'M',
            'pending_change_mode' => 'end_of_period',
            'pending_change_effective_at' => now('UTC')->addDays(5),
            'status' => 'active',
            'started_at' => now('UTC')->subDays(5),
            'current_period_start' => now('UTC')->subDays(5),
            'current_period_end' => now('UTC')->addDays(25),
            'is_current' => true,
            'created_at' => now('UTC')->subDays(5),
            'updated_at' => now('UTC')->subDay(),
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->patchJson('/api/v1/subscription/plan', [
                'plan' => 'S',
                'mode' => 'end_of_period',
                'reason' => 'Ganti jadwal downgrade ke plan S pada akhir periode.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('scheduled', true)
            ->assertJsonPath('pending_replaced', true)
            ->assertJsonPath('schedule_policy', 'replace_existing')
            ->assertJsonPath('requested_plan', 'S');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscriptionId,
            'pending_plan' => 'S',
            'pending_change_mode' => 'end_of_period',
        ]);
    }

    public function test_immediate_upgrade_clears_existing_pending_downgrade(): void
    {
        DB::table('tenants')->where('id', $this->tenant->id)->update(['plan' => 'M']);
        $subscriptionId = (string) Str::uuid();
        DB::table('subscriptions')->insert([
            'id' => $subscriptionId,
            'tenant_id' => $this->tenant->id,
            'plan' => 'M',
            'pending_plan' => 'S',
            'pending_change_mode' => 'end_of_period',
            'pending_change_effective_at' => now('UTC')->addDays(5),
            'status' => 'active',
            'started_at' => now('UTC')->subDays(5),
            'current_period_start' => now('UTC')->subDays(5),
            'current_period_end' => now('UTC')->addDays(25),
            'is_current' => true,
            'created_at' => now('UTC')->subDays(5),
            'updated_at' => now('UTC')->subDay(),
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->patchJson('/api/v1/subscription/plan', [
                'plan' => 'P',
                'mode' => 'immediate',
                'reason' => 'Upgrade langsung dan batalkan downgrade terjadwal sebelumnya.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('changed', true)
            ->assertJsonPath('scheduled', false)
            ->assertJsonPath('pending_cleared', true)
            ->assertJsonPath('plan', 'P');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscriptionId,
            'plan' => 'P',
            'pending_plan' => null,
            'pending_change_mode' => null,
            'pending_change_effective_at' => null,
        ]);
    }

    public function test_audit_logs_list_and_export_work_for_admin(): void
    {
        $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->patchJson('/api/v1/subscription/plan', [
                'plan' => 'M',
                'reason' => 'Upgrade untuk kebutuhan audit export CSV endpoint.',
            ])
            ->assertStatus(200);

        $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/subscription/audit-logs?mode=immediate')
            ->assertStatus(200)
            ->assertJsonPath('data.audit_logs.0.old_plan', 'S')
            ->assertJsonPath('data.audit_logs.0.new_plan', 'M');

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->get('/api/v1/subscription/audit-logs/export?mode=immediate');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('old_plan', $csv);
        $this->assertStringContainsString('new_plan', $csv);
        $this->assertStringContainsString('change_mode', $csv);
    }

    public function test_invoice_can_be_marked_paid_and_reactivate_subscription(): void
    {
        $invoiceId = (string) Str::uuid();
        $subscriptionId = (string) Str::uuid();

        DB::table('subscriptions')->insert([
            'id' => $subscriptionId,
            'tenant_id' => $this->tenant->id,
            'plan' => 'M',
            'status' => 'past_due',
            'started_at' => now('UTC')->subMonth(),
            'current_period_start' => now('UTC')->subMonth(),
            'current_period_end' => now('UTC')->addMonth(),
            'grace_ends_at' => now('UTC')->addDay(),
            'is_current' => true,
            'created_at' => now('UTC')->subMonth(),
            'updated_at' => now('UTC')->subDay(),
        ]);

        DB::table('invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $this->tenant->id,
            'number' => 'INV-CORE-001',
            'period_start' => now('UTC')->subMonth()->toDateString(),
            'period_end' => now('UTC')->toDateString(),
            'amount_due' => 150000,
            'amount_paid' => 0,
            'status' => 'unpaid',
            'issued_at' => now('UTC')->subDays(5),
            'due_at' => now('UTC')->subDay(),
            'created_at' => now('UTC')->subDays(5),
            'updated_at' => now('UTC')->subDays(5),
        ]);

        DB::table('invoice_items')->insert([
            'invoice_id' => $invoiceId,
            'description' => 'Subscription plan M',
            'qty' => 1,
            'unit_price' => 150000,
            'total_price' => 150000,
            'created_at' => now('UTC')->subDays(5),
            'updated_at' => now('UTC')->subDays(5),
        ]);

        $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/subscription/invoices?status=unpaid')
            ->assertStatus(200)
            ->assertJsonPath('data.invoices.0.id', $invoiceId)
            ->assertJsonPath('data.invoices.0.status', 'unpaid');

        $this->withHeaders(array_merge($this->tenantHeaders($this->tenant->id), [
            'X-Idempotency-Key' => 'mark-paid-core-001',
        ]))->postJson('/api/v1/subscription/invoices/'.$invoiceId.'/mark-paid', [
            'provider' => 'manual',
            'reference' => 'MANUAL-CORE-001',
        ])
            ->assertStatus(200)
            ->assertJsonPath('invoice_status', 'paid');

        $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/subscription/invoices/'.$invoiceId)
            ->assertStatus(200)
            ->assertJsonPath('invoice.status', 'paid')
            ->assertJsonPath('payments.0.provider', 'manual');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscriptionId,
            'status' => 'active',
        ]);
    }
}