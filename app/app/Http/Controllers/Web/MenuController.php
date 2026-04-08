<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
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
     * @return array<string, array{title:string,items:list<array{label:string,icon:string,href:string}>}>
     */
    private function sections(): array
    {
        return [
            'management' => [
                'title' => 'Manajemen',
                'items' => [
                    ['label' => 'Barang atau Jasa', 'icon' => 'inventory_2', 'href' => '/products'],
                    ['label' => 'Kategori Barang', 'icon' => 'grid_view', 'href' => '/categories'],
                    ['label' => 'Manajemen Stok', 'icon' => 'conveyor_belt', 'href' => '/inventory'],
                    ['label' => 'Pembelian Barang', 'icon' => 'shopping_cart', 'href' => '/purchases/new'],
                    ['label' => 'Pelanggan dan Supplier', 'icon' => 'groups', 'href' => '/customers'],
                    ['label' => 'Hutang dan Piutang', 'icon' => 'receipt_long', 'href' => '/lock-periods'],
                    ['label' => 'Diskon, Pajak, dan Biaya', 'icon' => 'percent', 'href' => '/settings/tax-promo'],
                    ['label' => 'Stok Opname', 'icon' => 'edit_note', 'href' => '/stock-adjustment'],
                    ['label' => 'Marketing', 'icon' => 'stars', 'href' => '/loyalty'],
                ],
            ],
            'sales' => [
                'title' => 'Transaksi Penjualan',
                'items' => [
                    ['label' => 'Kasir POS', 'icon' => 'point_of_sale', 'href' => '/kasir'],
                    ['label' => 'Riwayat Penjualan', 'icon' => 'history', 'href' => '/sales-history'],
                    ['label' => 'Void Transaksi', 'icon' => 'assignment_return', 'href' => '/sales-void-refund'],
                ],
            ],
            'purchases' => [
                'title' => 'Pembelian ke Supplier',
                'items' => [
                    ['label' => 'Daftar Pembelian', 'icon' => 'receipt_long', 'href' => '/purchases/detail'],
                    ['label' => 'Buat Pembelian Baru', 'icon' => 'add_circle', 'href' => '/purchases/new'],
                    ['label' => 'Detail Pembelian', 'icon' => 'description', 'href' => '/purchases/detail'],
                    ['label' => 'Penerimaan Barang', 'icon' => 'inventory_2', 'href' => '/receiving'],
                    ['label' => 'Retur Pembelian', 'icon' => 'assignment_return', 'href' => '/purchase-returns'],
                ],
            ],
            'finance' => [
                'title' => 'Keuangan',
                'items' => [
                    ['label' => 'Keuangan', 'icon' => 'account_balance_wallet', 'href' => '/finance-summary'],
                    ['label' => 'Riwayat Transaksi Kas/Bank', 'icon' => 'history', 'href' => '/lock-periods'],
                ],
            ],
            'shift' => [
                'title' => 'Shift',
                'items' => [
                    ['label' => 'Buka Shift', 'icon' => 'update', 'href' => '/shift/open'],
                    ['label' => 'Tutup Shift', 'icon' => 'lock_clock', 'href' => '/shift/close'],
                    ['label' => 'Riwayat Shift', 'icon' => 'history', 'href' => '/shift/close-summary'],
                ],
            ],
            'reports' => [
                'title' => 'Laporan',
                'items' => [
                    ['label' => 'Ringkasan Penjualan', 'icon' => 'bar_chart', 'href' => '/analytics'],
                    ['label' => 'Laporan Laba Rugi', 'icon' => 'trending_up', 'href' => '/finance-summary'],
                    ['label' => 'Laporan Arus Kas', 'icon' => 'savings', 'href' => '/finance-summary'],
                    ['label' => 'Laporan Penjualan', 'icon' => 'list_alt', 'href' => '/sales-history'],
                    ['label' => 'Laporan Pembelian', 'icon' => 'shopping_cart', 'href' => '/purchases/detail'],
                    ['label' => 'Laporan Persediaan', 'icon' => 'inventory_2', 'href' => '/stock-card'],
                    ['label' => 'Laporan Pembayaran', 'icon' => 'payments', 'href' => '/finance-summary'],
                ],
            ],
            'settings' => [
                'title' => 'Pengaturan',
                'items' => [
                    ['label' => 'Toko', 'icon' => 'store', 'href' => '/branches'],
                    ['label' => 'Bagan Akun (COA)', 'icon' => 'account_balance', 'href' => '/settings/chart-of-accounts'],
                    ['label' => 'Database / Sinkronisasi', 'icon' => 'database', 'href' => '/menu'],
                    ['label' => 'Printer & Struk', 'icon' => 'print', 'href' => '/menu'],
                    ['label' => 'Manajemen Staff', 'icon' => 'person', 'href' => '/users'],
                    ['label' => 'E-Wallet', 'icon' => 'account_balance_wallet', 'href' => '/menu'],
                    ['label' => 'Notifikasi', 'icon' => 'notifications', 'href' => '/menu'],
                    ['label' => 'Pembayaran / Langganan', 'icon' => 'payments', 'href' => '/menu'],
                    ['label' => 'Pengaturan Transaksi', 'icon' => 'tune', 'href' => '/settings/tax-promo'],
                    ['label' => 'Valuation Persediaan', 'icon' => 'inventory', 'href' => '/settings/inventory-valuation'],
                    ['label' => 'Audit Valuation', 'icon' => 'history', 'href' => '/settings/inventory-valuation-audit'],
                    ['label' => 'Observability', 'icon' => 'monitoring', 'href' => '/settings/observability'],
                ],
            ],
            'other' => [
                'title' => 'Lainnya',
                'items' => [
                    ['label' => 'Referral', 'icon' => 'group', 'href' => '/menu'],
                    ['label' => 'Toko Online Saya', 'icon' => 'storefront', 'href' => '/menu'],
                    ['label' => 'Pusat Bantuan', 'icon' => 'support_agent', 'href' => '/menu'],
                ],
            ],
        ];
    }

    private function resolveTenantId(): ?string
    {
        if (! Schema::hasTable('tenants')) {
            return null;
        }

        return Tenant::query()->value('id');
    }
}