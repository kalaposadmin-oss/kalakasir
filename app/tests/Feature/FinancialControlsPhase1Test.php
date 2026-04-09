<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class FinancialControlsPhase1Test extends TestCase
{
    use BootsApiTenantUser, RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $this->user] = $this->bootApiActor('admin', 'admin-finance-controls@example.com', 'Admin Controls');
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_purchase_tunai_returns_insufficient_cash_balance_when_total_exceeds_available_cash(): void
    {
        $branchId = (string) Str::uuid();
        $locationId = (string) Str::uuid();
        $supplierId = (string) Str::uuid();
        $productId = (string) Str::uuid();

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
            'name' => 'Supplier A',
            'balance' => 0,
            'created_at' => now('UTC'),
        ]);

        \DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $this->tenant->id,
            'sku' => 'PUR-CASH-001',
            'name' => 'Produk Purchase Cash',
            'price' => 15000,
            'hpp_average' => 5000,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        \DB::table('shifts')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'user_id' => $this->user->id,
            'opened_at' => CarbonImmutable::now('UTC')->subHour(),
            'closed_at' => null,
            'opening_cash' => 10000,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'payment_method' => 'tunai',
                'items' => [
                    [
                        'product_id' => $productId,
                        'qty' => 1,
                        'price' => 15000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'INSUFFICIENT_CASH_BALANCE',
            ]);

        $this->assertEquals(10000, (float) $response->json('details.available_cash'));
        $this->assertEquals(15000, (float) $response->json('details.required_amount'));
    }

    public function test_void_endpoint_requires_minimum_note_length_for_v99_reason(): void
    {
        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/sales/'.(string) Str::uuid().'/void', [
                'reason_code' => 'V99',
                'reason_note' => 'terlalu pendek',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'REASON_NOTE_REQUIRED',
            ]);
    }

    public function test_shift_close_summary_includes_expected_findings_for_difference_and_missing_note(): void
    {
        $branchId = (string) Str::uuid();
        $shiftId = (string) Str::uuid();

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
            'opened_at' => CarbonImmutable::now('UTC')->subHours(8),
            'closed_at' => CarbonImmutable::now('UTC')->subHour(),
            'opening_cash' => 100000,
            'closing_cash' => 90000,
            'system_cash_total' => 100000,
            'difference' => -10000,
            'close_note' => null,
            'status' => 'closed',
        ]);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->getJson('/api/v1/shifts/close-summary?shift_id='.$shiftId);

        $response->assertStatus(200);

        $codes = collect($response->json('findings', []))
            ->pluck('code')
            ->all();

        $this->assertContains('NO_SALES', $codes);
        $this->assertContains('CASH_DIFFERENCE', $codes);
        $this->assertContains('DIFFERENCE_NOTE_MISSING', $codes);
    }
}
