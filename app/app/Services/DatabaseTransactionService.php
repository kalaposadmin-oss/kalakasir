<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DatabaseTransactionService
{
    public function run(callable $callback, int $maxAttempts = 3)
    {
        $attempt = 0;

        beginning:
        $attempt++;

        try {
            return DB::transaction($callback, 1);
        } catch (QueryException $exception) {
            if ($attempt >= $maxAttempts || ! $this->isRetryableConcurrencyError($exception)) {
                throw $exception;
            }

            usleep(random_int(20_000, 120_000));
            goto beginning;
        }
    }

    private function isRetryableConcurrencyError(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return in_array($sqlState, ['40001', '40P01'], true)
            || in_array($driverCode, ['1213', '1205'], true);
    }
}
