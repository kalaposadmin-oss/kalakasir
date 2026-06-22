@extends('layouts.app')

@php
    $purchaseCreateTenant = \App\Support\TenantContext::get();
    $purchaseCreateUser = auth()->user();
    $purchaseCreateRole = null;

    if (! $purchaseCreateTenant && filled($purchaseCreateUser?->tenant_id)) {
        $purchaseCreateTenant = \App\Models\Tenant::query()->find($purchaseCreateUser->tenant_id);
    }

    if ($purchaseCreateTenant && $purchaseCreateUser) {
        $purchaseCreateRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $purchaseCreateTenant->id, (string) $purchaseCreateUser->id);

        if (! $purchaseCreateRole && filled($purchaseCreateUser->role ?? null)) {
            $purchaseCreateRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $purchaseCreateUser->role);
        }
    }

    abort_unless(in_array($purchaseCreateRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);
    $purchasePortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $purchaseListUrl = $purchasePortalPrefix.'/purchases';
    $purchaseDetailUrl = $purchasePortalPrefix.'/purchases';
    $purchaseTimezone = $purchaseCreateTenant?->timezone ?? config('app.timezone', 'Asia/Jakarta');
    $purchaseToday = \Carbon\CarbonImmutable::now($purchaseTimezone)->startOfDay();
    $purchaseMinDate = $purchaseToday->startOfMonth()->toDateString();
    $purchaseMaxDate = $purchaseToday->toDateString();
@endphp

@section('page_title', 'Form Buat PO Baru')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto px-4 py-6 space-y-6 pb-20')

@push('head')
        <style type="text/tailwindcss">
            .ios-blur {
                @apply backdrop-blur-md bg-white/80 dark:bg-background-dark/80;
            }
        </style>
@endpush

@section('header')
    <x-page-header title="Buat PO Baru" :backUrl="$purchaseListUrl" :showMenuButton="true">
        <x-slot:actions>
            <button class="text-primary font-semibold text-base">Panduan</button>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <section class="space-y-3">
        <div class="flex items-center justify-between px-1">
            <h3 class="text-[13px] font-semibold text-slate-500 uppercase tracking-wider">Informasi Pemasok</h3>
            <button id="open-supplier-modal" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1.5 text-[11px] font-bold text-primary" type="button">
                <span class="material-symbols-outlined text-sm">add</span>
                Tambah Pemasok
            </button>
        </div>
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="space-y-4">
                <div class="relative">
                    <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1">Pilih Pemasok</label>
                    <div class="relative">
                        <select id="supplier-select" class="w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 text-sm font-medium focus:ring-2 focus:ring-primary/20 appearance-none"></select>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">Tambah pemasok langsung dari halaman ini tanpa keluar dari form PO.</p>
                </div>
                <div class="flex items-center gap-3 px-1">
                    <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                        <span class="material-symbols-outlined text-slate-500 text-lg">event_note</span>
                    </div>
                    <div class="flex-1">
                        <label class="text-xs text-slate-400 block" for="order-date">Tanggal Pesanan</label>
                        <input id="order-date" class="mt-1 h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-800" type="date" min="{{ $purchaseMinDate }}" max="{{ $purchaseMaxDate }}" />
                        <p id="order-date-hint" class="mt-1 text-[11px] font-semibold text-slate-400">Boleh tanggal {{ $purchaseMinDate }} sampai {{ $purchaseMaxDate }}.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="space-y-3">
        <h3 class="text-[13px] font-semibold text-slate-500 uppercase tracking-wider px-1">Pembayaran</h3>
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="relative">
                <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1">Metode Pembayaran</label>
                <div class="relative">
                    <select id="payment-method" class="w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 text-sm font-medium focus:ring-2 focus:ring-primary/20 appearance-none">
                        <option value="tempo">Tempo</option>
                        <option value="tunai">Tunai</option>
                    </select>
                </div>
            </div>
            <div id="due-date-wrapper" class="relative">
                <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1">Jatuh Tempo</label>
                <input id="due-date" class="w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 text-sm font-medium focus:ring-2 focus:ring-primary/20" type="date" />
            </div>
            <div id="cash-account-wrapper" class="hidden rounded-xl border border-emerald-100 bg-emerald-50/80 p-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-600">Akun Kas Aktif</p>
                        <p id="cash-account-label" class="mt-1 text-sm font-semibold text-emerald-900">-</p>
                        <p class="mt-1 text-xs text-emerald-700">Tunai akan memakai akun kas yang diatur di bagan akun.</p>
                    </div>
                    <a class="text-xs font-bold text-primary shrink-0" href="/backoffice/settings/chart-of-accounts">Atur</a>
                </div>
            </div>
        </div>
    </section>
    <section class="space-y-3">
        <div class="flex items-center justify-between px-1">
            <h3 class="text-[13px] font-semibold text-slate-500 uppercase tracking-wider">Item Produk</h3>
            <button id="refresh-products" class="text-primary text-xs font-bold flex items-center gap-1" type="button">
                <span class="material-symbols-outlined text-sm">sync</span>
                Refresh Produk
            </button>
        </div>
        <p id="product-refresh-status" class="hidden px-1 text-xs font-semibold text-slate-500"></p>
        <p class="px-1 text-[11px] leading-5 text-slate-400">Form PO disimpan sementara di browser. Gunakan Refresh Produk untuk memuat produk baru tanpa menghapus item yang sudah diisi.</p>
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4" id="item-container"></div>
        <button id="add-item" class="w-full h-11 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl text-slate-400 text-sm font-semibold flex items-center justify-center gap-2 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors" type="button">
            <span class="material-symbols-outlined">add_circle</span>
            Tambah Item Lain
        </button>
    </section>

    <section class="space-y-3">
        <div class="flex items-center justify-between px-1">
            <div>
                <h3 class="text-[13px] font-semibold text-slate-500 uppercase tracking-wider">Biaya Tambahan</h3>
                <p class="mt-1 text-xs text-slate-400">Untuk plastik, kardus, ongkir, atau biaya supplier yang tidak menambah stok barang.</p>
            </div>
            <button id="add-additional-cost" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1.5 text-[11px] font-bold text-primary" type="button">
                <span class="material-symbols-outlined text-sm">add</span>
                Tambah
            </button>
        </div>
        <div id="additional-cost-list" class="space-y-3"></div>
    </section>
    <section class="bg-primary/5 dark:bg-primary/10 rounded-2xl p-4 border border-primary/10 space-y-3">
        <div class="flex justify-between items-center text-sm">
            <span class="text-slate-500">Subtotal</span>
            <span class="font-medium" id="subtotal">Rp 0</span>
        </div>
        <div class="flex justify-between items-center text-sm">
            <span class="text-slate-500">Biaya Tambahan</span>
            <span class="font-medium" id="additional-cost-total">Rp 0</span>
        </div>
        <div class="flex items-center justify-between gap-3 text-sm">
            <div class="flex items-center gap-2">
                <input id="tax-enabled" class="size-4 rounded border-slate-300 text-primary focus:ring-primary/20" type="checkbox" />
                <span class="text-slate-500">Pajak opsional</span>
            </div>
            <div id="tax-rate-wrapper" class="hidden flex items-center gap-2">
                <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900">
                    <input id="tax-rate" class="w-14 border-0 bg-transparent p-0 text-right text-sm font-semibold text-slate-900 outline-none focus:ring-0 dark:text-slate-100" type="number" min="0" step="0.1" value="11" />
                    <span class="text-slate-400">%</span>
                </div>
                <span class="font-medium tabular-nums" id="tax">Rp 0</span>
            </div>
        </div>
        <div class="pt-3 border-t border-primary/10 flex justify-between items-center">
            <span class="text-base font-bold text-slate-900 dark:text-white">Total Estimasi</span>
            <span class="text-lg font-bold text-primary tracking-tight" id="total">Rp 0</span>
        </div>
    </section>
    <section class="space-y-1.5 px-1">
        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Catatan Opsional</label>
        <textarea id="notes" class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-sm focus:ring-2 focus:ring-primary/20 placeholder:text-slate-400" placeholder="Tambahkan instruksi pengiriman..." rows="2"></textarea>
    </section>
    <div class="w-full p-4 ios-blur border-t border-slate-200 dark:border-slate-800 pb-10 mt-2">
        <div class="grid grid-cols-2 gap-3">
            <button id="buy-now" class="flex-1 bg-emerald-600 text-white py-4 rounded-2xl font-bold shadow-lg shadow-emerald-600/20 active:scale-[0.98] transition-transform" type="button">Beli &amp; Terima Sekarang</button>
            <button id="submit-po" class="flex-1 bg-primary text-white py-4 rounded-2xl font-bold shadow-lg shadow-primary/20 active:scale-[0.98] transition-transform" type="button">Buat PO</button>
        </div>
        <div class="mt-3 flex justify-center">
            <button id="save-draft" class="text-xs font-semibold text-slate-500 underline decoration-dotted underline-offset-4" type="button">Simpan Draft</button>
        </div>
        <div class="mt-5 flex justify-center">
            <div class="w-32 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
    </div>

    <div id="supplier-modal" class="fixed inset-0 z-50 hidden items-end justify-center bg-slate-950/40 px-4 py-6 sm:items-center">
        <div class="w-full max-w-[430px] rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 dark:border-slate-800">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Tambah Cepat</p>
                    <h4 class="text-base font-bold text-slate-900 dark:text-slate-100">Pemasok Baru</h4>
                </div>
                <button id="close-supplier-modal" class="size-9 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500" type="button">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Nama Pemasok</label>
                    <input id="supplier-name" class="w-full h-12 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-4 text-sm focus:ring-2 focus:ring-primary/20" placeholder="Contoh: CV Sumber Jaya" type="text" />
                </div>
                <p id="supplier-modal-error" class="hidden text-xs font-semibold text-rose-600"></p>
                <div class="flex gap-3 pt-1">
                    <button id="cancel-supplier-create" class="flex-1 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-3.5 text-sm font-bold text-slate-600 dark:text-slate-300" type="button">Batal</button>
                    <button id="save-supplier" class="flex-1 rounded-2xl bg-primary py-3.5 text-sm font-bold text-white shadow-lg shadow-primary/20" type="button">Simpan</button>
                </div>
            </div>
        </div>
    </div>

        @include('partials.ui-helpers')
        <script>
            (() => {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const purchaseStatusDraft = @json(\App\Support\PurchaseStatus::DRAFT);
                const purchaseStatusSubmitted = @json(\App\Support\PurchaseStatus::SUBMITTED);
                const purchaseModeSimple = @json(\App\Support\PurchaseMode::SIMPLE);
                const purchaseModePoShort = @json(\App\Support\PurchaseMode::PO_SHORT);
                const cashAccountManageUrl = '/backoffice/settings/chart-of-accounts';

                const el = {
                    supplier: document.getElementById('supplier-select'),
                    orderDate: document.getElementById('order-date'),
                    orderDateHint: document.getElementById('order-date-hint'),
                    container: document.getElementById('item-container'),
                    addItem: document.getElementById('add-item'),
                    refreshProducts: document.getElementById('refresh-products'),
                    productRefreshStatus: document.getElementById('product-refresh-status'),
                    subtotal: document.getElementById('subtotal'),
                    additionalCostTotal: document.getElementById('additional-cost-total'),
                    additionalCostList: document.getElementById('additional-cost-list'),
                    addAdditionalCost: document.getElementById('add-additional-cost'),
                    taxEnabled: document.getElementById('tax-enabled'),
                    taxRateWrapper: document.getElementById('tax-rate-wrapper'),
                    taxRate: document.getElementById('tax-rate'),
                    tax: document.getElementById('tax'),
                    total: document.getElementById('total'),
                    notes: document.getElementById('notes'),
                    paymentMethod: document.getElementById('payment-method'),
                    dueDate: document.getElementById('due-date'),
                    dueDateWrapper: document.getElementById('due-date-wrapper'),
                    cashAccountWrapper: document.getElementById('cash-account-wrapper'),
                    cashAccountLabel: document.getElementById('cash-account-label'),
                    buyNow: document.getElementById('buy-now'),
                    draft: document.getElementById('save-draft'),
                    submit: document.getElementById('submit-po'),
                    openSupplierModal: document.getElementById('open-supplier-modal'),
                    supplierModal: document.getElementById('supplier-modal'),
                    closeSupplierModal: document.getElementById('close-supplier-modal'),
                    cancelSupplierCreate: document.getElementById('cancel-supplier-create'),
                    supplierName: document.getElementById('supplier-name'),
                    supplierModalError: document.getElementById('supplier-modal-error'),
                    saveSupplier: document.getElementById('save-supplier'),
                };

                const state = {
                    products: [],
                    suppliers: [],
                    cashAccounts: [],
                    selectedCashAccountId: null,
                    cashAccountMapping: null,
                };
                const purchaseDraftStorageKey = `purchase-create-draft:${tenantId || 'tenant'}:${window.location.pathname}`;
                const purchaseDraftVersion = 1;
                let purchaseDraftTimer = null;

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const localDateInputValue = (date = new Date()) => {
                    const yyyy = date.getFullYear();
                    const mm = String(date.getMonth() + 1).padStart(2, '0');
                    const dd = String(date.getDate()).padStart(2, '0');
                    return `${yyyy}-${mm}-${dd}`;
                };

                const currentAllowedDateRange = () => {
                    const max = new Date();
                    max.setHours(0, 0, 0, 0);

                    return {
                        min: '',
                        max: localDateInputValue(max),
                    };
                };

                const syncOrderDateBounds = () => {
                    const range = currentAllowedDateRange();
                    el.orderDate.removeAttribute('min');
                    el.orderDate.max = range.max;
                    if (el.orderDateHint) {
                        el.orderDateHint.textContent = `Boleh tanggal sampai ${range.max}.`;
                    }

                    return range;
                };

                const setDefaultOrderDate = () => {
                    syncOrderDateBounds();
                    el.orderDate.value = localDateInputValue();
                };

                const validateOrderDate = () => {
                    const range = syncOrderDateBounds();
                    const value = el.orderDate.value || '';
                    const valid = !value || value <= range.max;
                    if (el.orderDateHint) {
                        el.orderDateHint.classList.toggle('text-rose-600', !valid);
                        el.orderDateHint.classList.toggle('text-slate-400', valid);
                    }

                    if (!valid) {
                        alert(`Tanggal pesanan ${value || '-'} tidak bisa dipakai. Pilih tanggal sampai ${range.max}.`);
                        el.orderDate.focus();
                    }

                    return valid;
                };

                const setDefaultDueDate = () => {
                    const now = new Date();
                    now.setDate(now.getDate() + 14);
                    const yyyy = now.getFullYear();
                    const mm = String(now.getMonth() + 1).padStart(2, '0');
                    const dd = String(now.getDate()).padStart(2, '0');
                    el.dueDate.value = `${yyyy}-${mm}-${dd}`;
                };

                const syncPaymentFields = () => {
                    const isTempo = (el.paymentMethod.value || 'tempo') === 'tempo';
                    el.dueDateWrapper.classList.toggle('hidden', !isTempo);
                    el.cashAccountWrapper.classList.toggle('hidden', (el.paymentMethod.value || 'tempo') !== 'tunai');
                };

                const renderCashAccountInfo = () => {
                    const activeAccount = state.cashAccounts.find((account) => account.id === state.selectedCashAccountId) || state.cashAccounts[0] || null;
                    if (!activeAccount) {
                        el.cashAccountLabel.textContent = 'Akun kas belum diatur';
                        el.cashAccountWrapper.classList.remove('hidden');
                        return;
                    }

                    el.cashAccountLabel.textContent = `${activeAccount.code || '-'} - ${activeAccount.name || '-'}`;
                };

                const getActiveCashAccountLabel = () => {
                    const activeAccount = state.cashAccounts.find((account) => account.id === state.selectedCashAccountId) || state.cashAccounts[0] || null;
                    if (!activeAccount) {
                        return 'akun kas aktif';
                    }

                    return `${activeAccount.code || '-'} - ${activeAccount.name || '-'}`;
                };

                const buildProductOptions = () => {
                    return state.products.map((product) => `<option value="${product.id}">${product.name}</option>`).join('');
                };

                const setProductRefreshStatus = (message, tone = 'info') => {
                    if (!el.productRefreshStatus) {
                        return;
                    }

                    el.productRefreshStatus.textContent = message;
                    el.productRefreshStatus.classList.toggle('hidden', !message);
                    el.productRefreshStatus.className = `px-1 text-xs font-semibold ${
                        tone === 'success' ? 'text-emerald-600' : (tone === 'error' ? 'text-rose-600' : 'text-slate-500')
                    }${message ? '' : ' hidden'}`;
                };

                const syncProductSelectOptions = () => {
                    el.container.querySelectorAll('.product-select').forEach((select) => {
                        const selectedProductId = select.value;
                        const wrapper = select.closest('.space-y-4');
                        const searchInput = wrapper?.querySelector('.product-search');
                        const selectedLabel = searchInput?.value || select.options[select.selectedIndex]?.textContent || 'Produk belum ada di daftar';
                        const productStillExists = state.products.some((product) => product.id === selectedProductId);

                        select.innerHTML = `
                            <option value="">Pilih Produk...</option>
                            ${selectedProductId && !productStillExists ? `<option value="${selectedProductId}">${selectedLabel}</option>` : ''}
                            ${buildProductOptions()}
                        `;
                        select.value = selectedProductId;
                        if (searchInput && selectedProductId) {
                            searchInput.value = getProductSearchLabel(selectedProductId, selectedLabel);
                        }
                        updateRowUnit(wrapper, selectedProductId);
                    });
                };

                const findProductById = (productId) => state.products.find((item) => item.id === productId) || null;

                const getProductSearchLabel = (productId, fallback = '') => {
                    const product = findProductById(productId);
                    return product?.name || fallback || '';
                };

                const normalizeProductSearch = (value) => String(value || '')
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, ' ')
                    .trim();

                const searchDistance = (left, right) => {
                    if (Math.abs(left.length - right.length) > 2) {
                        return 3;
                    }

                    const previous = Array.from({ length: right.length + 1 }, (_, index) => index);
                    for (let i = 1; i <= left.length; i += 1) {
                        let diagonal = previous[0];
                        previous[0] = i;
                        let rowBest = previous[0];
                        for (let j = 1; j <= right.length; j += 1) {
                            const insertCost = previous[j] + 1;
                            const deleteCost = previous[j - 1] + 1;
                            const replaceCost = diagonal + (left[i - 1] === right[j - 1] ? 0 : 1);
                            diagonal = previous[j];
                            previous[j] = Math.min(insertCost, deleteCost, replaceCost);
                            rowBest = Math.min(rowBest, previous[j]);
                        }
                        if (rowBest > 2) {
                            return 3;
                        }
                    }
                    return previous[right.length];
                };

                const isCloseProductWord = (token, word) => {
                    if (token.length < 4 || word.length < 4) {
                        return false;
                    }
                    const limit = token.length <= 5 ? 1 : 2;
                    return searchDistance(token, word) <= limit;
                };

                const matchingProducts = (query) => {
                    const normalized = normalizeProductSearch(query);
                    if (!normalized) {
                        return state.products.slice(0, 40);
                    }

                    const tokens = normalized.split(/\s+/).filter(Boolean);
                    const products = state.products.filter((product) => {
                        const searchable = normalizeProductSearch(product.name);
                        const words = searchable.split(/\s+/).filter(Boolean);
                        return tokens.every((token) => searchable.includes(token) || words.some((word) => isCloseProductWord(token, word)));
                    });
                    return products.slice(0, 40);
                };

                const hideProductSearchMenu = (wrapper) => {
                    const menu = wrapper?.querySelector('.product-search-menu');
                    if (!menu) {
                        return;
                    }
                    menu.innerHTML = '';
                    menu.classList.add('hidden');
                };

                const chooseProductForRow = (wrapper, productId, { updatePrice = true } = {}) => {
                    const product = findProductById(productId);
                    const select = wrapper.querySelector('.product-select');
                    const searchInput = wrapper.querySelector('.product-search');
                    const priceInput = wrapper.querySelector('.item-price');

                    select.value = product?.id || '';
                    searchInput.value = product?.name || '';
                    if (product) {
                        if (updatePrice) {
                            priceInput.value = product.price || 0;
                        }
                        updateRowUnit(wrapper, product.id);
                    } else {
                        updateRowUnit(wrapper, '');
                    }
                    hideProductSearchMenu(wrapper);
                    recalcTotals();
                    scheduleSavePurchaseDraft();
                };

                const renderProductSearchMenu = (wrapper) => {
                    const input = wrapper.querySelector('.product-search');
                    const menu = wrapper.querySelector('.product-search-menu');
                    const results = matchingProducts(input.value);
                    menu.innerHTML = '';

                    if (!results.length) {
                        const empty = document.createElement('div');
                        empty.className = 'px-4 py-3 text-xs font-semibold text-slate-400';
                        empty.textContent = 'Produk tidak ditemukan.';
                        menu.appendChild(empty);
                    } else {
                        results.forEach((product) => {
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'block w-full px-4 py-3 text-left text-sm font-semibold text-slate-700 hover:bg-primary/5 focus:bg-primary/10 dark:text-slate-100';
                            button.textContent = product.name || '-';
                            button.addEventListener('mousedown', (event) => {
                                event.preventDefault();
                                chooseProductForRow(wrapper, product.id);
                            });
                            menu.appendChild(button);
                        });
                    }

                    menu.classList.remove('hidden');
                };

                const refreshProducts = async ({ silent = false } = {}) => {
                    savePurchaseDraft();
                    if (state.refreshingProducts) {
                        return;
                    }

                    state.refreshingProducts = true;
                    el.refreshProducts?.setAttribute('disabled', 'disabled');
                    el.refreshProducts?.classList.add('opacity-60');
                    if (!silent) {
                        setProductRefreshStatus('Memuat ulang daftar produk...');
                    }

                    try {
                        state.products = await loadProducts();
                        syncProductSelectOptions();
                        recalcTotals();
                        setProductRefreshStatus(`Daftar produk diperbarui. Total ${state.products.length} produk tersedia.`, 'success');
                    } catch (error) {
                        if (!silent) {
                            setProductRefreshStatus('Gagal refresh produk. Coba lagi tanpa reload halaman.', 'error');
                        }
                    } finally {
                        state.refreshingProducts = false;
                        el.refreshProducts?.removeAttribute('disabled');
                        el.refreshProducts?.classList.remove('opacity-60');
                    }
                };

                const buildSupplierOptions = () => {
                    return state.suppliers.map((supplier) => `<option value="${supplier.id}">${supplier.name}</option>`).join('');
                };

                const getProductUnit = (productId) => {
                    const product = state.products.find((item) => item.id === productId);
                    return String(product?.unit || 'Pcs').trim() || 'Pcs';
                };

                const unitGroups = [
                    ['mg', 'gram', 'kg'],
                    ['ml', 'liter'],
                    ['cm', 'meter'],
                    ['pcs', 'unit', 'dozen', 'lusin'],
                ];
                const unitDefinitions = {
                    mg: { dimension: 'mass', factor: 0.001, label: 'mg' },
                    gram: { dimension: 'mass', factor: 1, label: 'gram' },
                    g: { dimension: 'mass', factor: 1, label: 'gram' },
                    kg: { dimension: 'mass', factor: 1000, label: 'kg' },
                    kilogram: { dimension: 'mass', factor: 1000, label: 'kg' },
                    ml: { dimension: 'volume', factor: 1, label: 'ml' },
                    liter: { dimension: 'volume', factor: 1000, label: 'liter' },
                    l: { dimension: 'volume', factor: 1000, label: 'liter' },
                    cm: { dimension: 'length', factor: 1, label: 'cm' },
                    meter: { dimension: 'length', factor: 100, label: 'meter' },
                    m: { dimension: 'length', factor: 100, label: 'meter' },
                    pcs: { dimension: 'count', factor: 1, label: 'pcs' },
                    pc: { dimension: 'count', factor: 1, label: 'pcs' },
                    unit: { dimension: 'count', factor: 1, label: 'unit' },
                    dozen: { dimension: 'count', factor: 12, label: 'dozen' },
                    lusin: { dimension: 'count', factor: 12, label: 'lusin' },
                };

                const normalizeUnit = (unit) => String(unit || '').trim().toLowerCase();
                const displayUnit = (unit) => unitDefinitions[normalizeUnit(unit)]?.label || normalizeUnit(unit) || 'unit';
                const formatQty = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: Number.isInteger(value) ? 0 : 4 }).format(value || 0);
                const getCompatibleUnits = (unit) => {
                    const normalized = normalizeUnit(unit);
                    const group = unitGroups.find((items) => items.includes(normalized));
                    return group || (normalized ? [normalized] : ['Pcs']);
                };
                const convertQtyForPreview = (qty, fromUnit, toUnit) => {
                    const from = unitDefinitions[normalizeUnit(fromUnit)];
                    const to = unitDefinitions[normalizeUnit(toUnit)];
                    if (!from || !to || from.dimension !== to.dimension || !to.factor) {
                        return Number(qty || 0);
                    }

                    return (Number(qty || 0) * from.factor) / to.factor;
                };

                const updateRowUnit = (wrapper, productId) => {
                    const unitSelect = wrapper.querySelector('.item-purchase-unit');
                    if (!unitSelect) {
                        return;
                    }

                    const stockUnit = getProductUnit(productId);
                    unitSelect.innerHTML = getCompatibleUnits(stockUnit)
                        .map((unit) => `<option value="${unit}">${unit}</option>`)
                        .join('');
                    unitSelect.value = normalizeUnit(stockUnit);
                    if (!unitSelect.value && unitSelect.options.length) {
                        unitSelect.selectedIndex = 0;
                    }
                };

                const syncTaxFields = () => {
                    const isEnabled = el.taxEnabled?.checked ?? false;
                    el.taxRateWrapper?.classList.toggle('hidden', !isEnabled);
                    if (el.taxRate) {
                        el.taxRate.disabled = !isEnabled;
                    }
                };

                const addItemRow = (item = {}) => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'space-y-4';
                    wrapper.innerHTML = `
                        <div class="relative">
                            <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1">Pilih Produk</label>
                            <div class="relative">
                                <input class="product-search w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 text-sm font-medium focus:ring-2 focus:ring-primary/20" placeholder="Ketik nama produk, contoh: UHT / coklat / hokiito" type="text" autocomplete="off" />
                                <select class="product-select hidden" tabindex="-1" aria-hidden="true">
                                    <option value="">Pilih Produk...</option>
                                    ${buildProductOptions()}
                                </select>
                                <div class="product-search-menu hidden absolute left-0 right-0 z-40 mt-1 max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-medium text-slate-400 ml-1">Jumlah pembelian</label>
                                <div class="flex items-center">
                                    <input class="item-qty w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 text-sm font-bold focus:ring-2 focus:ring-primary/20" placeholder="0" type="number" value="1" min="0.0001" step="0.0001" />
                                    <select class="item-purchase-unit ml-2 h-12 rounded-xl border-0 bg-slate-50 px-2 text-xs font-bold text-slate-600 focus:ring-2 focus:ring-primary/20 dark:bg-slate-800 dark:text-slate-300">
                                        <option value="Pcs">Pcs</option>
                                    </select>
                                </div>
                                <p class="item-conversion-hint mt-1 text-[11px] font-semibold text-slate-400">Stok masuk: -</p>
                            </div>
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <label class="block text-xs font-medium text-slate-400 ml-1">Harga beli</label>
                                    <select class="item-price-mode h-8 rounded-lg border-0 bg-primary/10 px-2 text-[11px] font-bold text-primary focus:ring-2 focus:ring-primary/20">
                                        <option value="total">Total baris</option>
                                        <option value="unit">Per satuan</option>
                                    </select>
                                </div>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">Rp</span>
                                    <input class="item-price w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl pl-10 pr-4 text-sm font-bold focus:ring-2 focus:ring-primary/20" placeholder="0" type="number" value="0" min="0" />
                                </div>
                                <p class="item-price-mode-hint mt-1 text-[11px] font-semibold text-slate-400">Harga yang diisi adalah total baris.</p>
                                <p class="item-base-price-hint mt-1 text-[11px] font-semibold text-slate-400">Harga stok: -</p>
                            </div>
                        </div>
                        <div class="rounded-xl border border-primary/10 bg-primary/5 px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Item</p>
                                    <p class="mt-1 text-xs text-slate-500"><span class="item-line-qty">1</span> <span class="item-line-unit">unit</span> x <span class="item-line-price">Rp 0</span></p>
                                    <p class="item-line-conversion mt-1 text-[11px] font-semibold text-slate-400"></p>
                                </div>
                                <p class="item-line-total text-base font-black text-primary">Rp 0</p>
                            </div>
                        </div>
                    `;
                    el.container.appendChild(wrapper);

                    const select = wrapper.querySelector('.product-select');
                    const searchInput = wrapper.querySelector('.product-search');
                    const qtyInput = wrapper.querySelector('.item-qty');
                    const priceInput = wrapper.querySelector('.item-price');
                    const priceModeSelect = wrapper.querySelector('.item-price-mode');
                    const unitSelect = wrapper.querySelector('.item-purchase-unit');

                    if (item.product_id) {
                        chooseProductForRow(wrapper, item.product_id, { updatePrice: false });
                    }
                    if (item.purchase_unit && unitSelect) {
                        const normalizedUnit = String(item.purchase_unit).trim().toLowerCase();
                        if (normalizedUnit && !Array.from(unitSelect.options).some((option) => option.value === normalizedUnit)) {
                            unitSelect.insertAdjacentHTML('beforeend', `<option value="${normalizedUnit}">${normalizedUnit}</option>`);
                        }
                        unitSelect.value = normalizedUnit;
                    }
                    if (item.qty !== undefined && item.qty !== null && item.qty !== '') {
                        qtyInput.value = item.qty;
                    }
                    if (item.price !== undefined && item.price !== null && item.price !== '') {
                        priceInput.value = item.price;
                    }
                    if (item.price_mode && priceModeSelect) {
                        priceModeSelect.value = item.price_mode === 'unit' ? 'unit' : 'total';
                    }

                    searchInput.addEventListener('focus', () => renderProductSearchMenu(wrapper));
                    searchInput.addEventListener('input', () => {
                        select.value = '';
                        updateRowUnit(wrapper, '');
                        renderProductSearchMenu(wrapper);
                        recalcTotals();
                    });
                    searchInput.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter') {
                            const firstProduct = matchingProducts(searchInput.value)[0];
                            if (firstProduct) {
                                event.preventDefault();
                                chooseProductForRow(wrapper, firstProduct.id);
                            }
                        }
                        if (event.key === 'Escape') {
                            hideProductSearchMenu(wrapper);
                        }
                    });
                    searchInput.addEventListener('blur', () => {
                        window.setTimeout(() => hideProductSearchMenu(wrapper), 120);
                    });

                    wrapper.querySelectorAll('input, select').forEach((input) => {
                        input.addEventListener('input', recalcTotals);
                        input.addEventListener('change', recalcTotals);
                    });
                };

                const addAdditionalCostRow = (cost = {}) => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'additional-cost-row rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900';
                    wrapper.innerHTML = `
                        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_9rem_2.5rem] sm:items-end">
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1">Nama Biaya</label>
                                <input class="additional-cost-name w-full h-11 rounded-xl border-0 bg-slate-50 px-3 text-sm font-semibold focus:ring-2 focus:ring-primary/20 dark:bg-slate-800" placeholder="Contoh: Plastik / Kardus / Ongkir" value="${cost.name || ''}" type="text" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1">Nominal</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">Rp</span>
                                    <input class="additional-cost-amount w-full h-11 rounded-xl border-0 bg-slate-50 pl-9 pr-3 text-sm font-bold focus:ring-2 focus:ring-primary/20 dark:bg-slate-800" placeholder="0" value="${cost.amount || ''}" min="0" type="number" />
                                </div>
                            </div>
                            <button class="remove-additional-cost flex size-10 items-center justify-center rounded-xl bg-rose-50 text-rose-600" type="button" aria-label="Hapus biaya tambahan">
                                <span class="material-symbols-outlined text-lg">delete</span>
                            </button>
                        </div>
                        <select class="additional-cost-category mt-3 w-full h-10 rounded-xl border-0 bg-slate-50 px-3 text-xs font-bold text-slate-600 focus:ring-2 focus:ring-primary/20 dark:bg-slate-800 dark:text-slate-300">
                            <option value="packaging">Kemasan / Packaging</option>
                            <option value="shipping">Ongkir / Pengiriman</option>
                            <option value="admin">Biaya Admin</option>
                            <option value="other">Lainnya</option>
                        </select>
                    `;
                    el.additionalCostList.appendChild(wrapper);
                    wrapper.querySelector('.additional-cost-category').value = cost.category || 'packaging';
                    wrapper.querySelectorAll('input, select').forEach((input) => {
                        input.addEventListener('input', recalcTotals);
                        input.addEventListener('change', recalcTotals);
                    });
                    wrapper.querySelector('.remove-additional-cost').addEventListener('click', () => {
                        wrapper.remove();
                        recalcTotals();
                    scheduleSavePurchaseDraft();
                    });
                };

                const collectAdditionalCosts = () => {
                    const costs = [];
                    el.additionalCostList.querySelectorAll('.additional-cost-row').forEach((row) => {
                        const name = row.querySelector('.additional-cost-name')?.value.trim() || '';
                        const amount = parseFloat(row.querySelector('.additional-cost-amount')?.value || '0');
                        const category = row.querySelector('.additional-cost-category')?.value || 'packaging';
                        if (name && amount > 0) {
                            costs.push({ name, category, amount });
                        }
                    });

                    return costs;
                };

                const collectItemDrafts = () => Array.from(el.container.querySelectorAll('.product-select')).map((select) => {
                    const wrapper = select.closest('.space-y-4');
                    return {
                        product_id: select.value || '',
                        qty: wrapper?.querySelector('.item-qty')?.value || '',
                        purchase_unit: wrapper?.querySelector('.item-purchase-unit')?.value || '',
                        price: wrapper?.querySelector('.item-price')?.value || '',
                        price_mode: wrapper?.querySelector('.item-price-mode')?.value || 'total',
                    };
                }).filter((item) => item.product_id || item.qty || item.price);

                const collectAdditionalCostDrafts = () => Array.from(el.additionalCostList.querySelectorAll('.additional-cost-row')).map((row) => ({
                    name: row.querySelector('.additional-cost-name')?.value || '',
                    category: row.querySelector('.additional-cost-category')?.value || 'packaging',
                    amount: row.querySelector('.additional-cost-amount')?.value || '',
                })).filter((cost) => cost.name || cost.amount);

                const collectPurchaseDraft = () => ({
                    version: purchaseDraftVersion,
                    saved_at: new Date().toISOString(),
                    supplier_id: el.supplier.value || '',
                    order_date: el.orderDate.value || '',
                    payment_method: el.paymentMethod.value || 'tempo',
                    due_date: el.dueDate.value || '',
                    tax_enabled: el.taxEnabled?.checked ?? false,
                    tax_rate: el.taxRate?.value || '11',
                    notes: el.notes.value || '',
                    items: collectItemDrafts(),
                    additional_costs: collectAdditionalCostDrafts(),
                });

                const savePurchaseDraft = () => {
                    try {
                        sessionStorage.setItem(purchaseDraftStorageKey, JSON.stringify(collectPurchaseDraft()));
                    } catch (error) {
                        // Browser storage can be unavailable in private mode.
                    }
                };

                const scheduleSavePurchaseDraft = () => {
                    window.clearTimeout(purchaseDraftTimer);
                    purchaseDraftTimer = window.setTimeout(savePurchaseDraft, 150);
                };

                const loadPurchaseDraft = () => {
                    try {
                        const raw = sessionStorage.getItem(purchaseDraftStorageKey);
                        return raw ? JSON.parse(raw) : null;
                    } catch (error) {
                        return null;
                    }
                };

                const clearPurchaseDraft = () => {
                    try {
                        sessionStorage.removeItem(purchaseDraftStorageKey);
                    } catch (error) {
                        // Ignore storage cleanup failures.
                    }
                };

                const restorePurchaseDraft = (draft) => {
                    if (!draft || draft.version !== purchaseDraftVersion) {
                        return false;
                    }

                    el.supplier.value = draft.supplier_id || '';
                    if (draft.order_date) {
                        el.orderDate.value = draft.order_date;
                    }
                    el.paymentMethod.value = draft.payment_method || 'tempo';
                    if (draft.due_date) {
                        el.dueDate.value = draft.due_date;
                    }
                    if (el.taxEnabled) {
                        el.taxEnabled.checked = !!draft.tax_enabled;
                    }
                    if (el.taxRate) {
                        el.taxRate.value = draft.tax_rate || '11';
                    }
                    el.notes.value = draft.notes || '';

                    el.container.innerHTML = '';
                    const draftItems = Array.isArray(draft.items) && draft.items.length ? draft.items : [{}];
                    draftItems.forEach((item) => addItemRow(item));

                    el.additionalCostList.innerHTML = '';
                    if (Array.isArray(draft.additional_costs)) {
                        draft.additional_costs.forEach((cost) => addAdditionalCostRow(cost));
                    }

                    syncPaymentFields();
                    syncTaxFields();
                    recalcTotals();
                    setProductRefreshStatus('Input PO dipulihkan setelah refresh halaman.', 'success');
                    return true;
                };
                const recalcTotals = () => {
                    let subtotal = 0;
                    el.container.querySelectorAll('.product-select').forEach((select) => {
                        const row = select.closest('.space-y-4');
                        if (!row) {
                            return;
                        }

                        const qtyInput = row.querySelector('.item-qty');
                        const priceInput = row.querySelector('.item-price');
                        if (!qtyInput || !priceInput) {
                            return;
                        }

                        const qty = parseFloat(qtyInput.value || '0');
                        const enteredPrice = parseFloat(priceInput.value || '0');
                        const priceMode = row.querySelector('.item-price-mode')?.value || 'total';
                        const unitPrice = priceMode === 'total' && qty > 0 ? enteredPrice / qty : enteredPrice;
                        const purchaseUnit = row.querySelector('.item-purchase-unit')?.value || getProductUnit(select.value);
                        const stockUnit = getProductUnit(select.value);
                        const baseQty = convertQtyForPreview(qty, purchaseUnit, stockUnit);
                        const baseUnitCost = baseQty > 0 ? (unitPrice * qty) / baseQty : 0;
                        const lineTotal = priceMode === 'total' ? enteredPrice : qty * unitPrice;
                        subtotal += lineTotal;
                        const lineQty = row.querySelector('.item-line-qty');
                        const lineUnit = row.querySelector('.item-line-unit');
                        const linePrice = row.querySelector('.item-line-price');
                        const lineTotalEl = row.querySelector('.item-line-total');
                        const lineConversion = row.querySelector('.item-line-conversion');
                        const conversionHint = row.querySelector('.item-conversion-hint');
                        const basePriceHint = row.querySelector('.item-base-price-hint');
                        const priceModeHint = row.querySelector('.item-price-mode-hint');
                        const purchaseUnitLabel = displayUnit(purchaseUnit);
                        const stockUnitLabel = displayUnit(stockUnit);
                        if (lineQty) {
                            lineQty.textContent = formatQty(qty);
                        }
                        if (lineUnit) {
                            lineUnit.textContent = purchaseUnitLabel;
                        }
                        if (linePrice) {
                            linePrice.textContent = priceMode === 'total'
                                ? formatCurrency(unitPrice)
                                : formatCurrency(enteredPrice);
                        }
                        if (lineTotalEl) {
                            lineTotalEl.textContent = formatCurrency(lineTotal);
                        }
                        if (conversionHint) {
                            conversionHint.textContent = select.value
                                ? `Stok masuk: ${formatQty(baseQty)} ${stockUnitLabel}`
                                : 'Stok masuk: pilih produk dulu';
                        }
                        if (basePriceHint) {
                            basePriceHint.textContent = select.value && baseQty > 0
                                ? `Harga stok: ${formatCurrency(baseUnitCost)} / ${stockUnitLabel}`
                                : 'Harga stok: -';
                        }
                        if (priceModeHint) {
                            priceModeHint.textContent = priceMode === 'total'
                                ? `Harga yang diisi adalah total baris. Setara ${formatCurrency(unitPrice)} / ${purchaseUnitLabel}.`
                                : `Harga yang diisi adalah per ${purchaseUnitLabel}.`;
                        }
                        if (lineConversion) {
                            lineConversion.textContent = select.value && normalizeUnit(purchaseUnit) !== normalizeUnit(stockUnit)
                                ? `Dikonversi menjadi ${formatQty(baseQty)} ${stockUnitLabel} untuk stok.`
                                : '';
                        }
                    });
                    const taxEnabled = el.taxEnabled?.checked ?? false;
                    const taxRate = taxEnabled ? Math.max(0, parseFloat(el.taxRate?.value || '0')) : 0;
                    const tax = subtotal * (taxRate / 100);
                    const additionalCostTotal = collectAdditionalCosts().reduce((sum, cost) => sum + Number(cost.amount || 0), 0);
                    const total = subtotal + tax + additionalCostTotal;
                    syncTaxFields();
                    el.subtotal.textContent = formatCurrency(subtotal);
                    el.additionalCostTotal.textContent = formatCurrency(additionalCostTotal);
                    el.tax.textContent = formatCurrency(tax);
                    el.total.textContent = formatCurrency(total);
                };

                const loadSuppliers = async () => {
                    const response = await fetch('/api/v1/suppliers', { headers });
                    if (!response.ok) {
                        return { suppliers: [], total: 0 };
                    }
                    return response.json();
                };

                const loadProducts = async () => {
                    const response = await fetch('/api/v1/products', { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const data = await response.json();
                    return data.products || [];
                };

                const loadCashAccounts = async () => {
                    const response = await fetch('/api/v1/chart-of-accounts?type=asset', { headers });
                    if (!response.ok) {
                        return { accounts: [], mapping: null };
                    }

                    return response.json();
                };

                const openSupplierModal = () => {
                    el.supplierModal.classList.remove('hidden');
                    el.supplierModal.classList.add('flex');
                    el.supplierName.focus();
                };

                const closeSupplierModal = () => {
                    el.supplierModal.classList.add('hidden');
                    el.supplierModal.classList.remove('flex');
                    el.supplierName.value = '';
                    el.supplierModalError.classList.add('hidden');
                    el.supplierModalError.textContent = '';
                };

                const showSupplierModalError = (message) => {
                    el.supplierModalError.textContent = message;
                    el.supplierModalError.classList.toggle('hidden', !message);
                };

                const createSupplier = async () => {
                    const name = el.supplierName.value.trim();
                    if (!name) {
                        showSupplierModalError('Nama pemasok wajib diisi.');
                        return;
                    }

                    await window.PosUi.runWithSubmitGuard(el.saveSupplier, async () => {
                        const response = await window.PosUi.fetchWithTimeout('/api/v1/suppliers', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                                'X-Requested-With': 'XMLHttpRequest',
                                ...headers,
                            },
                            body: JSON.stringify({ name }),
                        }, 15000);

                        if (!response.ok) {
                            const mapped = await window.PosUi.mapApiError(response, 'Gagal menambahkan pemasok.');
                            showSupplierModalError(mapped.message);
                            return;
                        }

                        const payload = await response.json();
                        const created = payload.supplier;
                        if (created) {
                            state.suppliers = [...state.suppliers, created].sort((a, b) => String(a.name).localeCompare(String(b.name)));
                            el.supplier.innerHTML = `<option value="">Pilih Pemasok...</option>${buildSupplierOptions()}`;
                            el.supplier.value = created.id;
                        }

                        closeSupplierModal();
                    });
                };

                const submit = async (status, purchaseMode) => {
                    const supplierId = el.supplier.value;
                    if (!supplierId) {
                        alert('Pilih pemasok terlebih dahulu.');
                        return;
                    }

                    const items = [];
                    el.container.querySelectorAll('.product-select').forEach((select) => {
                        const wrapper = select.closest('.space-y-4');
                        if (!wrapper) {
                            return;
                        }

                        const qtyInput = wrapper.querySelector('.item-qty');
                        const priceInput = wrapper.querySelector('.item-price');
                        if (!qtyInput || !priceInput) {
                            return;
                        }

                        if (!select.value) {
                            return;
                        }
                        const qty = parseFloat(qtyInput.value || '0');
                        const enteredPrice = parseFloat(priceInput.value || '0');
                        const priceMode = wrapper.querySelector('.item-price-mode')?.value || 'total';
                        items.push({
                            product_id: select.value,
                            qty,
                            purchase_unit: wrapper.querySelector('.item-purchase-unit')?.value || getProductUnit(select.value),
                            price: priceMode === 'total' && qty > 0 ? enteredPrice / qty : enteredPrice,
                            line_total: priceMode === 'total' ? enteredPrice : qty * enteredPrice,
                            price_mode: priceMode,
                        });
                    });

                    if (!items.length) {
                        alert('Tambahkan minimal satu produk.');
                        return;
                    }

                    const totalQty = items.reduce((sum, item) => sum + Number(item.qty || 0), 0);
                    const subtotalValue = items.reduce((sum, item) => sum + Number(item.line_total ?? (Number(item.qty || 0) * Number(item.price || 0))), 0);
                    const additionalCosts = collectAdditionalCosts();
                    const additionalCostValue = additionalCosts.reduce((sum, cost) => sum + Number(cost.amount || 0), 0);
                    const taxEnabled = el.taxEnabled?.checked ?? false;
                    const taxRate = taxEnabled ? Math.max(0, parseFloat(el.taxRate?.value || '0')) : 0;
                    const taxValue = taxEnabled ? subtotalValue * (taxRate / 100) : 0;
                    const totalValue = subtotalValue + taxValue + additionalCostValue;
                    const supplierName = el.supplier.options[el.supplier.selectedIndex]?.textContent || '-';
                    const paymentMethod = el.paymentMethod.value || 'tempo';
                    const dueDate = paymentMethod === 'tempo' ? (el.dueDate.value || null) : null;
                    const cashAccountId = paymentMethod === 'tunai' ? (state.selectedCashAccountId || state.cashAccounts[0]?.id || null) : null;
                    const transactionDate = el.orderDate.value || null;

                    if (!transactionDate) {
                        alert('Tanggal pesanan wajib diisi.');
                        return;
                    }

                    if (!validateOrderDate()) {
                        return;
                    }

                    if (paymentMethod === 'tempo' && !dueDate) {
                        alert('Tanggal jatuh tempo wajib diisi untuk pembelian tempo.');
                        return;
                    }

                    if (paymentMethod === 'tunai' && !cashAccountId) {
                        alert('Akun kas belum diatur. Buka Pembayaran & Rekening terlebih dahulu.');
                        return;
                    }

                    const allowed = await window.PosUi.confirmRiskActionAsync({
                        title: purchaseMode === purchaseModeSimple
                            ? 'Beli & Terima Sekarang'
                            : status === purchaseStatusDraft
                                ? 'Simpan sebagai Draft'
                                : 'Konfirmasi dan Buat PO',
                        message: `Supplier: ${supplierName}\nItem: ${items.length}\nTotal Qty: ${totalQty}\nSubtotal: ${formatCurrency(subtotalValue)}\nBiaya tambahan: ${formatCurrency(additionalCostValue)}\nPajak: ${formatCurrency(taxValue)}\nEstimasi Nilai: ${formatCurrency(totalValue)}`,
                        icon: purchaseMode === purchaseModeSimple ? 'inventory_2' : 'shopping_cart_checkout',
                    });
                    if (!allowed) {
                        return;
                    }

                    const triggerButton = status === purchaseStatusDraft ? el.draft : el.submit;

                    await window.PosUi.runWithSubmitGuard(triggerButton, async () => {
                        try {
                            const response = await window.PosUi.fetchWithTimeout('/api/v1/purchases', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                                    'X-Requested-With': 'XMLHttpRequest',
                                    ...headers,
                                },
                                body: JSON.stringify({
                                    supplier_id: supplierId,
                                    purchase_mode: purchaseMode,
                                    status,
                                    payment_method: paymentMethod,
                                    due_date: dueDate,
                                    cash_account_id: cashAccountId,
                                    transaction_date: transactionDate,
                                    tax_enabled: taxEnabled,
                                    tax_rate: taxRate,
                                    tax: taxValue,
                                    additional_costs: additionalCosts,
                                    notes: el.notes.value.trim() || null,
                                    items,
                                }),
                            }, 15000);

                            const responseData = await response.json().catch(() => ({}));

                            if (!response.ok) {
                                const mapped = window.PosUi.mapApiPayloadError(responseData, 'Gagal membuat PO.');
                                if (mapped.code === 'INSUFFICIENT_CASH_BALANCE') {
                                    const available = mapped.details?.available_cash || 0;
                                    const required = mapped.details?.required_amount || 0;
                                    alert(`Saldo ${getActiveCashAccountLabel()} tidak cukup. Tersedia ${formatCurrency(available)}, dibutuhkan ${formatCurrency(required)}.`);
                                    return;
                                }
                                if (mapped.code === 'INVALID_TRANSACTION_DATE') {
                                    const minDate = mapped.details?.min_date;
                                    const maxDate = mapped.details?.max_date;
                                    if (maxDate) {
                                        alert(minDate
                                            ? `Tanggal pesanan ${transactionDate || '-'} tidak bisa dipakai. Pilih tanggal ${minDate} sampai ${maxDate}.`
                                            : `Tanggal pesanan ${transactionDate || '-'} tidak bisa dipakai. Pilih tanggal sampai ${maxDate}.`);
                                        if (minDate) {
                                            el.orderDate.min = minDate;
                                        } else {
                                            el.orderDate.removeAttribute('min');
                                        }
                                        el.orderDate.max = maxDate;
                                        if (el.orderDateHint) {
                                            el.orderDateHint.textContent = minDate
                                                ? `Boleh tanggal ${minDate} sampai ${maxDate}.`
                                                : `Boleh tanggal sampai ${maxDate}.`;
                                            el.orderDateHint.classList.add('text-rose-600');
                                            el.orderDateHint.classList.remove('text-slate-400');
                                        }
                                        el.orderDate.focus();
                                    } else {
                                        validateOrderDate();
                                    }
                                    return;
                                }
                                alert(mapped.message);
                                return;
                            }

                            clearPurchaseDraft();
                            alert(purchaseMode === purchaseModeSimple ? 'Pembelian langsung berhasil diproses.' : 'PO berhasil disimpan.');
                            const purchaseId = responseData?.purchase_id || responseData?.purchase?.id || null;
                            window.location.href = purchaseId
                                ? `${@json($purchaseDetailUrl)}/${encodeURIComponent(purchaseId)}`
                                : @json($purchaseListUrl);
                        } catch (error) {
                            if (error?.name === 'AbortError') {
                                alert('Permintaan timeout. Cek daftar PO sebelum mengulang simpan.');
                                return;
                            }
                            alert(error?.message || 'Gagal membuat PO.');
                        }
                    });
                };

                el.addItem.addEventListener('click', () => {
                    addItemRow();
                scheduleSavePurchaseDraft();
                });

                el.addAdditionalCost?.addEventListener('click', () => {
                    addAdditionalCostRow();
                scheduleSavePurchaseDraft();
                });

                el.refreshProducts?.addEventListener('click', () => {
                    refreshProducts();
                });

                window.addEventListener('focus', () => {
                    syncOrderDateBounds();
                    refreshProducts({ silent: true });
                });

                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) {
                        syncOrderDateBounds();
                        refreshProducts({ silent: true });
                    }
                });

                el.paymentMethod.addEventListener('change', syncPaymentFields);
                el.orderDate.addEventListener('change', () => {
                    const range = syncOrderDateBounds();
                    const value = el.orderDate.value || '';
                    const valid = !value || value <= range.max;
                    el.orderDateHint?.classList.toggle('text-rose-600', !valid);
                    el.orderDateHint?.classList.toggle('text-slate-400', valid);
                });
                el.openSupplierModal.addEventListener('click', openSupplierModal);
                el.closeSupplierModal.addEventListener('click', closeSupplierModal);
                el.cancelSupplierCreate.addEventListener('click', closeSupplierModal);
                el.supplierModal.addEventListener('click', (event) => {
                    if (event.target === el.supplierModal) {
                        closeSupplierModal();
                    }
                });
                el.saveSupplier.addEventListener('click', createSupplier);
                el.supplierName.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        createSupplier();
                    }
                });

                el.buyNow.addEventListener('click', () => submit(purchaseStatusSubmitted, purchaseModeSimple));
                el.draft.addEventListener('click', () => submit(purchaseStatusDraft, purchaseModePoShort));
                el.submit.addEventListener('click', () => submit(purchaseStatusSubmitted, purchaseModePoShort));
                el.taxEnabled?.addEventListener('change', recalcTotals);
                el.taxRate?.addEventListener('input', recalcTotals);

                const draftFieldSelector = '#supplier-select, #order-date, #payment-method, #due-date, #tax-enabled, #tax-rate, #notes, #item-container, #additional-cost-list';
                ['input', 'change'].forEach((eventName) => {
                    document.addEventListener(eventName, (event) => {
                        if (event.target.closest(draftFieldSelector)) {
                            scheduleSavePurchaseDraft();
                        }
                    });
                });
                window.addEventListener('beforeunload', savePurchaseDraft);

                (async () => {
                    setDefaultOrderDate();
                    setDefaultDueDate();
                    syncPaymentFields();
                    const [supplierData, products, cashAccountsResponse] = await Promise.all([loadSuppliers(), loadProducts(), loadCashAccounts()]);
                    const suppliers = supplierData.suppliers || [];
                    el.supplier.innerHTML = '<option value="">Pilih Pemasok...</option>';
                    suppliers.forEach((supplier) => {
                        const option = document.createElement('option');
                        option.value = supplier.id;
                        option.textContent = supplier.name;
                        el.supplier.appendChild(option);
                    });
                    state.suppliers = suppliers;
                    state.products = products;
                    state.cashAccounts = Array.isArray(cashAccountsResponse?.mapping_accounts)
                        ? cashAccountsResponse.mapping_accounts.map((account) => ({ id: account.id, code: account.code, name: account.name }))
                        : [];
                    state.cashAccountMapping = cashAccountsResponse?.mapping || null;
                    state.selectedCashAccountId = state.cashAccountMapping?.cash_account_id || state.cashAccounts[0]?.id || null;
                    renderCashAccountInfo();
                    el.supplier.innerHTML = `<option value="">Pilih Pemasok...</option>${buildSupplierOptions()}`;
                    syncTaxFields();
                    if (!restorePurchaseDraft(loadPurchaseDraft())) {
                        addItemRow();
                        recalcTotals();
                    }
                })();
            })();
        </script>
    @endsection
