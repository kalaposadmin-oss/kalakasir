<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformDashboardRequest;
use App\Services\Platform\PlatformDashboardService;
use App\Support\ApiResponse;

class DashboardController extends Controller
{
    public function index(PlatformDashboardRequest $request, PlatformDashboardService $service)
    {
        $data = $service->summary($request->validated());

        return ApiResponse::success($data, [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }
}
