<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class CheckoutDiscountFlowTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    public function test_checkout_persists_discount_breakdown_and_aggregate_amounts(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'discount-flow@example.com', 'Kasir Diskon');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['items'][0]['qty'] = 2;
        $payload['items'][0]['discounts'] = [
            ['type' => 'percent', 'value' => 10, 'label' => 'Promo Item 10%'],
            ['type' => 'fixed', 'value' => 500, 'label' => 'Potongan Item'],
        ];
        $payload['subtotal'] = 17500;
        $payload['discount'] = 1875;
        $payload['total'] = 15625;
        $payload['transaction_discounts'] = [
            ['type' => 'percent', 'value' => 5, 'label' => 'Promo Nota 5%'],
            ['type' => 'fixed', 'value' => 1000, 'label' => 'Potongan Nota'],
        ];
        $payload['payments'][0]['amount'] = 15625;

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/checkout', $payload);

        $response->assertCreated()
            ->assertJsonPath('idempotent', false);

        $saleId = (string) $response->json('sale_id');

        $this->assertDatabaseHas('sales', [
            'tenant_id' => $tenant->id,
            'id' => $saleId,
            'subtotal' => 17500.00,
            'discount' => 1875.00,
            'total' => 15625.00,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'discount' => 2500.00,
        ]);

        $this->assertSame(4, \DB::table('discounts')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->count());

        $this->assertDatabaseHas('discounts', [
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'type' => 'item_percent',
            'value' => 2000.00,
        ]);

        $this->assertDatabaseHas('discounts', [
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'type' => 'transaction_percent',
            'value' => 875.00,
        ]);
    }
}
