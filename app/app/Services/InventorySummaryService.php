<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventorySummary;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class InventorySummaryService
{
    public function refresh(string $tenantId, string $locationId, array $productIds): void
    {
        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $productIds)
            ->get(['id']);

        foreach ($products as $product) {
            $qty = (float) InventoryBalance::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $product->id)
                ->where('location_id', $locationId)
                ->sum('qty_on_hand');

            // Product-level cache rolls up variant buckets by summing their authoritative valuation rows.
            $value = app(InventoryValuationService::class)->inventoryValueForScope(
                tenantId: $tenantId,
                locationId: $locationId,
                productId: (string) $product->id
            );

            $existing = InventorySummary::query()
                ->where('tenant_id', $tenantId)
                ->where('location_id', $locationId)
                ->where('product_id', $product->id)
                ->first();

            if (! $existing) {
                InventorySummary::query()->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'location_id' => $locationId,
                    'product_id' => $product->id,
                    'current_qty' => $qty,
                    'current_value' => $value,
                    'updated_at' => CarbonImmutable::now('UTC'),
                ]);

                continue;
            }

            $existing->update([
                'current_qty' => $qty,
                'current_value' => $value,
                'updated_at' => CarbonImmutable::now('UTC'),
            ]);
        }
    }
}
