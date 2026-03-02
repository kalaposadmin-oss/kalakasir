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

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchaseId,
                'supplier_id' => $otherSupplierId,
                'items' => [
                    [
                        'product_id' => $this->fixture['product_id'],
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
}
