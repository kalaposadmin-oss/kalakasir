<?php

namespace Tests\Feature;

use App\Services\TenantDefaultAccountingSetupService;
use App\Services\FinanceGlReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class FixedAssetInventoryTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_owner_can_record_asset_maintenance_depreciation_and_audit(): void
    {
        [$tenant] = $this->bootApiActor('Owner', 'owner+fixed-assets@example.test');

        $createResponse = $this->postJson('/api/v1/fixed-assets', [
            'asset_code' => 'AST-OVEN-001',
            'name' => 'Oven Deck 2 Loyang',
            'asset_category' => 'oven',
            'purchase_date' => '2026-02-01',
            'purchase_price' => 12000000,
            'residual_value' => 2000000,
            'useful_life_months' => 60,
            'depreciation_method' => 'straight_line',
            'condition' => 'good',
            'status' => 'active',
            'next_maintenance_at' => '2026-05-01',
        ], $this->tenantHeaders($tenant->id));

        $createResponse->assertCreated()
            ->assertJsonPath('asset.asset_code', 'AST-OVEN-001')
            ->assertJsonPath('asset.book_value', 12000000);

        $assetId = (string) $createResponse->json('asset.id');

        $maintenanceResponse = $this->postJson('/api/v1/fixed-assets/'.$assetId.'/maintenance', [
            'maintenance_date' => '2026-03-10',
            'maintenance_type' => 'routine',
            'vendor_name' => 'Teknisi Oven',
            'cost' => 250000,
            'condition_after' => 'good',
            'next_maintenance_at' => '2026-06-10',
        ], $this->tenantHeaders($tenant->id));

        $maintenanceResponse->assertCreated()
            ->assertJsonPath('asset.last_maintenance_at', '2026-03-10')
            ->assertJsonPath('asset.next_maintenance_at', '2026-06-10');

        $depreciationResponse = $this->postJson('/api/v1/fixed-assets/'.$assetId.'/depreciation', [
            'period_date' => '2026-03-01',
        ], $this->tenantHeaders($tenant->id));

        $depreciationResponse->assertCreated()
            ->assertJsonPath('depreciation.depreciation_amount', 166666.67)
            ->assertJsonPath('asset.accumulated_depreciation', 166666.67)
            ->assertJsonPath('asset.book_value', 11833333.33);

        $auditResponse = $this->postJson('/api/v1/fixed-assets/'.$assetId.'/audits', [
            'audit_date' => '2026-03-31',
            'physical_status' => 'found',
            'condition' => 'good',
            'notes' => 'Aset lengkap dan berfungsi.',
        ], $this->tenantHeaders($tenant->id));

        $auditResponse->assertCreated()
            ->assertJsonPath('audit.physical_status', 'found');

        $showResponse = $this->getJson('/api/v1/fixed-assets/'.$assetId, $this->tenantHeaders($tenant->id));

        $showResponse->assertOk()
            ->assertJsonCount(1, 'maintenance_logs')
            ->assertJsonCount(1, 'depreciation_entries')
            ->assertJsonCount(1, 'audits');
    }

    public function test_cash_asset_purchase_posts_cash_journal_and_depreciation_expense(): void
    {
        [$tenant, $user] = $this->bootApiActor('Owner', 'owner+fixed-assets-accounting@example.test');
        app(TenantDefaultAccountingSetupService::class)->ensure((string) $tenant->id, (string) $user->id);

        $cashAccountId = (string) DB::table('account_mappings')->where('tenant_id', $tenant->id)->value('cash_account_id');
        $capitalAccountId = (string) DB::table('chart_of_accounts')->where('tenant_id', $tenant->id)->where('code', '3101')->value('id');
        $openingJournalId = (string) Str::uuid();

        DB::table('journal_entries')->insert([
            'id' => $openingJournalId,
            'tenant_id' => $tenant->id,
            'reference_type' => 'test_opening_cash',
            'reference_id' => (string) Str::uuid(),
            'date' => '2026-02-01',
            'description' => 'Opening cash for asset test',
            'status' => 'posted',
            'posted_at' => CarbonImmutable::now('UTC'),
            'created_by' => $user->id,
            'created_at' => CarbonImmutable::now('UTC'),
        ]);

        DB::table('journal_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $openingJournalId,
                'tenant_id' => $tenant->id,
                'account_id' => $cashAccountId,
                'debit' => 15000000,
                'credit' => 0,
                'is_reversal' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $openingJournalId,
                'tenant_id' => $tenant->id,
                'account_id' => $capitalAccountId,
                'debit' => 0,
                'credit' => 15000000,
                'is_reversal' => false,
            ],
        ]);

        $createResponse = $this->postJson('/api/v1/fixed-assets', [
            'asset_code' => 'AST-MIX-001',
            'name' => 'Mixer Spiral 20L',
            'asset_category' => 'production_equipment',
            'purchase_date' => '2026-02-15',
            'purchase_price' => 12000000,
            'residual_value' => 0,
            'useful_life_months' => 48,
            'payment_method' => 'cash',
        ], $this->tenantHeaders($tenant->id));

        $createResponse->assertCreated()
            ->assertJsonPath('preview.cash_or_bank_out', 12000000)
            ->assertJsonPath('preview.monthly_depreciation', 250000);

        $assetId = (string) $createResponse->json('asset.id');
        $purchaseJournalId = (string) $createResponse->json('asset.purchase_journal_entry_id');

        $this->assertNotSame('', $purchaseJournalId);
        $this->assertDatabaseHas('cash_transactions', [
            'tenant_id' => $tenant->id,
            'journal_entry_id' => $purchaseJournalId,
            'direction' => 'cash-out',
            'category' => 'asset_purchase',
            'amount' => 12000000,
        ]);

        $depreciationResponse = $this->postJson('/api/v1/fixed-assets/'.$assetId.'/depreciation', [
            'period_date' => '2026-03-01',
        ], $this->tenantHeaders($tenant->id));

        $depreciationResponse->assertCreated()
            ->assertJsonPath('depreciation.depreciation_amount', 250000);

        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'fixed_asset_depreciation',
            'reference_id' => $assetId,
            'date' => '2026-03-01',
            'status' => 'posted',
        ]);

        $report = app(FinanceGlReportService::class)->build((string) $tenant->id, '2026-03-01', '2026-03-31', true);

        $this->assertTrue(collect($report['expense_breakdown'])->contains(
            fn (array $row) => str_contains((string) $row['label'], '6201') && (float) $row['amount'] === 250000.0
        ));
        $this->assertTrue(collect($report['balance_sheet']['assets'])->contains(
            fn (array $row) => str_contains((string) $row['label'], '1501') && (float) $row['amount'] === 12000000.0
        ));
        $this->assertTrue(collect($report['balance_sheet']['assets'])->contains(
            fn (array $row) => str_contains((string) $row['label'], '1502') && (float) $row['amount'] === -250000.0
        ));
    }

    public function test_payable_asset_purchase_can_be_paid_from_cash_and_reduces_liability(): void
    {
        [$tenant, $user] = $this->bootApiActor('Owner', 'owner+fixed-assets-payable@example.test');
        app(TenantDefaultAccountingSetupService::class)->ensure((string) $tenant->id, (string) $user->id);

        $mapping = DB::table('account_mappings')->where('tenant_id', $tenant->id)->first();
        $cashAccountId = (string) $mapping->cash_account_id;
        $capitalAccountId = (string) DB::table('chart_of_accounts')->where('tenant_id', $tenant->id)->where('code', '3101')->value('id');
        $openingJournalId = (string) Str::uuid();

        DB::table('journal_entries')->insert([
            'id' => $openingJournalId,
            'tenant_id' => $tenant->id,
            'reference_type' => 'test_opening_cash',
            'reference_id' => (string) Str::uuid(),
            'date' => '2026-02-01',
            'description' => 'Opening cash for payable asset test',
            'status' => 'posted',
            'posted_at' => CarbonImmutable::now('UTC'),
            'created_by' => $user->id,
            'created_at' => CarbonImmutable::now('UTC'),
        ]);

        DB::table('journal_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $openingJournalId,
                'tenant_id' => $tenant->id,
                'account_id' => $cashAccountId,
                'debit' => 10000000,
                'credit' => 0,
                'is_reversal' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $openingJournalId,
                'tenant_id' => $tenant->id,
                'account_id' => $capitalAccountId,
                'debit' => 0,
                'credit' => 10000000,
                'is_reversal' => false,
            ],
        ]);

        $createResponse = $this->postJson('/api/v1/fixed-assets', [
            'asset_code' => 'AST-CHL-001',
            'name' => 'Chiller Display',
            'asset_category' => 'cooling_equipment',
            'purchase_date' => '2026-02-20',
            'purchase_price' => 9000000,
            'residual_value' => 0,
            'useful_life_months' => 60,
            'payment_method' => 'payable',
        ], $this->tenantHeaders($tenant->id));

        $createResponse->assertCreated()
            ->assertJsonPath('asset.liability_status', 'open')
            ->assertJsonPath('asset.liability_remaining_amount', 9000000);

        $assetId = (string) $createResponse->json('asset.id');

        $paymentResponse = $this->postJson('/api/v1/fixed-assets/'.$assetId.'/payments', [
            'payment_date' => '2026-03-05',
            'payment_account_id' => $cashAccountId,
            'amount' => 4000000,
            'notes' => 'Termin pertama',
        ], $this->tenantHeaders($tenant->id));

        $paymentResponse->assertCreated()
            ->assertJsonPath('asset.liability_paid_amount', 4000000)
            ->assertJsonPath('asset.liability_status', 'partial')
            ->assertJsonPath('asset.liability_remaining_amount', 5000000);

        $this->assertDatabaseHas('cash_transactions', [
            'tenant_id' => $tenant->id,
            'direction' => 'cash-out',
            'category' => 'supplier_payment',
            'amount' => 4000000,
            'reference' => 'AST-CHL-001',
        ]);

        $showResponse = $this->getJson('/api/v1/fixed-assets/'.$assetId, $this->tenantHeaders($tenant->id));
        $showResponse->assertOk()
            ->assertJsonCount(1, 'payment_logs');
    }
}
