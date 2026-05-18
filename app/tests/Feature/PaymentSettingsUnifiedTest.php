<?php

namespace Tests\Feature;

use App\Models\AccountMapping;
use App\Models\ChartOfAccount;
use App\Models\PaymentSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantDefaultAccountingSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentSettingsUnifiedTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_saves_pos_receiving_accounts_and_customer_payment_instruction_from_one_form(): void
    {
        [$tenant, $user] = $this->createWebActor('owner', 'payment-settings-owner@example.test');

        app(TenantDefaultAccountingSetupService::class)->ensure($tenant->id, $user->id);

        $cashAccount = ChartOfAccount::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', '1101')
            ->firstOrFail();
        $bankAccount = ChartOfAccount::query()
            ->where('tenant_id', $tenant->id)
            ->where('code', '1102')
            ->firstOrFail();

        $this->actingAs($user)
            ->withSession(['auth_portal' => 'backoffice'])
            ->post('/backoffice/settings/payment-settings', [
                'cash_account_id' => $cashAccount->id,
                'bank_account_id' => $bankAccount->id,
                'bank_name' => 'BCA',
                'bank_account_number' => '1234567890',
                'bank_account_name' => 'Kala Roti',
                'admin_whatsapp_number' => '628123456789',
                'qris_image_url' => 'https://example.test/qris.png',
                'manager_pin' => '1234',
                'payment_note' => 'Kirim bukti transfer ke admin.',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $mapping = AccountMapping::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame((string) $cashAccount->id, (string) $mapping->cash_account_id);
        $this->assertSame((string) $bankAccount->id, (string) $mapping->bank_account_id);

        $setting = PaymentSetting::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('BCA', $setting->bank_name);
        $this->assertSame('1234567890', $setting->bank_account_number);
        $this->assertSame('Kala Roti', $setting->bank_account_name);
        $this->assertSame('https://example.test/qris.png', $setting->qris_image_url);
        $this->assertTrue((bool) $setting->is_active);
        $this->assertTrue(Hash::check('1234', $user->fresh()->manager_pin_hash));
    }

    private function createWebActor(string $role, string $email, string $name = 'Payment Owner'): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant '.Str::upper(Str::random(4)),
            'timezone' => 'Asia/Jakarta',
            'inventory_valuation_method' => 'average',
            'plan' => 'P',
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => $email,
            'password' => 'secret123',
            'failed_login_attempts' => 0,
        ]);

        $roleId = (string) Str::uuid();

        \DB::table('roles')->insert([
            'id' => $roleId,
            'tenant_id' => $tenant->id,
            'name' => $role,
        ]);

        \DB::table('user_roles')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $roleId,
        ]);

        return [$tenant, $user];
    }
}
