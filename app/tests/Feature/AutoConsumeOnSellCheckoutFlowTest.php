<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Services\AutoConsumeOnSellService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class AutoConsumeOnSellCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;

    public function test_flag_off_keeps_checkout_behavior_without_auto_consume(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+auto-consume-flag-off@example.test');

        $ctx = $this->prepareRecipeCheckoutContext($tenant, $user->id, rawMaterialStock: 10, autoConsumeEnabled: false);

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertCreated();

        $this->assertDatabaseMissing('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $response->json('sale_id'),
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $ctx['fixture']['location_id'],
            'product_id' => $ctx['raw_material_id'],
            'qty_on_hand' => 10.00,
        ]);

        $this->assertDatabaseHas('valuation_audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'auto_consume.skipped',
        ]);
    }

    public function test_flag_on_with_valid_recipe_consumes_raw_material(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+auto-consume-valid@example.test');

        $ctx = $this->prepareRecipeCheckoutContext($tenant, $user->id, rawMaterialStock: 10, autoConsumeEnabled: true);

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertCreated();
        $saleId = (string) $response->json('sale_id');

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $saleId,
            'product_id' => $ctx['raw_material_id'],
            'qty_out' => 2.00,
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $ctx['fixture']['location_id'],
            'product_id' => $ctx['raw_material_id'],
            'qty_on_hand' => 8.00,
        ]);

        $this->assertDatabaseHas('valuation_audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'auto_consume.started',
        ]);
        $this->assertDatabaseHas('valuation_audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'auto_consume.succeeded',
        ]);
    }

    public function test_variant_raw_material_consumes_exact_variant_bucket(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+auto-consume-variant-raw-material@example.test');

        $ctx = $this->prepareRecipeCheckoutContext(
            $tenant,
            $user->id,
            rawMaterialStock: 10,
            autoConsumeEnabled: true,
            rawMaterialHasVariants: true,
            recipeIncludesRawMaterialVariant: true
        );

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertCreated();
        $saleId = (string) $response->json('sale_id');

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $saleId,
            'product_id' => $ctx['raw_material_id'],
            'product_variant_id' => $ctx['raw_material_variant_id'],
            'qty_out' => 2.00,
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $ctx['fixture']['location_id'],
            'product_id' => $ctx['raw_material_id'],
            'product_variant_id' => $ctx['raw_material_variant_id'],
            'qty_on_hand' => 8.00,
        ]);

        $this->assertSame(0, DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $ctx['raw_material_id'])
            ->where('location_id', $ctx['fixture']['location_id'])
            ->whereNull('product_variant_id')
            ->count());

        $this->assertDatabaseHas('inventory_cost_states', [
            'tenant_id' => $tenant->id,
            'product_id' => $ctx['raw_material_id'],
            'product_variant_id' => $ctx['raw_material_variant_id'],
            'location_id' => $ctx['fixture']['location_id'],
            'qty_on_hand' => 8,
        ]);
    }

    public function test_variant_raw_material_without_recipe_variant_fails_fast(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+auto-consume-variant-missing-context@example.test');

        $ctx = $this->prepareRecipeCheckoutContext(
            $tenant,
            $user->id,
            rawMaterialStock: 10,
            autoConsumeEnabled: true,
            rawMaterialHasVariants: true,
            recipeIncludesRawMaterialVariant: false
        );

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertStatus(422)
            ->assertJsonPath('code', 'AUTO_CONSUME_RECIPE_INVALID')
            ->assertJsonPath('details.recipe.0', 'Bahan baku bervarian wajib menyimpan product_variant_id pada recipe.');

        $this->assertDatabaseMissing('sales', [
            'tenant_id' => $tenant->id,
            'idempotency_key' => $ctx['payload']['idempotency_key'],
        ]);
    }

    public function test_retry_with_same_idempotency_key_does_not_double_consume(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+auto-consume-idempotency@example.test');

        $ctx = $this->prepareRecipeCheckoutContext($tenant, $user->id, rawMaterialStock: 10, autoConsumeEnabled: true);
        $ctx['payload']['idempotency_key'] = 'checkout-auto-consume-'.Str::lower(Str::random(12));

        $first = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));
        $second = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $first->assertCreated();
        $second->assertOk()->assertJsonPath('idempotent', true);

        $saleId = (string) $first->json('sale_id');

        $this->assertSame(1, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale_recipe_input')
            ->where('reference_id', $saleId)
            ->count());

        $this->assertSame(1, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale_recipe_input')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', DB::table('sale_items')->where('sale_id', $saleId)->value('id'))
            ->where('product_id', $ctx['raw_material_id'])
            ->count());

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $ctx['fixture']['location_id'],
            'product_id' => $ctx['raw_material_id'],
            'qty_on_hand' => 8.00,
        ]);
    }

    public function test_insufficient_raw_material_blocks_when_negative_not_allowed(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+auto-consume-block@example.test');

        $ctx = $this->prepareRecipeCheckoutContext(
            $tenant,
            $user->id,
            rawMaterialStock: 1,
            autoConsumeEnabled: true,
            allowNegativeRawMaterialStock: false
        );

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertStatus(422)
            ->assertJsonPath('code', 'AUTO_CONSUME_INSUFFICIENT_RAW_MATERIAL')
            ->assertJsonPath('message', 'Stok bahan baku tidak mencukupi untuk auto-consume.');

        $this->assertDatabaseMissing('sales', [
            'tenant_id' => $tenant->id,
            'idempotency_key' => $ctx['payload']['idempotency_key'],
        ]);
    }

    public function test_insufficient_raw_material_allowed_when_negative_policy_enabled(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+auto-consume-allow-negative@example.test');

        $ctx = $this->prepareRecipeCheckoutContext(
            $tenant,
            $user->id,
            rawMaterialStock: 1,
            autoConsumeEnabled: true,
            allowNegativeRawMaterialStock: true
        );

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertCreated();

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $ctx['fixture']['location_id'],
            'product_id' => $ctx['raw_material_id'],
            'qty_on_hand' => -1.00,
        ]);

        $saleId = (string) $response->json('sale_id');

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $saleId,
            'product_id' => $ctx['raw_material_id'],
            'qty_out' => 2.00,
        ]);

        $this->assertSame(1, DB::table('valuation_audit_logs')
            ->where('tenant_id', $tenant->id)
            ->where('action', 'auto_consume.succeeded')
            ->where('meta_json', 'like', '%"policy_negative_stock":true%')
            ->count());
    }

    public function test_product_without_active_recipe_is_no_op_for_auto_consume(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+auto-consume-no-recipe@example.test');

        $ctx = $this->prepareRecipeCheckoutContext(
            $tenant,
            $user->id,
            rawMaterialStock: 10,
            autoConsumeEnabled: true,
            withRecipe: false
        );

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertCreated();

        $this->assertDatabaseMissing('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $response->json('sale_id'),
        ]);
    }

    public function test_batch_production_product_skips_auto_consume_even_when_recipe_exists(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+batch-production-skip@example.test');

        $ctx = $this->prepareRecipeCheckoutContext(
            $tenant,
            $user->id,
            rawMaterialStock: 10,
            autoConsumeEnabled: true,
            withRecipe: true,
            productionMode: Product::PRODUCTION_MODE_BATCH
        );

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertCreated();

        $this->assertDatabaseMissing('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $response->json('sale_id'),
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $ctx['fixture']['location_id'],
            'product_id' => $ctx['raw_material_id'],
            'qty_on_hand' => 10.00,
        ]);
    }

    public function test_recipe_input_non_raw_material_fails_checkout_clearly(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+auto-consume-invalid-recipe-type@example.test');

        $ctx = $this->prepareRecipeCheckoutContext(
            $tenant,
            $user->id,
            rawMaterialStock: 10,
            autoConsumeEnabled: true,
            invalidRecipeInputType: true
        );

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertStatus(422)
            ->assertJsonPath('code', 'AUTO_CONSUME_RECIPE_INVALID')
            ->assertJsonPath('message', 'Recipe auto-consume tidak valid.');
    }

    public function test_two_finished_goods_in_single_checkout_consume_both_recipes(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+auto-consume-two-items@example.test');

        $ctx = $this->prepareRecipeCheckoutContext($tenant, $user->id, rawMaterialStock: 10, autoConsumeEnabled: true);

        $rawMaterialSecondId = $this->createProductWithStock(
            tenantId: $tenant->id,
            userId: $user->id,
            skuPrefix: 'RM2',
            name: 'Bahan Auto Consume B',
            productMode: 'add_on',
            itemType: 'raw_material',
            stockQty: 20,
            locationId: $ctx['fixture']['location_id'],
            price: 1700
        );

        $finishedGoodSecondId = $this->createProductWithStock(
            tenantId: $tenant->id,
            userId: $user->id,
            skuPrefix: 'FG2',
            name: 'Produk Jadi B',
            productMode: 'bom',
            itemType: 'finished_good',
            stockQty: 100,
            locationId: $ctx['fixture']['location_id'],
            price: 8000,
            bomItems: [
                ['product_id' => $rawMaterialSecondId, 'name' => 'Bahan Auto Consume B', 'qty' => 3, 'unit' => 'gram'],
            ]
        );

        $ctx['payload']['items'] = [
            [
                'product_id' => $ctx['fixture']['product_id'],
                'location_id' => $ctx['fixture']['location_id'],
                'qty' => 1,
                'price' => 10000,
                'discount' => 0,
            ],
            [
                'product_id' => $finishedGoodSecondId,
                'location_id' => $ctx['fixture']['location_id'],
                'qty' => 1,
                'price' => 8000,
                'discount' => 0,
            ],
        ];
        $ctx['payload']['subtotal'] = 18000;
        $ctx['payload']['total'] = 18000;
        $ctx['payload']['payments'][0]['amount'] = 18000;

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertCreated();
        $saleId = (string) $response->json('sale_id');

        $this->assertSame(2, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale_recipe_input')
            ->where('reference_id', $saleId)
            ->count());

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $saleId,
            'product_id' => $ctx['raw_material_id'],
            'qty_out' => 2.00,
        ]);
        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $saleId,
            'product_id' => $rawMaterialSecondId,
            'qty_out' => 3.00,
        ]);
    }

    public function test_mixed_auto_consume_and_regular_item_in_one_checkout(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+auto-consume-mixed-items@example.test');

        $ctx = $this->prepareRecipeCheckoutContext($tenant, $user->id, rawMaterialStock: 10, autoConsumeEnabled: true);

        $retailProductId = $this->createProductWithStock(
            tenantId: $tenant->id,
            userId: $user->id,
            skuPrefix: 'RT1',
            name: 'Produk Retail Biasa',
            productMode: 'default',
            itemType: 'default',
            stockQty: 40,
            locationId: $ctx['fixture']['location_id'],
            price: 7000
        );

        $ctx['payload']['items'][] = [
            'product_id' => $retailProductId,
            'location_id' => $ctx['fixture']['location_id'],
            'qty' => 1,
            'price' => 7000,
            'discount' => 0,
        ];
        $ctx['payload']['subtotal'] = 17000;
        $ctx['payload']['total'] = 17000;
        $ctx['payload']['payments'][0]['amount'] = 17000;

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertCreated();
        $saleId = (string) $response->json('sale_id');

        $this->assertSame(1, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale_recipe_input')
            ->where('reference_id', $saleId)
            ->count());
    }

    public function test_recipe_exists_but_output_not_finished_good_fails(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+auto-consume-output-type-invalid@example.test');

        $ctx = $this->prepareRecipeCheckoutContext(
            $tenant,
            $user->id,
            rawMaterialStock: 10,
            autoConsumeEnabled: true,
            soldProductItemType: 'raw_material'
        );

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_PRODUCT_TYPE');
    }

    public function test_raw_material_cannot_be_sold_via_checkout(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+raw-material-checkout-blocked@example.test');

        $ctx = $this->prepareRecipeCheckoutContext(
            $tenant,
            $user->id,
            rawMaterialStock: 10,
            autoConsumeEnabled: false,
            withRecipe: false,
            soldProductItemType: 'raw_material'
        );

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_PRODUCT_TYPE')
            ->assertJsonPath('message', 'Produk raw material tidak dapat dijual melalui checkout.');
    }

    public function test_partially_consumed_reference_retry_stays_non_duplicate(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+auto-consume-partial-retry@example.test');

        $ctx = $this->prepareRecipeCheckoutContext($tenant, $user->id, rawMaterialStock: 10, autoConsumeEnabled: true);
        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();
        $now = CarbonImmutable::now('UTC');

        DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $ctx['raw_material_id'])
            ->where('location_id', $ctx['fixture']['location_id'])
            ->whereNull('product_variant_id')
            ->update(['qty_on_hand' => 9]);

        DB::table('stock_ledger')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $ctx['raw_material_id'],
            'product_variant_id' => null,
            'location_id' => $ctx['fixture']['location_id'],
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 0,
            'qty_out' => 1,
            'balance_after' => 9,
            'unit_cost' => 1500,
            'total_cost' => 1500,
            'created_at' => $now,
            'created_by' => $user->id,
        ]);

        $soldProduct = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('id', $ctx['fixture']['product_id'])
            ->firstOrFail();

        app(AutoConsumeOnSellService::class)->consumeForSaleItem(
            tenantId: $tenant->id,
            saleId: $saleId,
            saleItemId: $saleItemId,
            soldProduct: $soldProduct,
            soldQty: 1,
            locationId: $ctx['fixture']['location_id'],
            userId: $user->id,
            at: $now
        );

        $this->assertSame(1, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale_recipe_input')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->where('product_id', $ctx['raw_material_id'])
            ->count());

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $ctx['fixture']['location_id'],
            'product_id' => $ctx['raw_material_id'],
            'qty_on_hand' => 9.00,
        ]);
    }

    /**
     * @return array{fixture: array<string, mixed>, payload: array<string, mixed>, raw_material_id: string, raw_material_variant_id: ?string}
     */
    private function prepareRecipeCheckoutContext(
        Tenant $tenant,
        string $userId,
        float $rawMaterialStock,
        bool $autoConsumeEnabled,
        bool $allowNegativeRawMaterialStock = false,
        bool $invalidRecipeInputType = false,
        bool $withRecipe = true,
        string $soldProductItemType = 'finished_good',
        bool $rawMaterialHasVariants = false,
        bool $recipeIncludesRawMaterialVariant = true,
        string $productionMode = Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL
    ): array {
        $fixture = $this->seedCheckoutFixture($tenant->id, $userId);

        $rawMaterialId = (string) Str::uuid();
        $rawMaterialVariantId = null;
        $rawMaterialMode = $invalidRecipeInputType ? 'default' : 'add_on';
        $rawMaterialType = $invalidRecipeInputType ? 'finished_good' : 'raw_material';

        DB::table('products')->insert([
            'id' => $rawMaterialId,
            'tenant_id' => $tenant->id,
            'sku' => 'RM-'.Str::upper(Str::random(6)),
            'name' => 'Bahan Auto Consume',
            'price' => 1500,
            'hpp_average' => 1500,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'product_mode' => $rawMaterialMode,
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => $rawMaterialType,
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
                'tenant_id' => $tenant->id,
                'product_id' => $rawMaterialId,
                'sku' => 'RMV-'.Str::upper(Str::random(6)),
                'barcode' => null,
                'name' => 'Bahan Auto Consume Variant',
                'attributes' => json_encode(['warna' => 'merah']),
                'price_override' => null,
                'is_active' => true,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
        }

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'product_variant_id' => $rawMaterialVariantId,
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => $rawMaterialStock,
        ]);

        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'product_variant_id' => $rawMaterialVariantId,
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => $rawMaterialStock,
            'average_cost' => 1500,
            'last_cost' => 1500,
            'updated_at' => now('UTC'),
        ]);

        DB::table('tenants')
            ->where('id', $tenant->id)
            ->update([
                'auto_consume_on_sell_enabled' => $autoConsumeEnabled,
                'allow_negative_raw_material_stock' => $allowNegativeRawMaterialStock,
            ]);

        DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('id', $fixture['product_id'])
            ->update([
                'product_mode' => $withRecipe ? 'bom' : 'default',
                'production_mode' => $productionMode,
                'item_type' => $soldProductItemType,
                'bom_items' => $withRecipe ? json_encode([
                    [
                        'product_id' => $rawMaterialId,
                        'product_variant_id' => $rawMaterialHasVariants && $recipeIncludesRawMaterialVariant
                            ? $rawMaterialVariantId
                            : null,
                        'name' => 'Bahan Auto Consume',
                        'qty' => 2,
                        'unit' => 'gram',
                    ],
                ]) : json_encode([]),
                'updated_at' => now('UTC'),
            ]);

        $payload = $this->checkoutPayload($fixture, now('UTC')->toDateString());

        return [
            'fixture' => $fixture,
            'payload' => $payload,
            'raw_material_id' => $rawMaterialId,
            'raw_material_variant_id' => $rawMaterialVariantId,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $bomItems
     */
    private function createProductWithStock(
        string $tenantId,
        string $userId,
        string $skuPrefix,
        string $name,
        string $productMode,
        string $itemType,
        float $stockQty,
        string $locationId,
        float $price,
        array $bomItems = []
    ): string {
        $productId = (string) Str::uuid();

        DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $tenantId,
            'sku' => $skuPrefix.'-'.Str::upper(Str::random(6)),
            'name' => $name,
            'price' => $price,
            'hpp_average' => $price,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'product_mode' => $productMode,
            'production_mode' => $productMode === 'bom'
                ? Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL
                : Product::PRODUCTION_MODE_NONE,
            'item_type' => $itemType,
            'variants' => json_encode([]),
            'bom_items' => json_encode($bomItems),
            'bundle_items' => json_encode([]),
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'product_variant_id' => null,
            'location_id' => $locationId,
            'qty_on_hand' => $stockQty,
        ]);

        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'location_id' => $locationId,
            'qty_on_hand' => $stockQty,
            'average_cost' => $price,
            'last_cost' => $price,
            'updated_at' => now('UTC'),
        ]);

        return $productId;
    }
}
