<?php

namespace App\Exceptions;

use RuntimeException;

class AutoConsumeValidationException extends RuntimeException
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly array $details = [],
        private readonly int $status = 422
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }

    public function status(): int
    {
        return $this->status;
    }
}
