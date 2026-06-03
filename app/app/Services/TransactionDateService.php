<?php

namespace App\Services;

use App\Exceptions\InvalidTransactionDateException;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

class TransactionDateService
{
    public function resolve(?string $transactionDate): CarbonImmutable
    {
        $timezone = TenantContext::get()?->timezone ?? 'UTC';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        if (! $transactionDate) {
            return $today;
        }

        $value = CarbonImmutable::parse($transactionDate, $timezone)->startOfDay();

        if ($value->greaterThan($today)) {
            throw new InvalidTransactionDateException('Tanggal transaksi tidak boleh di masa depan.');
        }

        if ($value->lessThan($today->startOfMonth())) {
            throw new InvalidTransactionDateException('Tanggal transaksi tidak boleh melewati bulan berjalan.');
        }

        return $value;
    }
}
