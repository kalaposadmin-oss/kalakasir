<?php

namespace App\Services;

use App\Models\AccountMapping;
use App\Models\InventoryBalance;
use App\Models\InventoryCostState;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\Product;
use App\Models\StockLedger;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Support\Decimal;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class StockOpnameService
{
    public function createDraft(array $payload, ?string $userId): string
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $opnameDate = (new TransactionDateService())
            ->resolve(Arr::get($payload, 'opname_date'));
        $locationId = (string) Arr::get($payload, 'location_id');
        $now = CarbonImmutable::now('UTC');

        return DB::transaction(function () use ($payload, $tenantId, $userId, $opnameDate, $locationId, $now): string {
            $opnameId = (string) Str::uuid();

            StockOpname::query()->create([
                'id' => $opnameId,
                'tenant_id' => $tenantId,
                'location_id' => $locationId,
                'opname_date' => $opnameDate->toDateString(),
                'status' => StockOpname::STATUS_DRAFT,
                'notes' => Arr::get($payload, 'notes'),
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $items = $this->buildDraftItems($tenantId, $opnameId, $locationId, $now);
            if ($items === []) {
                throw new RuntimeException('Tidak ada produk aktif yang bisa dihitung untuk stock opname.');
            }

            StockOpnameItem::query()->insert($items);

            return $opnameId;
        });
    }

    public function updateItem(string $opnameId, string $itemId, array $payload): StockOpnameItem
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        return DB::transaction(function () use ($tenantId, $opnameId, $itemId, $payload): StockOpnameItem {
            $opname = StockOpname::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $opnameId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($opname->status !== StockOpname::STATUS_DRAFT) {
                throw new RuntimeException('Item tidak bisa diubah setelah stock opname difinalisasi atau dibatalkan.');
            }

            $item = StockOpnameItem::query()
                ->where('tenant_id', $tenantId)
                ->where('stock_opname_id', $opnameId)
                ->where('id', $itemId)
                ->lockForUpdate()
                ->firstOrFail();

            $physicalQty = Decimal::qty(Arr::get($payload, 'physical_qty'));
            $systemQty = Decimal::qty($item->system_qty);
            $differenceQty = Decimal::subtract($physicalQty, $systemQty, Decimal::QTY_SCALE);
            $differenceValue = Decimal::multiply($differenceQty, $item->unit_cost, Decimal::MONEY_SCALE);
            $notes = Arr::get($payload, 'notes', $item->notes);

            if ($this->requiresDifferenceNote($systemQty, $differenceQty) && trim((string) $notes) === '') {
                throw new RuntimeException('Catatan alasan wajib diisi untuk selisih stock opname yang besar.');
            }

            $item->update([
                'physical_qty' => $physicalQty,
                'difference_qty' => $differenceQty,
                'difference_value' => $differenceValue,
                'notes' => $notes,
            ]);

            return $item->refresh();
        });
    }

    public function finalize(string $opnameId, ?string $userId, bool $allowPartial = false): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $this->ensureActorAllowed($tenantId, $userId);

        app(DatabaseTransactionService::class)->run(function () use ($tenantId, $opnameId, $userId, $allowPartial): void {
            $now = CarbonImmutable::now('UTC');
            $valuationService = app(InventoryValuationService::class);

            $opname = StockOpname::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $opnameId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($opname->status !== StockOpname::STATUS_DRAFT) {
                throw new RuntimeException('Stock opname hanya bisa difinalisasi dari status draft.');
            }

            $this->assertAccountMappingReady($tenantId);

            $uncountedCount = StockOpnameItem::query()
                ->where('tenant_id', $tenantId)
                ->where('stock_opname_id', $opnameId)
                ->whereNull('physical_qty')
                ->count();

            if ($uncountedCount > 0 && ! $allowPartial) {
                throw new RuntimeException('Masih ada barang yang belum diisi stok fisiknya. Lengkapi semua item atau aktifkan opsi opname parsial.');
            }

            $opnameDate = CarbonImmutable::parse((string) $opname->opname_date, TenantContext::get()?->timezone ?? 'UTC')->startOfDay();
            (new LockPeriodService())->ensureNotLocked($opnameDate, $tenantId);

            $items = StockOpnameItem::query()
                ->where('tenant_id', $tenantId)
                ->where('stock_opname_id', $opnameId)
                ->whereNotNull('physical_qty')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw new RuntimeException('Isi stok fisik minimal untuk satu item sebelum finalisasi.');
            }

            $ledgerRows = [];
            $decreaseValue = Decimal::money(0);
            $increaseValue = Decimal::money(0);

            foreach ($items as $item) {
                $productVariantId = $item->product_variant_id ? (string) $item->product_variant_id : null;
                $currentSystemQty = Decimal::qty($this->getSystemQty(
                    tenantId: $tenantId,
                    productId: (string) $item->product_id,
                    productVariantId: $productVariantId,
                    locationId: (string) $opname->location_id
                ));
                $physicalQty = Decimal::qty($item->physical_qty);
                $differenceQty = Decimal::subtract($physicalQty, $currentSystemQty, Decimal::QTY_SCALE);
                $unitCost = Decimal::cost($this->resolveUnitCostSnapshot(
                    tenantId: $tenantId,
                    productId: (string) $item->product_id,
                    productVariantId: $productVariantId,
                    locationId: (string) $opname->location_id
                ));
                $absoluteQty = Decimal::abs($differenceQty, Decimal::QTY_SCALE);
                $movementValue = Decimal::money(0);

                if (Decimal::compare($physicalQty, 0) < 0) {
                    throw new RuntimeException('Qty fisik tidak boleh negatif.');
                }

                if ($this->requiresDifferenceNote($currentSystemQty, $differenceQty) && trim((string) $item->notes) === '') {
                    throw new RuntimeException('Catatan alasan wajib diisi untuk selisih stock opname yang besar.');
                }

                if (Decimal::compare($differenceQty, 0) < 0) {
                    $affected = $this->decrementInventory(
                        tenantId: $tenantId,
                        productId: (string) $item->product_id,
                        productVariantId: $productVariantId,
                        locationId: (string) $opname->location_id,
                        qty: Decimal::float($absoluteQty, Decimal::QTY_SCALE)
                    );

                    if ($affected === 0) {
                        throw new RuntimeException('Stok tidak mencukupi untuk finalisasi stock opname.');
                    }

                    $valuation = $valuationService->issue(
                        tenantId: $tenantId,
                        productId: (string) $item->product_id,
                        productVariantId: $productVariantId,
                        locationId: (string) $opname->location_id,
                        qty: Decimal::float($absoluteQty, Decimal::QTY_SCALE),
                        referenceType: 'stock_opname',
                        referenceId: (string) $opname->id,
                        referenceItemId: (string) $item->id,
                        at: $now
                    );

                    $unitCost = Decimal::cost($valuation['unit_cost'] ?? $unitCost);
                    $movementValue = Decimal::money($valuation['total_cost'] ?? 0);
                    $decreaseValue = Decimal::add($decreaseValue, $movementValue, Decimal::MONEY_SCALE);
                } elseif (Decimal::compare($differenceQty, 0) > 0) {
                    $this->ensureInventoryBalanceBucket(
                        tenantId: $tenantId,
                        productId: (string) $item->product_id,
                        productVariantId: $productVariantId,
                        locationId: (string) $opname->location_id
                    );
                    $this->incrementInventory(
                        tenantId: $tenantId,
                        productId: (string) $item->product_id,
                        productVariantId: $productVariantId,
                        locationId: (string) $opname->location_id,
                        qty: Decimal::float($differenceQty, Decimal::QTY_SCALE)
                    );

                    $valuationService->receive(
                        tenantId: $tenantId,
                        productId: (string) $item->product_id,
                        productVariantId: $productVariantId,
                        locationId: (string) $opname->location_id,
                        qty: Decimal::float($differenceQty, Decimal::QTY_SCALE),
                        unitCost: Decimal::float($unitCost, Decimal::COST_SCALE),
                        sourceType: 'stock_opname',
                        sourceId: (string) $opname->id,
                        sourceItemId: (string) $item->id,
                        at: $now
                    );

                    $movementValue = Decimal::multiply($differenceQty, $unitCost, Decimal::MONEY_SCALE);
                    $increaseValue = Decimal::add($increaseValue, $movementValue, Decimal::MONEY_SCALE);
                }

                $signedDifferenceValue = Decimal::compare($differenceQty, 0) < 0
                    ? Decimal::multiply('-1', $movementValue, Decimal::MONEY_SCALE)
                    : $movementValue;

                $item->update([
                    'system_qty' => $currentSystemQty,
                    'difference_qty' => $differenceQty,
                    'unit_cost' => $unitCost,
                    'difference_value' => $signedDifferenceValue,
                ]);

                if (Decimal::compare($differenceQty, 0) === 0) {
                    continue;
                }

                $balanceAfter = Decimal::qty(InventoryBalance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('location_id', $opname->location_id)
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
                    'location_id' => $opname->location_id,
                    'reference_type' => 'stock_opname',
                    'reference_id' => $opname->id,
                    'reference_item_id' => $item->id,
                    'qty_in' => Decimal::compare($differenceQty, 0) > 0 ? $differenceQty : Decimal::qty(0),
                    'qty_out' => Decimal::compare($differenceQty, 0) < 0 ? $absoluteQty : Decimal::qty(0),
                    'balance_after' => $balanceAfter,
                    'unit_cost' => Decimal::float($unitCost, Decimal::COST_SCALE),
                    'total_cost' => Decimal::float($movementValue, Decimal::MONEY_SCALE),
                    'created_at' => $now,
                    'created_by' => $userId,
                ];
            }

            if ($ledgerRows !== []) {
                StockLedger::query()->insert($ledgerRows);
            }

            $this->createJournalEntry(
                tenantId: $tenantId,
                opname: $opname,
                decreaseValue: $decreaseValue,
                increaseValue: $increaseValue,
                userId: $userId,
                now: $now
            );

            $opname->update([
                'status' => StockOpname::STATUS_FINALIZED,
                'finalized_by' => $userId,
                'finalized_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function cancel(string $opnameId, ?string $userId): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $now = CarbonImmutable::now('UTC');

        DB::transaction(function () use ($tenantId, $opnameId, $userId, $now): void {
            $opname = StockOpname::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $opnameId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($opname->status !== StockOpname::STATUS_DRAFT) {
                throw new RuntimeException('Stock opname hanya bisa dibatalkan saat masih draft.');
            }

            $opname->update([
                'status' => StockOpname::STATUS_CANCELLED,
                'cancelled_by' => $userId,
                'cancelled_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function finalizeCheck(string $opnameId, int $page = 1, int $perPage = 20): array
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));

        $opname = StockOpname::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $opnameId)
            ->firstOrFail();

        $totalCount = StockOpnameItem::query()
            ->where('tenant_id', $tenantId)
            ->where('stock_opname_id', $opnameId)
            ->count();

        $countedCount = StockOpnameItem::query()
            ->where('tenant_id', $tenantId)
            ->where('stock_opname_id', $opnameId)
            ->whereNotNull('physical_qty')
            ->count();

        $uncountedCount = $totalCount - $countedCount;
        $missingAccounts = $this->missingAccountMappings($tenantId);

        $changedQuery = null;
        if ($countedCount > 0) {
            $baseQuery = DB::table('stock_opname_items as soi')
                ->join('products as p', function ($join) {
                    $join->on('soi.product_id', '=', 'p.id')
                        ->on('soi.tenant_id', '=', 'p.tenant_id');
                })
                ->leftJoin('product_variants as pv', function ($join) {
                    $join->on('soi.product_variant_id', '=', 'pv.id')
                        ->on('soi.tenant_id', '=', 'pv.tenant_id');
                })
                ->leftJoin('inventory_balances as ib', function ($join) use ($opname) {
                    $join->on('ib.tenant_id', '=', 'soi.tenant_id')
                        ->on('ib.product_id', '=', 'soi.product_id')
                        ->where('ib.location_id', '=', $opname->location_id)
                        ->whereRaw('(ib.product_variant_id = soi.product_variant_id OR (ib.product_variant_id IS NULL AND soi.product_variant_id IS NULL))');
                })
                ->where('soi.tenant_id', $tenantId)
                ->where('soi.stock_opname_id', $opnameId)
                ->whereNotNull('soi.physical_qty');

            $changedQuery = (clone $baseQuery)
                ->whereRaw('coalesce(ib.qty_on_hand, 0) <> soi.system_qty');
        }

        $changedCount = $changedQuery ? (int) $changedQuery->count() : 0;
        $changedItems = [];

        if ($changedQuery && $changedCount > 0) {
            $rows = (clone $changedQuery)
                ->orderBy('p.name')
                ->orderBy('pv.name')
                ->forPage($page, $perPage)
                ->get([
                    'soi.id',
                    'soi.product_id',
                    'soi.product_variant_id',
                    'soi.system_qty',
                    'soi.physical_qty',
                    'p.name as product_name',
                    'p.sku as product_sku',
                    'pv.name as variant_name',
                    'pv.sku as variant_sku',
                    DB::raw('coalesce(ib.qty_on_hand, 0) as current_qty'),
                ]);

            $changedItems = $rows->map(function ($row) {
                return [
                    'id' => $row->id,
                    'product_id' => $row->product_id,
                    'product_variant_id' => $row->product_variant_id,
                    'product_name' => $row->product_name,
                    'sku' => $row->variant_sku ?: $row->product_sku,
                    'variant_name' => $row->variant_name,
                    'snapshot_qty' => (float) Decimal::qty($row->system_qty ?? 0),
                    'current_qty' => (float) Decimal::qty($row->current_qty ?? 0),
                    'physical_qty' => $row->physical_qty === null ? null : (float) $row->physical_qty,
                ];
            })->values()->all();
        }

        $hasMore = ($page * $perPage) < $changedCount;

        return [
            'status' => $opname->status,
            'total_count' => $totalCount,
            'counted_count' => $countedCount,
            'uncounted_count' => $uncountedCount,
            'missing_accounts' => $missingAccounts !== [],
            'missing_account_keys' => $missingAccounts,
            'missing_accounts_message' => $missingAccounts === []
                ? null
                : $this->missingAccountMessage(),
            'changed_count' => $changedCount,
            'changed_items' => $changedItems,
            'can_finalize' => $opname->status === StockOpname::STATUS_DRAFT && $countedCount > 0,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $changedCount,
            'has_more' => $hasMore,
        ];
    }

    private function buildDraftItems(string $tenantId, string $opnameId, string $locationId, CarbonImmutable $now): array
    {
        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('track_stock', true)
            ->orderBy('name')
            ->get(['id', 'hpp_average']);

        if ($products->isEmpty()) {
            return [];
        }

        $productIds = $products->pluck('id')->map(fn ($id) => (string) $id)->all();
        $variants = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'product_id'])
            ->groupBy('product_id');

        $balances = InventoryBalance::query()
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'product_variant_id', 'qty_on_hand'])
            ->mapWithKeys(fn ($row) => [
                $this->bucketKey((string) $row->product_id, $row->product_variant_id ? (string) $row->product_variant_id : null) => Decimal::qty($row->qty_on_hand),
            ]);

        $costStates = InventoryCostState::query()
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'product_variant_id', 'average_cost'])
            ->mapWithKeys(fn ($row) => [
                $this->bucketKey((string) $row->product_id, $row->product_variant_id ? (string) $row->product_variant_id : null) => Decimal::cost($row->average_cost),
            ]);

        $items = [];
        foreach ($products as $product) {
            $productVariants = $variants->get($product->id, collect());
            if ($productVariants->isEmpty()) {
                $items[] = $this->draftItemPayload(
                    tenantId: $tenantId,
                    opnameId: $opnameId,
                    productId: (string) $product->id,
                    productVariantId: null,
                    systemQty: $balances->get($this->bucketKey((string) $product->id, null), Decimal::qty(0)),
                    unitCost: $costStates->get($this->bucketKey((string) $product->id, null), Decimal::cost($product->hpp_average ?? 0)),
                    now: $now
                );

                continue;
            }

            foreach ($productVariants as $variant) {
                $variantId = (string) $variant->id;
                $items[] = $this->draftItemPayload(
                    tenantId: $tenantId,
                    opnameId: $opnameId,
                    productId: (string) $product->id,
                    productVariantId: $variantId,
                    systemQty: $balances->get($this->bucketKey((string) $product->id, $variantId), Decimal::qty(0)),
                    unitCost: $costStates->get($this->bucketKey((string) $product->id, $variantId), Decimal::cost($product->hpp_average ?? 0)),
                    now: $now
                );
            }
        }

        return $items;
    }

    private function draftItemPayload(
        string $tenantId,
        string $opnameId,
        string $productId,
        ?string $productVariantId,
        string $systemQty,
        string $unitCost,
        CarbonImmutable $now
    ): array {
        return [
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'stock_opname_id' => $opnameId,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'system_qty' => $systemQty,
            'physical_qty' => null,
            'difference_qty' => Decimal::qty(0),
            'unit_cost' => $unitCost,
            'difference_value' => Decimal::money(0),
            'created_at' => $now,
            'updated_at' => $now,
        ];
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

    private function resolveUnitCostSnapshot(string $tenantId, string $productId, ?string $productVariantId, string $locationId): float
    {
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

        if ($averageCost > 0) {
            return $averageCost;
        }

        return (float) Product::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $productId)
            ->value('hpp_average');
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

    private function createJournalEntry(
        string $tenantId,
        StockOpname $opname,
        string $decreaseValue,
        string $increaseValue,
        ?string $userId,
        CarbonImmutable $now
    ): void {
        if (Decimal::compare($decreaseValue, 0) <= 0 && Decimal::compare($increaseValue, 0) <= 0) {
            return;
        }

        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $mapping || ! $mapping->inventory_account_id || ! $mapping->expense_adjustment_account_id) {
            throw new RuntimeException($this->missingAccountMessage());
        }

        $entryId = (string) Str::uuid();
        JournalEntry::query()->create([
            'id' => $entryId,
            'tenant_id' => $tenantId,
            'reference_type' => 'stock_opname',
            'reference_id' => (string) $opname->id,
            'date' => CarbonImmutable::parse((string) $opname->opname_date)->toDateString(),
            'description' => 'Stock opname finalized',
            'status' => 'posted',
            'posted_at' => $now,
            'created_by' => $userId,
            'created_at' => $now,
        ]);

        $items = [];
        if (Decimal::compare($decreaseValue, 0) > 0) {
            $items[] = $this->journalItem($entryId, $tenantId, (string) $mapping->expense_adjustment_account_id, $decreaseValue, 0);
            $items[] = $this->journalItem($entryId, $tenantId, (string) $mapping->inventory_account_id, 0, $decreaseValue);
        }

        if (Decimal::compare($increaseValue, 0) > 0) {
            $items[] = $this->journalItem($entryId, $tenantId, (string) $mapping->inventory_account_id, $increaseValue, 0);
            // TODO: split inventory gain into a dedicated mapping when tenant accounting setup supports it.
            $items[] = $this->journalItem($entryId, $tenantId, (string) $mapping->expense_adjustment_account_id, 0, $increaseValue);
        }

        JournalItem::query()->insert($items);
    }

    private function journalItem(string $entryId, string $tenantId, string $accountId, mixed $debit, mixed $credit): array
    {
        return [
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $entryId,
            'tenant_id' => $tenantId,
            'account_id' => $accountId,
            'debit' => Decimal::float($debit, Decimal::MONEY_SCALE),
            'credit' => Decimal::float($credit, Decimal::MONEY_SCALE),
            'is_reversal' => false,
        ];
    }

    private function ensureActorAllowed(string $tenantId, ?string $userId): void
    {
        $role = app(UserRoleResolverService::class)->resolveNormalizedRole($tenantId, $userId);
        if (! in_array($role, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true)) {
            throw new RuntimeException('Role tidak berwenang finalisasi stock opname.');
        }
    }

    private function assertAccountMappingReady(string $tenantId): void
    {
        if ($this->missingAccountMappings($tenantId) !== []) {
            throw new RuntimeException($this->missingAccountMessage());
        }
    }

    private function missingAccountMappings(string $tenantId): array
    {
        $mapping = AccountMapping::query()
            ->where('tenant_id', $tenantId)
            ->first();

        $missing = [];

        if (! $mapping || ! $mapping->inventory_account_id) {
            $missing[] = 'inventory_account_id';
        }

        if (! $mapping || ! $mapping->expense_adjustment_account_id) {
            $missing[] = 'expense_adjustment_account_id';
        }

        return $missing;
    }

    private function missingAccountMessage(): string
    {
        return 'Finalisasi gagal: akun persediaan atau akun selisih stok belum dikonfigurasi. Silakan lengkapi pengaturan akun terlebih dahulu.';
    }

    private function requiresDifferenceNote(mixed $systemQty, mixed $differenceQty): bool
    {
        $absoluteDifference = Decimal::abs($differenceQty, Decimal::QTY_SCALE);
        if (Decimal::compare($absoluteDifference, 10) >= 0) {
            return true;
        }

        if (Decimal::compare($systemQty, 0) <= 0) {
            return Decimal::compare($absoluteDifference, 0) > 0;
        }

        $ratio = Decimal::divide($absoluteDifference, $systemQty, 6);

        return Decimal::compare($ratio, '0.25') >= 0;
    }

    private function bucketKey(string $productId, ?string $productVariantId): string
    {
        return $productId.'|'.($productVariantId ?: 'null');
    }
}
