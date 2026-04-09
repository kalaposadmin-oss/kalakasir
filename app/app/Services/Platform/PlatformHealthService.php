<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PlatformHealthService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $start = microtime(true);
        $generatedAt = CarbonImmutable::now('UTC')->toIso8601String();

        $dbOk = true;
        $dbMessage = 'Database normal.';
        $dbLatencyMs = null;
        try {
            $dbStart = microtime(true);
            DB::select('select 1');
            $dbLatencyMs = (int) round((microtime(true) - $dbStart) * 1000);
        } catch (\Throwable $exception) {
            $dbOk = false;
            $dbMessage = 'Database bermasalah.';
        }

        $cacheOk = true;
        $cacheMessage = 'Cache normal.';
        $cacheLatencyMs = null;
        try {
            $cacheKey = 'platform:health:ping';
            $cacheStart = microtime(true);
            Cache::put($cacheKey, 'ok', now('UTC')->addSeconds(15));
            $cacheOk = Cache::get($cacheKey) === 'ok';
            $cacheLatencyMs = (int) round((microtime(true) - $cacheStart) * 1000);
            if (! $cacheOk) {
                $cacheMessage = 'Cache tidak merespons.';
            }
        } catch (\Throwable $exception) {
            $cacheOk = false;
            $cacheMessage = 'Cache bermasalah.';
        }

        $storageOk = is_writable(storage_path());
        $storageMessage = $storageOk ? 'Storage dapat ditulis.' : 'Storage tidak dapat ditulis.';

        $queueDriver = (string) config('queue.default', 'sync');
        $queueStatus = $queueDriver === 'sync' ? 'warn' : 'ok';
        $queueMessage = $queueDriver === 'sync'
            ? 'Queue masih menggunakan driver sync.'
            : 'Queue aktif.';

        $queueBacklog = null;
        $failedJobs = null;
        if ($queueDriver === 'database' && Schema::hasTable('jobs')) {
            $queueBacklog = (int) DB::table('jobs')->count();
        }
        if (Schema::hasTable('failed_jobs')) {
            $failedJobs = (int) DB::table('failed_jobs')->count();
        }

        $tenantCount = (int) DB::table('tenants')->count();
        $userCount = (int) DB::table('users')->count();
        $sessionCount = (int) DB::table('sessions')->count();
        $overdueInvoices = (int) DB::table('invoices')
            ->whereNotNull('due_at')
            ->where('due_at', '<', CarbonImmutable::now('UTC'))
            ->whereNotIn('status', ['paid', 'void'])
            ->count();

        $responseLatencyMs = (int) round((microtime(true) - $start) * 1000);

        $payload = [
            'generated_at' => $generatedAt,
            'checks' => [
                [
                    'key' => 'db',
                    'label' => 'Database',
                    'status' => $dbOk ? 'ok' : 'error',
                    'message' => $dbMessage,
                    'latency_ms' => $dbLatencyMs,
                ],
                [
                    'key' => 'cache',
                    'label' => 'Cache',
                    'status' => $cacheOk ? 'ok' : 'error',
                    'message' => $cacheMessage,
                    'latency_ms' => $cacheLatencyMs,
                ],
                [
                    'key' => 'storage',
                    'label' => 'Storage',
                    'status' => $storageOk ? 'ok' : 'error',
                    'message' => $storageMessage,
                ],
                [
                    'key' => 'queue',
                    'label' => 'Queue',
                    'status' => $queueStatus,
                    'message' => $queueMessage,
                    'backlog' => $queueBacklog,
                    'failed_jobs' => $failedJobs,
                ],
                [
                    'key' => 'response_latency',
                    'label' => 'Response Latency',
                    'status' => 'ok',
                    'message' => 'Latency dihitung dari proses health check.',
                    'latency_ms' => $responseLatencyMs,
                ],
            ],
            'metrics' => [
                'tenant_total' => $tenantCount,
                'user_total' => $userCount,
                'active_sessions' => $sessionCount,
                'overdue_invoices' => $overdueInvoices,
                'response_latency_ms' => $responseLatencyMs,
            ],
        ];

        $this->alertIfUnhealthy($payload);

        return $payload;
    }

    private function alertIfUnhealthy(array $payload): void
    {
        $checks = $payload['checks'] ?? [];
        $hasError = collect($checks)->contains(fn ($check) => ($check['status'] ?? 'ok') === 'error');
        if (! $hasError) {
            return;
        }

        $requestId = app()->bound('request') ? app('request')->header('X-Request-Id') : null;

        Log::warning('platform.health.failure', [
            'event_type' => 'platform.health.failure',
            'request_id' => $requestId,
            'checks' => $checks,
        ]);

        $webhook = (string) config('platform.health_alert_webhook', '');
        if ($webhook !== '') {
            try {
                Http::timeout(2)->post($webhook, [
                    'event' => 'platform.health.failure',
                    'request_id' => $requestId,
                    'checks' => $checks,
                ]);
            } catch (\Throwable $exception) {
                Log::warning('platform.health.alert_failed', [
                    'event_type' => 'platform.health.alert_failed',
                    'request_id' => $requestId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
