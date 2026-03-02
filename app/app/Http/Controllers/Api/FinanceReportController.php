<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use App\Support\TenantContext;

class FinanceReportController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }
        $preset = $request->query('preset', 'thisMonth');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $month = $request->query('month');

        if ($month && ! $startDate && ! $endDate) {
            $startDate = $month . '-01';
            $preset = 'custom';
        }

        [$startUtc, $endExclusiveUtc, $rangeMeta] = $this->resolveRange($preset, $startDate, $endDate);

        $sales = Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endExclusiveUtc)
            ->where('status', 'completed')
            ->get(['id', 'total']);

        $saleIds = $sales->pluck('id')->all();
        $revenue = (float) $sales->sum('total');

        $cogs = 0.0;
        if ($saleIds) {
            $cogs = (float) SaleItem::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('sale_id', $saleIds)
                ->selectRaw('sum(qty * hpp_snapshot) as total')
                ->value('total');
        }

        $expenses = [
            ['label' => 'HPP (Harga Pokok Penjualan)', 'amount' => $cogs],
            ['label' => 'Gaji Karyawan', 'amount' => 0],
            ['label' => 'Sewa dan Utilitas', 'amount' => 0],
            ['label' => 'Pemasaran', 'amount' => 0],
            ['label' => 'Operasional Lainnya', 'amount' => 0],
        ];

        $totalExpenses = array_reduce($expenses, function ($carry, $row) {
            return $carry + $row['amount'];
        }, 0.0);

        $netProfit = $revenue - $totalExpenses;

        return response()->json([
            'period' => $rangeMeta,
            'summary' => [
                'net_profit' => $netProfit,
                'revenue' => $revenue,
                'expenses' => $totalExpenses,
                'tax' => 0,
            ],
            'revenue_breakdown' => [
                ['label' => 'Penjualan POS', 'amount' => $revenue],
                ['label' => 'Pendapatan Lainnya', 'amount' => 0],
            ],
            'expense_breakdown' => $expenses,
            'balance_sheet' => [
                'assets' => [
                    ['label' => 'Kas dan Bank', 'amount' => $revenue],
                ],
                'liabilities' => [
                    ['label' => 'Hutang Usaha', 'amount' => 0],
                ],
                'equity' => [
                    ['label' => 'Modal Bersih', 'amount' => $netProfit],
                ],
            ],
        ]);
    }

    private function resolveRange(string $preset, ?string $startDate, ?string $endDate): array
    {
        $timezone = 'Asia/Jakarta';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        [$startLocal, $endLocal, $label] = match ($preset) {
            'today' => [$today, $today, 'Hari Ini'],
            'yesterday' => [$today->subDay(), $today->subDay(), 'Kemarin'],
            'last30', '30days' => [$today->subDays(29), $today, '30 Hari Terakhir'],
            'thisMonth' => [$today->startOfMonth(), $today->endOfMonth()->startOfDay(), 'Bulan Ini'],
            'lastMonth' => [
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth()->startOfDay(),
                'Bulan Lalu',
            ],
            'custom' => $this->resolveCustomRange($startDate, $endDate, $today),
            'last7', '7days' => [$today->subDays(6), $today, '7 Hari Terakhir'],
            default => [$today->startOfMonth(), $today->endOfMonth()->startOfDay(), 'Bulan Ini'],
        };

        $days = $startLocal->diffInDays($endLocal) + 1;
        $limited = false;
        if ($days > 90) {
            $endLocal = $startLocal->addDays(89);
            $limited = true;
        }

        return [
            $startLocal->setTimezone('UTC'),
            $endLocal->addDay()->startOfDay()->setTimezone('UTC'),
            [
                'preset' => $preset,
                'start_date' => $startLocal->toDateString(),
                'end_date' => $endLocal->toDateString(),
                'label' => $label,
                'timezone' => 'Asia/Jakarta',
                'limited' => $limited,
                'limit_days' => 90,
            ],
        ];
    }

    private function resolveCustomRange(?string $startDate, ?string $endDate, CarbonImmutable $fallback): array
    {
        $timezone = 'Asia/Jakarta';
        $startLocal = CarbonImmutable::parse($startDate ?? $fallback->toDateString(), $timezone)->startOfDay();
        $endLocal = CarbonImmutable::parse($endDate ?? $startDate ?? $fallback->toDateString(), $timezone)->startOfDay();

        if ($endLocal < $startLocal) {
            [$startLocal, $endLocal] = [$endLocal, $startLocal];
        }

        return [$startLocal, $endLocal, 'Periode Kustom'];
    }
}
