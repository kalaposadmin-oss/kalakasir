<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformUserActionRequest;
use App\Http\Requests\Platform\PlatformUserAuditLogRequest;
use App\Http\Requests\Platform\PlatformUserIndexRequest;
use App\Http\Requests\Platform\PlatformUserShowRequest;
use App\Services\Platform\PlatformUserService;
use App\Services\UserRoleResolverService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(PlatformUserIndexRequest $request, PlatformUserService $service)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $filters = $request->validated();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);

        $result = $service->list($filters, $page, $perPage);

        return ApiResponse::paginated([
            'users' => $result['users'],
        ], $page, $perPage, $result['total'], [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function show(PlatformUserShowRequest $request, PlatformUserService $service, string $userId)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $data = $service->detail($userId, $request->validated());

        if (empty($data['user']['name'])) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'User tidak ditemukan.',
                'details' => [],
                'request_id' => $request->header('X-Request-Id'),
            ], 404);
        }

        return ApiResponse::success($data, [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function auditLogs(PlatformUserAuditLogRequest $request, PlatformUserService $service, string $userId)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $filters = $request->validated();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);

        $result = $service->auditLogs($userId, $filters, $page, $perPage);

        return ApiResponse::paginated([
            'audit_logs' => $result['audit_logs'],
        ], $page, $perPage, $result['total'], [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function resetPassword(PlatformUserActionRequest $request, PlatformUserService $service, string $userId)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $idempotencyKey = $this->resolveIdempotencyKey($request);
        if (! $idempotencyKey) {
            return $this->missingIdempotencyResponse($request);
        }

        $data = $service->action($userId, 'user_reset_password', $request->validated(), $this->context($request, $idempotencyKey));

        if (isset($data['error'])) {
            return $this->errorResponse($data, $request);
        }

        return ApiResponse::success($data, [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function forceLogout(PlatformUserActionRequest $request, PlatformUserService $service, string $userId)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $idempotencyKey = $this->resolveIdempotencyKey($request);
        if (! $idempotencyKey) {
            return $this->missingIdempotencyResponse($request);
        }

        $data = $service->action($userId, 'user_force_logout', $request->validated(), $this->context($request, $idempotencyKey));

        if (isset($data['error'])) {
            return $this->errorResponse($data, $request);
        }

        return ApiResponse::success($data, [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function unlock(PlatformUserActionRequest $request, PlatformUserService $service, string $userId)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $idempotencyKey = $this->resolveIdempotencyKey($request);
        if (! $idempotencyKey) {
            return $this->missingIdempotencyResponse($request);
        }

        $data = $service->action($userId, 'user_unlock', $request->validated(), $this->context($request, $idempotencyKey));

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
}
