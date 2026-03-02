<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class ValuationRebuildStateService
{
    public function lock(string $tenantId, int $seconds = 1800, array $meta = []): void
    {
        $status = [
            'status' => 'running',
            'started_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'finished_at' => null,
            'message' => null,
            'request_id' => $meta['request_id'] ?? null,
            'actor_id' => $meta['actor_id'] ?? null,
            'trigger' => $meta['trigger'] ?? null,
            'tenant_id' => $tenantId,
        ];

        Cache::put($this->lockKey($tenantId), true, $seconds);
        Cache::put($this->metaKey($tenantId), $status, $seconds * 2);

        if (! empty($status['request_id'])) {
            Cache::put($this->requestKey((string) $status['request_id']), $status, 60 * 60 * 24 * 7);
        }
    }

    public function unlock(string $tenantId, bool $success = true, ?string $message = null, array $meta = []): void
    {
        Cache::forget($this->lockKey($tenantId));

        $previous = $this->meta($tenantId);

        $status = [
            'status' => $success ? 'ok' : 'failed',
            'started_at' => $previous['started_at'] ?? null,
            'finished_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'message' => $message,
            'request_id' => $meta['request_id'] ?? ($previous['request_id'] ?? null),
            'actor_id' => $meta['actor_id'] ?? ($previous['actor_id'] ?? null),
            'trigger' => $meta['trigger'] ?? ($previous['trigger'] ?? null),
            'tenant_id' => $tenantId,
        ];

        Cache::put($this->metaKey($tenantId), $status, 60 * 60 * 24 * 7);
        if (! empty($status['request_id'])) {
            Cache::put($this->requestKey((string) $status['request_id']), $status, 60 * 60 * 24 * 7);
        }
    }

    public function isLocked(string $tenantId): bool
    {
        return (bool) Cache::get($this->lockKey($tenantId), false);
    }

    public function markCancelled(string $tenantId, ?string $message = null, array $meta = []): void
    {
        Cache::forget($this->lockKey($tenantId));

        $previous = $this->meta($tenantId);
        $status = [
            'status' => 'cancelled',
            'started_at' => $previous['started_at'] ?? null,
            'finished_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'message' => $message,
            'request_id' => $meta['request_id'] ?? ($previous['request_id'] ?? null),
            'actor_id' => $meta['actor_id'] ?? ($previous['actor_id'] ?? null),
            'trigger' => $meta['trigger'] ?? ($previous['trigger'] ?? null),
            'tenant_id' => $tenantId,
        ];

        Cache::put($this->metaKey($tenantId), $status, 60 * 60 * 24 * 7);
        if (! empty($status['request_id'])) {
            Cache::put($this->requestKey((string) $status['request_id']), $status, 60 * 60 * 24 * 7);
        }
    }

    public function meta(string $tenantId): array
    {
        return Cache::get($this->metaKey($tenantId), [
            'status' => 'idle',
            'started_at' => null,
            'finished_at' => null,
            'message' => null,
            'request_id' => null,
            'actor_id' => null,
            'trigger' => null,
        ]);
    }

    private function lockKey(string $tenantId): string
    {
        return 'valuation:rebuild:lock:'.$tenantId;
    }

    private function metaKey(string $tenantId): string
    {
        return 'valuation:rebuild:meta:'.$tenantId;
    }

    private function requestKey(string $requestId): string
    {
        return 'valuation:rebuild:request:'.$requestId;
    }
}
