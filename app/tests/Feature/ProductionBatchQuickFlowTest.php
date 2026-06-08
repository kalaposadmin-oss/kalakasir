<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Services\TenantDefaultAccountingSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class ProductionBatchQuickFlowTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_create_batch_builds_draft_from_recipe_snapshot(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-draft@example.test');

        $fixture = $this->seedFixture($tenant);

        $response = $this->postJson('/api/v1/production-batches', [
            'branch_id' => $fixture['branch_id'],
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'transaction_date' => '2026-02-27',
            'expected_output_qty' => 10,
            'recipe_version' => 3,
            'reference_no' => 'PB-001',
        ], $this->tenantHeaders($tenant->id));

        $response->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('expected_output_qty', 10)
            ->assertJsonPath('recipe_version', 3);

        $batchId = (string) $response->json('batch_id');

        $this->assertDatabaseHas('production_batches', [
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'status' => 'draft',
            'expected_output_qty' => 10.0000,
            'recipe_version' => 3,
        ]);

        $this->assertDatabaseHas('production_batch_items', [
            'tenant_id' => $tenant->id,
            'production_batch_id' => $batchId,
            'line_type' => 'input',
            'product_id' => $fixture['raw_material_id'],
            'planned_qty' => 20.0000,
            'actual_qty' => null,
        ]);

        $this->assertDatabaseHas('production_batch_items', [
            'tenant_id' => $tenant->id,
            'production_batch_id' => $batchId,
            'line_type' => 'output',
            'product_id' => $fixture['finished_good_id'],
            'planned_qty' => 10.0000,
            'actual_qty' => null,
        ]);
    }

    public function test_create_batch_rejects_non_batch_production_product(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-type-guard@example.test');

        $fixture = $this->seedFixture($tenant, finishedGoodProductionMode: Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL);

        $response = $this->postJson('/api/v1/production-batches', [
            'branch_id' => $fixture['branch_id'],
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'transaction_date' => '2026-02-27',
            'expected_output_qty' => 10,
            'recipe_version' => 1,
        ], $this->tenantHeaders($tenant->id));

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('details.product_id.0', 'Produk harus menggunakan production_mode batch_production.');
    }

    public function test_create_batch_rejects_inactive_batch_production_product(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-inactive-product@example.test');

        $fixture = $this->seedFixture($tenant, finishedGoodActive: false);

        $response = $this->postJson('/api/v1/production-batches', [
            'branch_id' => $fixture['branch_id'],
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'transaction_date' => '2026-02-27',
            'expected_output_qty' => 10,
            'recipe_version' => 1,
        ], $this->tenantHeaders($tenant->id));

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('details.product_id.0', 'Produk batch production harus aktif.');
    }

    public function test_warehouse_can_create_production_batch_draft(): void
    {
        [$tenant] = $this->bootApiActor('warehouse', 'warehouse+production-draft@example.test');

        $fixture = $this->seedFixture($tenant);

        $response = $this->postJson('/api/v1/production-batches', [
            'branch_id' => $fixture['branch_id'],
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'transaction_date' => '2026-02-27',
            'recipe_count' => 1,
        ], $this->tenantHeaders($tenant->id));

        $response->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('recipe_count', 1);
    }

    public function test_update_draft_rescales_inputs_and_updates_metadata(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-update-draft@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $response = $this->patchJson('/api/v1/production-batches/'.$batchId, [
            'transaction_date' => '2026-02-26',
            'expected_output_qty' => 12,
            'recipe_version' => 4,
            'reference_no' => 'PB-EDIT-001',
            'notes' => 'Revisi operator',
        ], $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('transaction_date', '2026-02-26')
            ->assertJsonPath('expected_output_qty', 12)
            ->assertJsonPath('recipe_version', 4)
            ->assertJsonPath('reference_no', 'PB-EDIT-001')
            ->assertJsonPath('notes', 'Revisi operator')
            ->assertJsonPath('inputs.0.planned_qty', 24)
            ->assertJsonPath('output.planned_qty', 12);

        $this->assertDatabaseHas('production_batches', [
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'transaction_date' => '2026-02-26',
            'expected_output_qty' => 12.0000,
            'recipe_version' => 4,
            'reference_no' => 'PB-EDIT-001',
            'notes' => 'Revisi operator',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('production_batch_items', [
            'tenant_id' => $tenant->id,
            'production_batch_id' => $batchId,
            'line_type' => 'input',
            'product_id' => $fixture['raw_material_id'],
            'planned_qty' => 24.0000,
        ]);
    }

    public function test_update_draft_accepts_manual_input_override(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-update-override@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $response = $this->patchJson('/api/v1/production-batches/'.$batchId, [
            'expected_output_qty' => 9,
            'inputs' => [
                [
                    'product_id' => $fixture['raw_material_id'],
                    'planned_qty' => 18,
                ],
            ],
        ], $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('expected_output_qty', 9)
            ->assertJsonPath('inputs.0.planned_qty', 18)
            ->assertJsonPath('output.planned_qty', 9);
    }

    public function test_cancel_draft_marks_batch_cancelled(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-cancel-draft@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $response = $this->postJson('/api/v1/production-batches/'.$batchId.'/cancel', [
            'cancel_reason' => 'Salah input output planning untuk shift ini.',
        ], $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('batch_id', $batchId)
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('cancel_reason', 'Salah input output planning untuk shift ini.');

        $this->assertDatabaseHas('production_batches', [
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'status' => 'cancelled',
            'cancel_reason' => 'Salah input output planning untuk shift ini.',
        ]);
    }

    public function test_cancel_draft_requires_reason(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-cancel-reason-required@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $response = $this->postJson('/api/v1/production-batches/'.$batchId.'/cancel', [], $this->tenantHeaders($tenant->id));

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('details.cancel_reason.0', 'Cancel reason wajib diisi saat membatalkan draft batch.');

        $this->assertDatabaseHas('production_batches', [
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'status' => 'draft',
            'cancel_reason' => null,
        ]);
    }

    public function test_update_and_cancel_reject_non_draft_batch(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-non-draft-guard@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $this->postJson(
            '/api/v1/production-batches/'.$batchId.'/post',
            [
                'actual_output_qty' => 10,
                'inputs' => [
                    [
                        'product_id' => $fixture['raw_material_id'],
                        'actual_qty' => 20,
                    ],
                ],
            ],
            $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-non-draft-guard')
        )->assertOk();

        $this->patchJson('/api/v1/production-batches/'.$batchId, [
            'notes' => 'Tidak boleh berubah',
        ], $this->tenantHeaders($tenant->id))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('details.batch.0', 'Batch draft sudah tidak bisa diubah atau dibatalkan.');

        $this->postJson('/api/v1/production-batches/'.$batchId.'/cancel', [
            'cancel_reason' => 'Sudah dipost sehingga tidak bisa dibatalkan lagi.',
        ], $this->tenantHeaders($tenant->id))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('details.batch.0', 'Batch draft sudah tidak bisa diubah atau dibatalkan.');
    }

    public function test_post_batch_rejects_when_batch_product_becomes_inactive(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-post-inactive-product@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('id', $fixture['finished_good_id'])
            ->update([
                'is_active' => false,
                'updated_at' => now('UTC'),
            ]);

        $response = $this->postJson(
            '/api/v1/production-batches/'.$batchId.'/post',
            [
                'actual_output_qty' => 10,
                'inputs' => [
                    [
                        'product_id' => $fixture['raw_material_id'],
                        'actual_qty' => 20,
                    ],
                ],
            ],
            $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-inactive-product')
        );

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('details.product_id.0', 'Produk batch production harus aktif.');
    }

    public function test_post_batch_moves_stock_and_writes_single_reference_type_ledger(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-post@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $response = $this->postJson(
            '/api/v1/production-batches/'.$batchId.'/post',
            [
                'actual_output_qty' => 11,
                'variance_reason' => 'Yield oven lebih bagus',
                'inputs' => [
                    [
                        'product_id' => $fixture['raw_material_id'],
                        'actual_qty' => 20,
                    ],
                ],
            ],
            $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-001')
        );

        $response->assertOk()
            ->assertJsonPath('status', 'posted')
            ->assertJsonPath('actual_output_qty', 11)
            ->assertJsonPath('yield_variance_qty', 1)
            ->assertJsonPath('yield_variance_pct', 10)
            ->assertJsonPath('idempotent', false);

        $this->assertDatabaseHas('production_batches', [
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'status' => 'posted',
            'actual_output_qty' => 11.0000,
            'yield_variance_qty' => 1.0000,
            'yield_variance_pct' => 10.0000,
            'idempotency_key' => 'prod-post-001',
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['raw_material_id'],
            'qty_on_hand' => 80.00,
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'qty_on_hand' => 11.00,
        ]);

        $this->assertSame(2, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'production_batch')
            ->where('reference_id', $batchId)
            ->count());

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'production_batch',
            'reference_id' => $batchId,
            'product_id' => $fixture['raw_material_id'],
            'qty_out' => 20.00,
        ]);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'production_batch',
            'reference_id' => $batchId,
            'product_id' => $fixture['finished_good_id'],
            'qty_in' => 11.00,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $fixture['finished_good_id'],
            'tenant_id' => $tenant->id,
            'hpp_average' => 5454.55,
        ]);

        $productsResponse = $this->getJson('/api/v1/products', $this->tenantHeaders($tenant->id));
        $productsResponse->assertOk();

        $finishedGood = collect($productsResponse->json('products'))->firstWhere('id', $fixture['finished_good_id']);

        $this->assertNotNull($finishedGood);
        $this->assertSame(5454.55, round((float) ($finishedGood['hpp_average'] ?? 0), 2));
    }

    public function test_post_batch_blocks_extreme_yield_variance_as_likely_input_error(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-extreme-variance@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $this->postJson(
            '/api/v1/production-batches/'.$batchId.'/post',
            [
                'actual_output_qty' => 100,
                'variance_reason' => 'Output terlalu jauh dari rencana',
                'inputs' => [
                    [
                        'product_id' => $fixture['raw_material_id'],
                        'actual_qty' => 20,
                    ],
                ],
            ],
            $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-extreme-variance')
        )
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('details.actual_output_qty.0', 'Selisih hasil terlalu besar dan kemungkinan salah input. Koreksi draft atau batalkan batch dulu.');
    }

    public function test_post_batch_rejects_input_mistake_reason_before_stock_moves(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-input-mistake-reason@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $this->postJson(
            '/api/v1/production-batches/'.$batchId.'/post',
            [
                'actual_output_qty' => 11,
                'variance_reason' => 'Salah input - koreksi draft',
                'inputs' => [
                    [
                        'product_id' => $fixture['raw_material_id'],
                        'actual_qty' => 20,
                    ],
                ],
            ],
            $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-input-mistake-reason')
        )
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('details.variance_reason.0', 'Alasan menunjukkan salah input. Jangan posting batch; edit draft atau batalkan draft dulu agar stok dan HPP tidak bergerak dari angka salah.');

        $this->assertDatabaseHas('production_batches', [
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'status' => 'draft',
            'actual_output_qty' => 0.0000,
        ]);
    }

    public function test_admin_can_correct_posted_batch_transaction_date_with_audit(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-correct-date@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $this->postJson('/api/v1/production-batches/'.$batchId.'/post', [
            'actual_output_qty' => 10,
            'inputs' => [
                [
                    'product_id' => $fixture['raw_material_id'],
                    'actual_qty' => 20,
                ],
            ],
        ], $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-correct-date'))
            ->assertOk();

        $this->postJson('/api/v1/production-batches/'.$batchId.'/correct-date', [
            'transaction_date' => '2026-02-26',
            'reason' => 'Tanggal produksi salah input.',
        ], $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJsonPath('changed', true)
            ->assertJsonPath('transaction_date', '2026-02-26');

        $this->assertDatabaseHas('production_batches', [
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'status' => 'posted',
            'transaction_date' => '2026-02-26',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'event_type' => 'production_batch.transaction_date_corrected',
            'reference_type' => 'production_batch',
            'reference_id' => $batchId,
        ]);
    }

    public function test_admin_can_void_posted_batch_and_reverse_inventory(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-void-posting@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $this->postJson('/api/v1/production-batches/'.$batchId.'/post', [
            'actual_output_qty' => 10,
            'inputs' => [
                [
                    'product_id' => $fixture['raw_material_id'],
                    'actual_qty' => 20,
                ],
            ],
        ], $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-void-posting'))
            ->assertOk();

        $this->postJson('/api/v1/production-batches/'.$batchId.'/void-posting', [
            'reason' => 'Jumlah resep salah input.',
        ], $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJsonPath('voided', true)
            ->assertJsonPath('status', 'cancelled');

        $this->assertDatabaseHas('production_batches', [
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'status' => 'cancelled',
            'cancel_reason' => 'Void posting: Jumlah resep salah input.',
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['raw_material_id'],
            'qty_on_hand' => 100.00,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'qty_on_hand' => 0.00,
        ]);
        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'production_batch_void',
            'reference_id' => $batchId,
            'product_id' => $fixture['raw_material_id'],
            'qty_in' => 20.00,
        ]);
        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'production_batch_void',
            'reference_id' => $batchId,
            'product_id' => $fixture['finished_good_id'],
            'qty_out' => 10.00,
        ]);
    }

    public function test_posted_batch_can_record_waste_as_stock_adjustment(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+production-waste@example.test');
        app(TenantDefaultAccountingSetupService::class)->ensure((string) $tenant->id, (string) $user->id);

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $this->postJson('/api/v1/production-batches/'.$batchId.'/post', [
            'actual_output_qty' => 10,
            'inputs' => [
                [
                    'product_id' => $fixture['raw_material_id'],
                    'actual_qty' => 20,
                ],
            ],
        ], $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-waste'))
            ->assertOk();

        $response = $this->postJson('/api/v1/production-batches/'.$batchId.'/waste', [
            'waste_type' => 'unsold',
            'notes' => 'Produk tidak laku dan dibuang setelah closing.',
            'items' => [
                [
                    'product_id' => $fixture['finished_good_id'],
                    'waste_qty' => 2,
                ],
            ],
        ], $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('status', 'posted')
            ->assertJsonPath('waste_type', 'unsold')
            ->assertJsonPath('items.0.waste_qty', 2);

        $adjustmentId = (string) $response->json('adjustment_id');

        $this->assertDatabaseHas('stock_adjustments', [
            'id' => $adjustmentId,
            'tenant_id' => $tenant->id,
            'status' => 'posted',
            'reason_code' => 'A02',
            'waste_type' => 'unsold',
            'production_batch_id' => $batchId,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'qty_on_hand' => 8.00,
        ]);
        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'stock_adjustment',
            'reference_id' => $adjustmentId,
            'product_id' => $fixture['finished_good_id'],
            'qty_out' => 2.00,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'event_type' => 'production_batch.waste_posted',
            'reference_type' => 'production_batch',
            'reference_id' => $batchId,
        ]);
    }

    public function test_multi_output_batch_allocates_stock_and_cost_by_recipe_factor(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-multi-output@example.test');

        $fixture = $this->seedFixture($tenant, rawMaterialQty: 100);
        $boxLargeId = $this->insertProduct($tenant, [
            'sku' => 'BR-10X30-'.Str::upper(Str::random(6)),
            'name' => 'Brownies Box 10x30 isi 27',
            'product_mode' => 'default',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 75000,
        ]);
        $boxSmallId = $this->insertProduct($tenant, [
            'sku' => 'BR-10X10-'.Str::upper(Str::random(6)),
            'name' => 'Brownies Box 10x10 isi 9',
            'product_mode' => 'default',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 30000,
        ]);

        $create = $this->postJson('/api/v1/production-batches', [
            'branch_id' => $fixture['branch_id'],
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'transaction_date' => '2026-02-27',
            'recipe_count' => 3,
            'outputs' => [
                [
                    'product_id' => $boxLargeId,
                    'planned_qty' => 1,
                    'recipe_factor' => 1.5,
                ],
                [
                    'product_id' => $boxSmallId,
                    'planned_qty' => 3,
                    'recipe_factor' => 0.5,
                ],
            ],
        ], $this->tenantHeaders($tenant->id));

        $create->assertCreated()
            ->assertJsonPath('expected_output_qty', 4)
            ->assertJsonPath('recipe_count', 3);

        $batchId = (string) $create->json('batch_id');

        $this->postJson('/api/v1/production-batches/'.$batchId.'/post', [
            'inputs' => [
                [
                    'product_id' => $fixture['raw_material_id'],
                    'actual_qty' => 6,
                ],
            ],
            'outputs' => [
                [
                    'product_id' => $boxLargeId,
                    'actual_qty' => 1,
                ],
                [
                    'product_id' => $boxSmallId,
                    'actual_qty' => 3,
                ],
            ],
        ], $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-multi-output'))
            ->assertOk()
            ->assertJsonPath('status', 'posted')
            ->assertJsonPath('actual_output_qty', 4)
            ->assertJsonPath('yield_variance_qty', 0);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['raw_material_id'],
            'qty_on_hand' => 94.00,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $boxLargeId,
            'qty_on_hand' => 1.00,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $boxSmallId,
            'qty_on_hand' => 3.00,
        ]);

        $this->assertSame(3, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'production_batch')
            ->where('reference_id', $batchId)
            ->count());
        $this->assertDatabaseHas('products', [
            'id' => $boxLargeId,
            'tenant_id' => $tenant->id,
            'hpp_average' => 9000.00,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $boxSmallId,
            'tenant_id' => $tenant->id,
            'hpp_average' => 3000.00,
        ]);
    }

    public function test_multi_output_batch_includes_additional_output_recipe_materials(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-output-extra-bom@example.test');

        $fixture = $this->seedFixture($tenant, rawMaterialQty: 100);
        $nutellaId = $this->insertProduct($tenant, [
            'sku' => 'NUTELLA-'.Str::upper(Str::random(6)),
            'name' => 'Nutella Selai',
            'product_mode' => 'add_on',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'raw_material',
            'track_stock' => true,
            'price' => 1,
            'unit' => 'gram',
        ]);
        $nutellaBrowniesId = $this->insertProduct($tenant, [
            'sku' => 'NUT-BR-'.Str::upper(Str::random(6)),
            'name' => 'Nutella Brownies',
            'product_mode' => 'bom',
            'production_mode' => Product::PRODUCTION_MODE_BATCH,
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 35000,
            'recipe_output_qty' => 6,
            'bom_items' => json_encode([
                [
                    'product_id' => $nutellaId,
                    'qty' => 10,
                    'unit' => 'gram',
                ],
            ]),
        ]);

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenant->id,
            'product_id' => $nutellaId,
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => 100,
        ]);
        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $nutellaId,
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => 100,
            'average_cost' => 200,
            'last_cost' => 200,
            'updated_at' => now('UTC'),
        ]);

        $create = $this->postJson('/api/v1/production-batches', [
            'branch_id' => $fixture['branch_id'],
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'transaction_date' => '2026-02-27',
            'recipe_count' => 1,
            'outputs' => [
                [
                    'product_id' => $nutellaBrowniesId,
                    'planned_qty' => 6,
                    'recipe_factor' => 0.16666667,
                ],
            ],
        ], $this->tenantHeaders($tenant->id));

        $create->assertCreated()
            ->assertJsonPath('expected_output_qty', 6)
            ->assertJsonPath('recipe_count', 1);

        $batchId = (string) $create->json('batch_id');

        $this->assertDatabaseHas('production_batch_items', [
            'tenant_id' => $tenant->id,
            'production_batch_id' => $batchId,
            'line_type' => 'input',
            'product_id' => $fixture['raw_material_id'],
            'planned_qty' => 2.0000,
        ]);
        $this->assertDatabaseHas('production_batch_items', [
            'tenant_id' => $tenant->id,
            'production_batch_id' => $batchId,
            'line_type' => 'input',
            'product_id' => $nutellaId,
            'planned_qty' => 10.0000,
        ]);

        $this->postJson('/api/v1/production-batches/'.$batchId.'/post', [
            'inputs' => [
                [
                    'product_id' => $fixture['raw_material_id'],
                    'actual_qty' => 2,
                ],
                [
                    'product_id' => $nutellaId,
                    'actual_qty' => 10,
                ],
            ],
            'outputs' => [
                [
                    'product_id' => $nutellaBrowniesId,
                    'actual_qty' => 6,
                ],
            ],
        ], $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-nutella-output'))
            ->assertOk()
            ->assertJsonPath('status', 'posted')
            ->assertJsonPath('actual_output_qty', 6);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['raw_material_id'],
            'qty_on_hand' => 98.00,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $nutellaId,
            'qty_on_hand' => 90.00,
        ]);
        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $nutellaBrowniesId,
            'qty_on_hand' => 6.00,
        ]);
    }

    public function test_multi_output_batch_accepts_fractional_brownies_recipe_factor_without_rounding_overage(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-brownies-fraction@example.test');

        $fixture = $this->seedFixture($tenant, rawMaterialQty: 100);
        $boxLargeId = $this->insertProduct($tenant, [
            'sku' => 'BR-10X30-'.Str::upper(Str::random(6)),
            'name' => 'Brownies Box 10x30 isi 27 Pcs',
            'product_mode' => 'default',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 75000,
        ]);
        $boxSmallId = $this->insertProduct($tenant, [
            'sku' => 'BR-10X10-'.Str::upper(Str::random(6)),
            'name' => 'Brownies Box 10x10 isi 9 Pcs',
            'product_mode' => 'default',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 30000,
        ]);
        $boxMediumId = $this->insertProduct($tenant, [
            'sku' => 'BR-10X20-'.Str::upper(Str::random(6)),
            'name' => 'Brownies Box 10x20 isi 18 Pcs',
            'product_mode' => 'default',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 55000,
        ]);

        $response = $this->postJson('/api/v1/production-batches', [
            'branch_id' => $fixture['branch_id'],
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'transaction_date' => '2026-02-27',
            'recipe_count' => 3,
            'outputs' => [
                [
                    'product_id' => $boxLargeId,
                    'planned_qty' => 27,
                    'recipe_factor' => 0.05555556,
                ],
                [
                    'product_id' => $boxSmallId,
                    'planned_qty' => 9,
                    'recipe_factor' => 0.05555556,
                ],
                [
                    'product_id' => $boxMediumId,
                    'planned_qty' => 18,
                    'recipe_factor' => 0.05555556,
                ],
            ],
        ], $this->tenantHeaders($tenant->id));

        $response->assertCreated()
            ->assertJsonPath('recipe_count', 3)
            ->assertJsonPath('expected_output_qty', 54);
    }

    public function test_post_batch_is_idempotent_for_same_key(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-idempotent@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $payload = [
            'actual_output_qty' => 10,
            'inputs' => [
                [
                    'product_id' => $fixture['raw_material_id'],
                    'actual_qty' => 20,
                ],
            ],
        ];

        $headers = $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-same-key');

        $first = $this->postJson('/api/v1/production-batches/'.$batchId.'/post', $payload, $headers);
        $second = $this->postJson('/api/v1/production-batches/'.$batchId.'/post', $payload, $headers);

        $first->assertOk()->assertJsonPath('idempotent', false);
        $second->assertOk()->assertJsonPath('idempotent', true);

        $this->assertSame(2, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'production_batch')
            ->where('reference_id', $batchId)
            ->count());
    }

    public function test_post_batch_fails_when_raw_material_stock_is_insufficient(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-stock-guard@example.test');

        $fixture = $this->seedFixture($tenant, rawMaterialQty: 5);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $response = $this->postJson(
            '/api/v1/production-batches/'.$batchId.'/post',
            [
                'actual_output_qty' => 10,
                'inputs' => [
                    [
                        'product_id' => $fixture['raw_material_id'],
                        'actual_qty' => 20,
                    ],
                ],
            ],
            $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-insufficient')
        );

        $response->assertStatus(422)
            ->assertJsonPath('code', 'PRODUCTION_BATCH_POST_FAILED');

        $this->assertDatabaseHas('production_batches', [
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'status' => 'draft',
        ]);
    }

    public function test_index_filters_batches_by_supported_dimensions(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+production-index-filters@example.test');

        $this->actingAs($user, 'sanctum');

        $fixtureA = $this->seedFixture($tenant);
        $fixtureB = $this->seedFixture($tenant);

        $matchingBatchId = $this->insertBatchRecord($tenant, $fixtureA, [
            'status' => 'draft',
            'transaction_date' => '2026-02-25',
            'reference_no' => 'PB-FILTER-MATCH',
            'expected_output_qty' => 10,
        ]);

        $this->insertBatchRecord($tenant, $fixtureA, [
            'status' => 'cancelled',
            'transaction_date' => '2026-02-25',
            'reference_no' => 'PB-FILTER-STATUS',
            'expected_output_qty' => 10,
        ]);

        $this->insertBatchRecord($tenant, $fixtureB, [
            'status' => 'draft',
            'transaction_date' => '2026-02-26',
            'reference_no' => 'PB-FILTER-BRANCH',
            'expected_output_qty' => 8,
        ]);

        $response = $this->getJson('/api/v1/production-batches?product_id='.$fixtureA['finished_good_id'].'&status=draft&branch_id='.$fixtureA['branch_id'].'&location_id='.$fixtureA['location_id'].'&transaction_date_from=2026-02-24&transaction_date_to=2026-02-25&limit=20', $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.limit', 20)
            ->assertJsonPath('meta.filters.product_id', $fixtureA['finished_good_id'])
            ->assertJsonPath('meta.filters.status', 'draft')
            ->assertJsonPath('meta.filters.branch_id', $fixtureA['branch_id'])
            ->assertJsonPath('meta.filters.location_id', $fixtureA['location_id'])
            ->assertJsonPath('meta.filters.transaction_date_from', '2026-02-24')
            ->assertJsonPath('meta.filters.transaction_date_to', '2026-02-25')
            ->assertJsonCount(1, 'data.batches')
            ->assertJsonPath('data.batches.0.id', $matchingBatchId)
            ->assertJsonPath('data.batches.0.reference_no', 'PB-FILTER-MATCH')
            ->assertJsonPath('data.batches.0.status', 'draft')
            ->assertJsonPath('data.batches.0.transaction_date', '2026-02-25')
            ->assertJsonPath('data.batches.0.input_line_count', 1)
            ->assertJsonPath('data.batches.0.output_line_count', 1);
    }

    public function test_create_update_and_cancel_write_audit_logs(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+production-audit-draft@example.test');

        $this->actingAs($user, 'sanctum');

        $fixture = $this->seedFixture($tenant);

        $createResponse = $this->postJson('/api/v1/production-batches', [
            'branch_id' => $fixture['branch_id'],
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'transaction_date' => '2026-02-27',
            'expected_output_qty' => 10,
            'recipe_version' => 3,
            'reference_no' => 'PB-AUDIT-001',
        ], $this->tenantHeaders($tenant->id));

        $batchId = (string) $createResponse->json('batch_id');

        $this->patchJson('/api/v1/production-batches/'.$batchId, [
            'transaction_date' => '2026-02-26',
            'expected_output_qty' => 12,
            'recipe_version' => 4,
            'notes' => 'Revisi audit',
        ], $this->tenantHeaders($tenant->id))->assertOk();

        $this->postJson('/api/v1/production-batches/'.$batchId.'/cancel', [
            'cancel_reason' => 'Revisi audit',
        ], $this->tenantHeaders($tenant->id))
            ->assertOk();

        $logs = DB::table('audit_logs')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'production_batch')
            ->where('reference_id', $batchId)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(3, $logs);
        $this->assertSame('production_batch.created', $logs[0]->event_type);
        $this->assertSame('production_batch.updated', $logs[1]->event_type);
        $this->assertSame('production_batch.cancelled', $logs[2]->event_type);
        $this->assertSame($user->id, $logs[0]->actor_id);

        $createdPayload = json_decode((string) $logs[0]->payload_json, true);
        $updatedPayload = json_decode((string) $logs[1]->payload_json, true);
        $cancelledPayload = json_decode((string) $logs[2]->payload_json, true);

        $this->assertSame('draft', $createdPayload['status'] ?? null);
        $this->assertSame('2026-02-27', $createdPayload['transaction_date'] ?? null);
        $this->assertSame(10.0, (float) ($createdPayload['expected_output_qty'] ?? 0));
        $this->assertSame(1, (int) ($createdPayload['input_count'] ?? 0));
        $this->assertNull($createdPayload['cancel_reason'] ?? null);
        $this->assertNull($createdPayload['variance_reason'] ?? null);

        $this->assertSame('2026-02-27', $updatedPayload['before']['transaction_date'] ?? null);
        $this->assertSame('2026-02-26', $updatedPayload['after']['transaction_date'] ?? null);
        $this->assertSame(10.0, (float) ($updatedPayload['before']['expected_output_qty'] ?? 0));
        $this->assertSame(12.0, (float) ($updatedPayload['after']['expected_output_qty'] ?? 0));
        $this->assertSame('Revisi audit', $updatedPayload['after']['notes'] ?? null);
        $this->assertNull($updatedPayload['before']['cancel_reason'] ?? null);
        $this->assertNull($updatedPayload['after']['cancel_reason'] ?? null);

        $this->assertSame('cancelled', $cancelledPayload['status'] ?? null);
        $this->assertSame(12.0, (float) ($cancelledPayload['expected_output_qty'] ?? 0));
        $this->assertSame('PB-AUDIT-001', $cancelledPayload['reference_no'] ?? null);
        $this->assertSame('Revisi audit', $cancelledPayload['notes'] ?? null);
        $this->assertSame('Revisi audit', $cancelledPayload['cancel_reason'] ?? null);
        $this->assertNotEmpty($cancelledPayload['cancelled_at'] ?? null);
    }

    public function test_post_writes_single_audit_log_and_replay_does_not_duplicate_it(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+production-audit-post@example.test');

        $this->actingAs($user, 'sanctum');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $payload = [
            'actual_output_qty' => 11,
            'variance_reason' => 'Audit yield check',
            'inputs' => [
                [
                    'product_id' => $fixture['raw_material_id'],
                    'actual_qty' => 20,
                ],
            ],
        ];

        $headers = $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-audit-same-key');

        $this->postJson('/api/v1/production-batches/'.$batchId.'/post', $payload, $headers)
            ->assertOk();

        $this->postJson('/api/v1/production-batches/'.$batchId.'/post', $payload, $headers)
            ->assertOk()
            ->assertJsonPath('idempotent', true);

        $logs = DB::table('audit_logs')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'production_batch')
            ->where('reference_id', $batchId)
            ->where('event_type', 'production_batch.posted')
            ->get();

        $this->assertCount(1, $logs);
        $this->assertSame($user->id, $logs[0]->actor_id);

        $payloadJson = json_decode((string) $logs[0]->payload_json, true);

        $this->assertSame('posted', $payloadJson['status'] ?? null);
        $this->assertSame('prod-post-audit-same-key', $payloadJson['idempotency_key'] ?? null);
        $this->assertSame(11.0, (float) ($payloadJson['actual_output_qty'] ?? 0));
        $this->assertSame(1.0, (float) ($payloadJson['yield_variance_qty'] ?? 0));
        $this->assertSame('Audit yield check', $payloadJson['variance_reason'] ?? null);
        $this->assertNull($payloadJson['cancel_reason'] ?? null);
    }

    public function test_show_batch_includes_variance_reason_and_anomaly_summary(): void
    {
        config([
            'services.anomaly.production_batch_yield_variance_pct_threshold' => 5,
            'services.anomaly.production_batch_yield_variance_qty_threshold' => 0.5,
            'services.anomaly.production_batch_yield_variance_severity' => 'high',
        ]);

        [$tenant] = $this->bootApiActor('admin', 'admin+production-show-anomaly@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $this->postJson(
            '/api/v1/production-batches/'.$batchId.'/post',
            [
                'actual_output_qty' => 12,
                'variance_reason' => 'Oven lebih efisien dari estimasi',
                'inputs' => [
                    [
                        'product_id' => $fixture['raw_material_id'],
                        'actual_qty' => 20,
                    ],
                ],
            ],
            $this->tenantHeadersWithIdempotency($tenant->id, 'prod-show-batch-anomaly')
        )->assertOk();

        $this->getJson('/api/v1/production-batches/'.$batchId, $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJsonPath('batch.status', 'posted')
            ->assertJsonPath('batch.variance_reason', 'Oven lebih efisien dari estimasi')
            ->assertJsonPath('batch.cancel_reason', null)
            ->assertJsonPath('anomaly_summary.type', 'production_batch_yield_variance')
            ->assertJsonPath('anomaly_summary.open_count', 1)
            ->assertJsonPath('anomaly_summary.has_open_alert', true)
            ->assertJsonPath('anomaly_history.0.status', 'open')
            ->assertJsonPath('anomaly_history.0.resolved_note', null)
            ->assertJsonPath('observability_url', '/backoffice/settings/observability?type=production_batch_yield_variance&resolved=0&reference_id='.$batchId);
    }

    public function test_list_history_can_filter_cancel_reason_and_open_anomaly_batches(): void
    {
        config([
            'services.anomaly.production_batch_yield_variance_pct_threshold' => 5,
            'services.anomaly.production_batch_yield_variance_qty_threshold' => 0.5,
            'services.anomaly.production_batch_yield_variance_severity' => 'high',
        ]);

        [$tenant] = $this->bootApiActor('admin', 'admin+production-list-cancel-alert@example.test');

        $cancelFixture = $this->seedFixture($tenant);
        $alertFixture = $this->seedFixture($tenant);

        $cancelledBatchId = $this->createDraftBatch($tenant->id, $cancelFixture, expectedOutputQty: 8, recipeVersion: 2, referenceNo: 'PB-CANCELLED');
        $alertBatchId = $this->createDraftBatch($tenant->id, $alertFixture, expectedOutputQty: 10, recipeVersion: 3, referenceNo: 'PB-ALERT');

        $this->postJson('/api/v1/production-batches/'.$cancelledBatchId.'/cancel', [
            'cancel_reason' => 'Salah timbang adonan untuk batch pagi',
        ], $this->tenantHeaders($tenant->id))->assertOk();

        $this->postJson(
            '/api/v1/production-batches/'.$alertBatchId.'/post',
            [
                'actual_output_qty' => 12,
                'variance_reason' => 'Output aktual lebih tinggi dari rencana',
                'inputs' => [
                    [
                        'product_id' => $alertFixture['raw_material_id'],
                        'actual_qty' => 20,
                    ],
                ],
            ],
            $this->tenantHeadersWithIdempotency($tenant->id, 'prod-list-open-alert')
        )->assertOk();

        $this->getJson('/api/v1/production-batches?status=cancelled&cancel_reason=Salah+timbang', $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJsonPath('meta.filters.cancel_reason', 'Salah timbang')
            ->assertJsonPath('data.batches.0.id', $cancelledBatchId)
            ->assertJsonPath('data.batches.0.cancel_reason', 'Salah timbang adonan untuk batch pagi')
            ->assertJsonPath('data.batches.0.anomaly_summary.has_open_alert', false);

        $this->getJson('/api/v1/production-batches?has_open_anomaly=1', $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJsonPath('meta.filters.has_open_anomaly', '1')
            ->assertJsonPath('data.batches.0.id', $alertBatchId)
            ->assertJsonPath('data.batches.0.anomaly_summary.open_count', 1)
            ->assertJsonPath('data.batches.0.anomaly_summary.has_open_alert', true);

        $this->getJson('/api/v1/production-batches?sort_by=latest_anomaly', $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJsonPath('meta.sort_by', 'latest_anomaly')
            ->assertJsonPath('meta.kpis.cancelled_batches', 1)
            ->assertJsonPath('meta.kpis.open_alert_batches', 1)
            ->assertJsonPath('meta.kpis.variance_qty_total', 2)
            ->assertJsonPath('data.batches.0.id', $alertBatchId);
    }

    public function test_resolving_batch_anomaly_updates_show_and_list_consistently(): void
    {
        config([
            'services.anomaly.production_batch_yield_variance_pct_threshold' => 5,
            'services.anomaly.production_batch_yield_variance_qty_threshold' => 0.5,
            'services.anomaly.production_batch_yield_variance_severity' => 'high',
        ]);

        [$tenant] = $this->bootApiActor('admin', 'admin+production-resolve-batch-anomaly@example.test');

        $fixture = $this->seedFixture($tenant);
        $this->setTenantPlan($tenant, 'P');
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3, referenceNo: 'PB-RESOLVE-001');

        $this->postJson(
            '/api/v1/production-batches/'.$batchId.'/post',
            [
                'actual_output_qty' => 12,
                'variance_reason' => 'Butuh investigasi hasil oven',
                'inputs' => [
                    [
                        'product_id' => $fixture['raw_material_id'],
                        'actual_qty' => 20,
                    ],
                ],
            ],
            $this->tenantHeadersWithIdempotency($tenant->id, 'prod-resolve-batch-anomaly')
        )->assertOk();

        $anomalyId = (string) DB::table('anomalies')
            ->where('tenant_id', $tenant->id)
            ->where('type', 'production_batch_yield_variance')
            ->where('reference_id', $batchId)
            ->value('id');

        $this->postJson('/api/v1/anomalies/'.$anomalyId.'/resolve', [
            'note' => 'Sudah diverifikasi supervisor produksi',
        ], $this->tenantHeaders($tenant->id))->assertOk();

        $this->getJson('/api/v1/production-batches/'.$batchId, $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJsonPath('anomaly_summary.open_count', 0)
            ->assertJsonPath('anomaly_summary.resolved_count', 1)
            ->assertJsonPath('anomaly_summary.has_open_alert', false)
            ->assertJsonPath('anomaly_history.0.status', 'resolved')
            ->assertJsonPath('anomaly_history.0.resolved_note', 'Sudah diverifikasi supervisor produksi');

        $this->getJson('/api/v1/production-batches?status=posted&has_open_anomaly=0', $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJsonPath('data.batches.0.id', $batchId)
            ->assertJsonPath('data.batches.0.anomaly_summary.open_count', 0)
            ->assertJsonPath('data.batches.0.anomaly_summary.resolved_count', 1)
            ->assertJsonPath('data.batches.0.anomaly_summary.has_open_alert', false);
    }

    private function setTenantPlan(Tenant $tenant, string $plan): void
    {
        DB::table('tenants')
            ->where('id', $tenant->id)
            ->update(['plan' => $plan]);
    }

    public function test_show_can_filter_and_limit_batch_anomaly_history(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+production-anomaly-history-filter@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->insertBatchRecord($tenant, $fixture, [
            'reference_no' => 'PB-HISTORY-001',
            'status' => 'posted',
            'actual_output_qty' => 12,
            'yield_variance_qty' => 2,
            'yield_variance_pct' => 20,
            'variance_reason' => 'Output lebih tinggi dari target',
        ]);

        DB::table('anomalies')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'severity' => 'high',
                'type' => 'production_batch_yield_variance',
                'reference_id' => $batchId,
                'payload_json' => json_encode([
                    'branch_id' => $fixture['branch_id'],
                    'location_id' => $fixture['location_id'],
                    'product_id' => $fixture['finished_good_id'],
                    'actual_output_qty' => 12,
                    'yield_variance_qty' => 2,
                    'yield_variance_pct' => 20,
                    'variance_reason' => 'Output lebih tinggi dari target',
                    'source' => 'production_batch_post',
                ]),
                'detected_at' => now('UTC')->subMinutes(20),
                'resolved_at' => now('UTC')->subMinutes(10),
                'resolved_by' => $user->id,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'severity' => 'high',
                'type' => 'production_batch_yield_variance',
                'reference_id' => $batchId,
                'payload_json' => json_encode([
                    'branch_id' => $fixture['branch_id'],
                    'location_id' => $fixture['location_id'],
                    'product_id' => $fixture['finished_good_id'],
                    'actual_output_qty' => 13,
                    'yield_variance_qty' => 3,
                    'yield_variance_pct' => 30,
                    'variance_reason' => 'Batch susulan berbeda',
                    'source' => 'production_batch_post',
                ]),
                'detected_at' => now('UTC')->subMinutes(5),
                'resolved_at' => null,
                'resolved_by' => null,
            ],
        ]);

        $this->getJson('/api/v1/production-batches/'.$batchId.'?anomaly_status=resolved&anomaly_limit=1', $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJsonPath('anomaly_history_meta.status', 'resolved')
            ->assertJsonPath('anomaly_history_meta.limit', 1)
            ->assertJsonPath('anomaly_history_meta.total', 1)
            ->assertJsonPath('anomaly_history_meta.has_more', false)
            ->assertJsonPath('anomaly_history.0.status', 'resolved')
            ->assertJsonPath('anomaly_history.0.branch_id', $fixture['branch_id'])
            ->assertJsonPath('anomaly_history.0.location_id', $fixture['location_id']);

        $this->getJson('/api/v1/production-batches/'.$batchId.'?anomaly_status=open&anomaly_limit=1', $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJsonPath('anomaly_history_meta.status', 'open')
            ->assertJsonPath('anomaly_history_meta.total', 1)
            ->assertJsonPath('anomaly_history.0.status', 'open')
            ->assertJsonPath('anomaly_history.0.payload_json.variance_reason', 'Batch susulan berbeda');
    }

    public function test_problematic_batch_export_returns_filtered_csv(): void
    {
        config([
            'services.anomaly.production_batch_yield_variance_pct_threshold' => 5,
            'services.anomaly.production_batch_yield_variance_qty_threshold' => 0.5,
            'services.anomaly.production_batch_yield_variance_severity' => 'high',
        ]);

        [$tenant] = $this->bootApiActor('admin', 'admin+production-problematic-export@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 4, referenceNo: 'PB-EXPORT-001');

        $this->postJson('/api/v1/production-batches/'.$batchId.'/post', [
            'actual_output_qty' => 13,
            'variance_reason' => 'Perlu review output',
            'inputs' => [
                [
                    'product_id' => $fixture['raw_material_id'],
                    'actual_qty' => 20,
                ],
            ],
        ], $this->tenantHeadersWithIdempotency($tenant->id, 'prod-export-problematic'))->assertOk();

        $response = $this->get('/api/v1/production-batches/export/problematic?has_open_anomaly=1', $this->tenantHeaders($tenant->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $header = str_getcsv($lines[0] ?? '');
        $firstRow = str_getcsv($lines[1] ?? '');

        $this->assertSame([
            'Batch ID',
            'Reference',
            'Product',
            'Branch',
            'Location',
            'Status',
            'Transaction Date',
            'Expected Output',
            'Actual Output',
            'Variance Qty',
            'Variance Pct',
            'Open Alerts',
            'Total Alerts',
            'Latest Open Alert',
            'Variance Reason',
            'Cancel Reason',
        ], $header);
        $this->assertSame('PB-EXPORT-001', $firstRow[1] ?? null);
        $this->assertSame('Perlu review output', $firstRow[14] ?? null);
    }

    public function test_post_raises_yield_variance_anomaly_when_threshold_is_exceeded(): void
    {
        config([
            'services.anomaly.production_batch_yield_variance_pct_threshold' => 5,
            'services.anomaly.production_batch_yield_variance_qty_threshold' => 0.5,
            'services.anomaly.production_batch_yield_variance_severity' => 'high',
        ]);
        [$tenant] = $this->bootApiActor('admin', 'admin+production-variance-anomaly@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $this->postJson(
            '/api/v1/production-batches/'.$batchId.'/post',
            [
                'actual_output_qty' => 12,
                'variance_reason' => 'Oven lebih efisien dari estimasi',
                'inputs' => [
                    [
                        'product_id' => $fixture['raw_material_id'],
                        'actual_qty' => 20,
                    ],
                ],
            ],
            $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-variance-anomaly')
        )->assertOk();

        $this->assertDatabaseHas('anomalies', [
            'tenant_id' => $tenant->id,
            'type' => 'production_batch_yield_variance',
            'severity' => 'high',
            'reference_id' => $batchId,
        ]);

        $payload = json_decode((string) DB::table('anomalies')
            ->where('tenant_id', $tenant->id)
            ->where('type', 'production_batch_yield_variance')
            ->where('reference_id', $batchId)
            ->value('payload_json'), true);

        $this->assertSame('production_batch_post', $payload['source'] ?? null);
        $this->assertSame($fixture['finished_good_id'], $payload['product_id'] ?? null);
        $this->assertSame(12.0, (float) ($payload['actual_output_qty'] ?? 0));
        $this->assertSame(2.0, (float) ($payload['yield_variance_qty'] ?? 0));
        $this->assertSame(20.0, (float) ($payload['yield_variance_pct'] ?? 0));
        $this->assertSame(5.0, (float) ($payload['threshold_pct'] ?? 0));
    }

    public function test_recipe_preview_shows_required_materials_and_shortage_status(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-recipe-preview@example.test');

        $fixture = $this->seedFixture($tenant, rawMaterialQty: 100);

        $response = $this->getJson('/api/v1/production/recipe-preview?product_id='.$fixture['finished_good_id'].'&qty=10&location_id='.$fixture['location_id'], $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('data.product.id', $fixture['finished_good_id'])
            ->assertJsonPath('data.qty_requested', 10)
            ->assertJsonPath('data.can_post', true)
            ->assertJsonPath('data.ingredients.0.material_id', $fixture['raw_material_id'])
            ->assertJsonPath('data.ingredients.0.material_name', 'Gula')
            ->assertJsonPath('data.ingredients.0.recipe_qty', 2)
            ->assertJsonPath('data.ingredients.0.required_qty', 20)
            ->assertJsonPath('data.ingredients.0.available_qty', 100)
            ->assertJsonPath('data.ingredients.0.shortage_flag', false)
            ->assertJsonPath('data.ingredients.0.stock_status', 'cukup');

        $shortageResponse = $this->getJson('/api/v1/production/recipe-preview?product_id='.$fixture['finished_good_id'].'&qty=60&location_id='.$fixture['location_id'], $this->tenantHeaders($tenant->id));

        $shortageResponse->assertOk()
            ->assertJsonPath('data.can_post', false)
            ->assertJsonPath('data.ingredients.0.required_qty', 120)
            ->assertJsonPath('data.ingredients.0.available_qty', 100)
            ->assertJsonPath('data.ingredients.0.shortage_flag', true)
            ->assertJsonPath('data.ingredients.0.shortage_qty', 20)
            ->assertJsonPath('data.ingredients.0.stock_status', 'kurang');
    }

    public function test_recipe_preview_requires_location_and_reports_empty_recipe_as_not_postable(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-recipe-preview-hardening@example.test');

        $fixture = $this->seedFixture($tenant, rawMaterialQty: 100);

        DB::table('products')->where('id', $fixture['finished_good_id'])->update([
            'bom_items' => json_encode([]),
        ]);

        $missingLocationResponse = $this->getJson(
            '/api/v1/production/recipe-preview?product_id='.$fixture['finished_good_id'].'&qty=10',
            $this->tenantHeaders($tenant->id)
        );

        $missingLocationResponse->assertStatus(422);

        $emptyRecipeResponse = $this->getJson(
            '/api/v1/production/recipe-preview?product_id='.$fixture['finished_good_id'].'&qty=10&location_id='.$fixture['location_id'],
            $this->tenantHeaders($tenant->id)
        );

        $emptyRecipeResponse->assertOk()
            ->assertJsonPath('data.can_post', false)
            ->assertJsonPath('data.ingredients', [])
            ->assertJsonPath('data.warning_message', 'Recipe aktif tidak ditemukan untuk produk batch production.');
    }

    public function test_recipe_preview_reads_persisted_json_bom_lines_for_multiple_materials(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-recipe-preview-json-bom@example.test');

        $fixture = $this->seedFixture($tenant, rawMaterialQty: 100);

        $secondRawMaterialId = $this->insertProduct($tenant, [
            'sku' => 'RM-PROD-'.Str::upper(Str::random(6)),
            'name' => 'Tepung',
            'product_mode' => 'add_on',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'raw_material',
            'track_stock' => true,
            'price' => 1,
        ]);

        DB::table('products')->where('id', $fixture['finished_good_id'])->update([
            'bom_items' => json_encode([
                [
                    'product_id' => $fixture['raw_material_id'],
                    'product_variant_id' => null,
                    'qty' => 1,
                ],
                [
                    'product_id' => $secondRawMaterialId,
                    'product_variant_id' => null,
                    'qty' => 2,
                ],
            ]),
        ]);

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenant->id,
            'product_id' => $secondRawMaterialId,
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => 100,
        ]);

        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $secondRawMaterialId,
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => 100,
            'average_cost' => 4000,
            'last_cost' => 4000,
            'updated_at' => now('UTC'),
        ]);

        $response = $this->getJson(
            '/api/v1/production/recipe-preview?product_id='.$fixture['finished_good_id'].'&qty=1&location_id='.$fixture['location_id'],
            $this->tenantHeaders($tenant->id)
        );

        $response->assertOk()
            ->assertJsonPath('data.can_post', true)
            ->assertJsonCount(2, 'data.ingredients')
            ->assertJsonPath('data.ingredients.0.material_name', 'Gula')
            ->assertJsonPath('data.ingredients.0.recipe_qty', 1)
            ->assertJsonPath('data.ingredients.1.material_name', 'Tepung')
            ->assertJsonPath('data.ingredients.1.recipe_qty', 2);
    }

    public function test_bootstrap_inventory_payload_exposes_batch_eligible_product_metadata_for_production_pages(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-bootstrap-contract@example.test');

        $fixture = $this->seedFixture($tenant, rawMaterialQty: 100);

        $response = $this->getJson('/api/v1/bootstrap?include=inventory', $this->tenantHeaders($tenant->id));

        $response->assertOk();

        $products = collect($response->json('products'));
        $finishedGood = $products->firstWhere('id', $fixture['finished_good_id']);

        $this->assertNotNull($finishedGood);
        $this->assertSame('batch_production', $finishedGood['production_mode'] ?? null);
        $this->assertSame('finished_good', $finishedGood['item_type'] ?? null);
        $this->assertTrue((bool) ($finishedGood['production_batch_eligible'] ?? false));
        $this->assertTrue((bool) data_get($finishedGood, 'production_batch_eligibility.eligible'));
        $this->assertTrue($products->contains(fn (array $product) => ($product['production_batch_eligible'] ?? false) === true));

        $previewResponse = $this->getJson(
            '/api/v1/production/recipe-preview?product_id='.$fixture['finished_good_id'].'&qty=10&location_id='.$fixture['location_id'],
            $this->tenantHeaders($tenant->id)
        );

        $previewResponse->assertOk()
            ->assertJsonPath('data.product.id', $fixture['finished_good_id'])
            ->assertJsonPath('data.can_post', true)
            ->assertJsonPath('data.ingredients.0.material_id', $fixture['raw_material_id'])
            ->assertJsonPath('data.ingredients.0.shortage_flag', false);
    }

    public function test_bootstrap_inventory_payload_keeps_legacy_batch_products_ineligible_for_production_dropdown(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-bootstrap-legacy-candidate@example.test');

        $legacyProductId = $this->insertProduct($tenant, [
            'sku' => 'LEGACY-PROD-'.Str::upper(Str::random(6)),
            'name' => 'Roti Legacy Batch',
            'product_mode' => 'bom',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'finished_good',
            'track_stock' => false,
            'price' => 15000,
        ]);

        $response = $this->getJson('/api/v1/bootstrap?include=inventory', $this->tenantHeaders($tenant->id));

        $response->assertOk();

        $products = collect($response->json('products'));
        $legacyProduct = $products->firstWhere('id', $legacyProductId);

        $this->assertNotNull($legacyProduct);
        $this->assertSame('bom', $legacyProduct['product_mode'] ?? null);
        $this->assertSame(Product::PRODUCTION_MODE_NONE, $legacyProduct['production_mode'] ?? null);
        $this->assertFalse((bool) ($legacyProduct['production_batch_eligible'] ?? true));
        $this->assertFalse((bool) data_get($legacyProduct, 'production_batch_eligibility.eligible'));
        $this->assertSame('Set mode produksi ke batch_production.', data_get($legacyProduct, 'production_batch_eligibility.reasons.0'));
        $this->assertTrue($products->contains(fn (array $product) => ($product['id'] ?? null) === $legacyProductId));
    }

    public function test_bootstrap_and_branch_loader_contracts_expose_branch_location_and_product_data_for_create_modal(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-bootstrap-branch-location-contract@example.test');

        $fixture = $this->seedFixture($tenant, rawMaterialQty: 100);

        $bootstrapResponse = $this->getJson('/api/v1/bootstrap?include=inventory', $this->tenantHeaders($tenant->id));

        $bootstrapResponse->assertOk()
            ->assertJsonPath('branches.0.id', $fixture['branch_id'])
            ->assertJsonPath('locations.0.id', $fixture['location_id']);

        $products = collect($bootstrapResponse->json('products'));
        $finishedGood = $products->firstWhere('id', $fixture['finished_good_id']);

        $this->assertNotNull($finishedGood);
        $this->assertTrue((bool) ($finishedGood['production_batch_eligible'] ?? false));
        $this->assertIsArray($finishedGood['bom_items'] ?? null);
        $this->assertSame($fixture['raw_material_id'], $finishedGood['bom_items'][0]['product_id'] ?? null);
        $this->assertSame(10.0, (float) ($finishedGood['recipe_output_qty'] ?? 0));

        $branchesResponse = $this->getJson('/api/v1/branches', $this->tenantHeaders($tenant->id));
        $branchesResponse->assertOk();

        $branch = collect($branchesResponse->json('branches'))->firstWhere('id', $fixture['branch_id']);

        $this->assertNotNull($branch);
        $this->assertSame($fixture['location_id'], data_get($branch, 'locations.0.id'));
        $this->assertTrue((bool) data_get($branch, 'locations.0.is_default'));
    }

    public function test_near_whole_recipe_output_qty_is_normalized_in_bootstrap_and_preview(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-near-whole-output@example.test');

        $fixture = $this->seedFixture($tenant, rawMaterialQty: 100);

        DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('id', $fixture['finished_good_id'])
            ->update(['recipe_output_qty' => 9.9998]);

        $bootstrapResponse = $this->getJson('/api/v1/bootstrap?include=inventory', $this->tenantHeaders($tenant->id));
        $bootstrapResponse->assertOk();

        $finishedGood = collect($bootstrapResponse->json('products'))->firstWhere('id', $fixture['finished_good_id']);

        $this->assertNotNull($finishedGood);
        $this->assertSame(10.0, (float) ($finishedGood['recipe_output_qty'] ?? 0));
        $this->assertSame(10.0, (float) data_get($finishedGood, 'production_batch_eligibility.summary.recipe_output_qty', 0));

        $previewResponse = $this->getJson('/api/v1/production/recipe-preview?'.http_build_query([
            'product_id' => $fixture['finished_good_id'],
            'location_id' => $fixture['location_id'],
            'recipe_count' => 1,
        ]), $this->tenantHeaders($tenant->id));

        $previewResponse->assertOk()
            ->assertJsonPath('data.recipe_output_qty', 10)
            ->assertJsonPath('data.expected_output_qty', 10);
    }

    public function test_branch_loader_contract_can_return_branch_without_locations_for_empty_state_handling(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-branch-without-location@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'M']);

        $branchId = (string) Str::uuid();

        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenant->id,
            'name' => 'Cabang Tanpa Lokasi',
        ]);

        $rawMaterialId = $this->insertProduct($tenant, [
            'sku' => 'RM-EMPTY-'.Str::upper(Str::random(6)),
            'name' => 'Tepung',
            'product_mode' => 'add_on',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'raw_material',
            'track_stock' => true,
            'price' => 1,
        ]);

        $finishedGoodId = $this->insertProduct($tenant, [
            'sku' => 'FG-EMPTY-'.Str::upper(Str::random(6)),
            'name' => 'Adonan Siap',
            'product_mode' => 'default',
            'production_mode' => Product::PRODUCTION_MODE_BATCH,
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

        $bootstrapResponse = $this->getJson('/api/v1/bootstrap?include=inventory', $this->tenantHeaders($tenant->id));
        $bootstrapResponse->assertOk()
            ->assertJsonPath('branches.0.id', $branchId)
            ->assertJsonPath('locations', []);

        $products = collect($bootstrapResponse->json('products'));
        $this->assertTrue($products->contains(fn (array $product) => ($product['id'] ?? null) === $finishedGoodId));

        $branchesResponse = $this->getJson('/api/v1/branches', $this->tenantHeaders($tenant->id));
        $branchesResponse->assertOk()
            ->assertJsonPath('branches.0.id', $branchId)
            ->assertJsonPath('branches.0.locations', []);
    }

    public function test_bootstrap_inventory_payload_can_return_empty_products_when_tenant_has_no_active_products(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-bootstrap-empty-products@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update(['plan' => 'M']);

        $branchId = (string) Str::uuid();
        $locationId = (string) Str::uuid();

        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenant->id,
            'name' => 'Cabang Produksi',
        ]);

        DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'name' => 'Gudang Produksi',
            'is_default' => true,
        ]);

        $response = $this->getJson('/api/v1/bootstrap?include=inventory', $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('branches.0.id', $branchId)
            ->assertJsonPath('locations.0.id', $locationId)
            ->assertJsonPath('products', [])
            ->assertJsonPath('inventory', []);
    }

    public function test_post_requires_variance_reason_when_threshold_is_exceeded(): void
    {
        config([
            'services.anomaly.production_batch_yield_variance_pct_threshold' => 5,
            'services.anomaly.production_batch_yield_variance_qty_threshold' => 0.5,
        ]);

        [$tenant] = $this->bootApiActor('admin', 'admin+production-variance-reason-required@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $response = $this->postJson(
            '/api/v1/production-batches/'.$batchId.'/post',
            [
                'actual_output_qty' => 12,
                'inputs' => [
                    [
                        'product_id' => $fixture['raw_material_id'],
                        'actual_qty' => 20,
                    ],
                ],
            ],
            $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-variance-reason-required')
        );

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonPath('details.variance_reason.0', 'Variance reason wajib diisi saat yield variance menyentuh threshold monitoring.');

        $this->assertDatabaseHas('production_batches', [
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'status' => 'draft',
            'posted_at' => null,
        ]);
    }

    public function test_post_replay_does_not_duplicate_yield_variance_anomaly(): void
    {
        config([
            'services.anomaly.production_batch_yield_variance_pct_threshold' => 5,
            'services.anomaly.production_batch_yield_variance_qty_threshold' => 0.5,
            'services.anomaly.production_batch_yield_variance_severity' => 'high',
        ]);

        [$tenant] = $this->bootApiActor('admin', 'admin+production-variance-replay@example.test');

        $fixture = $this->seedFixture($tenant);
        $batchId = $this->createDraftBatch($tenant->id, $fixture, expectedOutputQty: 10, recipeVersion: 3);

        $payload = [
            'actual_output_qty' => 12,
            'variance_reason' => 'Konsisten lebih tinggi',
            'inputs' => [
                [
                    'product_id' => $fixture['raw_material_id'],
                    'actual_qty' => 20,
                ],
            ],
        ];

        $headers = $this->tenantHeadersWithIdempotency($tenant->id, 'prod-post-variance-replay');

        $this->postJson('/api/v1/production-batches/'.$batchId.'/post', $payload, $headers)->assertOk();
        $this->postJson('/api/v1/production-batches/'.$batchId.'/post', $payload, $headers)
            ->assertOk()
            ->assertJsonPath('idempotent', true);

        $this->assertSame(1, DB::table('anomalies')
            ->where('tenant_id', $tenant->id)
            ->where('type', 'production_batch_yield_variance')
            ->where('reference_id', $batchId)
            ->count());
    }

    private function createDraftBatch(string $tenantId, array $fixture, float $expectedOutputQty, int $recipeVersion, ?string $referenceNo = null): string
    {
        $response = $this->postJson('/api/v1/production-batches', [
            'branch_id' => $fixture['branch_id'],
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['finished_good_id'],
            'transaction_date' => '2026-02-27',
            'expected_output_qty' => $expectedOutputQty,
            'recipe_version' => $recipeVersion,
            'reference_no' => $referenceNo,
        ], $this->tenantHeaders($tenantId));

        $response->assertCreated();

        return (string) $response->json('batch_id');
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
            'reference_no' => 'PB-FIXTURE-001',
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

        if (($batch['status'] ?? null) === 'posted' && ! $batch['posted_at']) {
            $batch['posted_at'] = $now;
        }

        if (($batch['status'] ?? null) === 'cancelled' && ! $batch['cancelled_at']) {
            $batch['cancelled_at'] = $now;
        }

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
                'qty' => (float) ($batch['expected_output_qty'] ?? 10),
                'planned_qty' => (float) ($batch['expected_output_qty'] ?? 10),
                'actual_qty' => null,
                'uom' => null,
                'unit_cost_snapshot' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        return $batchId;
    }

    private function tenantHeadersWithIdempotency(string $tenantId, string $idempotencyKey): array
    {
        return $this->tenantHeaders($tenantId) + [
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    private function seedFixture(
        Tenant $tenant,
        float $rawMaterialQty = 100,
        string $finishedGoodProductionMode = Product::PRODUCTION_MODE_BATCH,
        bool $finishedGoodActive = true
    ): array
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
            'production_mode' => $finishedGoodProductionMode,
            'item_type' => 'finished_good',
            'track_stock' => true,
            'is_active' => $finishedGoodActive,
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

    public function test_plan_s_cannot_access_production_batch_endpoints(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+production-plan-s@example.test');

        $this->getJson('/api/v1/production-batches', $this->tenantHeaders($tenant->id))
            ->assertStatus(403)
            ->assertJsonPath('code', 'FEATURE_NOT_ALLOWED');
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
}
