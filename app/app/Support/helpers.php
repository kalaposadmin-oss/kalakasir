<?php

use Illuminate\Support\Facades\Route;

if (! function_exists('area_route')) {
    function area_route(string $fromArea, string $target, array $params = [], ?string $badge = null): string
    {
        $url = Route::has($target)
            ? route($target, $params, false)
            : $target;

        if (! str_starts_with($url, '/'.$fromArea.'/') && $badge !== 'Backoffice') {
            throw new \LogicException("Cross-area route [{$target}] requires explicit Backoffice badge.");
        }

        return $url;
    }
}

if (! function_exists('pos_default_url')) {
    function pos_default_url(?\App\Models\User $user = null): string
    {
        $user = $user ?: auth()->user();
        if (! $user) {
            return '/pos/dashboard';
        }

        try {
            return app(\App\Services\WebRouteContextService::class)->userHasOpenShift($user)
                ? '/pos/checkout'
                : '/pos/dashboard';
        } catch (\Throwable) {
            return '/pos/dashboard';
        }
    }
}

if (! function_exists('normalize_pos_home_url')) {
    function normalize_pos_home_url(?string $url, ?\App\Models\User $user = null): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return pos_default_url($user);
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        if ($path === '/pos/dashboard') {
            return pos_default_url($user);
        }

        if ($path !== '/portal/switch/pos') {
            return $url;
        }

        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?: ''), $query);
        $next = trim((string) ($query['next'] ?? ''));

        if ((string) (parse_url($next, PHP_URL_PATH) ?: $next) !== '/pos/dashboard') {
            return $url;
        }

        $query['next'] = pos_default_url($user);

        return '/portal/switch/pos?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
