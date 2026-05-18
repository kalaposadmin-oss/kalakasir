<?php

namespace App\Services;

use App\Models\SystemHealthEvent;
use App\Models\SystemHealthEventOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SystemHealthEventService
{
    public function __construct(private readonly SystemHealthAlertService $alertService)
    {
    }

    public function record(string $eventName, array $attributes = []): SystemHealthEvent
    {
        return $this->persist($eventName, $attributes, false);
    }

    public function recordOrBump(string $eventName, array $attributes = []): SystemHealthEvent
    {
        return $this->persist($eventName, $attributes, true);
    }

    public function resolve(string $eventId, ?string $reason = null, array $context = []): ?SystemHealthEvent
    {
        $event = SystemHealthEvent::query()->find($eventId);
        if (! $event) {
            return null;
        }

        $payload = is_array($event->payload_json) ? $event->payload_json : [];
        if ($reason !== null && $reason !== '') {
            $payload['resolution'] = ['reason' => $reason] + Arr::only($context, ['actor_id', 'request_id']);
        }

        $event->forceFill([
            'status' => 'resolved',
            'resolved_at' => CarbonImmutable::now('UTC'),
            'payload_json' => $payload,
        ])->save();

        return $event->refresh();
    }

    public function ignore(string $eventId, ?string $reason = null, array $context = []): ?SystemHealthEvent
    {
        $event = SystemHealthEvent::query()->find($eventId);
        if (! $event) {
            return null;
        }

        $payload = is_array($event->payload_json) ? $event->payload_json : [];
        if ($reason !== null && $reason !== '') {
            $payload['ignore'] = ['reason' => $reason] + Arr::only($context, ['actor_id', 'request_id']);
        }

        $event->forceFill([
            'status' => 'ignored',
            'resolved_at' => CarbonImmutable::now('UTC'),
            'payload_json' => $payload,
        ])->save();

        return $event->refresh();
    }

    public function resolveMatching(string $eventName, array $filters = [], ?string $reason = null): int
    {
        $query = SystemHealthEvent::query()
            ->where('event_name', $eventName)
            ->where('status', 'open');

        foreach (['tenant_id', 'branch_id', 'entity_type', 'entity_id', 'dedupe_key'] as $column) {
            if (array_key_exists($column, $filters)) {
                $query->where($column, $filters[$column]);
            }
        }

        $payloadUpdates = [];
        if ($reason !== null && $reason !== '') {
            $payloadUpdates['auto_resolution_reason'] = $reason;
        }

        $count = 0;
        $query->get()->each(function (SystemHealthEvent $event) use (&$count, $payloadUpdates): void {
            $payload = is_array($event->payload_json) ? $event->payload_json : [];
            $event->forceFill([
                'status' => 'resolved',
                'resolved_at' => CarbonImmutable::now('UTC'),
                'payload_json' => array_merge($payload, $payloadUpdates),
            ])->save();
            $count++;
        });

        return $count;
    }

    public function openEvents(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = SystemHealthEvent::query()
            ->with('tenant:id,name')
            ->where('status', $filters['status'] ?? 'open');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['event_name'])) {
            $query->where('event_name', $filters['event_name']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['from'])) {
            $query->where('detected_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('detected_at', '<=', $filters['to']);
        }

        if (! empty($filters['search'])) {
            $needle = trim((string) $filters['search']);
            $query->where(function ($builder) use ($needle): void {
                $builder->where('entity_id', 'like', '%'.$needle.'%')
                    ->orWhere('summary', 'like', '%'.$needle.'%');
            });
        }

        $total = (clone $query)->count();
        $sortBy = (string) ($filters['sort_by'] ?? 'latest');
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $rows = $query
            ->when($sortBy === 'severity', fn ($builder) => $builder->orderByRaw("CASE severity WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END ".$sortDir))
            ->when($sortBy === 'occurrence_count', fn ($builder) => $builder->orderBy('occurrence_count', $sortDir))
            ->when($sortBy === 'latest' || ! in_array($sortBy, ['severity', 'occurrence_count'], true), fn ($builder) => $builder->orderBy('detected_at', $sortDir))
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'events' => $rows->map(fn (SystemHealthEvent $event) => $this->serializeEvent($event))->all(),
            'total' => $total,
        ];
    }

    public function eventStats(array $filters = []): array
    {
        $base = SystemHealthEvent::query()->with('tenant:id,name')->where('status', $filters['status'] ?? 'open');

        if (! empty($filters['tenant_id'])) {
            $base->where('tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['from'])) {
            $base->where('detected_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $base->where('detected_at', '<=', $filters['to']);
        }

        $bySeverity = (clone $base)
            ->select('severity', DB::raw('count(*) as total'))
            ->groupBy('severity')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->severity => (int) $row->total])
            ->all();

        $byTenant = (clone $base)
            ->select('tenant_id', DB::raw('count(*) as total'))
            ->whereNotNull('tenant_id')
            ->groupBy('tenant_id')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'tenant_id' => (string) $row->tenant_id,
                'tenant_name' => optional($row->tenant)->name,
                'total' => (int) $row->total,
            ])
            ->all();

        $latestCritical = (clone $base)
            ->where('severity', 'critical')
            ->orderByDesc('detected_at')
            ->limit(10)
            ->get()
            ->map(fn (SystemHealthEvent $event) => $this->serializeEvent($event))
            ->all();

        return [
            'unresolved_count' => (clone $base)->count(),
            'affected_tenant_count' => (clone $base)->whereNotNull('tenant_id')->distinct('tenant_id')->count('tenant_id'),
            'by_severity' => $bySeverity,
            'by_tenant' => $byTenant,
            'latest_critical_events' => $latestCritical,
            'top_event_names' => (clone $base)
                ->select('event_name', DB::raw('count(*) as total'))
                ->groupBy('event_name')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(fn ($row) => [
                    'event_name' => (string) $row->event_name,
                    'total' => (int) $row->total,
                ])
                ->all(),
            'strict_reader_blocks_today' => (clone $base)
                ->where('event_name', 'strict_reader_blocked')
                ->where('detected_at', '>=', CarbonImmutable::now('UTC')->startOfDay())
                ->count(),
            'sales_cost_mismatch_count' => (clone $base)
                ->whereIn('event_name', ['sales_cost_header_mismatch', 'cross_report_mismatch'])
                ->count(),
            'daily_summary_refresh_failures' => (clone $base)
                ->where('event_name', 'daily_summary_refresh_failed')
                ->count(),
        ];
    }

    public function catalog(): array
    {
        return config('system_health.events', []);
    }

    private function persist(string $eventName, array $attributes, bool $allowBump): SystemHealthEvent
    {
        $definition = $this->definition($eventName);
        $detectedAt = $attributes['detected_at'] ?? CarbonImmutable::now('UTC');
        if (! $detectedAt instanceof CarbonImmutable) {
            $detectedAt = CarbonImmutable::parse($detectedAt, 'UTC');
        }

        $payload = $attributes['payload'] ?? [];
        $summary = (string) ($attributes['summary'] ?? $payload['summary'] ?? $eventName);
        $dedupeKey = (string) ($attributes['dedupe_key'] ?? $this->buildDedupeKey($eventName, $attributes, $payload));
        $severity = strtolower((string) ($attributes['severity'] ?? $definition['severity'] ?? 'medium'));
        $source = (string) ($attributes['source'] ?? $definition['source'] ?? 'application');

        $event = DB::transaction(function () use ($allowBump, $eventName, $summary, $payload, $severity, $source, $detectedAt, $attributes, $dedupeKey): SystemHealthEvent {
            $existing = null;

            if ($allowBump) {
                $existing = SystemHealthEvent::query()
                    ->where('event_name', $eventName)
                    ->where('status', 'open')
                    ->where('dedupe_key', $dedupeKey)
                    ->lockForUpdate()
                    ->first();
            }

            if ($existing) {
                $existing->forceFill([
                    'severity' => $this->maxSeverity((string) $existing->severity, $severity),
                    'summary' => $summary,
                    'payload_json' => $payload,
                    'source' => $source,
                    'detected_at' => $detectedAt,
                    'occurrence_count' => (int) $existing->occurrence_count + 1,
                    'entity_type' => $attributes['entity_type'] ?? $existing->entity_type,
                    'entity_id' => $attributes['entity_id'] ?? $existing->entity_id,
                    'tenant_id' => $attributes['tenant_id'] ?? $existing->tenant_id,
                    'branch_id' => $attributes['branch_id'] ?? $existing->branch_id,
                ])->save();

                $this->insertOccurrence($existing, $summary, $payload, $detectedAt, (int) $existing->occurrence_count);

                return $existing->refresh();
            }

            $event = SystemHealthEvent::query()->create([
                'id' => (string) Str::uuid(),
                'event_name' => $eventName,
                'severity' => $severity,
                'tenant_id' => $attributes['tenant_id'] ?? null,
                'branch_id' => $attributes['branch_id'] ?? null,
                'entity_type' => $attributes['entity_type'] ?? null,
                'entity_id' => $attributes['entity_id'] ?? null,
                'summary' => $summary,
                'payload_json' => $payload,
                'source' => $source,
                'status' => 'open',
                'detected_at' => $detectedAt,
                'first_detected_at' => $detectedAt,
                'occurrence_count' => 1,
                'dedupe_key' => $dedupeKey,
            ]);

            $this->insertOccurrence($event, $summary, $payload, $detectedAt, 1);

            return $event;
        });

        $this->alertService->notifyForEvent($event);

        return $event;
    }

    private function insertOccurrence(SystemHealthEvent $event, string $summary, array $payload, CarbonImmutable $detectedAt, int $occurrenceNumber): void
    {
        SystemHealthEventOccurrence::query()->create([
            'id' => (string) Str::uuid(),
            'system_health_event_id' => $event->id,
            'summary' => $summary,
            'payload_json' => $payload,
            'detected_at' => $detectedAt,
            'occurrence_number' => $occurrenceNumber,
        ]);
    }

    private function definition(string $eventName): array
    {
        return config('system_health.events.'.$eventName, []);
    }

    private function buildDedupeKey(string $eventName, array $attributes, array $payload): string
    {
        return sha1(json_encode([
            'event_name' => $eventName,
            'tenant_id' => $attributes['tenant_id'] ?? null,
            'branch_id' => $attributes['branch_id'] ?? null,
            'entity_type' => $attributes['entity_type'] ?? null,
            'entity_id' => $attributes['entity_id'] ?? null,
            'source' => $attributes['source'] ?? null,
            'context' => Arr::only($payload, ['context', 'scope', 'reason', 'date', 'endpoint']),
        ], JSON_UNESCAPED_SLASHES));
    }

    private function maxSeverity(string $current, string $incoming): string
    {
        $rank = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

        return ($rank[$incoming] ?? 1) >= ($rank[$current] ?? 1) ? $incoming : $current;
    }

    private function serializeEvent(SystemHealthEvent $event): array
    {
        return [
            'event_id' => $event->id,
            'event_name' => $event->event_name,
            'severity' => $event->severity,
            'tenant_id' => $event->tenant_id,
            'tenant_name' => $event->tenant?->name,
            'branch_id' => $event->branch_id,
            'entity_type' => $event->entity_type,
            'entity_id' => $event->entity_id,
            'summary' => $event->summary,
            'payload_json' => $event->payload_json ?? [],
            'source' => $event->source,
            'status' => $event->status,
            'detected_at' => optional($event->detected_at)->toIso8601String(),
            'first_detected_at' => optional($event->first_detected_at)->toIso8601String(),
            'resolved_at' => optional($event->resolved_at)->toIso8601String(),
            'occurrence_count' => (int) $event->occurrence_count,
            'last_alerted_at' => optional($event->last_alerted_at)->toIso8601String(),
        ];
    }
}