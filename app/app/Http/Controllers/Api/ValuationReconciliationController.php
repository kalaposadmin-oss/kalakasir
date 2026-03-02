<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class ValuationReconciliationController extends Controller
{
    public function __invoke()
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            return response()->json([
                'code' => 'TENANT_NOT_RESOLVED',
                'message' => 'Tenant tidak ditemukan.',
                'details' => [],
            ], 400);
        }

        $qtyDrifts = DB::table('inventory_balances as ib')
            ->leftJoin('inventory_cost_states as cs', function ($join) {
                $join->on('ib.tenant_id', '=', 'cs.tenant_id')
                    ->on('ib.product_id', '=', 'cs.product_id')
                    ->on('ib.location_id', '=', 'cs.location_id');
            })
            ->where('ib.tenant_id', $tenantId)
            ->whereRaw('abs(ib.qty_on_hand - coalesce(cs.qty_on_hand, 0)) > 0.01')
            ->count();

        $zeroCostRows = DB::table('inventory_cost_states')
            ->where('tenant_id', $tenantId)
            ->where('qty_on_hand', '>', 0)
            ->where('average_cost', '<=', 0)
            ->count();

        $saleZeroHpp = DB::table('sale_items as si')
            ->join('sales as s', function ($join) {
                $join->on('si.sale_id', '=', 's.id')
                    ->on('si.tenant_id', '=', 's.tenant_id');
            })
            ->where('si.tenant_id', $tenantId)
            ->where('s.status', 'completed')
            ->where('si.qty', '>', 0)
            ->where('si.hpp_snapshot', '<=', 0)
            ->count();

        $topQtyDrifts = DB::table('inventory_balances as ib')
            ->leftJoin('inventory_cost_states as cs', function ($join) {
                $join->on('ib.tenant_id', '=', 'cs.tenant_id')
                    ->on('ib.product_id', '=', 'cs.product_id')
                    ->on('ib.location_id', '=', 'cs.location_id');
            })
            ->join('products as p', function ($join) {
                $join->on('ib.product_id', '=', 'p.id')
                    ->on('ib.tenant_id', '=', 'p.tenant_id');
            })
            ->where('ib.tenant_id', $tenantId)
            ->whereRaw('abs(ib.qty_on_hand - coalesce(cs.qty_on_hand, 0)) > 0.01')
            ->selectRaw('ib.product_id, p.name as product_name, ib.location_id, ib.qty_on_hand as balance_qty, coalesce(cs.qty_on_hand, 0) as state_qty, abs(ib.qty_on_hand - coalesce(cs.qty_on_hand, 0)) as delta')
            ->orderByDesc('delta')
            ->limit(20)
            ->get();

        return response()->json([
            'summary' => [
                'qty_drift_rows' => (int) $qtyDrifts,
                'zero_cost_rows' => (int) $zeroCostRows,
                'sale_zero_hpp_rows' => (int) $saleZeroHpp,
            ],
            'top_qty_drifts' => $topQtyDrifts,
        ]);
    }
}
