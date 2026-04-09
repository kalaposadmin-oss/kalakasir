<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\InventoryIssueAllocation;
use App\Models\InventoryLayer;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockLedger;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use App\Support\TenantContext;

class StockCardController extends Controller
{
    public function __invoke(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'product' => null,
                'location' => null,
                'balance' => 0,
                'ledger' => [],
                'range' => null,
            ], 400);
        }
        $productId = $request->query('product_id');
        $locationId = $request->query('location_id');
        $type = $request->query('type');
        $includeEmptyLayers = $request->boolean('include_empty_layers');
        $allocationType = $request->query('allocation_type');

        $productQuery = Product::query()
            ->leftJoin('categories', function ($join) {
                $join->on('products.category_id', '=', 'categories.id')
                    ->on('products.tenant_id', '=', 'categories.tenant_id');
            })
            ->where('products.tenant_id', $tenantId)
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.image_url',
                'products.category_id',
                'categories.name as category_name',
            ]);

        $product = $productId
            ? (clone $productQuery)->where('products.id', $productId)->first()
            : (clone $productQuery)->orderBy('products.name')->first();

        if (! $product) {
            return response()->json([
                'product' => null,
                'location' => null,
                'balance' => 0,
                'ledger' => [],
                'range' => null,
            ]);
        }

        $location = $locationId
            ? Location::query()->where('tenant_id', $tenantId)->where('id', $locationId)->first()
            : Location::query()->where('tenant_id', $tenantId)->where('is_default', true)->first();

        if (! $location) {
            $location = Location::query()->where('tenant_id', $tenantId)->orderBy('name')->first();
        }

        if (! $location) {
            return response()->json([
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'image_url' => $product->image_url,
                    'category_name' => $product->category_name,
                ],
                'location' => null,
                'balance' => 0,
                'ledger' => [],
                'range' => null,
            ]);
        }

        $preset = $request->query('preset', $request->query('range', 'last30'));
        $from = $request->query('start_date', $request->query('from'));
        $to = $request->query('end_date', $request->query('to'));

        [$startUtc, $endExclusiveUtc, $rangeMeta] = $this->resolveRange($preset, $from, $to);

        $ledgerQuery = StockLedger::query()
            ->select([
                'stock_ledger.*',
                'stock_adjustments.reason as adjustment_reason',
                'stock_adjustments.note as adjustment_note',
            ])
            ->leftJoin('stock_adjustments', function ($join) {
                $join->on('stock_ledger.reference_id', '=', 'stock_adjustments.id')
                    ->on('stock_ledger.tenant_id', '=', 'stock_adjustments.tenant_id')
                    ->where('stock_ledger.reference_type', '=', 'stock_adjustment');
            })
            ->where('stock_ledger.tenant_id', $tenantId)
            ->where('stock_ledger.product_id', $product->id)
            ->where('stock_ledger.location_id', $location->id)
            ->where('stock_ledger.created_at', '>=', $startUtc)
            ->where('stock_ledger.created_at', '<', $endExclusiveUtc);

        $ledgerSummaryQuery = StockLedger::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endExclusiveUtc);

        if ($type && $type !== 'all') {
            $ledgerQuery->where('stock_ledger.reference_type', $type);
            $ledgerSummaryQuery->where('reference_type', $type);
        }

        $ledger = $ledgerQuery
            ->orderBy('stock_ledger.created_at', 'desc')
            ->limit(200)
            ->get();

        $ledgerSummary = $ledgerSummaryQuery
            ->selectRaw('sum(qty_in) as total_in')
            ->selectRaw('sum(qty_out) as total_out')
            ->selectRaw('count(*) as movement_count')
            ->first();

        $balance = (float) InventoryBalance::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->value('qty_on_hand');

        $valuationMethod = TenantContext::get()?->inventory_valuation_method ?? 'average';

        $layersQuery = InventoryLayer::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->orderBy('received_at')
            ->orderBy('created_at');

        if (! $includeEmptyLayers) {
            $layersQuery->where('qty_remaining', '>', 0);
        }

        $layers = $layersQuery
            ->limit(200)
            ->get([
                'id',
                'qty_remaining',
                'qty_original',
                'cost_per_unit',
                'source_type',
                'source_id',
                'received_at',
                'created_at',
            ]);

        $allocationsQuery = InventoryIssueAllocation::query()
            ->leftJoin('inventory_layers as il', function ($join) {
                $join->on('inventory_issue_allocations.inventory_layer_id', '=', 'il.id')
                    ->on('inventory_issue_allocations.tenant_id', '=', 'il.tenant_id');
            })
            ->where('inventory_issue_allocations.tenant_id', $tenantId)
            ->where('inventory_issue_allocations.product_id', $product->id)
            ->where('inventory_issue_allocations.location_id', $location->id)
            ->where('inventory_issue_allocations.created_at', '>=', $startUtc)
            ->where('inventory_issue_allocations.created_at', '<', $endExclusiveUtc);

        if ($allocationType && $allocationType !== 'all') {
            $allocationsQuery->where('inventory_issue_allocations.reference_type', $allocationType);
        }

        $allocations = $allocationsQuery
            ->orderBy('inventory_issue_allocations.created_at', 'desc')
            ->limit(200)
            ->get([
                'inventory_issue_allocations.id',
                'inventory_issue_allocations.inventory_layer_id',
                'inventory_issue_allocations.reference_type',
                'inventory_issue_allocations.reference_id',
                'inventory_issue_allocations.reference_item_id',
                'inventory_issue_allocations.qty',
                'inventory_issue_allocations.unit_cost',
                'inventory_issue_allocations.total_cost',
                'inventory_issue_allocations.created_at',
                'inventory_issue_allocations.reversed_at',
                'il.received_at as layer_received_at',
                'il.source_type as layer_source_type',
                'il.source_id as layer_source_id',
                'il.cost_per_unit as layer_cost_per_unit',
            ]);

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'image_url' => $product->image_url,
                'category_name' => $product->category_name,
            ],
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'branch_id' => $location->branch_id,
            ],
            'valuation_method' => $valuationMethod,
            'balance' => $balance,
            'summary' => [
                'total_in' => (float) ($ledgerSummary->total_in ?? 0),
                'total_out' => (float) ($ledgerSummary->total_out ?? 0),
                'movement_count' => (int) ($ledgerSummary->movement_count ?? 0),
            ],
            'ledger' => $ledger,
            'layers' => $layers,
            'allocations' => $allocations,
            'range' => $rangeMeta,
        ]);
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
            default => [$today->subDays(29), $today, '30 Hari Terakhir'],
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
                'timezone' => 'Asia/Jakarta',
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
}
