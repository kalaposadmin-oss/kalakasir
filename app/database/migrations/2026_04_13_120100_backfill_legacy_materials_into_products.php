<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $materials = DB::table('materials')->orderBy('created_at')->get();

        foreach ($materials as $material) {
            $matchedProduct = Product::query()
                ->where('tenant_id', $material->tenant_id)
                ->where('item_type', 'raw_material')
                ->where(function ($query) use ($material) {
                    $query->where('legacy_material_id', $material->id)
                        ->orWhere('id', $material->id)
                        ->orWhere('name', $material->name);
                })
                ->orderByRaw('case when legacy_material_id = ? then 0 when id = ? then 1 else 2 end', [$material->id, $material->id])
                ->first();

            if ($matchedProduct) {
                Product::query()
                    ->where('id', $matchedProduct->id)
                    ->update([
                        'item_type' => 'raw_material',
                        'product_mode' => 'add_on',
                        'production_mode' => 'none',
                        'track_stock' => true,
                        'unit' => $matchedProduct->unit ?: $material->unit,
                        'supplier_id' => $matchedProduct->supplier_id ?: $material->supplier_id,
                        'legacy_material_id' => $matchedProduct->legacy_material_id ?: $material->id,
                        'hpp_average' => (float) ($matchedProduct->hpp_average ?: $material->unit_cost ?: 0),
                        'updated_at' => $material->updated_at,
                    ]);

                continue;
            }

            Product::query()->create([
                'id' => $material->id,
                'legacy_material_id' => $material->id,
                'tenant_id' => $material->tenant_id,
                'sku' => 'RM-'.strtoupper(str_replace('-', '', (string) $material->id)),
                'name' => $material->name,
                'price' => 0,
                'hpp_average' => (float) ($material->unit_cost ?? 0),
                'unit' => $material->unit,
                'supplier_id' => $material->supplier_id,
                'image_url' => null,
                'is_active' => (bool) $material->is_active,
                'track_stock' => true,
                'product_mode' => 'add_on',
                'production_mode' => 'none',
                'item_type' => 'raw_material',
                'variants' => json_encode([]),
                'bom_items' => json_encode([]),
                'bundle_items' => json_encode([]),
                'created_by' => $material->created_by,
                'updated_by' => $material->updated_by,
                'created_at' => $material->created_at,
                'updated_at' => $material->updated_at,
                'deleted_at' => $material->deleted_at,
            ]);
        }
    }

    public function down(): void
    {
        $legacyIds = DB::table('materials')->pluck('id')->all();

        Product::query()
            ->whereIn('legacy_material_id', $legacyIds)
            ->where('id', DB::raw('legacy_material_id'))
            ->delete();

        Product::query()
            ->whereIn('legacy_material_id', $legacyIds)
            ->update(['legacy_material_id' => null]);
    }
};