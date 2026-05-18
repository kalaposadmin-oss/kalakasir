<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Tenant;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class StockAvailabilityService
{
    public function resolveDefaultLocationId(Tenant $tenant): ?string
    {
        if (! empty($tenant->default_location_id)) {
            return (string) $tenant->default_location_id;
        }

        if (! $tenant->exists) {
            return null;
        }

        return Location::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->value('id');
    }

    public function availableQty(string $tenantId, string $productId, ?string $productVariantId, string $locationId): float
    {
        $this->expireStaleReservations($tenantId);

        $balance = DB::table('inventory_balances')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->when($productVariantId !== null && $productVariantId !== '', function ($query) use ($productVariantId) {
                $query->where('product_variant_id', $productVariantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            })
            ->value('qty_on_hand');

        $reserved = DB::table('stock_reservations')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('status', 'active')
            ->when($productVariantId !== null && $productVariantId !== '', function ($query) use ($productVariantId) {
                $query->where('product_variant_id', $productVariantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now('UTC'));
            })
            ->sum('qty');

        return max(0, Decimal::float($balance ?? 0) - Decimal::float($reserved ?? 0));
    }

    public function expireStaleReservations(string $tenantId): int
    {
        return DB::table('stock_reservations')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now('UTC'))
            ->update([
                'status' => 'expired',
                'updated_at' => now('UTC'),
            ]);
    }

    public function availableByProductIds(string $tenantId, string $locationId, array $productIds): array
    {
        $this->expireStaleReservations($tenantId);

        if ($productIds === []) {
            return [];
        }

        $balances = DB::table('inventory_balances')
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'product_variant_id', 'qty_on_hand']);

        $reserved = DB::table('stock_reservations')
            ->where('tenant_id', $tenantId)
            ->where('location_id', $locationId)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now('UTC'));
            })
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'product_variant_id', 'qty'])
            ->groupBy(function ($row) {
                return $row->product_id.'|'.($row->product_variant_id ?? 'null');
            })
            ->map(fn ($rows) => $rows->sum('qty'));

        $result = [];

        foreach ($balances as $balance) {
            $key = $balance->product_id.'|'.($balance->product_variant_id ?? 'null');
            $reservedQty = (float) ($reserved[$key] ?? 0);
            $result[$key] = max(0, Decimal::float($balance->qty_on_hand) - $reservedQty);
        }

        return $result;
    }
}
