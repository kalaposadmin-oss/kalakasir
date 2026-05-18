<?php

namespace Tests\Feature;

use App\Services\TenantDefaultAccountingSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class FinanceReportAccountDetailTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_inventory_account_detail_returns_journal_summary_and_product_breakdown(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner+finance-account-detail@example.test', 'Owner Finance Detail');
        $tenant->forceFill(['plan' => 'P'])->save();
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $mapping = DB::table('account_mappings')
            ->where('tenant_id', $tenant->id)
            ->first(['inventory_account_id', 'cash_account_id']);

        $branchId = (string) Str::uuid();
        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenant->id,
            'name' => 'Cabang Utama',
        ]);

        $locationId = (string) Str::uuid();
        DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'name' => 'Gudang Utama',
            'is_default' => true,
        ]);

        $productId = (string) Str::uuid();
        DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $tenant->id,
            'sku' => 'INV-001',
            'name' => 'Beras Premium',
            'price' => 25000,
            'hpp_average' => 12500,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        DB::table('inventory_summaries')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'location_id' => $locationId,
            'product_id' => $productId,
            'current_qty' => 12,
            'current_value' => 150000,
            'updated_at' => now('UTC'),
        ]);

        $this->seedJournalPair(
            tenantId: $tenant->id,
            userId: $user->id,
            date: '2026-03-31',
            debitAccountId: (string) $mapping->inventory_account_id,
            creditAccountId: (string) $mapping->cash_account_id,
            amount: 50000,
            description: 'Saldo awal persediaan'
        );

        $this->seedJournalPair(
            tenantId: $tenant->id,
            userId: $user->id,
            date: '2026-04-10',
            debitAccountId: (string) $mapping->inventory_account_id,
            creditAccountId: (string) $mapping->cash_account_id,
            amount: 120000,
            description: 'Pembelian stok April'
        );

        $this->seedJournalPair(
            tenantId: $tenant->id,
            userId: $user->id,
            date: '2026-04-18',
            debitAccountId: (string) $mapping->cash_account_id,
            creditAccountId: (string) $mapping->inventory_account_id,
            amount: 20000,
            description: 'Pemakaian stok April'
        );

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/finance-summary/account-detail?account_id='.(string) $mapping->inventory_account_id.'&preset=custom&start_date=2026-04-01&end_date=2026-04-30')
            ->assertOk()
            ->assertJsonPath('account.code', '1301')
            ->assertJsonPath('summary.opening_balance', 50000)
            ->assertJsonPath('summary.period_debit', 120000)
            ->assertJsonPath('summary.period_credit', 20000)
            ->assertJsonPath('summary.ending_balance', 150000)
            ->assertJsonPath('summary.report_basis', 'balance_as_of_end_date')
            ->assertJsonPath('inventory_breakdown.0.product_id', $productId)
            ->assertJsonPath('inventory_breakdown.0.product_name', 'Beras Premium')
            ->assertJsonPath('inventory_breakdown.0.current_value', 150000);
    }

    private function seedJournalPair(
        string $tenantId,
        string $userId,
        string $date,
        string $debitAccountId,
        string $creditAccountId,
        float $amount,
        string $description
    ): void {
        $journalEntryId = (string) Str::uuid();
        $postedAt = now('UTC');

        DB::table('journal_entries')->insert([
            'id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'reference_type' => 'manual_adjustment',
            'reference_id' => (string) Str::uuid(),
            'date' => $date,
            'description' => $description,
            'status' => 'posted',
            'posted_at' => $postedAt,
            'created_by' => $userId,
            'created_at' => $postedAt,
        ]);

        DB::table('journal_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'journal_entry_id' => $journalEntryId,
                'account_id' => $debitAccountId,
                'debit' => $amount,
                'credit' => 0,
                'is_reversal' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'journal_entry_id' => $journalEntryId,
                'account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $amount,
                'is_reversal' => false,
            ],
        ]);
    }
}
