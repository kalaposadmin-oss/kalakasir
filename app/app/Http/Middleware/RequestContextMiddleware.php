<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) ($request->header('X-Request-Id') ?: Str::uuid());
        $request->headers->set('X-Request-Id', $requestId);

        $start = microtime(true);

        Log::withContext([
            'request_id' => $requestId,
            'tenant_id' => TenantContext::id(),
            'user_id' => $request->user()?->id,
            'route' => $request->path(),
            'method' => $request->method(),
        ]);

        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $response->headers->set('X-Request-Id', $requestId);

        Log::info('request.completed', [
            'event_type' => 'request.completed',
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'reference_type' => 'http_request',
            'reference_id' => $requestId,
        ]);

        return $response;
    }
}
