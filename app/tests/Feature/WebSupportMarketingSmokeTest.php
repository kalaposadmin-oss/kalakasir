<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WebSupportMarketingSmokeTest extends TestCase
{
    public function test_settings_preferences_route_and_view_expose_regional_and_receipt_sections(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/settings/preferences', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/settings-preferences.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.store_profile', $middleware);
        $this->assertTrue(view()->exists('settings-preferences'));
        $this->assertStringContainsString('Preferensi Sistem & Cetak', $contents);
        $this->assertStringContainsString('Format & Regional', $contents);
        $this->assertStringContainsString('Konfigurasi Struk', $contents);
        $this->assertStringContainsString('Preview Struk', $contents);
        $this->assertStringContainsString('/api/v1/bootstrap', $contents);
    }

    public function test_settings_integrations_route_and_view_expose_catalog_and_connection_controls(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/settings/integrations', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/settings-integrations.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,settings.integrations', $middleware);
        $this->assertNotContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('settings-integrations'));
        $this->assertStringContainsString('Katalog Integrasi', $contents);
        $this->assertStringContainsString('Metode Pembayaran (Payment Gateway)', $contents);
        $this->assertStringContainsString('Detail Koneksi: Xendit', $contents);
        $this->assertStringContainsString('Test Connection', $contents);
        $this->assertStringContainsString('/api/v1/subscription/entitlements', $contents);
    }

    public function test_help_center_route_and_view_expose_search_categories_and_docs_cta(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/help/center', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/help-center.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,help.center', $middleware);
        $this->assertTrue(view()->exists('help-center'));
        $this->assertStringContainsString('Pusat Bantuan', $contents);
        $this->assertStringContainsString('help-search', $contents);
        $this->assertStringContainsString('data-help-category="billing"', $contents);
        $this->assertStringContainsString('Artikel Populer', $contents);
        $this->assertStringContainsString('Artikel bantuan belum dipublikasikan', $contents);
    }

    public function test_help_about_route_and_view_expose_release_notes_and_legal_cards(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/help/about', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/help-about.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,help.center', $middleware);
        $this->assertTrue(view()->exists('help-about'));
        $this->assertStringContainsString('Tentang Aplikasi', $contents);
        $this->assertStringContainsString('Riwayat Pembaruan', $contents);
        $this->assertStringContainsString('Syarat & Ketentuan', $contents);
        $this->assertStringContainsString('Kebijakan Privasi', $contents);
        $this->assertStringContainsString('/api/v1/subscription/current', $contents);
    }

    public function test_help_support_route_and_view_expose_ticket_table_and_sla_banner(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/help/support', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/help-support.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,help.center', $middleware);
        $this->assertTrue(view()->exists('help-support'));
        $this->assertStringContainsString('Dukungan & Tiket Bantuan', $contents);
        $this->assertStringContainsString('support-sla', $contents);
        $this->assertStringContainsString('Riwayat Tiket Bantuan', $contents);
        $this->assertStringContainsString('support-ticket-body', $contents);
        $this->assertStringContainsString('getBootstrap', $contents);
    }

    public function test_marketing_online_store_route_and_view_expose_channels_and_sync_log(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/marketing/online-store', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/marketing-online-store.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('marketing-online-store'));
        $this->assertStringContainsString('Integrasi Toko Online', $contents);
        $this->assertStringContainsString('Saluran Penjualan', $contents);
        $this->assertStringContainsString('Log Sinkronisasi Terbaru', $contents);
        $this->assertStringContainsString('online-sync-log', $contents);
        $this->assertStringContainsString('Produk Toko', $contents);
    }

    public function test_marketing_referral_route_and_view_expose_kpis_codes_and_reward_history(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/marketing/referral', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/marketing-referral.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('marketing-referral'));
        $this->assertStringContainsString('Program Referral', $contents);
        $this->assertStringContainsString('Daftar Kode Referral', $contents);
        $this->assertStringContainsString('Riwayat Reward', $contents);
        $this->assertStringContainsString('ref-code-body', $contents);
        $this->assertStringContainsString('/api/v1/loyalty', $contents);
    }
}
