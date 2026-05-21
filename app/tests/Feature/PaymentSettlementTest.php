<?php

namespace Tests\Feature;

use App\Services\FinanceGlReportService;
use App\Services\TenantDefaultAccountingSetupService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class PaymentSettlementTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_qris_settlement_records_real_bank_amount_and_admin_fee_to_reports(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'payment-settlement@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        app(TenantDefaultAccountingSetupService::class)->ensure((string) $tenant->id, (string) $user->id);

        $mapping = DB::table('account_mappings')->where('tenant_id', $tenant->id)->first();
        $feeAccountId = (string) DB::table('chart_of_accounts')->where('tenant_id', $tenant->id)->where('code', '6301')->value('id');

        $saleId = (string) Str::uuid();
        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'customer_id' => null,
            'subtotal' => 100000,
            'discount' => 0,
            'tax' => 0,
            'total' => 100000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'created_at' => CarbonImmutable::parse('2026-05-20 09:00:00', 'Asia/Jakarta')->utc(),
            'transaction_date' => '2026-05-20',
        ]);

        DB::table('payments')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'account_id' => $mapping->bank_account_id,
            'method' => 'qris',
            'amount' => 100000,
        ]);

        $summary = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/payment-settlements?sales_date=2026-05-20&payment_method=qris');

        $summary->assertOk()
            ->assertJsonPath('summary.expected_amount', 100000)
            ->assertJsonPath('summary.already_settled', false);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/payment-settlements', [
                'sales_date' => '2026-05-20',
                'settlement_date' => '2026-05-21',
                'payment_method' => 'qris',
                'settlement_account_id' => $mapping->bank_account_id,
                'bank_account_id' => $mapping->bank_account_id,
                'fee_account_id' => $feeAccountId,
                'received_amount' => 99000,
                'notes' => 'Mutasi QRIS masuk net.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('settlement.expected_amount', 100000)
            ->assertJsonPath('settlement.received_amount', 99000)
            ->assertJsonPath('settlement.admin_fee_amount', 1000);

        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'payment_settlement',
            'date' => '2026-05-21',
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'account_id' => $feeAccountId,
            'debit' => 1000,
            'credit' => 0,
        ]);

        $report = app(FinanceGlReportService::class)->build((string) $tenant->id, '2026-05-21', '2026-05-21', true);
        $this->assertTrue(collect($report['expense_breakdown'])->contains(
            fn (array $row) => str_contains((string) $row['label'], '6301') && (float) $row['amount'] === 1000.0
        ));
    }
}
