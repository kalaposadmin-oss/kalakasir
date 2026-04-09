<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class VoidManagerPinEnforcementTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    private string $tenantId;
    private string $userId;
    private string $approverId;
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier-void-pin@example.com', 'Cashier Void Pin');
        $this->tenantId = $tenant->id;
        $this->userId = $user->id;
        $this->fixture = $this->seedCheckoutFixture($this->tenantId, $this->userId);

        $this->approverId = (string) Str::uuid();
        \DB::table('users')->insert([
            'id' => $this->approverId,
            'tenant_id' => $this->tenantId,
            'name' => 'Admin Approver',
            'email' => 'admin-approver-void-pin@example.com',
            'password' => 'secret',
            'manager_pin_hash' => Hash::make('123456'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $roleId = (string) Str::uuid();
        \DB::table('roles')->insert([
            'id' => $roleId,
            'tenant_id' => $this->tenantId,
            'name' => 'admin',
        ]);

        \DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenantId,
            'user_id' => $this->approverId,
            'role_id' => $roleId,
        ]);
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_void_rejects_when_manager_pin_is_missing(): void
    {
        $saleId = $this->createSale();

        $response = $this->withHeaders($this->tenantHeaders($this->tenantId))
            ->postJson('/api/v1/sales/'.$saleId.'/void', [
                'reason' => 'void test',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'MANAGER_PIN_REQUIRED',
            ]);
    }

    public function test_void_rejects_when_manager_pin_is_invalid(): void
    {
        $saleId = $this->createSale();

        $response = $this->withHeaders($this->tenantHeaders($this->tenantId))
            ->postJson('/api/v1/sales/'.$saleId.'/void', [
                'reason' => 'void test',
                'manager_pin' => '000000',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'MANAGER_PIN_INVALID',
            ]);
    }

    public function test_void_locks_after_too_many_invalid_pin_attempts(): void
    {
        config([
            'services.security.manager_pin.max_attempts' => 2,
            'services.security.manager_pin.lockout_seconds' => 60,
        ]);

        $saleId = $this->createSale();

        $headers = array_merge($this->tenantHeaders($this->tenantId), ['REMOTE_ADDR' => '127.0.0.1']);

        $this->withHeaders($headers)->postJson('/api/v1/sales/'.$saleId.'/void', [
            'reason' => 'void test',
            'manager_pin' => '000000',
        ])->assertStatus(422);

        $this->withHeaders($headers)->postJson('/api/v1/sales/'.$saleId.'/void', [
            'reason' => 'void test',
            'manager_pin' => '000000',
        ])->assertStatus(422);

        $locked = $this->withHeaders($headers)->postJson('/api/v1/sales/'.$saleId.'/void', [
            'reason' => 'void test',
            'manager_pin' => '000000',
        ]);

        $locked->assertStatus(429)
            ->assertJson([
                'code' => 'MANAGER_PIN_LOCKED',
            ]);
    }

    public function test_void_persists_approver_and_audit_log_when_pin_valid(): void
    {
        $saleId = $this->createSale();

        $response = $this->withHeaders($this->tenantHeaders($this->tenantId))
            ->postJson('/api/v1/sales/'.$saleId.'/void', [
                'reason' => 'void test valid pin',
                'manager_pin' => '123456',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'voided',
            ]);

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'tenant_id' => $this->tenantId,
            'status' => 'voided',
            'voided_by' => $this->userId,
            'void_approved_by' => $this->approverId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenantId,
            'actor_id' => $this->userId,
            'event_type' => 'sale_void_authorized',
            'reference_type' => 'sale',
            'reference_id' => $saleId,
        ]);
    }

    public function test_void_rejects_sale_from_other_tenant(): void
    {
        $foreignTenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Void Lain',
            'timezone' => 'UTC',
        ]);

        $foreignSaleId = $this->createForeignTenantSale($foreignTenant);

        $response = $this->withHeaders($this->tenantHeaders($this->tenantId))
            ->postJson('/api/v1/sales/'.$foreignSaleId.'/void', [
                'reason' => 'cross tenant void attempt',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VOID_FAILED');

        $this->assertNotSame('', (string) $response->json('message'));
    }

    private function createSale(): string
    {
        $payload = $this->checkoutPayload($this->fixture, '2026-02-27');
        $payload['idempotency_key'] = (string) Str::uuid();

        $response = $this->withHeaders($this->tenantHeaders($this->tenantId))
            ->postJson('/api/v1/checkout', $payload);

        $response->assertStatus(201);

        $saleId = (string) $response->json('sale_id');

        // Force amount above cashier direct-void threshold so manager PIN is required.
        \DB::table('sales')->where('id', $saleId)->where('tenant_id', $this->tenantId)->update([
            'subtotal' => 300000,
            'total' => 300000,
        ]);

        return $saleId;
    }

    private function createForeignTenantSale(Tenant $tenant): string
    {
        $fixture = $this->seedCheckoutFixture($tenant->id, $this->userId);
        $saleId = (string) Str::uuid();

        \DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'customer_id' => null,
            'subtotal' => 100000,
            'discount' => 0,
            'tax' => 0,
            'total' => 100000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $this->userId,
            'voided_by' => null,
            'created_at' => now('UTC'),
        ]);

        return $saleId;
    }
}
