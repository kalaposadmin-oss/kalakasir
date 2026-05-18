<?php

namespace App\Services;

use App\Exceptions\LockPeriodException;
use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\CashTransaction;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashTransactionService
{
    private const CASHIER_TRANSACTION_LIMIT = 1500000;

    /**
     * @return array{cashAccounts: array<int, array<string, string>>, categoryOptions: array<int, array<string, string>>, recentTransactions: array<int, array<string, mixed>>, transactionHistory: array<int, array<string, mixed>>}
     */
    public function formContext(string $tenantId, string $direction): array
    {
        return [
            'cashAccounts' => $this->cashAccounts($tenantId),
            'categoryOptions' => $this->categoryOptions($direction),
            'recentTransactions' => $this->recentTransactions($tenantId, $direction),
            'transactionHistory' => $this->transactionHistory($tenantId, $direction),
        ];
    }

    /**
     * Context data for the unified Kas management page.
     *
     * @return array{cashAccounts: array, categoryOptions: array, transactionHistory: array, monthlySummary: array}
     */
    public function managementContext(string $tenantId, string $month): array
    {
        return [
            'cashAccounts' => $this->cashAccounts($tenantId),
            'categoryOptions' => [
                'cash-in' => $this->categoryOptions('cash-in'),
                'cash-out' => $this->categoryOptions('cash-out'),
            ],
            'transactionHistory' => $this->combinedTransactionHistory($tenantId, $month),
            'monthlySummary' => $this->monthlySummary($tenantId, $month),
        ];
    }

    /**
     * Combined transaction history for both cash-in and cash-out directions.
     *
     * @return array<int, array<string, mixed>>
     */
    private function combinedTransactionHistory(string $tenantId, string $month): array
    {
        $startDate = CarbonImmutable::parse($month . '-01')->startOfMonth()->toDateString();
        $endDate = CarbonImmutable::parse($month . '-01')->endOfMonth()->toDateString();

        $rows = DB::table('cash_transactions as ct')
            ->leftJoin('chart_of_accounts as cash_account', function ($join): void {
                $join->on('cash_account.id', '=', 'ct.account_id')
                    ->on('cash_account.tenant_id', '=', 'ct.tenant_id');
            })
            ->leftJoin('chart_of_accounts as counter_account', function ($join): void {
                $join->on('counter_account.id', '=', 'ct.counter_account_id')
                    ->on('counter_account.tenant_id', '=', 'ct.tenant_id');
            })
            ->leftJoin('users as creator', function ($join): void {
                $join->on('creator.id', '=', 'ct.created_by')
                    ->on('creator.tenant_id', '=', 'ct.tenant_id');
            })
            ->where('ct.tenant_id', $tenantId)
            ->whereBetween('ct.transaction_date', [$startDate, $endDate])
            ->orderByDesc('ct.transaction_date')
            ->orderByDesc('ct.created_at')
            ->limit(100)
            ->get([
                'ct.id',
                'ct.direction',
                'ct.transaction_date',
                'ct.category',
                'ct.amount',
                'ct.description',
                'ct.reference',
                'ct.balance_before',
                'ct.balance_after',
                'ct.created_at',
                'cash_account.code as cash_account_code',
                'cash_account.name as cash_account_name',
                'counter_account.code as counter_account_code',
                'counter_account.name as counter_account_name',
                'creator.name as created_by_name',
            ]);

        return $rows->map(function ($row): array {
            $cashAccountLabel = trim(((string) ($row->cash_account_code ?? '')) !== ''
                ? ((string) $row->cash_account_code).' - '.((string) ($row->cash_account_name ?? '-'))
                : (string) ($row->cash_account_name ?? '-'));
            $counterAccountLabel = trim(((string) ($row->counter_account_code ?? '')) !== ''
                ? ((string) $row->counter_account_code).' - '.((string) ($row->counter_account_name ?? '-'))
                : (string) ($row->counter_account_name ?? '-'));

            return [
                'id' => (string) $row->id,
                'direction' => (string) $row->direction,
                'transaction_date' => (string) $row->transaction_date,
                'category' => (string) $row->category,
                'amount' => (float) $row->amount,
                'description' => (string) $row->description,
                'reference' => $row->reference,
                'balance_before' => (float) ($row->balance_before ?? 0),
                'balance_after' => (float) ($row->balance_after ?? 0),
                'created_at' => $row->created_at,
                'account_label' => $cashAccountLabel,
                'counter_account_label' => $counterAccountLabel,
                'created_by_name' => $row->created_by_name,
            ];
        })->values()->all();
    }

    /**
     * Monthly summary of cash-in and cash-out totals.
     *
     * @return array{cash_in: float, cash_out: float, net: float, count: int}
     */
    private function monthlySummary(string $tenantId, string $month): array
    {
        $startDate = CarbonImmutable::parse($month . '-01')->startOfMonth()->toDateString();
        $endDate = CarbonImmutable::parse($month . '-01')->endOfMonth()->toDateString();

        $row = DB::table('cash_transactions')
            ->where('tenant_id', $tenantId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN direction = 'cash-in' THEN amount ELSE 0 END), 0) as cash_in,
                COALESCE(SUM(CASE WHEN direction = 'cash-out' THEN amount ELSE 0 END), 0) as cash_out,
                COUNT(*) as count
            ")
            ->first();

        $cashIn = (float) ($row->cash_in ?? 0);
        $cashOut = (float) ($row->cash_out ?? 0);

        return [
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net' => $cashIn - $cashOut,
            'count' => (int) ($row->count ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function store(string $tenantId, string $direction, array $payload, ?User $actor = null, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $this->validateDirection($direction);

        $validated = validator($payload, [
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'account_id' => ['required', 'uuid'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
        ])->validate();

        $amount = (float) $validated['amount'];
        $transactionDate = CarbonImmutable::parse((string) $validated['transaction_date']);

        $actorRole = $this->resolveActorRole($tenantId, $actor?->id);
        if ($actorRole === 'cashier' && $amount >= self::CASHIER_TRANSACTION_LIMIT) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal transaksi kas melebihi batas maksimal untuk cashier.',
            ]);
        }

        $categoryOptions = $this->categoryOptions($direction);
        if (! collect($categoryOptions)->contains('value', (string) $validated['category'])) {
            throw ValidationException::withMessages([
                'category' => 'Kategori transaksi tidak valid.',
            ]);
        }

        try {
            (new LockPeriodService())->ensureNotLocked($transactionDate, $tenantId);
        } catch (LockPeriodException $exception) {
            throw ValidationException::withMessages([
                'transaction_date' => 'Periode transaksi terkunci. Pilih tanggal di periode yang masih terbuka.',
            ]);
        }

        return DB::transaction(function () use ($tenantId, $direction, $validated, $actor, $ipAddress, $userAgent, $amount) {
            $cashAccountId = $this->resolvePostingAccountId($tenantId, (string) $validated['account_id']);
            $counterAccountId = $this->resolveCounterpartAccountId($tenantId, $direction, (string) $validated['category'], $cashAccountId);
            $balanceBefore = $this->accountBalance($tenantId, $cashAccountId, (string) $validated['transaction_date']);

            if ($direction === 'cash-out' && $balanceBefore < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Saldo kas/bank tidak mencukupi untuk kas keluar ini.',
                ]);
            }

            $now = CarbonImmutable::now('UTC');
            $transactionId = (string) Str::uuid();
            $journalEntryId = (string) Str::uuid();
            $categoryLabel = $this->categoryLabel($direction, (string) $validated['category']);
            $description = trim((string) $validated['description']);
            $reference = trim((string) ($validated['reference'] ?? ''));

            JournalEntry::query()->create([
                'id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'reference_type' => 'cash_transaction',
                'reference_id' => $transactionId,
                'date' => CarbonImmutable::parse((string) $validated['transaction_date'])->toDateString(),
                'description' => ($direction === 'cash-in' ? 'Kas Masuk: ' : 'Kas Keluar: ').$categoryLabel,
                'status' => 'posted',
                'posted_at' => $now,
                'created_by' => $actor?->id,
                'created_at' => $now,
            ]);

            $journalItems = $direction === 'cash-in'
                ? [
                    [
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journalEntryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $cashAccountId,
                        'debit' => $amount,
                        'credit' => 0,
                        'is_reversal' => false,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journalEntryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $counterAccountId,
                        'debit' => 0,
                        'credit' => $amount,
                        'is_reversal' => false,
                    ],
                ]
                : [
                    [
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journalEntryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $counterAccountId,
                        'debit' => $amount,
                        'credit' => 0,
                        'is_reversal' => false,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $journalEntryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $cashAccountId,
                        'debit' => 0,
                        'credit' => $amount,
                        'is_reversal' => false,
                    ],
                ];

            JournalItem::query()->insert($journalItems);

            $balanceAfter = $direction === 'cash-in'
                ? $balanceBefore + $amount
                : $balanceBefore - $amount;

            CashTransaction::query()->create([
                'id' => $transactionId,
                'tenant_id' => $tenantId,
                'direction' => $direction,
                'transaction_date' => CarbonImmutable::parse((string) $validated['transaction_date'])->toDateString(),
                'account_id' => $cashAccountId,
                'counter_account_id' => $counterAccountId,
                'category' => (string) $validated['category'],
                'amount' => $amount,
                'description' => $description,
                'reference' => $reference !== '' ? $reference : null,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'journal_entry_id' => $journalEntryId,
                'created_by' => $actor?->id,
                'created_at' => $now,
            ]);

            app(AuditLogger::class)->log(
                $direction === 'cash-in' ? 'cash_in_created' : 'cash_out_created',
                $actor?->id,
                'cash_transaction',
                $transactionId,
                [
                    'direction' => $direction,
                    'transaction_date' => (string) $validated['transaction_date'],
                    'account_id' => $cashAccountId,
                    'counter_account_id' => $counterAccountId,
                    'category' => (string) $validated['category'],
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'description' => $description,
                    'reference' => $reference !== '' ? $reference : null,
                ],
                $ipAddress,
                $userAgent,
                $tenantId,
                $now,
            );

            return [
                'transaction_id' => $transactionId,
                'journal_entry_id' => $journalEntryId,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'direction' => $direction,
                'category_label' => $categoryLabel,
            ];
        });
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function cashAccounts(string $tenantId): array
    {
        $mapping = AccountMapping::query()->where('tenant_id', $tenantId)->first();

        if (! $mapping) {
            return [];
        }

        $accounts = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_filter([$mapping->cash_account_id, $mapping->bank_account_id]))
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return $accounts->map(fn (ChartOfAccount $account) => [
            'value' => (string) $account->id,
            'label' => trim(($account->code ? $account->code.' - ' : '').$account->name),
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function categoryOptions(string $direction): array
    {
        return $direction === 'cash-in'
            ? [
                ['value' => 'capital_injection', 'label' => 'Tambahan modal / setoran owner'],
                ['value' => 'supplier_refund', 'label' => 'Refund supplier'],
                ['value' => 'customer_refund', 'label' => 'Refund pelanggan'],
                ['value' => 'other_income', 'label' => 'Penerimaan lain-lain'],
            ]
            : [
                ['value' => 'operational_expense', 'label' => 'Biaya operasional'],
                ['value' => 'utilities', 'label' => 'Listrik / air / internet'],
                ['value' => 'supplier_payment', 'label' => 'Bayar supplier tunai'],
                ['value' => 'asset_purchase', 'label' => 'Beli aset / peralatan'],
                ['value' => 'petty_cash', 'label' => 'Pengeluaran kas kecil'],
            ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentTransactions(string $tenantId, string $direction): array
    {
        return CashTransaction::query()
            ->where('tenant_id', $tenantId)
            ->where('direction', $direction)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(function (CashTransaction $transaction) {
                return [
                    'id' => $transaction->id,
                    'transaction_date' => $transaction->transaction_date?->toDateString(),
                    'category' => $transaction->category,
                    'amount' => (float) $transaction->amount,
                    'description' => $transaction->description,
                    'reference' => $transaction->reference,
                    'balance_after' => (float) $transaction->balance_after,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function transactionHistory(string $tenantId, string $direction): array
    {
        $rows = DB::table('cash_transactions as ct')
            ->leftJoin('chart_of_accounts as cash_account', function ($join): void {
                $join->on('cash_account.id', '=', 'ct.account_id')
                    ->on('cash_account.tenant_id', '=', 'ct.tenant_id');
            })
            ->leftJoin('chart_of_accounts as counter_account', function ($join): void {
                $join->on('counter_account.id', '=', 'ct.counter_account_id')
                    ->on('counter_account.tenant_id', '=', 'ct.tenant_id');
            })
            ->leftJoin('users as creator', function ($join): void {
                $join->on('creator.id', '=', 'ct.created_by')
                    ->on('creator.tenant_id', '=', 'ct.tenant_id');
            })
            ->where('ct.tenant_id', $tenantId)
            ->where('ct.direction', $direction)
            ->orderByDesc('ct.transaction_date')
            ->orderByDesc('ct.created_at')
            ->limit(50)
            ->get([
                'ct.id',
                'ct.transaction_date',
                'ct.category',
                'ct.amount',
                'ct.description',
                'ct.reference',
                'ct.balance_before',
                'ct.balance_after',
                'ct.created_at',
                'cash_account.code as cash_account_code',
                'cash_account.name as cash_account_name',
                'counter_account.code as counter_account_code',
                'counter_account.name as counter_account_name',
                'creator.name as created_by_name',
            ]);

        return $rows->map(function ($row): array {
            $cashAccountLabel = trim(((string) ($row->cash_account_code ?? '')) !== ''
                ? ((string) $row->cash_account_code).' - '.((string) ($row->cash_account_name ?? '-'))
                : (string) ($row->cash_account_name ?? '-'));
            $counterAccountLabel = trim(((string) ($row->counter_account_code ?? '')) !== ''
                ? ((string) $row->counter_account_code).' - '.((string) ($row->counter_account_name ?? '-'))
                : (string) ($row->counter_account_name ?? '-'));

            return [
                'id' => (string) $row->id,
                'transaction_date' => (string) $row->transaction_date,
                'category' => (string) $row->category,
                'amount' => (float) $row->amount,
                'description' => (string) $row->description,
                'reference' => $row->reference,
                'balance_before' => (float) ($row->balance_before ?? 0),
                'balance_after' => (float) ($row->balance_after ?? 0),
                'created_at' => $row->created_at,
                'account_label' => $cashAccountLabel,
                'counter_account_label' => $counterAccountLabel,
                'created_by_name' => $row->created_by_name,
            ];
        })->values()->all();
    }

    private function validateDirection(string $direction): void
    {
        if (! in_array($direction, ['cash-in', 'cash-out'], true)) {
            throw new \InvalidArgumentException('Arah transaksi kas tidak valid.');
        }
    }

    private function resolveActorRole(string $tenantId, ?string $userId): ?string
    {
        if (! $userId) {
            return null;
        }

        return app(UserRoleResolverService::class)->resolveNormalizedRole($tenantId, $userId);
    }

    private function resolvePostingAccountId(string $tenantId, string $accountId): string
    {
        $mapping = AccountMapping::query()->where('tenant_id', $tenantId)->firstOrFail();

        $allowed = array_filter([$mapping->cash_account_id, $mapping->bank_account_id]);
        if (! in_array($accountId, $allowed, true)) {
            throw ValidationException::withMessages([
                'account_id' => 'Akun yang dipilih harus akun kas atau bank aktif.',
            ]);
        }

        return $accountId;
    }

    private function resolveCounterpartAccountId(string $tenantId, string $direction, string $category, string $cashAccountId): string
    {
        $mapping = AccountMapping::query()->where('tenant_id', $tenantId)->firstOrFail();

        return match ($direction) {
            'cash-in' => match ($category) {
                'capital_injection' => $this->firstAccountIdByType($tenantId, 'equity'),
                'supplier_refund' => $mapping->payable_account_id,
                'customer_refund' => $mapping->receivable_account_id,
                'other_income' => $this->firstAccountIdByType($tenantId, 'revenue', $mapping->sale_account_id),
                default => throw ValidationException::withMessages([
                    'category' => 'Kategori kas masuk tidak didukung.',
                ]),
            },
            'cash-out' => match ($category) {
                'operational_expense', 'utilities', 'petty_cash' => $this->firstAccountIdByType($tenantId, 'expense', $mapping->expense_adjustment_account_id),
                'supplier_payment' => $mapping->payable_account_id,
                'asset_purchase' => $this->firstNonCashAssetAccountId($tenantId, $cashAccountId, $mapping->inventory_account_id),
                default => throw ValidationException::withMessages([
                    'category' => 'Kategori kas keluar tidak didukung.',
                ]),
            },
            default => throw ValidationException::withMessages([
                'direction' => 'Arah transaksi kas tidak valid.',
            ]),
        };
    }

    private function firstAccountIdByType(string $tenantId, string $type, ?string $fallbackId = null): string
    {
        $accountId = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->orderBy('code')
            ->value('id');

        if ($accountId) {
            return (string) $accountId;
        }

        if ($fallbackId) {
            return $fallbackId;
        }

        throw ValidationException::withMessages([
            'category' => 'Akun jurnal untuk kategori ini belum tersedia.',
        ]);
    }

    private function firstNonCashAssetAccountId(string $tenantId, string $cashAccountId, ?string $fallbackId = null): string
    {
        $accountId = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'asset')
            ->where('id', '!=', $cashAccountId)
            ->orderBy('code')
            ->value('id');

        if ($accountId) {
            return (string) $accountId;
        }

        if ($fallbackId) {
            return $fallbackId;
        }

        throw ValidationException::withMessages([
            'category' => 'Akun aset pengganti belum tersedia.',
        ]);
    }

    private function accountBalance(string $tenantId, string $accountId, string $asOfDate): float
    {
        $row = DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('ji.account_id', $accountId)
            ->where('je.status', 'posted')
            ->where('je.date', '<=', $asOfDate)
            ->selectRaw('coalesce(sum(ji.debit - ji.credit), 0) as balance')
            ->first();

        return (float) ($row->balance ?? 0);
    }

    private function categoryLabel(string $direction, string $category): string
    {
        $options = $this->categoryOptions($direction);

        foreach ($options as $option) {
            if ($option['value'] === $category) {
                return $option['label'];
            }
        }

        return $category;
    }
}