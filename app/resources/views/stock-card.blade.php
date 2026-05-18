@extends('layouts.app')

@section('page_title', 'Kartu Stok Produk (Ledger)')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-slate-900 shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto p-0')

@push('head')
@endpush

@php
    $stockCardPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $resolvedProductId = (string) ($productId ?? request()->query('product_id', ''));
    $stockCardFrom = trim((string) request()->query('from', ''));
    $stockCardCurrentPath = $currentPath ?? ($resolvedProductId !== ''
        ? $stockCardPortalPrefix.'/inventory/products/'.$resolvedProductId.'/stock-card'
        : $stockCardPortalPrefix.'/inventory/stock-card');
    $stockCardBackUrl = $backUrl ?? ($stockCardPortalPrefix === '/pos' ? '/pos/inventory' : '/backoffice/reports/inventory');
    if ($stockCardFrom !== '' && ! isset($backUrl)) {
        if (str_starts_with($stockCardFrom, $stockCardPortalPrefix.'/')) {
            $stockCardBackUrl = $stockCardFrom;
        } elseif ($stockCardPortalPrefix === '/backoffice' && str_starts_with($stockCardFrom, '/pos/')) {
            $stockCardBackUrl = '/portal/switch/pos?next='.rawurlencode($stockCardFrom);
        } elseif ($stockCardPortalPrefix === '/pos' && str_starts_with($stockCardFrom, '/backoffice/')) {
            $stockCardBackUrl = '/portal/switch/backoffice?next='.rawurlencode($stockCardFrom);
        }
    }
    $stockCardBackUrl = normalize_pos_home_url($stockCardBackUrl);
    $resolvedProductVariantId = (string) request()->query('product_variant_id', '');
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
                    <p class="text-white text-2xl font-bold"><span id="current-stock">0</span> <span id="current-stock-unit" class="text-sm font-normal">unit</span></p>
                </div>
                <div class="bg-white/20 p-2 rounded-lg">
                    <span class="material-symbols-outlined text-white">inventory_2</span>
                </div>
            </div>
        </div>
        <div class="px-4 pb-2">
            <div class="grid gap-3">
                <div class="rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-slate-900 dark:text-white text-sm font-semibold">Stok per Lokasi</p>
                        <span id="location-bucket-count" class="text-[11px] text-slate-400 uppercase tracking-widest">0 lokasi</span>
                    </div>
                    <div id="location-bucket-list" class="mt-3 flex flex-col gap-2"></div>
                </div>
                <div class="rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-slate-900 dark:text-white text-sm font-semibold">Stok per Varian</p>
                        <span id="variant-bucket-count" class="text-[11px] text-slate-400 uppercase tracking-widest">0 varian</span>
                    </div>
                    <div id="variant-bucket-list" class="mt-3 flex flex-col gap-2"></div>
                </div>
                <div class="rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-slate-900 dark:text-white text-sm font-semibold">Detail Costing</p>
                        <span id="cost-method" class="text-[11px] text-slate-400 uppercase tracking-widest">-</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-slate-50 dark:bg-slate-800/60 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">Average Cost</p>
                            <p id="average-cost-value" class="mt-1 text-sm font-bold text-slate-900 dark:text-white">-</p>
                        </div>
                        <div class="rounded-lg bg-slate-50 dark:bg-slate-800/60 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">Nilai Stok</p>
                            <p id="stock-value-value" class="mt-1 text-sm font-bold text-slate-900 dark:text-white">-</p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">Sumber cost terakhir</p>
                        <p id="last-cost-source" class="mt-1 text-sm font-semibold text-primary">-</p>
                    </div>
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
                <button id="stock-card-variant-control" class="relative hidden shrink-0 items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-700" type="button">
                    <span class="material-symbols-outlined text-sm">tune</span>
                    <span id="stock-card-variant-label">Semua Varian</span>
                    <span class="material-symbols-outlined text-sm">expand_more</span>
                    <select id="stock-card-variant-filter" class="absolute inset-0 cursor-pointer opacity-0"></select>
                </button>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <label class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Tipe Movement</label>
                <select id="ledger-type-filter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="all">Semua</option>
                    <option value="purchase_receive">Pembelian</option>
                    <option value="sale">Penjualan</option>
                    <option value="stock_adjustment">Penyesuaian</option>
                    <option value="stock_opname">Stock Opname</option>
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
                        <option value="stock_opname">Stock Opname</option>
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
                const stockCardPortalPrefix = @json($stockCardPortalPrefix);
                const params = new URLSearchParams(window.location.search);
                const productId = @json($resolvedProductId) || params.get('product_id');
                const initialLocationId = params.get('location_id') || '';
                const initialVariantId = @json($resolvedProductVariantId) || params.get('product_variant_id') || '';

                const el = {
                    image: document.getElementById('product-image'),
                    name: document.getElementById('product-name'),
                    sku: document.getElementById('product-sku'),
                    category: document.getElementById('product-category'),
                    totalIn: document.getElementById('total-in'),
                    totalOut: document.getElementById('total-out'),
                    currentStock: document.getElementById('current-stock'),
                    currentStockUnit: document.getElementById('current-stock-unit'),
                    ledgerList: document.getElementById('ledger-list'),
                    rangeLabels: document.querySelectorAll('[data-range-label]'),
                    rangeChip: document.getElementById('range-chip'),
                    period: document.getElementById('stock-card-period'),
                    locationFilter: document.getElementById('stock-card-location-filter'),
                    locationLabel: document.getElementById('stock-card-location-label'),
                    variantControl: document.getElementById('stock-card-variant-control'),
                    variantFilter: document.getElementById('stock-card-variant-filter'),
                    variantLabel: document.getElementById('stock-card-variant-label'),
                    ledgerType: document.getElementById('ledger-type-filter'),
                    movementCount: document.getElementById('movement-count'),
                    warning: document.getElementById('range-warning'),
                    locationBucketCount: document.getElementById('location-bucket-count'),
                    locationBucketList: document.getElementById('location-bucket-list'),
                    variantBucketCount: document.getElementById('variant-bucket-count'),
                    variantBucketList: document.getElementById('variant-bucket-list'),
                    costMethod: document.getElementById('cost-method'),
                    averageCostValue: document.getElementById('average-cost-value'),
                    stockValueValue: document.getElementById('stock-value-value'),
                    lastCostSource: document.getElementById('last-cost-source'),
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

                const resolveUnitLabel = (value) => {
                    const unit = String(value || '').trim();
                    return unit !== '' ? unit : 'unit';
                };

                const ledgerTypeMap = {
                    purchase_receive: { label: 'Pembelian (Stok Masuk)', tone: 'in' },
                    purchase: { label: 'Pembelian (Stok Masuk)', tone: 'in' },
                    sale: { label: 'Penjualan POS', tone: 'out' },
                    online_order: { label: 'Penjualan Online', tone: 'out' },
                    sales: { label: 'Penjualan POS', tone: 'out' },
                    stock_adjustment: { label: 'Penyesuaian Stok', tone: 'adjust' },
                    stock_opname: { label: 'Stock Opname', tone: 'adjust' },
                    stock_transfer_in: { label: 'Mutasi Masuk', tone: 'in' },
                    stock_transfer_out: { label: 'Mutasi Keluar', tone: 'out' },
                    stock_transfer: { label: 'Mutasi Stok', tone: 'adjust' },
                    opening_balance: { label: 'Stok Awal Periode', tone: 'adjust' },
                };

                const layerSourceMap = {
                    purchase_receive: 'Pembelian',
                    purchase: 'Pembelian',
                    stock_adjustment: 'Penyesuaian',
                    stock_opname: 'Stock Opname',
                    stock_transfer_in: 'Mutasi Masuk',
                    stock_transfer: 'Mutasi Stok',
                    opening_balance: 'Stok Awal',
                };

                const resolveLedgerMeta = (row) => {
                    if (row.source_label) {
                        const isSale = ['sale', 'sale_recipe_input'].includes(String(row.reference_type || '').toLowerCase());
                        return { label: row.source_label, tone: isSale ? 'out' : 'adjust' };
                    }

                    const key = String(row.reference_type || '').toLowerCase();
                    return ledgerTypeMap[key] || { label: row.reference_type || 'Pergerakan Stok', tone: 'adjust' };
                };

                const resolveLayerSource = (row) => {
                    const key = String(row.source_type || '').toLowerCase();
                    return layerSourceMap[key] || row.source_type || 'Layer FIFO';
                };

                const resolveAllocationLabel = (row) => {
                    if (row.source_label) {
                        return row.source_label;
                    }

                    const key = String(row.reference_type || '').toLowerCase();
                    return ledgerTypeMap[key]?.label || row.reference_type || 'Alokasi Keluar';
                };

                const resolveSourceLabel = (referenceType) => {
                    const key = String(referenceType || '').toLowerCase();
                    const labels = {
                        purchase: 'Pembelian',
                        purchase_receive: 'Pembelian',
                        sale: 'Penjualan',
                        sale_recipe_input: 'Penjualan',
                        online_order: 'Penjualan Online',
                        stock_adjustment: 'Penyesuaian',
                        stock_opname: 'Stock Opname',
                        stock_transfer: 'Mutasi Stok',
                        stock_transfer_in: 'Mutasi Stok',
                        stock_transfer_out: 'Mutasi Stok',
                        production_batch: 'Produksi',
                        opening_balance: 'Stok Awal',
                    };

                    return labels[key] || (key !== '' ? key.replaceAll('_', ' ') : 'Sumber Stok');
                };

                const buildSourceHref = (row) => {
                    const referenceType = String(row?.reference_type || '').toLowerCase();
                    const referenceId = String(row?.reference_id || '').trim();
                    if (!referenceId) {
                        return null;
                    }

                    const targetMap = {
                        purchase: `${stockCardPortalPrefix}/purchases/${encodeURIComponent(referenceId)}`,
                        purchase_receive: `${stockCardPortalPrefix}/purchases/${encodeURIComponent(referenceId)}`,
                        sale: `${stockCardPortalPrefix}/sales/${encodeURIComponent(referenceId)}`,
                        sale_recipe_input: `${stockCardPortalPrefix}/sales/${encodeURIComponent(referenceId)}`,
                        production_batch: `${stockCardPortalPrefix}/inventory/production-batches/${encodeURIComponent(referenceId)}`,
                    };

                    return targetMap[referenceType] || null;
                };

                const loadBootstrap = async () => {
                    return window.PosUi.getBootstrap({ headers, ttlMs: 20000 });
                };

                const updateHistoryUrl = () => {
                    const url = new URL(window.location.href);
                    if (state.locationId) {
                        url.searchParams.set('location_id', state.locationId);
                    } else {
                        url.searchParams.delete('location_id');
                    }
                    if (state.variantId) {
                        url.searchParams.set('product_variant_id', state.variantId);
                    } else {
                        url.searchParams.delete('product_variant_id');
                    }
                    window.history.replaceState({}, '', `${url.pathname}${url.search}`);
                };

                const buildVariantOptions = (variants) => {
                    if (!el.variantFilter || !el.variantControl) {
                        return;
                    }

                    if (!Array.isArray(variants) || variants.length === 0) {
                        el.variantControl.classList.add('hidden');
                        el.variantFilter.innerHTML = '';
                        state.variantId = '';
                        if (el.variantLabel) {
                            el.variantLabel.textContent = 'Semua Varian';
                        }
                        return;
                    }

                    el.variantControl.classList.remove('hidden');
                    el.variantFilter.innerHTML = '';

                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = 'Semua Varian';
                    el.variantFilter.appendChild(defaultOption);

                    variants.forEach((variant) => {
                        const option = document.createElement('option');
                        option.value = variant.id;
                        option.textContent = `${variant.name}${variant.sku ? ` · ${variant.sku}` : ''}`;
                        el.variantFilter.appendChild(option);
                    });

                    const hasSelectedVariant = variants.some((variant) => variant.id === state.variantId);
                    if (!hasSelectedVariant) {
                        state.variantId = '';
                    }

                    el.variantFilter.value = state.variantId;
                    if (el.variantLabel) {
                        el.variantLabel.textContent = el.variantFilter.options[el.variantFilter.selectedIndex]?.textContent || 'Semua Varian';
                    }
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
                        option.textContent = location.is_default ? `${location.name} (default)` : location.name;
                        el.locationFilter.appendChild(option);
                    });

                    const hasSelectedLocation = locations.some((location) => location.id === state.locationId);
                    if (!hasSelectedLocation) {
                        const defaultLocation = locations.find((location) => location.is_default);
                        state.locationId = defaultLocation?.id || locations[0]?.id || '';
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
                        const unitLabel = resolveUnitLabel(product.unit);
                    const image = product.image_url || '/images/placeholders/product.svg';
                        const qtyColor = qty === 0 ? 'text-rose-600' : 'text-slate-900 dark:text-slate-100';

                        const detailUrl = new URL(`${stockCardPortalPrefix}/inventory/products/${encodeURIComponent(product.id)}/stock-card`, window.location.origin);
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
                                <p class="text-slate-400 text-[10px] uppercase font-bold">${unitLabel}</p>
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
                        const sourceHref = buildSourceHref(row);
                        const sourceLabel = `${row.source_label || resolveSourceLabel(row.reference_type)}${row.reference_id ? ` · ${String(row.reference_id).slice(0, 8).toUpperCase()}` : ''}`;

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
                                <div class="flex justify-between items-end mt-0.5 gap-3">
                                    <p class="text-slate-500 dark:text-slate-400 text-xs">${formatDate(row.created_at)}</p>
                                    <p class="text-slate-400 dark:text-slate-500 text-[10px] font-medium uppercase tracking-tighter">Saldo: ${formatQty(row.balance_after)}</p>
                                </div>
                                <div class="mt-1 flex items-center justify-between gap-3 text-[11px] text-slate-400">
                                    ${sourceHref
                                        ? `<a class="truncate font-semibold text-primary" href="${sourceHref}">Sumber: ${sourceLabel}</a>`
                                        : `<p class="truncate font-semibold text-slate-500">Sumber: ${sourceLabel}</p>`}
                                    ${row.reference_item_id ? `<span class="shrink-0 uppercase tracking-widest">Item: ${String(row.reference_item_id).slice(0, 8).toUpperCase()}</span>` : ''}
                                </div>
                            </div>
                        `;
                        el.ledgerList.appendChild(card);
                    });
                };

                const renderLayers = (rows) => {
                    el.layerList.innerHTML = '';
                    const unitLabel = resolveUnitLabel(state.product?.unit);

                    if (!rows.length) {
                        el.layerCount.textContent = '0';
                        el.layerList.innerHTML = '<div class="text-xs text-slate-400">Layer FIFO tidak tersedia.</div>';
                        return;
                    }

                    const totalQty = rows.reduce((sum, row) => sum + Number(row.qty_remaining || 0), 0);
                    const totalValue = rows.reduce((sum, row) => sum + (Number(row.qty_remaining || 0) * Number(row.cost_per_unit || 0)), 0);
                    el.layerCount.textContent = `${formatQty(totalQty)} ${unitLabel} · ${formatCurrency(totalValue)}`;

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
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">${formatQty(qtyRemaining)} ${unitLabel}</p>
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
                    const unitLabel = resolveUnitLabel(state.product?.unit);

                    if (!rows.length) {
                        el.allocCount.textContent = '0';
                        el.allocList.innerHTML = '<div class="text-xs text-slate-400">Belum ada alokasi keluar di periode ini.</div>';
                        return;
                    }

                    const totalQty = rows.reduce((sum, row) => sum + Number(row.qty || 0), 0);
                    const totalCost = rows.reduce((sum, row) => sum + Number(row.total_cost || 0), 0);
                    el.allocCount.textContent = `${formatQty(totalQty)} ${unitLabel} · ${formatCurrency(totalCost)}`;

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
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">${formatQty(row.qty)} ${unitLabel}</p>
                                    <p class="text-[11px] text-slate-400">${formatCurrency(row.unit_cost)} / ${unitLabel}</p>
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

                const renderBucketSummary = (rows, listEl, countEl, emptyMessage, unitLabel, kind = 'location') => {
                    if (!listEl || !countEl) {
                        return;
                    }

                    listEl.innerHTML = '';

                    if (!rows.length) {
                        countEl.textContent = kind === 'location' ? '0 lokasi' : '0 varian';
                        listEl.innerHTML = `<div class="text-xs text-slate-400">${emptyMessage}</div>`;
                        return;
                    }

                    countEl.textContent = kind === 'location' ? `${rows.length} lokasi` : `${rows.length} varian`;

                    rows.slice(0, 6).forEach((row) => {
                        const card = document.createElement('div');
                        card.className = 'flex items-center justify-between gap-3 rounded-lg border border-slate-100 dark:border-slate-800 px-3 py-2';
                        const title = kind === 'location'
                            ? `${row.name || '-'}${row.is_default ? ' (default)' : ''}`
                            : `${row.name || 'Tanpa Varian'}${row.sku ? ` · ${row.sku}` : ''}`;
                        card.innerHTML = `
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">${title}</p>
                                ${kind === 'location' && row.branch_id ? `<p class="text-[11px] text-slate-400">Cabang: ${row.branch_id}</p>` : '<p class="text-[11px] text-slate-400">Bucket stok</p>'}
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-slate-900 dark:text-white">${formatQty(row.qty_on_hand || 0)} <span class="text-[11px] font-normal text-slate-400">${unitLabel}</span></p>
                            </div>
                        `;
                        listEl.appendChild(card);
                    });
                };

                const renderCostSummary = (data) => {
                    const costState = data.cost_state || null;
                    const valuationMethod = String(data.valuation_method || '').toLowerCase();

                    if (el.costMethod) {
                        el.costMethod.textContent = valuationMethod === 'fifo' ? 'FIFO' : 'AVERAGE';
                    }

                    if (!costState) {
                        if (el.averageCostValue) {
                            el.averageCostValue.textContent = '-';
                        }
                        if (el.stockValueValue) {
                            el.stockValueValue.textContent = '-';
                        }
                        if (el.lastCostSource) {
                            el.lastCostSource.textContent = 'Belum ada cost state untuk bucket ini.';
                        }
                        return;
                    }

                    if (el.averageCostValue) {
                        el.averageCostValue.textContent = formatCurrency(costState.average_cost || 0);
                    }
                    if (el.stockValueValue) {
                        el.stockValueValue.textContent = formatCurrency(costState.stock_value || 0);
                    }

                    const source = data.last_cost_source || null;
                    if (!el.lastCostSource) {
                        return;
                    }

                    if (!source) {
                        el.lastCostSource.textContent = 'Sumber cost terakhir belum tercatat.';
                        return;
                    }

                    const href = buildSourceHref(source);
                    const text = `${resolveSourceLabel(source.reference_type)} · ${source.reference_id ? String(source.reference_id).slice(0, 8).toUpperCase() : '-'}`;
                    if (href) {
                        el.lastCostSource.innerHTML = `<a class="underline decoration-dotted underline-offset-4" href="${href}">${text}</a>`;
                    } else {
                        el.lastCostSource.textContent = text;
                    }
                };

                const renderDetailData = (data) => {
                    if (!data.product) {
                        el.name.textContent = 'Produk tidak ditemukan';
                        return;
                    }

                    state.product = data.product;
                    buildVariantOptions(Array.isArray(data.product_variants) ? data.product_variants : []);
                    el.currentStockUnit.textContent = resolveUnitLabel(data.product.unit);

                    const imageUrl = data.product.image_url || '/images/placeholders/product.svg';
                    el.image.style.backgroundImage = `url("${imageUrl}")`;
                    el.name.textContent = data.product.name || '-';
                    el.sku.textContent = `SKU: ${data.product.sku || '-'}`;
                    el.category.textContent = data.product.category_name || 'Produk';
                    el.currentStock.textContent = formatQty(data.balance || 0);

                    const locationBuckets = Array.isArray(data.product_location_balances) ? data.product_location_balances : [];
                    const variantBuckets = Array.isArray(data.product_variant_balances) ? data.product_variant_balances : [];
                    renderBucketSummary(locationBuckets, el.locationBucketList, el.locationBucketCount, 'Belum ada stok per lokasi.', resolveUnitLabel(data.product.unit), 'location');
                    renderBucketSummary(variantBuckets, el.variantBucketList, el.variantBucketCount, 'Belum ada stok per varian.', resolveUnitLabel(data.product.unit), 'variant');
                    renderCostSummary(data);

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
                };

                const loadStockCard = async (period) => {
                    const url = new URL('/api/v1/stock-card', window.location.origin);
                    if (productId) {
                        url.searchParams.set('product_id', productId);
                    }
                    if (state.variantId) {
                        url.searchParams.set('product_variant_id', state.variantId);
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
                    variantId: initialVariantId,
                    term: '',
                    product: null,
                };

                const reload = async () => {
                    const data = await loadStockCard(state.period);
                    return data;
                };

                el.period?.addEventListener('period:change', (event) => {
                    state.period = event.detail;
                    reload()
                        .then((data) => {
                            renderDetailData(data);
                        })
                        .catch((error) => {
                            el.name.textContent = error.message || 'Gagal memuat kartu stok.';
                            el.ledgerList.innerHTML = '<div class="py-6 text-sm text-red-500">Gagal memuat pergerakan stok.</div>';
                        });
                });

                el.variantFilter?.addEventListener('change', (event) => {
                    state.variantId = event.target?.value || '';
                    const label = el.variantFilter.options[el.variantFilter.selectedIndex]?.textContent || 'Semua Varian';
                    if (el.variantLabel) {
                        el.variantLabel.textContent = label;
                    }
                    updateHistoryUrl();
                    reload()
                        .then((data) => {
                            renderDetailData(data);
                        })
                        .catch((error) => {
                            el.ledgerList.innerHTML = `<div class="py-6 text-sm text-red-500">${error.message || 'Gagal memuat kartu stok.'}</div>`;
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
                            renderDetailData(data);
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
                        if (!state.locationId) {
                            const defaultLocation = locations.find((location) => location.is_default);
                            state.locationId = defaultLocation?.id || locations[0]?.id || '';
                            if (el.locationFilter) {
                                el.locationFilter.value = state.locationId;
                            }
                            if (el.locationLabel) {
                                el.locationLabel.textContent = el.locationFilter?.options[el.locationFilter.selectedIndex]?.textContent || 'Semua Lokasi';
                            }
                        }

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
                        renderDetailData(data);
                    } catch (error) {
                        el.name.textContent = error.message || 'Gagal memuat kartu stok.';
                        el.ledgerList.innerHTML = '<div class="py-6 text-sm text-red-500">Gagal memuat pergerakan stok.</div>';
                    }
                })();
            })();
        </script>
@endsection
