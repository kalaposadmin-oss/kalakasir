<?php

namespace Tests\Feature;

use App\Services\TenantDefaultAccountingSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class StockOpnameFlowTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_create_draft_stock_opname_snapshots_system_stock(): void
    {
        $fixture = $this->seedInventoryFixture();

        $opnameId = $this->createDraft($fixture);

        $this->assertDatabaseHas('stock_opnames', [
            'tenant_id' => $fixture['tenant']->id,
            'id' => $opnameId,
            'status' => 'draft',
        ]);
        $this->assertSame(2, DB::table('stock_opname_items')->where('stock_opname_id', $opnameId)->count());
        $this->assertEquals(10.0, (float) $this->opnameItem($opnameId, $fixture['product_a'])->system_qty);
    }

    public function test_update_physical_qty_calculates_difference(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $item = $this->opnameItem($opnameId, $fixture['product_a']);

        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->patchJson("/api/v1/stock-opnames/{$opnameId}/items/{$item->id}", [
                'physical_qty' => 8,
                'notes' => 'Rak depan kurang dua',
            ])
            ->assertOk()
            ->assertJsonPath('item.difference_qty', -2)
            ->assertJsonPath('item.difference_value', -20000);
    }

    public function test_large_difference_requires_item_note(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $item = $this->opnameItem($opnameId, $fixture['product_a']);

        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->patchJson("/api/v1/stock-opnames/{$opnameId}/items/{$item->id}", [
                'physical_qty' => 13,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'STOCK_OPNAME_ITEM_UPDATE_FAILED');
    }

    public function test_finalize_plus_difference_increases_stock(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 13);

        $this->finalize($fixture, $opnameId, true);

        $this->assertEquals(13.0, $this->balanceQty($fixture, $fixture['product_a']));
        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $fixture['tenant']->id,
            'reference_type' => 'stock_opname',
            'reference_id' => $opnameId,
            'product_id' => $fixture['product_a'],
            'qty_in' => 3,
            'qty_out' => 0,
            'unit_cost' => 10000,
            'total_cost' => 30000,
        ]);
    }

    public function test_finalize_minus_difference_decreases_stock(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 8);

        $this->finalize($fixture, $opnameId, true);

        $this->assertEquals(8.0, $this->balanceQty($fixture, $fixture['product_a']));
        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $fixture['tenant']->id,
            'reference_type' => 'stock_opname',
            'reference_id' => $opnameId,
            'product_id' => $fixture['product_a'],
            'qty_in' => 0,
            'qty_out' => 2,
            'unit_cost' => 10000,
            'total_cost' => 20000,
        ]);
    }

    public function test_finalize_mixed_plus_minus_posts_all_movements(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 12);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_b'], 4);

        $this->finalize($fixture, $opnameId, true);

        $this->assertEquals(12.0, $this->balanceQty($fixture, $fixture['product_a']));
        $this->assertEquals(4.0, $this->balanceQty($fixture, $fixture['product_b']));
        $this->assertSame(2, DB::table('stock_ledger')->where('reference_type', 'stock_opname')->where('reference_id', $opnameId)->count());
    }

    public function test_cannot_finalize_twice(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 13);
        $this->finalize($fixture, $opnameId, true);

        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->postJson("/api/v1/stock-opnames/{$opnameId}/finalize")
            ->assertStatus(422)
            ->assertJsonPath('code', 'STOCK_OPNAME_FINALIZE_FAILED');
    }

    public function test_cannot_edit_after_finalized(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 13);
        $this->finalize($fixture, $opnameId, true);
        $item = $this->opnameItem($opnameId, $fixture['product_a']);

        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->patchJson("/api/v1/stock-opnames/{$opnameId}/items/{$item->id}", [
                'physical_qty' => 14,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'STOCK_OPNAME_ITEM_UPDATE_FAILED');
    }

    public function test_ledger_is_filled_with_cost(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 13);
        $this->finalize($fixture, $opnameId, true);

        $ledger = DB::table('stock_ledger')
            ->where('tenant_id', $fixture['tenant']->id)
            ->where('reference_type', 'stock_opname')
            ->where('reference_id', $opnameId)
            ->first();

        $this->assertNotNull($ledger);
        $this->assertEquals(10000.0, (float) $ledger->unit_cost);
        $this->assertEquals(30000.0, (float) $ledger->total_cost);
    }

    public function test_inventory_balance_matches_final_physical_qty(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 7);

        $this->finalize($fixture, $opnameId, true);

        $this->assertEquals(7.0, $this->balanceQty($fixture, $fixture['product_a']));
        $this->assertEquals(7.0, (float) DB::table('inventory_cost_states')
            ->where('tenant_id', $fixture['tenant']->id)
            ->where('location_id', $fixture['location_id'])
            ->where('product_id', $fixture['product_a'])
            ->value('qty_on_hand'));
    }

    public function test_journal_accounting_is_created(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 8);

        $this->finalize($fixture, $opnameId, true);

        $mapping = DB::table('account_mappings')->where('tenant_id', $fixture['tenant']->id)->first();
        $entryId = DB::table('journal_entries')
            ->where('tenant_id', $fixture['tenant']->id)
            ->where('reference_type', 'stock_opname')
            ->where('reference_id', $opnameId)
            ->value('id');

        $this->assertNotNull($entryId);
        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $fixture['tenant']->id,
            'journal_entry_id' => $entryId,
            'account_id' => $mapping->expense_adjustment_account_id,
            'debit' => 20000,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $fixture['tenant']->id,
            'journal_entry_id' => $entryId,
            'account_id' => $mapping->inventory_account_id,
            'debit' => 0,
            'credit' => 20000,
        ]);
    }

    public function test_finalize_fails_when_account_mapping_missing(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 8);

        DB::table('account_mappings')
            ->where('tenant_id', $fixture['tenant']->id)
            ->delete();

        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->postJson("/api/v1/stock-opnames/{$opnameId}/finalize")
            ->assertStatus(422)
            ->assertJsonPath('code', 'STOCK_OPNAME_FINALIZE_FAILED');

        $this->assertDatabaseHas('stock_opnames', [
            'tenant_id' => $fixture['tenant']->id,
            'id' => $opnameId,
            'status' => 'draft',
        ]);
        $this->assertSame(0, DB::table('stock_ledger')->where('reference_id', $opnameId)->count());
    }

    public function test_finalize_check_detects_stock_changes(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 8);

        DB::table('inventory_balances')
            ->where('tenant_id', $fixture['tenant']->id)
            ->where('location_id', $fixture['location_id'])
            ->where('product_id', $fixture['product_a'])
            ->update(['qty_on_hand' => 12]);

        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->getJson("/api/v1/stock-opnames/{$opnameId}/finalize-check")
            ->assertOk()
            ->assertJsonPath('changed_count', 1)
            ->assertJsonPath('changed_items.0.snapshot_qty', 10)
            ->assertJsonPath('changed_items.0.current_qty', 12);
    }

    public function test_finalize_check_pagination_metadata(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 8);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_b'], 4);

        DB::table('inventory_balances')
            ->where('tenant_id', $fixture['tenant']->id)
            ->where('location_id', $fixture['location_id'])
            ->where('product_id', $fixture['product_a'])
            ->update(['qty_on_hand' => 12]);
        DB::table('inventory_balances')
            ->where('tenant_id', $fixture['tenant']->id)
            ->where('location_id', $fixture['location_id'])
            ->where('product_id', $fixture['product_b'])
            ->update(['qty_on_hand' => 6]);

        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->getJson("/api/v1/stock-opnames/{$opnameId}/finalize-check?page=1&per_page=1")
            ->assertOk()
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('has_more', true)
            ->assertJsonCount(1, 'changed_items');

        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->getJson("/api/v1/stock-opnames/{$opnameId}/finalize-check?page=2&per_page=1")
            ->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('has_more', false)
            ->assertJsonCount(1, 'changed_items');
    }

    public function test_finalize_check_caps_per_page_maximum(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 8);

        DB::table('inventory_balances')
            ->where('tenant_id', $fixture['tenant']->id)
            ->where('location_id', $fixture['location_id'])
            ->where('product_id', $fixture['product_a'])
            ->update(['qty_on_hand' => 12]);

        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->getJson("/api/v1/stock-opnames/{$opnameId}/finalize-check?per_page=500")
            ->assertOk()
            ->assertJsonPath('per_page', 100);
    }

    public function test_finalize_rejects_partial_without_confirmation(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 8);

        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->postJson("/api/v1/stock-opnames/{$opnameId}/finalize")
            ->assertStatus(422)
            ->assertJsonPath('code', 'STOCK_OPNAME_FINALIZE_FAILED');

        $this->assertDatabaseHas('stock_opnames', [
            'tenant_id' => $fixture['tenant']->id,
            'id' => $opnameId,
            'status' => 'draft',
        ]);
    }

    public function test_finalize_allows_partial_with_confirmation(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 8);

        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->postJson("/api/v1/stock-opnames/{$opnameId}/finalize", [
                'allow_partial' => true,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'finalized');

        $this->assertSame(1, DB::table('stock_ledger')->where('reference_id', $opnameId)->count());
    }

    public function test_unauthorized_role_is_rejected(): void
    {
        $fixture = $this->seedInventoryFixture('cashier', 'cashier+stock-opname@example.test');

        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->postJson('/api/v1/stock-opnames', [
                'location_id' => $fixture['location_id'],
                'opname_date' => '2026-02-27',
            ])
            ->assertForbidden();
    }

    public function test_stock_change_between_draft_and_finalize_is_recalculated(): void
    {
        $fixture = $this->seedInventoryFixture();
        $opnameId = $this->createDraft($fixture);
        $this->updatePhysicalQty($fixture, $opnameId, $fixture['product_a'], 8);

        DB::table('inventory_balances')
            ->where('tenant_id', $fixture['tenant']->id)
            ->where('location_id', $fixture['location_id'])
            ->where('product_id', $fixture['product_a'])
            ->update(['qty_on_hand' => 12]);
        DB::table('inventory_cost_states')
            ->where('tenant_id', $fixture['tenant']->id)
            ->where('location_id', $fixture['location_id'])
            ->where('product_id', $fixture['product_a'])
            ->update(['qty_on_hand' => 12]);

        $this->finalize($fixture, $opnameId, true);

        $item = $this->opnameItem($opnameId, $fixture['product_a']);
        $this->assertEquals(12.0, (float) $item->system_qty);
        $this->assertEquals(-4.0, (float) $item->difference_qty);
        $this->assertEquals(8.0, $this->balanceQty($fixture, $fixture['product_a']));
        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $fixture['tenant']->id,
            'reference_type' => 'stock_opname',
            'reference_id' => $opnameId,
            'product_id' => $fixture['product_a'],
            'qty_out' => 4,
            'total_cost' => 40000,
        ]);
    }

    public function test_stock_adjustment_ledger_keeps_cost_after_fix(): void
    {
        $fixture = $this->seedInventoryFixture();

        $create = $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->postJson('/api/v1/stock-adjustments', [
                'location_id' => $fixture['location_id'],
                'reason_code' => 'A02',
                'notes' => 'Penyesuaian manual dengan nilai cost',
                'items' => [
                    [
                        'product_id' => $fixture['product_a'],
                        'physical_qty' => 8,
                    ],
                ],
            ])
            ->assertCreated();

        $adjustmentId = (string) $create->json('adjustment_id');
        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->postJson("/api/v1/stock-adjustments/{$adjustmentId}/approve")
            ->assertOk();
        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->postJson("/api/v1/stock-adjustments/{$adjustmentId}/post", ['transaction_date' => '2026-02-27'])
            ->assertOk();

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $fixture['tenant']->id,
            'reference_type' => 'stock_adjustment',
            'reference_id' => $adjustmentId,
            'unit_cost' => 10000,
            'total_cost' => 20000,
        ]);
    }

    private function seedInventoryFixture(string $role = 'admin', string $email = 'admin+stock-opname@example.test'): array
    {
        [$tenant, $user] = $this->bootApiActor($role, $email);
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $branchId = (string) Str::uuid();
        $locationId = (string) Str::uuid();
        $productA = (string) Str::uuid();
        $productB = (string) Str::uuid();

        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenant->id,
            'name' => 'Cabang Opname',
        ]);
        DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'name' => 'Gudang Opname',
            'is_default' => true,
        ]);

        $this->insertProduct($tenant->id, $user->id, $productA, 'OPN-A', 'Produk Opname A', 10000);
        $this->insertProduct($tenant->id, $user->id, $productB, 'OPN-B', 'Produk Opname B', 5000);
        $this->insertInventory($tenant->id, $locationId, $productA, 10, 10000);
        $this->insertInventory($tenant->id, $locationId, $productB, 5, 5000);

        return [
            'tenant' => $tenant,
            'user' => $user,
            'location_id' => $locationId,
            'product_a' => $productA,
            'product_b' => $productB,
        ];
    }

    private function insertProduct(string $tenantId, string $userId, string $productId, string $sku, string $name, float $cost): void
    {
        DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $tenantId,
            'sku' => $sku,
            'name' => $name,
            'price' => $cost * 2,
            'hpp_average' => $cost,
            'is_active' => true,
            'track_stock' => true,
            'product_mode' => 'default',
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
    }

    private function insertInventory(string $tenantId, string $locationId, string $productId, float $qty, float $cost): void
    {
        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenantId,
            'location_id' => $locationId,
            'product_id' => $productId,
            'qty_on_hand' => $qty,
        ]);
        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'location_id' => $locationId,
            'qty_on_hand' => $qty,
            'average_cost' => $cost,
            'last_cost' => $cost,
            'updated_at' => now('UTC'),
        ]);
    }

    private function createDraft(array $fixture): string
    {
        $response = $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->postJson('/api/v1/stock-opnames', [
                'location_id' => $fixture['location_id'],
                'opname_date' => '2026-02-27',
                'notes' => 'Opname harian',
            ])
            ->assertCreated();

        return (string) $response->json('opname_id');
    }

    private function updatePhysicalQty(array $fixture, string $opnameId, string $productId, float $physicalQty): void
    {
        $item = $this->opnameItem($opnameId, $productId);
        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->patchJson("/api/v1/stock-opnames/{$opnameId}/items/{$item->id}", [
                'physical_qty' => $physicalQty,
                'notes' => 'Dihitung test',
            ])
            ->assertOk();
    }

    private function finalize(array $fixture, string $opnameId, bool $allowPartial = false): void
    {
        $payload = $allowPartial ? ['allow_partial' => true] : [];
        $this->withHeaders($this->tenantHeaders($fixture['tenant']->id))
            ->postJson("/api/v1/stock-opnames/{$opnameId}/finalize", $payload)
            ->assertOk()
            ->assertJsonPath('status', 'finalized');
    }

    private function opnameItem(string $opnameId, string $productId): object
    {
        return DB::table('stock_opname_items')
            ->where('stock_opname_id', $opnameId)
            ->where('product_id', $productId)
            ->first();
    }

    private function balanceQty(array $fixture, string $productId): float
    {
        return (float) DB::table('inventory_balances')
            ->where('tenant_id', $fixture['tenant']->id)
            ->where('location_id', $fixture['location_id'])
            ->where('product_id', $productId)
            ->value('qty_on_hand');
    }
}
