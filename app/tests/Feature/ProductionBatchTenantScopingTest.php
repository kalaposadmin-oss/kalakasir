<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class ProductionBatchTenantScopingTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_store_rejects_foreign_tenant_branch_location_and_product_references(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+production-scope-primary@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+production-scope-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $fixture = $this->seedFixture($tenant);
        $foreignFixture = $this->seedFixture($foreignTenant);

        $this->postJson('/api/v1/production-batches', [
            'branch_id' => $fixture['branch_id'],
            'location_id' => $foreignFixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'transaction_date' => '2026-02-27',
            'expected_output_qty' => 10,
            'recipe_version' => 1,
        ], $this->tenantHeaders($tenant->id))
            ->assertStatus(422);

        $this->postJson('/api/v1/production-batches', [
            'branch_id' => $fixture['branch_id'],
            'location_id' => $fixture['location_id'],
            'product_id' => $foreignFixture['finished_good_id'],
            'transaction_date' => '2026-02-27',
            'expected_output_qty' => 10,
            'recipe_version' => 1,
        ], $this->tenantHeaders($tenant->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_show_does_not_join_foreign_tenant_product_metadata(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+production-show-primary@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+production-show-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $fixture = $this->seedFixture($tenant);
        $foreignFixture = $this->seedFixture($foreignTenant);
        $batchId = (string) Str::uuid();

        DB::table('production_batches')->insert([
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'product_variant_id' => null,
            'recipe_version' => 1,
            'reference_no' => 'PB-SCOPE-001',
            'status' => 'draft',
            'notes' => null,
            'created_by' => $user->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'transaction_date' => now('UTC')->toDateString(),
            'expected_output_qty' => 10,
            'actual_output_qty' => 0,
            'yield_variance_qty' => 0,
            'yield_variance_pct' => 0,
            'variance_reason' => null,
            'posted_at' => null,
            'posted_by' => null,
            'idempotency_key' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ]);

        DB::table('production_batch_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'production_batch_id' => $batchId,
            'line_type' => 'input',
            'product_id' => $foreignFixture['raw_material_id'],
            'product_variant_id' => null,
            'qty' => 2,
            'planned_qty' => 2,
            'actual_qty' => null,
            'uom' => 'kg',
            'unit_cost_snapshot' => 0,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $response = $this->getJson('/api/v1/production-batches/'.$batchId, $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('inputs.0.product_id', $foreignFixture['raw_material_id'])
            ->assertJsonPath('inputs.0.product_name', null)
            ->assertJsonPath('inputs.0.product_sku', null);
    }

    public function test_update_and_cancel_keep_foreign_tenant_batch_hidden(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+production-update-scope-primary@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+production-update-scope-foreign@example.test');

        $this->actingAs($user, 'sanctum');
        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'M']);

        $foreignFixture = $this->seedFixture($foreignTenant);
        $foreignBatchId = (string) Str::uuid();

        DB::table('production_batches')->insert([
            'id' => $foreignBatchId,
            'tenant_id' => $foreignTenant->id,
            'branch_id' => $foreignFixture['branch_id'],
            'location_id' => $foreignFixture['location_id'],
            'product_id' => $foreignFixture['finished_good_id'],
            'product_variant_id' => null,
            'recipe_version' => 1,
            'reference_no' => 'PB-SCOPE-EDIT-001',
            'status' => 'draft',
            'notes' => null,
            'created_by' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'transaction_date' => now('UTC')->toDateString(),
            'expected_output_qty' => 10,
            'actual_output_qty' => 0,
            'yield_variance_qty' => 0,
            'yield_variance_pct' => 0,
            'variance_reason' => null,
            'posted_at' => null,
            'posted_by' => null,
            'idempotency_key' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ]);

        DB::table('production_batch_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $foreignTenant->id,
            'production_batch_id' => $foreignBatchId,
            'line_type' => 'input',
            'product_id' => $foreignFixture['raw_material_id'],
            'product_variant_id' => null,
            'qty' => 20,
            'planned_qty' => 20,
            'actual_qty' => null,
            'uom' => 'kg',
            'unit_cost_snapshot' => 0,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('production_batch_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $foreignTenant->id,
            'production_batch_id' => $foreignBatchId,
            'line_type' => 'output',
            'product_id' => $foreignFixture['finished_good_id'],
            'product_variant_id' => null,
            'qty' => 10,
            'planned_qty' => 10,
            'actual_qty' => null,
            'uom' => null,
            'unit_cost_snapshot' => 0,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $this->patchJson('/api/v1/production-batches/'.$foreignBatchId, [
            'notes' => 'Tidak boleh terlihat tenant lain',
        ], $this->tenantHeaders($tenant->id))
            ->assertNotFound();

        $this->postJson('/api/v1/production-batches/'.$foreignBatchId.'/cancel', [], $this->tenantHeaders($tenant->id))
            ->assertNotFound();

        $this->assertDatabaseHas('production_batches', [
            'id' => $foreignBatchId,
            'tenant_id' => $foreignTenant->id,
            'status' => 'draft',
            'notes' => null,
        ]);
    }

    public function test_index_only_returns_batches_from_current_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+production-index-scope-primary@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+production-index-scope-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $fixture = $this->seedFixture($tenant);
        $foreignFixture = $this->seedFixture($foreignTenant);

        $ownBatchId = $this->insertBatchRecord($tenant, $fixture, [
            'reference_no' => 'PB-OWN-INDEX-001',
            'transaction_date' => '2026-02-27',
        ]);

        $this->insertBatchRecord($foreignTenant, $foreignFixture, [
            'reference_no' => 'PB-FOREIGN-INDEX-001',
            'transaction_date' => '2026-02-27',
        ]);

        $response = $this->getJson('/api/v1/production-batches?transaction_date_from=2026-02-27&transaction_date_to=2026-02-27', $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonCount(1, 'data.batches')
            ->assertJsonPath('data.batches.0.id', $ownBatchId)
            ->assertJsonPath('data.batches.0.reference_no', 'PB-OWN-INDEX-001');
    }

    private function seedFixture(Tenant $tenant): array
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
            'sku' => 'RM-PROD-'.Str::upper(Str::random(6)),
            'name' => 'Gula',
            'product_mode' => 'add_on',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'raw_material',
            'track_stock' => true,
            'price' => 1,
        ]);

        $finishedGoodId = $this->insertProduct($tenant, [
            'sku' => 'FG-PROD-'.Str::upper(Str::random(6)),
            'name' => 'Sirup Jadi',
            'product_mode' => 'default',
            'production_mode' => Product::PRODUCTION_MODE_BATCH,
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 10000,
            'recipe_output_qty' => 10,
            'bom_items' => json_encode([
                [
                    'product_id' => $rawMaterialId,
                    'qty' => 2,
                    'unit' => 'kg',
                ],
            ]),
        ]);

        return [
            'branch_id' => $branchId,
            'location_id' => $locationId,
            'raw_material_id' => $rawMaterialId,
            'finished_good_id' => $finishedGoodId,
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
            'recipe_output_qty' => null,
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

    private function insertBatchRecord(Tenant $tenant, array $fixture, array $overrides = []): string
    {
        $batchId = (string) Str::uuid();
        $now = now('UTC');

        $batch = array_merge([
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'product_variant_id' => null,
            'recipe_version' => 1,
            'reference_no' => 'PB-SCOPE-LIST-001',
            'status' => 'draft',
            'notes' => null,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'transaction_date' => '2026-02-27',
            'expected_output_qty' => 10,
            'actual_output_qty' => 0,
            'yield_variance_qty' => 0,
            'yield_variance_pct' => 0,
            'variance_reason' => null,
            'posted_at' => null,
            'posted_by' => null,
            'idempotency_key' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
        ], $overrides);

        DB::table('production_batches')->insert($batch);

        DB::table('production_batch_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'production_batch_id' => $batchId,
                'line_type' => 'input',
                'product_id' => $fixture['raw_material_id'],
                'product_variant_id' => null,
                'qty' => 20,
                'planned_qty' => 20,
                'actual_qty' => null,
                'uom' => 'kg',
                'unit_cost_snapshot' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'production_batch_id' => $batchId,
                'line_type' => 'output',
                'product_id' => $fixture['finished_good_id'],
                'product_variant_id' => null,
                'qty' => 10,
                'planned_qty' => 10,
                'actual_qty' => null,
                'uom' => null,
                'unit_cost_snapshot' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return $batchId;
    }
}
