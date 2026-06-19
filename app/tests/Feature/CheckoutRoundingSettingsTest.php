<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class CheckoutRoundingSettingsTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    public function test_checkout_accepts_total_rounded_up_to_configured_multiple(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'rounding-checkout@example.com', 'Kasir Pembulatan');
        $tenant->forceFill([
            'rounding_mode' => 'multiple',
            'rounding_increment' => 100,
        ])->save();

        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['subtotal'] = 36666;
        $payload['total'] = 36700;
        $payload['items'][0]['price'] = 36666;
        $payload['payments'][0]['amount'] = 36700;

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/checkout', $payload);

        $response->assertCreated()
            ->assertJsonPath('idempotent', false);

        $this->assertDatabaseHas('sales', [
            'tenant_id' => $tenant->id,
            'id' => $response->json('sale_id'),
            'subtotal' => 36666.00,
            'tax' => 0.00,
            'total' => 36700.00,
        ]);
    }
}
