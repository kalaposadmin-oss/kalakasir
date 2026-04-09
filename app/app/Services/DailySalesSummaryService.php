<?php

namespace App\Services;

use App\Models\DailySalesSummary;
use App\Models\Sale;
use App\Support\Decimal;
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

        // Daily sales summary follows the sale business date. Legacy rows keep a created_at fallback
        // until every historical sale is guaranteed to have transaction_date populated.
        $date = $sale->transaction_date
            ?: CarbonImmutable::parse($sale->created_at)->toDateString();

        $cost = Decimal::money(DB::table('sale_items')
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->selectRaw('sum(qty * hpp_snapshot) as total')
            ->value('total'));

        $sales = Decimal::multiply(Decimal::money($sale->total), (string) $multiplier, Decimal::MONEY_SCALE);
        $tax = Decimal::multiply(Decimal::money($sale->tax), (string) $multiplier, Decimal::MONEY_SCALE);
        $discount = Decimal::multiply(Decimal::money($sale->discount), (string) $multiplier, Decimal::MONEY_SCALE);
        $totalCost = Decimal::multiply($cost, (string) $multiplier, Decimal::MONEY_SCALE);
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
                'total_sales' => Decimal::float(Decimal::max($sales, '0', Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
                'total_tax' => Decimal::float(Decimal::max($tax, '0', Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
                'total_discount' => Decimal::float(Decimal::max($discount, '0', Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
                'total_cost' => Decimal::float(Decimal::max($totalCost, '0', Decimal::MONEY_SCALE), Decimal::MONEY_SCALE),
                'transaction_count' => max($count, 0),
                'updated_at' => CarbonImmutable::now('UTC'),
            ]);

            return;
        }

        DailySalesSummary::query()
            ->where('tenant_id', $tenantId)
            ->where('date', $date)
            ->update([
                'total_sales' => DB::raw('total_sales + '.Decimal::float($sales, Decimal::MONEY_SCALE)),
                'total_tax' => DB::raw('total_tax + '.Decimal::float($tax, Decimal::MONEY_SCALE)),
                'total_discount' => DB::raw('total_discount + '.Decimal::float($discount, Decimal::MONEY_SCALE)),
                'total_cost' => DB::raw('total_cost + '.Decimal::float($totalCost, Decimal::MONEY_SCALE)),
                'transaction_count' => DB::raw('transaction_count + '.$count),
                'updated_at' => CarbonImmutable::now('UTC'),
            ]);
    }
}
