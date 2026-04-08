<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('refunds')
            ->where(function ($query) {
                $query->whereNull('subtotal')
                    ->orWhere('subtotal', 0);
            })
            ->update([
                'subtotal' => DB::raw('total'),
                'payout_total' => DB::raw('CASE WHEN payout_total = 0 THEN total ELSE payout_total END'),
            ]);

        $refundItems = DB::table('refund_items')
            ->join('refunds', function ($join) {
                $join->on('refunds.id', '=', 'refund_items.refund_id')
                    ->on('refunds.tenant_id', '=', 'refund_items.tenant_id');
            })
            ->whereNull('refund_items.sale_item_id')
            ->select([
                'refund_items.id',
                'refund_items.tenant_id',
                'refund_items.refund_id',
                'refund_items.product_id',
                'refund_items.qty',
                'refund_items.amount',
                'refunds.sale_id',
            ])
            ->orderBy('refund_items.id')
            ->get();

        foreach ($refundItems as $refundItem) {
            $saleItems = DB::table('sale_items')
                ->where('tenant_id', $refundItem->tenant_id)
                ->where('sale_id', $refundItem->sale_id)
                ->where('product_id', $refundItem->product_id)
                ->orderBy('id')
                ->get(['id', 'product_variant_id', 'price', 'discount', 'qty', 'hpp_snapshot']);

            if ($saleItems->count() !== 1) {
                continue;
            }

            $saleItem = $saleItems->first();
            $locationId = DB::table('stock_ledger')
                ->where('tenant_id', $refundItem->tenant_id)
                ->where('reference_type', 'sale')
                ->where('reference_item_id', $saleItem->id)
                ->orderBy('created_at')
                ->value('location_id');

            $grossAmount = (float) ($refundItem->amount ?? 0);
            $itemDiscountAmount = 0.0;
            if ((float) $saleItem->qty > 0 && (float) $saleItem->discount > 0) {
                $itemDiscountAmount = round(((float) $saleItem->discount / (float) $saleItem->qty) * (float) $refundItem->qty, 2);
            }

            DB::table('refund_items')
                ->where('tenant_id', $refundItem->tenant_id)
                ->where('id', $refundItem->id)
                ->update([
                    'sale_item_id' => $saleItem->id,
                    'product_variant_id' => $saleItem->product_variant_id,
                    'location_id' => $locationId,
                    'unit_price_snapshot' => (float) $saleItem->price,
                    'gross_amount' => $grossAmount,
                    'item_discount_amount' => $itemDiscountAmount,
                    'transaction_discount_amount' => 0,
                    'tax_amount' => 0,
                    'net_amount' => max($grossAmount - $itemDiscountAmount, 0),
                    'hpp_snapshot' => (float) ($saleItem->hpp_snapshot ?? 0),
                    'refunded_cost_total' => round((float) ($saleItem->hpp_snapshot ?? 0) * (float) $refundItem->qty, 2),
                ]);
        }
    }

    public function down(): void
    {
        // Intentional no-op: this migration backfills historical snapshots safely.
    }
};