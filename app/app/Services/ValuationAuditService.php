<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ValuationAuditService
{
    public function log(string $tenantId, string $action, array $meta = [], ?Request $request = null): void
    {
        DB::table('valuation_audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'action' => $action,
            'actor_id' => $meta['actor_id'] ?? $request?->user()?->id,
            'request_id' => $meta['request_id'] ?? null,
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now('UTC'),
        ]);
    }
}
