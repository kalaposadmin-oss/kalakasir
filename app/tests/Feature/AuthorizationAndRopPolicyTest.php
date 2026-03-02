<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class AuthorizationAndRopPolicyTest extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_cashier_void_above_limit_requires_manager_pin(): void
    {
        [$this->tenant, $this->user] = $this->bootApiActor('cashier', 'cashier-policy@example.com', 'Kasir Policy');
        $saleId = $this->seedSale(total: 300000, status: 'completed');

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/sales/'.$saleId.'/void', [
                'reason_code' => 'V01',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'MANAGER_PIN_REQUIRED',
            ]);
    }

    public function test_cashier_void_within_low_limit_is_allowed_without_manager_pin(): void
    {
        [$this->tenant, $this->user] = $this->bootApiActor('cashier', 'cashier-low-void@example.com', 'Kasir Low Void');
        $saleId = $this->seedSale(total: 150000, status: 'completed');

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/sales/'.$saleId.'/void', [
                'reason_code' => 'V01',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'voided',
            ]);
    }

    public function test_admin_adjustment_post_above_limit_is_blocked_by_authorization_policy(): void
    {
        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-adjustment-policy@example.com', 'Admin Policy');
        $adjustmentId = $this->seedApprovedAdjustment(
            differenceQty: -100,
            unitCostSnapshot: 10000,
            referenceNo: 'ADJ-POLICY-1'
        );

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/stock-adjustments/'.$adjustmentId.'/post', [
                'transaction_date' => '2026-02-27',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'code' => 'AUTHORIZATION_LIMIT_EXCEEDED',
            ]);
    }

    public function test_admin_adjustment_post_within_limit_is_allowed(): void
    {
        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-adjustment-low@example.com', 'Admin Low Adjustment');

        $adjustmentId = $this->seedApprovedAdjustment(
            differenceQty: -10,
            unitCostSnapshot: 10000,
            referenceNo: 'ADJ-POLICY-LOW-1'
        );

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/stock-adjustments/'.$adjustmentId.'/post', [
                'transaction_date' => '2026-02-27',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'posted',
            ]);
    }

    public function test_inventory_alerts_use_rop_even_when_min_stock_is_zero(): void
    {
        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-rop@example.com', 'Admin ROP');

        $branchId = (string) Str::uuid();
        $shiftId = (string) Str::uuid();
        $locationId = (string) Str::uuid();
        $productId = (string) Str::uuid();
        $saleId = (string) Str::uuid();

        \DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Branch',
        ]);

        \DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'name' => 'Main Warehouse',
            'is_default' => true,
        ]);

        \DB::table('shifts')->insert([
            'id' => $shiftId,
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'user_id' => $this->user->id,
            'opened_at' => CarbonImmutable::now('UTC')->subHours(6),
            'closed_at' => null,
            'opening_cash' => 0,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        \DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $this->tenant->id,
            'sku' => 'SKU-ROP-001',
            'name' => 'Produk ROP',
            'price' => 10000,
            'hpp_average' => 5000,
            'min_stock' => 0,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        \DB::table('inventory_balances')->insert([
            'tenant_id' => $this->tenant->id,
            'location_id' => $locationId,
            'product_id' => $productId,
            'qty_on_hand' => 5,
        ]);

        \DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'shift_id' => $shiftId,
            'customer_id' => null,
            'subtotal' => 300000,
            'discount' => 0,
            'tax' => 0,
            'total' => 300000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $this->user->id,
            'voided_by' => null,
            'created_at' => CarbonImmutable::now('UTC')->subDays(2),
            'transaction_date' => CarbonImmutable::now('UTC')->subDays(2)->toDateString(),
        ]);

        \DB::table('sale_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $saleId,
            'product_id' => $productId,
            'qty' => 300,
            'price' => 1000,
            'discount' => 0,
            'hpp_snapshot' => 500,
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/inventory-alerts');

        $response->assertStatus(200);

        $alerts = collect($response->json('alerts', []));
        $alert = $alerts->firstWhere('id', $productId);

        $this->assertNotNull($alert);
        $this->assertGreaterThan(0, (float) ($alert['rop'] ?? 0));
        $this->assertGreaterThan(0, (float) ($alert['avg_daily_usage'] ?? 0));
    }

    private function seedSale(float $total, string $status): string
    {
        $branchId = (string) Str::uuid();
        $shiftId = (string) Str::uuid();
        $saleId = (string) Str::uuid();

        \DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Branch',
        ]);

        \DB::table('shifts')->insert([
            'id' => $shiftId,
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'user_id' => $this->user->id,
            'opened_at' => CarbonImmutable::now('UTC')->subHours(4),
            'closed_at' => null,
            'opening_cash' => 0,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        \DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'shift_id' => $shiftId,
            'customer_id' => null,
            'subtotal' => $total,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'status' => $status,
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $this->user->id,
            'voided_by' => null,
            'created_at' => now('UTC'),
            'transaction_date' => now('UTC')->toDateString(),
        ]);

        return $saleId;
    }

    private function seedApprovedAdjustment(float $differenceQty, float $unitCostSnapshot, string $referenceNo): string
    {
        $branchId = (string) Str::uuid();
        $locationId = (string) Str::uuid();
        $productId = (string) Str::uuid();
        $adjustmentId = (string) Str::uuid();

        \DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Branch',
        ]);

        \DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'name' => 'Main Warehouse',
            'is_default' => true,
        ]);

        \DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $this->tenant->id,
            'sku' => 'SKU-POLICY-'.substr((string) Str::uuid(), 0, 6),
            'name' => 'Produk Policy',
            'price' => 10000,
            'hpp_average' => $unitCostSnapshot,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        \DB::table('inventory_balances')->insert([
            'tenant_id' => $this->tenant->id,
            'location_id' => $locationId,
            'product_id' => $productId,
            'qty_on_hand' => 200,
        ]);

        \DB::table('stock_adjustments')->insert([
            'id' => $adjustmentId,
            'tenant_id' => $this->tenant->id,
            'product_id' => $productId,
            'location_id' => $locationId,
            'type' => 'opname',
            'qty' => $differenceQty,
            'reason' => 'A01',
            'note' => null,
            'balance_before' => 200,
            'balance_after' => 200 + $differenceQty,
            'reference_no' => $referenceNo,
            'reason_code' => 'A01',
            'status' => 'approved',
            'notes' => null,
            'approved_at' => now('UTC'),
            'approved_by' => $this->user->id,
            'posted_at' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'created_by' => $this->user->id,
            'created_at' => now('UTC'),
            'transaction_date' => '2026-02-27',
        ]);

        \DB::table('stock_adjustment_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'stock_adjustment_id' => $adjustmentId,
            'product_id' => $productId,
            'system_qty' => 200,
            'physical_qty' => 200 + $differenceQty,
            'difference_qty' => $differenceQty,
            'unit_cost_snapshot' => $unitCostSnapshot,
        ]);

        return $adjustmentId;
    }
}
