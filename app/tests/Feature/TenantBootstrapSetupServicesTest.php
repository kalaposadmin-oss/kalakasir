<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\OnboardingProvisioningService;
use App\Services\TenantDefaultAccountingSetupService;
use App\Services\TenantDefaultOperationalSetupService;
use App\Services\TenantDefaultRegisterSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantBootstrapSetupServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_setup_seeds_default_accounts_and_mapping_idempotently(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Accounting',
            'timezone' => 'UTC',
            'plan' => 'S',
        ]);
        $actorId = (string) Str::uuid();

        $service = app(TenantDefaultAccountingSetupService::class);
        $service->ensure($tenant->id, $actorId);
        $service->ensure($tenant->id, $actorId);

        $this->assertSame(9, DB::table('chart_of_accounts')->where('tenant_id', $tenant->id)->count());

        $mapping = DB::table('account_mappings')->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($mapping);
        $this->assertNotNull($mapping->sale_account_id ?? null);
        $this->assertNotNull($mapping->cash_account_id ?? null);
        $this->assertNotNull($mapping->inventory_account_id ?? null);
        $this->assertSame($actorId, $mapping->created_by ?? null);
    }

    public function test_operational_setup_creates_default_branch_location_and_updates_tenant_pointer(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Ops',
            'timezone' => 'UTC',
            'plan' => 'S',
        ]);

        [$branch, $location] = app(TenantDefaultOperationalSetupService::class)->ensure(
            $tenant,
            'Cabang Utama',
            'Jalan Merdeka 1',
        );

        $tenant->refresh();

        $this->assertSame('Cabang Utama', $branch->name);
        $this->assertSame('Jalan Merdeka 1', $branch->address);
        $this->assertSame($branch->id, $location->branch_id);
        $this->assertTrue((bool) $location->is_default);
        $this->assertSame($location->id, $tenant->default_location_id);

        [$sameBranch, $sameLocation] = app(TenantDefaultOperationalSetupService::class)->ensure(
            $tenant,
            'Cabang Utama 2',
            'Jalan Merdeka 2',
        );

        $this->assertSame($branch->id, $sameBranch->id);
        $this->assertSame($location->id, $sameLocation->id);
        $this->assertSame('Cabang Utama 2', $sameBranch->fresh()->name);
        $this->assertSame('Jalan Merdeka 2', $sameBranch->fresh()->address);
    }

    public function test_register_setup_creates_default_active_register_idempotently(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Register',
            'timezone' => 'UTC',
            'plan' => 'S',
        ]);

        $service = app(TenantDefaultRegisterSetupService::class);
        $service->ensure($tenant);
        $service->ensure($tenant);

        $registers = DB::table('registers')
            ->where('tenant_id', $tenant->id)
            ->get();

        $locationId = (string) DB::table('locations')
            ->where('tenant_id', $tenant->id)
            ->where('is_default', true)
            ->value('id');

        $this->assertCount(1, $registers);
        $this->assertSame('active', $registers[0]->status);
        $this->assertSame($locationId, $registers[0]->location_id);
    }

    public function test_onboarding_provisioning_creates_owner_and_bootstraps_defaults(): void
    {
        [$tenant, $owner] = app(OnboardingProvisioningService::class)->completeCompanyProfileStep(
            [
                'name' => 'Owner Baru',
                'email' => 'owner-baru@example.test',
                'password' => 'secret123',
                'phone' => '0812345678',
            ],
            [
                'store_name' => 'Toko Baru',
                'operational_address' => 'Jl. Toko Baru 123',
                'business_category' => 'retail',
                'timezone' => 'Asia/Jakarta',
            ],
        );

        $this->assertSame('Toko Baru', $tenant->name);
        $this->assertSame('Asia/Jakarta', $tenant->timezone);
        $this->assertSame('retail', $tenant->business_category);
        $this->assertSame($tenant->id, $owner->tenant_id);
        $this->assertTrue(Hash::check('secret123', (string) $owner->password));

        $this->assertDatabaseHas('roles', [
            'tenant_id' => $tenant->id,
            'name' => 'Owner',
        ]);
        $this->assertDatabaseHas('user_roles', [
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('branches', [
            'tenant_id' => $tenant->id,
            'name' => 'Toko Baru',
        ]);
        $this->assertDatabaseHas('locations', [
            'tenant_id' => $tenant->id,
            'name' => 'Lokasi Utama',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('account_mappings', [
            'tenant_id' => $tenant->id,
            'created_by' => $owner->id,
        ]);
        $this->assertDatabaseHas('registers', [
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'name' => 'Register Utama',
        ]);
    }
}
