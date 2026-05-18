<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerCategoryController extends Controller
{
    public function index()
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $categories = CustomerCategory::query()
            ->withoutGlobalScopes()
            ->where('customer_categories.tenant_id', $tenantId)
            ->leftJoin('customers', function ($join) use ($tenantId) {
                $join->on('customer_categories.id', '=', 'customers.customer_category_id')
                    ->where('customers.tenant_id', $tenantId);
            })
            ->select([
                'customer_categories.id',
                'customer_categories.name',
                'customer_categories.slug',
                'customer_categories.color',
                'customer_categories.created_at',
                'customer_categories.updated_at',
            ])
            ->selectRaw('count(customers.id) as customer_count')
            ->groupBy(
                'customer_categories.id',
                'customer_categories.name',
                'customer_categories.slug',
                'customer_categories.color',
                'customer_categories.created_at',
                'customer_categories.updated_at'
            )
            ->orderBy('customer_categories.name')
            ->get();

        return response()->json([
            'customer_categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = TenantContext::id();

        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', Rule::in($this->allowedColors())],
        ]);

        $category = CustomerCategory::query()->create([
            'tenant_id' => $tenantId,
            'name' => trim((string) $payload['name']),
            'slug' => $this->buildUniqueSlug($tenantId, (string) $payload['name']),
            'color' => $payload['color'] ?? 'slate',
        ]);

        return response()->json([
            'message' => 'Kategori pelanggan berhasil ditambahkan.',
            'customer_category' => $this->serializeCategory($category, 0),
        ], 201);
    }

    public function update(Request $request, CustomerCategory $customerCategory)
    {
        $tenantId = $this->assertTenantCategory($customerCategory);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', Rule::in($this->allowedColors())],
        ]);

        $customerCategory->forceFill([
            'name' => trim((string) $payload['name']),
            'slug' => $this->buildUniqueSlug($tenantId, (string) $payload['name'], (string) $customerCategory->id),
            'color' => $payload['color'] ?? 'slate',
        ])->save();

        return response()->json([
            'message' => 'Kategori pelanggan berhasil diperbarui.',
            'customer_category' => $this->serializeCategory(
                $customerCategory->refresh(),
                $this->customerCount($tenantId, (string) $customerCategory->id)
            ),
        ]);
    }

    public function destroy(CustomerCategory $customerCategory)
    {
        $tenantId = $this->assertTenantCategory($customerCategory);

        DB::transaction(function () use ($tenantId, $customerCategory): void {
            Customer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('customer_category_id', $customerCategory->id)
                ->update(['customer_category_id' => null]);

            $customerCategory->delete();
        });

        return response()->json([
            'message' => 'Kategori pelanggan dihapus. Pelanggan yang memakai kategori ini dipindah ke tanpa kategori.',
        ]);
    }

    private function assertTenantCategory(CustomerCategory $category): string
    {
        $tenantId = TenantContext::id();

        abort_unless($tenantId && (string) $category->tenant_id === (string) $tenantId, 404);

        return (string) $tenantId;
    }

    private function customerCount(string $tenantId, string $categoryId): int
    {
        return Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_category_id', $categoryId)
            ->count();
    }

    private function serializeCategory(CustomerCategory $category, int $customerCount): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'color' => $category->color ?: 'slate',
            'customer_count' => $customerCount,
        ];
    }

    private function allowedColors(): array
    {
        return ['slate', 'orange', 'emerald', 'sky', 'violet', 'rose', 'amber'];
    }

    private function buildUniqueSlug(string $tenantId, string $name, ?string $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        if ($baseSlug === '') {
            $baseSlug = 'kategori-pelanggan';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (CustomerCategory::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
