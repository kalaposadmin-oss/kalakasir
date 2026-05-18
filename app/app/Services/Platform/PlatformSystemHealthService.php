<?php

namespace App\Services\Platform;

use App\Services\AuditLogger;
use App\Services\SystemHealthEventService;

class PlatformSystemHealthService
{
    public function __construct(
        private readonly SystemHealthEventService $events,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function list(array $filters, int $page, int $perPage): array
    {
        return $this->events->openEvents($filters, $page, $perPage);
    }

    public function summary(array $filters = []): array
    {
        return $this->events->eventStats($filters);
    }

    public function event(string $eventId): ?array
    {
        $row = \App\Models\SystemHealthEvent::query()
            ->with(['tenant:id,name', 'occurrences' => fn ($query) => $query->orderByDesc('detected_at')])
            ->find($eventId);

        if (! $row) {
            return null;
        }

        return [
            'event_id' => $row->id,
            'event_name' => $row->event_name,
            'severity' => $row->severity,
            'tenant_id' => $row->tenant_id,
            'tenant_name' => $row->tenant?->name,
            'branch_id' => $row->branch_id,
            'entity_type' => $row->entity_type,
            'entity_id' => $row->entity_id,
            'summary' => $row->summary,
            'payload_json' => $row->payload_json ?? [],
            'source' => $row->source,
            'status' => $row->status,
            'detected_at' => optional($row->detected_at)->toIso8601String(),
            'first_detected_at' => optional($row->first_detected_at)->toIso8601String(),
            'resolved_at' => optional($row->resolved_at)->toIso8601String(),
            'occurrence_count' => (int) $row->occurrence_count,
            'last_alerted_at' => optional($row->last_alerted_at)->toIso8601String(),
            'occurrences' => $row->occurrences->map(fn ($occurrence) => [
                'occurrence_id' => $occurrence->id,
                'summary' => $occurrence->summary,
                'payload_json' => $occurrence->payload_json ?? [],
                'detected_at' => optional($occurrence->detected_at)->toIso8601String(),
                'occurrence_number' => (int) $occurrence->occurrence_number,
            ])->all(),
        ];
    }

    public function resolve(string $eventId, array $payload, array $context): array
    {
        $event = $this->events->resolve($eventId, $payload['reason'] ?? null, $context);
        if (! $event) {
            return $this->error('NOT_FOUND', 'System health event tidak ditemukan.', 404);
        }

        $this->logAction('system_health_event_resolved', $eventId, $event->tenant_id, $payload, $context);

        return [
            'status' => 'ok',
            'action' => 'system_health_event_resolved',
            'event_id' => $eventId,
        ];
    }

    public function ignore(string $eventId, array $payload, array $context): array
    {
        $event = $this->events->ignore($eventId, $payload['reason'] ?? null, $context);
        if (! $event) {
            return $this->error('NOT_FOUND', 'System health event tidak ditemukan.', 404);
        }

        $this->logAction('system_health_event_ignored', $eventId, $event->tenant_id, $payload, $context);

        return [
            'status' => 'ok',
            'action' => 'system_health_event_ignored',
            'event_id' => $eventId,
        ];
    }

    private function logAction(string $eventType, string $eventId, ?string $tenantId, array $payload, array $context): void
    {
        if (! $tenantId) {
            return;
        }

        $this->auditLogger->log(
            event: $eventType,
            actorId: $context['actor_id'] ?? null,
            referenceType: 'system_health_event',
            referenceId: $eventId,
            payload: [
                'reason' => $payload['reason'] ?? null,
                'request_id' => $context['request_id'] ?? null,
                'idempotency_key' => $context['idempotency_key'] ?? null,
            ],
            ipAddress: $context['ip_address'] ?? null,
            userAgent: $context['user_agent'] ?? null,
            tenantId: $tenantId,
        );
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