<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\TenantContext;
use Illuminate\Http\Request;

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
                'sales.customer_id',
                'sales.subtotal',
                'sales.discount',
                'sales.tax',
                'sales.total',
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
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->select([
                'sale_items.id',
                'sale_items.qty',
                'sale_items.price',
                'sale_items.discount',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.image_url as product_image',
            ])
            ->where('sale_items.sale_id', $sale)
            ->get()
            ->map(function ($item) {
                $lineTotal = ((float) $item->price * (float) $item->qty) - (float) $item->discount;

                return [
                    'id' => $item->id,
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
            ->where('sale_id', $sale)
            ->get(['method', 'amount'])
            ->map(function ($payment) {
                return [
                    'method' => $payment->method,
                    'amount' => (float) $payment->amount,
                ];
            });

        return response()->json([
            'sale' => [
                'id' => $saleRow->id,
                'branch_id' => $saleRow->branch_id,
                'branch_name' => $saleRow->branch_name,
                'customer_id' => $saleRow->customer_id,
                'customer_name' => $saleRow->customer_name,
                'cashier_id' => $saleRow->created_by,
                'cashier_name' => $saleRow->cashier_name,
                'subtotal' => (float) $saleRow->subtotal,
                'discount' => (float) $saleRow->discount,
                'tax' => (float) $saleRow->tax,
                'total' => (float) $saleRow->total,
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
        ]);
    }
}
