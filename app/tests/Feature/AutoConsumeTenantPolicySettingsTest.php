<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\AutoConsumeTenantPolicyService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutoConsumeTenantPolicySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_flags_default_to_false(): void
    {
        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Policy Default',
            'timezone' => 'UTC',
        ]);

        $tenant->refresh();

        $this->assertFalse((bool) $tenant->auto_consume_on_sell_enabled);
        $this->assertFalse((bool) $tenant->allow_negative_raw_material_stock);
    }

    public function test_policy_service_uses_config_defaults_when_tenant_not_set(): void
    {
        config()->set('platform.auto_consume_on_sell_enabled_default', true);
        config()->set('platform.allow_negative_raw_material_stock_default', false);
        TenantContext::clear();

        $service = app(AutoConsumeTenantPolicyService::class);

        $this->assertTrue($service->autoConsumeOnSellEnabled());
        $this->assertFalse($service->allowNegativeRawMaterialStock());
    }

    public function test_policy_service_uses_tenant_overrides_when_context_available(): void
    {
        config()->set('platform.auto_consume_on_sell_enabled_default', false);
        config()->set('platform.allow_negative_raw_material_stock_default', false);

        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Policy Override',
            'timezone' => 'UTC',
            'auto_consume_on_sell_enabled' => true,
            'allow_negative_raw_material_stock' => true,
        ]);

        TenantContext::set($tenant->fresh());

        $service = app(AutoConsumeTenantPolicyService::class);

        $this->assertTrue($service->autoConsumeOnSellEnabled());
        $this->assertTrue($service->allowNegativeRawMaterialStock());

        TenantContext::clear();
    }
}
