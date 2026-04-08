<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantDefaultRoleSetupService
{
    /**
     * @return array<string, string>
     */
    public function ensure(string $tenantId): array
    {
        $definitions = [
            'Owner',
            'Admin',
            'Manager',
            'Kasir',
            'Staf Gudang',
        ];

        $existing = DB::table('roles')
            ->where('tenant_id', $tenantId)
            ->pluck('id', 'name')
            ->all();

        foreach ($definitions as $name) {
            if (! array_key_exists($name, $existing)) {
                $roleId = (string) Str::uuid();
                DB::table('roles')->insert([
                    'id' => $roleId,
                    'tenant_id' => $tenantId,
                    'name' => $name,
                ]);
                $existing[$name] = $roleId;
            }
        }

        return $existing;
    }

    public function assignNamedRole(string $tenantId, string $userId, string $roleName): void
    {
        $roles = $this->ensure($tenantId);
        $roleId = $roles[$roleName] ?? null;

        if (! $roleId) {
            return;
        }

        DB::table('user_roles')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->delete();

        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }
}
