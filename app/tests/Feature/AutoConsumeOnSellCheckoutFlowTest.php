<?php

namespace Tests\Feature;

use App\Models\Tenant;
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
            ->assertJsonPath('code', 'AUTO_CONSUME_INSUFFICIENT_RAW_MATERIAL');

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
            ->assertJsonPath('code', 'AUTO_CONSUME_RECIPE_INVALID');
    }

    /**
     * @return array{fixture: array<string, mixed>, payload: array<string, mixed>, raw_material_id: string}
     */
    private function prepareRecipeCheckoutContext(
        Tenant $tenant,
        string $userId,
        float $rawMaterialStock,
        bool $autoConsumeEnabled,
        bool $allowNegativeRawMaterialStock = false,
        bool $invalidRecipeInputType = false,
        bool $withRecipe = true
    ): array {
        $fixture = $this->seedCheckoutFixture($tenant->id, $userId);

        $rawMaterialId = (string) Str::uuid();
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

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => $rawMaterialStock,
        ]);

        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
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
                'item_type' => 'finished_good',
                'bom_items' => $withRecipe ? json_encode([
                    [
                        'product_id' => $rawMaterialId,
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
        ];
    }
}
