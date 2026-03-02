<?php

namespace App\Http\Middleware;

use App\Services\ValuationRebuildStateService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventWritesDuringValuationRebuild
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->is('api/v1/settings/valuation-rebuild-cancel')
            || $request->is('api/v1/settings/valuation-rebuild-retry')
            || $request->is('api/v1/settings/valuation-reports/export-cancel')
            || $request->is('api/v1/settings/valuation-reports/export-retry')
        ) {
            return $next($request);
        }

        if (! $request->is('api/*')) {
            return $next($request);
        }

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return $next($request);
        }

        $state = app(ValuationRebuildStateService::class);
        if (! $state->isLocked($tenantId)) {
            return $next($request);
        }

        return response()->json([
            'code' => 'VALUATION_REBUILD_IN_PROGRESS',
            'message' => 'Write sementara dikunci karena proses rebuild valuation sedang berjalan.',
            'details' => [
                'tenant_id' => $tenantId,
            ],
        ], 423);
    }
}
