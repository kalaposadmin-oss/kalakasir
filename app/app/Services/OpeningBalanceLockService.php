<?php

namespace App\Services;

use App\Models\LockPeriod;
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

    public function lockInitialPeriod(OpeningSession $session, ?string $actorId, ?string $reason = null): void
    {
        $openingDate = CarbonImmutable::parse((string) $session->opening_date, 'UTC')->toDateString();
        $now = CarbonImmutable::now('UTC');

        LockPeriod::query()->updateOrCreate(
            [
                'tenant_id' => $session->tenant_id,
                'start_date' => $openingDate,
                'end_date' => $openingDate,
            ],
            [
                'is_locked' => true,
                'locked_by' => $actorId,
                'locked_at' => $now,
                'reason' => $reason ?: 'Locked by opening balance posting',
            ]
        );

        $session->update([
            'locked_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
