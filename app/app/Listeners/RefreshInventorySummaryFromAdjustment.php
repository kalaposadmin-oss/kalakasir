<?php

namespace App\Listeners;

use App\Events\AdjustmentPosted;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Services\InventorySummaryService;
use App\Services\ProcessedEventService;
use Illuminate\Contracts\Queue\ShouldQueue;

class RefreshInventorySummaryFromAdjustment implements ShouldQueue
{
    public bool $afterCommit = true;
    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(
        private readonly InventorySummaryService $service,
        private readonly ProcessedEventService $processed
    )
    {
    }

    public function handle(AdjustmentPosted $event): void
    {
        $listener = static::class;
        $this->processed->processOnce($event->tenantId, $event->eventId, $listener, function () use ($event): bool {
            $adjustment = StockAdjustment::query()
                ->where('tenant_id', $event->tenantId)
                ->where('id', $event->adjustmentId)
                ->first();

            if (! $adjustment) {
                return false;
            }

            $productIds = StockAdjustmentItem::query()
                ->where('tenant_id', $event->tenantId)
                ->where('stock_adjustment_id', $event->adjustmentId)
                ->pluck('product_id')
                ->unique()
                ->values()
                ->all();

            if (! $productIds) {
                return false;
            }

            $this->service->refresh($event->tenantId, $adjustment->location_id, $productIds);

            return true;
        });
    }
}
