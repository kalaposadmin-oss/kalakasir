<?php

namespace App\Listeners;

use App\Events\PurchaseReceived;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

class AuditPurchaseReceived implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private readonly AuditLogger $logger)
    {
    }

    public function handle(PurchaseReceived $event): void
    {
        $this->logger->log(
            event: 'purchase.received',
            actorId: $event->actorId,
            referenceType: 'purchase',
            referenceId: $event->purchaseId,
            payload: [
                'total' => $event->total,
                'occurred_at' => $event->occurredAt,
            ],
            tenantId: $event->tenantId,
            occurredAt: CarbonImmutable::parse($event->occurredAt)
        );
    }
}
