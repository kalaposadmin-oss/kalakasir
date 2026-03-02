<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OnboardingFirstTimeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_step_one_renders(): void
    {
        $this->seedTenant();

        $this->get('/register/step-1')->assertStatus(200)->assertSee('Pendaftaran Akun Baru');
    }

    public function test_register_step_guard_redirects_for_direct_access(): void
    {
        $this->seedTenant();

        $this->get('/register/step-2')->assertRedirect('/register/step-1');
        $this->get('/register/step-3')->assertRedirect('/register/step-2');
        $this->get('/register/step-4')->assertRedirect('/register/step-3');
    }

    public function test_root_redirects_to_onboarding_when_tenant_has_no_users(): void
    {
        $this->seedTenant();

        $this->get('/')
            ->assertRedirect('/register/step-1');
    }

    public function test_root_stays_on_dashboard_when_user_exists(): void
    {
        $tenantId = $this->seedTenant();

        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'password' => bcrypt('secret123'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $this->get('/')
            ->assertStatus(200)
            ->assertSee('Dashboard Kasir');
    }

    public function test_onboarding_flow_persists_user_tenant_method_and_product(): void
    {
        $tenantId = $this->seedTenant();

        $this->from('/register/step-1')->post('/register/step-1', [
            'name' => 'Pemilik Toko',
            'email' => 'owner@onboarding.test',
            'phone' => '08123456789',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'accept_terms' => '1',
        ])->assertRedirect('/register/step-2');

        $this->from('/register/step-2')->post('/register/step-2', [
            'store_name' => 'Toko Onboarding',
            'operational_address' => 'Jl. Mawar No. 1',
            'business_category' => 'retail',
            'timezone' => 'Asia/Jakarta',
        ])->assertRedirect('/register/step-3');

        $this->from('/register/step-3')->post('/register/step-3', [
            'valuation_method' => 'fifo',
        ])->assertRedirect('/register/step-4');

        $this->from('/register/step-4')->post('/register/step-4', [
            'name' => 'Produk Pertama',
            'sku' => 'PRD-001',
            'category' => 'Minuman',
            'hpp_average' => '10000',
            'price' => '18000',
            'initial_stock' => '5',
        ])->assertRedirect('/');

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenantId,
            'email' => 'owner@onboarding.test',
            'name' => 'Pemilik Toko',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'name' => 'Toko Onboarding',
            'inventory_valuation_method' => 'fifo',
        ]);

        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenantId,
            'name' => 'Produk Pertama',
            'sku' => 'PRD-001',
        ]);
    }

    private function seedTenant(): string
    {
        $id = (string) Str::uuid();

        DB::table('tenants')->insert([
            'id' => $id,
            'name' => 'Tenant Uji',
            'timezone' => 'Asia/Jakarta',
            'domain' => null,
            'subdomain' => null,
            'logo_url' => null,
            'created_at' => now('UTC'),
            'inventory_valuation_method' => 'average',
        ]);

        return $id;
    }
}
