<?php

namespace App\Http\Middleware;

use App\Services\UserRoleResolverService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'code' => 'UNAUTHENTICATED',
                'message' => 'User tidak terautentikasi.',
                'details' => [],
            ], 401);
        }

        $tenantId = TenantContext::id();

        $resolver = app(UserRoleResolverService::class);
        $normalizedRole = $resolver->resolveNormalizedRole((string) ($tenantId ?: $user->tenant_id), (string) $user->id);

        if (! $normalizedRole && filled($user->role ?? null)) {
            $normalizedRole = $resolver->normalize((string) $user->role);
        }

        if (! $normalizedRole) {
            return response()->json([
                'code' => 'ROLE_NOT_FOUND',
                'message' => 'Role pengguna tidak ditemukan.',
                'details' => [],
            ], 403);
        }

        $allowed = array_map([$resolver, 'normalize'], $roles);

        if (! in_array($normalizedRole, $allowed, true)) {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Akses ditolak untuk role ini.',
                'details' => [
                    'required' => $allowed,
                    'current' => $normalizedRole,
                ],
            ], 403);
        }

        return $next($request);
    }
}
