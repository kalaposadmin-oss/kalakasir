<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Services\OpeningBalancePostingService;
use App\Services\ProductionEligibilityService;
use App\Services\ProductItemTypeMapper;
use App\Services\ProductItemTypeValidator;
use App\Services\SubscriptionService;
use App\Support\Decimal;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $liteRequested = $request->boolean('lite')
            || $request->filled('q')
            || $request->filled('category_id')
            || $request->filled('location_id')
            || $request->filled('per_page')
            || $request->filled('page')
            || $request->filled('ids');

        if ($liteRequested) {
            return $this->indexLite($request, $tenantId);
        }

        $products = Cache::remember("products:index:{$tenantId}", now('UTC')->addSeconds(60), function () use ($tenantId) {
            return Product::query()
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
                    'products.unit',
                    'products.supplier_id',
                    'products.image_url',
                    'products.category_id',
                    'products.product_mode',
                    'products.production_mode',
                    'products.item_type',
                    'products.track_stock',
                    'categories.name as category_name',
                ])
                ->where('products.tenant_id', $tenantId)
                ->where('products.is_active', true)
                ->orderBy('products.name')
                ->get();
        });

        return response()->json([
            'products' => $products,
        ]);
    }

    private function indexLite(Request $request, string $tenantId)
    {
        $perPage = max(1, min((int) $request->query('per_page', 24), 100));
        $page = max(1, (int) $request->query('page', 1));
        $term = trim((string) $request->query('q', ''));
        $categoryId = $request->query('category_id');
        $locationId = $request->query('location_id');
        $ids = $request->query('ids', []);

        if (is_string($ids)) {
            $ids = array_values(array_filter(array_map('trim', explode(',', $ids))));
        }

        if (! is_array($ids)) {
            $ids = [];
        }

        $query = Product::query()
            ->leftJoin('categories', function ($join) {
                $join->on('products.category_id', '=', 'categories.id')
                    ->on('products.tenant_id', '=', 'categories.tenant_id');
            })
            ->select([
                'products.id',
                'products.sku',
                'products.name',
                'products.price',
                'products.unit',
                'products.image_url',
                'products.category_id',
                'products.production_mode',
                'products.track_stock',
                'categories.name as category_name',
            ])
            ->where('products.tenant_id', $tenantId)
            ->where('products.is_active', true)
            ->where(function ($query) {
                $query->whereNull('products.item_type')
                    ->orWhere('products.item_type', '!=', 'raw_material');
            })
            ->where(function ($query) {
                $query->whereNull('products.product_mode')
                    ->orWhere('products.product_mode', '!=', 'add_on');
            });

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        if ($term !== '') {
            $query->where(function ($nested) use ($term) {
                $likeTerm = '%'.$term.'%';
                $nested->where('products.name', 'like', $likeTerm)
                    ->orWhere('products.sku', 'like', $likeTerm);
            });
        }

        if ($ids !== []) {
            $query->whereIn('products.id', $ids);
        }

        $query->orderBy('products.name');

        if ($ids !== []) {
            $rows = $query->get();
            $products = $this->decorateLiteProducts($tenantId, $rows, $locationId);

            return response()->json([
                'products' => $products,
                'total' => count($products),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => count($products),
                    'has_more' => false,
                    'total' => count($products),
                ],
            ]);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $products = $this->decorateLiteProducts($tenantId, collect($paginator->items()), $locationId);

        return response()->json([
            'products' => $products,
            'total' => $paginator->total(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'has_more' => $paginator->hasMorePages(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'q' => $term,
                'category_id' => $categoryId,
                'location_id' => $locationId,
            ],
        ]);
    }

    private function decorateLiteProducts(string $tenantId, Collection $rows, mixed $locationId): array
    {
        $productIds = $rows->pluck('id')->filter()->values()->all();
        $tierRows = ProductPriceTier::query()
            ->where('tenant_id', $tenantId)
            ->when($productIds !== [], fn ($query) => $query->whereIn('product_id', $productIds))
            ->orderBy('min_qty')
            ->get(['product_id', 'min_qty', 'price']);
        $tiersByProduct = $tierRows->groupBy('product_id');

        $stockByProduct = collect();
        if ($locationId && $productIds !== []) {
            $stockByProduct = InventoryBalance::query()
                ->where('tenant_id', $tenantId)
                ->where('location_id', $locationId)
                ->whereIn('product_id', $productIds)
                ->get(['product_id', 'qty_on_hand'])
                ->keyBy('product_id');
        }

        return $rows->map(function ($product) use ($tiersByProduct, $stockByProduct) {
            $stockRow = $stockByProduct->get($product->id);

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'price' => (float) $product->price,
                'hpp_average' => (float) ($product->hpp_average ?? 0),
                'unit' => $product->unit,
                'image_url' => $product->image_url,
                'category_id' => $product->category_id,
                'category_name' => $product->category_name,
                'track_stock' => (bool) $product->track_stock,
                'qty_on_hand' => $stockRow ? (float) $stockRow->qty_on_hand : null,
                'price_tiers' => ($tiersByProduct[$product->id] ?? collect())
                    ->map(fn ($tier) => [
                        'product_id' => $tier->product_id,
                        'min_qty' => (int) $tier->min_qty,
                        'price' => (float) $tier->price,
                    ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        try {
            $payload = $request->validate([
                'name' => ['required', 'string', 'max:150'],
                'sku' => ['required', 'string', 'max:100'],
                'category_id' => ['nullable', 'uuid', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
                'unit' => ['nullable', 'string', 'max:30'],
                'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
                'price' => ['nullable', 'numeric', 'min:0'],
                'hpp_average' => ['nullable', 'numeric', 'min:0'],
                'initial_stock' => ['nullable', 'numeric', 'min:0'],
                'initial_stock_lines' => ['nullable', 'array'],
                'initial_stock_lines.*.product_variant_id' => ['nullable', 'uuid'],
                'initial_stock_lines.*.qty' => ['required_with:initial_stock_lines', 'numeric', 'min:0.0001'],
                'initial_stock_lines.*.unit_cost' => ['nullable', 'numeric', 'min:0.01'],
                'image_url' => ['nullable', 'string', 'max:255'],
                'image_base64' => ['nullable', 'string'],
                'track_stock' => ['nullable', 'boolean'],
                'product_mode' => ['nullable', Rule::in(ProductItemTypeMapper::PRODUCT_MODES)],
                'production_mode' => ['nullable', Rule::in(Product::PRODUCTION_MODES)],
                'item_type' => ['nullable', Rule::in(ProductItemTypeMapper::ITEM_TYPES)],
                'recipe_output_qty' => ['nullable', 'numeric', 'min:0.0001'],
                'variants' => ['nullable', 'array'],
                'variants.*.id' => ['nullable', 'uuid'],
                'variants.*.sku' => ['required_with:variants', 'string', 'max:100'],
                'variants.*.name' => ['required_with:variants', 'string', 'max:150'],
                'variants.*.attributes' => ['nullable', 'array'],
                'variants.*.price_override' => ['nullable', 'numeric', 'min:0'],
                'bom_items' => ['nullable', 'array'],
                'bom_items.*.product_id' => ['required', 'uuid'],
                'bom_items.*.product_variant_id' => ['nullable', 'uuid', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)],
                'bom_items.*.qty' => ['required', 'numeric', 'min:0.0001'],
                'price_tiers' => ['nullable', 'array'],
                'price_tiers.*.label' => ['required', 'string', 'max:50'],
                'price_tiers.*.min_qty' => ['required', 'integer', 'min:1'],
                'price_tiers.*.price' => ['required', 'numeric', 'min:0'],
            ]);

            $productMode = (string) ($payload['product_mode'] ?? 'default');
            $itemType = ProductItemTypeMapper::resolve($payload['item_type'] ?? null, $productMode);
            $trackStock = (bool) ($payload['track_stock'] ?? true);
            $productionMode = $this->resolveProductionMode($payload, $productMode, null);
            if ($productionMode === Product::PRODUCTION_MODE_BATCH && $itemType === 'finished_good') {
                $trackStock = true;
            }
            $variantPayload = $this->normalizeVariantPayload($payload['variants'] ?? []);
            $requestedInitialStock = $trackStock ? (float) ($payload['initial_stock'] ?? 0) : 0.0;
            $requestedInitialStockLines = $trackStock ? ($payload['initial_stock_lines'] ?? []) : [];

            if ($response = $this->ensureRecipeManagementAccess($productMode, $productionMode, $payload['bom_items'] ?? [])) {
                return $response;
            }

            ProductItemTypeValidator::assertModeItemTypeValid($productMode, $itemType);
            ProductItemTypeValidator::assertServiceTrackStockValid($itemType, $trackStock);
            ProductItemTypeValidator::assertProductionModeValid($productionMode, $itemType, $trackStock);

            $price = (float) ($payload['price'] ?? 0);
            if ($itemType !== 'raw_material' && $price <= 0) {
                return response()->json(['message' => 'Harga jual harus lebih dari 0.'], 422);
            }

            if ($itemType === 'raw_material') {
                $price = max(0.0, $price);
            }

            if ($this->requiresRecipeValidation($productMode, $productionMode)) {
                ProductItemTypeValidator::assertBomComponentsValid($tenantId, $payload['bom_items'] ?? []);
            }

            $location = $this->resolveInitialStockLocation($tenantId, $request->input('location_id'));
            if (! $location && ($requestedInitialStock > 0 || ! empty($requestedInitialStockLines))) {
                return response()->json(['message' => 'Lokasi stok tidak ditemukan.'], 422);
            }

            $now = CarbonImmutable::now('UTC');
            $imageUrl = $payload['image_url'] ?? null;
            if (! empty($payload['image_base64'])) {
                $imageUrl = $this->storeBase64Image($payload['image_base64']);
            }

            $product = DB::transaction(function () use ($payload, $tenantId, $location, $now, $request, $imageUrl, $trackStock, $productMode, $productionMode, $itemType, $variantPayload, $requestedInitialStock, $requestedInitialStockLines, $price) {
                $product = Product::query()->create([
                    'tenant_id' => $tenantId,
                    'category_id' => $payload['category_id'] ?? null,
                    'sku' => $payload['sku'],
                    'name' => $payload['name'],
                    'price' => $price,
                    'hpp_average' => (float) ($payload['hpp_average'] ?? 0),
                    'unit' => $payload['unit'] ?? null,
                    'supplier_id' => $payload['supplier_id'] ?? null,
                    'image_url' => $imageUrl,
                    'is_active' => true,
                    'track_stock' => $trackStock,
                    'product_mode' => $productMode,
                    'production_mode' => $productionMode,
                    'item_type' => $itemType,
                    'bom_items' => json_encode($payload['bom_items'] ?? []),
                    'recipe_output_qty' => isset($payload['recipe_output_qty']) ? $this->normalizeRecipeOutputQty($payload['recipe_output_qty']) : null,
                    'bundle_items' => json_encode($payload['bundle_items'] ?? []),
                    'variants' => json_encode($variantPayload),
                    'created_by' => $request->user()?->id,
                    'updated_by' => $request->user()?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $persistedVariants = $this->syncProductVariants($tenantId, (string) $product->id, $variantPayload, $now);
                $initialStockReceipts = $this->normalizeInitialStockReceipts(
                    tenantId: $tenantId,
                    productId: (string) $product->id,
                    variants: $persistedVariants,
                    scalarInitialStock: $requestedInitialStock,
                    initialStockLines: $requestedInitialStockLines,
                    fallbackUnitCost: (float) ($payload['hpp_average'] ?? 0)
                );

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

                if ($location && ! empty($initialStockReceipts)) {
                    app(OpeningBalancePostingService::class)->postInventoryOnlyOpening(
                        tenantId: $tenantId,
                        openingDate: $now->toDateString(),
                        locationId: (string) $location->id,
                        productId: (string) $product->id,
                        receipts: $initialStockReceipts,
                        actorId: $request->user()?->id,
                        context: [
                            'source' => 'product_initial_stock',
                            'reference_note' => 'Initial stock from product create',
                        ]
                    );
                }

                return $product;
            });

            $this->forgetProductLookupCaches($tenantId);

            return response()->json([
                'product' => $product,
            ], 201);
        } catch (ValidationException $exception) {
            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Data tidak valid.',
                'details' => $exception->errors(),
            ], 422);
        }
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
            ->leftJoin('suppliers', function ($join) {
                $join->on('products.supplier_id', '=', 'suppliers.id')
                    ->on('products.tenant_id', '=', 'suppliers.tenant_id');
            })
            ->select([
                'products.id',
                'products.sku',
                'products.name',
                'products.price',
                'products.hpp_average',
                'products.min_stock',
                'products.unit',
                'products.supplier_id',
                'products.legacy_material_id',
                'products.image_url',
                'products.category_id',
                'products.product_mode',
                'products.production_mode',
                'products.item_type',
                'products.bom_items',
                'products.recipe_output_qty',
                'products.track_stock',
                'categories.name as category_name',
                'suppliers.name as supplier_name',
            ])
            ->where('products.tenant_id', $tenantId)
            ->where('products.id', $product)
            ->firstOrFail();

        $bomItems = $productRow->bom_items;
        if (is_string($bomItems)) {
            $bomItems = json_decode($bomItems, true);
        }
        if (! is_array($bomItems)) {
            $bomItems = [];
        }

        $productVariants = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product)
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'sku',
                'name',
                'attributes',
                'price_override',
            ])
            ->map(function ($variant) {
                $attributes = $variant->attributes;
                if (is_string($attributes)) {
                    $attributes = json_decode($attributes, true);
                }

                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'attributes' => is_array($attributes) ? $attributes : [],
                    'price_override' => $variant->price_override !== null ? (float) $variant->price_override : null,
                ];
            })
            ->values();

        $productPayload = $productRow->toArray();
        $productPayload['bom_items'] = $bomItems;
        $productPayload['recipe_output_qty'] = $productRow->recipe_output_qty !== null
            ? $this->normalizeRecipeOutputQty($productRow->recipe_output_qty)
            : null;
        $productPayload['product_variants'] = $productVariants;
        $productPayload['has_variants'] = $productVariants->isNotEmpty();
        $productPayload['production_batch_eligibility'] = app(ProductionEligibilityService::class)
            ->summarizeBatchEligibility($tenantId, $productPayload);
        $productPayload['production_batch_eligible'] = (bool) ($productPayload['production_batch_eligibility']['eligible'] ?? false);
        $productPayload['product_location_balances'] = InventoryBalance::query()
            ->leftJoin('locations', function ($join) {
                $join->on('inventory_balances.location_id', '=', 'locations.id')
                    ->on('inventory_balances.tenant_id', '=', 'locations.tenant_id');
            })
            ->where('inventory_balances.tenant_id', $tenantId)
            ->where('inventory_balances.product_id', $product)
            ->orderByDesc('inventory_balances.qty_on_hand')
            ->orderBy('locations.name')
            ->get([
                'inventory_balances.location_id as id',
                'locations.branch_id',
                'locations.name',
                'locations.is_default',
                'inventory_balances.qty_on_hand',
            ])
            ->map(fn ($balance) => [
                'id' => $balance->id,
                'branch_id' => $balance->branch_id,
                'name' => $balance->name,
                'is_default' => (bool) $balance->is_default,
                'qty_on_hand' => (float) $balance->qty_on_hand,
            ])
            ->values()
            ->all();

        return response()->json([
            'product' => $productPayload,
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

    private function normalizeRecipeOutputQty(mixed $value): float
    {
        return Decimal::float(Decimal::snappedQty($value), Decimal::QTY_SCALE);
    }

    public function update(Request $request, string $product)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        try {
            $payload = $request->validate([
                'name' => ['required', 'string', 'max:150'],
                'sku' => ['required', 'string', 'max:100'],
                'category_id' => ['nullable', 'uuid', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
                'unit' => ['nullable', 'string', 'max:30'],
                'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
                'price' => ['nullable', 'numeric', 'min:0'],
                'hpp_average' => ['nullable', 'numeric', 'min:0'],
                'initial_stock' => ['nullable', 'numeric', 'min:0'],
                'initial_stock_lines' => ['nullable', 'array'],
                'initial_stock_lines.*.product_variant_id' => ['nullable', 'uuid', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)],
                'initial_stock_lines.*.qty' => ['required_with:initial_stock_lines', 'numeric', 'min:0.0001'],
                'initial_stock_lines.*.unit_cost' => ['nullable', 'numeric', 'min:0.01'],
                'is_active' => ['nullable', Rule::in([true, false])],
                'track_stock' => ['nullable', 'boolean'],
                'product_mode' => ['nullable', Rule::in(ProductItemTypeMapper::PRODUCT_MODES)],
                'production_mode' => ['nullable', Rule::in(Product::PRODUCTION_MODES)],
                'item_type' => ['nullable', Rule::in(ProductItemTypeMapper::ITEM_TYPES)],
                'recipe_output_qty' => ['nullable', 'numeric', 'min:0.0001'],
                'variants' => ['nullable', 'array'],
                'variants.*.id' => ['nullable', 'uuid'],
                'variants.*.sku' => ['required_with:variants', 'string', 'max:100'],
                'variants.*.name' => ['required_with:variants', 'string', 'max:150'],
                'variants.*.attributes' => ['nullable', 'array'],
                'variants.*.price_override' => ['nullable', 'numeric', 'min:0'],
                'bom_items' => ['nullable', 'array'],
                'bom_items.*.product_id' => ['required', 'uuid'],
                'bom_items.*.product_variant_id' => ['nullable', 'uuid', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)],
                'bom_items.*.qty' => ['required', 'numeric', 'min:0.0001'],
            ]);

            $productRow = Product::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $product)
                ->firstOrFail();

            $productMode = (string) ($payload['product_mode'] ?? $productRow->product_mode ?? 'default');
            $itemType = ProductItemTypeMapper::resolve($payload['item_type'] ?? null, $productMode);
            $trackStock = (bool) ($payload['track_stock'] ?? $productRow->track_stock);
            $productionMode = $this->resolveProductionMode($payload, $productMode, (string) ($productRow->production_mode ?? Product::PRODUCTION_MODE_NONE));
            if ($productionMode === Product::PRODUCTION_MODE_BATCH && $itemType === 'finished_good') {
                $trackStock = true;
            }
            $variantPayload = array_key_exists('variants', $payload)
                ? $this->normalizeVariantPayload($payload['variants'] ?? [])
                : null;
            $requestedInitialStock = $trackStock ? (float) ($payload['initial_stock'] ?? 0) : 0.0;
            $requestedInitialStockLines = $trackStock ? ($payload['initial_stock_lines'] ?? []) : [];
            $effectiveBomItems = array_key_exists('bom_items', $payload)
                ? ($payload['bom_items'] ?? [])
                : $this->decodeBomItems($productRow->bom_items);

            if ($response = $this->ensureRecipeManagementAccess($productMode, $productionMode, $effectiveBomItems)) {
                return $response;
            }

            ProductItemTypeValidator::assertModeItemTypeValid($productMode, $itemType);
            ProductItemTypeValidator::assertServiceTrackStockValid($itemType, $trackStock);
            ProductItemTypeValidator::assertProductionModeValid($productionMode, $itemType, $trackStock);
            ProductItemTypeValidator::assertNotProducedForAutoConsume($tenantId, $product, $productionMode);

            $price = (float) ($payload['price'] ?? $productRow->price ?? 0);
            if ($itemType !== 'raw_material' && $price <= 0) {
                return response()->json(['message' => 'Harga harus lebih dari 0.'], 422);
            }

            if ($itemType === 'raw_material') {
                $price = max(0.0, $price);
            }

            if ($this->requiresRecipeValidation($productMode, $productionMode)) {
                ProductItemTypeValidator::assertBomComponentsValid($tenantId, $payload['bom_items'] ?? []);
            }

            $location = $this->resolveInitialStockLocation($tenantId, $request->input('location_id'));
            if (! $location && ($requestedInitialStock > 0 || ! empty($requestedInitialStockLines))) {
                return response()->json(['message' => 'Lokasi stok tidak ditemukan.'], 422);
            }

            $responseProduct = DB::transaction(function () use ($payload, $productRow, $tenantId, $request, $trackStock, $productMode, $productionMode, $itemType, $variantPayload, $requestedInitialStock, $requestedInitialStockLines, $location, $price) {
                $now = CarbonImmutable::now('UTC');

                $productRow->update([
                    'name' => $payload['name'],
                    'sku' => $payload['sku'],
                    'category_id' => $payload['category_id'] ?? null,
                    'price' => $price,
                    'hpp_average' => (float) ($payload['hpp_average'] ?? $productRow->hpp_average ?? 0),
                    'unit' => array_key_exists('unit', $payload) ? $payload['unit'] : $productRow->unit,
                    'supplier_id' => array_key_exists('supplier_id', $payload) ? $payload['supplier_id'] : $productRow->supplier_id,
                    'is_active' => $payload['is_active'] ?? $productRow->is_active,
                    'track_stock' => $trackStock,
                    'product_mode' => $productMode,
                    'production_mode' => $productionMode,
                    'item_type' => $itemType,
                    'bom_items' => array_key_exists('bom_items', $payload) ? json_encode($payload['bom_items'] ?? []) : $productRow->bom_items,
                    'recipe_output_qty' => array_key_exists('recipe_output_qty', $payload)
                        ? $this->normalizeRecipeOutputQty($payload['recipe_output_qty'])
                        : $productRow->recipe_output_qty,
                    'variants' => $variantPayload !== null ? json_encode($variantPayload) : $productRow->variants,
                    'updated_by' => $request->user()?->id,
                ]);

                $persistedVariants = $variantPayload !== null
                    ? $this->syncProductVariants($tenantId, (string) $productRow->id, $variantPayload, $now)
                    : $this->activeProductVariants($tenantId, (string) $productRow->id);

                $initialStockReceipts = $this->normalizeInitialStockReceipts(
                    tenantId: $tenantId,
                    productId: (string) $productRow->id,
                    variants: $persistedVariants,
                    scalarInitialStock: $requestedInitialStock,
                    initialStockLines: $requestedInitialStockLines,
                    fallbackUnitCost: (float) ($payload['hpp_average'] ?? $productRow->hpp_average ?? 0)
                );

                if ($location && ! empty($initialStockReceipts)) {
                    app(OpeningBalancePostingService::class)->postInventoryOnlyOpening(
                        tenantId: $tenantId,
                        openingDate: $now->toDateString(),
                        locationId: (string) $location->id,
                        productId: (string) $productRow->id,
                        receipts: $initialStockReceipts,
                        actorId: $request->user()?->id,
                        context: [
                            'source' => 'product_initial_stock_update',
                            'reference_note' => 'Initial stock from product update',
                        ]
                    );
                }

                return $productRow->fresh();
            });

            $this->forgetProductLookupCaches($tenantId);

            return response()->json([
                'product' => $responseProduct,
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Data tidak valid.',
                'details' => $exception->errors(),
            ], 422);
        }
    }

    private function resolveInitialStockLocation(string $tenantId, ?string $locationId): ?Location
    {
        $location = $locationId
            ? Location::query()->where('tenant_id', $tenantId)->where('id', $locationId)->first()
            : Location::query()->where('tenant_id', $tenantId)->where('is_default', true)->first();

        if (! $location) {
            $location = Location::query()->where('tenant_id', $tenantId)->orderBy('name')->first();
        }

        return $location;
    }

    private function resolveProductionMode(array $payload, string $productMode, ?string $currentValue): string
    {
        if (array_key_exists('production_mode', $payload) && $payload['production_mode'] !== null) {
            return (string) $payload['production_mode'];
        }

        if ($currentValue !== null && $currentValue !== '') {
            return $currentValue;
        }

        return $productMode === 'bom'
            ? Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL
            : Product::PRODUCTION_MODE_NONE;
    }

    private function requiresRecipeValidation(string $productMode, string $productionMode): bool
    {
        return $productMode === 'bom'
            || in_array($productionMode, [
                Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL,
                Product::PRODUCTION_MODE_BATCH,
            ], true);
    }

    private function ensureRecipeManagementAccess(string $productMode, string $productionMode, array $bomItems)
    {
        if (! $this->usesRecipeOrProduction($productMode, $productionMode, $bomItems)) {
            return null;
        }

        $tenant = TenantContext::get();
        if ($tenant && app(SubscriptionService::class)->canAccessFeature($tenant, 'product.recipe_management')) {
            return null;
        }

        return response()->json([
            'code' => 'FEATURE_NOT_ALLOWED',
            'message' => 'Fitur resep dan produksi hanya tersedia mulai paket Medium.',
            'details' => [
                'feature' => 'product.recipe_management',
                'required_plan' => 'M',
            ],
        ], 403);
    }

    private function usesRecipeOrProduction(string $productMode, string $productionMode, array $bomItems): bool
    {
        return $productMode === 'bom'
            || ! empty($bomItems)
            || in_array($productionMode, [
                Product::PRODUCTION_MODE_AUTO_CONSUME_ON_SELL,
                Product::PRODUCTION_MODE_BATCH,
            ], true);
    }

    private function decodeBomItems(mixed $bomItems): array
    {
        if (is_array($bomItems)) {
            return $bomItems;
        }

        if (is_string($bomItems)) {
            $decoded = json_decode($bomItems, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function normalizeVariantPayload(array $variants): array
    {
        $normalized = [];
        $seenIds = [];
        $seenSkus = [];

        foreach (array_values($variants) as $index => $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $variantId = (string) ($variant['id'] ?? Str::uuid());
            $sku = trim((string) ($variant['sku'] ?? ''));
            $name = trim((string) ($variant['name'] ?? ''));
            $skuKey = mb_strtolower($sku);

            if ($sku === '' || $name === '') {
                throw ValidationException::withMessages([
                    "variants.$index.name" => 'Nama dan SKU variant wajib diisi.',
                ]);
            }

            if (isset($seenIds[$variantId])) {
                throw ValidationException::withMessages([
                    "variants.$index.id" => 'Variant duplikat terdeteksi.',
                ]);
            }

            if (isset($seenSkus[$skuKey])) {
                throw ValidationException::withMessages([
                    "variants.$index.sku" => 'SKU variant harus unik.',
                ]);
            }

            $seenIds[$variantId] = true;
            $seenSkus[$skuKey] = true;
            $normalized[] = [
                'id' => $variantId,
                'sku' => $sku,
                'name' => $name,
                'attributes' => is_array($variant['attributes'] ?? null) ? $variant['attributes'] : [],
                'price_override' => array_key_exists('price_override', $variant) && $variant['price_override'] !== null
                    ? (float) $variant['price_override']
                    : null,
            ];
        }

        return $normalized;
    }

    private function syncProductVariants(string $tenantId, string $productId, array $variants, CarbonImmutable $now): Collection
    {
        $existingIds = DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $incomingIds = array_map(fn (array $variant) => (string) $variant['id'], $variants);
        $retiredIds = array_values(array_diff($existingIds, $incomingIds));

        if (! empty($retiredIds)) {
            DB::table('product_variants')
                ->where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->whereIn('id', $retiredIds)
                ->update([
                    'is_active' => false,
                    'updated_at' => $now,
                ]);
        }

        foreach ($variants as $variant) {
            DB::table('product_variants')->updateOrInsert(
                [
                    'id' => $variant['id'],
                    'tenant_id' => $tenantId,
                ],
                [
                    'product_id' => $productId,
                    'sku' => $variant['sku'],
                    'barcode' => null,
                    'name' => $variant['name'],
                    'attributes' => json_encode($variant['attributes'] ?? []),
                    'price_override' => $variant['price_override'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        return $this->activeProductVariants($tenantId, $productId);
    }

    private function activeProductVariants(string $tenantId, string $productId): Collection
    {
        return DB::table('product_variants')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'attributes', 'price_override'])
            ->map(function ($variant) {
                $attributes = json_decode((string) ($variant->attributes ?? '{}'), true);

                return [
                    'id' => (string) $variant->id,
                    'sku' => (string) $variant->sku,
                    'name' => (string) $variant->name,
                    'attributes' => is_array($attributes) ? $attributes : [],
                    'price_override' => $variant->price_override !== null ? (float) $variant->price_override : null,
                ];
            })
            ->values();
    }

    private function normalizeInitialStockReceipts(
        string $tenantId,
        string $productId,
        Collection $variants,
        float $scalarInitialStock,
        array $initialStockLines,
        float $fallbackUnitCost
    ): array {
        $variantIds = $variants->pluck('id')->map(fn ($id) => (string) $id)->all();

        if (! empty($variantIds) && $scalarInitialStock > 0) {
            throw ValidationException::withMessages([
                'initial_stock' => 'Stok awal untuk produk bervarian harus diisi per variant.',
            ]);
        }

        if (empty($variantIds)) {
            foreach (array_values($initialStockLines) as $index => $line) {
                if (filled($line['product_variant_id'] ?? null)) {
                    throw ValidationException::withMessages([
                        "initial_stock_lines.$index.product_variant_id" => 'Produk non-variant tidak boleh mengirim product_variant_id.',
                    ]);
                }
            }

            if ($scalarInitialStock > 0 && $fallbackUnitCost <= 0) {
                throw ValidationException::withMessages([
                    'hpp_average' => 'Unit cost opening stock wajib lebih dari 0 saat qty stok awal diisi.',
                ]);
            }

            return $scalarInitialStock > 0
                ? [[
                    'reference_item_id' => null,
                    'product_variant_id' => null,
                    'qty' => $scalarInitialStock,
                    'unit_cost' => $fallbackUnitCost,
                ]]
                : [];
        }

        $receipts = [];
        $seenVariantIds = [];
        foreach (array_values($initialStockLines) as $index => $line) {
            $variantId = (string) ($line['product_variant_id'] ?? '');
            $qty = (float) ($line['qty'] ?? 0);

            if ($variantId === '') {
                throw ValidationException::withMessages([
                    "initial_stock_lines.$index.product_variant_id" => 'Stok awal variant wajib menyertakan product_variant_id.',
                ]);
            }

            if (! in_array($variantId, $variantIds, true)) {
                throw ValidationException::withMessages([
                    "initial_stock_lines.$index.product_variant_id" => 'Variant stok awal tidak cocok dengan produk ini.',
                ]);
            }

            if (isset($seenVariantIds[$variantId])) {
                throw ValidationException::withMessages([
                    "initial_stock_lines.$index.product_variant_id" => 'Variant stok awal duplikat tidak diizinkan.',
                ]);
            }

            if ($qty <= 0) {
                continue;
            }

            $resolvedUnitCost = array_key_exists('unit_cost', $line) && $line['unit_cost'] !== null
                ? (float) $line['unit_cost']
                : $fallbackUnitCost;

            if ($resolvedUnitCost <= 0) {
                throw ValidationException::withMessages([
                    "initial_stock_lines.$index.unit_cost" => 'Unit cost opening stock wajib lebih dari 0 saat qty stok awal diisi.',
                ]);
            }

            $seenVariantIds[$variantId] = true;
            $receipts[] = [
                'reference_item_id' => $variantId,
                'product_variant_id' => $variantId,
                'qty' => $qty,
                'unit_cost' => $resolvedUnitCost,
            ];
        }

        return $receipts;
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

        $this->forgetProductLookupCaches($tenantId);

        return response()->json([
            'message' => 'Produk berhasil dihapus.',
        ]);
    }

    private function forgetProductLookupCaches(string $tenantId): void
    {
        Cache::forget("products:index:{$tenantId}");
        Cache::forget("categories:index:{$tenantId}");
        Cache::forget("inventory-alerts:index:{$tenantId}");
    }
}
