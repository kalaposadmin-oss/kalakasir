<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\StockLedger;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\InventoryValuationService;
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
            return [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'stock_transfer_id' => $transferId,
                'product_id' => $item['product_id'],
                'qty' => (float) $item['qty'],
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
                $qty = (float) $item->qty;
                $affected = DB::update(
                    'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND qty_on_hand >= ?',
                    [$qty, $tenantId, $item->product_id, $transfer->from_location_id, $qty]
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
                            'requested_qty' => $qty,
                        ],
                        referenceId: $transfer->id
                    );
                    throw new RuntimeException('Stok tidak mencukupi untuk transfer.');
                }

                $balanceAfter = (float) InventoryBalance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('location_id', $transfer->from_location_id)
                    ->value('qty_on_hand');

                $ledgerRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $item->product_id,
                    'location_id' => $transfer->from_location_id,
                    'reference_type' => 'transfer_out',
                    'reference_id' => $transfer->id,
                    'reference_item_id' => $item->id,
                    'qty_in' => 0,
                    'qty_out' => $qty,
                    'balance_after' => $balanceAfter,
                    'created_at' => $now,
                    'created_by' => $userId,
                ];

                $valuationResult = $valuationService->issue(
                    tenantId: $tenantId,
                    productId: $item->product_id,
                    locationId: $transfer->from_location_id,
                    qty: $qty,
                    referenceType: 'transfer_out',
                    referenceId: $transfer->id,
                    referenceItemId: $item->id,
                    at: $now
                );

                $item->update([
                    'unit_cost_snapshot' => (float) $valuationResult['unit_cost'],
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
                $qty = (float) $item->qty;

                InventoryBalance::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'product_id' => $item->product_id,
                        'location_id' => $transfer->to_location_id,
                    ],
                    [
                        'qty_on_hand' => 0,
                    ]
                );

                DB::update(
                    'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND location_id = ?',
                    [$qty, $tenantId, $item->product_id, $transfer->to_location_id]
                );

                $balanceAfter = (float) InventoryBalance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('location_id', $transfer->to_location_id)
                    ->value('qty_on_hand');

                $ledgerRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $item->product_id,
                    'location_id' => $transfer->to_location_id,
                    'reference_type' => 'transfer_in',
                    'reference_id' => $transfer->id,
                    'reference_item_id' => $item->id,
                    'qty_in' => $qty,
                    'qty_out' => 0,
                    'balance_after' => $balanceAfter,
                    'created_at' => $now,
                    'created_by' => $userId,
                ];

                $valuationService->receive(
                    tenantId: $tenantId,
                    productId: $item->product_id,
                    locationId: $transfer->to_location_id,
                    qty: $qty,
                    unitCost: (float) $item->unit_cost_snapshot,
                    sourceType: 'transfer_in',
                    sourceId: $transfer->id,
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
}
