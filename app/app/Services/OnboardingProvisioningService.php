<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OnboardingProvisioningService
{
    public function __construct(
        private readonly TenantDefaultRoleSetupService $roleSetup,
        private readonly TenantDefaultAccountingSetupService $accountingSetup,
        private readonly TenantDefaultOperationalSetupService $operationalSetup,
    ) {
    }

    /**
     * @param  array{name:string,email:string,password:string,phone?:string|null}  $userData
     * @param  array{store_name:string,operational_address?:string|null,business_category?:string|null,timezone?:string|null}  $companyData
     * @return array{0: Tenant, 1: User}
     */
    public function completeCompanyProfileStep(array $userData, array $companyData, ?string $existingTenantId = null): array
    {
        $timezone = $companyData['timezone'] ?? 'Asia/Jakarta';

        return DB::transaction(function () use ($companyData, $timezone, $userData, $existingTenantId) {
            $tenant = null;

            if ($existingTenantId !== null && trim($existingTenantId) !== '') {
                $tenant = Tenant::query()->find($existingTenantId);
            }

            if (! $tenant) {
                $hasAnyUsers = User::query()->exists();
                if (! $hasAnyUsers) {
                    $tenant = Tenant::query()->first();
                }
            }

            if ($tenant) {
                $tenant->update([
                    'name' => $companyData['store_name'],
                    'timezone' => $timezone,
                    'business_category' => $companyData['business_category'] ?? null,
                ]);
                $tenant->refresh();
            } else {
                $tenant = Tenant::query()->create([
                    'name' => $companyData['store_name'],
                    'timezone' => $timezone,
                    'inventory_valuation_method' => 'average',
                    'business_category' => $companyData['business_category'] ?? null,
                ]);
            }

            $owner = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('email', $userData['email'])
                ->first();

            if (! $owner) {
                $owner = User::query()->create([
                    'tenant_id' => $tenant->id,
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make($userData['password']),
                    'failed_login_attempts' => 0,
                ]);
            }

            $this->roleSetup->assignNamedRole($tenant->id, $owner->id, 'Owner');
            $this->operationalSetup->ensure(
                $tenant,
                $companyData['store_name'],
                $companyData['operational_address'] ?? null,
            );
            $this->accountingSetup->ensure($tenant->id, $owner->id);

            return [$tenant, $owner];
        });
    }
}