<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MissingSalesCostAggregateException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\AnalyticsMetricsService;
use App\Services\SalesCostReadinessService;
use App\Services\SystemHealthEventService;
use App\Support\Decimal;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request)
    {
        $startedAt = microtime(true);
        $tenantId = null;

        try {
            $tenantId = TenantContext::id();
            $preset = $request->query('preset', $request->query('range', 'thisMonth'));
            $from = $request->query('start_date', $request->query('from'));
            $to = $request->query('end_date', $request->query('to'));

            [$startUtc, $endExclusiveUtc, $rangeMeta, $startLocal, $endLocal] = $this->resolveRange($preset, $from, $to);
            $cacheKey = sprintf(
                'analytics:%s:%s:%s:%s',
                $tenantId ?: 'no-tenant',
                $rangeMeta['preset'] ?? $preset,
                $rangeMeta['start_date'] ?? 'na',
                $rangeMeta['end_date'] ?? 'na'
            );
            if ($tenantId && ! $request->boolean('fresh') && is_array($cached = Cache::get($cacheKey))) {
                return response()->json($cached);
            }

            $todayLocal = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
            $todayStart = $todayLocal->setTimezone('UTC');
            $todayEnd = $todayLocal->endOfDay()->setTimezone('UTC');
            $yesterdayLocal = $todayLocal->subDay();
            $yesterdayStart = $yesterdayLocal->setTimezone('UTC');
            $yesterdayEnd = $yesterdayLocal->endOfDay()->setTimezone('UTC');

            app(SalesCostReadinessService::class)->assertNoMissingCompletedSalesForBusinessDateRange(
                $tenantId,
                $rangeMeta['start_date'],
                $rangeMeta['end_date'],
                'analytics'
            );

            $metricsService = app(AnalyticsMetricsService::class);
            $todaySummary = $metricsService->summaryForBusinessDate($tenantId, $todayLocal->toDateString());
            $yesterdaySummary = $metricsService->summaryForBusinessDate($tenantId, $yesterdayLocal->toDateString());

        $todayRevenue = Decimal::float($todaySummary['revenue'] ?? 0, Decimal::MONEY_SCALE);
        $yesterdayRevenue = Decimal::float($yesterdaySummary['revenue'] ?? 0, Decimal::MONEY_SCALE);
        $todayOrders = (int) ($todaySummary['orders'] ?? 0);
        $yesterdayOrders = (int) ($yesterdaySummary['orders'] ?? 0);
        $todayProfit = Decimal::float($todaySummary['profit'] ?? 0, Decimal::MONEY_SCALE);
        $yesterdayProfit = Decimal::float($yesterdaySummary['profit'] ?? 0, Decimal::MONEY_SCALE);

        $hourExpr = match (DB::getDriverName()) {
            'mysql' => "DATE_FORMAT(created_at, '%H')",
            'pgsql' => "to_char(created_at, 'HH24')",
            default => "strftime('%H', created_at)",
        };

        $trendRows = Sale::query()
            ->selectRaw($hourExpr . ' as hour')
            ->selectRaw('sum(total) as total')
            ->where('status', 'completed')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $refundTrendRows = DB::table('refunds')
            ->selectRaw($hourExpr . ' as hour')
            ->selectRaw('sum(total) as total')
            ->where('status', 'completed')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $trendMap = [];
        foreach ($trendRows as $row) {
            $trendMap[(int) $row->hour] = (float) $row->total;
        }
        foreach ($refundTrendRows as $row) {
            $hour = (int) $row->hour;
            $trendMap[$hour] = ($trendMap[$hour] ?? 0) - (float) $row->total;
        }

        $trendHours = [8, 12, 16, 20, 22];
        $trend = [];
        foreach ($trendHours as $hour) {
            $trend[] = [
                'hour' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00',
                'total' => $trendMap[$hour] ?? 0,
            ];
        }

        $topSalesProducts = DB::table('sale_items')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'sale_items.sale_id')
                    ->on('sales.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->join('products', function ($join) {
                $join->on('products.id', '=', 'sale_items.product_id')
                    ->on('products.tenant_id', '=', 'sale_items.tenant_id');
            })
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
            ->get();

        $topRefundProducts = DB::table('refund_items')
            ->join('refunds', function ($join) {
                $join->on('refunds.id', '=', 'refund_items.refund_id')
                    ->on('refunds.tenant_id', '=', 'refund_items.tenant_id');
            })
            ->join('products', function ($join) {
                $join->on('products.id', '=', 'refund_items.product_id')
                    ->on('products.tenant_id', '=', 'refund_items.tenant_id');
            })
            ->leftJoin('categories', function ($join) {
                $join->on('categories.id', '=', 'products.category_id')
                    ->on('categories.tenant_id', '=', 'products.tenant_id');
            })
            ->where('refunds.status', 'completed')
            ->where('refunds.tenant_id', $tenantId)
            ->where('refund_items.tenant_id', $tenantId)
            ->where('products.tenant_id', $tenantId)
            ->where('refunds.created_at', '>=', $startUtc)
            ->where('refunds.created_at', '<', $endExclusiveUtc)
            ->where('products.sku', 'not like', 'FEE-%')
            ->select([
                'products.id',
                'products.name',
                'products.image_url',
                'categories.name as category_name',
            ])
            ->selectRaw('sum(refund_items.qty) as qty')
            ->selectRaw('sum(refund_items.gross_amount - refund_items.item_discount_amount - refund_items.transaction_discount_amount) as revenue')
            ->groupBy('products.id', 'products.name', 'products.image_url', 'categories.name')
            ->get();

        $topProducts = $this->mergeTopProducts($topSalesProducts, $topRefundProducts);

        $paymentRows = Payment::query()
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'payments.sale_id')
                    ->on('sales.tenant_id', '=', 'payments.tenant_id');
            })
            ->where('sales.status', 'completed')
            ->where('sales.tenant_id', $tenantId)
            ->where('payments.tenant_id', $tenantId)
            ->where('sales.created_at', '>=', $startUtc)
            ->where('sales.created_at', '<', $endExclusiveUtc)
            ->select('payments.method')
            ->selectRaw('sum(payments.amount) as amount')
            ->groupBy('payments.method')
            ->get();

        $refundPaymentRows = DB::table('refund_payments')
            ->join('refunds', function ($join) {
                $join->on('refunds.id', '=', 'refund_payments.refund_id')
                    ->on('refunds.tenant_id', '=', 'refund_payments.tenant_id');
            })
            ->where('refunds.status', 'completed')
            ->where('refunds.tenant_id', $tenantId)
            ->where('refund_payments.tenant_id', $tenantId)
            ->where('refunds.created_at', '>=', $startUtc)
            ->where('refunds.created_at', '<', $endExclusiveUtc)
            ->select('refund_payments.method')
            ->selectRaw('sum(refund_payments.amount) as amount')
            ->groupBy('refund_payments.method')
            ->get();

        $paymentMap = [];
        foreach ($paymentRows as $row) {
            $paymentMap[$row->method] = ($paymentMap[$row->method] ?? 0) + (float) $row->amount;
        }
        foreach ($refundPaymentRows as $row) {
            $paymentMap[$row->method] = ($paymentMap[$row->method] ?? 0) - (float) $row->amount;
        }

        $paymentTotal = array_sum(array_filter($paymentMap, fn ($amount) => $amount > 0));
        $payments = collect($paymentMap)
            ->map(function ($amount, $method) use ($paymentTotal) {
                $amount = (float) $amount;

                return [
                    'method' => $method,
                    'amount' => $amount,
                    'percent' => $paymentTotal > 0 && $amount > 0 ? round(($amount / $paymentTotal) * 100, 1) : 0,
                ];
            })
            ->filter(fn (array $row) => abs($row['amount']) > 0.00001)
            ->sortByDesc('amount')
            ->values();

        $daily = $metricsService->dailySummary($tenantId, $rangeMeta['start_date'], $rangeMeta['end_date']);
        $rangeTotals = $metricsService->summaryForRange($tenantId, $rangeMeta['start_date'], $rangeMeta['end_date']);
        $topProducts = $metricsService->topProducts($tenantId, $rangeMeta['start_date'], $rangeMeta['end_date']);
        $payments = $metricsService->paymentMix($tenantId, $rangeMeta['start_date'], $rangeMeta['end_date']);
        $trend = $metricsService->trendForEventDay(
            $tenantId,
            $todayLocal->toDateString(),
            $todayStart->toDateTimeString(),
            $todayEnd->toDateTimeString(),
        );

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

            $payload = [
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
            ];

            Cache::put($cacheKey, $payload, now('UTC')->addSeconds(45));

            $response = response()->json($payload);
        } catch (MissingSalesCostAggregateException $exception) {
            app(SystemHealthEventService::class)->recordOrBump('strict_reader_blocked', [
                'tenant_id' => $tenantId,
                'entity_type' => 'report_endpoint',
                'entity_id' => 'analytics',
                'summary' => 'Strict reader blocked analytics because aggregate COGS is invalid.',
                'payload' => [
                    'endpoint' => 'analytics',
                    'query' => $request->query(),
                    'details' => $exception->details(),
                ],
                'source' => 'analytics-controller',
                'dedupe_key' => 'strict_reader_blocked:'.($tenantId ?? 'unknown').':analytics:'.sha1(json_encode($request->query(), JSON_UNESCAPED_SLASHES)),
            ]);

            $response = response()->json([
                'code' => 'SALES_COST_AGGREGATE_REQUIRED',
                'message' => $exception->getMessage(),
                'details' => $exception->details(),
            ], 409);
        }

        $this->recordSlowQueryIfNeeded($tenantId, 'analytics', $startedAt, [
            'query' => $request->query(),
        ]);

        return $response;
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

    private function recordSlowQueryIfNeeded(?string $tenantId, string $endpoint, float $startedAt, array $payload = []): void
    {
        $durationMs = (microtime(true) - $startedAt) * 1000;
        $thresholdMs = max(1, (int) config('system_health.report_slow_ms', 1500));

        if ($tenantId === null || $durationMs < $thresholdMs) {
            return;
        }

        app(SystemHealthEventService::class)->recordOrBump('report_query_slow', [
            'tenant_id' => $tenantId,
            'entity_type' => 'report_endpoint',
            'entity_id' => $endpoint,
            'summary' => 'Report query exceeded slow threshold.',
            'payload' => array_merge($payload, [
                'endpoint' => $endpoint,
                'duration_ms' => round($durationMs, 2),
                'threshold_ms' => $thresholdMs,
            ]),
            'source' => 'report-query',
            'dedupe_key' => 'report_query_slow:'.$tenantId.':'.$endpoint,
        ]);
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
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'sale_items.sale_id')
                    ->on('sales.tenant_id', '=', 'sale_items.tenant_id');
            })
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

        $refundRows = DB::table('refunds')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endExclusiveUtc)
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('sum(total) as revenue')
            ->selectRaw('sum(tax_total) as tax')
            ->selectRaw('sum(total - refunded_cost_total) as profit_impact')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $refundItemRows = DB::table('refund_items')
            ->join('refunds', function ($join) {
                $join->on('refunds.id', '=', 'refund_items.refund_id')
                    ->on('refunds.tenant_id', '=', 'refund_items.tenant_id');
            })
            ->where('refunds.tenant_id', $tenantId)
            ->where('refund_items.tenant_id', $tenantId)
            ->where('refunds.status', 'completed')
            ->where('refunds.created_at', '>=', $startUtc)
            ->where('refunds.created_at', '<', $endExclusiveUtc)
            ->selectRaw('DATE(refunds.created_at) as date')
            ->selectRaw('sum(refund_items.qty) as items')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $saleItemAggregateCogs = app(SalesCostReadinessService::class)->saleItemAggregateTotalsSubquery($tenantId);

        $profitRows = DB::table('sale_items')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'sale_items.sale_id')
                    ->on('sales.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->joinSub($saleItemAggregateCogs, 'item_cost_totals', function ($join) {
                $join->on('item_cost_totals.sale_item_id', '=', 'sale_items.id')
                    ->on('item_cost_totals.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->where('sales.tenant_id', $tenantId)
            ->where('sale_items.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->where('sales.created_at', '>=', $startUtc)
            ->where('sales.created_at', '<', $endExclusiveUtc)
            ->selectRaw('DATE(sales.created_at) as date')
            ->selectRaw('sum((sale_items.qty * sale_items.price) - sale_items.discount - item_cost_totals.aggregated_cost) as profit')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $feeRows = DB::table('sale_items')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'sale_items.sale_id')
                    ->on('sales.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->join('products', function ($join) {
                $join->on('products.id', '=', 'sale_items.product_id')
                    ->on('products.tenant_id', '=', 'sale_items.tenant_id');
            })
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
        $refundMap = $refundRows->keyBy('date');
        $refundItemsMap = $refundItemRows->keyBy('date');
        $profitMap = $profitRows->keyBy('date');
        $feeMap = $feeRows->keyBy('date');

        $cursor = $startLocal->startOfDay();
        $daily = [];

        while ($cursor <= $endLocal) {
            $dateKey = $cursor->toDateString();
            $sales = $salesMap[$dateKey] ?? null;
            $items = $itemsMap[$dateKey] ?? null;
            $refund = $refundMap[$dateKey] ?? null;
            $refundedItems = $refundItemsMap[$dateKey] ?? null;
            $profit = $profitMap[$dateKey] ?? null;
            $fee = $feeMap[$dateKey] ?? null;

            $revenue = (float) ($sales?->revenue ?? 0) - (float) ($refund?->revenue ?? 0);
            $orders = (int) ($sales?->orders ?? 0);
            $discount = (float) ($sales?->discount ?? 0);
            $tax = (float) ($sales?->tax ?? 0) - (float) ($refund?->tax ?? 0);
            $itemCount = (float) ($items?->items ?? 0) - (float) ($refundedItems?->items ?? 0);
            $feeTotal = (float) ($fee?->fee ?? 0);
            $profitTotal = (float) ($profit?->profit ?? 0) - (float) ($refund?->profit_impact ?? 0);

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

    private function refundTotalBetween(string $tenantId, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return (float) DB::table('refunds')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->sum('total');
    }

    private function refundProfitImpactBetween(string $tenantId, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return (float) DB::table('refunds')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('total - refunded_cost_total'));
    }

    private function mergeTopProducts($salesRows, $refundRows)
    {
        $products = [];

        foreach ($salesRows as $row) {
            $products[$row->id] = [
                'id' => $row->id,
                'name' => $row->name,
                'image_url' => $row->image_url,
                'category_name' => $row->category_name,
                'qty' => (float) $row->qty,
                'revenue' => (float) $row->revenue,
            ];
        }

        foreach ($refundRows as $row) {
            if (! array_key_exists($row->id, $products)) {
                $products[$row->id] = [
                    'id' => $row->id,
                    'name' => $row->name,
                    'image_url' => $row->image_url,
                    'category_name' => $row->category_name,
                    'qty' => 0.0,
                    'revenue' => 0.0,
                ];
            }

            $products[$row->id]['qty'] -= (float) $row->qty;
            $products[$row->id]['revenue'] -= (float) $row->revenue;
        }

        return collect($products)
            ->filter(fn (array $row) => $row['qty'] > 0 || abs($row['revenue']) > 0.00001)
            ->sortByDesc('revenue')
            ->take(5)
            ->values();
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
