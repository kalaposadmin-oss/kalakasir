<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformInvoiceIndexRequest;
use App\Http\Requests\Platform\PlatformInvoiceShowRequest;
use App\Services\Platform\PlatformInvoiceService;
use App\Services\UserRoleResolverService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(PlatformInvoiceIndexRequest $request, PlatformInvoiceService $service)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $filters = $request->validated();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);

        $result = $service->list($filters, $page, $perPage);

        return ApiResponse::paginated([
            'invoices' => $result['invoices'],
        ], $page, $perPage, $result['total'], [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function show(PlatformInvoiceShowRequest $request, PlatformInvoiceService $service, string $invoiceId)
    {
        if ($response = $this->assertSuperadmin($request)) {
            return $response;
        }

        $data = $service->detail($invoiceId, $request->validated());

        if (! $data['invoice']) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Invoice tidak ditemukan.',
                'details' => [],
                'request_id' => $request->header('X-Request-Id'),
            ], 404);
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
}
