<?php

namespace Tests\Feature;

use App\Models\InventoryIssueAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class RefundApiContractTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_request_tanpa_sale_item_id_ditolak_dan_tidak_menimbulkan_side_effect(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+refund-api-missing-sale-item@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $saleId = $this->createSale($tenant->id, $this->checkoutPayload($fixture, '2026-02-27'));
        $stockBefore = $this->inventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id']);

        $response = $this->withHeaders($this->jsonTenantHeaders($tenant->id))
            ->postJson('/api/v1/refunds', [
                'sale_id' => $saleId,
                'register_id' => $fixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'reason' => 'Missing sale item id',
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'account_id' => $fixture['cash_account_id'],
                        'payment_method_id' => $fixture['payment_method_id'],
                        'method' => 'cash',
                        'amount' => 10000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.sale_item_id']);

        $this->assertNotSame('', (string) $response->json('message'));
        $this->assertSame(0, DB::table('refunds')->where('tenant_id', $tenant->id)->count());
        $this->assertSame($stockBefore, $this->inventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id']));
    }

    public function test_request_dengan_sale_item_id_tidak_ada_ditolak_dan_tidak_menimbulkan_side_effect(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+refund-api-sale-item-not-found@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $saleId = $this->createSale($tenant->id, $this->checkoutPayload($fixture, '2026-02-27'));
        $stockBefore = $this->inventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id']);

        $response = $this->withHeaders($this->jsonTenantHeaders($tenant->id))
            ->postJson('/api/v1/refunds', [
                'sale_id' => $saleId,
                'register_id' => $fixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'reason' => 'Sale item not found',
                'items' => [
                    [
                        'sale_item_id' => (string) Str::uuid(),
                        'qty' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'account_id' => $fixture['cash_account_id'],
                        'payment_method_id' => $fixture['payment_method_id'],
                        'method' => 'cash',
                        'amount' => 10000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.sale_item_id']);

        $this->assertNotSame('', (string) $response->json('message'));
        $this->assertSame(0, DB::table('refunds')->where('tenant_id', $tenant->id)->count());
        $this->assertSame($stockBefore, $this->inventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id']));
    }

    public function test_request_dengan_sale_item_id_milik_sale_lain_ditolak(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+refund-api-other-sale-item@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $firstSaleId = $this->createSale($tenant->id, $this->checkoutPayload($fixture, '2026-02-27'));
        $secondSaleId = $this->createSale($tenant->id, $this->checkoutPayload($fixture, '2026-02-27'));
        $foreignSaleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $secondSaleId)
            ->value('id');
        $stockBefore = $this->inventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id']);

        $response = $this->withHeaders($this->jsonTenantHeaders($tenant->id))
            ->postJson('/api/v1/refunds', [
                'sale_id' => $firstSaleId,
                'register_id' => $fixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'reason' => 'Foreign sale item id',
                'items' => [
                    [
                        'sale_item_id' => $foreignSaleItemId,
                        'qty' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'account_id' => $fixture['cash_account_id'],
                        'payment_method_id' => $fixture['payment_method_id'],
                        'method' => 'cash',
                        'amount' => 10000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_REFUND')
            ->assertJsonPath('message', 'Line item refund tidak ditemukan pada transaksi.');

        $this->assertSame(0, DB::table('refunds')->where('tenant_id', $tenant->id)->count());
        $this->assertSame($stockBefore, $this->inventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id']));
    }

    public function test_request_dari_tenant_lain_dengan_sale_item_id_lintas_tenant_ditolak_tanpa_side_effect(): void
    {
        [$tenantA, $userA] = $this->bootApiActor('admin', 'admin+refund-api-cross-tenant-a@example.test');
        $fixtureA = $this->seedCheckoutFixture($tenantA->id, $userA->id);
        $this->seedInventoryCostState($tenantA->id, $fixtureA['product_id'], $fixtureA['location_id'], 100, 5000);

        $saleAId = $this->createSale($tenantA->id, $this->checkoutPayload($fixtureA, '2026-02-27'));
        $saleItemAId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenantA->id)
            ->where('sale_id', $saleAId)
            ->value('id');
        $allocationACountBefore = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenantA->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleAId)
            ->count();

        [$tenantB, $userB] = $this->bootApiActor('admin', 'admin+refund-api-cross-tenant-b@example.test');
        $fixtureB = $this->seedCheckoutFixture($tenantB->id, $userB->id);
        $this->seedInventoryCostState($tenantB->id, $fixtureB['product_id'], $fixtureB['location_id'], 100, 5000);

        $saleBId = $this->createSale($tenantB->id, $this->checkoutPayload($fixtureB, '2026-02-27'));
        $stockBBefore = $this->inventoryBalance($tenantB->id, $fixtureB['product_id'], null, $fixtureB['location_id']);
        $allocationBCountBefore = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenantB->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleBId)
            ->count();

        $response = $this->withHeaders($this->jsonTenantHeaders($tenantB->id))
            ->postJson('/api/v1/refunds', [
                'sale_id' => $saleBId,
                'register_id' => $fixtureB['register_id'],
                'shift_id' => $fixtureB['shift_id'],
                'reason' => 'Cross tenant sale item id',
                'items' => [
                    [
                        'sale_item_id' => $saleItemAId,
                        'qty' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'account_id' => $fixtureB['cash_account_id'],
                        'payment_method_id' => $fixtureB['payment_method_id'],
                        'method' => 'cash',
                        'amount' => 10000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.sale_item_id']);

        $this->assertNotSame('', (string) $response->json('message'));
        $this->assertSame(0, DB::table('refunds')->where('tenant_id', $tenantB->id)->count());
        $this->assertSame($stockBBefore, $this->inventoryBalance($tenantB->id, $fixtureB['product_id'], null, $fixtureB['location_id']));
        $this->assertSame($allocationBCountBefore, InventoryIssueAllocation::query()
            ->where('tenant_id', $tenantB->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleBId)
            ->count());
        $this->assertSame($allocationACountBefore, InventoryIssueAllocation::query()
            ->where('tenant_id', $tenantA->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleAId)
            ->count());
        $this->assertSame(99.0, $this->inventoryBalance($tenantA->id, $fixtureA['product_id'], null, $fixtureA['location_id']));
    }

    public function test_request_dengan_product_id_yang_tidak_cocok_dengan_sale_item_id_ditolak(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+refund-api-product-mismatch@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $otherProductId = $this->createProduct($tenant->id, $user->id, 'OTHER-PRODUCT-REFUND', 'Produk Lain Refund');
        $saleId = $this->createSale($tenant->id, $this->checkoutPayload($fixture, '2026-02-27'));
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');
        $stockBefore = $this->inventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id']);

        $response = $this->withHeaders($this->jsonTenantHeaders($tenant->id))
            ->postJson('/api/v1/refunds', [
                'sale_id' => $saleId,
                'register_id' => $fixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'reason' => 'Product mismatch',
                'items' => [
                    [
                        'sale_item_id' => $saleItemId,
                        'product_id' => $otherProductId,
                        'qty' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'account_id' => $fixture['cash_account_id'],
                        'payment_method_id' => $fixture['payment_method_id'],
                        'method' => 'cash',
                        'amount' => 10000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_REFUND')
            ->assertJsonPath('message', 'product_id refund tidak sesuai sale_item_id.');

        $this->assertSame(0, DB::table('refunds')->where('tenant_id', $tenant->id)->count());
        $this->assertSame($stockBefore, $this->inventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id']));
    }

    public function test_refund_request_with_same_idempotency_key_replays_existing_refund(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+refund-idempotent@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $saleId = $this->createSale($tenant->id, $this->checkoutPayload($fixture, '2026-02-27'));
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        $headers = array_merge($this->jsonTenantHeaders($tenant->id), [
            'Idempotency-Key' => 'refund-replay-001',
        ]);

        $payload = [
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'reason' => 'Refund idempotent replay',
            'items' => [
                [
                    'sale_item_id' => $saleItemId,
                    'qty' => 1,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $fixture['cash_account_id'],
                    'payment_method_id' => $fixture['payment_method_id'],
                    'method' => 'cash',
                    'amount' => 10000,
                ],
            ],
        ];

        $first = $this->withHeaders($headers)->postJson('/api/v1/refunds', $payload);
        $first->assertCreated()->assertJsonPath('idempotent', false);

        $refundId = (string) $first->json('refund_id');

        $second = $this->withHeaders($headers)->postJson('/api/v1/refunds', $payload);
        $second->assertOk()
            ->assertJsonPath('refund_id', $refundId)
            ->assertJsonPath('idempotent', true);

        $this->assertSame(1, DB::table('refunds')->where('tenant_id', $tenant->id)->count());
    }

    public function test_request_dengan_product_variant_id_yang_tidak_cocok_dengan_sale_item_id_ditolak(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+refund-api-variant-mismatch@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $saleId = $this->createSale($tenant->id, $this->checkoutPayload($fixture, '2026-02-27'));
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');
        $variantId = $this->createVariant($tenant->id, $fixture['product_id']);
        $stockBefore = $this->inventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id']);

        $response = $this->withHeaders($this->jsonTenantHeaders($tenant->id))
            ->postJson('/api/v1/refunds', [
                'sale_id' => $saleId,
                'register_id' => $fixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'reason' => 'Variant mismatch',
                'items' => [
                    [
                        'sale_item_id' => $saleItemId,
                        'product_id' => $fixture['product_id'],
                        'product_variant_id' => $variantId,
                        'qty' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'account_id' => $fixture['cash_account_id'],
                        'payment_method_id' => $fixture['payment_method_id'],
                        'method' => 'cash',
                        'amount' => 10000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_REFUND')
            ->assertJsonPath('message', 'product_variant_id refund tidak sesuai sale_item_id.');

        $this->assertSame(0, DB::table('refunds')->where('tenant_id', $tenant->id)->count());
        $this->assertSame($stockBefore, $this->inventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id']));
    }

    public function test_request_partial_refund_valid_berhasil(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+refund-api-partial-valid@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'transaction_date' => '2026-02-27',
            'subtotal' => 18000,
            'discount' => 1800,
            'tax' => 1620,
            'total' => 17820,
            'items' => [
                [
                    'product_id' => $fixture['product_id'],
                    'location_id' => $fixture['location_id'],
                    'qty' => 2,
                    'price' => 10000,
                    'discount' => 2000,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $fixture['cash_account_id'],
                    'method' => 'cash',
                    'amount' => 17820,
                    'payment_method_id' => $fixture['payment_method_id'],
                ],
            ],
        ];

        $saleId = $this->createSale($tenant->id, $payload);
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        $response = $this->withHeaders($this->jsonTenantHeaders($tenant->id))
            ->postJson('/api/v1/refunds', [
                'sale_id' => $saleId,
                'register_id' => $fixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'reason' => 'Partial refund valid',
                'items' => [
                    [
                        'sale_item_id' => $saleItemId,
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'account_id' => $fixture['cash_account_id'],
                        'payment_method_id' => $fixture['payment_method_id'],
                        'method' => 'cash',
                        'amount' => 8910,
                    ],
                ],
            ]);

        $response->assertCreated();
        $this->assertNotSame('', (string) $response->json('refund_id'));

        $this->assertSame(1, DB::table('refunds')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(99.0, $this->inventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id']));

        $allocation = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->first();

        $this->assertNotNull($allocation);
        $this->assertSame(1.0, (float) $allocation->reversed_qty);
        $this->assertNull($allocation->reversed_at);
    }

    public function test_request_over_refund_ditolak_tanpa_side_effect_tambahan(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+refund-api-over-refund@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'transaction_date' => '2026-02-27',
            'subtotal' => 18000,
            'discount' => 1800,
            'tax' => 1620,
            'total' => 17820,
            'items' => [
                [
                    'product_id' => $fixture['product_id'],
                    'location_id' => $fixture['location_id'],
                    'qty' => 2,
                    'price' => 10000,
                    'discount' => 2000,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $fixture['cash_account_id'],
                    'method' => 'cash',
                    'amount' => 17820,
                    'payment_method_id' => $fixture['payment_method_id'],
                ],
            ],
        ];

        $saleId = $this->createSale($tenant->id, $payload);
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        $validResponse = $this->withHeaders($this->jsonTenantHeaders($tenant->id))
            ->postJson('/api/v1/refunds', [
                'sale_id' => $saleId,
                'register_id' => $fixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'reason' => 'Partial refund valid first',
                'items' => [
                    [
                        'sale_item_id' => $saleItemId,
                        'qty' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'account_id' => $fixture['cash_account_id'],
                        'payment_method_id' => $fixture['payment_method_id'],
                        'method' => 'cash',
                        'amount' => 8910,
                    ],
                ],
            ]);

        $validResponse->assertCreated();

        $refundCountBeforeInvalid = DB::table('refunds')->where('tenant_id', $tenant->id)->count();
        $stockBeforeInvalid = $this->inventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id']);

        $response = $this->withHeaders($this->jsonTenantHeaders($tenant->id))
            ->postJson('/api/v1/refunds', [
                'sale_id' => $saleId,
                'register_id' => $fixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'reason' => 'Over refund invalid',
                'items' => [
                    [
                        'sale_item_id' => $saleItemId,
                        'qty' => 2,
                    ],
                ],
                'payments' => [
                    [
                        'account_id' => $fixture['cash_account_id'],
                        'payment_method_id' => $fixture['payment_method_id'],
                        'method' => 'cash',
                        'amount' => 17820,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_REFUND')
            ->assertJsonPath('message', 'Qty refund melebihi qty transaksi.');

        $this->assertSame($refundCountBeforeInvalid, DB::table('refunds')->where('tenant_id', $tenant->id)->count());
        $this->assertSame($stockBeforeInvalid, $this->inventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id']));
    }

    private function jsonTenantHeaders(string $tenantId): array
    {
        return array_merge($this->tenantHeaders($tenantId), [
            'Accept' => 'application/json',
        ]);
    }

    private function seedInventoryCostState(string $tenantId, string $productId, string $locationId, float $qtyOnHand, float $averageCost): void
    {
        DB::table('inventory_cost_states')->updateOrInsert([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'location_id' => $locationId,
        ], [
            'id' => (string) Str::uuid(),
            'qty_on_hand' => $qtyOnHand,
            'average_cost' => $averageCost,
            'last_cost' => $averageCost,
            'updated_at' => now('UTC'),
        ]);
    }

    private function createSale(string $tenantId, array $payload): string
    {
        $response = $this->withHeaders($this->jsonTenantHeaders($tenantId))
            ->postJson('/api/v1/checkout', $payload);

        $response->assertCreated();

        return (string) $response->json('sale_id');
    }

    private function inventoryBalance(string $tenantId, string $productId, ?string $variantId, string $locationId): float
    {
        return (float) DB::table('inventory_balances')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->when($variantId, function ($query) use ($variantId) {
                $query->where('product_variant_id', $variantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            })
            ->value('qty_on_hand');
    }

    private function createProduct(string $tenantId, string $userId, string $sku, string $name): string
    {
        $productId = (string) Str::uuid();

        DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $tenantId,
            'sku' => $sku,
            'name' => $name,
            'price' => 1000,
            'hpp_average' => 1000,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'product_mode' => 'add_on',
            'item_type' => 'raw_material',
            'variants' => json_encode([]),
            'bom_items' => json_encode([]),
            'bundle_items' => json_encode([]),
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        return $productId;
    }

    private function createVariant(string $tenantId, string $productId): string
    {
        $variantId = (string) Str::uuid();

        DB::table('product_variants')->insert([
            'id' => $variantId,
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'sku' => 'SKU-VAR-'.substr(str_replace('-', '', $variantId), 0, 8),
            'barcode' => null,
            'name' => 'Variant API Refund',
            'attributes' => json_encode(['ukuran' => 'L']),
            'price_override' => null,
            'is_active' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return $variantId;
    }
}