<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformTenantActionRequest;
use App\Http\Requests\Platform\PlatformTenantIndexRequest;
use App\Http\Requests\Platform\PlatformTenantShowRequest;
use App\Models\Tenant;
use App\Services\Platform\PlatformTenantService;
use App\Services\UserRoleResolverService;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index(PlatformTenantIndexRequest $request, PlatformTenantService $service)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $filters = $request->validated();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);

        $result = $service->list($filters, $page, $perPage);

        return ApiResponse::paginated([
            'tenants' => $result['tenants'],
        ], $page, $perPage, $result['total'], [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function show(PlatformTenantShowRequest $request, PlatformTenantService $service, string $tenantId)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $data = $service->detail($tenantId, $request->validated());

        if (empty($data['tenant']['name'])) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
                'request_id' => $request->header('X-Request-Id'),
            ], 404);
        }

        return ApiResponse::success($data, [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function suspend(PlatformTenantActionRequest $request, PlatformTenantService $service, string $tenantId)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $idempotencyKey = $this->resolveIdempotencyKey($request);
        if (! $idempotencyKey) {
            return $this->missingIdempotencyResponse($request);
        }

        $data = $service->action($tenantId, 'tenant_suspended', $request->validated(), $this->context($request, $idempotencyKey));

        if (isset($data['error'])) {
            return $this->errorResponse($data, $request);
        }

        return ApiResponse::success($data, [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function unsuspend(PlatformTenantActionRequest $request, PlatformTenantService $service, string $tenantId)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $idempotencyKey = $this->resolveIdempotencyKey($request);
        if (! $idempotencyKey) {
            return $this->missingIdempotencyResponse($request);
        }

        $data = $service->action($tenantId, 'tenant_unsuspended', $request->validated(), $this->context($request, $idempotencyKey));

        if (isset($data['error'])) {
            return $this->errorResponse($data, $request);
        }

        return ApiResponse::success($data, [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function impersonate(PlatformTenantActionRequest $request, PlatformTenantService $service, string $tenantId)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $idempotencyKey = $this->resolveIdempotencyKey($request);
        if (! $idempotencyKey) {
            return $this->missingIdempotencyResponse($request);
        }

        $data = $service->impersonate($tenantId, $request->validated(), $this->context($request, $idempotencyKey));

        if (isset($data['error'])) {
            return $this->errorResponse($data, $request);
        }

        $this->applyImpersonationSession($request, $tenantId, $data['impersonation_expires_at'] ?? null);

        return ApiResponse::success($data, [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function forceLogout(PlatformTenantActionRequest $request, PlatformTenantService $service, string $tenantId)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $idempotencyKey = $this->resolveIdempotencyKey($request);
        if (! $idempotencyKey) {
            return $this->missingIdempotencyResponse($request);
        }

        $data = $service->action($tenantId, 'tenant_force_logout', $request->validated(), $this->context($request, $idempotencyKey));

        if (isset($data['error'])) {
            return $this->errorResponse($data, $request);
        }

        return ApiResponse::success($data, [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function resetOwnerPassword(PlatformTenantActionRequest $request, PlatformTenantService $service, string $tenantId)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $idempotencyKey = $this->resolveIdempotencyKey($request);
        if (! $idempotencyKey) {
            return $this->missingIdempotencyResponse($request);
        }

        $data = $service->action($tenantId, 'tenant_reset_owner', $request->validated(), $this->context($request, $idempotencyKey));

        if (isset($data['error'])) {
            return $this->errorResponse($data, $request);
        }

        return ApiResponse::success($data, [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function unlock(PlatformTenantActionRequest $request, PlatformTenantService $service, string $tenantId)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $idempotencyKey = $this->resolveIdempotencyKey($request);
        if (! $idempotencyKey) {
            return $this->missingIdempotencyResponse($request);
        }

        $data = $service->action($tenantId, 'tenant_unlock', $request->validated(), $this->context($request, $idempotencyKey));

        if (isset($data['error'])) {
            return $this->errorResponse($data, $request);
        }

        return ApiResponse::success($data, [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    private function assertSuperadmin(Request $request)
    {
        $user = $request->user();
        $tenantId = (string) ($user->tenant_id ?? '');
        $userId = (string) ($user->id ?? '');

        $role = null;
        if ($tenantId !== '' && $userId !== '') {
            $resolver = app(UserRoleResolverService::class);
            $role = $resolver->resolveNormalizedRole($tenantId, $userId);

            if (! $role && filled($user->role ?? null)) {
                $role = $resolver->normalize((string) $user->role);
            }
        }

        if ($role !== 'superadmin') {
            return response()->json([
                'code' => 'FORBIDDEN',
                'message' => 'Akses ditolak untuk role ini.',
                'details' => [],
            ], 403);
        }

        return null;
    }

    private function resolveIdempotencyKey(Request $request): ?string
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if ($key === '') {
            $key = (string) $request->header('X-Idempotency-Key', '');
        }

        return $key !== '' ? $key : null;
    }

    private function missingIdempotencyResponse(Request $request)
    {
        return response()->json([
            'code' => 'VALIDATION_ERROR',
            'message' => 'Data tidak valid.',
            'details' => [
                'Idempotency-Key' => ['Idempotency-Key wajib diisi.'],
            ],
            'request_id' => $request->header('X-Request-Id'),
        ], 422);
    }

    private function errorResponse(array $result, Request $request)
    {
        return response()->json([
            'code' => $result['error']['code'],
            'message' => $result['error']['message'],
            'details' => $result['error']['details'],
            'request_id' => $request->header('X-Request-Id'),
        ], $result['status']);
    }

    private function context(Request $request, string $idempotencyKey): array
    {
        return [
            'actor_id' => $request->user()?->id,
            'request_id' => $request->header('X-Request-Id'),
            'idempotency_key' => $idempotencyKey,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }

    private function applyImpersonationSession(\Illuminate\Http\Request $request, string $tenantId, ?string $expiresAt): void
    {
        if (! $request->hasSession() || ! $expiresAt) {
            return;
        }

        $user = $request->user();
        if (! $user) {
            return;
        }

        $tenantName = Tenant::query()->where('id', $tenantId)->value('name');
        $startedAt = CarbonImmutable::now('UTC')->toIso8601String();
        $reason = (string) ($request->input('reason') ?? '');

        $request->session()->put('current_tenant_id', $tenantId);
        $request->session()->put('impersonation', [
            'tenant_id' => $tenantId,
            'tenant_name' => $tenantName,
            'actor_id' => $user->id,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
            'reason' => $reason,
            'request_id' => $request->header('X-Request-Id'),
        ]);

        if ((string) $user->current_tenant_id !== (string) $tenantId) {
            $user->current_tenant_id = $tenantId;
            $user->save();
        }
    }
}
