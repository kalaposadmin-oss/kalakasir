<?php

namespace Tests\Feature;

use App\Models\OnlineOrder;
use App\Services\OnlineOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class OnlineOrderAdminCompletionTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_admin_can_mark_paid_and_complete_online_order(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-online-order@example.test', 'Admin Online');
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
        $order = $service->markShippingFilled($order, [
            'shipping_provider' => 'Shopee Sent',
            'shipping_cost' => 10000,
            'shipping_discount' => 5000,
            'extra_fee' => 0,
        ], $user->id);

        $bankAccountId = (string) DB::table('account_mappings')
            ->where('tenant_id', $tenant->id)
            ->value('bank_account_id');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->withSession(['order_online.admin_verified.'.$order->order_code => $user->id])
            ->post('/admin-order/'.$order->order_code.'/mark-paid', [
                'payment_method' => 'qris',
                'payment_account_id' => $bankAccountId,
            ])
            ->assertRedirect('/admin-order/'.$order->order_code.'/final-review');

        $paidOrder = $order->refresh();
        $this->assertSame('pembayaran_terverifikasi', $paidOrder->status);
        $this->assertNotNull($paidOrder->sale_id);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->withSession(['order_online.admin_verified.'.$order->order_code => $user->id])
            ->post('/admin-order/'.$order->order_code.'/complete')
            ->assertRedirect('/admin-order/'.$order->order_code.'/final-review');

        $completedOrder = $order->refresh();
        $this->assertSame('selesai', $completedOrder->status);
        $this->assertNotNull($completedOrder->completed_at);
        $this->assertDatabaseHas('online_orders', [
            'id' => $completedOrder->id,
            'tenant_id' => $tenant->id,
            'status' => 'selesai',
        ]);

        $completedOrder->forceFill(['status' => 'pembayaran_terverifikasi'])->save();

        $monitorPayload = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/monitor/orders/data')
            ->assertOk()
            ->json();

        $this->assertSame(1, $monitorPayload['stats']['selesai']);
        $this->assertSame($order->order_code, $monitorPayload['orders']['selesai'][0]['order_code']);
        $this->assertSame('selesai', $monitorPayload['orders']['selesai'][0]['status']);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->withSession(['order_online.admin_verified.'.$order->order_code => $user->id])
            ->get('/admin-order/'.$order->order_code.'/edit')
            ->assertRedirect('/admin-order/'.$order->order_code)
            ->assertSessionHas('error');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->withSession(['order_online.admin_verified.'.$order->order_code => $user->id])
            ->from('/admin-order/'.$order->order_code)
            ->post('/admin-order/'.$order->order_code.'/shipping', [
                'shipping_provider' => 'Kurir koreksi',
                'shipping_cost' => 25000,
                'shipping_discount' => 0,
                'extra_fee' => 0,
            ])
            ->assertRedirect('/admin-order/'.$order->order_code)
            ->assertSessionHasErrors('order_locked');

        $this->assertSame('10000.00', $order->refresh()->shipping_cost);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->withSession([
                'order_online.admin_verified.'.$order->order_code => $user->id,
                'order_online.admin_override.'.$order->order_code => [
                    'approver_id' => $user->id,
                    'reason' => 'Koreksi darurat test',
                    'approved_at' => now('UTC')->toISOString(),
                ],
            ])
            ->post('/admin-order/'.$order->order_code.'/shipping', [
                'shipping_provider' => 'Kurir koreksi',
                'shipping_cost' => 25000,
                'shipping_discount' => 0,
                'extra_fee' => 0,
            ])
            ->assertRedirect('/admin-order/'.$order->order_code.'/final-review');

        $this->assertSame('25000.00', $order->refresh()->shipping_cost);
    }
}
