<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformSystemHealthIndexRequest;
use App\Http\Requests\Platform\PlatformSystemHealthResolveRequest;
use App\Services\Platform\PlatformSystemHealthService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SystemHealthController extends Controller
{
    public function index(PlatformSystemHealthIndexRequest $request, PlatformSystemHealthService $service)
    {
        $filters = $request->validated();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);
        $result = $service->list($filters, $page, $perPage);

        return ApiResponse::paginated([
            'events' => $result['events'],
        ], $page, $perPage, $result['total'], [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function summary(PlatformSystemHealthIndexRequest $request, PlatformSystemHealthService $service)
    {
        return ApiResponse::success($service->summary($request->validated()), [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function resolve(PlatformSystemHealthResolveRequest $request, PlatformSystemHealthService $service, string $eventId)
    {
        $data = $service->resolve($eventId, $request->validated(), $this->context($request));
        if (isset($data['error'])) {
            return response()->json([
                'code' => $data['error']['code'],
                'message' => $data['error']['message'],
                'details' => $data['error']['details'],
                'request_id' => $request->header('X-Request-Id'),
            ], $data['status']);
        }

        return ApiResponse::success($data, [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function ignore(PlatformSystemHealthResolveRequest $request, PlatformSystemHealthService $service, string $eventId)
    {
        $data = $service->ignore($eventId, $request->validated(), $this->context($request));
        if (isset($data['error'])) {
            return response()->json([
                'code' => $data['error']['code'],
                'message' => $data['error']['message'],
                'details' => $data['error']['details'],
                'request_id' => $request->header('X-Request-Id'),
            ], $data['status']);
        }

        return ApiResponse::success($data, [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    private function context(Request $request): array
    {
        return [
            'actor_id' => $request->user()?->id,
            'request_id' => $request->header('X-Request-Id'),
            'idempotency_key' => $request->header('Idempotency-Key') ?: $request->header('X-Idempotency-Key'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }
}