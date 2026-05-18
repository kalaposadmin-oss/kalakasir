<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id();

        $categories = Cache::remember("categories:index:{$tenantId}", now('UTC')->addMinutes(5), function () use ($tenantId) {
            return Category::query()
                ->when($tenantId, fn ($query) => $query->where('categories.tenant_id', $tenantId))
                ->leftJoin('products', function ($join) {
                    $join->on('categories.id', '=', 'products.category_id')
                        ->on('categories.tenant_id', '=', 'products.tenant_id')
                        ->whereNull('products.deleted_at');
                })
                ->select('categories.id', 'categories.name', 'categories.description', 'categories.icon', 'categories.slug')
                ->selectRaw('count(products.id) as product_count')
                ->groupBy('categories.id', 'categories.name', 'categories.description', 'categories.icon', 'categories.slug')
                ->orderBy('categories.name')
                ->get();
        });

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
        ]);

        $slug = $this->buildUniqueSlug($tenantId, $validated['name']);

        $category = Category::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'slug' => $slug,
        ]);
        Cache::forget("categories:index:{$tenantId}");
        Cache::forget("products:index:{$tenantId}");

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'icon' => $category->icon,
                'slug' => $category->slug,
            ],
        ], 201);
    }

    public function show(string $category)
    {
        $tenantId = TenantContext::id();

        $categoryRow = Category::query()
            ->when($tenantId, fn ($query) => $query->where('categories.tenant_id', $tenantId))
            ->leftJoin('products', function ($join) {
                $join->on('categories.id', '=', 'products.category_id')
                    ->on('categories.tenant_id', '=', 'products.tenant_id')
                    ->whereNull('products.deleted_at');
            })
            ->where('categories.id', $category)
            ->select('categories.id', 'categories.name', 'categories.description', 'categories.icon', 'categories.slug')
            ->selectRaw('count(products.id) as product_count')
            ->groupBy('categories.id', 'categories.name', 'categories.description', 'categories.icon', 'categories.slug')
            ->firstOrFail();

        return response()->json([
            'category' => $categoryRow,
        ]);
    }

    public function update(Request $request, string $category)
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant tidak ditemukan.',
            ], 400);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
        ]);

        $categoryRow = Category::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('id', $category)
            ->firstOrFail();
        $slug = $this->buildUniqueSlug($tenantId, $validated['name'], $categoryRow->id);

        $categoryRow->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'slug' => $slug,
        ]);
        Cache::forget("categories:index:{$tenantId}");
        Cache::forget("products:index:{$tenantId}");

        return response()->json([
            'category' => [
                'id' => $categoryRow->id,
                'name' => $categoryRow->name,
                'description' => $categoryRow->description,
                'icon' => $categoryRow->icon,
                'slug' => $categoryRow->slug,
            ],
        ]);
    }

    public function destroy(string $category)
    {
        $tenantId = TenantContext::id();

        $categoryRow = Category::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('id', $category)
            ->firstOrFail();

        $hasProducts = Product::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('category_id', $categoryRow->id)
            ->exists();

        if ($hasProducts) {
            return response()->json([
                'message' => 'Kategori masih digunakan oleh produk.',
            ], 422);
        }

        $categoryRow->delete();
        Cache::forget("categories:index:{$tenantId}");
        Cache::forget("products:index:{$tenantId}");

        return response()->json([
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }

    private function buildUniqueSlug(string $tenantId, string $name, ?string $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        if ($baseSlug === '') {
            $baseSlug = 'kategori';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (Category::query()
            ->where('tenant_id', $tenantId)
            ->when($ignoreId, function ($query) use ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            })
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
