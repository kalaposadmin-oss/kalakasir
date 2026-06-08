<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class CheckoutTransactionDateValidationTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->user] = $this->bootApiActor('cashier', 'kasir@example.com', 'Kasir A');

        $this->fixture = $this->seedCheckoutFixture($this->tenant->id, $this->user->id);
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_checkout_returns_invalid_transaction_date_for_future_date(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/checkout', $this->checkoutPayload($this->fixture, '2026-02-28'));

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'INVALID_TRANSACTION_DATE',
            ]);
    }

    public function test_checkout_accepts_transaction_date_before_current_month(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/checkout', $this->checkoutPayload($this->fixture, '2026-01-31'));

        $response->assertStatus(201);
        $this->assertDatabaseHas('sales', [
            'tenant_id' => $this->tenant->id,
            'transaction_date' => '2026-01-31',
        ]);
    }
}
