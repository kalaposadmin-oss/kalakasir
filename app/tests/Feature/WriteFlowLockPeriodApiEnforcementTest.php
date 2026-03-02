<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class WriteFlowLockPeriodApiEnforcementTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private array $checkoutFixture;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-lock@example.com', 'Admin Lock');
        $this->checkoutFixture = $this->seedCheckoutFixture($this->tenant->id, $this->user->id);

        \DB::table('lock_periods')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-29',
            'is_locked' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_checkout_returns_lock_period_when_transaction_date_locked(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/checkout', $this->checkoutPayload($this->checkoutFixture, '2026-02-27'));

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'LOCK_PERIOD',
            ]);
    }

    public function test_purchase_receive_returns_lock_period_when_transaction_date_locked(): void
    {
        $supplierId = (string) Str::uuid();
        \DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $this->tenant->id,
            'name' => 'Supplier Lock',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        $purchaseId = (string) Str::uuid();
        \DB::table('purchases')->insert([
            'id' => $purchaseId,
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplierId,
            'location_id' => $this->checkoutFixture['location_id'],
            'total' => 10000,
            'status' => 'approved',
            'notes' => null,
            'created_at' => now('UTC'),
            'approved_at' => now('UTC'),
            'approved_by' => $this->user->id,
            'received_at' => null,
            'received_by' => null,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'transaction_date' => null,
        ]);

        \DB::table('purchase_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'purchase_id' => $purchaseId,
            'product_id' => $this->checkoutFixture['product_id'],
            'qty' => 1,
            'received_qty' => 0,
            'price' => 10000,
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'transaction_date' => '2026-02-27',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'LOCK_PERIOD',
            ]);
    }

    public function test_transfer_ship_returns_lock_period_when_transaction_date_locked(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/stock-transfers/'.(string) Str::uuid().'/ship', [
                'transaction_date' => '2026-02-27',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'LOCK_PERIOD',
            ]);
    }

    public function test_transfer_receive_returns_lock_period_when_transaction_date_locked(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/stock-transfers/'.(string) Str::uuid().'/receive', [
                'transaction_date' => '2026-02-27',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'LOCK_PERIOD',
            ]);
    }

    public function test_adjustment_post_returns_lock_period_when_transaction_date_locked(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/stock-adjustments/'.(string) Str::uuid().'/post', [
                'transaction_date' => '2026-02-27',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'LOCK_PERIOD',
            ]);
    }
}
