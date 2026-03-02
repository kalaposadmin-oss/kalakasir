<?php

namespace App\Jobs;

use App\Services\AnomalyService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DetectTransferStuckJob implements ShouldQueue
{
    use Queueable;

    public function handle(AnomalyService $anomalyService): void
    {
        $limit = CarbonImmutable::now('UTC')->subHours(24);

        $rows = DB::table('stock_transfers')
            ->where('status', 'in_transit')
            ->where('shipped_at', '<=', $limit)
            ->get(['id', 'tenant_id', 'shipped_at']);

        foreach ($rows as $row) {
            $anomalyService->raise($row->tenant_id, 'high', 'transfer_stuck', [
                'transfer_id' => $row->id,
                'shipped_at' => $row->shipped_at,
                'hours_stuck' => CarbonImmutable::parse($row->shipped_at)->diffInHours(CarbonImmutable::now('UTC')),
            ], $row->id);
        }
    }
}
