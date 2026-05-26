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

    public function test_products_api_uses_authenticated_user_tenant_when_domain_is_empty(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+empty-domain-products@example.test');

        DB::table('tenants')->where('id', $tenant->id)->update([
            'domain' => null,
            'subdomain' => null,
        ]);

        $productId = $this->insertProductForTenant($tenant->id, [
            'sku' => 'EMPTY-DOMAIN-001',
            'name' => 'Produk Tenant Domain Kosong',
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonPath('products.0.id', $productId)
            ->assertJsonPath('products.0.name', 'Produk Tenant Domain Kosong');
    }

    public function test_products_api_prioritizes_current_tenant_id_over_user_tenant_id(): void
    {
        [$tenantA, $user] = $this->bootApiActor('Admin', 'admin+current-tenant-products@example.test');

        $tenantB = Tenant::query()->create([
            'name' => 'Tenant Current',
            'timezone' => 'UTC',
            'domain' => null,
            'subdomain' => null,
        ]);

        $user->forceFill(['current_tenant_id' => $tenantB->id])->save();
        $this->insertRoleForTenant($tenantB->id, $user->id, 'Admin');

        $this->insertProductForTenant($tenantA->id, [
            'sku' => 'BASE-TENANT-001',
            'name' => 'Produk Tenant Awal',
        ]);
        $currentProductId = $this->insertProductForTenant($tenantB->id, [
            'sku' => 'CURRENT-TENANT-001',
            'name' => 'Produk Tenant Current',
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonPath('products.0.id', $currentProductId)
            ->assertJsonPath('products.0.name', 'Produk Tenant Current');

        $this->assertStringNotContainsString('Produk Tenant Awal', json_encode($response->json(), JSON_THROW_ON_ERROR));
    }

    public function test_products_api_rejects_x_tenant_id_outside_authenticated_user_tenants(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+tenant-mismatch@example.test');

        $foreignTenant = Tenant::query()->create([
            'name' => 'Tenant Foreign',
            'timezone' => 'UTC',
        ]);

        $this->insertProductForTenant($tenant->id, [
            'sku' => 'MATCH-001',
            'name' => 'Produk Match',
        ]);

        $response = $this->getJson('/api/v1/products', [
            'X-Tenant-Id' => $foreignTenant->id,
        ]);

        $response->assertForbidden()
            ->assertJsonPath('code', 'TENANT_MISMATCH');
    }

    public function test_can_export_all_product_recipes_as_excel(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+recipe-export@example.test');

        $componentId = $this->insertProductForTenant($tenant->id, [
            'sku' => 'RAW-GULA',
            'name' => 'Gula',
            'unit' => 'gram',
            'hpp_average' => 20,
            'product_mode' => 'add_on',
            'item_type' => 'raw_material',
        ]);

        $this->insertProductForTenant($tenant->id, [
            'sku' => 'FG-ROTI',
            'name' => 'Roti Manis',
            'product_mode' => 'bom',
            'production_mode' => 'auto_consume_on_sell',
            'item_type' => 'finished_good',
            'recipe_output_qty' => 2,
            'bom_items' => json_encode([
                [
                    'product_id' => $componentId,
                    'product_variant_id' => null,
                    'qty' => 70,
                ],
            ]),
        ]);

        $response = $this->get('/api/v1/products/recipes/export', $this->tenantHeaders($tenant->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertDownload();
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_product_show_returns_recipe_hpp_detail_and_can_export_single_recipe_cost(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+recipe-cost-detail@example.test');

        $flourId = $this->insertProductForTenant($tenant->id, [
            'sku' => 'RAW-TEPUNG',
            'name' => 'Tepung',
            'unit' => 'gram',
            'hpp_average' => 12.5,
            'product_mode' => 'add_on',
            'item_type' => 'raw_material',
        ]);
        $sugarId = $this->insertProductForTenant($tenant->id, [
            'sku' => 'RAW-GULA',
            'name' => 'Gula',
            'unit' => 'gram',
            'hpp_average' => 20,
            'product_mode' => 'add_on',
            'item_type' => 'raw_material',
        ]);

        $productId = $this->insertProductForTenant($tenant->id, [
            'sku' => 'FG-BUN',
            'name' => 'Sweet Bun',
            'hpp_average' => 900,
            'product_mode' => 'bom',
            'production_mode' => 'batch_production',
            'item_type' => 'finished_good',
            'recipe_output_qty' => 5,
            'bom_items' => json_encode([
                [
                    'product_id' => $flourId,
                    'product_variant_id' => null,
                    'qty' => 100,
                ],
                [
                    'product_id' => $sugarId,
                    'product_variant_id' => null,
                    'qty' => 25,
                ],
            ]),
        ]);

        $this->getJson('/api/v1/products/'.$productId, $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJsonPath('product.recipe_cost_detail.recipe_total_cost', 1750)
            ->assertJsonPath('product.recipe_cost_detail.hpp_per_output', 350)
            ->assertJsonPath('product.recipe_cost_detail.hpp_difference', -550)
            ->assertJsonPath('product.recipe_cost_detail.lines.0.component_name', 'Tepung')
            ->assertJsonPath('product.recipe_cost_detail.lines.0.line_total', 1250);

        $response = $this->get('/api/v1/products/'.$productId.'/recipe-cost/export', $this->tenantHeaders($tenant->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertDownload();
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    private function insertProductForTenant(string $tenantId, array $overrides = []): string
    {
        $productId = (string) Str::uuid();

        DB::table('products')->insert(array_merge([
            'id' => $productId,
            'tenant_id' => $tenantId,
            'sku' => 'TENANT-PRODUCT',
            'name' => 'Produk Tenant',
            'price' => 12000,
            'hpp_average' => 9000,
            'image_url' => null,
            'category_id' => null,
            'is_active' => true,
            'track_stock' => true,
            'product_mode' => 'default',
            'production_mode' => 'none',
            'item_type' => 'finished_good',
            'variants' => json_encode([]),
            'bom_items' => json_encode([]),
            'bundle_items' => json_encode([]),
            'created_by' => null,
            'updated_by' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ], $overrides));

        return $productId;
    }

    private function insertRoleForTenant(string $tenantId, string $userId, string $role): void
    {
        $roleId = (string) Str::uuid();

        DB::table('roles')->insert([
            'id' => $roleId,
            'tenant_id' => $tenantId,
            'name' => $role,
        ]);

        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }
}
