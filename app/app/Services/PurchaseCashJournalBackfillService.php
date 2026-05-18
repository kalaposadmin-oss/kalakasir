<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PurchaseCashJournalBackfillService
{
    private const FIX_REFERENCE_PREFIX = 'purchase_cash_journal_fix';

    /**
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function preview(?string $tenantId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $rows = $this->loadCandidateRows($tenantId, $fromDate, $toDate)
            ->map(fn (object $row) => $this->classifyRow($row))
            ->values();

        return [
            'rows' => $rows->all(),
            'summary' => $this->summarize($rows),
        ];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, mixed>, processed: int, failed: int, repaired: array<int, array<string, mixed>>}
     */
    public function execute(?string $tenantId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $preview = $this->preview($tenantId, $fromDate, $toDate);
        $eligibleRows = collect($preview['rows'])
            ->filter(fn (array $row) => ($row['status'] ?? null) === 'eligible')
            ->values();

        $repaired = [];
        $failed = 0;
        $skipped = 0;

        foreach ($eligibleRows as $row) {
            try {
                $result = $this->repairRow($row);
                $repaired[] = $result;

                if (! ($result['applied'] ?? false)) {
                    $skipped++;
                }
            } catch (Throwable $exception) {
                $failed++;

                Log::warning('purchase_cash_journal_backfill_failed', [
                    'tenant_id' => $row['tenant_id'] ?? null,
                    'purchase_id' => $row['purchase_id'] ?? null,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'rows' => $preview['rows'],
            'summary' => $preview['summary'],
            'processed' => count($repaired),
            'skipped' => $skipped,
            'failed' => $failed,
            'repaired' => $repaired,
        ];
    }

    private function loadCandidateRows(?string $tenantId, ?string $fromDate, ?string $toDate): Collection
    {
        return DB::table('purchases as p')
            ->join('journal_entries as je', function ($join): void {
                $join->on('je.reference_id', '=', 'p.id')
                    ->on('je.tenant_id', '=', 'p.tenant_id')
                    ->where('je.reference_type', '=', 'purchase')
                    ->where('je.status', '=', 'posted');
            })
            ->join('account_mappings as am', function ($join): void {
                $join->on('am.tenant_id', '=', 'p.tenant_id');
            })
            ->leftJoin('journal_items as ji', function ($join): void {
                $join->on('ji.journal_entry_id', '=', 'je.id')
                    ->on('ji.tenant_id', '=', 'je.tenant_id');
            })
            ->when(($tenantId ?? '') !== '', fn ($query) => $query->where('p.tenant_id', $tenantId))
            ->when(($fromDate ?? '') !== '', fn ($query) => $query->whereRaw('DATE(COALESCE(p.transaction_date, p.created_at)) >= ?', [$fromDate]))
            ->when(($toDate ?? '') !== '', fn ($query) => $query->whereRaw('DATE(COALESCE(p.transaction_date, p.created_at)) <= ?', [$toDate]))
            ->groupBy(
                'p.id',
                'p.tenant_id',
                'p.payment_method',
                'p.cash_account_id',
                'p.total',
                'p.status',
                'p.transaction_date',
                'je.id',
                'je.date',
                'je.description',
                'am.cash_account_id',
                'am.payable_account_id',
                'am.inventory_account_id'
            )
            ->orderBy('p.tenant_id')
            ->orderByRaw('DATE(COALESCE(p.transaction_date, p.created_at))')
            ->orderBy('p.id')
            ->select([
                'p.id as purchase_id',
                'p.tenant_id',
                'p.payment_method',
                'p.cash_account_id as purchase_cash_account_id',
                'p.total as purchase_total',
                'p.status as purchase_status',
                'p.transaction_date as purchase_transaction_date',
                'je.id as original_journal_entry_id',
                'je.date as original_journal_date',
                'je.description as original_journal_description',
                'am.cash_account_id as mapping_cash_account_id',
                'am.payable_account_id as payable_account_id',
                'am.inventory_account_id as inventory_account_id',
                DB::raw('COALESCE(SUM(CASE WHEN ji.debit > 0 THEN ji.debit ELSE 0 END), 0) as debit_total'),
                DB::raw('COALESCE(SUM(CASE WHEN ji.credit > 0 THEN ji.credit ELSE 0 END), 0) as credit_total'),
                DB::raw('COALESCE(SUM(CASE WHEN ji.debit > 0 THEN 1 ELSE 0 END), 0) as debit_line_count'),
                DB::raw('COALESCE(SUM(CASE WHEN ji.credit > 0 THEN 1 ELSE 0 END), 0) as credit_line_count'),
                DB::raw('MAX(CASE WHEN ji.debit > 0 THEN ji.account_id ELSE NULL END) as debit_account_id'),
                DB::raw('MAX(CASE WHEN ji.credit > 0 THEN ji.account_id ELSE NULL END) as credit_account_id'),
                DB::raw('COALESCE(SUM(CASE WHEN ji.account_id = COALESCE(p.cash_account_id, am.cash_account_id) AND ji.credit > 0 THEN ji.credit ELSE 0 END), 0) as cash_credit_total'),
                DB::raw('COALESCE(SUM(CASE WHEN ji.account_id = am.payable_account_id AND ji.credit > 0 THEN ji.credit ELSE 0 END), 0) as payable_credit_total'),
            ])
            ->get();
    }

    private function classifyRow(object $row): array
    {
        $paymentMethod = strtolower(trim((string) ($row->payment_method ?? '')));
        $resolvedCashAccountId = $row->purchase_cash_account_id ?: $row->mapping_cash_account_id;
        $purchaseTotal = (float) ($row->purchase_total ?? 0);
        $debitTotal = (float) ($row->debit_total ?? 0);
        $creditTotal = (float) ($row->credit_total ?? 0);
        $debitLineCount = (int) ($row->debit_line_count ?? 0);
        $creditLineCount = (int) ($row->credit_line_count ?? 0);
        $cashCreditTotal = (float) ($row->cash_credit_total ?? 0);
        $payableCreditTotal = (float) ($row->payable_credit_total ?? 0);

        $entryCounts = $this->entryCounts((string) $row->tenant_id, (string) $row->purchase_id);

        $status = 'eligible';
        $reason = null;

        if ($paymentMethod !== 'tunai') {
            $status = 'not_tunai';
            $reason = 'payment_method_not_tunai';
        } elseif (! $resolvedCashAccountId) {
            $status = 'missing_cash_account';
            $reason = 'cash_account_not_resolved';
        } elseif ($entryCounts['purchase_entry_count'] !== 1) {
            $status = 'ambiguous_purchase_journal';
            $reason = 'purchase_journal_count_not_single';
        } elseif ($entryCounts['fix_entry_count'] > 0) {
            $status = 'already_repaired';
            $reason = 'purchase_already_has_fix_journal';
        } elseif ($entryCounts['other_entry_count'] > 0) {
            $status = 'separate_settlement';
            $reason = 'purchase_has_other_posted_journal_entries';
        } elseif (abs($purchaseTotal - $creditTotal) > 0.01 || abs($debitTotal - $creditTotal) > 0.01) {
            $status = 'amount_mismatch';
            $reason = 'journal_totals_do_not_match_purchase_total';
        } elseif ($creditLineCount !== 1 || $debitLineCount < 1) {
            $status = 'ambiguous_journal_lines';
            $reason = 'journal_line_count_not_expected';
        } elseif ($cashCreditTotal > 0) {
            $status = 'already_correct';
            $reason = 'cash_credit_already_present';
        } elseif ($payableCreditTotal <= 0) {
            $status = 'not_matching_bad_pattern';
            $reason = 'payable_credit_not_found';
        }

        return [
            'purchase_id' => (string) $row->purchase_id,
            'tenant_id' => (string) $row->tenant_id,
            'purchase_total' => round($purchaseTotal, 2),
            'purchase_status' => (string) $row->purchase_status,
            'purchase_date' => (string) ($row->purchase_transaction_date ?: $row->original_journal_date),
            'payment_method' => $paymentMethod,
            'purchase_cash_account_id' => $row->purchase_cash_account_id ? (string) $row->purchase_cash_account_id : null,
            'resolved_cash_account_id' => $resolvedCashAccountId ? (string) $resolvedCashAccountId : null,
            'payable_account_id' => $row->payable_account_id ? (string) $row->payable_account_id : null,
            'inventory_account_id' => $row->inventory_account_id ? (string) $row->inventory_account_id : null,
            'original_journal_entry_id' => (string) $row->original_journal_entry_id,
            'original_journal_date' => (string) $row->original_journal_date,
            'original_journal_description' => (string) ($row->original_journal_description ?? ''),
            'original_credit_account_id' => $row->credit_account_id ? (string) $row->credit_account_id : null,
            'original_debit_account_id' => $row->debit_account_id ? (string) $row->debit_account_id : null,
            'debit_total' => round($debitTotal, 2),
            'credit_total' => round($creditTotal, 2),
            'debit_line_count' => $debitLineCount,
            'credit_line_count' => $creditLineCount,
            'cash_credit_total' => round($cashCreditTotal, 2),
            'payable_credit_total' => round($payableCreditTotal, 2),
            'purchase_entry_count' => $entryCounts['purchase_entry_count'],
            'fix_entry_count' => $entryCounts['fix_entry_count'],
            'other_entry_count' => $entryCounts['other_entry_count'],
            'status' => $status,
            'reason' => $reason,
        ];
    }

    /**
     * @return array{purchase_entry_count: int, fix_entry_count: int, other_entry_count: int}
     */
    private function entryCounts(string $tenantId, string $purchaseId): array
    {
        $row = DB::table('journal_entries')
            ->where('tenant_id', $tenantId)
            ->where('reference_id', $purchaseId)
            ->where('status', 'posted')
            ->selectRaw("COALESCE(SUM(CASE WHEN reference_type = 'purchase' THEN 1 ELSE 0 END), 0) as purchase_entry_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN reference_type IN ('purchase_payment_reclass', 'purchase_cash_journal_fix_reversal', 'purchase_cash_journal_fix_repost', 'purchase_payment_correction') THEN 1 ELSE 0 END), 0) as fix_entry_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN reference_type NOT IN ('purchase', 'purchase_payment_reclass', 'purchase_cash_journal_fix_reversal', 'purchase_cash_journal_fix_repost', 'purchase_payment_correction') THEN 1 ELSE 0 END), 0) as other_entry_count")
            ->first();

        return [
            'purchase_entry_count' => (int) ($row->purchase_entry_count ?? 0),
            'fix_entry_count' => (int) ($row->fix_entry_count ?? 0),
            'other_entry_count' => (int) ($row->other_entry_count ?? 0),
        ];
    }

    private function repairRow(array $row): array
    {
        $tenantId = (string) $row['tenant_id'];
        $purchaseId = (string) $row['purchase_id'];

        $result = app(PurchasePaymentCorrectionService::class)->sync(
            $tenantId,
            $purchaseId,
            null,
            'purchase-cash-journal-backfill'
        );

        return [
            'purchase_id' => $purchaseId,
            'tenant_id' => $tenantId,
            'applied' => (bool) ($result['applied'] ?? false),
            'status' => $result['status'] ?? null,
            'signature' => $result['signature'] ?? null,
            'journal_entry_id' => $result['journal_entry_id'] ?? null,
            'timestamp' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summarize(Collection $rows): array
    {
        $eligibleRows = $rows->where('status', 'eligible');

        return [
            'total_rows' => $rows->count(),
            'eligible_rows' => $eligibleRows->count(),
            'eligible_total_value' => round((float) $eligibleRows->sum('purchase_total'), 2),
            'already_correct_rows' => $rows->where('status', 'already_correct')->count(),
            'already_correct_legacy_rows' => $rows->where('status', 'already_correct_legacy')->count(),
            'already_repaired_rows' => $rows->where('status', 'already_repaired')->count(),
            'duplicate_correction_path_rows' => $rows->where('status', 'duplicate_correction_path')->count(),
            'separate_settlement_rows' => $rows->where('status', 'separate_settlement')->count(),
            'missing_cash_account_rows' => $rows->where('status', 'missing_cash_account')->count(),
            'ambiguous_purchase_journal_rows' => $rows->where('status', 'ambiguous_purchase_journal')->count(),
            'ambiguous_journal_lines_rows' => $rows->where('status', 'ambiguous_journal_lines')->count(),
            'amount_mismatch_rows' => $rows->where('status', 'amount_mismatch')->count(),
            'not_tunai_rows' => $rows->where('status', 'not_tunai')->count(),
            'not_matching_bad_pattern_rows' => $rows->where('status', 'not_matching_bad_pattern')->count(),
        ];
    }
}