<?php

namespace App\Jobs;

use App\Models\DailySalesSummary;
use App\Models\Sale;
use App\Services\SalesCostReadinessService;
use App\Services\SystemHealthEventService;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RepairDailySalesSummaryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $date
    ) {
    }

    public function handle(): void
    {
        try {
            $readiness = app(SalesCostReadinessService::class);
            $readiness->assertNoMissingCompletedSalesForBusinessDateRange(
                $this->tenantId,
                $this->date,
                $this->date,
                'daily-sales-summary-repair'
            );

            $saleAggregateCogs = $readiness->saleAggregateTotalsSubquery($this->tenantId);

            $summary = [
            'total_sales' => Decimal::float(Decimal::money(DB::table('sales')
                ->where('tenant_id', $this->tenantId)
                ->where('status', Sale::STATUS_COMPLETED)
                ->whereRaw('COALESCE(transaction_date, DATE(created_at)) = ?', [$this->date])
                ->sum('total')), Decimal::MONEY_SCALE),
            'total_tax' => Decimal::float(Decimal::money(DB::table('sales')
                ->where('tenant_id', $this->tenantId)
                ->where('status', Sale::STATUS_COMPLETED)
                ->whereRaw('COALESCE(transaction_date, DATE(created_at)) = ?', [$this->date])
                ->sum('tax')), Decimal::MONEY_SCALE),
            'total_discount' => Decimal::float(Decimal::money(DB::table('sales')
                ->where('tenant_id', $this->tenantId)
                ->where('status', Sale::STATUS_COMPLETED)
                ->whereRaw('COALESCE(transaction_date, DATE(created_at)) = ?', [$this->date])
                ->sum('discount')), Decimal::MONEY_SCALE),
            'transaction_count' => (int) DB::table('sales')
                ->where('tenant_id', $this->tenantId)
                ->where('status', Sale::STATUS_COMPLETED)
                ->whereRaw('COALESCE(transaction_date, DATE(created_at)) = ?', [$this->date])
                ->count(),
            'total_cost' => Decimal::float(Decimal::money(DB::table('sales')
                ->joinSub($saleAggregateCogs, 'sale_aggregate_cogs', function ($join) {
                    $join->on('sale_aggregate_cogs.sale_id', '=', 'sales.id')
                        ->on('sale_aggregate_cogs.tenant_id', '=', 'sales.tenant_id');
                })
                ->where('sales.tenant_id', $this->tenantId)
                ->where('sales.status', Sale::STATUS_COMPLETED)
                ->whereRaw('COALESCE(sales.transaction_date, DATE(sales.created_at)) = ?', [$this->date])
                ->sum('sale_aggregate_cogs.aggregated_cogs')), Decimal::MONEY_SCALE),
            'updated_at' => CarbonImmutable::now('UTC'),
            ];

            $row = DailySalesSummary::query()
                ->where('tenant_id', $this->tenantId)
                ->where('date', $this->date)
                ->first();

            if (! $row) {
                DailySalesSummary::query()->create(array_merge($summary, [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $this->tenantId,
                    'date' => $this->date,
                ]));

                return;
            }

            $row->update($summary);
        } catch (\Throwable $exception) {
            app(SystemHealthEventService::class)->recordOrBump('daily_summary_refresh_failed', [
                'tenant_id' => $this->tenantId,
                'entity_type' => 'daily_sales_summary',
                'entity_id' => $this->date,
                'summary' => 'Repair daily sales summary job failed.',
                'payload' => [
                    'date' => $this->date,
                    'message' => $exception->getMessage(),
                ],
                'source' => 'repair-daily-sales-summary-job',
                'dedupe_key' => 'daily_summary_refresh_failed:'.$this->tenantId.':'.$this->date,
            ]);

            throw $exception;
        }
    }
}
