<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\InventoryIssueAllocation;
use App\Models\InventoryLayer;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockLedger;
use App\Support\SaleChannel;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $productVariantId = $request->query('product_variant_id');
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
                'products.unit',
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

        $productVariants = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'sku',
                'name',
                'attributes',
                'price_override',
            ])
            ->map(function ($variant) {
                $attributes = $variant->attributes;
                if (is_string($attributes)) {
                    $attributes = json_decode($attributes, true);
                }

                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'attributes' => is_array($attributes) ? $attributes : [],
                    'price_override' => $variant->price_override !== null ? (float) $variant->price_override : null,
                ];
            })
            ->values();

        if ($productVariantId !== null && $productVariantId !== '') {
            $variantExists = $productVariants->contains(fn (array $variant) => (string) $variant['id'] === (string) $productVariantId);

            if (! $variantExists) {
                return response()->json([
                    'message' => 'Varian produk tidak valid.',
                ], 422);
            }
        } else {
            $productVariantId = null;
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
                    'unit' => $product->unit,
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
                'ledger_sales.idempotency_key as sale_idempotency_key',
            ])
            ->leftJoin('stock_adjustments', function ($join) {
                $join->on('stock_ledger.reference_id', '=', 'stock_adjustments.id')
                    ->on('stock_ledger.tenant_id', '=', 'stock_adjustments.tenant_id')
                    ->where('stock_ledger.reference_type', '=', 'stock_adjustment');
            })
            ->leftJoin('sales as ledger_sales', function ($join) {
                $join->on('stock_ledger.reference_id', '=', 'ledger_sales.id')
                    ->on('stock_ledger.tenant_id', '=', 'ledger_sales.tenant_id')
                    ->whereIn('stock_ledger.reference_type', ['sale', 'sale_recipe_input']);
            })
            ->where('stock_ledger.tenant_id', $tenantId)
            ->where('stock_ledger.product_id', $product->id)
            ->where('stock_ledger.location_id', $location->id)
            ->where('stock_ledger.created_at', '>=', $startUtc)
            ->where('stock_ledger.created_at', '<', $endExclusiveUtc);

        $scopeByVariant = $productVariantId !== null || $productVariants->isEmpty();

        if ($productVariantId !== null) {
            $ledgerQuery->where('stock_ledger.product_variant_id', $productVariantId);
        }

        $ledgerSummaryQuery = StockLedger::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endExclusiveUtc);

        if ($productVariantId !== null) {
            $ledgerSummaryQuery->where('product_variant_id', $productVariantId);
        }

        if ($type && $type !== 'all') {
            $ledgerQuery->where('stock_ledger.reference_type', $type);
            $ledgerSummaryQuery->where('reference_type', $type);
        }

        $ledger = $ledgerQuery
            ->orderBy('stock_ledger.created_at', 'desc')
            ->limit(200)
            ->get()
            ->map(fn ($row) => $this->withSaleSourceMeta($row));

        $ledgerSummary = $ledgerSummaryQuery
            ->selectRaw('sum(qty_in) as total_in')
            ->selectRaw('sum(qty_out) as total_out')
            ->selectRaw('count(*) as movement_count')
            ->first();

        $balanceQuery = InventoryBalance::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->where('location_id', $location->id);

        if ($scopeByVariant) {
            if ($productVariantId !== null) {
                $balanceQuery->where('product_variant_id', $productVariantId);
            } else {
                $balanceQuery->whereNull('product_variant_id');
            }
        }

        $balance = (float) $balanceQuery->sum('qty_on_hand');

        $valuationMethod = TenantContext::get()?->inventory_valuation_method ?? 'average';

        [$locationBuckets, $variantBuckets] = $this->resolveBucketSummaries($tenantId, (string) $product->id);
        $costState = $this->resolveCostState($tenantId, (string) $product->id, (string) $location->id, $productVariantId, $scopeByVariant);
        $lastCostSource = $this->resolveLastCostSource($tenantId, (string) $product->id, (string) $location->id, $productVariantId, $scopeByVariant);

        $layersQuery = InventoryLayer::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->orderBy('received_at')
            ->orderBy('created_at');

        if ($productVariantId !== null) {
            $layersQuery->where('product_variant_id', $productVariantId);
        }

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
            ->leftJoin('sales as allocation_sales', function ($join) {
                $join->on('inventory_issue_allocations.reference_id', '=', 'allocation_sales.id')
                    ->on('inventory_issue_allocations.tenant_id', '=', 'allocation_sales.tenant_id')
                    ->where('inventory_issue_allocations.reference_type', '=', 'sale');
            })
            ->where('inventory_issue_allocations.tenant_id', $tenantId)
            ->where('inventory_issue_allocations.product_id', $product->id)
            ->where('inventory_issue_allocations.location_id', $location->id)
            ->where('inventory_issue_allocations.created_at', '>=', $startUtc)
            ->where('inventory_issue_allocations.created_at', '<', $endExclusiveUtc);

        if ($productVariantId !== null) {
            $allocationsQuery->where('inventory_issue_allocations.product_variant_id', $productVariantId);
        }

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
                'allocation_sales.idempotency_key as sale_idempotency_key',
                'il.received_at as layer_received_at',
                'il.source_type as layer_source_type',
                'il.source_id as layer_source_id',
                'il.cost_per_unit as layer_cost_per_unit',
            ])
            ->map(fn ($row) => $this->withSaleSourceMeta($row));

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'image_url' => $product->image_url,
                'category_name' => $product->category_name,
            ],
            'product_variants' => $productVariants,
            'selected_variant_id' => $productVariantId,
            'location' => [
                'id' => $location->id,
                'name' => $location->name,
                'branch_id' => $location->branch_id,
            ],
            'valuation_method' => $valuationMethod,
            'balance' => $balance,
            'product_location_balances' => $locationBuckets,
            'product_variant_balances' => $variantBuckets,
            'cost_state' => $costState,
            'last_cost_source' => $lastCostSource,
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

    /**
     * @return array{0: array<int, array{id:string,branch_id:?string,name:string,is_default:bool,qty_on_hand:float}>, 1: array<int, array{id:?string,sku:?string,name:string,qty_on_hand:float}>}
     */
    private function resolveBucketSummaries(string $tenantId, string $productId): array
    {
        $locationRows = DB::table('inventory_balances as ib')
            ->leftJoin('locations as l', function ($join) {
                $join->on('ib.location_id', '=', 'l.id')
                    ->on('ib.tenant_id', '=', 'l.tenant_id');
            })
            ->where('ib.tenant_id', $tenantId)
            ->where('ib.product_id', $productId)
            ->get([
                'ib.location_id',
                'l.branch_id',
                'l.name',
                'l.is_default',
                'ib.qty_on_hand',
            ]);

        $locationBuckets = $locationRows
            ->groupBy('location_id')
            ->map(function ($rows, $locationId) {
                $first = $rows->first();

                return [
                    'id' => (string) $locationId,
                    'branch_id' => $first?->branch_id ? (string) $first->branch_id : null,
                    'name' => (string) ($first?->name ?? '-'),
                    'is_default' => (bool) ($first?->is_default ?? false),
                    'qty_on_hand' => (float) $rows->sum('qty_on_hand'),
                ];
            })
            ->sortByDesc('qty_on_hand')
            ->values()
            ->all();

        $variantRows = DB::table('inventory_balances as ib')
            ->leftJoin('product_variants as pv', function ($join) {
                $join->on('ib.product_variant_id', '=', 'pv.id')
                    ->on('ib.tenant_id', '=', 'pv.tenant_id');
            })
            ->where('ib.tenant_id', $tenantId)
            ->where('ib.product_id', $productId)
            ->get([
                'ib.product_variant_id',
                'pv.sku',
                'pv.name',
                'ib.qty_on_hand',
            ]);

        $variantBuckets = $variantRows
            ->groupBy(fn ($row) => $row->product_variant_id ? (string) $row->product_variant_id : 'null')
            ->map(function ($rows, $variantKey) {
                $first = $rows->first();

                return [
                    'id' => $variantKey === 'null' ? null : $variantKey,
                    'sku' => $first?->sku ? (string) $first->sku : null,
                    'name' => $first?->name ? (string) $first->name : 'Tanpa Varian',
                    'qty_on_hand' => (float) $rows->sum('qty_on_hand'),
                ];
            })
            ->sortByDesc('qty_on_hand')
            ->values()
            ->all();

        return [$locationBuckets, $variantBuckets];
    }

    private function resolveCostState(string $tenantId, string $productId, string $locationId, ?string $productVariantId, bool $scopeByVariant): ?array
    {
        $query = DB::table('inventory_cost_states')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId);

        if ($scopeByVariant) {
            if ($productVariantId !== null) {
                $query->where('product_variant_id', $productVariantId);
            } else {
                $query->whereNull('product_variant_id');
            }
        }

        $row = $query->first([
            'qty_on_hand',
            'average_cost',
            'last_cost',
            'updated_at',
        ]);

        if (! $row) {
            return null;
        }

        return [
            'qty_on_hand' => (float) $row->qty_on_hand,
            'average_cost' => (float) $row->average_cost,
            'last_cost' => (float) $row->last_cost,
            'stock_value' => (float) $row->qty_on_hand * (float) $row->average_cost,
            'updated_at' => $row->updated_at,
        ];
    }

    private function resolveLastCostSource(string $tenantId, string $productId, string $locationId, ?string $productVariantId, bool $scopeByVariant): ?array
    {
        $query = DB::table('stock_ledger')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('qty_in', '>', 0)
            ->orderByDesc('created_at')
            ->orderByDesc('reference_id');

        if ($scopeByVariant) {
            if ($productVariantId !== null) {
                $query->where('product_variant_id', $productVariantId);
            } else {
                $query->whereNull('product_variant_id');
            }
        }

        $row = $query->first([
            'reference_type',
            'reference_id',
            'reference_item_id',
            'created_at',
        ]);

        if (! $row) {
            return null;
        }

        return [
            'reference_type' => (string) $row->reference_type,
            'reference_id' => (string) $row->reference_id,
            'reference_item_id' => $row->reference_item_id ? (string) $row->reference_item_id : null,
            'created_at' => $row->created_at,
            'label' => $this->resolveSourceLabel((string) $row->reference_type),
        ];
    }

    private function resolveSourceLabel(string $referenceType): string
    {
        return match (strtolower($referenceType)) {
            'purchase', 'purchase_receive' => 'Pembelian',
            'sale', 'sale_recipe_input' => 'Penjualan',
            'stock_adjustment' => 'Penyesuaian',
            'stock_opname' => 'Stock Opname',
            'stock_transfer_in', 'stock_transfer_out', 'stock_transfer' => 'Mutasi Stok',
            'production_batch' => 'Produksi',
            'opening_balance' => 'Stok Awal',
            default => $referenceType !== '' ? ucfirst($referenceType) : 'Sumber Stok',
        };
    }

    private function withSaleSourceMeta(object $row): object
    {
        $referenceType = strtolower((string) ($row->reference_type ?? ''));
        if ($referenceType === 'online_order') {
            $row->sales_channel = 'online';
            $row->sales_channel_label = 'Online';
            $row->source_label = 'Penjualan Online';

            return $row;
        }

        if (! in_array($referenceType, ['sale', 'sale_recipe_input'], true)) {
            return $row;
        }

        $channel = SaleChannel::fromIdempotencyKey($row->sale_idempotency_key ?? null);
        $row->sales_channel = $channel['channel'];
        $row->sales_channel_label = $channel['label'];
        $row->source_label = $channel['stock_label'];

        return $row;
    }
}
