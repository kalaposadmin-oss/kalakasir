<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Decimal
{
    public const MONEY_SCALE = 2;

    public const QTY_SCALE = 4;

    public const COST_SCALE = 6;

    public const RATE_SCALE = 8;

    public static function money(mixed $value): string
    {
        return self::scale($value, self::MONEY_SCALE);
    }

    public static function qty(mixed $value): string
    {
        return self::scale($value, self::QTY_SCALE);
    }

    public static function cost(mixed $value): string
    {
        return self::scale($value, self::COST_SCALE);
    }

    public static function rate(mixed $value): string
    {
        return self::scale($value, self::RATE_SCALE);
    }

    public static function scale(mixed $value, int $scale, RoundingMode $roundingMode = RoundingMode::HALF_UP): string
    {
        return (string) self::decimal($value)->toScale($scale, $roundingMode);
    }

    public static function add(mixed $left, mixed $right, ?int $scale = null): string
    {
        $result = self::decimal($left)->plus(self::decimal($right));

        return $scale === null ? (string) $result : (string) $result->toScale($scale, RoundingMode::HALF_UP);
    }

    public static function subtract(mixed $left, mixed $right, ?int $scale = null): string
    {
        $result = self::decimal($left)->minus(self::decimal($right));

        return $scale === null ? (string) $result : (string) $result->toScale($scale, RoundingMode::HALF_UP);
    }

    public static function multiply(mixed $left, mixed $right, int $scale, RoundingMode $roundingMode = RoundingMode::HALF_UP): string
    {
        return (string) self::decimal($left)
            ->multipliedBy(self::decimal($right))
            ->toScale($scale, $roundingMode);
    }

    public static function divide(mixed $left, mixed $right, int $scale, RoundingMode $roundingMode = RoundingMode::HALF_UP): string
    {
        if (self::compare($right, '0') === 0) {
            return self::scale('0', $scale, $roundingMode);
        }

        return (string) self::decimal($left)
            ->dividedBy(self::decimal($right), $scale, $roundingMode);
    }

    public static function compare(mixed $left, mixed $right): int
    {
        return self::decimal($left)->compareTo(self::decimal($right));
    }

    public static function min(mixed $left, mixed $right, ?int $scale = null): string
    {
        return self::compare($left, $right) <= 0
            ? ($scale === null ? (string) self::decimal($left) : self::scale($left, $scale))
            : ($scale === null ? (string) self::decimal($right) : self::scale($right, $scale));
    }

    public static function max(mixed $left, mixed $right, ?int $scale = null): string
    {
        return self::compare($left, $right) >= 0
            ? ($scale === null ? (string) self::decimal($left) : self::scale($left, $scale))
            : ($scale === null ? (string) self::decimal($right) : self::scale($right, $scale));
    }

    public static function abs(mixed $value, ?int $scale = null): string
    {
        $result = self::decimal($value)->abs();

        return $scale === null ? (string) $result : (string) $result->toScale($scale, RoundingMode::HALF_UP);
    }

    public static function float(mixed $value, ?int $scale = null): float
    {
        return (float) ($scale === null ? (string) self::decimal($value) : self::scale($value, $scale));
    }

    private static function decimal(mixed $value): BigDecimal
    {
        if ($value instanceof BigDecimal) {
            return $value;
        }

        if (is_int($value) || is_string($value)) {
            return BigDecimal::of((string) $value);
        }

        if (is_float($value)) {
            return BigDecimal::of(self::normalizeFloat($value));
        }

        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }

        return BigDecimal::of((string) $value);
    }

    private static function normalizeFloat(float $value): string
    {
        $normalized = sprintf('%.14F', $value);
        $normalized = rtrim(rtrim($normalized, '0'), '.');

        return $normalized === '' || $normalized === '-0' ? '0' : $normalized;
    }
}