<?php

namespace Tests\Feature;

use App\Services\RefundService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class RefundApiDetailTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_refund_index_and_show_expose_financial_breakdown_and_payments(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+refund-api@example.test', 'Admin Refund Api');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => 100,
            'average_cost' => 5000,
            'last_cost' => 5000,
            'updated_at' => now('UTC'),
        ]);

        $checkoutResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/checkout', $this->checkoutPayload($fixture, '2026-02-27'));

        $checkoutResponse->assertCreated();

        $saleId = (string) $checkoutResponse->json('sale_id');
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        TenantContext::set($tenant->fresh());

        $refundId = app(RefundService::class)->handle([
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'reason' => 'Refund api detail test',
            'items' => [
                [
                    'sale_item_id' => $saleItemId,
                    'qty' => 1,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $fixture['cash_account_id'],
                    'payment_method_id' => $fixture['payment_method_id'],
                    'method' => 'cash',
                    'amount' => 10000,
                ],
            ],
        ], $user->id);

        $headers = array_merge($this->tenantHeaders($tenant->id), [
            'Accept' => 'application/json',
        ]);

        $indexResponse = $this->actingAs($user)
            ->withHeaders($headers)
            ->getJson('/api/v1/refunds');

        $indexResponse->assertOk()
            ->assertJsonPath('data.refunds.0.id', $refundId)
            ->assertJsonPath('data.refunds.0.sale_id', $saleId)
            ->assertJsonPath('data.refunds.0.register_id', $fixture['register_id'])
            ->assertJsonPath('data.refunds.0.shift_id', $fixture['shift_id'])
            ->assertJsonPath('data.refunds.0.subtotal', 10000)
            ->assertJsonPath('data.refunds.0.refunded_cost_total', 5000)
            ->assertJsonPath('data.refunds.0.payout_total', 10000)
            ->assertJsonPath('data.refunds.0.qty_total', 1);

        $showResponse = $this->actingAs($user)
            ->withHeaders($headers)
            ->getJson('/api/v1/refunds/'.$refundId);

        $showResponse->assertOk()
            ->assertJsonPath('refund.id', $refundId)
            ->assertJsonPath('refund.subtotal', 10000)
            ->assertJsonPath('refund.refunded_cost_total', 5000)
            ->assertJsonPath('refund.payout_total', 10000)
            ->assertJsonPath('items.0.sale_item_id', $saleItemId)
            ->assertJsonPath('items.0.gross_amount', 10000)
            ->assertJsonPath('items.0.net_amount', 10000)
            ->assertJsonPath('items.0.refunded_cost_total', 5000)
            ->assertJsonPath('payments.0.method', 'cash')
            ->assertJsonPath('payments.0.amount', 10000);
    }
}