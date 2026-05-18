<?php

namespace App\Support;

use Illuminate\Http\Request;

class WebEntityFlowContext
{
    public function __construct(private readonly WebPortalResolver $portalResolver)
    {
    }

    public function resolvePortal(Request $request, string $default = 'pos'): string
    {
        $from = $this->sanitizeFrom((string) $request->query('from', ''));
        if ($from !== null) {
            return str_starts_with($from, '/backoffice/') ? 'backoffice' : 'pos';
        }

        return $this->portalResolver->resolveActivePortal($request, $default);
    }

    public function resolveBackUrl(Request $request, string $portal, string $fallback, array $allowedPrefixes = []): string
    {
        $from = $this->sanitizeFrom((string) $request->query('from', ''));
        if ($from === null) {
            return $portal === 'pos' ? normalize_pos_home_url($fallback) : $fallback;
        }

        if (! str_starts_with($from, '/'.$portal.'/')) {
            return $portal === 'pos' ? normalize_pos_home_url($fallback) : $fallback;
        }

        if ($allowedPrefixes !== [] && ! $this->matchesAllowedPrefixes($from, $allowedPrefixes)) {
            return $portal === 'pos' ? normalize_pos_home_url($fallback) : $fallback;
        }

        return $portal === 'pos' ? normalize_pos_home_url($from) : $from;
    }

    public function detailContext(
        Request $request,
        string $portal,
        string $currentPath,
        string $fallbackBackUrl,
        array $allowedBackPrefixes = [],
        array $extra = []
    ): array {
        return array_merge([
            'portal' => $portal,
            'currentPath' => $currentPath,
            'backUrl' => $this->resolveBackUrl($request, $portal, $fallbackBackUrl, $allowedBackPrefixes),
        ], $extra);
    }

    public function legacyTarget(Request $request, string $defaultPortal, string $prefix, string $entityId): string
    {
        $portal = $this->resolvePortal($request, $defaultPortal);

        return '/'.$portal.'/'.trim($prefix, '/').'/'.$entityId;
    }

    private function sanitizeFrom(string $from): ?string
    {
        $from = trim($from);
        if ($from === '') {
            return null;
        }

        $normalized = '/'.ltrim($from, '/');
        if (! preg_match('#^/(pos|backoffice)(/.*)?$#', $normalized)) {
            return null;
        }

        return $normalized;
    }

    private function matchesAllowedPrefixes(string $path, array $allowedPrefixes): bool
    {
        $pathOnly = (string) (parse_url($path, PHP_URL_PATH) ?? '');
        $normalizedPath = rtrim($pathOnly !== '' ? $pathOnly : $path, '/');

        foreach ($allowedPrefixes as $prefix) {
            $normalizedPrefix = rtrim('/'.ltrim((string) $prefix, '/'), '/');
            if ($normalizedPrefix === '') {
                continue;
            }

            if ($normalizedPath === $normalizedPrefix || str_starts_with($normalizedPath, $normalizedPrefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
