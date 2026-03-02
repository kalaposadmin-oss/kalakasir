<?php

namespace App\Listeners;

use App\Events\PurchaseReturned;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

class AuditPurchaseReturned implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private readonly AuditLogger $logger)
    {
    }

    public function handle(PurchaseReturned $event): void
    {
        $this->logger->log(
            event: 'purchase.returned',
            actorId: $event->actorId,
            referenceType: 'purchase_return',
            referenceId: $event->purchaseReturnId,
            payload: [
                'purchase_id' => $event->purchaseId,
                'total' => $event->total,
                'occurred_at' => $event->occurredAt,
            ],
            tenantId: $event->tenantId,
            occurredAt: CarbonImmutable::parse($event->occurredAt)
        );
    }
}
