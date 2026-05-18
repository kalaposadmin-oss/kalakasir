<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SystemHealthEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class PlatformSystemHealthWebTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_guest_is_redirected_to_official_backoffice_entry(): void
    {
        $response = $this->get('/platform/system-health');

        $response->assertRedirect('/backoffice');
    }

    public function test_non_superadmin_cannot_access_monitoring_pages(): void
    {
        [, $user] = $this->bootApiActor('admin', 'platform-monitor-admin@example.test', 'Platform Monitor Admin');
        $event = $this->seedSystemHealthEvent($user->tenant_id, 'sales_cost_validate_failed', 'scope-admin');

        $this->actingAs($user)
            ->get('/platform/system-health')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/platform/system-health/incidents')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/platform/system-health/incidents/'.$event->id)
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/platform/system-health/tenants')
            ->assertForbidden();
    }

    public function test_superadmin_can_render_monitoring_pages(): void
    {
        [$tenant, $user] = $this->bootApiActor('superadmin', 'platform-monitor-superadmin@example.test', 'Platform Monitor Superadmin');
        $tenantB = $this->seedTenant('Tenant B');

        $event = $this->seedSystemHealthEvent($tenant->id, 'sales_cost_validate_failed', 'scope-primary');
        $this->seedSystemHealthEvent($tenant->id, 'strict_reader_blocked', 'finance-summary');
        $resolved = $this->seedSystemHealthEvent($tenantB, 'daily_summary_refresh_failed', 'tenant-b-summary');
        app(SystemHealthEventService::class)->resolve($resolved->id, 'refresh clean', [
            'actor_id' => $user->id,
        ]);

        $webUser = User::query()->findOrFail($user->id);

        $this->actingAs($webUser)
            ->get('/platform/system-health')
            ->assertOk()
            ->assertSee('Pusat pemantauan anomali finansial')
            ->assertSee('Tenant B');

        $this->actingAs($webUser)
            ->get('/platform/system-health/incidents')
            ->assertOk()
            ->assertSee('Insiden monitoring')
            ->assertSee('sales_cost_validate_failed');

        $this->actingAs($webUser)
            ->get('/platform/system-health/incidents/'.$event->id)
            ->assertOk()
            ->assertSee('sales_cost_validate_failed')
            ->assertSee('Kejadian #2');

        $this->actingAs($webUser)
            ->get('/platform/system-health/tenants')
            ->assertOk()
            ->assertSee('Matriks kesehatan tenant')
            ->assertSee('Tenant B');
    }

    private function seedSystemHealthEvent(string $tenantId, string $eventName, string $entityId)
    {
        $service = app(SystemHealthEventService::class);

        $service->recordOrBump($eventName, [
            'tenant_id' => $tenantId,
            'entity_type' => 'sales_cost_scope',
            'entity_id' => $entityId,
            'summary' => $eventName.' detected for '.$entityId,
            'payload' => ['entity_id' => $entityId],
            'dedupe_key' => $eventName.':'.$entityId,
        ]);

        return $service->recordOrBump($eventName, [
            'tenant_id' => $tenantId,
            'entity_type' => 'sales_cost_scope',
            'entity_id' => $entityId,
            'summary' => $eventName.' repeated for '.$entityId,
            'payload' => ['entity_id' => $entityId, 'attempt' => 2],
            'dedupe_key' => $eventName.':'.$entityId,
        ]);
    }

    private function seedTenant(string $name): string
    {
        $tenantId = (string) Str::uuid();

        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => $name,
            'timezone' => 'UTC',
            'created_at' => now('UTC'),
        ]);

        return $tenantId;
    }
}