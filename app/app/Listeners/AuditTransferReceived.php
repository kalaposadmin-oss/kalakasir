<?php

namespace App\Listeners;

use App\Events\TransferReceived;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

class AuditTransferReceived implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private readonly AuditLogger $logger)
    {
    }

    public function handle(TransferReceived $event): void
    {
        $this->logger->log(
            event: 'transfer.received',
            actorId: $event->actorId,
            referenceType: 'stock_transfer',
            referenceId: $event->transferId,
            payload: [
                'occurred_at' => $event->occurredAt,
            ],
            tenantId: $event->tenantId,
            occurredAt: CarbonImmutable::parse($event->occurredAt)
        );
    }
}
