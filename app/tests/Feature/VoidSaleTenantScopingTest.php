<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\VoidSaleController;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class VoidSaleTenantScopingTest extends TestCase
{
    use BootsApiTenantUser, BuildsCheckoutRequestFixture, RefreshDatabase;

    private Tenant $tenant;
    private string $userId;
    private array $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->tenant, $user] = $this->bootApiActor('cashier', 'cashier-void-scope@example.com', 'Cashier Void Scope');
        $this->userId = $user->id;
        $this->fixture = $this->seedCheckoutFixture($this->tenant->id, $this->userId);
    }

    protected function tearDown(): void
    {
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_void_requires_resolved_tenant_context(): void
    {
        TenantContext::clear();

        $response = app(VoidSaleController::class)->__invoke(
            Request::create('/api/v1/sales/test-sale-id/void', 'POST'),
            'test-sale-id',
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame([
            'code' => 'TENANT_NOT_RESOLVED',
            'message' => 'Tenant tidak ditemukan.',
            'details' => [],
        ], $response->getData(true));
    }

    public function test_foreign_tenant_sale_cannot_be_voided_across_tenant_boundary(): void
    {
        $foreignTenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Void Foreign',
            'timezone' => 'UTC',
        ]);

        $foreignSaleId = $this->createForeignTenantSale($foreignTenant);

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/sales/'.$foreignSaleId.'/void', [
                'reason_code' => 'V01',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VOID_FAILED');

        $this->assertDatabaseHas('sales', [
            'id' => $foreignSaleId,
            'tenant_id' => $foreignTenant->id,
            'status' => 'completed',
            'voided_by' => null,
        ]);
    }

    public function test_same_tenant_void_flow_preserves_existing_success_contract(): void
    {
        $saleId = $this->createSaleForCurrentTenant();

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/sales/'.$saleId.'/void', [
                'reason_code' => 'V01',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'voided')
            ->assertJsonStructure([
                'status',
                'governance',
            ]);

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'tenant_id' => $this->tenant->id,
            'status' => 'voided',
            'voided_by' => $this->userId,
        ]);
    }

    private function createSaleForCurrentTenant(): string
    {
        $payload = $this->checkoutPayload($this->fixture, '2026-02-27');
        $payload['idempotency_key'] = (string) Str::uuid();

        $response = $this->withHeaders($this->tenantHeaders($this->tenant->id))
            ->postJson('/api/v1/checkout', $payload);

        $response->assertStatus(201);

        $saleId = (string) $response->json('sale_id');

        DB::table('sales')
            ->where('id', $saleId)
            ->where('tenant_id', $this->tenant->id)
            ->update([
                'subtotal' => 150000,
                'total' => 150000,
            ]);

        return $saleId;
    }

    private function createForeignTenantSale(Tenant $tenant): string
    {
        $fixture = $this->seedCheckoutFixture($tenant->id, $this->userId);
        $saleId = (string) Str::uuid();

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'customer_id' => null,
            'subtotal' => 100000,
            'discount' => 0,
            'tax' => 0,
            'total' => 100000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $this->userId,
            'voided_by' => null,
            'created_at' => now('UTC'),
        ]);

        return $saleId;
    }
}