<?php

namespace Tests\Feature;

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

class FinanceReportAccountingSyncTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_cash_in_capital_injection_updates_balance_sheet_only(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+capital@example.test', 'Owner Capital');
        $tenant->forceFill(['plan' => 'P'])->save();
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $cashAccountId = (string) DB::table('account_mappings')
            ->where('tenant_id', $tenant->id)
            ->value('cash_account_id');

        app(CashTransactionService::class)->store($tenant->id, 'cash-in', [
            'transaction_date' => '2026-04-18',
            'amount' => 250000,
            'account_id' => $cashAccountId,
            'category' => 'capital_injection',
            'description' => 'Setoran modal owner',
        ], $user);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date=2026-04-18&end_date=2026-04-18');

        $response->assertOk()
            ->assertJsonPath('period.closing_status.status', 'draft')
            ->assertJsonPath('period.closing_status.is_final', false)
            ->assertJsonPath('period.closing_status.label', 'Belum Final')
            ->assertJsonPath('summary.revenue', 0)
            ->assertJsonPath('summary.expenses', 0)
            ->assertJsonPath('summary.net_profit', 0)
            ->assertJsonStructure([
                'balance_sheet' => ['assets', 'liabilities', 'equity'],
            ]);

        $assetsTotal = collect($response->json('balance_sheet.assets', []))->sum('amount');
        $this->assertEqualsWithDelta(250000.0, (float) $assetsTotal, 0.01);

        DB::table('lock_periods')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'start_date' => '2026-04-18',
            'end_date' => '2026-04-18',
            'is_locked' => true,
            'locked_by' => $user->id,
            'locked_at' => now('UTC'),
            'reason' => 'Finalisasi laporan harian test',
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date=2026-04-18&end_date=2026-04-18')
            ->assertOk()
            ->assertJsonPath('period.closing_status.status', 'final')
            ->assertJsonPath('period.closing_status.is_final', true)
            ->assertJsonPath('period.closing_status.label', 'Final');
    }

    public function test_cash_in_other_income_updates_balance_sheet_and_profit_loss(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+other-income@example.test', 'Owner Other Income');
        $tenant->forceFill(['plan' => 'P'])->save();
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $cashAccountId = (string) DB::table('account_mappings')
            ->where('tenant_id', $tenant->id)
            ->value('cash_account_id');

        app(CashTransactionService::class)->store($tenant->id, 'cash-in', [
            'transaction_date' => '2026-04-18',
            'amount' => 300000,
            'account_id' => $cashAccountId,
            'category' => 'other_income',
            'description' => 'Pendapatan lain-lain',
        ], $user);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date=2026-04-18&end_date=2026-04-18');

        $response->assertOk()
            ->assertJsonPath('summary.revenue', 300000)
            ->assertJsonPath('summary.expenses', 0)
            ->assertJsonPath('summary.net_profit', 300000);

        $assetsTotal = collect($response->json('balance_sheet.assets', []))->sum('amount');
        $this->assertEqualsWithDelta(300000.0, (float) $assetsTotal, 0.01);
    }

    public function test_cash_out_expense_updates_balance_sheet_and_profit_loss(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+cashout-expense@example.test', 'Owner Expense');
        $tenant->forceFill(['plan' => 'P'])->save();
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $cashAccountId = (string) DB::table('account_mappings')
            ->where('tenant_id', $tenant->id)
            ->value('cash_account_id');

        app(CashTransactionService::class)->store($tenant->id, 'cash-in', [
            'transaction_date' => '2026-04-18',
            'amount' => 500000,
            'account_id' => $cashAccountId,
            'category' => 'capital_injection',
            'description' => 'Modal awal',
        ], $user);

        app(CashTransactionService::class)->store($tenant->id, 'cash-out', [
            'transaction_date' => '2026-04-18',
            'amount' => 125000,
            'account_id' => $cashAccountId,
            'category' => 'operational_expense',
            'description' => 'Beban operasional',
        ], $user);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date=2026-04-18&end_date=2026-04-18');

        $response->assertOk()
            ->assertJsonPath('summary.revenue', 0)
            ->assertJsonPath('summary.expenses', 125000)
            ->assertJsonPath('summary.net_profit', -125000);

        $assetsTotal = collect($response->json('balance_sheet.assets', []))->sum('amount');
        $this->assertEqualsWithDelta(375000.0, (float) $assetsTotal, 0.01);
    }

    public function test_finance_summary_ui_payload_is_synchronized_with_gl_service(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+sync@example.test', 'Owner Sync');
        $tenant->forceFill(['plan' => 'P'])->save();
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $cashAccountId = (string) DB::table('account_mappings')
            ->where('tenant_id', $tenant->id)
            ->value('cash_account_id');

        $service = app(CashTransactionService::class);
        $service->store($tenant->id, 'cash-in', [
            'transaction_date' => '2026-04-18',
            'amount' => 200000,
            'account_id' => $cashAccountId,
            'category' => 'other_income',
            'description' => 'Pendapatan lain sinkron',
        ], $user);
        $service->store($tenant->id, 'cash-out', [
            'transaction_date' => '2026-04-18',
            'amount' => 50000,
            'account_id' => $cashAccountId,
            'category' => 'operational_expense',
            'description' => 'Beban sinkron',
        ], $user);

        $apiResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date=2026-04-18&end_date=2026-04-18')
            ->assertOk()
            ->json();

        $glPayload = app(FinanceGlReportService::class)->build($tenant->id, '2026-04-18', '2026-04-18', true);

        $this->assertEqualsWithDelta(
            (float) ($glPayload['summary']['revenue'] ?? 0),
            (float) ($apiResponse['summary']['revenue'] ?? 0),
            0.01
        );
        $this->assertEqualsWithDelta(
            (float) ($glPayload['summary']['expenses'] ?? 0),
            (float) ($apiResponse['summary']['expenses'] ?? 0),
            0.01
        );
        $this->assertEqualsWithDelta(
            (float) ($glPayload['summary']['net_profit'] ?? 0),
            (float) ($apiResponse['summary']['net_profit'] ?? 0),
            0.01
        );
    }

    public function test_backdated_cash_transaction_is_reported_by_journal_entry_date_period(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+backdated@example.test', 'Owner Backdated');
        $tenant->forceFill(['plan' => 'P'])->save();
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $cashAccountId = (string) DB::table('account_mappings')
            ->where('tenant_id', $tenant->id)
            ->value('cash_account_id');

        app(CashTransactionService::class)->store($tenant->id, 'cash-in', [
            'transaction_date' => '2026-04-01',
            'amount' => 70000,
            'account_id' => $cashAccountId,
            'category' => 'other_income',
            'description' => 'Pendapatan backdated',
        ], $user);

        $dayOne = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date=2026-04-01&end_date=2026-04-01');

        $dayTwo = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date=2026-04-02&end_date=2026-04-02');

        $dayOne->assertOk()->assertJsonPath('summary.revenue', 70000);
        $dayTwo->assertOk()->assertJsonPath('summary.revenue', 0);
    }

    public function test_cash_purchase_receipt_updates_balance_sheet_without_liabilities(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+purchase-cash@example.test', 'Owner Purchase Cash');
        $tenant->forceFill(['plan' => 'P'])->save();
        $reportDate = now('UTC')->toDateString();

        $context = $this->seedPurchaseAuditContext($tenant->id, $user->id, $reportDate);

        $purchaseId = $this->createPurchase($tenant->id, $context['supplier_id'], $context['location_id'], $context['product_id'], 'tunai', $context['cash_account_id'], $reportDate);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'transaction_date' => $reportDate,
                'items' => [
                    [
                        'product_id' => $context['product_id'],
                        'qty' => 1,
                    ],
                ],
            ])->assertOk();

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date='.$reportDate.'&end_date='.$reportDate);

        $response->assertOk();

        $assets = collect($response->json('balance_sheet.assets', []));
        $liabilities = collect($response->json('balance_sheet.liabilities', []));

        $cashAmount = (float) ($assets->firstWhere('code', '1101')['amount'] ?? 0);
        $inventoryAmount = (float) ($assets->firstWhere('code', '1301')['amount'] ?? 0);
        $liabilityAmount = (float) $liabilities->sum('amount');

        $this->assertEqualsWithDelta(50000.0, $cashAmount, 0.01);
        $this->assertEqualsWithDelta(50000.0, $inventoryAmount, 0.01);
        $this->assertEqualsWithDelta(0.0, $liabilityAmount, 0.01);
    }

    public function test_tempo_purchase_receipt_updates_payables_not_cash(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+purchase-tempo@example.test', 'Owner Purchase Tempo');
        $tenant->forceFill(['plan' => 'P'])->save();
        $reportDate = now('UTC')->toDateString();

        $context = $this->seedPurchaseAuditContext($tenant->id, $user->id, $reportDate);

        $purchaseId = $this->createPurchase($tenant->id, $context['supplier_id'], $context['location_id'], $context['product_id'], 'tempo', null, $reportDate);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'transaction_date' => $reportDate,
                'items' => [
                    [
                        'product_id' => $context['product_id'],
                        'qty' => 1,
                    ],
                ],
            ])->assertOk();

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary?preset=custom&start_date='.$reportDate.'&end_date='.$reportDate);

        $response->assertOk();

        $assets = collect($response->json('balance_sheet.assets', []));
        $liabilities = collect($response->json('balance_sheet.liabilities', []));

        $inventoryAmount = (float) ($assets->firstWhere('code', '1301')['amount'] ?? 0);
        $payableAmount = (float) ($liabilities->firstWhere('code', '2101')['amount'] ?? 0);

        $this->assertEqualsWithDelta(50000.0, $inventoryAmount, 0.01);
        $this->assertEqualsWithDelta(50000.0, $payableAmount, 0.01);
    }

    private function createPurchase(string $tenantId, string $supplierId, string $locationId, string $productId, string $paymentMethod, ?string $cashAccountId, ?string $transactionDate = null): string
    {
        $payload = [
            'supplier_id' => $supplierId,
            'location_id' => $locationId,
            'purchase_mode' => PurchaseMode::PO_SHORT,
            'status' => PurchaseStatus::SUBMITTED,
            'payment_method' => $paymentMethod,
            'transaction_date' => $transactionDate,
            'items' => [
                [
                    'product_id' => $productId,
                    'qty' => 1,
                    'price' => 50000,
                ],
            ],
        ];

        if ($cashAccountId) {
            $payload['cash_account_id'] = $cashAccountId;
        }

        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/purchases', $payload);

        $response->assertCreated();

        return (string) $response->json('purchase_id');
    }

    private function seedPurchaseAuditContext(string $tenantId, string $userId, string $date): array
    {
        app(TenantDefaultAccountingSetupService::class)->ensure($tenantId, $userId);

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
            'sku' => 'SKU-AUDIT',
            'name' => 'Produk Audit',
            'price' => 50000,
            'hpp_average' => 0,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
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
            'name' => 'Supplier Audit',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        $cashAccountId = (string) DB::table('account_mappings')->where('tenant_id', $tenantId)->value('cash_account_id');
        $this->seedCashBalance($tenantId, $userId, $cashAccountId, 100000, $date);

        return [
            'location_id' => $locationId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'cash_account_id' => $cashAccountId,
        ];
    }

    private function insertSupplier(string $tenantId, string $name): string
    {
        $supplierId = (string) Str::uuid();

        DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $tenantId,
            'name' => $name,
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        return $supplierId;
    }

    private function seedCashBalance(string $tenantId, string $userId, string $cashAccountId, float $amount, ?string $date = null): void
    {
        $journalEntryId = (string) Str::uuid();
        $postedAt = now('UTC');
        $transactionDate = $date ?: now('UTC')->toDateString();

        DB::table('journal_entries')->insert([
            'id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'reference_type' => 'cash_seed',
            'reference_id' => (string) Str::uuid(),
            'date' => $transactionDate,
            'description' => 'Seed saldo kas purchase finance test',
            'status' => 'posted',
            'posted_at' => $postedAt,
            'created_by' => $userId,
            'created_at' => $postedAt,
        ]);

        DB::table('journal_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'journal_entry_id' => $journalEntryId,
            'account_id' => $cashAccountId,
            'debit' => $amount,
            'credit' => 0,
            'is_reversal' => false,
        ]);
    }
}
