<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class ReceivableFlowTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_partial_checkout_creates_receivable_and_payment_can_settle_it(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+receivable@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $customerId = $this->createCustomer($tenant->id);

        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['customer_id'] = $customerId;
        $payload['payment_mode'] = 'partial';
        $payload['due_date'] = '2026-03-05';
        $payload['payments'][0]['amount'] = 4000;

        $checkout = $this->postJson('/api/v1/checkout', $payload, $this->tenantHeaders($tenant->id));

        $checkout->assertCreated()
            ->assertJsonPath('payment_status', 'partial');

        $saleId = (string) $checkout->json('sale_id');
        $receivableId = (string) $checkout->json('receivable_id');
        $mapping = DB::table('account_mappings')->where('tenant_id', $tenant->id)->first();

        $this->assertNotSame('', $receivableId);
        $this->assertDatabaseHas('receivables', [
            'id' => $receivableId,
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'sale_id' => $saleId,
            'total_amount' => 10000.00,
            'paid_amount' => 4000.00,
            'remaining_amount' => 6000.00,
            'status' => 'partial',
        ]);
        $this->assertStringStartsWith('2026-03-05', (string) DB::table('receivables')->where('id', $receivableId)->value('due_date'));
        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'account_id' => $mapping->receivable_account_id,
            'debit' => 6000.00,
            'credit' => 0.00,
        ]);

        $payment = $this->postJson("/api/v1/receivables/{$receivableId}/payments", [
            'amount' => 6000,
            'payment_method' => 'cash',
            'account_id' => $fixture['cash_account_id'],
        ], $this->tenantHeaders($tenant->id));

        $payment->assertCreated()
            ->assertJsonPath('data.receivable.status', 'paid')
            ->assertJsonPath('data.receivable.remaining_amount', 0);

        $shiftSummary = $this->getJson('/api/v1/shifts/summary', $this->tenantHeaders($tenant->id));
        $shiftSummary->assertOk()
            ->assertJsonPath('cash_sales', 4000)
            ->assertJsonPath('receivable_cash', 6000)
            ->assertJsonPath('expected_cash', 10000);

        $this->assertDatabaseHas('receivables', [
            'id' => $receivableId,
            'tenant_id' => $tenant->id,
            'paid_amount' => 10000.00,
            'remaining_amount' => 0.00,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('receivable_payments', [
            'tenant_id' => $tenant->id,
            'receivable_id' => $receivableId,
            'sale_id' => $saleId,
            'customer_id' => $customerId,
            'payment_method' => 'cash',
            'amount' => 6000.00,
        ]);
        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'account_id' => $mapping->receivable_account_id,
            'debit' => 0.00,
            'credit' => 6000.00,
        ]);
    }

    public function test_receivable_checkout_without_dp_creates_unpaid_receivable(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+receivable-no-dp@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $customerId = $this->createCustomer($tenant->id);

        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['customer_id'] = $customerId;
        $payload['payment_mode'] = 'receivable';
        $payload['due_date'] = '2026-03-06';
        $payload['payments'] = [];

        $checkout = $this->postJson('/api/v1/checkout', $payload, $this->tenantHeaders($tenant->id));

        $checkout->assertCreated()
            ->assertJsonPath('payment_status', 'unpaid');

        $saleId = (string) $checkout->json('sale_id');
        $receivableId = (string) $checkout->json('receivable_id');
        $mapping = DB::table('account_mappings')->where('tenant_id', $tenant->id)->first();

        $this->assertNotSame('', $receivableId);
        $this->assertDatabaseHas('receivables', [
            'id' => $receivableId,
            'tenant_id' => $tenant->id,
            'customer_id' => $customerId,
            'sale_id' => $saleId,
            'total_amount' => 10000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 10000.00,
            'status' => 'unpaid',
        ]);
        $this->assertDatabaseMissing('payments', [
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
        ]);
        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'account_id' => $mapping->receivable_account_id,
            'debit' => 10000.00,
            'credit' => 0.00,
        ]);
    }

    public function test_receivable_summary_only_lists_customers_with_remaining_debt(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+receivable-summary@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $activeCustomerId = $this->createCustomer($tenant->id, 'FD');
        $settledCustomerId = $this->createCustomer($tenant->id, 'kala');

        $activePayload = $this->checkoutPayload($fixture, '2026-02-27');
        $activePayload['customer_id'] = $activeCustomerId;
        $activePayload['payment_mode'] = 'receivable';
        $activePayload['payments'] = [];

        $this->postJson('/api/v1/checkout', $activePayload, $this->tenantHeaders($tenant->id))
            ->assertCreated();

        $settledPayload = $this->checkoutPayload($fixture, '2026-02-27');
        $settledPayload['customer_id'] = $settledCustomerId;
        $settledPayload['payment_mode'] = 'receivable';
        $settledPayload['payments'] = [];

        $settledCheckout = $this->postJson('/api/v1/checkout', $settledPayload, $this->tenantHeaders($tenant->id))
            ->assertCreated();

        $settledReceivableId = (string) $settledCheckout->json('receivable_id');

        $this->postJson("/api/v1/receivables/{$settledReceivableId}/payments", [
            'amount' => 10000,
            'payment_method' => 'cash',
            'account_id' => $fixture['cash_account_id'],
        ], $this->tenantHeaders($tenant->id))->assertCreated();

        $summary = $this->getJson('/api/v1/receivables/summary', $this->tenantHeaders($tenant->id))
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $summary['active_count']);
        $this->assertEquals(10000.0, $summary['active_total']);
        $this->assertSame(['FD'], collect($summary['per_customer'])->pluck('customer_name')->all());
    }

    public function test_partial_checkout_requires_customer(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier+receivable-no-customer@example.test');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $payload = $this->checkoutPayload($fixture, '2026-02-27');
        $payload['payment_mode'] = 'partial';
        $payload['payments'][0]['amount'] = 5000;

        $response = $this->postJson('/api/v1/checkout', $payload, $this->tenantHeaders($tenant->id));

        $response->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_PAYMENT');

        $this->assertDatabaseMissing('receivables', [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_receivable_related_views_render(): void
    {
        $compiledPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kalakasir-receivable-views'.DIRECTORY_SEPARATOR.Str::uuid();
        File::ensureDirectoryExists($compiledPath);
        config(['view.compiled' => str_replace('\\', '/', $compiledPath)]);

        $receivables = view('receivables', [
            'showMenu' => false,
            'receivableContext' => 'pos',
        ])->render();

        $payment = view('payment-method', [
            'showMenu' => false,
            'bankAccounts' => collect(),
            'backUrl' => '/pos/checkout',
        ])->render();
        $history = view('sales-history', [
            'showMenu' => false,
            'historyContext' => 'backoffice',
        ])->render();

        $this->assertStringContainsString('Piutang', $receivables);
        $this->assertStringContainsString('Bayar Nanti', $payment);
        $this->assertStringContainsString('report-receivable-active', $history);
    }

    private function createCustomer(string $tenantId, string $name = 'Pelanggan Piutang'): string
    {
        $customerId = (string) Str::uuid();

        DB::table('customers')->insert([
            'id' => $customerId,
            'tenant_id' => $tenantId,
            'name' => $name,
            'phone' => '08123456789',
            'email' => null,
            'address' => null,
            'created_at' => now('UTC'),
        ]);

        return $customerId;
    }
}
