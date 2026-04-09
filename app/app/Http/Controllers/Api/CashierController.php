<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\TenantContext;

class CashierController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id();

        $cashiers = User::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get(['id', 'name']);

        return ApiResponse::success([
            'cashiers' => $cashiers,
        ], [
            'count' => $cashiers->count(),
        ]);
    }
}
