<?php

namespace App\Jobs;

use App\Services\AnomalyService;
use App\Services\AdaptiveAnomalyThresholdService;
use App\Services\AnomalyDetectorMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DetectAdjustmentSpikeJob implements ShouldQueue
{
    use Queueable;

    public function handle(AnomalyService $anomalyService): void
    {
        $startedAt = microtime(true);
        $success = false;
        $metrics = app(AnomalyDetectorMetricsService::class);

        try {
            $today = CarbonImmutable::now('UTC')->toDateString();
            $thresholdService = app(AdaptiveAnomalyThresholdService::class);
            $historyDays = max(7, (int) config('services.anomaly.adjustment_spike_lookback_days', 21));
            $floor = max(1, (int) config('services.anomaly.adjustment_spike_min_floor', 100));
            $sigmaMultiplier = (float) config('services.anomaly.adjustment_spike_sigma_multiplier', 3.0);
            $growthMultiplier = (float) config('services.anomaly.adjustment_spike_growth_multiplier', 2.0);
            $p95Multiplier = (float) config('services.anomaly.adjustment_spike_p95_multiplier', 1.1);
            $minSampleEvents = max(1, (int) config('services.anomaly.adjustment_spike_min_sample_events', 30));
            $additiveBuffer = max(0, (int) config('services.anomaly.adjustment_spike_additive_buffer', 2));
            $maxThreshold = max(0, (int) config('services.anomaly.adjustment_spike_max_threshold', 5000));

            $tenantIds = DB::table('tenants')->pluck('id')->all();

            foreach ($tenantIds as $tenantId) {
                $qty = (float) DB::table('stock_adjustment_items')
                    ->join('stock_adjustments', function ($join) {
                        $join->on('stock_adjustment_items.stock_adjustment_id', '=', 'stock_adjustments.id')
                            ->on('stock_adjustment_items.tenant_id', '=', 'stock_adjustments.tenant_id');
                    })
                    ->where('stock_adjustments.tenant_id', $tenantId)
                    ->where('stock_adjustments.status', 'posted')
                    ->whereDate('stock_adjustments.posted_at', $today)
                    ->selectRaw('sum(abs(stock_adjustment_items.difference_qty)) as total')
                    ->value('total');

                $history = [];
                for ($i = 1; $i <= $historyDays; $i++) {
                    $day = CarbonImmutable::parse($today)->subDays($i)->toDateString();

                    $history[] = (float) DB::table('stock_adjustment_items')
                        ->join('stock_adjustments', function ($join) {
                            $join->on('stock_adjustment_items.stock_adjustment_id', '=', 'stock_adjustments.id')
                                ->on('stock_adjustment_items.tenant_id', '=', 'stock_adjustments.tenant_id');
                        })
                        ->where('stock_adjustments.tenant_id', $tenantId)
                        ->where('stock_adjustments.status', 'posted')
                        ->whereDate('stock_adjustments.posted_at', $day)
                        ->selectRaw('sum(abs(stock_adjustment_items.difference_qty)) as total')
                        ->value('total');
                }

                $stats = $thresholdService->dailyThreshold(
                    history: $history,
                    floor: $floor,
                    sigmaMultiplier: $sigmaMultiplier,
                    growthMultiplier: $growthMultiplier,
                    p95Multiplier: $p95Multiplier,
                    minSampleEvents: $minSampleEvents,
                    additiveBuffer: $additiveBuffer,
                    maxThresholdCap: $maxThreshold
                );

                if ($qty > (float) $stats['threshold']) {
                    $anomalyService->raise($tenantId, 'med', 'adjustment_spike', [
                        'total_abs_difference' => $qty,
                        'threshold' => (int) $stats['threshold'],
                        'avg_daily' => round((float) $stats['avg'], 2),
                        'stddev_daily' => round((float) $stats['stddev'], 2),
                        'median_daily' => round((float) ($stats['median'] ?? 0), 2),
                        'mad_daily' => round((float) ($stats['mad'] ?? 0), 2),
                        'p95_daily' => round((float) $stats['p95'], 2),
                        'sample_events' => (int) ($stats['sample_events'] ?? 0),
                        'cold_start' => (bool) ($stats['cold_start'] ?? false),
                        'history_days' => $historyDays,
                    ]);
                }
            }

            $success = true;
        } finally {
            $metrics->recordRun('adjustment_spike', $startedAt, $success);
        }
    }
}
