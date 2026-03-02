<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Sale;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request)
    {
        $tenantId = TenantContext::id();
        $preset = $request->query('preset', $request->query('range', 'thisMonth'));
        $from = $request->query('start_date', $request->query('from'));
        $to = $request->query('end_date', $request->query('to'));

        [$startUtc, $endExclusiveUtc, $rangeMeta, $startLocal, $endLocal] = $this->resolveRange($preset, $from, $to);

        $todayLocal = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        $todayStart = $todayLocal->setTimezone('UTC');
        $todayEnd = $todayLocal->endOfDay()->setTimezone('UTC');
        $yesterdayLocal = $todayLocal->subDay();
        $yesterdayStart = $yesterdayLocal->setTimezone('UTC');
        $yesterdayEnd = $yesterdayLocal->endOfDay()->setTimezone('UTC');

        $todayRevenue = (float) Sale::query()
            ->where('status', 'completed')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('total');

        $yesterdayRevenue = (float) Sale::query()
            ->where('status', 'completed')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
            ->sum('total');

        $todayOrders = (int) Sale::query()
            ->where('status', 'completed')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $yesterdayOrders = (int) Sale::query()
            ->where('status', 'completed')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
            ->count();

        $todayProfit = (float) DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->where('sales.tenant_id', $tenantId)
            ->where('sale_items.tenant_id', $tenantId)
            ->whereBetween('sales.created_at', [$todayStart, $todayEnd])
            ->sum(DB::raw('(sale_items.qty * sale_items.price) - sale_items.discount - (sale_items.qty * sale_items.hpp_snapshot)'));

        $yesterdayProfit = (float) DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->where('sales.tenant_id', $tenantId)
            ->where('sale_items.tenant_id', $tenantId)
            ->whereBetween('sales.created_at', [$yesterdayStart, $yesterdayEnd])
            ->sum(DB::raw('(sale_items.qty * sale_items.price) - sale_items.discount - (sale_items.qty * sale_items.hpp_snapshot)'));

        $hourExpr = match (DB::getDriverName()) {
            'mysql' => "DATE_FORMAT(created_at, '%H')",
            'pgsql' => "to_char(created_at, 'HH24')",
            default => "strftime('%H', created_at)",
        };

        $trendRows = Sale::query()
            ->selectRaw($hourExpr . ' as hour')
            ->selectRaw('sum(total) as total')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $trendMap = [];
        foreach ($trendRows as $row) {
            $trendMap[(int) $row->hour] = (float) $row->total;
        }

        $trendHours = [8, 12, 16, 20, 22];
        $trend = [];
        foreach ($trendHours as $hour) {
            $trend[] = [
                'hour' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00',
                'total' => $trendMap[$hour] ?? 0,
            ];
        }

        $topProducts = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', function ($join) {
                $join->on('categories.id', '=', 'products.category_id')
                    ->on('categories.tenant_id', '=', 'products.tenant_id');
            })
            ->where('sales.status', 'completed')
            ->where('sales.tenant_id', $tenantId)
            ->where('sale_items.tenant_id', $tenantId)
            ->where('products.tenant_id', $tenantId)
            ->where('sales.created_at', '>=', $startUtc)
            ->where('sales.created_at', '<', $endExclusiveUtc)
            ->where('products.sku', 'not like', 'FEE-%')
            ->select([
                'products.id',
                'products.name',
                'products.image_url',
                'categories.name as category_name',
            ])
            ->selectRaw('sum(sale_items.qty) as qty')
            ->selectRaw('sum((sale_items.qty * sale_items.price) - sale_items.discount) as revenue')
            ->groupBy('products.id', 'products.name', 'products.image_url', 'categories.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        $paymentRows = Payment::query()
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->where('sales.status', 'completed')
            ->where('sales.tenant_id', $tenantId)
            ->where('payments.tenant_id', $tenantId)
            ->where('sales.created_at', '>=', $startUtc)
            ->where('sales.created_at', '<', $endExclusiveUtc)
            ->select('payments.method')
            ->selectRaw('sum(payments.amount) as amount')
            ->groupBy('payments.method')
            ->get();

        $paymentTotal = (float) $paymentRows->sum('amount');
        $payments = $paymentRows->map(function ($row) use ($paymentTotal) {
            $amount = (float) $row->amount;
            return [
                'method' => $row->method,
                'amount' => $amount,
                'percent' => $paymentTotal > 0 ? round(($amount / $paymentTotal) * 100, 1) : 0,
            ];
        })->values();

        $daily = $this->buildDailySummary($tenantId, $startUtc, $endExclusiveUtc, $startLocal, $endLocal);
        $rangeTotals = $this->summarizeDaily($daily);

        $location = Location::query()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();
        if (! $location) {
            $location = Location::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->first();
        }
        $branch = $location
            ? Branch::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $location->branch_id)
                ->first()
            : null;

        return response()->json([
            'date' => $todayLocal->toDateString(),
            'location_label' => $location ? $location->name : 'Lokasi',
            'branch_label' => $branch ? $branch->name : 'Branch',
            'revenue' => [
                'today' => $todayRevenue,
                'yesterday' => $yesterdayRevenue,
            ],
            'orders' => [
                'today' => $todayOrders,
                'yesterday' => $yesterdayOrders,
            ],
            'profit' => [
                'today' => $todayProfit,
                'yesterday' => $yesterdayProfit,
            ],
            'range' => $rangeMeta,
            'summary' => $rangeTotals,
            'daily' => $daily,
            'trend' => $trend,
            'top_products' => $topProducts,
            'payments' => $payments,
        ]);
    }

    private function resolveRange(string $preset, ?string $from, ?string $to): array
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
            'custom' => $this->resolveCustomRange($from, $to, $today),
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
            $startLocal,
            $endLocal,
        ];
    }

    private function resolveCustomRange(?string $from, ?string $to, CarbonImmutable $today): array
    {
        $timezone = 'Asia/Jakarta';
        $fromLocal = CarbonImmutable::parse($from ?? $today->toDateString(), $timezone)->startOfDay();
        $toLocal = CarbonImmutable::parse($to ?? $from ?? $today->toDateString(), $timezone)->startOfDay();

        if ($toLocal < $fromLocal) {
            [$fromLocal, $toLocal] = [$toLocal, $fromLocal];
        }

        return [$fromLocal, $toLocal, 'Periode Kustom'];
    }

    private function buildDailySummary(string $tenantId, CarbonImmutable $startUtc, CarbonImmutable $endExclusiveUtc, CarbonImmutable $startLocal, CarbonImmutable $endLocal): array
    {
        $dateExpr = match (DB::getDriverName()) {
            'mysql' => "DATE(created_at)",
            'pgsql' => "DATE(created_at)",
            default => "DATE(created_at)",
        };

        $salesRows = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endExclusiveUtc)
            ->selectRaw($dateExpr . ' as date')
            ->selectRaw('sum(total) as revenue')
            ->selectRaw('sum(discount) as discount')
            ->selectRaw('sum(tax) as tax')
            ->selectRaw('count(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $itemRows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sale_items.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->where('sales.created_at', '>=', $startUtc)
            ->where('sales.created_at', '<', $endExclusiveUtc)
            ->selectRaw('DATE(sales.created_at) as date')
            ->selectRaw('sum(sale_items.qty) as items')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $profitRows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sale_items.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->where('sales.created_at', '>=', $startUtc)
            ->where('sales.created_at', '<', $endExclusiveUtc)
            ->selectRaw('DATE(sales.created_at) as date')
            ->selectRaw('sum((sale_items.qty * sale_items.price) - sale_items.discount - (sale_items.qty * sale_items.hpp_snapshot)) as profit')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $feeRows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sale_items.tenant_id', $tenantId)
            ->where('products.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->where('sales.created_at', '>=', $startUtc)
            ->where('sales.created_at', '<', $endExclusiveUtc)
            ->where('products.sku', 'like', 'FEE-%')
            ->selectRaw('DATE(sales.created_at) as date')
            ->selectRaw('sum(sale_items.qty * sale_items.price) as fee')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $salesMap = $salesRows->keyBy('date');
        $itemsMap = $itemRows->keyBy('date');
        $profitMap = $profitRows->keyBy('date');
        $feeMap = $feeRows->keyBy('date');

        $cursor = $startLocal->startOfDay();
        $daily = [];

        while ($cursor <= $endLocal) {
            $dateKey = $cursor->toDateString();
            $sales = $salesMap[$dateKey] ?? null;
            $items = $itemsMap[$dateKey] ?? null;
            $profit = $profitMap[$dateKey] ?? null;
            $fee = $feeMap[$dateKey] ?? null;

            $revenue = (float) ($sales?->revenue ?? 0);
            $orders = (int) ($sales?->orders ?? 0);
            $discount = (float) ($sales?->discount ?? 0);
            $tax = (float) ($sales?->tax ?? 0);
            $itemCount = (float) ($items?->items ?? 0);
            $feeTotal = (float) ($fee?->fee ?? 0);
            $profitTotal = (float) ($profit?->profit ?? 0);

            $daily[] = [
                'date' => $dateKey,
                'revenue' => $revenue,
                'orders' => $orders,
                'items' => $itemCount,
                'discount' => $discount,
                'fee' => $feeTotal,
                'tax' => $tax,
                'profit' => $profitTotal,
                'aov' => $orders > 0 ? round($revenue / $orders, 2) : 0,
            ];

            $cursor = $cursor->addDay();
        }

        return $daily;
    }

    private function summarizeDaily(array $daily): array
    {
        $totals = [
            'revenue' => 0,
            'orders' => 0,
            'items' => 0,
            'discount' => 0,
            'fee' => 0,
            'tax' => 0,
            'profit' => 0,
        ];

        foreach ($daily as $row) {
            $totals['revenue'] += (float) ($row['revenue'] ?? 0);
            $totals['orders'] += (int) ($row['orders'] ?? 0);
            $totals['items'] += (float) ($row['items'] ?? 0);
            $totals['discount'] += (float) ($row['discount'] ?? 0);
            $totals['fee'] += (float) ($row['fee'] ?? 0);
            $totals['tax'] += (float) ($row['tax'] ?? 0);
            $totals['profit'] += (float) ($row['profit'] ?? 0);
        }

        $totals['aov'] = $totals['orders'] > 0 ? round($totals['revenue'] / $totals['orders'], 2) : 0;

        return $totals;
    }
}
