<?php

namespace App\Exceptions;

use RuntimeException;

class ShiftValidationException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = 'SHIFT_INVALID',
        private readonly int $status = 422
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
}
