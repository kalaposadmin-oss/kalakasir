<?php

namespace Tests\Feature;

use App\Models\InventoryIssueAllocation;
use App\Models\InventoryLayer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InventoryValuationService;
use App\Services\PurchaseHistoryBackfillService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class PurchaseReturnLegacyBackfillTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin+purchase-return-backfill@example.test', 'Admin Purchase Return Backfill');
        $this->tenant->forceFill(['plan' => 'M'])->save();
        $this->fixture = $this->seedCheckoutFixture($this->tenant->id, $this->user->id);

        TenantContext::set($this->tenant);

        \DB::table('tenants')
            ->where('id', $this->tenant->id)
            ->update(['inventory_valuation_method' => 'fifo']);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_backfill_updates_legacy_purchase_layer_when_reference_item_is_exact(): void
    {
        $purchase = $this->seedPurchaseGraph('2026-04-05 08:00:00', 5, 7000);

        $service = app(PurchaseHistoryBackfillService::class);
        $result = $service->backfillPurchaseLayerSourceItems($this->tenant->id, true);

        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['summary']['resolved_exact_ledger']);

        $this->assertDatabaseHas('inventory_layers', [
            'tenant_id' => $this->tenant->id,
            'id' => $purchase['layer_id'],
            'source_item_id' => $purchase['purchase_item_id'],
        ]);

        $this->assertSame(1, \DB::table('valuation_audit_logs')
            ->where('tenant_id', $this->tenant->id)
            ->where('action', 'valuation_purchase_layer_backfill_resolved')
            ->count());
    }

    public function test_backfill_marks_legacy_purchase_layer_ambiguous_without_guessing(): void
    {
        $supplierId = $this->insertSupplier();
        $purchaseId = $this->insertPurchase($supplierId, '2026-04-05 09:00:00');
        $firstPurchaseItemId = $this->insertPurchaseItem($purchaseId, 2, 7000);
        $secondPurchaseItemId = $this->insertPurchaseItem($purchaseId, 2, 7000);
        $receivedAt = '2026-04-05 09:15:00';
        $layerId = (string) Str::uuid();

        $this->insertPurchaseLedgerRow($purchaseId, $firstPurchaseItemId, 2, 7000, $receivedAt, 2);
        $this->insertPurchaseLedgerRow($purchaseId, $secondPurchaseItemId, 2, 7000, $receivedAt, 4);

        $this->insertLegacyPurchaseLayer($layerId, $purchaseId, 2, 2, 7000, $receivedAt);

        $service = app(PurchaseHistoryBackfillService::class);
        $result = $service->backfillPurchaseLayerSourceItems($this->tenant->id, true);

        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['summary']['legacy_ambiguous']);

        $this->assertDatabaseHas('inventory_layers', [
            'tenant_id' => $this->tenant->id,
            'id' => $layerId,
            'source_item_id' => null,
        ]);

        $this->assertSame(1, \DB::table('valuation_audit_logs')
            ->where('tenant_id', $this->tenant->id)
            ->where('action', 'valuation_purchase_layer_backfill_legacy_ambiguous')
            ->count());

        $this->assertSame(1, \DB::table('anomalies')
            ->where('tenant_id', $this->tenant->id)
            ->where('type', 'valuation_purchase_layer_backfill_legacy_ambiguous')
            ->count());
    }

    public function test_purchase_return_consumes_backfilled_purchase_layer_by_purchase_item(): void
    {
        $purchase = $this->seedPurchaseGraph('2026-04-05 10:00:00', 5, 7000);

        app(PurchaseHistoryBackfillService::class)->backfillPurchaseLayerSourceItems($this->tenant->id, true);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchase['purchase_id'],
                'supplier_id' => $purchase['supplier_id'],
                'items' => [
                    [
                        'purchase_item_id' => $purchase['purchase_item_id'],
                        'qty' => 2,
                        'price' => 7000,
                    ],
                ],
            ]);

        $response->assertCreated();

        $purchaseReturnId = (string) $response->json('purchase_return_id');
        $purchaseReturnItemId = (string) \DB::table('purchase_return_items')
            ->where('tenant_id', $this->tenant->id)
            ->where('purchase_return_id', $purchaseReturnId)
            ->value('id');

        $this->assertDatabaseHas('purchase_return_items', [
            'tenant_id' => $this->tenant->id,
            'id' => $purchaseReturnItemId,
            'purchase_item_id' => $purchase['purchase_item_id'],
        ]);

        $allocation = InventoryIssueAllocation::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('reference_type', 'purchase_return')
            ->where('reference_item_id', $purchaseReturnItemId)
            ->first();

        $this->assertNotNull($allocation);
        $this->assertSame($purchase['layer_id'], $allocation->inventory_layer_id);
        $this->assertSame(2.0, (float) $allocation->qty);

        $this->assertDatabaseHas('inventory_layers', [
            'tenant_id' => $this->tenant->id,
            'id' => $purchase['layer_id'],
            'source_item_id' => $purchase['purchase_item_id'],
            'qty_remaining' => 3,
        ]);
    }

    public function test_purchase_return_fails_fast_when_legacy_purchase_layer_is_not_safely_mapped(): void
    {
        $supplierId = $this->insertSupplier();
        $purchaseId = $this->insertPurchase($supplierId, '2026-04-05 11:00:00');
        $purchaseItemId = $this->insertPurchaseItem($purchaseId, 5, 7000);
        $receivedAt = '2026-04-05 11:15:00';

        $this->insertPurchaseLedgerRow($purchaseId, $purchaseItemId, 5, 7000, $receivedAt, 5);
        $this->insertLegacyPurchaseLayer((string) Str::uuid(), $purchaseId, 5, 5, 7000, $receivedAt);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchaseId,
                'supplier_id' => $supplierId,
                'items' => [
                    [
                        'purchase_item_id' => $purchaseItemId,
                        'qty' => 1,
                        'price' => 7000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'PURCHASE_RETURN_FAILED',
            ]);

        $this->assertStringContainsString('legacy belum dibackfill', (string) $response->json('message'));

        $this->assertSame(1, \DB::table('valuation_audit_logs')
            ->where('tenant_id', $this->tenant->id)
            ->where('action', 'purchase_return_fifo_legacy_source_item_unmapped')
            ->count());

        $this->assertSame(1, \DB::table('anomalies')
            ->where('tenant_id', $this->tenant->id)
            ->where('type', 'purchase_return_fifo_legacy_source_item_unmapped')
            ->count());
    }

    public function test_replay_resolves_legacy_purchase_row_and_rebuilds_layer_source_item_id(): void
    {
        $supplierId = $this->insertSupplier();
        $purchaseId = $this->insertPurchase($supplierId, '2026-04-05 12:00:00');
        $purchaseItemId = $this->insertPurchaseItem($purchaseId, 4, 7100);

        \DB::table('stock_ledger')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $this->fixture['location_id'],
            'reference_type' => 'purchase',
            'reference_id' => $purchaseId,
            'reference_item_id' => null,
            'qty_in' => 4,
            'qty_out' => 0,
            'balance_after' => 4,
            'unit_cost' => 7100,
            'total_cost' => 28400,
            'created_at' => '2026-04-05 12:15:00',
            'created_by' => $this->user->id,
        ]);

        app(InventoryValuationService::class)->replayFromLedger($this->tenant->id);

        $layer = InventoryLayer::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('source_type', 'purchase')
            ->where('source_id', $purchaseId)
            ->first();

        $this->assertNotNull($layer);
        $this->assertSame($purchaseItemId, $layer->source_item_id);

        $this->assertSame(1, \DB::table('valuation_audit_logs')
            ->where('tenant_id', $this->tenant->id)
            ->where('action', 'valuation_replay_legacy_purchase_item_resolved')
            ->count());
    }

    private function seedPurchaseGraph(string $createdAt, float $qty, float $unitCost): array
    {
        $supplierId = $this->insertSupplier();
        $purchaseId = $this->insertPurchase($supplierId, $createdAt);
        $purchaseItemId = $this->insertPurchaseItem($purchaseId, $qty, $unitCost);
        $receivedAt = date('Y-m-d H:i:s', strtotime($createdAt.' +15 minutes'));
        $layerId = (string) Str::uuid();

        $this->insertPurchaseLedgerRow($purchaseId, $purchaseItemId, $qty, $unitCost, $receivedAt, $qty);
        $this->insertLegacyPurchaseLayer($layerId, $purchaseId, $qty, $qty, $unitCost, $receivedAt);

        return [
            'supplier_id' => $supplierId,
            'purchase_id' => $purchaseId,
            'purchase_item_id' => $purchaseItemId,
            'layer_id' => $layerId,
        ];
    }

    private function insertSupplier(): string
    {
        $supplierId = (string) Str::uuid();

        \DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $this->tenant->id,
            'name' => 'Supplier Legacy Purchase',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        return $supplierId;
    }

    private function insertPurchase(string $supplierId, string $createdAt): string
    {
        $purchaseId = (string) Str::uuid();

        \DB::table('purchases')->insert([
            'id' => $purchaseId,
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplierId,
            'location_id' => $this->fixture['location_id'],
            'total' => 50000,
            'status' => 'received',
            'notes' => null,
            'created_at' => $createdAt,
            'approved_at' => $createdAt,
            'approved_by' => $this->user->id,
            'received_at' => $createdAt,
            'received_by' => $this->user->id,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'transaction_date' => substr($createdAt, 0, 10),
        ]);

        return $purchaseId;
    }

    private function insertPurchaseItem(string $purchaseId, float $qty, float $unitCost): string
    {
        $purchaseItemId = (string) Str::uuid();

        \DB::table('purchase_items')->insert([
            'id' => $purchaseItemId,
            'tenant_id' => $this->tenant->id,
            'purchase_id' => $purchaseId,
            'product_id' => $this->fixture['product_id'],
            'product_variant_id' => null,
            'qty' => $qty,
            'received_qty' => $qty,
            'price' => $unitCost,
        ]);

        return $purchaseItemId;
    }

    private function insertPurchaseLedgerRow(string $purchaseId, string $purchaseItemId, float $qty, float $unitCost, string $createdAt, float $balanceAfter): void
    {
        \DB::table('stock_ledger')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $this->fixture['location_id'],
            'reference_type' => 'purchase',
            'reference_id' => $purchaseId,
            'reference_item_id' => $purchaseItemId,
            'qty_in' => $qty,
            'qty_out' => 0,
            'balance_after' => $balanceAfter,
            'unit_cost' => $unitCost,
            'total_cost' => $qty * $unitCost,
            'created_at' => $createdAt,
            'created_by' => $this->user->id,
        ]);
    }

    private function insertLegacyPurchaseLayer(string $layerId, string $purchaseId, float $qtyOriginal, float $qtyRemaining, float $unitCost, string $receivedAt): void
    {
        \DB::table('inventory_layers')->insert([
            'id' => $layerId,
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $this->fixture['location_id'],
            'qty_remaining' => $qtyRemaining,
            'qty_original' => $qtyOriginal,
            'cost_per_unit' => $unitCost,
            'source_type' => 'purchase',
            'source_id' => $purchaseId,
            'source_item_id' => null,
            'received_at' => $receivedAt,
            'created_at' => $receivedAt,
        ]);

        \DB::table('inventory_balances')->updateOrInsert(
            [
                'tenant_id' => $this->tenant->id,
                'product_id' => $this->fixture['product_id'],
                'product_variant_id' => null,
                'location_id' => $this->fixture['location_id'],
            ],
            [
                'qty_on_hand' => $qtyRemaining,
            ]
        );

        $existingStateId = \DB::table('inventory_cost_states')
            ->where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->fixture['product_id'])
            ->whereNull('product_variant_id')
            ->where('location_id', $this->fixture['location_id'])
            ->value('id');

        if ($existingStateId) {
            \DB::table('inventory_cost_states')
                ->where('id', $existingStateId)
                ->update([
                    'qty_on_hand' => $qtyRemaining,
                    'average_cost' => $unitCost,
                    'last_cost' => $unitCost,
                    'updated_at' => $receivedAt,
                ]);
        } else {
            \DB::table('inventory_cost_states')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'product_id' => $this->fixture['product_id'],
                'product_variant_id' => null,
                'location_id' => $this->fixture['location_id'],
                'qty_on_hand' => $qtyRemaining,
                'average_cost' => $unitCost,
                'last_cost' => $unitCost,
                'updated_at' => $receivedAt,
            ]);
        }
    }
}