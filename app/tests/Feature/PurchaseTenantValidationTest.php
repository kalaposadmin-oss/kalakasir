<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class PurchaseTenantValidationTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_store_purchase_rejects_supplier_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+purchase-supplier-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Valid');
        $otherTenant = $this->createOtherTenant('Tenant Supplier Lain');
        $otherSupplierId = $this->insertSupplier($otherTenant->id, 'Supplier Tenant Lain');

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $otherSupplierId,
                'location_id' => $fixture['location_id'],
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                        'price' => 10000,
                    ],
                ],
            ]);

        $this->assertValidationErrorKey($response, 'supplier_id');
    }

    public function test_store_purchase_rejects_location_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+purchase-location-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Valid');
        $otherTenant = $this->createOtherTenant('Tenant Lokasi Lain');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $user->id);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'location_id' => $otherFixture['location_id'],
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                        'price' => 10000,
                    ],
                ],
            ]);

        $this->assertValidationErrorKey($response, 'location_id');
    }

    public function test_store_purchase_rejects_product_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+purchase-product-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Valid');
        $otherTenant = $this->createOtherTenant('Tenant Produk Lain');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $user->id);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'location_id' => $fixture['location_id'],
                'items' => [
                    [
                        'product_id' => $otherFixture['product_id'],
                        'qty' => 1,
                        'price' => 10000,
                    ],
                ],
            ]);

        $this->assertValidationErrorKey($response, 'items.0.product_id');
    }

    public function test_receive_purchase_rejects_product_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+purchase-receive-product-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Receive');
        $purchaseId = $this->insertApprovedPurchase($tenant->id, $user->id, $supplierId, $fixture['location_id'], $fixture['product_id']);
        $otherTenant = $this->createOtherTenant('Tenant Receive Produk Lain');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $user->id);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $otherFixture['product_id'],
                        'qty' => 1,
                    ],
                ],
            ]);

        $this->assertValidationErrorKey($response, 'items.0.product_id');
    }

    private function createOtherTenant(string $name): Tenant
    {
        return Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'timezone' => 'UTC',
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

    private function insertApprovedPurchase(string $tenantId, string $userId, string $supplierId, string $locationId, string $productId): string
    {
        $purchaseId = (string) Str::uuid();

        DB::table('purchases')->insert([
            'id' => $purchaseId,
            'tenant_id' => $tenantId,
            'supplier_id' => $supplierId,
            'location_id' => $locationId,
            'total' => 10000,
            'status' => 'approved',
            'notes' => null,
            'created_at' => now('UTC'),
            'approved_at' => now('UTC'),
            'approved_by' => $userId,
            'received_at' => null,
            'received_by' => null,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'transaction_date' => null,
        ]);

        DB::table('purchase_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'purchase_id' => $purchaseId,
            'product_id' => $productId,
            'qty' => 1,
            'received_qty' => 0,
            'price' => 10000,
        ]);

        return $purchaseId;
    }

    private function assertValidationErrorKey($response, string $key): void
    {
        $response->assertStatus(422);

        $this->assertNotSame('', (string) $response->json('message'));

        $errors = (array) $response->json('errors');

        $this->assertArrayHasKey($key, $errors);
        $this->assertIsArray($errors[$key] ?? null);
        $this->assertNotEmpty($errors[$key] ?? []);
    }
}