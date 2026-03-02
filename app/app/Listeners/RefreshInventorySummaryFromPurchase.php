<?php

namespace App\Listeners;

use App\Events\PurchaseReceived;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\InventorySummaryService;
use App\Services\ProcessedEventService;
use Illuminate\Contracts\Queue\ShouldQueue;

class RefreshInventorySummaryFromPurchase implements ShouldQueue
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

    public function handle(PurchaseReceived $event): void
    {
        $listener = static::class;
        $this->processed->processOnce($event->tenantId, $event->eventId, $listener, function () use ($event): bool {
            $purchase = Purchase::query()
                ->where('tenant_id', $event->tenantId)
                ->where('id', $event->purchaseId)
                ->first();

            if (! $purchase || ! $purchase->location_id) {
                return false;
            }

            $productIds = PurchaseItem::query()
                ->where('tenant_id', $event->tenantId)
                ->where('purchase_id', $event->purchaseId)
                ->pluck('product_id')
                ->unique()
                ->values()
                ->all();

            if (! $productIds) {
                return false;
            }

            $this->service->refresh($event->tenantId, $purchase->location_id, $productIds);

            return true;
        });
    }
}
