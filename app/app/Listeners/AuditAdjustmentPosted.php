<?php

namespace App\Listeners;

use App\Events\AdjustmentPosted;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

class AuditAdjustmentPosted implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private readonly AuditLogger $logger)
    {
    }

    public function handle(AdjustmentPosted $event): void
    {
        $adjustment = StockAdjustment::query()
            ->where('tenant_id', $event->tenantId)
            ->where('id', $event->adjustmentId)
            ->first();

        if (! $adjustment) {
            return;
        }

        $items = StockAdjustmentItem::query()
            ->where('tenant_id', $event->tenantId)
            ->where('stock_adjustment_id', $event->adjustmentId)
            ->get();

        $occurredAt = CarbonImmutable::parse($event->occurredAt);

        foreach ($items as $item) {
            $this->logger->log(
                event: 'adjustment.posted',
                actorId: $event->actorId,
                referenceType: 'stock_adjustment',
                referenceId: $event->adjustmentId,
                payload: [
                    'product_id' => $item->product_id,
                    'before_qty' => (float) $item->system_qty,
                    'after_qty' => (float) $item->physical_qty,
                    'difference' => (float) $item->difference_qty,
                    'reason_code' => $adjustment->reason_code,
                    'location_id' => $adjustment->location_id,
                    'occurred_at' => $event->occurredAt,
                ],
                ipAddress: $event->ipAddress,
                userAgent: $event->userAgent,
                tenantId: $event->tenantId,
                occurredAt: $occurredAt
            );
        }
    }
}
