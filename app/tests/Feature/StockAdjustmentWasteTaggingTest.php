<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class StockAdjustmentWasteTaggingTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_store_adjustment_persists_waste_fields(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+adjustment-waste@example.test');

        $fixture = $this->seedFixture($tenant);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/stock-adjustments', [
                'location_id' => $fixture['location_id'],
                'reference_no' => 'ADJ-WASTE-001',
                'reason_code' => 'A02',
                'waste_type' => 'damaged',
                'production_batch_id' => $fixture['production_batch_id'],
                'notes' => 'Barang rusak saat proses produksi batch pagi.',
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'physical_qty' => 2,
                    ],
                ],
            ]);

        $this->assertSame(201, $response->status(), $response->getContent());
        $response->assertJsonPath('status', 'draft');

        $adjustmentId = (string) $response->json('adjustment_id');

        $this->assertDatabaseHas('stock_adjustments', [
            'id' => $adjustmentId,
            'tenant_id' => $tenant->id,
            'waste_type' => 'damaged',
            'production_batch_id' => $fixture['production_batch_id'],
            'reason_code' => 'A02',
        ]);
    }

    public function test_store_adjustment_rejects_production_batch_from_other_tenant(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+adjustment-waste-scope@example.test');
        $otherTenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Lain',
            'timezone' => 'UTC',
        ]);

        $fixture = $this->seedFixture($tenant);
        $otherFixture = $this->seedFixture($otherTenant);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/stock-adjustments', [
                'location_id' => $fixture['location_id'],
                'reason_code' => 'A02',
                'waste_type' => 'loss',
                'production_batch_id' => $otherFixture['production_batch_id'],
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'physical_qty' => 3,
                    ],
                ],
            ]);

        $this->assertSame(422, $response->status(), $response->getContent());
        $response
            ->assertJsonPath('code', 'ADJUSTMENT_CREATE_FAILED')
            ->assertJsonPath('message', 'Production batch tidak ditemukan untuk tenant aktif.');
    }

    public function test_store_adjustment_rejects_location_from_other_tenant(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+adjustment-location-scope@example.test');
        $otherTenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Lokasi Lain',
            'timezone' => 'UTC',
        ]);

        $fixture = $this->seedFixture($tenant);
        $otherFixture = $this->seedFixture($otherTenant);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/stock-adjustments', [
                'location_id' => $otherFixture['location_id'],
                'reason_code' => 'A02',
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'physical_qty' => 2,
                    ],
                ],
            ]);

        $this->assertValidationErrorKey($response, 'location_id');
    }

    public function test_store_adjustment_rejects_product_from_other_tenant(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+adjustment-product-scope@example.test');
        $otherTenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Produk Lain',
            'timezone' => 'UTC',
        ]);

        $fixture = $this->seedFixture($tenant);
        $otherFixture = $this->seedFixture($otherTenant);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/stock-adjustments', [
                'location_id' => $fixture['location_id'],
                'reason_code' => 'A02',
                'items' => [
                    [
                        'product_id' => $otherFixture['product_id'],
                        'physical_qty' => 2,
                    ],
                ],
            ]);

        $this->assertValidationErrorKey($response, 'items.0.product_id');
    }

    private function seedFixture(Tenant $tenant): array
    {
        $branchId = (string) Str::uuid();
        $locationId = (string) Str::uuid();

        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenant->id,
            'name' => 'Branch Waste',
        ]);

        DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'name' => 'Waste Room',
            'is_default' => true,
        ]);

        $productId = $this->insertProduct($tenant, [
            'sku' => 'RM-WASTE-001',
            'name' => 'Bahan Uji Waste',
            'product_mode' => 'add_on',
            'item_type' => 'raw_material',
            'track_stock' => true,
            'price' => 5000,
        ]);

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenant->id,
            'product_id' => $productId,
            'location_id' => $locationId,
            'qty_on_hand' => 5,
        ]);

        DB::table('inventory_cost_states')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $productId,
            'location_id' => $locationId,
            'qty_on_hand' => 5,
            'average_cost' => 3000,
            'last_cost' => 3000,
            'updated_at' => now('UTC'),
        ]);

        $batchId = (string) Str::uuid();
        DB::table('production_batches')->insert([
            'id' => $batchId,
            'tenant_id' => $tenant->id,
            'location_id' => $locationId,
            'reference_no' => 'PB-WASTE-001',
            'status' => 'draft',
            'notes' => null,
            'created_by' => null,
            'created_at' => now('UTC'),
            'transaction_date' => now('UTC')->toDateString(),
            'posted_at' => null,
            'posted_by' => null,
        ]);

        return [
            'branch_id' => $branchId,
            'location_id' => $locationId,
            'product_id' => $productId,
            'production_batch_id' => $batchId,
        ];
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
            'name' => 'Produk Fixture Waste',
            'price' => 10000,
            'hpp_average' => 0,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'product_mode' => 'default',
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

    private function assertValidationErrorKey($response, string $key): void
    {
        $response->assertStatus(422);

        $this->assertNotSame('', (string) $response->json('message'));

        $errors = (array) $response->json('errors');

        $this->assertArrayHasKey($key, $errors);
        $this->assertIsArray($errors[$key] ?? null);
        $this->assertNotEmpty($errors[$key] ?? []);
    }
}
