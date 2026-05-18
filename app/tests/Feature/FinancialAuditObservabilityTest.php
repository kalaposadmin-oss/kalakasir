<?php

namespace Tests\Feature;

use App\Jobs\RunFinancialConsistencyAuditJob;
use App\Services\TenantDefaultAccountingSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class FinancialAuditObservabilityTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_finance_audit_report_endpoint_returns_all_required_checks(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+finance-audit-report@example.test');
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'P']);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-audit/report?audit_date=2026-04-18');

        $response->assertOk()
            ->assertJsonStructure([
                'audit_date',
                'status',
                'checks' => [
                    'inventory_vs_gl',
                    'cash_vs_gl',
                    'unbalanced_journal',
                    'orphan_transaction',
                ],
                'summary' => ['mismatch_count', 'critical_count'],
            ]);
    }

    public function test_finance_audit_detects_unbalanced_journal_and_orphan_transaction(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+finance-audit-mismatch@example.test');
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'P']);

        $coaId = (string) DB::table('chart_of_accounts')
            ->where('tenant_id', $tenant->id)
            ->value('id');

        $journalId = (string) Str::uuid();
        DB::table('journal_entries')->insert([
            'id' => $journalId,
            'tenant_id' => $tenant->id,
            'reference_type' => 'manual_adjustment',
            'reference_id' => (string) Str::uuid(),
            'date' => '2026-04-18',
            'description' => 'forced unbalanced journal for test',
            'status' => 'posted',
            'posted_at' => now('UTC'),
            'created_by' => $user->id,
            'created_at' => now('UTC'),
        ]);

        DB::table('journal_items')->insert([
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $journalId,
            'tenant_id' => $tenant->id,
            'account_id' => $coaId,
            'debit' => 50000,
            'credit' => 0,
            'is_reversal' => false,
        ]);

        DB::table('suppliers')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Supplier Orphan',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        $supplierId = (string) DB::table('suppliers')->where('tenant_id', $tenant->id)->value('id');

        DB::table('purchases')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplierId,
            'total' => 50000,
            'status' => 'received',
            'created_at' => now('UTC'),
            'transaction_date' => '2026-04-18',
            'approved_at' => now('UTC'),
            'approved_by' => $user->id,
            'received_at' => now('UTC'),
            'received_by' => $user->id,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'location_id' => null,
            'notes' => null,
            'payment_method' => 'tempo',
            'due_date' => null,
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-audit/report?audit_date=2026-04-18&persist=1');

        $response->assertOk()
            ->assertJsonPath('checks.unbalanced_journal.count', 1)
            ->assertJsonPath('checks.orphan_transaction.breakdown.purchase', 1)
            ->assertJsonPath('status', 'red');

        $this->assertDatabaseHas('finance_audit_logs', [
            'tenant_id' => $tenant->id,
            'audit_date' => '2026-04-18',
            'status' => 'red',
        ]);
    }

    public function test_daily_finance_audit_job_triggers_alert_event_on_mismatch(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+finance-audit-alert@example.test');
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $coaId = (string) DB::table('chart_of_accounts')
            ->where('tenant_id', $tenant->id)
            ->value('id');

        $journalId = (string) Str::uuid();
        DB::table('journal_entries')->insert([
            'id' => $journalId,
            'tenant_id' => $tenant->id,
            'reference_type' => 'manual_adjustment',
            'reference_id' => (string) Str::uuid(),
            'date' => '2026-04-17',
            'description' => 'forced unbalanced journal for alert',
            'status' => 'posted',
            'posted_at' => now('UTC'),
            'created_by' => $user->id,
            'created_at' => now('UTC'),
        ]);

        DB::table('journal_items')->insert([
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $journalId,
            'tenant_id' => $tenant->id,
            'account_id' => $coaId,
            'debit' => 1,
            'credit' => 0,
            'is_reversal' => false,
        ]);

        $job = new RunFinancialConsistencyAuditJob('2026-04-17');
        $job->handle(app(\App\Services\FinancialAuditService::class), app(\App\Services\SystemHealthEventService::class));

        $this->assertDatabaseHas('system_health_events', [
            'tenant_id' => $tenant->id,
            'event_name' => 'finance_audit_mismatch_detected',
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'event_type' => 'finance_audit_snapshot',
            'reference_type' => 'finance_audit',
        ]);
    }
}
