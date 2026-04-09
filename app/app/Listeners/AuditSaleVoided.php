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
        $payload = [
            'reason' => $event->reason,
            'occurred_at' => $event->occurredAt,
        ];

        if ($event->requestId) {
            $payload['request_id'] = $event->requestId;
        }

        $this->logger->log(
            event: 'sale.voided',
            actorId: $event->actorId,
            referenceType: 'sale',
            referenceId: $event->saleId,
            payload: $payload,
            tenantId: $event->tenantId,
            occurredAt: CarbonImmutable::parse($event->occurredAt)
        );
    }
}
