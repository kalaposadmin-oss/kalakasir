<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class SalesReportSummaryTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    public function test_sales_history_returns_discount_and_payment_summary_metrics(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'sales-report-summary@example.com', 'Admin Report');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $saleId = (string) Str::uuid();
        $saleTwoId = (string) Str::uuid();
        $now = CarbonImmutable::parse('2026-02-27 10:00:00', 'UTC');

        \DB::table('sales')->insert([
            [
                'id' => $saleId,
                'tenant_id' => $tenant->id,
                'branch_id' => $fixture['branch_id'],
                'shift_id' => $fixture['shift_id'],
                'register_id' => $fixture['register_id'],
                'customer_id' => null,
                'subtotal' => 17500,
                'discount' => 1875,
                'tax' => 0,
                'total' => 15625,
                'status' => 'completed',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => $user->id,
                'voided_by' => null,
                'created_at' => $now,
                'transaction_date' => '2026-02-27',
            ],
            [
                'id' => $saleTwoId,
                'tenant_id' => $tenant->id,
                'branch_id' => $fixture['branch_id'],
                'shift_id' => $fixture['shift_id'],
                'register_id' => $fixture['register_id'],
                'customer_id' => null,
                'subtotal' => 12000,
                'discount' => 1000,
                'tax' => 0,
                'total' => 11000,
                'status' => 'completed',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => $user->id,
                'voided_by' => null,
                'created_at' => $now->addHour(),
                'transaction_date' => '2026-02-27',
            ],
        ]);

        \DB::table('sale_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty' => 2,
                'price' => 10000,
                'discount' => 2500,
                'hpp_snapshot' => 5000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleTwoId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty' => 1,
                'price' => 12000,
                'discount' => 500,
                'hpp_snapshot' => 5000,
            ],
        ]);

        \DB::table('payments')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleId,
                'account_id' => (string) Str::uuid(),
                'method' => 'cash',
                'amount' => 15625,
                'payment_method_id' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleTwoId,
                'account_id' => (string) Str::uuid(),
                'method' => 'bank',
                'amount' => 11000,
                'payment_method_id' => null,
            ],
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/sales-history?preset=custom&start_date=2026-02-27&end_date=2026-02-27&status=completed');

        $response->assertOk()
            ->assertJsonPath('data.summary.gross_sales', 32000)
            ->assertJsonPath('data.summary.item_discount', 3000)
            ->assertJsonPath('data.summary.transaction_discount', 2875)
            ->assertJsonPath('data.summary.total_discount', 5875)
            ->assertJsonPath('data.summary.net_sales', 26125)
            ->assertJsonPath('data.summary.grand_total', 26625)
            ->assertJsonPath('data.summary.transaction_count', 2);

        $payments = collect($response->json('data.summary.payments'));
        $this->assertSame(2, $payments->count());
        $this->assertSame(15625.0, (float) $payments->firstWhere('method', 'cash')['amount']);
        $this->assertSame(11000.0, (float) $payments->firstWhere('method', 'bank')['amount']);
    }

    public function test_sales_history_applies_branch_and_cashier_filters_to_summary_and_list(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'sales-report-filter@example.com', 'Admin Filter');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $secondBranchId = (string) Str::uuid();
        \DB::table('branches')->insert([
            'id' => $secondBranchId,
            'tenant_id' => $tenant->id,
            'name' => 'Branch B',
        ]);

        $secondUserId = (string) Str::uuid();
        \DB::table('users')->insert([
            'id' => $secondUserId,
            'tenant_id' => $tenant->id,
            'name' => 'Kasir B',
            'email' => 'kasir-b@example.com',
            'password' => bcrypt('secret'),
        ]);

        $saleA = (string) Str::uuid();
        $saleB = (string) Str::uuid();
        $createdAt = CarbonImmutable::parse('2026-02-27 09:00:00', 'UTC');

        \DB::table('sales')->insert([
            [
                'id' => $saleA,
                'tenant_id' => $tenant->id,
                'branch_id' => $fixture['branch_id'],
                'shift_id' => $fixture['shift_id'],
                'register_id' => $fixture['register_id'],
                'customer_id' => null,
                'subtotal' => 10000,
                'discount' => 500,
                'tax' => 0,
                'total' => 9500,
                'status' => 'completed',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => $user->id,
                'voided_by' => null,
                'created_at' => $createdAt,
                'transaction_date' => '2026-02-27',
            ],
            [
                'id' => $saleB,
                'tenant_id' => $tenant->id,
                'branch_id' => $secondBranchId,
                'shift_id' => $fixture['shift_id'],
                'register_id' => $fixture['register_id'],
                'customer_id' => null,
                'subtotal' => 20000,
                'discount' => 1000,
                'tax' => 0,
                'total' => 19000,
                'status' => 'completed',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => $secondUserId,
                'voided_by' => null,
                'created_at' => $createdAt->addHour(),
                'transaction_date' => '2026-02-27',
            ],
        ]);

        \DB::table('sale_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleA,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty' => 1,
                'price' => 10000,
                'discount' => 500,
                'hpp_snapshot' => 5000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleB,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty' => 2,
                'price' => 10000,
                'discount' => 0,
                'hpp_snapshot' => 5000,
            ],
        ]);

        \DB::table('payments')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleA,
                'account_id' => (string) Str::uuid(),
                'method' => 'cash',
                'amount' => 9500,
                'payment_method_id' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleB,
                'account_id' => (string) Str::uuid(),
                'method' => 'bank',
                'amount' => 19000,
                'payment_method_id' => null,
            ],
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson(sprintf(
                '/api/v1/sales-history?preset=custom&start_date=2026-02-27&end_date=2026-02-27&status=completed&branch_id=%s&cashier_id=%s',
                $fixture['branch_id'],
                $user->id
            ));

        $response->assertOk()
            ->assertJsonCount(1, 'data.sales')
            ->assertJsonPath('data.sales.0.id', $saleA)
            ->assertJsonPath('data.sales.0.gross_sales', 10000)
            ->assertJsonPath('data.sales.0.item_discount', 500)
            ->assertJsonPath('data.sales.0.transaction_discount', 500)
            ->assertJsonPath('data.sales.0.total_discount', 1000)
            ->assertJsonPath('data.sales.0.net_sales', 9000)
            ->assertJsonPath('data.summary.gross_sales', 10000)
            ->assertJsonPath('data.summary.item_discount', 500)
            ->assertJsonPath('data.summary.transaction_discount', 500)
            ->assertJsonPath('data.summary.total_discount', 1000)
            ->assertJsonPath('data.summary.net_sales', 9000)
            ->assertJsonPath('data.summary.grand_total', 9500)
            ->assertJsonPath('data.summary.transaction_count', 1);
    }

    public function test_sales_history_applies_status_filter_consistently_to_summary_and_list(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'sales-report-status@example.com', 'Admin Status');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $completedSaleId = (string) Str::uuid();
        $voidSaleId = (string) Str::uuid();
        $createdAt = CarbonImmutable::parse('2026-02-27 11:00:00', 'UTC');

        \DB::table('sales')->insert([
            [
                'id' => $completedSaleId,
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
                'id' => $voidSaleId,
                'tenant_id' => $tenant->id,
                'branch_id' => $fixture['branch_id'],
                'shift_id' => $fixture['shift_id'],
                'register_id' => $fixture['register_id'],
                'customer_id' => null,
                'subtotal' => 8000,
                'discount' => 500,
                'tax' => 0,
                'total' => 7500,
                'status' => 'void',
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => $user->id,
                'voided_by' => $user->id,
                'created_at' => $createdAt->addHour(),
                'transaction_date' => '2026-02-27',
            ],
        ]);

        \DB::table('sale_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $completedSaleId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty' => 1,
                'price' => 15000,
                'discount' => 500,
                'hpp_snapshot' => 5000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $voidSaleId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty' => 1,
                'price' => 8000,
                'discount' => 250,
                'hpp_snapshot' => 5000,
            ],
        ]);

        \DB::table('payments')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $completedSaleId,
                'account_id' => (string) Str::uuid(),
                'method' => 'cash',
                'amount' => 14000,
                'payment_method_id' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $voidSaleId,
                'account_id' => (string) Str::uuid(),
                'method' => 'bank',
                'amount' => 7500,
                'payment_method_id' => null,
            ],
        ]);

        $completedResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/sales-history?preset=custom&start_date=2026-02-27&end_date=2026-02-27&status=completed');

        $completedResponse->assertOk()
            ->assertJsonCount(1, 'data.sales')
            ->assertJsonPath('data.sales.0.id', $completedSaleId)
            ->assertJsonPath('data.summary.gross_sales', 15000)
            ->assertJsonPath('data.summary.item_discount', 500)
            ->assertJsonPath('data.summary.transaction_discount', 1000)
            ->assertJsonPath('data.summary.total_discount', 1500)
            ->assertJsonPath('data.summary.net_sales', 13500)
            ->assertJsonPath('data.summary.grand_total', 14000)
            ->assertJsonPath('data.summary.transaction_count', 1)
            ->assertJsonPath('data.summary.payments.0.method', 'cash')
            ->assertJsonPath('data.summary.top_products.0.qty_sold', 1);

        $voidResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/sales-history?preset=custom&start_date=2026-02-27&end_date=2026-02-27&status=void');

        $voidResponse->assertOk()
            ->assertJsonCount(1, 'data.sales')
            ->assertJsonPath('data.sales.0.id', $voidSaleId)
            ->assertJsonPath('data.summary.gross_sales', 8000)
            ->assertJsonPath('data.summary.item_discount', 250)
            ->assertJsonPath('data.summary.transaction_discount', 500)
            ->assertJsonPath('data.summary.total_discount', 750)
            ->assertJsonPath('data.summary.net_sales', 7250)
            ->assertJsonPath('data.summary.grand_total', 7500)
            ->assertJsonPath('data.summary.transaction_count', 1)
            ->assertJsonPath('data.summary.payments.0.method', 'bank');

        $allResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/sales-history?preset=custom&start_date=2026-02-27&end_date=2026-02-27&status=all');

        $allResponse->assertOk()
            ->assertJsonCount(2, 'data.sales')
            ->assertJsonPath('data.summary.gross_sales', 23000)
            ->assertJsonPath('data.summary.item_discount', 750)
            ->assertJsonPath('data.summary.transaction_discount', 1500)
            ->assertJsonPath('data.summary.total_discount', 2250)
            ->assertJsonPath('data.summary.net_sales', 20750)
            ->assertJsonPath('data.summary.grand_total', 21500)
            ->assertJsonPath('data.summary.transaction_count', 2);
    }

    public function test_sales_history_uses_transaction_date_as_business_date_for_backdated_sales(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'sales-report-backdated@example.com', 'Admin Backdated');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $saleId = (string) Str::uuid();

        \DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 18000,
            'discount' => 1000,
            'tax' => 0,
            'total' => 17000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'created_at' => CarbonImmutable::parse('2026-02-28 01:15:00', 'UTC'),
            'transaction_date' => '2026-02-27',
        ]);

        \DB::table('sale_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 1,
            'price' => 18000,
            'discount' => 500,
            'hpp_snapshot' => 7000,
        ]);

        \DB::table('payments')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'account_id' => $fixture['cash_account_id'],
            'method' => 'cash',
            'amount' => 17000,
            'payment_method_id' => $fixture['payment_method_id'],
        ]);

        $transactionDay = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/sales-history?preset=custom&start_date=2026-02-27&end_date=2026-02-27&status=completed');

        $transactionDay->assertOk()
            ->assertJsonCount(1, 'data.sales')
            ->assertJsonPath('data.sales.0.id', $saleId)
            ->assertJsonPath('data.sales.0.transaction_date', '2026-02-27')
            ->assertJsonPath('data.summary.net_sales', 16500)
            ->assertJsonPath('data.summary.transaction_count', 1);

        $createdDay = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/sales-history?preset=custom&start_date=2026-02-28&end_date=2026-02-28&status=completed');

        $createdDay->assertOk()
            ->assertJsonCount(0, 'data.sales')
            ->assertJsonPath('data.summary.transaction_count', 0)
            ->assertJsonPath('data.summary.net_sales', 0);
    }

    public function test_sales_history_reconciles_with_analytics_for_discount_tax_and_refund_case(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'sales-history-reconcile@example.com', 'Admin Reconcile');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();
        $refundId = (string) Str::uuid();
        $createdAt = CarbonImmutable::parse('2026-02-27 10:00:00', 'UTC');

        \DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 20000,
            'discount' => 3000,
            'tax' => 1500,
            'total' => 16500,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'created_at' => $createdAt,
            'transaction_date' => '2026-02-27',
            'cogs_total' => 8000,
        ]);

        \DB::table('sale_items')->insert([
            'id' => $saleItemId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 2,
            'price' => 10000,
            'discount' => 2000,
            'hpp_snapshot' => 4000,
        ]);

        \DB::table('sale_cost_components')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'sale_item_id' => $saleItemId,
            'component_type' => 'sale_item',
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 2,
            'unit_cost' => 4000,
            'total_cost' => 8000,
            'source_reference_type' => 'sale',
            'source_reference_id' => $saleId,
            'source_reference_item_id' => $saleItemId,
            'source' => 'repair',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        \DB::table('payments')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'account_id' => $fixture['cash_account_id'],
            'method' => 'cash',
            'amount' => 16500,
            'payment_method_id' => $fixture['payment_method_id'],
        ]);

        \DB::table('refunds')->insert([
            'id' => $refundId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'branch_id' => $fixture['branch_id'],
            'created_by' => $user->id,
            'subtotal' => 10000,
            'item_discount_total' => 1000,
            'transaction_discount_total' => 1500,
            'tax_total' => 750,
            'refunded_cost_total' => 4000,
            'payout_total' => 8250,
            'total' => 8250,
            'status' => 'completed',
            'reason' => 'Refund reconcile test',
            'transaction_date' => '2026-02-27',
            'created_at' => $createdAt->addHour(),
        ]);

        \DB::table('refund_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'refund_id' => $refundId,
            'sale_item_id' => $saleItemId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'qty' => 1,
            'unit_price_snapshot' => 10000,
            'gross_amount' => 10000,
            'item_discount_amount' => 1000,
            'transaction_discount_amount' => 1500,
            'tax_amount' => 750,
            'net_amount' => 8250,
            'hpp_snapshot' => 4000,
            'refunded_cost_total' => 4000,
            'amount' => 8250,
        ]);

        \DB::table('refund_payments')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'refund_id' => $refundId,
            'sale_payment_id' => null,
            'account_id' => $fixture['cash_account_id'],
            'payment_method_id' => $fixture['payment_method_id'],
            'method' => 'cash',
            'amount' => 8250,
            'created_at' => $createdAt->addHour(),
        ]);

        $history = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/sales-history?preset=custom&start_date=2026-02-27&end_date=2026-02-27&status=completed');

        $history->assertOk()
            ->assertJsonCount(1, 'data.sales')
            ->assertJsonPath('data.sales.0.id', $saleId)
            ->assertJsonPath('data.sales.0.gross_sales', 20000)
            ->assertJsonPath('data.sales.0.item_discount', 2000)
            ->assertJsonPath('data.sales.0.transaction_discount', 3000)
            ->assertJsonPath('data.sales.0.total_discount', 5000)
            ->assertJsonPath('data.sales.0.net_sales_before_tax', 15000)
            ->assertJsonPath('data.sales.0.sales_tax', 1500)
            ->assertJsonPath('data.sales.0.refund_revenue_reversal_before_tax', 7500)
            ->assertJsonPath('data.sales.0.refund_tax', 750)
            ->assertJsonPath('data.sales.0.tax', 750)
            ->assertJsonPath('data.sales.0.refunded_amount', 8250)
            ->assertJsonPath('data.sales.0.original_receipt_total', 16500)
            ->assertJsonPath('data.sales.0.net_total', 8250)
            ->assertJsonPath('data.sales.0.net_sales', 7500)
            ->assertJsonPath('data.summary.gross_sales', 20000)
            ->assertJsonPath('data.summary.item_discount', 2000)
            ->assertJsonPath('data.summary.transaction_discount', 3000)
            ->assertJsonPath('data.summary.total_discount', 5000)
            ->assertJsonPath('data.summary.net_sales_before_tax', 15000)
            ->assertJsonPath('data.summary.refund_revenue_reversal_before_tax', 7500)
            ->assertJsonPath('data.summary.net_sales', 7500)
            ->assertJsonPath('data.summary.sales_tax', 1500)
            ->assertJsonPath('data.summary.refund_tax', 750)
            ->assertJsonPath('data.summary.tax', 750)
            ->assertJsonPath('data.summary.original_receipt_total', 16500)
            ->assertJsonPath('data.summary.refunded_amount', 8250)
            ->assertJsonPath('data.summary.net_total', 8250)
            ->assertJsonPath('data.summary.transaction_count', 1)
            ->assertJsonPath('data.summary.payments.0.method', 'cash')
            ->assertJsonPath('data.summary.payments.0.amount', 8250)
            ->assertJsonPath('data.summary.top_products.0.qty_sold', 1)
            ->assertJsonPath('data.summary.top_products.0.net_sales', 7500)
            ->assertJsonPath('meta.list_mode', 'sale_centric')
            ->assertJsonPath('meta.summary_mode', 'mixed_sales_and_refunds')
            ->assertJsonPath('meta.refund_business_date_basis', 'refunds.transaction_date');

        $analytics = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/analytics?preset=custom&start_date=2026-02-27&end_date=2026-02-27');

        $analytics->assertOk()
            ->assertJsonPath('summary.revenue', 7500)
            ->assertJsonPath('summary.tax', 750)
            ->assertJsonPath('summary.discount', 5000)
            ->assertJsonPath('payments.0.amount', 8250)
            ->assertJsonPath('top_products.0.revenue', 7500)
            ->assertJsonPath('top_products.0.qty', 1);

        $this->assertSame(
            (float) $analytics->json('summary.revenue'),
            (float) $history->json('data.summary.net_sales')
        );
        $this->assertSame(
            (float) $analytics->json('summary.tax'),
            (float) $history->json('data.summary.tax')
        );
        $this->assertSame(
            (float) $analytics->json('summary.discount'),
            (float) $history->json('data.summary.total_discount')
        );
        $this->assertSame(
            (float) $analytics->json('payments.0.amount'),
            (float) $history->json('data.summary.payments.0.amount')
        );
    }

    public function test_sales_history_summary_uses_refund_transaction_date_for_refund_netted_summary(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'sales-history-refund-date@example.com', 'Admin Refund Date Summary');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();
        $refundId = (string) Str::uuid();

        \DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 10000,
            'discount' => 0,
            'tax' => 0,
            'total' => 10000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'created_at' => CarbonImmutable::parse('2026-02-27 09:00:00', 'UTC'),
            'transaction_date' => '2026-02-27',
        ]);

        \DB::table('sale_items')->insert([
            'id' => $saleItemId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 1,
            'price' => 10000,
            'discount' => 0,
            'hpp_snapshot' => 5000,
        ]);

        \DB::table('payments')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'account_id' => $fixture['cash_account_id'],
            'method' => 'cash',
            'amount' => 10000,
            'payment_method_id' => $fixture['payment_method_id'],
        ]);

        \DB::table('refunds')->insert([
            'id' => $refundId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'branch_id' => $fixture['branch_id'],
            'created_by' => $user->id,
            'subtotal' => 10000,
            'item_discount_total' => 0,
            'transaction_discount_total' => 0,
            'tax_total' => 0,
            'refunded_cost_total' => 5000,
            'payout_total' => 10000,
            'total' => 10000,
            'status' => 'completed',
            'reason' => 'Refund date summary policy',
            'transaction_date' => '2026-02-28',
            'created_at' => CarbonImmutable::parse('2026-02-27 20:00:00', 'UTC'),
        ]);

        \DB::table('refund_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'refund_id' => $refundId,
            'sale_item_id' => $saleItemId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'qty' => 1,
            'unit_price_snapshot' => 10000,
            'gross_amount' => 10000,
            'item_discount_amount' => 0,
            'transaction_discount_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => 10000,
            'hpp_snapshot' => 5000,
            'refunded_cost_total' => 5000,
            'amount' => 10000,
        ]);

        \DB::table('refund_payments')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'refund_id' => $refundId,
            'sale_payment_id' => null,
            'account_id' => $fixture['cash_account_id'],
            'payment_method_id' => $fixture['payment_method_id'],
            'method' => 'cash',
            'amount' => 10000,
            'created_at' => CarbonImmutable::parse('2026-02-27 20:00:00', 'UTC'),
        ]);

        $saleDay = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/sales-history?preset=custom&start_date=2026-02-27&end_date=2026-02-27&status=completed');

        $saleDay->assertOk()
            ->assertJsonCount(1, 'data.sales')
            ->assertJsonPath('data.sales.0.id', $saleId)
            ->assertJsonPath('data.sales.0.refunded_amount', 0)
            ->assertJsonPath('data.summary.net_sales', 10000)
            ->assertJsonPath('data.summary.refunded_amount', 0)
            ->assertJsonPath('data.summary.net_total', 10000);

        $refundDay = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/sales-history?preset=custom&start_date=2026-02-28&end_date=2026-02-28&status=completed');

        $refundDay->assertOk()
            ->assertJsonCount(0, 'data.sales')
            ->assertJsonPath('data.summary.transaction_count', 0)
            ->assertJsonPath('data.summary.net_sales', -10000)
            ->assertJsonPath('data.summary.refunded_amount', 10000)
            ->assertJsonPath('data.summary.net_total', -10000)
            ->assertJsonPath('meta.summary_mode', 'mixed_sales_and_refunds')
            ->assertJsonPath('meta.refund_business_date_basis', 'refunds.transaction_date');
    }
}
