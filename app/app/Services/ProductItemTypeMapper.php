<?php

namespace App\Services;

class ProductItemTypeMapper
{
    public const PRODUCT_MODES = ['default', 'variant', 'bom', 'bundle', 'add_on'];

    public const ITEM_TYPES = ['finished_good', 'raw_material', 'service'];

    public static function mapFromMode(string $productMode): string
    {
        return $productMode === 'add_on' ? 'raw_material' : 'finished_good';
    }

    public static function resolve(?string $itemType, string $productMode): string
    {
        $normalized = trim((string) ($itemType ?? ''));

        if ($normalized !== '') {
            return $normalized;
        }

        return self::mapFromMode($productMode);
    }
}
