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
                        <input id="order-date" class="mt-1 h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm font-semibold focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-800" type="date" />
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
            <button class="text-primary text-xs font-bold flex items-center gap-1" type="button">
                <span class="material-symbols-outlined text-sm">barcode_scanner</span>
                Scan Barcode
            </button>
        </div>
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4" id="item-container"></div>
        <button id="add-item" class="w-full h-11 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl text-slate-400 text-sm font-semibold flex items-center justify-center gap-2 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors" type="button">
            <span class="material-symbols-outlined">add_circle</span>
            Tambah Item Lain
        </button>
    </section>
    <section class="bg-primary/5 dark:bg-primary/10 rounded-2xl p-4 border border-primary/10 space-y-3">
        <div class="flex justify-between items-center text-sm">
            <span class="text-slate-500">Subtotal</span>
            <span class="font-medium" id="subtotal">Rp 0</span>
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
                    container: document.getElementById('item-container'),
                    addItem: document.getElementById('add-item'),
                    subtotal: document.getElementById('subtotal'),
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

                const setDefaultOrderDate = () => {
                    el.orderDate.value = localDateInputValue();
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

                const getCompatibleUnits = (unit) => {
                    const normalized = String(unit || '').trim().toLowerCase();
                    const group = unitGroups.find((items) => items.includes(normalized));
                    return group || (normalized ? [normalized] : ['Pcs']);
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
                    unitSelect.value = String(stockUnit).trim().toLowerCase();
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

                const addItemRow = () => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'space-y-4';
                    wrapper.innerHTML = `
                        <div class="relative">
                            <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1">Pilih Produk</label>
                            <div class="relative">
                                <select class="product-select w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 text-sm font-medium focus:ring-2 focus:ring-primary/20 appearance-none">
                                    <option value="">Pilih Produk...</option>
                                    ${buildProductOptions()}
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-medium text-slate-400 ml-1">Jumlah</label>
                                <div class="flex items-center">
                                    <input class="item-qty w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 text-sm font-bold focus:ring-2 focus:ring-primary/20" placeholder="0" type="number" value="1" min="0.0001" step="0.0001" />
                                    <select class="item-purchase-unit ml-2 h-12 rounded-xl border-0 bg-slate-50 px-2 text-xs font-bold text-slate-600 focus:ring-2 focus:ring-primary/20 dark:bg-slate-800 dark:text-slate-300">
                                        <option value="Pcs">Pcs</option>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-medium text-slate-400 ml-1">Harga Beli / Satuan</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">Rp</span>
                                    <input class="item-price w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl pl-10 pr-4 text-sm font-bold focus:ring-2 focus:ring-primary/20" placeholder="0" type="number" value="0" min="0" />
                                </div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-primary/10 bg-primary/5 px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Item</p>
                                    <p class="mt-1 text-xs text-slate-500"><span class="item-line-qty">1</span> x <span class="item-line-price">Rp 0</span></p>
                                </div>
                                <p class="item-line-total text-base font-black text-primary">Rp 0</p>
                            </div>
                        </div>
                    `;
                    el.container.appendChild(wrapper);

                    const select = wrapper.querySelector('.product-select');
                    const priceInput = wrapper.querySelector('.item-price');
                    select.addEventListener('change', () => {
                        const product = state.products.find((item) => item.id === select.value);
                        if (product) {
                            priceInput.value = product.price || 0;
                            updateRowUnit(wrapper, product.id);
                            recalcTotals();
                        } else {
                            updateRowUnit(wrapper, '');
                        }
                    });

                    wrapper.querySelectorAll('input').forEach((input) => {
                        input.addEventListener('input', recalcTotals);
                    });
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
                        const price = parseFloat(priceInput.value || '0');
                        const lineTotal = qty * price;
                        subtotal += lineTotal;
                        const lineQty = row.querySelector('.item-line-qty');
                        const linePrice = row.querySelector('.item-line-price');
                        const lineTotalEl = row.querySelector('.item-line-total');
                        if (lineQty) {
                            lineQty.textContent = new Intl.NumberFormat('id-ID', { maximumFractionDigits: Number.isInteger(qty) ? 0 : 4 }).format(qty);
                        }
                        if (linePrice) {
                            linePrice.textContent = formatCurrency(price);
                        }
                        if (lineTotalEl) {
                            lineTotalEl.textContent = formatCurrency(lineTotal);
                        }
                    });
                    const taxEnabled = el.taxEnabled?.checked ?? false;
                    const taxRate = taxEnabled ? Math.max(0, parseFloat(el.taxRate?.value || '0')) : 0;
                    const tax = subtotal * (taxRate / 100);
                    const total = subtotal + tax;
                    syncTaxFields();
                    el.subtotal.textContent = formatCurrency(subtotal);
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
                        items.push({
                            product_id: select.value,
                            qty: parseFloat(qtyInput.value || '0'),
                            purchase_unit: wrapper.querySelector('.item-purchase-unit')?.value || getProductUnit(select.value),
                            price: parseFloat(priceInput.value || '0'),
                        });
                    });

                    if (!items.length) {
                        alert('Tambahkan minimal satu produk.');
                        return;
                    }

                    const totalQty = items.reduce((sum, item) => sum + Number(item.qty || 0), 0);
                    const subtotalValue = items.reduce((sum, item) => sum + (Number(item.qty || 0) * Number(item.price || 0)), 0);
                    const taxEnabled = el.taxEnabled?.checked ?? false;
                    const taxRate = taxEnabled ? Math.max(0, parseFloat(el.taxRate?.value || '0')) : 0;
                    const taxValue = taxEnabled ? subtotalValue * (taxRate / 100) : 0;
                    const totalValue = subtotalValue + taxValue;
                    const supplierName = el.supplier.options[el.supplier.selectedIndex]?.textContent || '-';
                    const paymentMethod = el.paymentMethod.value || 'tempo';
                    const dueDate = paymentMethod === 'tempo' ? (el.dueDate.value || null) : null;
                    const cashAccountId = paymentMethod === 'tunai' ? (state.selectedCashAccountId || state.cashAccounts[0]?.id || null) : null;
                    const transactionDate = el.orderDate.value || null;

                    if (!transactionDate) {
                        alert('Tanggal pesanan wajib diisi.');
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
                        message: `Supplier: ${supplierName}\nItem: ${items.length}\nTotal Qty: ${totalQty}\nSubtotal: ${formatCurrency(subtotalValue)}\nPajak: ${formatCurrency(taxValue)}\nEstimasi Nilai: ${formatCurrency(totalValue)}`,
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
                                alert(mapped.message);
                                return;
                            }

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
                });

                el.paymentMethod.addEventListener('change', syncPaymentFields);
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
                    addItemRow();
                    recalcTotals();
                })();
            })();
        </script>
    @endsection
