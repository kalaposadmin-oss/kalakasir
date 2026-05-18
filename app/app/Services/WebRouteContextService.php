<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class WebRouteContextService
{
    public function resolveTenantId(): ?string
    {
        if (! Schema::hasTable('tenants')) {
            return null;
        }

        $webUser = auth('web')->user();
        $candidateTenantIds = array_filter([
            $webUser?->current_tenant_id,
            $webUser?->tenant_id,
            session('current_tenant_id'),
        ]);

        foreach ($candidateTenantIds as $tenantId) {
            $tenantId = trim((string) $tenantId);
            if ($tenantId !== '' && Tenant::query()->where('id', $tenantId)->exists()) {
                return $tenantId;
            }
        }

        return Tenant::query()->value('id');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function withTenant(array $data = []): array
    {
        return array_merge([
            'tenantId' => $this->resolveTenantId(),
        ], $data);
    }

    public function userHasOpenShift(?User $user): bool
    {
        if (! $user || ! Schema::hasTable('shifts')) {
            return false;
        }

        $tenant = Tenant::query()->find($user->tenant_id);
        if ($tenant) {
            app(ShiftLifecycleService::class)->autoCloseExpiredOpenShifts($tenant);
        }

        return Shift::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->exists();
    }
}
