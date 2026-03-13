<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchItem;
use App\Models\Product;
use App\Models\StockLedger;
use App\Services\AnomalyService;
use App\Services\InventoryValuationService;
use App\Services\LockPeriodService;
use App\Services\ProductItemTypeMapper;
use App\Services\TransactionDateService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductionBatchService
{
    public function createDraft(array $payload, ?string $userId): string
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $this->ensureRequiredLinesPresent($payload);

        $allProductIds = collect(array_merge(
            Arr::pluck($payload['inputs'], 'product_id'),
            Arr::pluck($payload['outputs'], 'product_id')
        ))->unique()->values()->all();

        $products = $this->loadProducts($tenantId, $allProductIds);

        $this->assertLineItemTypes($products, $payload['inputs'], 'raw_material', 'input');
        $this->assertLineItemTypes($products, $payload['outputs'], 'finished_good', 'output');

        $now = CarbonImmutable::now('UTC');
        $transactionDate = (new TransactionDateService())
            ->resolve(Arr::get($payload, 'transaction_date'));

        return DB::transaction(function () use ($payload, $tenantId, $userId, $now, $transactionDate): string {
            $batchId = (string) Str::uuid();

            ProductionBatch::query()->create([
                'id' => $batchId,
                'tenant_id' => $tenantId,
                'location_id' => $payload['location_id'],
                'reference_no' => Arr::get($payload, 'reference_no'),
                'status' => ProductionBatch::STATUS_DRAFT,
                'notes' => Arr::get($payload, 'notes'),
                'created_by' => $userId,
                'created_at' => $now,
                'transaction_date' => $transactionDate->toDateString(),
            ]);

            $rows = [];

            foreach ($payload['inputs'] as $line) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'production_batch_id' => $batchId,
                    'line_type' => 'input',
                    'product_id' => $line['product_id'],
                    'qty' => (float) $line['qty'],
                    'unit_cost_snapshot' => 0,
                ];
            }

            foreach ($payload['outputs'] as $line) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'production_batch_id' => $batchId,
                    'line_type' => 'output',
                    'product_id' => $line['product_id'],
                    'qty' => (float) $line['qty'],
                    'unit_cost_snapshot' => 0,
                ];
            }

            ProductionBatchItem::query()->insert($rows);

            return $batchId;
        });
    }

    public function post(string $batchId, ?string $userId, array $context = []): void
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant tidak ditemukan.');
        }

        $now = CarbonImmutable::now('UTC');

        app(DatabaseTransactionService::class)->run(function () use ($batchId, $tenantId, $userId, $now, $context): void {
            $valuationService = app(InventoryValuationService::class);

            $batch = ProductionBatch::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $batchId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($batch->status !== ProductionBatch::STATUS_DRAFT) {
                throw new RuntimeException('Batch produksi sudah diposting.');
            }

            $transactionDate = (new TransactionDateService())
                ->resolve(Arr::get($context, 'transaction_date', $batch->transaction_date));
            (new LockPeriodService())->ensureNotLocked($transactionDate, $tenantId);

            $items = ProductionBatchItem::query()
                ->where('tenant_id', $tenantId)
                ->where('production_batch_id', $batch->id)
                ->get();

            $inputs = $items->where('line_type', 'input')->values();
            $outputs = $items->where('line_type', 'output')->values();

            if ($inputs->isEmpty() || $outputs->isEmpty()) {
                throw new RuntimeException('Batch produksi harus memiliki input dan output.');
            }

            $ledgerRows = [];
            $totalInputCost = 0.0;

            foreach ($inputs as $item) {
                $qty = (float) $item->qty;

                $affected = DB::update(
                    'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND qty_on_hand >= ?',
                    [$qty, $tenantId, $item->product_id, $batch->location_id, $qty]
                );

                if ($affected === 0) {
                    app(AnomalyService::class)->raise(
                        tenantId: $tenantId,
                        severity: 'high',
                        type: 'negative_stock_attempt',
                        payload: [
                            'source' => 'production_batch_post',
                            'batch_id' => $batch->id,
                            'product_id' => $item->product_id,
                            'location_id' => $batch->location_id,
                            'requested_qty' => $qty,
                        ],
                        referenceId: $batch->id
                    );

                    throw new RuntimeException('Stok bahan baku tidak mencukupi.');
                }

                $valuation = $valuationService->issue(
                    tenantId: $tenantId,
                    productId: $item->product_id,
                    locationId: $batch->location_id,
                    qty: $qty,
                    referenceType: 'production_input',
                    referenceId: $batch->id,
                    referenceItemId: $item->id,
                    at: $now
                );

                $unitCost = (float) $valuation['unit_cost'];
                $item->update([
                    'unit_cost_snapshot' => $unitCost,
                ]);

                $totalInputCost += ((float) $valuation['total_cost']);

                $balanceAfter = (float) InventoryBalance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('location_id', $batch->location_id)
                    ->value('qty_on_hand');

                $ledgerRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $item->product_id,
                    'location_id' => $batch->location_id,
                    'reference_type' => 'production_input',
                    'reference_id' => $batch->id,
                    'reference_item_id' => $item->id,
                    'qty_in' => 0,
                    'qty_out' => $qty,
                    'balance_after' => $balanceAfter,
                    'unit_cost' => $unitCost,
                    'total_cost' => ((float) $valuation['total_cost']),
                    'created_at' => $now,
                    'created_by' => $userId,
                ];
            }

            $totalOutputQty = (float) $outputs->sum(function ($item) {
                return (float) $item->qty;
            });

            if ($totalOutputQty <= 0) {
                throw new RuntimeException('Total qty output harus lebih dari 0.');
            }

            $outputUnitCost = $totalInputCost > 0 ? ($totalInputCost / $totalOutputQty) : 0.0;

            foreach ($outputs as $item) {
                $qty = (float) $item->qty;

                DB::table('inventory_balances')->insertOrIgnore([
                    'tenant_id' => $tenantId,
                    'product_id' => $item->product_id,
                    'location_id' => $batch->location_id,
                    'qty_on_hand' => 0,
                ]);

                DB::update(
                    'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND location_id = ?',
                    [$qty, $tenantId, $item->product_id, $batch->location_id]
                );

                $valuationService->receive(
                    tenantId: $tenantId,
                    productId: $item->product_id,
                    locationId: $batch->location_id,
                    qty: $qty,
                    unitCost: $outputUnitCost,
                    sourceType: 'production_output',
                    sourceId: $batch->id,
                    at: $now
                );

                $item->update([
                    'unit_cost_snapshot' => $outputUnitCost,
                ]);

                $balanceAfter = (float) InventoryBalance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('location_id', $batch->location_id)
                    ->value('qty_on_hand');

                $ledgerRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $item->product_id,
                    'location_id' => $batch->location_id,
                    'reference_type' => 'production_output',
                    'reference_id' => $batch->id,
                    'reference_item_id' => $item->id,
                    'qty_in' => $qty,
                    'qty_out' => 0,
                    'balance_after' => $balanceAfter,
                    'unit_cost' => $outputUnitCost,
                    'total_cost' => ($outputUnitCost * $qty),
                    'created_at' => $now,
                    'created_by' => $userId,
                ];
            }

            StockLedger::query()->insert($ledgerRows);

            $batch->update([
                'status' => ProductionBatch::STATUS_POSTED,
                'posted_at' => $now,
                'posted_by' => $userId,
                'transaction_date' => $transactionDate->toDateString(),
            ]);
        });
    }

    private function ensureRequiredLinesPresent(array $payload): void
    {
        if (empty($payload['inputs']) || empty($payload['outputs'])) {
            throw new RuntimeException('Batch produksi harus memiliki input dan output.');
        }
    }

    private function loadProducts(string $tenantId, array $productIds): Collection
    {
        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $productIds)
            ->get(['id', 'item_type', 'product_mode'])
            ->keyBy('id');

        if ($products->count() !== count($productIds)) {
            throw new RuntimeException('Produk tidak ditemukan untuk tenant aktif.');
        }

        return $products;
    }

    private function assertLineItemTypes(Collection $products, array $lines, string $expectedItemType, string $label): void
    {
        foreach ($lines as $line) {
            $product = $products->get($line['product_id']);
            $resolvedType = ProductItemTypeMapper::resolve(
                $product?->item_type,
                (string) ($product?->product_mode ?? 'default')
            );

            if ($resolvedType !== $expectedItemType) {
                throw new RuntimeException(sprintf(
                    'Produk %s harus bertipe %s.',
                    $label,
                    $expectedItemType
                ));
            }
        }
    }
}