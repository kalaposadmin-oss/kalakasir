<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anomaly;
use App\Models\InventoryBalance;
use App\Models\InventoryCostState;
use App\Models\InventoryLayer;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Services\InventoryValuationService;
use App\Support\TenantContext;
use App\Services\SubscriptionService;
use App\Support\FeatureGateConfig;
use App\Support\ApiResponse;
use App\Support\SaleChannel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InventoryReportController extends Controller
{
    public function summary(Request $request)
    {
        if (! $this->canUseFeature('reports.inventory_view')) {
            abort(403);
        }

        $tenantId = TenantContext::id();
        $validated = $request->validate([
            'preset' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'location_id' => ['nullable', 'uuid'],
            'audit_category' => ['nullable', 'string', 'in:inventory,purchase,sales,anomaly'],
            'movement_page' => ['nullable', 'integer', 'min:1'],
            'movement_per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        if (! $tenantId) {
            return response()->json([
                'sku_count' => 0,
                'low_stock_count' => 0,
                'out_of_stock_count' => 0,
                'total_value' => 0,
                'movement_7d' => [
                    'in' => 0,
                    'out' => 0,
                ],
                'movement_range' => [
                    'in' => 0,
                    'out' => 0,
                    'net' => 0,
                    'by_type' => [],
                ],
                'recent_movements' => [],
                'audit_trail' => [],
                'range' => null,
                'low_stock_items' => [],
            ], 400);
        }

        [$startUtc, $endExclusiveUtc, $rangeMeta] = $this->resolveRange(
            $validated['preset'] ?? 'last7',
            $validated['start_date'] ?? $validated['from'] ?? null,
            $validated['end_date'] ?? $validated['to'] ?? null
        );

        $locationId = $validated['location_id'] ?? null;
        $movementPage = max(1, (int) ($validated['movement_page'] ?? $request->query('page', 1)));
        $movementPerPage = max(1, min((int) ($validated['movement_per_page'] ?? 10), 50));
        $summaryCacheKey = sprintf(
            'reports:inventory:summary:%s:%s:%s:%s:%s:%d:%d',
            $tenantId,
            $rangeMeta['start_date'] ?? 'na',
            $rangeMeta['end_date'] ?? 'na',
            $locationId ?: 'all',
            $validated['audit_category'] ?? 'inventory',
            $movementPage,
            $movementPerPage
        );
        if (! $request->boolean('fresh') && is_array($cached = Cache::get($summaryCacheKey))) {
            return response()->json($cached);
        }

        $inventorySub = InventoryBalance::query()
            ->where('tenant_id', $tenantId)
            ->when($locationId, fn ($query) => $query->where('location_id', $locationId))
            ->select(['tenant_id', 'product_id'])
            ->selectRaw('sum(qty_on_hand) as qty_on_hand')
            ->groupBy('tenant_id', 'product_id');

        $skuCountQuery = Product::query()
            ->leftJoinSub($inventorySub, 'inventory', function ($join) {
                $join->on('products.id', '=', 'inventory.product_id')
                    ->on('products.tenant_id', '=', 'inventory.tenant_id');
            })
            ->where('products.tenant_id', $tenantId)
            ->where('products.is_active', true)
            ->where('products.track_stock', true);

        if ($locationId) {
            $skuCountQuery->whereNotNull('inventory.product_id');
        }

        $skuCount = $skuCountQuery->count();

        $lowStockCount = Product::query()
            ->leftJoinSub($inventorySub, 'inventory', function ($join) {
                $join->on('products.id', '=', 'inventory.product_id')
                    ->on('products.tenant_id', '=', 'inventory.tenant_id');
            })
            ->where('products.tenant_id', $tenantId)
            ->where('products.is_active', true)
            ->where('products.track_stock', true)
            ->whereRaw('coalesce(inventory.qty_on_hand, 0) <= products.min_stock')
            ->count();

        $outOfStockCount = Product::query()
            ->leftJoinSub($inventorySub, 'inventory', function ($join) {
                $join->on('products.id', '=', 'inventory.product_id')
                    ->on('products.tenant_id', '=', 'inventory.tenant_id');
            })
            ->where('products.tenant_id', $tenantId)
            ->where('products.is_active', true)
            ->where('products.track_stock', true)
            ->whereRaw('coalesce(inventory.qty_on_hand, 0) <= 0')
            ->count();

        $valuationMethod = app(InventoryValuationService::class)->methodForTenant($tenantId);

        if ($valuationMethod === 'fifo') {
            $totalValueQuery = InventoryLayer::query()
                ->where('tenant_id', $tenantId)
                ->where('qty_remaining', '>', 0);

            if ($locationId) {
                $totalValueQuery->where('location_id', $locationId);
            }

            $totalValue = $totalValueQuery
                ->selectRaw('sum(qty_remaining * cost_per_unit) as total_value')
                ->value('total_value') ?? 0;
        } else {
            $totalValueQuery = InventoryCostState::query()
                ->where('tenant_id', $tenantId);

            if ($locationId) {
                $totalValueQuery->where('location_id', $locationId);
            }

            $totalValue = $totalValueQuery
                ->selectRaw('sum(qty_on_hand * average_cost) as total_value')
                ->value('total_value') ?? 0;
        }

        $since = CarbonImmutable::now('UTC')->subDays(7);
        $movementQuery = StockLedger::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since);

        if ($locationId) {
            $movementQuery->where('location_id', $locationId);
        }

        $movement = $movementQuery
            ->selectRaw('sum(qty_in) as in_qty, sum(qty_out) as out_qty')
            ->first();

        $movementRangeQuery = StockLedger::query()
            ->where('stock_ledger.tenant_id', $tenantId)
            ->where('stock_ledger.created_at', '>=', $startUtc)
            ->where('stock_ledger.created_at', '<', $endExclusiveUtc);

        if ($locationId) {
            $movementRangeQuery->where('stock_ledger.location_id', $locationId);
        }

        $movementRange = (clone $movementRangeQuery)
            ->selectRaw('sum(qty_in) as in_qty, sum(qty_out) as out_qty')
            ->first();

        $movementByType = (clone $movementRangeQuery)
            ->leftJoin('sales as movement_sales', function ($join) {
                $join->on('stock_ledger.reference_id', '=', 'movement_sales.id')
                    ->on('stock_ledger.tenant_id', '=', 'movement_sales.tenant_id')
                    ->whereIn('stock_ledger.reference_type', ['sale', 'sale_recipe_input']);
            })
            ->select('stock_ledger.reference_type')
            ->selectRaw("CASE WHEN stock_ledger.reference_type = 'online_order' THEN 'online' WHEN stock_ledger.reference_type IN ('sale', 'sale_recipe_input') AND movement_sales.idempotency_key LIKE 'online-order:%' THEN 'online' WHEN stock_ledger.reference_type IN ('sale', 'sale_recipe_input') THEN 'offline' ELSE NULL END as sales_channel")
            ->selectRaw('sum(stock_ledger.qty_in) as qty_in')
            ->selectRaw('sum(stock_ledger.qty_out) as qty_out')
            ->groupBy('stock_ledger.reference_type', 'sales_channel')
            ->orderByRaw('(sum(qty_in) + sum(qty_out)) desc')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                $saleChannel = in_array((string) $row->reference_type, ['sale', 'sale_recipe_input', 'online_order'], true)
                    ? SaleChannel::fromIdempotencyKey($row->sales_channel === 'online' ? 'online-order:summary' : null)
                    : null;

                return [
                    'reference_type' => $row->reference_type,
                    'sales_channel' => $saleChannel['channel'] ?? null,
                    'sales_channel_label' => $saleChannel['label'] ?? null,
                    'label' => $saleChannel['stock_label'] ?? $this->movementTypeLabel((string) $row->reference_type),
                    'qty_in' => (float) $row->qty_in,
                    'qty_out' => (float) $row->qty_out,
                    'net' => (float) $row->qty_in - (float) $row->qty_out,
                ];
            })
            ->values();

        $recentMovementsQuery = StockLedger::query()
            ->join('products', function ($join) {
                $join->on('stock_ledger.product_id', '=', 'products.id')
                    ->on('stock_ledger.tenant_id', '=', 'products.tenant_id');
            })
            ->join('locations', function ($join) {
                $join->on('stock_ledger.location_id', '=', 'locations.id')
                    ->on('stock_ledger.tenant_id', '=', 'locations.tenant_id');
            })
            ->leftJoin('sales as recent_sales', function ($join) {
                $join->on('stock_ledger.reference_id', '=', 'recent_sales.id')
                    ->on('stock_ledger.tenant_id', '=', 'recent_sales.tenant_id')
                    ->whereIn('stock_ledger.reference_type', ['sale', 'sale_recipe_input']);
            })
            ->where('stock_ledger.tenant_id', $tenantId)
            ->where('stock_ledger.created_at', '>=', $startUtc)
            ->where('stock_ledger.created_at', '<', $endExclusiveUtc);

        if ($locationId) {
            $recentMovementsQuery->where('stock_ledger.location_id', $locationId);
        }

        $recentMovementsTotal = (clone $recentMovementsQuery)->count();
        $recentMovements = $recentMovementsQuery
            ->orderByDesc('stock_ledger.created_at')
            ->forPage($movementPage, $movementPerPage)
            ->get([
                'stock_ledger.reference_type',
                'stock_ledger.reference_id',
                'stock_ledger.qty_in',
                'stock_ledger.qty_out',
                'stock_ledger.balance_after',
                'stock_ledger.created_at',
                'recent_sales.idempotency_key as sale_idempotency_key',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.unit as product_unit',
                'locations.name as location_name',
            ])
            ->map(function ($row) {
                $saleChannel = in_array((string) $row->reference_type, ['sale', 'sale_recipe_input', 'online_order'], true)
                    ? SaleChannel::fromIdempotencyKey((string) $row->reference_type === 'online_order' ? 'online-order:legacy' : $row->sale_idempotency_key)
                    : null;

                return [
                    'reference_type' => $row->reference_type,
                    'sales_channel' => $saleChannel['channel'] ?? null,
                    'sales_channel_label' => $saleChannel['label'] ?? null,
                    'label' => $saleChannel['stock_label'] ?? $this->movementTypeLabel((string) $row->reference_type),
                    'reference_id' => $row->reference_id,
                    'qty_in' => (float) $row->qty_in,
                    'qty_out' => (float) $row->qty_out,
                    'balance_after' => (float) $row->balance_after,
                    'created_at' => $row->created_at,
                    'product_name' => $row->product_name,
                    'product_sku' => $row->product_sku,
                    'unit' => $row->product_unit,
                    'location_name' => $row->location_name,
                ];
            })
            ->values();

        $auditCategory = $validated['audit_category'] ?? null;
        $auditTrail = $this->buildOperationalAuditTrail($tenantId, $startUtc, $endExclusiveUtc, $auditCategory);

        $lowStockItems = Product::query()
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
            ->limit(5)
            ->get([
                'products.id',
                'products.name',
                'products.sku',
                'products.unit',
                'products.min_stock',
                DB::raw('coalesce(inventory.qty_on_hand, 0) as qty_on_hand'),
            ])
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'sku' => $row->sku,
                    'unit' => $row->unit,
                    'qty_on_hand' => (float) $row->qty_on_hand,
                    'min_stock' => (float) $row->min_stock,
                ];
            })
            ->values();

        $payload = [
            'sku_count' => (int) $skuCount,
            'low_stock_count' => (int) $lowStockCount,
            'out_of_stock_count' => (int) $outOfStockCount,
            'total_value' => (float) $totalValue,
            'movement_7d' => [
                'in' => (float) ($movement->in_qty ?? 0),
                'out' => (float) ($movement->out_qty ?? 0),
            ],
            'movement_range' => [
                'in' => (float) ($movementRange->in_qty ?? 0),
                'out' => (float) ($movementRange->out_qty ?? 0),
                'net' => (float) (($movementRange->in_qty ?? 0) - ($movementRange->out_qty ?? 0)),
                'by_type' => $movementByType,
            ],
            'recent_movements' => $recentMovements,
            'recent_movements_pagination' => [
                'current_page' => $movementPage,
                'per_page' => $movementPerPage,
                'total' => $recentMovementsTotal,
                'last_page' => max(1, (int) ceil($recentMovementsTotal / $movementPerPage)),
                'has_more' => $movementPage * $movementPerPage < $recentMovementsTotal,
            ],
            'audit_trail' => $auditTrail,
            'audit_category' => $auditCategory,
            'audit_categories' => $this->availableAuditCategories($auditCategory),
            'location_id' => $locationId,
            'range' => $rangeMeta,
            'low_stock_items' => $lowStockItems,
        ];

        Cache::put($summaryCacheKey, $payload, now('UTC')->addSeconds(60));

        return response()->json($payload);
    }

    public function exportCsv(Request $request)
    {
        if (! $this->canUseFeature('reports.export_csv')) {
            abort(403);
        }

        $summary = $this->summary($request)->getData(true);
        $rows = [
            ['SKU', $summary['sku_count'] ?? 0],
            ['Stok Rendah', $summary['low_stock_count'] ?? 0],
            ['Stok Habis', $summary['out_of_stock_count'] ?? 0],
            ['Nilai Stok (Est.)', $summary['total_value'] ?? 0],
            ['Masuk 7 Hari', $summary['movement_7d']['in'] ?? 0],
            ['Keluar 7 Hari', $summary['movement_7d']['out'] ?? 0],
        ];

        $filename = 'inventory-summary-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows, $summary) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Metric', 'Value']);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            if (! empty($summary['low_stock_items'])) {
                fputcsv($handle, []);
                fputcsv($handle, ['Top Stok Rendah']);
                fputcsv($handle, ['Nama Produk', 'SKU', 'Stok', 'Min Stok']);
                foreach ($summary['low_stock_items'] as $item) {
                    fputcsv($handle, [
                        $item['name'] ?? '-',
                        $item['sku'] ?? '-',
                        $item['qty_on_hand'] ?? 0,
                        $item['min_stock'] ?? 0,
                    ]);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request)
    {
        if (! $this->canUseFeature('reports.download_pdf')) {
            abort(403);
        }

        $summary = $this->summary($request)->getData(true);
        $lines = [
            'Laporan Persediaan Ringkas',
            'SKU: ' . ($summary['sku_count'] ?? 0),
            'Stok Rendah: ' . ($summary['low_stock_count'] ?? 0),
            'Stok Habis: ' . ($summary['out_of_stock_count'] ?? 0),
            'Nilai Stok (Est.): ' . ($summary['total_value'] ?? 0),
            'Masuk 7 Hari: ' . ($summary['movement_7d']['in'] ?? 0),
            'Keluar 7 Hari: ' . ($summary['movement_7d']['out'] ?? 0),
        ];

        if (! empty($summary['low_stock_items'])) {
            $lines[] = '';
            $lines[] = 'Top Stok Rendah:';
            foreach ($summary['low_stock_items'] as $item) {
                $lines[] = sprintf(
                    '- %s (%s): %s / min %s',
                    $item['name'] ?? '-',
                    $item['sku'] ?? '-',
                    $item['qty_on_hand'] ?? 0,
                    $item['min_stock'] ?? 0
                );
            }
        }

        $pdf = $this->buildSimplePdf($lines);
        $filename = 'inventory-summary-' . now()->format('Ymd-His') . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportStockCardCsv(Request $request, string $productId)
    {
        if (! $this->canUseFeature('reports.inventory_stock_card') || ! $this->canUseFeature('reports.export_csv')) {
            abort(403);
        }

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 422);
        }

        $product = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $productId)
            ->first();

        if (! $product) {
            return response()->json([
                'message' => 'Produk tidak ditemukan.',
            ], 404);
        }

        $locationId = $request->query('location_id');
        $location = null;
        if ($locationId) {
            $location = Location::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $locationId)
                ->first();
        }

        if (! $location) {
            $location = Location::query()
                ->where('tenant_id', $tenantId)
                ->where('is_default', true)
                ->first();
        }

        if (! $location) {
            $location = Location::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->first();
        }

        if (! $location) {
            return response()->json([
                'message' => 'Lokasi stok tidak ditemukan.',
            ], 422);
        }

        $from = $request->query('from');
        $to = $request->query('to');

        if ($from && $to) {
            $start = CarbonImmutable::parse($from, 'UTC')->startOfDay();
            $endExclusive = CarbonImmutable::parse($to, 'UTC')->addDay()->startOfDay();
        } else {
            $endExclusive = CarbonImmutable::now('UTC')->addDay()->startOfDay();
            $start = $endExclusive->subDays(30);
        }

        $filename = 'stock-card-' . $product->sku . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($tenantId, $product, $location, $start, $endExclusive) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Tanggal', 'Tipe', 'Qty Masuk', 'Qty Keluar', 'Saldo', 'Unit Cost', 'Total Cost']);

            StockLedger::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $product->id)
                ->where('location_id', $location->id)
                ->where('created_at', '>=', $start)
                ->where('created_at', '<', $endExclusive)
                ->orderBy('created_at')
                ->chunk(500, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            optional($row->created_at)->format('Y-m-d H:i:s'),
                            $row->reference_type,
                            $row->qty_in,
                            $row->qty_out,
                            $row->balance_after,
                            $row->unit_cost ?? 0,
                            $row->total_cost ?? 0,
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function lowStock(Request $request)
    {
        if (! $this->canUseFeature('reports.inventory_view')) {
            abort(403);
        }

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'items' => [],
            ], 400);
        }

        $limit = (int) $request->query('limit', 10);
        if ($limit < 1) {
            $limit = 10;
        }

        $cacheKey = sprintf('reports:inventory:low-stock:%s:%d', $tenantId, $limit);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return ApiResponse::success([
                'items' => $cached,
            ], [
                'count' => count($cached),
                'limit' => $limit,
            ]);
        }

        $inventorySub = InventoryBalance::query()
            ->where('tenant_id', $tenantId)
            ->select(['tenant_id', 'product_id'])
            ->selectRaw('sum(qty_on_hand) as qty_on_hand')
            ->groupBy('tenant_id', 'product_id');

        $items = Product::query()
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
            ]);

        $productIds = $items->pluck('id')->all();
        $supplierMap = $this->resolveLatestSupplierMap($tenantId, $productIds);
        $supplierNames = Supplier::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_values($supplierMap))
            ->get(['id', 'name'])
            ->pluck('name', 'id')
            ->all();

        $payload = $items->map(function ($row) use ($supplierMap) {
            $qtyOnHand = (float) $row->qty_on_hand;
            $minStock = (float) $row->min_stock;
            $suggested = max($minStock - $qtyOnHand, 0);
            $supplierId = $supplierMap[$row->id] ?? null;
            return [
                'product_id' => $row->id,
                'name' => $row->name,
                'sku' => $row->sku,
                'qty_on_hand' => $qtyOnHand,
                'min_stock' => $minStock,
                'suggested_qty' => $suggested,
                'last_supplier_id' => $supplierId,
                'last_supplier_name' => $supplierId ? ($supplierNames[$supplierId] ?? null) : null,
            ];
        })->values();

        Cache::put($cacheKey, $payload->all(), now()->addSeconds(60));

        return ApiResponse::success([
            'items' => $payload,
        ], [
            'count' => $payload->count(),
            'limit' => $limit,
        ]);
    }

    private function resolveLatestSupplierMap(string $tenantId, array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $rows = DB::table('purchase_items')
            ->join('purchases', function ($join) {
                $join->on('purchase_items.purchase_id', '=', 'purchases.id')
                    ->on('purchase_items.tenant_id', '=', 'purchases.tenant_id');
            })
            ->where('purchase_items.tenant_id', $tenantId)
            ->whereIn('purchase_items.product_id', $productIds)
            ->orderByDesc('purchases.created_at')
            ->get([
                'purchase_items.product_id',
                'purchases.supplier_id',
                'purchases.created_at',
            ]);

        $map = [];
        foreach ($rows as $row) {
            if (! isset($map[$row->product_id])) {
                $map[$row->product_id] = $row->supplier_id;
            }
        }

        return $map;
    }

    private function resolveRange(string $preset, ?string $from, ?string $to): array
    {
        $timezone = 'Asia/Jakarta';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        [$startLocal, $endLocal, $label] = match ($preset) {
            'today' => [$today, $today, 'Hari Ini'],
            'yesterday' => [$today->subDay(), $today->subDay(), 'Kemarin'],
            'last30', '30days' => [$today->subDays(29), $today, '30 Hari Terakhir'],
            'thisMonth' => [$today->startOfMonth(), $today->endOfMonth()->startOfDay(), 'Bulan Ini'],
            'lastMonth' => [
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth()->startOfDay(),
                'Bulan Lalu',
            ],
            'custom' => $this->resolveCustomRange($from, $to, $today),
            'last7', '7days' => [$today->subDays(6), $today, '7 Hari Terakhir'],
            default => [$today->subDays(6), $today, '7 Hari Terakhir'],
        };

        $days = $startLocal->diffInDays($endLocal) + 1;
        $limited = false;
        if ($days > 90) {
            $endLocal = $startLocal->addDays(89);
            $limited = true;
        }

        return [
            $startLocal->setTimezone('UTC'),
            $endLocal->addDay()->startOfDay()->setTimezone('UTC'),
            [
                'preset' => $preset,
                'start_date' => $startLocal->toDateString(),
                'end_date' => $endLocal->toDateString(),
                'label' => $label,
                'timezone' => $timezone,
                'limited' => $limited,
                'limit_days' => 90,
            ],
        ];
    }

    private function resolveCustomRange(?string $from, ?string $to, CarbonImmutable $today): array
    {
        $timezone = 'Asia/Jakarta';
        $fromLocal = CarbonImmutable::parse($from ?? $today->toDateString(), $timezone)->startOfDay();
        $toLocal = CarbonImmutable::parse($to ?? $from ?? $today->toDateString(), $timezone)->startOfDay();

        if ($toLocal < $fromLocal) {
            [$fromLocal, $toLocal] = [$toLocal, $fromLocal];
        }

        return [$fromLocal, $toLocal, 'Periode Kustom'];
    }

    private function movementTypeLabel(string $type): string
    {
        return match (strtolower($type)) {
            'purchase', 'purchase_receive' => 'Pembelian',
            'online_order' => 'Penjualan Online',
            'sale', 'sales' => 'Penjualan',
            'stock_adjustment' => 'Penyesuaian',
            'stock_opname' => 'Stock Opname',
            'stock_transfer_in' => 'Mutasi Masuk',
            'stock_transfer_out' => 'Mutasi Keluar',
            'opening_balance' => 'Stok Awal',
            default => $type !== '' ? $type : 'Pergerakan',
        };
    }

    private function buildOperationalAuditTrail(
        string $tenantId,
        CarbonImmutable $startUtc,
        CarbonImmutable $endExclusiveUtc,
        ?string $category = null,
        int $limit = 12
    )
    {
        $events = collect()
            ->concat($this->stockAdjustmentAuditEvents($tenantId, $startUtc, $endExclusiveUtc))
            ->concat($this->purchaseAuditEvents($tenantId, $startUtc, $endExclusiveUtc))
            ->concat($this->stockTransferAuditEvents($tenantId, $startUtc, $endExclusiveUtc))
            ->concat($this->saleVoidAuditEvents($tenantId, $startUtc, $endExclusiveUtc))
            ->concat($this->refundAuditEvents($tenantId, $startUtc, $endExclusiveUtc))
            ->concat($this->anomalyAuditEvents($tenantId, $startUtc, $endExclusiveUtc));

        if ($category) {
            $events = $events->where('category', $category);
        }

        return $events
            ->sortByDesc(fn (array $event) => (string) ($event['happened_at'] ?? ''))
            ->take($limit)
            ->values();
    }

    private function availableAuditCategories(?string $selected = null): array
    {
        return [
            ['value' => '', 'label' => 'Semua', 'selected' => $selected === null || $selected === ''],
            ['value' => 'inventory', 'label' => 'Inventory', 'selected' => $selected === 'inventory'],
            ['value' => 'purchase', 'label' => 'Purchase', 'selected' => $selected === 'purchase'],
            ['value' => 'sales', 'label' => 'Sales', 'selected' => $selected === 'sales'],
            ['value' => 'anomaly', 'label' => 'Anomaly', 'selected' => $selected === 'anomaly'],
        ];
    }

    private function stockAdjustmentAuditEvents(string $tenantId, CarbonImmutable $startUtc, CarbonImmutable $endExclusiveUtc)
    {
        $rows = DB::table('stock_adjustments as sa')
            ->leftJoin('products as p', function ($join) {
                $join->on('sa.product_id', '=', 'p.id')
                    ->on('sa.tenant_id', '=', 'p.tenant_id');
            })
            ->leftJoin('locations as l', function ($join) {
                $join->on('sa.location_id', '=', 'l.id')
                    ->on('sa.tenant_id', '=', 'l.tenant_id');
            })
            ->leftJoin('users as created_users', function ($join) {
                $join->on('sa.created_by', '=', 'created_users.id')
                    ->on('sa.tenant_id', '=', 'created_users.tenant_id');
            })
            ->leftJoin('users as approved_users', function ($join) {
                $join->on('sa.approved_by', '=', 'approved_users.id')
                    ->on('sa.tenant_id', '=', 'approved_users.tenant_id');
            })
            ->leftJoin('users as cancelled_users', function ($join) {
                $join->on('sa.cancelled_by', '=', 'cancelled_users.id')
                    ->on('sa.tenant_id', '=', 'cancelled_users.tenant_id');
            })
            ->where('sa.tenant_id', $tenantId)
            ->where(function ($query) use ($startUtc, $endExclusiveUtc) {
                $query->whereBetween('sa.created_at', [$startUtc, $endExclusiveUtc])
                    ->orWhereBetween('sa.approved_at', [$startUtc, $endExclusiveUtc])
                    ->orWhereBetween('sa.posted_at', [$startUtc, $endExclusiveUtc])
                    ->orWhereBetween('sa.cancelled_at', [$startUtc, $endExclusiveUtc]);
            })
            ->orderByDesc('sa.created_at')
            ->limit(20)
            ->get([
                'sa.id',
                'sa.reference_no',
                'sa.reason',
                'sa.reason_code',
                'sa.status',
                'sa.notes',
                'sa.qty',
                'sa.type',
                'sa.created_at',
                'sa.approved_at',
                'sa.posted_at',
                'sa.cancelled_at',
                'created_users.name as created_by_name',
                'approved_users.name as approved_by_name',
                'cancelled_users.name as cancelled_by_name',
                'l.name as location_name',
                'p.name as product_name',
            ]);

        $itemSummaries = $this->stockAdjustmentItemSummaryMap($tenantId, $rows->pluck('id')->all());

        return $rows->flatMap(function ($row) use ($startUtc, $endExclusiveUtc, $itemSummaries) {
            $reference = $row->reference_no ?: substr((string) $row->id, 0, 8);
            $summary = $itemSummaries[$row->id] ?? $this->composeAuditSummary([
                $row->product_name ?: null,
                $this->formatSignedQty((float) ($row->qty ?? 0), (string) ($row->type ?? ''), 'Selisih'),
                $row->location_name ? 'Lokasi '.$row->location_name : null,
                $row->reason ?: $row->reason_code,
            ]);

            return array_values(array_filter([
                $this->normalizeAuditEvent([
                    'id' => 'stock_adjustment:created:'.$row->id,
                    'category' => 'inventory',
                    'severity' => 'info',
                    'event_type' => 'stock_adjustment_created',
                    'title' => 'Adjustment stok dibuat',
                    'summary' => $summary,
                    'actor_name' => $row->created_by_name,
                    'entity_type' => 'stock_adjustment',
                    'entity_id' => $row->id,
                    'entity_label' => 'Adjustment '.$reference,
                    'reference_id' => $row->id,
                    'reference_type' => 'stock_adjustment',
                    'happened_at' => $row->created_at,
                    'status' => $row->status,
                ], $startUtc, $endExclusiveUtc),
                $this->normalizeAuditEvent([
                    'id' => 'stock_adjustment:approved:'.$row->id,
                    'category' => 'inventory',
                    'severity' => 'info',
                    'event_type' => 'stock_adjustment_approved',
                    'title' => 'Adjustment stok disetujui',
                    'summary' => $summary,
                    'actor_name' => $row->approved_by_name,
                    'entity_type' => 'stock_adjustment',
                    'entity_id' => $row->id,
                    'entity_label' => 'Adjustment '.$reference,
                    'reference_id' => $row->id,
                    'reference_type' => 'stock_adjustment',
                    'happened_at' => $row->approved_at,
                    'status' => $row->status,
                ], $startUtc, $endExclusiveUtc),
                $this->normalizeAuditEvent([
                    'id' => 'stock_adjustment:posted:'.$row->id,
                    'category' => 'inventory',
                    'severity' => 'info',
                    'event_type' => 'stock_adjustment_posted',
                    'title' => 'Adjustment stok diposting',
                    'summary' => $summary,
                    'actor_name' => $row->approved_by_name ?: $row->created_by_name,
                    'entity_type' => 'stock_adjustment',
                    'entity_id' => $row->id,
                    'entity_label' => 'Adjustment '.$reference,
                    'reference_id' => $row->id,
                    'reference_type' => 'stock_adjustment',
                    'happened_at' => $row->posted_at,
                    'status' => $row->status,
                ], $startUtc, $endExclusiveUtc),
                $this->normalizeAuditEvent([
                    'id' => 'stock_adjustment:cancelled:'.$row->id,
                    'category' => 'inventory',
                    'severity' => 'warning',
                    'event_type' => 'stock_adjustment_cancelled',
                    'title' => 'Adjustment stok dibatalkan',
                    'summary' => $summary,
                    'actor_name' => $row->cancelled_by_name,
                    'entity_type' => 'stock_adjustment',
                    'entity_id' => $row->id,
                    'entity_label' => 'Adjustment '.$reference,
                    'reference_id' => $row->id,
                    'reference_type' => 'stock_adjustment',
                    'happened_at' => $row->cancelled_at,
                    'status' => $row->status,
                ], $startUtc, $endExclusiveUtc),
            ]));
        })->values();
    }

    private function purchaseAuditEvents(string $tenantId, CarbonImmutable $startUtc, CarbonImmutable $endExclusiveUtc)
    {
        $rows = DB::table('purchases as p')
            ->leftJoin('suppliers as s', function ($join) {
                $join->on('p.supplier_id', '=', 's.id')
                    ->on('p.tenant_id', '=', 's.tenant_id');
            })
            ->leftJoin('locations as l', function ($join) {
                $join->on('p.location_id', '=', 'l.id')
                    ->on('p.tenant_id', '=', 'l.tenant_id');
            })
            ->leftJoin('users as approved_users', function ($join) {
                $join->on('p.approved_by', '=', 'approved_users.id')
                    ->on('p.tenant_id', '=', 'approved_users.tenant_id');
            })
            ->leftJoin('users as received_users', function ($join) {
                $join->on('p.received_by', '=', 'received_users.id')
                    ->on('p.tenant_id', '=', 'received_users.tenant_id');
            })
            ->leftJoin('users as cancelled_users', function ($join) {
                $join->on('p.cancelled_by', '=', 'cancelled_users.id')
                    ->on('p.tenant_id', '=', 'cancelled_users.tenant_id');
            })
            ->where('p.tenant_id', $tenantId)
            ->where(function ($query) use ($startUtc, $endExclusiveUtc) {
                $query->whereBetween('p.approved_at', [$startUtc, $endExclusiveUtc])
                    ->orWhereBetween('p.received_at', [$startUtc, $endExclusiveUtc])
                    ->orWhereBetween('p.cancelled_at', [$startUtc, $endExclusiveUtc]);
            })
            ->orderByDesc('p.received_at')
            ->limit(20)
            ->get([
                'p.id',
                'p.status',
                'p.notes',
                'p.approved_at',
                'p.received_at',
                'p.cancelled_at',
                'approved_users.name as approved_by_name',
                'received_users.name as received_by_name',
                'cancelled_users.name as cancelled_by_name',
                's.name as supplier_name',
                'l.name as location_name',
            ]);

        $itemSummaries = $this->purchaseItemSummaryMap($tenantId, $rows->pluck('id')->all());

        return $rows->flatMap(function ($row) use ($startUtc, $endExclusiveUtc, $itemSummaries) {
            $summary = $this->composeAuditSummary([
                $itemSummaries[$row->id] ?? null,
                $row->supplier_name ? 'Supplier '.$row->supplier_name : null,
                $row->location_name ? 'Lokasi '.$row->location_name : null,
            ]);

            return array_values(array_filter([
                $this->normalizeAuditEvent([
                    'id' => 'purchase:approved:'.$row->id,
                    'category' => 'purchase',
                    'severity' => 'info',
                    'event_type' => 'purchase_approved',
                    'title' => 'PO disetujui',
                    'summary' => $summary,
                    'actor_name' => $row->approved_by_name,
                    'entity_type' => 'purchase',
                    'entity_id' => $row->id,
                    'entity_label' => 'PO '.substr((string) $row->id, 0, 8),
                    'reference_id' => $row->id,
                    'reference_type' => 'purchase',
                    'happened_at' => $row->approved_at,
                    'status' => $row->status,
                ], $startUtc, $endExclusiveUtc),
                $this->normalizeAuditEvent([
                    'id' => 'purchase:received:'.$row->id,
                    'category' => 'purchase',
                    'severity' => 'info',
                    'event_type' => 'purchase_received',
                    'title' => 'Pembelian diterima',
                    'summary' => $summary,
                    'actor_name' => $row->received_by_name,
                    'entity_type' => 'purchase',
                    'entity_id' => $row->id,
                    'entity_label' => 'PO '.substr((string) $row->id, 0, 8),
                    'reference_id' => $row->id,
                    'reference_type' => 'purchase',
                    'happened_at' => $row->received_at,
                    'status' => $row->status,
                ], $startUtc, $endExclusiveUtc),
                $this->normalizeAuditEvent([
                    'id' => 'purchase:cancelled:'.$row->id,
                    'category' => 'purchase',
                    'severity' => 'warning',
                    'event_type' => 'purchase_cancelled',
                    'title' => 'PO dibatalkan',
                    'summary' => $summary,
                    'actor_name' => $row->cancelled_by_name,
                    'entity_type' => 'purchase',
                    'entity_id' => $row->id,
                    'entity_label' => 'PO '.substr((string) $row->id, 0, 8),
                    'reference_id' => $row->id,
                    'reference_type' => 'purchase',
                    'happened_at' => $row->cancelled_at,
                    'status' => $row->status,
                ], $startUtc, $endExclusiveUtc),
            ]));
        })->values();
    }

    private function stockTransferAuditEvents(string $tenantId, CarbonImmutable $startUtc, CarbonImmutable $endExclusiveUtc)
    {
        $rows = DB::table('stock_transfers as st')
            ->leftJoin('locations as from_locations', function ($join) {
                $join->on('st.from_location_id', '=', 'from_locations.id')
                    ->on('st.tenant_id', '=', 'from_locations.tenant_id');
            })
            ->leftJoin('locations as to_locations', function ($join) {
                $join->on('st.to_location_id', '=', 'to_locations.id')
                    ->on('st.tenant_id', '=', 'to_locations.tenant_id');
            })
            ->leftJoin('branches as from_branches', function ($join) {
                $join->on('st.from_branch_id', '=', 'from_branches.id')
                    ->on('st.tenant_id', '=', 'from_branches.tenant_id');
            })
            ->leftJoin('branches as to_branches', function ($join) {
                $join->on('st.to_branch_id', '=', 'to_branches.id')
                    ->on('st.tenant_id', '=', 'to_branches.tenant_id');
            })
            ->leftJoin('users as approved_users', function ($join) {
                $join->on('st.approved_by', '=', 'approved_users.id')
                    ->on('st.tenant_id', '=', 'approved_users.tenant_id');
            })
            ->leftJoin('users as shipped_users', function ($join) {
                $join->on('st.shipped_by', '=', 'shipped_users.id')
                    ->on('st.tenant_id', '=', 'shipped_users.tenant_id');
            })
            ->leftJoin('users as received_users', function ($join) {
                $join->on('st.received_by', '=', 'received_users.id')
                    ->on('st.tenant_id', '=', 'received_users.tenant_id');
            })
            ->leftJoin('users as cancelled_users', function ($join) {
                $join->on('st.cancelled_by', '=', 'cancelled_users.id')
                    ->on('st.tenant_id', '=', 'cancelled_users.tenant_id');
            })
            ->where('st.tenant_id', $tenantId)
            ->where(function ($query) use ($startUtc, $endExclusiveUtc) {
                $query->whereBetween('st.approved_at', [$startUtc, $endExclusiveUtc])
                    ->orWhereBetween('st.shipped_at', [$startUtc, $endExclusiveUtc])
                    ->orWhereBetween('st.received_at', [$startUtc, $endExclusiveUtc])
                    ->orWhereBetween('st.cancelled_at', [$startUtc, $endExclusiveUtc]);
            })
            ->orderByDesc('st.received_at')
            ->limit(20)
            ->get([
                'st.id',
                'st.reference',
                'st.status',
                'st.approved_at',
                'st.shipped_at',
                'st.received_at',
                'st.cancelled_at',
                'approved_users.name as approved_by_name',
                'shipped_users.name as shipped_by_name',
                'received_users.name as received_by_name',
                'cancelled_users.name as cancelled_by_name',
                'from_branches.name as from_branch_name',
                'to_branches.name as to_branch_name',
                'from_locations.name as from_location_name',
                'to_locations.name as to_location_name',
            ]);

        $itemSummaries = $this->stockTransferItemSummaryMap($tenantId, $rows->pluck('id')->all());

        return $rows->flatMap(function ($row) use ($startUtc, $endExclusiveUtc, $itemSummaries) {
            $reference = $row->reference ?: substr((string) $row->id, 0, 8);
            $summary = $this->composeAuditSummary([
                $itemSummaries[$row->id] ?? null,
                $this->transferRouteSummary($row),
            ]);

            return array_values(array_filter([
                $this->normalizeAuditEvent([
                    'id' => 'stock_transfer:approved:'.$row->id,
                    'category' => 'inventory',
                    'severity' => 'info',
                    'event_type' => 'stock_transfer_approved',
                    'title' => 'Mutasi stok disetujui',
                    'summary' => $summary,
                    'actor_name' => $row->approved_by_name,
                    'entity_type' => 'stock_transfer',
                    'entity_id' => $row->id,
                    'entity_label' => 'Transfer '.$reference,
                    'reference_id' => $row->id,
                    'reference_type' => 'stock_transfer',
                    'happened_at' => $row->approved_at,
                    'status' => $row->status,
                ], $startUtc, $endExclusiveUtc),
                $this->normalizeAuditEvent([
                    'id' => 'stock_transfer:shipped:'.$row->id,
                    'category' => 'inventory',
                    'severity' => 'info',
                    'event_type' => 'stock_transfer_shipped',
                    'title' => 'Mutasi stok dikirim',
                    'summary' => $summary,
                    'actor_name' => $row->shipped_by_name,
                    'entity_type' => 'stock_transfer',
                    'entity_id' => $row->id,
                    'entity_label' => 'Transfer '.$reference,
                    'reference_id' => $row->id,
                    'reference_type' => 'stock_transfer',
                    'happened_at' => $row->shipped_at,
                    'status' => $row->status,
                ], $startUtc, $endExclusiveUtc),
                $this->normalizeAuditEvent([
                    'id' => 'stock_transfer:received:'.$row->id,
                    'category' => 'inventory',
                    'severity' => 'info',
                    'event_type' => 'stock_transfer_received',
                    'title' => 'Mutasi stok diterima',
                    'summary' => $summary,
                    'actor_name' => $row->received_by_name,
                    'entity_type' => 'stock_transfer',
                    'entity_id' => $row->id,
                    'entity_label' => 'Transfer '.$reference,
                    'reference_id' => $row->id,
                    'reference_type' => 'stock_transfer',
                    'happened_at' => $row->received_at,
                    'status' => $row->status,
                ], $startUtc, $endExclusiveUtc),
                $this->normalizeAuditEvent([
                    'id' => 'stock_transfer:cancelled:'.$row->id,
                    'category' => 'inventory',
                    'severity' => 'warning',
                    'event_type' => 'stock_transfer_cancelled',
                    'title' => 'Mutasi stok dibatalkan',
                    'summary' => $summary,
                    'actor_name' => $row->cancelled_by_name,
                    'entity_type' => 'stock_transfer',
                    'entity_id' => $row->id,
                    'entity_label' => 'Transfer '.$reference,
                    'reference_id' => $row->id,
                    'reference_type' => 'stock_transfer',
                    'happened_at' => $row->cancelled_at,
                    'status' => $row->status,
                ], $startUtc, $endExclusiveUtc),
            ]));
        })->values();
    }

    private function saleVoidAuditEvents(string $tenantId, CarbonImmutable $startUtc, CarbonImmutable $endExclusiveUtc)
    {
        $rows = DB::table('sales as s')
            ->leftJoin('branches as b', function ($join) {
                $join->on('s.branch_id', '=', 'b.id')
                    ->on('s.tenant_id', '=', 'b.tenant_id');
            })
            ->leftJoin('users as void_users', function ($join) {
                $join->on('s.voided_by', '=', 'void_users.id')
                    ->on('s.tenant_id', '=', 'void_users.tenant_id');
            })
            ->leftJoin('users as approver_users', function ($join) {
                $join->on('s.void_approved_by', '=', 'approver_users.id')
                    ->on('s.tenant_id', '=', 'approver_users.tenant_id');
            })
            ->where('s.tenant_id', $tenantId)
            ->where('s.voided_at', '>=', $startUtc)
            ->where('s.voided_at', '<', $endExclusiveUtc)
            ->orderByDesc('s.voided_at')
            ->limit(20)
            ->get([
                's.id',
                's.total',
                's.status',
                's.void_reason',
                's.voided_at',
                'void_users.name as voided_by_name',
                'approver_users.name as approved_by_name',
                'b.name as branch_name',
            ]);

        $itemSummaries = $this->saleItemSummaryMap($tenantId, $rows->pluck('id')->all());

        return $rows->map(function ($row) use ($itemSummaries) {
            $summary = $this->composeAuditSummary([
                $itemSummaries[$row->id] ?? null,
                $row->total !== null ? 'Total '.$this->formatAuditCurrency((float) $row->total) : null,
                $row->branch_name ? 'Cabang '.$row->branch_name : null,
                $row->void_reason ?: null,
                $row->approved_by_name ? 'Disetujui '.$row->approved_by_name : null,
            ]);

            return [
                'id' => 'sale:voided:'.$row->id,
                'category' => 'sales',
                'severity' => 'warning',
                'event_type' => 'sale_voided',
                'title' => 'Transaksi di-void',
                'summary' => $summary,
                'actor_name' => $row->voided_by_name,
                'entity_type' => 'sale',
                'entity_id' => $row->id,
                'entity_label' => 'Sale '.substr((string) $row->id, 0, 8),
                'reference_id' => $row->id,
                'reference_type' => 'sale',
                'happened_at' => $row->voided_at,
                'status' => $row->status,
            ];
        })->values();
    }

    private function refundAuditEvents(string $tenantId, CarbonImmutable $startUtc, CarbonImmutable $endExclusiveUtc)
    {
        $rows = DB::table('refunds as r')
            ->leftJoin('branches as b', function ($join) {
                $join->on('r.branch_id', '=', 'b.id')
                    ->on('r.tenant_id', '=', 'b.tenant_id');
            })
            ->leftJoin('users as created_users', function ($join) {
                $join->on('r.created_by', '=', 'created_users.id')
                    ->on('r.tenant_id', '=', 'created_users.tenant_id');
            })
            ->where('r.tenant_id', $tenantId)
            ->where('r.created_at', '>=', $startUtc)
            ->where('r.created_at', '<', $endExclusiveUtc)
            ->orderByDesc('r.created_at')
            ->limit(20)
            ->get([
                'r.id',
                'r.sale_id',
                'r.total',
                'r.status',
                'r.reason',
                'r.created_at',
                'created_users.name as created_by_name',
                'b.name as branch_name',
            ]);

        $itemSummaries = $this->refundItemSummaryMap($tenantId, $rows->pluck('id')->all());

        return $rows->map(function ($row) use ($itemSummaries) {
            $summary = $this->composeAuditSummary([
                $itemSummaries[$row->id] ?? null,
                $row->total !== null ? 'Total '.$this->formatAuditCurrency((float) $row->total) : null,
                $row->branch_name ? 'Cabang '.$row->branch_name : null,
                $row->sale_id ? 'Sale '.substr((string) $row->sale_id, 0, 8) : null,
                $row->reason ?: null,
            ]);

            return [
                'id' => 'refund:created:'.$row->id,
                'category' => 'sales',
                'severity' => 'info',
                'event_type' => 'refund_created',
                'title' => 'Refund dibuat',
                'summary' => $summary,
                'actor_name' => $row->created_by_name,
                'entity_type' => 'refund',
                'entity_id' => $row->id,
                'entity_label' => 'Refund '.substr((string) $row->id, 0, 8),
                'reference_id' => $row->sale_id,
                'reference_type' => 'sale',
                'happened_at' => $row->created_at,
                'status' => $row->status,
            ];
        })->values();
    }

    private function anomalyAuditEvents(string $tenantId, CarbonImmutable $startUtc, CarbonImmutable $endExclusiveUtc)
    {
        $rows = Anomaly::query()
            ->leftJoin('users as resolved_users', function ($join) {
                $join->on('anomalies.resolved_by', '=', 'resolved_users.id')
                    ->on('anomalies.tenant_id', '=', 'resolved_users.tenant_id');
            })
            ->where('anomalies.tenant_id', $tenantId)
            ->where(function ($query) use ($startUtc, $endExclusiveUtc) {
                $query->whereBetween('anomalies.detected_at', [$startUtc, $endExclusiveUtc])
                    ->orWhereBetween('anomalies.resolved_at', [$startUtc, $endExclusiveUtc]);
            })
            ->orderByDesc('anomalies.detected_at')
            ->limit(20)
            ->get([
                'anomalies.id',
                'anomalies.severity',
                'anomalies.type',
                'anomalies.reference_id',
                'anomalies.payload_json',
                'anomalies.detected_at',
                'anomalies.resolved_at',
                'resolved_users.name as resolved_by_name',
            ]);

        $summaries = $this->anomalySummaryMap($tenantId, $rows);

        return $rows->flatMap(function ($row) use ($startUtc, $endExclusiveUtc, $summaries) {
            $isProductionBatchAnomaly = (string) $row->type === 'production_batch_yield_variance';
            $payload = is_array($row->payload_json) ? $row->payload_json : (json_decode((string) $row->payload_json, true) ?: []);

            return array_values(array_filter([
                $this->normalizeAuditEvent([
                    'id' => 'anomaly:detected:'.$row->id,
                    'category' => 'anomaly',
                    'severity' => $row->severity ?: 'warning',
                    'event_type' => 'anomaly_detected',
                    'title' => $this->anomalyTypeLabel((string) $row->type),
                    'summary' => $summaries[$row->id] ?? 'Terdeteksi otomatis oleh sistem.',
                    'actor_name' => null,
                    'entity_type' => 'anomaly',
                    'entity_id' => $row->id,
                    'entity_label' => 'Anomali '.substr((string) $row->id, 0, 8),
                    'reference_id' => $row->reference_id,
                    'reference_type' => $isProductionBatchAnomaly ? 'production_batch' : 'anomaly',
                    'happened_at' => $row->detected_at,
                    'status' => $row->resolved_at ? 'resolved' : 'open',
                ], $startUtc, $endExclusiveUtc),
                $this->normalizeAuditEvent([
                    'id' => 'anomaly:resolved:'.$row->id,
                    'category' => 'anomaly',
                    'severity' => 'info',
                    'event_type' => 'anomaly_resolved',
                    'title' => 'Anomali diselesaikan',
                    'summary' => $this->composeAuditSummary([
                        $summaries[$row->id] ?? $this->anomalyTypeLabel((string) $row->type),
                        ! empty($payload['resolved_note']) ? 'Resolve '.$payload['resolved_note'] : null,
                    ]),
                    'actor_name' => $row->resolved_by_name,
                    'entity_type' => 'anomaly',
                    'entity_id' => $row->id,
                    'entity_label' => 'Anomali '.substr((string) $row->id, 0, 8),
                    'reference_id' => $row->reference_id,
                    'reference_type' => $isProductionBatchAnomaly ? 'production_batch' : 'anomaly',
                    'happened_at' => $row->resolved_at,
                    'status' => 'resolved',
                ], $startUtc, $endExclusiveUtc),
            ]));
        })->values();
    }

    private function purchaseItemSummaryMap(string $tenantId, array $purchaseIds): array
    {
        if ($purchaseIds === []) {
            return [];
        }

        $rows = DB::table('purchase_items as pi')
            ->join('products as p', function ($join) {
                $join->on('pi.product_id', '=', 'p.id')
                    ->on('pi.tenant_id', '=', 'p.tenant_id');
            })
            ->where('pi.tenant_id', $tenantId)
            ->whereIn('pi.purchase_id', $purchaseIds)
            ->orderBy('pi.purchase_id')
            ->get([
                'pi.purchase_id as parent_id',
                'pi.qty',
                'p.name as product_name',
            ]);

        return $this->buildAuditItemSummaryMap($rows, 'parent_id', 'qty');
    }

    private function stockAdjustmentItemSummaryMap(string $tenantId, array $adjustmentIds): array
    {
        if ($adjustmentIds === []) {
            return [];
        }

        $rows = DB::table('stock_adjustment_items as sai')
            ->join('products as p', function ($join) {
                $join->on('sai.product_id', '=', 'p.id')
                    ->on('sai.tenant_id', '=', 'p.tenant_id');
            })
            ->where('sai.tenant_id', $tenantId)
            ->whereIn('sai.stock_adjustment_id', $adjustmentIds)
            ->orderBy('sai.stock_adjustment_id')
            ->get([
                'sai.stock_adjustment_id as parent_id',
                'sai.difference_qty as qty',
                'p.name as product_name',
            ]);

        return $this->buildAuditItemSummaryMap($rows, 'parent_id', 'qty', 'Selisih');
    }

    private function stockTransferItemSummaryMap(string $tenantId, array $transferIds): array
    {
        if ($transferIds === []) {
            return [];
        }

        $rows = DB::table('stock_transfer_items as sti')
            ->join('products as p', function ($join) {
                $join->on('sti.product_id', '=', 'p.id')
                    ->on('sti.tenant_id', '=', 'p.tenant_id');
            })
            ->where('sti.tenant_id', $tenantId)
            ->whereIn('sti.stock_transfer_id', $transferIds)
            ->orderBy('sti.stock_transfer_id')
            ->get([
                'sti.stock_transfer_id as parent_id',
                'sti.qty',
                'p.name as product_name',
            ]);

        return $this->buildAuditItemSummaryMap($rows, 'parent_id', 'qty');
    }

    private function saleItemSummaryMap(string $tenantId, array $saleIds): array
    {
        if ($saleIds === []) {
            return [];
        }

        $rows = DB::table('sale_items as si')
            ->join('products as p', function ($join) {
                $join->on('si.product_id', '=', 'p.id')
                    ->on('si.tenant_id', '=', 'p.tenant_id');
            })
            ->where('si.tenant_id', $tenantId)
            ->whereIn('si.sale_id', $saleIds)
            ->orderBy('si.sale_id')
            ->get([
                'si.sale_id as parent_id',
                'si.qty',
                'p.name as product_name',
            ]);

        return $this->buildAuditItemSummaryMap($rows, 'parent_id', 'qty');
    }

    private function refundItemSummaryMap(string $tenantId, array $refundIds): array
    {
        if ($refundIds === []) {
            return [];
        }

        $rows = DB::table('refund_items as ri')
            ->join('products as p', function ($join) {
                $join->on('ri.product_id', '=', 'p.id')
                    ->on('ri.tenant_id', '=', 'p.tenant_id');
            })
            ->where('ri.tenant_id', $tenantId)
            ->whereIn('ri.refund_id', $refundIds)
            ->orderBy('ri.refund_id')
            ->get([
                'ri.refund_id as parent_id',
                'ri.qty',
                'p.name as product_name',
            ]);

        return $this->buildAuditItemSummaryMap($rows, 'parent_id', 'qty');
    }

    private function buildAuditItemSummaryMap(iterable $rows, string $parentKey, string $qtyKey, string $qtyLabel = 'Qty'): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $parentId = (string) ($row->{$parentKey} ?? '');
            if ($parentId === '') {
                continue;
            }

            if (! isset($grouped[$parentId])) {
                $grouped[$parentId] = [
                    'count' => 0,
                    'qty' => 0.0,
                    'names' => [],
                ];
            }

            $grouped[$parentId]['count']++;
            $grouped[$parentId]['qty'] += (float) ($row->{$qtyKey} ?? 0);

            $name = trim((string) ($row->product_name ?? ''));
            if ($name !== '' && ! in_array($name, $grouped[$parentId]['names'], true)) {
                $grouped[$parentId]['names'][] = $name;
            }
        }

        $map = [];
        foreach ($grouped as $parentId => $data) {
            $map[$parentId] = $this->composeAuditSummary([
                $data['count'] > 0 ? $data['count'].' item' : null,
                $qtyLabel.' '.$this->formatAuditQty((float) $data['qty']),
                $this->compactAuditProductNames($data['names']),
            ]);
        }

        return $map;
    }

    private function anomalySummaryMap(string $tenantId, iterable $rows): array
    {
        $payloads = [];
        $rowMeta = [];
        $productIds = [];
        $locationIds = [];
        $branchIds = [];
        $productionBatchIds = [];

        foreach ($rows as $row) {
            $payload = $row->payload_json;
            if (is_string($payload)) {
                $payload = json_decode($payload, true);
            }

            $payload = is_array($payload) ? $payload : [];
            $payloads[(string) $row->id] = $payload;
            $rowMeta[(string) $row->id] = [
                'type' => (string) ($row->type ?? ''),
                'reference_id' => (string) ($row->reference_id ?? ''),
            ];

            if (! empty($payload['product_id'])) {
                $productIds[] = $payload['product_id'];
            }
            if (! empty($payload['branch_id'])) {
                $branchIds[] = $payload['branch_id'];
            }
            if (! empty($payload['location_id'])) {
                $locationIds[] = $payload['location_id'];
            }
            if ((string) ($row->type ?? '') === 'production_batch_yield_variance' && ! empty($row->reference_id)) {
                $productionBatchIds[] = (string) $row->reference_id;
            }
        }

        $productNames = DB::table('products')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_values(array_unique($productIds)))
            ->pluck('name', 'id')
            ->all();

        $locationNames = DB::table('locations')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_values(array_unique($locationIds)))
            ->pluck('name', 'id')
            ->all();

        $branchNames = DB::table('branches')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_values(array_unique($branchIds)))
            ->pluck('name', 'id')
            ->all();

        $productionBatchReferences = ProductionBatch::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_values(array_unique($productionBatchIds)))
            ->get(['id', 'reference_no'])
            ->mapWithKeys(fn (ProductionBatch $batch) => [
                (string) $batch->id => (string) ($batch->reference_no ?: 'Batch '.substr((string) $batch->id, 0, 8)),
            ])
            ->all();

        $map = [];
        foreach ($payloads as $id => $payload) {
            $sampleCount = isset($payload['sample']) && is_countable($payload['sample']) ? count($payload['sample']) : null;
            $referenceId = $rowMeta[$id]['reference_id'] ?? '';
            $type = $rowMeta[$id]['type'] ?? '';

            $map[$id] = $this->composeAuditSummary([
                $type === 'production_batch_yield_variance' && $referenceId !== ''
                    ? 'Batch '.($productionBatchReferences[$referenceId] ?? substr($referenceId, 0, 8))
                    : null,
                ! empty($payload['product_id']) ? 'Produk '.($productNames[$payload['product_id']] ?? substr((string) $payload['product_id'], 0, 8)) : null,
                ! empty($payload['branch_id']) ? 'Cabang '.($branchNames[$payload['branch_id']] ?? substr((string) $payload['branch_id'], 0, 8)) : null,
                ! empty($payload['location_id']) ? 'Lokasi '.($locationNames[$payload['location_id']] ?? substr((string) $payload['location_id'], 0, 8)) : null,
                isset($payload['actual_output_qty']) ? 'Output aktual '.$this->formatAuditQty((float) $payload['actual_output_qty']) : null,
                isset($payload['yield_variance_qty']) ? 'Selisih '.$this->formatAuditQty((float) $payload['yield_variance_qty']) : null,
                isset($payload['yield_variance_pct']) ? 'Selisih '.$this->formatAuditQty((float) $payload['yield_variance_pct']).'%' : null,
                ! empty($payload['variance_reason']) ? 'Alasan '.$payload['variance_reason'] : null,
                isset($payload['requested_qty']) ? 'Qty '.$this->formatAuditQty((float) $payload['requested_qty']) : null,
                isset($payload['count']) ? ((int) $payload['count']).' kasus' : ($sampleCount ? $sampleCount.' sampel' : null),
                ! empty($payload['source']) ? 'Sumber '.$payload['source'] : null,
                'Terdeteksi otomatis oleh sistem.',
            ]);
        }

        return $map;
    }

    private function transferRouteSummary(object $row): ?string
    {
        $from = $row->from_location_name ?: $row->from_branch_name;
        $to = $row->to_location_name ?: $row->to_branch_name;

        if ($from && $to) {
            return $from.' -> '.$to;
        }

        return $this->composeAuditSummary([
            $from ? 'Dari '.$from : null,
            $to ? 'Ke '.$to : null,
        ]);
    }

    private function compactAuditProductNames(array $names): ?string
    {
        $names = array_values(array_filter(array_unique(array_map('trim', $names))));
        if ($names === []) {
            return null;
        }

        $first = $names[0];
        $remaining = count($names) - 1;

        return $remaining > 0 ? $first.' +'.$remaining.' lain' : $first;
    }

    private function composeAuditSummary(array $parts): string
    {
        return trim(collect($parts)
            ->map(fn ($part) => is_string($part) ? trim($part) : $part)
            ->filter(fn ($part) => $part !== null && $part !== '')
            ->implode(' · '));
    }

    private function formatSignedQty(float $qty, string $type, string $label = 'Qty'): ?string
    {
        if (abs($qty) < 0.00001) {
            return null;
        }

        $normalized = strtolower($type) === 'out' ? -abs($qty) : abs($qty);

        return $label.' '.($normalized > 0 ? '+' : '').$this->formatAuditQty($normalized);
    }

    private function formatAuditQty(float $qty): string
    {
        if (abs($qty - round($qty)) < 0.00001) {
            return (string) (int) round($qty);
        }

        return rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
    }

    private function formatAuditCurrency(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    private function normalizeAuditEvent(array $event, CarbonImmutable $startUtc, CarbonImmutable $endExclusiveUtc): ?array
    {
        $happenedAt = $event['happened_at'] ?? null;
        if (! $happenedAt) {
            return null;
        }

        $timestamp = CarbonImmutable::parse((string) $happenedAt, 'UTC');
        if ($timestamp->lt($startUtc) || $timestamp->gte($endExclusiveUtc)) {
            return null;
        }

        $event['happened_at'] = $timestamp->toIso8601String();

        return $event;
    }

    private function anomalyTypeLabel(string $type): string
    {
        return match ($type) {
            'negative_stock_attempt' => 'Percobaan stok negatif',
            'fifo_layer_negative' => 'Layer FIFO negatif',
            'fifo_allocation_qty_mismatch' => 'Mismatch alokasi FIFO',
            'invalid_transaction_date_attempt' => 'Tanggal transaksi tidak valid',
            'production_batch_yield_variance' => 'Yield variance batch produksi',
            default => $type !== '' ? $type : 'Audit operasional',
        };
    }

    private function canUseFeature(string $key): bool
    {
        $tenant = TenantContext::get();
        if (! $tenant) {
            return false;
        }

        $plan = app(SubscriptionService::class)->currentPlan($tenant);

        return FeatureGateConfig::isFeatureAllowed($key, $plan);
    }

    private function buildSimplePdf(array $lines): string
    {
        $escaped = array_map(function (string $line): string {
            return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
        }, $lines);

        $content = "BT\n/F1 12 Tf\n72 720 Td\n";
        foreach ($escaped as $index => $line) {
            if ($index === 0) {
                $content .= "($line) Tj\n";
                continue;
            }
            $content .= "0 -16 Td\n($line) Tj\n";
        }
        $content .= "ET";

        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
        $objects[] = "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj\n";
        $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $offsets = [];
        $pdf = "%PDF-1.4\n";
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefPosition = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefPosition . "\n%%EOF";

        return $pdf;
    }
}
