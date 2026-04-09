<?php

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

class AuditSaleCompleted implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private readonly AuditLogger $logger)
    {
    }

    public function handle(SaleCompleted $event): void
    {
        $payload = [
            'total' => $event->total,
            'occurred_at' => $event->occurredAt,
        ];

        if ($event->requestId) {
            $payload['request_id'] = $event->requestId;
        }

        $this->logger->log(
            event: 'sale.completed',
            actorId: $event->actorId,
            referenceType: 'sale',
            referenceId: $event->saleId,
            payload: $payload,
            tenantId: $event->tenantId,
            occurredAt: CarbonImmutable::parse($event->occurredAt)
        );
    }
}
