<?php

namespace App\Exceptions;

use RuntimeException;

class PurchaseReturnValuationException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $type,
        private readonly array $payload = [],
        private readonly ?string $referenceId = null
    ) {
        parent::__construct($message);
    }

    public function type(): string
    {
        return $this->type;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function referenceId(): ?string
    {
        return $this->referenceId;
    }
}