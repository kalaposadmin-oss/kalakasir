<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformPlanChangeExportRequest;
use App\Http\Requests\Platform\PlatformPlanChangeIndexRequest;
use App\Services\Platform\PlatformPlanChangeService;
use App\Support\ApiResponse;

class PlanChangeController extends Controller
{
    public function index(PlatformPlanChangeIndexRequest $request, PlatformPlanChangeService $service)
    {
        $filters = $request->validated();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);

        $result = $service->list($filters, $page, $perPage);

        return ApiResponse::paginated([
            'plan_changes' => $result['plan_changes'],
        ], $page, $perPage, $result['total'], [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function export(PlatformPlanChangeExportRequest $request, PlatformPlanChangeService $service)
    {
        return $service->exportCsv($request->validated());
    }
}
