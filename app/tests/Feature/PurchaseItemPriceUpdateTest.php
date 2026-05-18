<?php

namespace Tests\Feature;

use App\Support\PurchaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class PurchaseItemPriceUpdateTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_update_purchase_item_price_before_receiving_updates_item_and_total(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-item-price-edit@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Harga');

        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id']);
        $itemId = (string) DB::table('purchase_items')->where('purchase_id', $purchaseId)->value('id');

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/api/v1/purchases/'.$purchaseId.'/items/'.$itemId.'/price', [
                'price' => 12500,
            ]);

        $response->assertOk()
            ->assertJsonPath('purchase.total', 12500)
            ->assertJsonPath('item.price', 12500);

        $this->assertDatabaseHas('purchase_items', [
            'id' => $itemId,
            'tenant_id' => $tenant->id,
            'price' => 12500,
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'tenant_id' => $tenant->id,
            'status' => PurchaseStatus::SUBMITTED,
            'total' => 12500,
        ]);
    }

    public function test_update_purchase_item_price_is_blocked_after_receiving_started(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-item-price-blocked@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Terkunci');

        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id']);
        $itemId = (string) DB::table('purchase_items')->where('purchase_id', $purchaseId)->value('id');

        DB::table('purchase_items')
            ->where('id', $itemId)
            ->update(['received_qty' => 1]);

        DB::table('purchases')
            ->where('id', $purchaseId)
            ->update(['status' => PurchaseStatus::PARTIALLY_RECEIVED]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/api/v1/purchases/'.$purchaseId.'/items/'.$itemId.'/price', [
                'price' => 15000,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Harga hanya bisa diubah sebelum penerimaan barang dimulai.');
    }

    private function createPurchase(string $tenantId, string $supplierId, string $locationId, string $productId): string
    {
        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'location_id' => $locationId,
                'status' => PurchaseStatus::SUBMITTED,
                'payment_method' => 'tempo',
                'items' => [
                    [
                        'product_id' => $productId,
                        'qty' => 1,
                        'price' => 10000,
                    ],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('purchase_id');
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