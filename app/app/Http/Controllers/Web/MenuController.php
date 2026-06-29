<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use App\Services\UserRoleResolverService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(): View
    {
        return view('menu', [
            'tenantId' => $this->resolveTenantId(),
            'sections' => $this->sections(),
        ]);
    }

    public function section(string $section): View
    {
        $sections = $this->sections();

        abort_unless(array_key_exists($section, $sections), 404);

        return view('menu-section', [
            'tenantId' => $this->resolveTenantId(),
            'title' => $sections[$section]['title'],
            'items' => $sections[$section]['items'],
        ]);
    }

    /**
     * @return array<string, array{title:string,items:list<array{label:string,icon:string,href:string,feature?:string,roles?:list<string>}>}>
     */
    private function sections(): array
    {
        $portal = session('auth_portal', 'pos') === 'backoffice' ? 'backoffice' : 'pos';
        $prefix = $portal === 'backoffice' ? '/backoffice' : '/pos';
        $posOrBackoffice = static fn (string $posPath, string $backofficePath): string => $portal === 'backoffice'
            ? $backofficePath
            : $posPath;
        $backofficeOnly = static function (string $target) use ($portal): string {
            if ($portal === 'backoffice') {
                return $target;
            }

            return '/portal/switch/backoffice?next='.rawurlencode($target);
        };
        $managementRoles = ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'];
        $settingsRoles = ['supervisor', 'admin', 'owner', 'superadmin'];
        $adminRoles = ['admin', 'owner', 'superadmin'];

        $sections = [
            'management' => [
                'title' => 'Manajemen',
                'items' => [
                    ['label' => 'Barang atau Jasa', 'icon' => 'inventory_2', 'href' => $prefix.'/products'],
                    ['label' => 'Tambah Produk', 'icon' => 'add_box', 'href' => $prefix.'/products/new', 'feature' => 'product.list', 'roles' => $adminRoles],
                    ['label' => 'Import Produk', 'icon' => 'upload_file', 'href' => $backofficeOnly('/backoffice/products/import'), 'feature' => 'product.list', 'roles' => $adminRoles],
                    ['label' => 'Kategori Barang', 'icon' => 'grid_view', 'href' => $prefix.'/categories'],
                    ['label' => 'Manajemen Stok', 'icon' => 'conveyor_belt', 'href' => $prefix.'/inventory'],
                    ['label' => 'Aset Tetap', 'icon' => 'home_repair_service', 'href' => $backofficeOnly('/backoffice/inventory/fixed-assets'), 'feature' => 'inventory.stock', 'roles' => $managementRoles],
                    ['label' => 'Pembelian Barang', 'icon' => 'shopping_cart', 'href' => $prefix.'/purchases/new', 'feature' => 'purchase.order', 'roles' => $adminRoles],
                    ['label' => 'Pelanggan', 'icon' => 'groups', 'href' => $prefix.'/customers', 'feature' => 'customer.manage', 'roles' => $managementRoles],
                    ['label' => 'Supplier', 'icon' => 'local_shipping', 'href' => $backofficeOnly('/backoffice/suppliers'), 'feature' => 'supplier.manage'],
                    ['label' => 'Hutang dan Piutang', 'icon' => 'receipt_long', 'href' => $posOrBackoffice('/pos/receivables', '/backoffice/reports/receivables'), 'feature' => 'finance.lock_period'],
                    ['label' => 'Diskon, Pajak, dan Biaya', 'icon' => 'percent', 'href' => $backofficeOnly('/backoffice/settings/tax-promo'), 'feature' => 'settings.tax'],
                    ['label' => 'Penyesuaian Stok', 'icon' => 'tune', 'href' => $prefix.'/inventory/stock-adjustment', 'feature' => 'inventory.adjustment'],
                    ['label' => 'Stok Opname', 'icon' => 'fact_check', 'href' => $prefix.'/inventory/stock-opname', 'feature' => 'inventory.stock_opname'],
                    ['label' => 'Transfer Stok', 'icon' => 'sync_alt', 'href' => $backofficeOnly('/backoffice/inventory/stock-transfers'), 'feature' => 'inventory.transfer', 'roles' => $managementRoles],
                    ['label' => 'Marketing', 'icon' => 'stars', 'href' => $backofficeOnly('/backoffice/loyalty'), 'feature' => 'loyalty.program'],
                ],
            ],
            'production' => [
                'title' => 'Produksi',
                'items' => [
                    ['label' => 'Bahan Baku & Resep', 'icon' => 'nutrition', 'href' => $prefix.'/materials', 'feature' => 'product.materials', 'roles' => $adminRoles],
                    ['label' => 'Batch Produksi', 'icon' => 'precision_manufacturing', 'href' => $prefix.'/inventory/production-batches', 'feature' => 'inventory.production_batches', 'roles' => $managementRoles],
                    ['label' => 'Stok Hasil Produksi', 'icon' => 'inventory', 'href' => $prefix.'/inventory', 'feature' => 'inventory.stock', 'roles' => $managementRoles],
                    ['label' => 'Laporan Persediaan', 'icon' => 'analytics', 'href' => $backofficeOnly('/backoffice/reports/inventory'), 'feature' => 'reports.inventory_view', 'roles' => $managementRoles],
                ],
            ],
            'sales' => [
                'title' => 'Transaksi Penjualan',
                'items' => [
                    ['label' => 'Kasir POS', 'icon' => 'point_of_sale', 'href' => '/pos/checkout'],
                    ['label' => 'Order Online', 'icon' => 'orders', 'href' => $backofficeOnly('/backoffice/online-orders'), 'feature' => 'sale.pos'],
                    ['label' => 'Riwayat Penjualan', 'icon' => 'history', 'href' => $posOrBackoffice('/pos/history', '/backoffice/reports/sales')],
                    ['label' => 'Riwayat Piutang', 'icon' => 'request_quote', 'href' => $posOrBackoffice('/pos/receivables', '/backoffice/reports/receivables')],
                    ['label' => 'Void Transaksi', 'icon' => 'assignment_return', 'href' => $posOrBackoffice('/pos/void-refund', '/backoffice/reports/sales')],
                ],
            ],
            'purchases' => [
                'title' => 'Pembelian ke Supplier',
                'items' => [
                    ['label' => 'Daftar Pembelian', 'icon' => 'receipt_long', 'href' => $posOrBackoffice('/pos/purchases', '/backoffice/purchases'), 'feature' => 'history.purchases', 'roles' => $managementRoles],
                    ['label' => 'Buat Pembelian Baru', 'icon' => 'add_circle', 'href' => $prefix.'/purchases/new', 'feature' => 'purchase.order', 'roles' => $adminRoles],
                    ['label' => 'Penerimaan Barang', 'icon' => 'inventory_2', 'href' => $prefix.'/receiving', 'feature' => 'purchase.receive', 'roles' => $managementRoles],
                    ['label' => 'Retur Pembelian', 'icon' => 'assignment_return', 'href' => $prefix.'/purchases/returns', 'feature' => 'purchase.return', 'roles' => $adminRoles],
                ],
            ],
            'finance' => [
                'title' => 'Keuangan',
                'items' => [
                    ['label' => 'Keuangan', 'icon' => 'account_balance_wallet', 'href' => $posOrBackoffice('/pos/finance/summary', '/backoffice/finance/summary'), 'feature' => 'finance.summary_basic', 'roles' => $managementRoles],
                    ['label' => 'Kas Masuk/Keluar', 'icon' => 'payments', 'href' => $posOrBackoffice('/pos/cash-in', '/backoffice/finance/cash'), 'feature' => 'finance.cash_transactions', 'roles' => $settingsRoles],
                    ['label' => 'Riwayat Transaksi Kas/Bank', 'icon' => 'history', 'href' => $backofficeOnly('/backoffice/finance/cash'), 'feature' => 'finance.lock_period', 'roles' => $settingsRoles],
                    ['label' => 'Saldo Awal', 'icon' => 'savings', 'href' => $backofficeOnly('/admin/opening-balance'), 'feature' => 'finance.bank_accounts', 'roles' => $adminRoles],
                    ['label' => 'Kunci Periode', 'icon' => 'lock_clock', 'href' => $backofficeOnly('/backoffice/finance/lock-periods'), 'feature' => 'finance.lock_period', 'roles' => $settingsRoles],
                    ['label' => 'Settlement QRIS', 'icon' => 'price_check', 'href' => $backofficeOnly('/backoffice/finance/payment-settlements'), 'feature' => 'finance.bank_accounts', 'roles' => $settingsRoles],
                ],
            ],
            'shift' => [
                'title' => 'Shift',
                'items' => [
                    ['label' => 'Buka Shift', 'icon' => 'update', 'href' => '/pos/shifts/open'],
                    ['label' => 'Tutup Shift', 'icon' => 'lock_clock', 'href' => '/pos/shifts/close'],
                    ['label' => 'Riwayat Shift', 'icon' => 'history', 'href' => '/pos/shifts/summary'],
                ],
            ],
            'reports' => [
                'title' => 'Laporan',
                'items' => [
                    ['label' => 'Ringkasan Penjualan', 'icon' => 'bar_chart', 'href' => $backofficeOnly('/backoffice/reports'), 'feature' => 'reports.summary'],
                    ['label' => 'Laporan Laba Rugi', 'icon' => 'trending_up', 'href' => $posOrBackoffice('/pos/finance/summary', '/backoffice/finance/summary'), 'feature' => 'finance.summary_basic'],
                    ['label' => 'Laporan Arus Kas', 'icon' => 'savings', 'href' => $posOrBackoffice('/pos/finance/summary', '/backoffice/finance/summary'), 'feature' => 'finance.summary_basic'],
                    ['label' => 'Laporan Penjualan', 'icon' => 'list_alt', 'href' => $posOrBackoffice('/pos/history', '/backoffice/reports/sales')],
                    ['label' => 'Laporan Piutang', 'icon' => 'request_quote', 'href' => $posOrBackoffice('/pos/receivables', '/backoffice/reports/receivables')],
                    ['label' => 'Laporan Pembelian', 'icon' => 'shopping_cart', 'href' => $posOrBackoffice('/pos/purchases', '/backoffice/purchases'), 'feature' => 'history.purchases'],
                    ['label' => 'Rekap Pembelian', 'icon' => 'summarize', 'href' => $backofficeOnly('/backoffice/reports/purchases'), 'feature' => 'reports.purchases_view', 'roles' => $settingsRoles],
                    ['label' => 'Laporan Persediaan', 'icon' => 'inventory_2', 'href' => $posOrBackoffice('/pos/inventory', '/backoffice/reports/inventory'), 'feature' => 'reports.inventory_view'],
                    ['label' => 'Kartu Stok', 'icon' => 'receipt_long', 'href' => $posOrBackoffice('/pos/inventory/stock-card', '/backoffice/reports/inventory/stock-card'), 'feature' => 'reports.inventory_stock_card'],
                    ['label' => 'Peringatan Stok', 'icon' => 'warning', 'href' => $backofficeOnly('/backoffice/reports/inventory/stock-alerts'), 'feature' => 'reports.inventory_view'],
                    ['label' => 'Laporan Pembayaran', 'icon' => 'payments', 'href' => $posOrBackoffice('/pos/finance/summary', '/backoffice/finance/summary'), 'feature' => 'finance.summary_basic'],
                    ['label' => 'Settlement QRIS', 'icon' => 'price_check', 'href' => $backofficeOnly('/backoffice/finance/payment-settlements'), 'feature' => 'finance.bank_accounts'],
                ],
            ],
            'settings' => [
                'title' => 'Pengaturan',
                'items' => [
                    ['label' => 'Toko', 'icon' => 'store', 'href' => $prefix.'/branches', 'feature' => 'settings.branches_manage', 'roles' => $adminRoles],
                    ['label' => 'Pembayaran & Rekening', 'icon' => 'payments', 'href' => $backofficeOnly('/backoffice/settings/payment-settings'), 'feature' => 'finance.bank_accounts', 'roles' => $settingsRoles],
                    ['label' => 'Manajemen Staff', 'icon' => 'person', 'href' => $prefix.'/users', 'feature' => 'settings.staff_basic', 'roles' => $adminRoles],
                    ['label' => 'Pembayaran / Langganan', 'icon' => 'payments', 'href' => $backofficeOnly('/backoffice/settings/subscription'), 'feature' => 'settings.subscription_billing', 'roles' => $settingsRoles],
                    ['label' => 'Pengaturan Transaksi', 'icon' => 'tune', 'href' => $backofficeOnly('/backoffice/settings/tax-promo'), 'feature' => 'settings.tax', 'roles' => $adminRoles],
                    ['label' => 'Preferensi Toko & Cetak', 'icon' => 'tune', 'href' => $backofficeOnly('/backoffice/settings/preferences'), 'feature' => 'settings.store_profile', 'roles' => $settingsRoles],
                    ['label' => 'Metode Persediaan', 'icon' => 'rule_settings', 'href' => $backofficeOnly('/backoffice/settings/inventory-method'), 'feature' => 'settings.inventory_method_basic', 'roles' => $adminRoles],
                    ['label' => 'Integrasi', 'icon' => 'integration_instructions', 'href' => $backofficeOnly('/backoffice/settings/integrations'), 'feature' => 'settings.integrations', 'roles' => $adminRoles],
                    ['label' => 'Valuation Persediaan', 'icon' => 'inventory', 'href' => $backofficeOnly('/backoffice/settings/inventory-valuation'), 'feature' => 'settings.inventory_valuation', 'roles' => $adminRoles],
                    ['label' => 'Audit Valuation', 'icon' => 'history', 'href' => $backofficeOnly('/backoffice/settings/inventory-valuation-audit'), 'feature' => 'settings.inventory_valuation', 'roles' => $adminRoles],
                    ['label' => 'Observability', 'icon' => 'monitoring', 'href' => $backofficeOnly('/backoffice/settings/observability'), 'feature' => 'settings.observability', 'roles' => $adminRoles],
                    ['label' => 'Audit Refund Lama', 'icon' => 'history_edu', 'href' => $backofficeOnly('/backoffice/settings/refund-legacy-audit'), 'roles' => $adminRoles],
                ],
            ],
            'other' => [
                'title' => 'Lainnya',
                'items' => [
                    ['label' => 'Referral', 'icon' => 'group', 'href' => $backofficeOnly('/backoffice/marketing/referral')],
                    ['label' => 'Toko Online Saya', 'icon' => 'storefront', 'href' => $backofficeOnly('/backoffice/marketing/online-store')],
                    ['label' => 'Pusat Bantuan', 'icon' => 'support_agent', 'href' => $backofficeOnly('/backoffice/help/center')],
                    ['label' => 'Hubungi Support', 'icon' => 'contact_support', 'href' => $backofficeOnly('/backoffice/help/support')],
                    ['label' => 'Tentang Aplikasi', 'icon' => 'info', 'href' => $backofficeOnly('/backoffice/help/about')],
                    ['label' => 'Buka Area Kasir', 'icon' => 'point_of_sale', 'href' => '/portal/switch/pos?next=%2Fpos%2Fdashboard'],
                    ['label' => 'Keluar', 'icon' => 'logout', 'href' => '/logout'],
                ],
            ],
        ];

        $tenant = $this->resolveTenant();
        if (! $tenant) {
            return $sections;
        }

        $subscription = app(SubscriptionService::class);
        $role = $this->resolveRole($tenant);

        return collect($sections)
            ->map(function (array $section) use ($subscription, $tenant, $role) {
                $section['items'] = collect($section['items'])
                    ->filter(function (array $item) use ($subscription, $tenant, $role) {
                        $feature = $item['feature'] ?? null;
                        $roles = $item['roles'] ?? [];

                        $hasFeature = ! $feature || $subscription->canAccessFeature($tenant, $feature);
                        $hasRole = $roles === [] || ($role !== null && in_array($role, $roles, true));

                        return $hasFeature && $hasRole;
                    })
                    ->map(function (array $item) {
                        unset($item['feature'], $item['roles']);

                        return $item;
                    })
                    ->values()
                    ->all();

                return $section;
            })
            ->filter(fn (array $section) => $section['items'] !== [])
            ->all();
    }

    private function resolveRole(Tenant $tenant): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $resolver = app(UserRoleResolverService::class);
        $role = $resolver->resolveNormalizedRole((string) $tenant->id, (string) $user->id);

        return $role ?: (filled($user->role ?? null) ? $resolver->normalize((string) $user->role) : null);
    }

    private function resolveTenant(): ?Tenant
    {
        $tenant = TenantContext::get();
        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        $user = auth()->user();
        if (! $user || ! filled($user->tenant_id ?? null) || ! Schema::hasTable('tenants')) {
            return null;
        }

        return Tenant::query()->find($user->tenant_id);
    }

    private function resolveTenantId(): ?string
    {
        return $this->resolveTenant()?->id;
    }
}
