<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductionBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductItemTypeValidator
{
    public static function isModeItemTypeValid(string $productMode, string $itemType): bool
    {
        $allowed = [
            'default' => ['finished_good', 'service'],
            'variant' => ['finished_good', 'service'],
            'bom' => ['finished_good'],
            'bundle' => ['finished_good'],
            'add_on' => ['raw_material'],
        ];

        return in_array($itemType, $allowed[$productMode] ?? ['finished_good'], true);
    }

    public static function assertModeItemTypeValid(string $productMode, string $itemType): void
    {
        if (! self::isModeItemTypeValid($productMode, $itemType)) {
            throw ValidationException::withMessages([
                'item_type' => 'Kombinasi product_mode dan item_type tidak valid.',
            ]);
        }
    }

    public static function assertServiceTrackStockValid(string $itemType, bool $trackStock): void
    {
        if ($itemType === 'service' && $trackStock) {
            throw ValidationException::withMessages([
                'track_stock' => 'Produk service tidak boleh menggunakan stok.',
            ]);
        }
    }

    public static function assertProductionModeValid(string $productionMode, string $itemType, bool $trackStock): void
    {
        if (! in_array($productionMode, Product::PRODUCTION_MODES, true)) {
            throw ValidationException::withMessages([
                'production_mode' => 'Production mode tidak valid.',
            ]);
        }

        if ($productionMode === Product::PRODUCTION_MODE_BATCH && $itemType !== 'finished_good') {
            throw ValidationException::withMessages([
                'production_mode' => 'Produk batch production harus bertipe finished_good.',
            ]);
        }

        if ($productionMode === Product::PRODUCTION_MODE_BATCH && ! $trackStock) {
            throw ValidationException::withMessages([
                'production_mode' => 'Produk batch production wajib track_stock.',
            ]);
        }
    }

    /**
     * Prevent switching to auto_consume_on_sell if the product has ever been
     * manufactured via batch production.  Allowing the switch would cause
     * double deduction: raw materials already consumed during production
     * would be consumed *again* at POS sale time.
     */
    public static function assertNotProducedForAutoConsume(
        string $tenantId,
        string $productId,
        string $productionMode
    ): void {
        if ($productionMode !== Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL) {
            return;
        }

        $hasPostedBatch = DB::table('production_batches')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('status', ProductionBatch::STATUS_POSTED)
            ->exists();

        if ($hasPostedBatch) {
            throw ValidationException::withMessages([
                'production_mode' => 'Produk ini pernah diproduksi. Tidak bisa menggunakan mode auto-consume saat jual karena bahan baku sudah dipotong saat produksi.',
            ]);
        }
    }

    public static function assertBomComponentsValid(string $tenantId, array $bomItems): void
    {
        $bomProductIds = collect($bomItems)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        $validCount = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('product_mode', 'add_on')
            ->where(function ($query) {
                $query->whereNull('item_type')
                    ->orWhere('item_type', '!=', 'service');
            })
            ->whereIn('id', $bomProductIds)
            ->count();

        if ($validCount !== $bomProductIds->count()) {
            throw ValidationException::withMessages([
                'bom_items' => 'Bahan baku harus berasal dari produk add on non-service.',
            ]);
        }

        $variantProductIds = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->whereIn('product_id', $bomProductIds)
            ->pluck('product_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->all();

        $providedVariantIds = collect($bomItems)
            ->pluck('product_variant_id')
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->values();

        $variantProductMap = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $providedVariantIds)
            ->pluck('product_id', 'id')
            ->mapWithKeys(fn ($productId, $variantId) => [(string) $variantId => (string) $productId])
            ->all();

        $errors = [];
        $seenLines = [];

        foreach ($bomItems as $index => $bomItem) {
            $productId = (string) ($bomItem['product_id'] ?? '');
            if ($productId === '') {
                continue;
            }

            $variantId = isset($bomItem['product_variant_id']) && $bomItem['product_variant_id'] !== ''
                ? (string) $bomItem['product_variant_id']
                : null;

            $hasVariants = in_array($productId, $variantProductIds, true);

            if ($hasVariants && $variantId === null) {
                $errors['bom_items.'.$index.'.product_variant_id'] = 'Bahan baku bervarian wajib memilih variant recipe.';
                continue;
            }

            if ($variantId !== null && (($variantProductMap[$variantId] ?? null) !== $productId)) {
                $errors['bom_items.'.$index.'.product_variant_id'] = 'Variant recipe tidak cocok dengan bahan baku yang dipilih.';
                continue;
            }

            $lineKey = $productId.'|'.($variantId ?? '__no_variant__');
            if (isset($seenLines[$lineKey])) {
                $errors['bom_items.'.$index.'.product_id'] = 'Bahan baku duplikat. Gabungkan qty pada satu line yang sama.';
                continue;
            }

            $seenLines[$lineKey] = true;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
