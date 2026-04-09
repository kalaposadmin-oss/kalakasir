<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Models\InventoryBalance;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockLedger;
use App\Services\LockPeriodService;
use App\Services\InventoryValuationService;
use App\Support\Decimal;
use App\Support\TenantContext;
use App\Events\PurchaseReceived;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PurchaseService
{
    public function approve(string $purchaseId, ?string $userId): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $purchase = Purchase::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $purchaseId)
            ->firstOrFail();

        if ($purchase->status !== 'draft') {
            throw new RuntimeException('Hanya purchase draft yang bisa di-approve.');
        }

        $purchase->update([
            'status' => 'approved',
            'approved_at' => CarbonImmutable::now('UTC'),
            'approved_by' => $userId,
        ]);
    }

    public function cancel(string $purchaseId, ?string $userId): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $purchase = Purchase::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $purchaseId)
            ->firstOrFail();

        if (! in_array($purchase->status, ['draft', 'approved'], true)) {
            throw new RuntimeException('Purchase tidak bisa dibatalkan.');
        }

        $purchase->update([
            'status' => 'cancelled',
            'cancelled_at' => CarbonImmutable::now('UTC'),
            'cancelled_by' => $userId,
        ]);
    }

    public function close(string $purchaseId, ?string $userId): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $purchase = Purchase::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $purchaseId)
            ->firstOrFail();

        if ($purchase->status !== 'received') {
            throw new RuntimeException('Hanya purchase received yang bisa di-close.');
        }

        $purchase->update([
            'status' => 'closed',
            'closed_at' => CarbonImmutable::now('UTC'),
            'closed_by' => $userId,
        ]);
    }

    public function receive(string $purchaseId, array $payload, ?string $userId): array
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $now = CarbonImmutable::now('UTC');
        $transactionDate = (new TransactionDateService())
            ->resolve(Arr::get($payload, 'transaction_date'));
        $lockService = new LockPeriodService();

        return app(DatabaseTransactionService::class)->run(function () use ($purchaseId, $payload, $tenantId, $userId, $now, $lockService, $transactionDate): array {
            $valuationService = app(InventoryValuationService::class);

            $purchase = Purchase::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $purchaseId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($purchase->status, ['approved', 'partially_received'], true)) {
                throw new RuntimeException('Purchase tidak bisa diterima pada status ini.');
            }

            if (! $purchase->location_id) {
                throw new RuntimeException('Lokasi purchase belum ditentukan.');
            }

            $lockService->ensureNotLocked($transactionDate, $tenantId);

            $items = PurchaseItem::query()
                ->where('tenant_id', $tenantId)
                ->where('purchase_id', $purchase->id)
                ->get();

            if ($items->isEmpty()) {
                throw new RuntimeException('Item purchase tidak ditemukan.');
            }

            $requested = collect(Arr::get($payload, 'items', []))
                ->keyBy('product_id');

            $ledgerRows = [];
            $totalReceivedValue = Decimal::money(0);
            $receivedCount = 0;

            foreach ($items as $item) {
                $remaining = Decimal::subtract($item->qty, $item->received_qty ?? 0, Decimal::QTY_SCALE);
                if (Decimal::compare($remaining, 0) <= 0) {
                    continue;
                }

                $receiveQty = $remaining;
                if ($requested->has($item->product_id)) {
                    $receiveQty = Decimal::qty($requested->get($item->product_id)['qty']);
                }

                if (Decimal::compare($receiveQty, 0) <= 0) {
                    continue;
                }

                if (Decimal::compare($receiveQty, $remaining) > 0) {
                    throw new RuntimeException('Qty received melebihi sisa untuk produk tertentu.');
                }

                $item->update([
                    'received_qty' => Decimal::add($item->received_qty ?? 0, $receiveQty, Decimal::QTY_SCALE),
                ]);

                InventoryBalance::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'product_id' => $item->product_id,
                        'location_id' => $purchase->location_id,
                    ],
                    [
                        'qty_on_hand' => 0,
                    ]
                );

                DB::update(
                    'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND location_id = ?',
                    [$receiveQty, $tenantId, $item->product_id, $purchase->location_id]
                );

                $balanceAfter = Decimal::qty(InventoryBalance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('location_id', $purchase->location_id)
                    ->when($item->product_variant_id, function ($query) use ($item) {
                        $query->where('product_variant_id', $item->product_variant_id);
                    }, function ($query) {
                        $query->whereNull('product_variant_id');
                    })
                    ->value('qty_on_hand'));

                $ledgerRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'location_id' => $purchase->location_id,
                    'reference_type' => 'purchase',
                    'reference_id' => $purchase->id,
                    'reference_item_id' => $item->id,
                    'qty_in' => $receiveQty,
                    'qty_out' => 0,
                    'balance_after' => $balanceAfter,
                    'created_at' => $now,
                    'created_by' => $userId,
                ];

                $valuationService->receive(
                    tenantId: $tenantId,
                    productId: $item->product_id,
                    productVariantId: $item->product_variant_id,
                    locationId: $purchase->location_id,
                    qty: $receiveQty,
                    unitCost: Decimal::float($item->price, Decimal::COST_SCALE),
                    sourceType: 'purchase',
                    sourceId: $purchase->id,
                    sourceItemId: $item->id,
                    at: $now
                );

                $totalReceivedValue = Decimal::add(
                    $totalReceivedValue,
                    Decimal::multiply($receiveQty, $item->price, Decimal::MONEY_SCALE),
                    Decimal::MONEY_SCALE
                );
                $receivedCount++;
            }

            if ($receivedCount === 0) {
                throw new RuntimeException('Tidak ada item yang diterima.');
            }

            StockLedger::query()->insert($ledgerRows);

            $this->createJournalEntries(
                tenantId: $tenantId,
                purchaseId: $purchase->id,
                total: Decimal::float($totalReceivedValue, Decimal::MONEY_SCALE),
                userId: $userId,
                now: $now,
                transactionDate: $transactionDate
            );

            $hasRemaining = PurchaseItem::query()
                ->where('tenant_id', $tenantId)
                ->where('purchase_id', $purchase->id)
                ->whereRaw('qty > received_qty')
                ->exists();

            $purchase->update([
                'status' => $hasRemaining ? 'partially_received' : 'received',
                'received_at' => $now,
                'received_by' => $userId,
                'transaction_date' => $transactionDate->toDateString(),
            ]);

            event(new PurchaseReceived(
                purchaseId: $purchase->id,
                tenantId: $tenantId,
                total: Decimal::float($totalReceivedValue, Decimal::MONEY_SCALE),
                actorId: $userId,
                occurredAt: $now->toIso8601String()
            ));

            return [
                'status' => $purchase->status,
                'received_total' => Decimal::float($totalReceivedValue, Decimal::MONEY_SCALE),
            ];
        });
    }

    private function createJournalEntries(
        string $tenantId,
        string $purchaseId,
        float $total,
        ?string $userId,
        CarbonImmutable $now,
        CarbonImmutable $transactionDate
    ): void {
        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $journalEntryId = (string) Str::uuid();

        JournalEntry::query()->create([
            'id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'reference_type' => 'purchase',
            'reference_id' => $purchaseId,
            'date' => $transactionDate->toDateString(),
            'description' => 'Purchase received',
            'status' => 'posted',
            'posted_at' => $now,
            'created_by' => $userId,
            'created_at' => $now,
        ]);

        $journalItems = [
            [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->inventory_account_id,
                'debit' => $total,
                'credit' => 0,
                'is_reversal' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $journalEntryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->payable_account_id,
                'debit' => 0,
                'credit' => $total,
                'is_reversal' => false,
            ],
        ];

        JournalItem::query()->insert($journalItems);
    }
}
