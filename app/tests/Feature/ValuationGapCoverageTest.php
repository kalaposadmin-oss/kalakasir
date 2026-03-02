<?php

namespace Tests\Feature;

use App\Events\SaleCompleted;
use App\Listeners\AggregateDailySales;
use App\Services\InventoryValuationService;
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

        parent::tearDown();
    }

    public function test_listener_idempotency_prevents_duplicate_daily_summary_updates_for_same_event_id(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-gap-idempotency@example.com', 'Admin Gap A');
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

        $valuationService->receive(
            tenantId: $tenantAverage->id,
            productId: $fixtureAverage['product_id'],
            locationId: $fixtureAverage['location_id'],
            qty: 10,
            unitCost: 5000,
            sourceType: 'seed',
            sourceId: (string) Str::uuid(),
            at: now('UTC'),
        );

        $valuationService->receive(
            tenantId: $tenantFifo->id,
            productId: $fixtureFifo['product_id'],
            locationId: $fixtureFifo['location_id'],
            qty: 10,
            unitCost: 5000,
            sourceType: 'seed',
            sourceId: (string) Str::uuid(),
            at: now('UTC'),
        );

        $avgResult = $valuationService->issue(
            tenantId: $tenantAverage->id,
            productId: $fixtureAverage['product_id'],
            locationId: $fixtureAverage['location_id'],
            qty: 4,
            referenceType: 'test_issue',
            referenceId: (string) Str::uuid(),
            referenceItemId: null,
            at: now('UTC'),
        );

        $fifoResult = $valuationService->issue(
            tenantId: $tenantFifo->id,
            productId: $fixtureFifo['product_id'],
            locationId: $fixtureFifo['location_id'],
            qty: 4,
            referenceType: 'test_issue',
            referenceId: (string) Str::uuid(),
            referenceItemId: null,
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
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        \DB::table('tenants')->where('id', $tenant->id)->update(['inventory_valuation_method' => 'average']);

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
}
