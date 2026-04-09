<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Support\TenantContext;

class SupplierController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $suppliers = Supplier::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'balance']);

        $totalBalance = (float) $suppliers->sum('balance');

        $rows = $suppliers->map(function ($supplier) {
            return [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'balance' => (float) $supplier->balance,
                'status' => (float) $supplier->balance > 0 ? 'active' : 'inactive',
            ];
        });

        return response()->json([
            'suppliers' => $rows,
            'total' => $rows->count(),
            'total_balance' => $totalBalance,
        ]);
    }
}
