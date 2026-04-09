<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class CategoryTenantScopingTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_index_and_show_exclude_foreign_tenant_categories(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+category-primary@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+category-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $ownCategoryId = $this->createCategory($tenant, 'Kategori Aktif', 'kategori-aktif');
        $foreignCategoryId = $this->createCategory($foreignTenant, 'Kategori Asing', 'kategori-asing');

        $this->getJson('/api/v1/categories', $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJsonMissing(['id' => $foreignCategoryId])
            ->assertJsonPath('categories.0.id', $ownCategoryId);

        $this->getJson('/api/v1/categories/'.$foreignCategoryId, $this->tenantHeaders($tenant->id))
            ->assertStatus(404);
    }

    public function test_update_and_destroy_return_404_for_foreign_tenant_category(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+category-update@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+category-update-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $foreignCategoryId = $this->createCategory($foreignTenant, 'Kategori Asing', 'kategori-asing');

        $this->putJson('/api/v1/categories/'.$foreignCategoryId, [
            'name' => 'Kategori Diubah',
            'description' => 'Tidak boleh',
            'icon' => 'tag',
        ], $this->tenantHeaders($tenant->id))
            ->assertStatus(404);

        $this->deleteJson('/api/v1/categories/'.$foreignCategoryId, [], $this->tenantHeaders($tenant->id))
            ->assertStatus(404);
    }

    private function createCategory(Tenant $tenant, string $name, string $slug): string
    {
        $categoryId = (string) Str::uuid();

        DB::table('categories')->insert([
            'id' => $categoryId,
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => $slug,
            'description' => null,
            'icon' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return $categoryId;
    }
}