<?php

namespace App\Services;

use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsMetricsService
{
    public function summaryForBusinessDate(string $tenantId, string $date): array
    {
        return $this->summaryForRange($tenantId, $date, $date);
    }

    public function summaryForRange(string $tenantId, string $startDate, string $endDate): array
    {
        $salesSummary = DB::query()
            ->fromSub($this->saleLineMetricsQuery($tenantId, $startDate, $endDate), 'sale_line_metrics')
            ->selectRaw('sum(line_qty) as qty')
            ->selectRaw('sum(line_gross_sales) as gross_sales')
            ->selectRaw('sum(line_item_discount) as item_discount')
            ->selectRaw('sum(line_allocated_transaction_discount) as transaction_discount')
            ->selectRaw('sum(line_net_sales_before_tax) as net_sales_before_tax')
            ->selectRaw('sum(line_cogs) as cogs')
            ->selectRaw('sum(line_gross_profit) as gross_profit')
            ->first();

        $refundSummary = DB::query()
            ->fromSub($this->refundLineMetricsQuery($tenantId, $startDate, $endDate), 'refund_line_metrics')
            ->selectRaw('sum(line_qty) as qty')
            ->selectRaw('sum(line_revenue_reversal_before_tax) as revenue_reversal_before_tax')
            ->selectRaw('sum(line_refunded_cogs) as refunded_cogs')
            ->selectRaw('sum(line_gross_profit_reversal) as gross_profit_reversal')
            ->first();

        $orderSummary = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereRaw($this->saleBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->saleBusinessDateExpr().' <= ?', [$endDate])
            ->selectRaw('count(*) as orders')
            ->selectRaw('sum(total) as total')
            ->selectRaw('sum(tax) as tax')
            ->first();

        $refundTaxSummary = DB::table('refunds')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereRaw($this->refundBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->refundBusinessDateExpr().' <= ?', [$endDate])
            ->selectRaw('sum(total) as total')
            ->selectRaw('sum(tax_total) as tax')
            ->first();

        $grossSales = Decimal::money($salesSummary?->gross_sales ?? 0);
        $itemDiscount = Decimal::money($salesSummary?->item_discount ?? 0);
        $transactionDiscount = Decimal::money($salesSummary?->transaction_discount ?? 0);
        $netSalesBeforeTax = Decimal::money($salesSummary?->net_sales_before_tax ?? 0);
        $refundRevenueReversalBeforeTax = Decimal::money($refundSummary?->revenue_reversal_before_tax ?? 0);
        $cogs = Decimal::money($salesSummary?->cogs ?? 0);
        $refundedCogs = Decimal::money($refundSummary?->refunded_cogs ?? 0);
        $saleTotal = Decimal::money($orderSummary?->total ?? 0);
        $refundTotal = Decimal::money($refundTaxSummary?->total ?? 0);
        $netTax = Decimal::subtract($orderSummary?->tax ?? 0, $refundTaxSummary?->tax ?? 0, Decimal::MONEY_SCALE);
        $revenue = Decimal::subtract(
            Decimal::subtract($saleTotal, $refundTotal, Decimal::MONEY_SCALE),
            $netTax,
            Decimal::MONEY_SCALE
        );
        $effectiveDiscount = Decimal::max(
            Decimal::subtract(
                $grossSales,
                Decimal::add($revenue, $refundRevenueReversalBeforeTax, Decimal::MONEY_SCALE),
                Decimal::MONEY_SCALE
            ),
            0,
            Decimal::MONEY_SCALE
        );

        return [
            'gross_sales' => Decimal::float($grossSales, Decimal::MONEY_SCALE),
            'item_discount' => Decimal::float($itemDiscount, Decimal::MONEY_SCALE),
            'transaction_discount' => Decimal::float($transactionDiscount, Decimal::MONEY_SCALE),
            'discount' => Decimal::float($effectiveDiscount, Decimal::MONEY_SCALE),
            'net_sales_before_tax' => Decimal::float($netSalesBeforeTax, Decimal::MONEY_SCALE),
            'refund_revenue_reversal_before_tax' => Decimal::float($refundRevenueReversalBeforeTax, Decimal::MONEY_SCALE),
            'revenue' => Decimal::float($revenue, Decimal::MONEY_SCALE),
            'cogs' => Decimal::float($cogs, Decimal::MONEY_SCALE),
            'refunded_cogs' => Decimal::float($refundedCogs, Decimal::MONEY_SCALE),
            'profit' => Decimal::float(Decimal::subtract($salesSummary?->gross_profit ?? 0, $refundSummary?->gross_profit_reversal ?? 0, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
            'tax' => Decimal::float(Decimal::subtract($orderSummary?->tax ?? 0, $refundTaxSummary?->tax ?? 0, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
            'orders' => (int) ($orderSummary?->orders ?? 0),
            'items' => Decimal::float(Decimal::subtract($salesSummary?->qty ?? 0, $refundSummary?->qty ?? 0, Decimal::QTY_SCALE), Decimal::QTY_SCALE),
            'fee' => $this->feeRevenueForRange($tenantId, $startDate, $endDate),
        ];
    }

    public function dailySummary(string $tenantId, string $startDate, string $endDate): array
    {
        $salesRows = DB::query()
            ->fromSub($this->saleLineMetricsQuery($tenantId, $startDate, $endDate), 'sale_line_metrics')
            ->selectRaw('business_date as date')
            ->selectRaw('sum(line_qty) as items')
            ->selectRaw('sum(line_gross_sales) as gross_sales')
            ->selectRaw('sum(line_item_discount) as item_discount')
            ->selectRaw('sum(line_allocated_transaction_discount) as transaction_discount')
            ->selectRaw('sum(line_net_sales_before_tax) as net_sales_before_tax')
            ->selectRaw('sum(line_cogs) as cogs')
            ->selectRaw('sum(line_gross_profit) as profit')
            ->groupBy('business_date')
            ->orderBy('business_date')
            ->get()
            ->keyBy('date');

        $salesHeaderRows = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereRaw($this->saleBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->saleBusinessDateExpr().' <= ?', [$endDate])
            ->selectRaw($this->saleBusinessDateExpr().' as date')
            ->selectRaw('count(*) as orders')
            ->selectRaw('sum(total) as total')
            ->selectRaw('sum(tax) as tax')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $refundRows = DB::query()
            ->fromSub($this->refundLineMetricsQuery($tenantId, $startDate, $endDate), 'refund_line_metrics')
            ->selectRaw('business_date as date')
            ->selectRaw('sum(line_qty) as items')
            ->selectRaw('sum(line_revenue_reversal_before_tax) as revenue_reversal_before_tax')
            ->selectRaw('sum(line_refunded_cogs) as refunded_cogs')
            ->selectRaw('sum(line_gross_profit_reversal) as gross_profit_reversal')
            ->groupBy('business_date')
            ->orderBy('business_date')
            ->get()
            ->keyBy('date');

        $refundTaxRows = DB::table('refunds')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereRaw($this->refundBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->refundBusinessDateExpr().' <= ?', [$endDate])
            ->selectRaw($this->refundBusinessDateExpr().' as date')
            ->selectRaw('sum(total) as total')
            ->selectRaw('sum(tax_total) as tax')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $feeRows = DB::query()
            ->fromSub($this->saleLineMetricsQuery($tenantId, $startDate, $endDate), 'sale_line_metrics')
            ->where('sku', 'like', 'FEE-%')
            ->selectRaw('business_date as date')
            ->selectRaw('sum(line_net_sales_before_tax) as fee')
            ->groupBy('business_date')
            ->orderBy('business_date')
            ->get()
            ->keyBy('date');

        $cursor = $startDate;
        $daily = [];

        while ($cursor <= $endDate) {
            $sales = $salesRows[$cursor] ?? null;
            $salesHeader = $salesHeaderRows[$cursor] ?? null;
            $refund = $refundRows[$cursor] ?? null;
            $refundTax = $refundTaxRows[$cursor] ?? null;
            $fee = $feeRows[$cursor] ?? null;

            $netTax = Decimal::subtract($salesHeader?->tax ?? 0, $refundTax?->tax ?? 0, Decimal::MONEY_SCALE);
            $revenue = Decimal::subtract(
                Decimal::subtract($salesHeader?->total ?? 0, $refundTax?->total ?? 0, Decimal::MONEY_SCALE),
                $netTax,
                Decimal::MONEY_SCALE
            );
            $discount = Decimal::max(
                Decimal::subtract(
                    $sales?->gross_sales ?? 0,
                    Decimal::add($revenue, $refund?->revenue_reversal_before_tax ?? 0, Decimal::MONEY_SCALE),
                    Decimal::MONEY_SCALE
                ),
                0,
                Decimal::MONEY_SCALE
            );
            $orders = (int) ($salesHeader?->orders ?? 0);

            $daily[] = [
                'date' => $cursor,
                'revenue' => Decimal::float($revenue, Decimal::MONEY_SCALE),
                'orders' => $orders,
                'items' => Decimal::float(Decimal::subtract($sales?->items ?? 0, $refund?->items ?? 0, Decimal::QTY_SCALE), Decimal::QTY_SCALE),
                'discount' => Decimal::float($discount, Decimal::MONEY_SCALE),
                'fee' => Decimal::float($fee?->fee ?? 0, Decimal::MONEY_SCALE),
                'tax' => Decimal::float(Decimal::subtract($salesHeader?->tax ?? 0, $refundTax?->tax ?? 0, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
                'profit' => Decimal::float(Decimal::subtract($sales?->profit ?? 0, $refund?->gross_profit_reversal ?? 0, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
                'aov' => $orders > 0 ? Decimal::float(Decimal::divide($revenue, (string) $orders, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE) : 0,
            ];

            $cursor = date('Y-m-d', strtotime($cursor.' +1 day'));
        }

        return $daily;
    }

    public function topProducts(string $tenantId, string $startDate, string $endDate): Collection
    {
        $salesRows = DB::query()
            ->fromSub($this->saleLineMetricsQuery($tenantId, $startDate, $endDate), 'sale_line_metrics')
            ->where('sku', 'not like', 'FEE-%')
            ->select([
                'product_id as id',
                'product_name as name',
                'image_url',
                'category_name',
            ])
            ->selectRaw('sum(line_qty) as qty')
            ->selectRaw('sum(line_net_sales_before_tax) as revenue')
            ->groupBy('product_id', 'product_name', 'image_url', 'category_name')
            ->get();

        $refundRows = DB::query()
            ->fromSub($this->refundLineMetricsQuery($tenantId, $startDate, $endDate), 'refund_line_metrics')
            ->where('sku', 'not like', 'FEE-%')
            ->select([
                'product_id as id',
                'product_name as name',
                'image_url',
                'category_name',
            ])
            ->selectRaw('sum(line_qty) as qty')
            ->selectRaw('sum(line_revenue_reversal_before_tax) as revenue')
            ->groupBy('product_id', 'product_name', 'image_url', 'category_name')
            ->get();

        $products = [];

        foreach ($salesRows as $row) {
            $products[$row->id] = [
                'id' => $row->id,
                'name' => $row->name,
                'image_url' => $row->image_url,
                'category_name' => $row->category_name,
                'qty' => Decimal::float($row->qty, Decimal::QTY_SCALE),
                'revenue' => Decimal::float($row->revenue, Decimal::MONEY_SCALE),
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

            $products[$row->id]['qty'] = Decimal::float(Decimal::subtract($products[$row->id]['qty'], $row->qty, Decimal::QTY_SCALE), Decimal::QTY_SCALE);
            $products[$row->id]['revenue'] = Decimal::float(Decimal::subtract($products[$row->id]['revenue'], $row->revenue, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE);
        }

        return collect($products)
            ->filter(fn (array $row) => $row['qty'] > 0 || abs($row['revenue']) > 0.00001)
            ->sortByDesc('revenue')
            ->take(5)
            ->values();
    }

    public function paymentMix(string $tenantId, string $startDate, string $endDate): Collection
    {
        $paymentRows = DB::table('payments')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'payments.sale_id')
                    ->on('sales.tenant_id', '=', 'payments.tenant_id');
            })
            ->where('sales.status', 'completed')
            ->where('sales.tenant_id', $tenantId)
            ->where('payments.tenant_id', $tenantId)
            ->whereRaw($this->saleBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->saleBusinessDateExpr().' <= ?', [$endDate])
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
            ->whereRaw($this->refundBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->refundBusinessDateExpr().' <= ?', [$endDate])
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
                        : 0,
                ];
            })
            ->filter(fn (array $row) => abs($row['amount']) > 0.00001)
            ->sortByDesc('amount')
            ->values();
    }

    public function trendForEventDay(string $tenantId, string $businessDate, string $startUtc, string $endUtc): array
    {
        $hourExpr = match (DB::getDriverName()) {
            'mysql' => "DATE_FORMAT(created_at, '%H')",
            'pgsql' => "to_char(created_at, 'HH24')",
            default => "strftime('%H', created_at)",
        };

        // Trend remains event-timestamp based because an hourly chart needs the actual event hour.
        $trendRows = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereRaw($this->saleBusinessDateExpr().' = ?', [$businessDate])
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->selectRaw($hourExpr.' as hour')
            ->selectRaw('sum(subtotal - discount) as total')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $refundTrendRows = DB::table('refunds')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereRaw($this->refundBusinessDateExpr().' = ?', [$businessDate])
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->selectRaw($hourExpr.' as hour')
            ->selectRaw('sum(total - tax_total) as total')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $trendMap = [];
        foreach ($trendRows as $row) {
            $trendMap[(int) $row->hour] = Decimal::money($row->total ?? 0);
        }
        foreach ($refundTrendRows as $row) {
            $hour = (int) $row->hour;
            $trendMap[$hour] = Decimal::subtract($trendMap[$hour] ?? 0, $row->total ?? 0, Decimal::MONEY_SCALE);
        }

        $trendHours = [8, 12, 16, 20, 22];
        $trend = [];
        foreach ($trendHours as $hour) {
            $trend[] = [
                'hour' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
                'total' => Decimal::float($trendMap[$hour] ?? 0, Decimal::MONEY_SCALE),
            ];
        }

        return $trend;
    }

    private function saleLineMetricsQuery(string $tenantId, string $startDate, string $endDate)
    {
        $allocationBasisExpr = $this->saleAllocationBasisExpr();
        $allocationBaseSub = DB::table('sale_items')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'sale_items.sale_id')
                    ->on('sales.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->join('products', function ($join) {
                $join->on('products.id', '=', 'sale_items.product_id')
                    ->on('products.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->select('sale_items.sale_id')
            ->selectRaw('sum('.$allocationBasisExpr.') as allocation_basis_total')
            ->groupBy('sale_items.sale_id');

        $allocatedDiscountExpr = 'case when allocation_basis_totals.allocation_basis_total > 0 then (sales.discount * ('
            .$allocationBasisExpr.') / allocation_basis_totals.allocation_basis_total) else 0 end';
        $grossExpr = '(sale_items.qty * sale_items.price)';
        $netSalesBeforeTaxExpr = $grossExpr.' - sale_items.discount - '.$allocatedDiscountExpr;
        $grossProfitExpr = $netSalesBeforeTaxExpr.' - (sale_items.qty * sale_items.hpp_snapshot)';

        return DB::table('sale_items')
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
            ->leftJoinSub($allocationBaseSub, 'allocation_basis_totals', function ($join) {
                $join->on('allocation_basis_totals.sale_id', '=', 'sale_items.sale_id');
            })
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->where('sale_items.tenant_id', $tenantId)
            ->where('products.tenant_id', $tenantId)
            ->whereRaw($this->saleBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->saleBusinessDateExpr().' <= ?', [$endDate])
            ->selectRaw($this->saleBusinessDateExpr().' as business_date')
            ->selectRaw('sale_items.qty as line_qty')
            ->selectRaw($grossExpr.' as line_gross_sales')
            ->selectRaw('sale_items.discount as line_item_discount')
            ->selectRaw($allocatedDiscountExpr.' as line_allocated_transaction_discount')
            ->selectRaw($netSalesBeforeTaxExpr.' as line_net_sales_before_tax')
            ->selectRaw('(sale_items.qty * sale_items.hpp_snapshot) as line_cogs')
            ->selectRaw($grossProfitExpr.' as line_gross_profit')
            ->selectRaw('sale_items.product_id as product_id')
            ->selectRaw('products.name as product_name')
            ->selectRaw('products.image_url as image_url')
            ->selectRaw('products.sku as sku')
            ->selectRaw('categories.name as category_name');
    }

    private function refundLineMetricsQuery(string $tenantId, string $startDate, string $endDate)
    {
        $revenueReversalExpr = 'refund_items.gross_amount - refund_items.item_discount_amount - refund_items.transaction_discount_amount';
        $grossProfitReversalExpr = $revenueReversalExpr.' - refund_items.refunded_cost_total';

        return DB::table('refund_items')
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
            ->where('refunds.tenant_id', $tenantId)
            ->where('refunds.status', 'completed')
            ->where('refund_items.tenant_id', $tenantId)
            ->where('products.tenant_id', $tenantId)
            ->whereRaw($this->refundBusinessDateExpr().' >= ?', [$startDate])
            ->whereRaw($this->refundBusinessDateExpr().' <= ?', [$endDate])
            ->selectRaw($this->refundBusinessDateExpr().' as business_date')
            ->selectRaw('refund_items.qty as line_qty')
            ->selectRaw($revenueReversalExpr.' as line_revenue_reversal_before_tax')
            ->selectRaw('refund_items.refunded_cost_total as line_refunded_cogs')
            ->selectRaw($grossProfitReversalExpr.' as line_gross_profit_reversal')
            ->selectRaw('refund_items.product_id as product_id')
            ->selectRaw('products.name as product_name')
            ->selectRaw('products.image_url as image_url')
            ->selectRaw('products.sku as sku')
            ->selectRaw('categories.name as category_name');
    }

    private function feeRevenueForRange(string $tenantId, string $startDate, string $endDate): float
    {
        $saleFee = Decimal::money(DB::query()
            ->fromSub($this->saleLineMetricsQuery($tenantId, $startDate, $endDate), 'sale_line_metrics')
            ->where('sku', 'like', 'FEE-%')
            ->selectRaw('sum(line_net_sales_before_tax) as fee')
            ->value('fee') ?? 0);

        $refundFee = Decimal::money(DB::query()
            ->fromSub($this->refundLineMetricsQuery($tenantId, $startDate, $endDate), 'refund_line_metrics')
            ->where('sku', 'like', 'FEE-%')
            ->selectRaw('sum(line_revenue_reversal_before_tax) as fee')
            ->value('fee') ?? 0);

        return Decimal::float(Decimal::subtract($saleFee, $refundFee, Decimal::MONEY_SCALE), Decimal::MONEY_SCALE);
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
