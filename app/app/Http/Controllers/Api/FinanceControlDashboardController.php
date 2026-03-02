<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DailyFinanceControlService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class FinanceControlDashboardController extends Controller
{
    public function __construct(private readonly DailyFinanceControlService $service)
    {
    }

    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = isset($validated['date'])
            ? CarbonImmutable::parse($validated['date'], 'UTC')->toDateString()
            : CarbonImmutable::now('UTC')->toDateString();

        $today = $this->service->capture($tenantId, $date);

        $weekStart = CarbonImmutable::parse($date, 'UTC')->subDays(6)->toDateString();
        $weekRows = $this->service->getRange($tenantId, $weekStart, $date);

        $weekly = [
            'sales_total' => round(collect($weekRows)->sum('sales_total'), 2),
            'cogs_total' => round(collect($weekRows)->sum('cogs_total'), 2),
            'cash_closing_total' => round(collect($weekRows)->sum('cash_closing_total'), 2),
            'debt_due_7d_max' => round(collect($weekRows)->max('debt_due_7d') ?? 0, 2),
            'cash_difference_total' => round(collect($weekRows)->sum('cash_difference_total'), 2),
            'shift_closed_count' => (int) collect($weekRows)->sum('shift_closed_count'),
            'transaction_count' => (int) collect($weekRows)->sum('transaction_count'),
        ];

        return response()->json([
            'date' => $date,
            'kpi_today' => $today,
            'kpi_week' => $weekly,
            'snapshots' => $weekRows,
        ]);
    }

    public function snapshot(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = isset($validated['date'])
            ? CarbonImmutable::parse($validated['date'], 'UTC')->toDateString()
            : CarbonImmutable::now('UTC')->toDateString();

        $snapshot = $this->service->capture($tenantId, $date);

        return response()->json([
            'status' => 'snapshotted',
            'snapshot' => $snapshot,
        ]);
    }

    public function export(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $toDate = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'], 'UTC')->toDateString()
            : CarbonImmutable::now('UTC')->toDateString();
        $fromDate = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'], 'UTC')->toDateString()
            : CarbonImmutable::parse($toDate, 'UTC')->subDays(6)->toDateString();

        $this->service->capture($tenantId, $toDate);
        $rows = $this->service->getRange($tenantId, $fromDate, $toDate);

        $lines = [
            'snapshot_date,sales_total,cogs_total,cash_closing_total,debt_due_7d,cash_difference_total,shift_closed_count,transaction_count',
        ];

        foreach ($rows as $row) {
            $lines[] = implode(',', [
                $row['snapshot_date'],
                $row['sales_total'],
                $row['cogs_total'],
                $row['cash_closing_total'],
                $row['debt_due_7d'],
                $row['cash_difference_total'],
                $row['shift_closed_count'],
                $row['transaction_count'],
            ]);
        }

        $csv = implode("\n", $lines);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="finance-control-'.$fromDate.'-to-'.$toDate.'.csv"',
        ]);
    }
}
