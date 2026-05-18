<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class CustomerCategoryApiTest extends TestCase
{
    use RefreshDatabase;
    use BootsApiTenantUser;

    public function test_customer_category_can_be_created_used_and_deleted_without_deleting_customer(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+customer-category@example.test');
        $tenant->forceFill(['plan' => 'M'])->save();

        $this->actingAs($user, 'sanctum');

        $categoryResponse = $this->postJson('/api/v1/customer-categories', [
            'name' => 'VIP',
            'color' => 'emerald',
        ], $this->tenantHeaders($tenant->id));

        $categoryResponse->assertCreated()
            ->assertJsonPath('customer_category.name', 'VIP')
            ->assertJsonPath('customer_category.color', 'emerald');

        $categoryId = $categoryResponse->json('customer_category.id');

        $customerResponse = $this->postJson('/api/v1/customers', [
            'name' => 'Aldi',
            'phone' => '08123456789',
            'address' => 'Jl. Pelanggan',
            'customer_category_id' => $categoryId,
        ], $this->tenantHeaders($tenant->id));

        $customerResponse->assertCreated()
            ->assertJsonPath('customer.name', 'Aldi')
            ->assertJsonPath('customer.customer_category_id', $categoryId)
            ->assertJsonPath('customer.category.name', 'VIP');

        $customerId = $customerResponse->json('customer.id');

        $this->getJson('/api/v1/customer-categories', $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->assertJsonPath('customer_categories.0.id', $categoryId)
            ->assertJsonPath('customer_categories.0.customer_count', 1);

        $this->deleteJson('/api/v1/customer-categories/'.$categoryId, [], $this->tenantHeaders($tenant->id))
            ->assertOk();

        $this->assertDatabaseMissing('customer_categories', [
            'id' => $categoryId,
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'tenant_id' => $tenant->id,
            'customer_category_id' => null,
        ]);
    }

    public function test_customer_category_index_excludes_foreign_tenant_categories(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+customer-category-scope@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+customer-category-foreign@example.test');
        $tenant->forceFill(['plan' => 'M'])->save();
        $foreignTenant->forceFill(['plan' => 'M'])->save();

        $this->actingAs($user, 'sanctum');

        $ownCategoryId = $this->createCustomerCategory($tenant, 'Langganan');
        $foreignCategoryId = $this->createCustomerCategory($foreignTenant, 'Asing');

        $response = $this->getJson('/api/v1/customer-categories', $this->tenantHeaders($tenant->id));

        $response->assertOk()
            ->assertJsonPath('customer_categories.0.id', $ownCategoryId)
            ->assertJsonMissing(['id' => $foreignCategoryId]);
    }

    private function createCustomerCategory(Tenant $tenant, string $name): string
    {
        $categoryId = (string) Str::uuid();

        DB::table('customer_categories')->insert([
            'id' => $categoryId,
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'color' => 'slate',
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return $categoryId;
    }
}
