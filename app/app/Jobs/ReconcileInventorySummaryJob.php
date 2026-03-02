<?php

namespace App\Jobs;

use App\Models\InventorySummary;
use App\Services\AnomalyService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ReconcileInventorySummaryJob implements ShouldQueue
{
    use Queueable;

    public function handle(AnomalyService $anomalyService): void
    {
        $rows = InventorySummary::query()->get(['tenant_id', 'location_id', 'product_id', 'current_qty']);

        foreach ($rows as $row) {
            $balance = (float) DB::table('inventory_balances')
                ->where('tenant_id', $row->tenant_id)
                ->where('location_id', $row->location_id)
                ->where('product_id', $row->product_id)
                ->value('qty_on_hand');

            $delta = abs((float) $row->current_qty - $balance);

            if ($delta > 0.01) {
                $anomalyService->raise(
                    tenantId: $row->tenant_id,
                    severity: 'med',
                    type: 'inventory_drift',
                    payload: [
                        'location_id' => $row->location_id,
                        'product_id' => $row->product_id,
                        'summary_qty' => (float) $row->current_qty,
                        'balance_qty' => $balance,
                        'delta' => $delta,
                        'checked_at' => CarbonImmutable::now('UTC')->toIso8601String(),
                    ]
                );
            }
        }
    }
}
