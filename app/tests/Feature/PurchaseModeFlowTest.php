<?php

namespace Tests\Feature;

use App\Support\PurchaseMode;
use App\Support\PurchaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class PurchaseModeFlowTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_simple_mode_purchase_is_received_immediately(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-mode-simple@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Simple');

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'location_id' => $fixture['location_id'],
                'purchase_mode' => PurchaseMode::SIMPLE,
                'status' => PurchaseStatus::SUBMITTED,
                'payment_method' => 'tempo',
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 2,
                        'price' => 10000,
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', PurchaseStatus::RECEIVED);

        $purchaseId = (string) DB::table('purchases')
            ->where('tenant_id', $tenant->id)
            ->where('supplier_id', $supplierId)
            ->where('purchase_mode', PurchaseMode::SIMPLE)
            ->value('id');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'tenant_id' => $tenant->id,
            'status' => PurchaseStatus::RECEIVED,
            'purchase_mode' => PurchaseMode::SIMPLE,
        ]);

        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchaseId,
            'product_id' => $fixture['product_id'],
            'received_qty' => 2,
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['product_id'],
            'qty_on_hand' => 102,
        ]);
    }

    public function test_purchase_unit_is_converted_to_product_stock_unit(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-unit-conversion@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Unit Conversion');

        DB::table('products')
            ->where('id', $fixture['product_id'])
            ->update([
                'name' => 'Gandum',
                'unit' => 'gram',
                'item_type' => 'raw_material',
                'price' => 0,
            ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'location_id' => $fixture['location_id'],
                'purchase_mode' => PurchaseMode::SIMPLE,
                'status' => PurchaseStatus::SUBMITTED,
                'payment_method' => 'tempo',
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                        'purchase_unit' => 'kg',
                        'price' => 12000,
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', PurchaseStatus::RECEIVED)
            ->assertJsonPath('received_total', 12000);

        $purchaseId = (string) $response->json('purchase_id');
        $item = DB::table('purchase_items')->where('purchase_id', $purchaseId)->first();

        $this->assertSame(1000.0, (float) $item->qty);
        $this->assertSame(1000.0, (float) $item->received_qty);
        $this->assertSame(1.0, (float) $item->purchase_qty);
        $this->assertSame('kg', $item->purchase_unit);
        $this->assertSame(1000.0, (float) $item->purchase_unit_multiplier);
        $this->assertSame(12000.0, (float) $item->purchase_price);
        $this->assertSame(12.0, (float) $item->price);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['product_id'],
            'qty_on_hand' => 1100,
        ]);

        $detail = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/purchases/'.$purchaseId);

        $detail->assertOk()
            ->assertJsonPath('items.0.qty', 1000)
            ->assertJsonPath('items.0.unit', 'gram')
            ->assertJsonPath('items.0.purchase_qty', 1)
            ->assertJsonPath('items.0.purchase_unit', 'kg')
            ->assertJsonPath('items.0.purchase_price', 12000)
            ->assertJsonPath('items.0.received_purchase_qty', 1);
    }

    public function test_po_short_mode_can_be_received_without_approval(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-mode-po-short@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier PO Short');

        $createResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'location_id' => $fixture['location_id'],
                'purchase_mode' => PurchaseMode::PO_SHORT,
                'status' => PurchaseStatus::SUBMITTED,
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 5,
                        'price' => 10000,
                    ],
                ],
            ]);

        $createResponse->assertCreated();
        $purchaseId = (string) $createResponse->json('purchase_id');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'tenant_id' => $tenant->id,
            'status' => PurchaseStatus::SUBMITTED,
            'purchase_mode' => PurchaseMode::PO_SHORT,
        ]);

        $receiveResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 5,
                    ],
                ],
            ]);

        $receiveResponse->assertOk()
            ->assertJsonPath('status', PurchaseStatus::RECEIVED);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::RECEIVED,
        ]);
    }

    public function test_controlled_mode_requires_approval_before_receive(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-mode-controlled@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Controlled');

        $createResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'location_id' => $fixture['location_id'],
                'purchase_mode' => PurchaseMode::CONTROLLED,
                'status' => PurchaseStatus::SUBMITTED,
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 3,
                        'price' => 10000,
                    ],
                ],
            ]);

        $createResponse->assertCreated();
        $purchaseId = (string) $createResponse->json('purchase_id');

        $blockedResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                    ],
                ],
            ]);

        $blockedResponse->assertStatus(422)
            ->assertJsonPath('code', 'PURCHASE_RECEIVE_FAILED')
            ->assertJsonPath('message', 'PO harus disetujui dulu sebelum menerima barang.');

        $approveResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/decision', [
                'status' => PurchaseStatus::APPROVED,
            ]);

        $approveResponse->assertOk();

        $receiveResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 3,
                    ],
                ],
            ]);

        $receiveResponse->assertOk()
            ->assertJsonPath('status', PurchaseStatus::RECEIVED);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::RECEIVED,
            'purchase_mode' => PurchaseMode::CONTROLLED,
        ]);
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
