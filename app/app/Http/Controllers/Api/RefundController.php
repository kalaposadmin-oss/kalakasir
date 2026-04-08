<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OperationIdempotencyStore;
use App\Services\RefundService;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class RefundController extends Controller
{
    public function __construct(private readonly RefundService $service)
    {
    }

    public function index(Request $request)
    {
        $tenantId = \App\Support\TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $query = \App\Models\Refund::query()
            ->leftJoin('sales', function ($join) {
                $join->on('refunds.sale_id', '=', 'sales.id')
                    ->on('refunds.tenant_id', '=', 'sales.tenant_id');
            })
            ->leftJoin('branches', function ($join) {
                $join->on('refunds.branch_id', '=', 'branches.id')
                    ->on('refunds.tenant_id', '=', 'branches.tenant_id');
            })
            ->leftJoin('users', function ($join) {
                $join->on('refunds.created_by', '=', 'users.id')
                    ->on('refunds.tenant_id', '=', 'users.tenant_id');
            })
            ->select([
                'refunds.id',
                'refunds.sale_id',
                'refunds.register_id',
                'refunds.shift_id',
                'refunds.branch_id',
                'refunds.subtotal',
                'refunds.item_discount_total',
                'refunds.transaction_discount_total',
                'refunds.tax_total',
                'refunds.refunded_cost_total',
                'refunds.payout_total',
                'refunds.total',
                'refunds.status',
                'refunds.reason',
                'refunds.created_at',
                'branches.name as branch_name',
                'users.name as cashier_name',
                'sales.total as sale_total',
            ])
            ->where('refunds.tenant_id', $tenantId)
            ->orderByDesc('refunds.created_at');

        if ($request->filled('branch_id')) {
            $query->where('refunds.branch_id', $request->query('branch_id'));
        }

        if ($request->filled('status')) {
            $query->where('refunds.status', $request->query('status'));
        }

        $limit = (int) $request->query('limit', 200);
        $limit = max(1, min($limit, 500));

        $refunds = $query->limit($limit)->get();
        $refundIds = $refunds->pluck('id')->all();

        $itemMap = [];
        if ($refundIds) {
            $items = \App\Models\RefundItem::query()
                ->where('tenant_id', $tenantId)
                ->select('refund_id')
                ->selectRaw('sum(qty) as qty_total')
                ->selectRaw('count(*) as line_count')
                ->whereIn('refund_id', $refundIds)
                ->groupBy('refund_id')
                ->get();

            foreach ($items as $item) {
                $itemMap[$item->refund_id] = [
                    'qty_total' => (float) $item->qty_total,
                    'line_count' => (int) $item->line_count,
                ];
            }
        }

        $rows = $refunds->map(function ($refund) use ($itemMap) {
            $items = $itemMap[$refund->id] ?? ['qty_total' => 0, 'line_count' => 0];

            return [
                'id' => $refund->id,
                'sale_id' => $refund->sale_id,
                'register_id' => $refund->register_id,
                'shift_id' => $refund->shift_id,
                'branch_id' => $refund->branch_id,
                'branch_name' => $refund->branch_name,
                'cashier_name' => $refund->cashier_name,
                'subtotal' => (float) ($refund->subtotal ?? 0),
                'item_discount_total' => (float) ($refund->item_discount_total ?? 0),
                'transaction_discount_total' => (float) ($refund->transaction_discount_total ?? 0),
                'tax_total' => (float) ($refund->tax_total ?? 0),
                'refunded_cost_total' => (float) ($refund->refunded_cost_total ?? 0),
                'payout_total' => (float) ($refund->payout_total ?? 0),
                'total' => (float) $refund->total,
                'status' => $refund->status,
                'reason' => $refund->reason,
                'created_at' => $refund->created_at,
                'sale_total' => (float) ($refund->sale_total ?? 0),
                'item_count' => $items['line_count'],
                'qty_total' => $items['qty_total'],
            ];
        });

        return ApiResponse::success([
            'refunds' => $rows,
        ], [
            'count' => $rows->count(),
            'limit' => $limit,
        ]);
    }

    public function show(Request $request, string $refund)
    {
        $tenantId = \App\Support\TenantContext::id();

        $refundRow = \App\Models\Refund::query()
            ->leftJoin('sales', function ($join) {
                $join->on('refunds.sale_id', '=', 'sales.id')
                    ->on('refunds.tenant_id', '=', 'sales.tenant_id');
            })
            ->leftJoin('branches', function ($join) {
                $join->on('refunds.branch_id', '=', 'branches.id')
                    ->on('refunds.tenant_id', '=', 'branches.tenant_id');
            })
            ->leftJoin('users', function ($join) {
                $join->on('refunds.created_by', '=', 'users.id')
                    ->on('refunds.tenant_id', '=', 'users.tenant_id');
            })
            ->select([
                'refunds.id',
                'refunds.sale_id',
                'refunds.register_id',
                'refunds.shift_id',
                'refunds.branch_id',
                'refunds.subtotal',
                'refunds.item_discount_total',
                'refunds.transaction_discount_total',
                'refunds.tax_total',
                'refunds.refunded_cost_total',
                'refunds.payout_total',
                'refunds.total',
                'refunds.status',
                'refunds.reason',
                'refunds.created_at',
                'branches.name as branch_name',
                'users.name as cashier_name',
                'sales.total as sale_total',
            ])
            ->when($tenantId, fn ($query) => $query->where('refunds.tenant_id', $tenantId))
            ->where('refunds.id', $refund)
            ->firstOrFail();

        $items = \App\Models\RefundItem::query()
            ->join('products', function ($join) {
                $join->on('products.id', '=', 'refund_items.product_id')
                    ->on('products.tenant_id', '=', 'refund_items.tenant_id');
            })
            ->select([
                'refund_items.id',
                'refund_items.sale_item_id',
                'refund_items.product_id',
                'refund_items.product_variant_id',
                'refund_items.location_id',
                'refund_items.qty',
                'refund_items.unit_price_snapshot',
                'refund_items.gross_amount',
                'refund_items.item_discount_amount',
                'refund_items.transaction_discount_amount',
                'refund_items.tax_amount',
                'refund_items.net_amount',
                'refund_items.hpp_snapshot',
                'refund_items.refunded_cost_total',
                'refund_items.amount',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.image_url as product_image',
            ])
            ->where('refund_items.tenant_id', $tenantId)
            ->where('refund_items.refund_id', $refund)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'sale_item_id' => $item->sale_item_id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'location_id' => $item->location_id,
                    'qty' => (float) $item->qty,
                    'unit_price_snapshot' => (float) ($item->unit_price_snapshot ?? 0),
                    'gross_amount' => (float) ($item->gross_amount ?? 0),
                    'item_discount_amount' => (float) ($item->item_discount_amount ?? 0),
                    'transaction_discount_amount' => (float) ($item->transaction_discount_amount ?? 0),
                    'tax_amount' => (float) ($item->tax_amount ?? 0),
                    'net_amount' => (float) ($item->net_amount ?? 0),
                    'hpp_snapshot' => (float) ($item->hpp_snapshot ?? 0),
                    'refunded_cost_total' => (float) ($item->refunded_cost_total ?? 0),
                    'amount' => (float) $item->amount,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'product_image' => $item->product_image,
                ];
            });

        $payments = \Illuminate\Support\Facades\DB::table('refund_payments')
            ->where('tenant_id', $tenantId)
            ->where('refund_id', $refund)
            ->orderBy('created_at')
            ->get([
                'id',
                'sale_payment_id',
                'account_id',
                'payment_method_id',
                'method',
                'amount',
                'created_at',
            ])
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'sale_payment_id' => $payment->sale_payment_id,
                    'account_id' => $payment->account_id,
                    'payment_method_id' => $payment->payment_method_id,
                    'method' => $payment->method,
                    'amount' => (float) $payment->amount,
                    'created_at' => $payment->created_at,
                ];
            })
            ->values();

        return response()->json([
            'refund' => [
                'id' => $refundRow->id,
                'sale_id' => $refundRow->sale_id,
                'register_id' => $refundRow->register_id,
                'shift_id' => $refundRow->shift_id,
                'branch_id' => $refundRow->branch_id,
                'branch_name' => $refundRow->branch_name,
                'cashier_name' => $refundRow->cashier_name,
                'subtotal' => (float) ($refundRow->subtotal ?? 0),
                'item_discount_total' => (float) ($refundRow->item_discount_total ?? 0),
                'transaction_discount_total' => (float) ($refundRow->transaction_discount_total ?? 0),
                'tax_total' => (float) ($refundRow->tax_total ?? 0),
                'refunded_cost_total' => (float) ($refundRow->refunded_cost_total ?? 0),
                'payout_total' => (float) ($refundRow->payout_total ?? 0),
                'total' => (float) $refundRow->total,
                'status' => $refundRow->status,
                'reason' => $refundRow->reason,
                'created_at' => $refundRow->created_at,
                'sale_total' => (float) ($refundRow->sale_total ?? 0),
            ],
            'items' => $items,
            'payments' => $payments,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

        $validated = $request->validate([
            'sale_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('sales', 'id')->where('tenant_id', $tenantId)
                : 'exists:sales,id'],
            'register_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('registers', 'id')->where('tenant_id', $tenantId)
                : 'exists:registers,id'],
            'shift_id' => ['nullable', 'uuid', $tenantId
                ? Rule::exists('shifts', 'id')->where('tenant_id', $tenantId)
                : 'exists:shifts,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'uuid', $tenantId
                ? Rule::exists('sale_items', 'id')->where('tenant_id', $tenantId)
                : 'exists:sale_items,id'],
            'items.*.product_id' => ['nullable', 'uuid', $tenantId
                ? Rule::exists('products', 'id')->where('tenant_id', $tenantId)
                : 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'uuid', $tenantId
                ? Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)
                : 'exists:product_variants,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'payments' => ['nullable', 'array', 'min:1'],
            'payments.*.account_id' => ['nullable', 'uuid'],
            'payments.*.payment_method_id' => ['nullable', 'uuid', $tenantId
                ? Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId)
                : 'exists:payment_methods,id'],
            'payments.*.method' => ['required_with:payments', 'string', 'max:50'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $idempotencyKey = $validated['idempotency_key'] ?? $request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key');
        $idempotencyKey = is_string($idempotencyKey) ? trim($idempotencyKey) : null;
        $idempotencyKey = $idempotencyKey !== '' ? $idempotencyKey : null;

        try {
            if ($idempotencyKey) {
                $result = app(OperationIdempotencyStore::class)->getOrRemember(
                    $this->idempotencyCacheKey((string) $tenantId, $idempotencyKey),
                    fn (): array => [
                        'refund_id' => $this->service->handle($validated, $request->user()?->id),
                        'idempotent' => false,
                    ]
                );

                return response()->json([
                    'refund_id' => $result['value']['refund_id'],
                    'idempotent' => $result['replayed'],
                ], $result['replayed'] ? 200 : 201);
            }

            $refundId = $this->service->handle($validated, $request->user()?->id);

            return response()->json([
                'refund_id' => $refundId,
                'idempotent' => false,
            ], 201);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'code' => 'INVALID_REFUND',
                'message' => $exception->getMessage(),
                'details' => [],
            ], 422);
        }
    }

    private function idempotencyCacheKey(string $tenantId, string $idempotencyKey): string
    {
        return 'pos:refund:'.sha1($tenantId.'|'.$idempotencyKey);
    }
}
