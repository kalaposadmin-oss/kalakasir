<?php

namespace Tests\Feature;

use App\Services\ProductImportService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BootsApiTenantUser;
use Tests\TestCase;

class ProductImportServiceTest extends TestCase
{
    use BootsApiTenantUser;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_import_creates_and_updates_products_inside_requested_tenant(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+product-import@example.test');
        [$foreignTenant] = $this->bootApiActor('admin', 'admin+product-import-foreign@example.test');

        TenantContext::set($tenant->fresh());

        $foreignProductId = (string) Str::uuid();
        $this->insertProduct($foreignTenant->id, $foreignProductId, 'EXIST-1', 'Produk Tenant Lain', 9000);

        $localProductId = (string) Str::uuid();
        $this->insertProduct($tenant->id, $localProductId, 'EXIST-1', 'Produk Lama', 10000);

        $path = $this->writeImportCsv(implode("\n", [
            'SKU,Nama Produk,Tipe Produk,Harga Jual,HPP,Kategori,Satuan,Gunakan Stok,Supplier',
            'EXIST-1,Produk Baru Dari Import,Barang Jual,25000,12000,Minuman,cup,true,Supplier Import',
            ',Cuci Loyang,Jasa / Service,15000,0,Layanan,kali,true,Supplier Import',
        ]));

        $summary = app(ProductImportService::class)->importProducts(
            tenantId: (string) $tenant->id,
            path: $path,
            mapping: [
                0 => 'sku',
                1 => 'name',
                2 => 'item_type',
                3 => 'price',
                4 => 'hpp_average',
                5 => 'category',
                6 => 'unit',
                7 => 'track_stock',
                8 => 'supplier',
            ],
            userId: (string) $user->id,
        );

        @unlink($path);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(1, $summary['updated']);
        $this->assertSame(0, $summary['skipped']);

        $categoryId = DB::table('categories')
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Minuman')
            ->value('id');

        $this->assertNotNull($categoryId);

        $supplierId = DB::table('suppliers')
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Supplier Import')
            ->value('id');

        $this->assertNotNull($supplierId);

        $updatedProduct = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('id', $localProductId)
            ->first();

        $this->assertSame('Produk Baru Dari Import', $updatedProduct->name);
        $this->assertSame(25000.0, (float) $updatedProduct->price);
        $this->assertSame($categoryId, $updatedProduct->category_id);
        $this->assertSame($supplierId, $updatedProduct->supplier_id);
        $this->assertSame('cup', $updatedProduct->unit);
        $this->assertSame('finished_good', $updatedProduct->item_type);
        $this->assertSame('default', $updatedProduct->product_mode);

        $createdProduct = DB::table('products')
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Cuci Loyang')
            ->first();

        $this->assertNotNull($createdProduct);
        $this->assertNotSame('', (string) $createdProduct->sku);
        $this->assertSame(0, (int) $createdProduct->track_stock);
        $this->assertSame('service', $createdProduct->item_type);
        $this->assertSame('default', $createdProduct->product_mode);
        $this->assertSame($supplierId, $createdProduct->supplier_id);

        $foreignProduct = DB::table('products')
            ->where('tenant_id', $foreignTenant->id)
            ->where('id', $foreignProductId)
            ->first();

        $this->assertSame('Produk Tenant Lain', $foreignProduct->name);
        $this->assertSame(9000.0, (float) $foreignProduct->price);
    }

    public function test_import_reports_row_errors_without_aborting_other_rows(): void
    {
        [$tenant, $user] = $this->bootApiActor('admin', 'admin+product-import-errors@example.test');

        TenantContext::set($tenant->fresh());

        $path = $this->writeImportCsv(implode("\n", [
            'sku,name,price,qty_on_hand',
            'BAD-1,,1000,0',
            'OK-1,Kopi Tubruk,8000,0',
        ]));

        $summary = app(ProductImportService::class)->importProducts(
            tenantId: (string) $tenant->id,
            path: $path,
            mapping: [
                0 => 'sku',
                1 => 'name',
                2 => 'price',
                3 => 'qty_on_hand',
            ],
            userId: (string) $user->id,
        );

        @unlink($path);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(0, $summary['updated']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertStringContainsString('Nama produk wajib diisi', $summary['errors'][0]);
        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenant->id,
            'sku' => 'OK-1',
            'name' => 'Kopi Tubruk',
        ]);
    }

    public function test_parse_excel_friendly_sample_csv_with_separator_instruction(): void
    {
        $parsed = app(ProductImportService::class)->parseFile(public_path('sample_products.csv'), 'csv', 10);

        $this->assertSame([
            'sku',
            'name',
            'item_type',
            'price',
            'hpp_average',
            'qty_on_hand',
            'track_stock',
            'category',
            'supplier',
            'unit',
            'min_stock',
        ], $parsed['headers']);
        $this->assertSame('P001', $parsed['rows'][0][0]);
        $this->assertSame('Nasi Goreng', $parsed['rows'][0][1]);
        $this->assertSame(3, $parsed['total_rows']);
    }

    private function writeImportCsv(string $contents): string
    {
        $path = storage_path('framework/testing-product-import-'.Str::uuid().'.csv');
        file_put_contents($path, $contents);

        return $path;
    }

    private function insertProduct(string $tenantId, string $productId, string $sku, string $name, float $price): void
    {
        DB::table('products')->insert([
            'id' => $productId,
            'tenant_id' => $tenantId,
            'sku' => $sku,
            'name' => $name,
            'price' => $price,
            'hpp_average' => 0,
            'is_active' => true,
            'track_stock' => true,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }
}
