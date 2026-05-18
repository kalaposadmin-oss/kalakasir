<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kalakasir-testing-views'.DIRECTORY_SEPARATOR.Str::uuid();
        File::ensureDirectoryExists($compiledPath);
        config(['view.compiled' => str_replace('\\', '/', $compiledPath)]);
    }

    public function test_login_page_uses_nonce_based_csp_for_scripts(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringNotContainsString('https://cdn.tailwindcss.com', $csp);
        $this->assertStringNotContainsString('https://cdn.jsdelivr.net', $csp);
        $this->assertStringNotContainsString('https://fonts.googleapis.com', $csp);
        $this->assertStringNotContainsString('https://fonts.gstatic.com', $csp);
        $this->assertStringContainsString('<script nonce="', $response->getContent());
    }

    public function test_login_page_csp_allows_form_submission_to_current_origin(): void
    {
        config([
            'app.url' => 'http://127.0.0.1:8000',
            'platform.portals.pos_hosts' => ['pos.127.0.0.1.nip.io'],
            'platform.portals.backoffice_hosts' => ['admin.127.0.0.1.nip.io'],
        ]);

        $response = $this->get('http://127.0.0.1:8000/');

        $response->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("form-action 'self' http://127.0.0.1:8000", $csp);
        $this->assertStringContainsString('http://pos.127.0.0.1.nip.io:8000', $csp);
        $this->assertStringContainsString('http://admin.127.0.0.1.nip.io:8000', $csp);
    }

    public function test_cross_origin_isolation_headers_are_skipped_for_insecure_nip_io_local_host(): void
    {
        $response = $this->get('http://pos.127.0.0.1.nip.io:8000/register/step-1');

        $response->assertOk();
        $this->assertNull($response->headers->get('Cross-Origin-Opener-Policy'));
        $this->assertNull($response->headers->get('Cross-Origin-Resource-Policy'));
    }

    public function test_cross_origin_isolation_headers_remain_for_loopback_http_host(): void
    {
        $response = $this->get('http://127.0.0.1:8000/register/step-1');

        $response->assertOk();
        $this->assertSame('same-origin', $response->headers->get('Cross-Origin-Opener-Policy'));
        $this->assertSame('same-site', $response->headers->get('Cross-Origin-Resource-Policy'));
    }
}
