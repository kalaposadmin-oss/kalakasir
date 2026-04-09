<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformAnomalyIndexRequest;
use App\Http\Requests\Platform\PlatformAnomalyResolveRequest;
use App\Services\Platform\PlatformAnomalyService;
use App\Services\UserRoleResolverService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AnomalyController extends Controller
{
    public function index(PlatformAnomalyIndexRequest $request, PlatformAnomalyService $service)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $filters = $request->validated();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);

        $result = $service->list($filters, $page, $perPage);

        return ApiResponse::paginated([
            'anomalies' => $result['anomalies'],
        ], $page, $perPage, $result['total'], [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function resolve(PlatformAnomalyResolveRequest $request, PlatformAnomalyService $service, string $anomalyId)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $idempotencyKey = $this->resolveIdempotencyKey($request);
        if (! $idempotencyKey) {
            return $this->missingIdempotencyResponse($request);
        }

        $data = $service->resolve($anomalyId, $request->validated(), $this->context($request, $idempotencyKey));

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
