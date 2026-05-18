<?php

namespace App\Listeners;

use App\Exceptions\MissingSalesCostAggregateException;
use App\Events\SaleCompleted;
use App\Services\ProcessedEventService;
use App\Services\DailySalesSummaryService;
use App\Services\SystemHealthEventService;
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
            try {
                $this->service->applySale($event->tenantId, $event->saleId, 1);
            } catch (MissingSalesCostAggregateException $exception) {
                app(SystemHealthEventService::class)->recordOrBump('daily_summary_refresh_failed', [
                    'tenant_id' => $event->tenantId,
                    'entity_type' => 'sale',
                    'entity_id' => $event->saleId,
                    'summary' => 'Queued daily sales aggregation failed because aggregate COGS is not ready.',
                    'payload' => [
                        'sale_id' => $event->saleId,
                        'listener' => $listener,
                        'message' => $exception->getMessage(),
                    ],
                    'source' => 'aggregate-daily-sales-listener',
                    'dedupe_key' => 'daily_summary_refresh_failed:'.$event->tenantId.':'.$event->saleId,
                ]);

                report($exception);
            }
        });
    }
}
