<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MaterialController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $search = trim((string) $request->query('search', ''));
        $limit = (int) $request->query('limit', 20);
        if ($limit <= 0) {
            $limit = 20;
        }

        $materials = $this->materialQuery($tenantId)
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->select('id', 'legacy_material_id', 'sku', 'name', 'unit', 'hpp_average', 'supplier_id')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $materialUsage = $this->materialUsageMap($tenantId, $materials->pluck('id')->all());
        $materialStock = $this->materialStockMap($tenantId, $materials->pluck('id')->all());

        return ApiResponse::success([
            'materials' => $materials->map(fn ($material) => $this->mapMaterial($material, $materialUsage, $materialStock))->values(),
        ], [
            'count' => $materials->count(),
            'limit' => $limit,
            'source' => 'products.raw_material',
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('products', 'name')->where(function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->where('item_type', 'raw_material')
                    ->whereNull('deleted_at');
            })],
            'unit' => ['required', 'string', 'max:30'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
        ]);

        $materialId = (string) Str::uuid();
        $material = Product::query()->create([
            'id' => (string) Str::uuid(),
            'legacy_material_id' => $materialId,
            'tenant_id' => $tenantId,
            'sku' => $this->makeMaterialSku(),
            'name' => $validated['name'],
            'price' => 0,
            'hpp_average' => (float) ($validated['unit_cost'] ?? 0),
            'unit' => $validated['unit'],
            'supplier_id' => $validated['supplier_id'] ?? null,
            'track_stock' => true,
            'product_mode' => 'add_on',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'raw_material',
            'is_active' => true,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'material' => $this->mapMaterial($material),
        ], 201);
    }

    public function show(Request $request, string $material)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $materialRow = $this->resolveMaterial($tenantId, $material, ['id', 'legacy_material_id', 'name', 'unit', 'hpp_average', 'supplier_id']);

        return response()->json([
            'material' => $this->mapMaterial($materialRow),
        ]);
    }

    public function update(Request $request, string $material)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $materialRow = $this->resolveMaterial($tenantId, $material);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('products', 'name')->where(function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->where('item_type', 'raw_material')
                    ->whereNull('deleted_at');
            })->ignore($materialRow->id)],
            'unit' => ['required', 'string', 'max:30'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $materialRow->update([
            'name' => $validated['name'],
            'unit' => $validated['unit'],
            'hpp_average' => $validated['unit_cost'] ?? $materialRow->hpp_average ?? 0,
            'supplier_id' => $validated['supplier_id'] ?? $materialRow->supplier_id,
            'is_active' => $validated['is_active'] ?? $materialRow->is_active,
            'track_stock' => true,
            'product_mode' => 'add_on',
            'production_mode' => Product::PRODUCTION_MODE_NONE,
            'item_type' => 'raw_material',
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'material' => $this->mapMaterial($materialRow),
        ]);
    }

    public function destroy(Request $request, string $material)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $materialRow = $this->resolveMaterial($tenantId, $material);

        $materialRow->update([
            'is_active' => false,
            'updated_by' => $request->user()?->id,
        ]);

        $materialRow->delete();

        return response()->json([
            'message' => 'Bahan berhasil dihapus.',
        ]);
    }

    private function materialQuery(string $tenantId)
    {
        return Product::query()
            ->where('tenant_id', $tenantId)
            ->where('item_type', 'raw_material');
    }

    private function resolveMaterial(string $tenantId, string $material, array $columns = ['*']): Product
    {
        return $this->materialQuery($tenantId)
            ->where(function ($query) use ($material) {
                $query->where('id', $material)
                    ->orWhere('legacy_material_id', $material);
            })
            ->firstOr($columns);
    }

    private function mapMaterial(object $material, array $usageMap = [], array $stockMap = []): array
    {
        $legacyId = $material->legacy_material_id ?? null;
        $materialId = (string) ($legacyId ?: $material->id);
        $usage = $usageMap[(string) $material->id] ?? ['count' => 0, 'products' => []];
        $stockOnHand = (float) ($stockMap[(string) $material->id] ?? 0);

        return [
            'id' => $materialId,
            'product_id' => $material->id,
            'sku' => $material->sku,
            'name' => $material->name,
            'unit' => $material->unit,
            'unit_cost' => (float) ($material->hpp_average ?? 0),
            'stock_on_hand' => $stockOnHand,
            'supplier_id' => $material->supplier_id,
            'used_in_count' => (int) ($usage['count'] ?? 0),
            'used_in_products' => array_values($usage['products'] ?? []),
        ];
    }

    private function materialStockMap(string $tenantId, array $materialProductIds): array
    {
        $materialProductIds = array_values(array_filter(array_map('strval', $materialProductIds)));

        if ($materialProductIds === []) {
            return [];
        }

        return DB::table('inventory_balances')
            ->where('tenant_id', $tenantId)
            ->whereIn('product_id', $materialProductIds)
            ->select('product_id', DB::raw('COALESCE(SUM(qty_on_hand), 0) as qty_on_hand'))
            ->groupBy('product_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->product_id => (float) $row->qty_on_hand])
            ->all();
    }

    private function materialUsageMap(string $tenantId, array $materialProductIds): array
    {
        $materialProductIds = array_values(array_filter(array_map('strval', $materialProductIds)));

        if ($materialProductIds === []) {
            return [];
        }

        $usageMap = [];
        foreach ($materialProductIds as $materialProductId) {
            $usageMap[$materialProductId] = [
                'count' => 0,
                'products' => [],
            ];
        }

        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'bom_items', 'item_type')
            ->orderBy('name')
            ->get();

        foreach ($products as $product) {
            if ((string) $product->item_type === 'raw_material') {
                continue;
            }

            $bomItems = is_array($product->bom_items) ? $product->bom_items : [];
            foreach ($bomItems as $bomItem) {
                $componentId = (string) ($bomItem['product_id'] ?? '');
                if ($componentId === '' || ! array_key_exists($componentId, $usageMap)) {
                    continue;
                }

                if (! in_array($product->name, $usageMap[$componentId]['products'], true)) {
                    $usageMap[$componentId]['products'][] = (string) $product->name;
                }
            }
        }

        foreach ($usageMap as $materialProductId => $usage) {
            $usageMap[$materialProductId]['count'] = count($usage['products']);
        }

        return $usageMap;
    }

    private function makeMaterialSku(): string
    {
        return 'RM-'.strtoupper(str_replace('-', '', (string) Str::uuid()));
    }
}
