<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\OnlineOrder;
use App\Models\OnlineOrderItem;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\StockLedger;
use App\Models\StockReservation;
use App\Models\Tenant;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OrderReservationService
{
    public function __construct(private readonly StockAvailabilityService $stockAvailability)
    {
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function reserve(OnlineOrder $order, array $items, ?string $actorUserId = null): void
    {
        $tenantId = (string) $order->tenant_id;
        $locationId = (string) ($order->location_id ?: '');

        if ($locationId === '') {
            throw new InvalidArgumentException('Lokasi default tenant belum tersedia.');
        }

        $expiresAt = now('UTC')->addHours(max(1, (int) config('services.order_online.hold_ttl_hours', 24)));

        foreach ($items as $item) {
            $productId = (string) Arr::get($item, 'product_id', '');
            $qty = (int) Arr::get($item, 'qty', 0);
            $productVariantId = Arr::get($item, 'product_variant_id');

            if ($productId === '' || $qty <= 0) {
                continue;
            }

            $product = Product::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $productId)
                ->firstOrFail();

            if ((string) $product->item_type === 'raw_material' || (string) $product->product_mode === 'add_on') {
                throw new InvalidArgumentException('Produk ini tidak dapat dipesan melalui katalog online.');
            }

            if (! (bool) $product->track_stock) {
                continue;
            }

            $available = $this->stockAvailability->availableQty($tenantId, $productId, $productVariantId ? (string) $productVariantId : null, $locationId);
            if ($available < $qty) {
                throw new InsufficientStockException($productId, $locationId);
            }

            StockReservation::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'online_order_id' => $order->id,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId ?: null,
                'location_id' => $locationId,
                'qty' => $qty,
                'status' => 'active',
                'expires_at' => $expiresAt,
            ]);
        }
    }

    public function release(OnlineOrder $order): void
    {
        StockReservation::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('online_order_id', $order->id)
            ->where('status', 'active')
            ->update([
                'status' => 'released',
                'updated_at' => now('UTC'),
            ]);
    }

    /**
     * @param  array<string, string>  $saleItemIdsByKey
     */
    public function convert(OnlineOrder $order, ?string $actorUserId = null, ?string $saleId = null, array $saleItemIdsByKey = []): void
    {
        $reservations = StockReservation::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('online_order_id', $order->id)
            ->where('status', 'active')
            ->get();

        if ($reservations->isEmpty()) {
            return;
        }

        $now = CarbonImmutable::now('UTC');

        DB::transaction(function () use ($reservations, $order, $actorUserId, $now, $saleId, $saleItemIdsByKey): void {
            foreach ($reservations as $reservation) {
                $productVariantId = $reservation->product_variant_id ? (string) $reservation->product_variant_id : null;
                $saleItemId = $saleItemIdsByKey[$this->itemKey((string) $reservation->product_id, $productVariantId)] ?? null;
                $unitCost = '0';
                $totalCost = '0';

                $affected = $this->decrementInventory(
                    tenantId: (string) $reservation->tenant_id,
                    productId: (string) $reservation->product_id,
                    productVariantId: $productVariantId,
                    locationId: (string) $reservation->location_id,
                    qty: (int) $reservation->qty
                );

                if ($affected === 0) {
                    throw new InsufficientStockException((string) $reservation->product_id, (string) $reservation->location_id);
                }

                if ($saleId && $saleItemId) {
                    $valuationResult = app(InventoryValuationService::class)->issue(
                        tenantId: (string) $reservation->tenant_id,
                        productId: (string) $reservation->product_id,
                        productVariantId: $productVariantId,
                        locationId: (string) $reservation->location_id,
                        qty: (float) $reservation->qty,
                        referenceType: 'sale',
                        referenceId: $saleId,
                        referenceItemId: $saleItemId,
                        at: $now
                    );

                    $unitCost = Decimal::cost($valuationResult['unit_cost'] ?? 0);
                    $totalCost = Decimal::money($valuationResult['total_cost'] ?? 0);

                    DB::table('sale_items')
                        ->where('tenant_id', $reservation->tenant_id)
                        ->where('id', $saleItemId)
                        ->update(['hpp_snapshot' => $unitCost]);
                }

                $balanceAfter = Decimal::qty(DB::table('inventory_balances')
                    ->where('tenant_id', $reservation->tenant_id)
                    ->where('product_id', $reservation->product_id)
                    ->where('location_id', $reservation->location_id)
                    ->when($reservation->product_variant_id, function ($query) use ($reservation) {
                        $query->where('product_variant_id', $reservation->product_variant_id);
                    }, function ($query) {
                        $query->whereNull('product_variant_id');
                    })
                    ->value('qty_on_hand') ?? 0);

                StockLedger::query()->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $reservation->tenant_id,
                    'product_id' => $reservation->product_id,
                    'product_variant_id' => $reservation->product_variant_id,
                    'location_id' => $reservation->location_id,
                    'reference_type' => $saleId ? 'sale' : 'online_order',
                    'reference_id' => $saleId ?: $order->id,
                    'reference_item_id' => $saleItemId,
                    'qty_in' => 0,
                    'qty_out' => (int) $reservation->qty,
                    'balance_after' => $balanceAfter,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'created_at' => $now,
                    'created_by' => $actorUserId,
                ]);
            }

            StockReservation::query()
                ->whereIn('id', $reservations->pluck('id')->all())
                ->update([
                    'status' => 'converted',
                    'updated_at' => $now,
                ]);
        });
    }

    public function releaseByOrder(OnlineOrder $order): void
    {
        $this->release($order);
    }

    public function orderItemsForReservation(OnlineOrder $order): array
    {
        return OnlineOrderItem::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('online_order_id', $order->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (OnlineOrderItem $item) => [
                'product_id' => $item->product_id,
                'qty' => (int) $item->qty,
                'product_variant_id' => $item->product_variant_id,
            ])
            ->all();
    }

    private function decrementInventory(string $tenantId, string $productId, ?string $productVariantId, string $locationId, int $qty): int
    {
        if ($productVariantId) {
            return DB::update(
                'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND product_variant_id = ? AND location_id = ? AND qty_on_hand >= ?',
                [$qty, $tenantId, $productId, $productVariantId, $locationId, $qty]
            );
        }

        return DB::update(
            'UPDATE inventory_balances SET qty_on_hand = qty_on_hand - ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND product_variant_id IS NULL AND qty_on_hand >= ?',
            [$qty, $tenantId, $productId, $locationId, $qty]
        );
    }

    private function itemKey(string $productId, ?string $productVariantId): string
    {
        return $productId.'|'.($productVariantId ?: 'base');
    }
}
