<?php

namespace App\Services;

use App\Models\DailyFinanceControlSnapshot;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DailyFinanceControlService
{
    public function calculate(string $tenantId, string $date): array
    {
        $snapshotDate = CarbonImmutable::parse($date, 'UTC')->toDateString();
        $windowStart = CarbonImmutable::parse($snapshotDate, 'UTC')->startOfDay();
        $windowEnd = CarbonImmutable::parse($snapshotDate, 'UTC')->endOfDay();
        $dueEnd = CarbonImmutable::parse($snapshotDate, 'UTC')->addDays(7)->toDateString();

        $salesRows = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            // Daily finance control is a business-day snapshot, so sales use transaction_date.
            // Legacy rows that still have null transaction_date fall back to created_at date.
            ->whereRaw('DATE(COALESCE(transaction_date, created_at)) = ?', [$snapshotDate])
            ->get(['id', 'total']);

        $saleIds = $salesRows->pluck('id')->all();
        $salesTotal = $salesRows->reduce(
            fn (string $carry, $row) => Decimal::add($carry, Decimal::money($row->total), Decimal::MONEY_SCALE),
            Decimal::money('0')
        );
        $transactionCount = count($saleIds);

        $cogsTotal = Decimal::money('0');
        if (! empty($saleIds)) {
            $cogsTotal = Decimal::money(DB::table('sale_items')
                ->where('tenant_id', $tenantId)
                ->whereIn('sale_id', $saleIds)
                ->selectRaw('sum(qty * hpp_snapshot) as total')
                ->value('total'));
        }

        $shiftRows = DB::table('shifts')
            ->where('tenant_id', $tenantId)
            ->where('status', 'closed')
            ->whereBetween('closed_at', [$windowStart, $windowEnd])
            ->get(['closing_cash', 'difference']);

        $cashClosingTotal = $shiftRows->reduce(
            fn (string $carry, $row) => Decimal::add($carry, Decimal::money($row->closing_cash), Decimal::MONEY_SCALE),
            Decimal::money('0')
        );
        $cashDifferenceTotal = $shiftRows->reduce(
            fn (string $carry, $row) => Decimal::add($carry, Decimal::abs($row->difference, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
            Decimal::money('0')
        );
        $shiftClosedCount = $shiftRows->count();

        $debtDue7d = Decimal::money(DB::table('purchases')
            ->where('tenant_id', $tenantId)
            ->where('payment_method', 'tempo')
            ->whereNotNull('due_date')
            ->whereNotIn('status', ['cancelled', 'rejected', 'draft'])
            ->whereBetween('due_date', [$snapshotDate, $dueEnd])
            ->sum('total'));

        $payload = [
            'sales_total' => Decimal::float($salesTotal, Decimal::MONEY_SCALE),
            'cogs_total' => Decimal::float($cogsTotal, Decimal::MONEY_SCALE),
            'cash_closing_total' => Decimal::float($cashClosingTotal, Decimal::MONEY_SCALE),
            'debt_due_7d' => Decimal::float($debtDue7d, Decimal::MONEY_SCALE),
            'cash_difference_total' => Decimal::float($cashDifferenceTotal, Decimal::MONEY_SCALE),
            'shift_closed_count' => (int) $shiftClosedCount,
            'transaction_count' => (int) $transactionCount,
        ];

        return array_merge(['snapshot_date' => $snapshotDate], $payload);
    }

    public function capture(string $tenantId, string $date): array
    {
        $payload = $this->calculate($tenantId, $date);
        $snapshotDate = $payload['snapshot_date'];

        $existing = DailyFinanceControlSnapshot::query()
            ->where('tenant_id', $tenantId)
            ->where('snapshot_date', $snapshotDate)
            ->first();

        if (! $existing) {
            DailyFinanceControlSnapshot::query()->create(array_merge($payload, [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'snapshot_date' => $snapshotDate,
                'created_at' => CarbonImmutable::now('UTC'),
            ]));
        } else {
            DailyFinanceControlSnapshot::query()
                ->where('tenant_id', $tenantId)
                ->where('snapshot_date', $snapshotDate)
                ->update(collect($payload)->except('snapshot_date')->all());
        }

        return $payload;
    }

    public function getRange(string $tenantId, string $fromDate, string $toDate): array
    {
        return DailyFinanceControlSnapshot::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('snapshot_date', [$fromDate, $toDate])
            ->orderBy('snapshot_date')
            ->get([
                'snapshot_date',
                'sales_total',
                'cogs_total',
                'cash_closing_total',
                'debt_due_7d',
                'cash_difference_total',
                'shift_closed_count',
                'transaction_count',
            ])
            ->map(fn ($row) => [
                'snapshot_date' => $row->snapshot_date,
                'sales_total' => (float) $row->sales_total,
                'cogs_total' => (float) $row->cogs_total,
                'cash_closing_total' => (float) $row->cash_closing_total,
                'debt_due_7d' => (float) $row->debt_due_7d,
                'cash_difference_total' => (float) $row->cash_difference_total,
                'shift_closed_count' => (int) $row->shift_closed_count,
                'transaction_count' => (int) $row->transaction_count,
            ])
            ->all();
    }

    public function readRange(string $tenantId, string $fromDate, string $toDate): array
    {
        $stored = collect($this->getRange($tenantId, $fromDate, $toDate))
            ->keyBy('snapshot_date');

        return collect(CarbonPeriod::create($fromDate, $toDate))
            ->map(function ($date) use ($tenantId, $stored) {
                $snapshotDate = CarbonImmutable::parse($date, 'UTC')->toDateString();

                return $stored->get($snapshotDate) ?? $this->calculate($tenantId, $snapshotDate);
            })
            ->values()
            ->all();
    }
}
