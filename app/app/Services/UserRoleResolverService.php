<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class UserRoleResolverService
{
    public function resolveNormalizedRole(string $tenantId, ?string $userId): ?string
    {
        if (! $tenantId || ! $userId) {
            return null;
        }

        $roleName = DB::table('user_roles')
            ->join('roles', function ($join) {
                $join->on('user_roles.role_id', '=', 'roles.id')
                    ->on('user_roles.tenant_id', '=', 'roles.tenant_id');
            })
            ->where('user_roles.tenant_id', $tenantId)
            ->where('user_roles.user_id', $userId)
            ->value('roles.name');

        if (! $roleName) {
            return null;
        }

        return $this->normalize((string) $roleName);
    }

    public function normalize(string $role): string
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
