<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class FeeProductTenantScopingTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_store_does_not_reuse_foreign_tenant_fee_product_and_creates_local_product(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+fee-primary@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+fee-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $foreignProductId = $this->insertProduct($foreignTenant, [
            'sku' => 'FEE-SERVICE-CHARGE',
            'name' => 'Service Charge',
        ]);

        $response = $this->postJson('/api/v1/fee-products', [
            'name' => 'Service Charge',
        ], $this->tenantHeaders($tenant->id));

        $createdId = $response->json('product.id');

        $response->assertOk()
            ->assertJsonPath('product.name', 'Service Charge')
            ->assertJsonPath('product.sku', 'FEE-SERVICE-CHARGE');

        $this->assertNotSame($foreignProductId, $createdId);

        $this->assertDatabaseHas('products', [
            'id' => $createdId,
            'tenant_id' => $tenant->id,
            'sku' => 'FEE-SERVICE-CHARGE',
            'name' => 'Service Charge',
        ]);
    }

    public function test_store_reuses_existing_active_tenant_product_by_name_when_sku_differs(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+fee-reuse@example.test');

        $this->actingAs($user, 'sanctum');

        $existingId = $this->insertProduct($tenant, [
            'sku' => 'FEE-MANUAL-001',
            'name' => 'Biaya Layanan',
        ]);

        $response = $this->postJson('/api/v1/fee-products', [
            'name' => 'Biaya Layanan',
        ], $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('product.id', $existingId)
            ->assertJsonPath('product.name', 'Biaya Layanan');

        $this->assertSame(1, DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Biaya Layanan')
            ->count());
    }

    private function insertProduct(Tenant $tenant, array $overrides): string
    {
        $productId = (string) Str::uuid();
        $now = now('UTC');

        DB::table('products')->insert(array_merge([
            'id' => $productId,
            'tenant_id' => $tenant->id,
            'category_id' => null,
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'name' => 'Produk Fee',
            'price' => 0,
            'hpp_average' => 0,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => false,
            'product_mode' => 'default',
            'item_type' => null,
            'variants' => null,
            'bom_items' => null,
            'bundle_items' => null,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], $overrides));

        return $productId;
    }
}