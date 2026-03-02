<?php

namespace App\Services;

use App\Models\ProcessedEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProcessedEventService
{
    public function processOnce(string $tenantId, string $eventUuid, string $listenerName, callable $handler): void
    {
        $lockKey = sprintf('processed_event:%s:%s:%s', $tenantId, $listenerName, $eventUuid);

        Cache::lock($lockKey, 15)->block(5, function () use ($tenantId, $eventUuid, $listenerName, $handler): void {
            if ($this->alreadyProcessed($tenantId, $eventUuid, $listenerName)) {
                return;
            }

            $processed = $handler();
            if ($processed === false) {
                return;
            }

            $this->markProcessed($tenantId, $eventUuid, $listenerName);
        });
    }

    public function alreadyProcessed(string $tenantId, string $eventUuid, string $listenerName): bool
    {
        return ProcessedEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_uuid', $eventUuid)
            ->where('listener_name', $listenerName)
            ->exists();
    }

    public function markProcessed(string $tenantId, string $eventUuid, string $listenerName): void
    {
        try {
            ProcessedEvent::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'event_uuid' => $eventUuid,
                'listener_name' => $listenerName,
                'processed_at' => CarbonImmutable::now('UTC'),
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return;
            }

            throw $exception;
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return $sqlState === '23000'
            || $sqlState === '23505'
            || $driverCode === '1062'
            || $driverCode === '19';
    }
}
