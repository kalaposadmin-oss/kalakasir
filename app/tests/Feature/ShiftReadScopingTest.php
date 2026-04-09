<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class ShiftReadScopingTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_opening_data_scopes_branches_and_open_shift_count_to_active_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+shift-opening-scope@example.test');
        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Tenant Opening Lain');

        $tenantBranch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Tenant Branch',
        ]);

        $foreignBranch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Branch',
        ]);

        Shift::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'branch_id' => $tenantBranch->id,
            'user_id' => $user->id,
            'opened_at' => now('UTC')->subHours(2),
            'closed_at' => null,
            'opening_cash' => 10000,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        $foreignBranchId = $foreignBranch->id;

        Shift::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'branch_id' => $foreignBranchId,
            'user_id' => $otherUser->id,
            'opened_at' => now('UTC')->subHour(),
            'closed_at' => null,
            'opening_cash' => 20000,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/shifts/opening');

        $response->assertOk()
            ->assertJsonPath('shift_number', 2)
            ->assertJsonCount(1, 'branches')
            ->assertJsonPath('branches.0.id', $tenantBranch->id)
            ->assertJsonMissing(['id' => $foreignBranchId]);
    }

    public function test_summary_prefers_active_tenant_open_shift_and_cash_total(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+shift-summary-scope@example.test');
        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Tenant Summary Lain');

        $tenantBranch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Tenant Branch',
        ]);

        $otherBranch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Branch',
        ]);

        $tenantShift = Shift::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'branch_id' => $tenantBranch->id,
            'user_id' => $user->id,
            'opened_at' => now('UTC')->subHours(3),
            'closed_at' => null,
            'opening_cash' => 15000,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        $foreignShift = Shift::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'branch_id' => $otherBranch->id,
            'user_id' => $otherUser->id,
            'opened_at' => now('UTC')->subMinutes(30),
            'closed_at' => null,
            'opening_cash' => 50000,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        $tenantSaleId = $this->insertCompletedSale($tenant->id, $tenantShift->id, $tenantBranch->id, $user->id, 12000);
        $this->insertPayment($tenant->id, $tenantSaleId, 12000);

        $foreignSaleId = $this->insertCompletedSale($otherTenant->id, $foreignShift->id, $otherBranch->id, $otherUser->id, 90000);
        $this->insertPayment($otherTenant->id, $foreignSaleId, 90000);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/shifts/summary');

        $response->assertOk()
            ->assertJsonPath('shift.id', $tenantShift->id)
            ->assertJsonPath('cashier.id', $user->id)
            ->assertJsonPath('expected_cash', 12000);
    }

    public function test_close_summary_and_closed_history_exclude_foreign_tenant_shift_data(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+shift-close-summary-scope@example.test');
        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Tenant Closed History Lain');

        $tenantBranch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Tenant Branch',
        ]);

        $otherBranch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Branch',
        ]);

        $tenantClosedShift = Shift::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'branch_id' => $tenantBranch->id,
            'user_id' => $user->id,
            'opened_at' => now('UTC')->subHours(8),
            'closed_at' => now('UTC')->subHours(2),
            'opening_cash' => 10000,
            'closing_cash' => 15000,
            'system_cash_total' => 15000,
            'difference' => 0,
            'close_note' => 'Tenant close note',
            'status' => 'closed',
        ]);

        $foreignClosedShift = Shift::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'branch_id' => $otherBranch->id,
            'user_id' => $otherUser->id,
            'opened_at' => now('UTC')->subHours(5),
            'closed_at' => now('UTC')->subMinutes(15),
            'opening_cash' => 50000,
            'closing_cash' => 70000,
            'system_cash_total' => 70000,
            'difference' => 0,
            'close_note' => 'Foreign close note',
            'status' => 'closed',
        ]);

        $tenantSaleId = $this->insertCompletedSale($tenant->id, $tenantClosedShift->id, $tenantBranch->id, $user->id, 15000);
        $this->insertPayment($tenant->id, $tenantSaleId, 15000);

        $foreignSaleId = $this->insertCompletedSale($otherTenant->id, $foreignClosedShift->id, $otherBranch->id, $otherUser->id, 70000);
        $this->insertPayment($otherTenant->id, $foreignSaleId, 70000);

        $summary = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/shifts/close-summary');

        $summary->assertOk()
            ->assertJsonPath('shift.id', $tenantClosedShift->id)
            ->assertJsonPath('branch.id', $tenantBranch->id)
            ->assertJsonPath('cashier.id', $user->id)
            ->assertJsonPath('sales.total', 15000)
            ->assertJsonPath('sales.count', 1);

        $history = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/shifts/closed');

        $history->assertOk()
            ->assertJsonCount(1, 'shifts')
            ->assertJsonPath('shifts.0.id', $tenantClosedShift->id)
            ->assertJsonMissing(['id' => $foreignClosedShift->id]);
    }

    private function createOtherTenantActor(string $tenantName): array
    {
        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $tenantName,
            'timezone' => 'UTC',
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Kasir Tenant Lain',
            'email' => 'other-'.Str::lower(Str::random(10)).'@example.test',
            'password' => 'secret',
        ]);

        return [$tenant, $user];
    }

    private function insertCompletedSale(string $tenantId, string $shiftId, string $branchId, string $userId, int $total): string
    {
        $saleId = (string) Str::uuid();

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'shift_id' => $shiftId,
            'customer_id' => null,
            'subtotal' => $total,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $userId,
            'voided_by' => null,
            'void_approved_by' => null,
            'void_reason' => null,
            'voided_at' => null,
            'created_at' => CarbonImmutable::now('UTC')->subHour(),
        ]);

        return $saleId;
    }

    private function insertPayment(string $tenantId, string $saleId, int $amount): void
    {
        DB::table('payments')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'sale_id' => $saleId,
            'account_id' => (string) Str::uuid(),
            'method' => 'cash',
            'amount' => $amount,
            'payment_method_id' => null,
        ]);
    }
}