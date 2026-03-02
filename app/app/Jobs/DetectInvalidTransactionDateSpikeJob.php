<?php

namespace App\Jobs;

use App\Services\AnomalyService;
use App\Services\AdaptiveAnomalyThresholdService;
use App\Services\AnomalyDetectorMetricsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DetectInvalidTransactionDateSpikeJob implements ShouldQueue
{
    use Queueable;

    public function handle(AnomalyService $anomalyService): void
    {
        $startedAt = microtime(true);
        $success = false;
        $metrics = app(AnomalyDetectorMetricsService::class);

        try {
            $now = CarbonImmutable::now('UTC');
            $windowMinutes = max(5, (int) config('services.anomaly.invalid_date_window_minutes', 15));
            $baselineHours = max(6, (int) config('services.anomaly.invalid_date_baseline_hours', 24));
            $since = $now->subMinutes($windowMinutes);
            $baselineStart = $now->subHours($baselineHours);
            $windowCount = max(1, (int) floor((60 * $baselineHours) / $windowMinutes) - 1);
            $floor = max(1, (int) config('services.anomaly.invalid_date_min_floor', 10));
            $thresholdService = app(AdaptiveAnomalyThresholdService::class);
            $minSampleEvents = max(1, (int) config('services.anomaly.invalid_date_min_sample_events', 30));
            $maxThreshold = max(0, (int) config('services.anomaly.invalid_date_max_threshold', 100));

            $tenantIds = DB::table('tenants')->pluck('id')->all();

            foreach ($tenantIds as $tenantId) {
                $count = (int) DB::table('anomalies')
                    ->where('tenant_id', $tenantId)
                    ->where('type', 'invalid_transaction_date_attempt')
                    ->where('detected_at', '>=', $since)
                    ->count();

                $historicalCount = (int) DB::table('anomalies')
                    ->where('tenant_id', $tenantId)
                    ->where('type', 'invalid_transaction_date_attempt')
                    ->whereBetween('detected_at', [$baselineStart, $since])
                    ->count();

                $stats = $thresholdService->windowThreshold(
                    historicalCount: $historicalCount,
                    windowCount: $windowCount,
                    floor: $floor,
                    growthMultiplier: (float) config('services.anomaly.invalid_date_growth_multiplier', 3.0),
                    additiveBuffer: (int) config('services.anomaly.invalid_date_additive_buffer', 2),
                    minSampleEvents: $minSampleEvents,
                    maxThresholdCap: $maxThreshold
                );

                if ($count > (int) $stats['threshold']) {
                    $anomalyService->raise($tenantId, 'med', 'invalid_transaction_date_spike', [
                        'window_minutes' => $windowMinutes,
                        'attempt_count' => $count,
                        'threshold' => (int) $stats['threshold'],
                        'expected_per_window' => round((float) $stats['expected'], 2),
                        'sample_events' => (int) ($stats['sample_events'] ?? 0),
                        'cold_start' => (bool) ($stats['cold_start'] ?? false),
                        'baseline_hours' => $baselineHours,
                    ]);
                }
            }

            $success = true;
        } finally {
            $metrics->recordRun('invalid_transaction_date_spike', $startedAt, $success);
        }
    }
}
