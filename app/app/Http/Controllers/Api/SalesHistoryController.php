<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\SalesHistoryMetricsService;
use App\Support\ApiResponse;
use App\Support\SaleChannel;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->leftJoin('customers', function ($join) {
                $join->on('sales.customer_id', '=', 'customers.id')
                    ->on('sales.tenant_id', '=', 'customers.tenant_id');
            })
            ->leftJoin('receivables', function ($join) {
                $join->on('sales.id', '=', 'receivables.sale_id')
                    ->on('sales.tenant_id', '=', 'receivables.tenant_id');
            })
            ->select([
                'sales.id',
                'sales.branch_id',
                'sales.customer_id',
                'sales.discount',
                'sales.tax',
                'sales.total',
                'sales.status',
                'sales.idempotency_key',
                'sales.created_at',
                'sales.transaction_date',
                'sales.created_by',
                'branches.name as branch_name',
                'customers.name as customer_name',
                'users.name as cashier_name',
                'receivables.id as receivable_id',
                'receivables.total_amount as receivable_total_amount',
                'receivables.paid_amount as receivable_paid_amount',
                'receivables.remaining_amount as receivable_remaining_amount',
                'receivables.due_date as receivable_due_date',
                'receivables.status as receivable_status',
            ])
            ->where('sales.tenant_id', $tenantId)
            ->whereRaw('DATE(COALESCE(sales.transaction_date, sales.created_at)) >= ?', [$rangeMeta['start_date']])
            ->whereRaw('DATE(COALESCE(sales.transaction_date, sales.created_at)) <= ?', [$rangeMeta['end_date']])
            ->orderByDesc('sales.created_at');

        $this->applyFilters($query, $request);

        $summaryParam = strtolower((string) $request->query('summary', $request->query('include_summary', '1')));
        $includeSummary = ! in_array($summaryParam, ['0', 'false', 'no', 'off'], true);
        $summary = $includeSummary
            ? $this->buildSummary($tenantId, $rangeMeta['start_date'], $rangeMeta['end_date'], $filters)
            : null;

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

        $receivablePaymentMap = [];
        if ($saleIds) {
            $receivablePayments = DB::table('receivable_payments')
                ->where('tenant_id', $tenantId)
                ->whereIn('sale_id', $saleIds)
                ->orderByDesc('created_at')
                ->get(['sale_id', 'id', 'receivable_id', 'amount', 'payment_method', 'created_at']);

            foreach ($receivablePayments as $payment) {
                $list = $receivablePaymentMap[$payment->sale_id] ?? [];
                $list[] = [
                    'id' => (string) $payment->id,
                    'receivable_id' => (string) $payment->receivable_id,
                    'amount' => (float) $payment->amount,
                    'payment_method' => (string) $payment->payment_method,
                    'created_at' => (string) $payment->created_at,
                ];
                $receivablePaymentMap[$payment->sale_id] = $list;
            }
        }

        $today = CarbonImmutable::now('Asia/Jakarta')->toDateString();

        $rows = $sales->map(function ($sale) use ($saleMetrics, $refundMetrics, $paymentMap, $receivablePaymentMap, $today) {
            $items = $saleMetrics->get($sale->id);
            $refunds = $refundMetrics->get($sale->id);
            $payments = $paymentMap[$sale->id] ?? [];
            $receivablePayments = $receivablePaymentMap[$sale->id] ?? [];

            $grossSales = (float) ($items?->gross_sales ?? 0);
            $itemDiscount = (float) ($items?->item_discount ?? 0);
            $transactionDiscount = (float) ($items?->transaction_discount ?? ($sale->discount ?? 0));
            $netSalesBeforeTax = (float) ($items?->net_sales_before_tax ?? 0);
            $refundRevenueReversalBeforeTax = (float) ($refunds?->refund_revenue_reversal_before_tax ?? 0);
            $refundTax = (float) ($refunds?->refund_tax ?? 0);
            $refundedAmount = (float) ($refunds?->refunded_amount ?? 0);
            $saleTax = (float) ($sale->tax ?? 0);
            $originalReceiptTotal = (float) ($sale->total ?? 0);
            $channel = SaleChannel::fromIdempotencyKey($sale->idempotency_key);

            return [
                'id' => $sale->id,
                'branch_id' => $sale->branch_id,
                'branch_name' => $sale->branch_name,
                'cashier_id' => $sale->created_by,
                'cashier_name' => $sale->cashier_name,
                'customer_id' => $sale->customer_id,
                'customer_name' => $sale->customer_name,
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
                'sales_channel' => $channel['channel'],
                'sales_channel_label' => $channel['label'],
                'stock_source_label' => $channel['stock_label'],
                'created_at' => $sale->created_at,
                'item_count' => (int) ($items?->line_count ?? 0),
                'qty_total' => (float) ($items?->qty_total ?? 0),
                'payments' => $payments,
                'payment_status' => $sale->receivable_id ? (string) $sale->receivable_status : 'paid',
                'receivable' => $sale->receivable_id ? [
                    'id' => (string) $sale->receivable_id,
                    'total_amount' => (float) $sale->receivable_total_amount,
                    'paid_amount' => (float) $sale->receivable_paid_amount,
                    'remaining_amount' => (float) $sale->receivable_remaining_amount,
                    'due_date' => $sale->receivable_due_date,
                    'status' => (string) $sale->receivable_status,
                    'is_overdue' => $sale->receivable_status !== 'paid'
                        && $sale->receivable_due_date
                        && (string) $sale->receivable_due_date < $today,
                ] : null,
                'receivable_payments' => $receivablePayments,
            ];
        });

        return ApiResponse::success([
            'sales' => $rows,
            'summary' => $summary,
        ], [
            'range' => $rangeMeta,
            'count' => $rows->count(),
            'limit' => $limit,
            'summary_included' => $includeSummary,
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
        $summary['receivables'] = $this->receivableSummaryForRange($tenantId, $startDate, $endDate, $filters);
        $summary['average_ticket'] = $summary['transaction_count'] > 0
            ? round(($summary['original_receipt_total'] ?? 0) / $summary['transaction_count'], 2)
            : 0.0;

        return $summary;
    }

    private function receivableSummaryForRange(string $tenantId, string $startDate, string $endDate, array $filters): array
    {
        $today = CarbonImmutable::now('Asia/Jakarta')->toDateString();

        $query = DB::table('receivables')
            ->join('sales', function ($join): void {
                $join->on('sales.id', '=', 'receivables.sale_id')
                    ->on('sales.tenant_id', '=', 'receivables.tenant_id');
            })
            ->where('receivables.tenant_id', $tenantId)
            ->whereRaw('DATE(COALESCE(sales.transaction_date, sales.created_at)) >= ?', [$startDate])
            ->whereRaw('DATE(COALESCE(sales.transaction_date, sales.created_at)) <= ?', [$endDate]);

        if (! empty($filters['branch_id'])) {
            $query->where('sales.branch_id', $filters['branch_id']);
        }

        if (! empty($filters['cashier_id'])) {
            $query->where('sales.created_by', $filters['cashier_id']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('sales.status', $filters['status']);
        }

        $row = $query
            ->selectRaw("COALESCE(SUM(CASE WHEN receivables.status <> 'paid' THEN receivables.remaining_amount ELSE 0 END), 0) as active_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN receivables.status <> 'paid' AND receivables.due_date IS NOT NULL AND receivables.due_date < ? THEN receivables.remaining_amount ELSE 0 END), 0) as overdue_total", [$today])
            ->selectRaw("COALESCE(SUM(CASE WHEN receivables.status = 'paid' THEN receivables.total_amount ELSE 0 END), 0) as paid_total")
            ->selectRaw('COUNT(*) as receivable_count')
            ->first();

        return [
            'active_total' => (float) ($row->active_total ?? 0),
            'overdue_total' => (float) ($row->overdue_total ?? 0),
            'paid_total' => (float) ($row->paid_total ?? 0),
            'receivable_count' => (int) ($row->receivable_count ?? 0),
        ];
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
