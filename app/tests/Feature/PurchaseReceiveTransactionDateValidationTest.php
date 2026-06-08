<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class PurchaseReceiveTransactionDateValidationTest extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin@example.com', 'Admin A');
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_purchase_receive_returns_invalid_transaction_date_for_future_date(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/purchases/'.(string) Str::uuid().'/receive', [
            'transaction_date' => '2026-02-28',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'INVALID_TRANSACTION_DATE',
            ]);
    }

}
