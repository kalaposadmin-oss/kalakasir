<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinanceGlReportService
{
    public function build(string $tenantId, string $startDate, string $endDate, bool $includeBalanceSheet = true): array
    {
        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->first();

        $periodRows = $this->accountBalancesForPeriod($tenantId, $startDate, $endDate);
        $periodGroups = $periodRows->groupBy('type');

        $revenueRows = $this->formatRows($periodGroups->get('revenue', collect()));
        $expenseRows = $this->formatRows($periodGroups->get('expense', collect()));

        $revenueTotal = collect($revenueRows)->reduce(
            fn (string $carry, array $row) => Decimal::add($carry, Decimal::money($row['amount']), Decimal::MONEY_SCALE),
            Decimal::money('0')
        );
        $expenseTotal = collect($expenseRows)->reduce(
            fn (string $carry, array $row) => Decimal::add($carry, Decimal::money($row['amount']), Decimal::MONEY_SCALE),
            Decimal::money('0')
        );
        $tax = $this->resolveTaxAmount($tenantId, $startDate, $endDate);
        $incompleteSections = [];
        if ($tax === null) {
            $incompleteSections[] = 'tax';
        }

        $payload = [
            'summary' => [
                'net_profit' => Decimal::float(Decimal::subtract($revenueTotal, $expenseTotal, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
                'revenue' => Decimal::float($revenueTotal, Decimal::MONEY_SCALE),
                'expenses' => Decimal::float($expenseTotal, Decimal::MONEY_SCALE),
                'tax' => $tax,
            ],
            'revenue_breakdown' => $revenueRows,
            'expense_breakdown' => $expenseRows,
            'meta' => [
                'source' => 'journal_gl',
                'basis' => 'journal_entries.date',
                'incomplete_sections' => $incompleteSections,
                'mapping_ready' => (bool) $mapping,
                'cogs_account_id' => $mapping?->cogs_account_id,
                'cash_account_id' => $mapping?->cash_account_id,
                'bank_account_id' => $mapping?->bank_account_id,
                // TODO: add explicit tax/equity mapping when tenant accounting setup supports it.
            ],
        ];

        if ($includeBalanceSheet) {
            $payload['balance_sheet'] = $this->buildBalanceSheet($tenantId, $endDate, $mapping);
        }

        return $payload;
    }

    private function buildBalanceSheet(string $tenantId, string $asOfDate, ?AccountMapping $mapping): array
    {
        $rows = $this->accountBalancesUpTo($tenantId, $asOfDate);
        $groups = $rows->groupBy('type');

        $assetRows = $this->formatRows($groups->get('asset', collect()));
        $liabilityRows = $this->formatRows($groups->get('liability', collect()));
        $equityRows = $this->formatRows($groups->get('equity', collect()));

        $currentEarnings = $this->profitAndLossThroughDate($tenantId, $asOfDate);
        if (Decimal::compare(Decimal::abs($currentEarnings, Decimal::MONEY_SCALE), '0.00') > 0) {
            $equityRows[] = [
                'label' => 'Laba Berjalan',
                'amount' => $currentEarnings,
                'account_id' => null,
                'code' => null,
                'derived' => true,
            ];
        }

        $notes = [];
        if ($mapping === null) {
            $notes[] = 'Account mapping tenant belum lengkap; identifikasi akun kas/bank bergantung pada chart of accounts.';
        }
        if (empty($equityRows)) {
            $notes[] = 'Tidak ada akun equity aktif atau saldo equity GL belum tersedia.';
        }

        $cashAndBank = [];
        foreach ([$mapping?->cash_account_id, $mapping?->bank_account_id] as $accountId) {
            if (! $accountId) {
                continue;
            }

            $matched = collect($assetRows)->firstWhere('account_id', (string) $accountId);
            if ($matched) {
                $cashAndBank[] = $matched;
            }
        }

        return [
            'assets' => $assetRows,
            'liabilities' => $liabilityRows,
            'equity' => $equityRows,
            'cash_and_bank' => array_values($cashAndBank),
            'status' => $notes === [] ? 'complete' : 'incomplete',
            'notes' => $notes,
            'as_of_date' => $asOfDate,
        ];
    }

    private function accountBalancesForPeriod(string $tenantId, string $startDate, string $endDate): Collection
    {
        return DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->join('chart_of_accounts as coa', function ($join) {
                $join->on('coa.id', '=', 'ji.account_id')
                    ->on('coa.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('je.status', 'posted')
            ->whereBetween('je.date', [$startDate, $endDate])
            ->groupBy('ji.account_id', 'coa.code', 'coa.name', 'coa.type')
            ->orderBy('coa.code')
            ->get([
                'ji.account_id',
                'coa.code',
                'coa.name',
                'coa.type',
                DB::raw('sum(ji.debit) as debit_total'),
                DB::raw('sum(ji.credit) as credit_total'),
            ])
            ->map(function ($row) {
                return [
                    'account_id' => (string) $row->account_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'amount' => $this->normalizedAmount(
                        (string) $row->type,
                        (float) $row->debit_total,
                        (float) $row->credit_total
                    ),
                ];
            })
            ->filter(fn (array $row) => Decimal::compare(Decimal::abs($row['amount'], Decimal::MONEY_SCALE), '0.00') > 0)
            ->values();
    }

    private function accountBalancesUpTo(string $tenantId, string $asOfDate): Collection
    {
        return DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->join('chart_of_accounts as coa', function ($join) {
                $join->on('coa.id', '=', 'ji.account_id')
                    ->on('coa.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('je.status', 'posted')
            ->where('je.date', '<=', $asOfDate)
            ->whereIn('coa.type', ['asset', 'liability', 'equity'])
            ->groupBy('ji.account_id', 'coa.code', 'coa.name', 'coa.type')
            ->orderBy('coa.code')
            ->get([
                'ji.account_id',
                'coa.code',
                'coa.name',
                'coa.type',
                DB::raw('sum(ji.debit) as debit_total'),
                DB::raw('sum(ji.credit) as credit_total'),
            ])
            ->map(function ($row) {
                return [
                    'account_id' => (string) $row->account_id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'amount' => $this->normalizedAmount(
                        (string) $row->type,
                        (float) $row->debit_total,
                        (float) $row->credit_total
                    ),
                ];
            })
            ->filter(fn (array $row) => Decimal::compare(Decimal::abs($row['amount'], Decimal::MONEY_SCALE), '0.00') > 0)
            ->values();
    }

    private function profitAndLossThroughDate(string $tenantId, string $asOfDate): float
    {
        $rows = DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->join('chart_of_accounts as coa', function ($join) {
                $join->on('coa.id', '=', 'ji.account_id')
                    ->on('coa.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('je.status', 'posted')
            ->where('je.date', '<=', $asOfDate)
            ->whereIn('coa.type', ['revenue', 'expense'])
            ->groupBy('coa.type')
            ->get([
                'coa.type',
                DB::raw('sum(ji.debit) as debit_total'),
                DB::raw('sum(ji.credit) as credit_total'),
            ]);

        $revenue = Decimal::money('0');
        $expense = Decimal::money('0');

        foreach ($rows as $row) {
            $amount = Decimal::money($this->normalizedAmount(
                (string) $row->type,
                (float) $row->debit_total,
                (float) $row->credit_total
            ));

            if ($row->type === 'revenue') {
                $revenue = Decimal::add($revenue, $amount, Decimal::MONEY_SCALE);
            }
            if ($row->type === 'expense') {
                $expense = Decimal::add($expense, $amount, Decimal::MONEY_SCALE);
            }
        }

        return Decimal::float(Decimal::subtract($revenue, $expense, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE);
    }

    private function resolveTaxAmount(string $tenantId, string $startDate, string $endDate): ?float
    {
        $taxAccounts = DB::table('chart_of_accounts')
            ->where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->where('name', 'like', '%pajak%')
                    ->orWhere('name', 'like', '%tax%')
                    ->orWhere('code', 'like', '22%');
            })
            ->pluck('id');

        if ($taxAccounts->isEmpty()) {
            return null;
        }

        $rows = DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->join('chart_of_accounts as coa', function ($join) {
                $join->on('coa.id', '=', 'ji.account_id')
                    ->on('coa.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenantId)
            ->where('je.status', 'posted')
            ->whereBetween('je.date', [$startDate, $endDate])
            ->whereIn('ji.account_id', $taxAccounts)
            ->groupBy('coa.type')
            ->get([
                'coa.type',
                DB::raw('sum(ji.debit) as debit_total'),
                DB::raw('sum(ji.credit) as credit_total'),
            ]);

        $total = Decimal::money('0');
        foreach ($rows as $row) {
            $total = Decimal::add(
                $total,
                Decimal::money($this->normalizedAmount(
                    (string) $row->type,
                    (float) $row->debit_total,
                    (float) $row->credit_total
                )),
                Decimal::MONEY_SCALE
            );
        }

        return Decimal::float($total, Decimal::MONEY_SCALE);
    }

    private function formatRows(Collection $rows): array
    {
        return $rows
            ->sortBy('code')
            ->map(fn (array $row) => [
                'label' => trim(($row['code'] ? $row['code'].' - ' : '').$row['name']),
                'amount' => $row['amount'],
                'account_id' => $row['account_id'],
                'code' => $row['code'],
            ])
            ->values()
            ->all();
    }

    private function normalizedAmount(string $type, float $debit, float $credit): float
    {
        return match ($type) {
            'asset', 'expense' => $debit - $credit,
            'liability', 'equity', 'revenue' => $credit - $debit,
            default => 0.0,
        };
    }
}
