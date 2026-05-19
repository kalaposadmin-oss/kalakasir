<?php

namespace App\Services;

use App\Support\Decimal;
use Illuminate\Validation\ValidationException;

class UnitConversionService
{
    private const UNITS = [
        'mg' => ['dimension' => 'mass', 'factor' => '0.001', 'label' => 'mg'],
        'gram' => ['dimension' => 'mass', 'factor' => '1', 'label' => 'gram'],
        'g' => ['dimension' => 'mass', 'factor' => '1', 'label' => 'gram'],
        'kg' => ['dimension' => 'mass', 'factor' => '1000', 'label' => 'kg'],
        'kilogram' => ['dimension' => 'mass', 'factor' => '1000', 'label' => 'kg'],

        'ml' => ['dimension' => 'volume', 'factor' => '1', 'label' => 'ml'],
        'liter' => ['dimension' => 'volume', 'factor' => '1000', 'label' => 'liter'],
        'l' => ['dimension' => 'volume', 'factor' => '1000', 'label' => 'liter'],

        'cm' => ['dimension' => 'length', 'factor' => '1', 'label' => 'cm'],
        'meter' => ['dimension' => 'length', 'factor' => '100', 'label' => 'meter'],
        'm' => ['dimension' => 'length', 'factor' => '100', 'label' => 'meter'],

        'pcs' => ['dimension' => 'count', 'factor' => '1', 'label' => 'pcs'],
        'pc' => ['dimension' => 'count', 'factor' => '1', 'label' => 'pcs'],
        'unit' => ['dimension' => 'count', 'factor' => '1', 'label' => 'unit'],
        'dozen' => ['dimension' => 'count', 'factor' => '12', 'label' => 'dozen'],
        'lusin' => ['dimension' => 'count', 'factor' => '12', 'label' => 'lusin'],
    ];

    public function normalizeUnit(?string $unit): string
    {
        return trim(mb_strtolower((string) $unit));
    }

    public function displayUnit(?string $unit): ?string
    {
        $normalized = $this->normalizeUnit($unit);

        return self::UNITS[$normalized]['label'] ?? ($normalized !== '' ? $normalized : null);
    }

    public function convertQuantity(mixed $qty, ?string $fromUnit, ?string $toUnit, string $field = 'items'): string
    {
        $from = $this->normalizeUnit($fromUnit);
        $to = $this->normalizeUnit($toUnit);

        if ($from === '' || $to === '' || $from === $to) {
            return Decimal::qty($qty);
        }

        $fromMeta = self::UNITS[$from] ?? null;
        $toMeta = self::UNITS[$to] ?? null;

        if (! $fromMeta || ! $toMeta || $fromMeta['dimension'] !== $toMeta['dimension']) {
            throw ValidationException::withMessages([
                $field => sprintf('Satuan %s tidak bisa dikonversi ke %s.', $fromUnit ?: '-', $toUnit ?: '-'),
            ]);
        }

        $baseQty = Decimal::multiply($qty, $fromMeta['factor'], Decimal::QTY_SCALE);

        return Decimal::divide($baseQty, $toMeta['factor'], Decimal::QTY_SCALE);
    }

    public function multiplier(?string $fromUnit, ?string $toUnit, string $field = 'items'): string
    {
        return Decimal::divide(
            $this->convertQuantity(1, $fromUnit, $toUnit, $field),
            1,
            Decimal::QTY_SCALE
        );
    }

    public function compatibleUnits(?string $unit): array
    {
        $normalized = $this->normalizeUnit($unit);
        $meta = self::UNITS[$normalized] ?? null;

        if (! $meta) {
            return $normalized !== '' ? [$normalized] : [];
        }

        return collect(self::UNITS)
            ->filter(fn (array $candidate) => $candidate['dimension'] === $meta['dimension'])
            ->pluck('label')
            ->unique()
            ->values()
            ->all();
    }
}
