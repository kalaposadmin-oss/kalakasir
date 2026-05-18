<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Product;
use App\Support\Decimal;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionEligibilityService
{
    public function summarizeBatchEligibility(string $tenantId, array|Product $product): array
    {
        $productData = $product instanceof Product ? $product->toArray() : $product;
        $productMode = (string) data_get($productData, 'product_mode', 'default');
        $itemType = ProductItemTypeMapper::resolve(data_get($productData, 'item_type'), $productMode);
        $productionMode = (string) data_get($productData, 'production_mode', Product::PRODUCTION_MODE_NONE);
        $trackStock = (bool) data_get($productData, 'track_stock', false);
        $bomItems = $this->decodeBomItems(data_get($productData, 'bom_items', []));
        $recipeOutputQty = Decimal::snappedQty(data_get($productData, 'recipe_output_qty', 0));

        $checks = [];
        $reasons = [];

        $addCheck = function (string $key, string $label, bool $passed, string $reason, array $meta = []) use (&$checks, &$reasons): void {
            $checks[] = [
                'key' => $key,
                'label' => $label,
                'passed' => $passed,
                'reason' => $reason,
                'meta' => $meta,
            ];

            if (! $passed) {
                $reasons[] = $reason;
            }
        };

        $addCheck(
            'is_active',
            'Produk aktif',
            (bool) data_get($productData, 'is_active', false),
            'Aktifkan produk terlebih dahulu agar bisa diproduksi.'
        );

        $addCheck(
            'production_mode',
            'Mode produksi batch',
            $productionMode === Product::PRODUCTION_MODE_BATCH,
            'Set mode produksi ke batch_production.'
        );

        $addCheck(
            'item_type',
            'Tipe produk jadi',
            $itemType === 'finished_good',
            'Ubah tipe produk menjadi finished_good.'
        );

        $addCheck(
            'track_stock',
            'Track stock aktif',
            $trackStock,
            'Aktifkan track stock agar batch bisa dihitung dan diposting.'
        );

        $addCheck(
            'bom_items',
            'Recipe / BOM terisi',
            $bomItems !== [],
            'Tambahkan minimal satu bahan baku di recipe / BOM.'
        );

        $addCheck(
            'recipe_output_qty',
            'Hasil standar per resep',
            $productionMode !== Product::PRODUCTION_MODE_BATCH || Decimal::compare($recipeOutputQty, '0') > 0,
            'Isi hasil standar per 1 resep agar jumlah resep dan output harapan bisa dihitung otomatis.',
            [
                'recipe_output_qty' => Decimal::float($recipeOutputQty, Decimal::QTY_SCALE),
            ]
        );

        if ($bomItems !== []) {
            $recipeChecks = $this->inspectRecipeEligibility($tenantId, $bomItems);
            $checks = array_merge($checks, $recipeChecks['checks']);
            $reasons = array_merge($reasons, $recipeChecks['reasons']);
        }

        $reasons = array_values(array_unique(array_filter($reasons)));

        return [
            'eligible' => $reasons === [],
            'reason_count' => count($reasons),
            'reasons' => $reasons,
            'checks' => $checks,
            'summary' => [
                'product_mode' => $productMode,
                'item_type' => $itemType,
                'production_mode' => $productionMode,
                'track_stock' => $trackStock,
                'bom_line_count' => count($bomItems),
                'recipe_output_qty' => Decimal::float($recipeOutputQty, Decimal::QTY_SCALE),
            ],
        ];
    }

    public function inspectRecipePreview(string $tenantId, string $productId, string $locationId, float|int|string $quantityRequested): array
    {
        if (trim($locationId) === '') {
            throw ValidationException::withMessages([
                'location_id' => 'Lokasi wajib dipilih untuk melihat preview produksi.',
            ]);
        }

        $location = $this->loadPreviewLocation($tenantId, $locationId);
        $product = $this->loadBatchProduct($tenantId, $productId);
        $bomItems = $this->decodeBomItems($product->bom_items ?? []);
        $recipeCount = Decimal::qty($quantityRequested);
        $recipeOutputQty = Decimal::snappedQty($product->recipe_output_qty ?? 0);

        if (Decimal::compare($recipeOutputQty, '0') <= 0) {
            return [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                ],
                'location_id' => $location->id,
                'qty_requested' => Decimal::float($recipeCount, Decimal::QTY_SCALE),
                'recipe_count' => Decimal::float($recipeCount, Decimal::QTY_SCALE),
                'recipe_output_qty' => 0.0,
                'expected_output_qty' => 0.0,
                'bom_items' => $bomItems,
                'ingredients' => [],
                'can_post' => false,
                'warning_message' => 'Produk ini belum punya hasil standar per 1 resep. Lengkapi dulu di master produk.',
            ];
        }

        $expectedOutputQty = Decimal::multiply($recipeCount, $recipeOutputQty, Decimal::QTY_SCALE);

        if ($bomItems === []) {
            return [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                ],
                'location_id' => $location->id,
                'qty_requested' => Decimal::float($recipeCount, Decimal::QTY_SCALE),
                'recipe_count' => Decimal::float($recipeCount, Decimal::QTY_SCALE),
                'recipe_output_qty' => Decimal::float($recipeOutputQty, Decimal::QTY_SCALE),
                'expected_output_qty' => Decimal::float($expectedOutputQty, Decimal::QTY_SCALE),
                'bom_items' => [],
                'ingredients' => [],
                'can_post' => false,
                'warning_message' => 'Recipe aktif tidak ditemukan untuk produk batch production.',
            ];
        }

        $recipeLines = $this->normalizeRecipeLines($product);
        $componentIds = collect($recipeLines)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values()
            ->all();

        $balances = InventoryBalance::query()
            ->where('tenant_id', $tenantId)
            ->where('location_id', $location->id)
            ->when($componentIds !== [], fn ($query) => $query->whereIn('product_id', $componentIds))
            ->get(['product_id', 'product_variant_id', 'qty_on_hand'])
            ->groupBy(function ($row) {
                return implode('|', [
                    (string) $row->product_id,
                    (string) ($row->product_variant_id ?? ''),
                ]);
            });

        $products = $this->loadProductsForRecipe(
            $tenantId,
            collect($recipeLines)->pluck('product_id')->all()
        );

        $ingredientRows = collect($recipeLines)->map(function (array $line) use ($balances, $recipeCount, $products) {
            $materialId = (string) $line['product_id'];
            $materialVariantId = $line['product_variant_id'] ?? null;
            $recipeQty = (float) $line['qty'];
            $requiredQty = round($recipeQty * Decimal::float($recipeCount, Decimal::QTY_SCALE), 4);
            $balanceKey = implode('|', [$materialId, (string) ($materialVariantId ?? '')]);
            $availableQty = (float) (($balances[$balanceKey] ?? collect())->sum('qty_on_hand'));
            $shortageQty = max(0, $requiredQty - $availableQty);
            $material = $products->get($materialId);

            return [
                'material_id' => $materialId,
                'material_variant_id' => $materialVariantId,
                'material_name' => $material->name ?? 'Bahan baku',
                'material_sku' => $material->sku ?? null,
                'unit' => $material->unit ?? 'unit',
                'recipe_qty' => (float) $recipeQty,
                'required_qty' => (float) $requiredQty,
                'available_qty' => (float) $availableQty,
                'shortage_qty' => (float) $shortageQty,
                'shortage_flag' => $shortageQty > 0,
                'stock_status' => $shortageQty > 0 ? 'kurang' : 'cukup',
            ];
        })->values();

        $hasShortage = $ingredientRows->contains(fn ($row) => (bool) ($row['shortage_flag'] ?? false));

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
            ],
            'location_id' => $location->id,
            'qty_requested' => Decimal::float($recipeCount, Decimal::QTY_SCALE),
            'recipe_count' => Decimal::float($recipeCount, Decimal::QTY_SCALE),
            'recipe_output_qty' => Decimal::float($recipeOutputQty, Decimal::QTY_SCALE),
            'expected_output_qty' => Decimal::float($expectedOutputQty, Decimal::QTY_SCALE),
            'bom_items' => $bomItems,
            'ingredients' => $ingredientRows,
            'can_post' => ! $hasShortage,
            'warning_message' => $hasShortage ? 'Stok bahan belum cukup untuk batch ini.' : null,
        ];
    }

    public function loadLocation(string $tenantId, string $branchId, string $locationId): Location
    {
        $location = Location::query()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('id', $locationId)
            ->first();

        if (! $location) {
            throw ValidationException::withMessages([
                'location_id' => 'Location tidak valid untuk branch dan tenant aktif.',
            ]);
        }

        return $location;
    }

    public function loadPreviewLocation(string $tenantId, string $locationId): Location
    {
        $location = Location::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $locationId)
            ->first();

        if (! $location) {
            throw ValidationException::withMessages([
                'location_id' => 'Location tidak valid untuk tenant aktif.',
            ]);
        }

        return $location;
    }

    public function loadBatchProduct(string $tenantId, string $productId): Product
    {
        $product = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $productId)
            ->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk tidak ditemukan untuk tenant aktif.',
            ]);
        }

        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk batch production harus aktif.',
            ]);
        }

        if ((string) ($product->production_mode ?? Product::PRODUCTION_MODE_NONE) !== Product::PRODUCTION_MODE_BATCH) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk harus menggunakan production_mode batch_production.',
            ]);
        }

        $resolvedType = ProductItemTypeMapper::resolve(
            $product->item_type,
            (string) ($product->product_mode ?? 'default')
        );

        if ($resolvedType !== 'finished_good' || ! $product->track_stock) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk batch production harus finished_good dan track_stock aktif.',
            ]);
        }

        if (Decimal::compare(Decimal::qty($product->recipe_output_qty ?? 0), '0') <= 0) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk batch production wajib punya hasil standar per 1 resep.',
            ]);
        }

        return $product;
    }

    public function normalizeRecipeLines(Product $product): array
    {
        $recipeLines = collect($this->decodeBomItems($product->bom_items ?? []))
            ->filter(fn ($line) => is_array($line) && ! empty($line['product_id']))
            ->map(function (array $line) {
                return [
                    'product_id' => (string) $line['product_id'],
                    'product_variant_id' => Arr::get($line, 'product_variant_id'),
                    'qty' => Decimal::qty($line['qty'] ?? 0),
                    'unit' => Arr::get($line, 'unit'),
                    'uom' => Arr::get($line, 'uom'),
                ];
            })
            ->filter(fn (array $line) => Decimal::compare($line['qty'], '0') > 0)
            ->values();

        if ($recipeLines->isEmpty()) {
            throw ValidationException::withMessages([
                'product_id' => 'Recipe aktif tidak ditemukan untuk produk batch production.',
            ]);
        }

        $products = $this->loadProductsForRecipe((string) $product->tenant_id, $recipeLines->pluck('product_id')->all());

        foreach ($recipeLines as $index => $line) {
            $recipeProduct = $products->get($line['product_id']);
            $resolvedType = ProductItemTypeMapper::resolve(
                $recipeProduct?->item_type,
                (string) ($recipeProduct?->product_mode ?? 'default')
            );

            if ($resolvedType !== 'raw_material') {
                throw ValidationException::withMessages([
                    'product_id' => 'Semua input recipe batch production harus bertipe raw_material.',
                ]);
            }

            if (! empty($line['product_variant_id'])) {
                $this->resolveVariantId((string) $product->tenant_id, $line['product_id'], (string) $line['product_variant_id']);
            } elseif ($this->productHasVariants((string) $product->tenant_id, $line['product_id'])) {
                throw ValidationException::withMessages([
                    'product_id' => sprintf('Recipe input index %d wajib menyimpan product_variant_id.', $index),
                ]);
            }
        }

        return $recipeLines->all();
    }

    private function decodeBomItems(mixed $bomItems): array
    {
        if (is_string($bomItems)) {
            $bomItems = json_decode($bomItems, true);
        }

        if (! is_array($bomItems)) {
            return [];
        }

        return array_values(array_filter($bomItems, static fn ($item) => is_array($item) && ! empty($item['product_id'])));
    }

    private function inspectRecipeEligibility(string $tenantId, array $recipeLines): array
    {
        $checks = [];
        $reasons = [];

        $componentIds = collect($recipeLines)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values()
            ->all();

        if ($componentIds === []) {
            return ['checks' => [], 'reasons' => []];
        }

        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $componentIds)
            ->get(['id', 'item_type', 'product_mode', 'name', 'sku'])
            ->keyBy('id');

        $variantIdsByProduct = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->whereIn('product_id', $componentIds)
            ->get(['product_id', 'id'])
            ->groupBy('product_id')
            ->map(fn (Collection $rows) => $rows->pluck('id')->map(fn ($value) => (string) $value)->values()->all())
            ->all();

        $seenLineKeys = [];

        foreach ($recipeLines as $index => $line) {
            $lineNumber = $index + 1;
            $componentId = (string) ($line['product_id'] ?? '');
            $variantId = Arr::get($line, 'product_variant_id');
            $lineKey = implode('|', [$componentId, (string) ($variantId ?? '')]);

            if (isset($seenLineKeys[$lineKey])) {
                $reason = sprintf('Line BOM #%d duplikat dengan line #%d. Gabungkan qty pada satu bahan.', $lineNumber, $seenLineKeys[$lineKey]);
                $checks[] = [
                    'key' => 'bom_duplicate_'.$lineNumber,
                    'label' => 'Duplikasi line BOM',
                    'passed' => false,
                    'reason' => $reason,
                    'meta' => ['line' => $lineNumber, 'duplicate_of' => $seenLineKeys[$lineKey]],
                ];
                $reasons[] = $reason;
                continue;
            }

            $seenLineKeys[$lineKey] = $lineNumber;

            $component = $products->get($componentId);
            if (! $component) {
                $reason = sprintf('Line BOM #%d: bahan baku tidak ditemukan atau tidak aktif.', $lineNumber);
                $checks[] = [
                    'key' => 'bom_missing_'.$lineNumber,
                    'label' => 'Komponen BOM ditemukan',
                    'passed' => false,
                    'reason' => $reason,
                    'meta' => ['line' => $lineNumber, 'product_id' => $componentId],
                ];
                $reasons[] = $reason;
                continue;
            }

            $resolvedType = ProductItemTypeMapper::resolve(
                $component->item_type,
                (string) ($component->product_mode ?? 'default')
            );

            if ($resolvedType !== 'raw_material') {
                $reason = sprintf('Line BOM #%d (%s): komponen harus raw_material.', $lineNumber, $component->name ?? 'bahan baku');
                $checks[] = [
                    'key' => 'bom_type_'.$lineNumber,
                    'label' => 'Tipe komponen raw material',
                    'passed' => false,
                    'reason' => $reason,
                    'meta' => ['line' => $lineNumber, 'product_id' => $componentId],
                ];
                $reasons[] = $reason;
            }

            $availableVariantIds = $variantIdsByProduct[$componentId] ?? [];
            $hasVariants = $availableVariantIds !== [];

            if ($hasVariants && empty($variantId)) {
                $reason = sprintf('Line BOM #%d (%s): bahan ini punya variant, product_variant_id wajib diisi.', $lineNumber, $component->name ?? 'bahan baku');
                $checks[] = [
                    'key' => 'bom_variant_required_'.$lineNumber,
                    'label' => 'Variant komponen dipilih',
                    'passed' => false,
                    'reason' => $reason,
                    'meta' => ['line' => $lineNumber, 'product_id' => $componentId],
                ];
                $reasons[] = $reason;
            }

            if (! empty($variantId) && ! in_array((string) $variantId, $availableVariantIds, true)) {
                $reason = sprintf('Line BOM #%d (%s): variant yang dipilih tidak cocok dengan bahan baku.', $lineNumber, $component->name ?? 'bahan baku');
                $checks[] = [
                    'key' => 'bom_variant_mismatch_'.$lineNumber,
                    'label' => 'Variant komponen valid',
                    'passed' => false,
                    'reason' => $reason,
                    'meta' => ['line' => $lineNumber, 'product_id' => $componentId, 'product_variant_id' => $variantId],
                ];
                $reasons[] = $reason;
            }
        }

        return [
            'checks' => $checks,
            'reasons' => $reasons,
        ];
    }

    private function loadProductsForRecipe(string $tenantId, array $productIds): Collection
    {
        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('id', $productIds)
            ->get(['id', 'item_type', 'product_mode', 'name', 'sku', 'unit'])
            ->keyBy('id');

        if ($products->count() !== count(array_unique($productIds))) {
            throw ValidationException::withMessages([
                'product_id' => 'Komponen recipe tidak ditemukan untuk tenant aktif.',
            ]);
        }

        return $products;
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
                throw ValidationException::withMessages([
                    'product_id' => 'Varian produk batch produksi tidak valid.',
                ]);
            }

            return $variantId;
        }

        $hasVariants = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->exists();

        if ($hasVariants) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk varian memerlukan product_variant_id pada production batch.',
            ]);
        }

        return null;
    }

    private function productHasVariants(string $tenantId, string $productId): bool
    {
        return DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->exists();
    }
}
