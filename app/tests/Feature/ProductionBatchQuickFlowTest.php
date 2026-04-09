<?php

namespace Tests\Feature;

use App\Models\Product;
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