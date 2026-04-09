<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class BankAccountsTenantScopingTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_transactions_do_not_read_foreign_tenant_sale_data(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+bank-accounts-primary@example.test');
        [$foreignTenant] = $this->bootApiActor('owner', 'owner+bank-accounts-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $tenantFixture = $this->seedAccountingFixture($tenant, '1102', 'Bank Utama');
        $foreignFixture = $this->seedAccountingFixture($foreignTenant, '1102', 'Bank Asing');

        DB::table('payments')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $foreignFixture['sale_id'],
            'account_id' => $tenantFixture['bank_account_id'],
            'method' => 'bank_transfer',
            'amount' => 25000,
        ]);

        $response = $this->getJson('/api/v1/bank-accounts/'.$tenantFixture['bank_account_id'].'/transactions', $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('account.id', $tenantFixture['bank_account_id'])
            ->assertJsonCount(0, 'transactions');
    }

    public function test_transactions_return_404_for_foreign_tenant_account_route_param(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+bank-route-primary@example.test');
        [$foreignTenant] = $this->bootApiActor('owner', 'owner+bank-route-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $this->seedAccountingFixture($tenant, '1102', 'Bank Utama');
        $foreignFixture = $this->seedAccountingFixture($foreignTenant, '1102', 'Bank Asing');

        $this->getJson('/api/v1/bank-accounts/'.$foreignFixture['bank_account_id'].'/transactions', $this->tenantHeaders($tenant->id))
            ->assertStatus(404)
            ->assertJsonPath('message', 'Akun bank tidak ditemukan.');
    }

    private function seedAccountingFixture(Tenant $tenant, string $parentCode, string $parentName): array
    {
        $branchId = (string) Str::uuid();
        $customerId = (string) Str::uuid();
        $parentId = (string) Str::uuid();
        $bankAccountId = (string) Str::uuid();
        $saleId = (string) Str::uuid();

        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenant->id,
            'name' => 'Cabang Bank',
        ]);

        DB::table('customers')->insert([
            'id' => $customerId,
            'tenant_id' => $tenant->id,
            'name' => 'Pelanggan Bank',
            'email' => null,
            'phone' => null,
            'address' => null,
            'created_at' => now('UTC'),
        ]);

        DB::table('chart_of_accounts')->insert([
            [
                'id' => $parentId,
                'tenant_id' => $tenant->id,
                'code' => $parentCode,
                'name' => $parentName,
                'type' => 'asset',
                'parent_id' => null,
            ],
            [
                'id' => $bankAccountId,
                'tenant_id' => $tenant->id,
                'code' => '110201',
                'name' => $parentName.' Operasional',
                'type' => 'asset',
                'parent_id' => $parentId,
            ],
        ]);

        DB::table('account_mappings')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_account_id' => $bankAccountId,
            'cash_account_id' => $bankAccountId,
            'bank_account_id' => $bankAccountId,
            'inventory_account_id' => $bankAccountId,
            'cogs_account_id' => $bankAccountId,
            'payable_account_id' => $bankAccountId,
            'receivable_account_id' => $bankAccountId,
            'expense_adjustment_account_id' => $bankAccountId,
            'created_by' => null,
            'updated_by' => null,
        ]);

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'shift_id' => (string) Str::uuid(),
            'customer_id' => $customerId,
            'subtotal' => 25000,
            'discount' => 0,
            'tax' => 0,
            'total' => 25000,
            'status' => 'completed',
            'idempotency_key' => 'sale-'.Str::lower(Str::random(12)),
            'created_by' => null,
            'voided_by' => null,
            'created_at' => now('UTC'),
        ]);

        return [
            'parent_id' => $parentId,
            'bank_account_id' => $bankAccountId,
            'sale_id' => $saleId,
        ];
    }
}