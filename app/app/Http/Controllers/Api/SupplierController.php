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

        $supplierIds = $suppliers->pluck('id')->filter()->values();
        $productCounts = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->whereIn('supplier_id', $supplierIds)
            ->select('supplier_id')
            ->selectRaw('count(*) as product_count')
            ->groupBy('supplier_id')
            ->pluck('product_count', 'supplier_id');
        $purchaseSummaries = DB::table('purchases')
            ->where('tenant_id', $tenantId)
            ->whereIn('supplier_id', $supplierIds)
            ->select('supplier_id')
            ->selectRaw('count(*) as purchase_count')
            ->selectRaw('coalesce(sum(total), 0) as purchase_total')
            ->selectRaw('max(transaction_date) as last_purchase_date')
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');
        $totalBalance = (float) $suppliers->sum('balance');

        $rows = $suppliers->map(function ($supplier) use ($productCounts, $purchaseSummaries) {
            $purchaseSummary = $purchaseSummaries->get($supplier->id);

            return [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'balance' => (float) $supplier->balance,
                'status' => (float) $supplier->balance > 0 ? 'active' : 'inactive',
                'product_count' => (int) ($productCounts[$supplier->id] ?? 0),
                'purchase_count' => (int) ($purchaseSummary?->purchase_count ?? 0),
                'purchase_total' => (float) ($purchaseSummary?->purchase_total ?? 0),
                'last_purchase_date' => $purchaseSummary?->last_purchase_date,
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
