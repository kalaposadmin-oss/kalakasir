<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\DailyFinanceControlService;
use App\Services\DailySalesSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class SalesCostAggregationValidationTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_checkout_bom_sale_writes_aggregated_cost_and_journal_uses_cogs_total(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+bom-cost-aggregation@example.test');
        $ctx = $this->prepareBomCheckoutContext($tenant->id, $user->id);

        $response = $this->postJson('/api/v1/checkout', $ctx['payload'], $this->tenantHeaders($tenant->id));

        $response->assertCreated();

        $saleId = (string) $response->json('sale_id');
        $saleItem = DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->first();

        $this->assertNotNull($saleItem);
        $this->assertSame(5000.0, (float) $saleItem->hpp_snapshot);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItem->id,
            'product_id' => $ctx['raw_material_id'],
            'qty_out' => 2.0,
        ]);

        $this->assertSame(2, DB::table('sale_cost_components')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->count());

        $this->assertDatabaseHas('sale_cost_components', [
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'sale_item_id' => $saleItem->id,
            'component_type' => 'sale_item',
            'total_cost' => 5000.0,
        ]);
        $this->assertDatabaseHas('sale_cost_components', [
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'sale_item_id' => $saleItem->id,
            'component_type' => 'recipe_input',
            'total_cost' => 3000.0,
        ]);

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'cogs_total' => 8000.0,
        ]);

        $journalEntryId = (string) DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->value('id');

        $this->assertNotSame('', $journalEntryId);
        $mapping = DB::table('account_mappings')->where('tenant_id', $tenant->id)->first();

        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'journal_entry_id' => $journalEntryId,
            'account_id' => $mapping->cogs_account_id,
            'debit' => 8000.0,
            'credit' => 0.0,
            'is_reversal' => false,
        ]);
        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'journal_entry_id' => $journalEntryId,
            'account_id' => $mapping->inventory_account_id,
            'debit' => 0.0,
            'credit' => 8000.0,
            'is_reversal' => false,
        ]);
    }

    public function test_checkout_non_bom_sale_writes_base_component_only_and_keeps_snapshot(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+non-bom-cost-aggregation@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $response = $this->postJson(
            '/api/v1/checkout',
            $this->checkoutPayload($fixture, now('UTC')->toDateString()),
            $this->tenantHeaders($tenant->id)
        );

        $response->assertCreated();

        $saleId = (string) $response->json('sale_id');
        $saleItem = DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->first();

        $this->assertNotNull($saleItem);
        $this->assertSame(5000.0, (float) $saleItem->hpp_snapshot);

        $this->assertSame(0, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale_recipe_input')
            ->where('reference_id', $saleId)
            ->count());

        $this->assertSame(1, DB::table('sale_cost_components')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->count());
        $this->assertDatabaseHas('sale_cost_components', [
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'sale_item_id' => $saleItem->id,
            'component_type' => 'sale_item',
            'total_cost' => 5000.0,
        ]);

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'cogs_total' => 5000.0,
        ]);
    }

    public function test_readers_prefer_aggregated_cost_over_snapshot_without_double_counting(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+reader-aggregate-first@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $createdAt = CarbonImmutable::parse('2026-04-10 09:00:00', 'UTC');
        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 15000,
            'discount' => 0,
            'tax' => 0,
            'total' => 15000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'created_at' => $createdAt,
            'transaction_date' => '2026-04-10',
            'cogs_total' => 9000,
        ]);

        DB::table('sale_items')->insert([
            'id' => $saleItemId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 2,
            'price' => 7500,
            'discount' => 0,
            'hpp_snapshot' => 2500,
        ]);

        DB::table('sale_cost_components')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleId,
                'sale_item_id' => $saleItemId,
                'component_type' => 'sale_item',
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty' => 2,
                'unit_cost' => 4500,
                'total_cost' => 9000,
                'source_reference_type' => 'sale',
                'source_reference_id' => $saleId,
                'source_reference_item_id' => $saleItemId,
                'source' => 'repair',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ],
        ]);

        DB::table('payments')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'account_id' => $fixture['cash_account_id'],
            'method' => 'cash',
            'amount' => 15000,
            'payment_method_id' => $fixture['payment_method_id'],
        ]);

        $financeResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date=2026-04-10&end_date=2026-04-10');

        $financeResponse->assertOk()
            ->assertJsonPath('summary.expenses', 9000)
            ->assertJsonPath('summary.net_profit', 6000);

        $dailyFinance = app(DailyFinanceControlService::class)->calculate($tenant->id, '2026-04-10');
        $this->assertSame(9000.0, (float) $dailyFinance['cogs_total']);

        app(DailySalesSummaryService::class)->applySale($tenant->id, $saleId);
        $this->assertDatabaseHas('daily_sales_summaries', [
            'tenant_id' => $tenant->id,
            'date' => '2026-04-10',
            'total_cost' => 9000.0,
        ]);

        $analyticsResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/analytics?preset=custom&start_date=2026-04-10&end_date=2026-04-10');

        $analyticsResponse->assertOk()
            ->assertJsonPath('summary.revenue', 15000)
            ->assertJsonPath('summary.profit', 6000)
            ->assertJsonPath('daily.0.profit', 6000);
    }

    public function test_rebuild_command_backfills_historical_bom_sale_and_resyncs_journal(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+rebuild-sales-cost@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $createdAt = CarbonImmutable::parse('2026-04-09 10:00:00', 'UTC');
        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();
        $journalEntryId = (string) Str::uuid();
        $rawMaterialId = (string) Str::uuid();
        $mapping = DB::table('account_mappings')->where('tenant_id', $tenant->id)->firstOrFail();

        DB::table('products')->insert([
            'id' => $rawMaterialId,
            'tenant_id' => $tenant->id,
            'sku' => 'RM-HIST-001',
            'name' => 'Bahan Histori',
            'price' => 1500,
            'hpp_average' => 1500,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'product_mode' => 'add_on',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'raw_material',
            'variants' => json_encode([]),
            'bom_items' => json_encode([]),
            'bundle_items' => json_encode([]),
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'deleted_at' => null,
        ]);

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 15000,
            'discount' => 0,
            'tax' => 0,
            'total' => 15000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'created_at' => $createdAt,
            'transaction_date' => '2026-04-09',
            'cogs_total' => 0,
        ]);

        DB::table('sale_items')->insert([
            'id' => $saleItemId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 1,
            'price' => 15000,
            'discount' => 0,
            'hpp_snapshot' => 5000,
        ]);

        DB::table('stock_ledger')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 0,
            'qty_out' => 2,
            'balance_after' => 8,
            'unit_cost' => 1500,
            'total_cost' => 3000,
            'created_at' => $createdAt,
            'created_by' => $user->id,
        ]);

        DB::table('payments')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'account_id' => $fixture['cash_account_id'],
            'method' => 'cash',
            'amount' => 15000,
            'payment_method_id' => $fixture['payment_method_id'],
        ]);

        DB::table('journal_entries')->insert([
            'id' => $journalEntryId,
            'tenant_id' => $tenant->id,
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'date' => '2026-04-09',
            'description' => 'POS sale',
            'status' => 'posted',
            'posted_at' => $createdAt,
            'created_by' => $user->id,
            'created_at' => $createdAt,
        ]);

        DB::table('journal_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenant->id,
                'account_id' => $mapping->cash_account_id,
                'debit' => 15000,
                'credit' => 0,
                'is_reversal' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenant->id,
                'account_id' => $mapping->sale_account_id,
                'debit' => 0,
                'credit' => 15000,
                'is_reversal' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenant->id,
                'account_id' => $mapping->cogs_account_id,
                'debit' => 5000,
                'credit' => 0,
                'is_reversal' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenant->id,
                'account_id' => $mapping->inventory_account_id,
                'debit' => 0,
                'credit' => 5000,
                'is_reversal' => false,
            ],
        ]);

        $beforeFinance = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date=2026-04-09&end_date=2026-04-09');
        $beforeAnalytics = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/analytics?preset=custom&start_date=2026-04-09&end_date=2026-04-09');

        $beforeFinance->assertStatus(409)
            ->assertJsonPath('code', 'SALES_COST_AGGREGATE_REQUIRED');
        $beforeAnalytics->assertStatus(409)
            ->assertJsonPath('code', 'SALES_COST_AGGREGATE_REQUIRED');

        $this->artisan('sales-cost:rebuild', [
            '--tenant' => $tenant->id,
            '--from' => '2026-04-09',
            '--to' => '2026-04-09',
            '--sync-journal' => true,
        ])->expectsOutputToContain('Sales cost rebuild selesai.')
            ->assertExitCode(0);

        $this->assertSame(2, DB::table('sale_cost_components')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->count());
        $this->assertDatabaseHas('sale_cost_components', [
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'sale_item_id' => $saleItemId,
            'component_type' => 'sale_item',
            'total_cost' => 5000.0,
        ]);
        $this->assertDatabaseHas('sale_cost_components', [
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'sale_item_id' => $saleItemId,
            'component_type' => 'recipe_input',
            'total_cost' => 3000.0,
        ]);
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'cogs_total' => 8000.0,
        ]);

        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'journal_entry_id' => $journalEntryId,
            'account_id' => $mapping->cogs_account_id,
            'debit' => 8000.0,
            'credit' => 0.0,
            'is_reversal' => false,
        ]);
        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'journal_entry_id' => $journalEntryId,
            'account_id' => $mapping->inventory_account_id,
            'debit' => 0.0,
            'credit' => 8000.0,
            'is_reversal' => false,
        ]);

        $afterFinance = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date=2026-04-09&end_date=2026-04-09');
        $afterAnalytics = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/analytics?preset=custom&start_date=2026-04-09&end_date=2026-04-09');

        $afterFinance->assertOk()->assertJsonPath('summary.expenses', 8000);
        $afterAnalytics->assertOk()->assertJsonPath('summary.profit', 7000);
    }

    public function test_finance_summary_rejects_missing_aggregate_sale_in_strict_mode(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+strict-finance-guard@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();
        $createdAt = CarbonImmutable::parse('2026-04-11 10:00:00', 'UTC');

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 12000,
            'discount' => 0,
            'tax' => 0,
            'total' => 12000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'created_at' => $createdAt,
            'transaction_date' => '2026-04-11',
            'cogs_total' => 0,
        ]);

        DB::table('sale_items')->insert([
            'id' => $saleItemId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 1,
            'price' => 12000,
            'discount' => 0,
            'hpp_snapshot' => 4000,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date=2026-04-11&end_date=2026-04-11')
            ->assertStatus(409)
            ->assertJsonPath('code', 'SALES_COST_AGGREGATE_REQUIRED')
            ->assertJsonPath('details.context', 'finance-summary');
    }

    public function test_analytics_rejects_missing_aggregate_sale_in_strict_mode(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+strict-analytics-guard@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();
        $createdAt = CarbonImmutable::parse('2026-04-12 10:00:00', 'UTC');

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 18000,
            'discount' => 0,
            'tax' => 0,
            'total' => 18000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'created_at' => $createdAt,
            'transaction_date' => '2026-04-12',
            'cogs_total' => 0,
        ]);

        DB::table('sale_items')->insert([
            'id' => $saleItemId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 1,
            'price' => 18000,
            'discount' => 0,
            'hpp_snapshot' => 5000,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/analytics?preset=custom&start_date=2026-04-12&end_date=2026-04-12')
            ->assertStatus(409)
            ->assertJsonPath('code', 'SALES_COST_AGGREGATE_REQUIRED')
            ->assertJsonPath('details.context', 'analytics');
    }

    public function test_finance_summary_rejects_mismatched_header_cogs_even_when_aggregate_exists(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+strict-finance-mismatch@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();
        $createdAt = CarbonImmutable::parse('2026-04-14 10:00:00', 'UTC');

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 12000,
            'discount' => 0,
            'tax' => 0,
            'total' => 12000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'created_at' => $createdAt,
            'transaction_date' => '2026-04-14',
            'cogs_total' => 3000,
        ]);

        DB::table('sale_items')->insert([
            'id' => $saleItemId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 1,
            'price' => 12000,
            'discount' => 0,
            'hpp_snapshot' => 4000,
        ]);

        DB::table('sale_cost_components')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'sale_item_id' => $saleItemId,
            'component_type' => 'sale_item',
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 1,
            'unit_cost' => 4000,
            'total_cost' => 4000,
            'source_reference_type' => 'sale',
            'source_reference_id' => $saleId,
            'source_reference_item_id' => $saleItemId,
            'source' => 'repair',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date=2026-04-14&end_date=2026-04-14')
            ->assertStatus(409)
            ->assertJsonPath('code', 'SALES_COST_AGGREGATE_REQUIRED')
            ->assertJsonPath('details.context', 'finance-summary')
            ->assertJsonPath('details.reason', 'invalid_sales_cost_state')
            ->assertJsonPath('details.summary.mismatched_header_cogs', 1);
    }

    public function test_daily_sales_summary_apply_sale_rejects_mismatched_header_cogs(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+strict-daily-summary-mismatch@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();
        $createdAt = CarbonImmutable::parse('2026-04-15 10:00:00', 'UTC');

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 12000,
            'discount' => 0,
            'tax' => 0,
            'total' => 12000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'created_at' => $createdAt,
            'transaction_date' => '2026-04-15',
            'cogs_total' => 3000,
        ]);

        DB::table('sale_items')->insert([
            'id' => $saleItemId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 1,
            'price' => 12000,
            'discount' => 0,
            'hpp_snapshot' => 4000,
        ]);

        DB::table('sale_cost_components')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'sale_item_id' => $saleItemId,
            'component_type' => 'sale_item',
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 1,
            'unit_cost' => 4000,
            'total_cost' => 4000,
            'source_reference_type' => 'sale',
            'source_reference_id' => $saleId,
            'source_reference_item_id' => $saleItemId,
            'source' => 'repair',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $this->expectException(\App\Exceptions\MissingSalesCostAggregateException::class);
        $this->expectExceptionMessage('COGS aggregated tidak konsisten untuk daily-sales-summary');

        app(DailySalesSummaryService::class)->applySale($tenant->id, $saleId);
    }

    public function test_validate_command_reports_missing_components_and_header_mismatch(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+sales-cost-validate@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $createdAt = CarbonImmutable::parse('2026-04-13 10:00:00', 'UTC');

        $missingSaleId = (string) Str::uuid();
        $missingSaleItemId = (string) Str::uuid();
        DB::table('sales')->insert([
            'id' => $missingSaleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 10000,
            'discount' => 0,
            'tax' => 0,
            'total' => 10000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'created_at' => $createdAt,
            'transaction_date' => '2026-04-13',
            'cogs_total' => 0,
        ]);
        DB::table('sale_items')->insert([
            'id' => $missingSaleItemId,
            'tenant_id' => $tenant->id,
            'sale_id' => $missingSaleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 1,
            'price' => 10000,
            'discount' => 0,
            'hpp_snapshot' => 3000,
        ]);

        $mismatchSaleId = (string) Str::uuid();
        $mismatchSaleItemId = (string) Str::uuid();
        DB::table('sales')->insert([
            'id' => $mismatchSaleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 20000,
            'discount' => 0,
            'tax' => 0,
            'total' => 20000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'created_at' => $createdAt,
            'transaction_date' => '2026-04-13',
            'cogs_total' => 6000,
        ]);
        DB::table('sale_items')->insert([
            'id' => $mismatchSaleItemId,
            'tenant_id' => $tenant->id,
            'sale_id' => $mismatchSaleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 1,
            'price' => 20000,
            'discount' => 0,
            'hpp_snapshot' => 7000,
        ]);
        DB::table('sale_cost_components')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $mismatchSaleId,
            'sale_item_id' => $mismatchSaleItemId,
            'component_type' => 'sale_item',
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 1,
            'unit_cost' => 7000,
            'total_cost' => 7000,
            'source_reference_type' => 'sale',
            'source_reference_id' => $mismatchSaleId,
            'source_reference_item_id' => $mismatchSaleItemId,
            'source' => 'repair',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $this->artisan('sales-cost:validate', [
            '--tenant' => $tenant->id,
            '--from' => '2026-04-13',
            '--to' => '2026-04-13',
        ])
            ->expectsOutputToContain('missing_component_sales: 1')
            ->expectsOutputToContain('mismatched_header_cogs: 1')
            ->assertExitCode(1);
    }

    private function prepareBomCheckoutContext(string $tenantId, string $userId): array
    {
        $fixture = $this->seedCheckoutFixture($tenantId, $userId);
        $rawMaterialId = (string) Str::uuid();
        $now = now('UTC');

        $this->seedInventoryCostState($tenantId, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        DB::table('products')->insert([
            'id' => $rawMaterialId,
            'tenant_id' => $tenantId,
            'sku' => 'RM-'.Str::upper(Str::random(6)),
            'name' => 'Bahan BOM',
            'price' => 1500,
            'hpp_average' => 1500,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'product_mode' => 'add_on',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'raw_material',
            'variants' => json_encode([]),
            'bom_items' => json_encode([]),
            'bundle_items' => json_encode([]),
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenantId,
            'product_id' => $rawMaterialId,
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => 10,
        ]);

        $this->seedInventoryCostState($tenantId, $rawMaterialId, $fixture['location_id'], 10, 1500);

        DB::table('tenants')
            ->where('id', $tenantId)
            ->update([
                'auto_consume_on_sell_enabled' => true,
                'allow_negative_raw_material_stock' => false,
            ]);

        DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('id', $fixture['product_id'])
            ->update([
                'product_mode' => 'bom',
                'production_mode' => Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL,
                'item_type' => 'finished_good',
                'bom_items' => json_encode([
                    [
                        'product_id' => $rawMaterialId,
                        'name' => 'Bahan BOM',
                        'qty' => 2,
                        'unit' => 'gram',
                    ],
                ]),
                'updated_at' => $now,
            ]);

        return [
            'fixture' => $fixture,
            'payload' => $this->checkoutPayload($fixture, $now->toDateString()),
            'raw_material_id' => $rawMaterialId,
        ];
    }

    private function seedInventoryCostState(
        string $tenantId,
        string $productId,
        string $locationId,
        float $qtyOnHand,
        float $averageCost
    ): void {
        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'product_variant_id' => null,
            'location_id' => $locationId,
            'qty_on_hand' => $qtyOnHand,
            'average_cost' => $averageCost,
            'last_cost' => $averageCost,
            'updated_at' => now('UTC'),
        ]);
    }
}