<?php

namespace App\Services;

use App\Exceptions\AutoConsumeValidationException;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockLedger;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

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
        $allowNegative = $policyService->allowNegativeRawMaterialStock();

        $baseContext = [
            'tenant_id' => $tenantId,
            'sale_id' => $saleId,
            'sale_item_id' => $saleItemId,
            'product_id' => (string) $soldProduct->id,
            'recipe_id' => null,
            'raw_material_product_id' => null,
            'raw_material_variant_id' => null,
            'qty_requested' => Decimal::float(Decimal::qty($soldQty), Decimal::QTY_SCALE),
            'qty_consumed' => 0.0,
            'policy_negative_stock' => $allowNegative,
            'error_code' => null,
        ];

        $this->emitEvent('auto_consume.started', $tenantId, $baseContext);

        if (! $policyService->autoConsumeOnSellEnabled()) {
            $this->emitSkipped($tenantId, $baseContext, 'feature_flag_disabled');
            return;
        }

        if ((string) ($soldProduct->production_mode ?? Product::PRODUCTION_MODE_NONE) !== Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL) {
            $this->emitSkipped($tenantId, $baseContext, 'production_mode_not_auto_consume');
            return;
        }

        $recipeLines = $this->normalizeBomItems($soldProduct->bom_items ?? []);

        $soldType = ProductItemTypeMapper::resolve(
            $soldProduct->item_type,
            (string) ($soldProduct->product_mode ?? 'default')
        );
        if ($soldType !== 'finished_good') {
            $candidateRecipeItems = collect($recipeLines)
                ->filter(fn ($line) => is_array($line)
                    && ! empty($line['product_id'])
                    && Decimal::compare(Decimal::qty($line['qty'] ?? 0), '0') > 0)
                ->values();

            if ($candidateRecipeItems->isNotEmpty()) {
                throw new AutoConsumeValidationException(
                    'AUTO_CONSUME_RECIPE_INVALID',
                    'Recipe auto-consume tidak valid.',
                    ['recipe' => ['Output recipe harus bertipe finished_good.']]
                );
            }

            $this->emitSkipped($tenantId, $baseContext, 'non_finished_good_output');
            return;
        }

        $recipeItems = collect($recipeLines)
            ->filter(fn ($line) => is_array($line)
                && ! empty($line['product_id'])
                && Decimal::compare(Decimal::qty($line['qty'] ?? 0), '0') > 0)
            ->values();

        if ($recipeItems->isEmpty()) {
            $this->emitSkipped($tenantId, $baseContext, 'no_active_recipe');
            return;
        }

        $alreadyConsumed = StockLedger::query()
            ->where('tenant_id', $tenantId)
            ->where('reference_type', 'sale_recipe_input')
            ->where('reference_id', $saleId)
            ->where('reference_item_id', $saleItemId)
            ->exists();

        if ($alreadyConsumed) {
            $this->emitSkipped($tenantId, $baseContext, 'already_consumed');
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

        $valuationService = app(InventoryValuationService::class);
        $ledgerRows = [];

        try {
            foreach ($recipeItems as $line) {
                $rawMaterialId = (string) $line['product_id'];
                $rawMaterialVariantId = $this->resolveRawMaterialVariantId($tenantId, $rawMaterialId, $line);
                $recipeQty = Decimal::qty($line['qty']);
                $requiredQty = Decimal::multiply(Decimal::qty($soldQty), $recipeQty, Decimal::QTY_SCALE);

                if (Decimal::compare($requiredQty, '0') <= 0) {
                    $this->emitSkipped($tenantId, [
                        ...$baseContext,
                        'raw_material_product_id' => $rawMaterialId,
                        'raw_material_variant_id' => $rawMaterialVariantId,
                    ], 'non_positive_required_qty');
                    continue;
                }

                if (! $allowNegative) {
                    $affected = $this->inventoryBalanceQuery(
                        tenantId: $tenantId,
                        productId: $rawMaterialId,
                        productVariantId: $rawMaterialVariantId,
                        locationId: $locationId,
                    )
                        ->where('qty_on_hand', '>=', Decimal::float($requiredQty, Decimal::QTY_SCALE))
                        ->decrement('qty_on_hand', Decimal::float($requiredQty, Decimal::QTY_SCALE));

                    if ($affected === 0) {
                        throw new AutoConsumeValidationException(
                            'AUTO_CONSUME_INSUFFICIENT_RAW_MATERIAL',
                            'Stok bahan baku tidak mencukupi untuk auto-consume.',
                            [
                                'product_id' => $rawMaterialId,
                                'product_variant_id' => $rawMaterialVariantId,
                                'location_id' => $locationId,
                                'required_qty' => Decimal::float($requiredQty, Decimal::QTY_SCALE),
                            ]
                        );
                    }
                } else {
                    DB::table('inventory_balances')->insertOrIgnore([
                        'tenant_id' => $tenantId,
                        'product_id' => $rawMaterialId,
                        'product_variant_id' => $rawMaterialVariantId,
                        'location_id' => $locationId,
                        'qty_on_hand' => 0,
                    ]);

                    $this->inventoryBalanceQuery(
                        tenantId: $tenantId,
                        productId: $rawMaterialId,
                        productVariantId: $rawMaterialVariantId,
                        locationId: $locationId,
                    )->decrement('qty_on_hand', Decimal::float($requiredQty, Decimal::QTY_SCALE));

                    app(AnomalyService::class)->raise(
                        tenantId: $tenantId,
                        severity: 'medium',
                        type: 'negative_stock_attempt',
                        payload: [
                            'source' => 'auto_consume_sale',
                            'sale_id' => $saleId,
                            'sale_item_id' => $saleItemId,
                            'product_id' => $rawMaterialId,
                            'product_variant_id' => $rawMaterialVariantId,
                            'location_id' => $locationId,
                            'requested_qty' => Decimal::float($requiredQty, Decimal::QTY_SCALE),
                            'allow_negative_policy' => true,
                        ],
                        referenceId: $saleId
                    );
                }

                $valuation = $valuationService->issue(
                    tenantId: $tenantId,
                    productId: $rawMaterialId,
                    productVariantId: $rawMaterialVariantId,
                    locationId: $locationId,
                    qty: Decimal::float($requiredQty, Decimal::QTY_SCALE),
                    referenceType: 'sale_recipe_input',
                    referenceId: $saleId,
                    referenceItemId: $saleItemId,
                    at: $at
                );

                $balanceAfter = Decimal::qty($this->inventoryBalanceQuery(
                    tenantId: $tenantId,
                    productId: $rawMaterialId,
                    productVariantId: $rawMaterialVariantId,
                    locationId: $locationId,
                )->value('qty_on_hand'));

                $ledgerRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $rawMaterialId,
                    'product_variant_id' => $rawMaterialVariantId,
                    'location_id' => $locationId,
                    'reference_type' => 'sale_recipe_input',
                    'reference_id' => $saleId,
                    'reference_item_id' => $saleItemId,
                    'qty_in' => 0,
                    'qty_out' => Decimal::float($requiredQty, Decimal::QTY_SCALE),
                    'balance_after' => Decimal::float($balanceAfter, Decimal::QTY_SCALE),
                    'unit_cost' => Decimal::float(Decimal::cost($valuation['unit_cost'] ?? 0), Decimal::COST_SCALE),
                    'total_cost' => Decimal::float(Decimal::cost($valuation['total_cost'] ?? 0), Decimal::COST_SCALE),
                    'created_at' => $at,
                    'created_by' => $userId,
                ];

                $this->emitEvent('auto_consume.succeeded', $tenantId, [
                    ...$baseContext,
                    'raw_material_product_id' => $rawMaterialId,
                    'raw_material_variant_id' => $rawMaterialVariantId,
                    'qty_requested' => Decimal::float($requiredQty, Decimal::QTY_SCALE),
                    'qty_consumed' => Decimal::float($requiredQty, Decimal::QTY_SCALE),
                ]);
            }

            if ($ledgerRows) {
                StockLedger::query()->insert($ledgerRows);
            }
        } catch (AutoConsumeValidationException $e) {
            $this->emitEvent('auto_consume.failed', $tenantId, [
                ...$baseContext,
                'error_code' => $e->errorCode(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            $this->emitEvent('auto_consume.failed', $tenantId, [
                ...$baseContext,
                'error_code' => 'AUTO_CONSUME_INTERNAL_ERROR',
            ]);

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function emitSkipped(string $tenantId, array $context, string $reason): void
    {
        $this->emitEvent('auto_consume.skipped', $tenantId, [
            ...$context,
            'skip_reason' => $reason,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function emitEvent(string $event, string $tenantId, array $context): void
    {
        Log::info($event, $context);
        app(ValuationAuditService::class)->log($tenantId, $event, $context);
    }

    /**
     * @param mixed $bomItems
     * @return array<int, array<string, mixed>>
     */
    private function normalizeBomItems(mixed $bomItems): array
    {
        if (is_array($bomItems)) {
            return $bomItems;
        }

        if (is_string($bomItems) && $bomItems !== '') {
            $decoded = json_decode($bomItems, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $line
     */
    private function resolveRawMaterialVariantId(string $tenantId, string $productId, array $line): ?string
    {
        $variantId = isset($line['product_variant_id']) && $line['product_variant_id'] !== ''
            ? (string) $line['product_variant_id']
            : null;

        $hasVariants = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->exists();

        if (! $hasVariants) {
            if ($variantId !== null) {
                $variantExists = DB::table('product_variants')
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('id', $variantId)
                    ->exists();

                if (! $variantExists) {
                    throw new AutoConsumeValidationException(
                        'AUTO_CONSUME_RECIPE_INVALID',
                        'Recipe auto-consume tidak valid.',
                        ['recipe' => ['Variant bahan baku pada recipe tidak cocok dengan product_id komponen.']]
                    );
                }
            }

            return $variantId;
        }

        if ($variantId === null) {
            throw new AutoConsumeValidationException(
                'AUTO_CONSUME_RECIPE_INVALID',
                'Recipe auto-consume tidak valid.',
                ['recipe' => ['Bahan baku bervarian wajib menyimpan product_variant_id pada recipe.']]
            );
        }

        $resolvedVariantId = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('id', $variantId)
            ->value('id');

        if (! $resolvedVariantId) {
            throw new AutoConsumeValidationException(
                'AUTO_CONSUME_RECIPE_INVALID',
                'Recipe auto-consume tidak valid.',
                ['recipe' => ['Variant bahan baku pada recipe tidak cocok dengan product_id komponen.']]
            );
        }

        return (string) $resolvedVariantId;
    }

    private function inventoryBalanceQuery(string $tenantId, string $productId, ?string $productVariantId, string $locationId)
    {
        return DB::table('inventory_balances')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->when($productVariantId !== null, function ($query) use ($productVariantId) {
                $query->where('product_variant_id', $productVariantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            });
    }
}
