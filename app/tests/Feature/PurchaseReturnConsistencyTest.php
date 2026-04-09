<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class PurchaseReturnConsistencyTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-return@example.com', 'Admin Return');
        $this->fixture = $this->seedCheckoutFixture($this->tenant->id, $this->user->id);
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_purchase_return_rejects_mismatched_supplier(): void
    {
        $purchaseId = (string) Str::uuid();
        $purchaseItemId = (string) Str::uuid();
        $purchaseSupplierId = (string) Str::uuid();
        $otherSupplierId = (string) Str::uuid();

        \DB::table('suppliers')->insert([
            [
                'id' => $purchaseSupplierId,
                'tenant_id' => $this->tenant->id,
                'name' => 'Supplier Asal',
                'balance' => 0,
                'created_at' => now('UTC'),
            ],
            [
                'id' => $otherSupplierId,
                'tenant_id' => $this->tenant->id,
                'name' => 'Supplier Lain',
                'balance' => 0,
                'created_at' => now('UTC'),
            ],
        ]);

        \DB::table('purchases')->insert([
            'id' => $purchaseId,
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $purchaseSupplierId,
            'location_id' => $this->fixture['location_id'],
            'total' => 10000,
            'status' => 'received',
            'notes' => null,
            'created_at' => now('UTC'),
            'approved_at' => now('UTC'),
            'approved_by' => $this->user->id,
            'received_at' => now('UTC'),
            'received_by' => $this->user->id,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'transaction_date' => '2026-02-27',
        ]);

        \DB::table('purchase_items')->insert([
            'id' => $purchaseItemId,
            'tenant_id' => $this->tenant->id,
            'purchase_id' => $purchaseId,
            'product_id' => $this->fixture['product_id'],
            'qty' => 1,
            'received_qty' => 1,
            'price' => 10000,
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchaseId,
                'supplier_id' => $otherSupplierId,
                'items' => [
                    [
                        'purchase_item_id' => $purchaseItemId,
                        'qty' => 1,
                        'price' => 10000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'PURCHASE_SUPPLIER_MISMATCH',
            ]);
    }

    public function test_purchase_return_rejects_purchase_from_other_tenant(): void
    {
        $otherTenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Return Lain',
            'timezone' => 'UTC',
        ]);

        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $this->user->id);
        $otherSupplierId = (string) Str::uuid();
        $otherPurchaseId = (string) Str::uuid();
        $otherPurchaseItemId = (string) Str::uuid();

        \DB::table('suppliers')->insert([
            'id' => $otherSupplierId,
            'tenant_id' => $otherTenant->id,
            'name' => 'Supplier Tenant Lain',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        \DB::table('purchases')->insert([
            'id' => $otherPurchaseId,
            'tenant_id' => $otherTenant->id,
            'supplier_id' => $otherSupplierId,
            'location_id' => $otherFixture['location_id'],
            'total' => 10000,
            'status' => 'received',
            'notes' => null,
            'created_at' => now('UTC'),
            'approved_at' => now('UTC'),
            'approved_by' => $this->user->id,
            'received_at' => now('UTC'),
            'received_by' => $this->user->id,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'transaction_date' => '2026-02-27',
        ]);

        \DB::table('purchase_items')->insert([
            'id' => $otherPurchaseItemId,
            'tenant_id' => $otherTenant->id,
            'purchase_id' => $otherPurchaseId,
            'product_id' => $otherFixture['product_id'],
            'qty' => 1,
            'received_qty' => 1,
            'price' => 10000,
        ]);

        $ownSupplierId = (string) Str::uuid();
        \DB::table('suppliers')->insert([
            'id' => $ownSupplierId,
            'tenant_id' => $this->tenant->id,
            'name' => 'Supplier Sendiri',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $otherPurchaseId,
                'supplier_id' => $ownSupplierId,
                'items' => [
                    [
                        'purchase_item_id' => $otherPurchaseItemId,
                        'qty' => 1,
                        'price' => 10000,
                    ],
                ],
            ]);

        $this->assertValidationErrorKey($response, 'purchase_id');
    }

    public function test_purchase_return_rejects_supplier_from_other_tenant(): void
    {
        $purchaseId = (string) Str::uuid();
        $purchaseItemId = (string) Str::uuid();
        $purchaseSupplierId = (string) Str::uuid();
        $otherTenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Supplier Return Lain',
            'timezone' => 'UTC',
        ]);
        $otherSupplierId = (string) Str::uuid();

        \DB::table('suppliers')->insert([
            [
                'id' => $purchaseSupplierId,
                'tenant_id' => $this->tenant->id,
                'name' => 'Supplier Asal',
                'balance' => 0,
                'created_at' => now('UTC'),
            ],
        ]);

        \DB::table('suppliers')->insert([
            'id' => $otherSupplierId,
            'tenant_id' => $otherTenant->id,
            'name' => 'Supplier Tenant Lain',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        \DB::table('purchases')->insert([
            'id' => $purchaseId,
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $purchaseSupplierId,
            'location_id' => $this->fixture['location_id'],
            'total' => 10000,
            'status' => 'received',
            'notes' => null,
            'created_at' => now('UTC'),
            'approved_at' => now('UTC'),
            'approved_by' => $this->user->id,
            'received_at' => now('UTC'),
            'received_by' => $this->user->id,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'transaction_date' => '2026-02-27',
        ]);

        \DB::table('purchase_items')->insert([
            'id' => $purchaseItemId,
            'tenant_id' => $this->tenant->id,
            'purchase_id' => $purchaseId,
            'product_id' => $this->fixture['product_id'],
            'qty' => 1,
            'received_qty' => 1,
            'price' => 10000,
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchaseId,
                'supplier_id' => $otherSupplierId,
                'items' => [
                    [
                        'purchase_item_id' => $purchaseItemId,
                        'qty' => 1,
                        'price' => 10000,
                    ],
                ],
            ]);

        $this->assertValidationErrorKey($response, 'supplier_id');
    }

    public function test_purchase_return_rejects_product_from_other_tenant(): void
    {
        $purchaseId = (string) Str::uuid();
        $purchaseSupplierId = (string) Str::uuid();
        $otherTenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Produk Return Lain',
            'timezone' => 'UTC',
        ]);

        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $this->user->id);
        $otherSupplierId = (string) Str::uuid();
        $otherPurchaseId = (string) Str::uuid();
        $otherPurchaseItemId = (string) Str::uuid();

        \DB::table('suppliers')->insert([
            'id' => $purchaseSupplierId,
            'tenant_id' => $this->tenant->id,
            'name' => 'Supplier Asal',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        \DB::table('suppliers')->insert([
            'id' => $otherSupplierId,
            'tenant_id' => $otherTenant->id,
            'name' => 'Supplier Tenant Produk Lain',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        \DB::table('purchases')->insert([
            'id' => $purchaseId,
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $purchaseSupplierId,
            'location_id' => $this->fixture['location_id'],
            'total' => 10000,
            'status' => 'received',
            'notes' => null,
            'created_at' => now('UTC'),
            'approved_at' => now('UTC'),
            'approved_by' => $this->user->id,
            'received_at' => now('UTC'),
            'received_by' => $this->user->id,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'transaction_date' => '2026-02-27',
        ]);

        \DB::table('purchases')->insert([
            'id' => $otherPurchaseId,
            'tenant_id' => $otherTenant->id,
            'supplier_id' => $otherSupplierId,
            'location_id' => $otherFixture['location_id'],
            'total' => 10000,
            'status' => 'received',
            'notes' => null,
            'created_at' => now('UTC'),
            'approved_at' => now('UTC'),
            'approved_by' => $this->user->id,
            'received_at' => now('UTC'),
            'received_by' => $this->user->id,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'transaction_date' => '2026-02-27',
        ]);

        \DB::table('purchase_items')->insert([
            'id' => $otherPurchaseItemId,
            'tenant_id' => $otherTenant->id,
            'purchase_id' => $otherPurchaseId,
            'product_id' => $otherFixture['product_id'],
            'qty' => 1,
            'received_qty' => 1,
            'price' => 10000,
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchaseId,
                'supplier_id' => $purchaseSupplierId,
                'items' => [
                    [
                        'purchase_item_id' => $otherPurchaseItemId,
                        'qty' => 1,
                        'price' => 10000,
                    ],
                ],
            ]);

        $this->assertValidationErrorKey($response, 'items.0.purchase_item_id');
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
