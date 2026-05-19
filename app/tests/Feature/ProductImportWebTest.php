<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class ProductImportWebTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    public function test_backoffice_import_form_renders_for_admin(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+product-import-web@example.test');

        $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->get('/backoffice/products/import')
            ->assertOk()
            ->assertSee('Import Produk')
            ->assertSee('Contoh CSV');
    }

    public function test_template_download_returns_csv_attachment_for_admin(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+product-import-template@example.test');

        $response = $this->actingAs($user)
            ->withHeaders($this->tenantHeaders($tenant->id))
            ->get('/backoffice/products/import/template');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('sample_products.csv', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('sep=;', $response->getContent());
        $this->assertStringContainsString('sku;name;price;hpp_average;qty_on_hand;track_stock;category;supplier;unit;min_stock', $response->getContent());
        $this->assertStringNotContainsString('<!DOCTYPE html>', $response->getContent());
    }

    public function test_legacy_sample_products_csv_url_returns_csv_when_static_file_is_missing_on_hosting(): void
    {
        $response = $this->get('/sample_products.csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('sep=;', $response->getContent());
        $this->assertStringNotContainsString('<!DOCTYPE html>', $response->getContent());
    }
}
