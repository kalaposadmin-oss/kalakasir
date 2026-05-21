<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(): View
    {
        return view('menu', [
            'tenantId' => $this->resolveTenantId(),
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
     * @return array<string, array{title:string,items:list<array{label:string,icon:string,href:string,feature?:string}>}>
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

        $sections = [
            'management' => [
                'title' => 'Manajemen',
                'items' => [
                    ['label' => 'Barang atau Jasa', 'icon' => 'inventory_2', 'href' => $prefix.'/products'],
                    ['label' => 'Kategori Barang', 'icon' => 'grid_view', 'href' => $prefix.'/categories'],
                    ['label' => 'Manajemen Stok', 'icon' => 'conveyor_belt', 'href' => $prefix.'/inventory'],
                    ['label' => 'Pembelian Barang', 'icon' => 'shopping_cart', 'href' => $prefix.'/purchases/new', 'feature' => 'purchase.order'],
                    ['label' => 'Pelanggan', 'icon' => 'groups', 'href' => $prefix.'/customers', 'feature' => 'customer.manage'],
                    ['label' => 'Supplier', 'icon' => 'local_shipping', 'href' => $backofficeOnly('/backoffice/suppliers'), 'feature' => 'supplier.manage'],
                    ['label' => 'Hutang dan Piutang', 'icon' => 'receipt_long', 'href' => $posOrBackoffice('/pos/receivables', '/backoffice/reports/receivables'), 'feature' => 'finance.lock_period'],
                    ['label' => 'Diskon, Pajak, dan Biaya', 'icon' => 'percent', 'href' => $backofficeOnly('/backoffice/settings/tax-promo'), 'feature' => 'settings.tax'],
                    ['label' => 'Penyesuaian Stok', 'icon' => 'tune', 'href' => $prefix.'/inventory/stock-adjustment', 'feature' => 'inventory.adjustment'],
                    ['label' => 'Stok Opname', 'icon' => 'fact_check', 'href' => $prefix.'/inventory/stock-opname', 'feature' => 'inventory.stock_opname'],
                    ['label' => 'Marketing', 'icon' => 'stars', 'href' => $backofficeOnly('/backoffice/loyalty'), 'feature' => 'loyalty.program'],
                ],
            ],
            'sales' => [
                'title' => 'Transaksi Penjualan',
                'items' => [
                    ['label' => 'Kasir POS', 'icon' => 'point_of_sale', 'href' => '/pos/checkout'],
                    ['label' => 'Riwayat Penjualan', 'icon' => 'history', 'href' => $posOrBackoffice('/pos/history', '/backoffice/reports/sales')],
                    ['label' => 'Riwayat Piutang', 'icon' => 'request_quote', 'href' => $posOrBackoffice('/pos/receivables', '/backoffice/reports/receivables')],
                    ['label' => 'Void Transaksi', 'icon' => 'assignment_return', 'href' => $posOrBackoffice('/pos/void-refund', '/backoffice/reports/sales')],
                ],
            ],
            'purchases' => [
                'title' => 'Pembelian ke Supplier',
                'items' => [
                    ['label' => 'Daftar Pembelian', 'icon' => 'receipt_long', 'href' => $posOrBackoffice('/pos/purchases', '/backoffice/purchases'), 'feature' => 'history.purchases'],
                    ['label' => 'Buat Pembelian Baru', 'icon' => 'add_circle', 'href' => $prefix.'/purchases/new', 'feature' => 'purchase.order'],
                    ['label' => 'Penerimaan Barang', 'icon' => 'inventory_2', 'href' => $prefix.'/receiving', 'feature' => 'purchase.receive'],
                    ['label' => 'Retur Pembelian', 'icon' => 'assignment_return', 'href' => $prefix.'/purchases/returns', 'feature' => 'purchase.return'],
                ],
            ],
            'finance' => [
                'title' => 'Keuangan',
                'items' => [
                    ['label' => 'Keuangan', 'icon' => 'account_balance_wallet', 'href' => $posOrBackoffice('/pos/finance/summary', '/backoffice/finance/summary'), 'feature' => 'finance.summary_basic'],
                    ['label' => 'Riwayat Transaksi Kas/Bank', 'icon' => 'history', 'href' => $backofficeOnly('/backoffice/finance/lock-periods'), 'feature' => 'finance.lock_period'],
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
                    ['label' => 'Laporan Persediaan', 'icon' => 'inventory_2', 'href' => $posOrBackoffice('/pos/inventory', '/backoffice/reports/inventory'), 'feature' => 'reports.inventory_view'],
                    ['label' => 'Kartu Stok', 'icon' => 'receipt_long', 'href' => $posOrBackoffice('/pos/inventory/stock-card', '/backoffice/reports/inventory/stock-card'), 'feature' => 'reports.inventory_stock_card'],
                    ['label' => 'Laporan Pembayaran', 'icon' => 'payments', 'href' => $posOrBackoffice('/pos/finance/summary', '/backoffice/finance/summary'), 'feature' => 'finance.summary_basic'],
                    ['label' => 'Settlement QRIS', 'icon' => 'price_check', 'href' => $backofficeOnly('/backoffice/finance/payment-settlements'), 'feature' => 'finance.bank_accounts'],
                ],
            ],
            'settings' => [
                'title' => 'Pengaturan',
                'items' => [
                    ['label' => 'Toko', 'icon' => 'store', 'href' => $prefix.'/branches', 'feature' => 'settings.branches_manage'],
                    ['label' => 'Pembayaran & Rekening', 'icon' => 'payments', 'href' => $backofficeOnly('/backoffice/settings/payment-settings'), 'feature' => 'finance.bank_accounts'],
                    ['label' => 'Database / Sinkronisasi', 'icon' => 'database', 'href' => $prefix.'/dashboard'],
                    ['label' => 'Printer & Struk', 'icon' => 'print', 'href' => $prefix.'/dashboard'],
                    ['label' => 'Manajemen Staff', 'icon' => 'person', 'href' => $prefix.'/users', 'feature' => 'settings.staff_basic'],
                    ['label' => 'E-Wallet', 'icon' => 'account_balance_wallet', 'href' => $prefix.'/dashboard'],
                    ['label' => 'Notifikasi', 'icon' => 'notifications', 'href' => $prefix.'/dashboard'],
                    ['label' => 'Pembayaran / Langganan', 'icon' => 'payments', 'href' => $backofficeOnly('/backoffice/settings/subscription')],
                    ['label' => 'Pengaturan Transaksi', 'icon' => 'tune', 'href' => $backofficeOnly('/backoffice/settings/tax-promo'), 'feature' => 'settings.tax'],
                    ['label' => 'Valuation Persediaan', 'icon' => 'inventory', 'href' => $backofficeOnly('/backoffice/settings/inventory-valuation'), 'feature' => 'settings.inventory_valuation'],
                    ['label' => 'Audit Valuation', 'icon' => 'history', 'href' => $backofficeOnly('/backoffice/settings/inventory-valuation-audit'), 'feature' => 'settings.inventory_valuation'],
                    ['label' => 'Observability', 'icon' => 'monitoring', 'href' => $backofficeOnly('/backoffice/settings/observability'), 'feature' => 'settings.observability'],
                ],
            ],
            'other' => [
                'title' => 'Lainnya',
                'items' => [
                    ['label' => 'Referral', 'icon' => 'group', 'href' => $backofficeOnly('/backoffice/marketing/referral')],
                    ['label' => 'Toko Online Saya', 'icon' => 'storefront', 'href' => $backofficeOnly('/backoffice/marketing/online-store')],
                    ['label' => 'Pusat Bantuan', 'icon' => 'support_agent', 'href' => $backofficeOnly('/backoffice/help/center')],
                ],
            ],
        ];

        $tenant = $this->resolveTenant();
        if (! $tenant) {
            return $sections;
        }

        $subscription = app(SubscriptionService::class);

        return collect($sections)
            ->map(function (array $section) use ($subscription, $tenant) {
                $section['items'] = collect($section['items'])
                    ->filter(function (array $item) use ($subscription, $tenant) {
                        $feature = $item['feature'] ?? null;

                        return ! $feature || $subscription->canAccessFeature($tenant, $feature);
                    })
                    ->map(function (array $item) {
                        unset($item['feature']);

                        return $item;
                    })
                    ->values()
                    ->all();

                return $section;
            })
            ->all();
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
