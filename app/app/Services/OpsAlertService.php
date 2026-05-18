<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpsAlertService
{
    public function shouldNotifyValuationAlert(string $severity, array $payload): bool
    {
        $rank = [
            'low' => 1,
            'med' => 2,
            'high' => 3,
            'critical' => 4,
        ];

        $configured = strtolower((string) config('services.ops_alerts.valuation_min_severity', 'med'));
        $minSeverityRank = $rank[$configured] ?? 2;
        $currentRank = $rank[strtolower($severity)] ?? 1;

        if ($currentRank < $minSeverityRank) {
            return false;
        }

        $minCount = max(1, (int) config('services.ops_alerts.valuation_min_count', 1));
        $count = (int) ($payload['count'] ?? $payload['rows'] ?? $payload['affected_rows'] ?? 1);

        return $count >= $minCount;
    }

    public function sendValuationAlert(string $tenantId, string $severity, string $type, array $payload): void
    {
        if (! config('services.ops_alerts.enabled')) {
            return;
        }

        $fingerprint = md5($tenantId.'|'.$severity.'|'.$type.'|'.json_encode($payload));
        $dedupeKey = 'ops-alert:valuation:'.$fingerprint;
        if (Cache::has($dedupeKey)) {
            return;
        }

        Cache::put($dedupeKey, true, 300);

        $message = [
            'source' => 'kalakasir',
            'tenant_id' => $tenantId,
            'severity' => $severity,
            'type' => $type,
            'payload' => $payload,
        ];

        $webhookUrl = (string) config('services.ops_alerts.webhook_url', '');
        if ($webhookUrl === '') {
            Log::warning('ops_alerts.webhook_url kosong', $message);
            return;
        }

        $timeout = max(1, min((int) config('services.ops_alerts.timeout_seconds', 2), 3));
        $token = (string) config('services.ops_alerts.bearer_token', '');

        app()->terminating(static function () use ($webhookUrl, $message, $timeout, $token): void {
            try {
                $request = Http::connectTimeout(1)->timeout($timeout);
                if ($token !== '') {
                    $request = $request->withToken($token);
                }

                $request->post($webhookUrl, $message)->throw();
            } catch (\Throwable $exception) {
                Log::error('gagal kirim ops alert valuation', [
                    'message' => $exception->getMessage(),
                    ...$message,
                ]);
            }
        });
    }
}
