<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\Concerns\BuildsCheckoutRequestFixture;
use Tests\TestCase;

class PeriodClosingApiTest extends TestCase
{
    use BootsApiTenantUser;
    use BuildsCheckoutRequestFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_assistant_prompts_previous_month_closing_at_start_of_new_month(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'period-assistant@example.test');
        $tenant->forceFill(['plan' => 'P'])->save();
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->closeFixtureShift($tenant->id, $fixture['shift_id']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-02 09:00:00', 'Asia/Jakarta'));

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->getJson('/api/v1/lock-periods/assistant')
            ->assertOk()
            ->assertJsonPath('target_period.start_date', '2026-05-01')
            ->assertJsonPath('target_period.end_date', '2026-05-31')
            ->assertJsonPath('target_period.is_closed', false)
            ->assertJsonPath('target_period.days_after_period_end', 2)
            ->assertJsonPath('status.severity', 'review')
            ->assertJsonPath('status.restriction_mode', 'none')
            ->assertJsonPath('checklist.0.key', 'shift_cash')
            ->assertJsonPath('checklist.0.review_scope', 'Data yang dicek: shift yang masih open sampai akhir periode.')
            ->assertJsonPath('checklist.0.action_label', 'Buka ringkasan shift')
            ->assertJsonPath('checklist.4.correction_action', 'Kalau stok fisik salah, lakukan stock opname atau penyesuaian stok. Kalau HPP kosong, lengkapi pembelian/stok awal/biaya produksi produk tersebut.')
            ->assertJsonPath('checklist.6.key', 'close_period')
            ->assertJsonPath('checklist.6.action_label', 'Review & tutup buku');
    }

    public function test_close_period_locks_period_and_snapshots_carry_forward_balances(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'period-close@example.test');
        $tenant->forceFill(['plan' => 'P'])->save();
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        $this->closeFixtureShift($tenant->id, $fixture['shift_id']);
        $this->seedCashJournal($tenant->id, $user->id, $fixture['cash_account_id'], 750000);

        $response = $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/lock-periods/close', [
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'name' => 'Januari 2026',
                'reason' => 'Tutup buku akhir bulan',
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'closed')
            ->assertJsonPath('preview.checks.can_close', true)
            ->assertJsonPath('preview.carry_forward.cash_balance', 750000)
            ->assertJsonPath('preview.carry_forward.stock_qty', 100)
            ->assertJsonPath('preview.carry_forward.stock_value', 500000);

        $periodId = (string) $response->json('period_id');

        $this->assertDatabaseHas('lock_periods', [
            'id' => $periodId,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'is_locked' => true,
            'closed_by' => $user->id,
            'name' => 'Januari 2026',
        ]);

        $this->assertDatabaseHas('period_closing_balances', [
            'tenant_id' => $tenant->id,
            'lock_period_id' => $periodId,
            'balance_type' => 'cash',
            'reference_id' => $fixture['cash_account_id'],
            'amount' => 750000,
        ]);

        $this->assertDatabaseHas('period_closing_balances', [
            'tenant_id' => $tenant->id,
            'lock_period_id' => $periodId,
            'balance_type' => 'stock',
            'reference_id' => $fixture['product_id'],
            'quantity' => 100,
            'amount' => 500000,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'event_type' => 'period_closed',
            'reference_type' => 'lock_period',
            'reference_id' => $periodId,
        ]);
    }

    public function test_close_period_is_rejected_when_shift_is_still_open(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'period-close-open-shift@example.test');
        $tenant->forceFill(['plan' => 'P'])->save();
        $fixture = $this->seedCheckoutFixture($tenant->id, $user->id);
        DB::table('shifts')
            ->where('tenant_id', $tenant->id)
            ->where('id', $fixture['shift_id'])
            ->update(['opened_at' => CarbonImmutable::parse('2026-01-15 08:00:00', 'UTC')]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/lock-periods/close', [
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PERIOD_CLOSE_FAILED');
    }

    public function test_reopen_period_unlocks_period_and_audits_reason(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'period-reopen@example.test');
        $tenant->forceFill(['plan' => 'P'])->save();

        $periodId = (string) Str::uuid();
        DB::table('lock_periods')->insert([
            'id' => $periodId,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'is_locked' => true,
            'locked_by' => $user->id,
            'locked_at' => CarbonImmutable::now('UTC'),
            'reason' => 'Closed',
            'closed_by' => $user->id,
            'closed_at' => CarbonImmutable::now('UTC'),
        ]);

        $this->withHeaders($this->tenantHeaders($tenant->id))
            ->postJson('/api/v1/lock-periods/'.$periodId.'/reopen', [
                'reason' => 'Koreksi transaksi salah input',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'open');

        $this->assertDatabaseHas('lock_periods', [
            'id' => $periodId,
            'tenant_id' => $tenant->id,
            'is_locked' => false,
            'reopened_by' => $user->id,
            'reason' => 'Koreksi transaksi salah input',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'event_type' => 'period_reopened',
            'reference_id' => $periodId,
        ]);
    }

    private function closeFixtureShift(string $tenantId, string $shiftId): void
    {
        DB::table('shifts')
            ->where('tenant_id', $tenantId)
            ->where('id', $shiftId)
            ->update([
                'status' => 'closed',
                'closed_at' => CarbonImmutable::parse('2026-01-31 23:00:00', 'UTC'),
                'closing_cash' => 0,
            ]);
    }

    private function seedCashJournal(string $tenantId, string $userId, string $cashAccountId, float $amount): void
    {
        $journalEntryId = (string) Str::uuid();

        DB::table('journal_entries')->insert([
            'id' => $journalEntryId,
            'tenant_id' => $tenantId,
            'reference_type' => 'period_test_seed',
            'reference_id' => (string) Str::uuid(),
            'date' => '2026-01-15',
            'description' => 'Seed cash for period closing test',
            'status' => 'posted',
            'posted_at' => CarbonImmutable::now('UTC'),
            'created_by' => $userId,
            'created_at' => CarbonImmutable::now('UTC'),
        ]);

        DB::table('journal_items')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'journal_entry_id' => $journalEntryId,
            'account_id' => $cashAccountId,
            'debit' => $amount,
            'credit' => 0,
            'is_reversal' => false,
        ]);
    }
}
