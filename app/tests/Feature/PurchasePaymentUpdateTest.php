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

class PurchasePaymentUpdateTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_create_submitted_cash_po_does_not_require_available_cash_before_receiving(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-cash-po-create@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Cash PO');

        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], 'tunai', $fixture['cash_account_id']);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'tenant_id' => $tenant->id,
            'status' => PurchaseStatus::SUBMITTED,
            'payment_method' => 'tunai',
            'cash_account_id' => $fixture['cash_account_id'],
        ]);
    }

    public function test_failed_purchase_item_validation_does_not_leave_partial_purchase_header(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-partial-create@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Partial Guard');
        DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('id', $fixture['product_id'])
            ->update(['unit' => 'pcs']);

        $beforeCount = DB::table('purchases')
            ->where('tenant_id', $tenant->id)
            ->count();

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'location_id' => $fixture['location_id'],
                'purchase_mode' => PurchaseMode::PO_SHORT,
                'status' => PurchaseStatus::SUBMITTED,
                'payment_method' => 'tempo',
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                        'purchase_unit' => 'kg',
                        'price' => 10000,
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->assertSame($beforeCount, DB::table('purchases')
            ->where('tenant_id', $tenant->id)
            ->count());
    }

    public function test_update_purchase_payment_from_tempo_to_tunai_updates_cash_account_and_clears_due_date(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-payment-edit-tempo@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Tempo');

        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id']);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/api/v1/purchases/'.$purchaseId.'/payment', [
                'payment_method' => 'tunai',
                'cash_account_id' => $fixture['cash_account_id'],
            ]);

        $response->assertOk()
            ->assertJsonPath('purchase.payment_method', 'tunai')
            ->assertJsonPath('purchase.cash_account_id', $fixture['cash_account_id'])
            ->assertJsonPath('purchase.due_date', null);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'tenant_id' => $tenant->id,
            'status' => PurchaseStatus::SUBMITTED,
            'payment_method' => 'tunai',
            'cash_account_id' => $fixture['cash_account_id'],
            'due_date' => null,
        ]);
    }

    public function test_update_purchase_payment_from_tunai_to_tempo_sets_due_date_and_clears_cash_account(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-payment-edit-cash@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Tunai');
        $this->seedCashBalance($tenant->id, $user->id, $fixture['cash_account_id'], 1000000);

        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], 'tunai', $fixture['cash_account_id']);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/api/v1/purchases/'.$purchaseId.'/payment', [
                'payment_method' => 'tempo',
                'due_date' => '2026-03-15',
            ]);

        $response->assertOk()
            ->assertJsonPath('purchase.payment_method', 'tempo')
            ->assertJsonPath('purchase.cash_account_id', null)
            ->assertJsonPath('purchase.due_date', '2026-03-15');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'tenant_id' => $tenant->id,
            'status' => PurchaseStatus::SUBMITTED,
            'payment_method' => 'tempo',
            'cash_account_id' => null,
            'due_date' => '2026-03-15',
        ]);
    }

    public function test_update_payment_after_receive_creates_canonical_correction_journal_from_payable_to_cash(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-payment-reclass@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Reclass');

        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], 'tempo');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', PurchaseStatus::RECEIVED);

        $payableAccountId = (string) DB::table('account_mappings')
            ->where('tenant_id', $tenant->id)
            ->value('payable_account_id');

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/api/v1/purchases/'.$purchaseId.'/payment', [
                'payment_method' => 'tunai',
                'cash_account_id' => $fixture['cash_account_id'],
            ]);

        $response->assertOk()
            ->assertJsonPath('purchase.payment_method', 'tunai')
            ->assertJsonPath('purchase.cash_account_id', $fixture['cash_account_id'])
            ->assertJsonPath('purchase.due_date', null);

        $correctionEntryId = (string) DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase_payment_correction')
            ->where('reference_id', $purchaseId)
            ->value('id');

        $this->assertNotSame('', $correctionEntryId);

        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'journal_entry_id' => $correctionEntryId,
            'account_id' => $payableAccountId,
            'debit' => 10000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'journal_entry_id' => $correctionEntryId,
            'account_id' => $fixture['cash_account_id'],
            'debit' => 0,
            'credit' => 10000,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/api/v1/purchases/'.$purchaseId.'/payment', [
                'payment_method' => 'tunai',
                'cash_account_id' => $fixture['cash_account_id'],
            ])
            ->assertOk();

        $this->assertSame(1, DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase_payment_correction')
            ->where('reference_id', $purchaseId)
            ->count());
    }

    private function createPurchase(string $tenantId, string $supplierId, string $locationId, string $productId, string $paymentMethod = 'tempo', ?string $cashAccountId = null): string
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
                    'price' => 10000,
                ],
            ],
        ];

        if ($paymentMethod === 'tunai') {
            $payload['cash_account_id'] = $cashAccountId;
        }

        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/purchases', $payload);

        $response->assertCreated();

        return (string) $response->json('purchase_id');
    }

    private function insertSupplier(string $tenantId, string $name): string
    {
        $supplierId = (string) Str::uuid();

        \DB::table('suppliers')->insert([
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
            'description' => 'Seed saldo kas purchase payment test',
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
