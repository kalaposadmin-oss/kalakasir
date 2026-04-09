<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CurrentTenantLoginSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_login_sets_current_tenant_id_and_session_when_missing(): void
    {
        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Login Sync',
            'timezone' => 'UTC',
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Web Login User',
            'email' => 'current-tenant-login@example.test',
            'password' => 'secret',
        ]);

        $session = app('session.store');
        $session->start();
        request()->setLaravelSession($session);

        event(new Login('web', $user, false));

        $user->refresh();

        $this->assertSame($tenant->id, $user->current_tenant_id);
        $this->assertSame($tenant->id, $session->get('current_tenant_id'));
    }

    public function test_web_login_replaces_invalid_current_tenant_id(): void
    {
        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Login Replace',
            'timezone' => 'UTC',
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Web Login Replace User',
            'email' => 'current-tenant-replace@example.test',
            'password' => 'secret',
        ]);

        $user->forceFill([
            'current_tenant_id' => (string) Str::uuid(),
        ])->save();

        $session = app('session.store');
        $session->start();
        request()->setLaravelSession($session);

        event(new Login('web', $user->fresh(), false));

        $user->refresh();

        $this->assertSame($tenant->id, $user->current_tenant_id);
        $this->assertSame($tenant->id, $session->get('current_tenant_id'));
    }
}