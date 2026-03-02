<?php

namespace App\Services;

use App\Models\DailyFinanceControlSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DailyFinanceControlService
{
    public function capture(string $tenantId, string $date): array
    {
        $snapshotDate = CarbonImmutable::parse($date, 'UTC')->toDateString();
        $windowStart = CarbonImmutable::parse($snapshotDate, 'UTC')->startOfDay();
        $windowEnd = CarbonImmutable::parse($snapshotDate, 'UTC')->endOfDay();
        $dueEnd = CarbonImmutable::parse($snapshotDate, 'UTC')->addDays(7)->toDateString();

        $salesRows = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereDate('transaction_date', $snapshotDate)
            ->get(['id', 'total']);

        $saleIds = $salesRows->pluck('id')->all();
        $salesTotal = (float) $salesRows->sum('total');
        $transactionCount = count($saleIds);

        $cogsTotal = 0.0;
        if (! empty($saleIds)) {
            $cogsTotal = (float) DB::table('sale_items')
                ->where('tenant_id', $tenantId)
                ->whereIn('sale_id', $saleIds)
                ->selectRaw('sum(qty * hpp_snapshot) as total')
                ->value('total');
        }

        $shiftRows = DB::table('shifts')
            ->where('tenant_id', $tenantId)
            ->where('status', 'closed')
            ->whereBetween('closed_at', [$windowStart, $windowEnd])
            ->get(['closing_cash', 'difference']);

        $cashClosingTotal = (float) $shiftRows->sum('closing_cash');
        $cashDifferenceTotal = (float) $shiftRows->sum(function ($row) {
            return abs((float) $row->difference);
        });
        $shiftClosedCount = $shiftRows->count();

        $debtDue7d = (float) DB::table('purchases')
            ->where('tenant_id', $tenantId)
            ->where('payment_method', 'tempo')
            ->whereNotNull('due_date')
            ->whereNotIn('status', ['cancelled', 'rejected', 'draft'])
            ->whereBetween('due_date', [$snapshotDate, $dueEnd])
            ->sum('total');

        $payload = [
            'sales_total' => round($salesTotal, 2),
            'cogs_total' => round($cogsTotal, 2),
            'cash_closing_total' => round($cashClosingTotal, 2),
            'debt_due_7d' => round($debtDue7d, 2),
            'cash_difference_total' => round($cashDifferenceTotal, 2),
            'shift_closed_count' => (int) $shiftClosedCount,
            'transaction_count' => (int) $transactionCount,
        ];

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
                ->update($payload);
        }

        return array_merge(['snapshot_date' => $snapshotDate], $payload);
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
}
