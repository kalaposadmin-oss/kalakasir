<?php

namespace App\Jobs;

use App\Services\AnomalyService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DetectValuationDriftJob implements ShouldQueue
{
    use Queueable;

    public function handle(AnomalyService $anomalyService): void
    {
        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $this->checkQtyDrift((string) $tenantId, $anomalyService);
            $this->checkZeroCost((string) $tenantId, $anomalyService);
            $this->checkZeroHppSale((string) $tenantId, $anomalyService);
        }
    }

    private function checkQtyDrift(string $tenantId, AnomalyService $anomalyService): void
    {
        $count = DB::table('inventory_balances as ib')
            ->leftJoin('inventory_cost_states as cs', function ($join) {
                $join->on('ib.tenant_id', '=', 'cs.tenant_id')
                    ->on('ib.product_id', '=', 'cs.product_id')
                    ->on('ib.location_id', '=', 'cs.location_id');
            })
            ->where('ib.tenant_id', $tenantId)
            ->whereRaw('abs(ib.qty_on_hand - coalesce(cs.qty_on_hand, 0)) > 0.01')
            ->count();

        if ($count > 0) {
            $anomalyService->raise(
                tenantId: $tenantId,
                severity: 'med',
                type: 'valuation_qty_drift',
                payload: [
                    'count' => (int) $count,
                    'checked_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                ]
            );
        }
    }

    private function checkZeroCost(string $tenantId, AnomalyService $anomalyService): void
    {
        $count = DB::table('inventory_cost_states')
            ->where('tenant_id', $tenantId)
            ->where('qty_on_hand', '>', 0)
            ->where('average_cost', '<=', 0)
            ->count();

        if ($count > 0) {
            $anomalyService->raise(
                tenantId: $tenantId,
                severity: 'high',
                type: 'valuation_zero_cost',
                payload: [
                    'count' => (int) $count,
                    'checked_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                ]
            );
        }
    }

    private function checkZeroHppSale(string $tenantId, AnomalyService $anomalyService): void
    {
        $count = DB::table('sale_items as si')
            ->join('sales as s', function ($join) {
                $join->on('si.sale_id', '=', 's.id')
                    ->on('si.tenant_id', '=', 's.tenant_id');
            })
            ->where('si.tenant_id', $tenantId)
            ->where('s.status', 'completed')
            ->where('si.qty', '>', 0)
            ->where('si.hpp_snapshot', '<=', 0)
            ->count();

        if ($count > 0) {
            $anomalyService->raise(
                tenantId: $tenantId,
                severity: 'high',
                type: 'valuation_sale_hpp_zero',
                payload: [
                    'count' => (int) $count,
                    'checked_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                ]
            );
        }
    }
}
