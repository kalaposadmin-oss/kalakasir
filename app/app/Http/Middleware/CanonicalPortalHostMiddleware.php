<?php

namespace App\Http\Middleware;

use App\Support\WebPortalResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanonicalPortalHostMiddleware
{
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        if (! in_array($portal, ['pos', 'backoffice'], true)) {
            return $next($request);
        }

        $resolver = app(WebPortalResolver::class);
        $targetUrl = $resolver->canonicalPortalRequestUrl($portal, $request);

        if ($targetUrl === null) {
            return $next($request);
        }

        return redirect()->to($targetUrl, $request->isMethodSafe() ? 302 : 307);
    }
}