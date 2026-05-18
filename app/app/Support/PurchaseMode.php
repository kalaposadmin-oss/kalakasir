<?php

namespace App\Support;

final class PurchaseMode
{
    public const SIMPLE = 'simple';
    public const PO_SHORT = 'po_short';
    public const CONTROLLED = 'controlled';

    public static function all(): array
    {
        return [self::SIMPLE, self::PO_SHORT, self::CONTROLLED];
    }

    public static function normalize(?string $mode): string
    {
        $value = strtolower(trim((string) $mode));

        return in_array($value, self::all(), true) ? $value : self::SIMPLE;
    }

    public static function isSimple(?string $mode): bool
    {
        return self::normalize($mode) === self::SIMPLE;
    }

    public static function isPoShort(?string $mode): bool
    {
        return self::normalize($mode) === self::PO_SHORT;
    }

    public static function isControlled(?string $mode): bool
    {
        return self::normalize($mode) === self::CONTROLLED;
    }

    public static function labels(): array
    {
        return [
            self::SIMPLE => 'Pembelian Langsung',
            self::PO_SHORT => 'PO Singkat',
            self::CONTROLLED => 'Terkontrol',
        ];
    }

    public static function label(?string $mode): string
    {
        $normalized = self::normalize($mode);

        return self::labels()[$normalized] ?? self::labels()[self::SIMPLE];
    }
}