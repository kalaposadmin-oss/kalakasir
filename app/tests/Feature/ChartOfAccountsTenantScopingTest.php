<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class ChartOfAccountsTenantScopingTest extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    public function test_chart_of_accounts_index_store_update_and_show_are_tenant_scoped(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'chart-accounts-scope@example.test', 'Chart Accounts Scope');

        $tenantParentId = (string) Str::uuid();
        $tenantCashId = (string) Str::uuid();
        $tenantInventoryId = (string) Str::uuid();

        DB::table('chart_of_accounts')->insert([
            [
                'id' => $tenantParentId,
                'tenant_id' => $tenant->id,
                'code' => '1000',
                'name' => 'Aset Tenant Aktif',
                'type' => 'asset',
                'parent_id' => null,
            ],
            [
                'id' => $tenantCashId,
                'tenant_id' => $tenant->id,
                'code' => '1101',
                'name' => 'Kas Tenant Aktif',
                'type' => 'asset',
                'parent_id' => $tenantParentId,
            ],
            [
                'id' => $tenantInventoryId,
                'tenant_id' => $tenant->id,
                'code' => '1301',
                'name' => 'Persediaan Tenant Aktif',
                'type' => 'asset',
                'parent_id' => $tenantParentId,
            ],
        ]);

        DB::table('account_mappings')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_account_id' => $tenantParentId,
            'cash_account_id' => $tenantCashId,
            'bank_account_id' => $tenantCashId,
            'inventory_account_id' => $tenantInventoryId,
            'cogs_account_id' => $tenantInventoryId,
            'payable_account_id' => $tenantParentId,
            'receivable_account_id' => $tenantParentId,
            'expense_adjustment_account_id' => $tenantParentId,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        [$otherTenant] = $this->bootApiActor('admin', 'chart-accounts-scope-other@example.test', 'Chart Accounts Scope Other');
        $this->actingAs($user, 'sanctum');

        $foreignParentId = (string) Str::uuid();
        $foreignCashId = (string) Str::uuid();

        DB::table('chart_of_accounts')->insert([
            [
                'id' => $foreignParentId,
                'tenant_id' => $otherTenant->id,
                'code' => '9000',
                'name' => 'Aset Tenant Lain',
                'type' => 'asset',
                'parent_id' => null,
            ],
            [
                'id' => $foreignCashId,
                'tenant_id' => $otherTenant->id,
                'code' => '9101',
                'name' => 'Kas Tenant Lain',
                'type' => 'asset',
                'parent_id' => $foreignParentId,
            ],
        ]);

        DB::table('account_mappings')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'sale_account_id' => $foreignParentId,
            'cash_account_id' => $foreignCashId,
            'bank_account_id' => $foreignCashId,
            'inventory_account_id' => $foreignParentId,
            'cogs_account_id' => $foreignParentId,
            'payable_account_id' => $foreignParentId,
            'receivable_account_id' => $foreignParentId,
            'expense_adjustment_account_id' => $foreignParentId,
            'created_by' => null,
            'updated_by' => null,
        ]);

        $indexResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/chart-of-accounts');

        $indexResponse->assertOk()
            ->assertJsonCount(3, 'accounts')
            ->assertJsonCount(3, 'mapping_accounts')
            ->assertJsonPath('mapping.cash_account_id', $tenantCashId)
            ->assertJsonMissing(['id' => $foreignParentId])
            ->assertJsonMissing(['id' => $foreignCashId]);

        $storeResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/chart-of-accounts', [
                'code' => '1200',
                'name' => 'Piutang Baru',
                'type' => 'asset',
                'parent_id' => $foreignParentId,
            ]);

        $storeResponse->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        $updateResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/api/v1/chart-of-accounts/'.$tenantCashId, [
                'name' => 'Kas Update',
                'parent_id' => $foreignParentId,
            ]);

        $updateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        $showResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/chart-of-accounts/'.$foreignParentId);

        $showResponse->assertStatus(404);
    }
}