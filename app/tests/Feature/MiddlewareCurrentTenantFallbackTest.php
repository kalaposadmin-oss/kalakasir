<?php

namespace Tests\Feature;

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\WebAccessMiddleware;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MiddlewareCurrentTenantFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_role_middleware_fallback_uses_current_tenant_before_user_base_tenant(): void
    {
        [$baseTenant, $currentTenant, $user] = $this->seedUserWithCurrentTenantRole('admin');
        $this->actingAs($user, 'sanctum');
        TenantContext::clear();

        $request = Request::create('/api/v1/products', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = app(RoleMiddleware::class)->handle(
            $request,
            fn () => response()->json(['ok' => true]),
            'admin'
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($baseTenant->id, $user->tenant_id);
        $this->assertSame($currentTenant->id, $user->current_tenant_id);
    }

    public function test_web_access_middleware_fallback_uses_current_tenant_for_role_checks(): void
    {
        [, , $user] = $this->seedUserWithCurrentTenantRole('owner');
        $this->actingAs($user);
        TenantContext::clear();

        $request = Request::create('/backoffice/products/new', 'GET');
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));

        $response = app(WebAccessMiddleware::class)->handle(
            $request,
            fn () => response('ok'),
            'backoffice',
            'owner',
            '*'
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    private function seedUserWithCurrentTenantRole(string $roleName): array
    {
        $baseTenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Base',
            'timezone' => 'UTC',
        ]);
        $currentTenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Current',
            'timezone' => 'UTC',
        ]);

        $user = User::query()->create([
            'tenant_id' => $baseTenant->id,
            'name' => 'Current Tenant User',
            'email' => 'current-tenant-'.Str::lower(Str::random(8)).'@example.test',
            'password' => 'secret',
        ]);
        $user->forceFill(['current_tenant_id' => $currentTenant->id])->save();

        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'id' => $roleId,
            'tenant_id' => $currentTenant->id,
            'name' => $roleName,
        ]);
        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $currentTenant->id,
            'user_id' => $user->id,
            'role_id' => $roleId,
        ]);

        return [$baseTenant, $currentTenant, $user];
    }
}
