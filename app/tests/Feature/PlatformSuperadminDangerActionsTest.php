<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Platform\TenantController;
use App\Http\Controllers\Api\Platform\AnomalyController;
use App\Http\Controllers\Api\Platform\UserController;
use App\Http\Requests\Platform\PlatformAnomalyResolveRequest;
use App\Http\Requests\Platform\PlatformTenantActionRequest;
use App\Http\Requests\Platform\PlatformUserActionRequest;
use App\Services\Platform\PlatformAnomalyService;
use App\Services\Platform\PlatformTenantService;
use App\Services\Platform\PlatformUserService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class PlatformSuperadminDangerActionsTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    private string $platformTenantId;

    protected function setUp(): void
    {
        parent::setUp();

        [$tenant] = $this->bootApiActor('superadmin', 'superadmin-danger@example.com', 'Superadmin Danger');
        $this->platformTenantId = $tenant->id;
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_global_audit_logs_list_and_export(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedAuditLog($tenantB, null, 'tenant_suspended', 'Suspend reason');

        $headers = $this->tenantHeaders($this->platformTenantId);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/audit-logs?tenant_id='.$tenantB.'&per_page=1&page=1');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.audit_logs.0.event_type', 'tenant_suspended');

        $export = $this->withHeaders($headers)
            ->get('/api/v1/platform/audit-logs/export?tenant_id='.$tenantB);

        $export->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $export->streamedContent();
        $this->assertStringContainsString('tenant_suspended', $csv);
    }

    public function test_tenant_suspend_conflict_and_audit_log(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedSubscription($tenantB, 'M', 'suspended');

        $headers = array_merge($this->tenantHeaders($this->platformTenantId), [
            'Idempotency-Key' => 'suspend-tenant-b',
        ]);

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/platform/tenants/'.$tenantB.'/suspend', [
                'reason' => 'Tenant sudah disuspend sebelumnya.',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');

        $tenantC = $this->seedTenant('Tenant C', 'S');
        $this->seedSubscription($tenantC, 'S', 'active');

        $okHeaders = array_merge($this->tenantHeaders($this->platformTenantId), [
            'Idempotency-Key' => 'suspend-tenant-c',
        ]);

        $ok = $this->withHeaders($okHeaders)
            ->postJson('/api/v1/platform/tenants/'.$tenantC.'/suspend', [
                'reason' => 'Suspend untuk pelanggaran kebijakan.',
            ]);

        $ok->assertStatus(200)
            ->assertJsonPath('data.action', 'tenant_suspended');

        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'tenant_suspended')->count());
    }

    public function test_tenant_impersonate_requires_idempotency_and_returns_expiry(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedSubscription($tenantB, 'M', 'active');

        $headers = $this->tenantHeaders($this->platformTenantId);

        $missing = $this->withHeaders($headers)
            ->postJson('/api/v1/platform/tenants/'.$tenantB.'/impersonate', [
                'reason' => 'Butuh investigasi cepat.',
            ]);

        $missing->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        $okHeaders = array_merge($headers, [
            'Idempotency-Key' => 'impersonate-tenant-b',
        ]);

        $ok = $this->withHeaders($okHeaders)
            ->postJson('/api/v1/platform/tenants/'.$tenantB.'/impersonate', [
                'reason' => 'Butuh investigasi cepat.',
            ]);

        $ok->assertStatus(200)
            ->assertJsonPath('data.action', 'tenant_impersonated');

        $this->assertNotNull($ok->json('data.impersonation_expires_at'));
    }

    public function test_direct_suspend_invocation_rejects_non_superadmin_actor(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedSubscription($tenantB, 'M', 'active');

        [, $admin] = $this->bootApiActor('admin', 'admin-platform-tenant-guard@example.com', 'Admin Platform Guard');

        $request = PlatformTenantActionRequest::create('/api/v1/platform/tenants/'.$tenantB.'/suspend', 'POST', [
            'reason' => 'Tenant action should be blocked outside superadmin context.',
        ]);
        $request->headers->set('Idempotency-Key', 'direct-suspend-blocked');
        $request->setUserResolver(fn () => $admin);

        $response = app(TenantController::class)->suspend(
            $request,
            app(PlatformTenantService::class),
            $tenantB,
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([
            'code' => 'FORBIDDEN',
            'message' => 'Akses ditolak untuk role ini.',
            'details' => [],
        ], $response->getData(true));
    }

    public function test_direct_impersonate_invocation_rejects_non_superadmin_actor(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedSubscription($tenantB, 'M', 'active');

        [, $admin] = $this->bootApiActor('admin', 'admin-platform-impersonate-guard@example.com', 'Admin Platform Impersonate Guard');

        $request = PlatformTenantActionRequest::create('/api/v1/platform/tenants/'.$tenantB.'/impersonate', 'POST', [
            'reason' => 'Impersonation should be blocked outside superadmin context.',
        ]);
        $request->headers->set('Idempotency-Key', 'direct-impersonate-blocked');
        $request->setUserResolver(fn () => $admin);

        $response = app(TenantController::class)->impersonate(
            $request,
            app(PlatformTenantService::class),
            $tenantB,
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([
            'code' => 'FORBIDDEN',
            'message' => 'Akses ditolak untuk role ini.',
            'details' => [],
        ], $response->getData(true));
    }

    public function test_user_actions_and_not_found(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $userId = $this->seedUser($tenantB, 'Admin', 'admin-b@example.com');

        $headers = array_merge($this->tenantHeaders($this->platformTenantId), [
            'Idempotency-Key' => 'reset-user-'.$userId,
        ]);

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/platform/users/'.$userId.'/reset-password', [
                'reason' => 'Reset kredensial untuk keamanan.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.action', 'user_reset_password');

        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'user_reset_password')->count());

        $notFound = $this->withHeaders($headers)
            ->postJson('/api/v1/platform/users/'.Str::uuid().'/reset-password', [
                'reason' => 'Reset kredensial untuk keamanan.',
            ]);

        $notFound->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_direct_reset_password_invocation_rejects_non_superadmin_actor(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $userId = $this->seedUser($tenantB, 'Admin', 'admin-direct-reset@example.com');

        [, $admin] = $this->bootApiActor('admin', 'admin-platform-user-guard@example.com', 'Admin Platform User Guard');

        $request = PlatformUserActionRequest::create('/api/v1/platform/users/'.$userId.'/reset-password', 'POST', [
            'reason' => 'Reset password should be blocked outside superadmin context.',
        ]);
        $request->headers->set('Idempotency-Key', 'direct-user-reset-blocked');
        $request->setUserResolver(fn () => $admin);

        $response = app(UserController::class)->resetPassword(
            $request,
            app(PlatformUserService::class),
            $userId,
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([
            'code' => 'FORBIDDEN',
            'message' => 'Akses ditolak untuk role ini.',
            'details' => [],
        ], $response->getData(true));
    }

    public function test_direct_anomaly_resolve_invocation_rejects_non_superadmin_actor(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $anomalyId = (string) Str::uuid();

        DB::table('anomalies')->insert([
            'id' => $anomalyId,
            'tenant_id' => $tenantB,
            'severity' => 'high',
            'type' => 'void_spike',
            'reference_id' => null,
            'payload_json' => json_encode(['scope' => 'platform-guard'], JSON_UNESCAPED_SLASHES),
            'detected_at' => now('UTC')->subMinute(),
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        [, $admin] = $this->bootApiActor('admin', 'admin-platform-anomaly-guard@example.com', 'Admin Platform Anomaly Guard');

        $request = PlatformAnomalyResolveRequest::create('/api/v1/platform/anomalies/'.$anomalyId.'/resolve', 'POST', [
            'reason' => 'Anomaly resolve should be blocked outside superadmin context.',
        ]);
        $request->headers->set('Idempotency-Key', 'direct-anomaly-resolve-blocked');
        $request->setUserResolver(fn () => $admin);

        $response = app(AnomalyController::class)->resolve(
            $request,
            app(PlatformAnomalyService::class),
            $anomalyId,
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([
            'code' => 'FORBIDDEN',
            'message' => 'Akses ditolak untuk role ini.',
            'details' => [],
        ], $response->getData(true));
    }

    public function test_superadmin_route_can_resolve_cross_tenant_anomaly(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $anomalyId = (string) Str::uuid();

        DB::table('anomalies')->insert([
            'id' => $anomalyId,
            'tenant_id' => $tenantB,
            'severity' => 'high',
            'type' => 'void_spike',
            'reference_id' => null,
            'payload_json' => json_encode(['scope' => 'platform-superadmin'], JSON_UNESCAPED_SLASHES),
            'detected_at' => now('UTC')->subMinute(),
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        $headers = array_merge($this->tenantHeaders($this->platformTenantId), [
            'Idempotency-Key' => 'resolve-anomaly-'.$anomalyId,
        ]);

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/platform/anomalies/'.$anomalyId.'/resolve', [
                'reason' => 'Resolve anomaly lintas tenant untuk investigasi platform.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.action', 'anomaly_resolved')
            ->assertJsonPath('data.anomaly_id', $anomalyId);

        $this->assertNotNull(DB::table('anomalies')->where('id', $anomalyId)->value('resolved_at'));
        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'anomaly_resolved')->count());
    }

    public function test_tenant_reset_owner_password_and_unlock(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedSubscription($tenantB, 'M', 'active');
        $ownerId = $this->seedUser($tenantB, 'Owner', 'owner-b@example.com');
        $staffId = $this->seedUser($tenantB, 'Admin', 'staff-b@example.com');

        DB::table('users')->where('id', $staffId)->update(['failed_login_attempts' => 4]);

        $headers = array_merge($this->tenantHeaders($this->platformTenantId), [
            'Idempotency-Key' => 'reset-owner-'.$tenantB,
        ]);

        $reset = $this->withHeaders($headers)
            ->postJson('/api/v1/platform/tenants/'.$tenantB.'/reset-owner-password', [
                'reason' => 'Reset akses owner karena insiden keamanan.',
            ]);

        $reset->assertStatus(200)
            ->assertJsonPath('data.action', 'tenant_reset_owner');

        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'tenant_reset_owner')->count());

        $unlockHeaders = array_merge($this->tenantHeaders($this->platformTenantId), [
            'Idempotency-Key' => 'unlock-tenant-'.$tenantB,
        ]);

        $unlock = $this->withHeaders($unlockHeaders)
            ->postJson('/api/v1/platform/tenants/'.$tenantB.'/unlock', [
                'reason' => 'Buka akses setelah verifikasi.',
            ]);

        $unlock->assertStatus(200)
            ->assertJsonPath('data.action', 'tenant_unlock');

        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'tenant_unlock')->count());
        $this->assertSame(0, DB::table('users')->where('id', $ownerId)->value('failed_login_attempts'));
        $this->assertSame(0, DB::table('users')->where('id', $staffId)->value('failed_login_attempts'));
    }

    public function test_user_force_logout_and_unlock(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $userId = $this->seedUser($tenantB, 'Admin', 'admin-logout@example.com');

        $this->seedSession($userId, now('UTC')->timestamp);

        $headers = array_merge($this->tenantHeaders($this->platformTenantId), [
            'Idempotency-Key' => 'force-logout-'.$userId,
        ]);

        $forceLogout = $this->withHeaders($headers)
            ->postJson('/api/v1/platform/users/'.$userId.'/force-logout', [
                'reason' => 'Hentikan sesi aktif untuk investigasi.',
            ]);

        $forceLogout->assertStatus(200)
            ->assertJsonPath('data.action', 'user_force_logout');

        $this->assertSame(0, DB::table('sessions')->where('user_id', $userId)->count());
        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'user_force_logout')->count());

        DB::table('users')->where('id', $userId)->update(['failed_login_attempts' => 5]);

        $unlockHeaders = array_merge($this->tenantHeaders($this->platformTenantId), [
            'Idempotency-Key' => 'unlock-user-'.$userId,
        ]);

        $unlock = $this->withHeaders($unlockHeaders)
            ->postJson('/api/v1/platform/users/'.$userId.'/unlock', [
                'reason' => 'Reset lock setelah verifikasi.',
            ]);

        $unlock->assertStatus(200)
            ->assertJsonPath('data.action', 'user_unlock');

        $this->assertSame(0, DB::table('users')->where('id', $userId)->value('failed_login_attempts'));
        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'user_unlock')->count());
    }

    public function test_idempotency_prevents_duplicate_audit_log(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedSubscription($tenantB, 'M', 'active');

        $headers = array_merge($this->tenantHeaders($this->platformTenantId), [
            'Idempotency-Key' => 'suspend-same-key',
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/platform/tenants/'.$tenantB.'/suspend', [
                'reason' => 'Pengujian idempotency action.',
            ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/platform/tenants/'.$tenantB.'/suspend', [
                'reason' => 'Pengujian idempotency action.',
            ]);

        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'tenant_suspended')->count());
    }

    public function test_rate_limit_for_danger_actions(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedSubscription($tenantB, 'M', 'active');

        $headers = $this->tenantHeaders($this->platformTenantId);
        $lastResponse = null;

        for ($i = 0; $i < 11; $i++) {
            $reqHeaders = array_merge($headers, [
                'Idempotency-Key' => 'force-logout-'.$tenantB.'-'.$i,
            ]);

            $lastResponse = $this->withHeaders($reqHeaders)
                ->postJson('/api/v1/platform/tenants/'.$tenantB.'/force-logout', [
                    'reason' => 'Rate limit test action '.$i.'.',
                ]);
        }

        $this->assertNotNull($lastResponse);
        $lastResponse->assertStatus(429)
            ->assertJsonPath('code', 'RATE_LIMITED');
    }

    public function test_impersonation_expiry_logs_audit_end_event(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedSubscription($tenantB, 'M', 'active');

        $user = User::query()->first();
        $this->actingAs($user);

        $expiredAt = CarbonImmutable::now('UTC')->subMinute()->toIso8601String();

        $this->withSession([
            'impersonation' => [
                'tenant_id' => $tenantB,
                'tenant_name' => 'Tenant B',
                'actor_id' => $this->platformTenantId,
                'started_at' => CarbonImmutable::now('UTC')->subMinutes(30)->toIso8601String(),
                'expires_at' => $expiredAt,
                'request_id' => 'req-expired-1',
            ],
        ])->get('/platform/dashboard')
            ->assertStatus(200);

        $this->assertSame(1, DB::table('audit_logs')
            ->where('event_type', 'tenant_impersonation_ended')
            ->where('reference_id', $tenantB)
            ->count());
    }

    private function seedTenant(string $name, string $plan): string
    {
        $id = (string) Str::uuid();

        DB::table('tenants')->insert([
            'id' => $id,
            'name' => $name,
            'timezone' => 'UTC',
            'plan' => $plan,
            'created_at' => now('UTC'),
        ]);

        return $id;
    }

    private function seedSubscription(string $tenantId, string $plan, string $status): void
    {
        DB::table('subscriptions')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'plan' => $plan,
            'status' => $status,
            'started_at' => now('UTC')->subDays(5),
            'expires_at' => now('UTC')->addDays(10),
            'current_period_start' => now('UTC')->subDays(5),
            'current_period_end' => now('UTC')->addDays(10),
            'is_current' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    private function seedUser(string $tenantId, string $roleName, string $email): string
    {
        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'tenant_id' => $tenantId,
            'name' => $roleName.' User',
            'email' => $email,
            'password' => 'secret',
            'failed_login_attempts' => 0,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'id' => $roleId,
            'tenant_id' => $tenantId,
            'name' => $roleName,
        ]);

        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);

        return $userId;
    }

    private function seedAuditLog(string $tenantId, ?string $actorId, string $eventType, string $reason): void
    {
        DB::table('audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
            'event_type' => $eventType,
            'reference_type' => 'tenant',
            'reference_id' => $tenantId,
            'payload_json' => json_encode([
                'reason' => $reason,
            ]),
            'created_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    private function seedSession(string $userId, int $lastActivity): void
    {
        DB::table('sessions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => '[]',
            'last_activity' => $lastActivity,
        ]);
    }
}
