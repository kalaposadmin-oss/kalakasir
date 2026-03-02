<?php

namespace App\Listeners;

use App\Events\SaleVoided;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

class AuditSaleVoided implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private readonly AuditLogger $logger)
    {
    }

    public function handle(SaleVoided $event): void
    {
        $this->logger->log(
            event: 'sale.voided',
            actorId: $event->actorId,
            referenceType: 'sale',
            referenceId: $event->saleId,
            payload: [
                'reason' => $event->reason,
                'occurred_at' => $event->occurredAt,
            ],
            tenantId: $event->tenantId,
            occurredAt: CarbonImmutable::parse($event->occurredAt)
        );
    }
}
