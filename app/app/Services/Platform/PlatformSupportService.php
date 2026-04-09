<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlatformSupportService
{
    /**
     * @param array<string, mixed> $filters
     * @return array{actions: array<int, mixed>, total: int}
     */
    public function actions(array $filters, int $page, int $perPage): array
    {
        $query = DB::table('audit_logs');

        $allowedActionTypes = [
            'tenant_suspended',
            'tenant_unsuspended',
            'tenant_impersonated',
            'tenant_impersonation_ended',
            'tenant_force_logout',
            'tenant_reset_owner',
            'tenant_unlock',
            'user_reset_password',
            'user_force_logout',
            'user_unlock',
            'anomaly_resolved',
        ];

        $query->whereIn('event_type', $allowedActionTypes);

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (! empty($filters['action_type'])) {
            $query->where('event_type', $filters['action_type']);
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
                    ->orWhere('reference_id', 'like', '%'.$needle.'%');
            });
        }

        $total = (int) $query->count();

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $sortColumn = $sortBy === 'action_type' ? 'event_type' : 'created_at';

        $rows = $query
            ->orderBy($sortColumn, $sortDir === 'asc' ? 'asc' : 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'id',
                'tenant_id',
                'actor_id',
                'event_type',
                'reference_id',
                'payload_json',
                'created_at',
            ]);

        $actions = $rows->map(function ($row) {
            $payload = is_array($row->payload_json)
                ? $row->payload_json
                : json_decode((string) $row->payload_json, true);

            $payload = $payload ?: [];

            $createdAt = null;
            if (! empty($row->created_at)) {
                $createdAt = CarbonImmutable::parse($row->created_at, 'UTC')->toIso8601String();
            }

            return [
                'action_id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'actor_id' => $row->actor_id,
                'action_type' => $row->event_type,
                'reference_id' => $row->reference_id,
                'reason' => $payload['reason'] ?? null,
                'request_id' => $payload['request_id'] ?? null,
                'created_at' => $createdAt,
            ];
        })->all();

        return [
            'actions' => $actions,
            'total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function search(array $filters): array
    {
        $needle = trim((string) ($filters['q'] ?? ''));
        if ($needle === '') {
            return ['matches' => []];
        }

        $matches = [];

        $tenants = DB::table('tenants')
            ->where('name', 'like', '%'.$needle.'%')
            ->orWhere('id', 'like', '%'.$needle.'%')
            ->limit(5)
            ->get(['id', 'name']);

        foreach ($tenants as $tenant) {
            $matches[] = [
                'type' => 'tenant',
                'id' => $tenant->id,
                'label' => $tenant->name,
            ];
        }

        $users = DB::table('users')
            ->where('email', 'like', '%'.$needle.'%')
            ->orWhere('name', 'like', '%'.$needle.'%')
            ->limit(5)
            ->get(['id', 'email', 'name']);

        foreach ($users as $user) {
            $matches[] = [
                'type' => 'user',
                'id' => $user->id,
                'label' => $user->email ?: $user->name,
            ];
        }

        $invoices = DB::table('invoices')
            ->where('number', 'like', '%'.$needle.'%')
            ->orWhere('id', 'like', '%'.$needle.'%')
            ->limit(5)
            ->get(['id', 'number']);

        foreach ($invoices as $invoice) {
            $matches[] = [
                'type' => 'invoice',
                'id' => $invoice->id,
                'label' => $invoice->number,
            ];
        }

        return [
            'matches' => $matches,
        ];
    }
}
