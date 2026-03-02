<?php

namespace App\Services;

use App\Models\DailySalesSummary;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DailySalesSummaryService
{
    public function applySale(string $tenantId, string $saleId, int $multiplier = 1): void
    {
        $sale = Sale::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $saleId)
            ->first();

        if (! $sale) {
            return;
        }

        $date = $sale->transaction_date
            ?: CarbonImmutable::parse($sale->created_at)->toDateString();

        $cost = (float) DB::table('sale_items')
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->selectRaw('sum(qty * hpp_snapshot) as total')
            ->value('total');

        $sales = (float) $sale->total * $multiplier;
        $tax = (float) $sale->tax * $multiplier;
        $discount = (float) $sale->discount * $multiplier;
        $totalCost = $cost * $multiplier;
        $count = $multiplier > 0 ? 1 : -1;

        $exists = DailySalesSummary::query()
            ->where('tenant_id', $tenantId)
            ->where('date', $date)
            ->exists();

        if (! $exists) {
            DailySalesSummary::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'date' => $date,
                'total_sales' => max($sales, 0),
                'total_tax' => max($tax, 0),
                'total_discount' => max($discount, 0),
                'total_cost' => max($totalCost, 0),
                'transaction_count' => max($count, 0),
                'updated_at' => CarbonImmutable::now('UTC'),
            ]);

            return;
        }

        DailySalesSummary::query()
            ->where('tenant_id', $tenantId)
            ->where('date', $date)
            ->update([
                'total_sales' => DB::raw('total_sales + '.(float) $sales),
                'total_tax' => DB::raw('total_tax + '.(float) $tax),
                'total_discount' => DB::raw('total_discount + '.(float) $discount),
                'total_cost' => DB::raw('total_cost + '.(float) $totalCost),
                'transaction_count' => DB::raw('transaction_count + '.$count),
                'updated_at' => CarbonImmutable::now('UTC'),
            ]);
    }
}
