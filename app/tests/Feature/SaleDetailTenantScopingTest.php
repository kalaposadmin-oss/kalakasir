<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class SaleDetailTenantScopingTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_sale_detail_excludes_foreign_tenant_children_and_product_lookup_data(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'sale-detail-scope@example.test', 'Admin Sale Detail');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Admin Other Detail', 'sale-detail-other@example.test');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);
        DB::table('products')
            ->where('tenant_id', $otherTenant->id)
            ->where('id', $otherFixture['product_id'])
            ->update([
                'name' => 'Produk Tenant Lain',
                'sku' => 'SKU-OTHER-001',
            ]);

        $saleId = (string) Str::uuid();
        $createdAt = CarbonImmutable::parse('2026-02-27 10:00:00', 'UTC');

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 15000,
            'discount' => 1000,
            'tax' => 0,
            'total' => 14000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'void_approved_by' => null,
            'void_reason' => null,
            'voided_at' => null,
            'created_at' => $createdAt,
            'transaction_date' => '2026-02-27',
        ]);

        DB::table('sale_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleId,
                'product_id' => $otherFixture['product_id'],
                'product_variant_id' => null,
                'qty' => 1,
                'price' => 15000,
                'discount' => 500,
                'hpp_snapshot' => 5000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $otherTenant->id,
                'sale_id' => $saleId,
                'product_id' => $otherFixture['product_id'],
                'product_variant_id' => null,
                'qty' => 9,
                'price' => 99999,
                'discount' => 999,
                'hpp_snapshot' => 999,
            ],
        ]);

        DB::table('payments')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleId,
                'account_id' => $fixture['cash_account_id'],
                'method' => 'cash',
                'amount' => 14000,
                'payment_method_id' => $fixture['payment_method_id'],
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $otherTenant->id,
                'sale_id' => $saleId,
                'account_id' => $otherFixture['cash_account_id'],
                'method' => 'bank',
                'amount' => 75000,
                'payment_method_id' => $otherFixture['payment_method_id'],
            ],
        ]);

        DB::table('discounts')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleId,
                'type' => 'manual',
                'value' => 1000,
                'notes' => json_encode(['label' => 'diskon lokal']),
                'created_at' => $createdAt,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $otherTenant->id,
                'sale_id' => $saleId,
                'type' => 'promo',
                'value' => 7777,
                'notes' => json_encode(['label' => 'diskon tenant lain']),
                'created_at' => $createdAt->addMinute(),
            ],
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/sales/'.$saleId);

        $response->assertOk()
            ->assertJsonPath('sale.id', $saleId)
            ->assertJsonPath('sale.branch_id', $fixture['branch_id'])
            ->assertJsonPath('sale.shift_id', $fixture['shift_id'])
            ->assertJsonPath('sale.register_id', $fixture['register_id'])
            ->assertJsonPath('sale.cashier_id', $user->id)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.qty', 1)
            ->assertJsonPath('items.0.price', 15000)
            ->assertJsonPath('items.0.discount', 500)
            ->assertJsonPath('items.0.line_total', 14500)
            ->assertJsonPath('items.0.product_name', null)
            ->assertJsonPath('items.0.product_sku', null)
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.id', DB::table('payments')->where('tenant_id', $tenant->id)->where('sale_id', $saleId)->value('id'))
            ->assertJsonPath('payments.0.account_id', $fixture['cash_account_id'])
            ->assertJsonPath('payments.0.payment_method_id', $fixture['payment_method_id'])
            ->assertJsonPath('payments.0.method', 'cash')
            ->assertJsonPath('payments.0.amount', 14000)
            ->assertJsonCount(1, 'discounts')
            ->assertJsonPath('discounts.0.type', 'manual')
            ->assertJsonPath('discounts.0.value', 1000)
            ->assertJsonPath('discounts.0.notes.label', 'diskon lokal')
            ->assertJsonMissing(['method' => 'bank', 'amount' => 75000])
            ->assertJsonMissing(['type' => 'promo', 'value' => 7777.0])
            ->assertJsonMissing(['product_name' => 'Produk Tenant Lain'])
            ->assertJsonMissing(['product_sku' => 'SKU-OTHER-001']);
    }

    public function test_sale_detail_rejects_foreign_tenant_sale_lookup(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'sale-detail-lookup@example.test', 'Admin Sale Lookup');

        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Admin Other Lookup', 'sale-detail-lookup-other@example.test');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);
        $foreignSaleId = (string) Str::uuid();

        DB::table('sales')->insert([
            'id' => $foreignSaleId,
            'tenant_id' => $otherTenant->id,
            'branch_id' => $otherFixture['branch_id'],
            'shift_id' => $otherFixture['shift_id'],
            'register_id' => $otherFixture['register_id'],
            'customer_id' => null,
            'subtotal' => 9000,
            'discount' => 0,
            'tax' => 0,
            'total' => 9000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $otherUser->id,
            'voided_by' => null,
            'void_approved_by' => null,
            'void_reason' => null,
            'voided_at' => null,
            'created_at' => CarbonImmutable::parse('2026-02-27 11:00:00', 'UTC'),
            'transaction_date' => '2026-02-27',
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/sales/'.$foreignSaleId)
            ->assertNotFound();
    }

    private function createOtherTenantActor(string $name, string $email): array
    {
        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Lain',
            'timezone' => 'UTC',
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'password' => 'secret',
        ]);

        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'id' => $roleId,
            'tenant_id' => $tenant->id,
            'name' => 'admin',
        ]);

        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $roleId,
        ]);

        return [$tenant, $user];
    }
}