<?php

namespace App\Support;

use Illuminate\Http\Request;

class WebPortalResolver
{
    public function normalizeHost(?string $host): string
    {
        $host = strtolower(trim((string) $host));
        if ($host === '') {
            return '';
        }

        if (str_contains($host, '://')) {
            $host = (string) parse_url($host, PHP_URL_HOST);
        }

        return explode(':', $host)[0] ?? '';
    }

    public function resolvePortalFromRequest(?Request $request): ?string
    {
        if (! $request) {
            return null;
        }

        $posHosts = array_map(fn ($host) => $this->normalizeHost($host), (array) config('platform.portals.pos_hosts', []));
        $backofficeHosts = array_map(fn ($host) => $this->normalizeHost($host), (array) config('platform.portals.backoffice_hosts', []));

        foreach ($this->requestHosts($request) as $host) {
            if ($host !== '' && in_array($host, $posHosts, true)) {
                return 'pos';
            }

            if ($host !== '' && in_array($host, $backofficeHosts, true)) {
                return 'backoffice';
            }
        }

        return null;
    }

    public function resolveActivePortal(Request $request, string $fallback = 'pos'): string
    {
        $requestPortal = $this->resolvePortalFromRequest($request);
        if (in_array($requestPortal, ['pos', 'backoffice'], true)) {
            $request->session()->put('auth_portal', $requestPortal);

            return $requestPortal;
        }

        $sessionPortal = $this->resolveSessionPortal($request);
        if ($sessionPortal !== null) {
            return $sessionPortal;
        }

        $fallbackPortal = in_array($fallback, ['pos', 'backoffice'], true) ? $fallback : 'pos';
        $request->session()->put('auth_portal', $fallbackPortal);

        return $fallbackPortal;
    }

    public function resolveSessionPortal(Request $request): ?string
    {
        $sessionPortal = strtolower((string) $request->session()->get('auth_portal', ''));

        return in_array($sessionPortal, ['pos', 'backoffice'], true) ? $sessionPortal : null;
    }

    public function sanitizeNextPath(?string $next, string $portal, string $fallback): string
    {
        $next = trim((string) $next);
        if ($next === '' || ! str_starts_with($next, '/')) {
            return $fallback;
        }

        if (str_starts_with($next, '//')) {
            return $fallback;
        }

        $normalized = '/'.ltrim($next, '/');
        $portalPrefix = '/'.$portal;

        if ($normalized === $portalPrefix || str_starts_with($normalized, $portalPrefix.'/')) {
            return $normalized;
        }

        return $fallback;
    }

    public function portalUrl(string $portal, string $path, ?Request $request = null): string
    {
        $normalizedPath = '/'.ltrim($path, '/');
        $host = $this->configuredPortalHost($portal);
        if ($host === '') {
            return $normalizedPath;
        }

        $scheme = $request?->getScheme() ?: parse_url((string) config('app.url', 'http://localhost'), PHP_URL_SCHEME) ?: 'http';
        $port = $request?->getPort();

        $base = $scheme.'://'.$host;
        if ($port && ! in_array([$scheme, $port], [['http', 80], ['https', 443]], true)) {
            $base .= ':'.$port;
        }

        return $base.$normalizedPath;
    }

    public function canonicalPortalRequestUrl(string $portal, Request $request): ?string
    {
        $canonicalHost = $this->configuredPortalHost($portal);
        if ($canonicalHost === '') {
            return null;
        }

        $currentHost = $this->normalizeHost($request->getHost());
        if ($currentHost === $canonicalHost) {
            return null;
        }

        $path = $request->getPathInfo();
        if ($request->getQueryString()) {
            $path .= '?'.$request->getQueryString();
        }

        return $this->portalUrl($portal, $path, $request);
    }

    /**
     * @return list<string>
     */
    private function requestHosts(Request $request): array
    {
        $hosts = [
            $request->getHost(),
            $request->headers->get('host'),
            $request->headers->get('x-forwarded-host'),
            $request->server->get('HTTP_HOST'),
            $request->server->get('HTTP_X_FORWARDED_HOST'),
            $request->server->get('SERVER_NAME'),
        ];

        $normalizedHosts = [];
        foreach ($hosts as $host) {
            $normalized = $this->normalizeHost($host);
            if ($normalized === '' || in_array($normalized, $normalizedHosts, true)) {
                continue;
            }

            $normalizedHosts[] = $normalized;
        }

        return $normalizedHosts;
    }

    private function configuredPortalHost(string $portal): string
    {
        $configuredHosts = (array) config($portal === 'backoffice' ? 'platform.portals.backoffice_hosts' : 'platform.portals.pos_hosts', []);

        foreach ($configuredHosts as $host) {
            $normalized = $this->normalizeHost((string) $host);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }
}