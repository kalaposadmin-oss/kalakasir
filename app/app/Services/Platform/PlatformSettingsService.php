<?php

namespace App\Services\Platform;

use App\Models\Tenant;
use App\Services\SubscriptionService;
use App\Support\FeatureGateConfig;

class PlatformSettingsService
{
    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function featureFlags(array $filters): array
    {
        $tenantId = (string) ($filters['tenant_id'] ?? '');
        $tenant = null;
        $plan = null;
        $entitlements = [];

        if ($tenantId !== '') {
            $tenant = Tenant::query()->where('id', $tenantId)->first();
            if ($tenant) {
                $plan = app(SubscriptionService::class)->currentPlan($tenant);
                $entitlements = FeatureGateConfig::entitlementsForPlan($plan);
            }
        }

        return [
            'feature_flags' => FeatureGateConfig::featureFlags(),
            'plan_labels' => FeatureGateConfig::planLabels(),
            'tenant' => $tenant ? [
                'tenant_id' => $tenant->id,
                'name' => $tenant->name,
                'plan' => $plan,
            ] : null,
            'entitlements' => $entitlements,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rateLimits(): array
    {
        return [
            'api_per_minute' => (int) config('platform.api_rate_limit_per_minute', 120),
            'sensitive_per_minute' => (int) config('platform.sensitive_rate_limit_per_minute', 30),
            'platform_danger_per_hour' => (int) config('platform.danger_rate_limit_per_hour', 10),
            'platform_rate_limit_override_enabled' => (string) config('platform.rate_limit_override_token', '') !== '',
            'idempotency_ttl_minutes' => (int) config('platform.idempotency_ttl_minutes', 60),
            'idempotency_lock_seconds' => (int) config('platform.idempotency_lock_seconds', 10),
        ];
    }
}
