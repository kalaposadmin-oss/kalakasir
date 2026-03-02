<?php

namespace App\Services;

use App\Models\InventoryCostState;
use App\Models\InventoryIssueAllocation;
use App\Models\InventoryLayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InventoryValuationService
{
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
        string $locationId,
        float $qty,
        float $unitCost,
        string $sourceType,
        string $sourceId,
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
                'location_id' => $locationId,
                'qty_remaining' => $qty,
                'qty_original' => $qty,
                'cost_per_unit' => $unitCost,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'received_at' => $at,
                'created_at' => $at,
            ]);
        }

        $this->upsertCostStateIn(
            tenantId: $tenantId,
            productId: $productId,
            locationId: $locationId,
            qty: $qty,
            unitCost: $unitCost,
            at: $at
        );
    }

    public function issue(
        string $tenantId,
        string $productId,
        string $locationId,
        float $qty,
        string $referenceType,
        string $referenceId,
        ?string $referenceItemId,
        \DateTimeInterface $at
    ): array {
        if ($qty <= 0) {
            return [
                'unit_cost' => 0.0,
                'total_cost' => 0.0,
            ];
        }

        $method = $this->methodForTenant($tenantId);

        if ($method === 'fifo') {
            return $this->issueFifo(
                tenantId: $tenantId,
                productId: $productId,
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
            ->whereNull('reversed_at')
            ->orderBy('created_at')
            ->get();

        if ($allocations->isEmpty()) {
            return;
        }

        $method = $this->methodForTenant($tenantId);

        if ($method === 'fifo') {
            foreach ($allocations as $allocation) {
                if ($allocation->inventory_layer_id) {
                    InventoryLayer::query()
                        ->where('tenant_id', $tenantId)
                        ->where('id', $allocation->inventory_layer_id)
                        ->lockForUpdate()
                        ->update([
                            'qty_remaining' => DB::raw('qty_remaining + '.(float) $allocation->qty),
                        ]);
                } else {
                    $this->receive(
                        tenantId: $tenantId,
                        productId: $allocation->product_id,
                        locationId: $allocation->location_id,
                        qty: (float) $allocation->qty,
                        unitCost: (float) $allocation->unit_cost,
                        sourceType: $newSourceType,
                        sourceId: $newSourceId,
                        at: $at
                    );
                }

                $this->upsertCostStateIn(
                    tenantId: $tenantId,
                    productId: $allocation->product_id,
                    locationId: $allocation->location_id,
                    qty: (float) $allocation->qty,
                    unitCost: (float) $allocation->unit_cost,
                    at: $at
                );
            }

            InventoryIssueAllocation::query()
                ->where('tenant_id', $tenantId)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->whereNull('reversed_at')
                ->update([
                    'reversed_at' => $at,
                ]);

            return;
        }

        foreach ($allocations as $allocation) {
            $this->receive(
                tenantId: $tenantId,
                productId: $allocation->product_id,
                locationId: $allocation->location_id,
                qty: (float) $allocation->qty,
                unitCost: (float) $allocation->unit_cost,
                sourceType: $newSourceType,
                sourceId: $newSourceId,
                at: $at
            );
        }

        InventoryIssueAllocation::query()
            ->where('tenant_id', $tenantId)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->whereNull('reversed_at')
            ->update([
                'reversed_at' => $at,
            ]);
    }

    public function transferInUnitCost(string $tenantId, string $transferId, string $productId): float
    {
        $row = DB::table('stock_transfer_items')
            ->where('tenant_id', $tenantId)
            ->where('stock_transfer_id', $transferId)
            ->where('product_id', $productId)
            ->first(['unit_cost_snapshot']);

        return (float) ($row->unit_cost_snapshot ?? 0);
    }

    private function issueFifo(
        string $tenantId,
        string $productId,
        string $locationId,
        float $qty,
        string $referenceType,
        string $referenceId,
        ?string $referenceItemId,
        \DateTimeInterface $at
    ): array {
        $remaining = $qty;
        $totalCost = 0.0;

        $layers = InventoryLayer::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('qty_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        if ($layers->isEmpty()) {
            throw new RuntimeException('Layer FIFO tidak mencukupi.');
        }

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $layer->qty_remaining;
            if ($available <= 0) {
                continue;
            }

            $consume = min($available, $remaining);
            $unitCost = (float) $layer->cost_per_unit;
            $cost = $consume * $unitCost;

            $layer->update([
                'qty_remaining' => $available - $consume,
            ]);

            InventoryIssueAllocation::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'product_id' => $productId,
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

            $remaining -= $consume;
            $totalCost += $cost;
        }

        if ($remaining > 0) {
            throw new RuntimeException('Layer FIFO tidak mencukupi.');
        }

        $unitCost = $qty > 0 ? ($totalCost / $qty) : 0.0;

        $this->upsertCostStateOut(
            tenantId: $tenantId,
            productId: $productId,
            locationId: $locationId,
            qty: $qty,
            issueUnitCost: $unitCost,
            at: $at
        );

        return [
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
        ];
    }

    private function issueAverage(
        string $tenantId,
        string $productId,
        string $locationId,
        float $qty,
        string $referenceType,
        string $referenceId,
        ?string $referenceItemId,
        \DateTimeInterface $at
    ): array {
        $state = InventoryCostState::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        $unitCost = (float) ($state?->average_cost ?? 0);
        $totalCost = $unitCost * $qty;

        InventoryIssueAllocation::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'location_id' => $locationId,
            'inventory_layer_id' => null,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference_item_id' => $referenceItemId,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'created_at' => $at,
        ]);

        $this->upsertCostStateOut(
            tenantId: $tenantId,
            productId: $productId,
            locationId: $locationId,
            qty: $qty,
            issueUnitCost: $unitCost,
            at: $at
        );

        return [
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
        ];
    }

    public function upsertCostStateIn(
        string $tenantId,
        string $productId,
        string $locationId,
        float $qty,
        float $unitCost,
        \DateTimeInterface $at
    ): void {
        $state = InventoryCostState::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if (! $state) {
            InventoryCostState::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'location_id' => $locationId,
                'qty_on_hand' => $qty,
                'average_cost' => $unitCost,
                'last_cost' => $unitCost,
                'updated_at' => $at,
            ]);

            return;
        }

        $oldQty = (float) $state->qty_on_hand;
        $oldAvg = (float) $state->average_cost;
        $newQty = $oldQty + $qty;

        $newAvg = $newQty > 0
            ? (($oldQty * $oldAvg) + ($qty * $unitCost)) / $newQty
            : $unitCost;

        $state->update([
            'qty_on_hand' => $newQty,
            'average_cost' => $newAvg,
            'last_cost' => $unitCost,
            'updated_at' => $at,
        ]);
    }

    public function upsertCostStateOut(
        string $tenantId,
        string $productId,
        string $locationId,
        float $qty,
        float $issueUnitCost,
        \DateTimeInterface $at
    ): void {
        $state = InventoryCostState::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if (! $state) {
            InventoryCostState::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'location_id' => $locationId,
                'qty_on_hand' => max(0, -$qty),
                'average_cost' => $issueUnitCost,
                'last_cost' => $issueUnitCost,
                'updated_at' => $at,
            ]);

            return;
        }

        $newQty = max(0, (float) $state->qty_on_hand - $qty);

        $state->update([
            'qty_on_hand' => $newQty,
            'last_cost' => $issueUnitCost,
            'updated_at' => $at,
        ]);
    }

    public function replayFromLedger(string $tenantId): void
    {
        InventoryIssueAllocation::query()->where('tenant_id', $tenantId)->delete();
        InventoryLayer::query()->where('tenant_id', $tenantId)->delete();
        InventoryCostState::query()->where('tenant_id', $tenantId)->delete();

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
                'location_id',
                'reference_type',
                'reference_id',
                'reference_item_id',
                'qty_in',
                'qty_out',
                'created_at',
            ])
            ->cursor();

        $saleItemPointer = [];

        foreach ($rows as $row) {
            $at = new \DateTimeImmutable((string) $row->created_at);
            $qtyIn = (float) $row->qty_in;
            $qtyOut = (float) $row->qty_out;
            $referenceItemId = $row->reference_item_id;

            if ($row->reference_type === 'sale' && ! $referenceItemId) {
                $referenceItemId = $this->resolveLegacySaleItemId(
                    tenantId: $tenantId,
                    saleId: (string) $row->reference_id,
                    productId: (string) $row->product_id,
                    pointer: $saleItemPointer
                );

                if ($referenceItemId) {
                    DB::table('stock_ledger')
                        ->where('id', $row->id)
                        ->update(['reference_item_id' => $referenceItemId]);
                }
            }

            if ($qtyIn > 0) {
                $unitCost = $this->inferInboundUnitCost(
                    tenantId: $tenantId,
                    productId: $row->product_id,
                    referenceType: $row->reference_type,
                    referenceId: $row->reference_id
                );

                $this->receive(
                    tenantId: $tenantId,
                    productId: $row->product_id,
                    locationId: $row->location_id,
                    qty: $qtyIn,
                    unitCost: $unitCost,
                    sourceType: (string) $row->reference_type,
                    sourceId: (string) $row->reference_id,
                    at: $at
                );
            }

            if ($qtyOut > 0) {
                $result = $this->issue(
                    tenantId: $tenantId,
                    productId: $row->product_id,
                    locationId: $row->location_id,
                    qty: $qtyOut,
                    referenceType: (string) $row->reference_type,
                    referenceId: (string) $row->reference_id,
                    referenceItemId: $referenceItemId,
                    at: $at
                );

                if ($row->reference_type === 'sale' && $referenceItemId) {
                    DB::table('sale_items')
                        ->where('tenant_id', $tenantId)
                        ->where('id', $referenceItemId)
                        ->update([
                            'hpp_snapshot' => round((float) $result['unit_cost'], 2),
                        ]);
                }
            }
        }

        $this->syncSaleJournalCogsFromSnapshots($tenantId);
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
            ->where('status', 'completed')
            ->select('id')
            ->cursor();

        foreach ($sales as $sale) {
            $saleId = (string) $sale->id;

            $cogsTotal = (float) DB::table('sale_items')
                ->where('tenant_id', $tenantId)
                ->where('sale_id', $saleId)
                ->selectRaw('sum(qty * hpp_snapshot) as total')
                ->value('total');

            $entryIds = DB::table('journal_entries')
                ->where('tenant_id', $tenantId)
                ->where('reference_type', 'sale')
                ->where('reference_id', $saleId)
                ->pluck('id');

            if ($entryIds->isEmpty()) {
                continue;
            }

            foreach ($entryIds as $entryId) {
                if ($cogsTotal <= 0) {
                    DB::table('journal_items')
                        ->where('tenant_id', $tenantId)
                        ->where('journal_entry_id', $entryId)
                        ->whereIn('account_id', [$mapping->cogs_account_id, $mapping->inventory_account_id])
                        ->where('is_reversal', false)
                        ->delete();

                    continue;
                }

                $roundedCogs = round($cogsTotal, 2);

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

    private function resolveLegacySaleItemId(string $tenantId, string $saleId, string $productId, array &$pointer): ?string
    {
        $key = $saleId.'|'.$productId;
        $index = $pointer[$key] ?? 0;

        $items = DB::table('sale_items')
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->where('product_id', $productId)
            ->orderBy('id')
            ->get(['id']);

        if (! isset($items[$index])) {
            return null;
        }

        $pointer[$key] = $index + 1;

        return (string) $items[$index]->id;
    }

    private function inferInboundUnitCost(string $tenantId, string $productId, string $referenceType, string $referenceId): float
    {
        if ($referenceType === 'purchase') {
            $row = DB::table('purchase_items')
                ->join('purchases', function ($join) {
                    $join->on('purchase_items.purchase_id', '=', 'purchases.id')
                        ->on('purchase_items.tenant_id', '=', 'purchases.tenant_id');
                })
                ->where('purchase_items.tenant_id', $tenantId)
                ->where('purchase_items.purchase_id', $referenceId)
                ->where('purchase_items.product_id', $productId)
                ->selectRaw('avg(purchase_items.price) as avg_price')
                ->first();

            return (float) ($row->avg_price ?? 0);
        }

        if ($referenceType === 'transfer_in') {
            $row = DB::table('stock_transfer_items')
                ->where('tenant_id', $tenantId)
                ->where('stock_transfer_id', $referenceId)
                ->where('product_id', $productId)
                ->first(['unit_cost_snapshot']);

            return (float) ($row->unit_cost_snapshot ?? 0);
        }

        if ($referenceType === 'stock_adjustment') {
            $row = DB::table('stock_adjustment_items')
                ->where('tenant_id', $tenantId)
                ->where('stock_adjustment_id', $referenceId)
                ->where('product_id', $productId)
                ->first(['unit_cost_snapshot']);

            return (float) ($row->unit_cost_snapshot ?? 0);
        }

        $product = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('id', $productId)
            ->first(['hpp_average']);

        return (float) ($product->hpp_average ?? 0);
    }
}
