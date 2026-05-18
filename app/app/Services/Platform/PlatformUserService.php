<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use App\Services\AuditLogger;
use App\Services\Platform\PlatformIdempotencyStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformUserService
{
    /**
     * @param array<string, mixed> $filters
     * @return array{users: array<int, mixed>, total: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $query = DB::table('users as u')
            ->leftJoin('tenants as t', 't.id', '=', 'u.tenant_id')
            ->leftJoinSub($this->roleNames(), 'ur', function ($join): void {
                $join->on('ur.user_id', '=', 'u.id');
            })
            ->leftJoinSub($this->lastLogin(), 'll', function ($join): void {
                $join->on('ll.user_id', '=', 'u.id');
            });

        if (! empty($filters['q'])) {
            $needle = trim((string) $filters['q']);
            $query->where(function ($inner) use ($needle): void {
                $inner->where('u.name', 'like', '%'.$needle.'%')
                    ->orWhere('u.email', 'like', '%'.$needle.'%');
            });
        }

        if (! empty($filters['tenant_id'])) {
            $query->where('u.tenant_id', $filters['tenant_id']);
        }

        if (! empty($filters['role'])) {
            $role = strtolower((string) $filters['role']);
            $query->whereRaw('LOWER(ur.role_name) = ?', [$role]);
        }

        if (! empty($filters['status'])) {
            $status = (string) $filters['status'];
            if ($status === 'inactive') {
                $query->whereNotNull('u.deleted_at');
            } else {
                $query->whereNull('u.deleted_at');
            }
        }

        if (! empty($filters['last_login_from'])) {
            $from = CarbonImmutable::parse($filters['last_login_from'], 'UTC')->timestamp;
            $query->where('ll.last_activity', '>=', $from);
        }

        if (! empty($filters['last_login_to'])) {
            $to = CarbonImmutable::parse($filters['last_login_to'], 'UTC')->timestamp;
            $query->where('ll.last_activity', '<=', $to);
        }

        $totalQuery = clone $query;
        $total = (int) $totalQuery->distinct('u.id')->count('u.id');

        $query->select([
            'u.id',
            'u.name',
            'u.email',
            'u.tenant_id',
            'u.deleted_at',
            't.name as tenant_name',
            'ur.role_name',
            'll.last_activity',
        ]);

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $sortColumn = match ($sortBy) {
            'name' => 'u.name',
            'last_login_at' => 'll.last_activity',
            default => 'u.created_at',
        };

        $rows = $query
            ->orderBy($sortColumn, $sortDir === 'asc' ? 'asc' : 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $users = $rows->map(function ($row) {
            $lastLoginAt = null;
            if (! empty($row->last_activity)) {
                $lastLoginAt = CarbonImmutable::createFromTimestamp((int) $row->last_activity, 'UTC')->toIso8601String();
            }

            return [
                'user_id' => $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'phone' => null,
                'tenant_id' => $row->tenant_id,
                'tenant_name' => $row->tenant_name,
                'role' => $row->role_name ? strtolower((string) $row->role_name) : null,
                'status' => $row->deleted_at ? 'inactive' : 'active',
                'last_login_at' => $lastLoginAt,
            ];
        })->all();

        return [
            'users' => $users,
            'total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function detail(string $userId, array $filters): array
    {
        $user = DB::table('users as u')
            ->leftJoin('tenants as t', 't.id', '=', 'u.tenant_id')
            ->leftJoinSub($this->roleNames(), 'ur', function ($join): void {
                $join->on('ur.user_id', '=', 'u.id');
            })
            ->leftJoinSub($this->lastLogin(), 'll', function ($join): void {
                $join->on('ll.user_id', '=', 'u.id');
            })
            ->where('u.id', $userId)
            ->first([
                'u.id',
                'u.name',
                'u.email',
                'u.tenant_id',
                'u.created_at',
                'u.deleted_at',
                't.name as tenant_name',
                'ur.role_name',
                'll.last_activity',
            ]);

        if (! $user) {
            return [
                'user' => [
                    'user_id' => $userId,
                    'name' => null,
                    'email' => null,
                ],
                'tenants' => [],
            ];
        }

        $lastLoginAt = null;
        if (! empty($user->last_activity)) {
            $lastLoginAt = CarbonImmutable::createFromTimestamp((int) $user->last_activity, 'UTC')->toIso8601String();
        }

        $createdAt = null;
        if (! empty($user->created_at)) {
            $createdAt = CarbonImmutable::parse($user->created_at, 'UTC')->toIso8601String();
        }

        return [
            'user' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role_name ? strtolower((string) $user->role_name) : null,
                'status' => $user->deleted_at ? 'inactive' : 'active',
                'last_login_at' => $lastLoginAt,
                'created_at' => $createdAt,
            ],
            'tenants' => [[
                'tenant_id' => $user->tenant_id,
                'name' => $user->tenant_name,
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{audit_logs: array<int, mixed>, total: int}
     */
    public function auditLogs(string $userId, array $filters, int $page, int $perPage): array
    {
        $query = DB::table('audit_logs')
            ->where('actor_id', $userId);

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['q'])) {
            $needle = trim((string) $filters['q']);
            $query->where('event_type', 'like', '%'.$needle.'%');
        }

        $total = (int) $query->count();

        $rows = $query
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'id',
                'tenant_id',
                'event_type',
                'reference_type',
                'reference_id',
                'payload_json',
                'created_at',
            ])
            ->map(function ($row) {
                $payload = (array) $row;
                if (! empty($payload['created_at'])) {
                    $payload['created_at'] = CarbonImmutable::parse($payload['created_at'], 'UTC')->toIso8601String();
                }

                return $payload;
            })
            ->all();

        return [
            'audit_logs' => $rows,
            'total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function action(string $userId, string $action, array $payload, array $context): array
    {
        $idempotencyKey = $context['idempotency_key'] ?? null;

        return $this->runIdempotent($action, $userId, $idempotencyKey, function () use ($userId, $action, $payload, $context): array {
            $user = DB::table('users')->where('id', $userId)->first();
            if (! $user) {
                return $this->error('NOT_FOUND', 'User tidak ditemukan.', 404);
            }

            return match ($action) {
                'user_reset_password' => $this->resetPassword($userId, $user->tenant_id, $payload, $context),
                'user_force_logout' => $this->forceLogout($userId, $user->tenant_id, $payload, $context),
                'user_unlock' => $this->unlockUser($userId, $user->tenant_id, $payload, $context),
                default => $this->error('BAD_REQUEST', 'Action tidak dikenal.', 400),
            };
        });
    }

    private function resetPassword(string $userId, string $tenantId, array $payload, array $context): array
    {
        DB::table('users')
            ->where('id', $userId)
            ->update([
                'password' => Hash::make(Str::random(16)),
                'updated_at' => now('UTC'),
                'failed_login_attempts' => 0,
            ]);

        $this->logAction('user_reset_password', $tenantId, $userId, $payload, $context);

        return [
            'status' => 'ok',
            'action' => 'user_reset_password',
            'user_id' => $userId,
        ];
    }

    private function forceLogout(string $userId, string $tenantId, array $payload, array $context): array
    {
        DB::table('sessions')
            ->where('user_id', $userId)
            ->delete();

        $this->logAction('user_force_logout', $tenantId, $userId, $payload, $context);

        return [
            'status' => 'ok',
            'action' => 'user_force_logout',
            'user_id' => $userId,
        ];
    }

    private function unlockUser(string $userId, string $tenantId, array $payload, array $context): array
    {
        DB::table('users')
            ->where('id', $userId)
            ->update([
                'failed_login_attempts' => 0,
                'updated_at' => now('UTC'),
            ]);

        $this->logAction('user_unlock', $tenantId, $userId, $payload, $context);

        return [
            'status' => 'ok',
            'action' => 'user_unlock',
            'user_id' => $userId,
        ];
    }

    private function logAction(string $action, string $tenantId, string $userId, array $payload, array $context): void
    {
        $logger = app(AuditLogger::class);
        $logger->log(
            event: $action,
            actorId: $context['actor_id'] ?? null,
            referenceType: 'user',
            referenceId: $userId,
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

    private function runIdempotent(string $action, string $targetId, ?string $idempotencyKey, callable $handler): array
    {
        if (! $idempotencyKey) {
            return $handler();
        }

        $cacheKey = 'platform:'.$action.':'.$targetId.':'.$idempotencyKey;

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

    private function roleNames()
    {
        return DB::table('user_roles as ur')
            ->join('roles as r', function ($join): void {
                $join->on('ur.role_id', '=', 'r.id')
                    ->on('ur.tenant_id', '=', 'r.tenant_id');
            })
            ->select('ur.user_id', DB::raw('min(r.name) as role_name'))
            ->groupBy('ur.user_id');
    }

    private function lastLogin()
    {
        return DB::table('sessions')
            ->select('user_id', DB::raw('max(last_activity) as last_activity'))
            ->groupBy('user_id');
    }
}
