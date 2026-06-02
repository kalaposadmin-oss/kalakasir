@extends('layouts.app')

@php
    $financeSummaryTenant = \App\Support\TenantContext::get();
    $financeSummaryUser = auth()->user();
    $financeSummaryRole = null;

    if (! $financeSummaryTenant && filled($financeSummaryUser?->tenant_id)) {
        $financeSummaryTenant = \App\Models\Tenant::query()->find($financeSummaryUser->tenant_id);
    }

    if ($financeSummaryTenant && $financeSummaryUser) {
        $financeSummaryRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $financeSummaryTenant->id, (string) $financeSummaryUser->id);

        if (! $financeSummaryRole && filled($financeSummaryUser->role ?? null)) {
            $financeSummaryRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $financeSummaryUser->role);
        }
    }

    $financeSummaryCanViewBalanceSheet = $financeSummaryTenant
        && app(\App\Services\SubscriptionService::class)->canAccessFeature($financeSummaryTenant, 'finance.balance_sheet');
    $financeSummaryCanDownloadPdf = $financeSummaryTenant
        && app(\App\Services\SubscriptionService::class)->canAccessFeature($financeSummaryTenant, 'reports.download_pdf');
    $financeSummaryBackUrl = normalize_pos_home_url((string) ($backUrl ?? '/backoffice/dashboard'));

    abort_unless(in_array($financeSummaryRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);
@endphp

@section('page_title', 'Laporan Keuangan Ringkas')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto pb-32')

@push('head')
        <style type="text/tailwindcss">
            body { font-family: 'Inter', sans-serif; }
            .material-symbols-outlined {
                font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            }
            .segmented-control-active {
                @apply bg-white dark:bg-slate-700 shadow-sm text-slate-900 dark:text-white;
            }
        </style>
    
@endpush

@section('header')
        <x-page-header title="Laporan Keuangan" :backUrl="$financeSummaryBackUrl" :showMenuButton="true">
        <x-slot:actions>
            <button class="flex items-center justify-center size-10 rounded-full bg-slate-100 dark:bg-slate-800">
                <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">calendar_today</span>
            </button>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <div class="px-4 pb-4 pt-4">
        <div class="bg-slate-100 dark:bg-slate-800 p-1 rounded-xl flex items-center">
            <button id="tab-pl" class="flex-1 py-1.5 text-sm font-semibold rounded-lg segmented-control-active">Laba Rugi</button>
            @if ($financeSummaryCanViewBalanceSheet)
                <button id="tab-balance" class="flex-1 py-1.5 text-sm font-semibold rounded-lg text-slate-500">Neraca</button>
            @endif
        </div>
    </div>
    <div class="px-4 pb-2">
        <x-period-filter id="finance-period" defaultPreset="thisMonth" />
    </div>
    <div class="px-4 pb-2">
        <div id="report-finality-banner" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-100">
            <div class="flex items-start gap-3">
                <span id="report-finality-icon" class="material-symbols-outlined mt-0.5 text-lg text-amber-600">pending_actions</span>
                <div class="min-w-0">
                    <p id="report-finality-label" class="text-xs font-bold uppercase tracking-[0.18em]">Belum Final</p>
                    <p id="report-finality-message" class="mt-1 text-xs leading-relaxed">Laporan masih bisa berubah karena periode belum ditutup.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="px-4 py-4" id="profit-section">
        <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest" id="period-label">Periode: -</p>
            @if ($financeSummaryCanDownloadPdf)
                <span class="text-xs font-medium text-primary flex items-center gap-1" id="finance-summary-download-profit">
                    Unduh PDF <span class="material-symbols-outlined text-sm">download</span>
                </span>
            @else
                <span class="text-xs font-medium text-slate-400" id="finance-summary-download-upgrade-profit">PDF tersedia mulai paket Medium</span>
            @endif
        </div>
        <div class="bg-primary p-6 rounded-2xl shadow-lg shadow-primary/20 text-white mb-6">
            <p class="text-sm font-medium opacity-80 mb-1">Laba Bersih</p>
            <h2 class="text-3xl font-bold" id="net-profit">Rp 0</h2>
            <div class="mt-4 flex items-center gap-2 text-xs font-medium bg-white/10 w-fit px-3 py-1 rounded-full">
                <span class="material-symbols-outlined text-sm">trending_up</span>
                <span>12% dari bulan lalu</span>
            </div>
        </div>
        <div class="space-y-6">
            <section>
                <div class="flex justify-between items-end mb-3 px-1">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Pendapatan</h3>
                    <span class="text-sm font-bold text-emerald-600" id="revenue-total">Rp 0</span>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800" id="revenue-list"></div>
            </section>
            <section>
                <div class="flex justify-between items-end mb-3 px-1">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Beban dan Pengeluaran</h3>
                    <span class="text-sm font-bold text-red-500" id="expense-total">Rp 0</span>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800" id="expense-list"></div>
            </section>
            <section>
                <div class="flex justify-between items-end mb-3 px-1">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Pajak</h3>
                    <span class="text-sm font-bold text-slate-500" id="tax-total">Rp 0</span>
                </div>
            </section>
        </div>
    </div>
    <div class="px-4 py-4 hidden" id="balance-section">
        <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Neraca Ringkas</p>
            @if ($financeSummaryCanDownloadPdf)
                <span class="text-xs font-medium text-primary flex items-center gap-1" id="finance-summary-download-balance">
                    Unduh PDF <span class="material-symbols-outlined text-sm">download</span>
                </span>
            @else
                <span class="text-xs font-medium text-slate-400" id="finance-summary-download-upgrade-balance">PDF tersedia mulai paket Medium</span>
            @endif
        </div>
        <div class="space-y-6">
            <section>
                <div class="flex justify-between items-end mb-3 px-1">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Aset</h3>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800" id="asset-list"></div>
            </section>
            <section>
                <div class="flex justify-between items-end mb-3 px-1">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Liabilitas</h3>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800" id="liability-list"></div>
            </section>
            <section>
                <div class="flex justify-between items-end mb-3 px-1">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Ekuitas</h3>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 divide-y divide-slate-100 dark:divide-slate-800" id="equity-list"></div>
            </section>
        </div>
    </div>

    <div id="hpp-breakdown-modal" class="fixed inset-0 z-[90] hidden">
        <div id="hpp-breakdown-overlay" class="absolute inset-0 bg-slate-900/55"></div>
        <div class="absolute bottom-0 left-1/2 w-full max-w-[430px] -translate-x-1/2 max-h-[85vh] overflow-y-auto rounded-t-3xl bg-white p-4 shadow-2xl app-bottom-safe-footer dark:bg-slate-900">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Detail HPP</h3>
                    <p id="hpp-breakdown-period" class="text-xs text-slate-500">Periode -</p>
                </div>
                <button id="hpp-breakdown-close" class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300" type="button">Tutup</button>
            </div>

            <div id="hpp-breakdown-summary" class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-3"></div>

            <div class="mb-3">
                <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">Ringkasan Produk</h4>
                <div id="hpp-breakdown-products" class="max-h-36 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-3 dark:border-slate-800"></div>
            </div>

            <div>
                <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">Rincian Per Transaksi</h4>
                <div id="hpp-breakdown-rows" class="max-h-[42vh] space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-3 dark:border-slate-800"></div>
            </div>
        </div>
    </div>

    <div id="account-detail-modal" class="fixed inset-0 z-[91] hidden">
        <div id="account-detail-overlay" class="absolute inset-0 bg-slate-900/55"></div>
        <div class="absolute bottom-0 left-1/2 w-full max-w-[430px] -translate-x-1/2 max-h-[86vh] overflow-y-auto rounded-t-3xl bg-white p-4 shadow-2xl app-bottom-safe-footer dark:bg-slate-900">
            <div class="mb-3 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.24em] text-slate-500">Detail Akun</p>
                    <h3 id="account-detail-title" class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">Akun</h3>
                    <p id="account-detail-period" class="text-xs text-slate-500">Periode -</p>
                </div>
                <button id="account-detail-close" class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300" type="button">Tutup</button>
            </div>

            <div id="account-detail-summary" class="mb-3 grid grid-cols-2 gap-2"></div>

            <div id="account-detail-inventory-section" class="mb-3 hidden">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Rincian Barang</h4>
                        <p id="account-detail-inventory-note" class="text-[11px] text-slate-500"></p>
                    </div>
                    <span id="account-detail-inventory-count" class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"></span>
                </div>
                <div id="account-detail-inventory-list" class="max-h-44 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-3 dark:border-slate-800"></div>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Rincian Jurnal</h4>
                    <span id="account-detail-entry-count" class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"></span>
                </div>
                <div id="account-detail-rows" class="max-h-[42vh] space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-3 dark:border-slate-800"></div>
            </div>
        </div>
    </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const canViewBalanceSheet = @json($financeSummaryCanViewBalanceSheet);

                const el = {
                    periodFilter: document.getElementById('finance-period'),
                    period: document.getElementById('period-label'),
                    netProfit: document.getElementById('net-profit'),
                    revenueTotal: document.getElementById('revenue-total'),
                    expenseTotal: document.getElementById('expense-total'),
                    taxTotal: document.getElementById('tax-total'),
                    revenueList: document.getElementById('revenue-list'),
                    expenseList: document.getElementById('expense-list'),
                    assetList: document.getElementById('asset-list'),
                    liabilityList: document.getElementById('liability-list'),
                    equityList: document.getElementById('equity-list'),
                    profitSection: document.getElementById('profit-section'),
                    balanceSection: document.getElementById('balance-section'),
                    tabPl: document.getElementById('tab-pl'),
                    tabBalance: document.getElementById('tab-balance'),
                    hppBreakdownModal: document.getElementById('hpp-breakdown-modal'),
                    hppBreakdownOverlay: document.getElementById('hpp-breakdown-overlay'),
                    hppBreakdownClose: document.getElementById('hpp-breakdown-close'),
                    hppBreakdownPeriod: document.getElementById('hpp-breakdown-period'),
                    hppBreakdownSummary: document.getElementById('hpp-breakdown-summary'),
                    hppBreakdownProducts: document.getElementById('hpp-breakdown-products'),
                    hppBreakdownRows: document.getElementById('hpp-breakdown-rows'),
                    accountDetailModal: document.getElementById('account-detail-modal'),
                    accountDetailOverlay: document.getElementById('account-detail-overlay'),
                    accountDetailClose: document.getElementById('account-detail-close'),
                    accountDetailTitle: document.getElementById('account-detail-title'),
                    accountDetailPeriod: document.getElementById('account-detail-period'),
                    accountDetailSummary: document.getElementById('account-detail-summary'),
                    accountDetailInventorySection: document.getElementById('account-detail-inventory-section'),
                    accountDetailInventoryNote: document.getElementById('account-detail-inventory-note'),
                    accountDetailInventoryCount: document.getElementById('account-detail-inventory-count'),
                    accountDetailInventoryList: document.getElementById('account-detail-inventory-list'),
                    accountDetailEntryCount: document.getElementById('account-detail-entry-count'),
                    accountDetailRows: document.getElementById('account-detail-rows'),
                    finalityBanner: document.getElementById('report-finality-banner'),
                    finalityIcon: document.getElementById('report-finality-icon'),
                    finalityLabel: document.getElementById('report-finality-label'),
                    finalityMessage: document.getElementById('report-finality-message'),
                };

                const state = {
                    period: { preset: 'thisMonth', start_date: '', end_date: '' },
                    loadingHppBreakdown: false,
                    loadingAccountDetail: false,
                };

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const escapeHtml = (value) => String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');

                const isHppExpenseRow = (label) => {
                    const normalized = String(label || '').toLowerCase();
                    return normalized.includes('harga pokok penjualan') || normalized.includes('hpp');
                };

                const saleDetailBasePath = window.location.pathname.startsWith('/pos/')
                    ? '/pos/sales/'
                    : '/backoffice/sales/';

                const buildFinanceReturnPath = () => {
                    const returnUrl = new URL(window.location.href);
                    returnUrl.searchParams.set('open_hpp_breakdown', '1');
                    return `${returnUrl.pathname}${returnUrl.search}`;
                };

                const buildFinanceCurrentPath = () => `${window.location.pathname}${window.location.search}`;

                const buildSaleDetailHref = (saleId) => {
                    const params = new URLSearchParams({
                        from: buildFinanceReturnPath(),
                    });

                    return `${saleDetailBasePath}${encodeURIComponent(saleId)}?${params.toString()}`;
                };

                const buildStockCardHref = (productId) => {
                    const portalPrefix = window.location.pathname.startsWith('/pos/') ? '/pos' : '/backoffice';
                    const url = new URL(`${portalPrefix}/inventory/products/${encodeURIComponent(productId)}/stock-card`, window.location.origin);
                    url.searchParams.set('from', buildFinanceCurrentPath());

                    return `${url.pathname}${url.search}`;
                };

                const openHppBreakdownModal = () => {
                    el.hppBreakdownModal?.classList.remove('hidden');
                };

                const closeHppBreakdownModal = () => {
                    el.hppBreakdownModal?.classList.add('hidden');
                };

                const openAccountDetailModal = () => {
                    el.accountDetailModal?.classList.remove('hidden');
                };

                const closeAccountDetailModal = () => {
                    el.accountDetailModal?.classList.add('hidden');
                };

                const renderHppBreakdown = (payload) => {
                    const summary = payload?.summary || {};
                    const details = payload?.details || [];
                    const products = payload?.product_summary || [];
                    const refunds = payload?.refund_adjustments || [];

                    el.hppBreakdownPeriod.textContent = `Periode: ${payload?.period?.start_date || '-'} s/d ${payload?.period?.end_date || '-'}`;

                    el.hppBreakdownSummary.innerHTML = `
                        <div class="rounded-xl bg-slate-100 p-3 dark:bg-slate-800">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">HPP Kotor</p>
                            <p class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">${formatCurrency(summary.gross_cogs || 0)}</p>
                        </div>
                        <div class="rounded-xl bg-slate-100 p-3 dark:bg-slate-800">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Koreksi Retur</p>
                            <p class="mt-1 text-sm font-bold text-amber-600">-${formatCurrency(summary.refunded_cogs || 0)}</p>
                        </div>
                        <div class="rounded-xl bg-slate-100 p-3 dark:bg-slate-800">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">HPP Bersih</p>
                            <p class="mt-1 text-sm font-bold text-primary">${formatCurrency(summary.net_cogs || 0)}</p>
                        </div>
                    `;

                    if (!products.length) {
                        el.hppBreakdownProducts.innerHTML = '<p class="text-xs text-slate-500">Belum ada detail produk untuk periode ini.</p>';
                    } else {
                        el.hppBreakdownProducts.innerHTML = products.map((row) => {
                            const variantText = row.variant_name ? ` (${escapeHtml(row.variant_name)})` : '';
                            return `
                                <div class="flex items-start justify-between gap-2 text-xs">
                                    <p class="text-slate-700 dark:text-slate-300">${escapeHtml(row.product_name)}${variantText}</p>
                                    <p class="font-semibold text-slate-900 dark:text-slate-100">${formatCurrency(row.cogs_total || 0)}</p>
                                </div>
                            `;
                        }).join('');
                    }

                    if (!details.length && !refunds.length) {
                        el.hppBreakdownRows.innerHTML = '<p class="text-xs text-slate-500">Belum ada rincian HPP untuk periode ini.</p>';
                        return;
                    }

                    const detailRowsHtml = details.map((row) => {
                        const variantText = row.variant_name ? ` • ${escapeHtml(row.variant_name)}` : '';
                        const detailHref = buildSaleDetailHref(row.sale_id);
                        return `
                            <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                                <div class="mb-1 flex items-start justify-between gap-2">
                                    <p class="text-xs font-semibold text-slate-900 dark:text-slate-100">${escapeHtml(row.product_name)}${variantText}</p>
                                    <p class="text-xs font-bold text-slate-900 dark:text-slate-100">${formatCurrency(row.cogs_total || 0)}</p>
                                </div>
                                <p class="text-[11px] text-slate-500">${escapeHtml(row.business_date)} • Qty ${Number(row.qty || 0)} × ${formatCurrency(row.unit_cost || 0)}</p>
                                <a class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-primary" href="${detailHref}">
                                    Lihat transaksi
                                    <span class="material-symbols-outlined text-[14px]">open_in_new</span>
                                </a>
                            </div>
                        `;
                    }).join('');

                    const refundRowsHtml = refunds.length
                        ? `
                            <div class="pt-2">
                                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-amber-700">Penyesuaian Retur</p>
                                ${refunds.map((row) => {
                                    const detailHref = buildSaleDetailHref(row.sale_id || '');
                                    return `
                                        <div class="mb-2 rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800/40 dark:bg-amber-950/20">
                                            <div class="flex items-start justify-between gap-2">
                                                <p class="text-xs text-amber-900 dark:text-amber-200">Retur ${escapeHtml(row.business_date || '-')}</p>
                                                <p class="text-xs font-bold text-amber-700">-${formatCurrency(row.cogs_amount || 0)}</p>
                                            </div>
                                            ${row.sale_id ? `<a class="mt-1 inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700" href="${detailHref}">Cek transaksi asal<span class="material-symbols-outlined text-[14px]">open_in_new</span></a>` : ''}
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        `
                        : '';

                    el.hppBreakdownRows.innerHTML = `${detailRowsHtml}${refundRowsHtml}`;
                };

                const loadHppBreakdown = async () => {
                    if (state.loadingHppBreakdown) {
                        return;
                    }

                    state.loadingHppBreakdown = true;
                    openHppBreakdownModal();
                    el.hppBreakdownSummary.innerHTML = '<p class="text-xs text-slate-500">Memuat ringkasan HPP...</p>';
                    el.hppBreakdownProducts.innerHTML = '';
                    el.hppBreakdownRows.innerHTML = '';

                    try {
                        const params = new URLSearchParams({
                            preset: state.period.preset || 'thisMonth',
                            start_date: state.period.start_date || '',
                            end_date: state.period.end_date || '',
                        });

                        const response = await fetch(`/api/v1/finance-summary/cogs-breakdown?${params.toString()}`, { headers });
                        if (!response.ok) {
                            throw new Error('Gagal memuat detail HPP.');
                        }

                        const payload = await response.json();
                        renderHppBreakdown(payload);
                    } catch (error) {
                        el.hppBreakdownSummary.innerHTML = '<p class="text-xs text-red-500">Gagal memuat detail HPP.</p>';
                    } finally {
                        state.loadingHppBreakdown = false;
                    }
                };

                const renderAccountDetail = (payload) => {
                    const account = payload?.account || {};
                    const summary = payload?.summary || {};
                    const entries = payload?.entries || [];
                    const inventoryBreakdown = payload?.inventory_breakdown || [];
                    const inventoryMeta = payload?.inventory_meta || null;

                    el.accountDetailTitle.textContent = account.label || account.name || 'Detail akun';
                    el.accountDetailPeriod.textContent = `Periode: ${payload?.period?.start_date || '-'} s/d ${payload?.period?.end_date || '-'}`;
                    el.accountDetailEntryCount.textContent = `${summary.entry_count || entries.length || 0} jurnal`;

                    const summaryCards = [
                        { label: 'Saldo Awal', value: formatCurrency(summary.opening_balance || 0) },
                        { label: 'Debit Periode', value: formatCurrency(summary.period_debit || 0) },
                        { label: 'Kredit Periode', value: formatCurrency(summary.period_credit || 0) },
                        {
                            label: summary.report_basis === 'balance_as_of_end_date' ? 'Saldo Akhir' : 'Nilai Laporan',
                            value: formatCurrency(summary.report_amount ?? summary.ending_balance ?? 0),
                            accent: 'text-primary',
                        },
                    ];

                    el.accountDetailSummary.innerHTML = summaryCards.map((card) => `
                        <div class="rounded-xl bg-slate-100 p-3 dark:bg-slate-800">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">${escapeHtml(card.label)}</p>
                            <p class="mt-1 text-sm font-bold ${card.accent || 'text-slate-900 dark:text-slate-100'}">${escapeHtml(card.value)}</p>
                        </div>
                    `).join('');

                    if (inventoryBreakdown.length) {
                        el.accountDetailInventorySection.classList.remove('hidden');
                        el.accountDetailInventoryCount.textContent = `${inventoryMeta?.product_count || inventoryBreakdown.length} barang`;
                        el.accountDetailInventoryNote.textContent = inventoryMeta?.note || '';
                        el.accountDetailInventoryList.innerHTML = inventoryBreakdown.map((row) => {
                            const stockCardHref = buildStockCardHref(row.product_id);
                            return `
                                <a href="${stockCardHref}" class="block rounded-xl border border-slate-200 px-3 py-2 transition hover:border-primary/40 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/80">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-semibold text-slate-900 dark:text-slate-100">${escapeHtml(row.product_name)}</p>
                                            <p class="text-[11px] text-slate-500">${escapeHtml(row.product_sku || 'Tanpa SKU')} • Qty ${escapeHtml(row.current_qty)}</p>
                                        </div>
                                        <p class="shrink-0 text-xs font-bold text-slate-900 dark:text-slate-100">${formatCurrency(row.current_value || 0)}</p>
                                    </div>
                                </a>
                            `;
                        }).join('');
                    } else {
                        el.accountDetailInventorySection.classList.add('hidden');
                        el.accountDetailInventoryCount.textContent = '';
                        el.accountDetailInventoryNote.textContent = '';
                        el.accountDetailInventoryList.innerHTML = '';
                    }

                    if (!entries.length) {
                        el.accountDetailRows.innerHTML = '<p class="text-xs text-slate-500">Belum ada rincian jurnal pada periode ini.</p>';
                        return;
                    }

                    el.accountDetailRows.innerHTML = entries.map((row) => `
                        <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                            <div class="mb-1 flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-900 dark:text-slate-100">${escapeHtml(row.description || 'Jurnal')}</p>
                                    <p class="text-[11px] text-slate-500">${escapeHtml(row.date)} • ${escapeHtml(row.journal_entry_id)}</p>
                                </div>
                                <p class="shrink-0 text-xs font-bold text-slate-900 dark:text-slate-100">${formatCurrency(row.amount || 0)}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-500">
                                <div>Debit: <span class="font-semibold text-slate-700 dark:text-slate-300">${formatCurrency(row.debit || 0)}</span></div>
                                <div>Kredit: <span class="font-semibold text-slate-700 dark:text-slate-300">${formatCurrency(row.credit || 0)}</span></div>
                            </div>
                        </div>
                    `).join('');
                };

                const loadAccountDetail = async (accountId) => {
                    if (!accountId || state.loadingAccountDetail) {
                        return;
                    }

                    state.loadingAccountDetail = true;
                    openAccountDetailModal();
                    el.accountDetailTitle.textContent = 'Memuat detail akun...';
                    el.accountDetailPeriod.textContent = 'Periode -';
                    el.accountDetailSummary.innerHTML = '<p class="text-xs text-slate-500">Memuat ringkasan akun...</p>';
                    el.accountDetailRows.innerHTML = '';
                    el.accountDetailInventorySection.classList.add('hidden');
                    el.accountDetailInventoryList.innerHTML = '';

                    try {
                        const params = new URLSearchParams({
                            account_id: accountId,
                            preset: state.period.preset || 'thisMonth',
                            start_date: state.period.start_date || '',
                            end_date: state.period.end_date || '',
                        });

                        const response = await fetch(`/api/v1/finance-summary/account-detail?${params.toString()}`, { headers });
                        if (!response.ok) {
                            throw new Error('Gagal memuat detail akun.');
                        }

                        const payload = await response.json();
                        renderAccountDetail(payload);
                    } catch (error) {
                        el.accountDetailTitle.textContent = 'Detail akun';
                        el.accountDetailSummary.innerHTML = '<p class="text-xs text-red-500">Gagal memuat detail akun.</p>';
                        el.accountDetailRows.innerHTML = '';
                        el.accountDetailInventorySection.classList.add('hidden');
                    } finally {
                        state.loadingAccountDetail = false;
                    }
                };

                const renderRows = (target, rows, options = {}) => {
                    target.innerHTML = '';
                    rows.forEach((row) => {
                        const item = document.createElement('div');
                        item.className = 'p-4 flex justify-between items-center';
                        const canInspectHpp = options.kind === 'expense' && isHppExpenseRow(row.label);
                        const canInspectAccount = Boolean(row.account_id) && !canInspectHpp;
                        if (canInspectHpp) {
                            item.innerHTML = `
                                <button class="flex min-w-0 items-center gap-1 text-left text-sm text-primary" data-hpp-breakdown-trigger type="button">
                                    <span class="truncate">${escapeHtml(row.label)}</span>
                                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                                </button>
                                <span class="text-sm font-semibold">${formatCurrency(row.amount)}</span>
                            `;
                            item.querySelector('[data-hpp-breakdown-trigger]')?.addEventListener('click', loadHppBreakdown);
                        } else if (canInspectAccount) {
                            item.innerHTML = `
                                <button class="flex w-full items-center justify-between gap-3 text-left" data-account-detail-trigger type="button" data-account-id="${escapeHtml(row.account_id)}">
                                    <span class="flex min-w-0 items-center gap-1 text-sm text-primary">
                                        <span class="truncate">${escapeHtml(row.label)}</span>
                                        <span class="material-symbols-outlined text-sm">open_in_new</span>
                                    </span>
                                    <span class="shrink-0 text-sm font-semibold text-slate-900 dark:text-slate-100">${formatCurrency(row.amount)}</span>
                                </button>
                            `;
                            item.querySelector('[data-account-detail-trigger]')?.addEventListener('click', () => loadAccountDetail(row.account_id));
                        } else {
                            item.innerHTML = `
                                <span class="text-sm text-slate-600 dark:text-slate-400">${escapeHtml(row.label)}</span>
                                <span class="text-sm font-semibold">${formatCurrency(row.amount)}</span>
                            `;
                        }
                        target.appendChild(item);
                    });
                };

                const renderFinalityStatus = (period) => {
                    const status = period?.closing_status || {};
                    const isFinal = Boolean(status.is_final);

                    el.finalityBanner.className = isFinal
                        ? 'rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-100'
                        : 'rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-100';
                    el.finalityIcon.textContent = isFinal ? 'verified_user' : 'pending_actions';
                    el.finalityIcon.className = `material-symbols-outlined mt-0.5 text-lg ${isFinal ? 'text-emerald-600' : 'text-amber-600'}`;
                    el.finalityLabel.textContent = status.label || (isFinal ? 'Final' : 'Belum Final');
                    el.finalityMessage.textContent = status.message || (isFinal
                        ? 'Periode ini sudah ditutup dan laporan terkunci.'
                        : 'Laporan masih bisa berubah karena periode belum ditutup.');
                };

                const setTab = (tab) => {
                    const isProfit = tab === 'pl';
                    el.profitSection.classList.toggle('hidden', !isProfit);
                    el.balanceSection.classList.toggle('hidden', isProfit);
                    el.tabPl.classList.toggle('segmented-control-active', isProfit);
                    el.tabPl.classList.toggle('text-slate-500', !isProfit);
                    if (el.tabBalance) {
                        el.tabBalance.classList.toggle('segmented-control-active', !isProfit);
                        el.tabBalance.classList.toggle('text-slate-500', isProfit);
                    }
                };

                el.tabPl.addEventListener('click', () => setTab('pl'));
                if (el.tabBalance) {
                    el.tabBalance.addEventListener('click', () => setTab('balance'));
                }

                const loadFinance = async () => {
                    const params = new URLSearchParams({
                        preset: state.period.preset || 'thisMonth',
                        start_date: state.period.start_date || '',
                        end_date: state.period.end_date || '',
                    });
                    const response = await fetch(`/api/v1/finance-summary?${params.toString()}`, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat laporan.');
                    }
                    return response.json();
                };

                const renderFinance = (data) => {
                    const hasBalanceSheet = Boolean(data.balance_sheet) && canViewBalanceSheet;
                    el.period.textContent = `Periode: ${data.period?.label || '-'}${data.period?.start_date ? ` (${data.period.start_date} - ${data.period.end_date})` : ''}`;
                    renderFinalityStatus(data.period || {});
                    el.netProfit.textContent = formatCurrency(data.summary?.net_profit || 0);
                    el.revenueTotal.textContent = `+ ${formatCurrency(data.summary?.revenue || 0)}`;
                    el.expenseTotal.textContent = `- ${formatCurrency(data.summary?.expenses || 0)}`;
                    el.taxTotal.textContent = formatCurrency(data.summary?.tax || 0);

                    renderRows(el.revenueList, data.revenue_breakdown || [], { kind: 'revenue' });
                    renderRows(el.expenseList, data.expense_breakdown || [], { kind: 'expense' });
                    renderRows(el.assetList, hasBalanceSheet ? (data.balance_sheet?.assets || []) : []);
                    renderRows(el.liabilityList, hasBalanceSheet ? (data.balance_sheet?.liabilities || []) : []);
                    renderRows(el.equityList, hasBalanceSheet ? (data.balance_sheet?.equity || []) : []);

                    if (!hasBalanceSheet) {
                        setTab('pl');
                    }
                };

                const refresh = async () => {
                    try {
                        const data = await loadFinance();
                        renderFinance(data);

                        const currentParams = new URLSearchParams(window.location.search);
                        if (currentParams.get('open_hpp_breakdown') === '1') {
                            loadHppBreakdown();
                            currentParams.delete('open_hpp_breakdown');
                            const nextQuery = currentParams.toString();
                            history.replaceState(null, '', `${window.location.pathname}${nextQuery ? `?${nextQuery}` : ''}`);
                        }
                    } catch (error) {
                        el.revenueList.innerHTML = '<div class="p-4 text-sm text-red-500">Gagal memuat laporan.</div>';
                    }
                };

                el.hppBreakdownOverlay?.addEventListener('click', closeHppBreakdownModal);
                el.hppBreakdownClose?.addEventListener('click', closeHppBreakdownModal);
                el.accountDetailOverlay?.addEventListener('click', closeAccountDetailModal);
                el.accountDetailClose?.addEventListener('click', closeAccountDetailModal);

                el.periodFilter?.addEventListener('period:change', (event) => {
                    state.period = event.detail;
                    refresh();
                });

                refresh();
            })();
        </script>
@endsection
