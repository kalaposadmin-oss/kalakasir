@extends('layouts.app')

@php
    $sessionIsPosPortal = session('auth_portal', 'pos') === 'pos';
    $historyContext = $historyContext ?? ($sessionIsPosPortal ? 'pos' : 'backoffice');
    $isPosPortal = $historyContext === 'pos';
    $pageTitle = $historyContext === 'backoffice' ? 'Laporan Penjualan' : 'Riwayat';
    $historyTenant = \App\Support\TenantContext::get();
    $historyUser = auth()->user();
    if (! $historyTenant && filled($historyUser?->tenant_id)) {
        $historyTenant = \App\Models\Tenant::query()->find($historyUser->tenant_id);
    }
    $historyCanExportCsv = $historyTenant
        ? app(\App\Services\SubscriptionService::class)->canAccessFeature($historyTenant, 'reports.export_csv')
        : false;
    $historyCanDownloadPdf = $historyTenant
        ? app(\App\Services\SubscriptionService::class)->canAccessFeature($historyTenant, 'reports.download_pdf')
        : false;
    $from = (string) request()->query('from', '');
    $submenuBackUrl = null;
    if ($from !== '') {
        $normalizedFrom = ltrim($from, '/');
        if (str_starts_with($normalizedFrom, 'menu/')) {
            $submenuBackUrl = '/' . $normalizedFrom;
        }
    }
    $posHistoryBackUrl = $submenuBackUrl ?: pos_default_url();
@endphp

@section('page_title', $pageTitle)
@section('ui_mode', $isPosPortal ? 'pos' : 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-100 font-display')
@section('app_shell_class', $isPosPortal ? 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl' : '')
@section('main_class', $isPosPortal ? 'flex-1 overflow-y-auto pb-24 app-bottom-safe-space' : 'flex-1 overflow-y-auto pb-8 xl:pl-72')

@push('head')
        <style>
            body { font-family: "Inter", sans-serif; }
            .material-symbols-outlined {
                font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24;
            }
            .ios-shadow { box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.05); }
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
            .history-surface {
                border: 1px solid rgb(226 232 240 / 0.85);
                background: rgba(255, 255, 255, 0.92);
                backdrop-filter: blur(12px);
                box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.05);
            }
            .history-chip {
                position: relative;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                border-radius: 9999px;
                border: 1px solid rgb(226 232 240 / 0.9);
                background: rgb(248 250 252 / 0.95);
                padding: 0.65rem 0.95rem;
                font-size: 0.75rem;
                font-weight: 700;
                color: rgb(51 65 85);
                min-width: 0;
            }
            .history-chip--active {
                border-color: rgb(147 197 253);
                background: rgb(219 234 254 / 0.9);
                color: rgb(29 78 216);
            }
            #history-tabs {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            #tab-transaksi #sales-period {
                border: none;
                background: transparent;
                box-shadow: none;
                padding: 0;
            }
            #tab-transaksi #sales-period [data-period-custom] {
                flex-wrap: wrap;
            }
        </style>
    
@endpush

@section('header')
    @if ($historyContext === 'pos')
        <header class="sticky top-0 z-30 border-b border-slate-200/60 bg-white/80 px-4 py-4 backdrop-blur-xl dark:border-slate-800/60 dark:bg-slate-900/80">
            <div class="flex items-center justify-between gap-3">
                <a href="{{ $posHistoryBackUrl }}" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300" aria-label="Kembali">
                    <span class="material-symbols-outlined text-[20px]">arrow_back_ios_new</span>
                </a>
                <div class="min-w-0 flex-1">
                    <h2 class="truncate text-lg font-bold leading-tight tracking-tight text-slate-900 dark:text-slate-100">{{ $pageTitle }}</h2>
                </div>
                <button class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300" type="button" aria-label="Menu" data-menu-trigger>
                    <span class="material-symbols-outlined">menu</span>
                </button>
            </div>
        </header>
    @else
        <x-page-header
            :title="$pageTitle"
            subtitle="Analitik dan export laporan penjualan"
            :showMenuButton="true"
            backUrl="/backoffice/reports"
            :breadcrumbs="['Laporan']"
        />
    @endif
@endsection

@section('content')
    @if ($historyContext === 'pos')
        <div class="mx-auto w-full px-4 pt-4">
            <div class="history-surface rounded-3xl p-4">
                <div class="gap-3" id="history-tabs">
                    <button class="whitespace-nowrap rounded-full bg-primary px-4 py-2 text-sm font-bold text-white shadow-sm" data-tab="transaksi">Transaksi</button>
                    <button class="whitespace-nowrap rounded-full bg-slate-100 px-4 py-2 text-sm font-bold text-slate-500" data-tab="shift">Shift</button>
                </div>
            </div>
        </div>

        <div id="tab-transaksi" class="tab-panel">
        <section class="mx-auto w-full p-4 space-y-4">
        <div class="history-surface rounded-3xl p-4">
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <span class="material-symbols-outlined text-[22px]">calendar_month</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Periode Riwayat</p>
                        <h3 id="history-period-summary" class="truncate text-base font-bold text-slate-900">7 Hari Terakhir</h3>
                        <p id="history-filter-summary" class="truncate text-xs text-slate-500">Semua cabang, kasir, dan status</p>
                    </div>
                </div>
                <button id="history-filter-open" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm active:scale-95" type="button" aria-label="Buka filter pencarian" aria-haspopup="dialog" aria-controls="history-filter-sheet">
                    <span class="material-symbols-outlined">more_horiz</span>
                </button>
            </div>
        </div>
        </section>
        <section class="mx-auto w-full p-4">
        <div class="history-surface rounded-3xl p-4">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="min-w-0 flex items-center gap-2">
                    <input id="select-all" class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" />
                    <span class="truncate text-sm font-medium text-slate-600 dark:text-slate-400" id="select-label">Dipilih 0 dari 0</span>
                </div>
                @if ($historyCanExportCsv)
                    <button id="export-button" class="flex items-center gap-1.5 rounded-2xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-primary/20 active:scale-95 transition-transform">
                        <span class="material-symbols-outlined text-sm">file_export</span>
                        Ekspor
                    </button>
                @else
                    <span id="sales-history-export-upgrade-pos" class="text-xs font-semibold text-amber-700">Export CSV tersedia mulai paket Medium</span>
                @endif
            </div>
            <div class="space-y-3" id="sale-list"></div>
        </div>
        </section>
    </div>

    <div id="tab-shift" class="tab-panel hidden">
        <div class="mx-auto w-full px-4 pt-4">
            <div class="history-surface rounded-3xl p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                            <span class="material-symbols-outlined text-[22px]">history</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Periode Shift</p>
                            <h3 id="shift-period-summary" class="truncate text-base font-bold text-slate-900">7 Hari Terakhir</h3>
                            <p id="shift-filter-summary" class="truncate text-xs text-slate-500">Semua cabang, kasir, dan status</p>
                        </div>
                    </div>
                    <button id="shift-filter-open" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm active:scale-95" type="button" aria-label="Buka filter shift" aria-haspopup="dialog" aria-controls="shift-filter-sheet">
                        <span class="material-symbols-outlined">more_horiz</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="mx-auto w-full px-4 pt-4 pb-24 app-bottom-safe-space" id="shift-list"></div>
        <p id="shift-empty" class="mx-auto w-full px-4 pt-4 text-sm text-slate-400 hidden">Belum ada riwayat shift.</p>
    </div>

    <div id="shift-filter-sheet" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="shift-filter-title">
        <div id="shift-filter-overlay" class="absolute inset-0 bg-slate-950/40"></div>
        <div class="absolute bottom-0 left-1/2 flex max-h-[90vh] w-full max-w-[430px] -translate-x-1/2 flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl dark:bg-slate-900">
            <div class="border-b border-slate-100 px-4 pb-4 pt-3 dark:border-slate-800">
                <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-slate-200 dark:bg-slate-700"></div>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 id="shift-filter-title" class="text-lg font-bold text-slate-900 dark:text-slate-100">Filter Shift</h2>
                        <p class="mt-1 text-xs text-slate-500">Cari riwayat shift berdasarkan periode, petugas, dan selisih kas.</p>
                    </div>
                    <button id="shift-filter-dismiss" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300" type="button" aria-label="Tutup filter shift">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            </div>
            <div class="flex-1 space-y-4 overflow-y-auto p-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold uppercase tracking-[0.15em] text-slate-400">Periode</p>
                    <button id="shift-reset-filter" class="text-sm font-bold text-primary" type="button">Atur Ulang</button>
                </div>
                <x-period-filter id="shift-period" defaultPreset="last7" />
                <div class="space-y-3">
                    <p class="text-xs font-bold uppercase tracking-[0.15em] text-slate-400">Detail Filter Shift</p>
                    <label class="relative block">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                        <input id="shift-search" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-12 py-3 text-sm font-medium placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/10" placeholder="Cari kasir, cabang, atau tanggal..." type="text" />
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button class="history-chip shrink-0 relative" type="button">
                            <span class="material-symbols-outlined text-sm">store</span>
                            <span id="shift-branch-label">Semua Cabang</span>
                            <span class="material-symbols-outlined text-sm">expand_more</span>
                            <select id="shift-branch-filter" class="absolute inset-0 cursor-pointer opacity-0"></select>
                        </button>
                        <button class="history-chip shrink-0 relative" type="button">
                            <span class="material-symbols-outlined text-sm">person</span>
                            <span id="shift-cashier-label">Kasir</span>
                            <span class="material-symbols-outlined text-sm">expand_more</span>
                            <select id="shift-cashier-filter" class="absolute inset-0 cursor-pointer opacity-0"></select>
                        </button>
                        <button class="history-chip shrink-0 relative" type="button">
                            <span class="material-symbols-outlined text-sm">fact_check</span>
                            <span id="shift-status-label">Semua Status</span>
                            <span class="material-symbols-outlined text-sm">expand_more</span>
                            <select id="shift-status-filter" class="absolute inset-0 cursor-pointer opacity-0">
                                <option value="all">Semua Status</option>
                                <option value="balanced">Sesuai</option>
                                <option value="difference">Selisih</option>
                            </select>
                        </button>
                    </div>
                </div>
            </div>
            <div class="border-t border-slate-100 p-4 dark:border-slate-800">
                <button id="shift-filter-close" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-white shadow-lg shadow-primary/20 active:scale-95" type="button">
                    <span class="material-symbols-outlined text-lg">check</span>
                    Terapkan
                </button>
            </div>
        </div>
    </div>

    <div id="history-filter-sheet" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="history-filter-title">
        <div id="history-filter-overlay" class="absolute inset-0 bg-slate-950/40"></div>
        <div class="absolute bottom-0 left-1/2 flex max-h-[90vh] w-full max-w-[430px] -translate-x-1/2 flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl dark:bg-slate-900">
            <div class="border-b border-slate-100 px-4 pb-4 pt-3 dark:border-slate-800">
                <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-slate-200 dark:bg-slate-700"></div>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 id="history-filter-title" class="text-lg font-bold text-slate-900 dark:text-slate-100">Filter Pencarian</h2>
                        <p class="mt-1 text-xs text-slate-500">Sesuaikan periode, cabang, kasir, dan status.</p>
                    </div>
                    <button id="history-filter-dismiss" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300" type="button" aria-label="Tutup filter">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            </div>
            <div class="flex-1 space-y-4 overflow-y-auto p-4">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold uppercase tracking-[0.15em] text-slate-400">Periode</p>
                    <button id="reset-filter" class="text-sm font-bold text-primary" type="button">Atur Ulang</button>
                </div>
                <x-period-filter id="sales-period" defaultPreset="last7" />
                <div class="space-y-3">
                    <p class="text-xs font-bold uppercase tracking-[0.15em] text-slate-400">Detail Filter</p>
                    <div class="flex flex-wrap gap-2">
                        <button class="history-chip shrink-0 relative" type="button">
                            <span class="material-symbols-outlined text-sm">store</span>
                            <span id="branch-label">Semua Cabang</span>
                            <span class="material-symbols-outlined text-sm">expand_more</span>
                            <select id="branch-filter" class="absolute inset-0 cursor-pointer opacity-0"></select>
                        </button>
                        <button class="history-chip shrink-0 relative" type="button">
                            <span class="material-symbols-outlined text-sm">person</span>
                            <span id="cashier-label">Kasir</span>
                            <span class="material-symbols-outlined text-sm">expand_more</span>
                            <select id="cashier-filter" class="absolute inset-0 cursor-pointer opacity-0"></select>
                        </button>
                        <button class="history-chip shrink-0 relative" type="button">
                            <span class="material-symbols-outlined text-sm">check_circle</span>
                            <span id="status-label">Semua Status</span>
                            <span class="material-symbols-outlined text-sm">expand_more</span>
                            <select id="status-filter" class="absolute inset-0 cursor-pointer opacity-0">
                                <option value="all">Semua Status</option>
                                <option value="paid">Berhasil</option>
                                <option value="void">Dibatalkan</option>
                            </select>
                        </button>
                    </div>
                </div>
            </div>
            <div class="border-t border-slate-100 p-4 dark:border-slate-800">
                <button id="history-filter-close" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-white shadow-lg shadow-primary/20 active:scale-95" type="button">
                    <span class="material-symbols-outlined text-lg">check</span>
                    Terapkan
                </button>
            </div>
        </div>
    </div>
    @endif

    @if ($historyContext === 'backoffice')
    <div id="tab-laporan" class="tab-panel">
        <div class="mx-auto w-full max-w-7xl px-4 pt-4 pb-24 app-bottom-safe-space space-y-4 lg:px-8">
            <div class="space-y-3">
                <x-period-filter id="sales-period" defaultPreset="today" />
                <div class="flex gap-2 overflow-x-auto no-scrollbar">
                    <button class="flex shrink-0 items-center gap-2 rounded-full bg-primary/10 border border-primary/20 px-4 py-2 text-primary text-xs font-bold relative">
                        <span class="material-symbols-outlined text-sm">store</span>
                        <span id="branch-label">Semua Cabang</span>
                        <span class="material-symbols-outlined text-sm">expand_more</span>
                        <select id="branch-filter" class="absolute inset-0 opacity-0 cursor-pointer"></select>
                    </button>
                    <button class="flex shrink-0 items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-slate-700 text-xs font-semibold relative">
                        <span class="material-symbols-outlined text-sm">person</span>
                        <span id="cashier-label">Kasir</span>
                        <span class="material-symbols-outlined text-sm">expand_more</span>
                        <select id="cashier-filter" class="absolute inset-0 opacity-0 cursor-pointer"></select>
                    </button>
                    <button class="flex shrink-0 items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-slate-700 text-xs font-semibold relative">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        <span id="status-label">Berhasil</span>
                        <span class="material-symbols-outlined text-sm">expand_more</span>
                        <select id="status-filter" class="absolute inset-0 opacity-0 cursor-pointer">
                            <option value="completed">Berhasil</option>
                            <option value="void">Dibatalkan</option>
                            <option value="all">Semua Status</option>
                        </select>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
                <div class="rounded-2xl bg-white border border-slate-100 p-4 ios-shadow">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Gross Sales</p>
                    <p id="report-gross-sales" class="mt-2 text-xl font-bold text-slate-900">Rp 0</p>
                    <span class="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-500">Sebelum diskon</span>
                </div>
                <div class="rounded-2xl bg-white border border-slate-100 p-4 ios-shadow">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Transaksi</p>
                    <p id="report-total-transactions" class="mt-2 text-xl font-bold text-slate-900">0</p>
                    <span id="report-average-ticket" class="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-500">Avg: Rp 0</span>
                </div>
                <div class="rounded-2xl bg-white border border-slate-100 p-4 ios-shadow">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Diskon Item</p>
                    <p id="report-item-discount" class="mt-2 text-xl font-bold text-emerald-600">Rp 0</p>
                </div>
                <div class="rounded-2xl bg-white border border-slate-100 p-4 ios-shadow">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Diskon Transaksi</p>
                    <p id="report-transaction-discount" class="mt-2 text-xl font-bold text-emerald-600">Rp 0</p>
                </div>
                <div class="rounded-2xl bg-white border border-slate-100 p-4 ios-shadow">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Diskon</p>
                    <p id="report-total-discount" class="mt-2 text-xl font-bold text-emerald-600">Rp 0</p>
                </div>
                <div class="rounded-2xl bg-white border border-slate-100 p-4 ios-shadow">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Net Sales</p>
                    <p id="report-net-sales" class="mt-2 text-xl font-bold text-primary">Rp 0</p>
                </div>
                <div class="rounded-2xl bg-white border border-slate-100 p-4 ios-shadow">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Piutang Aktif</p>
                    <p id="report-receivable-active" class="mt-2 text-xl font-bold text-amber-700">Rp 0</p>
                </div>
                <div class="rounded-2xl bg-white border border-slate-100 p-4 ios-shadow">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Piutang Tempo</p>
                    <p id="report-receivable-overdue" class="mt-2 text-xl font-bold text-rose-600">Rp 0</p>
                </div>
                <div class="rounded-2xl bg-white border border-slate-100 p-4 ios-shadow">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Piutang Lunas</p>
                    <p id="report-receivable-paid" class="mt-2 text-xl font-bold text-emerald-600">Rp 0</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl bg-white border border-slate-100 p-4 ios-shadow">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400">Breakdown Pembayaran</h3>
                        <span id="report-grand-total" class="text-xs font-semibold text-slate-500">Grand total Rp 0</span>
                    </div>
                    <div id="report-payments" class="space-y-3"></div>
                </div>

                <div class="rounded-2xl bg-white border border-slate-100 p-4 ios-shadow">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400">Top Produk</h3>
                    <button class="text-xs font-semibold text-primary" type="button">Lihat Semua</button>
                </div>
                <div id="report-top-products" class="space-y-3"></div>
            </div>
            </div>

            <section class="rounded-2xl bg-white border border-slate-100 p-4 ios-shadow">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <input id="select-all" class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" />
                        <span class="text-sm font-medium text-slate-600" id="select-label">Pilih Semua (0)</span>
                    </div>
                    @if ($historyCanExportCsv)
                        <button id="export-button" class="flex items-center gap-1.5 bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold shadow-lg shadow-primary/20 active:scale-95 transition-transform">
                            <span class="material-symbols-outlined text-sm">file_export</span>
                            Ekspor CSV
                        </button>
                    @else
                        <span id="sales-history-export-upgrade-backoffice" class="text-xs font-semibold text-amber-700">Export CSV tersedia mulai paket Medium</span>
                    @endif
                </div>
                <div class="space-y-3" id="sale-list"></div>
            </section>

            @if ($historyCanDownloadPdf)
                <button id="report-download" class="w-full rounded-2xl border border-slate-200 bg-white py-4 text-sm font-semibold text-slate-700 ios-shadow" type="button">
                    <span class="material-symbols-outlined text-base align-middle mr-2">download</span>
                    Unduh Laporan PDF/Excel
                </button>
            @else
                <div id="sales-history-download-upgrade-backoffice" class="w-full rounded-2xl border border-dashed border-amber-200 bg-amber-50 px-4 py-4 text-sm font-semibold text-amber-700 ios-shadow">
                    Download PDF/Excel tersedia mulai paket Medium.
                </div>
            @endif
        </div>
    </div>

    @if ($historyCanDownloadPdf)
    <div id="report-modal" class="fixed inset-0 z-50 hidden">
        <div id="report-modal-overlay" class="absolute inset-0 bg-black/30"></div>
        <div class="absolute bottom-0 left-1/2 w-full max-w-[430px] -translate-x-1/2 rounded-t-3xl bg-white p-6 pb-8 lg:max-w-xl">
            <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-slate-200"></div>
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900">Unduh Laporan PDF</h3>
                <button id="report-modal-close" class="size-9 rounded-full bg-slate-100 flex items-center justify-center" type="button">
                    <span class="material-symbols-outlined text-slate-500">close</span>
                </button>
            </div>
            <p class="mt-4 text-xs font-bold uppercase tracking-widest text-slate-400">Pilih Jenis Laporan</p>
            <div class="mt-3 space-y-3">
                <button class="w-full rounded-2xl border-2 border-primary/60 bg-primary/10 px-4 py-3 text-left" type="button">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="size-10 rounded-2xl bg-primary/15 text-primary flex items-center justify-center">
                                <span class="material-symbols-outlined">bar_chart</span>
                            </span>
                            <div>
                                <p class="text-sm font-bold text-slate-900">Laporan Penjualan</p>
                                <p class="text-xs text-slate-500">Ringkasan item terjual & profit</p>
                            </div>
                        </div>
                        <span class="size-5 rounded-full border-2 border-primary bg-white"></span>
                    </div>
                </button>
                <button class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left" type="button">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="size-10 rounded-2xl bg-slate-100 text-slate-500 flex items-center justify-center">
                                <span class="material-symbols-outlined">payments</span>
                            </span>
                            <div>
                                <p class="text-sm font-bold text-slate-900">Laporan Pembayaran</p>
                                <p class="text-xs text-slate-500">Metode bayar & rekonsiliasi</p>
                            </div>
                        </div>
                        <span class="size-5 rounded-full border-2 border-slate-200"></span>
                    </div>
                </button>
            </div>

            <p class="mt-6 text-xs font-bold uppercase tracking-widest text-slate-400">Pilih Periode</p>
            <div class="mt-3 grid grid-cols-3 gap-2">
                <button class="rounded-xl border border-slate-200 bg-white py-2 text-xs font-semibold">Hari Ini</button>
                <button class="rounded-xl border-2 border-primary/50 bg-primary/10 py-2 text-xs font-semibold text-primary">Minggu Ini</button>
                <button class="rounded-xl border border-slate-200 bg-white py-2 text-xs font-semibold">Bulan Ini</button>
            </div>
            <button class="mt-3 w-full rounded-2xl border border-dashed border-slate-300 py-3 text-sm font-semibold text-slate-500" type="button">
                <span class="material-symbols-outlined text-base align-middle mr-2">calendar_month</span>
                Pilih Tanggal Custom
            </button>

            <button class="mt-6 w-full rounded-2xl bg-primary py-4 text-white text-sm font-bold" type="button">
                <span class="material-symbols-outlined text-base align-middle mr-2">picture_as_pdf</span>
                Unduh PDF
            </button>
            <p class="mt-3 text-center text-xs text-slate-400">Laporan akan disimpan di folder Download</p>
        </div>
    </div>
    @endif
    @endif

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const currentFrom = new URLSearchParams(window.location.search).get('from');

                const historyContext = @json($historyContext);
                const el = {
                    period: document.getElementById('sales-period'),
                    branch: document.getElementById('branch-filter'),
                    branchLabel: document.getElementById('branch-label'),
                    cashier: document.getElementById('cashier-filter'),
                    cashierLabel: document.getElementById('cashier-label'),
                    status: document.getElementById('status-filter'),
                    statusLabel: document.getElementById('status-label'),
                    list: document.getElementById('sale-list'),
                    selectAll: document.getElementById('select-all'),
                    selectLabel: document.getElementById('select-label'),
                    reset: document.getElementById('reset-filter'),
                    export: document.getElementById('export-button'),
                    tabs: document.querySelectorAll('#history-tabs [data-tab]'),
                    tabPanels: document.querySelectorAll('.tab-panel'),
                    shiftList: document.getElementById('shift-list'),
                    shiftEmpty: document.getElementById('shift-empty'),
                    shiftSearch: document.getElementById('shift-search'),
                    shiftPeriod: document.getElementById('shift-period'),
                    shiftBranch: document.getElementById('shift-branch-filter'),
                    shiftBranchLabel: document.getElementById('shift-branch-label'),
                    shiftCashier: document.getElementById('shift-cashier-filter'),
                    shiftCashierLabel: document.getElementById('shift-cashier-label'),
                    shiftStatus: document.getElementById('shift-status-filter'),
                    shiftStatusLabel: document.getElementById('shift-status-label'),
                    shiftFilterOpen: document.getElementById('shift-filter-open'),
                    shiftFilterSheet: document.getElementById('shift-filter-sheet'),
                    shiftFilterOverlay: document.getElementById('shift-filter-overlay'),
                    shiftFilterDismiss: document.getElementById('shift-filter-dismiss'),
                    shiftFilterClose: document.getElementById('shift-filter-close'),
                    shiftReset: document.getElementById('shift-reset-filter'),
                    shiftPeriodSummary: document.getElementById('shift-period-summary'),
                    shiftFilterSummary: document.getElementById('shift-filter-summary'),
                    reportGrossSales: document.getElementById('report-gross-sales'),
                    reportTransactions: document.getElementById('report-total-transactions'),
                    reportAverageTicket: document.getElementById('report-average-ticket'),
                    reportItemDiscount: document.getElementById('report-item-discount'),
                    reportTransactionDiscount: document.getElementById('report-transaction-discount'),
                    reportTotalDiscount: document.getElementById('report-total-discount'),
                    reportNetSales: document.getElementById('report-net-sales'),
                    reportReceivableActive: document.getElementById('report-receivable-active'),
                    reportReceivableOverdue: document.getElementById('report-receivable-overdue'),
                    reportReceivablePaid: document.getElementById('report-receivable-paid'),
                    reportGrandTotal: document.getElementById('report-grand-total'),
                    reportPayments: document.getElementById('report-payments'),
                    reportTopProducts: document.getElementById('report-top-products'),
                    reportDownload: document.getElementById('report-download'),
                    reportModal: document.getElementById('report-modal'),
                    reportModalOverlay: document.getElementById('report-modal-overlay'),
                    reportModalClose: document.getElementById('report-modal-close'),
                    filterOpen: document.getElementById('history-filter-open'),
                    filterSheet: document.getElementById('history-filter-sheet'),
                    filterOverlay: document.getElementById('history-filter-overlay'),
                    filterDismiss: document.getElementById('history-filter-dismiss'),
                    filterClose: document.getElementById('history-filter-close'),
                    periodSummary: document.getElementById('history-period-summary'),
                    filterSummary: document.getElementById('history-filter-summary'),
                };

                const supportsSalesList = Boolean(el.list);
                const supportsFilters = Boolean(el.branch && el.cashier && el.status && el.period);
                const supportsSelection = Boolean(el.selectAll && el.selectLabel);
                const defaultPeriodPreset = historyContext === 'pos' ? 'last7' : 'today';
                const readInitialPeriod = () => {
                    const query = new URLSearchParams(window.location.search);

                    return {
                        preset: query.get('preset') || defaultPeriodPreset,
                        start_date: query.get('start_date') || '',
                        end_date: query.get('end_date') || '',
                    };
                };

                const state = {
                    branches: [],
                    cashiers: [],
                    sales: [],
                    shifts: [],
                    summary: null,
                    period: readInitialPeriod(),
                    shiftPeriod: readInitialPeriod(),
                    tab: 'transaksi',
                    shiftLoaded: false,
                    selectedSales: new Set(),
                };

                const updateSelectionMeta = () => {
                    if (!supportsSelection) {
                        return;
                    }
                    const total = state.sales.length;
                    const selected = state.selectedSales.size;
                    el.selectLabel.textContent = `Dipilih ${selected} dari ${total}`;
                    if (!total) {
                        el.selectAll.checked = false;
                        el.selectAll.indeterminate = false;
                        return;
                    }
                    el.selectAll.checked = selected > 0 && selected === total;
                    el.selectAll.indeterminate = selected > 0 && selected < total;
                };

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                }).format(new Date(value));

                const parseDateOnly = (value) => {
                    if (!value) {
                        return null;
                    }
                    const parsed = new Date(`${value}T00:00:00+07:00`);
                    return Number.isNaN(parsed.getTime()) ? null : parsed;
                };

                const formatDateRangeLabel = (startDate, endDate) => {
                    const start = parseDateOnly(startDate);
                    const end = parseDateOnly(endDate || startDate);

                    if (!start || !end) {
                        return 'Periode Custom';
                    }

                    const monthFormatter = new Intl.DateTimeFormat('id-ID', { month: 'short', timeZone: 'Asia/Jakarta' });
                    const yearFormatter = new Intl.DateTimeFormat('id-ID', { year: 'numeric', timeZone: 'Asia/Jakarta' });
                    const startDay = new Intl.DateTimeFormat('id-ID', { day: '2-digit', timeZone: 'Asia/Jakarta' }).format(start);
                    const endDay = new Intl.DateTimeFormat('id-ID', { day: '2-digit', timeZone: 'Asia/Jakarta' }).format(end);
                    const startMonth = monthFormatter.format(start);
                    const endMonth = monthFormatter.format(end);
                    const startYear = yearFormatter.format(start);
                    const endYear = yearFormatter.format(end);

                    if (startDate === (endDate || startDate)) {
                        return `${endDay} ${endMonth} ${endYear}`;
                    }

                    if (startMonth === endMonth && startYear === endYear) {
                        return `${startDay}-${endDay} ${endMonth} ${endYear}`;
                    }

                    if (startYear === endYear) {
                        return `${startDay} ${startMonth} - ${endDay} ${endMonth} ${endYear}`;
                    }

                    return `${startDay} ${startMonth} ${startYear} - ${endDay} ${endMonth} ${endYear}`;
                };

                const formatCsvDate = (value) => new Intl.DateTimeFormat('sv-SE', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    timeZone: 'Asia/Jakarta',
                }).format(new Date(value)).replace(' ', ' ') + ' WIB';

                const formatInvoice = (sale) => {
                    const date = new Date(sale.created_at);
                    const y = date.getFullYear();
                    const m = String(date.getMonth() + 1).padStart(2, '0');
                    const d = String(date.getDate()).padStart(2, '0');
                    return `INV/${y}${m}${d}/${String(sale.id).slice(0, 3).toUpperCase()}`;
                };

                const paymentLabel = (sale) => {
                    if (!sale.payments || sale.payments.length === 0) {
                        return '-';
                    }
                    const methods = [...new Set(sale.payments.map((item) => item.method))];
                    return methods.map((method) => {
                        if (method === 'qris') return 'QRIS';
                        if (method === 'cash') return 'Tunai';
                        if (method === 'bank') return 'Debit';
                        return method;
                    }).join(', ');
                };

                const receivableBadge = (sale) => {
                    if (!sale.receivable) {
                        return '';
                    }

                    if (sale.receivable.status === 'paid') {
                        return '<span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-bold uppercase">Piutang lunas</span>';
                    }

                    if (sale.receivable.is_overdue) {
                        return '<span class="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 text-[10px] font-bold uppercase">Piutang tempo</span>';
                    }

                    return '<span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[10px] font-bold uppercase">Piutang</span>';
                };

                const channelBadge = (sale) => {
                    const isOnline = sale.sales_channel === 'online';
                    const classes = isOnline ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600';
                    const label = sale.sales_channel_label || (isOnline ? 'Online' : 'Kasir / Offline');

                    return `<span class="px-2 py-0.5 rounded-full ${classes} text-[10px] font-bold uppercase">${label}</span>`;
                };

                const renderList = () => {
                    if (!supportsSalesList) {
                        updateReportSummary();
                        return;
                    }
                    el.list.innerHTML = '';

                    const validIds = new Set(state.sales.map((sale) => String(sale.id)));
                    state.selectedSales = new Set(Array.from(state.selectedSales).filter((id) => validIds.has(String(id))));

                    if (!state.sales.length) {
                        el.list.innerHTML = '<div class="text-sm text-slate-400">Belum ada transaksi.</div>';
                        updateSelectionMeta();
                        return;
                    }

                    state.sales.forEach((sale) => {
                        const statusBadge = sale.status === 'void'
                            ? 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400'
                            : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400';
                        const statusLabel = sale.status === 'void' ? 'Dibatalkan' : 'Berhasil';
                        const totalClass = sale.status === 'void' ? 'text-slate-400 line-through' : 'text-slate-900 dark:text-slate-100';

                        const row = document.createElement('a');
                        const detailParams = new URLSearchParams();
                        if (currentFrom) {
                            detailParams.set('from', currentFrom);
                        } else {
                            const canonicalHistoryPath = historyContext === 'backoffice'
                                ? '/backoffice/reports/sales'
                                : '/pos/history';
                            detailParams.set('from', canonicalHistoryPath);
                        }
                        const detailBasePath = historyContext === 'backoffice'
                            ? `/backoffice/sales/${sale.id}`
                            : `/pos/sales/${sale.id}`;
                        row.href = `${detailBasePath}?${detailParams.toString()}`;
                        row.className = `flex items-start justify-between gap-3 rounded-2xl bg-white dark:bg-slate-900 p-4 ios-shadow border border-slate-100 dark:border-slate-800 active:bg-slate-50 transition-colors${sale.status === 'void' ? ' opacity-80' : ''}`;
                        row.innerHTML = `
                            <div class="flex min-w-0 items-start gap-3">
                                <input class="mt-1 rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" onclick="event.stopPropagation()" />
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate font-bold text-slate-900 dark:text-slate-100">${formatInvoice(sale)}</p>
                                        <span class="px-2 py-0.5 rounded-full ${statusBadge} text-[10px] font-bold uppercase">${statusLabel}</span>
                                        ${channelBadge(sale)}
                                        ${receivableBadge(sale)}
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1">${formatDate(sale.created_at)} • ${sale.item_count} Produk</p>
                                </div>
                            </div>
                            <div class="shrink-0 pl-2 text-right">
                                <p class="text-base font-bold tracking-tight ${totalClass}">${formatCurrency(sale.total)}</p>
                                <p class="text-[10px] text-slate-400">${paymentLabel(sale)}</p>
                                ${sale.receivable ? `<p class="text-[10px] font-semibold text-amber-700">Sisa ${formatCurrency(sale.receivable.remaining_amount)}</p>` : ''}
                            </div>
                        `;
                        const checkbox = row.querySelector('input[type="checkbox"]');
                        const saleId = String(sale.id);
                        checkbox.checked = state.selectedSales.has(saleId);
                        checkbox.addEventListener('change', (event) => {
                            if (event.target.checked) {
                                state.selectedSales.add(saleId);
                            } else {
                                state.selectedSales.delete(saleId);
                            }
                            updateSelectionMeta();
                        });
                        el.list.appendChild(row);
                    });

                    updateSelectionMeta();

                    updateReportSummary();
                };

                const renderShiftList = () => {
                    if (!el.shiftList) {
                        return;
                    }
                    const term = (el.shiftSearch?.value || '').toLowerCase();
                    const rows = state.shifts.filter((row) => {
                        const label = `${row.cashier || ''} ${row.branch || ''} ${row.opened_at || ''}`.toLowerCase();
                        return !term || label.includes(term);
                    });

                    el.shiftList.innerHTML = '';
                    if (!rows.length) {
                        el.shiftEmpty?.classList.remove('hidden');
                        return;
                    }
                    el.shiftEmpty?.classList.add('hidden');

                    rows.forEach((row) => {
                        const opened = formatDate(row.opened_at);
                        const isBalanced = (row.difference || 0) === 0;
                        const statusLabel = isBalanced ? 'Sesuai' : 'Selisih';
                        const statusClass = isBalanced
                            ? 'bg-emerald-100 text-emerald-600'
                            : 'bg-amber-100 text-amber-600';
                        const card = document.createElement('a');
                        card.href = `/pos/shifts/${encodeURIComponent(row.id)}/summary?from=${encodeURIComponent('/pos/shifts/summary')}`;
                        card.className = 'block rounded-2xl bg-white border border-slate-100 p-4 ios-shadow';
                        card.innerHTML = `
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm font-bold text-slate-900">${opened}</p>
                                    <p class="text-xs text-slate-400">${row.shift_name || ''}</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-widest ${statusClass}">${statusLabel}</span>
                            </div>
                            <div class="mt-3 rounded-2xl bg-slate-50 p-3 flex items-center gap-3">
                                <div class="size-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                    <span class="material-symbols-outlined">person</span>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Kasir</p>
                                    <p class="text-sm font-semibold text-slate-900">${row.cashier || '-'}</p>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Saldo Awal</p>
                                    <p class="text-sm font-semibold text-slate-900">${formatCurrency(row.opening_cash || 0)}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Saldo Akhir</p>
                                    <p class="text-sm font-semibold text-primary">${formatCurrency(row.closing_cash || 0)}</p>
                                </div>
                            </div>
                        `;
                        el.shiftList.appendChild(card);
                    });
                };

                const formatPaymentMethod = (method) => {
                    if (method === 'cash') return 'Tunai';
                    if (method === 'bank') return 'Bank / EDC';
                    if (method === 'qris') return 'QRIS';
                    return method || '-';
                };

                const updateReportSummary = () => {
                    if (!el.reportGrossSales) {
                        return;
                    }
                    const summary = state.summary || {
                        gross_sales: 0,
                        item_discount: 0,
                        transaction_discount: 0,
                        total_discount: 0,
                        net_sales: 0,
                        grand_total: 0,
                        transaction_count: 0,
                        average_ticket: 0,
                        payments: [],
                        top_products: [],
                        receivables: {},
                    };
                    const receivables = summary.receivables || {};
                    el.reportGrossSales.textContent = formatCurrency(summary.gross_sales || 0);
                    el.reportTransactions.textContent = summary.transaction_count || 0;
                    el.reportAverageTicket.textContent = `Avg: ${formatCurrency(summary.average_ticket || 0)}`;
                    el.reportItemDiscount.textContent = formatCurrency(summary.item_discount || 0);
                    el.reportTransactionDiscount.textContent = formatCurrency(summary.transaction_discount || 0);
                    el.reportTotalDiscount.textContent = formatCurrency(summary.total_discount || 0);
                    el.reportNetSales.textContent = formatCurrency(summary.net_sales || 0);
                    if (el.reportReceivableActive) {
                        el.reportReceivableActive.textContent = formatCurrency(receivables.active_total || 0);
                    }
                    if (el.reportReceivableOverdue) {
                        el.reportReceivableOverdue.textContent = formatCurrency(receivables.overdue_total || 0);
                    }
                    if (el.reportReceivablePaid) {
                        el.reportReceivablePaid.textContent = formatCurrency(receivables.paid_total || 0);
                    }
                    if (el.reportGrandTotal) {
                        el.reportGrandTotal.textContent = `Grand total ${formatCurrency(summary.grand_total || 0)}`;
                    }

                    if (el.reportPayments) {
                        if (!summary.payments || !summary.payments.length) {
                            el.reportPayments.innerHTML = '<p class="text-sm text-slate-400">Belum ada pembayaran pada periode ini.</p>';
                        } else {
                            el.reportPayments.innerHTML = '';
                            summary.payments.forEach((payment) => {
                                const row = document.createElement('div');
                                row.className = 'rounded-2xl border border-slate-100 bg-slate-50 p-3';
                                row.innerHTML = `
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">${formatPaymentMethod(payment.method)}</p>
                                            <p class="text-xs text-slate-500">${payment.percent}% dari total pembayaran</p>
                                        </div>
                                        <p class="text-sm font-bold text-slate-900">${formatCurrency(payment.amount || 0)}</p>
                                    </div>
                                `;
                                el.reportPayments.appendChild(row);
                            });
                        }
                    }

                    if (el.reportTopProducts) {
                        if (!summary.top_products || !summary.top_products.length) {
                            el.reportTopProducts.innerHTML = '<p class="text-sm text-slate-400">Belum ada data top produk.</p>';
                        } else {
                            el.reportTopProducts.innerHTML = '';
                            summary.top_products.forEach((product) => {
                                const row = document.createElement('div');
                                row.className = 'flex items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-3';
                                row.innerHTML = `
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-slate-900">${product.name}</p>
                                        <p class="text-xs text-slate-500">${product.qty_sold} item terjual</p>
                                    </div>
                                    <p class="text-sm font-bold text-slate-900">${formatCurrency(product.net_sales || 0)}</p>
                                `;
                                el.reportTopProducts.appendChild(row);
                            });
                        }
                    }
                };

                const loadBootstrap = async () => {
                    return window.PosUi.getBootstrap({ headers, ttlMs: 20000 }).catch(() => null);
                };

                const loadSales = async () => {
                    const params = new URLSearchParams({
                        preset: state.period.preset || defaultPeriodPreset,
                        start_date: state.period.start_date || '',
                        end_date: state.period.end_date || '',
                        branch_id: el.branch?.value || '',
                        cashier_id: el.cashier?.value || '',
                        status: el.status?.value || 'completed',
                    });
                    const response = await fetch(`/api/v1/sales-history?${params.toString()}`, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat penjualan.');
                    }
                    const payload = await response.json().catch(() => ({}));
                    return window.PosUi.unwrapApiPayload(payload) || {};
                };

                const updateQuery = () => {
                    if (!supportsFilters) {
                        return;
                    }
                    const params = new URLSearchParams();
                    params.set('preset', state.period.preset || defaultPeriodPreset);
                    if (state.period.start_date) params.set('start_date', state.period.start_date);
                    if (state.period.end_date) params.set('end_date', state.period.end_date);
                    params.set('page', '1');

                    const entries = {
                        branch_id: el.branch.value || '',
                        cashier_id: el.cashier.value || '',
                        status: el.status.value || 'all',
                    };

                    Object.keys(entries).forEach((key) => {
                        if (entries[key]) {
                            params.set(key, entries[key]);
                        }
                    });

                    history.replaceState(null, '', `${window.location.pathname}?${params.toString()}`);
                };

                const updateShiftQuery = () => {
                    if (!el.shiftPeriod) {
                        return;
                    }

                    const params = new URLSearchParams(window.location.search);
                    params.set('tab', 'shift');
                    params.set('preset', state.shiftPeriod.preset || defaultPeriodPreset);
                    params.set('page', '1');

                    if (state.shiftPeriod.start_date) {
                        params.set('start_date', state.shiftPeriod.start_date);
                    } else {
                        params.delete('start_date');
                    }

                    if (state.shiftPeriod.end_date) {
                        params.set('end_date', state.shiftPeriod.end_date);
                    } else {
                        params.delete('end_date');
                    }

                    const entries = {
                        shift_branch_id: el.shiftBranch?.value || '',
                        shift_cashier_id: el.shiftCashier?.value || '',
                        shift_status: el.shiftStatus?.value || 'all',
                        shift_q: el.shiftSearch?.value?.trim() || '',
                    };

                    Object.keys(entries).forEach((key) => {
                        if (entries[key] && entries[key] !== 'all') {
                            params.set(key, entries[key]);
                        } else {
                            params.delete(key);
                        }
                    });

                    history.replaceState(null, '', `${window.location.pathname}?${params.toString()}`);
                };

                const loadCashiers = async () => {
                    const response = await fetch('/api/v1/cashiers', { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const payload = await response.json().catch(() => ({}));
                    return window.PosUi.unwrapApiList(payload, 'cashiers');
                };

                const loadShiftHistory = async () => {
                    const params = new URLSearchParams({
                        preset: state.shiftPeriod.preset || defaultPeriodPreset,
                        start_date: state.shiftPeriod.start_date || '',
                        end_date: state.shiftPeriod.end_date || '',
                        branch_id: el.shiftBranch?.value || '',
                        cashier_id: el.shiftCashier?.value || '',
                        status: el.shiftStatus?.value || 'all',
                        q: el.shiftSearch?.value?.trim() || '',
                        limit: '100',
                    });
                    const response = await fetch(`/api/v1/shifts/closed?${params.toString()}`, { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const payload = await response.json().catch(() => ({}));
                    return window.PosUi.unwrapApiList(payload, 'shifts');
                };

                const syncShiftLabels = () => {
                    const periodText = state.shiftPeriod.preset === 'custom'
                        ? formatDateRangeLabel(state.shiftPeriod.start_date, state.shiftPeriod.end_date)
                        : el.shiftPeriod?.querySelector('[data-period-summary]')?.textContent?.trim()
                        || (state.shiftPeriod.preset === 'last7' ? '7 Hari Terakhir' : 'Hari Ini');

                    if (el.shiftPeriodSummary) {
                        el.shiftPeriodSummary.textContent = periodText;
                    }
                    if (el.shiftBranchLabel && el.shiftBranch) {
                        el.shiftBranchLabel.textContent = el.shiftBranch.options[el.shiftBranch.selectedIndex]?.textContent || 'Semua Cabang';
                    }
                    if (el.shiftCashierLabel && el.shiftCashier) {
                        el.shiftCashierLabel.textContent = el.shiftCashier.options[el.shiftCashier.selectedIndex]?.textContent || 'Kasir';
                    }
                    if (el.shiftStatusLabel && el.shiftStatus) {
                        el.shiftStatusLabel.textContent = el.shiftStatus.options[el.shiftStatus.selectedIndex]?.textContent || 'Semua Status';
                    }
                    if (el.shiftFilterSummary) {
                        const activeDetails = [];
                        if (el.shiftSearch?.value?.trim()) activeDetails.push(`Cari: ${el.shiftSearch.value.trim()}`);
                        if (el.shiftBranch?.value) activeDetails.push(el.shiftBranchLabel?.textContent || 'Cabang dipilih');
                        if (el.shiftCashier?.value) activeDetails.push(el.shiftCashierLabel?.textContent || 'Kasir dipilih');
                        if (el.shiftStatus?.value && el.shiftStatus.value !== 'all') activeDetails.push(el.shiftStatusLabel?.textContent || 'Status dipilih');
                        el.shiftFilterSummary.textContent = activeDetails.length
                            ? activeDetails.join(' / ')
                            : 'Semua cabang, kasir, dan status';
                    }
                };

                const refreshShift = async () => {
                    if (!el.shiftList) {
                        return;
                    }
                    syncShiftLabels();
                    try {
                        state.shifts = await loadShiftHistory();
                        renderShiftList();
                        updateShiftQuery();
                    } catch (_) {
                        el.shiftList.innerHTML = '<div class="text-sm text-slate-400">Gagal memuat riwayat shift.</div>';
                    }
                };

                const setActiveTab = (tabId) => {
                    state.tab = tabId;
                    el.tabs.forEach((btn) => {
                        const isActive = btn.dataset.tab === tabId;
                        btn.className = isActive
                            ? 'whitespace-nowrap rounded-full bg-primary px-4 py-2 text-sm font-bold text-white shadow-sm'
                            : 'whitespace-nowrap rounded-full bg-slate-100 px-4 py-2 text-sm font-bold text-slate-500';
                    });
                    el.tabPanels.forEach((panel) => {
                        panel.classList.toggle('hidden', panel.id !== `tab-${tabId}`);
                    });

                    if (tabId === 'shift' && !state.shiftLoaded) {
                        state.shiftLoaded = true;
                        refreshShift();
                    }
                };

                const buildOptions = (select, items, labelKey, includeAllLabel) => {
                    if (!select) {
                        return;
                    }
                    select.innerHTML = '';
                    if (includeAllLabel) {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = includeAllLabel;
                        select.appendChild(option);
                    }
                    items.forEach((item) => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item[labelKey];
                        select.appendChild(option);
                    });
                };

                const syncLabels = () => {
                    if (!supportsFilters) {
                        return;
                    }
                    el.branchLabel.textContent = el.branch.options[el.branch.selectedIndex]?.textContent || 'Semua Cabang';
                    el.cashierLabel.textContent = el.cashier.options[el.cashier.selectedIndex]?.textContent || 'Kasir';
                    el.statusLabel.textContent = el.status.options[el.status.selectedIndex]?.textContent || 'Status';

                    const periodText = state.period.preset === 'custom'
                        ? formatDateRangeLabel(state.period.start_date, state.period.end_date)
                        : el.period?.querySelector('[data-period-summary]')?.textContent?.trim()
                        || (state.period.preset === 'last7' ? '7 Hari Terakhir' : 'Hari Ini');
                    if (el.periodSummary) {
                        el.periodSummary.textContent = periodText;
                    }
                    if (el.filterSummary) {
                        const activeDetails = [];
                        if (el.branch.value) activeDetails.push(el.branchLabel.textContent);
                        if (el.cashier.value) activeDetails.push(el.cashierLabel.textContent);
                        if (el.status.value && el.status.value !== 'all') activeDetails.push(el.statusLabel.textContent);
                        el.filterSummary.textContent = activeDetails.length
                            ? activeDetails.join(' / ')
                            : 'Semua cabang, kasir, dan status';
                    }
                };

                const openFilterSheet = () => {
                    if (!el.filterSheet) {
                        return;
                    }
                    el.filterSheet.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                };

                const closeFilterSheet = () => {
                    if (!el.filterSheet) {
                        return;
                    }
                    el.filterSheet.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                };

                const openShiftFilterSheet = () => {
                    if (!el.shiftFilterSheet) {
                        return;
                    }
                    el.shiftFilterSheet.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                    setTimeout(() => el.shiftSearch?.focus(), 80);
                };

                const closeShiftFilterSheet = () => {
                    if (!el.shiftFilterSheet) {
                        return;
                    }
                    el.shiftFilterSheet.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                };

                const refresh = async () => {
                    syncLabels();
                    try {
                        const data = await loadSales();
                        state.sales = data.sales || [];
                        state.summary = data.summary || null;
                        state.selectedSales.clear();
                        renderList();
                        updateQuery();
                    } catch (error) {
                        if (supportsSalesList) {
                            el.list.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                        }
                        state.selectedSales.clear();
                        updateSelectionMeta();
                    }
                };

                el.branch?.addEventListener('change', refresh);
                el.cashier?.addEventListener('change', refresh);
                el.status?.addEventListener('change', refresh);
                el.filterOpen?.addEventListener('click', openFilterSheet);
                el.filterOverlay?.addEventListener('click', closeFilterSheet);
                el.filterDismiss?.addEventListener('click', closeFilterSheet);
                el.filterClose?.addEventListener('click', closeFilterSheet);
                el.shiftFilterOpen?.addEventListener('click', openShiftFilterSheet);
                el.shiftFilterOverlay?.addEventListener('click', closeShiftFilterSheet);
                el.shiftFilterDismiss?.addEventListener('click', closeShiftFilterSheet);
                el.shiftFilterClose?.addEventListener('click', closeShiftFilterSheet);
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && !el.filterSheet?.classList.contains('hidden')) {
                        closeFilterSheet();
                    }
                    if (event.key === 'Escape' && !el.shiftFilterSheet?.classList.contains('hidden')) {
                        closeShiftFilterSheet();
                    }
                });

                el.reset?.addEventListener('click', () => {
                    if (!supportsFilters) {
                        return;
                    }
                    el.branch.value = '';
                    el.cashier.value = '';
                    el.status.value = historyContext === 'backoffice' ? 'completed' : 'all';
                    el.period?.querySelector('[data-period-reset]')?.click();
                    refresh();
                });

                el.period?.addEventListener('period:change', (event) => {
                    state.period = event.detail;
                    refresh();
                });

                el.shiftPeriod?.addEventListener('period:change', (event) => {
                    state.shiftPeriod = event.detail;
                    if (state.tab === 'shift') {
                        refreshShift();
                    } else {
                        syncShiftLabels();
                    }
                });

                el.shiftBranch?.addEventListener('change', refreshShift);
                el.shiftCashier?.addEventListener('change', refreshShift);
                el.shiftStatus?.addEventListener('change', refreshShift);
                let shiftSearchTimer = null;
                el.shiftSearch?.addEventListener('input', () => {
                    syncShiftLabels();
                    clearTimeout(shiftSearchTimer);
                    shiftSearchTimer = setTimeout(() => {
                        if (state.tab === 'shift') {
                            refreshShift();
                        } else {
                            updateShiftQuery();
                        }
                    }, 250);
                });

                el.shiftReset?.addEventListener('click', () => {
                    if (el.shiftBranch) el.shiftBranch.value = '';
                    if (el.shiftCashier) el.shiftCashier.value = '';
                    if (el.shiftStatus) el.shiftStatus.value = 'all';
                    if (el.shiftSearch) el.shiftSearch.value = '';
                    el.shiftPeriod?.querySelector('[data-period-reset]')?.click();
                    refreshShift();
                });

                el.export?.addEventListener('click', () => {
                    if (!state.sales.length) {
                        return;
                    }

                    const selectedRows = state.selectedSales.size
                        ? state.sales.filter((sale) => state.selectedSales.has(String(sale.id)))
                        : state.sales;

                    if (!selectedRows.length) {
                        return;
                    }

                    const rows = [
                        ['Invoice', 'Tanggal', 'Channel', 'Kasir', 'Cabang', 'Status', 'Gross Sales', 'Item Discount', 'Transaction Discount', 'Total Discount', 'Net Sales', 'Grand Total', 'Metode'],
                        ...selectedRows.map((sale) => [
                            formatInvoice(sale),
                            formatCsvDate(sale.created_at),
                            sale.sales_channel_label || '-',
                            sale.cashier_name || '-',
                            sale.branch_name || '-',
                            sale.status,
                            sale.gross_sales || 0,
                            sale.item_discount || 0,
                            sale.transaction_discount || 0,
                            sale.total_discount || 0,
                            sale.net_sales || 0,
                            sale.total || 0,
                            paymentLabel(sale),
                        ]),
                    ];
                    const csv = rows.map((row) => row.map((cell) => `"${cell}"`).join(',')).join('\n');
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = state.selectedSales.size ? 'riwayat-penjualan-selected.csv' : 'riwayat-penjualan.csv';
                    link.click();
                    URL.revokeObjectURL(url);
                });

                el.selectAll?.addEventListener('change', (event) => {
                    const checked = event.target.checked;
                    if (!state.sales.length) {
                        updateSelectionMeta();
                        return;
                    }
                    if (checked) {
                        state.sales.forEach((sale) => state.selectedSales.add(String(sale.id)));
                    } else {
                        state.selectedSales.clear();
                    }

                    document.querySelectorAll('#sale-list input[type="checkbox"]').forEach((checkbox) => {
                        checkbox.checked = checked;
                    });
                    updateSelectionMeta();
                });

                el.tabs.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const tabId = btn.dataset.tab || 'transaksi';
                        setActiveTab(tabId);
                        const params = new URLSearchParams(window.location.search);
                        params.set('tab', tabId);
                        history.replaceState(null, '', `${window.location.pathname}?${params.toString()}`);
                    });
                });

                el.reportDownload?.addEventListener('click', () => {
                    el.reportModal?.classList.remove('hidden');
                });

                el.reportModalClose?.addEventListener('click', () => {
                    el.reportModal?.classList.add('hidden');
                });

                el.reportModal?.addEventListener('click', (event) => {
                    if (event.target === el.reportModalOverlay) {
                        el.reportModal.classList.add('hidden');
                    }
                });

                (async () => {
                    const tabParam = new URLSearchParams(window.location.search).get('tab');
                    const defaultTab = historyContext === 'backoffice' ? 'laporan' : 'transaksi';
                    setActiveTab(tabParam || defaultTab);

                    const bootstrap = await loadBootstrap();
                    state.branches = bootstrap?.branches || [];
                    buildOptions(el.branch, state.branches, 'name', 'Semua Cabang');
                    buildOptions(el.shiftBranch, state.branches, 'name', 'Semua Cabang');

                    const cashiers = await loadCashiers();
                    state.cashiers = cashiers.map((item) => ({ id: item.id, name: item.name }));
                    buildOptions(el.cashier, state.cashiers, 'name', 'Kasir');
                    buildOptions(el.shiftCashier, state.cashiers, 'name', 'Kasir');
                    if (el.status && historyContext === 'backoffice' && !el.status.value) {
                        el.status.value = 'completed';
                    }

                    const query = new URLSearchParams(window.location.search);
                    if (el.shiftBranch && query.get('shift_branch_id')) {
                        el.shiftBranch.value = query.get('shift_branch_id');
                    }
                    if (el.shiftCashier && query.get('shift_cashier_id')) {
                        el.shiftCashier.value = query.get('shift_cashier_id');
                    }
                    if (el.shiftStatus && query.get('shift_status')) {
                        el.shiftStatus.value = query.get('shift_status');
                    }
                    if (el.shiftSearch && query.get('shift_q')) {
                        el.shiftSearch.value = query.get('shift_q');
                    }
                    syncShiftLabels();

                    refresh();
                    if (state.tab === 'shift') {
                        refreshShift();
                    }
                })();
            })();
        </script>
@endsection
