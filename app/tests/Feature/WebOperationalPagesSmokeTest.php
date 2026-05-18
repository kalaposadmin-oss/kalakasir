<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WebOperationalPagesSmokeTest extends TestCase
{
    public function test_products_route_and_view_expose_canonical_product_navigation(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/products', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/products.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('products'));
        $this->assertStringContainsString('Daftar Produk', $contents);
        $this->assertStringContainsString('/backoffice/dashboard', $contents);
        $this->assertStringContainsString('/backoffice/products/new', $contents);
        $this->assertStringContainsString('/backoffice/products/${product.id}/edit', $contents);
        $this->assertStringContainsString('/backoffice/products/${product.id}?from=', $contents);
    }

    public function test_product_create_route_and_view_expose_inline_category_creation(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/products/new', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/product-create.blade.php'));

        $this->assertContains('web_access:backoffice,admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('product-create'));
        $this->assertStringContainsString('Tambah Produk Baru', $contents);
        $this->assertStringContainsString('category-quick-toggle', $contents);
        $this->assertStringContainsString('category-quick-save', $contents);
        $this->assertStringContainsString('select-no-native-arrow', $contents);
        $this->assertStringContainsString('/api/v1/categories', $contents);
        $this->assertStringContainsString('Produk berhasil disimpan.', $contents);
        $this->assertStringContainsString('productListUrl', $contents);
        $this->assertStringContainsString('productBackUrl', $contents);
        $this->assertStringContainsString('id="cancel-product"', $contents);
        $this->assertStringContainsString('/backoffice/products', $contents);
        $this->assertStringContainsString('enable-variants', $contents);
        $this->assertStringContainsString('variant-line-list', $contents);
        $this->assertStringContainsString('variant-stock-section', $contents);
        $this->assertStringContainsString('payload.variants = collectVariants()', $contents);
        $this->assertStringContainsString('payload.initial_stock_lines = el.trackStock.checked', $contents);
        $this->assertStringContainsString('? collectVariantStockLines(payload.variants)', $contents);
        $this->assertStringContainsString('initial_stock: el.trackStock.checked && !el.enableVariants.checked ? Number(el.stock.value || 0) : 0,', $contents);
        $this->assertStringContainsString('const collectVariantStockLines = (variants) => {', $contents);
    }

    public function test_customer_and_category_views_use_csp_safe_create_flows(): void
    {
        $customerContents = file_get_contents(resource_path('views/customers.blade.php'));
        $categoriesContents = file_get_contents(resource_path('views/categories.blade.php'));
        $categoryCreateContents = file_get_contents(resource_path('views/category-create.blade.php'));

        $this->assertStringContainsString('id="btn-add-customer"', $customerContents);
        $this->assertStringContainsString('data-customer-create-form', $customerContents);
        $this->assertStringContainsString("apiFetch('/api/v1/customers'", $customerContents);
        $this->assertStringContainsString("apiFetch('/api/v1/customer-categories'", $customerContents);
        $this->assertStringContainsString('data-customer-category-field', $customerContents);
        $this->assertStringContainsString('customer_category_id', $customerContents);
        $this->assertStringContainsString('id="add-category"', $categoriesContents);
        $this->assertStringContainsString('data-product-add-url', $categoriesContents);
        $this->assertStringContainsString('id="tab-customer-categories"', $categoriesContents);
        $this->assertStringContainsString('/api/v1/customer-categories', $categoriesContents);
        $this->assertStringContainsString('window.PosUi.fetchWithTimeout(\'/api/v1/categories\'', $categoriesContents);
        $this->assertStringNotContainsString('onclick="location.href', $categoriesContents);
        $this->assertStringContainsString('window.PosUi.fetchWithTimeout(\'/api/v1/categories\'', $categoryCreateContents);
    }

    public function test_product_edit_route_and_view_expose_variant_initial_stock_contract(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/products/product-001/edit', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/product-edit.blade.php'));

        $this->assertContains('web_access:backoffice,admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('product-edit'));
        $this->assertStringContainsString('edit-variant-stock-section', $contents);
        $this->assertStringContainsString('edit-variant-stock-line-list', $contents);
        $this->assertStringContainsString('const collectVariantStockLines = (product) => {', $contents);
        $this->assertStringContainsString('payload.initial_stock_lines = collectVariantStockLines(currentProduct);', $contents);
        $this->assertStringContainsString('initial_stock: currentProduct?.has_variants ? 0 : Number(el.stock.value || 0)', $contents);
        $this->assertStringContainsString('renderVariantStockLines(product);', $contents);
    }

    public function test_product_detail_route_and_view_expose_stock_actions_without_undefined_success_color(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/products/product-001', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/product-detail.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('product-detail'));
        $this->assertStringContainsString('Detail Produk', $contents);
        $this->assertStringContainsString('Transfer Stok', $contents);
        $this->assertStringContainsString('Penyesuaian', $contents);
        $this->assertStringContainsString('bg-emerald-500', $contents);
        $this->assertStringNotContainsString('bg-success', $contents);
        $this->assertStringNotContainsString('text-success', $contents);
    }

    public function test_inventory_route_and_view_expose_back_navigation_context(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/inventory', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/inventory.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('inventory'));
        $this->assertStringContainsString('Stok Inventaris', $contents);
        $this->assertStringContainsString('$inventoryBackUrl = \'/backoffice/dashboard\';', $contents);
        $this->assertStringContainsString(':backUrl="$inventoryBackUrl"', $contents);
    }

    public function test_menu_route_and_view_expose_back_navigation_for_active_portal(): void
    {
        $route = Route::getRoutes()->match(Request::create('/menu', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/menu.blade.php'));

        $this->assertContains('web', $middleware);
        $this->assertTrue(view()->exists('menu'));
        $this->assertStringContainsString('$menuBackUrl = session(\'auth_portal\', \'pos\') === \'backoffice\'', $contents);
        $this->assertStringContainsString(':backUrl="$menuBackUrl"', $contents);
    }

    public function test_layout_includes_menu_drawer_inside_app_shell(): void
    {
        $contents = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $drawerContents = file_get_contents(resource_path('views/partials/menu-drawer.blade.php'));

        $this->assertStringContainsString("@include('partials.menu-drawer')", $contents);
        $this->assertStringContainsString('absolute inset-0 z-50 hidden overflow-hidden', $drawerContents);
    }

    public function test_suppliers_route_and_view_expose_purchase_entry_points(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/suppliers', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/suppliers.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('suppliers'));
        $this->assertStringContainsString('Pemasok & PO', $contents);
        $this->assertStringContainsString('/backoffice/dashboard', $contents);
        $this->assertStringContainsString('/backoffice/purchases/new', $contents);
        $this->assertStringContainsString('Tambah PO Baru', $contents);
        $this->assertStringContainsString('/backoffice/purchases/${purchase.id}?from=', $contents);
    }

    public function test_receiving_route_and_view_expose_canonical_receiving_flow(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/receiving', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/receiving.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('receiving'));
        $this->assertStringContainsString('Terima Barang', $contents);
        $this->assertStringContainsString('/backoffice/suppliers', $contents);
        $this->assertStringContainsString('/backoffice/receiving/${encodeURIComponent(selectedPurchaseId)}?from=', $contents);
        $this->assertStringContainsString('/backoffice/receiving/${encodeURIComponent(selectedPurchaseId)}/action?from=', $contents);
        $this->assertStringContainsString('KONFIRMASI STOK', $contents);
    }

    public function test_pos_sales_history_route_and_view_expose_transaction_and_shift_tabs(): void
    {
        $route = Route::getRoutes()->match(Request::create('/pos/history', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/sales-history.blade.php'));

        $this->assertContains('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('sales-history'));
        $this->assertStringContainsString("'Riwayat'", $contents);
        $this->assertStringContainsString('data-tab="transaksi"', $contents);
        $this->assertStringContainsString('data-tab="shift"', $contents);
        $this->assertStringContainsString('/pos/shifts/${encodeURIComponent(row.id)}/summary?from=', $contents);
        $this->assertStringContainsString('/pos/history', $contents);
    }

    public function test_backoffice_sales_report_route_and_view_expose_reporting_sections(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/reports/sales', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/sales-history.blade.php'));

        $this->assertContains('web_access:backoffice,supervisor|admin|owner|superadmin,reports.summary', $middleware);
        $this->assertTrue(view()->exists('sales-history'));
        $this->assertStringContainsString("'Laporan Penjualan'", $contents);
        $this->assertStringContainsString('report-gross-sales', $contents);
        $this->assertStringContainsString('report-top-products', $contents);
        $this->assertStringContainsString('report-download', $contents);
        $this->assertStringContainsString('Unduh Laporan PDF', $contents);
    }

    public function test_backoffice_online_orders_route_and_view_expose_real_sync_sections(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/online-orders', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/online-orders.blade.php'));

        $this->assertContains('web_access:backoffice,supervisor|admin|owner|superadmin,sale.pos', $middleware);
        $this->assertTrue(view()->exists('online-orders'));
        $this->assertStringContainsString('Order Online', $contents);
        $this->assertStringContainsString('Pelanggan Tersinkron', $contents);
        $this->assertStringContainsString('Masuk Laporan POS', $contents);
        $this->assertStringContainsString('OnlineOrder::query()', $contents);
        $this->assertStringContainsString('/backoffice/reports/sales', $contents);
    }

    public function test_production_batch_list_route_and_view_expose_filterable_batch_monitoring(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/inventory/production-batches', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/production-batches.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('production-batches'));
        $this->assertStringContainsString('Daftar Batch Produksi', $contents);
        $this->assertStringContainsString('production-status-filter', $contents);
        $this->assertStringContainsString('production-cancel-reason-filter', $contents);
        $this->assertStringContainsString('production-anomaly-filter', $contents);
        $this->assertStringContainsString('production-sort-filter', $contents);
        $this->assertStringContainsString('production-date-from', $contents);
        $this->assertStringContainsString('/api/v1/production-batches', $contents);
        $this->assertStringContainsString('/backoffice/inventory/production-batches/${encodeURIComponent(batch.id)}?from=', $contents);
        $this->assertStringContainsString('production-batch-create-open', $contents);
        $this->assertStringContainsString('production-batch-create-form', $contents);
        $this->assertStringContainsString('data-stat="cancelled"', $contents);
        $this->assertStringContainsString('data-stat="variance_qty_total"', $contents);
        $this->assertStringContainsString('production-batch-export-problematic', $contents);
        $this->assertStringContainsString('Observability', $contents);
    }

    public function test_production_batch_detail_route_and_view_expose_batch_snapshot_sections(): void
    {
        $route = Route::getRoutes()->match(Request::create('/backoffice/inventory/production-batches/batch-001', 'GET'));
        $middleware = $route->gatherMiddleware();
        $contents = file_get_contents(resource_path('views/production-batch-detail.blade.php'));

        $this->assertContains('web_access:backoffice,warehouse|supervisor|admin|owner|superadmin,dash.overview', $middleware);
        $this->assertTrue(view()->exists('production-batch-detail'));
        $this->assertStringContainsString('Detail Batch Produksi', $contents);
        $this->assertStringContainsString('Input Bahan Baku', $contents);
        $this->assertStringContainsString('Output Jadi', $contents);
        $this->assertStringContainsString('/api/v1/production-batches/${encodeURIComponent(batchId)}', $contents);
        $this->assertStringContainsString('batch-detail-edit-open', $contents);
        $this->assertStringContainsString('batch-post-form', $contents);
        $this->assertStringContainsString('batch-detail-cancel-open', $contents);
        $this->assertStringContainsString('batch-post-variance-hint', $contents);
        $this->assertStringContainsString('requiresVarianceReason', $contents);
        $this->assertStringContainsString('batch-detail-cancel-reason', $contents);
        $this->assertStringContainsString('batch-cancel-reason', $contents);
        $this->assertStringContainsString('batch-detail-anomaly-banner', $contents);
        $this->assertStringContainsString('batch-detail-observability-link', $contents);
        $this->assertStringContainsString('batch-detail-anomaly-history-section', $contents);
        $this->assertStringContainsString('batch-detail-anomaly-status-filter', $contents);
        $this->assertStringContainsString('batch-detail-anomaly-load-more', $contents);
        $this->assertStringContainsString('data-anomaly-resolve-id', $contents);
        $this->assertStringContainsString('batch-detail-resolve-modal', $contents);
        $this->assertStringContainsString('context_label', $contents);
    }

    public function test_pos_cash_transaction_routes_and_view_expose_journal_inputs(): void
    {
        $cashInRoute = Route::getRoutes()->match(Request::create('/pos/cash-in', 'GET'));
        $contents = file_get_contents(resource_path('views/cash-management.blade.php'));

        $this->assertContains('web_access:pos,cashier|staff|warehouse|supervisor|admin|owner|superadmin,finance.cash_transactions', $cashInRoute->gatherMiddleware());
        $this->assertTrue(view()->exists('cash-management'));
        $this->assertStringContainsString('Kas Masuk', $contents);
        $this->assertStringContainsString('Kas Keluar', $contents);
        $this->assertStringContainsString('transaction_date', $contents);
        $this->assertStringContainsString('account_id', $contents);
        $this->assertStringContainsString('category', $contents);
        $this->assertStringContainsString('description', $contents);
        $this->assertStringContainsString('Ringkasan Bulan Ini', $contents);
        $this->assertStringContainsString('Riwayat Transaksi', $contents);
        $this->assertStringContainsString('cash-sheet-overlay', $contents);
    }
}
