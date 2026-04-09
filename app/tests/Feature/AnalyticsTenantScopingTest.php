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

class AnalyticsTenantScopingTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_analytics_excludes_foreign_tenant_sales_from_trend_and_report_aggregates(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'analytics-scope@example.test', 'Admin Analytics');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Admin Analytics Other', 'analytics-scope-other@example.test');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);

        DB::table('products')
            ->where('tenant_id', $otherTenant->id)
            ->where('id', $otherFixture['product_id'])
            ->update([
                'name' => 'Produk Analytics Lain',
                'sku' => 'SKU-AN-OTHER-001',
            ]);

        $tenantSaleId = (string) Str::uuid();
        $foreignSaleId = (string) Str::uuid();
        $tenantRefundId = (string) Str::uuid();
        $foreignRefundId = (string) Str::uuid();
        $tenantSaleItemId = (string) Str::uuid();
        $activeAt = CarbonImmutable::parse('2026-02-27 12:15:00', 'UTC');

        DB::table('sales')->insert([
            [
                'id' => $tenantSaleId,
                'tenant_id' => $tenant->id,
                'branch_id' => $fixture['branch_id'],
                'shift_id' => $fixture['shift_id'],
                'register_id' => $fixture['register_id'],
                'customer_id' => null,
                'subtotal' => 15000,
                'discount' => 0,
                'tax' => 0,
                'total' => 15000,
                'status' => 'completed',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => $user->id,
                'voided_by' => null,
                'void_approved_by' => null,
                'void_reason' => null,
                'voided_at' => null,
                'created_at' => $activeAt,
                'transaction_date' => '2026-02-27',
            ],
            [
                'id' => $foreignSaleId,
                'tenant_id' => $otherTenant->id,
                'branch_id' => $otherFixture['branch_id'],
                'shift_id' => $otherFixture['shift_id'],
                'register_id' => $otherFixture['register_id'],
                'customer_id' => null,
                'subtotal' => 75000,
                'discount' => 0,
                'tax' => 0,
                'total' => 75000,
                'status' => 'completed',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => $otherUser->id,
                'voided_by' => null,
                'void_approved_by' => null,
                'void_reason' => null,
                'voided_at' => null,
                'created_at' => $activeAt,
                'transaction_date' => '2026-02-27',
            ],
        ]);

        DB::table('sale_items')->insert([
            [
                'id' => $tenantSaleItemId,
                'tenant_id' => $tenant->id,
                'sale_id' => $tenantSaleId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty' => 2,
                'price' => 7500,
                'discount' => 500,
                'hpp_snapshot' => 2500,
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
                'amount' => 15000,
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

        DB::table('refunds')->insert([
            [
                'id' => $tenantRefundId,
                'tenant_id' => $tenant->id,
                'sale_id' => $tenantSaleId,
                'register_id' => $fixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'branch_id' => $fixture['branch_id'],
                'created_by' => $user->id,
                'subtotal' => 7500,
                'item_discount_total' => 250,
                'transaction_discount_total' => 0,
                'tax_total' => 0,
                'refunded_cost_total' => 2500,
                'payout_total' => 7250,
                'total' => 7250,
                'status' => 'completed',
                'reason' => 'Refund analytics test',
                'created_at' => $activeAt,
            ],
            [
                'id' => $foreignRefundId,
                'tenant_id' => $otherTenant->id,
                'sale_id' => $foreignSaleId,
                'register_id' => $otherFixture['register_id'],
                'shift_id' => $otherFixture['shift_id'],
                'branch_id' => $otherFixture['branch_id'],
                'created_by' => $otherUser->id,
                'subtotal' => 20000,
                'item_discount_total' => 0,
                'transaction_discount_total' => 0,
                'tax_total' => 0,
                'refunded_cost_total' => 5000,
                'payout_total' => 20000,
                'total' => 20000,
                'status' => 'completed',
                'reason' => 'Refund analytics foreign test',
                'created_at' => $activeAt,
            ],
        ]);

        DB::table('refund_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'refund_id' => $tenantRefundId,
                'sale_item_id' => $tenantSaleItemId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'location_id' => $fixture['location_id'],
                'qty' => 1,
                'unit_price_snapshot' => 7500,
                'gross_amount' => 7500,
                'item_discount_amount' => 250,
                'transaction_discount_amount' => 0,
                'tax_amount' => 0,
                'net_amount' => 7250,
                'hpp_snapshot' => 2500,
                'refunded_cost_total' => 2500,
                'amount' => 7250,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $otherTenant->id,
                'refund_id' => $foreignRefundId,
                'sale_item_id' => null,
                'product_id' => $otherFixture['product_id'],
                'product_variant_id' => null,
                'location_id' => $otherFixture['location_id'],
                'qty' => 1,
                'unit_price_snapshot' => 20000,
                'gross_amount' => 20000,
                'item_discount_amount' => 0,
                'transaction_discount_amount' => 0,
                'tax_amount' => 0,
                'net_amount' => 20000,
                'hpp_snapshot' => 5000,
                'refunded_cost_total' => 5000,
                'amount' => 20000,
            ],
        ]);

        DB::table('refund_payments')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'refund_id' => $tenantRefundId,
                'sale_payment_id' => null,
                'account_id' => $fixture['cash_account_id'],
                'payment_method_id' => $fixture['payment_method_id'],
                'method' => 'cash',
                'amount' => 7250,
                'created_at' => $activeAt,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $otherTenant->id,
                'refund_id' => $foreignRefundId,
                'sale_payment_id' => null,
                'account_id' => $otherFixture['cash_account_id'],
                'payment_method_id' => $otherFixture['payment_method_id'],
                'method' => 'bank',
                'amount' => 20000,
                'created_at' => $activeAt,
            ],
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/analytics?preset=custom&start_date=2026-02-27&end_date=2026-02-27');

        $response->assertOk()
            ->assertJsonPath('revenue.today', 7750)
            ->assertJsonPath('orders.today', 1)
            ->assertJsonPath('profit.today', 4750)
            ->assertJsonPath('summary.revenue', 7750)
            ->assertJsonPath('summary.orders', 1)
            ->assertJsonPath('summary.items', 1)
            ->assertJsonPath('summary.discount', 0)
            ->assertJsonPath('summary.profit', 4750)
            ->assertJsonPath('daily.0.date', '2026-02-27')
            ->assertJsonPath('daily.0.revenue', 7750)
            ->assertJsonPath('daily.0.orders', 1)
            ->assertJsonPath('daily.0.items', 1)
            ->assertJsonPath('daily.0.profit', 4750)
            ->assertJsonCount(1, 'top_products')
            ->assertJsonPath('top_products.0.id', $fixture['product_id'])
            ->assertJsonPath('top_products.0.revenue', 7250)
            ->assertJsonPath('top_products.0.qty', 1)
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.method', 'cash')
            ->assertJsonPath('payments.0.amount', 7750)
            ->assertJsonPath('payments.0.percent', 100)
            ->assertJsonMissing(['method' => 'bank', 'amount' => 75000])
            ->assertJsonMissing(['id' => $otherFixture['product_id'], 'name' => 'Produk Analytics Lain']);

        $trendAtNoon = collect($response->json('trend'))->firstWhere('hour', '12:00');

        $this->assertNotNull($trendAtNoon);
        $this->assertSame(7750.0, (float) ($trendAtNoon['total'] ?? 0));
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