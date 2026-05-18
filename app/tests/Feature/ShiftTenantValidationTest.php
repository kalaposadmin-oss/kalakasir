<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class ShiftTenantValidationTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_shift_open_rejects_branch_from_other_tenant(): void
    {
        [$tenant] = $this->bootApiActor('cashier', 'cashier+shift-branch-scope@example.test');
        [$otherTenant] = $this->createOtherTenantActor('Tenant Shift Branch Lain');

        $otherBranch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Branch',
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/shifts/open', [
                'branch_id' => $otherBranch->id,
                'opening_cash' => 10000,
            ]);

        $this->assertValidationErrorKey($response, 'branch_id');
    }

    public function test_shift_close_rejects_shift_from_other_tenant(): void
    {
        [$tenant] = $this->bootApiActor('cashier', 'cashier+shift-close-scope@example.test');
        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Tenant Shift Close Lain');

        $otherBranch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Branch',
        ]);

        $otherShift = Shift::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $otherTenant->id,
            'branch_id' => $otherBranch->id,
            'user_id' => $otherUser->id,
            'opened_at' => now('UTC')->subHour(),
            'closed_at' => null,
            'opening_cash' => 50000,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/shifts/close', [
                'shift_id' => $otherShift->id,
                'closing_cash' => 50000,
                'close_note' => 'Cross tenant close attempt',
            ]);

        $this->assertValidationErrorKey($response, 'shift_id');
    }

    public function test_shift_open_requires_reconciliation_for_expired_shift_before_new_open(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+shift-reconcile-required@example.test');
        $tenant->update(['timezone' => 'Asia/Jakarta']);

        $branch = Branch::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
        ]);

        $expiredShift = Shift::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'opened_at' => CarbonImmutable::parse('2026-02-26 10:00:00', 'UTC'),
            'closed_at' => null,
            'opening_cash' => 50000,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        $openResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/shifts/open', [
                'branch_id' => $branch->id,
                'opening_cash' => 25000,
            ]);

        $openResponse->assertStatus(422)
            ->assertJsonPath('code', 'SHIFT_RECONCILIATION_REQUIRED')
            ->assertJsonPath('pending_shift_id', $expiredShift->id);

        $expiredShift->refresh();
        $this->assertSame('closed', $expiredShift->status);
        $this->assertTrue((bool) $expiredShift->requires_reconciliation);

        $reconcileResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/shifts/reconcile-overnight', [
                'shift_id' => $expiredShift->id,
                'closing_cash' => 52000,
                'close_note' => 'Kas sudah dihitung ulang pagi ini.',
            ]);

        $reconcileResponse->assertOk()
            ->assertJsonPath('status', 'reconciled')
            ->assertJsonPath('shift.requires_reconciliation', false)
            ->assertJsonPath('shift.closing_cash', 52000);

        $expiredShift->refresh();
        $this->assertFalse((bool) $expiredShift->requires_reconciliation);
        $this->assertSame(52000.0, (float) $expiredShift->closing_cash);

        $secondOpenResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/shifts/open', [
                'branch_id' => $branch->id,
                'opening_cash' => 25000,
            ]);

        $secondOpenResponse->assertCreated()
            ->assertJsonPath('shift.status', 'open');
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
