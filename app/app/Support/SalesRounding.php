<?php

namespace App\Support;

final class SalesRounding
{
    public static function apply(float $amount, ?string $mode, int $increment = 0): float
    {
        $amount = Decimal::float($amount, Decimal::MONEY_SCALE);

        return match ($mode) {
            'nearest' => Decimal::float(round($amount), Decimal::MONEY_SCALE),
            'up' => Decimal::float(ceil($amount), Decimal::MONEY_SCALE),
            'down' => Decimal::float(floor($amount), Decimal::MONEY_SCALE),
            'multiple' => self::applyMultiple($amount, $increment),
            default => $amount,
        };
    }

    private static function applyMultiple(float $amount, int $increment): float
    {
        if ($increment <= 0) {
            return $amount;
        }

        return Decimal::float(ceil($amount / $increment) * $increment, Decimal::MONEY_SCALE);
    }
}
