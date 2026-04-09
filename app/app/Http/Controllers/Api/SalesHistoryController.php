<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\SalesHistoryMetricsService;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class SalesHistoryController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }
        $range = $request->query('preset', $request->query('range', 'today'));
        $from = $request->query('start_date', $request->query('from'));
        $to = $request->query('end_date', $request->query('to'));

        [$fromAt, $toAt, $rangeMeta] = $this->resolveRange($range, $from, $to);

        $filters = [
            'branch_id' => $request->query('branch_id'),
            'cashier_id' => $request->query('cashier_id'),
            'status' => $request->query('status'),
        ];

        $query = Sale::query()
            ->leftJoin('branches', function ($join) {
                $join->on('sales.branch_id', '=', 'branches.id')
                    ->on('sales.tenant_id', '=', 'branches.tenant_id');
            })
            ->leftJoin('users', function ($join) {
                $join->on('sales.created_by', '=', 'users.id')
                    ->on('sales.tenant_id', '=', 'users.tenant_id');
            })
            ->select([
                'sales.id',
                'sales.branch_id',
                'sales.customer_id',
                'sales.discount',
                'sales.tax',
                'sales.total',
                'sales.status',
                'sales.created_at',
                'sales.transaction_date',
                'sales.created_by',
                'branches.name as branch_name',
                'users.name as cashier_name',
            ])
            ->where('sales.tenant_id', $tenantId)
            ->whereRaw('DATE(COALESCE(sales.transaction_date, sales.created_at)) >= ?', [$rangeMeta['start_date']])
            ->whereRaw('DATE(COALESCE(sales.transaction_date, sales.created_at)) <= ?', [$rangeMeta['end_date']])
            ->orderByDesc('sales.created_at');

        $this->applyFilters($query, $request);

        $summary = $this->buildSummary($tenantId, $rangeMeta['start_date'], $rangeMeta['end_date'], $filters);

        $limit = (int) $request->query('limit', 200);
        $limit = max(1, min($limit, 500));

        $sales = $query->limit($limit)->get();
        $saleIds = $sales->pluck('id')->all();

        $metricsService = app(SalesHistoryMetricsService::class);
        $saleMetrics = $metricsService->saleMetricsForSaleIds($tenantId, $saleIds);
        $refundMetrics = $metricsService->refundMetricsForSaleIds($tenantId, $saleIds, $rangeMeta['start_date'], $rangeMeta['end_date']);

        $paymentMap = [];
        if ($saleIds) {
            $payments = Payment::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('sale_id', $saleIds)
                ->get(['sale_id', 'method', 'amount']);

            foreach ($payments as $payment) {
                $list = $paymentMap[$payment->sale_id] ?? [];
                $list[] = [
                    'method' => $payment->method,
                    'amount' => (float) $payment->amount,
                ];
                $paymentMap[$payment->sale_id] = $list;
            }
        }

        $rows = $sales->map(function ($sale) use ($saleMetrics, $refundMetrics, $paymentMap) {
            $items = $saleMetrics->get($sale->id);
            $refunds = $refundMetrics->get($sale->id);
            $payments = $paymentMap[$sale->id] ?? [];

            $grossSales = (float) ($items?->gross_sales ?? 0);
            $itemDiscount = (float) ($items?->item_discount ?? 0);
            $transactionDiscount = (float) ($items?->transaction_discount ?? ($sale->discount ?? 0));
            $netSalesBeforeTax = (float) ($items?->net_sales_before_tax ?? 0);
            $refundRevenueReversalBeforeTax = (float) ($refunds?->refund_revenue_reversal_before_tax ?? 0);
            $refundTax = (float) ($refunds?->refund_tax ?? 0);
            $refundedAmount = (float) ($refunds?->refunded_amount ?? 0);
            $saleTax = (float) ($sale->tax ?? 0);
            $originalReceiptTotal = (float) ($sale->total ?? 0);

            return [
                'id' => $sale->id,
                'branch_id' => $sale->branch_id,
                'branch_name' => $sale->branch_name,
                'cashier_id' => $sale->created_by,
                'cashier_name' => $sale->cashier_name,
                'transaction_date' => $sale->transaction_date ?: CarbonImmutable::parse($sale->created_at)->toDateString(),
                'gross_sales' => $grossSales,
                'item_discount' => $itemDiscount,
                'transaction_discount' => $transactionDiscount,
                'total_discount' => $itemDiscount + $transactionDiscount,
                'net_sales_before_tax' => $netSalesBeforeTax,
                'refund_revenue_reversal_before_tax' => $refundRevenueReversalBeforeTax,
                'net_sales' => $netSalesBeforeTax - $refundRevenueReversalBeforeTax,
                'sales_tax' => $saleTax,
                'refund_tax' => $refundTax,
                'tax' => $saleTax - $refundTax,
                'refunded_amount' => $refundedAmount,
                'original_receipt_total' => $originalReceiptTotal,
                'net_total' => $originalReceiptTotal - $refundedAmount,
                'total' => $originalReceiptTotal,
                'status' => $sale->status,
                'created_at' => $sale->created_at,
                'item_count' => (int) ($items?->line_count ?? 0),
                'qty_total' => (float) ($items?->qty_total ?? 0),
                'payments' => $payments,
            ];
        });

        return ApiResponse::success([
            'sales' => $rows,
            'summary' => $summary,
        ], [
            'range' => $rangeMeta,
            'count' => $rows->count(),
            'limit' => $limit,
            'list_mode' => 'sale_centric',
            'summary_mode' => 'mixed_sales_and_refunds',
            'refund_business_date_basis' => 'refunds.transaction_date',
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('branch_id')) {
            $query->where('sales.branch_id', $request->query('branch_id'));
        }

        if ($request->filled('cashier_id')) {
            $query->where('sales.created_by', $request->query('cashier_id'));
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('sales.status', $request->query('status'));
        } elseif (! $request->filled('status')) {
            $query->where('sales.status', Sale::STATUS_COMPLETED);
        }
    }

    private function buildSummary(string $tenantId, string $startDate, string $endDate, array $filters): array
    {
        $metricsService = app(SalesHistoryMetricsService::class);
        $summary = $metricsService->summaryForRange($tenantId, $startDate, $endDate, $filters);
        $summary['payments'] = $metricsService->paymentMixForRange($tenantId, $startDate, $endDate, $filters);
        $summary['top_products'] = $metricsService->topProductsForRange($tenantId, $startDate, $endDate, $filters);
        $summary['average_ticket'] = $summary['transaction_count'] > 0
            ? round(($summary['original_receipt_total'] ?? 0) / $summary['transaction_count'], 2)
            : 0.0;

        return $summary;
    }

    private function resolveRange(string $range, ?string $from, ?string $to): array
    {
        $timezone = 'Asia/Jakarta';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        [$startLocal, $endLocal, $label] = match ($range) {
            'today' => [$today, $today, 'Hari Ini'],
            'yesterday' => [$today->subDay(), $today->subDay(), 'Kemarin'],
            'last30' => [$today->subDays(29), $today, '30 Hari Terakhir'],
            'thisMonth' => [$today->startOfMonth(), $today->endOfMonth()->startOfDay(), 'Bulan Ini'],
            'lastMonth' => [
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth()->startOfDay(),
                'Bulan Lalu',
            ],
            'custom' => $this->resolveCustom($from, $to, $today),
            '7days', 'last7' => [$today->subDays(6), $today, '7 Hari Terakhir'],
            default => [$today, $today, 'Hari Ini'],
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
                'preset' => $range,
                'start_date' => $startLocal->toDateString(),
                'end_date' => $endLocal->toDateString(),
                'label' => $label,
                'timezone' => 'Asia/Jakarta',
                'limited' => $limited,
                'limit_days' => 90,
            ],
        ];
    }

    private function resolveCustom(?string $from, ?string $to, CarbonImmutable $fallback): array
    {
        $timezone = 'Asia/Jakarta';
        $startLocal = CarbonImmutable::parse($from ?? $fallback->toDateString(), $timezone)->startOfDay();
        $endLocal = CarbonImmutable::parse($to ?? $from ?? $fallback->toDateString(), $timezone)->startOfDay();

        if ($endLocal < $startLocal) {
            [$startLocal, $endLocal] = [$endLocal, $startLocal];
        }

        return [$startLocal, $endLocal, 'Periode Kustom'];
    }
}
