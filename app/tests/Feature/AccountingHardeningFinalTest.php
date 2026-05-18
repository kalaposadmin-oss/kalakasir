<?php

namespace Tests\Feature;

use App\Services\CashTransactionService;
use App\Services\TenantDefaultAccountingSetupService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class AccountingHardeningFinalTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_inventory_adjustment_post_keeps_inventory_and_gl_in_sync(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+inventory-gl-sync@example.test');
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $branchId = (string) Str::uuid();
        $locationId = (string) Str::uuid();
        $productId = (string) Str::uuid();

        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenant->id,
            'name' => 'Cabang Sinkron',
        ]);

        DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'name' => 'Gudang Sinkron',
            'is_default' => true,
        ]);

        DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $tenant->id,
            'sku' => 'SYNC-INV-001',
            'name' => 'Produk Sinkron',
            'price' => 25000,
            'hpp_average' => 10000,
            'is_active' => true,
            'track_stock' => true,
            'product_mode' => 'default',
            'item_type' => 'finished_good',
            'variants' => json_encode([]),
            'bom_items' => json_encode([]),
            'bundle_items' => json_encode([]),
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenant->id,
            'location_id' => $locationId,
            'product_id' => $productId,
            'qty_on_hand' => 10,
        ]);

        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $productId,
            'location_id' => $locationId,
            'qty_on_hand' => 10,
            'average_cost' => 10000,
            'last_cost' => 10000,
            'updated_at' => now('UTC'),
        ]);

        $create = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/stock-adjustments', [
                'location_id' => $locationId,
                'reason_code' => 'A02',
                'notes' => 'Penyesuaian akhir hari untuk sinkronisasi GL',
                'items' => [
                    [
                        'product_id' => $productId,
                        'physical_qty' => 7,
                    ],
                ],
            ]);

        $create->assertCreated();
        $adjustmentId = (string) $create->json('adjustment_id');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/stock-adjustments/'.$adjustmentId.'/approve')
            ->assertOk();

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/stock-adjustments/'.$adjustmentId.'/post', [
                'transaction_date' => '2026-02-26',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'posted');

        $mapping = DB::table('account_mappings')->where('tenant_id', $tenant->id)->first();

        $entryId = (string) DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'stock_adjustment')
            ->where('reference_id', $adjustmentId)
            ->value('id');

        $this->assertNotSame('', $entryId);

        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'journal_entry_id' => $entryId,
            'account_id' => $mapping->expense_adjustment_account_id,
            'debit' => 30000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'journal_entry_id' => $entryId,
            'account_id' => $mapping->inventory_account_id,
            'debit' => 0,
            'credit' => 30000,
        ]);

        $this->assertSame(
            7.0,
            (float) DB::table('inventory_balances')
                ->where('tenant_id', $tenant->id)
                ->where('location_id', $locationId)
                ->where('product_id', $productId)
                ->value('qty_on_hand')
        );
    }

    public function test_cash_flow_report_is_consistent_with_gl_cash_movements(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+cash-flow-sync@example.test');
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $cashAccountId = (string) DB::table('account_mappings')
            ->where('tenant_id', $tenant->id)
            ->value('cash_account_id');

        $service = app(CashTransactionService::class);
        $service->store($tenant->id, 'cash-in', [
            'transaction_date' => '2026-02-26',
            'amount' => 100000,
            'account_id' => $cashAccountId,
            'category' => 'capital_injection',
            'description' => 'Modal awal',
        ], $user);
        $service->store($tenant->id, 'cash-out', [
            'transaction_date' => '2026-02-26',
            'amount' => 40000,
            'account_id' => $cashAccountId,
            'category' => 'operational_expense',
            'description' => 'Biaya operasional',
        ], $user);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-cash-flow?preset=custom&start_date=2026-02-26&end_date=2026-02-26');

        $response->assertOk()
            ->assertJsonPath('summary.opening_cash', 0)
            ->assertJsonPath('summary.net_change', 60000)
            ->assertJsonPath('summary.closing_cash', 60000)
            ->assertJsonPath('period.basis', 'journal_entries.date');

        $mapping = DB::table('account_mappings')->where('tenant_id', $tenant->id)->first();
        $cashGlNet = (float) DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenant->id)
            ->where('je.status', 'posted')
            ->whereBetween('je.date', ['2026-02-26', '2026-02-26'])
            ->whereIn('ji.account_id', [(string) $mapping->cash_account_id, (string) $mapping->bank_account_id])
            ->selectRaw('COALESCE(SUM(ji.debit - ji.credit), 0) as movement')
            ->value('movement');

        $this->assertEqualsWithDelta(60000.0, $cashGlNet, 0.01);
        $this->assertEqualsWithDelta(60000.0, (float) $response->json('summary.net_change'), 0.01);
    }

    public function test_account_mapping_update_is_configurable_and_type_safe(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+mapping-configurable@example.test');
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, null);

        $accountsByCode = DB::table('chart_of_accounts')
            ->where('tenant_id', $tenant->id)
            ->pluck('id', 'code');

        $invalid = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/api/v1/chart-of-accounts/mapping', [
                'sale_account_id' => (string) $accountsByCode['1101'],
                'cash_account_id' => (string) $accountsByCode['1101'],
                'bank_account_id' => (string) $accountsByCode['1102'],
                'inventory_account_id' => (string) $accountsByCode['1301'],
                'cogs_account_id' => (string) $accountsByCode['5101'],
                'payable_account_id' => (string) $accountsByCode['2101'],
                'receivable_account_id' => (string) $accountsByCode['1201'],
                'expense_adjustment_account_id' => (string) $accountsByCode['6101'],
            ]);

        $invalid->assertStatus(422)
            ->assertJsonValidationErrors(['sale_account_id']);

        $valid = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/api/v1/chart-of-accounts/mapping', [
                'sale_account_id' => (string) $accountsByCode['4101'],
                'cash_account_id' => (string) $accountsByCode['1101'],
                'bank_account_id' => (string) $accountsByCode['1102'],
                'inventory_account_id' => (string) $accountsByCode['1301'],
                'cogs_account_id' => (string) $accountsByCode['5101'],
                'payable_account_id' => (string) $accountsByCode['2101'],
                'receivable_account_id' => (string) $accountsByCode['1201'],
                'expense_adjustment_account_id' => (string) $accountsByCode['6101'],
            ]);

        $valid->assertOk()
            ->assertJsonPath('mapping.sale_account_id', (string) $accountsByCode['4101'])
            ->assertJsonPath('mapping.cash_account_id', (string) $accountsByCode['1101'])
            ->assertJsonPath('mapping.inventory_account_id', (string) $accountsByCode['1301']);

        $this->assertDatabaseHas('account_mappings', [
            'tenant_id' => $tenant->id,
            'sale_account_id' => (string) $accountsByCode['4101'],
            'cash_account_id' => (string) $accountsByCode['1101'],
            'bank_account_id' => (string) $accountsByCode['1102'],
            'inventory_account_id' => (string) $accountsByCode['1301'],
            'cogs_account_id' => (string) $accountsByCode['5101'],
            'payable_account_id' => (string) $accountsByCode['2101'],
            'receivable_account_id' => (string) $accountsByCode['1201'],
            'expense_adjustment_account_id' => (string) $accountsByCode['6101'],
        ]);
    }
}
