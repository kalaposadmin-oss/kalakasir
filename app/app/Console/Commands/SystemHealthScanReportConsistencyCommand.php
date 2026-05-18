<?php

namespace App\Console\Commands;

use App\Services\SystemHealthEventService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SystemHealthScanReportConsistencyCommand extends Command
{
    protected $signature = 'system-health:scan-report-consistency
        {--tenant= : Tenant ID}
        {--from= : Start business date}
        {--to= : End business date}';

    protected $description = 'Scan cross-report consistency, refund reversal cost, and stale summary state.';

    public function handle(SystemHealthEventService $events): int
    {
        $tenantId = (string) $this->option('tenant');
        $from = (string) $this->option('from') ?: CarbonImmutable::now('UTC')->subDays(7)->toDateString();
        $to = (string) $this->option('to') ?: CarbonImmutable::now('UTC')->toDateString();

        $crossReportCount = $this->scanCrossReportMismatch($events, $tenantId, $from, $to);
        $refundMismatchCount = $this->scanRefundMismatch($events, $tenantId, $from, $to);
        $staleSummaryCount = $this->scanStaleSummary($events, $tenantId, $from, $to);

        $this->line('cross_report_mismatch: '.$crossReportCount);
        $this->line('refund_cost_reversal_mismatch: '.$refundMismatchCount);
        $this->line('stale_or_missing_summary: '.$staleSummaryCount);

        if (($crossReportCount + $refundMismatchCount + $staleSummaryCount) > 0) {
            $this->error('report consistency scan menemukan anomaly');

            return self::FAILURE;
        }

        $this->info('report consistency scan clean');

        return self::SUCCESS;
    }

    private function scanCrossReportMismatch(SystemHealthEventService $events, string $tenantId, string $from, string $to): int
    {
        $rows = DB::table('daily_sales_summaries as dss')
            ->leftJoinSub(
                DB::table('sale_cost_components as scc')
                    ->join('sales', function ($join): void {
                        $join->on('sales.id', '=', 'scc.sale_id')
                            ->on('sales.tenant_id', '=', 'scc.tenant_id');
                    })
                    ->where('sales.status', 'completed')
                    ->selectRaw('scc.tenant_id, DATE(COALESCE(sales.transaction_date, sales.created_at)) as business_date, SUM(scc.total_cost) as aggregated_cogs')
                    ->groupBy('scc.tenant_id', DB::raw('DATE(COALESCE(sales.transaction_date, sales.created_at))')),
                'agg',
                function ($join): void {
                    $join->on('agg.tenant_id', '=', 'dss.tenant_id')
                        ->on('agg.business_date', '=', 'dss.date');
                }
            )
            ->whereBetween('dss.date', [$from, $to])
            ->when($tenantId !== '', fn ($query) => $query->where('dss.tenant_id', $tenantId))
            ->whereRaw('ABS(COALESCE(dss.total_cost, 0) - COALESCE(agg.aggregated_cogs, 0)) > 0.01')
            ->get([
                'dss.tenant_id',
                'dss.date',
                'dss.total_cost',
                DB::raw('COALESCE(agg.aggregated_cogs, 0) as aggregated_cogs'),
            ]);

        foreach ($rows as $row) {
            $events->recordOrBump('cross_report_mismatch', [
                'tenant_id' => (string) $row->tenant_id,
                'entity_type' => 'daily_sales_summary',
                'entity_id' => (string) $row->date,
                'summary' => 'Daily sales summary total_cost differs from aggregated sale cost components.',
                'payload' => [
                    'date' => (string) $row->date,
                    'daily_sales_summary_total_cost' => (float) $row->total_cost,
                    'aggregated_cogs' => (float) $row->aggregated_cogs,
                ],
                'source' => 'system-health:scan-report-consistency',
                'dedupe_key' => 'cross_report_mismatch:'.$row->tenant_id.':'.$row->date,
            ]);
        }

        return $rows->count();
    }

    private function scanRefundMismatch(SystemHealthEventService $events, string $tenantId, string $from, string $to): int
    {
        $rows = DB::table('refunds')
            ->leftJoin('refund_items', function ($join): void {
                $join->on('refund_items.refund_id', '=', 'refunds.id')
                    ->on('refund_items.tenant_id', '=', 'refunds.tenant_id');
            })
            ->where('refunds.status', 'completed')
            ->whereBetween(DB::raw('DATE(refunds.created_at)'), [$from, $to])
            ->when($tenantId !== '', fn ($query) => $query->where('refunds.tenant_id', $tenantId))
            ->groupBy('refunds.id', 'refunds.tenant_id', 'refunds.refunded_cost_total')
            ->havingRaw('ABS(COALESCE(refunds.refunded_cost_total, 0) - COALESCE(SUM(refund_items.refunded_cost_total), 0)) > 0.01')
            ->get([
                'refunds.id',
                'refunds.tenant_id',
                'refunds.refunded_cost_total',
                DB::raw('COALESCE(SUM(refund_items.refunded_cost_total), 0) as item_refunded_cost_total'),
            ]);

        foreach ($rows as $row) {
            $events->recordOrBump('refund_cost_reversal_mismatch', [
                'tenant_id' => (string) $row->tenant_id,
                'entity_type' => 'refund',
                'entity_id' => (string) $row->id,
                'summary' => 'Refund header refunded_cost_total differs from refund item cost reversal total.',
                'payload' => [
                    'refund_id' => (string) $row->id,
                    'refund_header_cost_total' => (float) $row->refunded_cost_total,
                    'refund_item_cost_total' => (float) $row->item_refunded_cost_total,
                ],
                'source' => 'system-health:scan-report-consistency',
                'dedupe_key' => 'refund_cost_reversal_mismatch:'.$row->tenant_id.':'.$row->id,
            ]);
        }

        return $rows->count();
    }

    private function scanStaleSummary(SystemHealthEventService $events, string $tenantId, string $from, string $to): int
    {
        $thresholdHours = max(1, (int) config('system_health.stale_summary_hours', 24));
        $cutoff = CarbonImmutable::now('UTC')->subHours($thresholdHours);

        $rows = DB::table('sales')
            ->leftJoin('daily_sales_summaries as dss', function ($join): void {
                $join->on('dss.tenant_id', '=', 'sales.tenant_id')
                    ->whereRaw('dss.date = DATE(COALESCE(sales.transaction_date, sales.created_at))');
            })
            ->where('sales.status', 'completed')
            ->whereBetween(DB::raw('DATE(COALESCE(sales.transaction_date, sales.created_at))'), [$from, $to])
            ->when($tenantId !== '', fn ($query) => $query->where('sales.tenant_id', $tenantId))
            ->groupBy('sales.tenant_id', DB::raw('DATE(COALESCE(sales.transaction_date, sales.created_at))'), 'dss.id', 'dss.updated_at')
            ->havingRaw('dss.id IS NULL OR dss.updated_at < ?', [$cutoff])
            ->get([
                'sales.tenant_id',
                DB::raw('DATE(COALESCE(sales.transaction_date, sales.created_at)) as business_date'),
                'dss.id as summary_id',
                'dss.updated_at',
            ]);

        foreach ($rows as $row) {
            $events->recordOrBump('daily_summary_refresh_failed', [
                'tenant_id' => (string) $row->tenant_id,
                'entity_type' => 'daily_sales_summary',
                'entity_id' => (string) $row->business_date,
                'summary' => 'Daily sales summary is missing or stale for a business date with completed sales.',
                'payload' => [
                    'date' => (string) $row->business_date,
                    'summary_id' => $row->summary_id,
                    'updated_at' => $row->updated_at,
                    'stale_threshold_hours' => $thresholdHours,
                ],
                'source' => 'system-health:scan-report-consistency',
                'dedupe_key' => 'daily_summary_refresh_failed:'.$row->tenant_id.':'.$row->business_date,
            ]);
        }

        return $rows->count();
    }
}