<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class WriteFlowTransactionDateHappyPathTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private array $checkoutFixture;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-happy@example.com', 'Admin Happy');
        $this->tenant->forceFill(['plan' => 'M'])->save();
        $this->checkoutFixture = $this->seedCheckoutFixture($this->tenant->id, $this->user->id);
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_checkout_persists_valid_backdate_transaction_date(): void
    {
        $payload = $this->checkoutPayload($this->checkoutFixture, '2026-02-26');

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/checkout', $payload);

        $response->assertStatus(201);
        $this->assertStringContainsString('checkout;dur=', (string) $response->headers->get('Server-Timing'));
        $saleId = (string) $response->json('sale_id');

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'tenant_id' => $this->tenant->id,
            'transaction_date' => '2026-02-26',
        ]);
    }

    public function test_purchase_receive_persists_valid_backdate_transaction_date(): void
    {
        $purchaseId = $this->seedApprovedPurchase();

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'transaction_date' => '2026-02-26',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'received',
            ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'tenant_id' => $this->tenant->id,
            'transaction_date' => '2026-02-26',
        ]);
    }

    public function test_transfer_ship_persists_valid_backdate_transaction_date(): void
    {
        $transferId = $this->seedApprovedTransfer();

        $shipResponse = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/stock-transfers/'.$transferId.'/ship', [
                'transaction_date' => '2026-02-26',
            ]);

        $shipResponse->assertStatus(200)
            ->assertJson([
                'status' => 'in_transit',
            ]);

        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transferId,
            'tenant_id' => $this->tenant->id,
            'status' => 'in_transit',
            'transaction_date' => '2026-02-26',
        ]);
    }

    public function test_transfer_receive_persists_valid_backdate_transaction_date(): void
    {
        $transferId = $this->seedInTransitTransfer();

        $receiveResponse = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/stock-transfers/'.$transferId.'/receive', [
                'transaction_date' => '2026-02-26',
            ]);

        $this->assertSame(200, $receiveResponse->status(), $receiveResponse->getContent());
        $receiveResponse->assertJson([
            'status' => 'received',
        ]);

        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transferId,
            'tenant_id' => $this->tenant->id,
            'status' => 'received',
            'transaction_date' => '2026-02-26',
        ]);
    }

    public function test_adjustment_post_persists_valid_backdate_transaction_date(): void
    {
        $adjustmentId = $this->seedApprovedAdjustment();

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/stock-adjustments/'.$adjustmentId.'/post', [
                'transaction_date' => '2026-02-26',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'posted',
            ]);

        $this->assertDatabaseHas('stock_adjustments', [
            'id' => $adjustmentId,
            'tenant_id' => $this->tenant->id,
            'status' => 'posted',
            'transaction_date' => '2026-02-26',
        ]);
    }

    private function seedApprovedPurchase(): string
    {
        $supplierId = (string) Str::uuid();
        \DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $this->tenant->id,
            'name' => 'Supplier A',
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

        return $purchaseId;
    }

    private function seedApprovedTransfer(): string
    {
        $toBranchId = (string) Str::uuid();
        \DB::table('branches')->insert([
            'id' => $toBranchId,
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch B',
        ]);

        $toLocationId = (string) Str::uuid();
        \DB::table('locations')->insert([
            'id' => $toLocationId,
            'tenant_id' => $this->tenant->id,
            'branch_id' => $toBranchId,
            'name' => 'Warehouse B',
            'is_default' => true,
        ]);

        $transferId = (string) Str::uuid();
        \DB::table('stock_transfers')->insert([
            'id' => $transferId,
            'tenant_id' => $this->tenant->id,
            'from_branch_id' => $this->checkoutFixture['branch_id'],
            'to_branch_id' => $toBranchId,
            'from_location_id' => $this->checkoutFixture['location_id'],
            'to_location_id' => $toLocationId,
            'status' => 'approved',
            'reference' => 'TRX-1',
            'created_by' => $this->user->id,
            'created_at' => now('UTC'),
            'approved_at' => now('UTC'),
            'approved_by' => $this->user->id,
            'shipped_at' => null,
            'shipped_by' => null,
            'received_at' => null,
            'received_by' => null,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'transaction_date' => null,
        ]);

        \DB::table('stock_transfer_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'stock_transfer_id' => $transferId,
            'product_id' => $this->checkoutFixture['product_id'],
            'qty' => 1,
        ]);

        return $transferId;
    }

    private function seedInTransitTransfer(): string
    {
        $toBranchId = (string) Str::uuid();
        \DB::table('branches')->insert([
            'id' => $toBranchId,
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch C',
        ]);

        $toLocationId = (string) Str::uuid();
        \DB::table('locations')->insert([
            'id' => $toLocationId,
            'tenant_id' => $this->tenant->id,
            'branch_id' => $toBranchId,
            'name' => 'Warehouse C',
            'is_default' => true,
        ]);

        $transferId = (string) Str::uuid();
        \DB::table('stock_transfers')->insert([
            'id' => $transferId,
            'tenant_id' => $this->tenant->id,
            'from_branch_id' => $this->checkoutFixture['branch_id'],
            'to_branch_id' => $toBranchId,
            'from_location_id' => $this->checkoutFixture['location_id'],
            'to_location_id' => $toLocationId,
            'status' => 'in_transit',
            'reference' => 'TRX-2',
            'created_by' => $this->user->id,
            'created_at' => now('UTC'),
            'approved_at' => now('UTC'),
            'approved_by' => $this->user->id,
            'shipped_at' => now('UTC'),
            'shipped_by' => $this->user->id,
            'received_at' => null,
            'received_by' => null,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'transaction_date' => null,
        ]);

        \DB::table('stock_transfer_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'stock_transfer_id' => $transferId,
            'product_id' => $this->checkoutFixture['product_id'],
            'qty' => 1,
        ]);

        return $transferId;
    }

    private function seedApprovedAdjustment(): string
    {
        $adjustmentId = (string) Str::uuid();
        \DB::table('stock_adjustments')->insert([
            'id' => $adjustmentId,
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->checkoutFixture['product_id'],
            'location_id' => $this->checkoutFixture['location_id'],
            'type' => 'opname',
            'qty' => 1,
            'reason' => 'opname',
            'note' => null,
            'balance_before' => 0,
            'balance_after' => 1,
            'created_by' => $this->user->id,
            'created_at' => now('UTC'),
            'reference_no' => 'ADJ-1',
            'reason_code' => 'opname',
            'status' => 'approved',
            'notes' => null,
            'approved_at' => now('UTC'),
            'approved_by' => $this->user->id,
            'posted_at' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'transaction_date' => null,
        ]);

        \DB::table('stock_adjustment_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'stock_adjustment_id' => $adjustmentId,
            'product_id' => $this->checkoutFixture['product_id'],
            'system_qty' => 0,
            'physical_qty' => 1,
            'difference_qty' => 1,
            'unit_cost_snapshot' => 5000,
        ]);

        return $adjustmentId;
    }
}
