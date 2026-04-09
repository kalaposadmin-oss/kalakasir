<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Platform\AuditLogController;
use App\Http\Controllers\Api\Platform\AnomalyController;
use App\Http\Controllers\Api\Platform\InvoiceController;
use App\Http\Controllers\Api\Platform\SupportController;
use App\Http\Controllers\Api\Platform\TenantController;
use App\Http\Controllers\Api\Platform\UserController;
use App\Http\Requests\Platform\PlatformAnomalyIndexRequest;
use App\Http\Requests\Platform\PlatformAuditLogIndexRequest;
use App\Http\Requests\Platform\PlatformInvoiceShowRequest;
use App\Http\Requests\Platform\PlatformSupportSearchRequest;
use App\Http\Requests\Platform\PlatformTenantShowRequest;
use App\Http\Requests\Platform\PlatformUserShowRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class PlatformSuperadminReadApiTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        [$tenant] = $this->bootApiActor('superadmin', 'superadmin-read@example.com', 'Superadmin Read');
        $this->tenantId = $tenant->id;
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_dashboard_requires_superadmin(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin-dashboard@example.com', 'Admin Dashboard');

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/platform/dashboard');

        $response->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_dashboard_returns_totals_health_and_recent_events(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $tenantC = $this->seedTenant('Tenant C', 'P');

        $this->seedSubscription($this->tenantId, 'S', 'active');
        $this->seedSubscription($tenantB, 'M', 'suspended');
        $this->seedSubscription($tenantC, 'P', 'active');

        $this->seedUser($this->tenantId, 'Admin', 'admin-a@example.com');
        $this->seedUser($tenantB, 'Admin', 'admin-b@example.com');
        $this->seedUser($tenantC, 'Admin', 'admin-c@example.com');

        $this->seedInvoice($tenantC, 'INV-001', CarbonImmutable::now('UTC')->subDay(), 'open');
        $this->seedInvoice($this->tenantId, 'INV-002', CarbonImmutable::now('UTC')->addDay(), 'paid');

        $this->seedSubscriptionAuditLog($tenantC, 'S', 'P', 'Upgrade audit 1');
        $this->seedSubscriptionAuditLog($tenantC, 'P', 'P', 'Renewal audit 2');

        $headers = array_merge($this->tenantHeaders($this->tenantId), [
            'X-Request-Id' => 'req-dashboard-1',
        ]);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('meta.request_id', 'req-dashboard-1')
            ->assertJsonPath('data.totals.tenant_total', 3)
            ->assertJsonPath('data.totals.tenant_suspended', 1)
            ->assertJsonPath('data.totals.tenant_active', 2)
            ->assertJsonPath('data.totals.user_total', 4)
            ->assertJsonPath('data.overdue_invoices', 1)
            ->assertJsonPath('data.health_summary.healthy', 2)
            ->assertJsonPath('data.health_summary.billing_issue', 1);

        $this->assertCount(2, $response->json('data.recent_events'));
    }

    public function test_tenants_list_filters_and_pagination(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $tenantC = $this->seedTenant('Tenant C', 'P');

        $this->seedSubscription($this->tenantId, 'S', 'active');
        $this->seedSubscription($tenantB, 'M', 'suspended');
        $this->seedSubscription($tenantC, 'P', 'active');

        $this->seedInvoice($tenantC, 'INV-010', CarbonImmutable::now('UTC')->subDay(), 'open');

        $this->seedSession($this->seedUser($this->tenantId, 'Admin', 'admin-a@example.com'), now('UTC')->timestamp);
        $this->seedSession($this->seedUser($tenantB, 'Admin', 'admin-b@example.com'), now('UTC')->timestamp);

        $headers = array_merge($this->tenantHeaders($this->tenantId), [
            'X-Request-Id' => 'req-tenants-1',
        ]);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/tenants?per_page=1&page=2&sort_by=name&sort_dir=asc');

        $response->assertStatus(200)
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.request_id', 'req-tenants-1');

        $filterPlan = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/tenants?plan=M');

        $filterPlan->assertStatus(200)
            ->assertJsonPath('data.tenants.0.tenant_id', $tenantB);

        $filterStatus = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/tenants?status=suspended');

        $filterStatus->assertStatus(200)
            ->assertJsonPath('data.tenants.0.tenant_id', $tenantB);

        $filterHealth = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/tenants?health=billing_issue');

        $filterHealth->assertStatus(200)
            ->assertJsonPath('data.tenants.0.tenant_id', $tenantC);
    }

    public function test_tenants_list_invalid_sort_returns_422(): void
    {
        $headers = $this->tenantHeaders($this->tenantId);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/tenants?sort_by=invalid');

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_tenant_detail_returns_usage_and_recent_events(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedSubscription($tenantB, 'M', 'active');

        $branchId = (string) Str::uuid();
        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenantB,
            'name' => 'Outlet B',
        ]);

        $this->seedSale($tenantB, $branchId, 120000);
        $this->seedSale($tenantB, $branchId, 80000);

        $this->seedUser($tenantB, 'Admin', 'admin-b@example.com');
        $this->seedSubscriptionAuditLog($tenantB, 'S', 'M', 'Upgrade audit B');

        $headers = $this->tenantHeaders($this->tenantId);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/tenants/'.$tenantB);

        $response->assertStatus(200)
            ->assertJsonPath('data.usage_summary.orders', 2)
            ->assertJsonPath('data.usage_summary.revenue', 200000);

        $this->assertNotEmpty($response->json('data.users_preview'));
        $this->assertNotEmpty($response->json('data.recent_events'));
    }

    public function test_direct_tenant_show_invocation_rejects_non_superadmin_actor(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedSubscription($tenantB, 'M', 'active');

        [, $admin] = $this->bootApiActor('admin', 'admin-platform-tenant-read-guard@example.com', 'Admin Platform Tenant Read Guard');

        $request = PlatformTenantShowRequest::create('/api/v1/platform/tenants/'.$tenantB, 'GET');
        $request->setUserResolver(fn () => $admin);

        $response = app(TenantController::class)->show(
            $request,
            app(\App\Services\Platform\PlatformTenantService::class),
            $tenantB,
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([
            'code' => 'FORBIDDEN',
            'message' => 'Akses ditolak untuk role ini.',
            'details' => [],
        ], $response->getData(true));
    }

    public function test_tenant_detail_not_found_returns_404(): void
    {
        $headers = $this->tenantHeaders($this->tenantId);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/tenants/'.Str::uuid());

        $response->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_users_list_filters_role_status_and_login_range(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $userAdminId = $this->seedUser($tenantB, 'Admin', 'admin-b@example.com');
        $userInactiveId = $this->seedUser($tenantB, 'Supervisor', 'inactive-b@example.com');

        DB::table('users')->where('id', $userInactiveId)->update([
            'deleted_at' => now('UTC'),
        ]);

        $this->seedSession($userAdminId, now('UTC')->subDay()->timestamp);
        $this->seedSession($userInactiveId, now('UTC')->subDays(10)->timestamp);

        $headers = $this->tenantHeaders($this->tenantId);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/users?tenant_id='.$tenantB.'&role=admin&last_login_from='.
                now('UTC')->subDays(2)->toDateString());

        $response->assertStatus(200)
            ->assertJsonPath('data.users.0.user_id', $userAdminId)
            ->assertJsonPath('data.users.0.role', 'admin');

        $inactive = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/users?status=inactive');

        $inactive->assertStatus(200);
        $this->assertTrue(collect($inactive->json('data.users'))->contains(fn ($row) => $row['user_id'] === $userInactiveId));
    }

    public function test_user_detail_not_found_returns_404(): void
    {
        $headers = $this->tenantHeaders($this->tenantId);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/users/'.Str::uuid());

        $response->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_user_detail_returns_role_status_and_last_login(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $userId = $this->seedUser($tenantB, 'Admin', 'admin-b@example.com');

        $this->seedSession($userId, now('UTC')->subHour()->timestamp);

        $headers = $this->tenantHeaders($this->tenantId);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/users/'.$userId);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.user_id', $userId)
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonPath('data.user.status', 'active');

        $this->assertNotEmpty($response->json('data.user.last_login_at'));
        $this->assertNotEmpty($response->json('data.user.created_at'));
    }

    public function test_direct_user_show_invocation_rejects_non_superadmin_actor(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $userId = $this->seedUser($tenantB, 'Admin', 'admin-user-guard@example.com');

        [, $admin] = $this->bootApiActor('admin', 'admin-platform-user-read-guard@example.com', 'Admin Platform User Read Guard');

        $request = PlatformUserShowRequest::create('/api/v1/platform/users/'.$userId, 'GET');
        $request->setUserResolver(fn () => $admin);

        $response = app(UserController::class)->show(
            $request,
            app(\App\Services\Platform\PlatformUserService::class),
            $userId,
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([
            'code' => 'FORBIDDEN',
            'message' => 'Akses ditolak untuk role ini.',
            'details' => [],
        ], $response->getData(true));
    }

    public function test_platform_health_returns_checks_and_metrics(): void
    {
        $headers = array_merge($this->tenantHeaders($this->tenantId), [
            'X-Request-Id' => 'req-health-1',
        ]);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/health');

        $response->assertStatus(200)
            ->assertJsonPath('meta.request_id', 'req-health-1')
            ->assertJsonPath('data.metrics.tenant_total', 1)
            ->assertJsonPath('data.metrics.user_total', 1);

        $this->assertNotEmpty($response->json('data.checks'));
    }

    public function test_user_audit_logs_paginate(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $userId = $this->seedUser($tenantB, 'Admin', 'admin-b@example.com');

        $this->seedAuditLog($tenantB, $userId, 'user_login');
        $this->seedAuditLog($tenantB, $userId, 'user_logout');

        $headers = $this->tenantHeaders($this->tenantId);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/users/'.$userId.'/audit-logs?per_page=1&page=2');

        $response->assertStatus(200)
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_direct_platform_audit_log_index_invocation_rejects_non_superadmin_actor(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $actorId = $this->seedUser($tenantB, 'Admin', 'audit-guard-actor@example.com');
        $this->seedAuditLog($tenantB, $actorId, 'tenant_suspended');

        [, $admin] = $this->bootApiActor('admin', 'admin-platform-audit-guard@example.com', 'Admin Platform Audit Guard');

        $request = PlatformAuditLogIndexRequest::create('/api/v1/platform/audit-logs', 'GET', [
            'tenant_id' => $tenantB,
        ]);
        $request->setUserResolver(fn () => $admin);

        $response = app(AuditLogController::class)->index(
            $request,
            app(\App\Services\Platform\PlatformAuditLogService::class),
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([
            'code' => 'FORBIDDEN',
            'message' => 'Akses ditolak untuk role ini.',
            'details' => [],
        ], $response->getData(true));
    }

    public function test_invoices_list_filters_pagination_and_normalization(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $tenantC = $this->seedTenant('Tenant C', 'P');
        $now = CarbonImmutable::now('UTC');

        $invoiceOpenId = $this->seedInvoice($tenantB, 'INV-100', $now->addDays(5), 'unpaid', 110000);
        $invoiceOverdueId = $this->seedInvoice($tenantB, 'INV-200', $now->subDays(2), 'open', 90000);
        $this->seedInvoice($tenantC, 'INV-300', $now->subDay(), 'paid', 120000);

        $headers = $this->tenantHeaders($this->tenantId);

        $pageTwo = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/invoices?tenant_id='.$tenantB.'&per_page=1&page=2&sort_by=due_at&sort_dir=asc');

        $pageTwo->assertStatus(200)
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);

        $list = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/invoices?tenant_id='.$tenantB.'&sort_by=due_at&sort_dir=asc');

        $items = collect($list->json('data.invoices'));
        $this->assertTrue($items->contains(fn ($row) => $row['invoice_id'] === $invoiceOpenId && $row['status'] === 'open'));
        $this->assertTrue($items->contains(fn ($row) => $row['invoice_id'] === $invoiceOverdueId && $row['status'] === 'overdue'));

        $overdue = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/invoices?status=overdue');

        $overdue->assertStatus(200);
        $this->assertTrue(collect($overdue->json('data.invoices'))
            ->contains(fn ($row) => $row['invoice_id'] === $invoiceOverdueId));

        $search = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/invoices?q=INV-100');

        $search->assertStatus(200);
        $this->assertTrue(collect($search->json('data.invoices'))
            ->contains(fn ($row) => $row['invoice_id'] === $invoiceOpenId));
    }

    public function test_invoice_detail_success_and_not_found(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $invoiceId = $this->seedInvoice($tenantB, 'INV-400', CarbonImmutable::now('UTC')->addDays(3), 'unpaid', 75000);
        $this->seedInvoiceItem($invoiceId, 'Subscription Fee', 1, 75000);

        $headers = $this->tenantHeaders($this->tenantId);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/invoices/'.$invoiceId);

        $response->assertStatus(200)
            ->assertJsonPath('data.invoice.invoice_id', $invoiceId)
            ->assertJsonPath('data.invoice.number', 'INV-400');

        $this->assertCount(1, $response->json('data.items'));

        $notFound = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/invoices/'.Str::uuid());

        $notFound->assertStatus(404)
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_direct_invoice_show_invocation_rejects_non_superadmin_actor(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $invoiceId = $this->seedInvoice($tenantB, 'INV-401', CarbonImmutable::now('UTC')->addDays(3), 'unpaid', 85000);

        [, $admin] = $this->bootApiActor('admin', 'admin-platform-invoice-guard@example.com', 'Admin Platform Invoice Guard');

        $request = PlatformInvoiceShowRequest::create('/api/v1/platform/invoices/'.$invoiceId, 'GET');
        $request->setUserResolver(fn () => $admin);

        $response = app(InvoiceController::class)->show(
            $request,
            app(\App\Services\Platform\PlatformInvoiceService::class),
            $invoiceId,
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([
            'code' => 'FORBIDDEN',
            'message' => 'Akses ditolak untuk role ini.',
            'details' => [],
        ], $response->getData(true));
    }

    public function test_plan_changes_list_filters_and_sort(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');

        $firstId = $this->seedPlanChange($tenantB, 'S', 'M', 'upgrade', 'Upgrade audit', now('UTC')->subDays(2));
        $secondId = $this->seedPlanChange($tenantB, 'M', 'S', 'downgrade', 'Downgrade audit', now('UTC')->subDay());

        $headers = $this->tenantHeaders($this->tenantId);

        $filtered = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/plan-changes?tenant_id='.$tenantB.'&mode=upgrade');

        $filtered->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.plan_changes.0.id', $firstId)
            ->assertJsonPath('data.plan_changes.0.mode', 'upgrade');

        $sorted = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/plan-changes?sort_by=created_at&sort_dir=asc');

        $sorted->assertStatus(200)
            ->assertJsonPath('data.plan_changes.0.id', $firstId)
            ->assertJsonPath('data.plan_changes.1.id', $secondId);
    }

    public function test_plan_changes_export_returns_csv(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedPlanChange($tenantB, 'S', 'M', 'upgrade', 'Upgrade export audit', now('UTC')->subDay());

        $headers = $this->tenantHeaders($this->tenantId);

        $export = $this->withHeaders($headers)
            ->get('/api/v1/platform/plan-changes/export?tenant_id='.$tenantB);

        $export->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $export->streamedContent();
        $this->assertStringContainsString('plan-changes-', $export->headers->get('content-disposition'));
        $this->assertStringContainsString('Upgrade export audit', $csv);
    }

    public function test_platform_payments_list_filters_and_pagination(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $invoiceId = $this->seedInvoice($tenantB, 'INV-910', CarbonImmutable::now('UTC')->addDays(3), 'unpaid');

        $paymentA = $this->seedSubscriptionPayment($tenantB, $invoiceId, 'manual', 'REF-910', 'paid', 120000);
        $this->seedSubscriptionPayment($tenantB, $invoiceId, 'manual', 'REF-911', 'failed', 50000);

        $headers = $this->tenantHeaders($this->tenantId);

        $pageTwo = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/payments?tenant_id='.$tenantB.'&per_page=1&page=2&sort_by=created_at&sort_dir=asc');

        $pageTwo->assertStatus(200)
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);

        $filtered = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/payments?tenant_id='.$tenantB.'&status=paid');

        $filtered->assertStatus(200);
        $this->assertTrue(collect($filtered->json('data.payments'))
            ->contains(fn ($row) => $row['payment_id'] === $paymentA));
    }

    public function test_platform_settings_feature_flags_and_rate_limits(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedSubscription($tenantB, 'M', 'active');

        $headers = $this->tenantHeaders($this->tenantId);

        $featureFlags = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/settings/feature-flags?tenant_id='.$tenantB);

        $featureFlags->assertStatus(200)
            ->assertJsonPath('data.tenant.tenant_id', $tenantB)
            ->assertJsonPath('data.tenant.plan', 'M');

        $rateLimits = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/settings/rate-limits');

        $rateLimits->assertStatus(200)
            ->assertJsonPath('data.platform_danger_per_hour', 10);
    }

    public function test_anomalies_list_filter_and_pagination(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $unresolvedId = $this->seedAnomaly($tenantB, 'high', null);
        $this->seedAnomaly($tenantB, 'low', now('UTC')->subDay());

        $headers = $this->tenantHeaders($this->tenantId);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/anomalies?severity=high&resolved=false&per_page=1&page=1');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.anomalies.0.anomaly_id', $unresolvedId);
    }

    public function test_direct_anomaly_index_invocation_rejects_non_superadmin_actor(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedAnomaly($tenantB, 'high', null);

        [, $admin] = $this->bootApiActor('admin', 'admin-platform-anomaly-read-guard@example.com', 'Admin Platform Anomaly Read Guard');

        $request = PlatformAnomalyIndexRequest::create('/api/v1/platform/anomalies', 'GET', [
            'severity' => 'high',
        ]);
        $request->setUserResolver(fn () => $admin);

        $response = app(AnomalyController::class)->index(
            $request,
            app(\App\Services\Platform\PlatformAnomalyService::class),
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([
            'code' => 'FORBIDDEN',
            'message' => 'Akses ditolak untuk role ini.',
            'details' => [],
        ], $response->getData(true));
    }

    public function test_anomaly_resolve_success_records_audit_log(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $anomalyId = $this->seedAnomaly($tenantB, 'critical', null);

        $headers = array_merge($this->tenantHeaders($this->tenantId), [
            'Idempotency-Key' => 'resolve-'.$anomalyId,
        ]);

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/platform/anomalies/'.$anomalyId.'/resolve', [
                'reason' => 'Validasi manual sudah dilakukan.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.action', 'anomaly_resolved');

        $row = DB::table('anomalies')->where('id', $anomalyId)->first();
        $this->assertNotNull($row->resolved_at);

        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'anomaly_resolved')->count());
    }

    public function test_anomaly_resolve_idempotency_and_conflict(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $anomalyId = $this->seedAnomaly($tenantB, 'high', null);

        $headers = array_merge($this->tenantHeaders($this->tenantId), [
            'Idempotency-Key' => 'resolve-once-'.$anomalyId,
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/platform/anomalies/'.$anomalyId.'/resolve', [
                'reason' => 'Idempotency check.',
            ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/platform/anomalies/'.$anomalyId.'/resolve', [
                'reason' => 'Idempotency check.',
            ]);

        $this->assertSame(1, DB::table('audit_logs')->where('event_type', 'anomaly_resolved')->count());

        $resolvedId = $this->seedAnomaly($tenantB, 'medium', now('UTC')->subDay());
        $conflict = $this->withHeaders(array_merge($this->tenantHeaders($this->tenantId), [
            'Idempotency-Key' => 'resolve-conflict-'.$resolvedId,
        ]))
            ->postJson('/api/v1/platform/anomalies/'.$resolvedId.'/resolve', [
                'reason' => 'Sudah selesai.',
            ]);

        $conflict->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');
    }

    public function test_support_actions_filters_and_pagination(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $actorId = $this->seedUser($tenantB, 'Admin', 'admin-support@example.com');

        $this->seedSupportAudit($tenantB, $actorId, 'tenant_suspended', 'Manual suspend');
        $this->seedSupportAudit($tenantB, $actorId, 'user_force_logout', 'Force logout');

        $headers = $this->tenantHeaders($this->tenantId);

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/support/actions?tenant_id='.$tenantB.'&action_type=tenant_suspended&per_page=1&page=1');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.actions.0.action_type', 'tenant_suspended');
    }

    public function test_support_search_by_tenant_user_and_invoice(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $userId = $this->seedUser($tenantB, 'Admin', 'helpdesk@example.com');
        $invoiceId = $this->seedInvoice($tenantB, 'INV-900', CarbonImmutable::now('UTC')->addDays(2), 'unpaid', 50000);

        $headers = $this->tenantHeaders($this->tenantId);

        $tenantSearch = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/support/search?q=Tenant B');

        $this->assertTrue(collect($tenantSearch->json('data.matches'))
            ->contains(fn ($row) => $row['type'] === 'tenant' && $row['id'] === $tenantB));

        $userSearch = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/support/search?q=helpdesk@example.com');

        $this->assertTrue(collect($userSearch->json('data.matches'))
            ->contains(fn ($row) => $row['type'] === 'user' && $row['id'] === $userId));

        $invoiceSearch = $this->withHeaders($headers)
            ->getJson('/api/v1/platform/support/search?q=INV-900');

        $this->assertTrue(collect($invoiceSearch->json('data.matches'))
            ->contains(fn ($row) => $row['type'] === 'invoice' && $row['id'] === $invoiceId));
    }

    public function test_direct_platform_support_search_invocation_rejects_non_superadmin_actor(): void
    {
        $tenantB = $this->seedTenant('Tenant B', 'M');
        $this->seedUser($tenantB, 'Admin', 'support-guard@example.com');

        [, $admin] = $this->bootApiActor('admin', 'admin-platform-support-guard@example.com', 'Admin Platform Support Guard');

        $request = PlatformSupportSearchRequest::create('/api/v1/platform/support/search', 'GET', [
            'q' => 'Tenant B',
        ]);
        $request->setUserResolver(fn () => $admin);

        $response = app(SupportController::class)->search(
            $request,
            app(\App\Services\Platform\PlatformSupportService::class),
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([
            'code' => 'FORBIDDEN',
            'message' => 'Akses ditolak untuk role ini.',
            'details' => [],
        ], $response->getData(true));
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
            'started_at' => now('UTC')->subDays(10),
            'expires_at' => now('UTC')->addDays(20),
            'current_period_start' => now('UTC')->subDays(10),
            'current_period_end' => now('UTC')->addDays(20),
            'is_current' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    private function seedInvoice(string $tenantId, string $number, CarbonImmutable $dueAt, string $status, ?int $amountDue = null): string
    {
        $invoiceId = (string) Str::uuid();

        DB::table('invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $tenantId,
            'number' => $number,
            'period_start' => $dueAt->toDateString(),
            'period_end' => $dueAt->toDateString(),
            'amount_due' => $amountDue ?? 100000,
            'amount_paid' => 0,
            'status' => $status,
            'issued_at' => $dueAt->subDay(),
            'due_at' => $dueAt,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return $invoiceId;
    }

    private function seedSubscriptionAuditLog(string $tenantId, string $oldPlan, string $newPlan, string $reason): void
    {
        DB::table('subscription_audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'actor_id' => (string) Str::uuid(),
            'old_plan' => $oldPlan,
            'new_plan' => $newPlan,
            'reason' => $reason,
            'created_at' => now('UTC')->subDay(),
        ]);
    }

    private function seedPlanChange(
        string $tenantId,
        string $oldPlan,
        string $newPlan,
        string $mode,
        string $reason,
        \DateTimeInterface $createdAt
    ): string
    {
        $id = (string) Str::uuid();

        DB::table('subscription_audit_logs')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'actor_id' => (string) Str::uuid(),
            'old_plan' => $oldPlan,
            'new_plan' => $newPlan,
            'reason' => $reason,
            'change_mode' => $mode,
            'effective_at' => $createdAt,
            'proration_amount' => 0,
            'request_id' => 'req-'.$id,
            'created_at' => $createdAt,
        ]);

        return $id;
    }

    private function seedInvoiceItem(string $invoiceId, string $description, int $qty, int $unitPrice): void
    {
        DB::table('invoice_items')->insert([
            'invoice_id' => $invoiceId,
            'description' => $description,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'total_price' => $qty * $unitPrice,
        ]);
    }

    private function seedSubscriptionPayment(
        string $tenantId,
        string $invoiceId,
        string $provider,
        string $reference,
        string $status,
        int $amount
    ): string {
        $id = (string) Str::uuid();

        DB::table('subscription_payments')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'invoice_id' => $invoiceId,
            'provider' => $provider,
            'reference' => $reference,
            'status' => $status,
            'amount' => $amount,
            'paid_at' => now('UTC')->subMinute(),
            'created_at' => now('UTC')->subMinute(),
        ]);

        return $id;
    }

    private function seedAnomaly(string $tenantId, string $severity, ?\DateTimeInterface $resolvedAt): string
    {
        $id = (string) Str::uuid();

        DB::table('anomalies')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'severity' => $severity,
            'type' => 'billing_mismatch',
            'reference_id' => (string) Str::uuid(),
            'payload_json' => json_encode(['source' => 'tests']),
            'detected_at' => now('UTC')->subHour(),
            'resolved_at' => $resolvedAt,
            'resolved_by' => $resolvedAt ? (string) Str::uuid() : null,
        ]);

        return $id;
    }

    private function seedSupportAudit(string $tenantId, string $actorId, string $eventType, string $reason): void
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
                'request_id' => 'req-'.Str::uuid(),
            ]),
            'created_at' => now('UTC')->subMinute(),
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

    private function seedSale(string $tenantId, string $branchId, float $total): void
    {
        DB::table('sales')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'shift_id' => (string) Str::uuid(),
            'customer_id' => null,
            'subtotal' => $total,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'status' => 'completed',
            'idempotency_key' => 'sale-'.$tenantId.'-'.Str::uuid(),
            'created_by' => null,
            'voided_by' => null,
            'created_at' => now('UTC'),
        ]);
    }

    private function seedAuditLog(string $tenantId, string $userId, string $eventType): void
    {
        DB::table('audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'actor_id' => $userId,
            'event_type' => $eventType,
            'reference_type' => 'user',
            'reference_id' => (string) Str::uuid(),
            'payload_json' => json_encode(['event' => $eventType]),
            'created_at' => now('UTC'),
        ]);
    }
}
