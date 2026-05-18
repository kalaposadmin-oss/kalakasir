<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class FinanceReportCogsBreakdownTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_cogs_breakdown_returns_traceable_detail_rows_and_refund_adjustments(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+finance-cogs-breakdown@example.test', 'Owner Cogs Breakdown');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();
        $refundId = (string) Str::uuid();
        $createdAt = CarbonImmutable::parse('2026-04-24 10:00:00', 'UTC');

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 20000,
            'discount' => 0,
            'tax' => 0,
            'total' => 20000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'void_approved_by' => null,
            'void_reason' => null,
            'voided_at' => null,
            'created_at' => $createdAt,
            'transaction_date' => '2026-04-24',
            'cogs_total' => 7000,
        ]);

        DB::table('sale_items')->insert([
            'id' => $saleItemId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 2,
            'price' => 10000,
            'discount' => 0,
            'hpp_snapshot' => 3500,
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
            'unit_cost' => 3500,
            'total_cost' => 7000,
            'source_reference_type' => 'sale',
            'source_reference_id' => $saleId,
            'source_reference_item_id' => $saleItemId,
            'source' => 'checkout',
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
            'subtotal' => 10000,
            'item_discount_total' => 0,
            'transaction_discount_total' => 0,
            'tax_total' => 0,
            'refunded_cost_total' => 3500,
            'payout_total' => 10000,
            'total' => 10000,
            'status' => 'completed',
            'reason' => 'Test refund cogs',
            'created_at' => $createdAt,
            'transaction_date' => '2026-04-24',
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary/cogs-breakdown?preset=custom&start_date=2026-04-24&end_date=2026-04-24');

        $response->assertOk()
            ->assertJsonPath('summary.gross_cogs', 7000)
            ->assertJsonPath('summary.refunded_cogs', 3500)
            ->assertJsonPath('summary.net_cogs', 3500)
            ->assertJsonPath('summary.transaction_count', 1)
            ->assertJsonPath('details.0.sale_id', $saleId)
            ->assertJsonPath('details.0.qty', 2)
            ->assertJsonPath('details.0.unit_cost', 3500)
            ->assertJsonPath('details.0.cogs_total', 7000)
            ->assertJsonPath('refund_adjustments.0.sale_id', $saleId)
            ->assertJsonPath('refund_adjustments.0.cogs_amount', 3500)
            ->assertJsonPath('product_summary.0.product_id', $fixture['product_id'])
            ->assertJsonPath('product_summary.0.cogs_total', 7000);
    }
}
