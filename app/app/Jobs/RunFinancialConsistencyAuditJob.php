<?php

namespace App\Jobs;

use App\Services\FinancialAuditService;
use App\Services\SystemHealthEventService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RunFinancialConsistencyAuditJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ?string $auditDate = null)
    {
    }

    public function handle(FinancialAuditService $auditService, SystemHealthEventService $events): void
    {
        $auditDate = $this->auditDate
            ? CarbonImmutable::parse($this->auditDate, 'UTC')->toDateString()
            : CarbonImmutable::now('UTC')->subDay()->toDateString();

        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $tenantId = (string) $tenantId;

            try {
                $result = $auditService->run($tenantId, $auditDate);
                $auditService->persistAuditLog($tenantId, $result);

                DB::table('audit_logs')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'tenant_id' => $tenantId,
                    'actor_id' => null,
                    'event_type' => 'finance_audit_snapshot',
                    'reference_type' => 'finance_audit',
                    'reference_id' => (string) \Illuminate\Support\Str::uuid(),
                    'payload_json' => json_encode($result, JSON_UNESCAPED_SLASHES),
                    'ip_address' => null,
                    'user_agent' => 'scheduler:financial-consistency-audit',
                    'created_at' => now('UTC'),
                ]);

                if (($result['summary']['mismatch_count'] ?? 0) > 0) {
                    $severity = ($result['summary']['critical_count'] ?? 0) > 0 ? 'critical' : 'high';

                    $events->recordOrBump('finance_audit_mismatch_detected', [
                        'tenant_id' => $tenantId,
                        'entity_type' => 'finance_audit',
                        'entity_id' => $auditDate,
                        'summary' => 'Financial audit mendeteksi mismatch pada inventory/cash/journal/orphan.',
                        'payload' => $result,
                        'source' => 'financial-consistency-audit-job',
                        'severity' => $severity,
                        'dedupe_key' => 'finance_audit_mismatch_detected:'.$tenantId.':'.$auditDate,
                    ]);
                } else {
                    $events->resolveMatching('finance_audit_mismatch_detected', [
                        'tenant_id' => $tenantId,
                        'entity_type' => 'finance_audit',
                        'entity_id' => $auditDate,
                    ], 'daily audit clean');
                }
            } catch (\Throwable $exception) {
                $events->recordOrBump('finance_audit_job_failed', [
                    'tenant_id' => $tenantId,
                    'entity_type' => 'finance_audit',
                    'entity_id' => $auditDate,
                    'summary' => 'Financial consistency audit job gagal berjalan.',
                    'payload' => [
                        'audit_date' => $auditDate,
                        'message' => $exception->getMessage(),
                    ],
                    'source' => 'financial-consistency-audit-job',
                    'severity' => 'high',
                    'dedupe_key' => 'finance_audit_job_failed:'.$tenantId.':'.$auditDate,
                ]);
            }
        }
    }
}
