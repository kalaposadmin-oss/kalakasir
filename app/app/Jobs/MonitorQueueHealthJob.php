<?php

namespace App\Jobs;

use App\Models\DailySalesSummary;
use App\Models\Sale;
use App\Services\AnomalyService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MonitorQueueHealthJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function handle(AnomalyService $anomalyService): void
    {
        $connection = config('queue.default');
        $now = CarbonImmutable::now('UTC');

        $depth = $this->queueDepth($connection);
        $failedCount = (int) DB::table('failed_jobs')
            ->where('failed_at', '>=', $now->subMinutes(5))
            ->count();

        Log::info('queue.health', [
            'event_type' => 'queue.health',
            'queue_connection' => $connection,
            'queue_depth' => $depth,
            'failed_jobs_5m' => $failedCount,
            'reference_type' => 'queue',
            'reference_id' => $connection,
        ]);

        if ($depth > 1000) {
            $this->raiseGlobal($anomalyService, 'med', 'queue_depth_high', [
                'depth' => $depth,
                'connection' => $connection,
            ]);
        }

        if ($failedCount > 0) {
            $this->raiseGlobal($anomalyService, 'high', 'failed_jobs_spike', [
                'failed_jobs_5m' => $failedCount,
            ]);
        }

        $this->checkSummaryLag($anomalyService);
    }

    private function queueDepth(string $connection): int
    {
        if ($connection === 'database') {
            return (int) DB::table(config('queue.connections.database.table', 'jobs'))->count();
        }

        if ($connection === 'redis') {
            $queue = config('queue.connections.redis.queue', 'default');
            return (int) Redis::llen('queues:'.$queue);
        }

        return 0;
    }

    private function checkSummaryLag(AnomalyService $anomalyService): void
    {
        $rows = Sale::query()
            ->where('status', 'completed')
            ->where('created_at', '>=', CarbonImmutable::now('UTC')->subMinutes(15))
            ->limit(300)
            ->get(['tenant_id', 'created_at', 'transaction_date']);

        $lags = [];

        foreach ($rows as $row) {
            $summaryDate = $row->transaction_date
                ?: CarbonImmutable::parse($row->created_at)->toDateString();

            $summary = DailySalesSummary::query()
                ->where('tenant_id', $row->tenant_id)
                ->where('date', $summaryDate)
                ->first(['updated_at']);

            if (! $summary || ! $summary->updated_at) {
                continue;
            }

            $lag = CarbonImmutable::parse($summary->updated_at)->diffInSeconds(CarbonImmutable::parse($row->created_at));
            $lags[] = $lag;
        }

        if (empty($lags)) {
            return;
        }

        sort($lags);
        $idx = (int) floor((count($lags) - 1) * 0.95);
        $p95 = $lags[$idx] ?? 0;

        Log::info('summary.lag', [
            'event_type' => 'summary.lag',
            'p95_seconds' => $p95,
            'reference_type' => 'summary',
            'reference_id' => 'daily_sales',
        ]);

        if ($p95 > 120) {
            $this->raiseGlobal($anomalyService, 'med', 'summary_lag_high', [
                'p95_seconds' => $p95,
            ]);
        }
    }

    private function raiseGlobal(AnomalyService $anomalyService, string $severity, string $type, array $payload): void
    {
        $tenantIds = DB::table('tenants')->pluck('id')->all();

        foreach ($tenantIds as $tenantId) {
            $anomalyService->raise($tenantId, $severity, $type, $payload);
        }
    }
}
