<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class TenantResolver
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('up')) {
            return $next($request);
        }

        $sanctumUser = config('auth.guards.sanctum') ? auth('sanctum')->user() : null;
        $authUser = $sanctumUser ?: $request->user();
        $requiresTenant = $this->requiresTenant($request);

        $tenant = null;

        if ($authUser && $this->shouldResolveAuthenticatedTenant($request)) {
            $tenant = $this->resolveAuthenticatedTenant($request, $authUser);
        } elseif (! $authUser) {
            $tenant = $this->resolveTenantFromRequest($request);
        }

        if ($tenant instanceof Response) {
            return $tenant;
        }

        if ($requiresTenant && $authUser && $this->userTenantIds($authUser) !== [] && ! $tenant) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant user tidak ditemukan.',
                'details' => [],
            ], 400);
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

    private function resolveAuthenticatedTenant(Request $request, mixed $user): Tenant|Response|null
    {
        if (! Schema::hasTable('tenants')) {
            return null;
        }

        $allowedTenantIds = $this->userTenantIds($user);
        $requestedTenantId = $request->header('X-Tenant-Id');

        if ($requestedTenantId && ! in_array((string) $requestedTenantId, $allowedTenantIds, true)) {
            return response()->json([
                'code' => 'TENANT_MISMATCH',
                'message' => 'Tenant context tidak sesuai dengan tenant user.',
                'details' => [],
            ], 403);
        }

        $tenantId = $allowedTenantIds[0] ?? null;

        if (! $tenantId) {
            return null;
        }

        return Tenant::query()->where('id', $tenantId)->first();
    }

    private function userTenantIds(mixed $user): array
    {
        $tenantIds = [
            $user->current_tenant_id ?? null,
            $user->tenant_id ?? null,
        ];

        return collect($tenantIds)
            ->filter(fn ($tenantId) => filled($tenantId))
            ->map(fn ($tenantId) => (string) $tenantId)
            ->unique()
            ->values()
            ->all();
    }

    private function requiresTenant(Request $request): bool
    {
        return $request->is('api/*')
            && ! $request->is('api/platform*')
            && ! $request->is('api/v1/platform*');
    }

    private function shouldResolveAuthenticatedTenant(Request $request): bool
    {
        if ($request->is('api/platform*') || $request->is('api/v1/platform*')) {
            return false;
        }

        if ($request->is('superadmin*') || $request->is('platform*')) {
            return false;
        }

        return $this->requiresTenant($request)
            || $request->is('pos*')
            || $request->is('backoffice*')
            || $request->is('admin*');
    }

    private function resolveTenantFromRequest(Request $request): ?Tenant
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
