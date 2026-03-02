<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\StockLedger;
use App\Services\InventoryValuationService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $products = Product::query()
            ->leftJoin('categories', function ($join) {
                $join->on('products.category_id', '=', 'categories.id')
                    ->on('products.tenant_id', '=', 'categories.tenant_id');
            })
            ->select([
                'products.id',
                'products.sku',
                'products.name',
                'products.price',
                'products.hpp_average',
                'products.image_url',
                'products.category_id',
                'categories.name as category_name',
            ])
            ->where('products.tenant_id', $tenantId)
            ->where('products.is_active', true)
            ->orderBy('products.name')
            ->get();

        return response()->json([
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['required', 'string', 'max:100'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'hpp_average' => ['nullable', 'numeric', 'min:0'],
            'initial_stock' => ['nullable', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:255'],
            'image_base64' => ['nullable', 'string'],
            'track_stock' => ['nullable', 'boolean'],
            'price_tiers' => ['nullable', 'array'],
            'price_tiers.*.label' => ['required', 'string', 'max:50'],
            'price_tiers.*.min_qty' => ['required', 'integer', 'min:1'],
            'price_tiers.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $trackStock = $payload['track_stock'] ?? true;
        $initialStock = $trackStock ? (float) ($payload['initial_stock'] ?? 0) : 0;
        $locationId = $request->input('location_id');

        $location = $locationId
            ? Location::query()->where('tenant_id', $tenantId)->where('id', $locationId)->first()
            : Location::query()->where('tenant_id', $tenantId)->where('is_default', true)->first();

        if (! $location) {
            $location = Location::query()->where('tenant_id', $tenantId)->orderBy('name')->first();
        }

        if (! $location && $initialStock > 0) {
            return response()->json(['message' => 'Lokasi stok tidak ditemukan.'], 422);
        }

        $now = CarbonImmutable::now('UTC');

        $imageUrl = $payload['image_url'] ?? null;
        if (! empty($payload['image_base64'])) {
            $imageUrl = $this->storeBase64Image($payload['image_base64']);
        }

        $product = DB::transaction(function () use ($payload, $tenantId, $initialStock, $location, $now, $request, $imageUrl, $trackStock) {
            $valuationService = app(InventoryValuationService::class);

            $product = Product::query()->create([
                'tenant_id' => $tenantId,
                'category_id' => $payload['category_id'] ?? null,
                'sku' => $payload['sku'],
                'name' => $payload['name'],
                'price' => (float) $payload['price'],
                'hpp_average' => (float) ($payload['hpp_average'] ?? 0),
                'image_url' => $imageUrl,
                'is_active' => true,
                'track_stock' => (bool) $trackStock,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! empty($payload['price_tiers'])) {
                $tiers = [];
                foreach ($payload['price_tiers'] as $tier) {
                    $minQty = (int) $tier['min_qty'];
                    $tiers[] = [
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'product_id' => $product->id,
                        'label' => $tier['label'],
                        'min_qty' => $minQty,
                        'max_qty' => null,
                        'price' => (float) $tier['price'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                ProductPriceTier::query()->insert($tiers);
            }

            if ($initialStock > 0 && $location) {
                InventoryBalance::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'product_id' => $product->id,
                        'location_id' => $location->id,
                    ],
                    [
                        'qty_on_hand' => 0,
                    ]
                );

                DB::update(
                    'UPDATE inventory_balances SET qty_on_hand = qty_on_hand + ? WHERE tenant_id = ? AND product_id = ? AND location_id = ?',
                    [$initialStock, $tenantId, $product->id, $location->id]
                );

                $balanceAfter = (float) InventoryBalance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $product->id)
                    ->where('location_id', $location->id)
                    ->value('qty_on_hand');

                StockLedger::query()->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'reference_type' => 'initial_stock',
                    'reference_id' => $product->id,
                    'reference_item_id' => null,
                    'qty_in' => $initialStock,
                    'qty_out' => 0,
                    'balance_after' => $balanceAfter,
                    'created_at' => $now,
                    'created_by' => $request->user()?->id,
                ]);

                $valuationService->receive(
                    tenantId: $tenantId,
                    productId: $product->id,
                    locationId: $location->id,
                    qty: $initialStock,
                    unitCost: (float) ($payload['hpp_average'] ?? 0),
                    sourceType: 'initial_stock',
                    sourceId: $product->id,
                    at: $now
                );
            }

            return $product;
        });

        return response()->json([
            'product' => $product,
        ], 201);
    }

    public function show(string $product)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $productRow = Product::query()
            ->leftJoin('categories', function ($join) {
                $join->on('products.category_id', '=', 'categories.id')
                    ->on('products.tenant_id', '=', 'categories.tenant_id');
            })
            ->select([
                'products.id',
                'products.sku',
                'products.name',
                'products.price',
                'products.hpp_average',
                'products.image_url',
                'products.category_id',
                'categories.name as category_name',
            ])
            ->where('products.tenant_id', $tenantId)
            ->where('products.id', $product)
            ->firstOrFail();

        return response()->json([
            'product' => $productRow,
        ]);
    }

    private function storeBase64Image(string $base64): ?string
    {
        if (! preg_match('/^data:image\/(png|jpe?g|webp);base64,/', $base64, $matches)) {
            return null;
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $binary = base64_decode(substr($base64, strpos($base64, ',') + 1), true);
        if ($binary === false) {
            return null;
        }

        $path = 'products/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        return '/storage/'.$path;
    }

    public function update(Request $request, string $product)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['required', 'string', 'max:100'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'hpp_average' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', Rule::in([true, false])],
        ]);

        $productRow = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $product)
            ->firstOrFail();

        $productRow->update([
            'name' => $payload['name'],
            'sku' => $payload['sku'],
            'category_id' => $payload['category_id'] ?? null,
            'price' => (float) $payload['price'],
            'hpp_average' => (float) ($payload['hpp_average'] ?? 0),
            'is_active' => $payload['is_active'] ?? $productRow->is_active,
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'product' => $productRow,
        ]);
    }

    public function destroy(Request $request, string $product)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $productRow = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $product)
            ->firstOrFail();

        $productRow->update([
            'is_active' => false,
            'updated_by' => $request->user()?->id,
        ]);

        $productRow->delete();

        return response()->json([
            'message' => 'Produk berhasil dihapus.',
        ]);
    }
}
