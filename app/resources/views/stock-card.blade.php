@extends('layouts.app')

@section('page_title', 'Kartu Stok Produk (Ledger)')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-slate-900 shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto p-0')

@push('head')
@endpush

@php
    $stockCardCurrentPath = $currentPath ?? '/backoffice/inventory/products/'.((string) ($productId ?? request()->query('product_id', ''))).'/stock-card';
    $stockCardBackUrl = $backUrl ?? '/backoffice/reports/inventory';
    $resolvedProductId = (string) ($productId ?? request()->query('product_id', ''));
@endphp

@section('header')
        <x-page-header title="Kartu Stok Produk" :backUrl="$stockCardBackUrl">
            <x-slot name="actions">
                <button class="flex size-9 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    <span class="material-symbols-outlined text-[20px]">search</span>
                </button>
            </x-slot>
        </x-page-header>
@endsection

@section('content')
        <div id="product-picker" class="hidden">
            <div class="px-4 py-4 space-y-3 bg-white dark:bg-slate-900">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                    <input id="stock-card-search" class="w-full pl-10 pr-4 py-3 bg-white dark:bg-slate-900 border-none rounded-xl shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 focus:ring-2 focus:ring-primary text-sm" placeholder="Cari produk atau SKU..." type="text" />
                </div>
            </div>
            <div id="stock-card-list" class="flex flex-col"></div>
        </div>
        <div id="stock-card-detail">
        <div class="flex p-4 @container bg-white dark:bg-slate-900">
            <div class="flex w-full flex-col gap-4">
                <div class="flex gap-4 items-center">
                    <div id="product-image" class="bg-center bg-no-repeat aspect-square bg-cover rounded-xl min-h-24 w-24 bg-slate-100 dark:bg-slate-800"></div>
                    <div class="flex flex-col">
                        <p id="product-name" class="text-slate-900 dark:text-white text-xl font-bold leading-tight tracking-tight">-</p>
                        <p id="product-sku" class="text-slate-500 dark:text-slate-400 text-sm font-medium mt-1">SKU: -</p>
                        <div class="flex items-center mt-2">
                            <span id="product-category" class="bg-primary/10 text-primary text-xs font-bold px-2 py-1 rounded">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex gap-3 p-4 bg-white dark:bg-slate-900">
            <div class="flex flex-1 flex-col gap-1 rounded-xl p-4 border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="flex items-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-emerald-500 text-sm">arrow_downward</span>
                    <p class="text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider">Total Masuk</p>
                </div>
                <p id="total-in" class="text-slate-900 dark:text-white text-2xl font-bold">0</p>
                <p class="text-slate-400 text-[10px]" data-range-label>-</p>
            </div>
            <div class="flex flex-1 flex-col gap-1 rounded-xl p-4 border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50">
                <div class="flex items-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-rose-500 text-sm">arrow_upward</span>
                    <p class="text-slate-500 dark:text-slate-400 text-xs font-semibold uppercase tracking-wider">Total Keluar</p>
                </div>
                <p id="total-out" class="text-slate-900 dark:text-white text-2xl font-bold">0</p>
                <p class="text-slate-400 text-[10px]" data-range-label>-</p>
            </div>
        </div>
        <div class="px-4 py-2">
            <div class="bg-primary rounded-xl p-4 flex justify-between items-center shadow-lg shadow-primary/20">
                <div>
                    <p class="text-white/80 text-xs font-medium">Stok Saat Ini</p>
                    <p class="text-white text-2xl font-bold"><span id="current-stock">0</span> <span class="text-sm font-normal">Unit</span></p>
                </div>
                <div class="bg-white/20 p-2 rounded-lg">
                    <span class="material-symbols-outlined text-white">inventory_2</span>
                </div>
            </div>
        </div>
        <div class="px-4 pb-2">
            <x-period-filter id="stock-card-period" defaultPreset="last30" />
            <div class="mt-3 flex items-center gap-2 overflow-x-auto no-scrollbar">
                <button class="relative flex shrink-0 items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-700" type="button">
                    <span class="material-symbols-outlined text-sm">location_on</span>
                    <span id="stock-card-location-label">Semua Lokasi</span>
                    <span class="material-symbols-outlined text-sm">expand_more</span>
                    <select id="stock-card-location-filter" class="absolute inset-0 cursor-pointer opacity-0"></select>
                </button>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <label class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Tipe Movement</label>
                <select id="ledger-type-filter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="all">Semua</option>
                    <option value="purchase_receive">Pembelian</option>
                    <option value="sale">Penjualan</option>
                    <option value="stock_adjustment">Penyesuaian</option>
                    <option value="stock_transfer_in">Mutasi Masuk</option>
                    <option value="stock_transfer_out">Mutasi Keluar</option>
                </select>
                <span id="movement-count" class="text-[11px] text-slate-400">0 movement</span>
            </div>
            <p id="range-warning" class="hidden text-[11px] text-amber-600 mt-2">Rentang dibatasi otomatis (maksimum 90 hari).</p>
        </div>
        <div class="px-4 pt-2">
            <div class="flex items-center justify-between">
                <h3 class="text-slate-900 dark:text-white text-lg font-bold leading-tight tracking-tight">FIFO Batch</h3>
                <span id="fifo-method" class="text-[10px] uppercase tracking-widest text-slate-400">-</span>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-3 text-[11px] text-slate-500">
                <label class="inline-flex items-center gap-2">
                    <input id="fifo-show-empty" type="checkbox" class="size-4 rounded border-slate-300 text-primary focus:ring-primary" />
                    Tampilkan layer habis
                </label>
                <label class="inline-flex items-center gap-2">
                    <span>Filter alokasi</span>
                    <select id="fifo-alloc-filter" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                        <option value="all">Semua</option>
                        <option value="sale">Penjualan</option>
                        <option value="stock_transfer_out">Mutasi Keluar</option>
                        <option value="stock_adjustment">Penyesuaian</option>
                        <option value="purchase_return">Retur Pembelian</option>
                    </select>
                </label>
            </div>
            <p id="fifo-warning" class="hidden text-[11px] text-amber-600 mt-2">Metode valuasi saat ini Average. Data layer FIFO bisa tidak tersedia.</p>
            <div class="mt-3 grid gap-4">
                <div class="rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-slate-900 dark:text-white text-sm font-semibold">Layer Tersisa</p>
                        <span id="layer-count" class="text-[11px] text-slate-400">0</span>
                    </div>
                    <div id="layer-list" class="mt-3 flex flex-col gap-3"></div>
                </div>
                <div class="rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-slate-900 dark:text-white text-sm font-semibold">Alokasi Keluar</p>
                        <span id="alloc-count" class="text-[11px] text-slate-400">0</span>
                    </div>
                    <div id="alloc-list" class="mt-3 flex flex-col gap-3"></div>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-between px-4 pb-2 pt-6">
            <h3 class="text-slate-900 dark:text-white text-lg font-bold leading-tight tracking-tight">Riwayat Pergerakan</h3>
            <span id="range-chip" class="text-[10px] text-slate-400">-</span>
        </div>
        <div class="flex-1 flex flex-col px-4 pb-24">
            <div id="ledger-list" class="flex flex-col"></div>
            <button id="ledger-more" class="mt-6 py-3 w-full border border-slate-200 dark:border-slate-700 rounded-lg text-slate-500 dark:text-slate-400 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors hidden" type="button">
                Muat Lebih Banyak
            </button>
        </div>
        </div>
        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const params = new URLSearchParams(window.location.search);
                const productId = @json($resolvedProductId) || params.get('product_id');
                const initialLocationId = params.get('location_id') || '';

                const el = {
                    image: document.getElementById('product-image'),
                    name: document.getElementById('product-name'),
                    sku: document.getElementById('product-sku'),
                    category: document.getElementById('product-category'),
                    totalIn: document.getElementById('total-in'),
                    totalOut: document.getElementById('total-out'),
                    currentStock: document.getElementById('current-stock'),
                    ledgerList: document.getElementById('ledger-list'),
                    rangeLabels: document.querySelectorAll('[data-range-label]'),
                    rangeChip: document.getElementById('range-chip'),
                    period: document.getElementById('stock-card-period'),
                    locationFilter: document.getElementById('stock-card-location-filter'),
                    locationLabel: document.getElementById('stock-card-location-label'),
                    ledgerType: document.getElementById('ledger-type-filter'),
                    movementCount: document.getElementById('movement-count'),
                    warning: document.getElementById('range-warning'),
                    fifoMethod: document.getElementById('fifo-method'),
                    fifoWarning: document.getElementById('fifo-warning'),
                    layerList: document.getElementById('layer-list'),
                    layerCount: document.getElementById('layer-count'),
                    allocList: document.getElementById('alloc-list'),
                    allocCount: document.getElementById('alloc-count'),
                    fifoShowEmpty: document.getElementById('fifo-show-empty'),
                    fifoAllocFilter: document.getElementById('fifo-alloc-filter'),
                    picker: document.getElementById('product-picker'),
                    pickerList: document.getElementById('stock-card-list'),
                    pickerSearch: document.getElementById('stock-card-search'),
                    detail: document.getElementById('stock-card-detail'),
                };

                const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                }).format(new Date(value));

                const formatQty = (value) => {
                    const qty = Number(value || 0);
                    if (Number.isNaN(qty)) {
                        return '0';
                    }
                    return Math.abs(qty % 1) < 0.0001 ? String(Math.round(qty)) : qty.toFixed(2);
                };

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2,
                }).format(Number(value || 0));

                const ledgerTypeMap = {
                    purchase_receive: { label: 'Pembelian (Stok Masuk)', tone: 'in' },
                    purchase: { label: 'Pembelian (Stok Masuk)', tone: 'in' },
                    sale: { label: 'Penjualan POS', tone: 'out' },
                    sales: { label: 'Penjualan POS', tone: 'out' },
                    stock_adjustment: { label: 'Penyesuaian Stok', tone: 'adjust' },
                    stock_transfer_in: { label: 'Mutasi Masuk', tone: 'in' },
                    stock_transfer_out: { label: 'Mutasi Keluar', tone: 'out' },
                    stock_transfer: { label: 'Mutasi Stok', tone: 'adjust' },
                    opening_balance: { label: 'Stok Awal Periode', tone: 'adjust' },
                };

                const layerSourceMap = {
                    purchase_receive: 'Pembelian',
                    purchase: 'Pembelian',
                    stock_adjustment: 'Penyesuaian',
                    stock_transfer_in: 'Mutasi Masuk',
                    stock_transfer: 'Mutasi Stok',
                    opening_balance: 'Stok Awal',
                };

                const resolveLedgerMeta = (row) => {
                    const key = String(row.reference_type || '').toLowerCase();
                    return ledgerTypeMap[key] || { label: row.reference_type || 'Pergerakan Stok', tone: 'adjust' };
                };

                const resolveLayerSource = (row) => {
                    const key = String(row.source_type || '').toLowerCase();
                    return layerSourceMap[key] || row.source_type || 'Layer FIFO';
                };

                const resolveAllocationLabel = (row) => {
                    const key = String(row.reference_type || '').toLowerCase();
                    return ledgerTypeMap[key]?.label || row.reference_type || 'Alokasi Keluar';
                };

                const loadBootstrap = async () => {
                    const response = await fetch('/api/v1/bootstrap', { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat daftar produk.');
                    }
                    return response.json();
                };

                const updateHistoryUrl = () => {
                    const url = new URL(window.location.href);
                    if (state.locationId) {
                        url.searchParams.set('location_id', state.locationId);
                    } else {
                        url.searchParams.delete('location_id');
                    }
                    window.history.replaceState({}, '', `${url.pathname}${url.search}`);
                };

                const buildLocationOptions = (locations) => {
                    if (!el.locationFilter) {
                        return;
                    }
                    el.locationFilter.innerHTML = '';
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = 'Semua Lokasi';
                    el.locationFilter.appendChild(defaultOption);

                    locations.forEach((location) => {
                        const option = document.createElement('option');
                        option.value = location.id;
                        option.textContent = location.name;
                        el.locationFilter.appendChild(option);
                    });

                    const hasSelectedLocation = locations.some((location) => location.id === state.locationId);
                    if (!hasSelectedLocation) {
                        state.locationId = '';
                    }

                    el.locationFilter.value = state.locationId;
                    const label = el.locationFilter.options[el.locationFilter.selectedIndex]?.textContent || 'Semua Lokasi';
                    if (el.locationLabel) {
                        el.locationLabel.textContent = label;
                    }
                };

                const renderProductList = (products, inventory) => {
                    const term = (state.term || '').toLowerCase();
                    const list = products.filter((product) => {
                        if (!term) {
                            return true;
                        }
                        return product.name.toLowerCase().includes(term)
                            || (product.sku || '').toLowerCase().includes(term);
                    });

                    if (!list.length) {
                        el.pickerList.innerHTML = '<div class="px-4 py-6 text-sm text-slate-400">Produk tidak ditemukan.</div>';
                        return;
                    }

                    el.pickerList.innerHTML = '';
                    list.forEach((product) => {
                        const qty = Math.round(inventory
                            .filter((item) => item.product_id === product.id)
                            .reduce((sum, item) => sum + (item.qty_on_hand || 0), 0));
                        const image = product.image_url || 'https://placehold.co/120x120?text=Produk';
                        const qtyColor = qty === 0 ? 'text-rose-600' : 'text-slate-900 dark:text-slate-100';

                        const detailUrl = new URL(`/backoffice/inventory/products/${encodeURIComponent(product.id)}/stock-card`, window.location.origin);
                        detailUrl.searchParams.set('from', @json($stockCardCurrentPath));
                        if (state.locationId) {
                            detailUrl.searchParams.set('location_id', state.locationId);
                        }

                        const card = document.createElement('a');
                        card.href = `${detailUrl.pathname}${detailUrl.search}`;
                        card.className = 'flex items-center gap-4 bg-white dark:bg-slate-900 px-4 py-4 border-b border-slate-50 dark:border-slate-800/50';
                        card.innerHTML = `
                            <div class="bg-center bg-no-repeat aspect-square bg-cover rounded-lg size-14 bg-slate-100 dark:bg-slate-800" style="background-image: url('${image}')"></div>
                            <div class="flex flex-col flex-1">
                                <p class="text-slate-900 dark:text-slate-100 text-base font-semibold leading-snug">${product.name}</p>
                                <p class="text-slate-500 text-xs mt-0.5 uppercase tracking-wide">SKU: ${product.sku || '-'}</p>
                            </div>
                            <div class="flex flex-col items-end shrink-0">
                                <p class="${qtyColor} text-lg font-bold">${qty}</p>
                                <p class="text-slate-400 text-[10px] uppercase font-bold">Unit</p>
                            </div>
                        `;
                        el.pickerList.appendChild(card);
                    });
                };

                const renderLedger = (rows) => {
                    el.ledgerList.innerHTML = '';

                    if (!rows.length) {
                        el.ledgerList.innerHTML = '<div class="py-6 text-sm text-slate-400">Belum ada pergerakan stok.</div>';
                        return;
                    }

                    rows.forEach((row) => {
                        const meta = resolveLedgerMeta(row);
                        const qtyIn = Number(row.qty_in || 0);
                        const qtyOut = Number(row.qty_out || 0);
                        const delta = qtyIn > 0 ? qtyIn : -qtyOut;
                        const isIn = delta >= 0;
                        const qtyLabel = `${isIn ? '+' : ''}${formatQty(delta)}`;
                        const icon = isIn ? 'add_circle' : 'remove_circle';
                        const toneClass = isIn ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400';
                        const bgClass = isIn ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-rose-100 dark:bg-rose-900/30';
                        const iconClass = isIn ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400';

                        const card = document.createElement('div');
                        card.className = 'py-4 border-b border-slate-100 dark:border-slate-800 flex items-center gap-4';
                        card.innerHTML = `
                            <div class="size-10 rounded-full ${bgClass} flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined ${iconClass}">${icon}</span>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between">
                                    <p class="text-slate-900 dark:text-white font-semibold text-sm">${meta.label}</p>
                                    <p class="font-bold text-sm ${toneClass}">${qtyLabel}</p>
                                </div>
                                <div class="flex justify-between items-end mt-0.5">
                                    <p class="text-slate-500 dark:text-slate-400 text-xs">${formatDate(row.created_at)}</p>
                                    <p class="text-slate-400 dark:text-slate-500 text-[10px] font-medium uppercase tracking-tighter">Saldo: ${formatQty(row.balance_after)}</p>
                                </div>
                            </div>
                        `;
                        el.ledgerList.appendChild(card);
                    });
                };

                const renderLayers = (rows) => {
                    el.layerList.innerHTML = '';

                    if (!rows.length) {
                        el.layerCount.textContent = '0';
                        el.layerList.innerHTML = '<div class="text-xs text-slate-400">Layer FIFO tidak tersedia.</div>';
                        return;
                    }

                    const totalQty = rows.reduce((sum, row) => sum + Number(row.qty_remaining || 0), 0);
                    const totalValue = rows.reduce((sum, row) => sum + (Number(row.qty_remaining || 0) * Number(row.cost_per_unit || 0)), 0);
                    el.layerCount.textContent = `${formatQty(totalQty)} unit · ${formatCurrency(totalValue)}`;

                    rows.forEach((row) => {
                        const qtyRemaining = Number(row.qty_remaining || 0);
                        const qtyOriginal = Number(row.qty_original || 0);
                        const value = qtyRemaining * Number(row.cost_per_unit || 0);
                        const card = document.createElement('div');
                        card.className = 'rounded-lg border border-slate-100 dark:border-slate-800 p-3';
                        card.innerHTML = `
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">${resolveLayerSource(row)}</p>
                                    <p class="text-[11px] text-slate-400">Diterima: ${row.received_at ? formatDate(row.received_at) : '-'}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">${formatQty(qtyRemaining)} unit</p>
                                    <p class="text-[11px] text-slate-400">Awal: ${formatQty(qtyOriginal)} · ${formatCurrency(row.cost_per_unit)}</p>
                                </div>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-[11px] text-slate-400">
                                <span>Nilai sisa</span>
                                <span class="text-slate-600 dark:text-slate-300 font-semibold">${formatCurrency(value)}</span>
                            </div>
                        `;
                        el.layerList.appendChild(card);
                    });
                };

                const renderAllocations = (rows) => {
                    el.allocList.innerHTML = '';

                    if (!rows.length) {
                        el.allocCount.textContent = '0';
                        el.allocList.innerHTML = '<div class="text-xs text-slate-400">Belum ada alokasi keluar di periode ini.</div>';
                        return;
                    }

                    const totalQty = rows.reduce((sum, row) => sum + Number(row.qty || 0), 0);
                    const totalCost = rows.reduce((sum, row) => sum + Number(row.total_cost || 0), 0);
                    el.allocCount.textContent = `${formatQty(totalQty)} unit · ${formatCurrency(totalCost)}`;

                    rows.forEach((row) => {
                        const card = document.createElement('div');
                        card.className = 'rounded-lg border border-slate-100 dark:border-slate-800 p-3';
                        const reversedLabel = row.reversed_at ? '<span class="ml-2 text-[10px] uppercase tracking-widest text-amber-500">Reversed</span>' : '';
                        card.innerHTML = `
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">${resolveAllocationLabel(row)}${reversedLabel}</p>
                                    <p class="text-[11px] text-slate-400">${row.created_at ? formatDate(row.created_at) : '-'}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">${formatQty(row.qty)} unit</p>
                                    <p class="text-[11px] text-slate-400">${formatCurrency(row.unit_cost)} / unit</p>
                                </div>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-[11px] text-slate-400">
                                <span>Layer: ${row.layer_received_at ? formatDate(row.layer_received_at) : '-'}</span>
                                <span class="text-slate-600 dark:text-slate-300 font-semibold">${formatCurrency(row.total_cost)}</span>
                            </div>
                        `;
                        el.allocList.appendChild(card);
                    });
                };

                const loadStockCard = async (period) => {
                    const url = new URL('/api/v1/stock-card', window.location.origin);
                    if (productId) {
                        url.searchParams.set('product_id', productId);
                    }
                    if (state.locationId) {
                        url.searchParams.set('location_id', state.locationId);
                    }
                    if (period?.preset) {
                        url.searchParams.set('preset', period.preset);
                    }
                    if (period?.start_date) {
                        url.searchParams.set('start_date', period.start_date);
                    }
                    if (period?.end_date) {
                        url.searchParams.set('end_date', period.end_date);
                    }
                    if (state.ledgerType && state.ledgerType !== 'all') {
                        url.searchParams.set('type', state.ledgerType);
                    }
                    if (state.showEmptyLayers) {
                        url.searchParams.set('include_empty_layers', '1');
                    }
                    if (state.allocationType && state.allocationType !== 'all') {
                        url.searchParams.set('allocation_type', state.allocationType);
                    }

                    const response = await fetch(url, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat kartu stok.');
                    }
                    return response.json();
                };

                const renderRange = (range) => {
                    if (!range?.start_date) {
                        el.rangeLabels.forEach((item) => {
                            item.textContent = '-';
                        });
                        if (el.rangeChip) el.rangeChip.textContent = '-';
                        if (el.warning) el.warning.classList.add('hidden');
                        return;
                    }
                    const label = range.label || 'Periode';
                    const text = `${label} (${range.start_date} - ${range.end_date})`;
                    el.rangeLabels.forEach((item) => {
                        item.textContent = text;
                    });
                    if (el.rangeChip) el.rangeChip.textContent = text;
                    if (el.warning) el.warning.classList.toggle('hidden', !range.limited);
                };

                const state = {
                    period: { preset: 'last30', start_date: '', end_date: '' },
                    locationId: initialLocationId,
                    ledgerType: 'all',
                    showEmptyLayers: false,
                    allocationType: 'all',
                    term: '',
                };

                const reload = async () => {
                    const data = await loadStockCard(state.period);
                    return data;
                };

                el.period?.addEventListener('period:change', (event) => {
                    state.period = event.detail;
                    reload()
                        .then((data) => {
                            if (!data.product) {
                                el.name.textContent = 'Produk tidak ditemukan';
                                return;
                            }
                            const imageUrl = data.product.image_url || 'https://placehold.co/200x200?text=Produk';
                            el.image.style.backgroundImage = `url("${imageUrl}")`;
                            el.name.textContent = data.product.name || '-';
                            el.sku.textContent = `SKU: ${data.product.sku || '-'}`;
                            el.category.textContent = data.product.category_name || 'Produk';
                            el.currentStock.textContent = formatQty(data.balance || 0);

                            const ledger = Array.isArray(data.ledger) ? data.ledger : [];
                            el.totalIn.textContent = formatQty(data.summary?.total_in || 0);
                            el.totalOut.textContent = formatQty(data.summary?.total_out || 0);
                            if (el.movementCount) {
                                el.movementCount.textContent = `${data.summary?.movement_count || 0} movement`;
                            }

                            el.fifoMethod.textContent = String(data.valuation_method || '-').toUpperCase();
                            el.fifoWarning.classList.toggle('hidden', String(data.valuation_method || '').toLowerCase() === 'fifo');
                            renderLayers(Array.isArray(data.layers) ? data.layers : []);
                            renderAllocations(Array.isArray(data.allocations) ? data.allocations : []);

                            renderRange(data.range);
                            renderLedger(ledger);
                        })
                        .catch((error) => {
                            el.name.textContent = error.message || 'Gagal memuat kartu stok.';
                            el.ledgerList.innerHTML = '<div class="py-6 text-sm text-red-500">Gagal memuat pergerakan stok.</div>';
                        });
                });

                el.fifoShowEmpty?.addEventListener('change', (event) => {
                    state.showEmptyLayers = event.target?.checked ?? false;
                    reload().catch((error) => {
                        el.layerList.innerHTML = `<div class="text-xs text-red-500">${error.message || 'Gagal memuat layer FIFO.'}</div>`;
                    });
                });

                el.fifoAllocFilter?.addEventListener('change', (event) => {
                    state.allocationType = event.target?.value || 'all';
                    reload().catch((error) => {
                        el.allocList.innerHTML = `<div class="text-xs text-red-500">${error.message || 'Gagal memuat alokasi.'}</div>`;
                    });
                });

                el.ledgerType?.addEventListener('change', (event) => {
                    state.ledgerType = event.target?.value || 'all';
                    reload()
                        .then((data) => {
                            const ledger = Array.isArray(data.ledger) ? data.ledger : [];
                            el.totalIn.textContent = formatQty(data.summary?.total_in || 0);
                            el.totalOut.textContent = formatQty(data.summary?.total_out || 0);
                            if (el.movementCount) {
                                el.movementCount.textContent = `${data.summary?.movement_count || 0} movement`;
                            }
                            renderRange(data.range);
                            renderLedger(ledger);
                        })
                        .catch((error) => {
                            el.ledgerList.innerHTML = `<div class="py-6 text-sm text-red-500">${error.message || 'Gagal memuat movement stok.'}</div>`;
                        });
                });

                el.locationFilter?.addEventListener('change', (event) => {
                    state.locationId = event.target?.value || '';
                    const label = el.locationFilter.options[el.locationFilter.selectedIndex]?.textContent || 'Semua Lokasi';
                    if (el.locationLabel) {
                        el.locationLabel.textContent = label;
                    }
                    updateHistoryUrl();

                    if (!productId) {
                        loadBootstrap()
                            .then((payload) => {
                                const data = window.PosUi.unwrapApiPayload(payload) || payload;
                                const products = data.products || [];
                                const inventory = Array.isArray(data.inventory)
                                    ? data.inventory.filter((item) => !state.locationId || item.location_id === state.locationId)
                                    : [];
                                state.term = el.pickerSearch?.value || '';
                                renderProductList(products, inventory);
                            })
                            .catch(() => {
                                el.pickerList.innerHTML = '<div class="px-4 py-6 text-sm text-red-500">Gagal memuat daftar produk.</div>';
                            });
                        return;
                    }

                    reload()
                        .then((data) => {
                            if (!data.product) {
                                el.name.textContent = 'Produk tidak ditemukan';
                                return;
                            }
                            el.currentStock.textContent = formatQty(data.balance || 0);
                            el.totalIn.textContent = formatQty(data.summary?.total_in || 0);
                            el.totalOut.textContent = formatQty(data.summary?.total_out || 0);
                            if (el.movementCount) {
                                el.movementCount.textContent = `${data.summary?.movement_count || 0} movement`;
                            }
                            renderLayers(Array.isArray(data.layers) ? data.layers : []);
                            renderAllocations(Array.isArray(data.allocations) ? data.allocations : []);
                            renderRange(data.range);
                            renderLedger(Array.isArray(data.ledger) ? data.ledger : []);
                        })
                        .catch((error) => {
                            el.ledgerList.innerHTML = `<div class="py-6 text-sm text-red-500">${error.message || 'Gagal memuat kartu stok.'}</div>`;
                        });
                });

                (async () => {
                    try {
                        const bootstrapPayload = await loadBootstrap();
                        const bootstrapData = window.PosUi.unwrapApiPayload(bootstrapPayload) || bootstrapPayload;
                        const locations = Array.isArray(bootstrapData.locations) ? bootstrapData.locations : [];
                        buildLocationOptions(locations);

                        if (!productId) {
                            el.picker.classList.remove('hidden');
                            el.detail.classList.add('hidden');
                            const products = bootstrapData.products || [];
                            const inventory = Array.isArray(bootstrapData.inventory)
                                ? bootstrapData.inventory.filter((item) => !state.locationId || item.location_id === state.locationId)
                                : [];
                            renderProductList(products, inventory);
                            el.pickerSearch?.addEventListener('input', (event) => {
                                state.term = event.target.value || '';
                                renderProductList(products, inventory);
                            });
                            return;
                        }

                        el.picker.classList.add('hidden');
                        el.detail.classList.remove('hidden');
                        const data = await reload();
                        if (!data.product) {
                            el.name.textContent = 'Produk tidak ditemukan';
                            return;
                        }
                        const imageUrl = data.product.image_url || 'https://placehold.co/200x200?text=Produk';
                        el.image.style.backgroundImage = `url("${imageUrl}")`;
                        el.name.textContent = data.product.name || '-';
                        el.sku.textContent = `SKU: ${data.product.sku || '-'}`;
                        el.category.textContent = data.product.category_name || 'Produk';
                        el.currentStock.textContent = formatQty(data.balance || 0);

                        const ledger = Array.isArray(data.ledger) ? data.ledger : [];
                        el.totalIn.textContent = formatQty(data.summary?.total_in || 0);
                        el.totalOut.textContent = formatQty(data.summary?.total_out || 0);
                        if (el.movementCount) {
                            el.movementCount.textContent = `${data.summary?.movement_count || 0} movement`;
                        }
                        el.fifoMethod.textContent = String(data.valuation_method || '-').toUpperCase();
                        el.fifoWarning.classList.toggle('hidden', String(data.valuation_method || '').toLowerCase() === 'fifo');
                        renderLayers(Array.isArray(data.layers) ? data.layers : []);
                        renderAllocations(Array.isArray(data.allocations) ? data.allocations : []);
                        renderRange(data.range);

                        renderLedger(ledger);
                    } catch (error) {
                        el.name.textContent = error.message || 'Gagal memuat kartu stok.';
                        el.ledgerList.innerHTML = '<div class="py-6 text-sm text-red-500">Gagal memuat pergerakan stok.</div>';
                    }
                })();
            })();
        </script>
@endsection
