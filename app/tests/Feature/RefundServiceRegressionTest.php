<?php

namespace Tests\Feature;

use App\Models\InventoryIssueAllocation;
use App\Services\RefundService;
use App\Support\TenantContext;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class RefundServiceRegressionTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_handle_uses_outer_payload_and_ledger_rows_inside_transaction(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+refund-regression@example.test', 'Admin Refund Regression');

        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        DB::table('inventory_cost_states')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'location_id' => $fixture['location_id'],
            'qty_on_hand' => 100,
            'average_cost' => 5000,
            'last_cost' => 5000,
            'updated_at' => now('UTC'),
        ]);

        $checkoutResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/checkout', $this->checkoutPayload($fixture, '2026-02-27'));

        $checkoutResponse->assertCreated();

        $saleId = (string) $checkoutResponse->json('sale_id');
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        TenantContext::set($tenant->fresh());

        $refundId = app(RefundService::class)->handle([
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'reason' => 'Retur pelanggan regression',
            'items' => [
                [
                    'sale_item_id' => $saleItemId,
                    'qty' => 1,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $fixture['cash_account_id'],
                    'payment_method_id' => $fixture['payment_method_id'],
                    'method' => 'cash',
                    'amount' => 10000,
                ],
            ],
        ], $user->id);

        $this->assertNotSame('', $refundId);

        $this->assertDatabaseHas('refunds', [
            'id' => $refundId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'branch_id' => $fixture['branch_id'],
            'created_by' => $user->id,
            'status' => 'completed',
            'reason' => 'Retur pelanggan regression',
            'subtotal' => 10000,
            'item_discount_total' => 0,
            'transaction_discount_total' => 0,
            'tax_total' => 0,
            'refunded_cost_total' => 5000,
            'payout_total' => 10000,
            'total' => 10000,
        ]);

        $this->assertDatabaseHas('refund_items', [
            'tenant_id' => $tenant->id,
            'refund_id' => $refundId,
            'sale_item_id' => $saleItemId,
            'product_id' => $fixture['product_id'],
            'qty' => 1,
            'gross_amount' => 10000,
            'item_discount_amount' => 0,
            'transaction_discount_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => 10000,
            'hpp_snapshot' => 5000,
            'refunded_cost_total' => 5000,
            'amount' => 10000,
        ]);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'location_id' => $fixture['location_id'],
            'reference_type' => 'refund',
            'reference_id' => $refundId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 1,
            'qty_out' => 0,
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('refund_payments', [
            'tenant_id' => $tenant->id,
            'refund_id' => $refundId,
            'account_id' => $fixture['cash_account_id'],
            'payment_method_id' => $fixture['payment_method_id'],
            'method' => 'cash',
            'amount' => 10000,
        ]);

        $this->assertDatabaseHas('cash_flows', [
            'tenant_id' => $tenant->id,
            'shift_id' => $fixture['shift_id'],
            'branch_id' => $fixture['branch_id'],
            'type' => 'refund',
            'amount' => 10000,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'refund',
            'reference_id' => $refundId,
            'status' => 'posted',
        ]);

        $entryId = (string) DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'refund')
            ->where('reference_id', $refundId)
            ->value('id');

        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'journal_entry_id' => $entryId,
            'account_id' => $fixture['cash_account_id'],
            'debit' => 0,
            'credit' => 10000,
            'is_reversal' => true,
        ]);

        $allocation = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->first();

        $this->assertNotNull($allocation);
        $this->assertSame(1.0, (float) $allocation->reversed_qty);
        $this->assertNotNull($allocation->reversed_at);
    }

    public function test_refund_uses_sale_item_id_for_duplicate_product_lines_with_different_prices(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+refund-duplicate-lines@example.test', 'Admin Refund Duplicate Lines');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'transaction_date' => '2026-02-27',
            'subtotal' => 24000,
            'discount' => 2400,
            'tax' => 2160,
            'total' => 23760,
            'items' => [
                [
                    'product_id' => $fixture['product_id'],
                    'location_id' => $fixture['location_id'],
                    'qty' => 1,
                    'price' => 10000,
                    'discount' => 1000,
                ],
                [
                    'product_id' => $fixture['product_id'],
                    'location_id' => $fixture['location_id'],
                    'qty' => 1,
                    'price' => 15000,
                    'discount' => 0,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $fixture['cash_account_id'],
                    'method' => 'cash',
                    'amount' => 23760,
                    'payment_method_id' => $fixture['payment_method_id'],
                ],
            ],
        ];

        $saleId = $this->createSale($tenant->id, $payload);
        $saleItems = DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->orderBy('price')
            ->get(['id', 'price']);

        $firstLineItemId = (string) $saleItems[0]->id;
        $secondLineItemId = (string) $saleItems[1]->id;

        TenantContext::set($tenant->fresh());

        $refundId = app(RefundService::class)->handle([
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'reason' => 'Refund line item pertama',
            'items' => [
                [
                    'sale_item_id' => $firstLineItemId,
                    'product_id' => $fixture['product_id'],
                    'qty' => 1,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $fixture['cash_account_id'],
                    'payment_method_id' => $fixture['payment_method_id'],
                    'method' => 'cash',
                    'amount' => 8910,
                ],
            ],
        ], $user->id);

        $this->assertDatabaseHas('refund_items', [
            'tenant_id' => $tenant->id,
            'refund_id' => $refundId,
            'sale_item_id' => $firstLineItemId,
            'gross_amount' => 10000,
            'item_discount_amount' => 1000,
            'transaction_discount_amount' => 900,
            'tax_amount' => 810,
            'net_amount' => 8910,
            'amount' => 8910,
        ]);

        $firstAllocation = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $firstLineItemId)
            ->first();

        $secondAllocation = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $secondLineItemId)
            ->first();

        $this->assertNotNull($firstAllocation);
        $this->assertNotNull($secondAllocation);
        $this->assertSame(1.0, (float) $firstAllocation->reversed_qty);
        $this->assertNotNull($firstAllocation->reversed_at);
        $this->assertSame(0.0, (float) $secondAllocation->reversed_qty);
        $this->assertNull($secondAllocation->reversed_at);
    }

    public function test_partial_refund_prorates_item_discount_transaction_discount_and_tax_and_blocks_over_refund(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+refund-partial-prorate@example.test', 'Admin Refund Partial');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 100, 5000);

        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'transaction_date' => '2026-02-27',
            'subtotal' => 18000,
            'discount' => 1800,
            'tax' => 1620,
            'total' => 17820,
            'items' => [
                [
                    'product_id' => $fixture['product_id'],
                    'location_id' => $fixture['location_id'],
                    'qty' => 2,
                    'price' => 10000,
                    'discount' => 2000,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $fixture['cash_account_id'],
                    'method' => 'cash',
                    'amount' => 17820,
                    'payment_method_id' => $fixture['payment_method_id'],
                ],
            ],
        ];

        $saleId = $this->createSale($tenant->id, $payload);
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        TenantContext::set($tenant->fresh());

        $firstRefundId = app(RefundService::class)->handle([
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'reason' => 'Refund parsial pertama',
            'items' => [
                [
                    'sale_item_id' => $saleItemId,
                    'qty' => 1,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $fixture['cash_account_id'],
                    'payment_method_id' => $fixture['payment_method_id'],
                    'method' => 'cash',
                    'amount' => 8910,
                ],
            ],
        ], $user->id);

        $this->assertDatabaseHas('refund_items', [
            'tenant_id' => $tenant->id,
            'refund_id' => $firstRefundId,
            'sale_item_id' => $saleItemId,
            'qty' => 1,
            'gross_amount' => 10000,
            'item_discount_amount' => 1000,
            'transaction_discount_amount' => 900,
            'tax_amount' => 810,
            'net_amount' => 8910,
            'amount' => 8910,
            'refunded_cost_total' => 5000,
        ]);

        $allocationAfterFirstRefund = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->first();

        $this->assertNotNull($allocationAfterFirstRefund);
        $this->assertSame(1.0, (float) $allocationAfterFirstRefund->reversed_qty);
        $this->assertNull($allocationAfterFirstRefund->reversed_at);

        app(RefundService::class)->handle([
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'reason' => 'Refund parsial kedua',
            'items' => [
                [
                    'sale_item_id' => $saleItemId,
                    'qty' => 1,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $fixture['cash_account_id'],
                    'payment_method_id' => $fixture['payment_method_id'],
                    'method' => 'cash',
                    'amount' => 8910,
                ],
            ],
        ], $user->id);

        $allocationAfterSecondRefund = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->first();

        $this->assertNotNull($allocationAfterSecondRefund);
        $this->assertSame(2.0, (float) $allocationAfterSecondRefund->reversed_qty);
        $this->assertNotNull($allocationAfterSecondRefund->reversed_at);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Qty refund melebihi qty transaksi.');

        app(RefundService::class)->handle([
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'reason' => 'Refund ketiga tidak valid',
            'items' => [
                [
                    'sale_item_id' => $saleItemId,
                    'qty' => 1,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $fixture['cash_account_id'],
                    'payment_method_id' => $fixture['payment_method_id'],
                    'method' => 'cash',
                    'amount' => 8910,
                ],
            ],
        ], $user->id);
    }

    public function test_refund_returns_stock_to_the_same_variant_bucket(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+refund-variant-stock@example.test', 'Admin Refund Variant');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $variantId = $this->createVariant($tenant->id, $fixture['product_id']);
        $this->seedInventoryCostState($tenant->id, $fixture['product_id'], $fixture['location_id'], 7, 5000);

        DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('location_id', $fixture['location_id'])
            ->whereNull('product_variant_id')
            ->update([
                'product_variant_id' => $variantId,
                'qty_on_hand' => 7,
            ]);

        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['items'][0]['product_variant_id'] = $variantId;

        $saleId = $this->createSale($tenant->id, $payload);
        $saleItemId = (string) DB::table('sale_items')
            ->where('tenant_id', $tenant->id)
            ->where('sale_id', $saleId)
            ->value('id');

        $this->assertInventoryBalance($tenant->id, $fixture['product_id'], $variantId, $fixture['location_id'], 6.0);

        TenantContext::set($tenant->fresh());

        app(RefundService::class)->handle([
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'reason' => 'Refund variant aman',
            'items' => [
                [
                    'sale_item_id' => $saleItemId,
                    'product_id' => $fixture['product_id'],
                    'product_variant_id' => $variantId,
                    'qty' => 1,
                ],
            ],
            'payments' => [
                [
                    'account_id' => $fixture['cash_account_id'],
                    'payment_method_id' => $fixture['payment_method_id'],
                    'method' => 'cash',
                    'amount' => 10000,
                ],
            ],
        ], $user->id);

        $this->assertInventoryBalance($tenant->id, $fixture['product_id'], $variantId, $fixture['location_id'], 7.0);
        $this->assertSame(0, DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('location_id', $fixture['location_id'])
            ->whereNull('product_variant_id')
            ->count());
    }

    private function seedInventoryCostState(string $tenantId, string $productId, string $locationId, float $qtyOnHand, float $averageCost): void
    {
        DB::table('inventory_cost_states')->updateOrInsert([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'location_id' => $locationId,
        ], [
            'id' => (string) Str::uuid(),
            'qty_on_hand' => $qtyOnHand,
            'average_cost' => $averageCost,
            'last_cost' => $averageCost,
            'updated_at' => now('UTC'),
        ]);
    }

    private function createSale(string $tenantId, array $payload): string
    {
        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/checkout', $payload);

        $response->assertCreated();

        return (string) $response->json('sale_id');
    }

    private function createVariant(string $tenantId, string $productId): string
    {
        $variantId = (string) Str::uuid();

        DB::table('product_variants')->insert([
            'id' => $variantId,
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'sku' => 'SKU-VAR-'.substr(str_replace('-', '', $variantId), 0, 8),
            'barcode' => null,
            'name' => 'Variant Refund',
            'attributes' => json_encode(['warna' => 'merah']),
            'price_override' => null,
            'is_active' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return $variantId;
    }

    private function assertInventoryBalance(string $tenantId, string $productId, ?string $variantId, string $locationId, float $expectedQty): void
    {
        $qty = (float) DB::table('inventory_balances')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->when($variantId, function ($query) use ($variantId) {
                $query->where('product_variant_id', $variantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            })
            ->value('qty_on_hand');

        $this->assertSame($expectedQty, $qty);
    }
}