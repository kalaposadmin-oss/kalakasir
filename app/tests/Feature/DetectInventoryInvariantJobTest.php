<?php

namespace Tests\Feature;

use App\Jobs\DetectInventoryInvariantJob;
use App\Services\AnomalyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class DetectInventoryInvariantJobTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_drift_check_variant_aware_tidak_mencampur_dua_bucket_variant_produk_yang_sama(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+invariant-variant-buckets@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $variantA = $this->createVariant($tenant->id, $fixture['product_id'], 'VAR-A');
        $variantB = $this->createVariant($tenant->id, $fixture['product_id'], 'VAR-B');

        DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('location_id', $fixture['location_id'])
            ->whereNull('product_variant_id')
            ->update([
                'product_variant_id' => $variantA,
                'qty_on_hand' => 5,
            ]);

        $this->insertLedgerRow([
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => $variantA,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'opening_balance',
            'reference_id' => (string) Str::uuid(),
            'qty_in' => 5,
            'qty_out' => 0,
            'balance_after' => 5,
            'created_by' => $user->id,
        ]);

        $this->insertLedgerRow([
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => $variantB,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'opening_balance',
            'reference_id' => (string) Str::uuid(),
            'qty_in' => 3,
            'qty_out' => 0,
            'balance_after' => 3,
            'created_by' => $user->id,
        ]);

        $this->runInvariantJob();

        $payload = $this->anomalyPayload($tenant->id, 'fifo_ledger_balance_drift');

        $this->assertNotNull($payload);
        $this->assertSame(1, (int) ($payload['count'] ?? 0));
        $this->assertTrue(collect($payload['sample'] ?? [])->contains(function (array $row) use ($fixture, $variantB) {
            return ($row['product_id'] ?? null) === $fixture['product_id']
                && ($row['location_id'] ?? null) === $fixture['location_id']
                && ($row['product_variant_id'] ?? null) === $variantB
                && (float) ($row['qty_on_hand'] ?? -1) === 0.0
                && (float) ($row['ledger_qty'] ?? -1) === 3.0;
        }));
        $this->assertFalse(collect($payload['sample'] ?? [])->contains(function (array $row) use ($variantA) {
            return ($row['product_variant_id'] ?? null) === $variantA;
        }));
    }

    public function test_drift_check_memisahkan_bucket_null_dan_variant_dengan_join_null_safe(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+invariant-null-variant@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $variantId = $this->createVariant($tenant->id, $fixture['product_id'], 'VAR-NULL-SAFE');

        DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('location_id', $fixture['location_id'])
            ->whereNull('product_variant_id')
            ->update(['qty_on_hand' => 4]);

        $this->insertLedgerRow([
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'opening_balance',
            'reference_id' => (string) Str::uuid(),
            'qty_in' => 4,
            'qty_out' => 0,
            'balance_after' => 4,
            'created_by' => $user->id,
        ]);

        $this->insertLedgerRow([
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => $variantId,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'opening_balance',
            'reference_id' => (string) Str::uuid(),
            'qty_in' => 2,
            'qty_out' => 0,
            'balance_after' => 2,
            'created_by' => $user->id,
        ]);

        $this->runInvariantJob();

        $payload = $this->anomalyPayload($tenant->id, 'fifo_ledger_balance_drift');

        $this->assertNotNull($payload);
        $this->assertSame(1, (int) ($payload['count'] ?? 0));
        $this->assertTrue(collect($payload['sample'] ?? [])->contains(function (array $row) use ($fixture, $variantId) {
            return ($row['product_id'] ?? null) === $fixture['product_id']
                && ($row['location_id'] ?? null) === $fixture['location_id']
                && ($row['product_variant_id'] ?? null) === $variantId
                && (float) ($row['qty_on_hand'] ?? -1) === 0.0
                && (float) ($row['ledger_qty'] ?? -1) === 2.0;
        }));
        $this->assertFalse(collect($payload['sample'] ?? [])->contains(function (array $row) {
            return array_key_exists('product_variant_id', $row) && $row['product_variant_id'] === null;
        }));
    }

    public function test_drift_check_mendeteksi_ledger_bucket_tanpa_inventory_balance_row(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+invariant-ledger-only@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('location_id', $fixture['location_id'])
            ->delete();

        $this->insertLedgerRow([
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'opening_balance',
            'reference_id' => (string) Str::uuid(),
            'qty_in' => 9,
            'qty_out' => 0,
            'balance_after' => 9,
            'created_by' => $user->id,
        ]);

        $this->runInvariantJob();

        $payload = $this->anomalyPayload($tenant->id, 'fifo_ledger_balance_drift');

        $this->assertNotNull($payload);
        $this->assertTrue(collect($payload['sample'] ?? [])->contains(function (array $row) use ($fixture) {
            $variantBucket = $row['product_variant_id'] ?? null;

            return ($row['product_id'] ?? null) === $fixture['product_id']
                && ($row['location_id'] ?? null) === $fixture['location_id']
                && ($variantBucket === null || $variantBucket === '')
                && (float) ($row['qty_on_hand'] ?? -1) === 0.0
                && (float) ($row['ledger_qty'] ?? -1) === 9.0;
        }));
    }

    public function test_void_mismatch_stream_sale_menghasilkan_anomaly_type_yang_benar(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+invariant-void-sale@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $saleId = $this->createVoidedSale($tenant->id, $fixture);

        $this->insertLedgerRow([
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'qty_in' => 0,
            'qty_out' => 1,
            'balance_after' => 99,
            'created_by' => $user->id,
        ]);

        $this->runInvariantJob();

        $payload = $this->anomalyPayload($tenant->id, 'fifo_void_ledger_mismatch');

        $this->assertNotNull($payload);
        $this->assertSame('sale', $payload['source_reference_type'] ?? null);
        $this->assertSame('void', $payload['reversal_reference_type'] ?? null);
        $this->assertTrue(collect($payload['sample'] ?? [])->contains(function (array $row) use ($saleId) {
            return ($row['reference_id'] ?? null) === $saleId
                && (float) ($row['source_qty'] ?? -1) === 1.0
                && (float) ($row['reversal_qty'] ?? -1) === 0.0;
        }));
    }

    public function test_void_mismatch_stream_sale_recipe_input_menghasilkan_anomaly_type_yang_benar(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+invariant-void-recipe@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $rawMaterialId = $this->createProduct($tenant->id, $user->id, 'RAW-MISMATCH', 'Raw Mismatch');
        $saleId = $this->createVoidedSale($tenant->id, $fixture);

        $this->insertLedgerRow([
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $saleId,
            'qty_in' => 0,
            'qty_out' => 2,
            'balance_after' => 8,
            'created_by' => $user->id,
        ]);

        $this->runInvariantJob();

        $payload = $this->anomalyPayload($tenant->id, 'fifo_void_recipe_input_ledger_mismatch');

        $this->assertNotNull($payload);
        $this->assertSame('sale_recipe_input', $payload['source_reference_type'] ?? null);
        $this->assertSame('void_recipe_input', $payload['reversal_reference_type'] ?? null);
        $this->assertTrue(collect($payload['sample'] ?? [])->contains(function (array $row) use ($saleId) {
            return ($row['reference_id'] ?? null) === $saleId
                && (float) ($row['source_qty'] ?? -1) === 2.0
                && (float) ($row['reversal_qty'] ?? -1) === 0.0;
        }));
        $this->assertSame(0, DB::table('anomalies')
            ->where('tenant_id', $tenant->id)
            ->where('type', 'fifo_void_ledger_mismatch')
            ->count());
    }

    public function test_pasangan_void_lengkap_tidak_memicu_false_positive_void_mismatch(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+invariant-complete-pairs@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $rawMaterialId = $this->createProduct($tenant->id, $user->id, 'RAW-COMPLETE', 'Raw Complete');
        $saleId = $this->createVoidedSale($tenant->id, $fixture);

        DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('location_id', $fixture['location_id'])
            ->whereNull('product_variant_id')
            ->update(['qty_on_hand' => 100]);

        DB::table('inventory_balances')->insert([
            'tenant_id' => $tenant->id,
            'location_id' => $fixture['location_id'],
            'product_id' => $rawMaterialId,
            'product_variant_id' => null,
            'qty_on_hand' => 10,
        ]);

        $this->insertLedgerRow([
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'opening_balance',
            'reference_id' => (string) Str::uuid(),
            'qty_in' => 100,
            'qty_out' => 0,
            'balance_after' => 100,
            'created_by' => $user->id,
        ]);

        $this->insertLedgerRow([
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'opening_balance',
            'reference_id' => (string) Str::uuid(),
            'qty_in' => 10,
            'qty_out' => 0,
            'balance_after' => 10,
            'created_by' => $user->id,
        ]);

        $this->insertLedgerRow([
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'qty_in' => 0,
            'qty_out' => 1,
            'balance_after' => 99,
            'created_by' => $user->id,
        ]);

        $this->insertLedgerRow([
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'void',
            'reference_id' => $saleId,
            'qty_in' => 1,
            'qty_out' => 0,
            'balance_after' => 100,
            'created_by' => $user->id,
        ]);

        $this->insertLedgerRow([
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'sale_recipe_input',
            'reference_id' => $saleId,
            'qty_in' => 0,
            'qty_out' => 2,
            'balance_after' => 8,
            'created_by' => $user->id,
        ]);

        $this->insertLedgerRow([
            'tenant_id' => $tenant->id,
            'product_id' => $rawMaterialId,
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'void_recipe_input',
            'reference_id' => $saleId,
            'qty_in' => 2,
            'qty_out' => 0,
            'balance_after' => 10,
            'created_by' => $user->id,
        ]);

        $this->runInvariantJob();

        $this->assertSame(0, DB::table('anomalies')
            ->where('tenant_id', $tenant->id)
            ->whereIn('type', ['fifo_void_ledger_mismatch', 'fifo_void_recipe_input_ledger_mismatch', 'fifo_ledger_balance_drift'])
            ->count());
    }

    private function runInvariantJob(): void
    {
        $job = new DetectInventoryInvariantJob();
        $job->handle(app(AnomalyService::class));
    }

    private function createVariant(string $tenantId, string $productId, string $suffix): string
    {
        $variantId = (string) Str::uuid();

        DB::table('product_variants')->insert([
            'id' => $variantId,
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'sku' => 'SKU-'.$suffix,
            'barcode' => null,
            'name' => $suffix,
            'attributes' => json_encode(['code' => $suffix]),
            'price_override' => null,
            'is_active' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return $variantId;
    }

    private function createProduct(string $tenantId, string $userId, string $sku, string $name): string
    {
        $productId = (string) Str::uuid();

        DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $tenantId,
            'sku' => $sku,
            'name' => $name,
            'price' => 1000,
            'hpp_average' => 1000,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'product_mode' => 'add_on',
            'item_type' => 'raw_material',
            'variants' => json_encode([]),
            'bom_items' => json_encode([]),
            'bundle_items' => json_encode([]),
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        return $productId;
    }

    private function createVoidedSale(string $tenantId, array $fixture): string
    {
        $saleId = (string) Str::uuid();

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenantId,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'customer_id' => null,
            'subtotal' => 10000,
            'discount' => 0,
            'tax' => 0,
            'total' => 10000,
            'status' => 'voided',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => null,
            'voided_by' => null,
            'created_at' => now('UTC')->subMinute(),
            'transaction_date' => now('UTC')->toDateString(),
        ]);

        return $saleId;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function insertLedgerRow(array $attributes): void
    {
        DB::table('stock_ledger')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $attributes['tenant_id'],
            'product_id' => $attributes['product_id'],
            'product_variant_id' => $attributes['product_variant_id'] ?? null,
            'location_id' => $attributes['location_id'],
            'reference_type' => $attributes['reference_type'],
            'reference_id' => $attributes['reference_id'],
            'reference_item_id' => $attributes['reference_item_id'] ?? null,
            'qty_in' => $attributes['qty_in'] ?? 0,
            'qty_out' => $attributes['qty_out'] ?? 0,
            'balance_after' => $attributes['balance_after'] ?? 0,
            'created_at' => $attributes['created_at'] ?? now('UTC'),
            'created_by' => $attributes['created_by'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function anomalyPayload(string $tenantId, string $type): ?array
    {
        $row = DB::table('anomalies')
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->orderByDesc('detected_at')
            ->first();

        if (! $row) {
            return null;
        }

        return json_decode((string) $row->payload_json, true);
    }
}