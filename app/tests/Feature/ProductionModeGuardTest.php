<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\Tenant;
use App\Services\AutoConsumeOnSellService;
use App\Services\ProductItemTypeValidator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class ProductionModeGuardTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;

    // ─── Scenario 1: API/UI Validation ──────────────────────────────

    public function test_validator_rejects_auto_consume_when_product_has_posted_production_batch(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+guard-reject@example.test');

        $productId = $this->createFinishedGoodWithPostedBatch($tenant, $user->id);

        $this->expectException(ValidationException::class);

        ProductItemTypeValidator::assertNotProducedForAutoConsume(
            $tenant->id,
            $productId,
            Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL
        );
    }

    public function test_validator_allows_auto_consume_when_product_has_no_production_batch(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+guard-allow@example.test');

        $productId = $this->createFinishedGoodWithoutBatch($tenant, $user->id);

        // Should not throw — no production batch exists.
        ProductItemTypeValidator::assertNotProducedForAutoConsume(
            $tenant->id,
            $productId,
            Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL
        );

        $this->assertTrue(true, 'Validation passed without exception.');
    }

    public function test_validator_allows_batch_mode_even_with_posted_batch(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+guard-batch-ok@example.test');

        $productId = $this->createFinishedGoodWithPostedBatch($tenant, $user->id);

        // Setting to batch_production should always be allowed regardless of history.
        ProductItemTypeValidator::assertNotProducedForAutoConsume(
            $tenant->id,
            $productId,
            Product::PRODUCTION_MODE_BATCH
        );

        $this->assertTrue(true, 'batch_production mode is always valid.');
    }

    public function test_validator_ignores_draft_and_cancelled_batches(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+guard-draft-only@example.test');

        $productId = $this->createFinishedGoodWithoutBatch($tenant, $user->id);

        // Insert a draft batch (not posted).
        $this->insertProductionBatch($tenant->id, $productId, $user->id, 'draft');
        // Insert a cancelled batch.
        $this->insertProductionBatch($tenant->id, $productId, $user->id, 'cancelled');

        // Should NOT throw — only posted batches trigger the guard.
        ProductItemTypeValidator::assertNotProducedForAutoConsume(
            $tenant->id,
            $productId,
            Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL
        );

        $this->assertTrue(true, 'Draft/cancelled batches do not block auto_consume_on_sell.');
    }

    public function test_update_product_api_rejects_auto_consume_when_produced(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+guard-api-reject@example.test');

        $productId = $this->createFinishedGoodWithPostedBatch($tenant, $user->id);

        $response = $this->putJson("/api/v1/products/{$productId}", [
            'name' => 'Updated Product',
            'sku' => 'UPD-'.Str::upper(Str::random(6)),
            'price' => 15000,
            'production_mode' => Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL,
            'product_mode' => 'bom',
            'item_type' => 'finished_good',
            'track_stock' => true,
            'bom_items' => [],
        ], $this->tenantHeaders($tenant->id));

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'pernah diproduksi',
            json_encode($response->json(), JSON_UNESCAPED_UNICODE)
        );
    }

    // ─── Scenario 2: POS Runtime Safety ─────────────────────────────

    public function test_auto_consume_skipped_at_pos_sale_when_product_has_posted_batch(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+guard-runtime-skip@example.test');

        $ctx = $this->prepareRecipeCheckoutContextForGuardTest(
            $tenant,
            $user->id,
            productionMode: Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL,
            insertPostedBatch: true
        );

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertCreated();
        $saleId = (string) $response->json('sale_id');

        // Raw material stock should NOT have decreased — auto-consume was skipped.
        $this->assertDatabaseMissing('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $saleId,
        ]);

        // Raw material balance must remain unchanged.
        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $ctx['fixture']['location_id'],
            'product_id' => $ctx['raw_material_id'],
            'qty_on_hand' => 10.00,
        ]);

        // An anomaly must have been raised.
        $this->assertDatabaseHas('anomalies', [
            'tenant_id' => $tenant->id,
            'type' => 'auto_consume_on_produced_product',
            'severity' => 'high',
        ]);
    }

    public function test_auto_consume_works_normally_without_posted_batch(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+guard-runtime-normal@example.test');

        $ctx = $this->prepareRecipeCheckoutContextForGuardTest(
            $tenant,
            $user->id,
            productionMode: Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL,
            insertPostedBatch: false
        );

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertCreated();
        $saleId = (string) $response->json('sale_id');

        // Raw material should have been consumed normally.
        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $saleId,
            'product_id' => $ctx['raw_material_id'],
            'qty_out' => 2.00,
        ]);

        // Raw material balance decreased by recipe qty.
        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $ctx['fixture']['location_id'],
            'product_id' => $ctx['raw_material_id'],
            'qty_on_hand' => 8.00,
        ]);

        // No anomaly raised.
        $this->assertDatabaseMissing('anomalies', [
            'tenant_id' => $tenant->id,
            'type' => 'auto_consume_on_produced_product',
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function createFinishedGoodWithPostedBatch(Tenant $tenant, string $userId): string
    {
        $productId = $this->createFinishedGoodWithoutBatch($tenant, $userId);
        $this->insertProductionBatch($tenant->id, $productId, $userId, ProductionBatch::STATUS_POSTED);

        return $productId;
    }

    private function createFinishedGoodWithoutBatch(Tenant $tenant, string $userId): string
    {
        $productId = (string) Str::uuid();

        DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $tenant->id,
            'sku' => 'FG-GUARD-'.Str::upper(Str::random(6)),
            'name' => 'Produk Jadi Guard Test',
            'price' => 10000,
            'hpp_average' => 5000,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'product_mode' => 'bom',
            'production_mode' => Product::PRODUCTION_MODE_BATCH,
            'item_type' => 'finished_good',
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

    private function insertProductionBatch(string $tenantId, string $productId, string $userId, string $status): string
    {
        $batchId = (string) Str::uuid();
        $now = CarbonImmutable::now('UTC');

        $locationId = DB::table('locations')->where('tenant_id', $tenantId)->value('id');
        if (! $locationId) {
            $locationId = (string) Str::uuid();
            DB::table('locations')->insert([
                'id' => $locationId,
                'tenant_id' => $tenantId,
                'branch_id' => DB::table('branches')->where('tenant_id', $tenantId)->value('id') ?? (string) Str::uuid(),
                'name' => 'Location Guard Test',
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('production_batches')->insert([
            'id' => $batchId,
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'location_id' => $locationId,
            'batch_code' => 'PB-GUARD-'.Str::upper(Str::random(6)),
            'status' => $status,
            'expected_output_qty' => 10,
            'actual_output_qty' => $status === ProductionBatch::STATUS_POSTED ? 10 : 0,
            'transaction_date' => $now->toDateString(),
            'posted_at' => $status === ProductionBatch::STATUS_POSTED ? $now : null,
            'cancelled_at' => $status === ProductionBatch::STATUS_CANCELLED ? $now : null,
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $batchId;
    }

    /**
     * Reuse the existing checkout fixture but with a posted production batch optionally injected.
     *
     * @return array{fixture: array, payload: array, raw_material_id: string}
     */
    private function prepareRecipeCheckoutContextForGuardTest(
        Tenant $tenant,
        string $userId,
        string $productionMode,
        bool $insertPostedBatch
    ): array {
        $fixture = $this->seedCheckoutFixture($tenant->id, $userId);

        $rawMaterialId = (string) Str::uuid();

        DB::table('products')->insert([
            'id' => $rawMaterialId,
            'tenant_id' => $tenant->id,
            'sku' => 'RM-GUARD-'.Str::upper(Str::random(6)),
            'name' => 'Bahan Baku Guard Test',
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

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => 10,
        ]);

        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => 10,
            'average_cost' => 1500,
            'last_cost' => 1500,
            'updated_at' => now('UTC'),
        ]);

        DB::table('tenants')
            ->where('id', $tenant->id)
            ->update([
                'auto_consume_on_sell_enabled' => true,
                'allow_negative_raw_material_stock' => false,
            ]);

        DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('id', $fixture['product_id'])
            ->update([
                'product_mode' => 'bom',
                'production_mode' => $productionMode,
                'item_type' => 'finished_good',
                'bom_items' => json_encode([
                    [
                        'product_id' => $rawMaterialId,
                        'product_variant_id' => null,
                        'name' => 'Bahan Baku Guard Test',
                        'qty' => 2,
                        'unit' => 'gram',
                    ],
                ]),
                'updated_at' => now('UTC'),
            ]);

        if ($insertPostedBatch) {
            $this->insertProductionBatch(
                $tenant->id,
                $fixture['product_id'],
                $userId,
                ProductionBatch::STATUS_POSTED
            );
        }

        $payload = $this->checkoutPayload($fixture, now('UTC')->toDateString());

        return [
            'fixture' => $fixture,
            'payload' => $payload,
            'raw_material_id' => $rawMaterialId,
        ];
    }
}
