<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use App\Support\TenantContext;

class InventoryAlertController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'total_alerts' => 0,
                'alerts' => [],
            ], 400);
        }
        $payload = Cache::remember("inventory-alerts:index:{$tenantId}", now('UTC')->addMinute(), function () use ($tenantId) {
            return $this->buildAlerts($tenantId);
        });

        return response()->json($payload);
    }

    private function buildAlerts(string $tenantId)
    {
        $lookbackDays = 30;
        $leadTimeDays = 3;

        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('track_stock', true)
            ->get(['id', 'name', 'sku', 'image_url', 'min_stock']);

        $inventory = InventoryBalance::query()
            ->where('tenant_id', $tenantId)
            ->select('product_id')
            ->selectRaw('sum(qty_on_hand) as qty_on_hand')
            ->groupBy('tenant_id', 'product_id')
            ->get()
            ->keyBy('product_id');

        $purchaseRows = PurchaseItem::query()
            ->join('purchases', function ($join) {
                $join->on('purchase_items.purchase_id', '=', 'purchases.id')
                    ->on('purchase_items.tenant_id', '=', 'purchases.tenant_id');
            })
            ->leftJoin('suppliers', function ($join) {
                $join->on('purchases.supplier_id', '=', 'suppliers.id')
                    ->on('purchases.tenant_id', '=', 'suppliers.tenant_id');
            })
            ->where('purchase_items.tenant_id', $tenantId)
            ->where('purchases.tenant_id', $tenantId)
            ->select([
                'purchase_items.product_id',
                'purchases.created_at as purchased_at',
                'suppliers.name as supplier_name',
            ])
            ->orderByDesc('purchases.created_at')
            ->get();

        $latestPurchaseMap = [];
        foreach ($purchaseRows as $row) {
            if (! isset($latestPurchaseMap[$row->product_id])) {
                $latestPurchaseMap[$row->product_id] = [
                    'supplier_name' => $row->supplier_name,
                    'purchased_at' => $row->purchased_at,
                ];
            }
        }

        $alerts = [];
        $now = CarbonImmutable::now('UTC');

        $salesUsage = DB::table('sale_items')
            ->join('sales', function ($join) {
                $join->on('sale_items.sale_id', '=', 'sales.id')
                    ->on('sale_items.tenant_id', '=', 'sales.tenant_id');
            })
            ->where('sale_items.tenant_id', $tenantId)
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->where('sales.created_at', '>=', $now->subDays($lookbackDays))
            ->select('sale_items.product_id')
            ->selectRaw('sum(sale_items.qty) as qty_sold')
            ->groupBy('sale_items.tenant_id', 'sale_items.product_id')
            ->get()
            ->keyBy('product_id');

        foreach ($products as $product) {
            $minStock = (float) $product->min_stock;
            $qtySold = (float) ($salesUsage[$product->id]->qty_sold ?? 0);
            $avgDailyUsage = $qtySold / $lookbackDays;
            $safetyDays = $avgDailyUsage >= 5 ? 3 : ($avgDailyUsage >= 1 ? 2 : 1);
            $safetyStock = $avgDailyUsage * $safetyDays;
            $rop = ($avgDailyUsage * $leadTimeDays) + $safetyStock;
            $effectiveThreshold = max($minStock, $rop);

            if ($effectiveThreshold <= 0) {
                continue;
            }

            $current = (float) ($inventory[$product->id]->qty_on_hand ?? 0);
            if ($current > $effectiveThreshold) {
                continue;
            }

            $suggested = max(($effectiveThreshold * 2) - $current, max($minStock, 1));
            $purchaseInfo = $latestPurchaseMap[$product->id] ?? null;

            $note = 'Penjualan meningkat minggu ini';
            if ($purchaseInfo && $purchaseInfo['purchased_at']) {
                $daysAgo = $now->diffInDays(CarbonImmutable::parse($purchaseInfo['purchased_at']));
                $note = 'Terakhir pesan ' . $daysAgo . ' hari lalu';
            }

            $alerts[] = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'image_url' => $product->image_url,
                'supplier_name' => $purchaseInfo['supplier_name'] ?? null,
                'stock_now' => $current,
                'min_stock' => $minStock,
                'rop' => round($rop, 2),
                'avg_daily_usage' => round($avgDailyUsage, 2),
                'suggested_qty' => $suggested,
                'note' => $note,
                'selected' => true,
            ];
        }

        usort($alerts, function ($a, $b) {
            return ($a['stock_now'] / max($a['min_stock'], 1)) <=> ($b['stock_now'] / max($b['min_stock'], 1));
        });

        return [
            'total_alerts' => count($alerts),
            'alerts' => $alerts,
        ];
    }
}
