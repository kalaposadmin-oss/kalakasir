<?php

namespace Tests\Feature;

use App\Support\PurchaseStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class PurchaseWorkflowStateMachineTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_create_purchase_draft_persists_draft_status(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-draft@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Draft');

        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::DRAFT);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'tenant_id' => $tenant->id,
            'status' => PurchaseStatus::DRAFT,
        ]);
    }

    public function test_submit_purchase_persists_submitted_status(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-submit@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Submitted');

        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'tenant_id' => $tenant->id,
            'status' => PurchaseStatus::SUBMITTED,
        ]);
    }

    public function test_purchase_tax_is_optional_and_editable(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-tax@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Tax');

        $withoutTaxId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::DRAFT, 2, 10000);
        $withoutTax = DB::table('purchases')->where('id', $withoutTaxId)->first();

        $this->assertSame(20000.0, (float) $withoutTax->subtotal);
        $this->assertSame(0.0, (float) $withoutTax->tax);
        $this->assertSame(20000.0, (float) $withoutTax->total);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/api/v1/purchases/'.$withoutTaxId.'/tax', [
                'tax_enabled' => true,
                'tax_rate' => 11,
            ]);

        $response->assertOk()
            ->assertJsonPath('purchase.subtotal', 20000)
            ->assertJsonPath('purchase.tax_rate', 11)
            ->assertJsonPath('purchase.tax', 2200)
            ->assertJsonPath('purchase.total', 22200);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/api/v1/purchases/'.$withoutTaxId.'/tax', [
                'tax_enabled' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('purchase.tax_rate', 0)
            ->assertJsonPath('purchase.tax', 0)
            ->assertJsonPath('purchase.total', 20000);
    }

    public function test_purchase_additional_costs_are_stored_and_posted_as_expense_not_stock(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-additional-cost@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Packaging');
        $purchaseId = $this->createPurchase(
            $tenant->id,
            $supplierId,
            $fixture['location_id'],
            $fixture['product_id'],
            PurchaseStatus::SUBMITTED,
            5,
            10000,
            [
                [
                    'name' => 'Plastik dan kardus',
                    'category' => 'packaging',
                    'amount' => 3000,
                ],
            ]
        );

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'tenant_id' => $tenant->id,
            'subtotal' => 50000,
            'additional_cost_total' => 3000,
            'total' => 53000,
        ]);

        $this->assertDatabaseHas('purchase_additional_costs', [
            'tenant_id' => $tenant->id,
            'purchase_id' => $purchaseId,
            'name' => 'Plastik dan kardus',
            'category' => 'packaging',
            'amount' => 3000,
            'posted_at' => null,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/purchases/'.$purchaseId)
            ->assertOk()
            ->assertJsonPath('purchase.additional_cost_total', 3000)
            ->assertJsonPath('purchase.total', 53000)
            ->assertJsonPath('additional_costs.0.name', 'Plastik dan kardus')
            ->assertJsonPath('additional_costs.0.posted', false);

        $this->approvePurchase($tenant->id, $purchaseId);

        $firstReceive = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 2,
                    ],
                ],
            ]);

        $firstReceive->assertOk()
            ->assertJsonPath('status', PurchaseStatus::PARTIALLY_RECEIVED)
            ->assertJsonPath('received_total', 20000)
            ->assertJsonPath('additional_cost_total', 3000);

        $this->assertNotNull(DB::table('purchase_additional_costs')
            ->where('tenant_id', $tenant->id)
            ->where('purchase_id', $purchaseId)
            ->value('posted_at'));

        $secondReceive = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 3,
                    ],
                ],
            ]);

        $secondReceive->assertOk()
            ->assertJsonPath('status', PurchaseStatus::RECEIVED)
            ->assertJsonPath('received_total', 30000)
            ->assertJsonPath('additional_cost_total', 0);

        $this->assertSame(50000.0, (float) DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->sum('total_cost'));

        $mapping = DB::table('account_mappings')
            ->where('tenant_id', $tenant->id)
            ->first();

        $journalItems = DB::table('journal_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.tenant_id', $tenant->id)
            ->where('journal_entries.reference_type', 'purchase')
            ->where('journal_entries.reference_id', $purchaseId)
            ->select('journal_items.account_id')
            ->selectRaw('sum(journal_items.debit) as debit_total')
            ->selectRaw('sum(journal_items.credit) as credit_total')
            ->groupBy('journal_items.account_id')
            ->pluck('debit_total', 'account_id')
            ->map(fn ($value) => (float) $value);

        $journalCredits = DB::table('journal_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_entries.tenant_id', $tenant->id)
            ->where('journal_entries.reference_type', 'purchase')
            ->where('journal_entries.reference_id', $purchaseId)
            ->select('journal_items.account_id')
            ->selectRaw('sum(journal_items.credit) as credit_total')
            ->groupBy('journal_items.account_id')
            ->pluck('credit_total', 'account_id')
            ->map(fn ($value) => (float) $value);

        $this->assertSame(50000.0, $journalItems[(string) $mapping->inventory_account_id] ?? 0.0);
        $this->assertSame(3000.0, $journalItems[(string) $mapping->expense_adjustment_account_id] ?? 0.0);
        $this->assertSame(53000.0, $journalCredits[(string) $mapping->payable_account_id] ?? 0.0);
    }

    public function test_create_purchase_returns_date_bounds_when_order_date_is_before_current_month(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-create-date-bounds@example.test');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-03 10:00:00', 'Asia/Jakarta'));
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Date Bounds');

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'location_id' => $fixture['location_id'],
                'status' => PurchaseStatus::SUBMITTED,
                'transaction_date' => '2026-05-31',
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                        'price' => 10000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_TRANSACTION_DATE')
            ->assertJsonPath('details.min_date', '2026-06-01')
            ->assertJsonPath('details.max_date', '2026-06-03')
            ->assertJsonPath('details.policy', 'current_month');
    }

    public function test_purchase_index_includes_item_summary_for_list_cards(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-index-summary@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Summary');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::DRAFT, 3, 12000);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/purchases');

        $response->assertOk()
            ->assertJsonPath('purchases.0.id', $purchaseId)
            ->assertJsonPath('purchases.0.item_count', 1)
            ->assertJsonPath('purchases.0.total_item_qty', 3)
            ->assertJsonPath('purchases.0.total', 36000);
    }

    public function test_draft_purchase_can_update_date_and_replace_items(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-draft-update@example.test');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-02 10:00:00', 'UTC'));
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Draft Update');
        $secondProductId = $this->insertProduct($tenant->id, $user->id, 'SKU-002', 'Produk B');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::DRAFT, 2, 10000);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/api/v1/purchases/'.$purchaseId.'/draft', [
                'supplier_id' => $supplierId,
                'location_id' => $fixture['location_id'],
                'transaction_date' => '2026-06-01',
                'payment_method' => 'tempo',
                'due_date' => '2026-06-15',
                'tax_enabled' => true,
                'tax_rate' => 10,
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 3,
                        'price' => 12000,
                    ],
                    [
                        'product_id' => $secondProductId,
                        'qty' => 1,
                        'price' => 5000,
                    ],
                ],
                'additional_costs' => [
                    [
                        'name' => 'Kardus',
                        'category' => 'packaging',
                        'amount' => 2500,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('transaction_date', '2026-06-01')
            ->assertJsonPath('subtotal', 41000)
            ->assertJsonPath('tax', 4100)
            ->assertJsonPath('additional_cost_total', 2500)
            ->assertJsonPath('total', 47600);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'tenant_id' => $tenant->id,
            'status' => PurchaseStatus::DRAFT,
            'transaction_date' => '2026-06-01',
            'subtotal' => 41000,
            'tax' => 4100,
            'additional_cost_total' => 2500,
            'total' => 47600,
        ]);

        $this->assertDatabaseHas('purchase_additional_costs', [
            'tenant_id' => $tenant->id,
            'purchase_id' => $purchaseId,
            'name' => 'Kardus',
            'category' => 'packaging',
            'amount' => 2500,
        ]);

        $this->assertSame(2, DB::table('purchase_items')
            ->where('tenant_id', $tenant->id)
            ->where('purchase_id', $purchaseId)
            ->count());

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/purchases/'.$purchaseId)
            ->assertOk()
            ->assertJsonPath('purchase.transaction_date', '2026-06-01')
            ->assertJsonPath('purchase.additional_cost_total', 2500)
            ->assertJsonPath('additional_costs.0.name', 'Kardus')
            ->assertJsonPath('items.0.purchase_qty', 3);
    }

    public function test_non_draft_purchase_cannot_be_replaced_through_draft_editor(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-draft-update-blocked@example.test');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-02 10:00:00', 'UTC'));
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Draft Blocked');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED, 2, 10000);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/api/v1/purchases/'.$purchaseId.'/draft', [
                'supplier_id' => $supplierId,
                'location_id' => $fixture['location_id'],
                'transaction_date' => '2026-06-01',
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 3,
                        'price' => 12000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'PURCHASE_DRAFT_UPDATE_FAILED');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::SUBMITTED,
            'total' => 20000,
        ]);
    }

    public function test_approve_purchase_from_submitted_status_succeeds(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-approve@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Approve');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/decision', [
                'status' => PurchaseStatus::APPROVED,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::APPROVED,
        ]);
    }

    public function test_approve_purchase_from_invalid_status_is_rejected(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-approve-invalid@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Draft Approve');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::DRAFT);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/decision', [
                'status' => PurchaseStatus::APPROVED,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'PURCHASE_DECISION_FAILED');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::DRAFT,
        ]);
    }

    public function test_receive_purchase_from_approved_status_succeeds(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-receive-approved@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Receive Full');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED, 5, 10000);

        $this->approvePurchase($tenant->id, $purchaseId);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 5,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('status', PurchaseStatus::RECEIVED);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::RECEIVED,
        ]);

        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchaseId,
            'product_id' => $fixture['product_id'],
            'received_qty' => 5,
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['product_id'],
            'qty_on_hand' => 105,
        ]);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'purchase',
            'reference_id' => $purchaseId,
            'qty_in' => 5,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/purchases/'.$purchaseId)
            ->assertOk()
            ->assertJsonPath('purchase.status', PurchaseStatus::RECEIVED)
            ->assertJsonPath('flow.can_close', false);

        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'purchase',
            'reference_id' => $purchaseId,
            'status' => 'posted',
        ]);
    }

    public function test_receive_purchase_from_partially_received_status_succeeds(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-receive-partial@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Receive Partial');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED, 5, 10000);

        $this->approvePurchase($tenant->id, $purchaseId);

        $firstResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 3,
                    ],
                ],
            ]);

        $firstResponse->assertOk()
            ->assertJsonPath('status', PurchaseStatus::PARTIALLY_RECEIVED);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::PARTIALLY_RECEIVED,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/purchases/'.$purchaseId)
            ->assertOk()
            ->assertJsonPath('purchase.status', PurchaseStatus::PARTIALLY_RECEIVED)
            ->assertJsonPath('flow.can_close', true);

        $secondResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 2,
                    ],
                ],
            ]);

        $secondResponse->assertOk()
            ->assertJsonPath('status', PurchaseStatus::RECEIVED);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::RECEIVED,
        ]);

        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchaseId,
            'product_id' => $fixture['product_id'],
            'received_qty' => 5,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/purchases/'.$purchaseId)
            ->assertOk()
            ->assertJsonPath('purchase.status', PurchaseStatus::RECEIVED)
            ->assertJsonPath('flow.can_close', false);

        $this->assertDatabaseHas('inventory_balances', [
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $fixture['product_id'],
            'qty_on_hand' => 105,
        ]);

        $this->assertSame(2, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->count());

        $this->assertSame(2, DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->count());
    }

    public function test_receive_purchase_from_submitted_status_is_rejected(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-receive-submitted@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Submitted Receive');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED, 5, 10000);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'PURCHASE_RECEIVE_FAILED')
            ->assertJsonPath('message', 'PO harus disetujui dulu sebelum menerima barang.');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::SUBMITTED,
        ]);

        $this->assertSame(0, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->count());

        $this->assertSame(0, DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->count());
    }

    public function test_receive_purchase_over_qty_is_rejected(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-receive-overqty@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Over Qty');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED, 5, 10000);

        $this->approvePurchase($tenant->id, $purchaseId);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 6,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'PURCHASE_RECEIVE_FAILED')
            ->assertJsonPath('message', 'Qty received melebihi sisa untuk produk tertentu.');

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'status' => PurchaseStatus::APPROVED,
        ]);

        $this->assertSame(0, DB::table('stock_ledger')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->count());

        $this->assertSame(0, DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchaseId)
            ->count());
    }

    public function test_receive_closed_purchase_is_rejected(): void
    {
        $this->assertReceiveBlockedForStatus(PurchaseStatus::CLOSED, 'PO sudah selesai atau dibatalkan, tidak bisa menerima barang lagi.');
    }

    public function test_receive_cancelled_purchase_is_rejected(): void
    {
        $this->assertReceiveBlockedForStatus(PurchaseStatus::CANCELLED, 'PO sudah selesai atau dibatalkan, tidak bisa menerima barang lagi.');
    }

    private function assertReceiveBlockedForStatus(string $status, string $message): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'purchase-receive-blocked-'.Str::random(8).'@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $supplierId = $this->insertSupplier($tenant->id, 'Supplier Blocked');
        $purchaseId = $this->createPurchase($tenant->id, $supplierId, $fixture['location_id'], $fixture['product_id'], PurchaseStatus::SUBMITTED, 5, 10000);

        DB::table('purchases')
            ->where('id', $purchaseId)
            ->where('tenant_id', $tenant->id)
            ->update(['status' => $status]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'PURCHASE_RECEIVE_FAILED')
            ->assertJsonPath('message', $message);
    }

    private function createPurchase(string $tenantId, string $supplierId, string $locationId, string $productId, string $status, float $qty = 1, float $price = 10000, array $additionalCosts = []): string
    {
        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'location_id' => $locationId,
                'status' => $status,
                'additional_costs' => $additionalCosts,
                'items' => [
                    [
                        'product_id' => $productId,
                        'qty' => $qty,
                        'price' => $price,
                    ],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('purchase_id');
    }

    private function approvePurchase(string $tenantId, string $purchaseId): void
    {
        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/decision', [
                'status' => PurchaseStatus::APPROVED,
            ]);

        $response->assertOk();
    }

    private function receivePurchase(string $tenantId, string $purchaseId, string $productId, float $qty): void
    {
        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/purchases/'.$purchaseId.'/receive', [
                'items' => [
                    [
                        'product_id' => $productId,
                        'qty' => $qty,
                    ],
                ],
            ]);

        $response->assertOk();
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

    private function insertProduct(string $tenantId, string $userId, string $sku, string $name): string
    {
        $productId = (string) Str::uuid();

        DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $tenantId,
            'sku' => $sku,
            'name' => $name,
            'price' => 10000,
            'hpp_average' => 5000,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        return $productId;
    }
}
