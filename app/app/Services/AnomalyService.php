<?php

namespace App\Services;

use App\Models\Anomaly;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class AnomalyService
{
    public function raise(
        string $tenantId,
        string $severity,
        string $type,
        array $payload,
        ?string $referenceId = null
    ): void {
        $fingerprint = md5(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $exists = Anomaly::query()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->whereNull('resolved_at')
            ->whereJsonContains('payload_json->fingerprint', $fingerprint)
            ->exists();

        if ($exists) {
            return;
        }

        Anomaly::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'severity' => $severity,
            'type' => $type,
            'reference_id' => $referenceId,
            'payload_json' => array_merge($payload, ['fingerprint' => $fingerprint]),
            'detected_at' => CarbonImmutable::now('UTC'),
        ]);

        if (str_starts_with($type, 'valuation_') || in_array($severity, ['high', 'critical'], true)) {
            $ops = app(OpsAlertService::class);
            if ($ops->shouldNotifyValuationAlert($severity, $payload)) {
                $ops->sendValuationAlert(
                    tenantId: $tenantId,
                    severity: $severity,
                    type: $type,
                    payload: $payload,
                );
            }
        }
    }
}
