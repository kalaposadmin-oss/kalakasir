<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class CheckoutShiftParityPilotTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    public function test_parity_checkout_001_success_when_shift_context_is_valid(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'pilot-kasir-a@example.com', 'Kasir A');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $payload = $this->checkoutPayload($fixture, '2026-02-27');

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/checkout', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('idempotent', false)
            ->assertJsonStructure([
                'sale_id',
                'idempotent',
            ]);

        $this->assertDatabaseHas('sales', [
            'tenant_id' => $tenant->id,
            'id' => $response->json('sale_id'),
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'status' => 'completed',
        ]);
    }

    public function test_parity_shift_002_rejects_when_actor_does_not_own_active_shift(): void
    {
        [$tenant, $ownerUser] = $this->bootApiActor('cashier', 'pilot-owner@example.com', 'Kasir Owner Shift');
        $fixture = $this->seedCheckoutFixture($tenant->id, $ownerUser->id);

        $actorUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Kasir Actor B',
            'email' => 'pilot-actor-b@example.com',
            'password' => 'secret',
        ]);

        $roleId = \DB::table('roles')
            ->where('tenant_id', $tenant->id)
            ->where('name', 'cashier')
            ->value('id');

        if (! $roleId) {
            $roleId = (string) Str::uuid();
            \DB::table('roles')->insert([
                'id' => $roleId,
                'tenant_id' => $tenant->id,
                'name' => 'cashier',
            ]);
        }

        \DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $actorUser->id,
            'role_id' => $roleId,
        ]);

        $this->actingAs($actorUser, 'sanctum');

        $payload = $this->checkoutPayload($fixture, '2026-02-27');

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/checkout', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'SHIFT_USER_MISMATCH');
    }
}
