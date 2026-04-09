<?php

namespace App\Listeners;

use App\Models\Tenant;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable;

class SetCurrentTenantOnLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof Authenticatable) {
            return;
        }

        $tenantId = $user->tenant_id ?? null;
        if (! $tenantId) {
            return;
        }

        $currentTenantId = $user->current_tenant_id ?? null;
        $currentValid = $currentTenantId === $tenantId
            && Tenant::query()->where('id', $currentTenantId)->exists();

        if (! $currentValid) {
            $user->forceFill(['current_tenant_id' => $tenantId])->save();
        }

        if ($event->guard === 'web' && request()->hasSession()) {
            request()->session()->put('current_tenant_id', $user->current_tenant_id);
        }
    }
}
