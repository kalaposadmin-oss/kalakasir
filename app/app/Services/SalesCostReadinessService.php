<?php

namespace App\Services;

use App\Exceptions\MissingSalesCostAggregateException;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class SalesCostReadinessService
{
    public function saleAggregateTotalsSubquery(string $tenantId)
    {
        return DB::table('sale_cost_components')
            ->where('tenant_id', $tenantId)
            ->select('tenant_id', 'sale_id')
            ->selectRaw('count(*) as component_count')
            ->selectRaw('sum(total_cost) as aggregated_cogs')
            ->groupBy('tenant_id', 'sale_id');
    }

    public function saleItemAggregateTotalsSubquery(string $tenantId)
    {
        return DB::table('sale_cost_components')
            ->where('tenant_id', $tenantId)
            ->select('tenant_id', 'sale_item_id')
            ->selectRaw('sum(total_cost) as aggregated_cost')
            ->groupBy('tenant_id', 'sale_item_id');
    }

    public function aggregatedTotalForSale(string $tenantId, string $saleId): string
    {
        return Decimal::money(DB::table('sale_cost_components')
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->selectRaw('coalesce(sum(total_cost), 0) as total')
            ->value('total'));
    }

    public function assertSaleHasAggregate(string $tenantId, string $saleId, string $context = 'sales-cost'): void
    {
        $summary = $this->summarizeInvalidSales(
            $this->validationBaseQuery($tenantId, null, null)
                ->where('sales.id', $saleId)
        );

        if ($summary['invalid_sales'] > 0) {
            $this->recordInvalidSalesEvents($tenantId, $context, $summary, 'sale', $saleId, [
                'scope' => ['sale_id' => $saleId],
            ]);

            throw new MissingSalesCostAggregateException(
                $context,
                $summary['sample_sale_ids'],
                $this->summaryReason($summary),
                $summary
            );
        }
    }

    public function assertNoMissingCompletedSalesForCreatedAtRange(
        string $tenantId,
        string $startUtc,
        string $endExclusiveUtc,
        string $context
    ): void {
        $summary = $this->summarizeInvalidSales(
            $this->validationBaseQuery($tenantId, null, null)
                ->where('sales.tenant_id', $tenantId)
                ->where('sales.created_at', '>=', $startUtc)
                ->where('sales.created_at', '<', $endExclusiveUtc)
        );

        if ($summary['invalid_sales'] > 0) {
            $scopeId = sha1($tenantId.'|'.$startUtc.'|'.$endExclusiveUtc.'|'.$context.'|created_at');
            $this->recordInvalidSalesEvents($tenantId, $context, $summary, 'sales_cost_scope', $scopeId, [
                'scope' => ['start_utc' => $startUtc, 'end_exclusive_utc' => $endExclusiveUtc],
            ]);

            throw new MissingSalesCostAggregateException(
                $context,
                $summary['sample_sale_ids'],
                'invalid_sales_cost_state',
                $summary
            );
        }
    }

    public function assertNoMissingCompletedSalesForBusinessDateRange(
        string $tenantId,
        string $startDate,
        string $endDate,
        string $context
    ): void {
        $summary = $this->summarizeInvalidSales(
            $this->validationBaseQuery($tenantId, null, null)
                ->where('sales.tenant_id', $tenantId)
                ->whereRaw('DATE(COALESCE(sales.transaction_date, sales.created_at)) >= ?', [$startDate])
                ->whereRaw('DATE(COALESCE(sales.transaction_date, sales.created_at)) <= ?', [$endDate])
        );

        if ($summary['invalid_sales'] > 0) {
            $scopeId = sha1($tenantId.'|'.$startDate.'|'.$endDate.'|'.$context.'|business_date');
            $this->recordInvalidSalesEvents($tenantId, $context, $summary, 'sales_cost_scope', $scopeId, [
                'scope' => ['start_date' => $startDate, 'end_date' => $endDate],
            ]);

            throw new MissingSalesCostAggregateException(
                $context,
                $summary['sample_sale_ids'],
                'invalid_sales_cost_state',
                $summary
            );
        }
    }

    public function validationSummary(?string $tenantId = null, ?string $from = null, ?string $to = null): array
    {
        return $this->summarizeInvalidSales($this->validationBaseQuery($tenantId, $from, $to));
    }

    private function validationBaseQuery(?string $tenantId, ?string $from, ?string $to)
    {
        $aggregateSubquery = DB::table('sale_cost_components')
            ->select('tenant_id', 'sale_id')
            ->selectRaw('count(*) as component_count')
            ->selectRaw('sum(total_cost) as aggregated_cogs')
            ->groupBy('tenant_id', 'sale_id');

        return DB::table('sales')
            ->leftJoinSub($aggregateSubquery, 'sale_cost_aggregates', function ($join) {
                $join->on('sale_cost_aggregates.sale_id', '=', 'sales.id')
                    ->on('sale_cost_aggregates.tenant_id', '=', 'sales.tenant_id');
            })
            ->where('sales.status', 'completed')
            ->when($tenantId !== null && $tenantId !== '', fn ($builder) => $builder->where('sales.tenant_id', $tenantId))
            ->when($from !== null && $from !== '', fn ($builder) => $builder->whereRaw('DATE(COALESCE(sales.transaction_date, sales.created_at)) >= ?', [$from]))
            ->when($to !== null && $to !== '', fn ($builder) => $builder->whereRaw('DATE(COALESCE(sales.transaction_date, sales.created_at)) <= ?', [$to]));
    }

    private function summarizeInvalidSales($query): array
    {
        $completedSales = (clone $query)->count();
        $missingComponentsQuery = (clone $query)->whereNull('sale_cost_aggregates.sale_id');
        $missingHeaderQuery = (clone $query)
            ->whereNotNull('sale_cost_aggregates.sale_id')
            ->whereNull('sales.cogs_total');
        $mismatchQuery = (clone $query)
            ->whereNotNull('sale_cost_aggregates.sale_id')
            ->whereNotNull('sales.cogs_total')
            ->whereRaw('abs(sale_cost_aggregates.aggregated_cogs - sales.cogs_total) > 0.01');

        $missingComponents = (clone $missingComponentsQuery)->count();
        $missingHeaderCogs = (clone $missingHeaderQuery)->count();
        $mismatchedHeaderCogs = (clone $mismatchQuery)->count();

        $sampleSaleIds = collect()
            ->merge((clone $missingComponentsQuery)->limit(10)->pluck('sales.id'))
            ->merge((clone $missingHeaderQuery)->limit(10)->pluck('sales.id'))
            ->merge((clone $mismatchQuery)->limit(10)->pluck('sales.id'))
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        return [
            'completed_sales' => $completedSales,
            'missing_component_sales' => $missingComponents,
            'missing_header_cogs' => $missingHeaderCogs,
            'mismatched_header_cogs' => $mismatchedHeaderCogs,
            'invalid_sales' => $missingComponents + $missingHeaderCogs + $mismatchedHeaderCogs,
            'sample_sale_ids' => $sampleSaleIds,
        ];
    }

    private function summaryReason(array $summary): string
    {
        if (($summary['missing_component_sales'] ?? 0) > 0) {
            return 'missing_aggregate';
        }

        if (($summary['missing_header_cogs'] ?? 0) > 0) {
            return 'missing_header_cogs';
        }

        if (($summary['mismatched_header_cogs'] ?? 0) > 0) {
            return 'mismatched_header_cogs';
        }

        return 'invalid_sales_cost_state';
    }

    private function recordInvalidSalesEvents(
        string $tenantId,
        string $context,
        array $summary,
        string $entityType,
        string $entityId,
        array $extraPayload = []
    ): void {
        $service = app(SystemHealthEventService::class);
        $payload = array_merge($extraPayload, [
            'context' => $context,
            'summary' => $summary,
            'sample_sale_ids' => $summary['sample_sale_ids'] ?? [],
        ]);

        if (($summary['missing_component_sales'] ?? 0) > 0) {
            $service->recordOrBump('sales_cost_missing_aggregate', [
                'tenant_id' => $tenantId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'summary' => 'Completed sales are missing aggregate COGS for '.$context.'.',
                'payload' => $payload,
                'source' => 'sales-cost-readiness',
                'dedupe_key' => 'sales_cost_missing_aggregate:'.$tenantId.':'.$entityType.':'.$entityId,
            ]);
        }

        if ((($summary['missing_header_cogs'] ?? 0) + ($summary['mismatched_header_cogs'] ?? 0)) > 0) {
            $service->recordOrBump('sales_cost_header_mismatch', [
                'tenant_id' => $tenantId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'summary' => 'sales.cogs_total is invalid against aggregate COGS for '.$context.'.',
                'payload' => $payload,
                'source' => 'sales-cost-readiness',
                'dedupe_key' => 'sales_cost_header_mismatch:'.$tenantId.':'.$entityType.':'.$entityId,
            ]);
        }
    }
}