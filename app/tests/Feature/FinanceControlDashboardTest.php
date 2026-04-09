<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class FinanceControlDashboardTest extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-finance-dashboard@example.com', 'Admin Finance');
        $this->seedDailyData();
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_finance_control_daily_endpoint_returns_kpi_payload(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/finance-control-daily?date=2026-02-27');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'kpi_today' => [
                    'snapshot_date',
                    'sales_total',
                    'cogs_total',
                    'cash_closing_total',
                    'debt_due_7d',
                    'cash_difference_total',
                ],
                'kpi_week',
                'snapshots',
            ]);

            $this->assertSame('1', $response->headers->get('X-Finance-Read-Only'));
            $this->assertStringContainsString('finance-control;dur=', (string) $response->headers->get('Server-Timing'));

            $this->assertDatabaseCount('daily_finance_control_snapshots', 0);
    }

    public function test_snapshot_endpoint_persists_daily_snapshot(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/finance-control-daily/snapshot', [
                'date' => '2026-02-27',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'snapshotted',
            ]);

        $this->assertDatabaseHas('daily_finance_control_snapshots', [
            'tenant_id' => $this->tenant->id,
            'snapshot_date' => '2026-02-27',
        ]);
    }

    public function test_export_endpoint_returns_csv_file(): void
    {
        $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/finance-control-daily/snapshot', [
                'date' => '2026-02-27',
            ])
            ->assertStatus(200);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->get('/api/v1/finance-control-daily/export?from=2026-02-27&to=2026-02-27');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('snapshot_date,sales_total,cogs_total', $response->getContent());
        $this->assertSame('1', $response->headers->get('X-Finance-Read-Only'));
    }

    public function test_export_endpoint_stays_read_only_when_snapshot_not_persisted(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->get('/api/v1/finance-control-daily/export?from=2026-02-27&to=2026-02-27');

        $response->assertOk();
        $this->assertStringContainsString('2026-02-27', $response->getContent());
        $this->assertDatabaseCount('daily_finance_control_snapshots', 0);
    }

    public function test_finance_control_uses_transaction_date_for_backdated_sales_snapshot(): void
    {
        $saleId = (string) Str::uuid();
        $productId = (string) Str::uuid();
        $branchId = (string) Str::uuid();
        $locationId = (string) Str::uuid();
        $shiftId = (string) Str::uuid();

        \DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $this->tenant->id,
            'name' => 'Backdated Branch',
        ]);

        \DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'name' => 'Backdated Warehouse',
            'is_default' => false,
        ]);

        \DB::table('shifts')->insert([
            'id' => $shiftId,
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'user_id' => $this->user->id,
            'opened_at' => CarbonImmutable::parse('2026-02-28 00:00:00', 'UTC'),
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
            'sku' => 'SKU-FIN-BACKDATE',
            'name' => 'Produk Backdated',
            'price' => 125000,
            'hpp_average' => 70000,
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

        \DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'shift_id' => $shiftId,
            'customer_id' => null,
            'subtotal' => 125000,
            'discount' => 0,
            'tax' => 0,
            'total' => 125000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $this->user->id,
            'voided_by' => null,
            'created_at' => CarbonImmutable::parse('2026-02-28 01:00:00', 'UTC'),
            'transaction_date' => '2026-02-27',
        ]);

        \DB::table('sale_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $saleId,
            'product_id' => $productId,
            'qty' => 1,
            'price' => 125000,
            'discount' => 0,
            'hpp_snapshot' => 70000,
        ]);

        $businessDay = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/finance-control-daily?date=2026-02-27');

        $businessDay->assertOk()
            ->assertJsonPath('kpi_today.sales_total', 325000)
            ->assertJsonPath('kpi_today.cogs_total', 190000)
            ->assertJsonPath('kpi_today.transaction_count', 2);

        $createdDay = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/finance-control-daily?date=2026-02-28');

        $createdDay->assertOk()
            ->assertJsonPath('kpi_today.sales_total', 0)
            ->assertJsonPath('kpi_today.cogs_total', 0)
            ->assertJsonPath('kpi_today.transaction_count', 0);
    }

    private function seedDailyData(): void
    {
        $branchId = (string) Str::uuid();
        $shiftId = (string) Str::uuid();
        $supplierId = (string) Str::uuid();
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

        \DB::table('suppliers')->insert([
            'id' => $supplierId,
            'tenant_id' => $this->tenant->id,
            'name' => 'Supplier Tempo',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        \DB::table('purchases')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $supplierId,
            'location_id' => $locationId,
            'total' => 500000,
            'status' => 'received',
            'payment_method' => 'tempo',
            'due_date' => '2026-03-03',
            'notes' => null,
            'created_at' => CarbonImmutable::parse('2026-02-27 08:00:00', 'UTC'),
            'approved_at' => CarbonImmutable::parse('2026-02-27 08:00:00', 'UTC'),
            'approved_by' => $this->user->id,
            'received_at' => CarbonImmutable::parse('2026-02-27 09:00:00', 'UTC'),
            'received_by' => $this->user->id,
            'closed_at' => null,
            'closed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'transaction_date' => '2026-02-27',
        ]);

        \DB::table('shifts')->insert([
            'id' => $shiftId,
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'user_id' => $this->user->id,
            'opened_at' => CarbonImmutable::parse('2026-02-27 08:00:00', 'UTC'),
            'closed_at' => CarbonImmutable::parse('2026-02-27 21:00:00', 'UTC'),
            'opening_cash' => 100000,
            'closing_cash' => 350000,
            'system_cash_total' => 340000,
            'difference' => 10000,
            'close_note' => 'Selisih kas kecil terkonfirmasi.',
            'status' => 'closed',
        ]);

        \DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $this->tenant->id,
            'sku' => 'SKU-FIN-001',
            'name' => 'Produk Finance',
            'price' => 200000,
            'hpp_average' => 120000,
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

        \DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'shift_id' => $shiftId,
            'customer_id' => null,
            'subtotal' => 200000,
            'discount' => 0,
            'tax' => 0,
            'total' => 200000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $this->user->id,
            'voided_by' => null,
            'created_at' => CarbonImmutable::parse('2026-02-27 12:00:00', 'UTC'),
            'transaction_date' => '2026-02-27',
        ]);

        \DB::table('sale_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'sale_id' => $saleId,
            'product_id' => $productId,
            'qty' => 1,
            'price' => 200000,
            'discount' => 0,
            'hpp_snapshot' => 120000,
        ]);
    }
}
