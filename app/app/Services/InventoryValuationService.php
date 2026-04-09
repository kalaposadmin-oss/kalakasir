<?php

namespace App\Services;

use App\Exceptions\PurchaseReturnValuationException;
use App\Models\InventoryCostState;
use App\Models\InventoryIssueAllocation;
use App\Models\InventoryLayer;
use App\Models\Sale;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InventoryValuationService
{
    public function inventoryValueForScope(
        string $tenantId,
        string $locationId,
        ?string $productId = null,
        ?string $productVariantId = null
    ): float {
        $method = $this->methodForTenant($tenantId);

        if ($method === 'fifo') {
            $query = DB::table('inventory_layers')
                ->where('tenant_id', $tenantId)
                ->where('location_id', $locationId)
                ->where('qty_remaining', '>', 0);

            if ($productId !== null) {
                $query->where('product_id', $productId);
            }

            if ($productVariantId !== null) {
                $query->where('product_variant_id', $productVariantId);
            }

            if ($productVariantId === null) {
                return Decimal::float($query->selectRaw('COALESCE(SUM(qty_remaining * cost_per_unit), 0) as total')->value('total') ?? 0, Decimal::MONEY_SCALE);
            }

            return Decimal::float($query->selectRaw('COALESCE(SUM(qty_remaining * cost_per_unit), 0) as total')->value('total') ?? 0, Decimal::MONEY_SCALE);
        }

        $query = DB::table('inventory_cost_states')
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId);

        if ($productId !== null) {
            $query->where('product_id', $productId);
        }

        if ($productVariantId !== null) {
            $query->where('product_variant_id', $productVariantId);
        }

        if ($productVariantId === null) {
            return Decimal::float($query->selectRaw('COALESCE(SUM(qty_on_hand * average_cost), 0) as total')->value('total') ?? 0, Decimal::MONEY_SCALE);
        }

        return Decimal::float($query->selectRaw('COALESCE(SUM(qty_on_hand * average_cost), 0) as total')->value('total') ?? 0, Decimal::MONEY_SCALE);
    }

    public function methodForTenant(string $tenantId): string
    {
        $method = DB::table('tenants')
            ->where('id', $tenantId)
            ->value('inventory_valuation_method');

        return in_array($method, ['fifo', 'average'], true) ? $method : 'average';
    }

    public function receive(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $locationId,
        float $qty,
        float $unitCost,
        string $sourceType,
        string $sourceId,
        ?string $sourceItemId,
        \DateTimeInterface $at
    ): void {
        if ($qty <= 0) {
            return;
        }

        $method = $this->methodForTenant($tenantId);
        if ($method === 'fifo') {
            InventoryLayer::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'location_id' => $locationId,
                'qty_remaining' => $qty,
                'qty_original' => $qty,
                'cost_per_unit' => $unitCost,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_item_id' => $sourceItemId,
                'received_at' => $at,
                'created_at' => $at,
            ]);
        }

        $this->upsertCostStateIn(
            tenantId: $tenantId,
            productId: $productId,
            productVariantId: $productVariantId,
            locationId: $locationId,
            qty: $qty,
            unitCost: $unitCost,
            at: $at
        );
    }

    public function issue(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $locationId,
        float $qty,
        string $referenceType,
        string $referenceId,
        string $referenceItemId,
        \DateTimeInterface $at
    ): array {
        if ($qty <= 0) {
            return [
                'unit_cost' => 0.0,
                'total_cost' => 0.0,
            ];
        }

        if ($referenceItemId === '') {
            throw new RuntimeException('Issue valuation memerlukan reference_item_id.');
        }

        $method = $this->methodForTenant($tenantId);

        if ($method === 'fifo') {
            if ($referenceType === 'purchase_return') {
                return $this->issuePurchaseReturnFifo(
                    tenantId: $tenantId,
                    productId: $productId,
                    productVariantId: $productVariantId,
                    locationId: $locationId,
                    qty: $qty,
                    referenceId: $referenceId,
                    referenceItemId: $referenceItemId,
                    at: $at
                );
            }

            return $this->issueFifo(
                tenantId: $tenantId,
                productId: $productId,
                productVariantId: $productVariantId,
                locationId: $locationId,
                qty: $qty,
                referenceType: $referenceType,
                referenceId: $referenceId,
                referenceItemId: $referenceItemId,
                at: $at
            );
        }

        return $this->issueAverage(
            tenantId: $tenantId,
            productId: $productId,
            productVariantId: $productVariantId,
            locationId: $locationId,
            qty: $qty,
            referenceType: $referenceType,
            referenceId: $referenceId,
            referenceItemId: $referenceItemId,
            at: $at
        );
    }

    public function reverseIssue(
        string $tenantId,
        string $referenceType,
        string $referenceId,
        \DateTimeInterface $at,
        string $newSourceType,
        string $newSourceId
    ): void {
        $allocations = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenantId)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->whereRaw('qty > reversed_qty')
            ->orderBy('created_at')
            ->get();

        if ($allocations->isEmpty()) {
            return;
        }

        $this->reverseAllocations($tenantId, $allocations, null, $at, $newSourceType, $newSourceId);
    }

    public function reverseIssueItem(
        string $tenantId,
        string $referenceType,
        string $referenceId,
        string $referenceItemId,
        float $qty,
        \DateTimeInterface $at,
        string $newSourceType,
        string $newSourceId
    ): float {
        if ($qty <= 0) {
            return 0.0;
        }

        $allocations = InventoryIssueAllocation::query()
            ->where('tenant_id', $tenantId)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('reference_item_id', $referenceItemId)
            ->whereRaw('qty > reversed_qty')
            ->orderBy('created_at')
            ->get();

        if ($allocations->isEmpty()) {
            throw new RuntimeException('Alokasi valuation item refund tidak ditemukan.');
        }

        return $this->reverseAllocations($tenantId, $allocations, $qty, $at, $newSourceType, $newSourceId);
    }

    public function transferInUnitCost(string $tenantId, string $transferId, string $productId): float
    {
        throw new RuntimeException('transferInUnitCost harus di-refactor menjadi variant-aware sebelum digunakan kembali.');

        $row = DB::table('stock_transfer_items')
            ->where('tenant_id', $tenantId)
            ->where('stock_transfer_id', $transferId)
            ->where('product_id', $productId)
            ->first(['unit_cost_snapshot']);

        return Decimal::float($row->unit_cost_snapshot ?? 0, Decimal::COST_SCALE);
    }

    private function issueFifo(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $locationId,
        float $qty,
        string $referenceType,
        string $referenceId,
        string $referenceItemId,
        \DateTimeInterface $at
    ): array {
        $remaining = Decimal::qty($qty);
        $totalCost = Decimal::money(0);

        $layersQuery = InventoryLayer::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId);

        $layers = $this->applyVariantScope($layersQuery, $productVariantId)
            ->where('qty_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        if ($layers->isEmpty()) {
            throw new RuntimeException('Layer FIFO tidak mencukupi.');
        }

        foreach ($layers as $layer) {
            if (Decimal::compare($remaining, 0) <= 0) {
                break;
            }

            $available = Decimal::qty($layer->qty_remaining);
            if (Decimal::compare($available, 0) <= 0) {
                continue;
            }

            $consume = Decimal::min($available, $remaining, Decimal::QTY_SCALE);
            $unitCost = Decimal::cost($layer->cost_per_unit);
            $cost = Decimal::multiply($consume, $unitCost, Decimal::MONEY_SCALE);

            $layer->update([
                'qty_remaining' => Decimal::subtract($available, $consume, Decimal::QTY_SCALE),
            ]);

            InventoryIssueAllocation::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'location_id' => $locationId,
                'inventory_layer_id' => $layer->id,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_item_id' => $referenceItemId,
                'qty' => $consume,
                'unit_cost' => $unitCost,
                'total_cost' => $cost,
                'created_at' => $at,
            ]);

            $remaining = Decimal::subtract($remaining, $consume, Decimal::QTY_SCALE);
            $totalCost = Decimal::add($totalCost, $cost, Decimal::MONEY_SCALE);
        }

        if (Decimal::compare($remaining, 0) > 0) {
            throw new RuntimeException('Layer FIFO tidak mencukupi.');
        }

        $unitCost = Decimal::compare($qty, 0) > 0
            ? Decimal::divide($totalCost, $qty, Decimal::COST_SCALE)
            : Decimal::cost(0);

        $this->upsertCostStateOut(
            tenantId: $tenantId,
            productId: $productId,
            productVariantId: $productVariantId,
            locationId: $locationId,
            qty: $qty,
            issueUnitCost: $unitCost,
            at: $at
        );

        return [
            'unit_cost' => Decimal::float($unitCost, Decimal::COST_SCALE),
            'total_cost' => Decimal::float($totalCost, Decimal::MONEY_SCALE),
        ];
    }

    private function issueAverage(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $locationId,
        float $qty,
        string $referenceType,
        string $referenceId,
        string $referenceItemId,
        \DateTimeInterface $at
    ): array {
        $stateQuery = InventoryCostState::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId);

        $state = $this->applyVariantScope($stateQuery, $productVariantId)
            ->lockForUpdate()
            ->first();

        $unitCost = Decimal::cost($state?->average_cost ?? 0);
        $totalCost = Decimal::multiply($qty, $unitCost, Decimal::MONEY_SCALE);

        InventoryIssueAllocation::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'location_id' => $locationId,
            'inventory_layer_id' => null,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference_item_id' => $referenceItemId,
            'qty' => Decimal::qty($qty),
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'created_at' => $at,
        ]);

        $this->upsertCostStateOut(
            tenantId: $tenantId,
            productId: $productId,
            productVariantId: $productVariantId,
            locationId: $locationId,
            qty: $qty,
            issueUnitCost: $unitCost,
            at: $at
        );

        return [
            'unit_cost' => Decimal::float($unitCost, Decimal::COST_SCALE),
            'total_cost' => Decimal::float($totalCost, Decimal::MONEY_SCALE),
        ];
    }

    private function reverseAllocations(
        string $tenantId,
        iterable $allocations,
        ?float $limitQty,
        \DateTimeInterface $at,
        string $newSourceType,
        string $newSourceId
    ): float {
        $remaining = $limitQty === null ? null : Decimal::qty($limitQty);
        $reversedTotal = Decimal::money(0);
        $method = $this->methodForTenant($tenantId);

        foreach ($allocations as $allocation) {
            $availableToReverse = Decimal::max(
                Decimal::subtract($allocation->qty, $allocation->reversed_qty ?? 0, Decimal::QTY_SCALE),
                0,
                Decimal::QTY_SCALE
            );
            if (Decimal::compare($availableToReverse, 0) <= 0) {
                continue;
            }

            $qtyToReverse = $remaining === null
                ? $availableToReverse
                : Decimal::min($availableToReverse, $remaining, Decimal::QTY_SCALE);

            if (Decimal::compare($qtyToReverse, 0) <= 0) {
                continue;
            }

            $dimensions = $this->allocationDimensions($allocation);

            if ($method === 'fifo' && $allocation->inventory_layer_id) {
                $updated = InventoryLayer::query()
                    ->where('tenant_id', $tenantId)
                    ->where('id', $allocation->inventory_layer_id)
                    ->lockForUpdate()
                    ->update([
                        'qty_remaining' => DB::raw('qty_remaining + '.$qtyToReverse),
                    ]);

                if ($updated === 0) {
                    $this->receive(
                        tenantId: $tenantId,
                        productId: $dimensions['product_id'],
                        productVariantId: $dimensions['product_variant_id'],
                        locationId: $dimensions['location_id'],
                        qty: Decimal::float($qtyToReverse, Decimal::QTY_SCALE),
                        unitCost: Decimal::float($allocation->unit_cost, Decimal::COST_SCALE),
                        sourceType: $newSourceType,
                        sourceId: $newSourceId,
                        sourceItemId: null,
                        at: $at
                    );
                } else {
                    $this->upsertCostStateIn(
                        tenantId: $tenantId,
                        productId: $dimensions['product_id'],
                        productVariantId: $dimensions['product_variant_id'],
                        locationId: $dimensions['location_id'],
                        qty: Decimal::float($qtyToReverse, Decimal::QTY_SCALE),
                        unitCost: Decimal::float($allocation->unit_cost, Decimal::COST_SCALE),
                        at: $at
                    );
                }
            } else {
                $this->receive(
                    tenantId: $tenantId,
                    productId: $dimensions['product_id'],
                    productVariantId: $dimensions['product_variant_id'],
                    locationId: $dimensions['location_id'],
                    qty: Decimal::float($qtyToReverse, Decimal::QTY_SCALE),
                    unitCost: Decimal::float($allocation->unit_cost, Decimal::COST_SCALE),
                    sourceType: $newSourceType,
                    sourceId: $newSourceId,
                    sourceItemId: null,
                    at: $at
                );
            }

            $newReversedQty = Decimal::add($allocation->reversed_qty ?? 0, $qtyToReverse, Decimal::QTY_SCALE);

            InventoryIssueAllocation::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $allocation->id)
                ->update([
                    'reversed_qty' => $newReversedQty,
                    'reversed_at' => Decimal::compare($newReversedQty, $allocation->qty) >= 0 ? $at : null,
                ]);

            $reversedTotal = Decimal::add(
                $reversedTotal,
                Decimal::multiply($qtyToReverse, $allocation->unit_cost, Decimal::MONEY_SCALE),
                Decimal::MONEY_SCALE
            );

            if ($remaining !== null) {
                $remaining = Decimal::subtract($remaining, $qtyToReverse, Decimal::QTY_SCALE);
                if (Decimal::compare($remaining, 0) <= 0) {
                    break;
                }
            }
        }

        if ($remaining !== null && Decimal::compare($remaining, 0) > 0) {
            throw new RuntimeException('Alokasi valuation tidak mencukupi untuk refund parsial.');
        }

        return Decimal::float($reversedTotal, Decimal::MONEY_SCALE);
    }

    private function allocationDimensions(object $allocation): array
    {
        $productId = $allocation->product_id ? (string) $allocation->product_id : '';
        $locationId = $allocation->location_id ? (string) $allocation->location_id : '';
        $productVariantId = $allocation->product_variant_id ? (string) $allocation->product_variant_id : null;

        if ($productId === '' || $locationId === '') {
            throw new RuntimeException('Dimensi allocation valuation tidak lengkap untuk reversal.');
        }

        return [
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'location_id' => $locationId,
        ];
    }

    public function upsertCostStateIn(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $locationId,
        float $qty,
        float $unitCost,
        \DateTimeInterface $at
    ): void {
        $stateQuery = InventoryCostState::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId);

        $state = $this->applyVariantScope($stateQuery, $productVariantId)
            ->lockForUpdate()
            ->first();

        if (! $state) {
            InventoryCostState::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'location_id' => $locationId,
                'qty_on_hand' => Decimal::qty($qty),
                'average_cost' => Decimal::cost($unitCost),
                'last_cost' => Decimal::cost($unitCost),
                'updated_at' => $at,
            ]);

            return;
        }

        $oldQty = Decimal::qty($state->qty_on_hand);
        $oldAvg = Decimal::cost($state->average_cost);
        $newQty = Decimal::add($oldQty, $qty, Decimal::QTY_SCALE);

        $newAvg = Decimal::compare($newQty, 0) > 0
            ? Decimal::divide(
                Decimal::add(
                    Decimal::multiply($oldQty, $oldAvg, Decimal::COST_SCALE),
                    Decimal::multiply($qty, $unitCost, Decimal::COST_SCALE),
                    Decimal::COST_SCALE
                ),
                $newQty,
                Decimal::COST_SCALE
            )
            : Decimal::cost($unitCost);

        $state->update([
            'qty_on_hand' => $newQty,
            'average_cost' => $newAvg,
            'last_cost' => Decimal::cost($unitCost),
            'updated_at' => $at,
        ]);
    }

    public function upsertCostStateOut(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $locationId,
        float $qty,
        float $issueUnitCost,
        \DateTimeInterface $at
    ): void {
        $stateQuery = InventoryCostState::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId);

        $state = $this->applyVariantScope($stateQuery, $productVariantId)
            ->lockForUpdate()
            ->first();

        if (! $state) {
            InventoryCostState::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'location_id' => $locationId,
                'qty_on_hand' => Decimal::max(0, Decimal::subtract(0, $qty, Decimal::QTY_SCALE), Decimal::QTY_SCALE),
                'average_cost' => Decimal::cost($issueUnitCost),
                'last_cost' => Decimal::cost($issueUnitCost),
                'updated_at' => $at,
            ]);

            return;
        }

        $newQty = Decimal::max(0, Decimal::subtract($state->qty_on_hand, $qty, Decimal::QTY_SCALE), Decimal::QTY_SCALE);

        $state->update([
            'qty_on_hand' => $newQty,
            'last_cost' => Decimal::cost($issueUnitCost),
            'updated_at' => $at,
        ]);
    }

    public function replayFromLedger(string $tenantId): void
    {
        InventoryIssueAllocation::query()->where('tenant_id', $tenantId)->delete();
        InventoryLayer::query()->where('tenant_id', $tenantId)->delete();
        InventoryCostState::query()->where('tenant_id', $tenantId)->delete();

        app(ValuationAuditService::class)->log($tenantId, 'valuation_replay_started', [
            'source' => 'InventoryValuationService.replayFromLedger',
        ]);

        DB::table('sale_items')
            ->where('tenant_id', $tenantId)
            ->update(['hpp_snapshot' => 0]);

        $rows = DB::table('stock_ledger')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->select([
                'id',
                'product_id',
                'product_variant_id',
                'location_id',
                'reference_type',
                'reference_id',
                'reference_item_id',
                'qty_in',
                'qty_out',
                'unit_cost',
                'created_at',
            ])
            ->cursor();

        foreach ($rows as $row) {
            $at = new \DateTimeImmutable((string) $row->created_at);
            $qtyIn = Decimal::float($row->qty_in, Decimal::QTY_SCALE);
            $qtyOut = Decimal::float($row->qty_out, Decimal::QTY_SCALE);

            $referenceContext = $this->resolveReplayReferenceContext($tenantId, $row);
            $referenceItemId = $referenceContext['reference_item_id'];
            $productVariantId = $referenceContext['product_variant_id'];

            if ($qtyIn > 0) {
                $unitCost = $this->inferInboundUnitCost(
                    tenantId: $tenantId,
                    productId: (string) $row->product_id,
                    productVariantId: $productVariantId,
                    referenceType: (string) $row->reference_type,
                    referenceId: (string) $row->reference_id,
                    referenceItemId: $referenceItemId,
                    ledgerUnitCost: Decimal::float($row->unit_cost ?? 0, Decimal::COST_SCALE)
                );

                $this->receive(
                    tenantId: $tenantId,
                    productId: (string) $row->product_id,
                    productVariantId: $productVariantId,
                    locationId: (string) $row->location_id,
                    qty: $qtyIn,
                    unitCost: $unitCost,
                    sourceType: (string) $row->reference_type,
                    sourceId: (string) $row->reference_id,
                    sourceItemId: $row->reference_type === 'purchase' ? $referenceItemId : null,
                    at: $at
                );
            }

            if ($qtyOut > 0) {
                if (! $referenceItemId) {
                    $this->reportReplayFailure(
                        tenantId: $tenantId,
                        type: 'valuation_replay_missing_reference_item',
                        referenceId: (string) $row->id,
                        payload: $this->replayRowContext($row, [
                            'message' => 'Replay qty_out memerlukan reference_item_id yang tidak ambigu.',
                        ])
                    );

                    throw new RuntimeException('Replay valuation gagal: reference_item_id tidak tersedia atau ambigu untuk qty_out.');
                }

                $result = $this->issue(
                    tenantId: $tenantId,
                    productId: (string) $row->product_id,
                    productVariantId: $productVariantId,
                    locationId: (string) $row->location_id,
                    qty: $qtyOut,
                    referenceType: (string) $row->reference_type,
                    referenceId: (string) $row->reference_id,
                    referenceItemId: (string) $referenceItemId,
                    at: $at
                );

                if ($row->reference_type === 'sale' && $referenceItemId) {
                    DB::table('sale_items')
                        ->where('tenant_id', $tenantId)
                        ->where('id', $referenceItemId)
                        ->update([
                            'hpp_snapshot' => Decimal::money($result['unit_cost']),
                        ]);
                }
            }
        }

        $this->syncSaleJournalCogsFromSnapshots($tenantId);

        app(ValuationAuditService::class)->log($tenantId, 'valuation_replay_completed', [
            'source' => 'InventoryValuationService.replayFromLedger',
        ]);
    }

    private function applyVariantScope($query, ?string $productVariantId)
    {
        if ($productVariantId !== null) {
            return $query->where('product_variant_id', $productVariantId);
        }

        return $query->whereNull('product_variant_id');
    }

    private function applyVariantScopeToTable($query, ?string $productVariantId, string $column)
    {
        if ($productVariantId !== null) {
            return $query->where($column, $productVariantId);
        }

        return $query->whereNull($column);
    }

    private function productHasVariants(string $tenantId, string $productId): bool
    {
        return DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->exists();
    }

    private function resolveReplayReferenceContext(string $tenantId, object $row): array
    {
        $productId = (string) $row->product_id;
        $referenceType = (string) $row->reference_type;
        $referenceId = (string) $row->reference_id;
        $referenceItemId = $row->reference_item_id ? (string) $row->reference_item_id : null;
        $ledgerVariantId = $row->product_variant_id ? (string) $row->product_variant_id : null;

        if ($referenceType === 'purchase' && $referenceItemId === null) {
            $resolution = $this->resolveLegacyPurchaseItemId(
                tenantId: $tenantId,
                purchaseId: $referenceId,
                productId: $productId,
                productVariantId: $ledgerVariantId,
                ledgerUnitCost: Decimal::float($row->unit_cost ?? 0, Decimal::COST_SCALE)
            );

            if (($resolution['status'] ?? null) === 'resolved') {
                DB::table('stock_ledger')
                    ->where('id', $row->id)
                    ->update([
                        'reference_item_id' => $resolution['reference_item_id'],
                        'product_variant_id' => $resolution['product_variant_id'],
                    ]);

                app(ValuationAuditService::class)->log($tenantId, 'valuation_replay_legacy_purchase_item_resolved', $this->replayRowContext($row, [
                    'resolved_reference_item_id' => $resolution['reference_item_id'],
                    'resolved_product_variant_id' => $resolution['product_variant_id'],
                ]));

                return [
                    'product_variant_id' => $resolution['product_variant_id'],
                    'reference_item_id' => $resolution['reference_item_id'],
                ];
            }

            $this->reportReplayFailure(
                tenantId: $tenantId,
                type: 'valuation_replay_legacy_ambiguous',
                referenceId: (string) $row->id,
                payload: $this->replayRowContext($row, $resolution)
            );

            throw new RuntimeException('Replay valuation gagal: purchase legacy ambigu dan tidak bisa dipetakan aman.');
        }

        if ($ledgerVariantId !== null) {
            return [
                'product_variant_id' => $ledgerVariantId,
                'reference_item_id' => $referenceItemId,
            ];
        }

        if (! $this->productHasVariants($tenantId, $productId)) {
            return [
                'product_variant_id' => null,
                'reference_item_id' => $referenceItemId,
            ];
        }

        if ($referenceType === 'sale' && $referenceItemId === null) {
            $resolution = $this->resolveLegacySaleItemId($tenantId, $referenceId, $productId);

            if (($resolution['status'] ?? null) === 'resolved') {
                DB::table('stock_ledger')
                    ->where('id', $row->id)
                    ->update([
                        'reference_item_id' => $resolution['reference_item_id'],
                        'product_variant_id' => $resolution['product_variant_id'],
                    ]);

                app(ValuationAuditService::class)->log($tenantId, 'valuation_replay_legacy_sale_item_resolved', $this->replayRowContext($row, [
                    'resolved_reference_item_id' => $resolution['reference_item_id'],
                    'resolved_product_variant_id' => $resolution['product_variant_id'],
                ]));

                return [
                    'product_variant_id' => $resolution['product_variant_id'],
                    'reference_item_id' => $resolution['reference_item_id'],
                ];
            }

            $this->reportReplayFailure(
                tenantId: $tenantId,
                type: 'valuation_replay_legacy_ambiguous',
                referenceId: (string) $row->id,
                payload: $this->replayRowContext($row, $resolution)
            );

            throw new RuntimeException('Replay valuation gagal: sale legacy ambigu dan tidak bisa dipetakan aman.');
        }

        $sourceVariantId = $this->resolveSourceItemVariantId(
            tenantId: $tenantId,
            referenceType: $referenceType,
            referenceId: $referenceId,
            referenceItemId: $referenceItemId,
            productId: $productId
        );

        $this->reportReplayFailure(
            tenantId: $tenantId,
            type: 'valuation_replay_missing_variant_context',
            referenceId: (string) $row->id,
            payload: $this->replayRowContext($row, [
                'source_item_variant_id' => $sourceVariantId,
                'message' => 'Stock ledger row untuk produk bervarian tidak menyimpan product_variant_id yang valid.',
            ])
        );

        throw new RuntimeException('Replay valuation gagal: missing variant context untuk row produk bervarian.');
    }

    private function syncSaleJournalCogsFromSnapshots(string $tenantId): void
    {
        $mapping = DB::table('account_mappings')
            ->where('tenant_id', $tenantId)
            ->first(['cogs_account_id', 'inventory_account_id']);

        if (! $mapping || ! $mapping->cogs_account_id || ! $mapping->inventory_account_id) {
            return;
        }

        $sales = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->select('id')
            ->cursor();

        foreach ($sales as $sale) {
            $saleId = (string) $sale->id;

            $cogsTotal = Decimal::money(DB::table('sale_items')
                ->where('tenant_id', $tenantId)
                ->where('sale_id', $saleId)
                ->selectRaw('sum(qty * hpp_snapshot) as total')
                ->value('total'));

            $entryIds = DB::table('journal_entries')
                ->where('tenant_id', $tenantId)
                ->where('reference_type', 'sale')
                ->where('reference_id', $saleId)
                ->pluck('id');

            if ($entryIds->isEmpty()) {
                continue;
            }

            foreach ($entryIds as $entryId) {
                if (Decimal::compare($cogsTotal, 0) <= 0) {
                    DB::table('journal_items')
                        ->where('tenant_id', $tenantId)
                        ->where('journal_entry_id', $entryId)
                        ->whereIn('account_id', [$mapping->cogs_account_id, $mapping->inventory_account_id])
                        ->where('is_reversal', false)
                        ->delete();

                    continue;
                }

                $roundedCogs = Decimal::money($cogsTotal);

                $updatedCogs = DB::table('journal_items')
                    ->where('tenant_id', $tenantId)
                    ->where('journal_entry_id', $entryId)
                    ->where('account_id', $mapping->cogs_account_id)
                    ->where('is_reversal', false)
                    ->update([
                        'debit' => $roundedCogs,
                        'credit' => 0,
                    ]);

                if ($updatedCogs === 0) {
                    DB::table('journal_items')->insert([
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $entryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $mapping->cogs_account_id,
                        'debit' => $roundedCogs,
                        'credit' => 0,
                        'is_reversal' => false,
                    ]);
                }

                $updatedInventory = DB::table('journal_items')
                    ->where('tenant_id', $tenantId)
                    ->where('journal_entry_id', $entryId)
                    ->where('account_id', $mapping->inventory_account_id)
                    ->where('is_reversal', false)
                    ->update([
                        'debit' => 0,
                        'credit' => $roundedCogs,
                    ]);

                if ($updatedInventory === 0) {
                    DB::table('journal_items')->insert([
                        'id' => (string) Str::uuid(),
                        'journal_entry_id' => $entryId,
                        'tenant_id' => $tenantId,
                        'account_id' => $mapping->inventory_account_id,
                        'debit' => 0,
                        'credit' => $roundedCogs,
                        'is_reversal' => false,
                    ]);
                }
            }
        }
    }

    private function resolveLegacySaleItemId(string $tenantId, string $saleId, string $productId): array
    {
        $items = DB::table('sale_items')
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->where('product_id', $productId)
            ->orderBy('id')
            ->get(['id', 'product_variant_id']);

        if ($items->isEmpty()) {
            return [
                'status' => 'not_found',
                'reference_item_id' => null,
                'product_variant_id' => null,
            ];
        }

        if ($items->count() > 1) {
            return [
                'status' => 'legacy_ambiguous',
                'reference_item_id' => null,
                'product_variant_id' => null,
                'candidate_count' => $items->count(),
                'candidate_variant_ids' => $items
                    ->pluck('product_variant_id')
                    ->map(fn ($value) => $value ? (string) $value : null)
                    ->unique()
                    ->values()
                    ->all(),
            ];
        }

        $item = $items->first();

        return [
            'status' => 'resolved',
            'reference_item_id' => (string) $item->id,
            'product_variant_id' => $item->product_variant_id ? (string) $item->product_variant_id : null,
        ];
    }

    private function resolveLegacyPurchaseItemId(
        string $tenantId,
        string $purchaseId,
        string $productId,
        ?string $productVariantId,
        float $ledgerUnitCost
    ): array {
        $query = DB::table('purchase_items')
            ->where('tenant_id', $tenantId)
            ->where('purchase_id', $purchaseId)
            ->where('product_id', $productId);

        $query = $this->applyVariantScopeToTable($query, $productVariantId, 'product_variant_id');

        if ($ledgerUnitCost > 0) {
            $query->where('price', $ledgerUnitCost);
        }

        $items = $query
            ->orderBy('id')
            ->get(['id', 'product_variant_id']);

        if ($items->isEmpty()) {
            return [
                'status' => 'not_found',
                'reference_item_id' => null,
                'product_variant_id' => null,
            ];
        }

        if ($items->count() > 1) {
            return [
                'status' => 'legacy_ambiguous',
                'reference_item_id' => null,
                'product_variant_id' => null,
                'candidate_count' => $items->count(),
                'candidate_item_ids' => $items->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                'candidate_variant_ids' => $items
                    ->pluck('product_variant_id')
                    ->map(fn ($value) => $value ? (string) $value : null)
                    ->unique()
                    ->values()
                    ->all(),
            ];
        }

        $item = $items->first();

        return [
            'status' => 'resolved',
            'reference_item_id' => (string) $item->id,
            'product_variant_id' => $item->product_variant_id ? (string) $item->product_variant_id : null,
        ];
    }

    private function issuePurchaseReturnFifo(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $locationId,
        float $qty,
        string $referenceId,
        string $referenceItemId,
        \DateTimeInterface $at
    ): array {
        $context = $this->resolvePurchaseReturnItemContext(
            tenantId: $tenantId,
            purchaseReturnId: $referenceId,
            purchaseReturnItemId: $referenceItemId,
            productId: $productId,
            productVariantId: $productVariantId
        );

        $remaining = Decimal::qty($qty);
        $totalCost = Decimal::money(0);

        $layers = InventoryLayer::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('source_type', 'purchase')
            ->where('source_item_id', $context['purchase_item_id'])
            ->where('qty_remaining', '>', 0);

        $layers = $this->applyVariantScope($layers, $productVariantId)
            ->orderBy('received_at')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        if ($layers->isEmpty()) {
            $legacyLayerExists = InventoryLayer::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('location_id', $locationId)
                ->where('source_type', 'purchase')
                ->where('source_id', $context['purchase_id'])
                ->whereNull('source_item_id');

            $legacyLayerExists = $this->applyVariantScope($legacyLayerExists, $productVariantId)->exists();

            if ($legacyLayerExists) {
                throw new PurchaseReturnValuationException(
                    'Purchase return gagal: layer purchase legacy belum dibackfill secara aman untuk purchase_item ini.',
                    'purchase_return_fifo_legacy_source_item_unmapped',
                    [
                        'purchase_return_id' => $referenceId,
                        'purchase_return_item_id' => $referenceItemId,
                        'purchase_id' => $context['purchase_id'],
                        'purchase_item_id' => $context['purchase_item_id'],
                        'product_id' => $productId,
                        'product_variant_id' => $productVariantId,
                        'location_id' => $locationId,
                    ],
                    $referenceId
                );
            }

            throw new RuntimeException('Layer FIFO purchase item tidak mencukupi.');
        }

        foreach ($layers as $layer) {
            if (Decimal::compare($remaining, 0) <= 0) {
                break;
            }

            $available = Decimal::qty($layer->qty_remaining);
            if (Decimal::compare($available, 0) <= 0) {
                continue;
            }

            $consume = Decimal::min($available, $remaining, Decimal::QTY_SCALE);
            $unitCost = Decimal::cost($layer->cost_per_unit);
            $cost = Decimal::multiply($consume, $unitCost, Decimal::MONEY_SCALE);

            $layer->update([
                'qty_remaining' => Decimal::subtract($available, $consume, Decimal::QTY_SCALE),
            ]);

            InventoryIssueAllocation::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'location_id' => $locationId,
                'inventory_layer_id' => $layer->id,
                'reference_type' => 'purchase_return',
                'reference_id' => $referenceId,
                'reference_item_id' => $referenceItemId,
                'qty' => $consume,
                'unit_cost' => $unitCost,
                'total_cost' => $cost,
                'created_at' => $at,
            ]);

            $remaining = Decimal::subtract($remaining, $consume, Decimal::QTY_SCALE);
            $totalCost = Decimal::add($totalCost, $cost, Decimal::MONEY_SCALE);
        }

        if (Decimal::compare($remaining, 0) > 0) {
            throw new RuntimeException('Layer FIFO purchase item tidak mencukupi.');
        }

        $unitCost = Decimal::compare($qty, 0) > 0
            ? Decimal::divide($totalCost, $qty, Decimal::COST_SCALE)
            : Decimal::cost(0);

        $this->upsertCostStateOut(
            tenantId: $tenantId,
            productId: $productId,
            productVariantId: $productVariantId,
            locationId: $locationId,
            qty: $qty,
            issueUnitCost: $unitCost,
            at: $at
        );

        return [
            'unit_cost' => Decimal::float($unitCost, Decimal::COST_SCALE),
            'total_cost' => Decimal::float($totalCost, Decimal::MONEY_SCALE),
        ];
    }

    private function inferInboundUnitCost(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $referenceType,
        string $referenceId,
        ?string $referenceItemId,
        float $ledgerUnitCost = 0.0
    ): float {
        $exactCost = $this->resolveExactInboundUnitCost(
            tenantId: $tenantId,
            productId: $productId,
            productVariantId: $productVariantId,
            referenceType: $referenceType,
            referenceId: $referenceId,
            referenceItemId: $referenceItemId
        );

        if ($exactCost !== null) {
            return $exactCost;
        }

        if ($ledgerUnitCost > 0) {
            app(ValuationAuditService::class)->log($tenantId, 'valuation_replay_inbound_cost_fallback_ledger', [
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_item_id' => $referenceItemId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'ledger_unit_cost' => $ledgerUnitCost,
            ]);

            return $ledgerUnitCost;
        }

        if ($referenceItemId === null && ! $this->productHasVariants($tenantId, $productId)) {
            $product = DB::table('products')
                ->where('tenant_id', $tenantId)
                ->where('id', $productId)
                ->first(['hpp_average']);

            $fallback = Decimal::float($product->hpp_average ?? 0, Decimal::COST_SCALE);

            app(ValuationAuditService::class)->log($tenantId, 'valuation_replay_inbound_cost_fallback_product', [
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_item_id' => null,
                'product_id' => $productId,
                'product_variant_id' => null,
                'fallback_unit_cost' => $fallback,
            ]);

            return $fallback;
        }

        $this->reportReplayFailure(
            tenantId: $tenantId,
            type: 'valuation_replay_inbound_cost_unmapped',
            referenceId: $referenceId,
            payload: [
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_item_id' => $referenceItemId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
            ]
        );

        throw new RuntimeException('Replay valuation gagal: inbound cost tidak bisa dipetakan secara aman.');
    }

    private function resolveExactInboundUnitCost(
        string $tenantId,
        string $productId,
        ?string $productVariantId,
        string $referenceType,
        string $referenceId,
        ?string $referenceItemId
    ): ?float {
        if ($referenceType === 'purchase') {
            $query = DB::table('purchase_items')
                ->where('tenant_id', $tenantId)
                ->where('purchase_id', $referenceId)
                ->where('product_id', $productId);

            $query = $this->applyVariantScopeToTable($query, $productVariantId, 'product_variant_id');

            if ($referenceItemId !== null) {
                $query->where('id', $referenceItemId);
            }

            return $this->singleValueOrNull($tenantId, $query, 'price', [
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_item_id' => $referenceItemId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
            ]);
        }

        if (in_array($referenceType, ['transfer_in', 'transfer_out'], true)) {
            $query = DB::table('stock_transfer_items')
                ->where('tenant_id', $tenantId)
                ->where('stock_transfer_id', $referenceId)
                ->where('product_id', $productId);

            $query = $this->applyVariantScopeToTable($query, $productVariantId, 'product_variant_id');

            if ($referenceItemId !== null) {
                $query->where('id', $referenceItemId);
            }

            return $this->singleValueOrNull($tenantId, $query, 'unit_cost_snapshot', [
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_item_id' => $referenceItemId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
            ]);
        }

        if ($referenceType === 'stock_adjustment') {
            $query = DB::table('stock_adjustment_items')
                ->where('tenant_id', $tenantId)
                ->where('stock_adjustment_id', $referenceId)
                ->where('product_id', $productId);

            $query = $this->applyVariantScopeToTable($query, $productVariantId, 'product_variant_id');

            if ($referenceItemId !== null) {
                $query->where('id', $referenceItemId);
            }

            return $this->singleValueOrNull($tenantId, $query, 'unit_cost_snapshot', [
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_item_id' => $referenceItemId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
            ]);
        }

        if (in_array($referenceType, ['production_output', 'production_input'], true)) {
            $query = DB::table('production_batch_items')
                ->where('tenant_id', $tenantId)
                ->where('production_batch_id', $referenceId)
                ->where('product_id', $productId);

            $query = $this->applyVariantScopeToTable($query, $productVariantId, 'product_variant_id');

            if ($referenceItemId !== null) {
                $query->where('id', $referenceItemId);
            }

            return $this->singleValueOrNull($tenantId, $query, 'unit_cost_snapshot', [
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_item_id' => $referenceItemId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
            ]);
        }

        if ($referenceType === 'refund') {
            $query = DB::table('refund_items')
                ->where('tenant_id', $tenantId)
                ->where('refund_id', $referenceId)
                ->where('product_id', $productId);

            $query = $this->applyVariantScopeToTable($query, $productVariantId, 'product_variant_id');

            if ($referenceItemId !== null) {
                $query->where('sale_item_id', $referenceItemId);
            }

            return $this->singleValueOrNull($tenantId, $query, 'hpp_snapshot', [
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_item_id' => $referenceItemId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
            ]);
        }

        if ($referenceType === 'void') {
            return $this->lookupSaleItemCost($tenantId, $referenceId, $referenceItemId, $productId, $productVariantId);
        }

        if ($referenceType === 'void_recipe_input') {
            $query = DB::table('stock_ledger')
                ->where('tenant_id', $tenantId)
                ->where('reference_type', 'sale_recipe_input')
                ->where('reference_id', $referenceId)
                ->where('product_id', $productId)
                ->where('qty_out', '>', 0);

            $query = $this->applyVariantScopeToTable($query, $productVariantId, 'product_variant_id');

            if ($referenceItemId !== null) {
                $query->where('reference_item_id', $referenceItemId);
            }

            return $this->singleValueOrNull($tenantId, $query, 'unit_cost', [
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_item_id' => $referenceItemId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
            ]);
        }

        return null;
    }

    private function lookupSaleItemCost(
        string $tenantId,
        string $saleId,
        ?string $referenceItemId,
        string $productId,
        ?string $productVariantId
    ): ?float {
        $query = DB::table('sale_items')
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->where('product_id', $productId);

        $query = $this->applyVariantScopeToTable($query, $productVariantId, 'product_variant_id');

        if ($referenceItemId !== null) {
            $query->where('id', $referenceItemId);
        }

        return $this->singleValueOrNull($tenantId, $query, 'hpp_snapshot', [
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'reference_item_id' => $referenceItemId,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
        ]);
    }

    private function resolveSourceItemVariantId(
        string $tenantId,
        string $referenceType,
        string $referenceId,
        ?string $referenceItemId,
        string $productId
    ): ?string {
        if ($referenceItemId === null) {
            return null;
        }

        if (in_array($referenceType, ['sale', 'void'], true)) {
            $row = DB::table('sale_items')
                ->where('tenant_id', $tenantId)
                ->where('sale_id', $referenceId)
                ->where('id', $referenceItemId)
                ->where('product_id', $productId)
                ->first(['product_variant_id']);

            return $row?->product_variant_id ? (string) $row->product_variant_id : null;
        }

        if ($referenceType === 'refund') {
            $row = DB::table('refund_items')
                ->where('tenant_id', $tenantId)
                ->where('refund_id', $referenceId)
                ->where('sale_item_id', $referenceItemId)
                ->where('product_id', $productId)
                ->first(['product_variant_id']);

            return $row?->product_variant_id ? (string) $row->product_variant_id : null;
        }

        $mapping = [
            'purchase' => ['table' => 'purchase_items', 'parent' => 'purchase_id'],
            'transfer_in' => ['table' => 'stock_transfer_items', 'parent' => 'stock_transfer_id'],
            'transfer_out' => ['table' => 'stock_transfer_items', 'parent' => 'stock_transfer_id'],
            'stock_adjustment' => ['table' => 'stock_adjustment_items', 'parent' => 'stock_adjustment_id'],
            'production_input' => ['table' => 'production_batch_items', 'parent' => 'production_batch_id'],
            'production_output' => ['table' => 'production_batch_items', 'parent' => 'production_batch_id'],
            'purchase_return' => ['table' => 'purchase_return_items', 'parent' => 'purchase_return_id'],
        ];

        if (! isset($mapping[$referenceType])) {
            return null;
        }

        $config = $mapping[$referenceType];

        $row = DB::table($config['table'])
            ->where('tenant_id', $tenantId)
            ->where($config['parent'], $referenceId)
            ->where('id', $referenceItemId)
            ->where('product_id', $productId)
            ->first(['product_variant_id']);

        return $row?->product_variant_id ? (string) $row->product_variant_id : null;
    }

    private function resolvePurchaseReturnItemContext(
        string $tenantId,
        string $purchaseReturnId,
        string $purchaseReturnItemId,
        string $productId,
        ?string $productVariantId
    ): array {
        $row = DB::table('purchase_return_items')
            ->join('purchase_returns', function ($join) {
                $join->on('purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
                    ->on('purchase_returns.tenant_id', '=', 'purchase_return_items.tenant_id');
            })
            ->where('purchase_return_items.tenant_id', $tenantId)
            ->where('purchase_return_items.purchase_return_id', $purchaseReturnId)
            ->where('purchase_return_items.id', $purchaseReturnItemId)
            ->where('purchase_return_items.product_id', $productId)
            ->first([
                'purchase_return_items.purchase_item_id',
                'purchase_return_items.product_variant_id',
                'purchase_returns.purchase_id',
            ]);

        if (! $row) {
            throw new RuntimeException('Purchase return item valuation tidak ditemukan.');
        }

        $resolvedVariantId = $row->product_variant_id ? (string) $row->product_variant_id : null;
        if ($resolvedVariantId !== $productVariantId) {
            throw new RuntimeException('Konteks varian purchase return tidak konsisten.');
        }

        if ($row->purchase_item_id) {
            return [
                'purchase_id' => (string) $row->purchase_id,
                'purchase_item_id' => (string) $row->purchase_item_id,
            ];
        }

        $resolution = app(PurchaseHistoryBackfillService::class)->resolveLegacyPurchaseReturnItem($tenantId, $purchaseReturnItemId);

        if (($resolution['status'] ?? null) === 'resolved_single_purchase_item') {
            DB::table('purchase_return_items')
                ->where('tenant_id', $tenantId)
                ->where('id', $purchaseReturnItemId)
                ->update([
                    'purchase_item_id' => $resolution['purchase_item_id'],
                ]);

            app(ValuationAuditService::class)->log($tenantId, 'purchase_return_legacy_item_resolved', [
                'purchase_return_id' => $purchaseReturnId,
                'purchase_return_item_id' => $purchaseReturnItemId,
                'purchase_id' => $resolution['purchase_id'],
                'purchase_item_id' => $resolution['purchase_item_id'],
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
            ]);

            return [
                'purchase_id' => $resolution['purchase_id'],
                'purchase_item_id' => $resolution['purchase_item_id'],
            ];
        }

        throw new PurchaseReturnValuationException(
            'Purchase return gagal: histori purchase return tidak bisa dipetakan aman ke purchase item.',
            'purchase_return_legacy_ambiguous',
            [
                'purchase_return_id' => $purchaseReturnId,
                'purchase_return_item_id' => $purchaseReturnItemId,
                'purchase_id' => $resolution['purchase_id'] ?? (string) $row->purchase_id,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'status' => $resolution['status'] ?? 'not_found',
                'candidate_count' => $resolution['candidate_count'] ?? 0,
                'candidate_purchase_item_ids' => $resolution['candidate_purchase_item_ids'] ?? [],
            ],
            $purchaseReturnId
        );
    }

    private function singleValueOrNull(string $tenantId, $query, string $column, array $context): ?float
    {
        $rows = $query->get([$column]);

        if ($rows->isEmpty()) {
            return null;
        }

        if ($rows->count() > 1) {
            $this->reportReplayFailure(
                tenantId: $tenantId,
                type: 'valuation_replay_legacy_ambiguous',
                referenceId: $context['reference_id'] ?? null,
                payload: array_merge($context, [
                    'message' => 'Lookup replay menghasilkan lebih dari satu candidate row.',
                    'candidate_count' => $rows->count(),
                ])
            );

            throw new RuntimeException('Replay valuation gagal: lookup historis ambigu.');
        }

        return Decimal::float($rows->first()->{$column} ?? 0, Decimal::COST_SCALE);
    }

    private function reportReplayFailure(string $tenantId, string $type, ?string $referenceId, array $payload): void
    {
        app(ValuationAuditService::class)->log($tenantId, $type, $payload);
        app(AnomalyService::class)->raise(
            tenantId: $tenantId,
            severity: 'high',
            type: $type,
            payload: $payload,
            referenceId: $referenceId,
        );
    }


    private function replayRowContext(object $row, array $extra = []): array
    {
        return array_merge([
            'ledger_row_id' => (string) $row->id,
            'reference_type' => (string) $row->reference_type,
            'reference_id' => (string) $row->reference_id,
            'reference_item_id' => $row->reference_item_id ? (string) $row->reference_item_id : null,
            'product_id' => (string) $row->product_id,
            'product_variant_id' => $row->product_variant_id ? (string) $row->product_variant_id : null,
            'location_id' => (string) $row->location_id,
        ], $extra);
    }
}
