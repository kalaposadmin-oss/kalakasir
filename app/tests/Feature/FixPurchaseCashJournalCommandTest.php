<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CashTransactionService;
use App\Services\FinanceGlReportService;
use App\Services\TenantDefaultAccountingSetupService;
use App\Support\PurchaseMode;
use App\Support\PurchaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class FixPurchaseCashJournalCommandTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_dry_run_reports_only_bad_cash_purchase_and_total_value(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'fix-purchase-cash-dry-run@example.test');
        $context = $this->seedRepairContext($tenant->id, $user);

        $badPurchaseId = $this->createCashPurchase($tenant->id, $context['supplier_id'], $context['location_id'], $context['product_id'], $context['cash_account_id'], '2026-02-27');
        $goodPurchaseId = $this->createCashPurchase($tenant->id, $context['supplier_id'], $context['location_id'], $context['product_id'], $context['cash_account_id'], '2026-02-27');

        $this->receivePurchase($tenant->id, $badPurchaseId, $context['product_id'], '2026-02-27');
        $this->receivePurchase($tenant->id, $goodPurchaseId, $context['product_id'], '2026-02-27');

        $this->corruptPurchaseJournalToPayable($tenant->id, $badPurchaseId, $context['payable_account_id']);

        $this->artisan('fix:purchase-cash-journal', [
            '--dry-run' => true,
            '--tenant_id' => $tenant->id,
        ])->expectsOutputToContain('total_rows: 2')
            ->expectsOutputToContain('eligible_rows: 1')
            ->expectsOutputToContain('eligible_total_value: 50000.00')
            ->expectsOutputToContain('already_correct_rows: 1')
            ->expectsOutputToContain('Dry run only.')
            ->assertExitCode(0);

        $badEntryCount = DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_id', $badPurchaseId)
            ->where('status', 'posted')
            ->count();

        $this->assertSame(1, $badEntryCount);
    }

    public function test_execute_backfills_bad_cash_purchase_using_canonical_correction(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'fix-purchase-cash-execute@example.test');
        $context = $this->seedRepairContext($tenant->id, $user);

        $purchaseId = $this->createCashPurchase($tenant->id, $context['supplier_id'], $context['location_id'], $context['product_id'], $context['cash_account_id'], '2026-02-27');
        $this->receivePurchase($tenant->id, $purchaseId, $context['product_id'], '2026-02-27');
        $this->corruptPurchaseJournalToPayable($tenant->id, $purchaseId, $context['payable_account_id']);

        $before = app(FinanceGlReportService::class)->build($tenant->id, '2026-02-27', '2026-02-27', true);

        $this->assertEqualsWithDelta(100000.0, $this->balanceSheetAmountByCode($before['balance_sheet']['assets'] ?? [], '1101'), 0.01);
        $this->assertEqualsWithDelta(50000.0, $this->balanceSheetAmountByCode($before['balance_sheet']['assets'] ?? [], '1301'), 0.01);
        $this->assertEqualsWithDelta(50000.0, $this->balanceSheetAmountByCode($before['balance_sheet']['liabilities'] ?? [], '2101'), 0.01);

        $this->artisan('fix:purchase-cash-journal', [
            '--execute' => true,
            '--tenant_id' => $tenant->id,
        ])->expectsOutputToContain('eligible_rows: 1')
            ->expectsOutputToContain('processed_rows: 1')
            ->expectsOutputToContain('failed_rows: 0')
            ->expectsOutputToContain('Backfill selesai.')
            ->assertExitCode(0);

        $after = app(FinanceGlReportService::class)->build($tenant->id, '2026-02-27', '2026-02-27', true);

        $this->assertEqualsWithDelta(50000.0, $this->balanceSheetAmountByCode($after['balance_sheet']['assets'] ?? [], '1101'), 0.01);
        $this->assertEqualsWithDelta(50000.0, $this->balanceSheetAmountByCode($after['balance_sheet']['assets'] ?? [], '1301'), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->balanceSheetAmountByCode($after['balance_sheet']['liabilities'] ?? [], '2101'), 0.01);

        $entryTypes = DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_id', $purchaseId)
            ->where('status', 'posted')
            ->orderBy('created_at')
            ->pluck('reference_type')
            ->all();

        $this->assertSame([
            'purchase',
            'purchase_payment_correction',
        ], $entryTypes);

        $this->assertSame(1, DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase_payment_correction')
            ->where('reference_id', $purchaseId)
            ->count());

        $originalCreditAccountId = (string) DB::table('journal_items')
            ->where('tenant_id', $tenant->id)
            ->where('journal_entry_id', DB::table('journal_entries')
                ->where('tenant_id', $tenant->id)
                ->where('reference_type', 'purchase')
                ->where('reference_id', $purchaseId)
                ->value('id'))
            ->where('credit', '>', 0)
            ->value('account_id');

        $this->assertSame($context['payable_account_id'], $originalCreditAccountId);

        $debitTotal = (float) DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join): void {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenant->id)
            ->where('je.status', 'posted')
            ->sum('ji.debit');

        $creditTotal = (float) DB::table('journal_items as ji')
            ->join('journal_entries as je', function ($join): void {
                $join->on('je.id', '=', 'ji.journal_entry_id')
                    ->on('je.tenant_id', '=', 'ji.tenant_id');
            })
            ->where('ji.tenant_id', $tenant->id)
            ->where('je.status', 'posted')
            ->sum('ji.credit');

        $this->assertEqualsWithDelta($debitTotal, $creditTotal, 0.01);

        $this->artisan('fix:purchase-cash-journal', [
            '--execute' => true,
            '--tenant_id' => $tenant->id,
        ])->assertExitCode(0);

        $this->assertSame(1, DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase_payment_correction')
            ->where('reference_id', $purchaseId)
            ->count());

        $this->assertSame(2, DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_id', $purchaseId)
            ->where('status', 'posted')
            ->count());
    }

    public function test_correct_cash_purchase_is_left_untouched(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'fix-purchase-cash-clean@example.test');
        $context = $this->seedRepairContext($tenant->id, $user);

        $purchaseId = $this->createCashPurchase($tenant->id, $context['supplier_id'], $context['location_id'], $context['product_id'], $context['cash_account_id'], '2026-02-27');
        $this->receivePurchase($tenant->id, $purchaseId, $context['product_id'], '2026-02-27');

        $this->artisan('fix:purchase-cash-journal', [
            '--execute' => true,
            '--tenant_id' => $tenant->id,
        ])->expectsOutputToContain('eligible_rows: 0')
            ->expectsOutputToContain('already_correct_rows: 1')
            ->expectsOutputToContain('processed_rows: 0')
            ->assertExitCode(0);

        $entryTypes = DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_id', $purchaseId)
            ->where('status', 'posted')
            ->pluck('reference_type')
            ->all();

        $this->assertSame(['purchase'], $entryTypes);
    }

    public function test_repaired_purchase_is_skipped_on_subsequent_runs(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'fix-purchase-cash-rerun@example.test');
        $context = $this->seedRepairContext($tenant->id, $user);

        $purchaseId = $this->createCashPurchase($tenant->id, $context['supplier_id'], $context['location_id'], $context['product_id'], $context['cash_account_id'], '2026-02-27');
        $this->receivePurchase($tenant->id, $purchaseId, $context['product_id'], '2026-02-27');
        $this->corruptPurchaseJournalToPayable($tenant->id, $purchaseId, $context['payable_account_id']);

        $this->artisan('fix:purchase-cash-journal', [
            '--execute' => true,
            '--tenant_id' => $tenant->id,
        ])->assertExitCode(0);

        $this->artisan('fix:purchase-cash-journal', [
            '--dry-run' => true,
            '--tenant_id' => $tenant->id,
        ])->expectsOutputToContain('eligible_rows: 0')
            ->expectsOutputToContain('already_repaired_rows: 1')
            ->assertExitCode(0);

        $entryCount = DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_id', $purchaseId)
            ->where('status', 'posted')
            ->count();

        $this->assertSame(2, $entryCount);
    }

    private function seedRepairContext(string $tenantId, User $user): array
    {
        app(TenantDefaultAccountingSetupService::class)->ensure($tenantId, $user->id);

        $branchId = (string) Str::uuid();
        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenantId,
            'name' => 'Main Branch',
        ]);

        $locationId = (string) Str::uuid();
        DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'name' => 'Main Warehouse',
            'is_default' => true,
        ]);

        $productId = (string) Str::uuid();
        DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $tenantId,
            'sku' => 'SKU-CASH-FIX',
            'name' => 'Produk Fix Cash Journal',
            'price' => 50000,
            'hpp_average' => 0,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenantId,
            'location_id' => $locationId,
            'product_id' => $productId,
            'qty_on_hand' => 0,
        ]);

        $supplierId = (string) Str::uuid();
        DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $tenantId,
            'name' => 'Supplier Fix Cash Journal',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        $cashAccountId = (string) DB::table('account_mappings')
            ->where('tenant_id', $tenantId)
            ->value('cash_account_id');

        $payableAccountId = (string) DB::table('account_mappings')
            ->where('tenant_id', $tenantId)
            ->value('payable_account_id');

        app(CashTransactionService::class)->store($tenantId, 'cash-in', [
            'transaction_date' => '2026-02-27',
            'amount' => 100000,
            'account_id' => $cashAccountId,
            'category' => 'capital_injection',
            'description' => 'Seed kas untuk backfill purchase tunai',
        ], $user);

        return [
            'branch_id' => $branchId,
            'location_id' => $locationId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'cash_account_id' => $cashAccountId,
            'payable_account_id' => $payableAccountId,
        ];
    }

    private function createCashPurchase(string $tenantId, string $supplierId, string $locationId, string $productId, string $cashAccountId, string $date): string
    {
        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'location_id' => $locationId,
                'purchase_mode' => PurchaseMode::PO_SHORT,
                'status' => PurchaseStatus::SUBMITTED,
                'payment_method' => 'tunai',
                'cash_account_id' => $cashAccountId,
                'transaction_date' => $date,
                'items' => [
                    [
                        'product_id' => $productId,
                        'qty' => 1,
                        'price' => 50000,
                    ],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('purchase_id');
    }

    private function receivePurchase(string $tenantId, string $purchaseId, string $productId, string $date): void
    {
        $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'transaction_date' => $date,
                'items' => [
                    [
                        'product_id' => $productId,
                        'qty' => 1,
                    ],
                ],
            ])->assertOk();
    }

    private function corruptPurchaseJournalToPayable(string $tenantId, string $purchaseId, string $payableAccountId): void
    {
        $journalEntryId = (string) DB::table('journal_entries')
            ->where('tenant_id', $tenantId)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->value('id');

        DB::table('journal_items')
            ->where('tenant_id', $tenantId)
            ->where('journal_entry_id', $journalEntryId)
            ->where('credit', '>', 0)
            ->update([
                'account_id' => $payableAccountId,
            ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function balanceSheetAmountByCode(array $rows, string $code): float
    {
        return (float) (collect($rows)->firstWhere('code', $code)['amount'] ?? 0);
    }
}