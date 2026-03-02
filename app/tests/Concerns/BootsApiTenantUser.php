<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

trait BootsApiTenantUser
{
    protected function bootApiActor(string $role, string $email, string $name = 'User A'): array
    {
        config([
            'auth.guards.sanctum' => [
                'driver' => 'session',
                'provider' => 'users',
            ],
            'app.max_backdate_days' => 1,
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-27 10:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Tenant A',
            'timezone' => 'UTC',
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'password' => 'secret',
        ]);

        $this->actingAs($user, 'sanctum');

        $roleId = (string) Str::uuid();
        \DB::table('roles')->insert([
            'id' => $roleId,
            'tenant_id' => $tenant->id,
            'name' => $role,
        ]);

        \DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $roleId,
        ]);

        return [$tenant, $user];
    }

    protected function tenantHeaders(string $tenantId): array
    {
        return [
            'X-Tenant-Id' => $tenantId,
        ];
    }

    protected function resetApiClock(): void
    {
        CarbonImmutable::setTestNow();
    }
}
