<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        private readonly string $productId,
        private readonly string $locationId
    ) {
        parent::__construct('Stok tidak mencukupi.');
    }

    public function context(): array
    {
        return [
            'product_id' => $this->productId,
            'location_id' => $this->locationId,
        ];
    }
}
