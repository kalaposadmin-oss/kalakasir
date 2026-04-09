<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class RefundLegacyAuditCommandTest extends TestCase
{
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_refund_legacy_audit_command_reports_ambiguous_and_orphan_rows(): void
    {
        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Refund Audit',
            'timezone' => 'UTC',
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Audit User',
            'email' => 'refund-audit@example.test',
            'password' => 'secret',
        ]);

        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $saleId = (string) Str::uuid();
        $ambiguousRefundId = (string) Str::uuid();
        $orphanRefundId = (string) Str::uuid();
        $secondProductId = (string) Str::uuid();

        DB::table('products')->insert([
            'id' => $secondProductId,
            'tenant_id' => $tenant->id,
            'sku' => 'SKU-ORPHAN-001',
            'name' => 'Produk Orphan',
            'price' => 7000,
            'hpp_average' => 3000,
            'image_url' => null,
            'is_active' => true,
            'track_stock' => true,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 20000,
            'discount' => 0,
            'tax' => 0,
            'total' => 20000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'void_approved_by' => null,
            'void_reason' => null,
            'voided_at' => null,
            'created_at' => now('UTC'),
            'transaction_date' => now('UTC')->toDateString(),
        ]);

        DB::table('sale_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty' => 1,
                'price' => 10000,
                'discount' => 0,
                'hpp_snapshot' => 5000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'sale_id' => $saleId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'qty' => 1,
                'price' => 10000,
                'discount' => 0,
                'hpp_snapshot' => 5000,
            ],
        ]);

        DB::table('refunds')->insert([
            [
                'id' => $ambiguousRefundId,
                'tenant_id' => $tenant->id,
                'sale_id' => $saleId,
                'register_id' => $fixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'branch_id' => $fixture['branch_id'],
                'created_by' => $user->id,
                'subtotal' => 10000,
                'item_discount_total' => 0,
                'transaction_discount_total' => 0,
                'tax_total' => 0,
                'refunded_cost_total' => 0,
                'payout_total' => 10000,
                'total' => 10000,
                'status' => 'completed',
                'reason' => 'Ambiguous refund',
                'created_at' => now('UTC'),
            ],
            [
                'id' => $orphanRefundId,
                'tenant_id' => $tenant->id,
                'sale_id' => $saleId,
                'register_id' => $fixture['register_id'],
                'shift_id' => $fixture['shift_id'],
                'branch_id' => $fixture['branch_id'],
                'created_by' => $user->id,
                'subtotal' => 7000,
                'item_discount_total' => 0,
                'transaction_discount_total' => 0,
                'tax_total' => 0,
                'refunded_cost_total' => 0,
                'payout_total' => 7000,
                'total' => 7000,
                'status' => 'completed',
                'reason' => 'Orphan refund',
                'created_at' => now('UTC'),
            ],
        ]);

        DB::table('refund_items')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'refund_id' => $ambiguousRefundId,
                'sale_item_id' => null,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => null,
                'location_id' => $fixture['location_id'],
                'qty' => 1,
                'unit_price_snapshot' => 10000,
                'gross_amount' => 10000,
                'item_discount_amount' => 0,
                'transaction_discount_amount' => 0,
                'tax_amount' => 0,
                'net_amount' => 10000,
                'hpp_snapshot' => 5000,
                'refunded_cost_total' => 0,
                'amount' => 10000,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'refund_id' => $orphanRefundId,
                'sale_item_id' => null,
                'product_id' => $secondProductId,
                'product_variant_id' => null,
                'location_id' => $fixture['location_id'],
                'qty' => 1,
                'unit_price_snapshot' => 7000,
                'gross_amount' => 7000,
                'item_discount_amount' => 0,
                'transaction_discount_amount' => 0,
                'tax_amount' => 0,
                'net_amount' => 7000,
                'hpp_snapshot' => 3000,
                'refunded_cost_total' => 0,
                'amount' => 7000,
            ],
        ]);

        $this->artisan('refunds:audit-legacy-ambiguity', ['--tenant_id' => $tenant->id])
            ->expectsOutputToContain('legacy_missing_sale_item_id: 2')
            ->expectsOutputToContain('resolvable_single_match: 0')
            ->expectsOutputToContain('ambiguous_multi_match: 1')
            ->expectsOutputToContain('orphan_no_match: 1')
            ->expectsOutputToContain('Ambiguous samples')
            ->expectsOutputToContain('Orphan samples')
            ->assertExitCode(0);
    }

    public function test_refund_legacy_audit_command_shows_tenant_summary_without_scope(): void
    {
        [$tenant] = $this->seedResolvableLegacyRefundFixture();

        $this->artisan('refunds:audit-legacy-ambiguity')
            ->expectsOutputToContain('Tenant summary')
            ->expectsOutputToContain($tenant->id)
            ->assertExitCode(0);
    }

    public function test_refund_legacy_audit_can_export_csv(): void
    {
        [$tenant, , , , $refundItemId] = $this->seedResolvableLegacyRefundFixture();

        $path = storage_path('app/testing/refund-legacy-export.csv');
        File::ensureDirectoryExists(dirname($path));
        if (File::exists($path)) {
            File::delete($path);
        }

        $this->artisan('refunds:export-legacy-ambiguity', [
            '--tenant_id' => $tenant->id,
            '--path' => $path,
        ])->expectsOutputToContain('Legacy refund audit CSV exported: '.$path)
            ->expectsOutputToContain('summary_path: '.str_replace('.csv', '-summary.csv', $path))
            ->expectsOutputToContain('rows: 1')
            ->assertExitCode(0);

        $this->assertTrue(File::exists($path));
        $this->assertTrue(File::exists(str_replace('.csv', '-summary.csv', $path)));

        $csv = File::get($path);
        $this->assertStringContainsString('classification', $csv);
        $this->assertStringContainsString('resolvable_single_match', $csv);
        $this->assertStringContainsString($refundItemId, $csv);
    }

    public function test_refund_legacy_remediation_command_backfills_resolvable_rows(): void
    {
        [$tenant, , , , $refundItemId] = $this->seedResolvableLegacyRefundFixture();

        $this->artisan('refunds:remediate-legacy-sale-item', [
            '--tenant_id' => $tenant->id,
        ])->expectsOutputToContain('resolvable_rows: 1')
            ->expectsOutputToContain('limit: 1000')
            ->expectsOutputToContain('Dry run only.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('refund_items', [
            'tenant_id' => $tenant->id,
            'id' => $refundItemId,
            'sale_item_id' => null,
        ]);

        $this->artisan('refunds:remediate-legacy-sale-item', [
            '--tenant_id' => $tenant->id,
            '--confirm' => 'REMEDIATE_ACK',
        ])->expectsOutputToContain('Updated rows: 1')
            ->expectsOutputToContain('Skipped rows: 0')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('refund_items', [
            'tenant_id' => $tenant->id,
            'id' => $refundItemId,
            'sale_item_id' => null,
        ]);
    }

    private function seedResolvableLegacyRefundFixture(): array
    {
        $tenant = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tenant Refund Resolvable',
            'timezone' => 'UTC',
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Resolvable User',
            'email' => 'refund-resolvable@example.test',
            'password' => 'secret',
        ]);

        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $saleId = (string) Str::uuid();
        $refundId = (string) Str::uuid();
        $refundItemId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenant->id,
            'branch_id' => $fixture['branch_id'],
            'shift_id' => $fixture['shift_id'],
            'register_id' => $fixture['register_id'],
            'customer_id' => null,
            'subtotal' => 10000,
            'discount' => 0,
            'tax' => 0,
            'total' => 10000,
            'status' => 'completed',
            'idempotency_key' => (string) Str::uuid(),
            'created_by' => $user->id,
            'voided_by' => null,
            'void_approved_by' => null,
            'void_reason' => null,
            'voided_at' => null,
            'created_at' => now('UTC'),
            'transaction_date' => now('UTC')->toDateString(),
        ]);

        DB::table('sale_items')->insert([
            'id' => $saleItemId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 1,
            'price' => 10000,
            'discount' => 0,
            'hpp_snapshot' => 5000,
        ]);

        DB::table('stock_ledger')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'reference_item_id' => $saleItemId,
            'qty_in' => 0,
            'qty_out' => 1,
            'balance_after' => 99,
            'created_at' => now('UTC'),
            'created_by' => $user->id,
        ]);

        DB::table('refunds')->insert([
            'id' => $refundId,
            'tenant_id' => $tenant->id,
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'branch_id' => $fixture['branch_id'],
            'created_by' => $user->id,
            'subtotal' => 10000,
            'item_discount_total' => 0,
            'transaction_discount_total' => 0,
            'tax_total' => 0,
            'refunded_cost_total' => 0,
            'payout_total' => 10000,
            'total' => 10000,
            'status' => 'completed',
            'reason' => 'Resolvable refund',
            'created_at' => now('UTC'),
        ]);

        DB::table('refund_items')->insert([
            'id' => $refundItemId,
            'tenant_id' => $tenant->id,
            'refund_id' => $refundId,
            'sale_item_id' => null,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => null,
            'qty' => 1,
            'unit_price_snapshot' => 0,
            'gross_amount' => 0,
            'item_discount_amount' => 0,
            'transaction_discount_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => 0,
            'hpp_snapshot' => 0,
            'refunded_cost_total' => 0,
            'amount' => 10000,
        ]);

        return [$tenant, $user, $fixture, $saleId, $refundItemId];
    }
}