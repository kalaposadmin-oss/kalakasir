<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantDefaultRegisterSetupService
{
    public function __construct(
        private readonly TenantDefaultOperationalSetupService $operationalSetup,
    ) {
    }

    public function ensure(Tenant $tenant, ?string $registerName = null): void
    {
        [$branch, $location] = $this->operationalSetup->ensure($tenant);

        $activeForBranch = DB::table('registers')
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branch->id)
            ->where('status', 'active')
            ->exists();

        if ($activeForBranch) {
            return;
        }

        $fallbackRegister = DB::table('registers')
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at')
            ->first();

        if ($fallbackRegister) {
            DB::table('registers')
                ->where('id', $fallbackRegister->id)
                ->update([
                    'branch_id' => $branch->id,
                    'location_id' => $location->id,
                    'name' => trim((string) ($fallbackRegister->name ?: ($registerName ?: 'Register Utama'))),
                    'status' => 'active',
                    'updated_at' => now('UTC'),
                ]);

            return;
        }

        DB::table('registers')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'location_id' => $location->id,
            'name' => $registerName ?: 'Register Utama',
            'device_uid' => 'default-register-'.Str::lower(Str::random(12)),
            'status' => 'active',
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }
}
