<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FeeProductController extends Controller
{
    public function store(Request $request)
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant tidak ditemukan.'], 400);
        }

        $name = trim($payload['name']);
        $baseSku = 'FEE-' . strtoupper(Str::slug($name, '-'));
        $sku = $baseSku ?: 'FEE-' . strtoupper(Str::random(6));

        $product = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('sku', $sku)
            ->first();

        if (! $product) {
            $product = Product::query()
                ->where('tenant_id', $tenantId)
                ->where('name', $name)
                ->first();
        }

        if (! $product) {
            $sku = $this->ensureUniqueSku($sku, $tenantId);

            $product = Product::query()->create([
                'tenant_id' => $tenantId,
                'category_id' => null,
                'sku' => $sku,
                'name' => $name,
                'price' => 0,
                'hpp_average' => 0,
                'image_url' => null,
                'is_active' => true,
                'track_stock' => false,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);
        }

        return response()->json([
            'product' => $product->only(['id', 'sku', 'name']),
        ]);
    }

    private function ensureUniqueSku(string $sku, string $tenantId): string
    {
        $exists = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('sku', $sku)
            ->exists();

        if (! $exists) {
            return $sku;
        }

        return $sku . '-' . strtoupper(Str::random(4));
    }
}
