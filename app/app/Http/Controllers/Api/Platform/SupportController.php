<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformSupportActionIndexRequest;
use App\Http\Requests\Platform\PlatformSupportSearchRequest;
use App\Services\Platform\PlatformSupportService;
use App\Services\UserRoleResolverService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function actions(PlatformSupportActionIndexRequest $request, PlatformSupportService $service)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $filters = $request->validated();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);

        $result = $service->actions($filters, $page, $perPage);

        return ApiResponse::paginated([
            'actions' => $result['actions'],
        ], $page, $perPage, $result['total'], [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function search(PlatformSupportSearchRequest $request, PlatformSupportService $service)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $data = $service->search($request->validated());

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
}
