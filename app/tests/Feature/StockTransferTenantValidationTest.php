<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class StockTransferTenantValidationTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_store_transfer_rejects_from_branch_from_other_tenant(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+transfer-from-branch-scope@example.test');
        $tenant->forceFill(['plan' => 'M'])->save();
        $fixture = $this->seedTransferFixture($tenant->id);
        $otherTenant = $this->createOtherTenant('Tenant Cabang Asal Lain');
        $otherFixture = $this->seedTransferFixture($otherTenant->id);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/stock-transfers', [
                'from_branch_id' => $otherFixture['from_branch_id'],
                'to_branch_id' => $fixture['to_branch_id'],
                'from_location_id' => $fixture['from_location_id'],
                'to_location_id' => $fixture['to_location_id'],
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                    ],
                ],
            ]);

        $this->assertValidationErrorKey($response, 'from_branch_id');
    }

    public function test_store_transfer_rejects_to_branch_from_other_tenant(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+transfer-to-branch-scope@example.test');
        $tenant->forceFill(['plan' => 'M'])->save();
        $fixture = $this->seedTransferFixture($tenant->id);
        $otherTenant = $this->createOtherTenant('Tenant Cabang Tujuan Lain');
        $otherFixture = $this->seedTransferFixture($otherTenant->id);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/stock-transfers', [
                'from_branch_id' => $fixture['from_branch_id'],
                'to_branch_id' => $otherFixture['to_branch_id'],
                'from_location_id' => $fixture['from_location_id'],
                'to_location_id' => $fixture['to_location_id'],
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                    ],
                ],
            ]);

        $this->assertValidationErrorKey($response, 'to_branch_id');
    }

    public function test_store_transfer_rejects_from_location_from_other_tenant(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+transfer-from-location-scope@example.test');
        $tenant->forceFill(['plan' => 'M'])->save();
        $fixture = $this->seedTransferFixture($tenant->id);
        $otherTenant = $this->createOtherTenant('Tenant Lokasi Asal Lain');
        $otherFixture = $this->seedTransferFixture($otherTenant->id);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/stock-transfers', [
                'from_branch_id' => $fixture['from_branch_id'],
                'to_branch_id' => $fixture['to_branch_id'],
                'from_location_id' => $otherFixture['from_location_id'],
                'to_location_id' => $fixture['to_location_id'],
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                    ],
                ],
            ]);

        $this->assertValidationErrorKey($response, 'from_location_id');
    }

    public function test_store_transfer_rejects_to_location_from_other_tenant(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+transfer-to-location-scope@example.test');
        $tenant->forceFill(['plan' => 'M'])->save();
        $fixture = $this->seedTransferFixture($tenant->id);
        $otherTenant = $this->createOtherTenant('Tenant Lokasi Tujuan Lain');
        $otherFixture = $this->seedTransferFixture($otherTenant->id);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/stock-transfers', [
                'from_branch_id' => $fixture['from_branch_id'],
                'to_branch_id' => $fixture['to_branch_id'],
                'from_location_id' => $fixture['from_location_id'],
                'to_location_id' => $otherFixture['to_location_id'],
                'items' => [
                    [
                        'product_id' => $fixture['product_id'],
                        'qty' => 1,
                    ],
                ],
            ]);

        $this->assertValidationErrorKey($response, 'to_location_id');
    }

    public function test_store_transfer_rejects_product_from_other_tenant(): void
    {
        [$tenant] = $this->bootApiActor('admin', 'admin+transfer-product-scope@example.test');
        $tenant->forceFill(['plan' => 'M'])->save();
        $fixture = $this->seedTransferFixture($tenant->id);
        $otherTenant = $this->createOtherTenant('Tenant Produk Transfer Lain');
        $otherFixture = $this->seedTransferFixture($otherTenant->id);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/stock-transfers', [
                'from_branch_id' => $fixture['from_branch_id'],
                'to_branch_id' => $fixture['to_branch_id'],
                'from_location_id' => $fixture['from_location_id'],
                'to_location_id' => $fixture['to_location_id'],
                'items' => [
                    [
                        'product_id' => $otherFixture['product_id'],
                        'qty' => 1,
                    ],
                ],
            ]);

        $this->assertValidationErrorKey($response, 'items.0.product_id');
    }

    private function createOtherTenant(string $name): Tenant
    {
        return Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'timezone' => 'UTC',
        ]);
    }

    private function seedTransferFixture(string $tenantId): array
    {
        $fromBranchId = (string) Str::uuid();
        $toBranchId = (string) Str::uuid();
        $fromLocationId = (string) Str::uuid();
        $toLocationId = (string) Str::uuid();
        $productId = (string) Str::uuid();
        $now = now('UTC');

        DB::table('branches')->insert([
            [
                'id' => $fromBranchId,
                'tenant_id' => $tenantId,
                'name' => 'Branch From '.substr($fromBranchId, 0, 6),
            ],
            [
                'id' => $toBranchId,
                'tenant_id' => $tenantId,
                'name' => 'Branch To '.substr($toBranchId, 0, 6),
            ],
        ]);

        DB::table('locations')->insert([
            [
                'id' => $fromLocationId,
                'tenant_id' => $tenantId,
                'branch_id' => $fromBranchId,
                'name' => 'Warehouse From',
                'is_default' => true,
            ],
            [
                'id' => $toLocationId,
                'tenant_id' => $tenantId,
                'branch_id' => $toBranchId,
                'name' => 'Warehouse To',
                'is_default' => true,
            ],
        ]);

        DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $tenantId,
            'sku' => 'TRF-'.Str::upper(substr(str_replace('-', '', $productId), 0, 6)),
            'name' => 'Produk Transfer '.substr($productId, 0, 6),
            'price' => 10000,
            'hpp_average' => 5000,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        return [
            'from_branch_id' => $fromBranchId,
            'to_branch_id' => $toBranchId,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'product_id' => $productId,
        ];
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