<?php

namespace Tests\Feature;

use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class OpeningBalanceEngineTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        $this->resetApiClock();

        parent::tearDown();
    }

    public function test_opening_balance_happy_path_posts_stock_fund_journal_and_lock_period(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-opening-balance@example.test');
        $this->actingAs($user);
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $expectedInventoryLayerCount = $tenant->inventory_valuation_method === 'fifo' ? 1 : 0;

        $openingDate = CarbonImmutable::now('UTC')->toDateString();
        $draftResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/admin/opening-balance', [
                'opening_date' => $openingDate,
                'meta' => [
                    'source' => 'feature-test',
                ],
            ]);

        $draftResponse->assertCreated()
            ->assertJsonPath('session.tenant_id', $tenant->id)
            ->assertJsonPath('session.status', 'draft')
            ->assertJsonPath('session.opening_date', $openingDate);

        $sessionId = (string) $draftResponse->json('session.id');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/admin/opening-balance/'.$sessionId.'/stock', [
                'location_id' => $fixture['location_id'],
                'product_id' => $fixture['product_id'],
                'qty' => 10,
                'unit_cost' => 7000,
                'reference_note' => 'stok awal feature test',
            ])
            ->assertCreated()
            ->assertJsonPath('line.qty', 10)
            ->assertJsonPath('line.unit_cost', 7000);

        $primaryStockLineId = (string) DB::table('opening_stock_lines')
            ->where('tenant_id', $tenant->id)
            ->where('opening_session_id', $sessionId)
            ->orderBy('created_at')
            ->value('id');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/admin/opening-balance/'.$sessionId.'/stock/'.$primaryStockLineId, [
                'location_id' => $fixture['location_id'],
                'product_id' => $fixture['product_id'],
                'qty' => 10,
                'unit_cost' => 7000,
                'reference_note' => 'stok utama diperbarui',
            ])
            ->assertOk()
            ->assertJsonPath('line.id', $primaryStockLineId)
            ->assertJsonPath('line.reference_note', 'stok utama diperbarui');

        $secondStockLineResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/admin/opening-balance/'.$sessionId.'/stock', [
                'location_id' => $fixture['location_id'],
                'product_id' => $fixture['product_id'],
                'qty' => 2,
                'unit_cost' => 7000,
                'reference_note' => 'stok tambahan sementara',
            ]);

        $secondStockLineResponse->assertCreated();
        $secondStockLineId = (string) $secondStockLineResponse->json('line.id');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->deleteJson('/admin/opening-balance/'.$sessionId.'/stock/'.$secondStockLineId)
            ->assertOk()
            ->assertJsonPath('status', 'deleted')
            ->assertJsonPath('line_id', $secondStockLineId);

        $this->assertDatabaseMissing('opening_stock_lines', [
            'tenant_id' => $tenant->id,
            'opening_session_id' => $sessionId,
            'id' => $secondStockLineId,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/admin/opening-balance/'.$sessionId.'/fund', [
                'account_id' => $fixture['cash_account_id'],
                'source_type' => 'cash',
                'amount' => 70000,
                'reference_note' => 'kas awal feature test',
            ])
            ->assertCreated()
            ->assertJsonPath('line.amount', 70000);

        $primaryFundLineId = (string) DB::table('opening_fund_lines')
            ->where('tenant_id', $tenant->id)
            ->where('opening_session_id', $sessionId)
            ->where('source_type', 'cash')
            ->orderBy('created_at')
            ->value('id');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/admin/opening-balance/'.$sessionId.'/fund/'.$primaryFundLineId, [
                'account_id' => $fixture['cash_account_id'],
                'source_type' => 'cash',
                'amount' => 70000,
                'reference_note' => 'kas utama diperbarui',
            ])
            ->assertOk()
            ->assertJsonPath('line.id', $primaryFundLineId)
            ->assertJsonPath('line.reference_note', 'kas utama diperbarui');

        $secondFundLineResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/admin/opening-balance/'.$sessionId.'/fund', [
                'account_id' => $fixture['cash_account_id'],
                'source_type' => 'bank',
                'amount' => 10000,
                'reference_note' => 'dana tambahan sementara',
            ]);

        $secondFundLineResponse->assertCreated();
        $secondFundLineId = (string) $secondFundLineResponse->json('line.id');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->deleteJson('/admin/opening-balance/'.$sessionId.'/fund/'.$secondFundLineId)
            ->assertOk()
            ->assertJsonPath('status', 'deleted')
            ->assertJsonPath('line_id', $secondFundLineId);

        $this->assertDatabaseMissing('opening_fund_lines', [
            'tenant_id' => $tenant->id,
            'opening_session_id' => $sessionId,
            'id' => $secondFundLineId,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/admin/opening-balance/current')
            ->assertOk()
            ->assertJsonPath('session.id', $sessionId)
            ->assertJsonPath('session.status', 'draft')
            ->assertJsonPath('session.stock_lines.0.qty', 10)
            ->assertJsonPath('session.stock_lines.0.line_value', 70000)
            ->assertJsonPath('session.fund_lines.0.amount', 70000);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/admin/opening-balance/validate', [
                'session_id' => $sessionId,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'valid')
            ->assertJsonPath('totals.inventory_total', 70000)
            ->assertJsonPath('totals.fund_total', 70000)
            ->assertJsonPath('totals.total_debit', 140000)
            ->assertJsonPath('totals.equity_total', 140000);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/admin/opening-balance/post', [
                'session_id' => $sessionId,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'posted')
            ->assertJsonPath('session.status', 'posted');

        $this->assertDatabaseHas('opening_sessions', [
            'id' => $sessionId,
            'tenant_id' => $tenant->id,
            'status' => 'posted',
        ]);

        $this->assertDatabaseHas('opening_posting_logs', [
            'opening_session_id' => $sessionId,
            'tenant_id' => $tenant->id,
            'stock_ledger_count' => 1,
            'inventory_layer_count' => $expectedInventoryLayerCount,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'opening_balance',
            'reference_id' => $sessionId,
            'status' => 'posted',
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/admin/opening-balance/history')
            ->assertOk()
            ->assertJsonPath('history.0.id', $sessionId)
            ->assertJsonPath('history.0.status', 'posted')
            ->assertJsonPath('history.0.stock_count', 1)
            ->assertJsonPath('history.0.stock_total', 70000)
            ->assertJsonPath('history.0.fund_count', 1)
            ->assertJsonPath('history.0.fund_total', 70000)
            ->assertJsonPath('history.0.equity_total', 140000)
            ->assertJsonPath('history.0.funds.0.account_id', $fixture['cash_account_id'])
            ->assertJsonPath('history.0.funds.0.source_type', 'cash')
            ->assertJsonPath('history.0.funds.0.amount', 70000);

        $this->assertDatabaseHas('stock_ledger', [
            'tenant_id' => $tenant->id,
            'product_id' => $fixture['product_id'],
            'location_id' => $fixture['location_id'],
            'reference_type' => 'opening_balance',
            'reference_id' => $sessionId,
            'qty_in' => 10,
            'qty_out' => 0,
        ]);

        $this->assertSame(110.0, (float) DB::table('inventory_balances')
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $fixture['product_id'])
            ->where('location_id', $fixture['location_id'])
            ->value('qty_on_hand'));

        $this->assertDatabaseHas('lock_periods', [
            'tenant_id' => $tenant->id,
            'start_date' => $openingDate,
            'end_date' => $openingDate,
            'is_locked' => true,
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/admin/opening-balance/'.$sessionId.'/stock', [
                'location_id' => $fixture['location_id'],
                'product_id' => $fixture['product_id'],
                'qty' => 1,
                'unit_cost' => 7000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'OPENING_BALANCE_STOCK_LINE_FAILED')
            ->assertJsonPath('message', 'Session opening balance tidak dapat diubah karena sudah diposting.');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->patchJson('/admin/opening-balance/'.$sessionId.'/stock/'.$primaryStockLineId, [
                'location_id' => $fixture['location_id'],
                'product_id' => $fixture['product_id'],
                'qty' => 11,
                'unit_cost' => 7000,
                'reference_note' => 'tidak boleh update setelah post',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'OPENING_BALANCE_STOCK_LINE_UPDATE_FAILED')
            ->assertJsonPath('message', 'Session opening balance tidak dapat diubah karena sudah diposting.');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->deleteJson('/admin/opening-balance/'.$sessionId.'/stock/'.(string) DB::table('opening_stock_lines')
                ->where('tenant_id', $tenant->id)
                ->where('opening_session_id', $sessionId)
                ->value('id'))
            ->assertStatus(422)
            ->assertJsonPath('code', 'OPENING_BALANCE_STOCK_LINE_DELETE_FAILED')
            ->assertJsonPath('message', 'Session opening balance tidak dapat diubah karena sudah diposting.');
    }

    public function test_opening_balance_rejects_zero_unit_cost_and_imbalance(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin-opening-balance-invalid@example.test');
        $this->actingAs($user);
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);

        $openingDate = CarbonImmutable::now('UTC')->toDateString();
        $draftResponse = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/admin/opening-balance', [
                'opening_date' => $openingDate,
                'equity_amount' => 50000,
            ]);

        $draftResponse->assertCreated();
        $sessionId = (string) $draftResponse->json('session.id');

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/admin/opening-balance/'.$sessionId.'/stock', [
                'location_id' => $fixture['location_id'],
                'product_id' => $fixture['product_id'],
                'qty' => 1,
                'unit_cost' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit_cost']);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/admin/opening-balance/'.$sessionId.'/stock', [
                'location_id' => $fixture['location_id'],
                'product_id' => $fixture['product_id'],
                'qty' => 10,
                'unit_cost' => 7000,
            ])
            ->assertCreated();

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/admin/opening-balance/validate', [
                'session_id' => $sessionId,
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'invalid')
            ->assertJsonPath('code', 'OPENING_BALANCE_INVALID')
            ->assertJsonPath('message', 'Opening balance tidak seimbang: total debit tidak sama dengan total credit.');
    }
}
