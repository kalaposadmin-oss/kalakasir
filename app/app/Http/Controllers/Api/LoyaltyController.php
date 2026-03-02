<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoyaltyTier;
use App\Models\Sale;

class LoyaltyController extends Controller
{
    public function index()
    {
        $tiers = LoyaltyTier::query()->orderBy('min_points')->get(['id', 'name', 'min_points']);

        $customers = Customer::query()->get(['id', 'name']);
        $sales = Sale::query()
            ->whereNotNull('customer_id')
            ->where('status', 'completed')
            ->get(['customer_id', 'total']);

        $totals = [];
        $counts = [];
        foreach ($sales as $sale) {
            $totals[$sale->customer_id] = ($totals[$sale->customer_id] ?? 0) + (float) $sale->total;
            $counts[$sale->customer_id] = ($counts[$sale->customer_id] ?? 0) + 1;
        }

        $rows = $customers->map(function ($customer) use ($totals, $counts, $tiers) {
            $totalSpend = $totals[$customer->id] ?? 0;
            $points = (int) floor($totalSpend / 1000);
            $transactions = $counts[$customer->id] ?? 0;

            $tier = $tiers->first(function ($tier) use ($points) {
                return $points >= $tier->min_points;
            });

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'points' => $points,
                'transactions' => $transactions,
                'tier' => $tier?->name ?? 'Silver',
            ];
        });

        return response()->json([
            'tiers' => $tiers,
            'members' => $rows,
        ]);
    }
}
