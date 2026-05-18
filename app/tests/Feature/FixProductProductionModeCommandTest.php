<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class FixProductProductionModeCommandTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_dry_run_reports_legacy_bom_candidates_and_samples(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+product-production-mode-dry-run@example.test');
        $fixture = $this->seedBackfillFixture($tenant);

        $this->artisan('fix:product-production-mode', [
            '--tenant' => $tenant->id,
            '--dry-run' => true,
        ])->expectsOutputToContain('Scope tenant: '.$tenant->id)
            ->expectsOutputToContain('mode: dry-run')
            ->expectsOutputToContain('scanned_total: 3')
            ->expectsOutputToContain('candidate_total: 2')
            ->expectsOutputToContain('updated_total: 0')
            ->expectsOutputToContain('skipped_total: 1')
            ->expectsOutputToContain('sample:')
            ->expectsOutputToContain('Legacy Batch A')
            ->expectsOutputToContain('Legacy Batch Sell')
            ->expectsOutputToContain('Dry run only.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('products', [
            'id' => $fixture['legacy_auto_consume_id'],
            'tenant_id' => $tenant->id,
            'production_mode' => Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $fixture['legacy_null_id'],
            'tenant_id' => $tenant->id,
            'production_mode' => Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL,
        ]);
    }

    public function test_execute_backfills_legacy_bom_products_and_marks_bootstrap_eligible(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+product-production-mode-execute@example.test');
        $fixture = $this->seedBackfillFixture($tenant);

        $this->artisan('fix:product-production-mode', [
            '--tenant' => $tenant->id,
            '--execute' => true,
        ])->expectsOutputToContain('candidate_total: 2')
            ->expectsOutputToContain('skipped_total: 1')
            ->expectsOutputToContain('backup_file:')
            ->expectsOutputToContain('Backfill selesai.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('products', [
            'id' => $fixture['legacy_auto_consume_id'],
            'tenant_id' => $tenant->id,
            'product_mode' => 'bom',
            'production_mode' => Product::PRODUCTION_MODE_BATCH,
            'item_type' => 'finished_good',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $fixture['legacy_null_id'],
            'tenant_id' => $tenant->id,
            'product_mode' => 'bom',
            'production_mode' => Product::PRODUCTION_MODE_BATCH,
            'item_type' => 'finished_good',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $fixture['no_bom_id'],
            'tenant_id' => $tenant->id,
            'product_mode' => 'default',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'finished_good',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $fixture['raw_material_id'],
            'tenant_id' => $tenant->id,
            'product_mode' => 'add_on',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'raw_material',
        ]);

        $bootstrapResponse = $this->getJson('/api/v1/bootstrap?include=inventory', $this->tenantHeaders($tenant->id));

        $bootstrapResponse->assertOk();

        $products = collect($bootstrapResponse->json('products'));
        $legacyAutoConsume = $products->firstWhere('id', $fixture['legacy_auto_consume_id']);
        $legacyNull = $products->firstWhere('id', $fixture['legacy_null_id']);

        $this->assertTrue((bool) ($legacyAutoConsume['production_batch_eligible'] ?? false));
        $this->assertTrue((bool) ($legacyNull['production_batch_eligible'] ?? false));
        $this->assertSame('bom', $legacyAutoConsume['product_mode'] ?? null);
        $this->assertSame('bom', $legacyNull['product_mode'] ?? null);

        $previewResponse = $this->getJson(
            '/api/v1/production/recipe-preview?product_id='.$fixture['legacy_auto_consume_id'].'&qty=10&location_id='.$fixture['location_id'],
            $this->tenantHeaders($tenant->id)
        );

        $previewResponse->assertOk()
            ->assertJsonPath('data.can_post', true)
            ->assertJsonPath('data.ingredients.0.material_id', $fixture['raw_material_id']);
    }

    public function test_rerun_is_idempotent_after_backfill(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+product-production-mode-rerun@example.test');
        $fixture = $this->seedBackfillFixture($tenant);

        $this->artisan('fix:product-production-mode', [
            '--tenant' => $tenant->id,
            '--execute' => true,
        ])->assertExitCode(0);

        $this->artisan('fix:product-production-mode', [
            '--tenant' => $tenant->id,
            '--execute' => true,
        ])->expectsOutputToContain('scanned_total: 1')
            ->expectsOutputToContain('candidate_total: 0')
            ->expectsOutputToContain('updated_total: 0')
            ->expectsOutputToContain('skipped_total: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('products', [
            'id' => $fixture['legacy_auto_consume_id'],
            'tenant_id' => $tenant->id,
            'product_mode' => 'bom',
            'production_mode' => Product::PRODUCTION_MODE_BATCH,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function seedBackfillFixture(Tenant $tenant): array
    {
        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'M']);

        $branchId = (string) Str::uuid();
        $locationId = (string) Str::uuid();

        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenant->id,
            'name' => 'Branch Produksi',
        ]);

        DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'name' => 'Kitchen',
            'is_default' => true,
        ]);

        $rawMaterialId = $this->insertProduct($tenant, [
            'sku' => 'RM-BACKFILL-'.Str::upper(Str::random(6)),
            'name' => 'Gula Backfill',
            'product_mode' => 'add_on',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'raw_material',
            'track_stock' => true,
            'price' => 1,
        ]);

        $legacyAutoConsumeId = $this->insertProduct($tenant, [
            'sku' => 'FG-BACKFILL-AUTO-'.Str::upper(Str::random(6)),
            'name' => 'Legacy Batch A',
            'product_mode' => 'default',
            'production_mode' => Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL,
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 10000,
            'bom_items' => json_encode([
                [
                    'product_id' => $rawMaterialId,
                    'qty' => 2,
                    'unit' => 'kg',
                ],
            ]),
        ]);

        $legacyNullId = $this->insertProduct($tenant, [
            'sku' => 'FG-BACKFILL-NULL-'.Str::upper(Str::random(6)),
            'name' => 'Legacy Batch Sell',
            'product_mode' => 'default',
            'production_mode' => Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL,
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 12000,
            'bom_items' => json_encode([
                [
                    'product_id' => $rawMaterialId,
                    'qty' => 1,
                    'unit' => 'kg',
                ],
            ]),
        ]);

        $noBomId = $this->insertProduct($tenant, [
            'sku' => 'FG-NOBOM-'.Str::upper(Str::random(6)),
            'name' => 'Non BOM Product',
            'product_mode' => 'default',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 14000,
        ]);

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'location_id' => $locationId,
            'qty_on_hand' => 100,
        ]);

        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'location_id' => $locationId,
            'qty_on_hand' => 100,
            'average_cost' => 3000,
            'last_cost' => 3000,
            'updated_at' => now('UTC'),
        ]);

        return [
            'location_id' => $locationId,
            'raw_material_id' => $rawMaterialId,
            'legacy_auto_consume_id' => $legacyAutoConsumeId,
            'legacy_null_id' => $legacyNullId,
            'no_bom_id' => $noBomId,
        ];
    }

    private function insertProduct(Tenant $tenant, array $overrides): string
    {
        $id = (string) Str::uuid();
        $now = now('UTC');

        DB::table('products')->insert(array_merge([
            'id' => $id,
            'tenant_id' => $tenant->id,
            'category_id' => null,
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'name' => 'Produk Fixture',
            'price' => 10000,
            'hpp_average' => 0,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'product_mode' => 'default',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'finished_good',
            'variants' => json_encode([]),
            'bom_items' => json_encode([]),
            'bundle_items' => json_encode([]),
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], $overrides));

        return $id;
    }
}