<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class CashierTenantScopingTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_index_excludes_foreign_tenant_cashiers_and_preserves_count(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier-primary@example.test', 'Kasir Aktif');
        [$foreignTenant, $foreignUser] = $this->bootApiActor('cashier', 'cashier-foreign@example.test', 'Kasir Asing');

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/v1/cashiers', $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('data.cashiers.0.id', $user->id)
            ->assertJsonPath('data.cashiers.0.name', 'Kasir Aktif')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonMissing(['id' => $foreignUser->id])
            ->assertJsonMissing(['name' => 'Kasir Asing']);
    }
}