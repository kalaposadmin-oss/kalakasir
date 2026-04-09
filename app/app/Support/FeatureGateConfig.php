<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class FeatureGateConfig
{
    private const CONFIG_CACHE_KEY = 'feature-gating:config:v1';
    private const ENTITLEMENT_CACHE_PREFIX = 'feature-gating:entitlements:';
    private const CACHE_TTL_SECONDS = 300;

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return Cache::remember(self::CONFIG_CACHE_KEY, self::CACHE_TTL_SECONDS, static function (): array {
            $path = base_path('config/feature-gating.json');
            if (! is_file($path)) {
                return [];
            }

            $decoded = json_decode((string) file_get_contents($path), true);

            return is_array($decoded) ? $decoded : [];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function featureFlags(): array
    {
        $config = static::config();

        return is_array($config['featureFlags'] ?? null) ? $config['featureFlags'] : [];
    }

    /**
     * @return array<string, string>
     */
    public static function planLabels(): array
    {
        $config = static::config();
        $labels = $config['planLabels'] ?? [];
        if (! is_array($labels)) {
            return [
                'S' => 'Starter',
                'M' => 'Medium',
                'P' => 'Pro',
            ];
        }

        return [
            'S' => (string) ($labels['S'] ?? 'Starter'),
            'M' => (string) ($labels['M'] ?? 'Medium'),
            'P' => (string) ($labels['P'] ?? 'Pro'),
        ];
    }

    public static function normalizePlan(?string $plan): string
    {
        $normalized = strtoupper(trim((string) $plan));

        return in_array($normalized, ['S', 'M', 'P'], true) ? $normalized : 'S';
    }

    public static function planLabel(?string $plan): string
    {
        $normalized = static::normalizePlan($plan);

        return static::planLabels()[$normalized] ?? $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function entitlementsForPlan(string $plan): array
    {
        $normalized = static::normalizePlan($plan);

        return Cache::remember(static::entitlementCacheKey($normalized), self::CACHE_TTL_SECONDS, static function () use ($normalized): array {
            return collect(static::featureFlags())
                ->map(static function (array $row) use ($normalized): array {
                    $plans = $row['plans'] ?? [];
                    $allowed = is_array($plans) && in_array($normalized, $plans, true);

                    return [
                        'key' => $row['key'] ?? '',
                        'module' => $row['module'] ?? 'misc',
                        'ui_type' => $row['uiType'] ?? null,
                        'default_visible' => (bool) ($row['defaultVisible'] ?? false),
                        'plans' => array_values(is_array($plans) ? $plans : []),
                        'allowed' => $allowed,
                    ];
                })
                ->values()
                ->all();
        });
    }

    public static function isFeatureAllowed(string $featureKey, ?string $plan = null): bool
    {
        $normalized = static::normalizePlan($plan);

        foreach (static::entitlementsForPlan($normalized) as $row) {
            if (($row['key'] ?? null) === $featureKey) {
                return (bool) ($row['allowed'] ?? false);
            }
        }

        return false;
    }

    public static function forgetEntitlements(?string $plan = null): void
    {
        if ($plan !== null) {
            Cache::forget(static::entitlementCacheKey(static::normalizePlan($plan)));

            return;
        }

        foreach (['S', 'M', 'P'] as $knownPlan) {
            Cache::forget(static::entitlementCacheKey($knownPlan));
        }
    }

    private static function entitlementCacheKey(string $plan): string
    {
        return self::ENTITLEMENT_CACHE_PREFIX.$plan.':v1';
    }
}
