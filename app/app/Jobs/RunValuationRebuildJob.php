<?php

namespace App\Jobs;

use App\Services\AnomalyService;
use App\Services\InventoryValuationService;
use App\Services\ValuationAuditService;
use App\Services\ValuationRebuildStateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RunValuationRebuildJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public string $tenantId,
        public string $requestId,
        public ?string $actorId = null,
        public ?string $trigger = null,
    ) {
    }

    public function handle(
        InventoryValuationService $valuationService,
        ValuationRebuildStateService $state,
        AnomalyService $anomalyService,
        ValuationAuditService $auditService,
    ): void {
        $meta = $state->meta($this->tenantId);
        if (($meta['request_id'] ?? null) === $this->requestId && ($meta['status'] ?? null) === 'cancelled') {
            return;
        }

        if (! $state->isLocked($this->tenantId)) {
            $state->lock($this->tenantId, 3600, [
                'request_id' => $this->requestId,
                'actor_id' => $this->actorId,
                'trigger' => $this->trigger,
            ]);
        }

        $auditService->log($this->tenantId, 'valuation_rebuild_job_started', [
            'request_id' => $this->requestId,
            'actor_id' => $this->actorId,
            'trigger' => $this->trigger,
        ]);

        try {
            DB::transaction(function () use ($valuationService): void {
                $valuationService->replayFromLedger($this->tenantId);
            }, 3);

            $state->unlock($this->tenantId, true, 'valuation rebuild success', [
                'request_id' => $this->requestId,
                'actor_id' => $this->actorId,
                'trigger' => $this->trigger,
            ]);

            $anomalyService->raise(
                tenantId: $this->tenantId,
                severity: 'low',
                type: 'valuation_rebuild_completed',
                payload: [
                    'request_id' => $this->requestId,
                    'actor_id' => $this->actorId,
                    'trigger' => $this->trigger,
                ]
            );

            $auditService->log($this->tenantId, 'valuation_rebuild_job_completed', [
                'request_id' => $this->requestId,
                'actor_id' => $this->actorId,
                'trigger' => $this->trigger,
            ]);
        } catch (\Throwable $exception) {
            $state->unlock($this->tenantId, false, $exception->getMessage(), [
                'request_id' => $this->requestId,
                'actor_id' => $this->actorId,
                'trigger' => $this->trigger,
            ]);

            $anomalyService->raise(
                tenantId: $this->tenantId,
                severity: 'high',
                type: 'valuation_rebuild_failed',
                payload: [
                    'request_id' => $this->requestId,
                    'actor_id' => $this->actorId,
                    'trigger' => $this->trigger,
                    'message' => $exception->getMessage(),
                ]
            );

            $auditService->log($this->tenantId, 'valuation_rebuild_job_failed', [
                'request_id' => $this->requestId,
                'actor_id' => $this->actorId,
                'trigger' => $this->trigger,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
