<?php

namespace Tests\Feature;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Models\User;
use Tests\TestCase;

class OnboardingFirstTimeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_step_one_renders(): void
    {
        $this->seedTenant();

        $request = Request::create('/register/step-1', 'GET');
        $request->setLaravelSession(app('session.store'));
        $route = Route::getRoutes()->match($request);
        $response = app()->call($route->getAction('uses'), ['request' => $request]);
        $viewContents = file_get_contents(resource_path('views/onboarding-register-user.blade.php'));

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('onboarding-register-user', $response->name());
        $this->assertStringContainsString('Akun pertama ini otomatis menjadi Owner', $viewContents);
        $this->assertStringContainsString('Kasir dan staff lain ditambahkan nanti', $viewContents);
    }

    public function test_register_step_guard_redirects_for_direct_access(): void
    {
        $this->seedTenant();

        $this->get('/register/step-2')->assertRedirect('/register/step-1');
        $this->get('/register/step-3')->assertRedirect('/register/step-2');
        $this->get('/register/step-4')->assertRedirect('/register/step-3');
        $this->get('/register/review')->assertRedirect('/register/step-4');
    }

    public function test_onboarding_step_one_with_expired_session_redirects_back_with_message(): void
    {
        $this->seedTenant();

        Route::post('/register/step-expired', static function () {
            throw new TokenMismatchException('expired');
        });

        $response = $this->post('/register/step-expired', [
            'name' => 'Owner Expired',
            'email' => 'owner-expired@example.test',
            'phone' => '08123456789',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'accept_terms' => '1',
        ]);

        $response->assertRedirect('/register/step-expired');
        $response->assertSessionHasErrors('session');
        $response->assertSessionHas('_old_input.name', 'Owner Expired');
        $response->assertSessionHas('_old_input.email', 'owner-expired@example.test');
    }

    public function test_register_step_four_view_exposes_variant_initial_stock_contract(): void
    {
        $this->seedTenant();

        $contents = file_get_contents(resource_path('views/onboarding-product-first.blade.php'));

        $this->assertStringContainsString('enable-variants', $contents);
        $this->assertStringContainsString('variant-line-list', $contents);
        $this->assertStringContainsString('variant-stock-line-list', $contents);
        $this->assertStringContainsString('variant-hidden-inputs', $contents);
        $this->assertStringContainsString('appendHiddenInput(`initial_stock_lines[${index}][product_variant_id]`, line.product_variant_id);', $contents);
        $this->assertStringContainsString('appendHiddenInput(`variants[${index}][id]`, variant.id);', $contents);
        $this->assertStringContainsString("initialStockInput.value = '0';", $contents);
        $this->assertStringContainsString("hiddenInputs.innerHTML = '';", $contents);
        $this->assertStringContainsString('Opsional. Boleh dikosongkan jika HPP baru akan terbentuk saat pembelian atau produksi pertama.', $contents);
        $this->assertStringContainsString('Opsional. Boleh dikosongkan jika harga jual akan ditentukan setelah onboarding.', $contents);
        $this->assertStringContainsString('@error(\'hpp_average\')', $contents);
    }

    public function test_onboarding_step_four_allows_empty_hpp_and_price_and_defaults_to_zero(): void
    {
        $existingTenantId = $this->seedTenant();

        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $existingTenantId,
            'name' => 'Owner Existing',
            'email' => 'owner-existing-validation@example.test',
            'password' => bcrypt('secret123'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $this->completeOnboardingStepThree('owner-validation@example.test', 'Tenant Validasi');

        $this->from('/register/step-4')
            ->post('/register/step-4', [
                'name' => 'Produk Validasi',
                'sku' => 'VAL-001',
                'category' => 'Snack',
                'hpp_average' => '',
                'price' => '',
                'initial_stock' => '0',
            ])
            ->assertRedirect('/register/review')
            ->assertSessionHas('onboarding.step4.skip_product', false);

        $this->post('/register/complete', [
            'selected_plan' => 'M',
        ])
            ->assertRedirect('/pos/dashboard')
            ->assertSessionHas('auth_portal', 'pos');

        $tenant = DB::table('tenants')->where('name', 'Tenant Validasi')->first();
        $this->assertNotNull($tenant);

        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenant->id,
            'sku' => 'VAL-001',
            'price' => 0,
            'hpp_average' => 0,
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'plan' => 'M',
        ]);

        $subscription = DB::table('subscriptions')
            ->where('tenant_id', $tenant->id)
            ->first();

        $this->assertNotNull($subscription);
        $this->assertSame('M', $subscription->plan);
        $this->assertSame('active', $subscription->status);
        $this->assertTrue((bool) $subscription->is_current);
    }

    public function test_onboarding_step_four_invalid_numeric_values_use_indonesian_messages(): void
    {
        $existingTenantId = $this->seedTenant();

        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $existingTenantId,
            'name' => 'Owner Existing',
            'email' => 'owner-existing-validation-number@example.test',
            'password' => bcrypt('secret123'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $this->completeOnboardingStepThree('owner-validation-number@example.test', 'Tenant Validasi Angka');

        $this->followingRedirects()
            ->from('/register/step-4')
            ->post('/register/step-4', [
                'name' => 'Produk Validasi',
                'sku' => 'VAL-002',
                'category' => 'Snack',
                'hpp_average' => 'abc',
                'price' => 'xyz',
                'initial_stock' => '1',
            ])
            ->assertSee('Silakan lengkapi field wajib dan perbaiki input yang masih invalid.')
            ->assertSee('Harga pokok (hpp) harus berupa angka.', false)
            ->assertSee('Harga jual harus berupa angka.', false)
            ->assertDontSee('validation.numeric', false);
    }

    public function test_root_redirects_to_onboarding_when_tenant_has_no_users(): void
    {
        $this->seedTenant();

        $this->get('/')
            ->assertRedirect('/register/step-1');
    }

    public function test_root_renders_portal_chooser_when_user_exists(): void
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
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Pilih Area Kerja')
            ->assertSee('Masuk POS')
            ->assertSee('Masuk Backoffice')
            ->assertSee('href="/pos/login"', false)
            ->assertDontSee('name="email"', false)
            ->assertDontSee('action="/"', false);
    }

    public function test_onboarding_step_two_assigns_owner_and_creates_default_branch_location(): void
    {
        $tenantId = $this->seedTenant();

        $this->from('/register/step-1')->post('/register/step-1', [
            'name' => 'Owner Step Dua',
            'email' => 'owner-step-dua@example.test',
            'phone' => '081234567890',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'accept_terms' => '1',
        ])->assertRedirect('/register/step-2');

        $this->from('/register/step-2')->post('/register/step-2', [
            'store_name' => 'Toko Step Dua',
            'operational_address' => 'Jl. Operasional No. 1',
            'business_category' => 'retail',
            'timezone' => 'Asia/Jakarta',
        ])->assertRedirect('/register/step-3');

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $this->assertNotNull($tenant);
        $this->assertSame('Toko Step Dua', $tenant->name);

        $ownerId = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('email', 'owner-step-dua@example.test')
            ->value('id');

        $ownerRoleId = DB::table('roles')
            ->where('tenant_id', $tenantId)
            ->where('name', 'Owner')
            ->value('id');

        $defaultLocation = DB::table('locations')
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();

        $branch = DB::table('branches')
            ->where('tenant_id', $tenantId)
            ->where('name', 'Toko Step Dua')
            ->first();

        $this->assertNotNull($ownerId);
        $this->assertNotNull($ownerRoleId);
        $this->assertNotNull($defaultLocation);
        $this->assertNotNull($branch);

        $this->assertDatabaseHas('user_roles', [
            'tenant_id' => $tenantId,
            'user_id' => $ownerId,
            'role_id' => $ownerRoleId,
        ]);
        $this->assertSame($defaultLocation->id, $tenant->default_location_id);
        $this->assertSame($branch->id, $defaultLocation->branch_id);
    }

    public function test_onboarding_creates_new_tenant_when_existing_tenant_already_has_users(): void
    {
        $existingTenantId = $this->seedTenant();

        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $existingTenantId,
            'name' => 'Owner Existing',
            'email' => 'owner-existing@example.test',
            'password' => bcrypt('secret123'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $this->from('/register/step-1')->post('/register/step-1', [
            'name' => 'Owner Baru',
            'email' => 'owner-baru@example.test',
            'phone' => '081298765432',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'accept_terms' => '1',
        ])->assertRedirect('/register/step-2');

        $this->from('/register/step-2')->post('/register/step-2', [
            'store_name' => 'Tenant Baru',
            'operational_address' => 'Jl. Baru No. 2',
            'business_category' => 'service',
            'timezone' => 'Asia/Jakarta',
        ])->assertRedirect('/register/step-3');

        $this->from('/register/step-3')->post('/register/step-3', [
            'valuation_method' => 'fifo',
        ])->assertRedirect('/register/step-4');

        $this->from('/register/step-4')->post('/register/step-4', [
            'name' => 'Produk Baru',
            'sku' => 'NEW-001',
            'category' => 'Layanan',
            'hpp_average' => '15000',
            'price' => '25000',
            'initial_stock' => '2',
        ])->assertRedirect('/register/review');

        $this->assertDatabaseMissing('products', [
            'sku' => 'NEW-001',
        ]);

        $this->post('/register/complete', [
            'selected_plan' => 'M',
        ])->assertRedirect('/pos/dashboard')
            ->assertSessionHas('auth_portal', 'pos');

        $this->assertAuthenticated();

        $newTenant = DB::table('tenants')
            ->where('name', 'Tenant Baru')
            ->first();

        $this->assertNotNull($newTenant);
        $this->assertNotSame($existingTenantId, $newTenant->id);

        $this->assertDatabaseHas('tenants', [
            'id' => $existingTenantId,
            'name' => 'Tenant Uji',
            'inventory_valuation_method' => 'average',
        ]);

        $this->assertDatabaseHas('users', [
            'tenant_id' => $newTenant->id,
            'email' => 'owner-baru@example.test',
            'name' => 'Owner Baru',
        ]);

        $newOwnerRoleId = DB::table('roles')
            ->where('tenant_id', $newTenant->id)
            ->where('name', 'Owner')
            ->value('id');

        $newOwnerUserId = DB::table('users')
            ->where('tenant_id', $newTenant->id)
            ->where('email', 'owner-baru@example.test')
            ->value('id');

        $this->assertNotNull($newOwnerRoleId);
        $this->assertNotNull($newOwnerUserId);
        $this->assertDatabaseHas('user_roles', [
            'tenant_id' => $newTenant->id,
            'user_id' => $newOwnerUserId,
            'role_id' => $newOwnerRoleId,
        ]);

        $this->assertDatabaseHas('products', [
            'tenant_id' => $newTenant->id,
            'sku' => 'NEW-001',
            'name' => 'Produk Baru',
        ]);

        $this->assertSame('M', $newTenant->plan);

        $this->assertDatabaseMissing('product_variants', [
            'tenant_id' => $newTenant->id,
            'product_id' => DB::table('products')->where('tenant_id', $newTenant->id)->where('sku', 'NEW-001')->value('id'),
        ]);
    }

    public function test_onboarding_variant_product_posts_initial_stock_per_variant_bucket(): void
    {
        $existingTenantId = $this->seedTenant();

        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $existingTenantId,
            'name' => 'Owner Existing',
            'email' => 'owner-existing-variant@example.test',
            'password' => bcrypt('secret123'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $this->completeOnboardingStepThree('owner-variant@example.test', 'Tenant Variant Baru');

        $variantA = (string) Str::uuid();
        $variantB = (string) Str::uuid();

        $this->from('/register/step-4')->post('/register/step-4', [
            'name' => 'Produk Variant Baru',
            'sku' => 'NEW-VAR-001',
            'category' => 'Layanan',
            'hpp_average' => '15000',
            'price' => '25000',
            'initial_stock' => '0',
            'variants' => [
                ['id' => $variantA, 'sku' => 'VAR-A', 'name' => 'Variant A'],
                ['id' => $variantB, 'sku' => 'VAR-B', 'name' => 'Variant B'],
            ],
            'initial_stock_lines' => [
                ['product_variant_id' => $variantA, 'qty' => '2', 'unit_cost' => '14000'],
                ['product_variant_id' => $variantB, 'qty' => '3', 'unit_cost' => '16000'],
            ],
        ])->assertRedirect('/register/review');

        $this->post('/register/complete', [
            'selected_plan' => 'P',
        ])->assertRedirect('/pos/dashboard')
            ->assertSessionHas('auth_portal', 'pos');

        $newTenant = DB::table('tenants')->where('name', 'Tenant Variant Baru')->first();
        $this->assertNotNull($newTenant);

        $product = DB::table('products')
            ->where('tenant_id', $newTenant->id)
            ->where('sku', 'NEW-VAR-001')
            ->first();

        $this->assertNotNull($product);
        $this->assertDatabaseHas('product_variants', [
            'tenant_id' => $newTenant->id,
            'product_id' => $product->id,
            'id' => $variantA,
            'sku' => 'VAR-A',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'tenant_id' => $newTenant->id,
            'product_id' => $product->id,
            'id' => $variantB,
            'sku' => 'VAR-B',
            'is_active' => true,
        ]);

        $locationId = DB::table('locations')->where('tenant_id', $newTenant->id)->where('is_default', true)->value('id');
        $this->assertNotNull($locationId);
        $this->assertSame('P', $newTenant->plan);

        $this->assertSame(2.0, $this->inventoryBalanceQty($newTenant->id, $product->id, $variantA, $locationId));
        $this->assertSame(3.0, $this->inventoryBalanceQty($newTenant->id, $product->id, $variantB, $locationId));
        $this->assertNull(DB::table('inventory_balances')
            ->where('tenant_id', $newTenant->id)
            ->where('product_id', $product->id)
            ->where('location_id', $locationId)
            ->whereNull('product_variant_id')
            ->value('qty_on_hand'));
    }

    public function test_onboarding_review_renders_summary_and_edit_links(): void
    {
        $this->seedTenant();

        $this->withSession([
            'onboarding.step1' => [
                'name' => 'Owner Review',
                'email' => 'owner-review@example.test',
                'phone' => '08123',
                'password' => 'secret123',
            ],
            'onboarding.step2' => [
                'tenant_id' => (string) Str::uuid(),
                'user_id' => (string) Str::uuid(),
                'store_name' => 'Toko Review',
                'operational_address' => 'Jl. Review No. 1',
                'business_category' => 'service',
                'timezone' => 'Asia/Jakarta',
                'valuation_method' => 'fifo',
            ],
            'onboarding.step3' => [
                'valuation_method' => 'fifo',
            ],
            'onboarding.step4' => [
                'skip_product' => false,
                'product' => [
                    'name' => 'Produk Review',
                    'sku' => 'REV-001',
                    'category' => 'Layanan',
                    'hpp_average' => '0',
                    'price' => '0',
                    'initial_stock' => '1',
                    'variants' => [],
                    'initial_stock_lines' => [],
                ],
            ],
        ])->get('/register/review')
            ->assertOk()
            ->assertSee('Review Sebelum Masuk')
            ->assertSee('Pilih Paket')
            ->assertSee('Cocok untuk:')
            ->assertSee('Owner Review')
            ->assertSee('Toko Review')
            ->assertSee('Produk Review')
            ->assertSee('Konfirmasi dan Masuk Dashboard')
            ->assertSee('href="/register/step-1"', false)
            ->assertSee('href="/register/step-4"', false);
    }

    public function test_onboarding_variant_rejects_initial_stock_line_without_product_variant_id(): void
    {
        $existingTenantId = $this->seedTenant();

        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $existingTenantId,
            'name' => 'Owner Existing',
            'email' => 'owner-existing-variant-invalid@example.test',
            'password' => bcrypt('secret123'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $this->completeOnboardingStepThree('owner-variant-invalid@example.test', 'Tenant Variant Invalid');

        $this->from('/register/step-4')->post('/register/step-4', [
            'name' => 'Produk Variant Invalid',
            'sku' => 'NEW-VAR-ERR-001',
            'category' => 'Layanan',
            'hpp_average' => '15000',
            'price' => '25000',
            'initial_stock' => '0',
            'variants' => [
                ['id' => (string) Str::uuid(), 'sku' => 'VAR-X', 'name' => 'Variant X'],
            ],
            'initial_stock_lines' => [
                ['qty' => '2', 'unit_cost' => '14000'],
            ],
        ])->assertRedirect('/register/step-4')
            ->assertSessionHasErrors(['initial_stock_lines.0.product_variant_id']);
    }

    public function test_onboarding_step_four_can_skip_initial_product_and_still_login_owner(): void
    {
        $existingTenantId = $this->seedTenant();

        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $existingTenantId,
            'name' => 'Owner Existing',
            'email' => 'owner-existing-skip@example.test',
            'password' => bcrypt('secret123'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $this->completeOnboardingStepThree('owner-skip@example.test', 'Tenant Skip Produk');

        $this->from('/register/step-4')->post('/register/step-4', [
            'skip_product' => '1',
        ])->assertRedirect('/register/review');

        $this->post('/register/complete', [
            'selected_plan' => 'S',
        ])->assertRedirect('/pos/dashboard')
            ->assertSessionHas('auth_portal', 'pos');

        $this->assertAuthenticated();

        $newTenant = DB::table('tenants')->where('name', 'Tenant Skip Produk')->first();
        $this->assertNotNull($newTenant);
        $this->assertSame('S', $newTenant->plan);
        $this->assertDatabaseMissing('products', [
            'tenant_id' => $newTenant->id,
        ]);

        $subscription = DB::table('subscriptions')
            ->where('tenant_id', $newTenant->id)
            ->first();

        $this->assertNotNull($subscription);
        $this->assertSame('S', $subscription->plan);
        $this->assertSame('active', $subscription->status);
        $this->assertTrue((bool) $subscription->is_current);
    }

    public function test_onboarding_completion_clears_step_session_and_marks_completed(): void
    {
        $existingTenantId = $this->seedTenant();

        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $existingTenantId,
            'name' => 'Owner Existing',
            'email' => 'owner-existing-session@example.test',
            'password' => bcrypt('secret123'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $this->completeOnboardingStepThree('owner-session@example.test', 'Tenant Session Final');

        $this->from('/register/step-4')->post('/register/step-4', [
            'skip_product' => '1',
        ])->assertRedirect('/register/review');

        $this->post('/register/complete', [
            'selected_plan' => 'M',
        ])->assertRedirect('/pos/dashboard')
            ->assertSessionHas('auth_portal', 'pos')
            ->assertSessionHas('onboarding.completed', true)
            ->assertSessionMissing('onboarding.step1')
            ->assertSessionMissing('onboarding.step2')
            ->assertSessionMissing('onboarding.step3')
            ->assertSessionMissing('onboarding.step4');
    }

    private function completeOnboardingStepThree(string $ownerEmail, string $storeName): void
    {
        $this->from('/register/step-1')->post('/register/step-1', [
            'name' => 'Owner Baru',
            'email' => $ownerEmail,
            'phone' => '081298765432',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'accept_terms' => '1',
        ])->assertRedirect('/register/step-2');

        $this->from('/register/step-2')->post('/register/step-2', [
            'store_name' => $storeName,
            'operational_address' => 'Jl. Baru No. 2',
            'business_category' => 'service',
            'timezone' => 'Asia/Jakarta',
        ])->assertRedirect('/register/step-3');

        $this->from('/register/step-3')->post('/register/step-3', [
            'valuation_method' => 'fifo',
        ])->assertRedirect('/register/step-4');
    }

    private function inventoryBalanceQty(string $tenantId, string $productId, ?string $variantId, string $locationId): float
    {
        return (float) DB::table('inventory_balances')
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->when($variantId, function ($query) use ($variantId) {
                $query->where('product_variant_id', $variantId);
            }, function ($query) {
                $query->whereNull('product_variant_id');
            })
            ->value('qty_on_hand');
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
