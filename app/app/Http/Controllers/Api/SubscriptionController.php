<?php

namespace App\Http\Controllers\Api;

use App\Events\SubscriptionPlanChanged;
use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use App\Support\ApiResponse;
use App\Support\FeatureGateConfig;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function current(): \Illuminate\Http\JsonResponse
    {
        $tenant = TenantContext::get();
        if (! $tenant) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $subscriptionService = app(SubscriptionService::class);
        $plan = $subscriptionService->currentPlan($tenant);
        $planLabel = FeatureGateConfig::planLabel($plan);
        $entitlements = collect(FeatureGateConfig::entitlementsForPlan($plan));

        $allowedCount = $entitlements->where('allowed', true)->count();
        $blockedCount = $entitlements->where('allowed', false)->count();
        $impactedMenuCount = $entitlements
            ->filter(fn (array $row): bool => ($row['ui_type'] ?? '') === 'page')
            ->filter(fn (array $row): bool => ! (bool) ($row['allowed'] ?? false))
            ->count();

        $latestSub = $subscriptionService->currentSubscription($tenant);

        return response()->json([
            'plan' => $plan,
            'plan_label' => $planLabel,
            'plan_labels' => FeatureGateConfig::planLabels(),
            'started_at' => $latestSub->started_at ?? $tenant->created_at,
            'status' => $latestSub->status ?? $subscriptionService->currentStatus($tenant),
            'pending_plan' => $latestSub->pending_plan ?? null,
            'pending_change_mode' => $latestSub->pending_change_mode ?? null,
            'pending_change_effective_at' => $latestSub->pending_change_effective_at ?? null,
            'allowed_count' => $allowedCount,
            'blocked_count' => $blockedCount,
            'impacted_menu_count' => $impactedMenuCount,
        ]);
    }

    public function updatePlan(Request $request): \Illuminate\Http\JsonResponse
    {
        $tenant = TenantContext::get();
        if (! $tenant) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'plan' => ['required', 'in:S,M,P'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'mode' => ['nullable', 'in:auto,immediate,end_of_period'],
        ]);

        $subscriptionService = app(SubscriptionService::class);
        $oldPlan = $subscriptionService->currentPlan($tenant);
        $newPlan = FeatureGateConfig::normalizePlan($validated['plan']);
        $reason = trim($validated['reason']);

        $result = $subscriptionService->changePlan(
            tenant: $tenant,
            actorId: $request->user()?->id,
            newPlan: $newPlan,
            reason: $reason,
            mode: (string) ($validated['mode'] ?? 'auto'),
            context: [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => (string) $request->header('X-Request-Id', ''),
                'actor_role' => (string) $this->resolveActorRole($request),
                'source' => 'api',
            ],
            idempotencyKey: $request->header('X-Idempotency-Key'),
        );

        if (($result['changed'] ?? false) === true) {
            event(new SubscriptionPlanChanged(
                tenantId: (string) $tenant->id,
                actorId: $request->user()?->id,
                oldPlan: (string) ($result['old_plan'] ?? $oldPlan),
                newPlan: (string) ($result['plan'] ?? $newPlan),
                reason: $reason,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        }

        return response()->json($result);
    }

    public function impactPreview(Request $request): \Illuminate\Http\JsonResponse
    {
        $tenant = TenantContext::get();
        if (! $tenant) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'target_plan' => ['required', 'in:S,M,P'],
            'mode' => ['nullable', 'in:auto,immediate,end_of_period'],
        ]);

        $preview = app(SubscriptionService::class)->previewPlanChange(
            tenant: $tenant,
            targetPlan: (string) $validated['target_plan'],
            mode: (string) ($validated['mode'] ?? 'auto'),
        );

        return response()->json($preview);
    }

    public function auditLogs(Request $request): \Illuminate\Http\JsonResponse
    {
        $tenant = TenantContext::get();
        if (! $tenant) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'source' => ['nullable', 'string', 'max:30'],
            'actor_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', 'string', 'max:20'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);
        $offset = ($page - 1) * $perPage;

        $base = $this->auditLogsBaseQuery($tenant->id, $validated);

        $total = (clone $base)->count();
        $rows = $base
            ->orderByDesc('s.created_at')
            ->offset($offset)
            ->limit($perPage)
            ->get([
                's.id',
                's.old_plan',
                's.new_plan',
                's.reason',
                's.ip_address',
                's.user_agent',
                's.request_id',
                's.actor_role',
                's.source',
                's.created_at',
                's.actor_id',
                'u.name as actor_name',
            ]);

            return ApiResponse::paginated([
                'audit_logs' => $rows,
            ], $page, $perPage, $total);
    }

    public function exportAuditLogsCsv(Request $request)
    {
        $tenant = TenantContext::get();
        if (! $tenant) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'source' => ['nullable', 'string', 'max:30'],
            'actor_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', 'string', 'max:20'],
        ]);

        return $this->exportAuditLogsCsvForTenant((string) $tenant->id, $validated);
    }

    public function exportAuditLogsCsvPlatform(Request $request)
    {
        if ($this->resolveActorRole($request) !== 'superadmin') {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Akses ditolak untuk role ini.',
                'details' => [],
            ], 403);
        }

        $validated = $request->validate([
            'tenant_id' => ['required', 'uuid'],
            'source' => ['nullable', 'string', 'max:30'],
            'actor_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', 'string', 'max:20'],
        ]);

        return $this->exportAuditLogsCsvForTenant((string) $validated['tenant_id'], $validated);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function exportAuditLogsCsvForTenant(string $tenantId, array $filters)
    {
        $rows = $this->auditLogsBaseQuery($tenantId, $filters)
            ->orderByDesc('s.created_at')
            ->limit(5000)
            ->get([
                's.id',
                's.old_plan',
                's.new_plan',
                's.reason',
                's.change_mode',
                's.effective_at',
                's.proration_amount',
                's.source',
                's.actor_role',
                's.request_id',
                's.ip_address',
                's.created_at',
                'u.name as actor_name',
            ]);

        $filename = 'subscription-audit-logs-'.now('UTC')->format('Ymd-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id',
                'created_at',
                'actor_name',
                'actor_role',
                'source',
                'request_id',
                'old_plan',
                'new_plan',
                'change_mode',
                'effective_at',
                'proration_amount',
                'reason',
                'ip_address',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->created_at,
                    $row->actor_name,
                    $row->actor_role,
                    $row->source,
                    $row->request_id,
                    $row->old_plan,
                    $row->new_plan,
                    $row->change_mode,
                    $row->effective_at,
                    $row->proration_amount,
                    $row->reason,
                    $row->ip_address,
                ]);
            }
            fclose($out);
        }, $filename, $headers);
    }

    public function entitlements(Request $request): \Illuminate\Http\JsonResponse
    {
        $tenant = TenantContext::get();
        if (! $tenant) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'max:100'],
            'lockedOnly' => ['nullable', 'boolean'],
        ]);

        $plan = app(SubscriptionService::class)->currentPlan($tenant);
        $planLabel = FeatureGateConfig::planLabel($plan);
        $search = strtolower(trim((string) ($validated['search'] ?? '')));
        $module = strtolower(trim((string) ($validated['module'] ?? '')));
        $lockedOnly = (bool) ($validated['lockedOnly'] ?? false);

        $rows = collect(FeatureGateConfig::entitlementsForPlan($plan))
            ->filter(function (array $row) use ($search, $module, $lockedOnly): bool {
                if ($search !== '') {
                    $haystack = strtolower($row['key'].' '.$row['module']);
                    if (! str_contains($haystack, $search)) {
                        return false;
                    }
                }

                if ($module !== '' && strtolower($row['module']) !== $module) {
                    return false;
                }

                if ($lockedOnly && $row['allowed']) {
                    return false;
                }

                return true;
            })
            ->values();

        return response()->json([
            'plan' => $plan,
            'plan_label' => $planLabel,
            'plan_labels' => FeatureGateConfig::planLabels(),
            'count' => $rows->count(),
            'allowed_count' => $rows->where('allowed', true)->count(),
            'blocked_count' => $rows->where('allowed', false)->count(),
            'entitlements' => $rows,
        ]);
    }

    private function resolveActorRole(Request $request): ?string
    {
        $user = $request->user();
        $tenantId = TenantContext::id();
        if (! $user || ! $tenantId) {
            return null;
        }

        return DB::table('user_roles')
            ->join('roles', function ($join): void {
                $join->on('user_roles.role_id', '=', 'roles.id')
                    ->on('user_roles.tenant_id', '=', 'roles.tenant_id');
            })
            ->where('user_roles.user_id', $user->id)
            ->where('user_roles.tenant_id', $tenantId)
            ->value('roles.name');
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function auditLogsBaseQuery(string $tenantId, array $filters)
    {
        $query = DB::table('subscription_audit_logs as s')
            ->leftJoin('users as u', function ($join): void {
                $join->on('s.actor_id', '=', 'u.id')
                    ->on('s.tenant_id', '=', 'u.tenant_id');
            })
            ->where('s.tenant_id', $tenantId);

        if (! empty($filters['source'])) {
            $query->where('s.source', (string) $filters['source']);
        }

        if (! empty($filters['mode'])) {
            $query->where('s.change_mode', (string) $filters['mode']);
        }

        if (! empty($filters['actor_id'])) {
            $query->where('s.actor_id', (string) $filters['actor_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('s.created_at', '>=', Carbon::parse((string) $filters['from'])->startOfDay()->toDateTimeString());
        }

        if (! empty($filters['to'])) {
            $query->where('s.created_at', '<=', Carbon::parse((string) $filters['to'])->endOfDay()->toDateTimeString());
        }

        if (! empty($filters['q'])) {
            $needle = trim((string) $filters['q']);
            $query->where(function ($nested) use ($needle): void {
                $nested->where('s.reason', 'like', '%'.$needle.'%')
                    ->orWhere('s.request_id', 'like', '%'.$needle.'%')
                    ->orWhere('u.name', 'like', '%'.$needle.'%');
            });
        }

        return $query;
    }
}
