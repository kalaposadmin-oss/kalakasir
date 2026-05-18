<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Services\WebRouteContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TenantViewController extends Controller
{
    public function __construct(private readonly WebRouteContextService $routeContext)
    {
    }

    public function show(Request $request): View
    {
        $view = (string) $request->route('view');
        $data = $request->route('data', []);

        abort_unless($view !== '', 404);

        return view($view, $this->routeContext->withTenant(is_array($data) ? $data : []));
    }

    public function showWithRouteParam(Request $request): View
    {
        $view = (string) $request->route('view');
        $routeParam = (string) $request->route('routeParam');
        $viewDataKey = (string) $request->route('viewDataKey');
        $data = $request->route('data', []);

        abort_unless($view !== '' && $routeParam !== '' && $viewDataKey !== '', 404);

        $payload = is_array($data) ? $data : [];
        $payload[$viewDataKey] = $request->route($routeParam);

        if ($view === 'product-edit' && $viewDataKey === 'productId') {
            $productId = (string) $payload[$viewDataKey];
            $tenantId = $this->routeContext->resolveTenantId();

            if ($tenantId !== null && $productId !== '') {
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
                        'products.recipe_output_qty',
                        'products.product_mode',
                        'products.production_mode',
                        'products.item_type',
                        'products.bom_items',
                        'products.track_stock',
                        'products.is_active',
                        'categories.name as category_name',
                        'suppliers.name as supplier_name',
                    ])
                    ->where('products.tenant_id', $tenantId)
                    ->where('products.id', $productId)
                    ->first();

                if ($productRow) {
                    $bomItems = $productRow->bom_items;
                    if (is_string($bomItems)) {
                        $bomItems = json_decode($bomItems, true);
                    }
                    if (! is_array($bomItems)) {
                        $bomItems = [];
                    }

                    $productVariants = DB::table('product_variants')
                        ->where('tenant_id', $tenantId)
                        ->where('product_id', $productId)
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

                    $payload['initialProduct'] = array_merge($productRow->toArray(), [
                        'bom_items' => $bomItems,
                        'product_variants' => $productVariants,
                        'has_variants' => $productVariants->isNotEmpty(),
                        'product_location_balances' => InventoryBalance::query()
                            ->leftJoin('locations', function ($join) {
                                $join->on('inventory_balances.location_id', '=', 'locations.id')
                                    ->on('inventory_balances.tenant_id', '=', 'locations.tenant_id');
                            })
                            ->where('inventory_balances.tenant_id', $tenantId)
                            ->where('inventory_balances.product_id', $productId)
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
                            ->all(),
                    ]);
                }
            }
        }

        return view($view, $this->routeContext->withTenant($payload));
    }
}
