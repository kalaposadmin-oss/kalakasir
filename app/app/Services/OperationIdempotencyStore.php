<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class OperationIdempotencyStore
{
    /**
     * @return array{value: array, replayed: bool}
     */
    public function getOrRemember(string $key, callable $handler): array
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return [
                'value' => $cached,
                'replayed' => true,
            ];
        }

        $lock = Cache::store($this->store())->lock($key.':lock', $this->lockSeconds());

        try {
            $lock->block(3);

            $cached = $this->get($key);
            if ($cached !== null) {
                return [
                    'value' => $cached,
                    'replayed' => true,
                ];
            }

            $value = $handler();
            $this->put($key, $value);

            return [
                'value' => $value,
                'replayed' => false,
            ];
        } catch (LockTimeoutException) {
            $cached = $this->get($key);
            if ($cached !== null) {
                return [
                    'value' => $cached,
                    'replayed' => true,
                ];
            }

            $value = $handler();
            $this->put($key, $value);

            return [
                'value' => $value,
                'replayed' => false,
            ];
        } finally {
            optional($lock)->release();
        }
    }

    private function get(string $key): ?array
    {
        $cached = Cache::store($this->store())->get($key);

        return is_array($cached) ? $cached : null;
    }

    private function put(string $key, array $value): void
    {
        Cache::store($this->store())->put($key, $value, now('UTC')->addMinutes($this->ttlMinutes()));
    }

    private function store(): string
    {
        return config('cache.default');
    }

    private function ttlMinutes(): int
    {
        return max(5, (int) config('services.security.operation_idempotency.ttl_minutes', 60));
    }

    private function lockSeconds(): int
    {
        return max(3, (int) config('services.security.operation_idempotency.lock_seconds', 10));
    }
}