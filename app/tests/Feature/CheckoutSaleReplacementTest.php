<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class CheckoutSaleReplacementTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    private string $tenantId;
    private string $userId;
    private array $fixture;
    private string $customerId;

    protected function setUp(): void
    {
        parent::setUp();

        [$tenant, $user] = $this->bootApiActor('admin', 'admin-replace@example.com', 'Admin Replace');
        $this->tenantId = $tenant->id;
        $this->userId = $user->id;
        $this->fixture = $this->seedCheckoutFixture($this->tenantId, $this->userId);

        $this->customerId = (string) Str::uuid();
        Customer::query()->create([
            'id' => $this->customerId,
            'tenant_id' => $this->tenantId,
            'name' => 'Customer Replace',
            'phone' => '08123456789',
            'address' => 'Jl. Replace',
        ]);
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_checkout_replaces_previous_unpaid_receivable_sale(): void
    {
        $transactionDate = CarbonImmutable::now('Asia/Jakarta')->toDateString();

        $originalPayload = $this->checkoutPayload($this->fixture, $transactionDate);
        $originalPayload['idempotency_key'] = (string) Str::uuid();
        $originalPayload['customer_id'] = $this->customerId;
        $originalPayload['order_holder_name'] = 'Toko Replace';
        $originalPayload['order_note'] = 'Catatan awal transaksi';
        $originalPayload['payment_mode'] = 'receivable';
        $originalPayload['payments'] = [];
        $originalPayload['due_date'] = CarbonImmutable::now('Asia/Jakarta')->addDays(7)->toDateString();

        $originalResponse = $this->withHeaders($this->tenantHeaders($this->tenantId))
            ->postJson('/api/v1/checkout', $originalPayload);

        $originalResponse->assertStatus(201);

        $originalSaleId = (string) $originalResponse->json('sale_id');
        $originalReceivableId = (string) $originalResponse->json('receivable_id');

        $detailResponse = $this->withHeaders($this->tenantHeaders($this->tenantId))
            ->getJson('/api/v1/sales/'.$originalSaleId);

        $detailResponse->assertOk()
            ->assertJsonPath('sale.order_holder_name', 'Toko Replace')
            ->assertJsonPath('sale.order_note', 'Catatan awal transaksi');

        $replacementPayload = $originalPayload;
        $replacementPayload['idempotency_key'] = (string) Str::uuid();
        $replacementPayload['replace_sale_id'] = $originalSaleId;
        $replacementPayload['items'][0]['qty'] = 2;
        $replacementPayload['subtotal'] = 20000;
        $replacementPayload['total'] = 20000;

        $replacementResponse = $this->withHeaders($this->tenantHeaders($this->tenantId))
            ->postJson('/api/v1/checkout', $replacementPayload);

        $replacementResponse->assertStatus(201);

        $replacementSaleId = (string) $replacementResponse->json('sale_id');
        $replacementReceivableId = (string) $replacementResponse->json('receivable_id');

        $this->assertNotSame($originalSaleId, $replacementSaleId);
        $this->assertNotSame($originalReceivableId, $replacementReceivableId);

        $this->assertDatabaseHas('sales', [
            'id' => $originalSaleId,
            'tenant_id' => $this->tenantId,
            'status' => Sale::STATUS_VOIDED,
        ]);

        $this->assertDatabaseMissing('receivables', [
            'id' => $originalReceivableId,
            'tenant_id' => $this->tenantId,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $replacementSaleId,
            'tenant_id' => $this->tenantId,
            'qty' => 2,
        ]);

        $this->assertDatabaseHas('receivables', [
            'id' => $replacementReceivableId,
            'tenant_id' => $this->tenantId,
            'sale_id' => $replacementSaleId,
        ]);
    }
}