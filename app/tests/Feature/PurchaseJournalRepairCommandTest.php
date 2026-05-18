<?php

namespace Tests\Feature;

use App\Support\PurchaseMode;
use App\Support\PurchaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class PurchaseJournalRepairCommandTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_purchase_journal_repair_command_backfills_cash_and_leaves_tempo_intact(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'purchase-journal-repair@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Repair');

        $this->seedCashBalance($tenant->id, $user->id, $fixture['cash_account_id'], 100000);

        $cashPurchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], 'tunai', $fixture['cash_account_id']);
        $tempoPurchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], 'tempo', null);

        $cashJournalEntryId = $this->receivePurchase($tenant->id, $cashPurchaseId, $fixture['product_id']);
        $tempoJournalEntryId = $this->receivePurchase($tenant->id, $tempoPurchaseId, $fixture['product_id']);

        $payableAccountId = (string) DB::table('account_mappings')
            ->where('tenant_id', $tenant->id)
            ->value('payable_account_id');

        DB::table('journal_items')
            ->where('tenant_id', $tenant->id)
            ->where('journal_entry_id', $cashJournalEntryId)
            ->where('credit', '>', 0)
            ->update(['account_id' => $payableAccountId]);

        $this->artisan('purchase:repair-journals', [
            '--tenant_id' => $tenant->id,
        ])->expectsOutputToContain('cash_mismatch_rows: 1')
            ->expectsOutputToContain('tempo_mismatch_rows: 0')
            ->expectsOutputToContain('Dry run only.')
            ->assertExitCode(0);

        $this->artisan('purchase:repair-journals', [
            '--tenant_id' => $tenant->id,
            '--confirm' => 'REPAIR_ACK',
        ])->expectsOutputToContain('Updated rows: 1')
            ->expectsOutputToContain('Skipped rows: 0')
            ->assertExitCode(0);

        $cashPurchaseOriginalCreditAccountId = (string) DB::table('journal_items')
            ->where('tenant_id', $tenant->id)
            ->where('journal_entry_id', $cashJournalEntryId)
            ->where('credit', '>', 0)
            ->value('account_id');

        $tempoCreditAccountId = (string) DB::table('journal_items')
            ->where('tenant_id', $tenant->id)
            ->where('journal_entry_id', $tempoJournalEntryId)
            ->where('credit', '>', 0)
            ->value('account_id');

        $correctionEntryId = (string) DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase_payment_correction')
            ->where('reference_id', $cashPurchaseId)
            ->value('id');

        $this->assertNotSame('', $correctionEntryId);
        $this->assertSame($payableAccountId, $cashPurchaseOriginalCreditAccountId);
        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'journal_entry_id' => $correctionEntryId,
            'account_id' => $payableAccountId,
            'debit' => 50000,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'journal_entry_id' => $correctionEntryId,
            'account_id' => $fixture['cash_account_id'],
            'debit' => 0,
            'credit' => 50000,
        ]);
        $this->assertSame($payableAccountId, $tempoCreditAccountId);
    }

    private function createPurchase(string $tenantId, string $supplierId, string $locationId, string $productId, string $paymentMethod, ?string $cashAccountId): string
    {
        $payload = [
            'supplier_id' => $supplierId,
            'location_id' => $locationId,
            'purchase_mode' => PurchaseMode::PO_SHORT,
            'status' => PurchaseStatus::SUBMITTED,
            'payment_method' => $paymentMethod,
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

    private function receivePurchase(string $tenantId, string $purchaseId, string $productId): string
    {
        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $productId,
                        'qty' => 1,
                    ],
                ],
            ]);

        $response->assertOk();

        return (string) DB::table('journal_entries')
            ->where('tenant_id', $tenantId)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->value('id');
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

    private function seedCashBalance(string $tenantId, string $userId, string $cashAccountId, float $amount): void
    {
        $journalEntryId = (string) Str::uuid();
        $postedAt = now('UTC');

        DB::table('journal_entries')->insert([
            'id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'reference_type' => 'cash_seed',
            'reference_id' => (string) Str::uuid(),
            'date' => now('UTC')->toDateString(),
            'description' => 'Seed saldo kas purchase journal repair test',
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