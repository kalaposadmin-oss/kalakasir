<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class PurchaseReturned implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $eventId;

    public function __construct(
        public readonly string $purchaseReturnId,
        public readonly string $purchaseId,
        public readonly string $tenantId,
        public readonly float $total,
        public readonly ?string $actorId,
        public readonly string $occurredAt,
        ?string $eventId = null
    ) {
        $this->eventId = $eventId ?: (string) Str::uuid();
    }
}
