<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformSuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::now('UTC');
        $email = env('PLATFORM_SUPERADMIN_EMAIL', 'superadmin@admin.com');
        $password = env('PLATFORM_SUPERADMIN_PASSWORD', 'superadmin123');
        $name = env('PLATFORM_SUPERADMIN_NAME', 'Platform Superadmin');

        $tenant = DB::table('tenants')->orderBy('created_at')->first();
        if (! $tenant) {
            $tenantId = (string) Str::uuid();
            DB::table('tenants')->insert([
                'id' => $tenantId,
                'name' => 'Platform HQ',
                'timezone' => 'UTC',
                'domain' => null,
                'subdomain' => null,
                'logo_url' => null,
                'created_at' => $now,
            ]);
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        }

        $user = DB::table('users')->where('email', $email)->orderByDesc('created_at')->first();
        if (! $user) {
            $userId = (string) Str::uuid();
            DB::table('users')->insert([
                'id' => $userId,
                'tenant_id' => $tenant->id,
                'name' => $name,
                'email' => $email,
                'email_verified_at' => $now,
                'password' => Hash::make($password),
                'failed_login_attempts' => 0,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            $user = DB::table('users')->where('id', $userId)->first();
        }

        $roleId = DB::table('roles')
            ->where('tenant_id', $user->tenant_id)
            ->where('name', 'superadmin')
            ->value('id');

        if (! $roleId) {
            $roleId = (string) Str::uuid();
            DB::table('roles')->insert([
                'id' => $roleId,
                'tenant_id' => $user->tenant_id,
                'name' => 'superadmin',
            ]);
        }

        $exists = DB::table('user_roles')
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('role_id', $roleId)
            ->exists();

        if (! $exists) {
            DB::table('user_roles')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'role_id' => $roleId,
            ]);
        }
    }
}