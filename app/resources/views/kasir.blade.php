@extends('layouts.app')

@section('page_title', 'Kasir POS - Checkout Utama')
@section('body_class', 'font-display text-slate-900')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-slate-50 shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
        <style>
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
@endpush

@section('header')
        <header class="flex items-center justify-between px-4 pt-4 pb-3 bg-white border-b border-slate-200" data-auto-center-title="true">
            <div class="flex items-center gap-3">
                <button class="p-2 rounded-lg bg-slate-100 text-slate-500" type="button" data-menu-trigger>
                    <span class="material-symbols-outlined text-xl">menu</span>
                </button>
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined font-semibold">person</span>
                </div>
                <div>
                    <h1 class="text-sm font-bold leading-tight" id="cashier-name">Kasir</h1>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500" id="shift-indicator"></span>
                        <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold" id="shift-status">Shift Aktif</p>
                    </div>
                    <p class="text-[10px] text-slate-400" id="shift-opened-time">Mulai: -</p>
                </div>
            </div>
            <div class="flex gap-2">
                <button class="p-2 rounded-lg bg-slate-100 text-slate-500">
                    <span class="material-symbols-outlined text-xl">sync</span>
                </button>
                <a id="quick-close-shift" href="/shift/close" class="p-2 rounded-lg bg-slate-100 text-slate-500 hidden" title="Tutup Shift">
                    <span class="material-symbols-outlined text-xl">lock_clock</span>
                </a>
                <button class="p-2 rounded-lg bg-slate-100 text-slate-500">
                    <span class="material-symbols-outlined text-xl">settings</span>
                </button>
            </div>
        </header>
@endsection

@section('content')
        <div class="relative flex h-full flex-col">
            <div class="flex-1 flex flex-col overflow-hidden">
                <div class="px-4 py-3 space-y-3 bg-white">
                    <div id="shift-guard" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3">
                        <p class="text-xs font-semibold text-amber-800">Shift belum aktif. Buka shift dulu untuk mulai transaksi.</p>
                        <a href="/shift/open" class="mt-2 inline-flex items-center gap-1 rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-800">
                            <span class="material-symbols-outlined text-sm">lock_open</span>
                            Buka Shift
                        </a>
                    </div>
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">search</span>
                            <input id="search-input" class="w-full pl-10 pr-4 py-2.5 rounded-xl border-none bg-slate-100 focus:ring-2 focus:ring-primary text-sm" placeholder="Cari produk atau scan..." type="text" />
                        </div>
                        <button class="bg-primary/10 text-primary p-2.5 rounded-xl flex items-center justify-center">
                            <span class="material-symbols-outlined">barcode_scanner</span>
                        </button>
                    </div>
                    <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 p-2 rounded-xl">
                        <span class="material-symbols-outlined text-slate-400 ml-1">person_add</span>
                        <select id="customer-select" class="flex-1 bg-transparent border-none text-sm focus:ring-0 py-1 cursor-pointer font-medium">
                            <option value="">Pelanggan Umum</option>
                        </select>
                        <button class="text-primary text-sm font-bold px-2">UBAH</button>
                    </div>
                </div>
                <div id="category-filters" class="flex gap-2 px-4 py-3 overflow-x-auto no-scrollbar bg-white border-b border-slate-200"></div>
                <div class="flex-1 overflow-y-auto px-4 py-4 bg-slate-50">
                    <div id="product-grid" class="grid grid-cols-2 gap-3 pb-24"></div>
                </div>
            </div>
            <button id="cart-toggle" class="absolute bottom-24 right-4 z-20 flex items-center justify-center size-14 rounded-full bg-primary text-white shadow-lg shadow-primary/40">
                <span class="material-symbols-outlined text-3xl">shopping_bag</span>
                <span id="cart-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">0</span>
            </button>
            <div id="cart-overlay" class="absolute inset-0 bg-black/30 hidden z-30">
                <div id="cart-drawer" class="absolute right-0 top-0 h-full w-full max-w-[340px] bg-white shadow-2xl flex flex-col">
                    <div class="px-4 py-4 border-b border-slate-200 flex items-center justify-between">
                        <h2 class="text-base font-bold flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">shopping_basket</span>
                            Keranjang Belanja
                        </h2>
                        <button id="cart-close" class="w-9 h-9 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center">
                            <span class="material-symbols-outlined text-lg">close</span>
                        </button>
                    </div>
                    <div class="px-4 py-3 flex justify-between items-center">
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Item</p>
                        <button id="clear-cart" class="text-red-500 text-xs font-bold uppercase">Hapus Semua</button>
                    </div>
                    <div id="cart-list" class="px-4 flex-1 overflow-y-auto space-y-4 pb-4"></div>
                    <div class="px-4 py-4 bg-slate-50 space-y-3 border-t border-slate-200">
                        <div class="space-y-1 border-b border-slate-200 pb-3">
                            <div class="flex justify-between text-sm text-slate-600">
                                <span>Subtotal (<span id="item-count">0</span> item)</span>
                                <span class="font-medium" id="subtotal">Rp 0</span>
                            </div>
                            <div class="flex justify-between text-sm text-slate-600">
                                <span id="tax-label">Pajak (0%)</span>
                                <span class="font-medium" id="tax">Rp 0</span>
                            </div>
                            <div class="flex justify-between text-sm text-slate-600">
                                <div class="flex items-center gap-2">
                                    <span>Diskon</span>
                                    <button id="discount-toggle" class="text-primary text-xs font-bold" type="button">Pilih</button>
                                </div>
                                <span class="font-medium" id="discount-amount">Rp 0</span>
                            </div>
                            <div id="discount-panel" class="hidden mt-2 rounded-lg border border-slate-200 bg-white p-3">
                                <p class="text-[10px] font-bold text-slate-400 uppercase mb-2">Pilih Diskon</p>
                                <div id="discount-list" class="space-y-2"></div>
                                <p id="discount-empty" class="text-xs text-slate-400 hidden">Belum ada diskon aktif.</p>
                            </div>
                            <div class="flex justify-between text-sm text-slate-600">
                                <div class="flex items-center gap-2">
                                    <span>Biaya Tambahan</span>
                                    <button id="fee-add" class="text-primary text-xs font-bold" type="button">Tambah</button>
                                </div>
                                <span class="font-medium" id="fee-total">Rp 0</span>
                            </div>
                            <div id="fee-list" class="mt-2 space-y-2"></div>
                        </div>
                        <div class="flex justify-between items-end pt-1">
                            <div>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Total Pembayaran</p>
                                <span class="text-2xl font-black text-slate-900 leading-none" id="total">Rp 0</span>
                            </div>
                            <div class="flex gap-2">
                                <a id="payment-link" class="w-12 h-14 bg-white border border-slate-200 text-slate-600 rounded-xl flex items-center justify-center shadow-sm active:bg-slate-100" href="/payment-method" aria-label="Pilih metode pembayaran">
                                    <span class="material-symbols-outlined">receipt</span>
                                </a>
                                <button id="checkout-button" class="h-14 bg-emerald-500 text-white font-black px-6 rounded-xl flex items-center justify-center gap-3 shadow-lg shadow-emerald-500/30 active:scale-95 transition-transform flex-1 min-w-[140px]">
                                    <span class="material-symbols-outlined font-bold">payments</span>
                                    BAYAR
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @include('partials.ui-helpers')
        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    cashierName: document.getElementById('cashier-name'),
                    shiftIndicator: document.getElementById('shift-indicator'),
                    shiftStatus: document.getElementById('shift-status'),
                    shiftOpened: document.getElementById('shift-opened-time'),
                    quickClose: document.getElementById('quick-close-shift'),
                    search: document.getElementById('search-input'),
                    customer: document.getElementById('customer-select'),
                    categoryFilters: document.getElementById('category-filters'),
                    productGrid: document.getElementById('product-grid'),
                    cartList: document.getElementById('cart-list'),
                    clearCart: document.getElementById('clear-cart'),
                    itemCount: document.getElementById('item-count'),
                    subtotal: document.getElementById('subtotal'),
                    taxLabel: document.getElementById('tax-label'),
                    tax: document.getElementById('tax'),
                    discountAmount: document.getElementById('discount-amount'),
                    discountToggle: document.getElementById('discount-toggle'),
                    discountPanel: document.getElementById('discount-panel'),
                    discountList: document.getElementById('discount-list'),
                    discountEmpty: document.getElementById('discount-empty'),
                    feeList: document.getElementById('fee-list'),
                    feeAdd: document.getElementById('fee-add'),
                    feeTotal: document.getElementById('fee-total'),
                    total: document.getElementById('total'),
                    checkout: document.getElementById('checkout-button'),
                    paymentLink: document.getElementById('payment-link'),
                    cartToggle: document.getElementById('cart-toggle'),
                    cartOverlay: document.getElementById('cart-overlay'),
                    cartDrawer: document.getElementById('cart-drawer'),
                    cartClose: document.getElementById('cart-close'),
                    cartBadge: document.getElementById('cart-badge'),
                    bottomNav: document.getElementById('bottom-nav'),
                    shiftGuard: document.getElementById('shift-guard'),
                };

                const state = {
                    branchId: null,
                    shiftId: null,
                    locationId: null,
                    customers: [],
                    categories: [],
                    products: [],
                    inventory: [],
                    accountMapping: null,
                    cart: new Map(),
                    category: 'all',
                    term: '',
                    shiftBlocked: false,
                    taxRate: 0,
                    promos: [],
                    selectedPromos: new Set(),
                    fees: [],
                };

                const cartStorageKey = 'pos_cart_draft';

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const formatRate = (rate) => {
                    const numeric = Number(rate || 0);
                    if (!Number.isFinite(numeric)) {
                        return '0';
                    }
                    return Number.isInteger(numeric) ? String(numeric) : numeric.toFixed(2).replace(/\.00$/, '');
                };

                const formatDateOnly = (value) => {
                    if (!value) {
                        return null;
                    }
                    const parsed = new Date(value);
                    return Number.isNaN(parsed.getTime()) ? null : parsed;
                };

                const getStock = (product) => {
                    if (!product?.track_stock) {
                        return null;
                    }
                    if (!state.locationId) {
                        return 0;
                    }
                    const row = state.inventory.find((item) => item.product_id === product.id && item.location_id === state.locationId);
                    return row ? Number(row.qty_on_hand || 0) : 0;
                };

                const resolveUnitPrice = (product, qty) => {
                    const tiers = Array.isArray(product.price_tiers) ? product.price_tiers : [];
                    const qtyValue = Number(qty || 0);
                    let price = Number(product.price || 0);
                    tiers.forEach((tier) => {
                        const minQty = Number(tier.min_qty || 0);
                        const tierPrice = Number(tier.price || 0);
                        if (qtyValue >= minQty && tierPrice > 0) {
                            price = tierPrice;
                        }
                    });
                    return price;
                };

                const saveCartDraft = () => {
                    const items = [...state.cart.values()].map((item) => ({
                        product_id: item.product.id,
                        qty: item.qty,
                    }));
                    if (!items.length) {
                        sessionStorage.removeItem(cartStorageKey);
                        return;
                    }
                    sessionStorage.setItem(cartStorageKey, JSON.stringify({
                        items,
                        customer_id: el.customer.value || null,
                        fees: state.fees,
                        selected_promos: [...state.selectedPromos],
                        updated_at: new Date().toISOString(),
                    }));
                };

                const loadCartDraft = () => {
                    try {
                        const raw = sessionStorage.getItem(cartStorageKey);
                        return raw ? JSON.parse(raw) : null;
                    } catch (_) {
                        return null;
                    }
                };

                const restoreCartDraft = () => {
                    const draft = loadCartDraft();
                    if (!draft || !Array.isArray(draft.items) || !draft.items.length) {
                        return;
                    }

                    const productMap = new Map(state.products.map((product) => [product.id, product]));
                    draft.items.forEach((item) => {
                        const product = productMap.get(item.product_id);
                        if (!product) {
                            return;
                        }
                        const qty = Math.max(1, Number(item.qty || 1));
                        state.cart.set(product.id, {
                            product,
                            qty,
                            unit_price: resolveUnitPrice(product, qty),
                        });
                    });

                    if (draft.customer_id) {
                        el.customer.value = draft.customer_id;
                    }
                    if (Array.isArray(draft.fees)) {
                        state.fees = draft.fees.map((fee) => ({
                            id: fee.id || crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`,
                            name: fee.name || '',
                            type: fee.type === 'percent' ? 'percent' : 'amount',
                            value: Number(fee.value || 0),
                            product_id: fee.product_id || null,
                        }));
                    }
                    if (Array.isArray(draft.selected_promos)) {
                        state.selectedPromos = new Set(draft.selected_promos);
                    }
                    renderCart();
                    renderProducts();
                };

                const renderCategories = () => {
                    el.categoryFilters.innerHTML = '';
                    const allBtn = document.createElement('button');
                    allBtn.className = 'flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap shadow-sm';
                    allBtn.dataset.category = 'all';
                    allBtn.innerHTML = '<span class="material-symbols-outlined text-lg">grid_view</span> Semua';
                    el.categoryFilters.appendChild(allBtn);

                    state.categories.forEach((category) => {
                        const button = document.createElement('button');
                        button.className = 'flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap';
                        button.dataset.category = category.id;
                        button.innerHTML = `<span class="material-symbols-outlined text-lg">sell</span> ${category.name}`;
                        el.categoryFilters.appendChild(button);
                    });
                    applyCategoryStyles();
                };

                const applyCategoryStyles = () => {
                    el.categoryFilters.querySelectorAll('button').forEach((button) => {
                        const active = button.dataset.category === state.category;
                        button.classList.toggle('bg-primary', active);
                        button.classList.toggle('text-white', active);
                        button.classList.toggle('bg-slate-100', !active);
                        button.classList.toggle('text-slate-600', !active);
                    });
                };

                const renderProducts = () => {
                    el.productGrid.innerHTML = '';
                    const term = state.term.toLowerCase();
                    const products = state.products.filter((product) => {
                        if (state.category !== 'all' && product.category_id !== state.category) {
                            return false;
                        }
                        if (!term) {
                            return true;
                        }
                        return product.name.toLowerCase().includes(term) || (product.sku || '').toLowerCase().includes(term);
                    });

                    if (!products.length) {
                        el.productGrid.innerHTML = '<div class="text-sm text-slate-400">Produk tidak ditemukan.</div>';
                        return;
                    }

                    products.forEach((product) => {
                        const stock = getStock(product);
                        const stockLabel = product.track_stock ? `Stok: ${stock}` : 'Tanpa stok';
                        const item = state.cart.get(product.id);
                        const card = document.createElement('button');
                        card.type = 'button';
                        card.className = `bg-white p-2.5 rounded-2xl border ${item ? 'border-primary shadow-md' : 'border-slate-200 shadow-sm'} active:scale-95 transition-transform relative text-left`;
                        const image = product.image_url || 'https://placehold.co/240x240?text=Produk';
                        card.innerHTML = `
                            <div class="aspect-square w-full rounded-xl bg-slate-100 mb-2 overflow-hidden relative">
                                <img alt="${product.name}" class="w-full h-full object-cover" src="${image}" />
                                ${item ? `<div class="absolute inset-0 bg-primary/10 flex items-center justify-center"><div class="bg-primary text-white text-[11px] px-2 py-1 rounded-full font-bold shadow-lg">${item.qty} DI KERANJANG</div></div>` : ''}
                            </div>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wide">${product.category_name || 'Produk'}</p>
                            <h3 class="text-sm font-bold text-slate-800 truncate">${product.name}</h3>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-primary font-bold">${formatCurrency(resolveUnitPrice(product, 1))}</span>
                                <span class="text-[10px] text-slate-400">${stockLabel}</span>
                            </div>
                        `;
                        card.addEventListener('click', () => addToCart(product));
                        el.productGrid.appendChild(card);
                    });
                };

                const addToCart = (product) => {
                    if (state.shiftBlocked) {
                        alert('Shift belum aktif. Silakan buka shift terlebih dahulu.');
                        return;
                    }

                    const existing = state.cart.get(product.id);
                    const qty = existing ? existing.qty + 1 : 1;
                    if (product.track_stock) {
                        const stock = getStock(product);
                        if (stock !== null && qty > stock) {
                            alert('Stok produk tidak cukup.');
                            return;
                        }
                    }
                    const unitPrice = resolveUnitPrice(product, qty);
                    state.cart.set(product.id, {
                        product,
                        qty,
                        unit_price: unitPrice,
                    });
                    renderCart();
                    renderProducts();
                };

                const updateCartQty = (productId, delta) => {
                    if (state.shiftBlocked) {
                        return;
                    }

                    const existing = state.cart.get(productId);
                    if (!existing) {
                        return;
                    }
                    const next = existing.qty + delta;
                    if (next <= 0) {
                        state.cart.delete(productId);
                    } else {
                        if (existing.product.track_stock) {
                            const stock = getStock(existing.product);
                            if (stock !== null && next > stock) {
                                alert('Stok produk tidak cukup.');
                                return;
                            }
                        }
                        state.cart.set(productId, {
                            ...existing,
                            qty: next,
                            unit_price: resolveUnitPrice(existing.product, next),
                        });
                    }
                    renderCart();
                    renderProducts();
                };

                const renderCart = () => {
                    el.cartList.innerHTML = '';
                    if (!state.cart.size) {
                        el.cartList.innerHTML = '<div class="text-sm text-slate-400">Keranjang masih kosong.</div>';
                    } else {
                        [...state.cart.values()].forEach((item) => {
                            const row = document.createElement('div');
                            row.className = 'flex items-center justify-between';
                            row.innerHTML = `
                                <div class="flex-1">
                                    <h4 class="text-sm font-bold text-slate-800">${item.product.name}</h4>
                                    <p class="text-[11px] text-slate-500">${formatCurrency(item.unit_price)} / pcs</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center bg-slate-100 rounded-lg p-1">
                                        <button class="qty-minus w-8 h-8 flex items-center justify-center text-primary bg-white rounded-md shadow-sm" data-id="${item.product.id}">
                                            <span class="material-symbols-outlined text-base">remove</span>
                                        </button>
                                        <span class="w-10 text-center text-sm font-bold">${item.qty}</span>
                                        <button class="qty-plus w-8 h-8 flex items-center justify-center text-primary bg-white rounded-md shadow-sm" data-id="${item.product.id}">
                                            <span class="material-symbols-outlined text-base">add</span>
                                        </button>
                                    </div>
                                    <span class="text-sm font-bold w-20 text-right">${formatCurrency(item.unit_price * item.qty)}</span>
                                </div>
                            `;
                            el.cartList.appendChild(row);
                        });
                    }
                    updateTotals();
                    saveCartDraft();
                };

                const computeDiscountTotal = (productSubtotal) => {
                    if (!state.promos.length || !state.selectedPromos.size) {
                        return 0;
                    }
                    const now = new Date();
                    let total = 0;
                    state.promos.forEach((promo) => {
                        if (!state.selectedPromos.has(promo.id)) {
                            return;
                        }
                        if (promo.status && promo.status !== 'active') {
                            return;
                        }
                        const endsAt = formatDateOnly(promo.ends_at);
                        if (endsAt && endsAt < now) {
                            return;
                        }
                        const minPurchase = Number(promo.min_purchase || 0);
                        if (minPurchase > 0 && productSubtotal < minPurchase) {
                            return;
                        }
                        if (promo.type === 'percent') {
                            total += productSubtotal * (Number(promo.value || 0) / 100);
                            return;
                        }
                        total += Number(promo.value || 0);
                    });
                    return Math.max(total, 0);
                };

                const computeFeeTotal = (productSubtotal) => {
                    let total = 0;
                    state.fees.forEach((fee) => {
                        if (!fee.product_id) {
                            return;
                        }
                        const value = Number(fee.value || 0);
                        if (value <= 0) {
                            return;
                        }
                        if (fee.type === 'percent') {
                            total += productSubtotal * (value / 100);
                            return;
                        }
                        total += value;
                    });
                    return Math.max(total, 0);
                };

                const updateTotals = () => {
                    const items = [...state.cart.values()];
                    const productSubtotal = items.reduce((sum, item) => sum + item.unit_price * item.qty, 0);
                    const discount = computeDiscountTotal(productSubtotal);
                    const feeTotal = computeFeeTotal(productSubtotal);
                    const subtotal = productSubtotal + feeTotal;
                    const cappedDiscount = Math.min(discount, subtotal);
                    const tax = (subtotal - cappedDiscount) * state.taxRate;
                    const total = subtotal - cappedDiscount + tax;
                    el.itemCount.textContent = items.reduce((sum, item) => sum + item.qty, 0);
                    el.cartBadge.textContent = el.itemCount.textContent;
                    el.subtotal.textContent = formatCurrency(subtotal);
                    el.tax.textContent = formatCurrency(tax);
                    el.discountAmount.textContent = formatCurrency(cappedDiscount);
                    el.feeTotal.textContent = formatCurrency(feeTotal);
                    el.total.textContent = formatCurrency(total);
                    el.checkout.disabled = items.length === 0 || !state.shiftId || !state.locationId || !state.accountMapping?.cash_account_id;
                    el.checkout.classList.toggle('opacity-60', el.checkout.disabled);
                    el.checkout.classList.toggle('cursor-not-allowed', el.checkout.disabled);
                };

                const openCart = () => {
                    if (state.shiftBlocked) {
                        alert('Shift belum aktif. Silakan buka shift terlebih dahulu.');
                        return;
                    }

                    el.cartOverlay.classList.remove('hidden');
                    el.bottomNav.classList.add('hidden');
                };

                const closeCart = () => {
                    el.cartOverlay.classList.add('hidden');
                    el.bottomNav.classList.remove('hidden');
                };

                const setSellingEnabled = (enabled) => {
                    const disabled = !enabled;
                    state.shiftBlocked = disabled;

                    el.shiftGuard.classList.toggle('hidden', enabled);
                    el.search.disabled = disabled;
                    el.customer.disabled = disabled;
                    el.categoryFilters.classList.toggle('pointer-events-none', disabled);
                    el.productGrid.classList.toggle('pointer-events-none', disabled);
                    el.categoryFilters.classList.toggle('opacity-60', disabled);
                    el.productGrid.classList.toggle('opacity-60', disabled);

                    if (disabled) {
                        closeCart();
                        state.cart.clear();
                        state.fees = [];
                        state.selectedPromos.clear();
                        sessionStorage.removeItem(cartStorageKey);
                        renderFeeList();
                        renderPromoList();
                        updateTotals();
                    }
                };

                const renderCustomers = () => {
                    el.customer.innerHTML = '<option value="">Pelanggan Umum</option>';
                    state.customers.forEach((customer) => {
                        const option = document.createElement('option');
                        option.value = customer.id;
                        option.textContent = customer.name;
                        el.customer.appendChild(option);
                    });
                };

                const loadBootstrap = async () => {
                    const response = await fetch('/api/v1/bootstrap', { headers });
                    if (!response.ok) {
                        const mapped = await window.PosUi.mapApiError(response, 'Gagal memuat data kasir.');
                        throw new Error(mapped.message);
                    }
                    return response.json();
                };

                const refreshContext = async () => {
                    try {
                        const data = await loadBootstrap();
                        setupContext(data);
                    } catch (_) {
                        // keep current state if refresh fails
                    }
                };

                const loadTaxSettings = async () => {
                    const response = await fetch('/api/v1/settings/tax-promos', { headers });
                    if (!response.ok) {
                        return { taxes: [], promos: [] };
                    }
                    return response.json().catch(() => ({ taxes: [], promos: [] }));
                };

                const applyTaxSettings = (taxes) => {
                    const active = Array.isArray(taxes) ? taxes.find((tax) => tax.is_active) : null;
                    const rateValue = Number(active?.rate || 0);
                    state.taxRate = Number.isFinite(rateValue) ? rateValue / 100 : 0;
                    el.taxLabel.textContent = `Pajak (${formatRate(rateValue)}%)`;
                    updateTotals();
                };

                const applyPromoSettings = (promos) => {
                    state.promos = Array.isArray(promos) ? promos : [];
                    renderPromoList();
                    updateTotals();
                };

                const renderPromoList = () => {
                    el.discountList.innerHTML = '';
                    if (!state.promos.length) {
                        el.discountEmpty.classList.remove('hidden');
                        return;
                    }
                    el.discountEmpty.classList.add('hidden');

                    state.promos.forEach((promo) => {
                        if (promo.status && promo.status !== 'active') {
                            return;
                        }
                        const row = document.createElement('label');
                        row.className = 'flex items-center justify-between gap-3 text-sm text-slate-700';
                        row.innerHTML = `
                            <div>
                                <p class="font-semibold">${promo.name}</p>
                                <p class="text-xs text-slate-500">${promo.type === 'percent' ? `${promo.value}%` : formatCurrency(promo.value)}${promo.min_purchase ? ` • min ${formatCurrency(promo.min_purchase)}` : ''}</p>
                            </div>
                            <input type="checkbox" class="rounded border-slate-300 text-primary" data-promo-id="${promo.id}" ${state.selectedPromos.has(promo.id) ? 'checked' : ''} />
                        `;
                        el.discountList.appendChild(row);
                    });
                };

                const createFeeRow = (fee) => {
                    const row = document.createElement('div');
                    row.className = 'flex flex-wrap items-center gap-2';
                    row.innerHTML = `
                        <input class="min-w-[140px] flex-1 rounded-lg border-slate-200 bg-white px-3 py-2 text-xs" placeholder="Contoh: Plastik" value="${fee.name || ''}" />
                        <select class="w-14 rounded-lg border-slate-200 bg-white px-2 py-2 text-xs">
                            <option value="amount" ${fee.type === 'amount' ? 'selected' : ''}>Rp</option>
                            <option value="percent" ${fee.type === 'percent' ? 'selected' : ''}>%</option>
                        </select>
                        <input class="w-20 rounded-lg border-slate-200 bg-white px-2 py-2 text-xs text-right" placeholder="0" value="${fee.value || ''}" />
                        <button class="text-red-500" type="button" aria-label="Hapus biaya">
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </button>
                    `;

                    const [nameInput, typeSelect, valueInput, removeBtn] = row.querySelectorAll('input, select, button');

                    const syncFee = () => {
                        fee.name = nameInput.value.trim();
                        fee.type = typeSelect.value === 'percent' ? 'percent' : 'amount';
                        fee.value = Number(valueInput.value || 0);
                        updateTotals();
                        saveCartDraft();
                    };

                    const ensureFeeProduct = async () => {
                        if (!fee.name) {
                            fee.product_id = null;
                            return;
                        }
                        try {
                            const response = await fetch('/api/v1/fee-products', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    ...headers,
                                },
                                body: JSON.stringify({ name: fee.name }),
                            });
                            if (!response.ok) {
                                return;
                            }
                            const payload = await response.json();
                            fee.product_id = payload.product?.id || null;
                        } catch (_) {
                            fee.product_id = null;
                        }
                    };

                    nameInput.addEventListener('change', async () => {
                        syncFee();
                        await ensureFeeProduct();
                        saveCartDraft();
                    });
                    typeSelect.addEventListener('change', syncFee);
                    valueInput.addEventListener('input', syncFee);
                    removeBtn.addEventListener('click', () => {
                        state.fees = state.fees.filter((item) => item.id !== fee.id);
                        renderFeeList();
                        updateTotals();
                        saveCartDraft();
                    });

                    return row;
                };

                const renderFeeList = () => {
                    el.feeList.innerHTML = '';
                    state.fees.forEach((fee) => {
                        el.feeList.appendChild(createFeeRow(fee));
                    });
                };

                const setupContext = (data) => {
                    state.categories = data.categories || [];
                    state.products = (data.products || []).map((product) => ({
                        ...product,
                        price_tiers: product.price_tiers || [],
                        category_name: state.categories.find((cat) => cat.id === product.category_id)?.name || 'Produk',
                    }));
                    state.inventory = data.inventory || [];
                    state.customers = data.customers || [];
                    state.accountMapping = data.account_mapping || null;

                    state.branchId = data.branches?.[0]?.id || null;
                    const branchLocations = (data.locations || []).filter((loc) => loc.branch_id === state.branchId);
                    state.locationId = branchLocations.find((loc) => loc.is_default)?.id || branchLocations[0]?.id || null;

                    const shifts = data.shifts || [];
                    const currentUserId = data.user?.id || null;
                    let activeShift = null;

                    if (currentUserId) {
                        activeShift = shifts.find((shift) => shift.user_id === currentUserId && shift.branch_id === state.branchId)
                            || shifts.find((shift) => shift.user_id === currentUserId)
                            || null;
                    } else {
                        const storedShiftId = sessionStorage.getItem('pos_active_shift_id');
                        if (storedShiftId) {
                            activeShift = shifts.find((shift) => shift.id === storedShiftId) || null;
                        }
                    }

                    state.shiftId = activeShift?.id || null;

                    if (!state.shiftId) {
                        el.shiftIndicator.classList.remove('bg-success');
                        el.shiftIndicator.classList.add('bg-slate-300');
                        el.shiftStatus.textContent = 'Shift Tutup';
                        el.shiftOpened.textContent = 'Mulai: -';
                        setSellingEnabled(false);
                        if (el.quickClose) {
                            el.quickClose.classList.add('hidden');
                        }
                        sessionStorage.removeItem('pos_active_shift_id');
                    } else {
                        el.shiftIndicator.classList.remove('bg-slate-300');
                        el.shiftIndicator.classList.add('bg-success');
                        el.shiftStatus.textContent = 'Shift Aktif';
                        const openedAt = activeShift?.opened_at ? new Date(activeShift.opened_at) : null;
                        const tz = data.tenant?.timezone || 'UTC';
                        el.shiftOpened.textContent = openedAt
                            ? `Mulai: ${openedAt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: tz })}`
                            : 'Mulai: -';
                        setSellingEnabled(true);
                        if (el.quickClose) {
                            el.quickClose.classList.remove('hidden');
                        }
                        sessionStorage.setItem('pos_active_shift_id', state.shiftId);
                    }

                    if (data.user?.name) {
                        el.cashierName.textContent = data.user.name;
                    } else if (activeShift?.cashier_name) {
                        el.cashierName.textContent = activeShift.cashier_name;
                    }

                    renderCategories();
                    renderCustomers();
                    restoreCartDraft();
                    renderProducts();
                    renderCart();
                };

                const goToPayment = () => {
                    if (state.shiftBlocked) {
                        alert('Shift belum aktif. Silakan buka shift terlebih dahulu.');
                        return;
                    }

                    if (!state.shiftId || !state.locationId || !state.accountMapping?.cash_account_id) {
                        alert('Shift, lokasi, atau akun kas belum siap.');
                        return;
                    }
                    const items = [...state.cart.values()];
                    if (!items.length) {
                        return;
                    }

                    const snapshot = buildPaymentSnapshot();
                    snapshot.branch_id = state.branchId;
                    snapshot.shift_id = state.shiftId;
                    snapshot.location_id = state.locationId;
                    snapshot.customer_id = el.customer.value || null;
                    snapshot.account_mapping = state.accountMapping || null;
                    snapshot.idempotency_key = crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;
                    snapshot.cashier = el.cashierName.textContent || null;
                    snapshot.createdAt = new Date().toISOString();

                    sessionStorage.setItem('pos_payment', JSON.stringify(snapshot));
                    window.location.href = '/payment-method';
                };

                el.categoryFilters.addEventListener('click', (event) => {
                    const button = event.target.closest('button');
                    if (!button) {
                        return;
                    }
                    state.category = button.dataset.category || 'all';
                    applyCategoryStyles();
                    renderProducts();
                });

                el.search.addEventListener('input', (event) => {
                    state.term = event.target.value || '';
                    renderProducts();
                });

                el.cartList.addEventListener('click', (event) => {
                    const minus = event.target.closest('.qty-minus');
                    const plus = event.target.closest('.qty-plus');
                    if (minus) {
                        updateCartQty(minus.dataset.id, -1);
                    }
                    if (plus) {
                        updateCartQty(plus.dataset.id, 1);
                    }
                });

                el.clearCart.addEventListener('click', () => {
                    state.cart.clear();
                    state.fees = [];
                    state.selectedPromos.clear();
                    sessionStorage.removeItem(cartStorageKey);
                    renderCart();
                    renderProducts();
                    renderFeeList();
                    renderPromoList();
                    updateTotals();
                });

                el.cartToggle.addEventListener('click', openCart);
                el.cartClose.addEventListener('click', closeCart);
                el.cartOverlay.addEventListener('click', (event) => {
                    if (!event.target.closest('#cart-drawer')) {
                        closeCart();
                    }
                });

                const buildPaymentSnapshot = () => {
                    const items = [...state.cart.values()].map((item) => ({
                        id: item.product.id,
                        name: item.product.name,
                        qty: item.qty,
                        price: item.unit_price,
                        location_id: state.locationId,
                    }));
                    const productSubtotal = items.reduce((sum, item) => sum + item.price * item.qty, 0);
                    const discount = computeDiscountTotal(productSubtotal);
                    const feeTotal = computeFeeTotal(productSubtotal);
                    const subtotal = productSubtotal + feeTotal;
                    const cappedDiscount = Math.min(discount, subtotal);
                    const tax = (subtotal - cappedDiscount) * state.taxRate;
                    const total = subtotal - cappedDiscount + tax;
                    const feeItems = state.fees
                        .filter((fee) => fee.product_id && Number(fee.value || 0) > 0)
                        .map((fee) => {
                            const amount = fee.type === 'percent'
                                ? productSubtotal * (Number(fee.value || 0) / 100)
                                : Number(fee.value || 0);
                            return {
                                product_id: fee.product_id,
                                location_id: state.locationId,
                                qty: 1,
                                price: amount,
                                discount: 0,
                                name: fee.name,
                            };
                        });
                    const checkoutItems = [...state.cart.values()].map((item) => ({
                        product_id: item.product.id,
                        location_id: state.locationId,
                        qty: item.qty,
                        price: item.unit_price,
                        discount: 0,
                    })).concat(feeItems.map((fee) => ({
                        product_id: fee.product_id,
                        location_id: fee.location_id,
                        qty: 1,
                        price: fee.price,
                        discount: 0,
                    })));
                    return {
                        subtotal,
                        discount: cappedDiscount,
                        tax,
                        total,
                        items: items.concat(feeItems.map((fee) => ({
                            id: fee.product_id,
                            name: fee.name,
                            qty: 1,
                            price: fee.price,
                            location_id: fee.location_id,
                        }))),
                        checkout_items: checkoutItems,
                    };
                };

                el.checkout.addEventListener('click', goToPayment);
                el.paymentLink.addEventListener('click', (event) => {
                    event.preventDefault();
                    goToPayment();
                });

                (async () => {
                    try {
                        const [data, settings] = await Promise.all([
                            loadBootstrap(),
                            loadTaxSettings(),
                        ]);
                        setupContext(data);
                        applyTaxSettings(settings.taxes || []);
                        applyPromoSettings(settings.promos || []);
                        renderFeeList();
                    } catch (error) {
                        el.productGrid.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                    }
                })();

                window.addEventListener('focus', refreshContext);
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        refreshContext();
                    }
                });

                el.discountToggle.addEventListener('click', () => {
                    el.discountPanel.classList.toggle('hidden');
                });

                el.discountList.addEventListener('change', (event) => {
                    const checkbox = event.target.closest('input[data-promo-id]');
                    if (!checkbox) {
                        return;
                    }
                    const promoId = checkbox.dataset.promoId;
                    if (checkbox.checked) {
                        state.selectedPromos.add(promoId);
                    } else {
                        state.selectedPromos.delete(promoId);
                    }
                    updateTotals();
                    saveCartDraft();
                });

                el.feeAdd.addEventListener('click', () => {
                    const fee = {
                        id: crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`,
                        name: '',
                        type: 'amount',
                        value: 0,
                        product_id: null,
                    };
                    state.fees.push(fee);
                    renderFeeList();
                });
            })();
        </script>
@endsection
