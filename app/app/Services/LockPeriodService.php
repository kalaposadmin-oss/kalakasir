<?php

namespace App\Services;

use App\Exceptions\LockPeriodException;
use App\Models\LockPeriod;
use Carbon\CarbonInterface;

class LockPeriodService
{
    public function ensureNotLocked(CarbonInterface $date, string $tenantId): void
    {
        $locked = LockPeriod::query()
            ->where('tenant_id', $tenantId)
            ->where('is_locked', true)
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->where(function ($query) {
                $query->whereNull('reason')
                    ->orWhereRaw('LOWER(reason) NOT LIKE ?', ['%opening balance%']);
            })
            ->exists();

        if ($locked) {
            throw new LockPeriodException();
        }
    }
}
