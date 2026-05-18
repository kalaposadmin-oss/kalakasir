<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class FinanceReportRefundNettingTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_finance_summary_nets_completed_refunds_from_revenue_and_cogs(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+finance-refund@example.test', 'Owner Finance Refund');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $saleId = (string) Str::uuid();
        $refundId = (string) Str::uuid();
        $createdAt = CarbonImmutable::parse('2026-02-27 12:15:00', 'UTC');

        DB::table('sales')->insert([
            'id' => $saleId,
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
            'created_at' => $createdAt,
            'transaction_date' => '2026-02-27',
            'cogs_total' => 5000,
        ]);

        $saleItemId = (string) Str::uuid();

        DB::table('sale_items')->insert([
            'id' => $saleItemId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 2,
            'price' => 7500,
            'discount' => 0,
            'hpp_snapshot' => 2500,
        ]);

        DB::table('sale_cost_components')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'sale_item_id' => $saleItemId,
            'component_type' => 'sale_item',
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 2,
            'unit_cost' => 2500,
            'total_cost' => 5000,
            'source_reference_type' => 'sale',
            'source_reference_id' => $saleId,
            'source_reference_item_id' => $saleItemId,
            'source' => 'repair',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        DB::table('refunds')->insert([
            'id' => $refundId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'branch_id' => $fixture['branch_id'],
            'created_by' => $user->id,
            'subtotal' => 7500,
            'item_discount_total' => 0,
            'transaction_discount_total' => 0,
            'tax_total' => 0,
            'refunded_cost_total' => 2500,
            'payout_total' => 7500,
            'total' => 7500,
            'status' => 'completed',
            'reason' => 'Finance refund test',
            'created_at' => $createdAt,
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date=2026-02-27&end_date=2026-02-27');

        $response->assertOk()
            ->assertJsonPath('summary.revenue', 7500)
            ->assertJsonPath('summary.expenses', 2500)
            ->assertJsonPath('summary.net_profit', 5000)
            ->assertJsonPath('revenue_breakdown.0.amount', 15000)
            ->assertJsonPath('revenue_breakdown.1.amount', -7500)
            ->assertJsonPath('expense_breakdown.0.amount', 2500);
    }
}