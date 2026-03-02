<?php

namespace Tests\Feature;

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
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        [$tenant, $user] = $this->bootApiActor('admin', 'admin-void-pin@example.com', 'Admin Void Pin');
        $this->tenantId = $tenant->id;
        $this->userId = $user->id;
        $this->fixture = $this->seedCheckoutFixture($this->tenantId, $this->userId);

        \DB::table('users')->where('id', $this->userId)->update([
            'manager_pin_hash' => Hash::make('123456'),
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
            'void_approved_by' => $this->userId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenantId,
            'actor_id' => $this->userId,
            'event_type' => 'sale_void_authorized',
            'reference_type' => 'sale',
            'reference_id' => $saleId,
        ]);
    }

    private function createSale(): string
    {
        $payload = $this->checkoutPayload($this->fixture, '2026-02-27');
        $payload['idempotency_key'] = (string) Str::uuid();

        $response = $this->withHeaders($this->tenantHeaders($this->tenantId))
            ->postJson('/api/v1/checkout', $payload);

        $response->assertStatus(201);

        return (string) $response->json('sale_id');
    }
}
