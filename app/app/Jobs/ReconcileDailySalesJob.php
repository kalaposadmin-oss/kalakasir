<?php

namespace App\Jobs;

use App\Models\DailySalesSummary;
use App\Models\Sale;
use App\Services\AnomalyService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ReconcileDailySalesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ?string $date = null)
    {
    }

    public function handle(AnomalyService $anomalyService): void
    {
        $date = $this->date ?: CarbonImmutable::now('UTC')->subDay()->toDateString();

        $tenantIds = DB::table('tenants')->pluck('id')->all();

        foreach ($tenantIds as $tenantId) {
            $source = DB::table('sales')
                ->where('tenant_id', $tenantId)
                ->where('status', Sale::STATUS_COMPLETED)
                ->whereRaw('COALESCE(transaction_date, DATE(created_at)) = ?', [$date])
                ->sum('total');

            $summary = (float) DailySalesSummary::query()
                ->where('tenant_id', $tenantId)
                ->where('date', $date)
                ->value('total_sales');

            $delta = abs((float) $source - $summary);
            $pct = $source > 0 ? ($delta / (float) $source) * 100 : 0;

            if ($delta > 10000 || $pct > 0.5) {
                $anomalyService->raise(
                    tenantId: $tenantId,
                    severity: 'high',
                    type: 'summary_drift',
                    payload: [
                        'date' => $date,
                        'source_total' => (float) $source,
                        'summary_total' => $summary,
                        'delta' => $delta,
                        'delta_pct' => $pct,
                    ]
                );
            }
        }
    }
}
