<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class VoidServiceInventoryReversalTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_void_sale_biasa_mengembalikan_stok_dan_membuat_pasangan_ledger_sale_void(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+void-basic-stock@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $saleId = $this->createSale($tenant->id, $fixture);
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        $this->assertInventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id'], 99.0);

        $this->voidSale($tenant->id, $saleId)
            ->assertOk()
            ->assertJsonPath('status', 'voided');

        $this->assertInventoryBalance($tenant->id, $fixture['product_id'], null, $fixture['location_id'], 100.0);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 0,
            'qty_out' => 1,
        ]);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'void',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 1,
            'qty_out' => 0,
        ]);
    }

    public function test_void_sale_dengan_bom_mengembalikan_bahan_baku_dan_membuat_pasangan_ledger_recipe(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+void-bom-stock@example.test');
        $ctx = $this->prepareRecipeCheckoutContext($tenant->id, $user->id, rawMaterialStock: 10);
        $this->seedInventoryCostState($tenant->id, $ctx['fixture']['product_id'], $ctx['fixture']['location_id'], 100, 5000);

        $saleId = $this->createSale($tenant->id, $ctx['payload']);
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        $this->assertInventoryBalance($tenant->id, $ctx['fixture']['product_id'], null, $ctx['fixture']['location_id'], 99.0);
        $this->assertInventoryBalance($tenant->id, $ctx['raw_material_id'], null, $ctx['fixture']['location_id'], 8.0);

        $this->voidSale($tenant->id, $saleId)->assertOk();

        $this->assertInventoryBalance($tenant->id, $ctx['fixture']['product_id'], null, $ctx['fixture']['location_id'], 100.0);
        $this->assertInventoryBalance($tenant->id, $ctx['raw_material_id'], null, $ctx['fixture']['location_id'], 10.0);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $ctx['fixture']['product_id'],
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_out' => 1,
        ]);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $ctx['fixture']['product_id'],
            'reference_type' => 'void',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 1,
        ]);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $ctx['raw_material_id'],
            'product_variant_id' => null,
            'location_id' => $ctx['fixture']['location_id'],
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 0,
            'qty_out' => 2,
        ]);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $ctx['raw_material_id'],
            'product_variant_id' => null,
            'location_id' => $ctx['fixture']['location_id'],
            'reference_type' => 'void_recipe_input',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 2,
            'qty_out' => 0,
        ]);
    }

    public function test_void_sale_bom_variant_raw_material_mengembalikan_variant_yang_benar(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+void-bom-variant-raw-material@example.test');
        $ctx = $this->prepareRecipeCheckoutContext($tenant->id, $user->id, rawMaterialStock: 10, rawMaterialHasVariants: true);
        $this->seedInventoryCostState($tenant->id, $ctx['fixture']['product_id'], $ctx['fixture']['location_id'], 100, 5000);

        $saleId = $this->createSale($tenant->id, $ctx['payload']);
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        $this->assertInventoryBalance($tenant->id, $ctx['raw_material_id'], $ctx['raw_material_variant_id'], $ctx['fixture']['location_id'], 8.0);
        $this->assertSame(0, DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $ctx['raw_material_id'])
            ->where('location_id', $ctx['fixture']['location_id'])
            ->whereNull('product_variant_id')
            ->count());

        $this->voidSale($tenant->id, $saleId)->assertOk();

        $this->assertInventoryBalance($tenant->id, $ctx['raw_material_id'], $ctx['raw_material_variant_id'], $ctx['fixture']['location_id'], 10.0);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $ctx['raw_material_id'],
            'product_variant_id' => $ctx['raw_material_variant_id'],
            'location_id' => $ctx['fixture']['location_id'],
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_out' => 2,
        ]);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $ctx['raw_material_id'],
            'product_variant_id' => $ctx['raw_material_variant_id'],
            'location_id' => $ctx['fixture']['location_id'],
            'reference_type' => 'void_recipe_input',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 2,
        ]);
    }

    public function test_void_dengan_variant_mengembalikan_ke_variant_yang_benar(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+void-variant-stock@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 7, 5000);

        $variantId = (string) Str::uuid();
        DB::table('product_variants')->insert([
            'id' => $variantId,
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'sku' => 'SKU-001-VAR',
            'barcode' => null,
            'name' => 'Varian Merah',
            'attributes' => json_encode(['warna' => 'merah']),
            'price_override' => null,
            'is_active' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('location_id', $fixture['location_id'])
            ->whereNull('product_variant_id')
            ->update([
                'product_variant_id' => $variantId,
                'qty_on_hand' => 7,
            ]);

        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['items'][0]['product_variant_id'] = $variantId;

        $saleId = $this->createSale($tenant->id, $payload);
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        $this->assertInventoryBalance($tenant->id, $fixture['product_id'], $variantId, $fixture['location_id'], 6.0);
        $this->assertSame(0, DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('location_id', $fixture['location_id'])
            ->whereNull('product_variant_id')
            ->count());

        $this->voidSale($tenant->id, $saleId)->assertOk();

        $this->assertInventoryBalance($tenant->id, $fixture['product_id'], $variantId, $fixture['location_id'], 7.0);
        $this->assertSame(0, DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('location_id', $fixture['location_id'])
            ->whereNull('product_variant_id')
            ->count());

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => $variantId,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_out' => 1,
        ]);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => $variantId,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'void',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 1,
        ]);
    }

    public function test_void_ditolak_jika_sale_sudah_memiliki_refund_completed(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+void-refund-conflict@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $saleId = $this->createSale($tenant->id, $fixture);
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/refunds', [
                'sale_id' => $saleId,
                'register_id' => $fixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'reason' => 'Refund dulu sebelum void',
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
            ])
            ->assertCreated();

        $this->voidSale($tenant->id, $saleId)
            ->assertStatus(422)
            ->assertJsonPath('code', 'VOID_FAILED')
            ->assertJsonPath('message', 'Transaksi yang sudah direfund tidak bisa di-void.');

        $this->assertDatabaseHas('sales', [
            'tenant_id' => $tenant->id,
            'id' => $saleId,
            'status' => 'completed',
        ]);
    }

    public function test_void_replay_with_same_idempotency_key_does_not_duplicate_reversal_rows(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+void-idempotent@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $saleId = $this->createSale($tenant->id, $fixture);
        $headers = array_merge($this->tenantHeaders($tenant->id), [
            'Idempotency-Key' => 'void-replay-001',
        ]);

        $first = $this->withHeaders($headers)->postJson('/api/v1/sales/'.$saleId.'/void', [
            'reason_code' => 'V01',
        ]);
        $first->assertOk()->assertJsonPath('status', 'voided');

        $second = $this->withHeaders($headers)->postJson('/api/v1/sales/'.$saleId.'/void', [
            'reason_code' => 'V01',
        ]);
        $second->assertOk()->assertJsonPath('status', 'voided');

        $this->assertSame(1, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'void')
            ->where('reference_id', $saleId)
            ->count());

        $this->assertSame(1, DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'void')
            ->where('reference_id', $saleId)
            ->count());
    }

    public function test_void_bom_memiliki_pasangan_ledger_sale_dan_recipe_input_yang_lengkap(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+void-ledger-pairs@example.test');
        $ctx = $this->prepareRecipeCheckoutContext($tenant->id, $user->id, rawMaterialStock: 10);
        $this->seedInventoryCostState($tenant->id, $ctx['fixture']['product_id'], $ctx['fixture']['location_id'], 100, 5000);

        $saleId = $this->createSale($tenant->id, $ctx['payload']);

        $this->voidSale($tenant->id, $saleId)->assertOk();

        $this->assertSame(1, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->count());

        $this->assertSame(1, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'void')
            ->where('reference_id', $saleId)
            ->count());

        $this->assertSame(1, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale_recipe_input')
            ->where('reference_id', $saleId)
            ->count());

        $this->assertSame(1, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'void_recipe_input')
            ->where('reference_id', $saleId)
            ->count());
    }

    public function test_void_sale_biasa_menutup_seluruh_allocation_stream_sale(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+void-sale-allocation@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $saleId = $this->createSale($tenant->id, $fixture);
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        $allocationBeforeVoid = DB::table('inventory_issue_allocations')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->where('product_id', $fixture['product_id'])
            ->first();

        $this->assertNotNull($allocationBeforeVoid);
        $this->assertSame(1.0, (float) $allocationBeforeVoid->qty);
        $this->assertSame(0.0, (float) $allocationBeforeVoid->reversed_qty);
        $this->assertNull($allocationBeforeVoid->reversed_at);

        $this->voidSale($tenant->id, $saleId)->assertOk();

        $allocationAfterVoid = DB::table('inventory_issue_allocations')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->where('product_id', $fixture['product_id'])
            ->first();

        $this->assertNotNull($allocationAfterVoid);
        $this->assertSame(1.0, (float) $allocationAfterVoid->qty);
        $this->assertSame(1.0, (float) $allocationAfterVoid->reversed_qty);
        $this->assertNotNull($allocationAfterVoid->reversed_at);

        $this->assertSame(0, DB::table('inventory_issue_allocations')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->whereRaw('qty > reversed_qty')
            ->count());

        $this->assertDatabaseHas('inventory_cost_states', [
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => 100,
        ]);
    }

    public function test_void_sale_bom_menutup_seluruh_allocation_stream_sale_dan_sale_recipe_input(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+void-bom-allocation@example.test');
        $ctx = $this->prepareRecipeCheckoutContext($tenant->id, $user->id, rawMaterialStock: 10);
        $this->seedInventoryCostState($tenant->id, $ctx['fixture']['product_id'], $ctx['fixture']['location_id'], 100, 5000);

        $saleId = $this->createSale($tenant->id, $ctx['payload']);
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        $saleAllocationBeforeVoid = DB::table('inventory_issue_allocations')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->where('product_id', $ctx['fixture']['product_id'])
            ->first();

        $recipeAllocationBeforeVoid = DB::table('inventory_issue_allocations')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale_recipe_input')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->where('product_id', $ctx['raw_material_id'])
            ->first();

        $this->assertNotNull($saleAllocationBeforeVoid);
        $this->assertNotNull($recipeAllocationBeforeVoid);
        $this->assertSame(1.0, (float) $saleAllocationBeforeVoid->qty);
        $this->assertSame(0.0, (float) $saleAllocationBeforeVoid->reversed_qty);
        $this->assertNull($saleAllocationBeforeVoid->reversed_at);
        $this->assertSame(2.0, (float) $recipeAllocationBeforeVoid->qty);
        $this->assertSame(0.0, (float) $recipeAllocationBeforeVoid->reversed_qty);
        $this->assertNull($recipeAllocationBeforeVoid->reversed_at);

        $this->voidSale($tenant->id, $saleId)->assertOk();

        $saleAllocationAfterVoid = DB::table('inventory_issue_allocations')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->where('product_id', $ctx['fixture']['product_id'])
            ->first();

        $recipeAllocationAfterVoid = DB::table('inventory_issue_allocations')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale_recipe_input')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->where('product_id', $ctx['raw_material_id'])
            ->first();

        $this->assertNotNull($saleAllocationAfterVoid);
        $this->assertNotNull($recipeAllocationAfterVoid);
        $this->assertSame(1.0, (float) $saleAllocationAfterVoid->qty);
        $this->assertSame(1.0, (float) $saleAllocationAfterVoid->reversed_qty);
        $this->assertNotNull($saleAllocationAfterVoid->reversed_at);
        $this->assertSame(2.0, (float) $recipeAllocationAfterVoid->qty);
        $this->assertSame(2.0, (float) $recipeAllocationAfterVoid->reversed_qty);
        $this->assertNotNull($recipeAllocationAfterVoid->reversed_at);

        $this->assertSame(0, DB::table('inventory_issue_allocations')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->whereRaw('qty > reversed_qty')
            ->count());

        $this->assertSame(0, DB::table('inventory_issue_allocations')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale_recipe_input')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->whereRaw('qty > reversed_qty')
            ->count());

        $this->assertDatabaseHas('inventory_cost_states', [
            'tenant_id' => $tenant->id,
            'product_id' => $ctx['fixture']['product_id'],
            'location_id' => $ctx['fixture']['location_id'],
            'qty_on_hand' => 100,
        ]);

        $this->assertDatabaseHas('inventory_cost_states', [
            'tenant_id' => $tenant->id,
            'product_id' => $ctx['raw_material_id'],
            'location_id' => $ctx['fixture']['location_id'],
            'qty_on_hand' => 10,
        ]);
    }

    private function createSale(string $tenantId, array $payloadOrFixture): string
    {
        $payload = array_key_exists('items', $payloadOrFixture)
            ? $payloadOrFixture
            : $this->checkoutPayload($payloadOrFixture, '2026-02-27');

        $payload['idempotency_key'] = (string) Str::uuid();

        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/checkout', $payload);

        $response->assertCreated();

        return (string) $response->json('sale_id');
    }

    private function voidSale(string $tenantId, string $saleId)
    {
        return $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/sales/'.$saleId.'/void', [
                'reason_code' => 'V01',
            ]);
    }

    private function seedInventoryCostState(
        string $tenantId,
        string $productId,
        string $locationId,
        float $qtyOnHand,
        float $averageCost
    ): void {
        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'location_id' => $locationId,
            'qty_on_hand' => $qtyOnHand,
            'average_cost' => $averageCost,
            'last_cost' => $averageCost,
            'updated_at' => now('UTC'),
        ]);
    }

    private function assertInventoryBalance(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $locationId,
        float $expectedQty
    ): void {
        $qty = (float) DB::table('inventory_balances')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->when($productVariantId, function ($query) use ($productVariantId) {
                $query->where('product_variant_id', $productVariantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            })
            ->value('qty_on_hand');

        $this->assertSame($expectedQty, $qty);
    }

    /**
     * @return array{fixture: array<string, mixed>, payload: array<string, mixed>, raw_material_id: string, raw_material_variant_id: ?string}
     */
    private function prepareRecipeCheckoutContext(string $tenantId, string $userId, float $rawMaterialStock, bool $rawMaterialHasVariants = false): array
    {
        $fixture = $this->seedCheckoutFixture($tenantId, $userId);

        $rawMaterialId = (string) Str::uuid();
        $rawMaterialVariantId = null;

        DB::table('products')->insert([
            'id' => $rawMaterialId,
            'tenant_id' => $tenantId,
            'sku' => 'RM-'.Str::upper(Str::random(6)),
            'name' => 'Bahan Baku Void',
            'price' => 1500,
            'hpp_average' => 1500,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'product_mode' => 'add_on',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
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

        if ($rawMaterialHasVariants) {
            $rawMaterialVariantId = (string) Str::uuid();

            DB::table('product_variants')->insert([
                'id' => $rawMaterialVariantId,
                'tenant_id' => $tenantId,
                'product_id' => $rawMaterialId,
                'sku' => 'RMV-'.Str::upper(Str::random(6)),
                'barcode' => null,
                'name' => 'Bahan Baku Void Variant',
                'attributes' => json_encode(['warna' => 'merah']),
                'price_override' => null,
                'is_active' => true,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
        }

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenantId,
            'product_id' => $rawMaterialId,
            'product_variant_id' => $rawMaterialVariantId,
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => $rawMaterialStock,
        ]);

        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_id' => $rawMaterialId,
            'product_variant_id' => $rawMaterialVariantId,
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => $rawMaterialStock,
            'average_cost' => 1500,
            'last_cost' => 1500,
            'updated_at' => now('UTC'),
        ]);

        DB::table('tenants')
            ->where('id', $tenantId)
            ->update([
                'auto_consume_on_sell_enabled' => true,
                'allow_negative_raw_material_stock' => false,
            ]);

        DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('id', $fixture['product_id'])
            ->update([
                'product_mode' => 'bom',
                'production_mode' => Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL,
                'item_type' => 'finished_good',
                'bom_items' => json_encode([
                    [
                        'product_id' => $rawMaterialId,
                        'product_variant_id' => $rawMaterialVariantId,
                        'name' => 'Bahan Baku Void',
                        'qty' => 2,
                        'unit' => 'gram',
                    ],
                ]),
                'updated_at' => now('UTC'),
            ]);

        return [
            'fixture' => $fixture,
            'payload' => $this->checkoutPayload($fixture, now('UTC')->toDateString()),
            'raw_material_id' => $rawMaterialId,
            'raw_material_variant_id' => $rawMaterialVariantId,
        ];
    }
}