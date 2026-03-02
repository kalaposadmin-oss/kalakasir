<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventoryCostState;
use App\Models\InventoryLayer;
use App\Models\InventorySummary;
use App\Models\Product;
use App\Services\InventoryValuationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventorySummaryService
{
    public function refresh(string $tenantId, string $locationId, array $productIds): void
    {
        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $productIds)
            ->get(['id', 'hpp_average']);

        foreach ($products as $product) {
            $qty = (float) InventoryBalance::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $product->id)
                ->where('location_id', $locationId)
                ->value('qty_on_hand');

            $method = app(InventoryValuationService::class)->methodForTenant($tenantId);

            if ($method === 'fifo') {
                $fifoValue = (float) InventoryLayer::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $product->id)
                    ->where('location_id', $locationId)
                    ->selectRaw('sum(qty_remaining * cost_per_unit) as total')
                    ->value('total');

                $value = $fifoValue;

                if ($value <= 0 && $qty > 0) {
                    $averageCost = (float) InventoryCostState::query()
                        ->where('tenant_id', $tenantId)
                        ->where('product_id', $product->id)
                        ->where('location_id', $locationId)
                        ->value('average_cost');

                    if ($averageCost <= 0) {
                        $averageCost = (float) $product->hpp_average;
                    }

                    $value = $qty * $averageCost;
                }
            } else {
                $averageCost = (float) InventoryCostState::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $product->id)
                    ->where('location_id', $locationId)
                    ->value('average_cost');

                if ($averageCost <= 0) {
                    $averageCost = (float) $product->hpp_average;
                }

                $value = $qty * $averageCost;
            }

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
