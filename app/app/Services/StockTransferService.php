<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\StockLedger;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\InventoryValuationService;
use App\Support\Decimal;
use App\Support\TenantContext;
use App\Events\TransferShipped;
use App\Events\TransferReceived;
use App\Services\AnomalyService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class StockTransferService
{
    public function create(array $payload, ?string $userId): string
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        if ($payload['from_branch_id'] === $payload['to_branch_id']) {
            throw new RuntimeException('Branch asal dan tujuan tidak boleh sama.');
        }

        $fromLocation = $this->resolveLocation($payload['from_branch_id'], $payload['from_location_id'] ?? null);
        $toLocation = $this->resolveLocation($payload['to_branch_id'], $payload['to_location_id'] ?? null);

        if (! $fromLocation || ! $toLocation) {
            throw new RuntimeException('Lokasi transfer tidak ditemukan.');
        }

        $transferId = (string) Str::uuid();
        $now = CarbonImmutable::now('UTC');
        $transactionDate = (new TransactionDateService())
            ->resolve(Arr::get($payload, 'transaction_date'));

        StockTransfer::query()->create([
            'id' => $transferId,
            'tenant_id' => $tenantId,
            'from_branch_id' => $payload['from_branch_id'],
            'to_branch_id' => $payload['to_branch_id'],
            'from_location_id' => $fromLocation->id,
            'to_location_id' => $toLocation->id,
            'status' => 'draft',
            'reference' => $payload['reference'] ?? null,
            'created_by' => $userId,
            'created_at' => $now,
            'transaction_date' => $transactionDate->toDateString(),
        ]);

        $items = collect($payload['items'])->map(function ($item) use ($tenantId, $transferId) {
            $productVariantId = $this->resolveVariantId($tenantId, (string) $item['product_id'], Arr::get($item, 'product_variant_id'));

            return [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'stock_transfer_id' => $transferId,
                'product_id' => $item['product_id'],
                'product_variant_id' => $productVariantId,
                'qty' => Decimal::float(Decimal::qty($item['qty']), Decimal::QTY_SCALE),
            ];
        })->all();

        StockTransferItem::query()->insert($items);

        return $transferId;
    }

    public function approve(string $transferId, ?string $userId): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $transfer = StockTransfer::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $transferId)
            ->firstOrFail();

        if ($transfer->status !== 'draft') {
            throw new RuntimeException('Hanya transfer draft yang bisa di-approve.');
        }

        $transfer->update([
            'status' => 'approved',
            'approved_at' => CarbonImmutable::now('UTC'),
            'approved_by' => $userId,
        ]);
    }

    public function cancel(string $transferId, ?string $userId): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $transfer = StockTransfer::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $transferId)
            ->firstOrFail();

        if (! in_array($transfer->status, ['draft', 'approved'], true)) {
            throw new RuntimeException('Transfer tidak bisa dibatalkan.');
        }

        $transfer->update([
            'status' => 'cancelled',
            'cancelled_at' => CarbonImmutable::now('UTC'),
            'cancelled_by' => $userId,
        ]);
    }

    public function ship(string $transferId, ?string $userId, ?string $transactionDateInput = null): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $now = CarbonImmutable::now('UTC');
        $transactionDate = (new TransactionDateService())->resolve($transactionDateInput);
        (new LockPeriodService())->ensureNotLocked($transactionDate, $tenantId);

        app(DatabaseTransactionService::class)->run(function () use ($transferId, $tenantId, $userId, $now, $transactionDate): void {
            $valuationService = app(InventoryValuationService::class);

            $transfer = StockTransfer::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $transferId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transfer->status !== 'approved') {
                throw new RuntimeException('Transfer harus approved sebelum dikirim.');
            }

            $items = StockTransferItem::query()
                ->where('tenant_id', $tenantId)
                ->where('stock_transfer_id', $transfer->id)
                ->get();

            if ($items->isEmpty()) {
                throw new RuntimeException('Item transfer tidak ditemukan.');
            }

            $ledgerRows = [];

            foreach ($items as $item) {
                $qty = Decimal::qty($item->qty);
                $affected = $this->decrementInventory(
                    tenantId: $tenantId,
                    productId: (string) $item->product_id,
                    productVariantId: $item->product_variant_id ? (string) $item->product_variant_id : null,
                    locationId: (string) $transfer->from_location_id,
                    qty: Decimal::float($qty, Decimal::QTY_SCALE)
                );

                if ($affected === 0) {
                    app(AnomalyService::class)->raise(
                        tenantId: $tenantId,
                        severity: 'high',
                        type: 'negative_stock_attempt',
                        payload: [
                            'source' => 'transfer_ship',
                            'transfer_id' => $transfer->id,
                            'product_id' => $item->product_id,
                            'location_id' => $transfer->from_location_id,
                            'requested_qty' => Decimal::float($qty, Decimal::QTY_SCALE),
                        ],
                        referenceId: $transfer->id
                    );
                    throw new RuntimeException('Stok tidak mencukupi untuk transfer.');
                }

                $balanceAfter = Decimal::qty(InventoryBalance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('location_id', $transfer->from_location_id)
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
                    'location_id' => $transfer->from_location_id,
                    'reference_type' => 'transfer_out',
                    'reference_id' => $transfer->id,
                    'reference_item_id' => $item->id,
                    'qty_in' => 0,
                    'qty_out' => Decimal::float($qty, Decimal::QTY_SCALE),
                    'balance_after' => Decimal::float($balanceAfter, Decimal::QTY_SCALE),
                    'created_at' => $now,
                    'created_by' => $userId,
                ];

                $valuationResult = $valuationService->issue(
                    tenantId: $tenantId,
                    productId: $item->product_id,
                    productVariantId: $item->product_variant_id ? (string) $item->product_variant_id : null,
                    locationId: $transfer->from_location_id,
                    qty: Decimal::float($qty, Decimal::QTY_SCALE),
                    referenceType: 'transfer_out',
                    referenceId: $transfer->id,
                    referenceItemId: $item->id,
                    at: $now
                );

                $item->update([
                    'unit_cost_snapshot' => Decimal::float(Decimal::cost($valuationResult['unit_cost']), Decimal::COST_SCALE),
                ]);
            }

            StockLedger::query()->insert($ledgerRows);

            $transfer->update([
                'status' => 'in_transit',
                'shipped_at' => $now,
                'shipped_by' => $userId,
                'transaction_date' => $transactionDate->toDateString(),
            ]);

            event(new TransferShipped(
                transferId: $transfer->id,
                tenantId: $tenantId,
                actorId: $userId,
                occurredAt: $now->toIso8601String()
            ));
        });
    }

    public function receive(string $transferId, ?string $userId, ?string $transactionDateInput = null): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $now = CarbonImmutable::now('UTC');
        $transactionDate = (new TransactionDateService())->resolve($transactionDateInput);
        (new LockPeriodService())->ensureNotLocked($transactionDate, $tenantId);

        app(DatabaseTransactionService::class)->run(function () use ($transferId, $tenantId, $userId, $now, $transactionDate): void {
            $valuationService = app(InventoryValuationService::class);

            $transfer = StockTransfer::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $transferId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transfer->status !== 'in_transit') {
                throw new RuntimeException('Transfer harus in_transit sebelum diterima.');
            }

            $items = StockTransferItem::query()
                ->where('tenant_id', $tenantId)
                ->where('stock_transfer_id', $transfer->id)
                ->get();

            if ($items->isEmpty()) {
                throw new RuntimeException('Item transfer tidak ditemukan.');
            }

            $ledgerRows = [];

            foreach ($items as $item) {
                $qty = Decimal::qty($item->qty);
                $this->ensureInventoryBalanceBucket(
                    tenantId: $tenantId,
                    productId: (string) $item->product_id,
                    productVariantId: $item->product_variant_id ? (string) $item->product_variant_id : null,
                    locationId: (string) $transfer->to_location_id
                );

                $this->incrementInventory(
                    tenantId: $tenantId,
                    productId: (string) $item->product_id,
                    productVariantId: $item->product_variant_id ? (string) $item->product_variant_id : null,
                    locationId: (string) $transfer->to_location_id,
                    qty: Decimal::float($qty, Decimal::QTY_SCALE)
                );

                $balanceAfter = Decimal::qty(InventoryBalance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('location_id', $transfer->to_location_id)
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
                    'location_id' => $transfer->to_location_id,
                    'reference_type' => 'transfer_in',
                    'reference_id' => $transfer->id,
                    'reference_item_id' => $item->id,
                    'qty_in' => Decimal::float($qty, Decimal::QTY_SCALE),
                    'qty_out' => 0,
                    'balance_after' => Decimal::float($balanceAfter, Decimal::QTY_SCALE),
                    'created_at' => $now,
                    'created_by' => $userId,
                ];

                $valuationService->receive(
                    tenantId: $tenantId,
                    productId: $item->product_id,
                    productVariantId: $item->product_variant_id ? (string) $item->product_variant_id : null,
                    locationId: $transfer->to_location_id,
                    qty: Decimal::float($qty, Decimal::QTY_SCALE),
                    unitCost: Decimal::float(Decimal::cost($item->unit_cost_snapshot), Decimal::COST_SCALE),
                    sourceType: 'transfer_in',
                    sourceId: $transfer->id,
                    sourceItemId: null,
                    at: $now
                );
            }

            StockLedger::query()->insert($ledgerRows);

            $transfer->update([
                'status' => 'received',
                'received_at' => $now,
                'received_by' => $userId,
                'transaction_date' => $transactionDate->toDateString(),
            ]);

            event(new TransferReceived(
                transferId: $transfer->id,
                tenantId: $tenantId,
                actorId: $userId,
                occurredAt: $now->toIso8601String()
            ));
        });
    }

    public function close(string $transferId, ?string $userId): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $transfer = StockTransfer::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $transferId)
            ->firstOrFail();

        if ($transfer->status !== 'received') {
            throw new RuntimeException('Hanya transfer received yang bisa di-close.');
        }

        $transfer->update([
            'status' => 'closed',
            'closed_at' => CarbonImmutable::now('UTC'),
            'closed_by' => $userId,
        ]);
    }

    private function resolveLocation(string $branchId, ?string $locationId)
    {
        if ($locationId) {
            return Location::query()
                ->where('branch_id', $branchId)
                ->where('id', $locationId)
                ->first();
        }

        $location = Location::query()
            ->where('branch_id', $branchId)
            ->where('is_default', true)
            ->first();

        if ($location) {
            return $location;
        }

        return Location::query()
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->first();
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
                throw new RuntimeException('Varian produk transfer tidak valid.');
            }

            return $variantId;
        }

        $hasVariants = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->exists();

        if ($hasVariants) {
            throw new RuntimeException('Produk varian memerlukan product_variant_id pada transfer.');
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
}
