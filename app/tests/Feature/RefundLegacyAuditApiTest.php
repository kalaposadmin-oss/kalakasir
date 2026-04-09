<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class RefundLegacyAuditApiTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    public function test_refund_legacy_audit_api_returns_summary_and_rows_for_admin(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-refund-legacy-audit@example.test', 'Admin Refund Audit');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedLegacyRefundRow($tenant->id, $user->id, $fixture);

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/settings/refund-legacy-audit')
            ->assertOk()
            ->assertJsonPath('summary.legacy_missing_sale_item_id', 1)
            ->assertJsonPath('summary.resolvable_single_match', 1)
            ->assertJsonPath('rows.0.branch_id', $fixture['branch_id'])
            ->assertJsonPath('rows.0.classification', 'resolvable_single_match');
    }

    public function test_refund_legacy_audit_api_can_filter_by_branch_and_date(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-refund-legacy-audit-filter@example.test', 'Admin Refund Audit Filter');
        $firstFixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $secondFixture = $this->seedSecondaryBranchFixture($tenant->id, $user->id, $firstFixture);

        $this->seedLegacyRefundRow($tenant->id, $user->id, $firstFixture, [
            'refund_created_at' => CarbonImmutable::parse('2026-04-01 10:00:00', 'UTC'),
        ]);
        $this->seedLegacyRefundRow($tenant->id, $user->id, $secondFixture, [
            'refund_created_at' => CarbonImmutable::parse('2026-04-03 10:00:00', 'UTC'),
        ]);

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson(sprintf(
                '/api/v1/settings/refund-legacy-audit?branch_id=%s&date_from=2026-04-03&date_to=2026-04-03',
                $secondFixture['branch_id']
            ))
            ->assertOk()
            ->assertJsonPath('summary.legacy_missing_sale_item_id', 1)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.branch_id', $secondFixture['branch_id'])
            ->assertJsonPath('rows.0.classification', 'resolvable_single_match');
    }

    public function test_refund_legacy_audit_api_paginates_rows(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-refund-legacy-pagination@example.test', 'Admin Refund Pagination');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $first = $this->seedLegacyRefundRow($tenant->id, $user->id, $fixture, [
            'refund_created_at' => CarbonImmutable::parse('2026-04-01 08:00:00', 'UTC'),
        ]);
        $second = $this->seedLegacyRefundRow($tenant->id, $user->id, $fixture, [
            'refund_created_at' => CarbonImmutable::parse('2026-04-02 08:00:00', 'UTC'),
        ]);

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/settings/refund-legacy-audit?page=2&per_page=1')
            ->assertOk()
            ->assertJsonPath('page', 2)
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('rows.0.refund_id', $second['refund_id']);
    }

    public function test_refund_legacy_audit_export_returns_csv_for_owner(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner-refund-legacy-audit@example.test', 'Owner Refund Audit');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->seedLegacyRefundRow($tenant->id, $user->id, $fixture);

        $response = $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->get('/api/v1/settings/refund-legacy-audit/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('classification', $response->streamedContent());
        $this->assertStringContainsString('branch_id', $response->streamedContent());
    }

    public function test_refund_legacy_audit_remediation_preview_and_execute_updates_resolvable_row(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-refund-legacy-remediate@example.test', 'Admin Refund Remediate');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $legacy = $this->seedResolvableLegacyRefundRow($tenant->id, $user->id, $fixture);

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/settings/refund-legacy-audit/remediate', [
                'refund_item_ids' => [$legacy['refund_item_id']],
                'dry_run' => true,
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'dry_run')
            ->assertJsonPath('matched_candidates', 1)
            ->assertJsonPath('matched_resolvable', 1)
            ->assertJsonPath('updated', 0)
            ->assertJsonPath('preview.0.refund_item_id', $legacy['refund_item_id']);

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/settings/refund-legacy-audit/remediate', [
                'refund_item_ids' => [$legacy['refund_item_id']],
                'dry_run' => false,
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'execute')
            ->assertJsonPath('matched_resolvable', 1)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('skipped', 0)
            ->assertJsonPath('updated_rows.0.refund_item_id', $legacy['refund_item_id']);

        $this->assertDatabaseMissing('refund_items', [
            'tenant_id' => $tenant->id,
            'id' => $legacy['refund_item_id'],
            'sale_item_id' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'event_type' => 'refund_legacy_remediated',
            'reference_type' => 'refund_item',
            'reference_id' => $legacy['refund_item_id'],
        ]);
    }

    public function test_refund_legacy_audit_bulk_execute_writes_audit_logs_per_updated_row(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner-refund-legacy-bulk@example.test', 'Owner Refund Bulk');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $first = $this->seedResolvableLegacyRefundRow($tenant->id, $user->id, $fixture);
        $second = $this->seedResolvableLegacyRefundRow($tenant->id, $user->id, $fixture);

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/settings/refund-legacy-audit/remediate', [
                'refund_item_ids' => [$first['refund_item_id'], $second['refund_item_id']],
                'dry_run' => false,
            ])
            ->assertOk()
            ->assertJsonPath('updated', 2);

        $this->assertSame(2, DB::table('audit_logs')
            ->where('tenant_id', $tenant->id)
            ->where('actor_id', $user->id)
            ->where('event_type', 'refund_legacy_remediated')
            ->count());
    }

    public function test_refund_legacy_audit_history_returns_remediation_entries(): void
    {
        [$tenant, $user] = $this->bootApiActor('owner', 'owner-refund-legacy-history@example.test', 'Owner Refund History');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $legacy = $this->seedResolvableLegacyRefundRow($tenant->id, $user->id, $fixture);

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/settings/refund-legacy-audit/remediate', [
                'refund_item_ids' => [$legacy['refund_item_id']],
                'dry_run' => false,
            ])
            ->assertOk();

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/settings/refund-legacy-audit/history?page=1&per_page=5')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('entries.0.actor_id', $user->id)
            ->assertJsonPath('entries.0.reference_id', $legacy['refund_item_id'])
            ->assertJsonPath('entries.0.payload_json.updated', 1);
    }

    public function test_refund_legacy_audit_history_can_filter_and_export_csv(): void
    {
        [$tenant, $owner] = $this->bootApiActor('owner', 'owner-refund-legacy-history-filter@example.test', 'Owner Refund History Filter');
        $fixture = $this->seedCheckoutFixture($tenant->id, $owner->id);
        $legacy = $this->seedResolvableLegacyRefundRow($tenant->id, $owner->id, $fixture);

        $this->actingAs($owner)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/settings/refund-legacy-audit/remediate', [
                'refund_item_ids' => [$legacy['refund_item_id']],
                'dry_run' => false,
            ])
            ->assertOk();

        $historyResponse = $this->actingAs($owner)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/settings/refund-legacy-audit/history')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $createdAt = (string) $historyResponse->json('entries.0.created_at');
        $historyDate = substr($createdAt, 0, 10);

        $this->actingAs($owner)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson(sprintf('/api/v1/settings/refund-legacy-audit/history?actor_id=%s&date_from=%s&date_to=%s', $owner->id, $historyDate, $historyDate))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('entries.0.actor_id', $owner->id);

        $response = $this->actingAs($owner)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->get(sprintf('/api/v1/settings/refund-legacy-audit/history/export?actor_id=%s&date_from=%s&date_to=%s', $owner->id, $historyDate, $historyDate));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('actor_id', $response->streamedContent());
        $this->assertStringContainsString($legacy['refund_item_id'], $response->streamedContent());
    }

    public function test_refund_legacy_audit_remediation_skips_ambiguous_candidates(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-refund-legacy-ambiguous@example.test', 'Admin Refund Ambiguous');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $legacy = $this->seedAmbiguousLegacyRefundRow($tenant->id, $user->id, $fixture);

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/settings/refund-legacy-audit/remediate', [
                'refund_item_ids' => [$legacy['refund_item_id']],
                'dry_run' => true,
            ])
            ->assertOk()
            ->assertJsonPath('matched_candidates', 1)
            ->assertJsonPath('matched_resolvable', 0)
            ->assertJsonPath('skipped_candidates', 1)
            ->assertJsonCount(0, 'preview');

            $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_refund_legacy_audit_remediation_matches_sale_item_by_variant_when_product_is_duplicated(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-refund-legacy-variant@example.test', 'Admin Refund Variant');
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $legacy = $this->seedVariantScopedResolvableLegacyRefundRow($tenant->id, $user->id, $fixture);

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/settings/refund-legacy-audit')
            ->assertOk()
            ->assertJsonPath('summary.resolvable_single_match', 1)
            ->assertJsonPath('summary.ambiguous_multi_match', 0)
            ->assertJsonPath('rows.0.classification', 'resolvable_single_match')
            ->assertJsonPath('rows.0.sale_item_match_count', 1);

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/settings/refund-legacy-audit/remediate', [
                'refund_item_ids' => [$legacy['refund_item_id']],
                'dry_run' => false,
            ])
            ->assertOk()
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('updated_rows.0.refund_item_id', $legacy['refund_item_id']);

        $this->assertDatabaseHas('refund_items', [
            'tenant_id' => $tenant->id,
            'id' => $legacy['refund_item_id'],
            'sale_item_id' => $legacy['expected_sale_item_id'],
            'product_variant_id' => $legacy['variant_id'],
        ]);
    }

    public function test_refund_legacy_audit_api_rejects_cashier(): void
    {
        [$tenant, $user] = $this->bootApiActor('cashier', 'cashier-refund-legacy-audit@example.test', 'Cashier Refund Audit');

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/settings/refund-legacy-audit')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/settings/refund-legacy-audit/remediate', [
                'dry_run' => true,
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/settings/refund-legacy-audit/history')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->get('/api/v1/settings/refund-legacy-audit/history/export')
            ->assertStatus(403);
    }

    private function seedLegacyRefundRow(string $tenantId, string $userId, array $fixture, array $overrides = []): array
    {
        $saleId = (string) Str::uuid();
        $refundId = (string) Str::uuid();
        $refundItemId = (string) Str::uuid();
        $saleCreatedAt = $overrides['sale_created_at'] ?? CarbonImmutable::now('UTC');
        $refundCreatedAt = $overrides['refund_created_at'] ?? CarbonImmutable::now('UTC');

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenantId,
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
            'created_by' => $userId,
            'voided_by' => null,
            'void_approved_by' => null,
            'void_reason' => null,
            'voided_at' => null,
            'created_at' => $saleCreatedAt,
            'transaction_date' => $saleCreatedAt->toDateString(),
        ]);

        DB::table('sale_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'sale_id' => $saleId,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'qty' => 1,
            'price' => 10000,
            'discount' => 0,
            'hpp_snapshot' => 5000,
        ]);

        DB::table('refunds')->insert([
            'id' => $refundId,
            'tenant_id' => $tenantId,
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'branch_id' => $fixture['branch_id'],
            'created_by' => $userId,
            'subtotal' => 10000,
            'item_discount_total' => 0,
            'transaction_discount_total' => 0,
            'tax_total' => 0,
            'refunded_cost_total' => 0,
            'payout_total' => 10000,
            'total' => 10000,
            'status' => 'completed',
            'reason' => 'Legacy audit api',
            'created_at' => $refundCreatedAt,
        ]);

        DB::table('refund_items')->insert([
            'id' => $refundItemId,
            'tenant_id' => $tenantId,
            'refund_id' => $refundId,
            'sale_item_id' => null,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
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

        return [
            'sale_id' => $saleId,
            'refund_id' => $refundId,
            'refund_item_id' => $refundItemId,
        ];
    }

    private function seedSecondaryBranchFixture(string $tenantId, string $userId, array $baseFixture): array
    {
        $branchId = (string) Str::uuid();
        DB::table('branches')->insert([
            'id' => $branchId,
            'tenant_id' => $tenantId,
            'name' => 'Secondary Branch',
        ]);

        $shiftId = (string) Str::uuid();
        DB::table('shifts')->insert([
            'id' => $shiftId,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'opened_at' => CarbonImmutable::now('UTC')->subHour(),
            'closed_at' => null,
            'opening_cash' => 0,
            'closing_cash' => 0,
            'system_cash_total' => 0,
            'difference' => 0,
            'status' => 'open',
        ]);

        $locationId = (string) Str::uuid();
        DB::table('locations')->insert([
            'id' => $locationId,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'name' => 'Secondary Warehouse',
            'is_default' => false,
        ]);

        $registerId = (string) Str::uuid();
        DB::table('registers')->insert([
            'id' => $registerId,
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'location_id' => $locationId,
            'name' => 'Register B',
            'device_uid' => 'fixture-'.substr(str_replace('-', '', $registerId), 0, 12),
            'status' => 'active',
            'created_at' => CarbonImmutable::now('UTC'),
            'updated_at' => CarbonImmutable::now('UTC'),
        ]);

        return [
            'branch_id' => $branchId,
            'shift_id' => $shiftId,
            'register_id' => $registerId,
            'location_id' => $locationId,
            'product_id' => $baseFixture['product_id'],
            'cash_account_id' => $baseFixture['cash_account_id'],
            'payment_method_id' => $baseFixture['payment_method_id'],
        ];
    }

    private function seedResolvableLegacyRefundRow(string $tenantId, string $userId, array $fixture): array
    {
        $saleId = (string) Str::uuid();
        $saleItemId = (string) Str::uuid();
        $refundId = (string) Str::uuid();
        $refundItemId = (string) Str::uuid();

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenantId,
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
            'created_by' => $userId,
            'voided_by' => null,
            'void_approved_by' => null,
            'void_reason' => null,
            'voided_at' => null,
            'created_at' => now('UTC'),
            'transaction_date' => now('UTC')->toDateString(),
        ]);

        DB::table('sale_items')->insert([
            'id' => $saleItemId,
            'tenant_id' => $tenantId,
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
            'tenant_id' => $tenantId,
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
            'created_by' => $userId,
        ]);

        DB::table('refunds')->insert([
            'id' => $refundId,
            'tenant_id' => $tenantId,
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'branch_id' => $fixture['branch_id'],
            'created_by' => $userId,
            'subtotal' => 10000,
            'item_discount_total' => 0,
            'transaction_discount_total' => 0,
            'tax_total' => 0,
            'refunded_cost_total' => 0,
            'payout_total' => 10000,
            'total' => 10000,
            'status' => 'completed',
            'reason' => 'Resolvable refund api remediation',
            'created_at' => now('UTC'),
        ]);

        DB::table('refund_items')->insert([
            'id' => $refundItemId,
            'tenant_id' => $tenantId,
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

        return [
            'sale_id' => $saleId,
            'sale_item_id' => $saleItemId,
            'refund_id' => $refundId,
            'refund_item_id' => $refundItemId,
        ];
    }

    private function seedAmbiguousLegacyRefundRow(string $tenantId, string $userId, array $fixture): array
    {
        $saleId = (string) Str::uuid();
        $refundId = (string) Str::uuid();
        $refundItemId = (string) Str::uuid();

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenantId,
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
            'created_by' => $userId,
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
                'tenant_id' => $tenantId,
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
                'tenant_id' => $tenantId,
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
            'id' => $refundId,
            'tenant_id' => $tenantId,
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'branch_id' => $fixture['branch_id'],
            'created_by' => $userId,
            'subtotal' => 10000,
            'item_discount_total' => 0,
            'transaction_discount_total' => 0,
            'tax_total' => 0,
            'refunded_cost_total' => 0,
            'payout_total' => 10000,
            'total' => 10000,
            'status' => 'completed',
            'reason' => 'Ambiguous refund api remediation',
            'created_at' => now('UTC'),
        ]);

        DB::table('refund_items')->insert([
            'id' => $refundItemId,
            'tenant_id' => $tenantId,
            'refund_id' => $refundId,
            'sale_item_id' => null,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => null,
            'location_id' => $fixture['location_id'],
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

        return [
            'sale_id' => $saleId,
            'refund_id' => $refundId,
            'refund_item_id' => $refundItemId,
        ];
    }

    private function seedVariantScopedResolvableLegacyRefundRow(string $tenantId, string $userId, array $fixture): array
    {
        $saleId = (string) Str::uuid();
        $firstVariantId = (string) Str::uuid();
        $secondVariantId = (string) Str::uuid();
        $firstSaleItemId = (string) Str::uuid();
        $secondSaleItemId = (string) Str::uuid();
        $refundId = (string) Str::uuid();
        $refundItemId = (string) Str::uuid();

        DB::table('product_variants')->insert([
            [
                'id' => $firstVariantId,
                'tenant_id' => $tenantId,
                'product_id' => $fixture['product_id'],
                'sku' => 'VARIANT-A',
                'barcode' => null,
                'name' => 'Variant A',
                'attributes' => json_encode(['label' => 'A']),
                'price_override' => null,
                'is_active' => true,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ],
            [
                'id' => $secondVariantId,
                'tenant_id' => $tenantId,
                'product_id' => $fixture['product_id'],
                'sku' => 'VARIANT-B',
                'barcode' => null,
                'name' => 'Variant B',
                'attributes' => json_encode(['label' => 'B']),
                'price_override' => null,
                'is_active' => true,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ],
        ]);

        DB::table('sales')->insert([
            'id' => $saleId,
            'tenant_id' => $tenantId,
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
            'created_by' => $userId,
            'voided_by' => null,
            'void_approved_by' => null,
            'void_reason' => null,
            'voided_at' => null,
            'created_at' => now('UTC'),
            'transaction_date' => now('UTC')->toDateString(),
        ]);

        DB::table('sale_items')->insert([
            [
                'id' => $firstSaleItemId,
                'tenant_id' => $tenantId,
                'sale_id' => $saleId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => $firstVariantId,
                'qty' => 1,
                'price' => 10000,
                'discount' => 0,
                'hpp_snapshot' => 5000,
            ],
            [
                'id' => $secondSaleItemId,
                'tenant_id' => $tenantId,
                'sale_id' => $saleId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => $secondVariantId,
                'qty' => 1,
                'price' => 10000,
                'discount' => 0,
                'hpp_snapshot' => 5000,
            ],
        ]);

        DB::table('stock_ledger')->insert([
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => $firstVariantId,
                'location_id' => $fixture['location_id'],
                'reference_type' => 'sale',
                'reference_id' => $saleId,
                'reference_item_id' => $firstSaleItemId,
                'qty_in' => 0,
                'qty_out' => 1,
                'balance_after' => 99,
                'created_at' => now('UTC'),
                'created_by' => $userId,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'product_id' => $fixture['product_id'],
                'product_variant_id' => $secondVariantId,
                'location_id' => $fixture['location_id'],
                'reference_type' => 'sale',
                'reference_id' => $saleId,
                'reference_item_id' => $secondSaleItemId,
                'qty_in' => 0,
                'qty_out' => 1,
                'balance_after' => 98,
                'created_at' => now('UTC'),
                'created_by' => $userId,
            ],
        ]);

        DB::table('refunds')->insert([
            'id' => $refundId,
            'tenant_id' => $tenantId,
            'sale_id' => $saleId,
            'register_id' => $fixture['register_id'],
            'shift_id' => $fixture['shift_id'],
            'branch_id' => $fixture['branch_id'],
            'created_by' => $userId,
            'subtotal' => 10000,
            'item_discount_total' => 0,
            'transaction_discount_total' => 0,
            'tax_total' => 0,
            'refunded_cost_total' => 0,
            'payout_total' => 10000,
            'total' => 10000,
            'status' => 'completed',
            'reason' => 'Variant scoped refund legacy remediation',
            'created_at' => now('UTC'),
        ]);

        DB::table('refund_items')->insert([
            'id' => $refundItemId,
            'tenant_id' => $tenantId,
            'refund_id' => $refundId,
            'sale_item_id' => null,
            'product_id' => $fixture['product_id'],
            'product_variant_id' => $secondVariantId,
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

        return [
            'refund_item_id' => $refundItemId,
            'expected_sale_item_id' => $secondSaleItemId,
            'variant_id' => $secondVariantId,
        ];
    }
}