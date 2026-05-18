<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class PlatformSuperadminWebPagesTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_superadmin_can_open_resource_pages(): void
    {
        [$tenant, $apiUser] = $this->bootApiActor('superadmin', 'platform-web-superadmin@example.test', 'Platform Web Superadmin');
        $webUser = User::query()->findOrFail($apiUser->id);

        $this->actingAs($webUser)
            ->get('/superadmin/tenants')
            ->assertOk()
            ->assertSee('Tenants')
            ->assertSee($tenant->name);

        $this->actingAs($webUser)
            ->get('/superadmin/tenants/'.$tenant->id)
            ->assertOk()
            ->assertSee($tenant->name);

        $this->actingAs($webUser)
            ->get('/superadmin/users')
            ->assertOk()
            ->assertSee('Global Users')
            ->assertSee('platform-web-superadmin@example.test');

        $this->actingAs($webUser)
            ->get('/superadmin/users/'.$webUser->id)
            ->assertOk()
            ->assertSee('Platform Web Superadmin');

        $this->actingAs($webUser)
            ->get('/superadmin/billing/invoices')
            ->assertOk()
            ->assertSee('Billing / Invoices');

        $this->actingAs($webUser)
            ->get('/superadmin/audit-logs')
            ->assertOk()
            ->assertSee('Audit Logs');

        $this->actingAs($webUser)
            ->get('/superadmin/anomalies')
            ->assertOk()
            ->assertSee('Anomalies');

        $this->actingAs($webUser)
            ->get('/superadmin/support/search')
            ->assertOk()
            ->assertSee('Support Search');

        $this->actingAs($webUser)
            ->get('/superadmin/health')
            ->assertOk()
            ->assertSee('Platform Health');
    }

    public function test_non_superadmin_cannot_open_resource_pages(): void
    {
        [, $apiUser] = $this->bootApiActor('admin', 'platform-web-admin@example.test', 'Platform Web Admin');
        $webUser = User::query()->findOrFail($apiUser->id);

        $this->actingAs($webUser)
            ->get('/superadmin/tenants')
            ->assertForbidden();
    }
}
