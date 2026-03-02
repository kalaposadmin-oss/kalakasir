<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AnomalyDetectorMetricsService
{
    public function recordRun(string $detector, float $startedAt, bool $success): void
    {
        $runtimeMs = max(0, (int) round((microtime(true) - $startedAt) * 1000));

        Cache::increment($this->key($detector, 'runs_total'));
        Cache::put($this->key($detector, 'last_runtime_ms'), $runtimeMs, now()->addDays(7));
        Cache::put($this->key($detector, 'last_success_at'), now('UTC')->toIso8601String(), now()->addDays(7));

        if (! $success) {
            Cache::increment($this->key($detector, 'errors_total'));
            Cache::put($this->key($detector, 'last_error_at'), now('UTC')->toIso8601String(), now()->addDays(7));
        }
    }

    public function snapshot(array $detectors): array
    {
        $result = [];

        foreach ($detectors as $detector) {
            $result[$detector] = [
                'runs_total' => (int) Cache::get($this->key($detector, 'runs_total'), 0),
                'errors_total' => (int) Cache::get($this->key($detector, 'errors_total'), 0),
                'last_runtime_ms' => (int) Cache::get($this->key($detector, 'last_runtime_ms'), 0),
                'last_success_at' => Cache::get($this->key($detector, 'last_success_at')),
                'last_error_at' => Cache::get($this->key($detector, 'last_error_at')),
            ];
        }

        return $result;
    }

    private function key(string $detector, string $suffix): string
    {
        return 'anomaly:detector:'.$detector.':'.$suffix;
    }
}
