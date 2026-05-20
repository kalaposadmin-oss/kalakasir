<?php

namespace Tests\Feature;

use App\Support\PurchaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class PurchaseWorkflowStateMachineTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_create_purchase_draft_persists_draft_status(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-draft@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Draft');

        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::DRAFT);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'tenant_id' => $tenant->id,
            'status' => PurchaseStatus::DRAFT,
        ]);
    }

    public function test_submit_purchase_persists_submitted_status(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-submit@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Submitted');

        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'tenant_id' => $tenant->id,
            'status' => PurchaseStatus::SUBMITTED,
        ]);
    }

    public function test_approve_purchase_from_submitted_status_succeeds(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-approve@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Approve');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/decision', [
                'status' => PurchaseStatus::APPROVED,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::APPROVED,
        ]);
    }

    public function test_approve_purchase_from_invalid_status_is_rejected(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-approve-invalid@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Draft Approve');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::DRAFT);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/decision', [
                'status' => PurchaseStatus::APPROVED,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'PURCHASE_DECISION_FAILED');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::DRAFT,
        ]);
    }

    public function test_receive_purchase_from_approved_status_succeeds(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-receive-approved@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Receive Full');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED, 5, 10000);

        $this->approvePurchase($tenant->id, $purchaseId);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 5,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('status', PurchaseStatus::RECEIVED);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::RECEIVED,
        ]);

        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchaseId,
            'product_id' => $fixture['product_id'],
            'received_qty' => 5,
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['product_id'],
            'qty_on_hand' => 105,
        ]);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'purchase',
            'reference_id' => $purchaseId,
            'qty_in' => 5,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/purchases/'.$purchaseId)
            ->assertOk()
            ->assertJsonPath('purchase.status', PurchaseStatus::RECEIVED)
            ->assertJsonPath('flow.can_close', false);

        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'purchase',
            'reference_id' => $purchaseId,
            'status' => 'posted',
        ]);
    }

    public function test_receive_purchase_from_partially_received_status_succeeds(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-receive-partial@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Receive Partial');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED, 5, 10000);

        $this->approvePurchase($tenant->id, $purchaseId);

        $firstResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 3,
                    ],
                ],
            ]);

        $firstResponse->assertOk()
            ->assertJsonPath('status', PurchaseStatus::PARTIALLY_RECEIVED);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::PARTIALLY_RECEIVED,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/purchases/'.$purchaseId)
            ->assertOk()
            ->assertJsonPath('purchase.status', PurchaseStatus::PARTIALLY_RECEIVED)
            ->assertJsonPath('flow.can_close', true);

        $secondResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 2,
                    ],
                ],
            ]);

        $secondResponse->assertOk()
            ->assertJsonPath('status', PurchaseStatus::RECEIVED);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::RECEIVED,
        ]);

        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchaseId,
            'product_id' => $fixture['product_id'],
            'received_qty' => 5,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/purchases/'.$purchaseId)
            ->assertOk()
            ->assertJsonPath('purchase.status', PurchaseStatus::RECEIVED)
            ->assertJsonPath('flow.can_close', false);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['product_id'],
            'qty_on_hand' => 105,
        ]);

        $this->assertSame(2, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->count());

        $this->assertSame(2, DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->count());
    }

    public function test_receive_purchase_from_submitted_status_is_rejected(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-receive-submitted@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Submitted Receive');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED, 5, 10000);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'PURCHASE_RECEIVE_FAILED')
            ->assertJsonPath('message', 'PO harus disetujui dulu sebelum menerima barang.');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::SUBMITTED,
        ]);

        $this->assertSame(0, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->count());

        $this->assertSame(0, DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->count());
    }

    public function test_receive_purchase_over_qty_is_rejected(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-receive-overqty@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Over Qty');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED, 5, 10000);

        $this->approvePurchase($tenant->id, $purchaseId);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 6,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'PURCHASE_RECEIVE_FAILED')
            ->assertJsonPath('message', 'Qty received melebihi sisa untuk produk tertentu.');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::APPROVED,
        ]);

        $this->assertSame(0, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->count());

        $this->assertSame(0, DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->count());
    }

    public function test_receive_closed_purchase_is_rejected(): void
    {
        $this->assertReceiveBlockedForStatus(PurchaseStatus::CLOSED, 'PO sudah selesai atau dibatalkan, tidak bisa menerima barang lagi.');
    }

    public function test_receive_cancelled_purchase_is_rejected(): void
    {
        $this->assertReceiveBlockedForStatus(PurchaseStatus::CANCELLED, 'PO sudah selesai atau dibatalkan, tidak bisa menerima barang lagi.');
    }

    private function assertReceiveBlockedForStatus(string $status, string $message): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-receive-blocked-'.Str::random(8).'@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Blocked');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED, 5, 10000);

        DB::table('purchases')
            ->where('id', $purchaseId)
            ->where('tenant_id', $tenant->id)
            ->update(['status' => $status]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'PURCHASE_RECEIVE_FAILED')
            ->assertJsonPath('message', $message);
    }

    private function createPurchase(string $tenantId, string $supplierId, string $locationId, string $productId, string $status, float $qty = 1, float $price = 10000): string
    {
        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'location_id' => $locationId,
                'status' => $status,
                'items' => [
                    [
                        'product_id' => $productId,
                        'qty' => $qty,
                        'price' => $price,
                    ],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('purchase_id');
    }

    private function approvePurchase(string $tenantId, string $purchaseId): void
    {
        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/decision', [
                'status' => PurchaseStatus::APPROVED,
            ]);

        $response->assertOk();
    }

    private function receivePurchase(string $tenantId, string $purchaseId, string $productId, float $qty): void
    {
        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $productId,
                        'qty' => $qty,
                    ],
                ],
            ]);

        $response->assertOk();
    }

    private function insertSupplier(string $tenantId, string $name): string
    {
        $supplierId = (string) Str::uuid();

        DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $tenantId,
            'name' => $name,
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        return $supplierId;
    }
}
