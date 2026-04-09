<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class ProductItemTypeCatalogApiTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_create_product_without_item_type_uses_mode_fallback(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+create-fallback@example.test');

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Bahan Gula',
            'sku' => 'RM-001',
            'price' => 15000,
            'product_mode' => 'add_on',
            'track_stock' => true,
        ], $this->tenantHeaders($tenant->id));

        $response->assertCreated();
        $response->assertJsonPath('product.item_type', 'raw_material');

        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenant->id,
            'sku' => 'RM-001',
            'item_type' => 'raw_material',
            'product_mode' => 'add_on',
        ]);
    }

    public function test_update_product_without_item_type_uses_mode_fallback(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+update-fallback@example.test');

        $productId = $this->insertProduct($tenant, [
            'sku' => 'UPD-001',
            'name' => 'Produk Update',
            'product_mode' => 'default',
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 20000,
        ]);

        $response = $this->putJson('/api/v1/products/'.$productId, [
            'name' => 'Produk Update v2',
            'sku' => 'UPD-001',
            'price' => 21000,
            'product_mode' => 'add_on',
            'track_stock' => true,
        ], $this->tenantHeaders($tenant->id));

        $response->assertOk();
        $response->assertJsonPath('product.item_type', 'raw_material');

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'tenant_id' => $tenant->id,
            'item_type' => 'raw_material',
            'product_mode' => 'add_on',
        ]);
    }

    public function test_create_product_rejects_category_from_another_tenant(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+cross-tenant-category-create@example.test');
        $foreignCategoryId = $this->insertCategoryForOtherTenant();

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Produk Tenant A',
            'sku' => 'CAT-CRT-001',
            'category_id' => $foreignCategoryId,
            'price' => 15000,
            'track_stock' => true,
        ], $this->tenantHeaders($tenant->id));

        $this->assertValidationErrorKey($response, 'category_id');
    }

    public function test_update_product_rejects_category_from_another_tenant(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+cross-tenant-category-update@example.test');
        $productId = $this->insertProduct($tenant, [
            'sku' => 'CAT-UPD-001',
            'name' => 'Produk Uji Category',
            'price' => 18000,
        ]);
        $foreignCategoryId = $this->insertCategoryForOtherTenant();

        $response = $this->putJson('/api/v1/products/'.$productId, [
            'name' => 'Produk Uji Category',
            'sku' => 'CAT-UPD-001',
            'category_id' => $foreignCategoryId,
            'price' => 18000,
            'track_stock' => true,
        ], $this->tenantHeaders($tenant->id));

        $this->assertValidationErrorKey($response, 'category_id');
    }

    public function test_create_with_invalid_item_type_returns_422(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+create-invalid-type@example.test');

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Produk Invalid',
            'sku' => 'INV-TYPE-001',
            'price' => 10000,
            'product_mode' => 'default',
            'item_type' => 'semi_finished',
        ], $this->tenantHeaders($tenant->id));

        $this->assertValidationErrorKey($response, 'item_type');
    }

    public function test_update_with_invalid_item_type_returns_422(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+update-invalid-type@example.test');

        $productId = $this->insertProduct($tenant, [
            'sku' => 'INV-UPD-001',
            'name' => 'Produk Ubah',
            'product_mode' => 'default',
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 10000,
        ]);

        $response = $this->putJson('/api/v1/products/'.$productId, [
            'name' => 'Produk Ubah',
            'sku' => 'INV-UPD-001',
            'price' => 12000,
            'product_mode' => 'default',
            'item_type' => 'semi_finished',
            'track_stock' => true,
        ], $this->tenantHeaders($tenant->id));

        $this->assertValidationErrorKey($response, 'item_type');
    }

    public function test_invalid_product_mode_item_type_combination_returns_422_on_create_and_update(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+invalid-combo@example.test');

        $createResponse = $this->postJson('/api/v1/products', [
            'name' => 'Produk Salah Kombinasi',
            'sku' => 'INV-CMB-CRT-001',
            'price' => 10000,
            'product_mode' => 'add_on',
            'item_type' => 'finished_good',
            'track_stock' => true,
        ], $this->tenantHeaders($tenant->id));

        $this->assertValidationErrorKey($createResponse, 'item_type');

        $productId = $this->insertProduct($tenant, [
            'sku' => 'INV-CMB-UPD-001',
            'name' => 'Produk Valid',
            'product_mode' => 'default',
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 10000,
        ]);

        $updateResponse = $this->putJson('/api/v1/products/'.$productId, [
            'name' => 'Produk Valid',
            'sku' => 'INV-CMB-UPD-001',
            'price' => 13000,
            'product_mode' => 'add_on',
            'item_type' => 'finished_good',
            'track_stock' => true,
        ], $this->tenantHeaders($tenant->id));

        $this->assertValidationErrorKey($updateResponse, 'item_type');
    }

    public function test_service_with_track_stock_true_returns_422_on_create_and_update(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+service-stock@example.test');

        $createResponse = $this->postJson('/api/v1/products', [
            'name' => 'Jasa Kirim',
            'sku' => 'SRV-CRT-001',
            'price' => 5000,
            'product_mode' => 'default',
            'item_type' => 'service',
            'track_stock' => true,
        ], $this->tenantHeaders($tenant->id));

        $this->assertValidationErrorKey($createResponse, 'track_stock');

        $productId = $this->insertProduct($tenant, [
            'sku' => 'SRV-UPD-001',
            'name' => 'Produk Siap Ubah',
            'product_mode' => 'default',
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 10000,
        ]);

        $updateResponse = $this->putJson('/api/v1/products/'.$productId, [
            'name' => 'Produk Siap Ubah',
            'sku' => 'SRV-UPD-001',
            'price' => 11000,
            'product_mode' => 'default',
            'item_type' => 'service',
            'track_stock' => true,
        ], $this->tenantHeaders($tenant->id));

        $this->assertValidationErrorKey($updateResponse, 'track_stock');
    }

    public function test_create_bom_with_service_component_returns_422(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+bom-service@example.test');

        $serviceComponentId = $this->insertProduct($tenant, [
            'sku' => 'CMP-SRV-001',
            'name' => 'Komponen Service Legacy',
            'product_mode' => 'add_on',
            'item_type' => 'service',
            'track_stock' => false,
            'price' => 1,
        ]);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Paket BOM Salah',
            'sku' => 'BOM-001',
            'price' => 30000,
            'product_mode' => 'bom',
            'item_type' => 'finished_good',
            'track_stock' => true,
            'bom_items' => [
                [
                    'product_id' => $serviceComponentId,
                    'name' => 'Komponen Service Legacy',
                    'qty' => 1,
                    'unit' => 'pcs',
                ],
            ],
        ], $this->tenantHeaders($tenant->id));

        $this->assertValidationErrorKey($response, 'bom_items');
    }

    public function test_create_bom_with_variant_raw_material_requires_product_variant_id(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+bom-variant-required@example.test');

        $rawMaterialId = $this->insertProduct($tenant, [
            'sku' => 'CMP-VAR-001',
            'name' => 'Komponen Variant',
            'product_mode' => 'add_on',
            'item_type' => 'raw_material',
            'track_stock' => true,
            'price' => 5000,
        ]);
        $this->insertVariant($tenant, $rawMaterialId, 'RMV-REQ-001');

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Paket BOM Variant',
            'sku' => 'BOM-VAR-001',
            'price' => 30000,
            'product_mode' => 'bom',
            'item_type' => 'finished_good',
            'track_stock' => true,
            'bom_items' => [
                [
                    'product_id' => $rawMaterialId,
                    'name' => 'Komponen Variant',
                    'qty' => 1,
                    'unit' => 'pcs',
                ],
            ],
        ], $this->tenantHeaders($tenant->id));

        $this->assertValidationErrorLiteralKey($response, 'bom_items.0.product_variant_id');
    }

    public function test_create_bom_rejects_variant_that_does_not_belong_to_component_product(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+bom-variant-mismatch@example.test');

        $rawMaterialAId = $this->insertProduct($tenant, [
            'sku' => 'CMP-VAR-A01',
            'name' => 'Komponen Variant A',
            'product_mode' => 'add_on',
            'item_type' => 'raw_material',
            'track_stock' => true,
            'price' => 5000,
        ]);
        $rawMaterialBId = $this->insertProduct($tenant, [
            'sku' => 'CMP-VAR-B01',
            'name' => 'Komponen Variant B',
            'product_mode' => 'add_on',
            'item_type' => 'raw_material',
            'track_stock' => true,
            'price' => 5000,
        ]);
        $foreignVariantId = $this->insertVariant($tenant, $rawMaterialBId, 'RMV-MIS-001');

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Paket BOM Variant Mismatch',
            'sku' => 'BOM-VAR-002',
            'price' => 30000,
            'product_mode' => 'bom',
            'item_type' => 'finished_good',
            'track_stock' => true,
            'bom_items' => [
                [
                    'product_id' => $rawMaterialAId,
                    'product_variant_id' => $foreignVariantId,
                    'name' => 'Komponen Variant A',
                    'qty' => 1,
                    'unit' => 'pcs',
                ],
            ],
        ], $this->tenantHeaders($tenant->id));

        $this->assertValidationErrorLiteralKey($response, 'bom_items.0.product_variant_id');
    }

    public function test_create_non_variant_initial_stock_posts_to_null_variant_bucket(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+initial-stock-non-variant@example.test');
        $locationId = $this->insertDefaultLocation($tenant->id);

        DB::table('tenants')
            ->where('id', $tenant->id)
            ->update(['inventory_valuation_method' => 'fifo']);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Produk Non Variant',
            'sku' => 'INIT-NV-001',
            'price' => 22000,
            'hpp_average' => 12000,
            'track_stock' => true,
            'location_id' => $locationId,
            'initial_stock' => 5,
        ], $this->tenantHeaders($tenant->id));

        $response->assertCreated();
        $productId = (string) $response->json('product.id');

        $this->assertInventoryBalanceQty($tenant->id, $productId, null, $locationId, 5.0);
        $this->assertLedgerQtyIn($tenant->id, $productId, null, $locationId, 'initial_stock', 5.0);
        $this->assertValuationStateQty($tenant->id, $productId, null, $locationId, 5.0);
        $this->assertFifoLayerQty($tenant->id, $productId, null, $locationId, 5.0);
    }

    public function test_create_variant_product_initial_stock_posts_per_variant_bucket(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+initial-stock-variant-create@example.test');
        $locationId = $this->insertDefaultLocation($tenant->id);

        DB::table('tenants')
            ->where('id', $tenant->id)
            ->update(['inventory_valuation_method' => 'fifo']);

        $variantA = (string) Str::uuid();
        $variantB = (string) Str::uuid();

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Produk Variant',
            'sku' => 'INIT-VAR-001',
            'price' => 34000,
            'hpp_average' => 10000,
            'track_stock' => true,
            'location_id' => $locationId,
            'variants' => [
                ['id' => $variantA, 'sku' => 'VAR-A', 'name' => 'Variant A'],
                ['id' => $variantB, 'sku' => 'VAR-B', 'name' => 'Variant B'],
            ],
            'initial_stock_lines' => [
                ['product_variant_id' => $variantA, 'qty' => 3, 'unit_cost' => 11000],
                ['product_variant_id' => $variantB, 'qty' => 2, 'unit_cost' => 13000],
            ],
        ], $this->tenantHeaders($tenant->id));

        $response->assertCreated();
        $productId = (string) $response->json('product.id');

        $this->assertDatabaseHas('product_variants', [
            'tenant_id' => $tenant->id,
            'product_id' => $productId,
            'id' => $variantA,
            'sku' => 'VAR-A',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'tenant_id' => $tenant->id,
            'product_id' => $productId,
            'id' => $variantB,
            'sku' => 'VAR-B',
            'is_active' => true,
        ]);

        $this->assertInventoryBalanceQty($tenant->id, $productId, $variantA, $locationId, 3.0);
        $this->assertInventoryBalanceQty($tenant->id, $productId, $variantB, $locationId, 2.0);
        $this->assertSame(0, $this->inventoryBalanceQuery($tenant->id, $productId, null, $locationId)->count());
        $this->assertLedgerQtyIn($tenant->id, $productId, $variantA, $locationId, 'initial_stock', 3.0);
        $this->assertLedgerQtyIn($tenant->id, $productId, $variantB, $locationId, 'initial_stock', 2.0);
        $this->assertValuationStateQty($tenant->id, $productId, $variantA, $locationId, 3.0);
        $this->assertValuationStateQty($tenant->id, $productId, $variantB, $locationId, 2.0);
        $this->assertFifoLayerQty($tenant->id, $productId, $variantA, $locationId, 3.0);
        $this->assertFifoLayerQty($tenant->id, $productId, $variantB, $locationId, 2.0);
    }

    public function test_create_variant_initial_stock_rejects_missing_product_variant_id(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+initial-stock-variant-missing@example.test');
        $locationId = $this->insertDefaultLocation($tenant->id);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Produk Variant Invalid',
            'sku' => 'INIT-VAR-ERR-001',
            'price' => 34000,
            'hpp_average' => 10000,
            'track_stock' => true,
            'location_id' => $locationId,
            'variants' => [
                ['id' => (string) Str::uuid(), 'sku' => 'VAR-X', 'name' => 'Variant X'],
            ],
            'initial_stock_lines' => [
                ['qty' => 3, 'unit_cost' => 11000],
            ],
        ], $this->tenantHeaders($tenant->id));

        $this->assertValidationErrorLiteralKey($response, 'initial_stock_lines.0.product_variant_id');
    }

    public function test_update_variant_product_initial_stock_posts_to_existing_variant_bucket(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+initial-stock-variant-update@example.test');
        $locationId = $this->insertDefaultLocation($tenant->id);

        DB::table('tenants')
            ->where('id', $tenant->id)
            ->update(['inventory_valuation_method' => 'fifo']);

        $productId = $this->insertProduct($tenant, [
            'sku' => 'UPD-VAR-001',
            'name' => 'Produk Update Variant',
            'price' => 19000,
            'hpp_average' => 9000,
            'track_stock' => true,
        ]);
        $variantA = $this->insertVariant($tenant, $productId, 'UPD-VAR-A');
        $variantB = $this->insertVariant($tenant, $productId, 'UPD-VAR-B');

        $response = $this->putJson('/api/v1/products/'.$productId, [
            'name' => 'Produk Update Variant',
            'sku' => 'UPD-VAR-001',
            'price' => 20000,
            'hpp_average' => 9500,
            'initial_stock_lines' => [
                ['product_variant_id' => $variantA, 'qty' => 4, 'unit_cost' => 9000],
            ],
            'location_id' => $locationId,
        ], $this->tenantHeaders($tenant->id));

        $response->assertOk();

        $this->assertInventoryBalanceQty($tenant->id, $productId, $variantA, $locationId, 4.0);
        $this->assertSame(0, $this->inventoryBalanceQuery($tenant->id, $productId, $variantB, $locationId)->count());
        $this->assertSame(0, $this->inventoryBalanceQuery($tenant->id, $productId, null, $locationId)->count());
        $this->assertLedgerQtyIn($tenant->id, $productId, $variantA, $locationId, 'initial_stock', 4.0);
        $this->assertValuationStateQty($tenant->id, $productId, $variantA, $locationId, 4.0);
        $this->assertFifoLayerQty($tenant->id, $productId, $variantA, $locationId, 4.0);
    }

    public function test_legacy_payload_without_item_type_stays_successful_and_show_is_consistent(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+legacy-show@example.test');

        $createResponse = $this->postJson('/api/v1/products', [
            'name' => 'Produk Legacy',
            'sku' => 'LEG-001',
            'price' => 17000,
            'track_stock' => true,
        ], $this->tenantHeaders($tenant->id));

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('product.product_mode', 'default');
        $createResponse->assertJsonPath('product.item_type', 'finished_good');

        $productId = (string) $createResponse->json('product.id');

        $showResponse = $this->getJson('/api/v1/products/'.$productId, $this->tenantHeaders($tenant->id));

        $showResponse->assertOk();
        $showResponse->assertJsonPath('product.sku', 'LEG-001');
        $showResponse->assertJsonPath('product.name', 'Produk Legacy');
        $showResponse->assertJsonPath('product.product_mode', 'default');
        $showResponse->assertJsonPath('product.production_mode', 'none');
        $showResponse->assertJsonPath('product.item_type', 'finished_good');
    }

    public function test_create_and_update_product_persists_production_mode(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+production-mode-product@example.test');

        $createResponse = $this->postJson('/api/v1/products', [
            'name' => 'Roti Batch',
            'sku' => 'BATCH-001',
            'price' => 18000,
            'track_stock' => true,
            'product_mode' => 'default',
            'item_type' => 'finished_good',
            'production_mode' => 'batch_production',
            'bom_items' => [],
        ], $this->tenantHeaders($tenant->id));

        $createResponse->assertCreated()
            ->assertJsonPath('product.production_mode', 'batch_production');

        $productId = (string) $createResponse->json('product.id');

        $updateResponse = $this->putJson('/api/v1/products/'.$productId, [
            'name' => 'Roti Instan',
            'sku' => 'BATCH-001',
            'price' => 19000,
            'track_stock' => true,
            'product_mode' => 'bom',
            'item_type' => 'finished_good',
            'production_mode' => 'auto_consume_on_sell',
            'bom_items' => [],
        ], $this->tenantHeaders($tenant->id));

        $updateResponse->assertOk()
            ->assertJsonPath('product.production_mode', 'auto_consume_on_sell');

        $showResponse = $this->getJson('/api/v1/products/'.$productId, $this->tenantHeaders($tenant->id));

        $showResponse->assertOk()
            ->assertJsonPath('product.production_mode', 'auto_consume_on_sell');
    }

    public function test_show_product_returns_decoded_bom_items_and_active_product_variants(): void
    {
        [$tenant] = $this->bootApiActor('Admin', 'admin+show-bom-variants@example.test');

        $rawMaterialId = $this->insertProduct($tenant, [
            'sku' => 'SHOW-RM-001',
            'name' => 'Bahan Baku Show',
            'product_mode' => 'add_on',
            'item_type' => 'raw_material',
            'track_stock' => true,
            'price' => 5000,
        ]);
        $activeVariantId = $this->insertVariant($tenant, $rawMaterialId, 'SHOW-VAR-001');
        $inactiveVariantId = (string) Str::uuid();
        DB::table('product_variants')->insert([
            'id' => $inactiveVariantId,
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'sku' => 'SHOW-VAR-OLD',
            'name' => 'Variant Lama',
            'is_active' => false,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $productId = $this->insertProduct($tenant, [
            'sku' => 'SHOW-FG-001',
            'name' => 'Finished Good Show',
            'product_mode' => 'bom',
            'item_type' => 'finished_good',
            'track_stock' => true,
            'price' => 15000,
            'bom_items' => json_encode([
                [
                    'product_id' => $rawMaterialId,
                    'product_variant_id' => $activeVariantId,
                    'qty' => 2.5,
                ],
            ]),
        ]);

        $showResponse = $this->getJson('/api/v1/products/'.$productId, $this->tenantHeaders($tenant->id));

        $showResponse->assertOk();
        $showResponse->assertJsonPath('product.sku', 'SHOW-FG-001');
        $showResponse->assertJsonPath('product.name', 'Finished Good Show');
        $showResponse->assertJsonPath('product.product_mode', 'bom');
        $showResponse->assertJsonPath('product.bom_items.0.product_id', $rawMaterialId);
        $showResponse->assertJsonPath('product.bom_items.0.product_variant_id', $activeVariantId);
        $showResponse->assertJsonPath('product.bom_items.0.qty', 2.5);
        $showResponse->assertJsonPath('product.product_variants', []);
        $showResponse->assertJsonPath('product.has_variants', false);

        $rawMaterialShow = $this->getJson('/api/v1/products/'.$rawMaterialId, $this->tenantHeaders($tenant->id));

        $rawMaterialShow->assertOk();
        $rawMaterialShow->assertJsonPath('product.sku', 'SHOW-RM-001');
        $rawMaterialShow->assertJsonPath('product.has_variants', true);
        $rawMaterialShow->assertJsonCount(1, 'product.product_variants');
        $rawMaterialShow->assertJsonPath('product.product_variants.0.id', $activeVariantId);
    }

    private function insertProduct(Tenant $tenant, array $overrides): string
    {
        $id = (string) Str::uuid();
        $now = now('UTC');

        DB::table('products')->insert(array_merge([
            'id' => $id,
            'tenant_id' => $tenant->id,
            'category_id' => null,
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'name' => 'Produk Fixture',
            'price' => 10000,
            'hpp_average' => 0,
            'image_url' => null,
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
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], $overrides));

        return $id;
    }

    private function insertCategoryForOtherTenant(): string
    {
        $tenantId = (string) Str::uuid();

        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Tenant Lain',
            'timezone' => 'UTC',
            'created_at' => now('UTC'),
        ]);

        $categoryId = (string) Str::uuid();

        DB::table('categories')->insert([
            'id' => $categoryId,
            'tenant_id' => $tenantId,
            'name' => 'Kategori Tenant Lain',
            'slug' => 'kategori-tenant-lain',
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return $categoryId;
    }

    private function insertVariant(Tenant $tenant, string $productId, string $sku): string
    {
        $variantId = (string) Str::uuid();

        DB::table('product_variants')->insert([
            'id' => $variantId,
            'tenant_id' => $tenant->id,
            'product_id' => $productId,
            'sku' => $sku,
            'barcode' => null,
            'name' => 'Variant '.substr($sku, -3),
            'attributes' => json_encode(['kode' => $sku]),
            'price_override' => null,
            'is_active' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return $variantId;
    }

    private function insertDefaultLocation(string $tenantId): string
    {
        $branchId = (string) Str::uuid();
        $locationId = (string) Str::uuid();

        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenantId,
            'name' => 'Branch Default',
        ]);

        DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'name' => 'Gudang Default',
            'is_default' => true,
        ]);

        return $locationId;
    }

    private function inventoryBalanceQuery(string $tenantId, string $productId, ?string $productVariantId, string $locationId)
    {
        return DB::table('inventory_balances')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->when($productVariantId, function ($query) use ($productVariantId) {
                $query->where('product_variant_id', $productVariantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            });
    }

    private function assertInventoryBalanceQty(string $tenantId, string $productId, ?string $productVariantId, string $locationId, float $expectedQty): void
    {
        $qty = (float) $this->inventoryBalanceQuery($tenantId, $productId, $productVariantId, $locationId)->value('qty_on_hand');
        $this->assertSame($expectedQty, $qty);
    }

    private function assertLedgerQtyIn(string $tenantId, string $productId, ?string $productVariantId, string $locationId, string $referenceType, float $expectedQty): void
    {
        $qtyIn = (float) DB::table('stock_ledger')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('reference_type', $referenceType)
            ->when($productVariantId, function ($query) use ($productVariantId) {
                $query->where('product_variant_id', $productVariantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            })
            ->sum('qty_in');

        $this->assertSame($expectedQty, $qtyIn);
    }

    private function assertValuationStateQty(string $tenantId, string $productId, ?string $productVariantId, string $locationId, float $expectedQty): void
    {
        $qty = (float) DB::table('inventory_cost_states')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->when($productVariantId, function ($query) use ($productVariantId) {
                $query->where('product_variant_id', $productVariantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            })
            ->value('qty_on_hand');

        $this->assertSame($expectedQty, $qty);
    }

    private function assertFifoLayerQty(string $tenantId, string $productId, ?string $productVariantId, string $locationId, float $expectedQty): void
    {
        $qty = (float) DB::table('inventory_layers')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->when($productVariantId, function ($query) use ($productVariantId) {
                $query->where('product_variant_id', $productVariantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            })
            ->sum('qty_remaining');

        $this->assertSame($expectedQty, $qty);
    }

    private function assertValidationErrorKey($response, string $key): void
    {
        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->assertIsArray($response->json('details.'.$key));
        $this->assertNotEmpty($response->json('details.'.$key));
    }

    private function assertValidationErrorLiteralKey($response, string $key): void
    {
        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        $details = (array) $response->json('details');

        $this->assertArrayHasKey($key, $details);
        $this->assertIsArray($details[$key] ?? null);
        $this->assertNotEmpty($details[$key] ?? []);
    }
}
