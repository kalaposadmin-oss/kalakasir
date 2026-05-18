<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class FinancialAuditService
{
    public function run(string $tenantId, string $auditDate): array
    {
        $asOfDate = CarbonImmutable::parse($auditDate, 'UTC')->toDateString();

        $inventory = $this->auditInventoryVsGl($tenantId, $asOfDate);
        $cash = $this->auditCashVsGl($tenantId, $asOfDate);
        $unbalanced = $this->auditUnbalancedJournal($tenantId, $asOfDate);
        $orphan = $this->auditOrphanTransaction($tenantId, $asOfDate);

        $overallStatus = $this->resolveOverallStatus($inventory, $cash, $unbalanced, $orphan);

        return [
            'audit_date' => $asOfDate,
            'status' => $overallStatus,
            'checks' => [
                'inventory_vs_gl' => $inventory,
                'cash_vs_gl' => $cash,
                'unbalanced_journal' => $unbalanced,
                'orphan_transaction' => $orphan,
            ],
            'summary' => [
                'mismatch_count' => (
                    ($inventory['is_match'] ? 0 : 1)
                    + ($cash['is_match'] ? 0 : 1)
                    + (int) ($unbalanced['count'] > 0)
                    + (int) ($orphan['count'] > 0)
                ),
                'critical_count' => (int) ($unbalanced['count'] > 0) + (int) ($orphan['count'] > 0),
            ],
        ];
    }

    public function persistAuditLog(string $tenantId, array $result): void
    {
        DB::table('finance_audit_logs')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenantId,
            'audit_date' => (string) $result['audit_date'],
            'status' => (string) $result['status'],
            'inventory_vs_gl_difference' => (float) ($result['checks']['inventory_vs_gl']['difference'] ?? 0),
            'cash_vs_gl_difference' => (float) ($result['checks']['cash_vs_gl']['difference'] ?? 0),
            'unbalanced_journal_count' => (int) ($result['checks']['unbalanced_journal']['count'] ?? 0),
            'orphan_transaction_count' => (int) ($result['checks']['orphan_transaction']['count'] ?? 0),
            'payload_json' => json_encode($result, JSON_UNESCAPED_SLASHES),
            'created_at' => now('UTC'),
        ]);
    }

    private function auditInventoryVsGl(string $tenantId, string $asOfDate): array
    {
        $mapping = DB::table('account_mappings')
            ->where('tenant_id', $tenantId)
            ->first(['inventory_account_id']);

        if (! $mapping || ! $mapping->inventory_account_id) {
            return [
                'status' => 'warning',
                'is_match' => false,
                'difference' => 0,
                'inventory_value' => 0,
                'gl_inventory_balance' => 0,
                'message' => 'Mapping inventory_account_id belum tersedia.',
            ];
        }

        $inventoryValue = (float) DB::table('inventory_cost_states')
            ->where('tenant_id', $tenantId)
            ->selectRaw('COALESCE(SUM(qty_on_hand * average_cost), 0) as total')
            ->value('total');

        $glInventoryBalance = (float) DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('je.status', 'posted')
            ->where('je.date', '<=', $asOfDate)
            ->where('ji.account_id', (string) $mapping->inventory_account_id)
            ->selectRaw('COALESCE(SUM(ji.debit - ji.credit), 0) as balance')
            ->value('balance');

        $difference = round($inventoryValue - $glInventoryBalance, 2);

        return [
            'status' => abs($difference) > 0.01 ? 'critical' : 'ok',
            'is_match' => abs($difference) <= 0.01,
            'difference' => $difference,
            'inventory_value' => round($inventoryValue, 2),
            'gl_inventory_balance' => round($glInventoryBalance, 2),
            'basis' => 'inventory_cost_states vs journal_items(inventory_account_id)',
        ];
    }

    private function auditCashVsGl(string $tenantId, string $asOfDate): array
    {
        $mapping = DB::table('account_mappings')
            ->where('tenant_id', $tenantId)
            ->first(['cash_account_id', 'bank_account_id']);

        if (! $mapping || (! $mapping->cash_account_id && ! $mapping->bank_account_id)) {
            return [
                'status' => 'warning',
                'is_match' => false,
                'difference' => 0,
                'cash_transaction_net' => 0,
                'gl_cash_transaction_net' => 0,
                'message' => 'Mapping cash/bank account belum tersedia.',
            ];
        }

        $cashAccountIds = array_values(array_filter([
            (string) ($mapping->cash_account_id ?? ''),
            (string) ($mapping->bank_account_id ?? ''),
        ]));

        $cashTransactionNet = (float) DB::table('cash_transactions')
            ->where('tenant_id', $tenantId)
            ->where('transaction_date', '<=', $asOfDate)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'cash-in' THEN amount ELSE -amount END), 0) as movement")
            ->value('movement');

        $glCashNet = (float) DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('je.status', 'posted')
            ->where('je.reference_type', 'cash_transaction')
            ->where('je.date', '<=', $asOfDate)
            ->whereIn('ji.account_id', $cashAccountIds)
            ->selectRaw('COALESCE(SUM(ji.debit - ji.credit), 0) as movement')
            ->value('movement');

        $difference = round($cashTransactionNet - $glCashNet, 2);

        $entryMismatchRows = DB::table('cash_transactions as ct')
            ->leftJoin('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'ct.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ct.tenant_id');
            })
            ->leftJoin('journal_items as ji', function ($join) {
                $join->on('ji.journal_entry_id', '=', 'je.id')
                    ->on('ji.tenant_id', '=', 'je.tenant_id')
                    ->on('ji.account_id', '=', 'ct.account_id');
            })
            ->where('ct.tenant_id', $tenantId)
            ->where('ct.transaction_date', '<=', $asOfDate)
            ->groupBy('ct.id', 'ct.direction', 'ct.amount')
            ->havingRaw("ABS(COALESCE(SUM(CASE WHEN ct.direction = 'cash-in' THEN ji.debit - ji.credit ELSE ji.credit - ji.debit END), 0) - ct.amount) > 0.01")
            ->limit(20)
            ->get(['ct.id', 'ct.direction', 'ct.amount']);

        return [
            'status' => (abs($difference) > 0.01 || $entryMismatchRows->isNotEmpty()) ? 'critical' : 'ok',
            'is_match' => abs($difference) <= 0.01 && $entryMismatchRows->isEmpty(),
            'difference' => $difference,
            'cash_transaction_net' => round($cashTransactionNet, 2),
            'gl_cash_transaction_net' => round($glCashNet, 2),
            'entry_mismatch_count' => (int) $entryMismatchRows->count(),
            'entry_mismatch_sample' => $entryMismatchRows->values(),
            'basis' => 'cash_transactions net vs journal_entries(reference_type=cash_transaction)',
        ];
    }

    private function auditUnbalancedJournal(string $tenantId, string $asOfDate): array
    {
        $rows = DB::table('journal_entries as je')
            ->join('journal_items as ji', function ($join) {
                $join->on('ji.journal_entry_id', '=', 'je.id')
                    ->on('ji.tenant_id', '=', 'je.tenant_id');
            })
            ->where('je.tenant_id', $tenantId)
            ->where('je.status', 'posted')
            ->where('je.date', '<=', $asOfDate)
            ->groupBy('je.id', 'je.reference_type', 'je.reference_id', 'je.date')
            ->havingRaw('ABS(COALESCE(SUM(ji.debit),0) - COALESCE(SUM(ji.credit),0)) > 0.01')
            ->limit(50)
            ->get([
                'je.id',
                'je.reference_type',
                'je.reference_id',
                'je.date',
                DB::raw('COALESCE(SUM(ji.debit),0) as debit_total'),
                DB::raw('COALESCE(SUM(ji.credit),0) as credit_total'),
            ]);

        return [
            'status' => $rows->isEmpty() ? 'ok' : 'critical',
            'count' => (int) $rows->count(),
            'sample' => $rows->values(),
        ];
    }

    private function auditOrphanTransaction(string $tenantId, string $asOfDate): array
    {
        $salesOrphans = DB::table('sales as s')
            ->leftJoin('journal_entries as je', function ($join) {
                $join->on('je.reference_id', '=', 's.id')
                    ->on('je.tenant_id', '=', 's.tenant_id')
                    ->where('je.reference_type', '=', 'sale')
                    ->where('je.status', '=', 'posted');
            })
            ->where('s.tenant_id', $tenantId)
            ->where('s.status', 'completed')
            ->whereRaw('DATE(COALESCE(s.transaction_date, s.created_at)) <= ?', [$asOfDate])
            ->whereNull('je.id')
            ->selectRaw("'sale' as type, s.id as reference_id")
            ->limit(25)
            ->get();

        $purchaseOrphans = DB::table('purchases as p')
            ->leftJoin('journal_entries as je', function ($join) {
                $join->on('je.reference_id', '=', 'p.id')
                    ->on('je.tenant_id', '=', 'p.tenant_id')
                    ->where('je.reference_type', '=', 'purchase')
                    ->where('je.status', '=', 'posted');
            })
            ->where('p.tenant_id', $tenantId)
            ->whereIn('p.status', ['received', 'partially_received', 'closed'])
            ->whereRaw('DATE(COALESCE(p.transaction_date, p.created_at)) <= ?', [$asOfDate])
            ->whereNull('je.id')
            ->selectRaw("'purchase' as type, p.id as reference_id")
            ->limit(25)
            ->get();

        $adjustmentOrphans = DB::table('stock_adjustments as sa')
            ->leftJoin('journal_entries as je', function ($join) {
                $join->on('je.reference_id', '=', 'sa.id')
                    ->on('je.tenant_id', '=', 'sa.tenant_id')
                    ->whereIn('je.reference_type', ['stock_adjustment', 'stock_opname'])
                    ->where('je.status', '=', 'posted');
            })
            ->where('sa.tenant_id', $tenantId)
            ->where('sa.status', 'posted')
            ->whereRaw('DATE(COALESCE(sa.transaction_date, sa.created_at)) <= ?', [$asOfDate])
            ->whereNull('je.id')
            ->selectRaw("'stock_adjustment' as type, sa.id as reference_id")
            ->limit(25)
            ->get();

        $cashOrphans = DB::table('cash_transactions as ct')
            ->leftJoin('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'ct.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ct.tenant_id');
            })
            ->where('ct.tenant_id', $tenantId)
            ->where('ct.transaction_date', '<=', $asOfDate)
            ->where(function ($query) {
                $query->whereNull('je.id')
                    ->orWhere('je.status', '!=', 'posted')
                    ->orWhere('je.reference_type', '!=', 'cash_transaction')
                    ->orWhereColumn('je.reference_id', '!=', 'ct.id');
            })
            ->selectRaw("'cash_transaction' as type, ct.id as reference_id")
            ->limit(25)
            ->get();

        $sample = $salesOrphans
            ->concat($purchaseOrphans)
            ->concat($adjustmentOrphans)
            ->concat($cashOrphans)
            ->take(50)
            ->values();

        return [
            'status' => $sample->isEmpty() ? 'ok' : 'critical',
            'count' => (int) $sample->count(),
            'sample' => $sample,
            'breakdown' => [
                'sale' => (int) $salesOrphans->count(),
                'purchase' => (int) $purchaseOrphans->count(),
                'stock_adjustment' => (int) $adjustmentOrphans->count(),
                'cash_transaction' => (int) $cashOrphans->count(),
            ],
        ];
    }

    private function resolveOverallStatus(array $inventory, array $cash, array $unbalanced, array $orphan): string
    {
        if (($unbalanced['count'] ?? 0) > 0 || ($orphan['count'] ?? 0) > 0) {
            return 'red';
        }

        if (($inventory['is_match'] ?? false) === false || ($cash['is_match'] ?? false) === false) {
            return 'yellow';
        }

        return 'green';
    }
}
