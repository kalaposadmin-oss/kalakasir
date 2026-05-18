<?php

namespace App\Services;

use App\Models\Sale;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SalesCostAggregationService
{
    public function captureForSale(
        string $tenantId,
        string $saleId,
        ?CarbonImmutable $now = null,
        string $source = 'checkout'
    ): array {
        $now ??= CarbonImmutable::now('UTC');

        if (! $this->allowsOverwrite($source) && $this->componentsExist($tenantId, $saleId)) {
            return $this->currentAggregate($tenantId, $saleId);
        }

        return $this->persistAggregate($tenantId, $saleId, $now, $source, $this->allowsOverwrite($source));
    }

    public function recomputeForSale(
        string $tenantId,
        string $saleId,
        ?CarbonImmutable $now = null,
        string $source = 'rebuild'
    ): array {
        $now ??= CarbonImmutable::now('UTC');

        return $this->persistAggregate($tenantId, $saleId, $now, $source, true);
    }

    public function totalForSale(string $tenantId, string $saleId): string
    {
        return Decimal::money(DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('id', $saleId)
            ->value('cogs_total'));
    }

    public function perSaleItemTotals(string $tenantId, string $saleId): array
    {
        return DB::table('sale_cost_components')
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->select('sale_item_id')
            ->selectRaw('sum(total_cost) as total_cost')
            ->groupBy('sale_item_id')
            ->pluck('total_cost', 'sale_item_id')
            ->map(fn ($value) => Decimal::float($value, Decimal::MONEY_SCALE))
            ->all();
    }

    public function syncSaleJournalForSale(string $tenantId, string $saleId): void
    {
        $mapping = DB::table('account_mappings')
            ->where('tenant_id', $tenantId)
            ->first(['cogs_account_id', 'inventory_account_id']);

        if (! $mapping || ! $mapping->cogs_account_id || ! $mapping->inventory_account_id) {
            return;
        }

        $cogsTotal = $this->totalForSale($tenantId, $saleId);

        $entryIds = DB::table('journal_entries')
            ->where('tenant_id', $tenantId)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->pluck('id');

        if ($entryIds->isEmpty()) {
            return;
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

    private function persistAggregate(
        string $tenantId,
        string $saleId,
        CarbonImmutable $now,
        string $source,
        bool $allowOverwrite
    ): array {
        return app(DatabaseTransactionService::class)->run(function () use ($tenantId, $saleId, $now, $source, $allowOverwrite): array {
            $sale = DB::table('sales')
                ->where('tenant_id', $tenantId)
                ->where('id', $saleId)
                ->lockForUpdate()
                ->first(['id', 'status']);

            if (! $sale) {
                throw new RuntimeException('Sale tidak ditemukan untuk agregasi cost.');
            }

            if ((string) $sale->status !== Sale::STATUS_COMPLETED) {
                throw new RuntimeException('Hanya sale completed yang bisa diagregasi cost-nya.');
            }

            $saleItems = DB::table('sale_items')
                ->where('tenant_id', $tenantId)
                ->where('sale_id', $saleId)
                ->orderBy('id')
                ->get([
                    'id',
                    'product_id',
                    'product_variant_id',
                    'qty',
                    'hpp_snapshot',
                ]);

            if ($saleItems->isEmpty()) {
                throw new RuntimeException('Sale item tidak ditemukan untuk agregasi cost.');
            }

            $recipeRows = DB::table('stock_ledger')
                ->where('tenant_id', $tenantId)
                ->where('reference_type', 'sale_recipe_input')
                ->where('reference_id', $saleId)
                ->orderBy('reference_item_id')
                ->orderBy('id')
                ->get([
                    'id',
                    'reference_item_id',
                    'product_id',
                    'product_variant_id',
                    'qty_out',
                    'unit_cost',
                    'total_cost',
                ]);

            $validSaleItemIds = $saleItems->pluck('id')->map(fn ($id) => (string) $id)->all();
            foreach ($recipeRows as $recipeRow) {
                $referenceItemId = $recipeRow->reference_item_id ? (string) $recipeRow->reference_item_id : null;

                if ($referenceItemId === null || ! in_array($referenceItemId, $validSaleItemIds, true)) {
                    throw new RuntimeException('Stock ledger sale_recipe_input tidak memiliki reference_item_id sale_item yang valid.');
                }
            }

            if ($allowOverwrite) {
                DB::table('sale_cost_components')
                    ->where('tenant_id', $tenantId)
                    ->where('sale_id', $saleId)
                    ->delete();
            }

            $componentRows = [];
            $baseItemCogsTotal = Decimal::money(0);
            $recipeInputCogsTotal = Decimal::money(0);

            foreach ($saleItems as $saleItem) {
                $baseTotalCost = Decimal::multiply($saleItem->qty, $saleItem->hpp_snapshot, Decimal::MONEY_SCALE);
                $baseItemCogsTotal = Decimal::add($baseItemCogsTotal, $baseTotalCost, Decimal::MONEY_SCALE);

                $componentRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'sale_id' => $saleId,
                    'sale_item_id' => (string) $saleItem->id,
                    'component_type' => 'sale_item',
                    'product_id' => (string) $saleItem->product_id,
                    'product_variant_id' => $saleItem->product_variant_id,
                    'qty' => Decimal::float($saleItem->qty, Decimal::QTY_SCALE),
                    'unit_cost' => Decimal::float(Decimal::cost($saleItem->hpp_snapshot), Decimal::COST_SCALE),
                    'total_cost' => Decimal::float($baseTotalCost, Decimal::MONEY_SCALE),
                    'source_reference_type' => 'sale',
                    'source_reference_id' => $saleId,
                    'source_reference_item_id' => (string) $saleItem->id,
                    'source' => $source,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach ($recipeRows as $recipeRow) {
                $totalCost = Decimal::money($recipeRow->total_cost ?? 0);
                $recipeInputCogsTotal = Decimal::add($recipeInputCogsTotal, $totalCost, Decimal::MONEY_SCALE);

                $componentRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'sale_id' => $saleId,
                    'sale_item_id' => (string) $recipeRow->reference_item_id,
                    'component_type' => 'recipe_input',
                    'product_id' => (string) $recipeRow->product_id,
                    'product_variant_id' => $recipeRow->product_variant_id,
                    'qty' => Decimal::float($recipeRow->qty_out ?? 0, Decimal::QTY_SCALE),
                    'unit_cost' => Decimal::float(Decimal::cost($recipeRow->unit_cost ?? 0), Decimal::COST_SCALE),
                    'total_cost' => Decimal::float($totalCost, Decimal::MONEY_SCALE),
                    'source_reference_type' => 'sale_recipe_input',
                    'source_reference_id' => $saleId,
                    'source_reference_item_id' => (string) $recipeRow->reference_item_id,
                    'source' => $source,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($componentRows !== []) {
                DB::table('sale_cost_components')->insert($componentRows);
            }

            $totalCogs = Decimal::add($baseItemCogsTotal, $recipeInputCogsTotal, Decimal::MONEY_SCALE);

            DB::table('sales')
                ->where('tenant_id', $tenantId)
                ->where('id', $saleId)
                ->update([
                    'cogs_total' => Decimal::float($totalCogs, Decimal::MONEY_SCALE),
                ]);

            return [
                'sale_id' => $saleId,
                'base_item_cogs_total' => Decimal::float($baseItemCogsTotal, Decimal::MONEY_SCALE),
                'recipe_input_cogs_total' => Decimal::float($recipeInputCogsTotal, Decimal::MONEY_SCALE),
                'total_cogs' => Decimal::float($totalCogs, Decimal::MONEY_SCALE),
                'component_count' => count($componentRows),
            ];
        });
    }

    private function componentsExist(string $tenantId, string $saleId): bool
    {
        return DB::table('sale_cost_components')
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->exists();
    }

    private function currentAggregate(string $tenantId, string $saleId): array
    {
        $baseTotal = Decimal::money(DB::table('sale_cost_components')
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->where('component_type', 'sale_item')
            ->selectRaw('coalesce(sum(total_cost), 0) as total')
            ->value('total'));

        $recipeTotal = Decimal::money(DB::table('sale_cost_components')
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->where('component_type', 'recipe_input')
            ->selectRaw('coalesce(sum(total_cost), 0) as total')
            ->value('total'));

        $total = Decimal::money(DB::table('sale_cost_components')
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->selectRaw('coalesce(sum(total_cost), 0) as total')
            ->value('total'));

        return [
            'sale_id' => $saleId,
            'base_item_cogs_total' => Decimal::float($baseTotal, Decimal::MONEY_SCALE),
            'recipe_input_cogs_total' => Decimal::float($recipeTotal, Decimal::MONEY_SCALE),
            'total_cogs' => Decimal::float($total, Decimal::MONEY_SCALE),
            'component_count' => (int) DB::table('sale_cost_components')
                ->where('tenant_id', $tenantId)
                ->where('sale_id', $saleId)
                ->count(),
        ];
    }

    private function allowsOverwrite(string $source): bool
    {
        return in_array($source, ['rebuild', 'repair'], true);
    }
}