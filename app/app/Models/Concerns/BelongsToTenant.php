<?php

namespace App\Models\Concerns;

use App\Scopes\TenantScope;
use App\Support\TenantContext;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model): void {
            if (empty($model->tenant_id)) {
                $tenantId = TenantContext::id();

                if ($tenantId) {
                    $model->tenant_id = $tenantId;
                }
            }
        });
    }
}
