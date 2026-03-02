<?php

namespace App\Jobs;

use App\Models\DailySalesSummary;
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
        $summary = [
            'total_sales' => (float) DB::table('sales')
                ->where('tenant_id', $this->tenantId)
                ->where('status', 'completed')
                ->whereRaw('COALESCE(transaction_date, DATE(created_at)) = ?', [$this->date])
                ->sum('total'),
            'total_tax' => (float) DB::table('sales')
                ->where('tenant_id', $this->tenantId)
                ->where('status', 'completed')
                ->whereRaw('COALESCE(transaction_date, DATE(created_at)) = ?', [$this->date])
                ->sum('tax'),
            'total_discount' => (float) DB::table('sales')
                ->where('tenant_id', $this->tenantId)
                ->where('status', 'completed')
                ->whereRaw('COALESCE(transaction_date, DATE(created_at)) = ?', [$this->date])
                ->sum('discount'),
            'transaction_count' => (int) DB::table('sales')
                ->where('tenant_id', $this->tenantId)
                ->where('status', 'completed')
                ->whereRaw('COALESCE(transaction_date, DATE(created_at)) = ?', [$this->date])
                ->count(),
            'total_cost' => (float) DB::table('sale_items')
                ->join('sales', function ($join) {
                    $join->on('sale_items.sale_id', '=', 'sales.id')
                        ->on('sale_items.tenant_id', '=', 'sales.tenant_id');
                })
                ->where('sales.tenant_id', $this->tenantId)
                ->where('sales.status', 'completed')
                ->whereRaw('COALESCE(sales.transaction_date, DATE(sales.created_at)) = ?', [$this->date])
                ->selectRaw('sum(sale_items.qty * sale_items.hpp_snapshot) as total')
                ->value('total'),
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
    }
}
