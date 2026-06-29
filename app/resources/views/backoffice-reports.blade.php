@extends('layouts.app')

@php
    $backofficeReportsTenant = \App\Support\TenantContext::get();
    $backofficeReportsUser = auth()->user();
    $backofficeReportsRole = null;

    if (! $backofficeReportsTenant && filled($backofficeReportsUser?->tenant_id)) {
        $backofficeReportsTenant = \App\Models\Tenant::query()->find($backofficeReportsUser->tenant_id);
    }

    if ($backofficeReportsTenant && $backofficeReportsUser) {
        $backofficeReportsRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $backofficeReportsTenant->id, (string) $backofficeReportsUser->id);

        if (! $backofficeReportsRole && filled($backofficeReportsUser->role ?? null)) {
            $backofficeReportsRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $backofficeReportsUser->role);
        }
    }

    abort_unless(in_array($backofficeReportsRole, ['supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canViewPurchaseReports = $backofficeReportsTenant
        ? app(\App\Services\SubscriptionService::class)->canAccessFeature($backofficeReportsTenant, 'reports.purchases_view')
        : false;
    $canViewStockCardReports = $backofficeReportsTenant
        ? app(\App\Services\SubscriptionService::class)->canAccessFeature($backofficeReportsTenant, 'reports.inventory_stock_card')
        : false;
@endphp

@section('page_title', 'Laporan')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto pb-8 lg:pl-72')

@section('header')
    <x-page-header title="Laporan" :showMenuButton="true" backUrl="/backoffice/dashboard" />
@endsection

@section('content')
    <div class="w-full space-y-4 p-4">
        <section class="app-compact-card">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Ringkasan</p>
            <div class="app-hub-grid mt-3">
                <a class="app-hub-tile group" href="/backoffice/reports/overview">
                    <div class="flex items-center gap-3">
                        <span class="app-hub-icon"><span class="material-symbols-outlined">dashboard</span></span>
                        <div>
                            <p class="text-sm font-semibold">Ringkasan Laporan</p>
                            <p class="text-xs text-slate-500">KPI utama & tren</p>
                        </div>
                    </div>
                </a>
                <a class="app-hub-tile group" href="/backoffice/reports/sales">
                    <div class="flex items-center gap-3">
                        <span class="app-hub-icon"><span class="material-symbols-outlined">list_alt</span></span>
                        <div>
                            <p class="text-sm font-semibold">Laporan Penjualan</p>
                            <p class="text-xs text-slate-500">Analitik & export</p>
                        </div>
                    </div>
                </a>
                <a class="app-hub-tile group" href="/backoffice/reports/receivables">
                    <div class="flex items-center gap-3">
                        <span class="app-hub-icon"><span class="material-symbols-outlined">request_quote</span></span>
                        <div>
                            <p class="text-sm font-semibold">Piutang</p>
                            <p class="text-xs text-slate-500">Tagihan customer & cicilan</p>
                        </div>
                    </div>
                </a>
                @if ($canViewPurchaseReports)
                    <a class="app-hub-tile group" href="/backoffice/reports/purchases">
                        <div class="flex items-center gap-3">
                            <span class="app-hub-icon"><span class="material-symbols-outlined">shopping_bag</span></span>
                            <div>
                                <p class="text-sm font-semibold">Laporan Pembelian</p>
                                <p class="text-xs text-slate-500">Riwayat PO & status pembelian</p>
                            </div>
                        </div>
                    </a>
                @endif
                <a class="app-hub-tile group" href="/backoffice/reports/inventory">
                    <div class="flex items-center gap-3">
                        <span class="app-hub-icon"><span class="material-symbols-outlined">inventory_2</span></span>
                        <div>
                            <p class="text-sm font-semibold">Laporan Persediaan</p>
                            <p class="text-xs text-slate-500">Stok, reorder, valuasi</p>
                        </div>
                    </div>
                </a>
                @if ($canViewStockCardReports)
                    <a class="app-hub-tile group" href="/backoffice/reports/inventory/stock-card">
                        <div class="flex items-center gap-3">
                            <span class="app-hub-icon"><span class="material-symbols-outlined">receipt_long</span></span>
                            <div>
                                <p class="text-sm font-semibold">Kartu Stok</p>
                                <p class="text-xs text-slate-500">Ledger per produk</p>
                            </div>
                        </div>
                    </a>
                @endif
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <div class="app-compact-card">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold uppercase tracking-widest text-slate-500">Aksi Cepat</h2>
                    <span class="text-[10px] font-semibold text-slate-400">Backoffice</span>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-800" href="/backoffice/reports/sales">Export Penjualan</a>
                    <a class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-800" href="/backoffice/reports/receivables">Kelola Piutang</a>
                    @if ($canViewPurchaseReports)
                        <a class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-800" href="/backoffice/reports/purchases">Review Pembelian</a>
                    @endif
                    <a class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-800" href="/backoffice/reports/inventory">Cek Stok Menipis</a>
                    <a class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-800" href="/backoffice/reports/overview">Lihat KPI Mingguan</a>
                    @if ($canViewStockCardReports)
                        <a class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-800" href="/backoffice/reports/inventory/stock-card">Audit Kartu Stok</a>
                    @endif
                </div>
            </div>

            <div class="app-compact-card">
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-500">Catatan</h2>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                    Gunakan laporan penjualan untuk kebutuhan analitik dan export. Laporan persediaan
                    fokus pada stok, reorder, dan valuasi. Semua laporan mengikuti hak akses portal.
                </p>
                <div class="mt-4 rounded-xl border border-dashed border-slate-300 p-3 text-xs text-slate-500 dark:border-slate-700">
                    Tips: gunakan filter periode untuk menjaga performa saat data besar.
                </div>
            </div>
        </section>
    </div>
@endsection
