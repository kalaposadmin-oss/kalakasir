<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::query()
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
