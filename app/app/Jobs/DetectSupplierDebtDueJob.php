<?php

namespace App\Jobs;

use App\Services\AnomalyService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DetectSupplierDebtDueJob implements ShouldQueue
{
    use Queueable;

    public function handle(AnomalyService $anomalyService): void
    {
        $today = CarbonImmutable::now('UTC')->startOfDay();
        $targetDates = [
            3 => $today->addDays(3)->toDateString(),
            1 => $today->addDay()->toDateString(),
        ];

        $rows = DB::table('purchases')
            ->join('suppliers', function ($join) {
                $join->on('purchases.supplier_id', '=', 'suppliers.id')
                    ->on('purchases.tenant_id', '=', 'suppliers.tenant_id');
            })
            ->where('purchases.payment_method', 'tempo')
            ->whereNotNull('purchases.due_date')
            ->whereNotIn('purchases.status', ['cancelled', 'rejected', 'draft'])
            ->whereIn('purchases.due_date', array_values($targetDates))
            ->select([
                'purchases.tenant_id',
                'purchases.supplier_id',
                'suppliers.name as supplier_name',
                'purchases.due_date',
            ])
            ->selectRaw('sum(purchases.total) as total_due')
            ->groupBy('purchases.tenant_id', 'purchases.supplier_id', 'suppliers.name', 'purchases.due_date')
            ->get();

        foreach ($rows as $row) {
            $daysToDue = CarbonImmutable::parse($today->toDateString())
                ->diffInDays(CarbonImmutable::parse($row->due_date), false);

            if (! in_array($daysToDue, [1, 3], true)) {
                continue;
            }

            $severity = $daysToDue === 1 ? 'high' : 'med';
            $type = $daysToDue === 1 ? 'supplier_debt_due_h1' : 'supplier_debt_due_h3';

            $anomalyService->raise(
                tenantId: (string) $row->tenant_id,
                severity: $severity,
                type: $type,
                payload: [
                    'supplier_id' => (string) $row->supplier_id,
                    'supplier_name' => (string) $row->supplier_name,
                    'due_date' => (string) $row->due_date,
                    'days_to_due' => $daysToDue,
                    'total_due' => round((float) $row->total_due, 2),
                ],
                referenceId: (string) $row->supplier_id,
            );
        }
    }
}
