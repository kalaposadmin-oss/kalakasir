<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockLedger;
use App\Models\Product;
use App\Models\InventoryCostState;
use App\Services\LockPeriodService;
use App\Services\InventoryValuationService;
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

            StockAdjustment::query()->create([
                'id' => $adjustmentId,
                'tenant_id' => $tenantId,
                'location_id' => $payload['location_id'],
                'reference_no' => Arr::get($payload, 'reference_no'),
                'reason_code' => $payload['reason_code'],
                'reason' => $payload['reason_code'],
                'notes' => Arr::get($payload, 'notes'),
                'note' => Arr::get($payload, 'notes'),
                'status' => 'draft',
                'created_by' => $userId,
                'created_at' => $now,
                'transaction_date' => $transactionDate->toDateString(),
            ]);

            $items = collect($payload['items'])->map(function ($item) use ($tenantId, $adjustmentId, $payload) {
                $systemQty = $this->getSystemQty($tenantId, $item['product_id'], $payload['location_id']);
                $physicalQty = (float) $item['physical_qty'];
                $difference = $physicalQty - $systemQty;
                $product = Product::query()->where('id', $item['product_id'])->first();
                $unitCost = $this->resolveUnitCostSnapshot(
                    tenantId: $tenantId,
                    productId: $item['product_id'],
                    locationId: $payload['location_id'],
                    fallback: (float) ($product?->hpp_average ?? 0)
                );

                return [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'stock_adjustment_id' => $adjustmentId,
                    'product_id' => $item['product_id'],
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

            $adjustmentValue = (float) $items->sum(function ($item) {
                return abs((float) $item->difference_qty) * (float) $item->unit_cost_snapshot;
            });

            app(TransactionAuthorizationPolicyService::class)->ensureAdjustmentPostingAllowed(
                tenantId: $tenantId,
                actorUserId: $userId,
                amount: $adjustmentValue,
            );

            $ledgerRows = [];

            foreach ($items as $item) {
                $difference = (float) $item->difference_qty;

                if ($difference === 0.0) {
                    continue;
                }

                if ($item->physical_qty < 0) {
                    throw new RuntimeException('Qty fisik tidak boleh negatif.');
                }

                if ($difference < 0) {
                    $qty = abs($difference);
                    $affected = DB::update(
                        'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND qty_on_hand >= ?',
                        [$qty, $tenantId, $item->product_id, $adjustment->location_id, $qty]
                    );

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
                                'requested_qty' => $qty,
                            ],
                            referenceId: $adjustment->id
                        );
                        throw new RuntimeException('Stok tidak mencukupi untuk penyesuaian.');
                    }

                    $valuationService->issue(
                        tenantId: $tenantId,
                        productId: $item->product_id,
                        locationId: $adjustment->location_id,
                        qty: $qty,
                        referenceType: 'stock_adjustment',
                        referenceId: $adjustment->id,
                        referenceItemId: $item->id,
                        at: $now
                    );
                } else {
                    InventoryBalance::query()->firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'product_id' => $item->product_id,
                            'location_id' => $adjustment->location_id,
                        ],
                        [
                            'qty_on_hand' => 0,
                        ]
                    );

                    DB::update(
                        'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND location_id = ?',
                        [$difference, $tenantId, $item->product_id, $adjustment->location_id]
                    );

                    $valuationService->receive(
                        tenantId: $tenantId,
                        productId: $item->product_id,
                        locationId: $adjustment->location_id,
                        qty: $difference,
                        unitCost: (float) $item->unit_cost_snapshot,
                        sourceType: 'stock_adjustment',
                        sourceId: $adjustment->id,
                        at: $now
                    );
                }

                $balanceAfter = (float) InventoryBalance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('location_id', $adjustment->location_id)
                    ->value('qty_on_hand');

                $ledgerRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $item->product_id,
                    'location_id' => $adjustment->location_id,
                    'reference_type' => 'stock_adjustment',
                    'reference_id' => $adjustment->id,
                    'reference_item_id' => $item->id,
                    'qty_in' => $difference > 0 ? $difference : 0,
                    'qty_out' => $difference < 0 ? abs($difference) : 0,
                    'balance_after' => $balanceAfter,
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

    private function getSystemQty(string $tenantId, string $productId, string $locationId): float
    {
        return (float) InventoryBalance::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->value('qty_on_hand');
    }

    private function resolveUnitCostSnapshot(
        string $tenantId,
        string $productId,
        string $locationId,
        float $fallback
    ): float {
        $averageCost = (float) InventoryCostState::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->value('average_cost');

        return $averageCost > 0 ? $averageCost : $fallback;
    }

}
