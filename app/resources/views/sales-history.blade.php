@extends('layouts.app')

@section('page_title', 'Riwayat & Ekspor Penjualan')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto pb-24')

@push('head')
        <style>
            body { font-family: "Inter", sans-serif; }
            .material-symbols-outlined {
                font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24;
            }
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
    
@endpush

@section('header')
    <x-page-header title="Riwayat Penjualan" backUrl="/menu/sales" :showMenuButton="true">
        <x-slot:actions>
            <button class="flex items-center justify-center size-10 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <span class="material-symbols-outlined text-slate-700 dark:text-slate-300">search</span>
            </button>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <section class="p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Filter Pencarian</h2>
            <button id="reset-filter" class="text-primary text-sm font-medium">Atur Ulang</button>
        </div>
        <x-period-filter id="sales-period" defaultPreset="today" />
        <div class="grid grid-cols-1 gap-3">
            <div class="flex gap-2 overflow-x-auto pb-2 no-scrollbar">
                <button class="flex shrink-0 items-center gap-2 rounded-full bg-primary/10 border border-primary/20 px-4 py-2 text-primary text-sm font-medium relative">
                    <span class="material-symbols-outlined text-sm">store</span>
                    <span id="branch-label">Semua Cabang</span>
                    <span class="material-symbols-outlined text-sm">expand_more</span>
                    <select id="branch-filter" class="absolute inset-0 opacity-0 cursor-pointer"></select>
                </button>
                <button class="flex shrink-0 items-center gap-2 rounded-full bg-slate-100 dark:bg-slate-900 px-4 py-2 text-slate-700 dark:text-slate-300 text-sm font-medium relative">
                    <span class="material-symbols-outlined text-sm">person</span>
                    <span id="cashier-label">Kasir</span>
                    <span class="material-symbols-outlined text-sm">expand_more</span>
                    <select id="cashier-filter" class="absolute inset-0 opacity-0 cursor-pointer"></select>
                </button>
                <button class="flex shrink-0 items-center gap-2 rounded-full bg-slate-100 dark:bg-slate-900 px-4 py-2 text-slate-700 dark:text-slate-300 text-sm font-medium relative">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    <span id="status-label">Status</span>
                    <span class="material-symbols-outlined text-sm">expand_more</span>
                    <select id="status-filter" class="absolute inset-0 opacity-0 cursor-pointer">
                        <option value="all">Semua Status</option>
                        <option value="paid">Berhasil</option>
                        <option value="void">Dibatalkan</option>
                    </select>
                </button>
            </div>
        </div>
    </section>
    <div class="h-2 bg-background-light dark:bg-slate-900"></div>
    <section class="p-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <input id="select-all" class="rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" />
                <span class="text-sm font-medium text-slate-600 dark:text-slate-400" id="select-label">Pilih Semua (0)</span>
            </div>
            <button id="export-button" class="flex items-center gap-1.5 bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold shadow-lg shadow-primary/20 active:scale-95 transition-transform">
                <span class="material-symbols-outlined text-sm">file_export</span>
                Ekspor
            </button>
        </div>
        <div class="space-y-3" id="sale-list"></div>
    </section>
    <div class="fixed bottom-24 right-4 max-w-md">
        <button class="size-14 rounded-full bg-primary text-white shadow-2xl flex items-center justify-center active:scale-90 transition-transform">
            <span class="material-symbols-outlined text-3xl">add</span>
        </button>
    </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

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
                };

                const state = {
                    branches: [],
                    cashiers: [],
                    sales: [],
                    period: { preset: 'today', start_date: '', end_date: '' },
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

                const renderList = () => {
                    el.list.innerHTML = '';
                    el.selectLabel.textContent = `Pilih Semua (${state.sales.length})`;

                    if (!state.sales.length) {
                        el.list.innerHTML = '<div class="text-sm text-slate-400">Belum ada transaksi.</div>';
                        return;
                    }

                    state.sales.forEach((sale) => {
                        const statusBadge = sale.status === 'void'
                            ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
                            : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400';
                        const statusLabel = sale.status === 'void' ? 'Dibatalkan' : 'Berhasil';
                        const totalClass = sale.status === 'void' ? 'text-slate-400 line-through' : 'text-primary';

                        const row = document.createElement('a');
                        row.href = `/sales-detail?sale_id=${sale.id}`;
                        row.className = `p-4 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900/50 flex items-start gap-3 hover:border-primary/40 transition-colors${sale.status === 'void' ? ' opacity-70' : ''}`;
                        row.innerHTML = `
                            <input class="mt-1 rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" onclick="event.stopPropagation()" />
                            <div class="flex-1">
                                <div class="flex justify-between items-start mb-1">
                                    <p class="font-bold text-slate-900 dark:text-slate-100">${formatInvoice(sale)}</p>
                                    <span class="px-2 py-0.5 rounded-full ${statusBadge} text-[10px] font-bold uppercase">${statusLabel}</span>
                                </div>
                                <p class="text-xs text-slate-500 mb-2">${formatDate(sale.created_at)} • Kasir: ${sale.cashier_name || '-'} • ${sale.branch_name || 'Cabang'}</p>
                                <div class="flex justify-between items-center">
                                    <p class="text-sm text-slate-600 dark:text-slate-400">${sale.item_count} Produk • ${paymentLabel(sale)}</p>
                                    <p class="text-base font-bold ${totalClass}">${formatCurrency(sale.total)}</p>
                                </div>
                            </div>
                        `;
                        el.list.appendChild(row);
                    });
                };

                const loadBootstrap = async () => {
                    const response = await fetch('/api/v1/bootstrap', { headers });
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                };

                const loadSales = async () => {
                    const params = new URLSearchParams({
                        preset: state.period.preset || 'today',
                        start_date: state.period.start_date || '',
                        end_date: state.period.end_date || '',
                        branch_id: el.branch.value || '',
                        cashier_id: el.cashier.value || '',
                        status: el.status.value || 'all',
                    });
                    const response = await fetch(`/api/v1/sales-history?${params.toString()}`, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat penjualan.');
                    }
                    return response.json();
                };

                const updateQuery = () => {
                    const params = new URLSearchParams();
                    params.set('preset', state.period.preset || 'today');
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

                const loadCashiers = async () => {
                    const response = await fetch('/api/v1/cashiers', { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const data = await response.json();
                    return data.cashiers || [];
                };

                const buildOptions = (select, items, labelKey, includeAllLabel) => {
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
                    el.branchLabel.textContent = el.branch.options[el.branch.selectedIndex]?.textContent || 'Semua Cabang';
                    el.cashierLabel.textContent = el.cashier.options[el.cashier.selectedIndex]?.textContent || 'Kasir';
                    el.statusLabel.textContent = el.status.options[el.status.selectedIndex]?.textContent || 'Status';
                };

                const refresh = async () => {
                    syncLabels();
                    try {
                        const data = await loadSales();
                        state.sales = data.sales || [];
                        renderList();
                        updateQuery();
                    } catch (error) {
                        el.list.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                    }
                };

                el.branch.addEventListener('change', refresh);
                el.cashier.addEventListener('change', refresh);
                el.status.addEventListener('change', refresh);

                el.reset.addEventListener('click', () => {
                    el.branch.value = '';
                    el.cashier.value = '';
                    el.status.value = 'all';
                    el.period?.querySelector('[data-period-reset]')?.click();
                    refresh();
                });

                el.period?.addEventListener('period:change', (event) => {
                    state.period = event.detail;
                    refresh();
                });

                el.export.addEventListener('click', () => {
                    if (!state.sales.length) {
                        return;
                    }
                    const rows = [
                        ['Invoice', 'Tanggal', 'Kasir', 'Cabang', 'Status', 'Total', 'Metode'],
                        ...state.sales.map((sale) => [
                            formatInvoice(sale),
                            formatDate(sale.created_at),
                            sale.cashier_name || '-',
                            sale.branch_name || '-',
                            sale.status,
                            sale.total,
                            paymentLabel(sale),
                        ]),
                    ];
                    const csv = rows.map((row) => row.map((cell) => `"${cell}"`).join(',')).join('\n');
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'riwayat-penjualan.csv';
                    link.click();
                    URL.revokeObjectURL(url);
                });

                (async () => {
                    const bootstrap = await loadBootstrap();
                    state.branches = bootstrap?.branches || [];
                    buildOptions(el.branch, state.branches, 'name', 'Semua Cabang');

                    const cashiers = await loadCashiers();
                    state.cashiers = cashiers.map((item) => ({ id: item.id, name: item.name }));
                    buildOptions(el.cashier, state.cashiers, 'name', 'Kasir');

                    refresh();
                })();
            })();
        </script>
@endsection
