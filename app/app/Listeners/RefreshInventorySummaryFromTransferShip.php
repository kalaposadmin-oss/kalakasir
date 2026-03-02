<?php

namespace App\Listeners;

use App\Events\TransferShipped;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\InventorySummaryService;
use App\Services\ProcessedEventService;
use Illuminate\Contracts\Queue\ShouldQueue;

class RefreshInventorySummaryFromTransferShip implements ShouldQueue
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

    public function handle(TransferShipped $event): void
    {
        $listener = static::class;
        $this->processed->processOnce($event->tenantId, $event->eventId, $listener, function () use ($event): bool {
            $transfer = StockTransfer::query()
                ->where('tenant_id', $event->tenantId)
                ->where('id', $event->transferId)
                ->first();

            if (! $transfer || ! $transfer->from_location_id) {
                return false;
            }

            $productIds = StockTransferItem::query()
                ->where('tenant_id', $event->tenantId)
                ->where('stock_transfer_id', $event->transferId)
                ->pluck('product_id')
                ->unique()
                ->values()
                ->all();

            if (! $productIds) {
                return false;
            }

            $this->service->refresh($event->tenantId, $transfer->from_location_id, $productIds);

            return true;
        });
    }
}
