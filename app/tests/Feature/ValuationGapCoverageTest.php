<?php

namespace Tests\Feature;

use App\Events\SaleCompleted;
use App\Listeners\AggregateDailySales;
use App\Models\InventoryIssueAllocation;
use App\Models\InventoryLayer;
use App\Services\InventoryValuationService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class ValuationGapCoverageTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->resetApiClock();
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_listener_idempotency_prevents_duplicate_daily_summary_updates_for_same_event_id(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-gap-idempotency@example.com', 'Admin Gap A');
        TenantContext::set($tenant);
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $saleId = (string) Str::uuid();
        $eventId = (string) Str::uuid();

        \DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'customer_id' => null,
            'subtotal' => 10000,
            'discount' => 0,
            'tax' => 0,
            'total' => 10000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'created_at' => now('UTC'),
            'transaction_date' => '2026-02-27',
        ]);

        \DB::table('sale_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'qty' => 1,
            'price' => 10000,
            'discount' => 0,
            'hpp_snapshot' => 5000,
        ]);

        $event = new SaleCompleted(
            saleId: $saleId,
            tenantId: $tenant->id,
            total: 10000,
            actorId: $user->id,
            occurredAt: now('UTC')->toIso8601String(),
            eventId: $eventId,
        );

        $listener = app(AggregateDailySales::class);
        $listener->handle($event);
        $listener->handle($event);

        $this->assertDatabaseHas('daily_sales_summaries', [
            'tenant_id' => $tenant->id,
            'date' => '2026-02-27',
            'total_sales' => 10000,
            'transaction_count' => 1,
        ]);

        $this->assertSame(1, \DB::table('processed_events')
            ->where('tenant_id', $tenant->id)
            ->where('event_uuid', $eventId)
            ->where('listener_name', AggregateDailySales::class)
            ->count());
    }

    public function test_fifo_and_average_produce_same_cost_for_single_layer_issue_case(): void
    {
        $valuationService = app(InventoryValuationService::class);

        [$tenantAverage, $userAverage] = $this->bootApiActor('admin', 'admin-gap-avg@example.com', 'Admin Gap Avg');
        [$tenantFifo, $userFifo] = $this->bootApiActor('admin', 'admin-gap-fifo@example.com', 'Admin Gap Fifo');

        $fixtureAverage = $this->seedCheckoutFixture($tenantAverage->id, $userAverage->id);
        $fixtureFifo = $this->seedCheckoutFixture($tenantFifo->id, $userFifo->id);

        \DB::table('tenants')->where('id', $tenantAverage->id)->update(['inventory_valuation_method' => 'average']);
        \DB::table('tenants')->where('id', $tenantFifo->id)->update(['inventory_valuation_method' => 'fifo']);

        \DB::table('inventory_cost_states')
            ->where('tenant_id', $tenantAverage->id)
            ->where('product_id', $fixtureAverage['product_id'])
            ->where('location_id', $fixtureAverage['location_id'])
            ->delete();

        \DB::table('inventory_cost_states')
            ->where('tenant_id', $tenantFifo->id)
            ->where('product_id', $fixtureFifo['product_id'])
            ->where('location_id', $fixtureFifo['location_id'])
            ->delete();

        TenantContext::set($tenantAverage);
        $valuationService->receive(
            tenantId: $tenantAverage->id,
            productId: $fixtureAverage['product_id'],
            productVariantId: null,
            locationId: $fixtureAverage['location_id'],
            qty: 10,
            unitCost: 5000,
            sourceType: 'seed',
            sourceId: (string) Str::uuid(),
            sourceItemId: null,
            at: now('UTC'),
        );

        TenantContext::set($tenantFifo);
        $valuationService->receive(
            tenantId: $tenantFifo->id,
            productId: $fixtureFifo['product_id'],
            productVariantId: null,
            locationId: $fixtureFifo['location_id'],
            qty: 10,
            unitCost: 5000,
            sourceType: 'seed',
            sourceId: (string) Str::uuid(),
            sourceItemId: null,
            at: now('UTC'),
        );

        TenantContext::set($tenantAverage);
        $avgResult = $valuationService->issue(
            tenantId: $tenantAverage->id,
            productId: $fixtureAverage['product_id'],
            productVariantId: null,
            locationId: $fixtureAverage['location_id'],
            qty: 4,
            referenceType: 'test_issue',
            referenceId: (string) Str::uuid(),
            referenceItemId: (string) Str::uuid(),
            at: now('UTC'),
        );

        TenantContext::set($tenantFifo);
        $fifoResult = $valuationService->issue(
            tenantId: $tenantFifo->id,
            productId: $fixtureFifo['product_id'],
            productVariantId: null,
            locationId: $fixtureFifo['location_id'],
            qty: 4,
            referenceType: 'test_issue',
            referenceId: (string) Str::uuid(),
            referenceItemId: (string) Str::uuid(),
            at: now('UTC'),
        );

        $this->assertSame(5000.0, (float) $avgResult['unit_cost']);
        $this->assertSame(5000.0, (float) $fifoResult['unit_cost']);
        $this->assertSame(20000.0, (float) $avgResult['total_cost']);
        $this->assertSame(20000.0, (float) $fifoResult['total_cost']);
    }

    public function test_replay_handles_large_ledger_volume_without_missing_cost_state(): void
    {
        $valuationService = app(InventoryValuationService::class);

        [$tenant, $user] = $this->bootApiActor('admin', 'admin-gap-replay@example.com', 'Admin Gap Replay');
        TenantContext::set($tenant);
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        \DB::table('tenants')->where('id', $tenant->id)->update(['inventory_valuation_method' => 'average']);

        \DB::table('inventory_cost_states')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('location_id', $fixture['location_id'])
            ->delete();

        $rows = [];
        for ($i = 1; $i <= 1200; $i++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'product_id' => $fixture['product_id'],
                'location_id' => $fixture['location_id'],
                'reference_type' => 'seed_in',
                'reference_id' => (string) Str::uuid(),
                'reference_item_id' => null,
                'qty_in' => 1,
                'qty_out' => 0,
                'balance_after' => $i,
                'created_at' => now('UTC')->addSeconds($i),
                'created_by' => $user->id,
            ];
        }

        \DB::table('stock_ledger')->insert($rows);

        $valuationService->replayFromLedger($tenant->id);

        $this->assertDatabaseHas('inventory_cost_states', [
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => 1200,
        ]);
    }

    public function test_replay_rejects_legacy_ambiguous_sale_row_for_variant_product(): void
    {
        $valuationService = app(InventoryValuationService::class);

        [$tenant, $user] = $this->bootApiActor('admin', 'admin-gap-replay-legacy-variant@example.com', 'Admin Gap Replay Legacy Variant');
        TenantContext::set($tenant);
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        \DB::table('tenants')->where('id', $tenant->id)->update(['inventory_valuation_method' => 'fifo']);

        $variantA = $this->createVariant($tenant->id, $fixture['product_id'], 'LEG-A', 'Varian Legacy A');
        $variantB = $this->createVariant($tenant->id, $fixture['product_id'], 'LEG-B', 'Varian Legacy B');
        $saleId = $this->insertReplaySale($tenant->id, $fixture, $user->id, '2026-04-06 08:00:00');

        \DB::table('sale_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => $variantA,
                'qty' => 1,
                'price' => 10000,
                'discount' => 0,
                'hpp_snapshot' => 0,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => $variantB,
                'qty' => 1,
                'price' => 10000,
                'discount' => 0,
                'hpp_snapshot' => 0,
            ],
        ]);

        $this->insertReplayLedgerRow([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'reference_item_id' => null,
            'qty_in' => 0,
            'qty_out' => 1,
            'balance_after' => 99,
            'unit_cost' => 5000,
            'total_cost' => 5000,
            'created_at' => '2026-04-06 08:00:00',
            'created_by' => $user->id,
        ]);

        try {
            $valuationService->replayFromLedger($tenant->id);
            $this->fail('Replay seharusnya menolak row legacy ambigu untuk produk bervarian.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('sale legacy ambigu', $exception->getMessage());
        }

        $this->assertSame(1, \DB::table('valuation_audit_logs')
            ->where('tenant_id', $tenant->id)
            ->where('action', 'valuation_replay_legacy_ambiguous')
            ->count());

        $this->assertSame(1, \DB::table('anomalies')
            ->where('tenant_id', $tenant->id)
            ->where('type', 'valuation_replay_legacy_ambiguous')
            ->count());

        $auditMeta = $this->latestValuationAuditMeta($tenant->id, 'valuation_replay_legacy_ambiguous');

        $this->assertSame('legacy_ambiguous', $auditMeta['status'] ?? null);
        $this->assertSame(2, $auditMeta['candidate_count'] ?? null);
        $this->assertEqualsCanonicalizing([$variantA, $variantB], array_values(array_filter($auditMeta['candidate_variant_ids'] ?? [])));

        $this->assertSame(0, InventoryIssueAllocation::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, InventoryLayer::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, \DB::table('inventory_cost_states')->where('tenant_id', $tenant->id)->count());
    }

    public function test_replay_rejects_modern_row_missing_variant_context_for_variant_product(): void
    {
        $valuationService = app(InventoryValuationService::class);

        [$tenant, $user] = $this->bootApiActor('admin', 'admin-gap-replay-missing-variant@example.com', 'Admin Gap Replay Missing Variant');
        TenantContext::set($tenant);
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        \DB::table('tenants')->where('id', $tenant->id)->update(['inventory_valuation_method' => 'average']);

        $variantId = $this->createVariant($tenant->id, $fixture['product_id'], 'MOD-MISS', 'Varian Modern');
        $supplierId = $this->insertReplaySupplier($tenant->id);
        $purchaseId = $this->insertReplayPurchase($tenant->id, $supplierId, $fixture['location_id'], $user->id, '2026-04-06 09:00:00');
        $purchaseItemId = (string) Str::uuid();

        \DB::table('purchase_items')->insert([
            'id' => $purchaseItemId,
            'tenant_id' => $tenant->id,
            'purchase_id' => $purchaseId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => $variantId,
            'qty' => 4,
            'price' => 7200,
        ]);

        $this->insertReplayLedgerRow([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'purchase',
            'reference_id' => $purchaseId,
            'reference_item_id' => $purchaseItemId,
            'qty_in' => 4,
            'qty_out' => 0,
            'balance_after' => 4,
            'unit_cost' => 7200,
            'total_cost' => 28800,
            'created_at' => '2026-04-06 09:00:00',
            'created_by' => $user->id,
        ]);

        try {
            $valuationService->replayFromLedger($tenant->id);
            $this->fail('Replay seharusnya menolak row modern tanpa product_variant_id untuk produk bervarian.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('missing variant context', $exception->getMessage());
        }

        $this->assertSame(1, \DB::table('valuation_audit_logs')
            ->where('tenant_id', $tenant->id)
            ->where('action', 'valuation_replay_missing_variant_context')
            ->count());

        $this->assertSame(1, \DB::table('anomalies')
            ->where('tenant_id', $tenant->id)
            ->where('type', 'valuation_replay_missing_variant_context')
            ->count());

        $auditMeta = $this->latestValuationAuditMeta($tenant->id, 'valuation_replay_missing_variant_context');

        $this->assertSame($variantId, $auditMeta['source_item_variant_id'] ?? null);
        $this->assertSame($purchaseItemId, $auditMeta['reference_item_id'] ?? null);
        $this->assertSame($fixture['product_id'], $auditMeta['product_id'] ?? null);
        $this->assertSame(0, InventoryLayer::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, \DB::table('inventory_cost_states')->where('tenant_id', $tenant->id)->count());
    }

    public function test_replay_rebuilds_exact_variant_bucket_for_modern_rows(): void
    {
        $valuationService = app(InventoryValuationService::class);

        [$tenant, $user] = $this->bootApiActor('admin', 'admin-gap-replay-exact-variant@example.com', 'Admin Gap Replay Exact Variant');
        TenantContext::set($tenant);
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        \DB::table('tenants')->where('id', $tenant->id)->update(['inventory_valuation_method' => 'fifo']);

        $variantId = $this->createVariant($tenant->id, $fixture['product_id'], 'MOD-OK', 'Varian Replay Tepat');
        $supplierId = $this->insertReplaySupplier($tenant->id);
        $purchaseId = $this->insertReplayPurchase($tenant->id, $supplierId, $fixture['location_id'], $user->id, '2026-04-06 10:00:00');
        $purchaseItemId = (string) Str::uuid();

        \DB::table('purchase_items')->insert([
            'id' => $purchaseItemId,
            'tenant_id' => $tenant->id,
            'purchase_id' => $purchaseId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => $variantId,
            'qty' => 5,
            'price' => 7000,
        ]);

        $saleId = $this->insertReplaySale($tenant->id, $fixture, $user->id, '2026-04-06 11:00:00');
        $saleItemId = (string) Str::uuid();

        \DB::table('sale_items')->insert([
            'id' => $saleItemId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => $variantId,
            'qty' => 2,
            'price' => 10000,
            'discount' => 0,
            'hpp_snapshot' => 0,
        ]);

        $this->insertReplayLedgerRow([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => $variantId,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'purchase',
            'reference_id' => $purchaseId,
            'reference_item_id' => $purchaseItemId,
            'qty_in' => 5,
            'qty_out' => 0,
            'balance_after' => 5,
            'unit_cost' => 7000,
            'total_cost' => 35000,
            'created_at' => '2026-04-06 10:00:00',
            'created_by' => $user->id,
        ]);

        $this->insertReplayLedgerRow([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => $variantId,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 0,
            'qty_out' => 2,
            'balance_after' => 3,
            'unit_cost' => 7000,
            'total_cost' => 14000,
            'created_at' => '2026-04-06 11:00:00',
            'created_by' => $user->id,
        ]);

        $valuationService->replayFromLedger($tenant->id);

        $layer = InventoryLayer::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('product_variant_id', $variantId)
            ->where('location_id', $fixture['location_id'])
            ->first();

        $this->assertNotNull($layer);
        $this->assertSame(5.0, (float) $layer->qty_original);
        $this->assertSame(3.0, (float) $layer->qty_remaining);
        $this->assertSame(7000.0, (float) $layer->cost_per_unit);

        $allocation = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('product_variant_id', $variantId)
            ->where('location_id', $fixture['location_id'])
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->first();

        $this->assertNotNull($allocation);
        $this->assertSame(2.0, (float) $allocation->qty);
        $this->assertSame(7000.0, (float) $allocation->unit_cost);
        $this->assertSame(14000.0, (float) $allocation->total_cost);

        $this->assertDatabaseHas('inventory_cost_states', [
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => $variantId,
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => 3,
            'average_cost' => 7000,
            'last_cost' => 7000,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'tenant_id' => $tenant->id,
            'id' => $saleItemId,
            'hpp_snapshot' => 7000,
        ]);

        $this->assertSame(0, InventoryLayer::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->whereNull('product_variant_id')
            ->count());

        $this->assertSame(0, InventoryIssueAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->whereNull('product_variant_id')
            ->count());

        $this->assertSame(1, \DB::table('valuation_audit_logs')
            ->where('tenant_id', $tenant->id)
            ->where('action', 'valuation_replay_completed')
            ->count());
    }

    public function test_fifo_issue_consumes_oldest_layers_and_records_split_allocations(): void
    {
        $valuationService = app(InventoryValuationService::class);

        [$tenant, $user] = $this->bootApiActor('admin', 'admin-gap-fifo-order@example.com', 'Admin Gap Fifo Order');
        TenantContext::set($tenant);
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        \DB::table('tenants')->where('id', $tenant->id)->update(['inventory_valuation_method' => 'fifo']);

        $valuationService->receive(
            tenantId: $tenant->id,
            productId: $fixture['product_id'],
            productVariantId: null,
            locationId: $fixture['location_id'],
            qty: 2,
            unitCost: 10000,
            sourceType: 'seed_oldest',
            sourceId: (string) Str::uuid(),
            sourceItemId: null,
            at: new \DateTimeImmutable('2026-03-19T08:00:00Z'),
        );

        $valuationService->receive(
            tenantId: $tenant->id,
            productId: $fixture['product_id'],
            productVariantId: null,
            locationId: $fixture['location_id'],
            qty: 3,
            unitCost: 12000,
            sourceType: 'seed_newer',
            sourceId: (string) Str::uuid(),
            sourceItemId: null,
            at: new \DateTimeImmutable('2026-03-19T09:00:00Z'),
        );

        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();

        $result = $valuationService->issue(
            tenantId: $tenant->id,
            productId: $fixture['product_id'],
            productVariantId: null,
            locationId: $fixture['location_id'],
            qty: 4,
            referenceType: 'sale',
            referenceId: $saleId,
            referenceItemId: $saleItemId,
            at: new \DateTimeImmutable('2026-03-19T10:00:00Z'),
        );

        $this->assertSame(11000.0, round((float) $result['unit_cost'], 2));
        $this->assertSame(44000.0, round((float) $result['total_cost'], 2));

        $layers = InventoryLayer::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('location_id', $fixture['location_id'])
            ->orderBy('received_at')
            ->get();

        $this->assertCount(2, $layers);
        $this->assertSame(0.0, (float) $layers[0]->qty_remaining);
        $this->assertSame(1.0, (float) $layers[1]->qty_remaining);

        $allocations = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(2, $allocations);
        $this->assertSame(2.0, (float) $allocations[0]->qty);
        $this->assertSame(10000.0, (float) $allocations[0]->unit_cost);
        $this->assertSame(2.0, (float) $allocations[1]->qty);
        $this->assertSame(12000.0, (float) $allocations[1]->unit_cost);
    }

    public function test_fifo_reverse_issue_restores_layer_qty_and_marks_allocations_reversed(): void
    {
        $valuationService = app(InventoryValuationService::class);

        [$tenant, $user] = $this->bootApiActor('admin', 'admin-gap-fifo-reverse@example.com', 'Admin Gap Fifo Reverse');
        TenantContext::set($tenant);
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        \DB::table('tenants')->where('id', $tenant->id)->update(['inventory_valuation_method' => 'fifo']);

        $valuationService->receive(
            tenantId: $tenant->id,
            productId: $fixture['product_id'],
            productVariantId: null,
            locationId: $fixture['location_id'],
            qty: 5,
            unitCost: 9000,
            sourceType: 'seed_opening',
            sourceId: (string) Str::uuid(),
            sourceItemId: null,
            at: new \DateTimeImmutable('2026-03-19T08:00:00Z'),
        );

        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();

        $valuationService->issue(
            tenantId: $tenant->id,
            productId: $fixture['product_id'],
            productVariantId: null,
            locationId: $fixture['location_id'],
            qty: 3,
            referenceType: 'sale',
            referenceId: $saleId,
            referenceItemId: $saleItemId,
            at: new \DateTimeImmutable('2026-03-19T09:00:00Z'),
        );

        $valuationService->reverseIssue(
            tenantId: $tenant->id,
            referenceType: 'sale',
            referenceId: $saleId,
            at: new \DateTimeImmutable('2026-03-19T10:00:00Z'),
            newSourceType: 'sale_void',
            newSourceId: (string) Str::uuid(),
        );

        $layer = InventoryLayer::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('location_id', $fixture['location_id'])
            ->firstOrFail();

        $this->assertSame(5.0, (float) $layer->qty_remaining);

        $reversedCount = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->whereNotNull('reversed_at')
            ->count();

        $this->assertSame(1, $reversedCount);

        $this->assertDatabaseHas('inventory_cost_states', [
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => 5,
        ]);
    }

    public function test_fifo_multi_layer_issue_consumes_without_skipping_layers(): void
    {
        $valuationService = app(InventoryValuationService::class);

        [$tenant, $user] = $this->bootApiActor('admin', 'admin-gap-fifo-multilayer@example.com', 'Admin Gap Fifo MultiLayer');
        TenantContext::set($tenant);
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        \DB::table('tenants')->where('id', $tenant->id)->update(['inventory_valuation_method' => 'fifo']);

        $valuationService->receive(
            tenantId: $tenant->id,
            productId: $fixture['product_id'],
            productVariantId: null,
            locationId: $fixture['location_id'],
            qty: 1,
            unitCost: 9000,
            sourceType: 'seed_layer_a',
            sourceId: (string) Str::uuid(),
            sourceItemId: null,
            at: new \DateTimeImmutable('2026-03-19T08:00:00Z'),
        );

        $valuationService->receive(
            tenantId: $tenant->id,
            productId: $fixture['product_id'],
            productVariantId: null,
            locationId: $fixture['location_id'],
            qty: 2,
            unitCost: 10000,
            sourceType: 'seed_layer_b',
            sourceId: (string) Str::uuid(),
            sourceItemId: null,
            at: new \DateTimeImmutable('2026-03-19T09:00:00Z'),
        );

        $valuationService->receive(
            tenantId: $tenant->id,
            productId: $fixture['product_id'],
            productVariantId: null,
            locationId: $fixture['location_id'],
            qty: 4,
            unitCost: 12000,
            sourceType: 'seed_layer_c',
            sourceId: (string) Str::uuid(),
            sourceItemId: null,
            at: new \DateTimeImmutable('2026-03-19T10:00:00Z'),
        );

        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();

        $result = $valuationService->issue(
            tenantId: $tenant->id,
            productId: $fixture['product_id'],
            productVariantId: null,
            locationId: $fixture['location_id'],
            qty: 6,
            referenceType: 'sale',
            referenceId: $saleId,
            referenceItemId: $saleItemId,
            at: new \DateTimeImmutable('2026-03-19T11:00:00Z'),
        );

        $this->assertSame(10833.33, round((float) $result['unit_cost'], 2));
        $this->assertSame(65000.0, round((float) $result['total_cost'], 2));

        $allocations = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(3, $allocations);
        $this->assertSame(1.0, (float) $allocations[0]->qty);
        $this->assertSame(2.0, (float) $allocations[1]->qty);
        $this->assertSame(3.0, (float) $allocations[2]->qty);
    }

    public function test_fifo_reverse_issue_is_replay_safe_and_does_not_double_restore(): void
    {
        $valuationService = app(InventoryValuationService::class);

        [$tenant, $user] = $this->bootApiActor('admin', 'admin-gap-fifo-replay@example.com', 'Admin Gap Fifo Replay');
        TenantContext::set($tenant);
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        \DB::table('tenants')->where('id', $tenant->id)->update(['inventory_valuation_method' => 'fifo']);

        $valuationService->receive(
            tenantId: $tenant->id,
            productId: $fixture['product_id'],
            productVariantId: null,
            locationId: $fixture['location_id'],
            qty: 5,
            unitCost: 9500,
            sourceType: 'seed_opening',
            sourceId: (string) Str::uuid(),
            sourceItemId: null,
            at: new \DateTimeImmutable('2026-03-19T08:00:00Z'),
        );

        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();

        $valuationService->issue(
            tenantId: $tenant->id,
            productId: $fixture['product_id'],
            productVariantId: null,
            locationId: $fixture['location_id'],
            qty: 2,
            referenceType: 'sale',
            referenceId: $saleId,
            referenceItemId: $saleItemId,
            at: new \DateTimeImmutable('2026-03-19T09:00:00Z'),
        );

        $valuationService->reverseIssue(
            tenantId: $tenant->id,
            referenceType: 'sale',
            referenceId: $saleId,
            at: new \DateTimeImmutable('2026-03-19T10:00:00Z'),
            newSourceType: 'sale_void',
            newSourceId: (string) Str::uuid(),
        );

        $valuationService->reverseIssue(
            tenantId: $tenant->id,
            referenceType: 'sale',
            referenceId: $saleId,
            at: new \DateTimeImmutable('2026-03-19T11:00:00Z'),
            newSourceType: 'sale_void_replay',
            newSourceId: (string) Str::uuid(),
        );

        $layer = InventoryLayer::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('location_id', $fixture['location_id'])
            ->firstOrFail();

        $this->assertSame(5.0, (float) $layer->qty_remaining);

        $reversedCount = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->whereNotNull('reversed_at')
            ->count();

        $this->assertSame(1, $reversedCount);
    }

    private function createVariant(string $tenantId, string $productId, string $skuSuffix, string $name): string
    {
        $variantId = (string) Str::uuid();

        \DB::table('product_variants')->insert([
            'id' => $variantId,
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'sku' => 'SKU-001-'.$skuSuffix,
            'barcode' => null,
            'name' => $name,
            'attributes' => json_encode(['variant' => $name]),
            'price_override' => null,
            'is_active' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return $variantId;
    }

    private function insertReplaySupplier(string $tenantId): string
    {
        $supplierId = (string) Str::uuid();

        \DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $tenantId,
            'name' => 'Supplier Replay',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        return $supplierId;
    }

    private function insertReplayPurchase(string $tenantId, string $supplierId, string $locationId, string $userId, string $createdAt): string
    {
        $purchaseId = (string) Str::uuid();

        \DB::table('purchases')->insert([
            'id' => $purchaseId,
            'tenant_id' => $tenantId,
            'supplier_id' => $supplierId,
            'location_id' => $locationId,
            'total' => 35000,
            'status' => 'received',
            'notes' => null,
            'created_at' => $createdAt,
            'approved_at' => $createdAt,
            'approved_by' => $userId,
            'received_at' => $createdAt,
            'received_by' => $userId,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'transaction_date' => substr($createdAt, 0, 10),
        ]);

        return $purchaseId;
    }

    private function insertReplaySale(string $tenantId, array $fixture, string $userId, string $createdAt): string
    {
        $saleId = (string) Str::uuid();

        \DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenantId,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'customer_id' => null,
            'subtotal' => 20000,
            'discount' => 0,
            'tax' => 0,
            'total' => 20000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $userId,
            'voided_by' => null,
            'created_at' => $createdAt,
            'transaction_date' => substr($createdAt, 0, 10),
        ]);

        return $saleId;
    }

    private function insertReplayLedgerRow(array $row): void
    {
        \DB::table('stock_ledger')->insert($row);
    }

    private function latestValuationAuditMeta(string $tenantId, string $action): array
    {
        $row = \DB::table('valuation_audit_logs')
            ->where('tenant_id', $tenantId)
            ->where('action', $action)
            ->latest('created_at')
            ->first();

        $meta = json_decode((string) ($row->meta_json ?? '{}'), true);

        return is_array($meta) ? $meta : [];
    }
}
