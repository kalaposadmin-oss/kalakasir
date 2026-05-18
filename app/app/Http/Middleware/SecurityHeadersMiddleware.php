<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $nonce = bin2hex(random_bytes(16));
        $formActionSources = implode(' ', $this->resolveFormActionSources($request));

        $this->applyNonceToHtmlResponse($response, $nonce);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($this->shouldSendCrossOriginIsolationHeaders($request)) {
            $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
            $headers->set('Cross-Origin-Resource-Policy', 'same-site');
        }

        $headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action {$formActionSources}",
            "frame-ancestors 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
        ]));

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function shouldSendCrossOriginIsolationHeaders(Request $request): bool
    {
        if ($request->isSecure()) {
            return true;
        }

        $host = strtolower((string) $request->getHost());

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * @return array<int, string>
     */
    private function resolveFormActionSources(Request $request): array
    {
        $sources = ["'self'"];
        $currentOrigin = $request->getSchemeAndHttpHost();

        if ($currentOrigin !== '') {
            $sources[] = $currentOrigin;
        }

        $configuredOrigins = [config('app.url')];
        $requestScheme = $request->getScheme();
        $requestPort = $request->getPort();
        $portSuffix = in_array($requestPort, [80, 443], true) ? '' : ':'.$requestPort;

        foreach (array_merge(
            (array) config('platform.portals.pos_hosts', []),
            (array) config('platform.portals.backoffice_hosts', []),
        ) as $host) {
            $host = strtolower(trim((string) $host));
            if ($host === '') {
                continue;
            }

            $configuredOrigins[] = $requestScheme.'://'.$host.$portSuffix;
        }

        foreach ($configuredOrigins as $origin) {
            $normalizedOrigin = $this->normalizeOrigin($origin);
            if ($normalizedOrigin === null || in_array($normalizedOrigin, $sources, true)) {
                continue;
            }

            $sources[] = $normalizedOrigin;
        }

        return $sources;
    }

    private function normalizeOrigin(mixed $origin): ?string
    {
        if (! is_string($origin)) {
            return null;
        }

        $origin = trim($origin);
        if ($origin === '') {
            return null;
        }

        $parts = parse_url($origin);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $normalized = strtolower($parts['scheme']).'://'.strtolower($parts['host']);
        if (isset($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        return $normalized;
    }

    private function applyNonceToHtmlResponse(Response $response, string $nonce): void
    {
        $contentType = (string) $response->headers->get('Content-Type', '');
        if (! str_contains(strtolower($contentType), 'text/html')) {
            return;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return;
        }

        $updated = preg_replace('/<script(?![^>]*\bnonce=)([^>]*)>/i', '<script nonce="'.$nonce.'"$1>', $content);
        if (is_string($updated)) {
            $response->setContent($updated);
        }
    }
}
