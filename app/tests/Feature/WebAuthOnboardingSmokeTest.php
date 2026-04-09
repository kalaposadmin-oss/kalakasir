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
        $previousPos = getenv('POS_PORTAL_HOSTS');
        $previousBackoffice = getenv('BACKOFFICE_PORTAL_HOSTS');
        $previousSessionDomain = getenv('SESSION_DOMAIN');

        putenv('POS_PORTAL_HOSTS=pos.127.0.0.1.nip.io');
        putenv('BACKOFFICE_PORTAL_HOSTS=admin.127.0.0.1.nip.io');
        putenv('SESSION_DOMAIN');
        unset($_ENV['POS_PORTAL_HOSTS'], $_ENV['BACKOFFICE_PORTAL_HOSTS'], $_ENV['SESSION_DOMAIN']);
        $_ENV['POS_PORTAL_HOSTS'] = 'pos.127.0.0.1.nip.io';
        $_ENV['BACKOFFICE_PORTAL_HOSTS'] = 'admin.127.0.0.1.nip.io';
        Env::getRepository()->set('POS_PORTAL_HOSTS', 'pos.127.0.0.1.nip.io');
        Env::getRepository()->set('BACKOFFICE_PORTAL_HOSTS', 'admin.127.0.0.1.nip.io');
        Env::getRepository()->clear('SESSION_DOMAIN');

        $config = require config_path('session.php');

        $this->assertSame('.127.0.0.1.nip.io', $config['domain']);

        $this->restoreEnvValue('POS_PORTAL_HOSTS', $previousPos);
        $this->restoreEnvValue('BACKOFFICE_PORTAL_HOSTS', $previousBackoffice);
        $this->restoreEnvValue('SESSION_DOMAIN', $previousSessionDomain);
    }

    public function test_session_config_keeps_explicit_session_domain_override(): void
    {
        $previousPos = getenv('POS_PORTAL_HOSTS');
        $previousBackoffice = getenv('BACKOFFICE_PORTAL_HOSTS');
        $previousSessionDomain = getenv('SESSION_DOMAIN');

        putenv('POS_PORTAL_HOSTS=pos.127.0.0.1.nip.io');
        putenv('BACKOFFICE_PORTAL_HOSTS=admin.127.0.0.1.nip.io');
        putenv('SESSION_DOMAIN=.kasir.test');
        unset($_ENV['POS_PORTAL_HOSTS'], $_ENV['BACKOFFICE_PORTAL_HOSTS'], $_ENV['SESSION_DOMAIN']);
        $_ENV['POS_PORTAL_HOSTS'] = 'pos.127.0.0.1.nip.io';
        $_ENV['BACKOFFICE_PORTAL_HOSTS'] = 'admin.127.0.0.1.nip.io';
        $_ENV['SESSION_DOMAIN'] = '.kasir.test';
        Env::getRepository()->set('POS_PORTAL_HOSTS', 'pos.127.0.0.1.nip.io');
        Env::getRepository()->set('BACKOFFICE_PORTAL_HOSTS', 'admin.127.0.0.1.nip.io');
        Env::getRepository()->set('SESSION_DOMAIN', '.kasir.test');

        $config = require config_path('session.php');

        $this->assertSame('.kasir.test', $config['domain']);

        $this->restoreEnvValue('POS_PORTAL_HOSTS', $previousPos);
        $this->restoreEnvValue('BACKOFFICE_PORTAL_HOSTS', $previousBackoffice);
        $this->restoreEnvValue('SESSION_DOMAIN', $previousSessionDomain);
    }

    public function test_primary_login_page_renders_main_portal_copy_and_register_link(): void
    {
        $this->seedTenant();

        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk ke KalaKasir')
            ->assertSee('PORTAL UTAMA')
            ->assertSee('action="/login"', false)
            ->assertSee('/register', false);
    }

    public function test_primary_login_on_pos_host_renders_pos_copy(): void
    {
        $this->seedTenant();

        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        $this->get('http://pos.kalakasir.test/login')
            ->assertOk()
            ->assertSee('Login POS')
            ->assertSee('Masuk cepat untuk operasional kasir harian.')
            ->assertSee('POS')
            ->assertSee('action="/login"', false);
    }

    public function test_primary_login_on_backoffice_host_renders_backoffice_copy(): void
    {
        $this->seedTenant();

        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        $this->get('http://office.kalakasir.test/login')
            ->assertOk()
            ->assertSee('Login Backoffice')
            ->assertSee('BACKOFFICE')
            ->assertSee('master data, laporan, dan pengaturan toko', false)
            ->assertSee('action="/login"', false);
    }

    public function test_pos_login_page_renders_portal_specific_copy(): void
    {
        $this->seedTenant();

        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        $this->get('http://pos.kalakasir.test/pos/login')
            ->assertOk()
            ->assertSee('Login POS')
            ->assertSee('Masuk cepat untuk operasional kasir harian.')
            ->assertSee('POS')
            ->assertSee('action="/pos/login"', false)
                ->assertSee('Daftar di /register')
                ->assertSee('Portal utama tersedia di', false);
    }

    public function test_backoffice_login_page_renders_portal_specific_copy(): void
    {
        $this->seedTenant();

        config([
            'platform.portals.pos_hosts' => ['pos.kalakasir.test'],
            'platform.portals.backoffice_hosts' => ['office.kalakasir.test'],
        ]);

        $this->get('http://office.kalakasir.test/backoffice/login')
            ->assertOk()
            ->assertSee('Login Backoffice')
            ->assertSee('BACKOFFICE')
            ->assertSee('action="/backoffice/login"', false)
            ->assertSee('master data, laporan, dan pengaturan toko', false);
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
            ->assertSee('Pengaturan Stok')
            ->assertSee('Metode Penilaian Inventaris')
            ->assertSee('action="/register/step-3"', false)
            ->assertSee('FIFO (First-In, First-Out)')
            ->assertSee('Average Cost (Biaya Rata-rata)');
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
            ->assertSee('Input Produk Pertama Anda')
            ->assertSee('Harga Pokok (HPP)')
            ->assertSee('Bisa ketik kategori baru')
            ->assertSee('Sistem akan membuat kategori baru otomatis jika belum ada.')
            ->assertSee('action="/register/step-4"', false)
            ->assertSee('Simpan dan Selesai')
            ->assertSee('Lewati dan Selesai');
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