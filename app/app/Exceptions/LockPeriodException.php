<?php

namespace App\Exceptions;

use RuntimeException;

class LockPeriodException extends RuntimeException
{
    public function __construct(
        string $message = 'Periode sudah dikunci.',
        private readonly string $errorCode = 'LOCK_PERIOD',
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
