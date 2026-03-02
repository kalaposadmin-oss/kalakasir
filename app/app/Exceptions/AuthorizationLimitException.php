<?php

namespace App\Exceptions;

use RuntimeException;

class AuthorizationLimitException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = 'AUTHORIZATION_LIMIT_EXCEEDED',
        private readonly int $status = 403,
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function details(): array
    {
        return $this->details;
    }
}
