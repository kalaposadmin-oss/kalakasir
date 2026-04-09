<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformPlanChangeService
{
    /**
     * @param array<string, mixed> $filters
     * @return array{plan_changes: array<int, mixed>, total: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $query = DB::table('subscription_audit_logs');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['mode'])) {
            $query->where('change_mode', $filters['mode']);
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
                $inner->where('reason', 'like', '%'.$needle.'%')
                    ->orWhere('old_plan', 'like', '%'.$needle.'%')
                    ->orWhere('new_plan', 'like', '%'.$needle.'%')
                    ->orWhere('request_id', 'like', '%'.$needle.'%');
            });
        }

        $total = (int) $query->count();

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $sortColumn = $sortBy === 'mode' ? 'change_mode' : 'created_at';

        $rows = $query
            ->orderBy($sortColumn, $sortDir === 'asc' ? 'asc' : 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'id',
                'tenant_id',
                'old_plan',
                'new_plan',
                'reason',
                'change_mode',
                'effective_at',
                'proration_amount',
                'request_id',
                'created_at',
            ]);

        $planChanges = $rows->map(function ($row) {
            $effectiveAt = null;
            if (! empty($row->effective_at)) {
                $effectiveAt = CarbonImmutable::parse($row->effective_at, 'UTC')->toIso8601String();
            }

            $createdAt = null;
            if (! empty($row->created_at)) {
                $createdAt = CarbonImmutable::parse($row->created_at, 'UTC')->toIso8601String();
            }

            return [
                'id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'old_plan' => $row->old_plan,
                'new_plan' => $row->new_plan,
                'mode' => $row->change_mode,
                'effective_at' => $effectiveAt,
                'proration_amount' => $row->proration_amount,
                'reason' => $row->reason,
                'request_id' => $row->request_id,
                'created_at' => $createdAt,
            ];
        })->all();

        return [
            'plan_changes' => $planChanges,
            'total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $query = DB::table('subscription_audit_logs');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['mode'])) {
            $query->where('change_mode', $filters['mode']);
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
                $inner->where('reason', 'like', '%'.$needle.'%')
                    ->orWhere('old_plan', 'like', '%'.$needle.'%')
                    ->orWhere('new_plan', 'like', '%'.$needle.'%')
                    ->orWhere('request_id', 'like', '%'.$needle.'%');
            });
        }

        $filename = 'plan-changes-'.now('UTC')->format('Y-m-d').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id',
                'created_at',
                'tenant_id',
                'old_plan',
                'new_plan',
                'mode',
                'actor_id',
                'reason',
                'request_id',
            ]);

            foreach ($query->orderByDesc('created_at')->cursor() as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->created_at,
                    $row->tenant_id,
                    $row->old_plan,
                    $row->new_plan,
                    $row->change_mode,
                    $row->actor_id,
                    $row->reason,
                    $row->request_id,
                ]);
            }
            fclose($out);
        }, $filename, $headers);
    }
}
