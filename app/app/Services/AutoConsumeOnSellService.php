<?php

namespace App\Services;

use App\Exceptions\AutoConsumeValidationException;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutoConsumeOnSellService
{
    public function consumeForSaleItem(
        string $tenantId,
        string $saleId,
        string $saleItemId,
        Product $soldProduct,
        float $soldQty,
        string $locationId,
        ?string $userId,
        CarbonImmutable $at
    ): void {
        $policyService = app(AutoConsumeTenantPolicyService::class);

        if (! $policyService->autoConsumeOnSellEnabled()) {
            return;
        }

        $soldType = ProductItemTypeMapper::resolve(
            $soldProduct->item_type,
            (string) ($soldProduct->product_mode ?? 'default')
        );

        if ($soldType !== 'finished_good') {
            return;
        }

        $recipeItems = collect($soldProduct->bom_items ?? [])
            ->filter(fn ($line) => is_array($line)
                && ! empty($line['product_id'])
                && (float) ($line['qty'] ?? 0) > 0)
            ->values();

        if ($recipeItems->isEmpty()) {
            return;
        }

        $alreadyConsumed = StockLedger::query()
            ->where('tenant_id', $tenantId)
            ->where('reference_type', 'sale_recipe_input')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->exists();

        if ($alreadyConsumed) {
            return;
        }

        $rawMaterialIds = $recipeItems
            ->pluck('product_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $rawMaterials = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $rawMaterialIds->all())
            ->get(['id', 'item_type', 'product_mode'])
            ->keyBy('id');

        if ($rawMaterials->count() !== $rawMaterialIds->count()) {
            throw new AutoConsumeValidationException(
                'AUTO_CONSUME_RECIPE_INVALID',
                'Recipe auto-consume tidak valid.',
                ['recipe' => ['Komponen recipe tidak ditemukan untuk tenant aktif.']]
            );
        }

        foreach ($rawMaterialIds as $rawMaterialId) {
            $material = $rawMaterials->get($rawMaterialId);

            $materialType = ProductItemTypeMapper::resolve(
                $material?->item_type,
                (string) ($material?->product_mode ?? 'default')
            );

            if ($materialType !== 'raw_material') {
                throw new AutoConsumeValidationException(
                    'AUTO_CONSUME_RECIPE_INVALID',
                    'Recipe auto-consume tidak valid.',
                    ['recipe' => ['Semua input recipe harus bertipe raw_material.']]
                );
            }
        }

        $allowNegative = $policyService->allowNegativeRawMaterialStock();
        $valuationService = app(InventoryValuationService::class);
        $ledgerRows = [];

        foreach ($recipeItems as $line) {
            $rawMaterialId = (string) $line['product_id'];
            $recipeQty = (float) $line['qty'];
            $requiredQty = round($soldQty * $recipeQty, 4);

            if ($requiredQty <= 0) {
                continue;
            }

            if (! $allowNegative) {
                $affected = DB::update(
                    'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND product_variant_id IS NULL AND qty_on_hand >= ?',
                    [$requiredQty, $tenantId, $rawMaterialId, $locationId, $requiredQty]
                );

                if ($affected === 0) {
                    throw new AutoConsumeValidationException(
                        'AUTO_CONSUME_INSUFFICIENT_RAW_MATERIAL',
                        'Stok bahan baku tidak mencukupi untuk auto-consume.',
                        [
                            'product_id' => $rawMaterialId,
                            'location_id' => $locationId,
                            'required_qty' => $requiredQty,
                        ]
                    );
                }
            } else {
                DB::table('inventory_balances')->insertOrIgnore([
                    'tenant_id' => $tenantId,
                    'product_id' => $rawMaterialId,
                    'product_variant_id' => null,
                    'location_id' => $locationId,
                    'qty_on_hand' => 0,
                ]);

                DB::update(
                    'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND product_variant_id IS NULL',
                    [$requiredQty, $tenantId, $rawMaterialId, $locationId]
                );

                app(AnomalyService::class)->raise(
                    tenantId: $tenantId,
                    severity: 'medium',
                    type: 'negative_stock_attempt',
                    payload: [
                        'source' => 'auto_consume_sale',
                        'sale_id' => $saleId,
                        'sale_item_id' => $saleItemId,
                        'product_id' => $rawMaterialId,
                        'location_id' => $locationId,
                        'requested_qty' => $requiredQty,
                        'allow_negative_policy' => true,
                    ],
                    referenceId: $saleId
                );
            }

            $valuation = $valuationService->issue(
                tenantId: $tenantId,
                productId: $rawMaterialId,
                locationId: $locationId,
                qty: $requiredQty,
                referenceType: 'sale_recipe_input',
                referenceId: $saleId,
                referenceItemId: $saleItemId,
                at: $at
            );

            $balanceAfter = (float) InventoryBalance::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $rawMaterialId)
                ->where('location_id', $locationId)
                ->whereNull('product_variant_id')
                ->value('qty_on_hand');

            $ledgerRows[] = [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'product_id' => $rawMaterialId,
                'product_variant_id' => null,
                'location_id' => $locationId,
                'reference_type' => 'sale_recipe_input',
                'reference_id' => $saleId,
                'reference_item_id' => $saleItemId,
                'qty_in' => 0,
                'qty_out' => $requiredQty,
                'balance_after' => $balanceAfter,
                'unit_cost' => (float) ($valuation['unit_cost'] ?? 0),
                'total_cost' => (float) ($valuation['total_cost'] ?? 0),
                'created_at' => $at,
                'created_by' => $userId,
            ];
        }

        if ($ledgerRows) {
            StockLedger::query()->insert($ledgerRows);
        }
    }
}
