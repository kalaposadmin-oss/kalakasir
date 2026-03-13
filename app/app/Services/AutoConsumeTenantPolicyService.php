<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\TenantContext;

class AutoConsumeTenantPolicyService
{
    public function autoConsumeOnSellEnabled(?Tenant $tenant = null): bool
    {
        $resolvedTenant = $tenant ?? TenantContext::get();

        if ($resolvedTenant) {
            return (bool) $resolvedTenant->auto_consume_on_sell_enabled;
        }

        return (bool) config('platform.auto_consume_on_sell_enabled_default', false);
    }

    public function allowNegativeRawMaterialStock(?Tenant $tenant = null): bool
    {
        $resolvedTenant = $tenant ?? TenantContext::get();

        if ($resolvedTenant) {
            return (bool) $resolvedTenant->allow_negative_raw_material_stock;
        }

        return (bool) config('platform.allow_negative_raw_material_stock_default', false);
    }
}
