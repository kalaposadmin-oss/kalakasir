<?php

namespace Tests\Feature;

use App\Services\OrderPickupWindowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class OrderPickupWindowSettingsTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_backoffice_preferences_save_pickup_window_settings(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-pickup-window@example.test');

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->post('/backoffice/settings/preferences', [
                'pickup_window_enabled' => '1',
                'pickup_start_time' => '11:00',
                'pickup_end_time' => '17:00',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $setting = DB::table('settings')
            ->where('tenant_id', $tenant->id)
            ->where('setting_key', OrderPickupWindowService::SETTING_KEY)
            ->first();

        $this->assertNotNull($setting);

        $value = json_decode((string) $setting->value_json, true);
        $this->assertSame([
            'enabled' => true,
            'start_time' => '11:00',
            'end_time' => '17:00',
        ], $value);

        $resolved = app(OrderPickupWindowService::class)->resolve($tenant->refresh());
        $this->assertTrue($resolved['enabled']);
        $this->assertSame('11.00', $resolved['start_label']);
        $this->assertSame('17.00', $resolved['end_label']);
    }

    public function test_pickup_window_rejects_end_time_before_start_time(): void
    {
        [, $user] = $this->bootApiActor('admin', 'admin-pickup-window-invalid@example.test');

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->post('/backoffice/settings/preferences', [
                'pickup_window_enabled' => '1',
                'pickup_start_time' => '17:00',
                'pickup_end_time' => '11:00',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('pickup_end_time');
    }

    public function test_pickup_time_validation_uses_saved_window(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin-pickup-window-service@example.test');
        $service = app(OrderPickupWindowService::class);

        $service->save($tenant->id, [
            'enabled' => true,
            'start_time' => '11:00',
            'end_time' => '17:00',
        ], $tenant->timezone);

        $this->assertNull($service->pickupTimeValidationMessage($tenant->refresh(), '12:30'));
        $this->assertSame(
            'Waktu pengambilan harus antara 11.00 - 17.00.',
            $service->pickupTimeValidationMessage($tenant->refresh(), '10:30')
        );
    }
}
