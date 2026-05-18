<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantDefaultAccountingSetupService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashTransactionWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kalakasir-testing-views'.DIRECTORY_SEPARATOR.Str::uuid();
        File::ensureDirectoryExists($compiledPath);
        config(['view.compiled' => str_replace('\\', '/', $compiledPath)]);
        config([
            'platform.portals.pos_hosts' => ['pos.127.0.0.1.nip.io'],
            'platform.portals.backoffice_hosts' => ['office.127.0.0.1.nip.io'],
        ]);
        Auth::guard('web')->logout();
        TenantContext::clear();
    }

    public function test_cash_in_creates_posted_journal_and_audit_log(): void
    {
        [$tenant, $user] = $this->createWebActor('cashier', 'cash-in-cashier@example.test');
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $cashAccountId = (string) DB::table('account_mappings')->where('tenant_id', $tenant->id)->value('cash_account_id');
        $equityAccountId = (string) DB::table('chart_of_accounts')->where('tenant_id', $tenant->id)->where('code', '3101')->value('id');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->post('http://pos.127.0.0.1.nip.io/pos/cash-in', [
                'transaction_date' => '2026-04-18',
                'amount' => 250000,
                'account_id' => $cashAccountId,
                'category' => 'capital_injection',
                'description' => 'Setoran modal awal kas operasional',
                'reference' => 'INV-001',
            ]);

        $this->assertSame('/pos/cash-in', $this->redirectPath($response));

        $transaction = DB::table('cash_transactions')
            ->where('tenant_id', $tenant->id)
            ->where('direction', 'cash-in')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertSame('capital_injection', $transaction->category);
        $this->assertSame(250000.0, (float) $transaction->amount);
        $this->assertSame(0.0, (float) $transaction->balance_before);
        $this->assertSame(250000.0, (float) $transaction->balance_after);

        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'cash_transaction',
            'reference_id' => $transaction->id,
            'status' => 'posted',
        ]);

        $journalEntryId = DB::table('journal_entries')
            ->where('tenant_id', $tenant->id)
            ->where('reference_id', $transaction->id)
            ->value('id');

        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'journal_entry_id' => $journalEntryId,
            'account_id' => $cashAccountId,
            'debit' => 250000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_items', [
            'tenant_id' => $tenant->id,
            'journal_entry_id' => $journalEntryId,
            'account_id' => $equityAccountId,
            'debit' => 0,
            'credit' => 250000,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'cash_transaction',
            'reference_id' => $transaction->id,
            'event_type' => 'cash_in_created',
        ]);
    }

    public function test_cash_out_is_rejected_when_balance_is_insufficient(): void
    {
        [$tenant, $user] = $this->createWebActor('admin', 'cash-out-admin@example.test');
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $cashAccountId = (string) DB::table('account_mappings')->where('tenant_id', $tenant->id)->value('cash_account_id');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->post('http://pos.127.0.0.1.nip.io/pos/cash-out', [
                'transaction_date' => '2026-04-18',
                'amount' => 100000,
                'account_id' => $cashAccountId,
                'category' => 'operational_expense',
                'description' => 'Bayar operasional tunai',
            ]);

        $this->assertSame('/pos/cash-out', $this->redirectPath($response), 'status='.$response->getStatusCode().' headers='.json_encode($response->headers->all()));
        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseMissing('cash_transactions', [
            'tenant_id' => $tenant->id,
            'direction' => 'cash-out',
        ]);
    }

    public function test_cashier_cannot_exceed_cash_transaction_limit(): void
    {
        [$tenant, $user] = $this->createWebActor('cashier', 'cash-limit-cashier@example.test');
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $cashAccountId = (string) DB::table('account_mappings')->where('tenant_id', $tenant->id)->value('cash_account_id');

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->post('http://pos.127.0.0.1.nip.io/pos/cash-in', [
                'transaction_date' => '2026-04-18',
                'amount' => 1500000,
                'account_id' => $cashAccountId,
                'category' => 'other_income',
                'description' => 'Transaksi batas kasir',
            ]);

        $this->assertSame('/pos/cash-in', $this->redirectPath($response), 'status='.$response->getStatusCode().' headers='.json_encode($response->headers->all()));
        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseMissing('cash_transactions', [
            'tenant_id' => $tenant->id,
            'direction' => 'cash-in',
        ]);
    }

    public function test_cash_in_index_displays_detailed_transaction_history(): void
    {
        [$tenant, $user] = $this->createWebActor('admin', 'cash-history-admin@example.test');
        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $cashAccountId = (string) DB::table('account_mappings')->where('tenant_id', $tenant->id)->value('cash_account_id');

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->post('http://pos.127.0.0.1.nip.io/pos/cash-in', [
                'transaction_date' => '2026-04-18',
                'amount' => 350000,
                'account_id' => $cashAccountId,
                'category' => 'other_income',
                'description' => 'Penerimaan kas non operasional',
                'reference' => 'HIS-001',
            ]);

        $response = $this->actingAs($user)
            ->withSession(['auth_portal' => 'pos'])
            ->get('http://pos.127.0.0.1.nip.io/pos/cash-in');

        $response->assertOk();
        $response->assertSee('Riwayat lengkap');
        $response->assertSee('Penerimaan kas non operasional');
        $response->assertSee('Akun kas/bank');
        $response->assertSee('Akun lawan');
        $response->assertSee('Dicatat oleh');
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createWebActor(string $role, string $email, string $name = 'Portal User'): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant '.Str::upper(Str::random(4)),
            'timezone' => 'Asia/Jakarta',
            'inventory_valuation_method' => 'average',
            'plan' => 'P',
        ]);

        $userId = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $userId,
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('secret123'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
            'deleted_at' => null,
        ]);

        $user = User::withoutGlobalScopes()->findOrFail($userId);

        $roleId = (string) Str::uuid();

        DB::table('roles')->insert([
            'id' => $roleId,
            'tenant_id' => $tenant->id,
            'name' => $role,
        ]);

        DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $roleId,
        ]);

        return [$tenant, $user];
    }

    private function redirectPath($response): ?string
    {
        $location = $response->headers->get('Location');

        return $location ? parse_url((string) $location, PHP_URL_PATH) : null;
    }
}