<?php

namespace App\Services;

use App\Models\OpeningSession;
use Carbon\CarbonImmutable;
use RuntimeException;

class OpeningBalanceLockService
{
    public function preventModification(OpeningSession $session): void
    {
        if ($session->status !== 'draft') {
            throw new RuntimeException('Session opening balance tidak dapat diubah karena sudah diposting.');
        }

        if ($session->locked_at !== null) {
            throw new RuntimeException('Session opening balance terkunci dan tidak dapat diubah.');
        }
    }

    public function lockInitialPeriod(OpeningSession $session): void
    {
        $now = CarbonImmutable::now('UTC');

        $session->update([
            'locked_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
