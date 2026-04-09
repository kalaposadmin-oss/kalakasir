<?php

namespace App\Services\Platform;

use App\Services\AuditLogger;
use App\Services\Platform\PlatformIdempotencyStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlatformAnomalyService
{
    /**
     * @param array<string, mixed> $filters
     * @return array{anomalies: array<int, mixed>, total: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $query = DB::table('anomalies');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['resolved'])) {
            if ($filters['resolved'] === 'true') {
                $query->whereNotNull('resolved_at');
            } else {
                $query->whereNull('resolved_at');
            }
        }

        if (! empty($filters['from'])) {
            $query->where('detected_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('detected_at', '<=', $filters['to']);
        }

        $total = (int) $query->count();

        $sortBy = $filters['sort_by'] ?? 'detected_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $sortColumn = $sortBy === 'severity' ? 'severity' : 'detected_at';

        $rows = $query
            ->orderBy($sortColumn, $sortDir === 'asc' ? 'asc' : 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'id',
                'tenant_id',
                'severity',
                'type',
                'reference_id',
                'payload_json',
                'detected_at',
                'resolved_at',
                'resolved_by',
            ]);

        $anomalies = $rows->map(function ($row) {
            $payload = is_array($row->payload_json)
                ? $row->payload_json
                : json_decode((string) $row->payload_json, true);

            $detectedAt = null;
            if (! empty($row->detected_at)) {
                $detectedAt = CarbonImmutable::parse($row->detected_at, 'UTC')->toIso8601String();
            }

            $resolvedAt = null;
            if (! empty($row->resolved_at)) {
                $resolvedAt = CarbonImmutable::parse($row->resolved_at, 'UTC')->toIso8601String();
            }

            return [
                'anomaly_id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'severity' => $row->severity,
                'type' => $row->type,
                'reference_id' => $row->reference_id,
                'payload_json' => $payload ?: [],
                'detected_at' => $detectedAt,
                'resolved_at' => $resolvedAt,
                'resolved_by' => $row->resolved_by,
            ];
        })->all();

        return [
            'anomalies' => $anomalies,
            'total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function resolve(string $anomalyId, array $payload, array $context): array
    {
        $idempotencyKey = $context['idempotency_key'] ?? null;

        return $this->runIdempotent($anomalyId, $idempotencyKey, function () use ($anomalyId, $payload, $context): array {
            $row = DB::table('anomalies')->where('id', $anomalyId)->first();
            if (! $row) {
                return $this->error('NOT_FOUND', 'Anomali tidak ditemukan.', 404);
            }

            if (! empty($row->resolved_at)) {
                return $this->error('CONFLICT', 'Anomali sudah resolved.', 409);
            }

            $payloadJson = is_array($row->payload_json)
                ? $row->payload_json
                : json_decode((string) $row->payload_json, true);

            $payloadJson = $payloadJson ?: [];
            if (! empty($payload['reason'])) {
                $payloadJson['resolved_reason'] = $payload['reason'];
            }

            DB::table('anomalies')
                ->where('id', $anomalyId)
                ->update([
                    'payload_json' => $payloadJson,
                    'resolved_at' => CarbonImmutable::now('UTC'),
                    'resolved_by' => $context['actor_id'] ?? null,
                ]);

            $this->logAction($row->tenant_id, $anomalyId, $payload, $context);

            return [
                'status' => 'ok',
                'action' => 'anomaly_resolved',
                'anomaly_id' => $anomalyId,
            ];
        });
    }

    private function logAction(string $tenantId, string $anomalyId, array $payload, array $context): void
    {
        $logger = app(AuditLogger::class);
        $logger->log(
            event: 'anomaly_resolved',
            actorId: $context['actor_id'] ?? null,
            referenceType: 'anomaly',
            referenceId: $anomalyId,
            payload: [
                'reason' => $payload['reason'] ?? null,
                'request_id' => $context['request_id'] ?? null,
                'idempotency_key' => $context['idempotency_key'] ?? null,
            ],
            ipAddress: $context['ip_address'] ?? null,
            userAgent: $context['user_agent'] ?? null,
            tenantId: $tenantId
        );
    }

    private function runIdempotent(string $targetId, ?string $idempotencyKey, callable $handler): array
    {
        if (! $idempotencyKey) {
            return $handler();
        }

        $cacheKey = 'platform:anomaly_resolved:'.$targetId.':'.$idempotencyKey;

        return app(PlatformIdempotencyStore::class)->remember($cacheKey, $handler);
    }

    private function error(string $code, string $message, int $status): array
    {
        return [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => [],
            ],
            'status' => $status,
        ];
    }
}
