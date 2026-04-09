<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\FeatureGateConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SubscriptionService
{
    private const PLAN_ORDER = [
        'S' => 1,
        'M' => 2,
        'P' => 3,
    ];

    private const PLAN_PRICE = [
        'S' => 0.0,
        'M' => 199000.0,
        'P' => 399000.0,
    ];

    public function currentPlan(Tenant $tenant): string
    {
        // Source of truth MVP: tenant.plan is the active entitlement plan.
        return FeatureGateConfig::normalizePlan($tenant->plan ?? null);
    }

    public function currentStatus(Tenant $tenant): string
    {
        $subscription = $this->currentSubscription($tenant);

        return strtolower((string) ($subscription->status ?? 'active'));
    }

    public function currentSubscription(Tenant $tenant): ?object
    {
        if (! Schema::hasTable('subscriptions')) {
            return null;
        }

        $query = DB::table('subscriptions')
            ->where('tenant_id', $tenant->id);

        if (DB::getSchemaBuilder()->hasColumn('subscriptions', 'is_current')) {
            $current = (clone $query)
                ->where('is_current', true)
                ->orderByDesc('started_at')
                ->first();

            if ($current) {
                return $current;
            }
        }

        return $query
            ->orderByDesc('started_at')
            ->first();
    }

    public function canAccessFeature(Tenant $tenant, string $featureKey): bool
    {
        $status = $this->currentStatus($tenant);
        if (in_array($status, ['suspended', 'canceled'], true)) {
            return false;
        }

        return FeatureGateConfig::isFeatureAllowed($featureKey, $this->currentPlan($tenant));
    }

    public function isStatusAllowed(Tenant $tenant, array $allowedStatuses): bool
    {
        $allowed = array_values(array_filter(array_map(
            static fn ($status) => strtolower(trim((string) $status)),
            $allowedStatuses
        )));

        if ($allowed === []) {
            return true;
        }

        return in_array($this->currentStatus($tenant), $allowed, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function previewPlanChange(Tenant $tenant, string $targetPlan, string $mode = 'auto'): array
    {
        $currentPlan = $this->currentPlan($tenant);
        $target = FeatureGateConfig::normalizePlan($targetPlan);
        $resolvedMode = $this->resolveChangeMode($currentPlan, $target, $mode);
        $effectiveAt = $resolvedMode === 'end_of_period'
            ? ($this->resolveCurrentPeriodEnd($tenant) ?? now('UTC')->addMonth())
            : now('UTC');

        $currentEntitlements = collect(FeatureGateConfig::entitlementsForPlan($currentPlan))->keyBy('key');
        $targetEntitlements = collect(FeatureGateConfig::entitlementsForPlan($target))->keyBy('key');

        $added = $targetEntitlements
            ->filter(fn (array $feature): bool => (bool) ($feature['allowed'] ?? false))
            ->filter(fn (array $feature, string $key): bool => ! (bool) (($currentEntitlements[$key]['allowed'] ?? false)))
            ->map(fn (array $feature): array => [
                'key' => $feature['key'] ?? '',
                'module' => $feature['module'] ?? 'misc',
                'ui_type' => $feature['ui_type'] ?? null,
            ])
            ->values()
            ->all();

        $removed = $currentEntitlements
            ->filter(fn (array $feature): bool => (bool) ($feature['allowed'] ?? false))
            ->filter(fn (array $feature, string $key): bool => ! (bool) (($targetEntitlements[$key]['allowed'] ?? false)))
            ->map(fn (array $feature): array => [
                'key' => $feature['key'] ?? '',
                'module' => $feature['module'] ?? 'misc',
                'ui_type' => $feature['ui_type'] ?? null,
            ])
            ->values()
            ->all();

        $proration = $this->calculateProration($tenant, $currentPlan, $target, $resolvedMode);

        return [
            'current_plan' => $currentPlan,
            'target_plan' => $target,
            'mode' => $resolvedMode,
            'effective_at' => $effectiveAt,
            'effective_timezone' => 'UTC',
            'period_definition' => 'actual_subscription_period',
            'proration_rounding' => 'round_2_half_up',
            'plan_labels' => FeatureGateConfig::planLabels(),
            'features_added' => $added,
            'features_removed' => $removed,
            'impacted_menu_count' => collect($removed)->where('ui_type', 'page')->count(),
            'proration' => $proration,
        ];
    }

    public function applyDuePendingPlanChanges(?string $tenantId = null): int
    {
        if (! Schema::hasTable('subscriptions') || ! Schema::hasColumn('subscriptions', 'pending_plan')) {
            return 0;
        }

        $query = DB::table('subscriptions')
            ->where('is_current', true)
            ->whereNotNull('pending_plan')
            ->whereNotNull('pending_change_effective_at')
            ->where('pending_change_effective_at', '<=', now('UTC'));

        if ($tenantId !== null && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        $rows = $query->get(['id', 'tenant_id', 'plan', 'pending_plan', 'pending_change_effective_at']);
        $applied = 0;

        foreach ($rows as $row) {
            $didApply = DB::transaction(function () use ($row): bool {
                $locked = DB::table('subscriptions')
                    ->where('id', $row->id)
                    ->lockForUpdate()
                    ->first(['id', 'tenant_id', 'plan', 'pending_plan', 'pending_change_effective_at']);

                if (! $locked || ! $locked->pending_plan || ! $locked->pending_change_effective_at) {
                    return false;
                }

                if ((string) $locked->pending_change_effective_at > (string) now('UTC')) {
                    return false;
                }

                $newPlan = FeatureGateConfig::normalizePlan((string) $locked->pending_plan);
                $oldPlan = FeatureGateConfig::normalizePlan((string) $locked->plan);

                DB::table('subscriptions')
                    ->where('id', $locked->id)
                    ->update([
                        'plan' => $newPlan,
                        'pending_plan' => null,
                        'pending_change_mode' => null,
                        'pending_change_effective_at' => null,
                        'pending_change_reason' => null,
                        'pending_change_set_by' => null,
                        'updated_at' => now('UTC'),
                    ]);

                DB::table('tenants')
                    ->where('id', $locked->tenant_id)
                    ->update(['plan' => $newPlan]);

                FeatureGateConfig::forgetEntitlements($oldPlan);
                FeatureGateConfig::forgetEntitlements($newPlan);

                Log::info('subscription.plan.change_applied', [
                    'tenant_id' => (string) $locked->tenant_id,
                    'old_plan' => $oldPlan,
                    'new_plan' => $newPlan,
                    'effective_at' => (string) $locked->pending_change_effective_at,
                    'source' => 'lifecycle_enforcer',
                ]);

                return true;
            }, 3);

            if ($didApply) {
                $applied++;
            }
        }

        return $applied;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function changePlan(
        Tenant $tenant,
        ?string $actorId,
        string $newPlan,
        string $reason,
        string $mode = 'auto',
        array $context = [],
        ?string $idempotencyKey = null,
    ): array {
        $normalizedNewPlan = FeatureGateConfig::normalizePlan($newPlan);

        if ($idempotencyKey) {
            $cached = null;
            if (Schema::hasTable('subscription_operations')) {
                $cached = DB::table('subscription_operations')
                    ->where('tenant_id', $tenant->id)
                    ->where('operation', 'change_plan')
                    ->where('idempotency_key', $idempotencyKey)
                    ->value('response_payload');
            }

            if (is_string($cached) && $cached !== '') {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    $decoded['idempotent_replay'] = true;

                    return $decoded;
                }
            }
        }

        $result = DB::transaction(function () use ($tenant, $actorId, $normalizedNewPlan, $reason, $mode, $context, $idempotencyKey): array {
            $tenantRow = DB::table('tenants')
                ->where('id', $tenant->id)
                ->lockForUpdate()
                ->first(['id', 'plan']);

            if ($idempotencyKey && Schema::hasTable('subscription_operations')) {
                $existingPayload = DB::table('subscription_operations')
                    ->where('tenant_id', $tenant->id)
                    ->where('operation', 'change_plan')
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->value('response_payload');

                if (is_string($existingPayload) && $existingPayload !== '') {
                    $decoded = json_decode($existingPayload, true);
                    if (is_array($decoded)) {
                        $decoded['idempotent_replay'] = true;

                        return $decoded;
                    }
                }
            }

            $oldPlan = FeatureGateConfig::normalizePlan($tenantRow->plan ?? null);
            $resolvedMode = $this->resolveChangeMode($oldPlan, $normalizedNewPlan, $mode);
            $proration = $this->calculateProration($tenant, $oldPlan, $normalizedNewPlan, $resolvedMode);
            $effectiveAt = $resolvedMode === 'end_of_period'
                ? ($this->resolveCurrentPeriodEnd($tenant) ?? now('UTC')->addMonth())
                : now('UTC');

            $isNoop = $oldPlan === $normalizedNewPlan;
            $changed = ! $isNoop && $resolvedMode === 'immediate';
            $scheduled = ! $isNoop && $resolvedMode === 'end_of_period';

            $hadPendingPlan = false;
            $pendingReplaced = false;
            $pendingCleared = false;

            if ($changed) {
                DB::table('tenants')
                    ->where('id', $tenant->id)
                    ->update(['plan' => $normalizedNewPlan]);
            }

            if (Schema::hasTable('subscriptions')) {
                $hasPendingPlan = DB::getSchemaBuilder()->hasColumn('subscriptions', 'pending_plan');
                $hasPendingMode = DB::getSchemaBuilder()->hasColumn('subscriptions', 'pending_change_mode');
                $hasPendingEffective = DB::getSchemaBuilder()->hasColumn('subscriptions', 'pending_change_effective_at');
                $hasPendingReason = DB::getSchemaBuilder()->hasColumn('subscriptions', 'pending_change_reason');
                $hasPendingActor = DB::getSchemaBuilder()->hasColumn('subscriptions', 'pending_change_set_by');

                $subscriptionColumns = ['id', 'status'];
                if ($hasPendingPlan) {
                    $subscriptionColumns[] = 'pending_plan';
                }

                $subscription = DB::table('subscriptions')
                    ->where('tenant_id', $tenant->id)
                    ->when(
                        DB::getSchemaBuilder()->hasColumn('subscriptions', 'is_current'),
                        static fn ($q) => $q->where('is_current', true)
                    )
                    ->lockForUpdate()
                    ->orderByDesc('started_at')
                    ->first($subscriptionColumns);

                if (! $subscription) {
                    $insert = [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'plan' => $changed ? $normalizedNewPlan : $oldPlan,
                        'status' => 'active',
                        'started_at' => now('UTC'),
                        'current_period_start' => now('UTC'),
                        'current_period_end' => now('UTC')->addMonth(),
                        'is_current' => true,
                        'created_at' => now('UTC'),
                        'updated_at' => now('UTC'),
                    ];

                    if ($hasPendingPlan) {
                        $insert['pending_plan'] = $scheduled ? $normalizedNewPlan : null;
                    }
                    if ($hasPendingMode) {
                        $insert['pending_change_mode'] = $scheduled ? 'end_of_period' : null;
                    }
                    if ($hasPendingEffective) {
                        $insert['pending_change_effective_at'] = $scheduled ? $effectiveAt : null;
                    }
                    if ($hasPendingReason) {
                        $insert['pending_change_reason'] = $scheduled ? $reason : null;
                    }
                    if ($hasPendingActor) {
                        $insert['pending_change_set_by'] = $scheduled ? $actorId : null;
                    }

                    DB::table('subscriptions')->insert($insert);
                } else {
                    $hadPendingPlan = ! empty($subscription->pending_plan ?? null);

                    $updates = [
                        'plan' => $changed ? $normalizedNewPlan : $oldPlan,
                        'updated_at' => now('UTC'),
                    ];

                    if ($hasPendingPlan) {
                        $updates['pending_plan'] = $scheduled ? $normalizedNewPlan : null;
                        if ($scheduled && $hadPendingPlan && (string) $subscription->pending_plan !== $normalizedNewPlan) {
                            $pendingReplaced = true;
                        }
                        if ($changed && $hadPendingPlan) {
                            $pendingCleared = true;
                        }
                    }
                    if ($hasPendingMode) {
                        $updates['pending_change_mode'] = $scheduled ? 'end_of_period' : null;
                    }
                    if ($hasPendingEffective) {
                        $updates['pending_change_effective_at'] = $scheduled ? $effectiveAt : null;
                    }
                    if ($hasPendingReason) {
                        $updates['pending_change_reason'] = $scheduled ? $reason : null;
                    }
                    if ($hasPendingActor) {
                        $updates['pending_change_set_by'] = $scheduled ? $actorId : null;
                    }

                    DB::table('subscriptions')
                        ->where('id', $subscription->id)
                        ->update($updates);
                }
            }

            if (! $isNoop && Schema::hasTable('subscription_audit_logs')) {
                $audit = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actorId,
                    'old_plan' => $oldPlan,
                    'new_plan' => $normalizedNewPlan,
                    'reason' => $reason,
                    'ip_address' => (string) ($context['ip_address'] ?? ''),
                    'user_agent' => (string) ($context['user_agent'] ?? ''),
                    'request_id' => (string) ($context['request_id'] ?? ''),
                    'actor_role' => (string) ($context['actor_role'] ?? ''),
                    'source' => (string) ($context['source'] ?? 'api'),
                    'created_at' => now('UTC'),
                ];

                if (DB::getSchemaBuilder()->hasColumn('subscription_audit_logs', 'change_mode')) {
                    $audit['change_mode'] = $resolvedMode;
                }
                if (DB::getSchemaBuilder()->hasColumn('subscription_audit_logs', 'effective_at')) {
                    $audit['effective_at'] = $effectiveAt;
                }
                if (DB::getSchemaBuilder()->hasColumn('subscription_audit_logs', 'proration_amount')) {
                    $audit['proration_amount'] = $proration['amount'] ?? 0;
                }

                DB::table('subscription_audit_logs')->insert($audit);
            }

            FeatureGateConfig::forgetEntitlements($oldPlan);
            if ($changed) {
                FeatureGateConfig::forgetEntitlements($normalizedNewPlan);
            }

            $response = [
                'status' => $isNoop ? 'no_changes' : 'ok',
                'message' => $isNoop ? 'Tidak ada perubahan plan.' : 'Perubahan plan berhasil diproses.',
                'old_plan' => $oldPlan,
                'plan' => $changed ? $normalizedNewPlan : $oldPlan,
                'requested_plan' => $normalizedNewPlan,
                'plan_label' => FeatureGateConfig::planLabel($normalizedNewPlan),
                'plan_labels' => FeatureGateConfig::planLabels(),
                'noop' => $isNoop,
                'changed' => $changed,
                'scheduled' => $scheduled,
                'schedule_policy' => 'replace_existing',
                'pending_replaced' => $pendingReplaced,
                'pending_cleared' => $pendingCleared,
                'mode' => $resolvedMode,
                'effective_at' => $effectiveAt,
                'proration' => $proration,
            ];

            if ($idempotencyKey && Schema::hasTable('subscription_operations')) {
                DB::table('subscription_operations')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'operation' => 'change_plan',
                    'idempotency_key' => $idempotencyKey,
                    'response_payload' => json_encode($response, JSON_UNESCAPED_UNICODE),
                    'created_at' => now('UTC'),
                ]);
            }

            return $response;
        }, 3);

        if (($result['scheduled'] ?? false) === true) {
            Log::info('subscription.plan.change_scheduled', [
                'tenant_id' => (string) $tenant->id,
                'actor_id' => $actorId,
                'old_plan' => (string) ($result['old_plan'] ?? ''),
                'requested_plan' => (string) ($result['requested_plan'] ?? ''),
                'effective_at' => (string) ($result['effective_at'] ?? ''),
                'mode' => (string) ($result['mode'] ?? ''),
                'pending_replaced' => (bool) ($result['pending_replaced'] ?? false),
                'request_id' => (string) ($context['request_id'] ?? ''),
                'source' => (string) ($context['source'] ?? 'api'),
            ]);

            if ($this->planRank((string) ($result['requested_plan'] ?? 'S')) < $this->planRank((string) ($result['old_plan'] ?? 'S'))) {
                Log::info('metric.subscription.downgrade_scheduled', [
                    'tenant_id' => (string) $tenant->id,
                    'count' => 1,
                    'source' => (string) ($context['source'] ?? 'api'),
                ]);
            }
        }

        if (($result['changed'] ?? false) === true && (float) (($result['proration']['amount'] ?? 0) ?: 0) > 0) {
            Log::info('metric.subscription.proration_invoice_created', [
                'tenant_id' => (string) $tenant->id,
                'count' => 1,
                'amount' => (float) $result['proration']['amount'],
                'currency' => (string) ($result['proration']['currency'] ?? 'IDR'),
                'source' => (string) ($context['source'] ?? 'api'),
            ]);
        }

        return $result;
    }

    private function resolveChangeMode(string $oldPlan, string $newPlan, string $mode): string
    {
        $normalizedMode = strtolower(trim($mode));
        if (in_array($normalizedMode, ['immediate', 'end_of_period'], true)) {
            return $normalizedMode;
        }

        if (($this->planRank($newPlan) <=> $this->planRank($oldPlan)) < 0) {
            return 'end_of_period';
        }

        return 'immediate';
    }

    private function planRank(string $plan): int
    {
        return self::PLAN_ORDER[FeatureGateConfig::normalizePlan($plan)] ?? 1;
    }

    /**
     * @return array{amount: float, currency: string, ratio_remaining: float}
     */
    private function calculateProration(Tenant $tenant, string $oldPlan, string $newPlan, string $mode): array
    {
        if ($oldPlan === $newPlan || $mode !== 'immediate') {
            return [
                'amount' => 0.0,
                'currency' => 'IDR',
                'ratio_remaining' => 0.0,
            ];
        }

        $oldPrice = self::PLAN_PRICE[$oldPlan] ?? 0.0;
        $newPrice = self::PLAN_PRICE[$newPlan] ?? 0.0;
        $delta = $newPrice - $oldPrice;

        $period = $this->resolveCurrentPeriodWindow($tenant);
        $ratio = 1.0;
        if ($period !== null) {
            $total = max(1, $period['end']->getTimestamp() - $period['start']->getTimestamp());
            $remaining = max(0, $period['end']->getTimestamp() - now('UTC')->getTimestamp());
            $ratio = min(1, $remaining / $total);
        }

        return [
            'amount' => round($delta * $ratio, 2),
            'currency' => 'IDR',
            'ratio_remaining' => round($ratio, 4),
        ];
    }

    /**
     * @return array{start: \Illuminate\Support\Carbon, end: \Illuminate\Support\Carbon}|null
     */
    private function resolveCurrentPeriodWindow(Tenant $tenant): ?array
    {
        $current = $this->currentSubscription($tenant);
        if (! $current || empty($current->current_period_start) || empty($current->current_period_end)) {
            return null;
        }

        return [
            'start' => Carbon::parse((string) $current->current_period_start, 'UTC'),
            'end' => Carbon::parse((string) $current->current_period_end, 'UTC'),
        ];
    }

    private function resolveCurrentPeriodEnd(Tenant $tenant): ?\Illuminate\Support\Carbon
    {
        $period = $this->resolveCurrentPeriodWindow($tenant);

        return $period['end'] ?? null;
    }
}
