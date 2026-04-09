<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class CheckoutTenantValidationTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_checkout_rejects_branch_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+checkout-branch-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Tenant Branch Lain');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);

        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['branch_id'] = $otherFixture['branch_id'];

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/checkout', $payload);

        $this->assertValidationErrorKey($response, 'branch_id');
    }

    public function test_checkout_rejects_shift_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+checkout-shift-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Tenant Shift Lain');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);

        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['shift_id'] = $otherFixture['shift_id'];

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/checkout', $payload);

        $this->assertValidationErrorKey($response, 'shift_id');
    }

    public function test_checkout_rejects_register_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+checkout-register-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Tenant Register Lain');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);

        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['register_id'] = $otherFixture['register_id'];

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/checkout', $payload);

        $this->assertValidationErrorKey($response, 'register_id');
    }

    public function test_checkout_rejects_customer_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+checkout-customer-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $foreignCustomerId = $this->insertCustomerForOtherTenant();

        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['customer_id'] = $foreignCustomerId;

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/checkout', $payload);

        $this->assertValidationErrorKey($response, 'customer_id');
    }

    public function test_checkout_rejects_product_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+checkout-product-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Tenant Produk Checkout Lain');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);

        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['items'][0]['product_id'] = $otherFixture['product_id'];

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/checkout', $payload);

        $this->assertValidationErrorKey($response, 'items.0.product_id');
    }

    public function test_checkout_rejects_location_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+checkout-location-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Tenant Lokasi Checkout Lain');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);

        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['items'][0]['location_id'] = $otherFixture['location_id'];

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/checkout', $payload);

        $this->assertValidationErrorKey($response, 'items.0.location_id');
    }

    public function test_checkout_rejects_account_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+checkout-account-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Tenant Account Checkout Lain');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);

        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['payments'][0]['account_id'] = $otherFixture['cash_account_id'];

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/checkout', $payload);

        $this->assertValidationErrorKey($response, 'payments.0.account_id');
    }

    public function test_checkout_rejects_payment_method_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+checkout-payment-method-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Tenant Payment Method Lain');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);

        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['payments'][0]['payment_method_id'] = $otherFixture['payment_method_id'];

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/checkout', $payload);

        $this->assertValidationErrorKey($response, 'payments.0.payment_method_id');
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

    private function insertCustomerForOtherTenant(): string
    {
        [$otherTenant] = $this->createOtherTenantActor('Tenant Customer Lain');
        $customerId = (string) Str::uuid();

        DB::table('customers')->insert([
            'id' => $customerId,
            'tenant_id' => $otherTenant->id,
            'name' => 'Customer Tenant Lain',
            'phone' => null,
            'email' => null,
            'address' => null,
            'created_at' => now('UTC'),
        ]);

        return $customerId;
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