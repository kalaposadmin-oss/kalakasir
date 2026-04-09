<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\StockTransferService;
use App\Services\AnomalyService;
use App\Support\TenantContext;
use App\Exceptions\LockPeriodException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class StockTransferController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id();

        $transfers = StockTransfer::query()
            ->where('stock_transfers.tenant_id', $tenantId)
            ->leftJoin('branches as from_branch', function ($join) {
                $join->on('stock_transfers.from_branch_id', '=', 'from_branch.id')
                    ->on('stock_transfers.tenant_id', '=', 'from_branch.tenant_id');
            })
            ->leftJoin('branches as to_branch', function ($join) {
                $join->on('stock_transfers.to_branch_id', '=', 'to_branch.id')
                    ->on('stock_transfers.tenant_id', '=', 'to_branch.tenant_id');
            })
            ->select([
                'stock_transfers.id',
                'stock_transfers.status',
                'stock_transfers.reference',
                'stock_transfers.created_at',
                'from_branch.name as from_branch_name',
                'to_branch.name as to_branch_name',
            ])
            ->orderByDesc('stock_transfers.created_at')
            ->get();

        $transferIds = $transfers->pluck('id')->all();
        $itemCounts = [];
        $qtyCounts = [];

        if ($transferIds) {
            $items = StockTransferItem::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('stock_transfer_id', $transferIds)
                ->select('stock_transfer_id')
                ->selectRaw('count(*) as item_count')
                ->selectRaw('sum(qty) as qty_total')
                ->groupBy('stock_transfer_id')
                ->get();

            foreach ($items as $item) {
                $itemCounts[$item->stock_transfer_id] = (int) $item->item_count;
                $qtyCounts[$item->stock_transfer_id] = (float) $item->qty_total;
            }
        }

        $rows = $transfers->map(function ($transfer) use ($itemCounts, $qtyCounts) {
            return [
                'id' => $transfer->id,
                'reference' => $transfer->reference,
                'status' => $transfer->status,
                'created_at' => $transfer->created_at,
                'from_branch' => $transfer->from_branch_name,
                'to_branch' => $transfer->to_branch_name,
                'item_count' => $itemCounts[$transfer->id] ?? 0,
                'qty_total' => $qtyCounts[$transfer->id] ?? 0,
            ];
        });

        $stats = [
            'approved' => $rows->where('status', 'approved')->count(),
            'in_transit' => $rows->where('status', 'in_transit')->count(),
            'received' => $rows->where('status', 'received')->count(),
        ];

        return response()->json([
            'stats' => $stats,
            'transfers' => $rows,
        ]);
    }

    public function store(Request $request, StockTransferService $service)
    {
        $tenantId = TenantContext::id();

        $payload = $request->validate([
            'from_branch_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('branches', 'id')->where('tenant_id', $tenantId)
                : 'exists:branches,id'],
            'to_branch_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('branches', 'id')->where('tenant_id', $tenantId)
                : 'exists:branches,id'],
            'from_location_id' => ['nullable', 'uuid', $tenantId
                ? Rule::exists('locations', 'id')->where('tenant_id', $tenantId)
                : 'exists:locations,id'],
            'to_location_id' => ['nullable', 'uuid', $tenantId
                ? Rule::exists('locations', 'id')->where('tenant_id', $tenantId)
                : 'exists:locations,id'],
            'transaction_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('products', 'id')->where('tenant_id', $tenantId)
                : 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'uuid', $tenantId
                ? Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)
                : 'exists:product_variants,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $transferId = $service->create($payload, $request->user()?->id);

            return response()->json([
                'transfer_id' => $transferId,
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'TRANSFER_CREATE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function show(string $transfer)
    {
        $tenantId = TenantContext::id();

        $transferRow = StockTransfer::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $transfer)
            ->firstOrFail();

        $items = StockTransferItem::query()
            ->join('products', 'products.id', '=', 'stock_transfer_items.product_id')
            ->select([
                'stock_transfer_items.id',
                'stock_transfer_items.qty',
                'stock_transfer_items.product_variant_id',
                'products.id as product_id',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.image_url as product_image',
            ])
            ->where('stock_transfer_items.tenant_id', $tenantId)
            ->where('stock_transfer_items.stock_transfer_id', $transferRow->id)
            ->get();

        $fromBranch = Branch::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $transferRow->from_branch_id)
            ->first();

        $toBranch = Branch::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $transferRow->to_branch_id)
            ->first();

        return response()->json([
            'transfer' => [
                'id' => $transferRow->id,
                'status' => $transferRow->status,
                'reference' => $transferRow->reference,
                'from_branch_id' => $transferRow->from_branch_id,
                'to_branch_id' => $transferRow->to_branch_id,
                'from_location_id' => $transferRow->from_location_id,
                'to_location_id' => $transferRow->to_location_id,
                'created_at' => $transferRow->created_at,
            ],
            'from_branch' => $fromBranch ? [
                'id' => $fromBranch->id,
                'name' => $fromBranch->name,
            ] : null,
            'to_branch' => $toBranch ? [
                'id' => $toBranch->id,
                'name' => $toBranch->name,
            ] : null,
            'items' => $items,
        ]);
    }

    public function approve(Request $request, string $transfer, StockTransferService $service)
    {
        try {
            $service->approve($transfer, $request->user()?->id);

            return response()->json([
                'status' => 'approved',
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'TRANSFER_APPROVE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function ship(Request $request, string $transfer, StockTransferService $service)
    {
        $payload = $request->validate([
            'transaction_date' => ['nullable', 'date'],
        ]);

        try {
            $service->ship($transfer, $request->user()?->id, $payload['transaction_date'] ?? null);

            return response()->json([
                'status' => 'in_transit',
            ]);
        } catch (LockPeriodException $exception) {
            return response()->json([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => [],
            ], $exception->status());
        } catch (InvalidArgumentException $exception) {
            $tenantId = TenantContext::id();
            if ($tenantId) {
                app(AnomalyService::class)->raise(
                    tenantId: $tenantId,
                    severity: 'low',
                    type: 'invalid_transaction_date_attempt',
                    payload: [
                        'source' => 'transfer_ship',
                        'message' => $exception->getMessage(),
                    ]
                );
            }

            return response()->json([
                'code' => 'INVALID_TRANSACTION_DATE',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'TRANSFER_SHIP_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function receive(Request $request, string $transfer, StockTransferService $service)
    {
        $payload = $request->validate([
            'transaction_date' => ['nullable', 'date'],
        ]);

        try {
            $service->receive($transfer, $request->user()?->id, $payload['transaction_date'] ?? null);

            return response()->json([
                'status' => 'received',
            ]);
        } catch (LockPeriodException $exception) {
            return response()->json([
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => [],
            ], $exception->status());
        } catch (InvalidArgumentException $exception) {
            $tenantId = TenantContext::id();
            if ($tenantId) {
                app(AnomalyService::class)->raise(
                    tenantId: $tenantId,
                    severity: 'low',
                    type: 'invalid_transaction_date_attempt',
                    payload: [
                        'source' => 'transfer_receive',
                        'message' => $exception->getMessage(),
                    ]
                );
            }

            return response()->json([
                'code' => 'INVALID_TRANSACTION_DATE',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'TRANSFER_RECEIVE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function close(Request $request, string $transfer, StockTransferService $service)
    {
        try {
            $service->close($transfer, $request->user()?->id);

            return response()->json([
                'status' => 'closed',
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'TRANSFER_CLOSE_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    public function cancel(Request $request, string $transfer, StockTransferService $service)
    {
        try {
            $service->cancel($transfer, $request->user()?->id);

            return response()->json([
                'status' => 'cancelled',
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'code' => 'TRANSFER_CANCEL_FAILED',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }
}
