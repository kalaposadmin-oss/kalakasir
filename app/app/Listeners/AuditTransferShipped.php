<?php

namespace App\Listeners;

use App\Events\TransferShipped;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

class AuditTransferShipped implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private readonly AuditLogger $logger)
    {
    }

    public function handle(TransferShipped $event): void
    {
        $this->logger->log(
            event: 'transfer.shipped',
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
