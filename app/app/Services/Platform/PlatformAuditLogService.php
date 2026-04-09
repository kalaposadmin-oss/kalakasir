<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformAuditLogService
{
    /**
     * @param array<string, mixed> $filters
     * @return array{audit_logs: array<int, mixed>, total: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $query = DB::table('audit_logs');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (! empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['q'])) {
            $needle = trim((string) $filters['q']);
            $query->where(function ($inner) use ($needle): void {
                $inner->where('event_type', 'like', '%'.$needle.'%')
                    ->orWhere('reference_type', 'like', '%'.$needle.'%')
                    ->orWhere('reference_id', 'like', '%'.$needle.'%');
            });
        }

        $total = (int) $query->count();

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $sortColumn = $sortBy === 'event_type' ? 'event_type' : 'created_at';

        $rows = $query
            ->orderBy($sortColumn, $sortDir === 'asc' ? 'asc' : 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'id',
                'tenant_id',
                'actor_id',
                'event_type',
                'reference_type',
                'reference_id',
                'payload_json',
                'created_at',
            ]);

        $logs = $rows->map(function ($row) {
            $payload = is_array($row->payload_json)
                ? $row->payload_json
                : json_decode((string) $row->payload_json, true);

            $createdAt = null;
            if (! empty($row->created_at)) {
                $createdAt = CarbonImmutable::parse($row->created_at, 'UTC')->toIso8601String();
            }

            return [
                'id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'actor_id' => $row->actor_id,
                'event_type' => $row->event_type,
                'reference_type' => $row->reference_type,
                'reference_id' => $row->reference_id,
                'payload_json' => $payload ?: [],
                'created_at' => $createdAt,
            ];
        })->all();

        return [
            'audit_logs' => $logs,
            'total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $query = DB::table('audit_logs');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (! empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['q'])) {
            $needle = trim((string) $filters['q']);
            $query->where(function ($inner) use ($needle): void {
                $inner->where('event_type', 'like', '%'.$needle.'%')
                    ->orWhere('reference_type', 'like', '%'.$needle.'%')
                    ->orWhere('reference_id', 'like', '%'.$needle.'%');
            });
        }

        $filename = 'audit-logs-'.now('UTC')->format('Y-m-d').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id',
                'created_at',
                'event_type',
                'actor_id',
                'tenant_id',
                'reference_type',
                'reference_id',
                'request_id',
                'reason',
            ]);

            foreach ($query->orderByDesc('created_at')->cursor() as $row) {
                $payload = is_array($row->payload_json)
                    ? $row->payload_json
                    : json_decode((string) $row->payload_json, true);

                $payload = $payload ?: [];

                fputcsv($out, [
                    $row->id,
                    $row->created_at,
                    $row->event_type,
                    $row->actor_id,
                    $row->tenant_id,
                    $row->reference_type,
                    $row->reference_id,
                    $payload['request_id'] ?? null,
                    $payload['reason'] ?? null,
                ]);
            }
            fclose($out);
        }, $filename, $headers);
    }
}
