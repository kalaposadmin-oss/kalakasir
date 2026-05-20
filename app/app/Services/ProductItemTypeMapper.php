<?php

namespace App\Services;

class ProductItemTypeMapper
{
    public const PRODUCT_MODES = ['default', 'variant', 'bom', 'bundle', 'add_on'];

    public const ITEM_FINISHED_GOOD = 'finished_good';
    public const ITEM_RAW_MATERIAL = 'raw_material';
    public const ITEM_PACKAGING = 'packaging';
    public const ITEM_SUPPLY = 'supply';
    public const ITEM_SMALL_TOOL = 'small_tool';
    public const ITEM_SERVICE = 'service';

    public const ITEM_TYPES = [
        self::ITEM_FINISHED_GOOD,
        self::ITEM_RAW_MATERIAL,
        self::ITEM_PACKAGING,
        self::ITEM_SUPPLY,
        self::ITEM_SMALL_TOOL,
        self::ITEM_SERVICE,
    ];

    public const STOCKED_NON_SELLABLE_TYPES = [
        self::ITEM_RAW_MATERIAL,
        self::ITEM_PACKAGING,
        self::ITEM_SUPPLY,
        self::ITEM_SMALL_TOOL,
    ];

    public const RECIPE_COMPONENT_TYPES = [
        self::ITEM_RAW_MATERIAL,
        self::ITEM_PACKAGING,
    ];

    public static function mapFromMode(string $productMode): string
    {
        return $productMode === 'add_on' ? self::ITEM_RAW_MATERIAL : self::ITEM_FINISHED_GOOD;
    }

    public static function resolve(?string $itemType, string $productMode): string
    {
        $normalized = trim((string) ($itemType ?? ''));

        if ($normalized !== '' && in_array($normalized, self::ITEM_TYPES, true)) {
            return $normalized;
        }

        return self::mapFromMode($productMode);
    }

    public static function isSellable(string $itemType, string $productMode): bool
    {
        if ($productMode === 'add_on') {
            return false;
        }

        return in_array($itemType, [self::ITEM_FINISHED_GOOD, self::ITEM_SERVICE], true);
    }

    public static function isStockedNonSellable(string $itemType): bool
    {
        return in_array($itemType, self::STOCKED_NON_SELLABLE_TYPES, true);
    }

    public static function isRecipeComponent(string $itemType): bool
    {
        return in_array($itemType, self::RECIPE_COMPONENT_TYPES, true);
    }
}
