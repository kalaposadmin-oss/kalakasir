<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformAuditLogExportRequest;
use App\Http\Requests\Platform\PlatformAuditLogIndexRequest;
use App\Services\Platform\PlatformAuditLogService;
use App\Services\UserRoleResolverService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(PlatformAuditLogIndexRequest $request, PlatformAuditLogService $service)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $filters = $request->validated();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);

        $result = $service->list($filters, $page, $perPage);

        return ApiResponse::paginated([
            'audit_logs' => $result['audit_logs'],
        ], $page, $perPage, $result['total'], [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function export(PlatformAuditLogExportRequest $request, PlatformAuditLogService $service)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        return $service->exportCsv($request->validated());
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
