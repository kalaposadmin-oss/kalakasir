<?php

namespace Tests\Feature;

use App\Models\OnlineOrder;
use App\Services\OnlineOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class OnlineOrderPosSyncTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_paid_online_order_is_recorded_as_pos_sale_once_even_without_open_shift(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'online-pos-sync@example.test', 'Admin Online');
        $tenant->forceFill(['plan' => 'P'])->save();
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $tenant->forceFill(['default_location_id' => $fixture['location_id']])->save();
        $tenant->refresh();

        DB::table('shifts')
            ->where('tenant_id', $tenant->id)
            ->where('id', $fixture['shift_id'])
            ->update([
                'status' => 'closed',
                'closed_at' => now('UTC'),
            ]);

        $service = app(OnlineOrderService::class);
        $created = $service->createOrder($tenant, [
            'customer_name' => 'Theo',
            'customer_phone' => '082136726327',
            'fulfillment_type' => 'pickup',
            'fulfillment_time' => '14.00',
            'items' => [
                [
                    'product_id' => $fixture['product_id'],
                    'qty' => 3,
                ],
            ],
        ]);

        /** @var OnlineOrder $order */
        $order = $created['order'];
        $this->assertNotNull($order->customer_id);
        $this->assertDatabaseHas('customers', [
            'id' => $order->customer_id,
            'tenant_id' => $tenant->id,
            'name' => 'Theo',
            'phone' => '082136726327',
        ]);
        $this->assertDatabaseHas('online_orders', [
            'id' => $order->id,
            'tenant_id' => $tenant->id,
            'customer_id' => $order->customer_id,
        ]);

        $order = $service->markShippingFilled($order, [
            'shipping_provider' => 'Shopee Sent',
            'shipping_cost' => 10000,
            'shipping_discount' => 5000,
            'extra_fee' => 0,
        ], $user->id);

        $bankAccountId = (string) DB::table('account_mappings')
            ->where('tenant_id', $tenant->id)
            ->value('bank_account_id');

        $paidOrder = $service->markPaid($order, $user->id, [
            'method' => 'qris',
            'account_id' => $bankAccountId,
        ]);
        $saleId = $paidOrder->sale_id;

        $this->assertNotNull($saleId);
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'status' => 'completed',
            'total' => 35000,
            'idempotency_key' => 'online-order:'.$order->id,
            'customer_id' => $order->customer_id,
            'order_holder_name' => 'Theo',
            'transaction_date' => '2026-02-27',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'qty' => 3,
            'price' => 10000,
        ]);
        $this->assertDatabaseHas('payments', [
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'account_id' => $bankAccountId,
            'method' => 'qris',
            'amount' => 35000,
        ]);
        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'account_id' => $bankAccountId,
            'debit' => 35000,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('stock_reservations', [
            'tenant_id' => $tenant->id,
            'online_order_id' => $order->id,
            'status' => 'converted',
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['product_id'],
            'qty_on_hand' => 97,
        ]);
        $this->assertSame(1, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->whereNotNull('reference_item_id')
            ->count());
        $this->assertDatabaseHas('sale_cost_components', [
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'component_type' => 'sale_item',
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/pos/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.orders_today', 1)
            ->assertJsonPath('data.sales_today', 35000);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/sales-history?preset=custom&start_date=2026-02-27&end_date=2026-02-27')
            ->assertOk()
            ->assertJsonPath('data.sales.0.sales_channel', 'online')
            ->assertJsonPath('data.sales.0.sales_channel_label', 'Online')
            ->assertJsonPath('data.sales.0.customer_id', $order->customer_id)
            ->assertJsonPath('data.sales.0.customer_name', 'Theo');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/customers')
            ->assertOk()
            ->assertJsonPath('customers.0.id', $order->customer_id)
            ->assertJsonPath('customers.0.name', 'Theo')
            ->assertJsonPath('customers.0.order_count', 1)
            ->assertJsonPath('customers.0.total_spent', 35000);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/sales/'.$saleId)
            ->assertOk()
            ->assertJsonPath('sale.sales_channel', 'online')
            ->assertJsonPath('sale.sales_channel_label', 'Online');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/stock-card?product_id='.$fixture['product_id'].'&location_id='.$fixture['location_id'].'&preset=custom&start_date=2026-02-27&end_date=2026-02-27&type=sale')
            ->assertOk()
            ->assertJsonPath('ledger.0.sales_channel', 'online')
            ->assertJsonPath('ledger.0.source_label', 'Penjualan Online');

        $service->markPaid($paidOrder->refresh(), $user->id);

        $this->assertSame(1, DB::table('sales')
            ->where('tenant_id', $tenant->id)
            ->where('idempotency_key', 'online-order:'.$order->id)
            ->count());
        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['product_id'],
            'qty_on_hand' => 97,
        ]);
    }
}
