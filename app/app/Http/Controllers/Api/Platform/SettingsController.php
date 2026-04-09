<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformSettingsFeatureFlagsRequest;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;

class SettingsController extends Controller
{
    public function featureFlags(PlatformSettingsFeatureFlagsRequest $request, PlatformSettingsService $service)
    {
        $data = $service->featureFlags($request->validated());

        return ApiResponse::success($data, [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }

    public function rateLimits(PlatformSettingsService $service)
    {
        $data = $service->rateLimits();

        return ApiResponse::success($data, [
            'request_id' => request()->header('X-Request-Id'),
        ]);
    }
}
