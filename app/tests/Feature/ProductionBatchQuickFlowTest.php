<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class ProductionBatchQuickFlowTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_create_and_post_quick_batch_moves_stock_and_writes_ledger(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-happy@example.test');

        $fixture = $this->seedFixture($tenant);

        $createResponse = $this->postJson('/api/v1/production-batches', [
            'location_id' => $fixture['location_id'],
            'reference_no' => 'PB-001',
            'inputs' => [
                [
                    'product_id' => $fixture['raw_material_id'],
                    'qty' => 4,
                ],
            ],
            'outputs' => [
                [
                    'product_id' => $fixture['finished_good_id'],
                    'qty' => 2,
                ],
            ],
        ], $this->tenantHeaders($tenant->id));

        $createResponse->assertStatus(201)
            ->assertJsonPath('status', 'draft');

        $batchId = (string) $createResponse->json('batch_id');

        $postResponse = $this->postJson('/api/v1/production-batches/'.$batchId.'/post', [], $this->tenantHeaders($tenant->id));

        $postResponse->assertOk()
            ->assertJson([
                'status' => 'posted',
            ]);

        $this->assertDatabaseHas('production_batches', [
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['raw_material_id'],
            'qty_on_hand' => 6.00,
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'qty_on_hand' => 2.00,
        ]);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'production_input',
            'reference_id' => $batchId,
            'product_id' => $fixture['raw_material_id'],
            'qty_out' => 4.00,
        ]);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'production_output',
            'reference_id' => $batchId,
            'product_id' => $fixture['finished_good_id'],
            'qty_in' => 2.00,
        ]);
    }

    public function test_create_batch_rejects_invalid_input_or_output_item_types(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-type-guard@example.test');

        $fixture = $this->seedFixture($tenant);

        $response = $this->postJson('/api/v1/production-batches', [
            'location_id' => $fixture['location_id'],
            'inputs' => [
                [
                    'product_id' => $fixture['finished_good_id'],
                    'qty' => 1,
                ],
            ],
            'outputs' => [
                [
                    'product_id' => $fixture['raw_material_id'],
                    'qty' => 1,
                ],
            ],
        ], $this->tenantHeaders($tenant->id));

        $response->assertStatus(422)
            ->assertJsonPath('code', 'PRODUCTION_BATCH_CREATE_FAILED');
    }

    public function test_post_batch_fails_when_raw_material_stock_is_insufficient(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-stock-guard@example.test');

        $fixture = $this->seedFixture($tenant, rawMaterialQty: 1);

        $createResponse = $this->postJson('/api/v1/production-batches', [
            'location_id' => $fixture['location_id'],
            'inputs' => [
                [
                    'product_id' => $fixture['raw_material_id'],
                    'qty' => 5,
                ],
            ],
            'outputs' => [
                [
                    'product_id' => $fixture['finished_good_id'],
                    'qty' => 2,
                ],
            ],
        ], $this->tenantHeaders($tenant->id));

        $batchId = (string) $createResponse->json('batch_id');

        $postResponse = $this->postJson('/api/v1/production-batches/'.$batchId.'/post', [], $this->tenantHeaders($tenant->id));

        $postResponse->assertStatus(422)
            ->assertJsonPath('code', 'PRODUCTION_BATCH_POST_FAILED');

        $this->assertDatabaseHas('production_batches', [
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'status' => 'draft',
        ]);
    }

    public function test_post_batch_cannot_be_repeated_once_posted(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-repost@example.test');

        $fixture = $this->seedFixture($tenant);

        $createResponse = $this->postJson('/api/v1/production-batches', [
            'location_id' => $fixture['location_id'],
            'inputs' => [
                [
                    'product_id' => $fixture['raw_material_id'],
                    'qty' => 2,
                ],
            ],
            'outputs' => [
                [
                    'product_id' => $fixture['finished_good_id'],
                    'qty' => 1,
                ],
            ],
        ], $this->tenantHeaders($tenant->id));

        $batchId = (string) $createResponse->json('batch_id');

        $this->postJson('/api/v1/production-batches/'.$batchId.'/post', [], $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJson([
                'status' => 'posted',
            ]);

        $this->postJson('/api/v1/production-batches/'.$batchId.'/post', [], $this->tenantHeaders($tenant->id))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('details.batch.0', 'Batch already posted.');
    }

    public function test_post_batch_fails_when_batch_has_no_items(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-empty-items@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = (string) Str::uuid();

        DB::table('production_batches')->insert([
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'reference_no' => 'PB-EMPTY-001',
            'status' => 'draft',
            'notes' => null,
            'created_by' => null,
            'posted_by' => null,
            'created_at' => now('UTC'),
            'posted_at' => null,
            'transaction_date' => now('UTC')->toDateString(),
        ]);

        $this->postJson('/api/v1/production-batches/'.$batchId.'/post', [], $this->tenantHeaders($tenant->id))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('details.batch.0', 'Batch must contain at least one input and one output item.');
    }

    private function seedFixture(Tenant $tenant, float $rawMaterialQty = 10): array
    {
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
            'sku' => 'RM-PROD-001',
            'name' => 'Gula',
            'product_mode' => 'add_on',
            'item_type' => 'raw_material',
            'track_stock' => true,
            'price' => 1,
        ]);

        $finishedGoodId = $this->insertProduct($tenant, [
            'sku' => 'FG-PROD-001',
            'name' => 'Sirup Jadi',
            'product_mode' => 'default',
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 10000,
        ]);

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'location_id' => $locationId,
            'qty_on_hand' => $rawMaterialQty,
        ]);

        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'location_id' => $locationId,
            'qty_on_hand' => $rawMaterialQty,
            'average_cost' => 3000,
            'last_cost' => 3000,
            'updated_at' => now('UTC'),
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