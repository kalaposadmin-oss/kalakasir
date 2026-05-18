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

class PurchaseCashReceiptJournalTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_cash_purchase_receipt_posts_to_cash_not_accounts_payable(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-cash-receipt-journal@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Tunai');
        $payableAccountId = (string) DB::table('account_mappings')
            ->where('tenant_id', $tenant->id)
            ->value('payable_account_id');

        $this->seedCashBalance($tenant->id, $user->id, $fixture['cash_account_id'], 100000);

        $purchaseId = $this->createTunaiPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], $fixture['cash_account_id']);

        $receiveResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                    ],
                ],
            ]);

        $receiveResponse->assertOk()
            ->assertJsonPath('status', PurchaseStatus::RECEIVED);

        $journalEntryId = (string) DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->value('id');

        $cashAccountLine = DB::table('journal_items')
            ->where('tenant_id', $tenant->id)
            ->where('journal_entry_id', $journalEntryId)
            ->where('account_id', $fixture['cash_account_id'])
            ->first();

        $payableLine = DB::table('journal_items')
            ->where('tenant_id', $tenant->id)
            ->where('journal_entry_id', $journalEntryId)
            ->where('account_id', $payableAccountId)
            ->first();

        $this->assertNotNull($cashAccountLine);
        $this->assertSame('10000', (string) $cashAccountLine->credit);
        $this->assertNull($payableLine);
    }

    private function createTunaiPurchase(string $tenantId, string $supplierId, string $locationId, string $productId, string $cashAccountId): string
    {
        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'location_id' => $locationId,
                'purchase_mode' => PurchaseMode::PO_SHORT,
                'status' => PurchaseStatus::SUBMITTED,
                'payment_method' => 'tunai',
                'cash_account_id' => $cashAccountId,
                'items' => [
                    [
                        'product_id' => $productId,
                        'qty' => 1,
                        'price' => 10000,
                    ],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('purchase_id');
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
            'date' => '2026-02-27',
            'description' => 'Seed saldo kas purchase cash test',
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