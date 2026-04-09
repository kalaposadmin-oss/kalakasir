<?php

namespace App\Services;

use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesHistoryMetricsService
{
    public function saleMetricsForSaleIds(string $tenantId, array $saleIds): Collection
    {
        if ($saleIds === []) {
            return collect();
        }

        return DB::query()
            ->fromSub($this->saleLineMetricsForSaleIdsQuery($tenantId, $saleIds), 'sale_line_metrics')
            ->selectRaw('sale_id')
            ->selectRaw('sum(line_qty) as qty_total')
            ->selectRaw('count(*) as line_count')
            ->selectRaw('sum(line_gross_sales) as gross_sales')
            ->selectRaw('sum(line_item_discount) as item_discount')
            ->selectRaw('sum(line_allocated_transaction_discount) as transaction_discount')
            ->selectRaw('sum(line_net_sales_before_tax) as net_sales_before_tax')
            ->groupBy('sale_id')
            ->get()
            ->keyBy('sale_id');
    }

    public function refundMetricsForSaleIds(string $tenantId, array $saleIds, string $startDate, string $endDate): Collection
    {
        if ($saleIds === []) {
            return collect();
        }

        $lineRows = DB::query()
            ->fromSub($this->refundLineMetricsForSaleIdsQuery($tenantId, $saleIds, $startDate, $endDate), 'refund_line_metrics')
            ->selectRaw('sale_id')
            ->selectRaw('sum(line_qty) as qty_total')
            ->selectRaw('sum(line_revenue_reversal_before_tax) as refund_revenue_reversal_before_tax')
            ->groupBy('sale_id')
            ->get()
            ->keyBy('sale_id');

        $headerRows = DB::table('refunds')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereIn('sale_id', $saleIds)
            ->whereRaw($this->refundBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->refundBusinessDateExpr().' <= ?', [$endDate])
            ->selectRaw('sale_id')
            ->selectRaw('sum(total) as refunded_amount')
            ->selectRaw('sum(tax_total) as refund_tax')
            ->groupBy('sale_id')
            ->get()
            ->keyBy('sale_id');

        return collect($saleIds)
            ->mapWithKeys(function ($saleId) use ($lineRows, $headerRows) {
                $line = $lineRows->get($saleId);
                $header = $headerRows->get($saleId);

                return [
                    $saleId => (object) [
                        'sale_id' => $saleId,
                        'refund_qty_total' => Decimal::float($line?->qty_total ?? 0, Decimal::QTY_SCALE),
                        'refund_revenue_reversal_before_tax' => Decimal::float($line?->refund_revenue_reversal_before_tax ?? 0, Decimal::MONEY_SCALE),
                        'refunded_amount' => Decimal::float($header?->refunded_amount ?? 0, Decimal::MONEY_SCALE),
                        'refund_tax' => Decimal::float($header?->refund_tax ?? 0, Decimal::MONEY_SCALE),
                    ],
                ];
            });
    }

    public function summaryForRange(string $tenantId, string $startDate, string $endDate, array $filters = []): array
    {
        $salesSummary = DB::query()
            ->fromSub($this->saleLineMetricsQuery($tenantId, $startDate, $endDate, $filters), 'sale_line_metrics')
            ->selectRaw('sum(line_qty) as qty')
            ->selectRaw('sum(line_gross_sales) as gross_sales')
            ->selectRaw('sum(line_item_discount) as item_discount')
            ->selectRaw('sum(line_allocated_transaction_discount) as transaction_discount')
            ->selectRaw('sum(line_net_sales_before_tax) as net_sales_before_tax')
            ->first();

        $salesHeaderSummary = $this->salesHeaderQuery($tenantId, $startDate, $endDate, $filters)
            ->selectRaw('count(*) as transaction_count')
            ->selectRaw('sum(tax) as tax')
            ->selectRaw('sum(total) as grand_total')
            ->first();

        $refundLineSummary = DB::query()
            ->fromSub($this->refundLineMetricsQuery($tenantId, $startDate, $endDate, $filters), 'refund_line_metrics')
            ->selectRaw('sum(line_qty) as qty')
            ->selectRaw('sum(line_revenue_reversal_before_tax) as refund_revenue_reversal_before_tax')
            ->first();

        $refundHeaderSummary = $this->refundHeaderQuery($tenantId, $startDate, $endDate, $filters)
            ->selectRaw('sum(refunds.total) as refunded_amount')
            ->selectRaw('sum(refunds.tax_total) as refund_tax')
            ->first();

        $grossSales = Decimal::money($salesSummary?->gross_sales ?? 0);
        $itemDiscount = Decimal::money($salesSummary?->item_discount ?? 0);
        $transactionDiscount = Decimal::money($salesSummary?->transaction_discount ?? 0);
        $netSalesBeforeTax = Decimal::money($salesSummary?->net_sales_before_tax ?? 0);
        $tax = Decimal::money($salesHeaderSummary?->tax ?? 0);
        $refundRevenueReversalBeforeTax = Decimal::money($refundLineSummary?->refund_revenue_reversal_before_tax ?? 0);
        $refundedAmount = Decimal::money($refundHeaderSummary?->refunded_amount ?? 0);
        $refundTax = Decimal::money($refundHeaderSummary?->refund_tax ?? 0);
        $grandTotal = Decimal::money($salesHeaderSummary?->grand_total ?? 0);

        return [
            'gross_sales' => Decimal::float($grossSales, Decimal::MONEY_SCALE),
            'item_discount' => Decimal::float($itemDiscount, Decimal::MONEY_SCALE),
            'transaction_discount' => Decimal::float($transactionDiscount, Decimal::MONEY_SCALE),
            'total_discount' => Decimal::float(Decimal::add($itemDiscount, $transactionDiscount, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
            'net_sales_before_tax' => Decimal::float($netSalesBeforeTax, Decimal::MONEY_SCALE),
            'refund_revenue_reversal_before_tax' => Decimal::float($refundRevenueReversalBeforeTax, Decimal::MONEY_SCALE),
            'net_effect_before_tax' => Decimal::float(Decimal::subtract($netSalesBeforeTax, $refundRevenueReversalBeforeTax, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
            'net_sales' => Decimal::float(Decimal::subtract($netSalesBeforeTax, $refundRevenueReversalBeforeTax, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
            'tax' => Decimal::float(Decimal::subtract($tax, $refundTax, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
            'sales_tax' => Decimal::float($tax, Decimal::MONEY_SCALE),
            'refund_tax' => Decimal::float($refundTax, Decimal::MONEY_SCALE),
            'grand_total' => Decimal::float($grandTotal, Decimal::MONEY_SCALE),
            'original_receipt_total' => Decimal::float($grandTotal, Decimal::MONEY_SCALE),
            'refunded_amount' => Decimal::float($refundedAmount, Decimal::MONEY_SCALE),
            'net_total' => Decimal::float(Decimal::subtract($grandTotal, $refundedAmount, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
            'transaction_count' => (int) ($salesHeaderSummary?->transaction_count ?? 0),
            'items' => Decimal::float(Decimal::subtract($salesSummary?->qty ?? 0, $refundLineSummary?->qty ?? 0, Decimal::QTY_SCALE), Decimal::QTY_SCALE),
        ];
    }

    public function paymentMixForRange(string $tenantId, string $startDate, string $endDate, array $filters = []): array
    {
        $paymentRows = DB::table('payments')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'payments.sale_id')
                    ->on('sales.tenant_id', '=', 'payments.tenant_id');
            })
            ->where('sales.tenant_id', $tenantId)
            ->where('payments.tenant_id', $tenantId)
            ->whereRaw($this->saleBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->saleBusinessDateExpr().' <= ?', [$endDate]);

        $this->applyFilters($paymentRows, $filters, 'sales');

        $paymentRows = $paymentRows
            ->select('payments.method')
            ->selectRaw('sum(payments.amount) as amount')
            ->groupBy('payments.method')
            ->get();

        $refundPaymentRows = DB::table('refund_payments')
            ->join('refunds', function ($join) {
                $join->on('refunds.id', '=', 'refund_payments.refund_id')
                    ->on('refunds.tenant_id', '=', 'refund_payments.tenant_id');
            })
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'refunds.sale_id')
                    ->on('sales.tenant_id', '=', 'refunds.tenant_id');
            })
            ->where('refunds.tenant_id', $tenantId)
            ->where('refund_payments.tenant_id', $tenantId)
            ->whereRaw($this->refundBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->refundBusinessDateExpr().' <= ?', [$endDate]);

        $this->applyFilters($refundPaymentRows, $filters, 'sales');

        $refundPaymentRows = $refundPaymentRows
            ->select('refund_payments.method')
            ->selectRaw('sum(refund_payments.amount) as amount')
            ->groupBy('refund_payments.method')
            ->get();

        $paymentMap = [];
        foreach ($paymentRows as $row) {
            $paymentMap[$row->method] = Decimal::add($paymentMap[$row->method] ?? 0, $row->amount, Decimal::MONEY_SCALE);
        }
        foreach ($refundPaymentRows as $row) {
            $paymentMap[$row->method] = Decimal::subtract($paymentMap[$row->method] ?? 0, $row->amount, Decimal::MONEY_SCALE);
        }

        $paymentTotal = collect($paymentMap)
            ->filter(fn ($amount) => Decimal::compare($amount, 0) > 0)
            ->reduce(fn (string $carry, $amount) => Decimal::add($carry, $amount, Decimal::MONEY_SCALE), Decimal::money(0));

        return collect($paymentMap)
            ->map(function ($amount, $method) use ($paymentTotal) {
                $amount = Decimal::money($amount);

                return [
                    'method' => $method,
                    'amount' => Decimal::float($amount, Decimal::MONEY_SCALE),
                    'percent' => Decimal::compare($paymentTotal, 0) > 0 && Decimal::compare($amount, 0) > 0
                        ? Decimal::float(Decimal::multiply(Decimal::divide($amount, $paymentTotal, Decimal::RATE_SCALE), '100', 1), 1)
                        : 0.0,
                ];
            })
            ->filter(fn (array $row) => abs($row['amount']) > 0.00001)
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    public function topProductsForRange(string $tenantId, string $startDate, string $endDate, array $filters = []): array
    {
        $salesRows = DB::query()
            ->fromSub($this->saleLineMetricsQuery($tenantId, $startDate, $endDate, $filters), 'sale_line_metrics')
            ->where('sku', 'not like', 'FEE-%')
            ->select([
                'product_id as id',
                'product_name as name',
                'image_url',
            ])
            ->selectRaw('sum(line_qty) as qty_sold')
            ->selectRaw('sum(line_net_sales_before_tax) as net_sales')
            ->groupBy('product_id', 'product_name', 'image_url')
            ->get();

        $refundRows = DB::query()
            ->fromSub($this->refundLineMetricsQuery($tenantId, $startDate, $endDate, $filters), 'refund_line_metrics')
            ->where('sku', 'not like', 'FEE-%')
            ->select([
                'product_id as id',
                'product_name as name',
                'image_url',
            ])
            ->selectRaw('sum(line_qty) as qty_refunded')
            ->selectRaw('sum(line_revenue_reversal_before_tax) as refund_revenue')
            ->groupBy('product_id', 'product_name', 'image_url')
            ->get();

        $products = [];

        foreach ($salesRows as $row) {
            $products[$row->id] = [
                'id' => $row->id,
                'name' => $row->name,
                'image_url' => $row->image_url,
                'qty_sold' => Decimal::float($row->qty_sold, Decimal::QTY_SCALE),
                'net_sales' => Decimal::float($row->net_sales, Decimal::MONEY_SCALE),
            ];
        }

        foreach ($refundRows as $row) {
            if (! isset($products[$row->id])) {
                $products[$row->id] = [
                    'id' => $row->id,
                    'name' => $row->name,
                    'image_url' => $row->image_url,
                    'qty_sold' => 0.0,
                    'net_sales' => 0.0,
                ];
            }

            $products[$row->id]['qty_sold'] = Decimal::float(Decimal::subtract($products[$row->id]['qty_sold'], $row->qty_refunded, Decimal::QTY_SCALE), Decimal::QTY_SCALE);
            $products[$row->id]['net_sales'] = Decimal::float(Decimal::subtract($products[$row->id]['net_sales'], $row->refund_revenue, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE);
        }

        return collect($products)
            ->filter(fn (array $row) => $row['qty_sold'] > 0 || abs($row['net_sales']) > 0.00001)
            ->sortByDesc('net_sales')
            ->take(5)
            ->values()
            ->all();
    }

    public function saleDetailMetrics(string $tenantId, string $saleId): array
    {
        $saleLineRows = DB::query()
            ->fromSub($this->saleLineMetricsForSaleIdsQuery($tenantId, [$saleId]), 'sale_line_metrics')
            ->get()
            ->keyBy('sale_item_id');

        $saleSummary = DB::query()
            ->fromSub($this->saleLineMetricsForSaleIdsQuery($tenantId, [$saleId]), 'sale_line_metrics')
            ->selectRaw('sum(line_gross_sales) as gross_sales')
            ->selectRaw('sum(line_item_discount) as item_discount')
            ->selectRaw('sum(line_allocated_transaction_discount) as transaction_discount')
            ->selectRaw('sum(line_net_sales_before_tax) as net_sales_before_tax')
            ->first();

        $refundLineSummary = DB::table('refund_items')
            ->join('refunds', function ($join) {
                $join->on('refunds.id', '=', 'refund_items.refund_id')
                    ->on('refunds.tenant_id', '=', 'refund_items.tenant_id');
            })
            ->where('refunds.tenant_id', $tenantId)
            ->where('refunds.sale_id', $saleId)
            ->where('refunds.status', 'completed')
            ->selectRaw('sum(refund_items.gross_amount - refund_items.item_discount_amount - refund_items.transaction_discount_amount) as refund_revenue_reversal_before_tax')
            ->first();

        $refundHeaderSummary = DB::table('refunds')
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->where('status', 'completed')
            ->selectRaw('sum(total) as refunded_amount')
            ->selectRaw('sum(tax_total) as refund_tax')
            ->first();

        return [
            'line_metrics' => $saleLineRows,
            'summary' => [
                'gross_sales' => Decimal::float($saleSummary?->gross_sales ?? 0, Decimal::MONEY_SCALE),
                'item_discount' => Decimal::float($saleSummary?->item_discount ?? 0, Decimal::MONEY_SCALE),
                'transaction_discount' => Decimal::float($saleSummary?->transaction_discount ?? 0, Decimal::MONEY_SCALE),
                'total_discount' => Decimal::float(Decimal::add($saleSummary?->item_discount ?? 0, $saleSummary?->transaction_discount ?? 0, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
                'net_sales_before_tax' => Decimal::float($saleSummary?->net_sales_before_tax ?? 0, Decimal::MONEY_SCALE),
                'refund_revenue_reversal_before_tax' => Decimal::float($refundLineSummary?->refund_revenue_reversal_before_tax ?? 0, Decimal::MONEY_SCALE),
                'refunded_amount' => Decimal::float($refundHeaderSummary?->refunded_amount ?? 0, Decimal::MONEY_SCALE),
                'refund_tax' => Decimal::float($refundHeaderSummary?->refund_tax ?? 0, Decimal::MONEY_SCALE),
            ],
        ];
    }

    private function saleLineMetricsQuery(string $tenantId, string $startDate, string $endDate, array $filters = [])
    {
        $allocationBasisExpr = $this->saleAllocationBasisExpr();

        $allocationBaseSub = DB::table('sale_items')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'sale_items.sale_id')
                    ->on('sales.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->leftJoin('products', function ($join) {
                $join->on('products.id', '=', 'sale_items.product_id')
                    ->on('products.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->where('sales.tenant_id', $tenantId)
            ->whereRaw($this->saleBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->saleBusinessDateExpr().' <= ?', [$endDate]);

        $this->applyFilters($allocationBaseSub, $filters, 'sales');

        $allocationBaseSub = $allocationBaseSub
            ->select('sale_items.sale_id')
            ->selectRaw('sum('.$allocationBasisExpr.') as allocation_basis_total')
            ->groupBy('sale_items.sale_id');

        return $this->saleLineMetricsCoreQuery($tenantId, $allocationBaseSub, function ($query) use ($startDate, $endDate, $filters) {
            $query->whereRaw($this->saleBusinessDateExpr().' >= ?', [$startDate])
                ->whereRaw($this->saleBusinessDateExpr().' <= ?', [$endDate]);

            $this->applyFilters($query, $filters, 'sales');
        });
    }

    private function saleLineMetricsForSaleIdsQuery(string $tenantId, array $saleIds)
    {
        $allocationBasisExpr = $this->saleAllocationBasisExpr();

        $allocationBaseSub = DB::table('sale_items')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'sale_items.sale_id')
                    ->on('sales.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->leftJoin('products', function ($join) {
                $join->on('products.id', '=', 'sale_items.product_id')
                    ->on('products.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->where('sales.tenant_id', $tenantId)
            ->whereIn('sale_items.sale_id', $saleIds)
            ->select('sale_items.sale_id')
            ->selectRaw('sum('.$allocationBasisExpr.') as allocation_basis_total')
            ->groupBy('sale_items.sale_id');

        return $this->saleLineMetricsCoreQuery($tenantId, $allocationBaseSub, function ($query) use ($saleIds) {
            $query->whereIn('sale_items.sale_id', $saleIds);
        });
    }

    private function saleLineMetricsCoreQuery(string $tenantId, $allocationBaseSub, callable $scopeCallback)
    {
        $allocationBasisExpr = $this->saleAllocationBasisExpr();
        $allocatedDiscountExpr = 'case when allocation_basis_totals.allocation_basis_total > 0 then (sales.discount * ('
            .$allocationBasisExpr.') / allocation_basis_totals.allocation_basis_total) else 0 end';
        $grossExpr = '(sale_items.qty * sale_items.price)';
        $netSalesBeforeTaxExpr = $grossExpr.' - sale_items.discount - '.$allocatedDiscountExpr;

        $query = DB::table('sale_items')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'sale_items.sale_id')
                    ->on('sales.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->leftJoin('products', function ($join) {
                $join->on('products.id', '=', 'sale_items.product_id')
                    ->on('products.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->leftJoinSub($allocationBaseSub, 'allocation_basis_totals', function ($join) {
                $join->on('allocation_basis_totals.sale_id', '=', 'sale_items.sale_id');
            })
            ->where('sales.tenant_id', $tenantId)
            ->where('sale_items.tenant_id', $tenantId);

        $scopeCallback($query);

        return $query
            ->selectRaw('sale_items.sale_id as sale_id')
            ->selectRaw('sale_items.id as sale_item_id')
            ->selectRaw('sale_items.product_id as product_id')
            ->selectRaw('products.name as product_name')
            ->selectRaw('products.image_url as image_url')
            ->selectRaw('products.sku as sku')
            ->selectRaw('sale_items.qty as line_qty')
            ->selectRaw($grossExpr.' as line_gross_sales')
            ->selectRaw('sale_items.discount as line_item_discount')
            ->selectRaw($allocatedDiscountExpr.' as line_allocated_transaction_discount')
            ->selectRaw($netSalesBeforeTaxExpr.' as line_net_sales_before_tax');
    }

    private function refundLineMetricsQuery(string $tenantId, string $startDate, string $endDate, array $filters = [])
    {
        $query = DB::table('refund_items')
            ->join('refunds', function ($join) {
                $join->on('refunds.id', '=', 'refund_items.refund_id')
                    ->on('refunds.tenant_id', '=', 'refund_items.tenant_id');
            })
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'refunds.sale_id')
                    ->on('sales.tenant_id', '=', 'refunds.tenant_id');
            })
            ->leftJoin('products', function ($join) {
                $join->on('products.id', '=', 'refund_items.product_id')
                    ->on('products.tenant_id', '=', 'refund_items.tenant_id');
            })
            ->where('refunds.tenant_id', $tenantId)
            ->where('refund_items.tenant_id', $tenantId)
            ->where('refunds.status', 'completed')
            ->whereRaw($this->refundBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->refundBusinessDateExpr().' <= ?', [$endDate]);

        $this->applyFilters($query, $filters, 'sales');

        return $query
            ->selectRaw('refunds.sale_id as sale_id')
            ->selectRaw('refund_items.product_id as product_id')
            ->selectRaw('products.name as product_name')
            ->selectRaw('products.image_url as image_url')
            ->selectRaw('products.sku as sku')
            ->selectRaw('refund_items.qty as line_qty')
            ->selectRaw('(refund_items.gross_amount - refund_items.item_discount_amount - refund_items.transaction_discount_amount) as line_revenue_reversal_before_tax');
    }

    private function refundLineMetricsForSaleIdsQuery(string $tenantId, array $saleIds, string $startDate, string $endDate)
    {
        return DB::table('refund_items')
            ->join('refunds', function ($join) {
                $join->on('refunds.id', '=', 'refund_items.refund_id')
                    ->on('refunds.tenant_id', '=', 'refund_items.tenant_id');
            })
            ->leftJoin('products', function ($join) {
                $join->on('products.id', '=', 'refund_items.product_id')
                    ->on('products.tenant_id', '=', 'refund_items.tenant_id');
            })
            ->where('refunds.tenant_id', $tenantId)
            ->where('refund_items.tenant_id', $tenantId)
            ->where('refunds.status', 'completed')
            ->whereIn('refunds.sale_id', $saleIds)
            ->whereRaw($this->refundBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->refundBusinessDateExpr().' <= ?', [$endDate])
            ->selectRaw('refunds.sale_id as sale_id')
            ->selectRaw('refund_items.qty as line_qty')
            ->selectRaw('(refund_items.gross_amount - refund_items.item_discount_amount - refund_items.transaction_discount_amount) as line_revenue_reversal_before_tax');
    }

    private function salesHeaderQuery(string $tenantId, string $startDate, string $endDate, array $filters = [])
    {
        $query = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereRaw($this->saleBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->saleBusinessDateExpr().' <= ?', [$endDate]);

        $this->applyFilters($query, $filters, 'sales');

        return $query;
    }

    private function refundHeaderQuery(string $tenantId, string $startDate, string $endDate, array $filters = [])
    {
        $query = DB::table('refunds')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'refunds.sale_id')
                    ->on('sales.tenant_id', '=', 'refunds.tenant_id');
            })
            ->where('refunds.tenant_id', $tenantId)
            ->where('refunds.status', 'completed')
            ->whereRaw($this->refundBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->refundBusinessDateExpr().' <= ?', [$endDate]);

        $this->applyFilters($query, $filters, 'sales');

        return $query;
    }

    private function applyFilters($query, array $filters, string $salesAlias): void
    {
        if (($filters['branch_id'] ?? null) !== null) {
            $query->where($salesAlias.'.branch_id', $filters['branch_id']);
        }

        if (($filters['cashier_id'] ?? null) !== null) {
            $query->where($salesAlias.'.created_by', $filters['cashier_id']);
        }

        if (($filters['status'] ?? null) !== null && $filters['status'] !== 'all') {
            $query->where($salesAlias.'.status', $filters['status']);
        } elseif (! array_key_exists('status', $filters) || $filters['status'] === null) {
            $query->where($salesAlias.'.status', 'completed');
        }
    }

    private function saleAllocationBasisExpr(): string
    {
        return "case when products.sku like 'FEE-%' then 0 ".
            "when ((sale_items.qty * sale_items.price) - sale_items.discount) > 0 ".
            "then ((sale_items.qty * sale_items.price) - sale_items.discount) ".
            'else 0 end';
    }

    private function saleBusinessDateExpr(): string
    {
        return 'DATE(COALESCE(sales.transaction_date, sales.created_at))';
    }

    private function refundBusinessDateExpr(): string
    {
        return 'DATE(COALESCE(refunds.transaction_date, refunds.created_at))';
    }
}
