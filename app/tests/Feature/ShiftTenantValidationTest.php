<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
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