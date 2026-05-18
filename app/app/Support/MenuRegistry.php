<?php

namespace App\Support;

use App\Services\SubscriptionService;

class MenuRegistry
{
    public static function section(string $key): ?array
    {
        $sections = (array) config('menu-registry.sections', []);
        $section = $sections[$key] ?? null;

        return is_array($section) ? $section : null;
    }

    public static function canAccessSection(string $key, string $portal, ?string $role, mixed $tenant = null): bool
    {
        $section = self::section($key);
        if (! $section) {
            return false;
        }

        if (! self::matchesPortal((string) ($section['portal'] ?? 'any'), $portal)) {
            return false;
        }

        if (! self::matchesRole((array) ($section['allowed_roles'] ?? ['*']), $role)) {
            return false;
        }

        $featureKey = (string) ($section['feature_key'] ?? '');
        if ($featureKey !== '' && $tenant) {
            $subscription = app(SubscriptionService::class);
            if (! $subscription->canAccessFeature($tenant, $featureKey)) {
                return false;
            }
        }

        return (bool) ($section['is_visible'] ?? true);
    }

    public static function matchesPortal(string $requiredPortal, string $activePortal): bool
    {
        $required = strtolower(trim($requiredPortal));
        $active = strtolower(trim($activePortal));

        if ($required === '' || $required === 'any' || $required === '*') {
            return true;
        }

        return $required === $active;
    }

    public static function matchesRole(array $allowedRoles, ?string $role): bool
    {
        if (in_array('*', $allowedRoles, true)) {
            return true;
        }

        $normalized = strtolower(trim((string) $role));

        return $normalized !== '' && in_array($normalized, array_map('strtolower', $allowedRoles), true);
    }
}
