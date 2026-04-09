<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_new_user_sees_empty_products(): void
    {
        [$tenantA] = $this->bootApiActor('Admin', 'admin@example.test');

        $tenantB = Tenant::query()->create([
            'name' => 'Tenant B',
            'timezone' => 'UTC',
        ]);

        DB::table('products')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'sku' => 'PRD-OTHER',
            'name' => 'Produk Tenant B',
            'price' => 10000,
            'hpp_average' => 8000,
            'image_url' => null,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $response = $this->getJson('/api/v1/products', $this->tenantHeaders($tenantA->id));

        $response->assertStatus(200)->assertJsonCount(0, 'products');
    }

    public function test_tenant_context_null_returns_empty(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant C',
            'timezone' => 'UTC',
        ]);

        DB::table('products')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'sku' => 'PRD-ONE',
            'name' => 'Produk Tenant C',
            'price' => 12000,
            'hpp_average' => 9000,
            'image_url' => null,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $this->getJson('/api/v1/products')
            ->assertStatus(400)
            ->assertJsonPath('message', 'Tenant tidak ditemukan.');
    }

    public function test_product_lite_mode_returns_scoped_paginated_catalog_and_location_stock(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+product-lite@example.test');
        $this->actingAs($user, 'sanctum');

        $categoryId = (string) Str::uuid();
        $locationId = (string) Str::uuid();
        $branchId = (string) Str::uuid();
        $productId = (string) Str::uuid();
        $foreignTenantId = (string) Str::uuid();
        $foreignProductId = (string) Str::uuid();

        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenant->id,
            'name' => 'Cabang Lite',
        ]);

        DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'name' => 'Gudang Lite',
            'is_default' => true,
        ]);

        DB::table('categories')->insert([
            'id' => $categoryId,
            'tenant_id' => $tenant->id,
            'name' => 'Minuman',
            'slug' => 'minuman',
        ]);

        DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $tenant->id,
            'sku' => 'LITE-001',
            'name' => 'Es Teh Lite',
            'price' => 12000,
            'hpp_average' => 5000,
            'image_url' => null,
            'category_id' => $categoryId,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        DB::table('product_price_tiers')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $productId,
            'label' => 'Grosir',
            'min_qty' => 3,
            'price' => 10000,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenant->id,
            'location_id' => $locationId,
            'product_id' => $productId,
            'qty_on_hand' => 17,
        ]);

        DB::table('tenants')->insert([
            'id' => $foreignTenantId,
            'name' => 'Tenant Asing Lite',
            'timezone' => 'UTC',
            'created_at' => now('UTC'),
        ]);

        DB::table('products')->insert([
            'id' => $foreignProductId,
            'tenant_id' => $foreignTenantId,
            'sku' => 'FOREIGN-001',
            'name' => 'Produk Asing',
            'price' => 22000,
            'hpp_average' => 8000,
            'image_url' => null,
            'category_id' => null,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $response = $this->getJson('/api/v1/products?lite=1&q=teh&location_id='.$locationId.'&per_page=10', $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('products.0.id', $productId)
            ->assertJsonPath('products.0.qty_on_hand', 17)
            ->assertJsonPath('products.0.price_tiers.0.min_qty', 3)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('filters.location_id', $locationId);

        $this->assertStringNotContainsString('Produk Asing', json_encode($response->json(), JSON_THROW_ON_ERROR));
    }
}
