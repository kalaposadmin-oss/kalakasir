<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'code' => 'UNAUTHENTICATED',
                'message' => 'User tidak terautentikasi.',
                'details' => [],
            ], 401);
        }

        $tenantId = TenantContext::id();

        $roleName = DB::table('user_roles')
            ->join('roles', function ($join) {
                $join->on('user_roles.role_id', '=', 'roles.id')
                    ->on('user_roles.tenant_id', '=', 'roles.tenant_id');
            })
            ->where('user_roles.user_id', $user->id)
            ->when($tenantId, function ($query) use ($tenantId) {
                $query->where('user_roles.tenant_id', $tenantId);
            })
            ->value('roles.name');

        if (! $roleName) {
            return response()->json([
                'code' => 'ROLE_NOT_FOUND',
                'message' => 'Role pengguna tidak ditemukan.',
                'details' => [],
            ], 403);
        }

        $normalizedRole = $this->normalizeRoleName($roleName);
        $allowed = array_map([$this, 'normalizeRoleName'], $roles);

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

    private function normalizeRoleName(string $role): string
    {
        $role = strtolower(trim($role));

        return match ($role) {
            'owner', 'pemilik' => 'owner',
            'kasir', 'cashier' => 'cashier',
            'manager', 'supervisor' => 'supervisor',
            'admin', 'administrator' => 'admin',
            'staf gudang', 'warehouse' => 'warehouse',
            default => preg_replace('/\s+/', '-', $role),
        };
    }
}
