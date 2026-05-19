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
}
