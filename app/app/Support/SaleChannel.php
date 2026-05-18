<?php

namespace App\Support;

class SaleChannel
{
    public static function fromIdempotencyKey(?string $idempotencyKey): array
    {
        $channel = str_starts_with((string) $idempotencyKey, 'online-order:')
            ? 'online'
            : 'offline';

        return [
            'channel' => $channel,
            'label' => $channel === 'online' ? 'Online' : 'Kasir / Offline',
            'stock_label' => $channel === 'online' ? 'Penjualan Online' : 'Penjualan Kasir',
        ];
    }
}
