<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseJournalRepairService
{
    public function __construct(private readonly PurchasePaymentCorrectionService $correctionService)
    {
    }

    public function audit(?string $tenantId = null, ?string $fromDate = null, ?string $toDate = null, int $limit = 1000): array
    {
        $rows = $this->candidateRows($tenantId, $fromDate, $toDate)
            ->take($limit)
            ->map(fn (object $row) => $this->classifyRow($row))
            ->values();

        return [
            'rows' => $rows,
            'summary' => $this->summary($rows),
        ];
    }

    public function repair(?string $tenantId = null, ?string $fromDate = null, ?string $toDate = null, int $limit = 1000, bool $persist = false): array
    {
        $rows = $this->candidateRows($tenantId, $fromDate, $toDate)
            ->map(fn (object $row) => $this->classifyRow($row))
            ->values();

        $repairable = $rows
            ->filter(fn (array $row) => in_array($row['status'], ['cash_mismatch', 'tempo_mismatch'], true) && ! empty($row['delta']))
            ->take($limit)
            ->values();

        $updated = 0;
        $skipped = 0;
        $conflicts = 0;

        if ($persist) {
            foreach ($repairable as $row) {
                $result = $this->correctionService->sync(
                    (string) $row['tenant_id'],
                    (string) $row['purchase_id'],
                    null,
                    'purchase-journal-repair'
                );

                if (($result['applied'] ?? false) === true) {
                    $updated++;
                } elseif (($result['status'] ?? null) === 'conflict') {
                    $conflicts++;
                } else {
                    $skipped++;
                }
            }
        }

        return [
            'rows' => $rows,
            'summary' => $this->summary($rows),
            'updated' => $updated,
            'skipped' => $skipped,
            'conflicts' => $conflicts,
            'repairable' => $repairable,
        ];
    }

    private function candidateRows(?string $tenantId, ?string $fromDate, ?string $toDate): Collection
    {
        return DB::table('purchases as p')
            ->join('journal_entries as je', function ($join): void {
                $join->on('je.reference_id', '=', 'p.id')
                    ->on('je.tenant_id', '=', 'p.tenant_id');
            })
            ->leftJoin('account_mappings as am', function ($join): void {
                $join->on('am.tenant_id', '=', 'p.tenant_id');
            })
            ->leftJoin('journal_items as ji', function ($join): void {
                $join->on('ji.journal_entry_id', '=', 'je.id')
                    ->where('ji.credit', '>', 0);
            })
            ->where('je.reference_type', 'purchase')
            ->where('je.status', 'posted')
            ->when(($tenantId ?? '') !== '', fn ($query) => $query->where('p.tenant_id', $tenantId))
            ->when(($fromDate ?? '') !== '', fn ($query) => $query->whereRaw('DATE(COALESCE(p.transaction_date, p.created_at)) >= ?', [$fromDate]))
            ->when(($toDate ?? '') !== '', fn ($query) => $query->whereRaw('DATE(COALESCE(p.transaction_date, p.created_at)) <= ?', [$toDate]))
            ->orderBy('p.tenant_id')
            ->orderByRaw('DATE(COALESCE(p.transaction_date, p.created_at))')
            ->orderBy('p.id')
            ->select([
                'p.id as purchase_id',
                'p.tenant_id',
                'p.payment_method',
                'p.cash_account_id as purchase_cash_account_id',
                'p.due_date',
                'p.status as purchase_status',
                'je.id as journal_entry_id',
                'ji.id as current_credit_journal_item_id',
                'ji.account_id as current_credit_account_id',
                'am.cash_account_id as mapping_cash_account_id',
                'am.payable_account_id as mapping_payable_account_id',
            ])
            ->get();
    }

    private function classifyRow(object $row): array
    {
        $inspection = $this->correctionService->inspect((string) $row->tenant_id, (string) $row->purchase_id);
        $paymentMethod = strtolower(trim((string) ($row->payment_method ?? 'tempo')));
        $resolvedCashAccountId = $inspection['desired_credit_account_id'] ?? ($row->purchase_cash_account_id ?: $row->mapping_cash_account_id);

        $status = match ($inspection['status'] ?? null) {
            'needs_correction' => $paymentMethod === 'tunai' ? 'cash_mismatch' : 'tempo_mismatch',
            'duplicate_correction_path', 'already_correct_duplicate_path' => 'duplicate_correction_path',
            'already_correct', 'already_correct_legacy_path' => 'ok',
            default => (string) ($inspection['status'] ?? 'missing_journal'),
        };

        return [
            'purchase_id' => (string) $row->purchase_id,
            'tenant_id' => (string) $row->tenant_id,
            'payment_method' => $paymentMethod,
            'purchase_cash_account_id' => $row->purchase_cash_account_id ? (string) $row->purchase_cash_account_id : null,
            'resolved_cash_account_id' => $resolvedCashAccountId ? (string) $resolvedCashAccountId : null,
            'journal_entry_id' => $row->journal_entry_id ? (string) $row->journal_entry_id : null,
            'current_credit_journal_item_id' => $row->current_credit_journal_item_id ? (string) $row->current_credit_journal_item_id : null,
            'current_credit_account_id' => $row->current_credit_account_id ? (string) $row->current_credit_account_id : null,
            'expected_credit_account_id' => $inspection['desired_credit_account_id'] ?? null,
            'purchase_status' => (string) $row->purchase_status,
            'status' => $status,
            'inspection_status' => $inspection['status'] ?? null,
            'duplicate_correction_path' => (bool) ($inspection['duplicate_correction_path'] ?? false),
            'delta' => $inspection['delta'] ?? [],
        ];
    }

    private function summary(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'ok' => $rows->where('status', 'ok')->count(),
            'cash_mismatch' => $rows->where('status', 'cash_mismatch')->count(),
            'tempo_mismatch' => $rows->where('status', 'tempo_mismatch')->count(),
            'duplicate_correction_path' => $rows->where('status', 'duplicate_correction_path')->count(),
            'missing_journal' => $rows->where('status', 'missing_journal')->count(),
            'missing_cash_account' => $rows->where('status', 'missing_cash_account')->count(),
            'missing_purchase' => $rows->where('status', 'missing_purchase')->count(),
            'ambiguous_purchase_journal' => $rows->where('status', 'ambiguous_purchase_journal')->count(),
            'repairable' => $rows->whereIn('status', ['cash_mismatch', 'tempo_mismatch'])->count(),
        ];
    }
}