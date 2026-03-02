@extends('layouts.app')

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
    <x-page-header title="Laporan Keuangan" backUrl="/menu/finance" :showMenuButton="true">
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
            <button id="tab-balance" class="flex-1 py-1.5 text-sm font-semibold rounded-lg text-slate-500">Neraca</button>
        </div>
    </div>
    <div class="px-4 pb-2">
        <x-period-filter id="finance-period" defaultPreset="thisMonth" />
    </div>
    <div class="px-4 py-4" id="profit-section">
        <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest" id="period-label">Periode: -</p>
            <span class="text-xs font-medium text-primary flex items-center gap-1">
                Unduh PDF <span class="material-symbols-outlined text-sm">download</span>
            </span>
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
            <span class="text-xs font-medium text-primary flex items-center gap-1">
                Unduh PDF <span class="material-symbols-outlined text-sm">download</span>
            </span>
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

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

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
                };

                const state = {
                    period: { preset: 'thisMonth', start_date: '', end_date: '' },
                };

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const renderRows = (target, rows) => {
                    target.innerHTML = '';
                    rows.forEach((row) => {
                        const item = document.createElement('div');
                        item.className = 'p-4 flex justify-between items-center';
                        item.innerHTML = `
                            <span class="text-sm text-slate-600 dark:text-slate-400">${row.label}</span>
                            <span class="text-sm font-semibold">${formatCurrency(row.amount)}</span>
                        `;
                        target.appendChild(item);
                    });
                };

                const setTab = (tab) => {
                    const isProfit = tab === 'pl';
                    el.profitSection.classList.toggle('hidden', !isProfit);
                    el.balanceSection.classList.toggle('hidden', isProfit);
                    el.tabPl.classList.toggle('segmented-control-active', isProfit);
                    el.tabBalance.classList.toggle('segmented-control-active', !isProfit);
                    el.tabPl.classList.toggle('text-slate-500', !isProfit);
                    el.tabBalance.classList.toggle('text-slate-500', isProfit);
                };

                el.tabPl.addEventListener('click', () => setTab('pl'));
                el.tabBalance.addEventListener('click', () => setTab('balance'));

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
                    el.period.textContent = `Periode: ${data.period?.label || '-'}${data.period?.start_date ? ` (${data.period.start_date} - ${data.period.end_date})` : ''}`;
                    el.netProfit.textContent = formatCurrency(data.summary?.net_profit || 0);
                    el.revenueTotal.textContent = `+ ${formatCurrency(data.summary?.revenue || 0)}`;
                    el.expenseTotal.textContent = `- ${formatCurrency(data.summary?.expenses || 0)}`;
                    el.taxTotal.textContent = formatCurrency(data.summary?.tax || 0);

                    renderRows(el.revenueList, data.revenue_breakdown || []);
                    renderRows(el.expenseList, data.expense_breakdown || []);
                    renderRows(el.assetList, data.balance_sheet?.assets || []);
                    renderRows(el.liabilityList, data.balance_sheet?.liabilities || []);
                    renderRows(el.equityList, data.balance_sheet?.equity || []);
                };

                const refresh = async () => {
                    try {
                        const data = await loadFinance();
                        renderFinance(data);
                    } catch (error) {
                        el.revenueList.innerHTML = '<div class="p-4 text-sm text-red-500">Gagal memuat laporan.</div>';
                    }
                };

                el.periodFilter?.addEventListener('period:change', (event) => {
                    state.period = event.detail;
                    refresh();
                });

                refresh();
            })();
        </script>
@endsection
