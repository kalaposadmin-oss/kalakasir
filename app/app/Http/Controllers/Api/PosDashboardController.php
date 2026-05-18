<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PosDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 422);
        }

        $now = CarbonImmutable::now('Asia/Jakarta');
        $today = $now->toDateString();
        $todayStartUtc = $now->startOfDay()->setTimezone('UTC');
        $tomorrowStartUtc = $now->addDay()->startOfDay()->setTimezone('UTC');
        $monthStart = $now->startOfMonth()->toDateString();
        $monthEnd = $now->endOfMonth()->toDateString();
        $monthStartUtc = $now->startOfMonth()->startOfDay()->setTimezone('UTC');
        $nextMonthStartUtc = $now->startOfMonth()->addMonthNoOverflow()->startOfDay()->setTimezone('UTC');
        $topLimit = max(1, min((int) $request->query('top_limit', 3), 10));
        $lowStockLimit = max(1, min((int) $request->query('low_stock_limit', 3), 10));
        $sections = $this->requestedSections($request);

        $salesSummary = $this->salesSummary($tenantId, $today, $todayStartUtc, $tomorrowStartUtc);

        $orders = (int) ($salesSummary?->orders ?? 0);
        $sales = (float) ($salesSummary?->revenue ?? 0);
        $data = [
            'date' => $today,
            'sales_today' => $sales,
            'orders_today' => $orders,
            'average_order_today' => $orders > 0 ? round($sales / $orders, 2) : 0,
        ];

        if (in_array('top_products', $sections, true)) {
            $data['top_products'] = $this->topProducts($tenantId, $monthStart, $monthEnd, $monthStartUtc, $nextMonthStartUtc, $topLimit);
            $data['top_products_period'] = [
                'start_date' => $monthStart,
                'end_date' => $monthEnd,
                'label' => 'Bulan Ini',
            ];
        }

        if (in_array('low_stock', $sections, true)) {
            $data['low_stock_items'] = $this->lowStockItems($tenantId, $lowStockLimit);
        }

        return ApiResponse::success($data, [
            'top_limit' => $topLimit,
            'low_stock_limit' => $lowStockLimit,
            'sections' => $sections,
        ]);
    }

    private function requestedSections(Request $request): array
    {
        $raw = trim((string) $request->query('sections', ''));
        if ($raw === '') {
            return ['summary', 'top_products', 'low_stock'];
        }

        $allowed = ['summary', 'top_products', 'low_stock'];
        $sections = array_values(array_intersect($allowed, array_filter(array_map('trim', explode(',', $raw)))));

        return $sections === [] ? ['summary'] : $sections;
    }

    private function salesSummary(string $tenantId, string $date, CarbonImmutable $startUtc, CarbonImmutable $endUtc): object
    {
        return Cache::remember(
            "pos-dashboard:summary:{$tenantId}:{$date}",
            now('UTC')->addSeconds(30),
            fn () => DB::table('sales')
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->where(function ($query) use ($date, $startUtc, $endUtc) {
                    $query->where('transaction_date', $date)
                        ->orWhere(function ($fallback) use ($startUtc, $endUtc) {
                            $fallback->whereNull('transaction_date')
                                ->where('created_at', '>=', $startUtc)
                                ->where('created_at', '<', $endUtc);
                        });
                })
                ->selectRaw('count(*) as orders')
                ->selectRaw('sum(total - tax) as revenue')
                ->first() ?? (object) ['orders' => 0, 'revenue' => 0]
        );
    }

    private function topProducts(
        string $tenantId,
        string $startDate,
        string $endDate,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        int $limit
    ): array {
        return Cache::remember(
            "pos-dashboard:top-products:{$tenantId}:{$startDate}:{$endDate}:{$limit}",
            now('UTC')->addMinutes(5),
            fn () => $this->buildTopProducts($tenantId, $startDate, $endDate, $startUtc, $endUtc, $limit)
        );
    }

    private function buildTopProducts(
        string $tenantId,
        string $startDate,
        string $endDate,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        int $limit
    ): array
    {
        $salesRows = DB::table('sale_items')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'sale_items.sale_id')
                    ->on('sales.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->join('products', function ($join) {
                $join->on('products.id', '=', 'sale_items.product_id')
                    ->on('products.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->where('sales.tenant_id', $tenantId)
            ->where('sale_items.tenant_id', $tenantId)
            ->where('products.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->where(function ($query) use ($startDate, $endDate, $startUtc, $endUtc) {
                $query->whereBetween('sales.transaction_date', [$startDate, $endDate])
                    ->orWhere(function ($fallback) use ($startUtc, $endUtc) {
                        $fallback->whereNull('sales.transaction_date')
                            ->where('sales.created_at', '>=', $startUtc)
                            ->where('sales.created_at', '<', $endUtc);
                    });
            })
            ->where('products.is_active', true)
            ->where('products.sku', 'not like', 'FEE-%')
            ->select([
                'products.id',
                'products.name',
                'products.image_url',
            ])
            ->selectRaw('sum(sale_items.qty) as qty')
            ->selectRaw('sum((sale_items.qty * sale_items.price) - sale_items.discount) as revenue')
            ->groupBy('products.id', 'products.name', 'products.image_url')
            ->get();

        $refundRows = DB::table('refund_items')
            ->join('refunds', function ($join) {
                $join->on('refunds.id', '=', 'refund_items.refund_id')
                    ->on('refunds.tenant_id', '=', 'refund_items.tenant_id');
            })
            ->join('products', function ($join) {
                $join->on('products.id', '=', 'refund_items.product_id')
                    ->on('products.tenant_id', '=', 'refund_items.tenant_id');
            })
            ->where('refunds.tenant_id', $tenantId)
            ->where('refund_items.tenant_id', $tenantId)
            ->where('products.tenant_id', $tenantId)
            ->where('refunds.status', 'completed')
            ->where(function ($query) use ($startDate, $endDate, $startUtc, $endUtc) {
                $query->whereBetween('refunds.transaction_date', [$startDate, $endDate])
                    ->orWhere(function ($fallback) use ($startUtc, $endUtc) {
                        $fallback->whereNull('refunds.transaction_date')
                            ->where('refunds.created_at', '>=', $startUtc)
                            ->where('refunds.created_at', '<', $endUtc);
                    });
            })
            ->where('products.is_active', true)
            ->where('products.sku', 'not like', 'FEE-%')
            ->select([
                'products.id',
                'products.name',
                'products.image_url',
            ])
            ->selectRaw('sum(refund_items.qty) as qty')
            ->selectRaw('sum(refund_items.gross_amount - refund_items.item_discount_amount - refund_items.transaction_discount_amount) as revenue')
            ->groupBy('products.id', 'products.name', 'products.image_url')
            ->get();

        $merged = [];

        foreach ($salesRows as $row) {
            $merged[$row->id] = [
                'id' => $row->id,
                'name' => $row->name,
                'image_url' => $row->image_url,
                'qty' => (float) $row->qty,
                'revenue' => (float) $row->revenue,
            ];
        }

        foreach ($refundRows as $row) {
            if (! array_key_exists($row->id, $merged)) {
                $merged[$row->id] = [
                    'id' => $row->id,
                    'name' => $row->name,
                    'image_url' => $row->image_url,
                    'qty' => 0.0,
                    'revenue' => 0.0,
                ];
            }

            $merged[$row->id]['qty'] -= (float) $row->qty;
            $merged[$row->id]['revenue'] -= (float) $row->revenue;
        }

        return collect($merged)
            ->filter(fn (array $row): bool => $row['qty'] > 0 || $row['revenue'] > 0)
            ->sortByDesc('revenue')
            ->take($limit)
            ->values()
            ->all();
    }

    private function lowStockItems(string $tenantId, int $limit): array
    {
        return Cache::remember(
            "pos-dashboard:low-stock:{$tenantId}:{$limit}",
            now('UTC')->addMinute(),
            fn () => $this->buildLowStockItems($tenantId, $limit)
        );
    }

    private function buildLowStockItems(string $tenantId, int $limit): array
    {
        $inventorySub = InventoryBalance::query()
            ->where('tenant_id', $tenantId)
            ->select(['tenant_id', 'product_id'])
            ->selectRaw('sum(qty_on_hand) as qty_on_hand')
            ->groupBy('tenant_id', 'product_id');

        return Product::query()
            ->leftJoinSub($inventorySub, 'inventory', function ($join) {
                $join->on('products.id', '=', 'inventory.product_id')
                    ->on('products.tenant_id', '=', 'inventory.tenant_id');
            })
            ->where('products.tenant_id', $tenantId)
            ->where('products.is_active', true)
            ->where('products.track_stock', true)
            ->whereRaw('products.min_stock > 0')
            ->whereRaw('coalesce(inventory.qty_on_hand, 0) <= products.min_stock')
            ->orderByRaw('coalesce(inventory.qty_on_hand, 0) asc')
            ->orderBy('products.min_stock', 'desc')
            ->limit($limit)
            ->get([
                'products.id',
                'products.name',
                'products.sku',
                'products.min_stock',
                DB::raw('coalesce(inventory.qty_on_hand, 0) as qty_on_hand'),
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'sku' => $row->sku,
                'qty_on_hand' => (float) $row->qty_on_hand,
                'min_stock' => (float) $row->min_stock,
            ])
            ->values()
            ->all();
    }
}
