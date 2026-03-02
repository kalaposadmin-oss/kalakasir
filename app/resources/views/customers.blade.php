@extends('layouts.app')

@section('page_title', 'Daftar & Detail Pelanggan')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-slate-950 shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
        <style>
            .no-scrollbar::-webkit-scrollbar {
                display: none;
            }
            .no-scrollbar {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
        </style>
@endpush

@section('header')
    <x-page-header title="Pelanggan" backUrl="/menu/management">
        <x-slot name="actions">
            <button class="flex size-10 cursor-pointer items-center justify-center rounded-full bg-primary text-white" type="button">
                <span class="material-symbols-outlined">person_add</span>
            </button>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
        <div class="bg-white dark:bg-slate-900 px-4 py-3 space-y-3">
            <label class="flex flex-col w-full">
                <div class="flex w-full items-stretch rounded-xl h-12 bg-slate-100 dark:bg-slate-800">
                    <div class="text-slate-400 flex items-center justify-center pl-4">
                        <span class="material-symbols-outlined">search</span>
                    </div>
                    <input id="search-input" class="w-full border-none bg-transparent focus:ring-0 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 px-3 text-sm" placeholder="Cari nama atau nomor telepon..." value="" />
                </div>
            </label>
            <div class="flex gap-2 overflow-x-auto pb-1 no-scrollbar" id="filter-bar">
                <button data-filter="all" class="flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full bg-primary px-4 text-white text-sm font-medium">Semua</button>
                <button data-filter="gold" class="flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full bg-slate-100 dark:bg-slate-800 px-4 text-slate-600 dark:text-slate-300 text-sm font-medium">Member Gold</button>
                <button data-filter="new" class="flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full bg-slate-100 dark:bg-slate-800 px-4 text-slate-600 dark:text-slate-300 text-sm font-medium">Terbaru</button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950">
            <div class="p-4 space-y-3">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider" id="customer-count">Daftar Pelanggan (0)</p>
                <div id="featured-customer"></div>
                <div id="customer-list" class="space-y-3"></div>
            </div>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    search: document.getElementById('search-input'),
                    filterBar: document.getElementById('filter-bar'),
                    featured: document.getElementById('featured-customer'),
                    list: document.getElementById('customer-list'),
                    count: document.getElementById('customer-count'),
                };

                const state = {
                    customers: [],
                    filter: 'all',
                    term: '',
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

                const initials = (name) => name
                    .split(' ')
                    .filter(Boolean)
                    .slice(0, 2)
                    .map((part) => part[0])
                    .join('')
                    .toUpperCase();

                const filterCustomers = () => {
                    const term = state.term.toLowerCase();
                    let items = state.customers.filter((customer) => {
                        const matchTerm = customer.name.toLowerCase().includes(term)
                            || (customer.phone || '').toLowerCase().includes(term);
                        if (!matchTerm) {
                            return false;
                        }
                        if (state.filter === 'gold') {
                            return customer.tier === 'gold';
                        }
                        return true;
                    });

                    if (state.filter === 'new') {
                        items = items.slice().sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                    }

                    return items;
                };

                const renderFeatured = (customer) => {
                    if (!customer) {
                        el.featured.innerHTML = '<div class="text-sm text-slate-400">Belum ada pelanggan.</div>';
                        return;
                    }

                    const badge = customer.tier === 'gold'
                        ? '<div class="bg-primary/10 text-primary px-2 py-1 rounded text-[10px] font-bold uppercase">Gold Member</div>'
                        : '<div class="bg-slate-100 text-slate-500 px-2 py-1 rounded text-[10px] font-bold uppercase">Regular</div>';

                    const recent = (customer.recent_sales || []).map((sale) => `
                        <div class="flex justify-between items-center text-xs p-2 bg-slate-50 dark:bg-slate-800 rounded-lg">
                            <div>
                                <p class="font-medium">INV-${String(sale.id).slice(0, 6).toUpperCase()}</p>
                                <p class="text-[10px] text-slate-500">${formatDate(sale.created_at)}</p>
                            </div>
                            <p class="font-bold text-slate-900 dark:text-white">${formatCurrency(sale.total)}</p>
                        </div>
                    `).join('');

                    el.featured.innerHTML = `
                        <div class="flex flex-col gap-4 bg-white dark:bg-slate-900 p-4 rounded-xl border border-primary/20 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="size-12 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-lg">${initials(customer.name)}</div>
                                    <div>
                                        <h3 class="font-bold text-slate-900 dark:text-white">${customer.name}</h3>
                                        <p class="text-xs text-slate-500">${customer.phone || '-'}</p>
                                    </div>
                                </div>
                                ${badge}
                            </div>
                            <div class="grid grid-cols-2 gap-3 border-t border-slate-100 dark:border-slate-800 pt-3">
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold">Total Belanja</p>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white">${formatCurrency(customer.total_spent)}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold">Loyalty Points</p>
                                    <p class="text-sm font-bold text-primary">${customer.points} pts</p>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-bold text-slate-900 dark:text-white">Riwayat Transaksi</p>
                                    <span class="text-[10px] text-primary font-bold">Lihat Semua</span>
                                </div>
                                <div class="space-y-2">${recent || '<div class="text-xs text-slate-400">Belum ada transaksi.</div>'}</div>
                            </div>
                        </div>
                    `;
                };

                const renderList = (customers) => {
                    el.list.innerHTML = '';

                    customers.forEach((customer, index) => {
                        if (index === 0) {
                            return;
                        }
                        const row = document.createElement('div');
                        row.className = 'flex items-center gap-4 bg-white dark:bg-slate-900 px-4 py-3 rounded-xl border border-slate-100 dark:border-slate-800';
                        row.innerHTML = `
                            <div class="bg-slate-100 dark:bg-slate-800 aspect-square rounded-full size-10 flex items-center justify-center text-slate-500 font-bold">${initials(customer.name)}</div>
                            <div class="flex-1">
                                <p class="text-sm font-bold text-slate-900 dark:text-white">${customer.name}</p>
                                <p class="text-xs text-slate-500">${customer.phone || '-'}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-bold text-slate-900 dark:text-white">${formatCurrency(customer.total_spent)}</p>
                                <span class="material-symbols-outlined text-slate-400 text-sm">chevron_right</span>
                            </div>
                        `;
                        el.list.appendChild(row);
                    });
                };

                const render = () => {
                    const filtered = filterCustomers();
                    el.count.textContent = `Daftar Pelanggan (${filtered.length})`;
                    renderFeatured(filtered[0]);
                    renderList(filtered);
                };

                const loadCustomers = async () => {
                    const response = await fetch('/api/v1/customers', { headers });
                    if (!response.ok) {
                        el.featured.innerHTML = '<div class="text-sm text-red-500">Gagal memuat pelanggan.</div>';
                        return;
                    }
                    const data = await response.json();
                    state.customers = data.customers || [];
                    render();
                };

                el.search.addEventListener('input', (event) => {
                    state.term = event.target.value;
                    render();
                });

                el.filterBar.addEventListener('click', (event) => {
                    const button = event.target.closest('button[data-filter]');
                    if (!button) {
                        return;
                    }
                    state.filter = button.dataset.filter;
                    document.querySelectorAll('#filter-bar button').forEach((btn) => {
                        btn.classList.toggle('bg-primary', btn === button);
                        btn.classList.toggle('text-white', btn === button);
                        btn.classList.toggle('bg-slate-100', btn !== button);
                        btn.classList.toggle('text-slate-600', btn !== button);
                    });
                    render();
                });

                loadCustomers();
            })();
        </script>
@endsection
