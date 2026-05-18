<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\LockPeriodException;
use App\Http\Controllers\Controller;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\AnomalyService;
use App\Services\StockOpnameService;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class StockOpnameController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $status = $request->query('status');
        $limit = max(1, min((int) $request->query('limit', 50), 100));

        $rows = DB::table('stock_opnames')
            ->leftJoin('locations', function ($join) {
                $join->on('stock_opnames.location_id', '=', 'locations.id')
                    ->on('stock_opnames.tenant_id', '=', 'locations.tenant_id');
            })
            ->leftJoin('users as creator', 'stock_opnames.created_by', '=', 'creator.id')
            ->leftJoin('users as finalizer', 'stock_opnames.finalized_by', '=', 'finalizer.id')
            ->where('stock_opnames.tenant_id', $tenantId)
            ->when($status, fn ($query) => $query->where('stock_opnames.status', $status))
            ->select([
                'stock_opnames.id',
                'stock_opnames.location_id',
                'stock_opnames.opname_date',
                'stock_opnames.status',
                'stock_opnames.notes',
                'stock_opnames.created_at',
                'stock_opnames.finalized_at',
                'locations.name as location_name',
                'creator.name as created_by_name',
                'finalizer.name as finalized_by_name',
            ])
            ->orderByDesc('stock_opnames.opname_date')
            ->orderByDesc('stock_opnames.created_at')
            ->limit($limit)
            ->get();

        $aggregates = $this->aggregateItems($tenantId, $rows->pluck('id')->all());

        return response()->json([
            'opnames' => $rows->map(fn ($row) => $this->serializeOpnameRow($row, $aggregates[$row->id] ?? []))->values(),
        ]);
    }

    public function history(Request $request)
    {
        return $this->index($request);
    }

    public function show(string $opname)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $row = $this->findOpnameRow($tenantId, $opname);
        $aggregates = $this->aggregateItems($tenantId, [$opname]);

        return response()->json([
            'opname' => $this->serializeOpnameRow($row, $aggregates[$opname] ?? []),
            'items' => $this->serializeItems($tenantId, $opname),
        ]);
    }

    public function store(Request $request, StockOpnameService $service)
    {
        $tenantId = TenantContext::id();

        $payload = $request->validate([
            'location_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('locations', 'id')->where('tenant_id', $tenantId)
                : 'exists:locations,id'],
            'opname_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $opnameId = $service->createDraft($payload, $request->user()?->id);

            return response()->json([
                'opname_id' => $opnameId,
                'status' => StockOpname::STATUS_DRAFT,
            ], 201);
        } catch (InvalidArgumentException $exception) {
            if ($tenantId) {
                app(AnomalyService::class)->raise(
                    tenantId: $tenantId,
                    severity: 'low',
                    type: 'invalid_transaction_date_attempt',
                    payload: [
                        'source' => 'stock_opname_create',
                        'message' => $exception->getMessage(),
                    ]
                );
            }

            return response()->json([
                'code' => 'INVALID_OPNAME_DATE',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'STOCK_OPNAME_CREATE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function updateItem(Request $request, string $opname, string $item, StockOpnameService $service)
    {
        $payload = $request->validate([
            'physical_qty' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $updated = $service->updateItem($opname, $item, $payload);

            return response()->json([
                'item' => $this->serializeItemModel($updated),
                'totals' => $this->aggregateItems(TenantContext::id() ?: '', [$opname])[$opname] ?? [],
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'STOCK_OPNAME_ITEM_UPDATE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function finalize(Request $request, string $opname, StockOpnameService $service)
    {
        $payload = $request->validate([
            'allow_partial' => ['nullable', 'boolean'],
        ]);

        try {
            $service->finalize($opname, $request->user()?->id, (bool) ($payload['allow_partial'] ?? false));

            return response()->json([
                'status' => StockOpname::STATUS_FINALIZED,
            ]);
        } catch (LockPeriodException $exception) {
            return response()->json([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => [],
            ], $exception->status());
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'STOCK_OPNAME_FINALIZE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function finalizeCheck(Request $request, string $opname, StockOpnameService $service)
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = (int) $request->query('per_page', 20);

            $result = $service->finalizeCheck($opname, $page, $perPage);

            return response()->json($result);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'STOCK_OPNAME_FINALIZE_CHECK_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function cancel(Request $request, string $opname, StockOpnameService $service)
    {
        try {
            $service->cancel($opname, $request->user()?->id);

            return response()->json([
                'status' => StockOpname::STATUS_CANCELLED,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'STOCK_OPNAME_CANCEL_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    private function findOpnameRow(string $tenantId, string $opnameId): object
    {
        return DB::table('stock_opnames')
            ->leftJoin('locations', function ($join) {
                $join->on('stock_opnames.location_id', '=', 'locations.id')
                    ->on('stock_opnames.tenant_id', '=', 'locations.tenant_id');
            })
            ->leftJoin('users as creator', 'stock_opnames.created_by', '=', 'creator.id')
            ->leftJoin('users as finalizer', 'stock_opnames.finalized_by', '=', 'finalizer.id')
            ->where('stock_opnames.tenant_id', $tenantId)
            ->where('stock_opnames.id', $opnameId)
            ->select([
                'stock_opnames.id',
                'stock_opnames.location_id',
                'stock_opnames.opname_date',
                'stock_opnames.status',
                'stock_opnames.notes',
                'stock_opnames.created_at',
                'stock_opnames.finalized_at',
                'stock_opnames.cancelled_at',
                'locations.name as location_name',
                'creator.name as created_by_name',
                'finalizer.name as finalized_by_name',
            ])
            ->firstOrFail();
    }

    private function aggregateItems(string $tenantId, array $opnameIds): array
    {
        if ($tenantId === '' || $opnameIds === []) {
            return [];
        }

        return DB::table('stock_opname_items')
            ->where('tenant_id', $tenantId)
            ->whereIn('stock_opname_id', $opnameIds)
            ->select('stock_opname_id')
            ->selectRaw('COUNT(*) as item_count')
            ->selectRaw('SUM(CASE WHEN physical_qty IS NOT NULL THEN 1 ELSE 0 END) as counted_item_count')
            ->selectRaw('COALESCE(SUM(difference_qty), 0) as total_difference_qty')
            ->selectRaw('COALESCE(SUM(difference_value), 0) as total_difference_value')
            ->groupBy('stock_opname_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->stock_opname_id => [
                    'item_count' => (int) $row->item_count,
                    'counted_item_count' => (int) $row->counted_item_count,
                    'total_difference_qty' => (float) $row->total_difference_qty,
                    'total_difference_value' => (float) $row->total_difference_value,
                ],
            ])
            ->all();
    }

    private function serializeOpnameRow(object $row, array $aggregate): array
    {
        $status = (string) $row->status;
        $countedItemCount = (int) ($aggregate['counted_item_count'] ?? 0);

        return [
            'id' => $row->id,
            'location_id' => $row->location_id,
            'location_name' => $row->location_name,
            'opname_date' => $row->opname_date,
            'status' => $status,
            'notes' => $row->notes,
            'created_at' => $row->created_at,
            'created_by_name' => $row->created_by_name,
            'finalized_at' => $row->finalized_at ?? null,
            'finalized_by_name' => $row->finalized_by_name ?? null,
            'item_count' => (int) ($aggregate['item_count'] ?? 0),
            'counted_item_count' => $countedItemCount,
            'total_difference_qty' => (float) ($aggregate['total_difference_qty'] ?? 0),
            'total_difference_value' => (float) ($aggregate['total_difference_value'] ?? 0),
            'can_edit' => $status === StockOpname::STATUS_DRAFT,
            'can_finalize' => $status === StockOpname::STATUS_DRAFT && $countedItemCount > 0,
            'can_cancel' => $status === StockOpname::STATUS_DRAFT,
        ];
    }

    private function serializeItems(string $tenantId, string $opnameId): array
    {
        return DB::table('stock_opname_items')
            ->join('products', function ($join) {
                $join->on('stock_opname_items.product_id', '=', 'products.id')
                    ->on('stock_opname_items.tenant_id', '=', 'products.tenant_id');
            })
            ->leftJoin('product_variants', function ($join) {
                $join->on('stock_opname_items.product_variant_id', '=', 'product_variants.id')
                    ->on('stock_opname_items.tenant_id', '=', 'product_variants.tenant_id');
            })
            ->where('stock_opname_items.tenant_id', $tenantId)
            ->where('stock_opname_items.stock_opname_id', $opnameId)
            ->select([
                'stock_opname_items.id',
                'stock_opname_items.product_id',
                'stock_opname_items.product_variant_id',
                'stock_opname_items.system_qty',
                'stock_opname_items.physical_qty',
                'stock_opname_items.difference_qty',
                'stock_opname_items.unit_cost',
                'stock_opname_items.difference_value',
                'stock_opname_items.notes',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.unit as product_unit',
                'product_variants.name as variant_name',
                'product_variants.sku as variant_sku',
            ])
            ->orderBy('products.name')
            ->orderBy('product_variants.name')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'product_id' => $row->product_id,
                'product_variant_id' => $row->product_variant_id,
                'product_name' => $row->product_name,
                'product_sku' => $row->variant_sku ?: $row->product_sku,
                'variant_name' => $row->variant_name,
                'unit' => $row->product_unit,
                'system_qty' => (float) $row->system_qty,
                'physical_qty' => $row->physical_qty === null ? null : (float) $row->physical_qty,
                'difference_qty' => (float) $row->difference_qty,
                'unit_cost' => (float) $row->unit_cost,
                'difference_value' => (float) $row->difference_value,
                'notes' => $row->notes,
                'is_counted' => $row->physical_qty !== null,
            ])
            ->values()
            ->all();
    }

    private function serializeItemModel(StockOpnameItem $item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'system_qty' => (float) $item->system_qty,
            'physical_qty' => $item->physical_qty === null ? null : (float) $item->physical_qty,
            'difference_qty' => (float) $item->difference_qty,
            'unit_cost' => (float) $item->unit_cost,
            'difference_value' => (float) $item->difference_value,
            'notes' => $item->notes,
        ];
    }
}
