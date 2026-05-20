<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebAuthOnboardingSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_config_infers_shared_cookie_domain_from_split_portal_hosts(): void
    {
        $previousAppUrl = getenv('APP_URL');
        $previousPos = getenv('POS_PORTAL_HOSTS');
        $previousBackoffice = getenv('BACKOFFICE_PORTAL_HOSTS');
        $previousSessionDomain = getenv('SESSION_DOMAIN');

        putenv('APP_URL=http://pos.127.0.0.1.nip.io:8000');
        putenv('POS_PORTAL_HOSTS=pos.127.0.0.1.nip.io');
        putenv('BACKOFFICE_PORTAL_HOSTS=admin.127.0.0.1.nip.io');
        putenv('SESSION_DOMAIN');
        unset($_ENV['APP_URL'], $_ENV['POS_PORTAL_HOSTS'], $_ENV['BACKOFFICE_PORTAL_HOSTS'], $_ENV['SESSION_DOMAIN']);
        $_ENV['APP_URL'] = 'http://pos.127.0.0.1.nip.io:8000';
        $_ENV['POS_PORTAL_HOSTS'] = 'pos.127.0.0.1.nip.io';
        $_ENV['BACKOFFICE_PORTAL_HOSTS'] = 'admin.127.0.0.1.nip.io';
        Env::getRepository()->set('APP_URL', 'http://pos.127.0.0.1.nip.io:8000');
        Env::getRepository()->set('POS_PORTAL_HOSTS', 'pos.127.0.0.1.nip.io');
        Env::getRepository()->set('BACKOFFICE_PORTAL_HOSTS', 'admin.127.0.0.1.nip.io');
        Env::getRepository()->clear('SESSION_DOMAIN');

        $config = require config_path('session.php');

        $this->assertSame('.127.0.0.1.nip.io', $config['domain']);

        $this->restoreEnvValue('APP_URL', $previousAppUrl);
        $this->restoreEnvValue('POS_PORTAL_HOSTS', $previousPos);
        $this->restoreEnvValue('BACKOFFICE_PORTAL_HOSTS', $previousBackoffice);
        $this->restoreEnvValue('SESSION_DOMAIN', $previousSessionDomain);
    }

    public function test_session_config_does_not_infer_shared_cookie_domain_for_local_app_host(): void
    {
        $previousAppUrl = getenv('APP_URL');
        $previousPos = getenv('POS_PORTAL_HOSTS');
        $previousBackoffice = getenv('BACKOFFICE_PORTAL_HOSTS');
        $previousSessionDomain = getenv('SESSION_DOMAIN');

        putenv('APP_URL=http://127.0.0.1:8000');
        putenv('POS_PORTAL_HOSTS=pos.127.0.0.1.nip.io');
        putenv('BACKOFFICE_PORTAL_HOSTS=admin.127.0.0.1.nip.io');
        putenv('SESSION_DOMAIN');
        unset($_ENV['APP_URL'], $_ENV['POS_PORTAL_HOSTS'], $_ENV['BACKOFFICE_PORTAL_HOSTS'], $_ENV['SESSION_DOMAIN']);
        $_ENV['APP_URL'] = 'http://127.0.0.1:8000';
        $_ENV['POS_PORTAL_HOSTS'] = 'pos.127.0.0.1.nip.io';
        $_ENV['BACKOFFICE_PORTAL_HOSTS'] = 'admin.127.0.0.1.nip.io';
        Env::getRepository()->set('APP_URL', 'http://127.0.0.1:8000');
        Env::getRepository()->set('POS_PORTAL_HOSTS', 'pos.127.0.0.1.nip.io');
        Env::getRepository()->set('BACKOFFICE_PORTAL_HOSTS', 'admin.127.0.0.1.nip.io');
        Env::getRepository()->clear('SESSION_DOMAIN');

        $config = require config_path('session.php');

        $this->assertNull($config['domain']);

        $this->restoreEnvValue('APP_URL', $previousAppUrl);
        $this->restoreEnvValue('POS_PORTAL_HOSTS', $previousPos);
        $this->restoreEnvValue('BACKOFFICE_PORTAL_HOSTS', $previousBackoffice);
        $this->restoreEnvValue('SESSION_DOMAIN', $previousSessionDomain);
    }

    public function test_session_config_keeps_explicit_session_domain_override(): void
    {
        $previousAppUrl = getenv('APP_URL');
        $previousPos = getenv('POS_PORTAL_HOSTS');
        $previousBackoffice = getenv('BACKOFFICE_PORTAL_HOSTS');
        $previousSessionDomain = getenv('SESSION_DOMAIN');

        putenv('APP_URL=http://127.0.0.1:8000');
        putenv('POS_PORTAL_HOSTS=pos.127.0.0.1.nip.io');
        putenv('BACKOFFICE_PORTAL_HOSTS=admin.127.0.0.1.nip.io');
        putenv('SESSION_DOMAIN=.kasir.test');
        unset($_ENV['APP_URL'], $_ENV['POS_PORTAL_HOSTS'], $_ENV['BACKOFFICE_PORTAL_HOSTS'], $_ENV['SESSION_DOMAIN']);
        $_ENV['APP_URL'] = 'http://127.0.0.1:8000';
        $_ENV['POS_PORTAL_HOSTS'] = 'pos.127.0.0.1.nip.io';
        $_ENV['BACKOFFICE_PORTAL_HOSTS'] = 'admin.127.0.0.1.nip.io';
        $_ENV['SESSION_DOMAIN'] = '.kasir.test';
        Env::getRepository()->set('APP_URL', 'http://127.0.0.1:8000');
        Env::getRepository()->set('POS_PORTAL_HOSTS', 'pos.127.0.0.1.nip.io');
        Env::getRepository()->set('BACKOFFICE_PORTAL_HOSTS', 'admin.127.0.0.1.nip.io');
        Env::getRepository()->set('SESSION_DOMAIN', '.kasir.test');

        $config = require config_path('session.php');

        $this->assertSame('.kasir.test', $config['domain']);

        $this->restoreEnvValue('APP_URL', $previousAppUrl);
        $this->restoreEnvValue('POS_PORTAL_HOSTS', $previousPos);
        $this->restoreEnvValue('BACKOFFICE_PORTAL_HOSTS', $previousBackoffice);
        $this->restoreEnvValue('SESSION_DOMAIN', $previousSessionDomain);
    }

    public function test_session_config_treats_string_null_override_as_unset(): void
    {
        $previousAppUrl = getenv('APP_URL');
        $previousPos = getenv('POS_PORTAL_HOSTS');
        $previousBackoffice = getenv('BACKOFFICE_PORTAL_HOSTS');
        $previousSessionDomain = getenv('SESSION_DOMAIN');

        putenv('APP_URL=http://pos.127.0.0.1.nip.io:8000');
        putenv('POS_PORTAL_HOSTS=pos.127.0.0.1.nip.io');
        putenv('BACKOFFICE_PORTAL_HOSTS=admin.127.0.0.1.nip.io');
        putenv('SESSION_DOMAIN=null');
        unset($_ENV['APP_URL'], $_ENV['POS_PORTAL_HOSTS'], $_ENV['BACKOFFICE_PORTAL_HOSTS'], $_ENV['SESSION_DOMAIN']);
        $_ENV['APP_URL'] = 'http://pos.127.0.0.1.nip.io:8000';
        $_ENV['POS_PORTAL_HOSTS'] = 'pos.127.0.0.1.nip.io';
        $_ENV['BACKOFFICE_PORTAL_HOSTS'] = 'admin.127.0.0.1.nip.io';
        $_ENV['SESSION_DOMAIN'] = 'null';
        Env::getRepository()->set('APP_URL', 'http://pos.127.0.0.1.nip.io:8000');
        Env::getRepository()->set('POS_PORTAL_HOSTS', 'pos.127.0.0.1.nip.io');
        Env::getRepository()->set('BACKOFFICE_PORTAL_HOSTS', 'admin.127.0.0.1.nip.io');
        Env::getRepository()->set('SESSION_DOMAIN', 'null');

        $config = require config_path('session.php');

        $this->assertSame('.127.0.0.1.nip.io', $config['domain']);

        $this->restoreEnvValue('APP_URL', $previousAppUrl);
        $this->restoreEnvValue('POS_PORTAL_HOSTS', $previousPos);
        $this->restoreEnvValue('BACKOFFICE_PORTAL_HOSTS', $previousBackoffice);
        $this->restoreEnvValue('SESSION_DOMAIN', $previousSessionDomain);
    }

    public function test_root_renders_portal_chooser_without_login_form(): void
    {
        $tenantId = $this->seedTenant();
        $this->seedWebUser($tenantId, 'owner-root-entry@example.test');

        $this->get('/')
            ->assertOk()
            ->assertSee('Pilih Area Kerja')
            ->assertSee('Masuk POS')
            ->assertSee('Masuk Backoffice')
            ->assertSee('href="/pos/login"', false)
            ->assertSee('/backoffice', false)
            ->assertDontSee('name="email"', false)
            ->assertDontSee('action="/login"', false)
            ->assertDontSee('action="/"', false)
            ->assertSee('/register', false);
    }

    public function test_pos_login_page_renders_official_pos_entry(): void
    {
        $tenantId = $this->seedTenant();
        $this->seedWebUser($tenantId, 'owner-pos-entry@example.test');

        $this->get('/pos/login')
            ->assertOk()
            ->assertSee('Login POS')
            ->assertSee('Area kerja kasir untuk transaksi dan operasional harian.')
            ->assertSee('Buka Backoffice')
            ->assertSee('POS')
            ->assertSee('action="/pos/login"', false);
    }

    public function test_legacy_login_alias_renders_portal_chooser(): void
    {
        $tenantId = $this->seedTenant();
        $this->seedWebUser($tenantId, 'owner-login-chooser@example.test');

        $this->get('/login')
            ->assertOk()
            ->assertSee('Pilih Area Kerja')
            ->assertSee('Masuk POS')
            ->assertSee('Masuk Backoffice')
            ->assertSee('href="/pos/login"', false)
            ->assertSee('href="/backoffice"', false);
    }

    public function test_primary_login_on_pos_host_renders_pos_copy(): void
    {
        $tenantId = $this->seedTenant();
        $this->seedWebUser($tenantId, 'owner-pos-entry@example.test');

        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        $this->get('http://pos.kalakasir.test/')
            ->assertOk()
            ->assertSee('Login POS')
            ->assertSee('Area kerja kasir untuk transaksi dan operasional harian.')
            ->assertSee('POS')
            ->assertSee('action="/"', false);
    }

    public function test_official_backoffice_entry_renders_backoffice_copy(): void
    {
        $tenantId = $this->seedTenant();
        $this->seedWebUser($tenantId, 'owner-backoffice-entry@example.test');

        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        $this->get('http://office.kalakasir.test/backoffice')
            ->assertOk()
            ->assertSee('Login Backoffice')
            ->assertSee('BACKOFFICE')
            ->assertSee('Area kerja owner dan admin untuk laporan, data master, dan pengaturan toko.')
            ->assertSee('Buka POS')
            ->assertSee('href="/pos/login"', false)
            ->assertSee('action="/backoffice"', false);
    }

    public function test_pos_login_alias_renders_pos_entry(): void
    {
        $tenantId = $this->seedTenant();
        $this->seedWebUser($tenantId, 'owner-pos-alias@example.test');

        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        $this->get('http://pos.kalakasir.test/pos/login')
            ->assertOk()
            ->assertSee('Login POS')
            ->assertSee('action="/pos/login"', false);
    }

    public function test_legacy_backoffice_login_alias_redirects_to_official_backoffice_entry(): void
    {
        $tenantId = $this->seedTenant();
        $this->seedWebUser($tenantId, 'owner-backoffice-alias@example.test');

        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        $this->get('http://office.kalakasir.test/backoffice/login')
            ->assertRedirect('http://office.kalakasir.test/backoffice');
    }

    public function test_onboarding_step_one_renders_registration_form_and_terms_copy(): void
    {
        $this->seedTenant();

        $this->get('/register/step-1')
            ->assertOk()
            ->assertSee('Pendaftaran Akun Baru')
            ->assertSee('Langkah 1: Registrasi Pengguna')
            ->assertSee('action="/register/step-1"', false)
            ->assertSee('Syarat dan Ketentuan')
            ->assertSee('Kebijakan Privasi');
    }

    public function test_onboarding_step_two_renders_company_profile_fields(): void
    {
        $this->seedTenant();

        $this->withSession([
            'onboarding.step1' => [
                'name' => 'Owner',
                'email' => 'owner@example.test',
                'phone' => '08123',
                'password' => 'secret123',
            ],
        ])->get('/register/step-2')
            ->assertOk()
            ->assertSee('Data Perusahaan')
            ->assertSee('Langkah 2: Data Perusahaan')
            ->assertSee('Profil Bisnis')
            ->assertSee('action="/register/step-2"', false)
            ->assertDontSee('Metode Inventaris Awal')
            ->assertSee('Kategori Bisnis');
    }

    public function test_onboarding_step_three_renders_valuation_method_choices(): void
    {
        $tenantId = $this->seedTenant();

        $this->withSession([
            'onboarding.step2' => [
                'tenant_id' => $tenantId,
                'user_id' => (string) Str::uuid(),
                'store_name' => 'Tenant Uji',
            ],
        ])->get('/register/step-3')
            ->assertOk()
            ->assertSee('Pengaturan Stok Awal')
            ->assertSee('Langkah 3 dari 5')
            ->assertSee('Metode Penilaian Inventaris')
            ->assertSee('action="/register/step-3"', false)
            ->assertSee('FIFO')
            ->assertSee('First-In, First-Out')
            ->assertSee('Average Cost');
    }

    public function test_onboarding_step_four_renders_first_product_form_and_skip_action(): void
    {
        $this->seedTenant();

        $this->withSession([
            'onboarding.step3' => [
                'valuation_method' => 'fifo',
            ],
        ])->get('/register/step-4')
            ->assertOk()
            ->assertSee('Langkah 4: Produk Awal')
            ->assertSee('Input Produk Pertama Anda')
            ->assertSee('Harga Pokok (HPP)')
            ->assertSee('Bisa ketik kategori baru')
            ->assertSee('Sistem akan membuat kategori baru otomatis jika belum ada.')
            ->assertSee('action="/register/step-4"', false)
            ->assertSee('Lanjut ke Rekap')
            ->assertSee('Lewati dan Lanjut ke Rekap');
    }

    public function test_onboarding_review_renders_summary_and_complete_action(): void
    {
        $this->seedTenant();

        $this->withSession([
            'onboarding.step1' => [
                'name' => 'Owner Review',
                'email' => 'owner-review@example.test',
                'phone' => '08123',
                'password' => 'secret123',
            ],
            'onboarding.step2' => [
                'tenant_id' => (string) Str::uuid(),
                'user_id' => (string) Str::uuid(),
                'store_name' => 'Tenant Uji',
                'operational_address' => 'Jl. Review No. 2',
                'business_category' => 'retail',
                'timezone' => 'Asia/Jakarta',
                'valuation_method' => 'fifo',
            ],
            'onboarding.step3' => [
                'valuation_method' => 'fifo',
            ],
            'onboarding.step4' => [
                'skip_product' => true,
                'product' => [],
            ],
        ])->get('/register/review')
            ->assertOk()
            ->assertSee('Review Sebelum Masuk')
            ->assertSee('Pilih Paket')
            ->assertSee('S - Starter')
            ->assertSee('M - Medium')
            ->assertSee('P - Pro')
            ->assertSee('Konfirmasi dan Masuk Dashboard')
            ->assertSee('Kembali ke Form Produk');
    }

    private function seedTenant(): string
    {
        $id = (string) Str::uuid();

        DB::table('tenants')->insert([
            'id' => $id,
            'name' => 'Tenant Uji',
            'timezone' => 'Asia/Jakarta',
            'domain' => null,
            'subdomain' => null,
            'logo_url' => null,
            'created_at' => now('UTC'),
            'inventory_valuation_method' => 'average',
        ]);

        return $id;
    }

    private function seedWebUser(string $tenantId, string $email): void
    {
        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'name' => 'Owner Test',
            'email' => $email,
            'password' => bcrypt('secret123'),
            'failed_login_attempts' => 0,
            'remember_token' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    private function restoreEnvValue(string $key, string|false $value): void
    {
        unset($_ENV[$key]);

        if ($value === false) {
            putenv($key);
            Env::getRepository()->clear($key);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        Env::getRepository()->set($key, $value);
    }
}
