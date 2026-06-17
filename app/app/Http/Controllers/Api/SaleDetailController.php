<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Payment;
use App\Models\Receivable;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\SaleChannel;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaleDetailController extends Controller
{
    public function __invoke(Request $request, string $sale)
    {
        $tenantId = TenantContext::id();

        $saleRow = Sale::query()
            ->leftJoin('branches', function ($join) {
                $join->on('sales.branch_id', '=', 'branches.id')
                    ->on('sales.tenant_id', '=', 'branches.tenant_id');
            })
            ->leftJoin('customers', function ($join) {
                $join->on('sales.customer_id', '=', 'customers.id')
                    ->on('sales.tenant_id', '=', 'customers.tenant_id');
            })
            ->leftJoin('users', function ($join) {
                $join->on('sales.created_by', '=', 'users.id')
                    ->on('sales.tenant_id', '=', 'users.tenant_id');
            })
            ->leftJoin('users as void_users', function ($join) {
                $join->on('sales.voided_by', '=', 'void_users.id')
                    ->on('sales.tenant_id', '=', 'void_users.tenant_id');
            })
            ->leftJoin('users as approver_users', function ($join) {
                $join->on('sales.void_approved_by', '=', 'approver_users.id')
                    ->on('sales.tenant_id', '=', 'approver_users.tenant_id');
            })
            ->select([
                'sales.id',
                'sales.branch_id',
                'sales.shift_id',
                'sales.register_id',
                'sales.customer_id',
                'sales.order_holder_name',
                'sales.order_note',
                'sales.subtotal',
                'sales.discount',
                'sales.tax',
                'sales.total',
                'sales.idempotency_key',
                'sales.transaction_date',
                'sales.status',
                'sales.created_at',
                'sales.voided_by',
                'sales.void_approved_by',
                'sales.void_reason',
                'sales.voided_at',
                'sales.created_by',
                'branches.name as branch_name',
                'customers.name as customer_name',
                'users.name as cashier_name',
                'void_users.name as voided_by_name',
                'approver_users.name as void_approved_by_name',
            ])
            ->when($tenantId, fn ($query) => $query->where('sales.tenant_id', $tenantId))
            ->where('sales.id', $sale)
            ->firstOrFail();

        $items = SaleItem::query()
            ->leftJoin('products', function ($join) {
                $join->on('products.id', '=', 'sale_items.product_id')
                    ->on('products.tenant_id', '=', 'sale_items.tenant_id');
            })
            ->select([
                'sale_items.id',
                'sale_items.product_id',
                'sale_items.product_variant_id',
                'sale_items.qty',
                'sale_items.price',
                'sale_items.discount',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.image_url as product_image',
            ])
            ->when($tenantId, fn ($query) => $query->where('sale_items.tenant_id', $tenantId))
            ->where('sale_items.sale_id', $saleRow->id)
            ->get()
            ->map(function ($item) {
                $lineTotal = ((float) $item->price * (float) $item->qty) - (float) $item->discount;

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'qty' => (float) $item->qty,
                    'price' => (float) $item->price,
                    'discount' => (float) $item->discount,
                    'line_total' => $lineTotal,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'product_image' => $item->product_image,
                ];
            });

        $payments = Payment::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('sale_id', $saleRow->id)
            ->get(['id', 'account_id', 'payment_method_id', 'method', 'amount'])
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'account_id' => $payment->account_id,
                    'payment_method_id' => $payment->payment_method_id,
                    'method' => $payment->method,
                    'amount' => (float) $payment->amount,
                ];
            });

        $discounts = Discount::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('sale_id', $saleRow->id)
            ->orderBy('created_at')
            ->get(['type', 'value', 'notes'])
            ->map(function ($discount) {
                $notes = json_decode((string) $discount->notes, true);

                return [
                    'type' => $discount->type,
                    'value' => (float) $discount->value,
                    'notes' => is_array($notes) ? $notes : [],
                ];
            })
            ->values();

        $receivable = Receivable::query()
            ->with(['payments' => fn ($query) => $query->orderByDesc('created_at')])
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('sale_id', $saleRow->id)
            ->first();

        $channel = SaleChannel::fromIdempotencyKey($saleRow->idempotency_key);

        return response()->json([
            'sale' => [
                'id' => $saleRow->id,
                'branch_id' => $saleRow->branch_id,
                'shift_id' => $saleRow->shift_id,
                'register_id' => $saleRow->register_id,
                'branch_name' => $saleRow->branch_name,
                'customer_id' => $saleRow->customer_id,
                'customer_name' => $saleRow->customer_name,
                'order_holder_name' => $saleRow->order_holder_name,
                'order_note' => $saleRow->order_note,
                'cashier_id' => $saleRow->created_by,
                'cashier_name' => $saleRow->cashier_name,
                'subtotal' => (float) $saleRow->subtotal,
                'discount' => (float) $saleRow->discount,
                'tax' => (float) $saleRow->tax,
                'total' => (float) $saleRow->total,
                'sales_channel' => $channel['channel'],
                'sales_channel_label' => $channel['label'],
                'stock_source_label' => $channel['stock_label'],
                'transaction_date' => $saleRow->transaction_date,
                'status' => $saleRow->status,
                'created_at' => $saleRow->created_at,
                'voided_by' => $saleRow->voided_by,
                'voided_by_name' => $saleRow->voided_by_name,
                'void_approved_by' => $saleRow->void_approved_by,
                'void_approved_by_name' => $saleRow->void_approved_by_name,
                'void_reason' => $saleRow->void_reason,
                'voided_at' => $saleRow->voided_at,
            ],
            'items' => $items,
            'payments' => $payments,
            'discounts' => $discounts,
            'receivable' => $receivable ? [
                'id' => $receivable->id,
                'customer_id' => $receivable->customer_id,
                'total_amount' => (float) $receivable->total_amount,
                'paid_amount' => (float) $receivable->paid_amount,
                'remaining_amount' => (float) $receivable->remaining_amount,
                'due_date' => $receivable->due_date?->toDateString(),
                'status' => $receivable->status,
                'payments' => $receivable->payments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'created_at' => $payment->created_at,
                    'notes' => $payment->notes,
                ])->values(),
            ] : null,
        ]);
    }

    public function update(Request $request, string $sale)
    {
        $tenantId = TenantContext::id();

        $payload = $request->validate([
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $saleRow = Sale::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('id', $sale)
            ->firstOrFail();

        $saleRow->forceFill([
            'transaction_date' => $payload['transaction_date'],
        ])->save();

        return response()->json([
            'message' => 'Tanggal transaksi berhasil diperbarui.',
            'sale' => [
                'id' => $saleRow->id,
                'transaction_date' => $saleRow->transaction_date,
            ],
        ]);
    }
}
