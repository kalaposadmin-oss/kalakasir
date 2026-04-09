<?php

namespace App\Jobs;

use App\Models\Sale;
use App\Services\AdaptiveAnomalyThresholdService;
use App\Services\AnomalyDetectorMetricsService;
use App\Services\AnomalyService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DetectVoidSplitAttackJob implements ShouldQueue
{
    use Queueable;

    public function handle(AnomalyService $anomalyService): void
    {
        $startedAt = microtime(true);
        $success = false;
        $metrics = app(AnomalyDetectorMetricsService::class);

        try {
            $now = CarbonImmutable::now('UTC');
            $windowMinutes = max(15, (int) config('services.anomaly.void_split_window_minutes', 60));
            $since = $now->subMinutes($windowMinutes);
            $baselineHours = max(6, (int) config('services.anomaly.void_split_baseline_hours', 24));
            $baselineStart = $now->subHours($baselineHours);
            $windowCount = max(1, (int) floor((60 * $baselineHours) / $windowMinutes) - 1);

            $smallVoidAmountLimit = max(0, (float) config('services.anomaly.void_split_small_amount_limit', 50000));
            $actorThreshold = max(3, (int) config('services.anomaly.void_split_actor_count_threshold', 20));
            $tenantFloor = max(5, (int) config('services.anomaly.void_split_tenant_min_floor', 25));
            $growthMultiplier = (float) config('services.anomaly.void_split_growth_multiplier', 2.5);
            $additiveBuffer = max(0, (int) config('services.anomaly.void_split_additive_buffer', 3));
            $minSampleEvents = max(1, (int) config('services.anomaly.void_split_min_sample_events', 30));
            $maxThreshold = max(0, (int) config('services.anomaly.void_split_max_threshold', 300));

            $thresholdService = app(AdaptiveAnomalyThresholdService::class);
            $tenantIds = DB::table('tenants')->pluck('id')->all();

            foreach ($tenantIds as $tenantId) {
                $current = DB::table('sales')
                    ->where('tenant_id', $tenantId)
                    ->where('status', Sale::STATUS_VOIDED)
                    ->where('voided_at', '>=', $since)
                    ->where('total', '<=', $smallVoidAmountLimit);

                $smallVoidCount = (int) $current->count();
                if ($smallVoidCount === 0) {
                    continue;
                }

                $actorRows = DB::table('sales')
                    ->where('tenant_id', $tenantId)
                    ->where('status', Sale::STATUS_VOIDED)
                    ->where('voided_at', '>=', $since)
                    ->where('total', '<=', $smallVoidAmountLimit)
                    ->whereNotNull('voided_by')
                    ->selectRaw('voided_by, count(*) as total')
                    ->groupBy('voided_by')
                    ->orderByDesc('total')
                    ->get();

                $uniqueActors = max(1, $actorRows->count());
                $topActor = $actorRows->first();
                $topActorCount = (int) ($topActor->total ?? 0);
                $topActorId = $topActor->voided_by ?? null;
                $topActorShare = $smallVoidCount > 0 ? round($topActorCount / $smallVoidCount, 4) : 0.0;

                $historicalCount = (int) DB::table('sales')
                    ->where('tenant_id', $tenantId)
                    ->where('status', Sale::STATUS_VOIDED)
                    ->whereBetween('voided_at', [$baselineStart, $since])
                    ->where('total', '<=', $smallVoidAmountLimit)
                    ->count();

                $tenantStats = $thresholdService->windowThreshold(
                    historicalCount: $historicalCount,
                    windowCount: $windowCount,
                    floor: $tenantFloor,
                    growthMultiplier: $growthMultiplier,
                    additiveBuffer: $additiveBuffer,
                    minSampleEvents: $minSampleEvents,
                    maxThresholdCap: $maxThreshold,
                );

                if ($smallVoidCount > (int) $tenantStats['threshold']) {
                    $anomalyService->raise($tenantId, 'high', 'void_small_event_rate_spike', [
                        'window_minutes' => $windowMinutes,
                        'small_void_count' => $smallVoidCount,
                        'threshold' => (int) $tenantStats['threshold'],
                        'expected_per_window' => round((float) $tenantStats['expected'], 2),
                        'sample_events' => (int) ($tenantStats['sample_events'] ?? 0),
                        'cold_start' => (bool) ($tenantStats['cold_start'] ?? false),
                        'small_amount_limit' => $smallVoidAmountLimit,
                        'unique_actors' => $uniqueActors,
                        'top_actor_id' => $topActorId,
                        'top_actor_count' => $topActorCount,
                        'top_actor_share' => $topActorShare,
                    ]);
                }

                if ($topActorCount >= $actorThreshold) {
                    $anomalyService->raise($tenantId, 'high', 'void_split_attack_actor', [
                        'window_minutes' => $windowMinutes,
                        'small_amount_limit' => $smallVoidAmountLimit,
                        'top_actor_id' => $topActorId,
                        'top_actor_count' => $topActorCount,
                        'actor_threshold' => $actorThreshold,
                        'small_void_count' => $smallVoidCount,
                        'unique_actors' => $uniqueActors,
                        'top_actor_share' => $topActorShare,
                    ]);
                }
            }

            $success = true;
        } finally {
            $metrics->recordRun('void_split_attack', $startedAt, $success);
        }
    }
}
