<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class BranchTenantScopingTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    public function test_branch_read_and_update_paths_are_tenant_scoped(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'branch-scope@example.test', 'Branch Scope');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $localLocationId = (string) Str::uuid();
        DB::table('locations')->insert([
            'id' => $localLocationId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'name' => 'Lokasi Cabang Aktif',
            'is_default' => false,
        ]);

        [$otherTenant, $otherUser] = $this->bootApiActor('admin', 'branch-scope-other@example.test', 'Branch Scope Other');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);
        $this->actingAs($user, 'sanctum');

        $foreignLocationId = (string) Str::uuid();
        DB::table('locations')->insert([
            'id' => $foreignLocationId,
            'tenant_id' => $otherTenant->id,
            'branch_id' => $otherFixture['branch_id'],
            'name' => 'Lokasi Cabang Tenant Lain',
            'is_default' => false,
        ]);

        $indexResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/branches');

        $indexResponse->assertOk()
            ->assertJsonFragment([
                'id' => $fixture['branch_id'],
                'name' => 'Main Branch',
            ])
            ->assertJsonMissing([
                'id' => $otherFixture['branch_id'],
            ])
            ->assertJsonMissing([
                'id' => $foreignLocationId,
            ]);

        $showResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/branches/'.$otherFixture['branch_id']);

        $showResponse->assertStatus(404);

        $updateForeignBranchResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->putJson('/api/v1/branches/'.$otherFixture['branch_id'], [
                'name' => 'Tidak Boleh Update',
                'phone' => null,
                'address' => null,
                'is_active' => true,
            ]);

        $updateForeignBranchResponse->assertStatus(404);

        $updateForeignLocationResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->putJson('/api/v1/branches/'.$fixture['branch_id'], [
                'name' => 'Main Branch Renamed',
                'phone' => '0812',
                'address' => 'Alamat Baru',
                'is_active' => true,
                'default_location_id' => $foreignLocationId,
            ]);

        $updateForeignLocationResponse->assertStatus(422)
            ->assertJsonValidationErrors(['default_location_id']);

        $updateLocalLocationResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->putJson('/api/v1/branches/'.$fixture['branch_id'], [
                'name' => 'Main Branch Renamed',
                'phone' => '0812',
                'address' => 'Alamat Baru',
                'is_active' => true,
                'default_location_id' => $localLocationId,
            ]);

        $updateLocalLocationResponse->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertDatabaseHas('branches', [
            'id' => $fixture['branch_id'],
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch Renamed',
        ]);

        $this->assertDatabaseHas('locations', [
            'id' => $localLocationId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('locations', [
            'id' => $foreignLocationId,
            'tenant_id' => $otherTenant->id,
            'branch_id' => $otherFixture['branch_id'],
            'is_default' => false,
        ]);
    }
}