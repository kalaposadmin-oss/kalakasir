<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sale;
use App\Support\TenantContext;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();

        $liteRequested = $request->boolean('lite') || $request->filled('q') || $request->filled('limit');

        if ($liteRequested) {
            $limit = max(1, min((int) $request->query('limit', 100), 250));
            $term = trim((string) $request->query('q', ''));

            $rows = Customer::query()
                ->when($tenantId, fn ($query) => $query->where('customers.tenant_id', $tenantId))
                ->when($term !== '', function ($query) use ($term) {
                    $query->where(function ($nested) use ($term) {
                        $likeTerm = '%'.$term.'%';
                        $nested->where('customers.name', 'like', $likeTerm)
                            ->orWhere('customers.phone', 'like', $likeTerm);
                    });
                })
                ->orderBy('customers.name')
                ->limit($limit)
                ->get([
                    'customers.id',
                    'customers.name',
                ]);

            return response()->json([
                'customers' => $rows,
                'total' => $rows->count(),
                'filters' => [
                    'q' => $term,
                    'limit' => $limit,
                ],
            ]);
        }

        $customers = Customer::query()
            ->when($tenantId, fn ($query) => $query->where('customers.tenant_id', $tenantId))
            ->leftJoin('sales', function ($join) {
                $join->on('customers.id', '=', 'sales.customer_id')
                    ->on('customers.tenant_id', '=', 'sales.tenant_id')
                    ->where('sales.status', '=', 'completed');
            })
            ->select([
                'customers.id',
                'customers.name',
                'customers.phone',
                'customers.created_at',
            ])
            ->selectRaw('coalesce(sum(sales.total), 0) as total_spent')
            ->selectRaw('count(sales.id) as order_count')
            ->groupBy('customers.id', 'customers.name', 'customers.phone', 'customers.created_at')
            ->orderBy('customers.created_at', 'desc')
            ->get();

        $customerIds = $customers->pluck('id')->all();
        $recentSales = [];

        if ($customerIds) {
            $sales = Sale::query()
                ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                ->whereIn('customer_id', $customerIds)
                ->where('status', 'completed')
                ->orderByDesc('created_at')
                ->get(['id', 'customer_id', 'total', 'created_at']);

            foreach ($sales as $sale) {
                $list = $recentSales[$sale->customer_id] ?? [];
                if (count($list) < 2) {
                    $list[] = $sale;
                }
                $recentSales[$sale->customer_id] = $list;
            }
        }

        $rows = $customers->map(function ($customer) use ($recentSales) {
            $total = (float) $customer->total_spent;
            $tier = $total >= 2000000 ? 'gold' : ($total >= 1000000 ? 'silver' : 'regular');
            $points = (int) floor($total / 2000);

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'total_spent' => $total,
                'order_count' => (int) $customer->order_count,
                'created_at' => $customer->created_at,
                'tier' => $tier,
                'points' => $points,
                'recent_sales' => collect($recentSales[$customer->id] ?? [])->map(function ($sale) {
                    return [
                        'id' => $sale->id,
                        'total' => (float) $sale->total,
                        'created_at' => $sale->created_at,
                    ];
                })->values(),
            ];
        });

        return response()->json([
            'customers' => $rows,
            'total' => $rows->count(),
        ]);
    }
}
