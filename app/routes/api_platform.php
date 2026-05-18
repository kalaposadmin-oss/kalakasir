<?php

use App\Http\Controllers\Api\Platform\AuditLogController as PlatformAuditLogController;
use App\Http\Controllers\Api\Platform\AnomalyController as PlatformAnomalyController;
use App\Http\Controllers\Api\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Api\Platform\InvoiceController as PlatformInvoiceController;
use App\Http\Controllers\Api\Platform\PlanChangeController as PlatformPlanChangeController;
use App\Http\Controllers\Api\Platform\SupportController as PlatformSupportController;
use App\Http\Controllers\Api\Platform\TenantController as PlatformTenantController;
use App\Http\Controllers\Api\Platform\UserController as PlatformUserController;
use App\Http\Controllers\Api\Platform\PaymentController as PlatformPaymentController;
use App\Http\Controllers\Api\Platform\SettingsController as PlatformSettingsController;
use App\Http\Controllers\Api\Platform\SystemHealthController as PlatformSystemHealthController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Services\Platform\PlatformHealthService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$registerPlatformRoutes = static function (string $prefix): void {
    Route::prefix($prefix)->middleware('role:superadmin')->group(function (): void {
        Route::get('health', static function (Request $request, PlatformHealthService $service) {
            $data = $service->summary();

            return ApiResponse::success($data, [
                'request_id' => $request->header('X-Request-Id'),
            ]);
        });
        Route::get('subscription/audit-logs/export', [SubscriptionController::class, 'exportAuditLogsCsvPlatform']);
        Route::get('dashboard', [PlatformDashboardController::class, 'index']);
        Route::get('tenants', [PlatformTenantController::class, 'index']);
        Route::get('tenants/{tenantId}', [PlatformTenantController::class, 'show']);
        Route::post('tenants/{tenantId}/suspend', [PlatformTenantController::class, 'suspend'])->middleware('throttle:platform-danger');
        Route::post('tenants/{tenantId}/unsuspend', [PlatformTenantController::class, 'unsuspend'])->middleware('throttle:platform-danger');
        Route::post('tenants/{tenantId}/impersonate', [PlatformTenantController::class, 'impersonate'])->middleware('throttle:platform-danger');
        Route::post('tenants/{tenantId}/force-logout', [PlatformTenantController::class, 'forceLogout'])->middleware('throttle:platform-danger');
        Route::post('tenants/{tenantId}/reset-owner-password', [PlatformTenantController::class, 'resetOwnerPassword'])->middleware('throttle:platform-danger');
        Route::post('tenants/{tenantId}/unlock', [PlatformTenantController::class, 'unlock'])->middleware('throttle:platform-danger');
        Route::get('users', [PlatformUserController::class, 'index']);
        Route::get('users/{userId}', [PlatformUserController::class, 'show']);
        Route::get('users/{userId}/audit-logs', [PlatformUserController::class, 'auditLogs']);
        Route::post('users/{userId}/reset-password', [PlatformUserController::class, 'resetPassword'])->middleware('throttle:platform-danger');
        Route::post('users/{userId}/force-logout', [PlatformUserController::class, 'forceLogout'])->middleware('throttle:platform-danger');
        Route::post('users/{userId}/unlock', [PlatformUserController::class, 'unlock'])->middleware('throttle:platform-danger');
        Route::get('invoices', [PlatformInvoiceController::class, 'index']);
        Route::get('invoices/{invoiceId}', [PlatformInvoiceController::class, 'show']);
        Route::get('plan-changes', [PlatformPlanChangeController::class, 'index']);
        Route::get('plan-changes/export', [PlatformPlanChangeController::class, 'export']);
        Route::get('payments', [PlatformPaymentController::class, 'index']);
        Route::get('audit-logs', [PlatformAuditLogController::class, 'index']);
        Route::get('audit-logs/export', [PlatformAuditLogController::class, 'export']);
        Route::get('anomalies', [PlatformAnomalyController::class, 'index']);
        Route::post('anomalies/{anomalyId}/resolve', [PlatformAnomalyController::class, 'resolve']);
        Route::get('system-health/events', [PlatformSystemHealthController::class, 'index']);
        Route::get('system-health/summary', [PlatformSystemHealthController::class, 'summary']);
        Route::post('system-health/events/{eventId}/resolve', [PlatformSystemHealthController::class, 'resolve']);
        Route::post('system-health/events/{eventId}/ignore', [PlatformSystemHealthController::class, 'ignore']);
        Route::get('support/actions', [PlatformSupportController::class, 'actions']);
        Route::get('support/search', [PlatformSupportController::class, 'search']);
        Route::get('settings/feature-flags', [PlatformSettingsController::class, 'featureFlags']);
        Route::get('settings/rate-limits', [PlatformSettingsController::class, 'rateLimits']);
    });
};

$registerPlatformRoutes('platform');
$registerPlatformRoutes('v1/platform');
