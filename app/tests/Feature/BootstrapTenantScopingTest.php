<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class BootstrapTenantScopingTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    public function test_bootstrap_excludes_foreign_tenant_pos_inventory_data(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'bootstrap-scope@example.test', 'Bootstrap Scope');

        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $categoryId = (string) Str::uuid();
        $customerId = (string) Str::uuid();
        $priceTierId = (string) Str::uuid();

        DB::table('categories')->insert([
            'id' => $categoryId,
            'tenant_id' => $tenant->id,
            'name' => 'Kategori Aktif',
            'slug' => 'kategori-aktif',
        ]);

        DB::table('customers')->insert([
            'id' => $customerId,
            'tenant_id' => $tenant->id,
            'name' => 'Customer Aktif',
            'phone' => null,
            'email' => null,
            'address' => null,
            'created_at' => now('UTC'),
        ]);

        DB::table('products')
            ->where('id', $fixture['product_id'])
            ->update([
                'name' => 'Produk Bootstrap Aktif',
                'sku' => 'BOOT-AKTIF',
                'category_id' => $categoryId,
            ]);

        DB::table('product_price_tiers')->insert([
            'id' => $priceTierId,
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'label' => 'Tier Aktif',
            'min_qty' => 5,
            'price' => 9000,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $otherTenantId = (string) Str::uuid();
        $otherUserId = (string) Str::uuid();
        $otherBranchId = (string) Str::uuid();
        $otherLocationId = (string) Str::uuid();
        $otherShiftId = (string) Str::uuid();
        $otherRegisterId = (string) Str::uuid();
        $otherCategoryId = (string) Str::uuid();
        $otherCustomerId = (string) Str::uuid();
        $otherProductId = (string) Str::uuid();
        $otherTierId = (string) Str::uuid();
        $otherAccountMappingId = (string) Str::uuid();
        $foreignAccountIds = [
            (string) Str::uuid(),
            (string) Str::uuid(),
            (string) Str::uuid(),
            (string) Str::uuid(),
            (string) Str::uuid(),
            (string) Str::uuid(),
            (string) Str::uuid(),
            (string) Str::uuid(),
        ];

        DB::table('tenants')->insert([
            'id' => $otherTenantId,
            'name' => 'Tenant Lain Bootstrap',
            'timezone' => 'UTC',
            'created_at' => now('UTC'),
        ]);

        DB::table('users')->insert([
            'id' => $otherUserId,
            'tenant_id' => $otherTenantId,
            'name' => 'Kasir Tenant Lain',
            'email' => 'bootstrap-foreign-user@example.test',
            'password' => 'secret',
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        DB::table('branches')->insert([
            'id' => $otherBranchId,
            'tenant_id' => $otherTenantId,
            'name' => 'Branch Tenant Lain',
        ]);

        DB::table('locations')->insert([
            'id' => $otherLocationId,
            'tenant_id' => $otherTenantId,
            'branch_id' => $otherBranchId,
            'name' => 'Lokasi Tenant Lain',
            'is_default' => true,
        ]);

        DB::table('shifts')->insert([
            'id' => $otherShiftId,
            'tenant_id' => $otherTenantId,
            'branch_id' => $otherBranchId,
            'user_id' => $otherUserId,
            'opened_at' => CarbonImmutable::now('UTC')->subHour(),
            'closed_at' => null,
            'opening_cash' => 0,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        DB::table('registers')->insert([
            'id' => $otherRegisterId,
            'tenant_id' => $otherTenantId,
            'branch_id' => $otherBranchId,
            'location_id' => $otherLocationId,
            'name' => 'Register Tenant Lain',
            'device_uid' => 'foreign-bootstrap-register',
            'status' => 'active',
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('categories')->insert([
            'id' => $otherCategoryId,
            'tenant_id' => $otherTenantId,
            'name' => 'Kategori Tenant Lain',
            'slug' => 'kategori-tenant-lain',
        ]);

        DB::table('customers')->insert([
            'id' => $otherCustomerId,
            'tenant_id' => $otherTenantId,
            'name' => 'Customer Tenant Lain',
            'phone' => null,
            'email' => null,
            'address' => null,
            'created_at' => now('UTC'),
        ]);

        DB::table('products')->insert([
            'id' => $otherProductId,
            'tenant_id' => $otherTenantId,
            'sku' => 'BOOT-LAWAN',
            'name' => 'Produk Bootstrap Tenant Lain',
            'price' => 22000,
            'hpp_average' => 11000,
            'image_url' => null,
            'category_id' => $otherCategoryId,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => $otherUserId,
            'updated_by' => $otherUserId,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        DB::table('product_price_tiers')->insert([
            'id' => $otherTierId,
            'tenant_id' => $otherTenantId,
            'product_id' => $otherProductId,
            'label' => 'Tier Tenant Lain',
            'min_qty' => 10,
            'price' => 20000,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('inventory_balances')->insert([
            'tenant_id' => $otherTenantId,
            'location_id' => $otherLocationId,
            'product_id' => $otherProductId,
            'qty_on_hand' => 999,
        ]);

        DB::table('chart_of_accounts')->insert([
            [
                'id' => $foreignAccountIds[0],
                'tenant_id' => $otherTenantId,
                'code' => '4101X',
                'name' => 'foreign_sale_account',
                'type' => 'asset',
                'parent_id' => null,
            ],
            [
                'id' => $foreignAccountIds[1],
                'tenant_id' => $otherTenantId,
                'code' => '1101X',
                'name' => 'foreign_cash_account',
                'type' => 'asset',
                'parent_id' => null,
            ],
            [
                'id' => $foreignAccountIds[2],
                'tenant_id' => $otherTenantId,
                'code' => '1102X',
                'name' => 'foreign_bank_account',
                'type' => 'asset',
                'parent_id' => null,
            ],
            [
                'id' => $foreignAccountIds[3],
                'tenant_id' => $otherTenantId,
                'code' => '1301X',
                'name' => 'foreign_inventory_account',
                'type' => 'asset',
                'parent_id' => null,
            ],
            [
                'id' => $foreignAccountIds[4],
                'tenant_id' => $otherTenantId,
                'code' => '5101X',
                'name' => 'foreign_cogs_account',
                'type' => 'asset',
                'parent_id' => null,
            ],
            [
                'id' => $foreignAccountIds[5],
                'tenant_id' => $otherTenantId,
                'code' => '2101X',
                'name' => 'foreign_payable_account',
                'type' => 'asset',
                'parent_id' => null,
            ],
            [
                'id' => $foreignAccountIds[6],
                'tenant_id' => $otherTenantId,
                'code' => '1201X',
                'name' => 'foreign_receivable_account',
                'type' => 'asset',
                'parent_id' => null,
            ],
            [
                'id' => $foreignAccountIds[7],
                'tenant_id' => $otherTenantId,
                'code' => '6101X',
                'name' => 'foreign_expense_adjustment_account',
                'type' => 'asset',
                'parent_id' => null,
            ],
        ]);

        DB::table('account_mappings')->insert([
            'id' => $otherAccountMappingId,
            'tenant_id' => $otherTenantId,
            'sale_account_id' => $foreignAccountIds[0],
            'cash_account_id' => $foreignAccountIds[1],
            'bank_account_id' => $foreignAccountIds[2],
            'inventory_account_id' => $foreignAccountIds[3],
            'cogs_account_id' => $foreignAccountIds[4],
            'payable_account_id' => $foreignAccountIds[5],
            'receivable_account_id' => $foreignAccountIds[6],
            'expense_adjustment_account_id' => $foreignAccountIds[7],
            'created_by' => $otherUserId,
            'updated_by' => $otherUserId,
        ]);

        $response = $this->actingAs($user)->withHeaders([
            'X-Tenant-Id' => $tenant->id,
        ])->getJson('/api/v1/bootstrap');

        $response->assertOk()
            ->assertJsonPath('tenant.id', $tenant->id)
            ->assertJsonPath('categories.0.id', $categoryId)
            ->assertJsonPath('registers.0.location_id', $fixture['location_id'])
            ->assertJsonPath('shifts.0.id', $fixture['shift_id'])
            ->assertJsonPath('catalog.products_endpoint', '/api/v1/products?lite=1')
            ->assertJsonPath('catalog.customers_endpoint', '/api/v1/customers?lite=1');

        $this->assertSame('1', $response->headers->get('X-Pos-Catalog-Lazy'));
        $this->assertGreaterThan(0, (int) $response->headers->get('X-Pos-Bootstrap-Bytes'));

        $payload = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Produk Bootstrap Tenant Lain', $payload);
        $this->assertStringNotContainsString('BOOT-LAWAN', $payload);
        $this->assertStringNotContainsString('Lokasi Tenant Lain', $payload);
        $this->assertStringNotContainsString('Register Tenant Lain', $payload);
        $this->assertStringNotContainsString('Customer Tenant Lain', $payload);
        $this->assertStringNotContainsString('Kategori Tenant Lain', $payload);
        $this->assertStringNotContainsString('foreign_sale_account', $payload);
        $this->assertStringNotContainsString('Produk Bootstrap Aktif', $payload);
        $this->assertStringNotContainsString('Customer Aktif', $payload);
    }
}