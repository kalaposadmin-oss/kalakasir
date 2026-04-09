<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserRoleResolverService
{
    public function resolveNormalizedRole(string $tenantId, ?string $userId): ?string
    {
        if (! $tenantId || ! $userId) {
            return null;
        }

        $roleName = $this->resolveAssignedRoleName($tenantId, $userId);

        if (! $roleName) {
            $roleName = $this->repairLegacyRoleAssignment($tenantId, $userId);
        }

        if (! $roleName) {
            return null;
        }

        return $this->normalize((string) $roleName);
    }

    private function resolveAssignedRoleName(string $tenantId, string $userId): ?string
    {
        return DB::table('user_roles')
            ->join('roles', function ($join) {
                $join->on('user_roles.role_id', '=', 'roles.id')
                    ->on('user_roles.tenant_id', '=', 'roles.tenant_id');
            })
            ->where('user_roles.tenant_id', $tenantId)
            ->where('user_roles.user_id', $userId)
            ->value('roles.name');
    }

    private function repairLegacyRoleAssignment(string $tenantId, string $userId): ?string
    {
        $hasLegacyRoleColumn = Schema::hasColumn('users', 'role');
        $columns = ['id'];
        if ($hasLegacyRoleColumn) {
            $columns[] = 'role';
        }

        $user = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('id', $userId)
            ->first($columns);

        if (! $user) {
            return null;
        }

        $roleName = $this->inferLegacyRoleName($tenantId, $userId, $hasLegacyRoleColumn ? (string) ($user->role ?? '') : '');
        if (! $roleName) {
            return null;
        }

        app(TenantDefaultRoleSetupService::class)->assignNamedRole($tenantId, $userId, $roleName);

        if ($hasLegacyRoleColumn) {
            DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('id', $userId)
                ->update([
                    'role' => $this->normalize($roleName),
                    'updated_at' => now('UTC'),
                ]);
        }

        return $roleName;
    }

    private function inferLegacyRoleName(string $tenantId, string $userId, string $legacyRole): ?string
    {
        $normalizedLegacyRole = $this->normalize($legacyRole);

        $mappedRoleName = match ($normalizedLegacyRole) {
            'owner' => 'Owner',
            'admin' => 'Admin',
            'supervisor' => 'Manager',
            'cashier' => 'Kasir',
            'warehouse' => 'Staf Gudang',
            default => null,
        };

        if ($mappedRoleName) {
            return $mappedRoleName;
        }

        if ($this->shouldPromoteFirstTenantUserToOwner($tenantId, $userId)) {
            return 'Owner';
        }

        return null;
    }

    private function shouldPromoteFirstTenantUserToOwner(string $tenantId, string $userId): bool
    {
        $ownerExists = DB::table('user_roles')
            ->join('roles', function ($join) {
                $join->on('user_roles.role_id', '=', 'roles.id')
                    ->on('user_roles.tenant_id', '=', 'roles.tenant_id');
            })
            ->where('user_roles.tenant_id', $tenantId)
            ->whereIn(DB::raw('LOWER(roles.name)'), ['owner', 'pemilik'])
            ->exists();

        if ($ownerExists) {
            return false;
        }

        $firstUserId = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('id');

        return (string) $firstUserId === $userId;
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
