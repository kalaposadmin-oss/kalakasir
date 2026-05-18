<?php

namespace App\Services;

use App\Models\OpeningSession;
use Illuminate\Support\Collection;
use RuntimeException;

class OpeningBalanceValidationService
{
    /**
     * @return array{inventory_total:float,fund_total:float,total_debit:float,equity_total:float}
     */
    public function validateForPost(OpeningSession $session): array
    {
        $stockLines = $session->stockLines()->get();
        $fundLines = $session->fundLines()->get();

        if ($stockLines->isEmpty() && $fundLines->isEmpty()) {
            throw new RuntimeException('Session opening balance kosong. Isi stok awal atau dana awal terlebih dahulu.');
        }

        $this->validateNoDuplicateStockBucket($stockLines);

        $inventoryTotal = 0.0;
        foreach ($stockLines as $line) {
            $qty = (float) $line->qty;
            $unitCost = (float) $line->unit_cost;
            $lineValue = (float) $line->line_value;

            if ($qty <= 0) {
                throw new RuntimeException('Qty opening stock harus lebih dari 0.');
            }

            if ($unitCost <= 0) {
                throw new RuntimeException('Unit cost opening stock wajib lebih dari 0 saat qty lebih dari 0.');
            }

            $expected = round($qty * $unitCost, 2);
            if ($lineValue <= 0 || abs($lineValue - $expected) > 0.01) {
                throw new RuntimeException('Line value opening stock tidak valid.');
            }

            $inventoryTotal += $lineValue;
        }

        $fundTotal = 0.0;
        foreach ($fundLines as $line) {
            $amount = (float) $line->amount;
            if ($amount <= 0) {
                throw new RuntimeException('Nilai opening cash/bank wajib lebih dari 0.');
            }
            $fundTotal += $amount;
        }

        $totalDebit = round($inventoryTotal + $fundTotal, 2);
        if ($totalDebit <= 0) {
            throw new RuntimeException('Total debit opening balance harus lebih dari 0.');
        }

        if (! $session->equity_account_id) {
            throw new RuntimeException('Akun equity/modal wajib dipilih sebelum posting opening balance.');
        }

        $equityTotal = $session->equity_amount !== null
            ? round((float) $session->equity_amount, 2)
            : $totalDebit;

        if ($equityTotal <= 0) {
            throw new RuntimeException('Nilai equity opening balance harus lebih dari 0.');
        }

        if (abs($totalDebit - $equityTotal) > 0.01) {
            throw new RuntimeException('Opening balance tidak seimbang: total debit tidak sama dengan total credit.');
        }

        return [
            'inventory_total' => round($inventoryTotal, 2),
            'fund_total' => round($fundTotal, 2),
            'total_debit' => round($totalDebit, 2),
            'equity_total' => round($equityTotal, 2),
        ];
    }

    private function validateNoDuplicateStockBucket(Collection $lines): void
    {
        $keys = $lines->map(function ($line) {
            return implode('|', [
                (string) $line->location_id,
                (string) $line->product_id,
                (string) ($line->product_variant_id ?? ''),
            ]);
        });

        if ($keys->count() !== $keys->unique()->count()) {
            throw new RuntimeException('Duplikasi produk/lokasi/varian pada opening stock tidak diizinkan.');
        }
    }
}
