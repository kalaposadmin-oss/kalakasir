<?php

namespace App\Listeners;

use App\Events\PurchaseReturned;
use App\Models\Purchase;
use App\Models\PurchaseReturnItem;
use App\Services\InventorySummaryService;
use App\Services\ProcessedEventService;
use Illuminate\Contracts\Queue\ShouldQueue;

class RefreshInventorySummaryFromReturn implements ShouldQueue
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

    public function handle(PurchaseReturned $event): void
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

            $productIds = PurchaseReturnItem::query()
                ->where('tenant_id', $event->tenantId)
                ->where('purchase_return_id', $event->purchaseReturnId)
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
