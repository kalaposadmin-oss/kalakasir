<?php

namespace App\Services\Platform;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PlatformIdempotencyStore
{
    public function get(string $key): ?array
    {
        $cached = $this->store()->get($key);

        return is_array($cached) ? $cached : null;
    }

    public function put(string $key, array $value): void
    {
        $this->store()->put($key, $value, now('UTC')->addMinutes($this->ttlMinutes()));
    }

    /**
     * @param callable(): array $handler
     */
    public function remember(string $key, callable $handler): array
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $lockKey = $key.':lock';
        $lock = $this->store()->lock($lockKey, $this->lockSeconds());

        try {
            $lock->block(3);
            $cached = $this->get($key);
            if ($cached !== null) {
                return $cached;
            }

            $result = $handler();
            $this->put($key, $result);

            return $result;
        } catch (LockTimeoutException) {
            $cached = $this->get($key);
            if ($cached !== null) {
                return $cached;
            }

            $result = $handler();
            $this->put($key, $result);

            return $result;
        } finally {
            optional($lock)->release();
        }
    }

    private function store()
    {
        $store = $this->resolveStore();

        return Cache::store($store);
    }

    private function resolveStore(): string
    {
        if (app()->environment('production')) {
            $redisConfigured = is_array(config('cache.stores.redis'));
            if ($redisConfigured) {
                return 'redis';
            }

            Log::warning('Platform idempotency store fallback to default cache.', [
                'event_type' => 'platform.idempotency.cache_fallback',
                'cache_default' => config('cache.default'),
            ]);
        }

        return config('cache.default');
    }

    private function ttlMinutes(): int
    {
        return max(5, (int) config('platform.idempotency_ttl_minutes', 60));
    }

    private function lockSeconds(): int
    {
        return max(3, (int) config('platform.idempotency_lock_seconds', 10));
    }
}
