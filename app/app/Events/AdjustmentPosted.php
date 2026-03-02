<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class AdjustmentPosted implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $eventId;

    public function __construct(
        public readonly string $adjustmentId,
        public readonly string $tenantId,
        public readonly ?string $actorId,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly string $occurredAt,
        ?string $eventId = null
    ) {
        $this->eventId = $eventId ?: (string) Str::uuid();
    }
}
