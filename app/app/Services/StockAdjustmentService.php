<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Models\InventoryBalance;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockLedger;
use App\Models\Product;
use App\Models\InventoryCostState;
use App\Services\LockPeriodService;
use App\Services\InventoryValuationService;
use App\Support\Decimal;
use App\Support\TenantContext;
use App\Events\AdjustmentPosted;
use App\Services\AnomalyService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class StockAdjustmentService
{
    public function createDraft(array $payload, ?string $userId): string
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $now = CarbonImmutable::now('UTC');
        $transactionDate = (new TransactionDateService())
            ->resolve(Arr::get($payload, 'transaction_date'));

        return DB::transaction(function () use ($payload, $tenantId, $userId, $now, $transactionDate): string {
            $adjustmentId = (string) Str::uuid();
            $firstPayloadItem = collect($payload['items'])->first();
            $firstProductId = (string) Arr::get($firstPayloadItem, 'product_id', '');
            $firstProductVariantId = $firstProductId !== ''
                ? $this->resolveVariantId($tenantId, $firstProductId, Arr::get($firstPayloadItem, 'product_variant_id'))
                : null;
            $firstSystemQty = $firstProductId !== ''
                ? $this->getSystemQty($tenantId, $firstProductId, $firstProductVariantId, $payload['location_id'])
                : 0.0;
            $firstPhysicalQty = Decimal::qty(Arr::get($firstPayloadItem, 'physical_qty', 0));

            StockAdjustment::query()->create([
                'id' => $adjustmentId,
                'tenant_id' => $tenantId,
                'product_id' => $firstProductId !== '' ? $firstProductId : null,
                'location_id' => $payload['location_id'],
                'reference_no' => Arr::get($payload, 'reference_no'),
                'reason_code' => $payload['reason_code'],
                'waste_type' => Arr::get($payload, 'waste_type'),
                'production_batch_id' => Arr::get($payload, 'production_batch_id'),
                'type' => 'multi_item',
                'qty' => Decimal::abs(Decimal::subtract($firstPhysicalQty, $firstSystemQty, Decimal::QTY_SCALE), Decimal::QTY_SCALE),
                'reason' => $payload['reason_code'],
                'notes' => Arr::get($payload, 'notes'),
                'note' => Arr::get($payload, 'notes'),
                'balance_before' => $firstSystemQty,
                'balance_after' => $firstPhysicalQty,
                'status' => 'draft',
                'created_by' => $userId,
                'created_at' => $now,
                'transaction_date' => $transactionDate->toDateString(),
            ]);

            $items = collect($payload['items'])->map(function ($item) use ($tenantId, $adjustmentId, $payload) {
                $productVariantId = $this->resolveVariantId($tenantId, (string) $item['product_id'], Arr::get($item, 'product_variant_id'));
                $systemQty = $this->getSystemQty($tenantId, $item['product_id'], $productVariantId, $payload['location_id']);
                $physicalQty = Decimal::qty($item['physical_qty']);
                $difference = Decimal::subtract($physicalQty, $systemQty, Decimal::QTY_SCALE);
                $product = Product::query()->where('id', $item['product_id'])->first();
                $unitCost = $this->resolveUnitCostSnapshot(
                    tenantId: $tenantId,
                    productId: $item['product_id'],
                    productVariantId: $productVariantId,
                    locationId: $payload['location_id'],
                    fallback: Decimal::float($product?->hpp_average ?? 0, Decimal::COST_SCALE)
                );

                return [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'stock_adjustment_id' => $adjustmentId,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $productVariantId,
                    'system_qty' => $systemQty,
                    'physical_qty' => $physicalQty,
                    'difference_qty' => $difference,
                    'unit_cost_snapshot' => $unitCost,
                ];
            })->all();

            StockAdjustmentItem::query()->insert($items);

            return $adjustmentId;
        });
    }

    public function approve(string $adjustmentId, ?string $userId): void
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $adjustment = StockAdjustment::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $adjustmentId)
            ->firstOrFail();

        if ($adjustment->status !== 'draft') {
            throw new RuntimeException('Hanya adjustment draft yang bisa di-approve.');
        }

        $adjustment->update([
            'status' => 'approved',
            'approved_at' => CarbonImmutable::now('UTC'),
            'approved_by' => $userId,
        ]);
    }

    public function post(string $adjustmentId, ?string $userId, array $context = []): void
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $now = CarbonImmutable::now('UTC');
        $transactionDate = (new TransactionDateService())
            ->resolve(Arr::get($context, 'transaction_date'));
        (new LockPeriodService())->ensureNotLocked($transactionDate, $tenantId);

        app(DatabaseTransactionService::class)->run(function () use ($adjustmentId, $tenantId, $userId, $now, $context, $transactionDate): void {
            $valuationService = app(InventoryValuationService::class);

            $adjustment = StockAdjustment::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $adjustmentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($adjustment->status !== 'approved') {
                throw new RuntimeException('Adjustment harus approved sebelum diposting.');
            }

            $items = StockAdjustmentItem::query()
                ->where('tenant_id', $tenantId)
                ->where('stock_adjustment_id', $adjustmentId)
                ->get();

            if ($items->isEmpty()) {
                throw new RuntimeException('Item adjustment tidak ditemukan.');
            }

            $adjustmentValue = Decimal::float($items->reduce(function (string $carry, $item) {
                return Decimal::add(
                    $carry,
                    Decimal::multiply(Decimal::abs($item->difference_qty, Decimal::QTY_SCALE), $item->unit_cost_snapshot, Decimal::MONEY_SCALE),
                    Decimal::MONEY_SCALE
                );
            }, Decimal::money(0)), Decimal::MONEY_SCALE);

            app(TransactionAuthorizationPolicyService::class)->ensureAdjustmentPostingAllowed(
                tenantId: $tenantId,
                actorUserId: $userId,
                amount: $adjustmentValue,
            );

            $ledgerRows = [];
            $decreaseValue = Decimal::money(0);
            $increaseValue = Decimal::money(0);

            foreach ($items as $item) {
                $difference = Decimal::qty($item->difference_qty);
                $productVariantId = $item->product_variant_id ? (string) $item->product_variant_id : null;
                $movementUnitCost = Decimal::cost($item->unit_cost_snapshot);
                $movementTotalCost = Decimal::money(0);

                if (Decimal::compare($difference, 0) === 0) {
                    continue;
                }

                if (Decimal::compare($item->physical_qty, 0) < 0) {
                    throw new RuntimeException('Qty fisik tidak boleh negatif.');
                }

                if (Decimal::compare($difference, 0) < 0) {
                    $qty = Decimal::abs($difference, Decimal::QTY_SCALE);
                    $affected = $this->decrementInventory($tenantId, (string) $item->product_id, $productVariantId, (string) $adjustment->location_id, $qty);

                    if ($affected === 0) {
                        app(AnomalyService::class)->raise(
                            tenantId: $tenantId,
                            severity: 'high',
                            type: 'negative_stock_attempt',
                            payload: [
                                'source' => 'stock_adjustment_post',
                                'adjustment_id' => $adjustment->id,
                                'product_id' => $item->product_id,
                                'location_id' => $adjustment->location_id,
                                'requested_qty' => Decimal::float($qty, Decimal::QTY_SCALE),
                            ],
                            referenceId: $adjustment->id
                        );
                        throw new RuntimeException('Stok tidak mencukupi untuk penyesuaian.');
                    }

                    $valuation = $valuationService->issue(
                        tenantId: $tenantId,
                        productId: $item->product_id,
                        productVariantId: $productVariantId,
                        locationId: $adjustment->location_id,
                        qty: Decimal::float($qty, Decimal::QTY_SCALE),
                        referenceType: 'stock_adjustment',
                        referenceId: $adjustment->id,
                        referenceItemId: $item->id,
                        at: $now
                    );

                    $movementUnitCost = Decimal::cost($valuation['unit_cost'] ?? $movementUnitCost);
                    $movementTotalCost = Decimal::money($valuation['total_cost'] ?? Decimal::multiply($qty, $movementUnitCost, Decimal::MONEY_SCALE));
                    $decreaseValue = Decimal::add($decreaseValue, $movementTotalCost, Decimal::MONEY_SCALE);
                } else {
                    $this->ensureInventoryBalanceBucket($tenantId, (string) $item->product_id, $productVariantId, (string) $adjustment->location_id);
                    $this->incrementInventory($tenantId, (string) $item->product_id, $productVariantId, (string) $adjustment->location_id, $difference);
                    $movementTotalCost = Decimal::multiply($difference, $movementUnitCost, Decimal::MONEY_SCALE);
                    $increaseValue = Decimal::add($increaseValue, $movementTotalCost, Decimal::MONEY_SCALE);

                    $valuationService->receive(
                        tenantId: $tenantId,
                        productId: $item->product_id,
                        productVariantId: $productVariantId,
                        locationId: $adjustment->location_id,
                        qty: Decimal::float($difference, Decimal::QTY_SCALE),
                        unitCost: Decimal::float($movementUnitCost, Decimal::COST_SCALE),
                        sourceType: 'stock_adjustment',
                        sourceId: $adjustment->id,
                        sourceItemId: (string) $item->id,
                        at: $now
                    );
                }

                $item->unit_cost_snapshot = $movementUnitCost;
                $item->save();

                $balanceAfter = Decimal::qty(InventoryBalance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('location_id', $adjustment->location_id)
                    ->when($productVariantId, function ($query) use ($productVariantId) {
                        $query->where('product_variant_id', $productVariantId);
                    }, function ($query) {
                        $query->whereNull('product_variant_id');
                    })
                    ->value('qty_on_hand'));

                $ledgerRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $productVariantId,
                    'location_id' => $adjustment->location_id,
                    'reference_type' => 'stock_adjustment',
                    'reference_id' => $adjustment->id,
                    'reference_item_id' => $item->id,
                    'qty_in' => Decimal::compare($difference, 0) > 0 ? $difference : Decimal::qty(0),
                    'qty_out' => Decimal::compare($difference, 0) < 0 ? Decimal::abs($difference, Decimal::QTY_SCALE) : Decimal::qty(0),
                    'balance_after' => $balanceAfter,
                    'unit_cost' => Decimal::float($movementUnitCost, Decimal::COST_SCALE),
                    'total_cost' => Decimal::float($movementTotalCost, Decimal::MONEY_SCALE),
                    'created_at' => $now,
                    'created_by' => $userId,
                ];

            }

            if (empty($ledgerRows)) {
                throw new RuntimeException('Tidak ada perbedaan stok untuk diposting.');
            }

            if (! empty($ledgerRows)) {
                StockLedger::query()->insert($ledgerRows);
            }

            $this->createJournalEntries(
                tenantId: $tenantId,
                adjustment: $adjustment,
                items: $items,
                userId: $userId,
                now: $now,
                transactionDate: $transactionDate,
                decreaseValue: $decreaseValue,
                increaseValue: $increaseValue,
            );

            $adjustment->update([
                'status' => 'posted',
                'posted_at' => $now,
                'transaction_date' => $transactionDate->toDateString(),
            ]);

            event(new AdjustmentPosted(
                adjustmentId: $adjustment->id,
                tenantId: $tenantId,
                actorId: $userId,
                ipAddress: Arr::get($context, 'ip_address'),
                userAgent: Arr::get($context, 'user_agent'),
                occurredAt: $now->toIso8601String()
            ));
        });
    }

    public function cancel(string $adjustmentId, ?string $userId): void
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $adjustment = StockAdjustment::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $adjustmentId)
            ->firstOrFail();

        if (! in_array($adjustment->status, ['draft', 'approved'], true)) {
            throw new RuntimeException('Adjustment tidak bisa dibatalkan.');
        }

        $adjustment->update([
            'status' => 'cancelled',
            'cancelled_by' => $userId,
            'cancelled_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    private function getSystemQty(string $tenantId, string $productId, ?string $productVariantId, string $locationId): float
    {
        return (float) InventoryBalance::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->when($productVariantId, function ($query) use ($productVariantId) {
                $query->where('product_variant_id', $productVariantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            })
            ->value('qty_on_hand');
    }

    private function resolveUnitCostSnapshot(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $locationId,
        float $fallback
    ): float {
        $averageCost = (float) InventoryCostState::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->when($productVariantId, function ($query) use ($productVariantId) {
                $query->where('product_variant_id', $productVariantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            })
            ->value('average_cost');

        return $averageCost > 0 ? $averageCost : $fallback;
    }

    private function resolveVariantId(string $tenantId, string $productId, ?string $variantId): ?string
    {
        if ($variantId) {
            $exists = DB::table('product_variants')
                ->where('tenant_id', $tenantId)
                ->where('id', $variantId)
                ->where('product_id', $productId)
                ->exists();

            if (! $exists) {
                throw new RuntimeException('Varian produk adjustment tidak valid.');
            }

            return $variantId;
        }

        $hasVariants = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->exists();

        if ($hasVariants) {
            throw new RuntimeException('Produk varian memerlukan product_variant_id pada stock adjustment.');
        }

        return null;
    }

    private function decrementInventory(string $tenantId, string $productId, ?string $productVariantId, string $locationId, float $qty): int
    {
        if ($productVariantId) {
            return DB::update(
                'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND product_variant_id = ? AND location_id = ? AND qty_on_hand >= ?',
                [$qty, $tenantId, $productId, $productVariantId, $locationId, $qty]
            );
        }

        return DB::update(
            'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND product_variant_id IS NULL AND qty_on_hand >= ?',
            [$qty, $tenantId, $productId, $locationId, $qty]
        );
    }

    private function ensureInventoryBalanceBucket(string $tenantId, string $productId, ?string $productVariantId, string $locationId): void
    {
        InventoryBalance::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'location_id' => $locationId,
            ],
            [
                'qty_on_hand' => 0,
            ]
        );
    }

    private function incrementInventory(string $tenantId, string $productId, ?string $productVariantId, string $locationId, float $qty): void
    {
        if ($productVariantId) {
            DB::update(
                'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND product_variant_id = ? AND location_id = ?',
                [$qty, $tenantId, $productId, $productVariantId, $locationId]
            );

            return;
        }

        DB::update(
            'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND product_variant_id IS NULL',
            [$qty, $tenantId, $productId, $locationId]
        );
    }

    private function createJournalEntries(
        string $tenantId,
        StockAdjustment $adjustment,
        iterable $items,
        ?string $userId,
        CarbonImmutable $now,
        CarbonImmutable $transactionDate,
        ?string $decreaseValue = null,
        ?string $increaseValue = null
    ): void {
        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $mapping || ! $mapping->inventory_account_id || ! $mapping->expense_adjustment_account_id) {
            throw new RuntimeException('Account mapping inventory/penyesuaian beban wajib diset sebelum posting stock adjustment.');
        }

        if ($decreaseValue === null || $increaseValue === null) {
            $decreaseValue = Decimal::money(0);
            $increaseValue = Decimal::money(0);

            foreach ($items as $item) {
                $lineValue = Decimal::multiply(
                    Decimal::abs($item->difference_qty, Decimal::QTY_SCALE),
                    $item->unit_cost_snapshot,
                    Decimal::MONEY_SCALE
                );

                if (Decimal::compare($item->difference_qty, 0) < 0) {
                    $decreaseValue = Decimal::add($decreaseValue, $lineValue, Decimal::MONEY_SCALE);
                }

                if (Decimal::compare($item->difference_qty, 0) > 0) {
                    $increaseValue = Decimal::add($increaseValue, $lineValue, Decimal::MONEY_SCALE);
                }
            }
        }

        $totalValue = Decimal::add($decreaseValue, $increaseValue, Decimal::MONEY_SCALE);
        if (Decimal::compare($totalValue, 0) <= 0) {
            return;
        }

        $entryId = (string) Str::uuid();
        JournalEntry::query()->create([
            'id' => $entryId,
            'tenant_id' => $tenantId,
            'reference_type' => $this->isStockOpname($adjustment) ? 'stock_opname' : 'stock_adjustment',
            'reference_id' => (string) $adjustment->id,
            'date' => $transactionDate->toDateString(),
            'description' => $this->journalDescription($adjustment),
            'status' => 'posted',
            'posted_at' => $now,
            'created_by' => $userId,
            'created_at' => $now,
        ]);

        $itemsToInsert = [];
        if (Decimal::compare($decreaseValue, 0) > 0) {
            $itemsToInsert[] = [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $entryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->expense_adjustment_account_id,
                'debit' => Decimal::float($decreaseValue, Decimal::MONEY_SCALE),
                'credit' => 0,
                'is_reversal' => false,
            ];
            $itemsToInsert[] = [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $entryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->inventory_account_id,
                'debit' => 0,
                'credit' => Decimal::float($decreaseValue, Decimal::MONEY_SCALE),
                'is_reversal' => false,
            ];
        }

        if (Decimal::compare($increaseValue, 0) > 0) {
            $itemsToInsert[] = [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $entryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->inventory_account_id,
                'debit' => Decimal::float($increaseValue, Decimal::MONEY_SCALE),
                'credit' => 0,
                'is_reversal' => false,
            ];
            $itemsToInsert[] = [
                'id' => (string) Str::uuid(),
                'journal_entry_id' => $entryId,
                'tenant_id' => $tenantId,
                'account_id' => $mapping->expense_adjustment_account_id,
                'debit' => 0,
                'credit' => Decimal::float($increaseValue, Decimal::MONEY_SCALE),
                'is_reversal' => false,
            ];
        }

        if ($itemsToInsert !== []) {
            JournalItem::query()->insert($itemsToInsert);
        }
    }

    private function isStockOpname(StockAdjustment $adjustment): bool
    {
        return in_array((string) $adjustment->reason_code, ['A01', 'A04'], true);
    }

    private function journalDescription(StockAdjustment $adjustment): string
    {
        $base = $this->isStockOpname($adjustment) ? 'Stock opname posted' : 'Stock adjustment posted';
        $reasonCode = trim((string) ($adjustment->reason_code ?? ''));

        return $reasonCode !== '' ? $base.' ['.$reasonCode.']' : $base;
    }

}
