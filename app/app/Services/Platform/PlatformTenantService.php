<?php

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use App\Services\AuditLogger;
use App\Services\Platform\PlatformIdempotencyStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformTenantService
{
    /**
     * @param array<string, mixed> $filters
     * @return array{tenants: array<int, mixed>, total: int}
     */
    public function list(array $filters, int $page, int $perPage): array
    {
        $query = DB::table('tenants as t')
            ->leftJoinSub($this->currentSubscriptions(), 'sub', function ($join): void {
                $join->on('sub.tenant_id', '=', 't.id');
            })
            ->leftJoinSub($this->userCounts(), 'uc', function ($join): void {
                $join->on('uc.tenant_id', '=', 't.id');
            })
            ->leftJoinSub($this->branchCounts(), 'bc', function ($join): void {
                $join->on('bc.tenant_id', '=', 't.id');
            })
            ->leftJoinSub($this->lastActivity(), 'la', function ($join): void {
                $join->on('la.tenant_id', '=', 't.id');
            })
            ->leftJoinSub($this->overdueInvoices(), 'od', function ($join): void {
                $join->on('od.tenant_id', '=', 't.id');
            });

        if (! empty($filters['q'])) {
            $query->where('t.name', 'like', '%'.trim((string) $filters['q']).'%');
        }

        if (! empty($filters['plan'])) {
            $query->where('t.plan', $filters['plan']);
        }

        if (! empty($filters['created_from'])) {
            $query->where('t.created_at', '>=', $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->where('t.created_at', '<=', $filters['created_to']);
        }

        if (! empty($filters['status'])) {
            $status = (string) $filters['status'];
            if ($status === 'suspended') {
                $query->whereIn('sub.status', ['suspended', 'canceled']);
            } else {
                $query->where(function ($inner): void {
                    $inner->whereNull('sub.status')
                        ->orWhereNotIn('sub.status', ['suspended', 'canceled']);
                });
            }
        }

        if (! empty($filters['health'])) {
            $health = (string) $filters['health'];
            if ($health === 'billing_issue') {
                $query->where('od.overdue_count', '>', 0);
            } elseif ($health === 'suspended') {
                $query->where('sub.status', '=', 'suspended');
            } elseif ($health === 'inactive') {
                $query->where('sub.status', '=', 'canceled');
            } else {
                $query->where(function ($inner): void {
                    $inner->whereNull('od.overdue_count')
                        ->orWhere('od.overdue_count', '=', 0);
                });
            }
        }

        $totalQuery = clone $query;
        $total = (int) $totalQuery->distinct('t.id')->count('t.id');

        $query->select([
            't.id',
            't.name',
            't.plan',
            't.created_at',
            'sub.status as subscription_status',
            'sub.current_period_end',
            'uc.user_count',
            'bc.outlet_count',
            'la.last_activity',
            'od.overdue_count',
        ]);

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $sortColumn = match ($sortBy) {
            'name' => 't.name',
            'last_active_at' => 'la.last_activity',
            'user_count' => 'uc.user_count',
            default => 't.created_at',
        };

        $rows = $query
            ->orderBy($sortColumn, $sortDir === 'asc' ? 'asc' : 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $tenants = $rows->map(function ($row) {
            $subscriptionStatus = strtolower((string) ($row->subscription_status ?? ''));
            $status = in_array($subscriptionStatus, ['suspended', 'canceled'], true) ? 'suspended' : 'active';
            $health = ($row->overdue_count ?? 0) > 0 ? 'billing_issue' : ($status === 'suspended' ? 'suspended' : 'healthy');

            $lastActiveAt = null;
            if (! empty($row->last_activity)) {
                $lastActiveAt = CarbonImmutable::createFromTimestamp((int) $row->last_activity, 'UTC')->toIso8601String();
            }

            $planExpiresAt = null;
            if (! empty($row->current_period_end)) {
                $planExpiresAt = CarbonImmutable::parse($row->current_period_end, 'UTC')->toIso8601String();
            }

            return [
                'tenant_id' => $row->id,
                'name' => $row->name,
                'plan' => $row->plan ?? 'S',
                'status' => $status,
                'health' => $health,
                'created_at' => $row->created_at ? CarbonImmutable::parse($row->created_at, 'UTC')->toIso8601String() : null,
                'last_active_at' => $lastActiveAt,
                'user_count' => (int) ($row->user_count ?? 0),
                'outlet_count' => (int) ($row->outlet_count ?? 0),
                'plan_expires_at' => $planExpiresAt,
                'storage_usage' => null,
            ];
        })->all();

        return [
            'tenants' => $tenants,
            'total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function detail(string $tenantId, array $filters): array
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        if (! $tenant) {
            return [
                'tenant' => [
                    'tenant_id' => $tenantId,
                    'name' => null,
                    'status' => null,
                    'plan' => null,
                ],
                'usage_summary' => [
                    'orders' => 0,
                    'revenue' => 0,
                ],
                'user_count' => 0,
                'owner_exists' => false,
                'users_preview' => [],
                'recent_events' => [],
            ];
        }

        $subscription = DB::table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->where('is_current', true)
            ->first();

        $subscriptionStatus = strtolower((string) ($subscription->status ?? ''));
        $status = in_array($subscriptionStatus, ['suspended', 'canceled'], true) ? 'suspended' : 'active';

        [$startAt, $endAt] = $this->resolveRange($filters);
        $sales = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startAt, $endAt])
            ->get(['total']);

        $orders = $sales->count();
        $revenue = (float) $sales->sum('total');

        $userCount = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->count();

        $usersPreview = DB::table('users as u')
            ->leftJoinSub($this->userRoleSummary(), 'ur', function ($join): void {
                $join->on('ur.user_id', '=', 'u.id');
            })
            ->leftJoinSub($this->userLastLogin(), 'll', function ($join): void {
                $join->on('ll.user_id', '=', 'u.id');
            })
            ->where('u.tenant_id', $tenantId)
            ->orderByDesc('u.created_at')
            ->limit(5)
            ->get([
                'u.id',
                'u.name',
                'u.email',
                'u.deleted_at',
                'u.created_at',
                'ur.role_name',
                'ur.is_owner',
                'll.last_activity',
            ])
            ->map(function ($row) {
                $lastLoginAt = null;
                if (! empty($row->last_activity)) {
                    $lastLoginAt = CarbonImmutable::createFromTimestamp((int) $row->last_activity, 'UTC')->toIso8601String();
                }

                return [
                    'user_id' => $row->id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'phone' => null,
                    'role' => $row->role_name ? strtolower((string) $row->role_name) : null,
                    'status' => $row->deleted_at ? 'inactive' : 'active',
                    'last_login_at' => $lastLoginAt,
                    'created_at' => $row->created_at ? CarbonImmutable::parse($row->created_at, 'UTC')->toIso8601String() : null,
                    'is_owner' => (bool) ($row->is_owner ?? false),
                ];
            })
            ->all();

        $ownerExists = DB::table('user_roles as ur')
            ->join('roles as r', function ($join): void {
                $join->on('ur.role_id', '=', 'r.id')
                    ->on('ur.tenant_id', '=', 'r.tenant_id');
            })
            ->where('ur.tenant_id', $tenantId)
            ->whereIn(DB::raw('LOWER(r.name)'), ['owner', 'pemilik'])
            ->exists();

        $recentEvents = DB::table('subscription_audit_logs')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get([
                'id',
                'old_plan',
                'new_plan',
                'reason',
                'request_id',
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
            'tenant' => [
                'tenant_id' => $tenant->id,
                'name' => $tenant->name,
                'status' => $status,
                'plan' => $tenant->plan ?? 'S',
            ],
            'usage_summary' => [
                'orders' => $orders,
                'revenue' => $revenue,
            ],
            'user_count' => $userCount,
            'owner_exists' => $ownerExists,
            'users_preview' => $usersPreview,
            'recent_events' => $recentEvents,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function action(string $tenantId, string $action, array $payload, array $context): array
    {
        $idempotencyKey = $context['idempotency_key'] ?? null;

        return $this->runIdempotent($action, $tenantId, $idempotencyKey, function () use ($tenantId, $action, $payload, $context): array {
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            if (! $tenant) {
                return $this->error('NOT_FOUND', 'Tenant tidak ditemukan.', 404);
            }

            return match ($action) {
                'tenant_suspended' => $this->suspendTenant($tenantId, $payload, $context),
                'tenant_unsuspended' => $this->unsuspendTenant($tenantId, $payload, $context),
                'tenant_force_logout' => $this->forceLogoutTenant($tenantId, $payload, $context),
                'tenant_reset_owner' => $this->resetOwnerPassword($tenantId, $payload, $context),
                'tenant_unlock' => $this->unlockTenant($tenantId, $payload, $context),
                default => $this->error('BAD_REQUEST', 'Action tidak dikenal.', 400),
            };
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function impersonate(string $tenantId, array $payload, array $context): array
    {
        $idempotencyKey = $context['idempotency_key'] ?? null;

        return $this->runIdempotent('tenant_impersonated', $tenantId, $idempotencyKey, function () use ($tenantId, $payload, $context): array {
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            if (! $tenant) {
                return $this->error('NOT_FOUND', 'Tenant tidak ditemukan.', 404);
            }

            $expiresAt = now('UTC')->addMinutes(30)->toIso8601String();
            $this->logAction('tenant_impersonated', $tenantId, null, $payload, $context);

            return [
                'status' => 'ok',
                'action' => 'tenant_impersonated',
                'tenant_id' => $tenantId,
                'impersonation_expires_at' => $expiresAt,
            ];
        });
    }

    private function suspendTenant(string $tenantId, array $payload, array $context): array
    {
        $subscription = $this->currentSubscription($tenantId);
        if (! $subscription) {
            return $this->error('CONFLICT', 'Subscription tidak ditemukan.', 409);
        }

        $status = strtolower((string) $subscription->status);
        if (in_array($status, ['suspended', 'canceled'], true)) {
            return $this->error('CONFLICT', 'Tenant sudah suspended.', 409);
        }

        DB::table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->where('is_current', true)
            ->update(['status' => 'suspended', 'updated_at' => now('UTC')]);

        $this->logAction('tenant_suspended', $tenantId, null, $payload, $context);

        return [
            'status' => 'ok',
            'action' => 'tenant_suspended',
            'tenant_id' => $tenantId,
        ];
    }

    private function unsuspendTenant(string $tenantId, array $payload, array $context): array
    {
        $subscription = $this->currentSubscription($tenantId);
        if (! $subscription) {
            return $this->error('CONFLICT', 'Subscription tidak ditemukan.', 409);
        }

        $status = strtolower((string) $subscription->status);
        if ($status !== 'suspended') {
            return $this->error('CONFLICT', 'Tenant tidak dalam status suspended.', 409);
        }

        DB::table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->where('is_current', true)
            ->update(['status' => 'active', 'updated_at' => now('UTC')]);

        $this->logAction('tenant_unsuspended', $tenantId, null, $payload, $context);

        return [
            'status' => 'ok',
            'action' => 'tenant_unsuspended',
            'tenant_id' => $tenantId,
        ];
    }

    private function forceLogoutTenant(string $tenantId, array $payload, array $context): array
    {
        DB::table('sessions')
            ->whereIn('user_id', function ($query) use ($tenantId): void {
                $query->select('id')
                    ->from('users')
                    ->where('tenant_id', $tenantId);
            })
            ->delete();

        $this->logAction('tenant_force_logout', $tenantId, null, $payload, $context);

        return [
            'status' => 'ok',
            'action' => 'tenant_force_logout',
            'tenant_id' => $tenantId,
        ];
    }

    private function resetOwnerPassword(string $tenantId, array $payload, array $context): array
    {
        $ownerId = $this->findOwnerUserId($tenantId);
        if (! $ownerId) {
            return $this->error('NOT_FOUND', 'Owner tidak ditemukan.', 404);
        }

        DB::table('users')
            ->where('id', $ownerId)
            ->update([
                'password' => Hash::make(Str::random(16)),
                'updated_at' => now('UTC'),
                'failed_login_attempts' => 0,
            ]);

        $this->logAction('tenant_reset_owner', $tenantId, $ownerId, $payload, $context);

        return [
            'status' => 'ok',
            'action' => 'tenant_reset_owner',
            'tenant_id' => $tenantId,
        ];
    }

    private function unlockTenant(string $tenantId, array $payload, array $context): array
    {
        DB::table('users')
            ->where('tenant_id', $tenantId)
            ->update([
                'failed_login_attempts' => 0,
                'updated_at' => now('UTC'),
            ]);

        $this->logAction('tenant_unlock', $tenantId, null, $payload, $context);

        return [
            'status' => 'ok',
            'action' => 'tenant_unlock',
            'tenant_id' => $tenantId,
        ];
    }

    private function currentSubscription(string $tenantId): ?object
    {
        return DB::table('subscriptions')
            ->where('tenant_id', $tenantId)
            ->where('is_current', true)
            ->first();
    }

    private function findOwnerUserId(string $tenantId): ?string
    {
        return DB::table('user_roles as ur')
            ->join('roles as r', function ($join): void {
                $join->on('ur.role_id', '=', 'r.id')
                    ->on('ur.tenant_id', '=', 'r.tenant_id');
            })
            ->where('ur.tenant_id', $tenantId)
            ->whereIn(DB::raw('LOWER(r.name)'), ['owner', 'pemilik'])
            ->value('ur.user_id');
    }

    private function logAction(string $action, string $tenantId, ?string $userId, array $payload, array $context): void
    {
        $logger = app(AuditLogger::class);
        $logger->log(
            event: $action,
            actorId: $context['actor_id'] ?? null,
            referenceType: $userId ? 'user' : 'tenant',
            referenceId: $userId ?: $tenantId,
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

    private function currentSubscriptions()
    {
        return DB::table('subscriptions')
            ->select(['tenant_id', 'status', 'current_period_end'])
            ->where('is_current', true);
    }

    private function userCounts()
    {
        return DB::table('users')
            ->select('tenant_id', DB::raw('count(*) as user_count'))
            ->groupBy('tenant_id');
    }

    private function userRoleSummary()
    {
        return DB::table('user_roles as ur')
            ->join('roles as r', function ($join): void {
                $join->on('ur.role_id', '=', 'r.id')
                    ->on('ur.tenant_id', '=', 'r.tenant_id');
            })
            ->select(
                'ur.user_id',
                DB::raw('min(r.name) as role_name'),
                DB::raw("max(case when lower(r.name) in ('owner','pemilik') then 1 else 0 end) as is_owner")
            )
            ->groupBy('ur.user_id');
    }

    private function userLastLogin()
    {
        return DB::table('sessions')
            ->select('user_id', DB::raw('max(last_activity) as last_activity'))
            ->groupBy('user_id');
    }

    private function branchCounts()
    {
        return DB::table('branches')
            ->select('tenant_id', DB::raw('count(*) as outlet_count'))
            ->groupBy('tenant_id');
    }

    private function lastActivity()
    {
        return DB::table('sessions as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->select('u.tenant_id', DB::raw('max(s.last_activity) as last_activity'))
            ->groupBy('u.tenant_id');
    }

    private function overdueInvoices()
    {
        $now = CarbonImmutable::now('UTC');

        return DB::table('invoices')
            ->select('tenant_id', DB::raw('count(*) as overdue_count'))
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->whereNotIn('status', ['paid', 'void'])
            ->groupBy('tenant_id');
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(array $filters): array
    {
        $preset = (string) ($filters['preset'] ?? 'last7');
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $today = CarbonImmutable::now('UTC')->startOfDay();

        return match ($preset) {
            'today' => [$today, $today->endOfDay()],
            'last30' => [$today->subDays(29), $today->endOfDay()],
            default => $this->resolveCustomOrDefault($from, $to, $today),
        };
    }

    private function resolveCustomOrDefault(?string $from, ?string $to, CarbonImmutable $today): array
    {
        if ($from || $to) {
            $start = CarbonImmutable::parse($from ?? $today->toDateString(), 'UTC')->startOfDay();
            $end = CarbonImmutable::parse($to ?? $from ?? $today->toDateString(), 'UTC')->endOfDay();

            if ($end < $start) {
                [$start, $end] = [$end, $start];
            }

            return [$start, $end];
        }

        return [$today->subDays(6), $today->endOfDay()];
    }
}
