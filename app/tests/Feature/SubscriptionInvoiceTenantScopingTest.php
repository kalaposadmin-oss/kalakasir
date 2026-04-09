<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class SubscriptionInvoiceTenantScopingTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_index_and_show_exclude_foreign_tenant_invoices(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+invoice-scope-primary@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+invoice-scope-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $ownInvoiceId = $this->insertInvoice($tenant->id, 'INV-OWN-001');
        $foreignInvoiceId = $this->insertInvoice($foreignTenant->id, 'INV-FOR-001');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/subscription/invoices')
            ->assertOk()
            ->assertJsonMissing(['id' => $foreignInvoiceId])
            ->assertJsonPath('data.invoices.0.id', $ownInvoiceId);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/subscription/invoices/'.$foreignInvoiceId)
            ->assertStatus(404)
            ->assertJsonPath('code', 'INVOICE_NOT_FOUND');
    }

    public function test_mark_paid_rejects_foreign_tenant_invoice_without_mutation(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+invoice-mark-primary@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+invoice-mark-foreign@example.test');

        $this->actingAs($user, 'sanctum');

        $foreignInvoiceId = $this->insertInvoice($foreignTenant->id, 'INV-FOR-002');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/subscription/invoices/'.$foreignInvoiceId.'/mark-paid', [
                'provider' => 'manual',
                'reference' => 'MANUAL-FOR-002',
            ])
            ->assertStatus(404)
            ->assertJsonPath('code', 'INVOICE_NOT_FOUND');

        $this->assertDatabaseHas('invoices', [
            'id' => $foreignInvoiceId,
            'tenant_id' => $foreignTenant->id,
            'status' => 'unpaid',
            'amount_paid' => 0,
        ]);

        $this->assertDatabaseMissing('subscription_payments', [
            'tenant_id' => $tenant->id,
            'invoice_id' => $foreignInvoiceId,
            'reference' => 'MANUAL-FOR-002',
        ]);
    }

    private function insertInvoice(string $tenantId, string $number): string
    {
        $invoiceId = (string) Str::uuid();

        DB::table('invoices')->insert([
            'id' => $invoiceId,
            'tenant_id' => $tenantId,
            'number' => $number,
            'period_start' => now('UTC')->subMonth()->toDateString(),
            'period_end' => now('UTC')->toDateString(),
            'amount_due' => 100000,
            'amount_paid' => 0,
            'status' => 'unpaid',
            'issued_at' => now('UTC')->subDays(5),
            'due_at' => now('UTC')->addDays(5),
            'paid_at' => null,
            'metadata' => null,
            'created_at' => now('UTC')->subDays(5),
            'updated_at' => now('UTC')->subDays(5),
        ]);

        DB::table('invoice_items')->insert([
            'invoice_id' => $invoiceId,
            'description' => 'Subscription plan',
            'qty' => 1,
            'unit_price' => 100000,
            'total_price' => 100000,
            'created_at' => now('UTC')->subDays(5),
            'updated_at' => now('UTC')->subDays(5),
        ]);

        return $invoiceId;
    }
}