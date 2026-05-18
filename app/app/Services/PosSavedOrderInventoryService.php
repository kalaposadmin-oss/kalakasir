<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\PosSavedOrder;
use App\Models\Product;
use App\Models\StockLedger;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PosSavedOrderInventoryService
{
    public function reserve(PosSavedOrder $order, array $items, ?string $userId, CarbonImmutable $now): void
    {
        $this->applyReservation($order, $items, $userId, $now, direction: 'out');
    }

    public function release(PosSavedOrder $order, array $items, ?string $userId, CarbonImmutable $now): void
    {
        $this->applyReservation($order, $items, $userId, $now, direction: 'in');
    }

    private function applyReservation(
        PosSavedOrder $order,
        array $items,
        ?string $userId,
        CarbonImmutable $now,
        string $direction
    ): void {
        $tenantId = (string) $order->tenant_id;
        $locationId = $this->resolveLocationId($order);

        if (! $locationId) {
            throw new InvalidArgumentException('Lokasi transaksi belum tersedia untuk simpan pesanan.');
        }

        foreach ($items as $item) {
            $productId = (string) Arr::get($item, 'product_id', '');
            if ($productId === '') {
                continue;
            }
            $qty = Decimal::qty(Arr::get($item, 'qty', 0));
            if (Decimal::compare($qty, 0) <= 0) {
                continue;
            }

            $product = Product::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $productId)
                ->firstOrFail();

            if ((string) $product->item_type === 'raw_material') {
                throw new InvalidArgumentException('Produk raw material tidak dapat disimpan untuk pesanan.');
            }

            if (! $product->track_stock) {
                continue;
            }

            $productVariantId = $this->resolveVariantId($tenantId, $productId, Arr::get($item, 'product_variant_id'));

            if ($direction === 'out') {
                $this->ensureInventoryBalanceBucket($tenantId, $productId, $productVariantId, $locationId);
                $affected = $this->decrementInventory($tenantId, $productId, $productVariantId, $locationId, $qty);
                if ($affected === 0) {
                    throw new InsufficientStockException($productId, $locationId);
                }
            } else {
                $this->ensureInventoryBalanceBucket($tenantId, $productId, $productVariantId, $locationId);
                $this->incrementInventory($tenantId, $productId, $productVariantId, $locationId, $qty);
            }

            $balanceAfter = Decimal::qty(DB::table('inventory_balances')
                ->where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('location_id', $locationId)
                ->when($productVariantId, function ($query) use ($productVariantId) {
                    $query->where('product_variant_id', $productVariantId);
                }, function ($query) {
                    $query->whereNull('product_variant_id');
                })
                ->value('qty_on_hand'));

            StockLedger::query()->create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'location_id' => $locationId,
                'reference_type' => 'pos_saved_order',
                'reference_id' => (string) $order->id,
                'qty_in' => $direction === 'in' ? $qty : 0,
                'qty_out' => $direction === 'out' ? $qty : 0,
                'balance_after' => $balanceAfter,
                'created_at' => $now,
                'created_by' => $userId,
            ]);
        }
    }

    private function resolveLocationId(PosSavedOrder $order): ?string
    {
        if (! empty($order->location_id)) {
            return (string) $order->location_id;
        }

        $payload = (array) ($order->payload ?? []);
        if (! empty($payload['location_id'])) {
            return (string) $payload['location_id'];
        }

        $branchId = (string) ($order->branch_id ?? '');
        if ($branchId === '') {
            return null;
        }

        return Location::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('branch_id', $branchId)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->value('id');
    }

    private function resolveVariantId(string $tenantId, string $productId, ?string $variantId): ?string
    {
        if ($variantId) {
            $exists = DB::table('product_variants')
                ->where('tenant_id', $tenantId)
                ->where('id', $variantId)
                ->where('product_id', $productId)
                ->exists();

            if (! $exists) {
                throw new InvalidArgumentException('Varian produk tidak valid.');
            }

            return $variantId;
        }

        $hasVariants = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->exists();

        if ($hasVariants) {
            throw new InvalidArgumentException('Produk varian memerlukan product_variant_id.');
        }

        return null;
    }

    private function ensureInventoryBalanceBucket(string $tenantId, string $productId, ?string $productVariantId, string $locationId): void
    {
        InventoryBalance::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'product_variant_id' => $productVariantId,
                'location_id' => $locationId,
            ],
            [
                'qty_on_hand' => 0,
            ]
        );
    }

    private function decrementInventory(string $tenantId, string $productId, ?string $productVariantId, string $locationId, float $qty): int
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

    private function incrementInventory(string $tenantId, string $productId, ?string $productVariantId, string $locationId, float $qty): void
    {
        if ($productVariantId) {
            DB::update(
                'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND product_variant_id = ? AND location_id = ?',
                [$qty, $tenantId, $productId, $productVariantId, $locationId]
            );
            return;
        }

        DB::update(
            'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND location_id = ? AND product_variant_id IS NULL',
            [$qty, $tenantId, $productId, $locationId]
        );
    }
}
