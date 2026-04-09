<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformPaymentIndexRequest;
use App\Services\Platform\PlatformPaymentService;
use App\Support\ApiResponse;

class PaymentController extends Controller
{
    public function index(PlatformPaymentIndexRequest $request, PlatformPaymentService $service)
    {
        $filters = $request->validated();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);

        $result = $service->list($filters, $page, $perPage);

        return ApiResponse::paginated([
            'payments' => $result['payments'],
        ], $page, $perPage, $result['total'], [
            'request_id' => $request->header('X-Request-Id'),
        ]);
    }
}
