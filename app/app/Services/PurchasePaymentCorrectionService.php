<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Purchase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PurchasePaymentCorrectionService
{
    private const CANONICAL_REFERENCE_TYPE = 'purchase_payment_correction';

    private const LEGACY_REFERENCE_TYPES = [
        'purchase_payment_reclass',
        'purchase_cash_journal_fix_reversal',
        'purchase_cash_journal_fix_repost',
    ];

    private const PRIMARY_REFERENCE_TYPE = 'purchase';

    public function inspect(string $tenantId, string $purchaseId): array
    {
        $purchase = $this->loadPurchase($tenantId, $purchaseId);
        if (! $purchase) {
            return [
                'tenant_id' => $tenantId,
                'purchase_id' => $purchaseId,
                'status' => 'missing_purchase',
                'can_sync' => false,
                'purchase_entry_count' => 0,
                'correction_entry_count' => 0,
                'canonical_correction_entry_count' => 0,
                'legacy_correction_entry_count' => 0,
                'duplicate_correction_path' => false,
                'current' => [],
                'desired' => [],
                'delta' => [],
                'signature' => null,
            ];
        }

        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $mapping || ! $mapping->payable_account_id) {
            return [
                'tenant_id' => $tenantId,
                'purchase_id' => $purchaseId,
                'status' => 'missing_mapping',
                'can_sync' => false,
                'purchase_entry_count' => 0,
                'correction_entry_count' => 0,
                'canonical_correction_entry_count' => 0,
                'legacy_correction_entry_count' => 0,
                'duplicate_correction_path' => false,
                'current' => [],
                'desired' => [],
                'delta' => [],
                'signature' => null,
            ];
        }

        $purchaseEntries = $this->journalEntries($tenantId, $purchaseId, [self::PRIMARY_REFERENCE_TYPE]);
        $canonicalCorrectionEntries = $this->journalEntries($tenantId, $purchaseId, [self::CANONICAL_REFERENCE_TYPE]);
        $legacyCorrectionEntries = $this->journalEntries($tenantId, $purchaseId, self::LEGACY_REFERENCE_TYPES);
        $allRelevantEntries = $purchaseEntries
            ->concat($canonicalCorrectionEntries)
            ->concat($legacyCorrectionEntries)
            ->values();

        if ($purchaseEntries->isEmpty()) {
            return [
                'tenant_id' => $tenantId,
                'purchase_id' => $purchaseId,
                'status' => 'missing_journal',
                'can_sync' => false,
                'purchase_entry_count' => 0,
                'correction_entry_count' => $canonicalCorrectionEntries->count() + $legacyCorrectionEntries->count(),
                'canonical_correction_entry_count' => $canonicalCorrectionEntries->count(),
                'legacy_correction_entry_count' => $legacyCorrectionEntries->count(),
                'duplicate_correction_path' => false,
                'current' => [],
                'desired' => [],
                'delta' => [],
                'signature' => null,
            ];
        }

        if ($purchaseEntries->count() !== 1) {
            return [
                'tenant_id' => $tenantId,
                'purchase_id' => $purchaseId,
                'status' => 'ambiguous_purchase_journal',
                'can_sync' => false,
                'purchase_entry_count' => $purchaseEntries->count(),
                'correction_entry_count' => $canonicalCorrectionEntries->count() + $legacyCorrectionEntries->count(),
                'canonical_correction_entry_count' => $canonicalCorrectionEntries->count(),
                'legacy_correction_entry_count' => $legacyCorrectionEntries->count(),
                'duplicate_correction_path' => $this->hasDuplicateCorrectionPath($canonicalCorrectionEntries, $legacyCorrectionEntries),
                'current' => [],
                'desired' => [],
                'delta' => [],
                'signature' => null,
            ];
        }

        $purchaseEntry = $purchaseEntries->first();
        $purchaseItems = $this->journalItems((string) $purchaseEntry->id);
        if ($purchaseItems->isEmpty()) {
            return [
                'tenant_id' => $tenantId,
                'purchase_id' => $purchaseId,
                'status' => 'missing_journal_items',
                'can_sync' => false,
                'purchase_entry_count' => 1,
                'correction_entry_count' => $canonicalCorrectionEntries->count() + $legacyCorrectionEntries->count(),
                'canonical_correction_entry_count' => $canonicalCorrectionEntries->count(),
                'legacy_correction_entry_count' => $legacyCorrectionEntries->count(),
                'duplicate_correction_path' => $this->hasDuplicateCorrectionPath($canonicalCorrectionEntries, $legacyCorrectionEntries),
                'current' => [],
                'desired' => [],
                'delta' => [],
                'signature' => null,
            ];
        }

        $desiredCreditAccountId = $this->resolveDesiredCreditAccountId($purchase, $mapping);
        if (! $desiredCreditAccountId) {
            return [
                'tenant_id' => $tenantId,
                'purchase_id' => $purchaseId,
                'status' => 'missing_cash_account',
                'can_sync' => false,
                'purchase_entry_count' => 1,
                'correction_entry_count' => $canonicalCorrectionEntries->count() + $legacyCorrectionEntries->count(),
                'canonical_correction_entry_count' => $canonicalCorrectionEntries->count(),
                'legacy_correction_entry_count' => $legacyCorrectionEntries->count(),
                'duplicate_correction_path' => $this->hasDuplicateCorrectionPath($canonicalCorrectionEntries, $legacyCorrectionEntries),
                'current' => [],
                'desired' => [],
                'delta' => [],
                'signature' => null,
            ];
        }

        $originalBalances = $this->balancesFromItems($purchaseItems);
        $currentBalances = $this->balancesFromEntries($allRelevantEntries);
        $desiredBalances = $this->buildDesiredBalances($purchaseItems, $desiredCreditAccountId);
        $deltaBalances = $this->diffBalances($currentBalances, $desiredBalances);
        $signature = $this->buildSignature($tenantId, $purchaseId, (string) $purchase->payment_method, $desiredCreditAccountId, $desiredBalances);
        $duplicateCorrectionPath = $this->hasDuplicateCorrectionPath($canonicalCorrectionEntries, $legacyCorrectionEntries);

        $status = $this->classifyStatus($deltaBalances, $duplicateCorrectionPath, $canonicalCorrectionEntries->count(), $legacyCorrectionEntries->count());

        return [
            'tenant_id' => $tenantId,
            'purchase_id' => $purchaseId,
            'purchase_payment_method' => (string) $purchase->payment_method,
            'purchase_total' => round($this->snapshotTotal($originalBalances), 2),
            'status' => $status,
            'can_sync' => true,
            'purchase_entry_count' => 1,
            'correction_entry_count' => $canonicalCorrectionEntries->count() + $legacyCorrectionEntries->count(),
            'canonical_correction_entry_count' => $canonicalCorrectionEntries->count(),
            'legacy_correction_entry_count' => $legacyCorrectionEntries->count(),
            'duplicate_correction_path' => $duplicateCorrectionPath,
            'current' => $currentBalances,
            'desired' => $desiredBalances,
            'delta' => $deltaBalances,
            'signature' => $signature,
            'desired_credit_account_id' => $desiredCreditAccountId,
            'original_purchase_entry_id' => (string) $purchaseEntry->id,
            'original_purchase_date' => (string) $purchaseEntry->date,
        ];
    }

    public function sync(string $tenantId, string $purchaseId, ?string $actorId = null, string $source = 'live'): array
    {
        return DB::transaction(function () use ($tenantId, $purchaseId, $actorId, $source): array {
            $purchase = Purchase::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $purchaseId)
                ->lockForUpdate()
                ->first();

            if (! $purchase) {
                return [
                    'status' => 'missing_purchase',
                    'applied' => false,
                    'purchase_id' => $purchaseId,
                    'tenant_id' => $tenantId,
                ];
            }

            $inspection = $this->inspect($tenantId, $purchaseId);
            if (! ($inspection['can_sync'] ?? false)) {
                return $inspection + [
                    'applied' => false,
                ];
            }

            if (empty($inspection['delta'])) {
                return $inspection + [
                    'applied' => false,
                    'status' => ($inspection['duplicate_correction_path'] ?? false)
                        ? 'already_correct_duplicate_path'
                        : 'already_correct',
                ];
            }

            $deltaSum = round(array_sum(array_map('floatval', array_values($inspection['delta']))), 2);
            if (abs($deltaSum) > 0.01) {
                return $inspection + [
                    'applied' => false,
                    'status' => 'conflict',
                    'message' => 'Correction delta tidak seimbang.',
                ];
            }

            $now = CarbonImmutable::now('UTC');
            $journalEntryId = (string) Str::uuid();
            $signature = (string) ($inspection['signature'] ?? $this->buildSignature(
                $tenantId,
                $purchaseId,
                (string) ($inspection['purchase_payment_method'] ?? $purchase->payment_method),
                (string) ($inspection['desired_credit_account_id'] ?? ''),
                $inspection['desired'] ?? []
            ));

            JournalEntry::query()->create([
                'id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'reference_type' => self::CANONICAL_REFERENCE_TYPE,
                'reference_id' => $purchaseId,
                'date' => $now->toDateString(),
                'description' => 'Purchase payment correction :: '.$signature.' :: source='.$source,
                'status' => 'posted',
                'posted_at' => $now,
                'created_by' => $actorId,
                'created_at' => $now,
            ]);

            $journalItems = [];
            foreach ($inspection['delta'] as $accountId => $balance) {
                $balance = round((float) $balance, 2);
                if (abs($balance) < 0.01) {
                    continue;
                }

                $journalItems[] = [
                    'id' => (string) Str::uuid(),
                    'journal_entry_id' => $journalEntryId,
                    'tenant_id' => $tenantId,
                    'account_id' => (string) $accountId,
                    'debit' => $balance > 0 ? $balance : 0,
                    'credit' => $balance < 0 ? abs($balance) : 0,
                    'is_reversal' => false,
                ];
            }

            if ($journalItems === []) {
                return $inspection + [
                    'applied' => false,
                    'status' => 'already_correct',
                ];
            }

            JournalItem::query()->insert($journalItems);

            app(AuditLogger::class)->log(
                'purchase_payment_correction_posted',
                $actorId,
                'purchase_payment_correction',
                $purchaseId,
                [
                    'purchase_id' => $purchaseId,
                    'tenant_id' => $tenantId,
                    'source' => $source,
                    'signature' => $signature,
                    'status_before' => $inspection['status'] ?? null,
                    'duplicate_correction_path' => $inspection['duplicate_correction_path'] ?? false,
                    'purchase_payment_method' => $inspection['purchase_payment_method'] ?? $purchase->payment_method,
                    'desired_credit_account_id' => $inspection['desired_credit_account_id'] ?? null,
                    'delta' => $inspection['delta'],
                    'current' => $inspection['current'],
                    'desired' => $inspection['desired'],
                    'canonical_correction_entry_count' => $inspection['canonical_correction_entry_count'] ?? 0,
                    'legacy_correction_entry_count' => $inspection['legacy_correction_entry_count'] ?? 0,
                ],
                null,
                null,
                $tenantId,
                $now
            );

            return $inspection + [
                'applied' => true,
                'journal_entry_id' => $journalEntryId,
                'reference_type' => self::CANONICAL_REFERENCE_TYPE,
                'signature' => $signature,
                'source' => $source,
            ];
        });
    }

    private function loadPurchase(string $tenantId, string $purchaseId): ?Purchase
    {
        return Purchase::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $purchaseId)
            ->first();
    }

    /**
     * @param  array<int, string>  $referenceTypes
     */
    private function journalEntries(string $tenantId, string $purchaseId, array $referenceTypes): Collection
    {
        if ($referenceTypes === []) {
            return collect();
        }

        return DB::table('journal_entries')
            ->where('tenant_id', $tenantId)
            ->where('reference_id', $purchaseId)
            ->where('status', 'posted')
            ->whereIn('reference_type', $referenceTypes)
            ->orderBy('created_at')
            ->get([
                'id',
                'reference_type',
                'reference_id',
                'date',
                'description',
            ]);
    }

    private function journalItems(string $journalEntryId): Collection
    {
        return DB::table('journal_items')
            ->where('journal_entry_id', $journalEntryId)
            ->orderBy('id')
            ->get([
                'account_id',
                'debit',
                'credit',
            ]);
    }

    private function balancesFromEntries(Collection $entries): array
    {
        $balances = [];

        foreach ($entries as $entry) {
            $items = $this->journalItems((string) $entry->id);
            $balances = $this->mergeBalances($balances, $this->balancesFromItems($items));
        }

        return $this->normalizeBalances($balances);
    }

    private function balancesFromItems(Collection $items): array
    {
        $balances = [];

        foreach ($items as $item) {
            $accountId = (string) $item->account_id;
            $balances[$accountId] = round((float) ($balances[$accountId] ?? 0) + ((float) $item->debit - (float) $item->credit), 2);
        }

        return $this->normalizeBalances($balances);
    }

    private function buildDesiredBalances(Collection $purchaseItems, string $desiredCreditAccountId): array
    {
        $balances = [];
        $creditAmount = 0.0;

        foreach ($purchaseItems as $item) {
            $accountId = (string) $item->account_id;
            $balance = round((float) $item->debit - (float) $item->credit, 2);
            $balances[$accountId] = round((float) ($balances[$accountId] ?? 0) + $balance, 2);

            if ($balance < 0) {
                $creditAmount += abs($balance);
            }
        }

        foreach ($purchaseItems as $item) {
            $balance = round((float) $item->debit - (float) $item->credit, 2);
            if ($balance < 0) {
                $accountId = (string) $item->account_id;
                $balances[$accountId] = round((float) ($balances[$accountId] ?? 0) + abs($balance), 2);
            }
        }

        $balances[$desiredCreditAccountId] = round((float) ($balances[$desiredCreditAccountId] ?? 0) - $creditAmount, 2);

        return $this->normalizeBalances($balances);
    }

    private function diffBalances(array $current, array $desired): array
    {
        $accounts = array_unique(array_merge(array_keys($current), array_keys($desired)));
        $delta = [];

        foreach ($accounts as $accountId) {
            $currentBalance = (float) ($current[$accountId] ?? 0);
            $desiredBalance = (float) ($desired[$accountId] ?? 0);
            $diff = round($desiredBalance - $currentBalance, 2);

            if (abs($diff) < 0.01) {
                continue;
            }

            $delta[$accountId] = $diff;
        }

        return $delta;
    }

    private function resolveDesiredCreditAccountId(Purchase $purchase, AccountMapping $mapping): ?string
    {
        $paymentMethod = strtolower(trim((string) ($purchase->payment_method ?? 'tempo')));

        if ($paymentMethod === 'tunai') {
            $cashAccountId = $purchase->cash_account_id ?: $mapping->cash_account_id;

            return $cashAccountId ? (string) $cashAccountId : null;
        }

        return $mapping->payable_account_id ? (string) $mapping->payable_account_id : null;
    }

    private function buildSignature(string $tenantId, string $purchaseId, string $paymentMethod, string $desiredCreditAccountId, array $desiredBalances): string
    {
        return sha1(json_encode([
            'tenant_id' => $tenantId,
            'purchase_id' => $purchaseId,
            'payment_method' => strtolower($paymentMethod),
            'desired_credit_account_id' => $desiredCreditAccountId,
            'desired_balances' => $desiredBalances,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function hasDuplicateCorrectionPath(Collection $canonicalCorrectionEntries, Collection $legacyCorrectionEntries): bool
    {
        $referenceTypes = $canonicalCorrectionEntries
            ->pluck('reference_type')
            ->concat($legacyCorrectionEntries->pluck('reference_type'))
            ->unique()
            ->values();

        return $referenceTypes->count() > 1;
    }

    private function classifyStatus(array $deltaBalances, bool $duplicateCorrectionPath, int $canonicalCorrectionCount, int $legacyCorrectionCount): string
    {
        if ($deltaBalances === []) {
            if ($duplicateCorrectionPath) {
                return 'already_correct_duplicate_path';
            }

            if ($canonicalCorrectionCount > 0) {
                return 'already_correct';
            }

            if ($legacyCorrectionCount > 0) {
                return 'already_correct_legacy_path';
            }

            return 'already_correct';
        }

        if ($duplicateCorrectionPath) {
            return 'duplicate_correction_path';
        }

        return 'needs_correction';
    }

    private function mergeBalances(array $base, array $append): array
    {
        foreach ($append as $accountId => $balance) {
            $base[$accountId] = round((float) ($base[$accountId] ?? 0) + (float) $balance, 2);
        }

        return $this->normalizeBalances($base);
    }

    private function normalizeBalances(array $balances): array
    {
        foreach ($balances as $accountId => $balance) {
            $rounded = round((float) $balance, 2);
            if (abs($rounded) < 0.01) {
                unset($balances[$accountId]);

                continue;
            }

            $balances[$accountId] = $rounded;
        }

        ksort($balances);

        return $balances;
    }

    private function snapshotTotal(array $balances): float
    {
        return round(array_sum(array_map('abs', $balances)), 2);
    }
}