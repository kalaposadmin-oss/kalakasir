<?php

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Services\ProcessedEventService;
use App\Services\DailySalesSummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;

class AggregateDailySales implements ShouldQueue
{
    public bool $afterCommit = true;
    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(
        private readonly DailySalesSummaryService $service,
        private readonly ProcessedEventService $processed
    )
    {
    }

    public function handle(SaleCompleted $event): void
    {
        $listener = static::class;
        $this->processed->processOnce($event->tenantId, $event->eventId, $listener, function () use ($event): void {
            $this->service->applySale($event->tenantId, $event->saleId, 1);
        });
    }
}
