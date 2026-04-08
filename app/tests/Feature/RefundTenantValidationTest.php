<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\RefundController;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class RefundTenantValidationTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->post('/api/v1/test-refunds', [RefundController::class, 'store']);
    }

    public function test_refund_rejects_sale_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+refund-sale-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Tenant Refund Sale Lain');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);

        $otherSaleId = $this->createForeignTenantSale($otherTenant->id, $otherUser->id, $otherFixture);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/test-refunds', [
                'sale_id' => $otherSaleId,
                'register_id' => $fixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'items' => [
                    [
                        'sale_item_id' => (string) Str::uuid(),
                        'qty' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'account_id' => $fixture['cash_account_id'],
                        'payment_method_id' => $fixture['payment_method_id'],
                        'method' => 'cash',
                        'amount' => 10000,
                    ],
                ],
                'reason' => 'Cross tenant sale refund attempt',
            ]);

        $this->assertValidationErrorKey($response, 'sale_id');
    }

    public function test_refund_rejects_register_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+refund-register-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Tenant Refund Register Lain');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);

        $saleId = $this->createSale($tenant->id, $fixture);
        $saleItemId = (string) DB::table('sale_items')->where('tenant_id', $tenant->id)->where('sale_id', $saleId)->value('id');

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/test-refunds', [
                'sale_id' => $saleId,
                'register_id' => $otherFixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'items' => [
                    [
                        'sale_item_id' => $saleItemId,
                        'qty' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'account_id' => $fixture['cash_account_id'],
                        'payment_method_id' => $fixture['payment_method_id'],
                        'method' => 'cash',
                        'amount' => 10000,
                    ],
                ],
                'reason' => 'Cross tenant register refund attempt',
            ]);

        $this->assertValidationErrorKey($response, 'register_id');
    }

    public function test_refund_rejects_product_from_other_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+refund-product-scope@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        [$otherTenant, $otherUser] = $this->createOtherTenantActor('Tenant Refund Product Lain');
        $otherFixture = $this->seedCheckoutFixture($otherTenant->id, $otherUser->id);

        $saleId = $this->createSale($tenant->id, $fixture);
        $saleItemId = (string) DB::table('sale_items')->where('tenant_id', $tenant->id)->where('sale_id', $saleId)->value('id');

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/test-refunds', [
                'sale_id' => $saleId,
                'register_id' => $fixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'items' => [
                    [
                        'sale_item_id' => $saleItemId,
                        'product_id' => $otherFixture['product_id'],
                        'qty' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'account_id' => $fixture['cash_account_id'],
                        'payment_method_id' => $fixture['payment_method_id'],
                        'method' => 'cash',
                        'amount' => 10000,
                    ],
                ],
                'reason' => 'Cross tenant product refund attempt',
            ]);

        $this->assertValidationErrorKey($response, 'items.0.product_id');
    }

    private function createSale(string $tenantId, array $fixture): string
    {
        $response = $this->withHeaders($this->tenantHeaders($tenantId))
            ->postJson('/api/v1/checkout', $this->checkoutPayload($fixture, '2026-02-27'));

        $response->assertCreated();

        return (string) $response->json('sale_id');
    }

    private function createForeignTenantSale(string $tenantId, string $userId, array $fixture): string
    {
        $saleId = (string) Str::uuid();

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenantId,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'customer_id' => null,
            'subtotal' => 10000,
            'discount' => 0,
            'tax' => 0,
            'total' => 10000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $userId,
            'voided_by' => null,
            'void_approved_by' => null,
            'void_reason' => null,
            'voided_at' => null,
            'created_at' => now('UTC'),
        ]);

        return $saleId;
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