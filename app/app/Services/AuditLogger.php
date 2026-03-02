<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class AuditLogger
{
    public function log(
        string $event,
        ?string $actorId,
        string $referenceType,
        string $referenceId,
        array $payload,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $tenantId = null,
        ?CarbonInterface $occurredAt = null
    ): void {
        $tenantId = $tenantId ?: TenantContext::id();
        $timestamp = $occurredAt ? CarbonImmutable::instance($occurredAt) : CarbonImmutable::now('UTC');

        AuditLog::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
            'event_type' => $event,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'payload_json' => $payload,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => $timestamp,
        ]);
    }
}
