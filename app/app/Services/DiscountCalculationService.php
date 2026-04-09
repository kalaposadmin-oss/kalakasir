<?php

namespace App\Services;

use App\Support\Decimal;
use Illuminate\Support\Arr;

class DiscountCalculationService
{
    public function calculate(array $payload): array
    {
        $items = [];
        $subtotal = Decimal::money(0);
        $itemDiscountTotal = Decimal::money(0);
        $transactionBase = Decimal::money(0);

        foreach ((array) ($payload['items'] ?? []) as $index => $item) {
            $qty = Decimal::qty(Arr::get($item, 'qty', 0));
            $price = Decimal::money(Arr::get($item, 'price', 0));
            $lineSubtotal = Decimal::multiply($qty, $price, Decimal::MONEY_SCALE);
            $lineType = Arr::get($item, 'line_type', 'product') === 'fee' ? 'fee' : 'product';

            $normalizedItemDiscounts = $this->normalizeItemDiscounts($item);
            $appliedItemDiscounts = $this->applyDiscounts($lineSubtotal, $normalizedItemDiscounts);
            $lineNetSubtotal = Decimal::max(
                Decimal::subtract($lineSubtotal, $appliedItemDiscounts['total'], Decimal::MONEY_SCALE),
                0,
                Decimal::MONEY_SCALE
            );

            $items[] = [
                'index' => $index,
                'product_id' => Arr::get($item, 'product_id'),
                'line_type' => $lineType,
                'line_subtotal' => Decimal::float($lineSubtotal, Decimal::MONEY_SCALE),
                'net_subtotal' => Decimal::float($lineNetSubtotal, Decimal::MONEY_SCALE),
                'applied_discount_total' => Decimal::float($appliedItemDiscounts['total'], Decimal::MONEY_SCALE),
                'discount_breakdown' => $appliedItemDiscounts['lines'],
            ];

            $subtotal = Decimal::add($subtotal, $lineNetSubtotal, Decimal::MONEY_SCALE);
            $itemDiscountTotal = Decimal::add($itemDiscountTotal, $appliedItemDiscounts['total'], Decimal::MONEY_SCALE);

            if ($lineType !== 'fee') {
                $transactionBase = Decimal::add($transactionBase, $lineNetSubtotal, Decimal::MONEY_SCALE);
            }
        }

        $normalizedTransactionDiscounts = $this->normalizeTransactionDiscounts($payload);
        $appliedTransactionDiscounts = $this->applyDiscounts($transactionBase, $normalizedTransactionDiscounts);

        return [
            'items' => $items,
            'subtotal' => Decimal::float($subtotal, Decimal::MONEY_SCALE),
            'transaction_base' => Decimal::float($transactionBase, Decimal::MONEY_SCALE),
            'item_discount_total' => Decimal::float($itemDiscountTotal, Decimal::MONEY_SCALE),
            'transaction_discount_total' => Decimal::float($appliedTransactionDiscounts['total'], Decimal::MONEY_SCALE),
            'total_discount' => Decimal::float(Decimal::add($itemDiscountTotal, $appliedTransactionDiscounts['total'], Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
            'transaction_discounts' => $appliedTransactionDiscounts['lines'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItemDiscounts(array $item): array
    {
        $discounts = Arr::get($item, 'discounts');

        if (is_array($discounts) && $discounts !== []) {
            return $this->normalizeLines($discounts, 'item');
        }

        $legacyDiscount = Decimal::money(Arr::get($item, 'discount', 0));

        if (Decimal::compare($legacyDiscount, 0) <= 0) {
            return [];
        }

        return $this->normalizeLines([[
            'type' => 'fixed',
            'value' => Decimal::float($legacyDiscount, Decimal::MONEY_SCALE),
            'label' => 'Diskon item',
            'source' => 'legacy',
        ]], 'item');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTransactionDiscounts(array $payload): array
    {
        $discounts = Arr::get($payload, 'transaction_discounts');

        if (is_array($discounts) && $discounts !== []) {
            return $this->normalizeLines($discounts, 'transaction');
        }

        $legacyDiscount = Decimal::money(Arr::get($payload, 'discount', 0));

        if (Decimal::compare($legacyDiscount, 0) <= 0) {
            return [];
        }

        return $this->normalizeLines([[
            'type' => 'fixed',
            'value' => Decimal::float($legacyDiscount, Decimal::MONEY_SCALE),
            'label' => 'Diskon transaksi',
            'source' => 'legacy',
        ]], 'transaction');
    }

    /**
     * @param array<int, mixed> $lines
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLines(array $lines, string $scope): array
    {
        $normalized = [];

        foreach ($lines as $sequence => $line) {
            if (! is_array($line)) {
                continue;
            }

            $type = Arr::get($line, 'type') === 'percent' ? 'percent' : 'fixed';
            $value = $type === 'percent'
                ? Decimal::rate(Arr::get($line, 'value', 0))
                : Decimal::money(Arr::get($line, 'value', 0));

            if (Decimal::compare($value, 0) <= 0) {
                continue;
            }

            $normalized[] = [
                'scope' => $scope,
                'type' => $type,
                'value' => $type === 'percent'
                    ? Decimal::float($value, Decimal::RATE_SCALE)
                    : Decimal::float($value, Decimal::MONEY_SCALE),
                'decimal_value' => $value,
                'label' => trim((string) Arr::get($line, 'label', '')) ?: ($scope === 'item' ? 'Diskon item' : 'Diskon transaksi'),
                'source' => trim((string) Arr::get($line, 'source', 'manual')) ?: 'manual',
                'reference_id' => Arr::get($line, 'reference_id'),
                'sequence' => $sequence,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $discounts
     * @return array{total: string, lines: array<int, array<string, mixed>>}
     */
    private function applyDiscounts(string $baseAmount, array $discounts): array
    {
        $remaining = Decimal::max($baseAmount, 0, Decimal::MONEY_SCALE);
        $applied = [];
        $total = Decimal::money(0);

        foreach ($discounts as $discount) {
            if (Decimal::compare($remaining, 0) <= 0) {
                break;
            }

            $basis = $remaining;
            $candidate = $discount['type'] === 'percent'
                ? Decimal::multiply(
                    $basis,
                    Decimal::divide($discount['decimal_value'], '100', Decimal::RATE_SCALE),
                    Decimal::MONEY_SCALE
                )
                : Decimal::money($discount['decimal_value']);

            $appliedValue = Decimal::min(
                Decimal::max($candidate, 0, Decimal::MONEY_SCALE),
                $remaining,
                Decimal::MONEY_SCALE
            );

            if (Decimal::compare($appliedValue, 0) <= 0) {
                continue;
            }

            $remaining = Decimal::subtract($remaining, $appliedValue, Decimal::MONEY_SCALE);
            $total = Decimal::add($total, $appliedValue, Decimal::MONEY_SCALE);
            $applied[] = [
                ...Arr::except($discount, ['decimal_value']),
                'basis_amount' => Decimal::float($basis, Decimal::MONEY_SCALE),
                'applied_value' => Decimal::float($appliedValue, Decimal::MONEY_SCALE),
            ];
        }

        return [
            'total' => Decimal::money($total),
            'lines' => $applied,
        ];
    }
}
