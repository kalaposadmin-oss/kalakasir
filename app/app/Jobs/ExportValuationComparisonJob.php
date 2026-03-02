<?php

namespace App\Jobs;

use App\Services\AnomalyService;
use App\Services\ValuationAuditService;
use App\Services\ValuationComparisonExportService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class ExportValuationComparisonJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public string $requestId,
        public string $tenantId,
        public ?string $fromDate,
        public ?string $toDate,
        public ?string $skuPrefix,
        public int $limit,
        public ?string $actorId = null,
    ) {
    }

    public function handle(ValuationComparisonExportService $service, AnomalyService $anomalyService, ValuationAuditService $auditService): void
    {
        $key = $this->cacheKey();
        $current = Cache::get($key, []);
        if (($current['status'] ?? null) === 'cancelled') {
            return;
        }

        Cache::put($key, [
            'status' => 'running',
            'request_id' => $this->requestId,
            'tenant_id' => $this->tenantId,
            'actor_id' => $this->actorId,
            'queued_at' => $current['queued_at'] ?? null,
            'started_at' => CarbonImmutable::now('UTC')->toIso8601String(),
            'params' => $current['params'] ?? [
                'from' => $this->fromDate,
                'to' => $this->toDate,
                'sku_prefix' => $this->skuPrefix,
                'limit' => $this->limit,
            ],
            'retried_from' => $current['retried_from'] ?? null,
        ], 60 * 60 * 24);

        $auditService->log($this->tenantId, 'valuation_export_job_started', [
            'request_id' => $this->requestId,
            'actor_id' => $this->actorId,
        ]);

        try {
            $result = $service->export(
                tenantId: $this->tenantId,
                fromDate: $this->fromDate,
                toDate: $this->toDate,
                skuPrefix: $this->skuPrefix,
                limit: $this->limit,
            );

            Cache::put($key, [
                'status' => 'success',
                'request_id' => $this->requestId,
                'tenant_id' => $this->tenantId,
                'actor_id' => $this->actorId,
                'queued_at' => Cache::get($key)['queued_at'] ?? null,
                'started_at' => Cache::get($key)['started_at'] ?? null,
                'finished_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                'params' => Cache::get($key)['params'] ?? null,
                'retried_from' => Cache::get($key)['retried_from'] ?? null,
                'result' => [
                    'csv_name' => $result['csv_name'],
                    'md_name' => $result['md_name'],
                    'count' => $result['count'],
                ],
            ], 60 * 60 * 24 * 7);

            $anomalyService->raise(
                tenantId: $this->tenantId,
                severity: 'low',
                type: 'valuation_export_completed',
                payload: [
                    'request_id' => $this->requestId,
                    'csv_name' => $result['csv_name'],
                    'md_name' => $result['md_name'],
                    'count' => $result['count'],
                    'actor_id' => $this->actorId,
                ]
            );

            $auditService->log($this->tenantId, 'valuation_export_job_completed', [
                'request_id' => $this->requestId,
                'actor_id' => $this->actorId,
                'csv_name' => $result['csv_name'],
                'md_name' => $result['md_name'],
                'count' => $result['count'],
            ]);
        } catch (\Throwable $exception) {
            Cache::put($key, [
                'status' => 'failed',
                'request_id' => $this->requestId,
                'tenant_id' => $this->tenantId,
                'actor_id' => $this->actorId,
                'queued_at' => Cache::get($key)['queued_at'] ?? null,
                'started_at' => Cache::get($key)['started_at'] ?? null,
                'finished_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                'params' => Cache::get($key)['params'] ?? null,
                'retried_from' => Cache::get($key)['retried_from'] ?? null,
                'error' => $exception->getMessage(),
            ], 60 * 60 * 24 * 7);

            $anomalyService->raise(
                tenantId: $this->tenantId,
                severity: 'med',
                type: 'valuation_export_failed',
                payload: [
                    'request_id' => $this->requestId,
                    'message' => $exception->getMessage(),
                    'actor_id' => $this->actorId,
                ]
            );

            $auditService->log($this->tenantId, 'valuation_export_job_failed', [
                'request_id' => $this->requestId,
                'actor_id' => $this->actorId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function cacheKey(): string
    {
        return 'valuation:export:request:'.$this->requestId;
    }
}
