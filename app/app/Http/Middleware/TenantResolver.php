<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class TenantResolver
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('up')) {
            return $next($request);
        }

        $requiresTenant = $request->is('api/*');

        $tenant = $this->resolveTenant($request);
        $sanctumUser = config('auth.guards.sanctum') ? auth('sanctum')->user() : null;
        $authUser = $sanctumUser ?: $request->user();

        if ($requiresTenant && $authUser?->tenant_id) {
            $userTenant = Tenant::query()->where('id', $authUser->tenant_id)->first();

            if (! $userTenant) {
                return response()->json([
                    'code' => 'TENANT_NOT_RESOLVED',
                    'message' => 'Tenant user tidak ditemukan.',
                    'details' => [],
                ], 400);
            }

            if ($tenant && (string) $tenant->id !== (string) $userTenant->id) {
                return response()->json([
                    'code' => 'TENANT_MISMATCH',
                    'message' => 'Tenant context tidak sesuai dengan tenant user.',
                    'details' => [],
                ], 403);
            }

            $tenant = $userTenant;
        }

        if (! $tenant && $requiresTenant) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        if (! $tenant) {
            return $next($request);
        }

        TenantContext::set($tenant);

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        if (! Schema::hasTable('tenants')) {
            return null;
        }

        $tenantId = $request->header('X-Tenant-Id');

        if ($tenantId) {
            return Tenant::query()->where('id', $tenantId)->first();
        }

        $host = $request->getHost();

        if ($host) {
            $tenant = Tenant::query()->where('domain', $host)->first();

            if ($tenant) {
                return $tenant;
            }

            $subdomain = $this->extractSubdomain($host);

            if ($subdomain) {
                return Tenant::query()->where('subdomain', $subdomain)->first();
            }
        }

        return null;
    }

    private function extractSubdomain(string $host): ?string
    {
        $parts = explode('.', $host);

        if (count($parts) < 3) {
            return null;
        }

        return $parts[0];
    }
}
