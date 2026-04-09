<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anomaly;
use App\Services\AuditLogger;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AnomalyController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'severity' => ['nullable', 'string', 'max:32'],
            'type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
            'actor_id' => ['nullable', 'uuid'],
            'resolved' => ['nullable', 'in:0,1'],
            'preset' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);
        [$startAt, $endExclusive, $rangeMeta] = $this->resolveRange(
            $validated['preset'] ?? 'last7',
            $validated['start_date'] ?? $validated['from'] ?? null,
            $validated['end_date'] ?? $validated['to'] ?? null
        );

        $query = Anomaly::query()
            ->where('tenant_id', $tenantId)
            ->when(isset($validated['severity']), fn ($q) => $q->where('severity', $validated['severity']))
            ->when(isset($validated['type']), fn ($q) => $q->where('type', $validated['type']))
            ->when(isset($validated['reference_id']), fn ($q) => $q->where('reference_id', $validated['reference_id']))
            ->when(isset($validated['branch_id']), fn ($q) => $q->where('payload_json->branch_id', $validated['branch_id']))
            ->when(isset($validated['location_id']), fn ($q) => $q->where('payload_json->location_id', $validated['location_id']))
            ->when(isset($validated['resolved']), function ($q) use ($validated) {
                if ((string) $validated['resolved'] === '1') {
                    $q->whereNotNull('resolved_at');
                } else {
                    $q->whereNull('resolved_at');
                }
            })
            ->where('detected_at', '>=', $startAt)
            ->where('detected_at', '<', $endExclusive)
            ->when(isset($validated['actor_id']), function ($q) use ($validated) {
                $actorId = (string) $validated['actor_id'];
                $q->where(function ($inner) use ($actorId) {
                    $inner->where('resolved_by', $actorId)
                        ->orWhereJsonContains('payload_json->top_actor_id', $actorId)
                        ->orWhereJsonContains('payload_json->actor_id', $actorId);
                });
            })
            ->orderByDesc('detected_at')
            ->limit($limit);

        $rows = $query->get([
            'id',
            'severity',
            'type',
            'reference_id',
            'payload_json',
            'detected_at',
            'resolved_at',
            'resolved_by',
        ]);

        return response()->json([
            'count' => $rows->count(),
            'range' => $rangeMeta,
            'anomalies' => $rows,
        ]);
    }

    private function resolveRange(string $preset, ?string $startDate, ?string $endDate): array
    {
        $timezone = 'Asia/Jakarta';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        [$startLocal, $endLocal, $label] = match ($preset) {
            'today' => [$today, $today, 'Hari Ini'],
            'yesterday' => [$today->subDay(), $today->subDay(), 'Kemarin'],
            'last30' => [$today->subDays(29), $today, '30 Hari Terakhir'],
            'thisMonth' => [$today->startOfMonth(), $today->endOfMonth()->startOfDay(), 'Bulan Ini'],
            'lastMonth' => [
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth()->startOfDay(),
                'Bulan Lalu',
            ],
            'custom' => $this->resolveCustom($startDate, $endDate, $today),
            default => [$today->subDays(6), $today, '7 Hari Terakhir'],
        };

        $days = $startLocal->diffInDays($endLocal) + 1;
        $limited = false;
        if ($days > 90) {
            $endLocal = $startLocal->addDays(89);
            $limited = true;
        }

        return [
            $startLocal->setTimezone('UTC'),
            $endLocal->addDay()->startOfDay()->setTimezone('UTC'),
            [
                'preset' => $preset,
                'start_date' => $startLocal->toDateString(),
                'end_date' => $endLocal->toDateString(),
                'label' => $label,
                'timezone' => 'Asia/Jakarta',
                'limited' => $limited,
                'limit_days' => 90,
            ],
        ];
    }

    private function resolveCustom(?string $startDate, ?string $endDate, CarbonImmutable $fallback): array
    {
        $timezone = 'Asia/Jakarta';
        $startLocal = CarbonImmutable::parse($startDate ?? $fallback->toDateString(), $timezone)->startOfDay();
        $endLocal = CarbonImmutable::parse($endDate ?? $startDate ?? $fallback->toDateString(), $timezone)->startOfDay();

        if ($endLocal < $startLocal) {
            [$startLocal, $endLocal] = [$endLocal, $startLocal];
        }

        return [$startLocal, $endLocal, 'Periode Kustom'];
    }

    public function resolve(Request $request, string $anomaly)
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $row = Anomaly::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $anomaly)
            ->first();

        if (! $row) {
            return response()->json([
                'code' => 'ANOMALY_NOT_FOUND',
                'message' => 'Anomali tidak ditemukan.',
                'details' => [],
            ], 404);
        }

        if ($row->resolved_at) {
            return response()->json([
                'status' => 'already_resolved',
            ]);
        }

        $payload = is_array($row->payload_json) ? $row->payload_json : [];
        if (! empty($validated['note'])) {
            $payload['resolved_note'] = $validated['note'];
        }

        $resolvedAt = CarbonImmutable::now('UTC');

        $row->update([
            'payload_json' => $payload,
            'resolved_at' => $resolvedAt,
            'resolved_by' => $request->user()?->id,
        ]);

        app(AuditLogger::class)->log(
            event: 'anomaly_resolved',
            actorId: $request->user()?->id,
            referenceType: 'anomaly',
            referenceId: (string) $row->id,
            payload: [
                'type' => (string) $row->type,
                'severity' => (string) $row->severity,
                'reference_id' => $row->reference_id,
                'branch_id' => $payload['branch_id'] ?? null,
                'location_id' => $payload['location_id'] ?? null,
                'resolved_note' => $payload['resolved_note'] ?? null,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            tenantId: $tenantId,
            occurredAt: $resolvedAt,
        );

        return response()->json([
            'status' => 'resolved',
        ]);
    }
}
