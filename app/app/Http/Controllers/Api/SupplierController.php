<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->where(function ($query) use ($tenantId) {
                return $query->where('tenant_id', $tenantId);
            })],
        ]);

        $name = trim((string) $validated['name']);
        $supplierId = (string) Str::uuid();

        DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $tenantId,
            'name' => $name,
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        return response()->json([
            'supplier' => [
                'id' => $supplierId,
                'name' => $name,
                'balance' => 0,
                'status' => 'inactive',
            ],
        ], 201);
    }
}
