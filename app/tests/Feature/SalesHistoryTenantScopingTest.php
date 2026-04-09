<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class SalesHistoryTenantScopingTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_sales_history_excludes_foreign_tenant_sales_and_report_aggregates(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'sales-history-scope@example.test', 'Admin Scope');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Admin Scope Other', 'sales-history-scope-other@example.test');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);

        $tenantSaleId = (string) Str::uuid();
        $foreignSaleId = (string) Str::uuid();
        $createdAt = CarbonImmutable::parse('2026-02-27 10:00:00', 'UTC');

        DB::table('sales')->insert([
            [
                'id' => $tenantSaleId,
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
                'created_at' => $createdAt,
                'transaction_date' => '2026-02-27',
            ],
            [
                'id' => $foreignSaleId,
                'tenant_id' => $otherTenant->id,
                'branch_id' => $otherFixture['branch_id'],
                'shift_id' => $otherFixture['shift_id'],
                'register_id' => $otherFixture['register_id'],
                'customer_id' => null,
                'subtotal' => 80000,
                'discount' => 5000,
                'tax' => 0,
                'total' => 75000,
                'status' => 'completed',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => $otherUser->id,
                'voided_by' => null,
                'created_at' => $createdAt->addHour(),
                'transaction_date' => '2026-02-27',
            ],
        ]);

        DB::table('sale_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $tenantSaleId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty' => 1,
                'price' => 15000,
                'discount' => 500,
                'hpp_snapshot' => 5000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $otherTenant->id,
                'sale_id' => $foreignSaleId,
                'product_id' => $otherFixture['product_id'],
                'product_variant_id' => null,
                'qty' => 2,
                'price' => 40000,
                'discount' => 2000,
                'hpp_snapshot' => 10000,
            ],
        ]);

        DB::table('payments')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $tenantSaleId,
                'account_id' => $fixture['cash_account_id'],
                'method' => 'cash',
                'amount' => 14000,
                'payment_method_id' => $fixture['payment_method_id'],
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $otherTenant->id,
                'sale_id' => $foreignSaleId,
                'account_id' => $otherFixture['cash_account_id'],
                'method' => 'bank',
                'amount' => 75000,
                'payment_method_id' => $otherFixture['payment_method_id'],
            ],
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/sales-history?preset=custom&start_date=2026-02-27&end_date=2026-02-27&status=completed');

        $response->assertOk()
            ->assertJsonCount(1, 'data.sales')
            ->assertJsonPath('data.sales.0.id', $tenantSaleId)
            ->assertJsonPath('data.sales.0.branch_id', $fixture['branch_id'])
            ->assertJsonPath('data.sales.0.cashier_id', $user->id)
            ->assertJsonPath('data.sales.0.payments.0.method', 'cash')
            ->assertJsonPath('data.summary.gross_sales', 15000)
            ->assertJsonPath('data.summary.item_discount', 500)
            ->assertJsonPath('data.summary.transaction_discount', 1000)
            ->assertJsonPath('data.summary.total_discount', 1500)
            ->assertJsonPath('data.summary.net_sales', 13500)
            ->assertJsonPath('data.summary.grand_total', 14000)
            ->assertJsonPath('data.summary.transaction_count', 1)
            ->assertJsonPath('data.summary.payments.0.method', 'cash')
            ->assertJsonPath('data.summary.payments.0.amount', 14000)
            ->assertJsonPath('data.summary.top_products.0.id', $fixture['product_id'])
            ->assertJsonPath('data.summary.top_products.0.qty_sold', 1)
            ->assertJsonMissing(['id' => $foreignSaleId])
            ->assertJsonMissing(['branch_id' => $otherFixture['branch_id']])
            ->assertJsonMissing(['cashier_id' => $otherUser->id])
            ->assertJsonMissing(['method' => 'bank', 'amount' => 75000]);
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

        DB::table('roles')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'admin',
        ]);

        $roleId = DB::table('roles')
            ->where('tenant_id', $tenant->id)
            ->where('name', 'admin')
            ->value('id');

        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $roleId,
        ]);

        return [$tenant, $user];
    }
}