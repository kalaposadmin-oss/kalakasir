<?php

namespace App\Services;

use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockLedger;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OnboardingInitialProductService
{
    public function __construct(
        private readonly TenantDefaultOperationalSetupService $operationalSetup,
        private readonly InventoryValuationService $inventoryValuation,
    ) {
    }

    /**
     * @param  array{tenant_id:string,user_id?:string|null,operational_address?:string|null}  $step2
     * @param  array{name:string,sku?:string|null,category?:string|null,hpp_average:numeric-string|int|float,price:numeric-string|int|float,initial_stock:numeric-string|int|float,variants?:array<int,array{id?:string|null,sku:string,name:string}>,initial_stock_lines?:array<int,array{product_variant_id?:string|null,qty?:numeric-string|int|float,unit_cost?:numeric-string|int|float|null}>}  $payload
     */
    public function bootstrap(array $step2, array $payload): Product
    {
        $sku = trim((string) ($payload['sku'] ?? ''));
        if ($sku === '') {
            $sku = 'SKU-'.strtoupper(substr(md5($payload['name'].microtime(true)), 0, 8));
        }

        return DB::transaction(function () use ($step2, $payload, $sku) {
            $tenant = Tenant::query()->findOrFail($step2['tenant_id']);
            [, $location] = $this->operationalSetup->ensure(
                $tenant,
                $tenant->name,
                $step2['operational_address'] ?? null,
            );

            $now = CarbonImmutable::now('UTC');
            $category = $this->resolveCategory($tenant->id, (string) ($payload['category'] ?? ''), $now);
            $normalizedVariants = $this->normalizeVariants((array) ($payload['variants'] ?? []));

            $product = Product::query()->create([
                'tenant_id' => $tenant->id,
                'category_id' => $category?->id,
                'name' => $payload['name'],
                'sku' => $sku,
                'price' => (float) $payload['price'],
                'hpp_average' => (float) $payload['hpp_average'],
                'min_stock' => 0,
                'track_stock' => true,
                'is_active' => true,
                'variants' => json_encode($normalizedVariants->all()),
                'created_by' => $step2['user_id'] ?? null,
                'updated_by' => $step2['user_id'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($normalizedVariants as $variant) {
                DB::table('product_variants')->insert([
                    'id' => $variant['id'],
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'sku' => $variant['sku'],
                    'barcode' => null,
                    'name' => $variant['name'],
                    'attributes' => json_encode([]),
                    'price_override' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $receipts = $this->resolveInitialReceipts($normalizedVariants, $payload);

            foreach ($receipts as $receipt) {
                if (! $location || (float) $receipt['qty'] <= 0) {
                    continue;
                }

                InventoryBalance::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'product_id' => $product->id,
                        'product_variant_id' => $receipt['product_variant_id'],
                        'location_id' => $location->id,
                    ],
                    [
                        'qty_on_hand' => 0,
                    ]
                );

                InventoryBalance::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('product_id', $product->id)
                    ->where('location_id', $location->id)
                    ->when($receipt['product_variant_id'], function ($query, $variantId) {
                        $query->where('product_variant_id', $variantId);
                    }, function ($query) {
                        $query->whereNull('product_variant_id');
                    })
                    ->update([
                        'qty_on_hand' => DB::raw('qty_on_hand + '.(float) $receipt['qty']),
                    ]);

                $balanceAfter = (float) InventoryBalance::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('product_id', $product->id)
                    ->where('location_id', $location->id)
                    ->when($receipt['product_variant_id'], function ($query, $variantId) {
                        $query->where('product_variant_id', $variantId);
                    }, function ($query) {
                        $query->whereNull('product_variant_id');
                    })
                    ->value('qty_on_hand');

                StockLedger::query()->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $receipt['product_variant_id'],
                    'location_id' => $location->id,
                    'reference_type' => 'initial_stock',
                    'reference_id' => $product->id,
                    'reference_item_id' => $receipt['reference_item_id'],
                    'qty_in' => (float) $receipt['qty'],
                    'qty_out' => 0,
                    'balance_after' => $balanceAfter,
                    'created_at' => $now,
                    'created_by' => $step2['user_id'] ?? null,
                ]);

                $this->inventoryValuation->receive(
                    tenantId: $tenant->id,
                    productId: $product->id,
                    productVariantId: $receipt['product_variant_id'],
                    locationId: $location->id,
                    qty: (float) $receipt['qty'],
                    unitCost: (float) $receipt['unit_cost'],
                    sourceType: 'initial_stock',
                    sourceId: $product->id,
                    sourceItemId: $receipt['reference_item_id'] ?? null,
                    at: $now,
                );
            }

            return $product;
        });
    }

    private function resolveCategory(string $tenantId, string $categoryName, CarbonImmutable $now): ?Category
    {
        $categoryName = trim($categoryName);
        if ($categoryName === '') {
            return null;
        }

        $slug = Str::slug($categoryName);
        if ($slug === '') {
            $slug = 'kategori-'.substr(md5($categoryName), 0, 8);
        }

        return Category::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'slug' => $slug,
            ],
            [
                'name' => $categoryName,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * @param  array<int,array{id?:string|null,sku:string,name:string}>  $variants
     * @return Collection<int, array{id:string,sku:string,name:string}>
     */
    private function normalizeVariants(array $variants): Collection
    {
        $normalizedVariants = collect($variants)->values()->map(function (array $variant, int $index) {
            $variantId = (string) ($variant['id'] ?? Str::uuid());
            $variantSku = trim((string) ($variant['sku'] ?? ''));
            $variantName = trim((string) ($variant['name'] ?? ''));

            if ($variantSku === '' || $variantName === '') {
                throw ValidationException::withMessages([
                    "variants.$index.name" => 'Nama dan SKU variant wajib diisi.',
                ]);
            }

            return [
                'id' => $variantId,
                'sku' => $variantSku,
                'name' => $variantName,
            ];
        });

        $duplicateVariantSkus = $normalizedVariants
            ->groupBy(fn (array $variant) => mb_strtolower($variant['sku']))
            ->filter(fn ($group) => $group->count() > 1)
            ->keys()
            ->values();

        if ($duplicateVariantSkus->isNotEmpty()) {
            throw ValidationException::withMessages([
                'variants' => 'SKU variant harus unik.',
            ]);
        }

        return $normalizedVariants;
    }

    /**
     * @param  Collection<int, array{id:string,sku:string,name:string}>  $normalizedVariants
     * @param  array{name:string,hpp_average:numeric-string|int|float,initial_stock:numeric-string|int|float,initial_stock_lines?:array<int,array{product_variant_id?:string|null,qty?:numeric-string|int|float,unit_cost?:numeric-string|int|float|null}>}  $payload
     * @return list<array{product_variant_id:?string,reference_item_id:?string,qty:float,unit_cost:float}>
     */
    private function resolveInitialReceipts(Collection $normalizedVariants, array $payload): array
    {
        $initialStockLines = collect($payload['initial_stock_lines'] ?? [])->values();
        $variantIds = $normalizedVariants->pluck('id')->map(fn ($id) => (string) $id)->all();

        if (! empty($variantIds) && (float) $payload['initial_stock'] > 0) {
            throw ValidationException::withMessages([
                'initial_stock' => 'Produk bervarian harus mengisi stok awal per variant.',
            ]);
        }

        if (empty($variantIds) && $initialStockLines->contains(fn ($line) => filled($line['product_variant_id'] ?? null))) {
            throw ValidationException::withMessages([
                'initial_stock_lines.0.product_variant_id' => 'Produk non-variant tidak boleh mengirim product_variant_id.',
            ]);
        }

        $receipts = [];
        if (empty($variantIds)) {
            $initialStock = (float) $payload['initial_stock'];
            if ($initialStock > 0) {
                $receipts[] = [
                    'product_variant_id' => null,
                    'reference_item_id' => null,
                    'qty' => $initialStock,
                    'unit_cost' => (float) $payload['hpp_average'],
                ];
            }

            return $receipts;
        }

        $seenVariantIds = [];
        foreach ($initialStockLines as $index => $line) {
            $variantId = (string) ($line['product_variant_id'] ?? '');
            $qty = (float) ($line['qty'] ?? 0);

            if ($variantId === '') {
                throw ValidationException::withMessages([
                    "initial_stock_lines.$index.product_variant_id" => 'Stok awal variant wajib menyertakan product_variant_id.',
                ]);
            }

            if (! in_array($variantId, $variantIds, true)) {
                throw ValidationException::withMessages([
                    "initial_stock_lines.$index.product_variant_id" => 'Variant stok awal tidak cocok dengan produk onboarding ini.',
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

            $seenVariantIds[$variantId] = true;
            $receipts[] = [
                'product_variant_id' => $variantId,
                'reference_item_id' => $variantId,
                'qty' => $qty,
                'unit_cost' => array_key_exists('unit_cost', $line) && $line['unit_cost'] !== null
                    ? (float) $line['unit_cost']
                    : (float) $payload['hpp_average'],
            ];
        }

        return $receipts;
    }
}