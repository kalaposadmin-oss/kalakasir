<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class StockAdjustmentTransactionDateValidationTest extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-adjustment@example.com', 'Admin A');
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_adjustment_post_returns_invalid_transaction_date_for_future_date(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/stock-adjustments/'.(string) Str::uuid().'/post', [
            'transaction_date' => '2026-02-28',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'INVALID_TRANSACTION_DATE',
            ]);
    }

    public function test_adjustment_post_returns_invalid_transaction_date_for_backdate_beyond_limit(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/stock-adjustments/'.(string) Str::uuid().'/post', [
            'transaction_date' => '2026-02-25',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'INVALID_TRANSACTION_DATE',
            ]);
    }
}
