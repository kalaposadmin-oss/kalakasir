@extends('layouts.app')

@php
    $productCreateTenant = \App\Support\TenantContext::get();
    $productCreateUser = auth()->user();
    $productCreateRole = null;

    if (! $productCreateTenant && filled($productCreateUser?->tenant_id)) {
        $productCreateTenant = \App\Models\Tenant::query()->find($productCreateUser->tenant_id);
    }

    if ($productCreateTenant && $productCreateUser) {
        $productCreateRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $productCreateTenant->id, (string) $productCreateUser->id);

        if (! $productCreateRole && filled($productCreateUser->role ?? null)) {
            $productCreateRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $productCreateUser->role);
        }
    }

    abort_unless(in_array($productCreateRole, ['admin', 'owner', 'superadmin'], true), 403);
@endphp

@php
    $productCreateFrom = trim((string) request()->query('from', ''));
    $productCreateBackUrl = '/backoffice/products';
    $productCreateListUrl = '/backoffice/products';
    if ($productCreateFrom !== '') {
        if (str_starts_with($productCreateFrom, '/pos/')) {
            $productCreateBackUrl = '/portal/switch/pos?next='.rawurlencode($productCreateFrom);
            $productCreateListUrl = '/backoffice/products?from='.rawurlencode($productCreateFrom);
        } elseif (str_starts_with($productCreateFrom, '/backoffice/products')) {
            $productCreateBackUrl = $productCreateFrom;
            $productCreateListUrl = $productCreateFrom;
        } elseif (str_starts_with($productCreateFrom, '/backoffice/')) {
            $productCreateBackUrl = $productCreateFrom;
            $productCreateListUrl = '/backoffice/products?from='.rawurlencode($productCreateFrom);
        }
    }
@endphp

@section('page_title', 'POS SaaS')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex flex-1 flex-col overflow-hidden p-0')

@push('head')
    <style>
        .select-no-native-arrow {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: none !important;
        }

        .select-no-native-arrow::-ms-expand {
            display: none;
        }
    </style>
@endpush

@section('header')
    <x-page-header title="Tambah Produk Baru" :backUrl="$productCreateBackUrl" :showMenuButton="true" />
@endsection

@section('content')
        <div class="flex-1 overflow-y-auto pb-32">
            <div class="px-4 py-4">
                <div class="relative group">
                    <div id="image-preview" class="w-full aspect-video bg-slate-50 dark:bg-slate-800/50 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl flex flex-col items-center justify-center overflow-hidden transition-all hover:border-primary/50" style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuBYuCMEoCG-olGE_fxLtmDzd2bF2w2IDFLmzF5xj54APUEJc1lpXNRBOXm7B5Z6F2AcxRHbUUZVDOhEPRnGL-dQEETshdoiEmQfsGLZv8EArgbo1ghz7_ijoAuhIyUMrt1kXLXkHz1tip9vdyNOkymgyvC1mYlbPjlbFqQIkgmqQfzZq64Vpq_K7BGS3P3ZzCi7WBjPH-rC3Lqu7bfqWH6Q5ad6MqENP020ISAC8ehZ9L7iAtlchEQr293m2bhO2L2pkNIb-C5L4w"); background-size: cover; background-position: center;'>
                        <div id="image-overlay" class="absolute inset-0 bg-black/20 flex flex-col items-center justify-center backdrop-blur-[2px]">
                            <div class="bg-white/90 dark:bg-slate-900/90 p-3 rounded-full shadow-lg mb-2">
                                <span class="material-symbols-outlined text-primary text-3xl">add_a_photo</span>
                            </div>
                            <p class="text-white text-sm font-semibold">Unggah Foto Produk</p>
                            <p class="text-white/80 text-xs">PNG, JPG up to 5MB</p>
                        </div>
                        <input id="product-image" class="absolute inset-0 opacity-0 cursor-pointer" type="file" accept="image/*" />
                    </div>
                </div>
            </div>

            <div class="px-4 space-y-5">
                <div class="flex flex-col gap-1.5">
                    <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold px-0.5">Nama Produk</label>
                    <input id="product-name" class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 text-base placeholder:text-slate-400" placeholder="Contoh: Kopi Susu Gula Aren" type="text" />
                </div>
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between px-0.5">
                            <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold">SKU / Barcode</label>
                            <button id="sku-generate" class="text-xs font-semibold text-primary" type="button">Buat Otomatis</button>
                        </div>
                        <div class="flex items-center gap-2">
                            <input id="product-sku" class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 text-base placeholder:text-slate-400" placeholder="Masukkan kode atau scan" type="text" />
                            <button class="bg-primary/10 text-primary p-3 rounded-xl hover:bg-primary/20 transition-colors flex items-center justify-center" type="button">
                                <span class="material-symbols-outlined">barcode_scanner</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between px-0.5">
                            <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold">Kategori</label>
                            <button id="category-quick-toggle" class="text-xs font-semibold text-primary" type="button">Tambah cepat</button>
                        </div>
                        <div class="relative">
                            <select id="category-select" class="select-no-native-arrow w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 pr-10 text-base">
                                <option value="">Pilih Kategori</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                        </div>
                        <div id="category-quick-panel" class="hidden rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-3 space-y-3">
                            <div class="flex items-center gap-2">
                                <input id="category-quick-name" class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-11 px-4 text-sm placeholder:text-slate-400" placeholder="Contoh: Minuman Dingin" type="text" />
                                <button id="category-quick-save" class="h-11 rounded-xl bg-primary px-4 text-sm font-semibold text-white shadow-lg shadow-primary/20" type="button">Simpan</button>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <p id="category-quick-status" class="text-xs text-slate-500 dark:text-slate-400">Buat kategori baru tanpa keluar dari halaman ini.</p>
                                <button id="category-quick-cancel" class="text-xs font-medium text-slate-500 dark:text-slate-400" type="button">Tutup</button>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold px-0.5">Harga Jual (Eceran)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">Rp</span>
                                <input id="product-price" class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 pl-10 pr-4 text-base placeholder:text-slate-400" placeholder="0" type="number" />
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold px-0.5">HPP (Modal)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">Rp</span>
                                <input id="product-hpp" class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 pl-10 pr-4 text-base placeholder:text-slate-400" placeholder="0" type="number" />
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Gunakan Stok</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Aktifkan jika produk memakai stok.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input id="track-stock" type="checkbox" class="sr-only peer" checked />
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:bg-primary"></div>
                            <div class="absolute left-1 top-1 size-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                        </label>
                    </div>
                    <div class="rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-4" id="variant-editor-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Produk Bervarian</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Aktifkan jika stok dan valuation harus dipisah per variant.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input id="enable-variants" type="checkbox" class="sr-only peer" />
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:bg-primary"></div>
                                <div class="absolute left-1 top-1 size-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                            </label>
                        </div>
                        <div id="variant-editor" class="hidden space-y-3">
                            <div class="rounded-xl bg-sky-50 text-sky-900 px-3 py-3 text-xs leading-relaxed">
                                Saat mode variant aktif, stok awal harus diisi per variant. Tidak ada fallback ke bucket produk umum.
                            </div>
                            <div class="flex items-center justify-between">
                                <p id="variant-editor-hint" class="text-xs text-slate-500 dark:text-slate-400">Belum ada variant.</p>
                                <button id="add-variant-line" class="text-xs font-semibold text-primary" type="button">Tambah Variant</button>
                            </div>
                            <div id="variant-line-list" class="space-y-3"></div>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5" id="stock-section">
                        <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold px-0.5">Stok Awal</label>
                        <div class="flex items-center gap-3">
                            <button id="stock-minus" class="size-12 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-400 hover:bg-slate-50">
                                <span class="material-symbols-outlined">remove</span>
                            </button>
                            <input id="product-stock" class="flex-1 text-center rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 text-lg font-bold" type="number" value="0" />
                            <button id="stock-plus" class="size-12 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-400 hover:bg-slate-50">
                                <span class="material-symbols-outlined">add</span>
                            </button>
                        </div>
                    </div>
                    <div class="hidden rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3" id="variant-stock-section">
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Stok Awal Per Variant</p>
                            <p id="variant-stock-hint" class="text-xs text-slate-500 dark:text-slate-400">Tambahkan variant untuk mengisi stok awal per bucket.</p>
                        </div>
                        <div id="variant-stock-line-list" class="space-y-3"></div>
                    </div>
                    <div class="rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-4" id="bom-editor-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Gunakan BOM / Recipe</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Tambahkan bahan baku yang akan auto-consume saat produk ini dijual.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input id="enable-bom" type="checkbox" class="sr-only peer" />
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:bg-primary"></div>
                                <div class="absolute left-1 top-1 size-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                            </label>
                        </div>
                        <div id="bom-editor" class="hidden space-y-3">
                            <div class="rounded-xl bg-amber-50 text-amber-900 px-3 py-3 text-xs leading-relaxed">
                                Raw material non-variant akan dikirim dengan <span class="font-semibold">product_variant_id = null</span>. Jika raw material punya variants, variant wajib dipilih eksplisit per line.
                            </div>
                            <div class="flex items-center justify-between">
                                <p id="bom-editor-hint" class="text-xs text-slate-500 dark:text-slate-400">Belum ada line BOM.</p>
                                <button id="add-bom-line" class="text-xs font-semibold text-primary" type="button">Tambah Bahan Baku</button>
                            </div>
                            <div id="bom-line-list" class="space-y-3"></div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3" id="price-tiers">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Tipe Harga</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Atur harga berdasarkan minimal qty.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input id="enable-tiers" type="checkbox" class="sr-only peer" />
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:bg-primary"></div>
                                <div class="absolute left-1 top-1 size-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400">Contoh: min 1 = ecer, min 25 = grosir.</span>
                            <button id="add-tier" class="text-xs font-semibold text-primary" type="button">Tambah</button>
                        </div>
                        <div id="tier-list" class="space-y-3 hidden"></div>
                    </div>
                    <button id="advanced-toggle" class="flex items-center justify-between w-full py-2 group" type="button" aria-expanded="false">
                        <span class="text-slate-500 dark:text-slate-400 text-sm font-medium group-hover:text-primary transition-colors">Opsi Lanjutan (Pajak, Diskon, dll)</span>
                        <span id="advanced-icon" class="material-symbols-outlined text-slate-400 group-hover:text-primary transition-colors">chevron_right</span>
                    </button>
                    <div id="advanced-options" class="hidden rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3">
                        <div class="flex items-center gap-3">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300 w-20">Pajak</label>
                            <input id="product-tax" class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 h-10 px-3 text-sm" placeholder="0" type="number" />
                            <span class="text-xs text-slate-400">%</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300 w-20">Diskon</label>
                            <input id="product-discount" class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 h-10 px-3 text-sm" placeholder="0" type="number" />
                            <span class="text-xs text-slate-400">%</span>
                        </div>
                        <p class="text-xs text-slate-400">Opsi lanjutan akan diterapkan setelah modul pajak dan diskon aktif.</p>
                    </div>
                <p id="form-error" class="text-sm text-red-500 font-semibold hidden"></p>
            </div>
        </div>

        <div class="fixed bottom-0 left-1/2 w-full max-w-[430px] -translate-x-1/2 bg-white dark:bg-background-dark p-4 border-t border-slate-100 dark:border-slate-800 flex gap-3">
            <button class="flex-1 h-12 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" onclick="location.href=@js($productCreateBackUrl)">
                Batal
            </button>
            <button id="save-product" class="flex-[2] h-12 rounded-xl bg-primary text-white font-bold hover:bg-primary/90 shadow-lg shadow-primary/20 transition-all active:scale-95">
                Simpan Produk
            </button>
        </div>

        @include('partials.ui-helpers')

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const productListUrl = @json($productCreateListUrl);

                const el = {
                    name: document.getElementById('product-name'),
                    sku: document.getElementById('product-sku'),
                    skuGenerate: document.getElementById('sku-generate'),
                    category: document.getElementById('category-select'),
                    categoryQuickToggle: document.getElementById('category-quick-toggle'),
                    categoryQuickPanel: document.getElementById('category-quick-panel'),
                    categoryQuickName: document.getElementById('category-quick-name'),
                    categoryQuickSave: document.getElementById('category-quick-save'),
                    categoryQuickCancel: document.getElementById('category-quick-cancel'),
                    categoryQuickStatus: document.getElementById('category-quick-status'),
                    price: document.getElementById('product-price'),
                    hpp: document.getElementById('product-hpp'),
                    stock: document.getElementById('product-stock'),
                    minus: document.getElementById('stock-minus'),
                    plus: document.getElementById('stock-plus'),
                    trackStock: document.getElementById('track-stock'),
                    stockSection: document.getElementById('stock-section'),
                    enableVariants: document.getElementById('enable-variants'),
                    variantEditor: document.getElementById('variant-editor'),
                    variantEditorHint: document.getElementById('variant-editor-hint'),
                    variantLineList: document.getElementById('variant-line-list'),
                    addVariantLine: document.getElementById('add-variant-line'),
                    variantStockSection: document.getElementById('variant-stock-section'),
                    variantStockHint: document.getElementById('variant-stock-hint'),
                    variantStockLineList: document.getElementById('variant-stock-line-list'),
                    enableBom: document.getElementById('enable-bom'),
                    bomEditor: document.getElementById('bom-editor'),
                    bomHint: document.getElementById('bom-editor-hint'),
                    bomLineList: document.getElementById('bom-line-list'),
                    addBomLine: document.getElementById('add-bom-line'),
                    save: document.getElementById('save-product'),
                    error: document.getElementById('form-error'),
                    image: document.getElementById('product-image'),
                    imagePreview: document.getElementById('image-preview'),
                    imageOverlay: document.getElementById('image-overlay'),
                    advancedToggle: document.getElementById('advanced-toggle'),
                    advancedIcon: document.getElementById('advanced-icon'),
                    advancedOptions: document.getElementById('advanced-options'),
                    tierList: document.getElementById('tier-list'),
                    addTier: document.getElementById('add-tier'),
                    enableTiers: document.getElementById('enable-tiers'),
                };

                let imageBase64 = null;
                let rawMaterialProducts = [];
                const productDetailCache = new Map();

                const apiFetch = (url, init = {}, timeoutMs = 15000) => {
                    if (window.PosUi?.fetchWithTimeout) {
                        return window.PosUi.fetchWithTimeout(url, init, timeoutMs);
                    }

                    return fetch(url, {
                        ...init,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(init.headers || {}),
                        },
                    });
                };

                const generateSku = () => {
                    const rand = Math.random().toString(36).slice(2, 8).toUpperCase();
                    const date = new Date().toISOString().slice(2, 10).replace(/-/g, '');
                    return `SKU-${date}-${rand}`;
                };

                const generateUuid = () => {
                    if (window.crypto?.randomUUID) {
                        return window.crypto.randomUUID();
                    }

                    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
                        const random = Math.random() * 16 | 0;
                        const value = char === 'x' ? random : (random & 0x3 | 0x8);
                        return value.toString(16);
                    });
                };

                const ensureSku = () => {
                    if (!el.sku.value.trim()) {
                        el.sku.value = generateSku();
                    }
                };

                const showError = (message) => {
                    if (!message) {
                        el.error.classList.add('hidden');
                        el.error.textContent = '';
                        return;
                    }
                    el.error.textContent = message;
                    el.error.classList.remove('hidden');
                };

                const setCategoryQuickPanel = (open) => {
                    el.categoryQuickPanel.classList.toggle('hidden', !open);
                    el.categoryQuickToggle.textContent = open ? 'Tutup' : 'Tambah cepat';
                    if (open) {
                        el.categoryQuickName.focus();
                    }
                };

                const setCategoryQuickStatus = (message, tone = 'muted') => {
                    el.categoryQuickStatus.textContent = message;
                    el.categoryQuickStatus.className = 'text-xs';

                    if (tone === 'error') {
                        el.categoryQuickStatus.classList.add('text-red-500');
                        return;
                    }

                    if (tone === 'success') {
                        el.categoryQuickStatus.classList.add('text-emerald-600', 'dark:text-emerald-400');
                        return;
                    }

                    el.categoryQuickStatus.classList.add('text-slate-500', 'dark:text-slate-400');
                };

                const renderCategories = (categories, selectedId = '') => {
                    el.category.innerHTML = '<option value="">Pilih Kategori</option>';
                    categories.forEach((item) => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.name;
                        el.category.appendChild(option);
                    });
                    el.category.value = selectedId || '';
                };

                const loadCategories = async (selectedId = '') => {
                    const response = await fetch('/api/v1/categories', { headers });
                    if (!response.ok) {
                        return;
                    }
                    const data = await response.json();
                    const categories = data.categories || [];
                    renderCategories(categories, selectedId);
                };

                const loadRawMaterialProducts = async () => {
                    const response = await apiFetch('/api/v1/products', { headers });
                    if (!response.ok) {
                        return [];
                    }

                    const data = await response.json().catch(() => ({}));
                    return (data.products || [])
                        .filter((product) => String(product.item_type || '') === 'raw_material')
                        .sort((left, right) => String(left.name || '').localeCompare(String(right.name || '')));
                };

                const loadProductDetail = async (productId) => {
                    if (!productId) {
                        return null;
                    }

                    if (productDetailCache.has(productId)) {
                        return productDetailCache.get(productId);
                    }

                    const response = await apiFetch(`/api/v1/products/${productId}`, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat detail bahan baku.');
                    }

                    const data = await response.json().catch(() => ({}));
                    const product = data.product || null;
                    productDetailCache.set(productId, product);

                    return product;
                };

                const bomProductOptionsHtml = (selectedId = '') => {
                    const options = ['<option value="">Pilih bahan baku</option>'];

                    rawMaterialProducts.forEach((product) => {
                        const selected = String(selectedId || '') === String(product.id) ? 'selected' : '';
                        const suffix = product.sku ? ` (${product.sku})` : '';
                        options.push(`<option value="${product.id}" ${selected}>${product.name}${suffix}</option>`);
                    });

                    return options.join('');
                };

                const setBomHint = (message) => {
                    el.bomHint.textContent = message;
                };

                const setVariantHint = (message) => {
                    el.variantEditorHint.textContent = message;
                };

                const setVariantStockHint = (message) => {
                    el.variantStockHint.textContent = message;
                };

                const variantRowsData = () => Array.from(el.variantLineList.children).map((row, index) => ({
                    id: row.querySelector('[data-variant-id]')?.value || '',
                    sku: row.querySelector('[data-variant-sku]')?.value?.trim() || '',
                    name: row.querySelector('[data-variant-name]')?.value?.trim() || `Variant ${index + 1}`,
                }));

                const buildVariantRow = (variant = {}) => {
                    const row = document.createElement('div');
                    row.className = 'rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 p-3 space-y-3';
                    row.innerHTML = `
                        <input type="hidden" data-variant-id value="${variant.id || generateUuid()}" />
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Variant Produk</p>
                            <button class="text-xs font-semibold text-rose-500" type="button" data-variant-remove>Hapus</button>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-slate-500">SKU Variant</label>
                                <input class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100" data-variant-sku type="text" value="${variant.sku || ''}" placeholder="Contoh: MERAH-M" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-slate-500">Nama Variant</label>
                                <input class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100" data-variant-name type="text" value="${variant.name || ''}" placeholder="Contoh: Merah / Medium" />
                            </div>
                        </div>
                    `;

                    return row;
                };

                const updateVariantEditorVisibility = () => {
                    const enabled = el.enableVariants.checked;
                    el.variantEditor.classList.toggle('hidden', !enabled);

                    if (!enabled) {
                        el.variantLineList.innerHTML = '';
                        setVariantHint('Belum ada variant.');
                    }

                    updateInitialStockMode();
                };

                const updateVariantHint = () => {
                    if (!el.enableVariants.checked) {
                        setVariantHint('Mode variant nonaktif.');
                        return;
                    }

                    if (!el.variantLineList.children.length) {
                        setVariantHint('Belum ada variant.');
                        return;
                    }

                    setVariantHint(`${el.variantLineList.children.length} variant aktif.`);
                };

                const buildVariantStockRow = (variant, currentValue = {}) => {
                    const row = document.createElement('div');
                    row.className = 'rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 p-3 grid grid-cols-12 gap-3 items-center';
                    row.innerHTML = `
                        <input type="hidden" data-stock-variant-id value="${variant.id}" />
                        <div class="col-span-5">
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">${variant.name || 'Variant'}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">${variant.sku || 'SKU variant belum diisi'}</p>
                        </div>
                        <div class="col-span-3 space-y-1">
                            <label class="text-[11px] font-semibold text-slate-500">Qty</label>
                            <input class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100" data-stock-qty type="number" min="0" step="0.0001" value="${currentValue.qty ?? ''}" placeholder="0" />
                        </div>
                        <div class="col-span-4 space-y-1">
                            <label class="text-[11px] font-semibold text-slate-500">Unit Cost</label>
                            <input class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100" data-stock-unit-cost type="number" min="0" step="0.01" value="${currentValue.unit_cost ?? ''}" placeholder="HPP default" />
                        </div>
                    `;

                    return row;
                };

                const renderVariantStockLines = () => {
                    const currentValues = new Map(
                        Array.from(el.variantStockLineList.children).map((row) => ([
                            row.querySelector('[data-stock-variant-id]')?.value || '',
                            {
                                qty: row.querySelector('[data-stock-qty]')?.value || '',
                                unit_cost: row.querySelector('[data-stock-unit-cost]')?.value || '',
                            },
                        ]))
                    );

                    el.variantStockLineList.innerHTML = '';
                    const variants = variantRowsData();
                    variants.forEach((variant) => {
                        el.variantStockLineList.appendChild(buildVariantStockRow(variant, currentValues.get(variant.id) || {}));
                    });

                    if (!variants.length) {
                        setVariantStockHint('Tambahkan variant untuk mengisi stok awal per bucket.');
                        return;
                    }

                    setVariantStockHint(`${variants.length} bucket variant siap diisi.`);
                };

                const updateInitialStockMode = () => {
                    const trackStockEnabled = el.trackStock.checked;
                    const variantMode = el.enableVariants.checked;

                    el.stockSection.classList.toggle('hidden', variantMode || !trackStockEnabled);
                    el.variantStockSection.classList.toggle('hidden', !variantMode || !trackStockEnabled);

                    if (!trackStockEnabled) {
                        el.stock.value = '0';
                        Array.from(el.variantStockLineList.querySelectorAll('input')).forEach((input) => {
                            input.value = '';
                        });
                        return;
                    }

                    if (variantMode) {
                        renderVariantStockLines();
                    }
                };

                const collectVariants = () => {
                    const variants = variantRowsData();

                    if (!variants.length) {
                        throw new Error('Minimal satu variant wajib diisi saat mode variant aktif.');
                    }

                    const seenSkus = new Set();
                    return variants.map((variant, index) => {
                        if (!variant.sku || !variant.name) {
                            throw new Error(`Variant #${index + 1}: nama dan SKU wajib diisi.`);
                        }

                        const skuKey = variant.sku.toLowerCase();
                        if (seenSkus.has(skuKey)) {
                            throw new Error(`Variant #${index + 1}: SKU variant duplikat.`);
                        }

                        seenSkus.add(skuKey);
                        return {
                            id: variant.id,
                            sku: variant.sku,
                            name: variant.name,
                            attributes: {},
                            price_override: null,
                        };
                    });
                };

                const collectVariantStockLines = (variants) => {
                    const rows = Array.from(el.variantStockLineList.children);
                    const knownVariantIds = new Set(variants.map((variant) => String(variant.id)));

                    return rows.map((row, index) => {
                        const variantId = row.querySelector('[data-stock-variant-id]')?.value || '';
                        const qty = Number(row.querySelector('[data-stock-qty]')?.value || 0);
                        const unitCostRaw = row.querySelector('[data-stock-unit-cost]')?.value || '';

                        if (!knownVariantIds.has(String(variantId))) {
                            throw new Error(`Line stok variant #${index + 1}: variant tidak valid.`);
                        }

                        return {
                            product_variant_id: variantId,
                            qty,
                            unit_cost: unitCostRaw === '' ? null : Number(unitCostRaw),
                        };
                    }).filter((line) => line.qty > 0);
                };

                const createBomLineRow = (line = {}) => {
                    const row = document.createElement('div');
                    row.className = 'rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 p-3 space-y-3';
                    row.innerHTML = `
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Line BOM</p>
                            <button class="text-xs font-semibold text-rose-500" type="button" data-bom-remove>Hapus</button>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-500">Raw Material</label>
                            <select class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100" data-bom-product>
                                ${bomProductOptionsHtml(line.product_id || '')}
                            </select>
                        </div>
                        <div class="space-y-1.5 hidden" data-bom-variant-wrap>
                            <label class="text-xs font-semibold text-slate-500">Variant Raw Material</label>
                            <select class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100" data-bom-variant>
                                <option value="">Pilih variant</option>
                            </select>
                            <p class="text-[11px] text-slate-400" data-bom-variant-hint></p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-500">Qty per 1 output</label>
                            <input class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100" data-bom-qty type="number" min="0.0001" step="0.0001" value="${line.qty ?? ''}" placeholder="Contoh: 2" />
                        </div>
                    `;

                    return row;
                };

                const updateBomEditorVisibility = () => {
                    const enabled = el.enableBom.checked;
                    el.bomEditor.classList.toggle('hidden', !enabled);
                    if (!enabled) {
                        el.bomLineList.innerHTML = '';
                        setBomHint('Belum ada line BOM.');
                        return;
                    }

                    if (!el.bomLineList.children.length) {
                        setBomHint(rawMaterialProducts.length
                            ? 'Tambahkan bahan baku dan pilih variant jika bahan baku punya variants.'
                            : 'Belum ada produk raw material yang bisa dipilih.');
                    }
                };

                const updateBomLineCountHint = () => {
                    if (!el.enableBom.checked) {
                        setBomHint('Belum ada line BOM.');
                        return;
                    }

                    if (!el.bomLineList.children.length) {
                        setBomHint(rawMaterialProducts.length
                            ? 'Belum ada line BOM.'
                            : 'Belum ada produk raw material yang bisa dipilih.');
                        return;
                    }

                    setBomHint(`${el.bomLineList.children.length} line BOM aktif.`);
                };

                const configureBomVariantField = async (row, productId, selectedVariantId = null) => {
                    const wrap = row.querySelector('[data-bom-variant-wrap]');
                    const variantSelect = row.querySelector('[data-bom-variant]');
                    const hint = row.querySelector('[data-bom-variant-hint]');

                    if (!productId) {
                        wrap.classList.add('hidden');
                        variantSelect.innerHTML = '<option value="">Pilih variant</option>';
                        variantSelect.value = '';
                        hint.textContent = '';
                        return;
                    }

                    const productDetail = await loadProductDetail(productId);
                    const variants = Array.isArray(productDetail?.product_variants) ? productDetail.product_variants : [];

                    if (!variants.length) {
                        wrap.classList.add('hidden');
                        variantSelect.innerHTML = '<option value="">Tidak perlu variant</option>';
                        variantSelect.value = '';
                        hint.textContent = 'Raw material ini non-variant, product_variant_id akan dikirim sebagai null.';
                        return;
                    }

                    wrap.classList.remove('hidden');
                    variantSelect.innerHTML = '<option value="">Pilih variant</option>';

                    variants.forEach((variant) => {
                        const option = document.createElement('option');
                        option.value = variant.id;
                        option.textContent = variant.sku ? `${variant.name} (${variant.sku})` : variant.name;
                        variantSelect.appendChild(option);
                    });

                    const stillValid = variants.some((variant) => String(variant.id) === String(selectedVariantId || ''));
                    variantSelect.value = stillValid ? String(selectedVariantId) : '';
                    hint.textContent = 'Raw material ini punya variants. Variant wajib dipilih eksplisit.';
                };

                const appendBomLine = async (line = {}) => {
                    const row = createBomLineRow(line);
                    el.bomLineList.appendChild(row);
                    await configureBomVariantField(row, line.product_id || '', line.product_variant_id || null);
                    updateBomLineCountHint();
                };

                const collectBomItems = () => {
                    const items = [];
                    const rows = Array.from(el.bomLineList.querySelectorAll('div[data-bom-product], [data-bom-remove]')).length
                        ? Array.from(el.bomLineList.children)
                        : [];

                    if (!rows.length) {
                        throw new Error('Minimal satu line BOM wajib diisi saat mode BOM aktif.');
                    }

                    rows.forEach((row, index) => {
                        const productId = row.querySelector('[data-bom-product]')?.value || '';
                        const variantWrap = row.querySelector('[data-bom-variant-wrap]');
                        const variantRequired = variantWrap && !variantWrap.classList.contains('hidden');
                        const variantId = row.querySelector('[data-bom-variant]')?.value || '';
                        const qtyValue = Number(row.querySelector('[data-bom-qty]')?.value || 0);

                        if (!productId) {
                            throw new Error(`Line BOM #${index + 1}: raw material wajib dipilih.`);
                        }

                        if (!(qtyValue > 0)) {
                            throw new Error(`Line BOM #${index + 1}: qty harus lebih dari 0.`);
                        }

                        if (variantRequired && !variantId) {
                            throw new Error(`Line BOM #${index + 1}: variant raw material wajib dipilih.`);
                        }

                        items.push({
                            product_id: productId,
                            product_variant_id: variantRequired ? variantId : null,
                            qty: qtyValue,
                        });
                    });

                    return items;
                };

                const createCategoryQuick = async () => {
                    const name = el.categoryQuickName.value.trim();
                    if (!name) {
                        setCategoryQuickStatus('Nama kategori wajib diisi.', 'error');
                        return;
                    }

                    el.categoryQuickSave.disabled = true;
                    setCategoryQuickStatus('Menyimpan kategori...', 'muted');

                    try {
                        const response = await apiFetch('/api/v1/categories', {
                            method: 'POST',
                            headers: {
                                ...headers,
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ name }),
                        });

                        if (!response.ok) {
                            const errorPayload = await response.json().catch(() => ({}));
                            const validationMessage = Object.values(errorPayload?.errors || {})[0]?.[0];
                            throw new Error(validationMessage || errorPayload?.message || 'Gagal menambah kategori.');
                        }

                        const data = await response.json().catch(() => ({}));

                        const category = data?.category || null;
                        await loadCategories(category?.id || '');
                        el.categoryQuickName.value = '';
                        setCategoryQuickStatus('Kategori berhasil ditambahkan.', 'success');
                        setCategoryQuickPanel(false);
                    } catch (error) {
                        setCategoryQuickStatus(error.message || 'Gagal menambah kategori.', 'error');
                    } finally {
                        el.categoryQuickSave.disabled = false;
                    }
                };

                const buildTierRow = (tier = {}) => {
                    const row = document.createElement('div');
                    row.className = 'grid grid-cols-12 gap-2 items-center';
                    row.innerHTML = `
                        <input class="col-span-5 rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 h-10 px-3 text-xs" placeholder="Nama" value="${tier.label || ''}" data-tier-label />
                        <input class="col-span-3 rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 h-10 px-2 text-xs" placeholder="Min" type="number" value="${tier.min_qty ?? ''}" data-tier-min />
                        <input class="col-span-3 rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 h-10 px-3 text-xs" placeholder="Harga" type="number" value="${tier.price ?? ''}" data-tier-price />
                        <button class="col-span-1 flex items-center justify-center size-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500" type="button" data-tier-remove>
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    `;
                    return row;
                };

                const addTier = (tier) => {
                    el.tierList.appendChild(buildTierRow(tier));
                };

                const collectTiers = () => {
                    const tiers = [];
                    el.tierList.querySelectorAll('div').forEach((row) => {
                        const label = row.querySelector('[data-tier-label]')?.value?.trim();
                        const minQty = row.querySelector('[data-tier-min]')?.value;
                        const price = row.querySelector('[data-tier-price]')?.value;
                        if (!label || !minQty || !price) {
                            return;
                        }
                        tiers.push({
                            label,
                            min_qty: Number(minQty),
                            price: Number(price),
                        });
                    });
                    return tiers.sort((a, b) => a.min_qty - b.min_qty);
                };

                const resetProductForm = () => {
                    el.name.value = '';
                    el.sku.value = '';
                    el.category.value = '';
                    el.categoryQuickName.value = '';
                    setCategoryQuickStatus('Buat kategori baru tanpa keluar dari halaman ini.');
                    setCategoryQuickPanel(false);
                    el.price.value = '';
                    el.hpp.value = '';
                    el.stock.value = '0';
                    el.trackStock.checked = true;
                    el.stockSection.classList.remove('opacity-50');
                    el.stockSection.querySelectorAll('input, button').forEach((input) => {
                        input.disabled = false;
                    });
                    el.enableVariants.checked = false;
                    el.variantLineList.innerHTML = '';
                    el.variantStockLineList.innerHTML = '';
                    updateVariantEditorVisibility();
                    el.enableBom.checked = false;
                    el.bomLineList.innerHTML = '';
                    updateBomEditorVisibility();
                    el.enableTiers.checked = false;
                    el.tierList.innerHTML = '';
                    el.tierList.classList.add('hidden');
                    el.addTier.disabled = true;
                    el.advancedOptions.classList.add('hidden');
                    el.advancedToggle.setAttribute('aria-expanded', 'false');
                    el.advancedIcon.textContent = 'chevron_right';
                    imageBase64 = null;
                    el.image.value = '';
                    el.imagePreview.style.backgroundImage = '';
                    el.imageOverlay.classList.remove('hidden');
                    showError('');
                    ensureSku();
                    el.name.focus();
                };

                const submit = async () => {
                    showError('');

                    ensureSku();

                    const payload = {
                        name: el.name.value.trim(),
                        sku: el.sku.value.trim(),
                        category_id: el.category.value || null,
                        price: Number(el.price.value || 0),
                        hpp_average: Number(el.hpp.value || 0),
                        initial_stock: el.trackStock.checked && !el.enableVariants.checked ? Number(el.stock.value || 0) : 0,
                        image_base64: imageBase64,
                        track_stock: el.trackStock.checked,
                        price_tiers: el.enableTiers.checked ? collectTiers() : [],
                    };

                    if (!payload.name) {
                        showError('Nama produk wajib diisi.');
                        return;
                    }

                    if (payload.price <= 0) {
                        showError('Harga jual harus lebih dari 0.');
                        return;
                    }

                    if (el.enableVariants.checked) {
                        payload.variants = collectVariants();
                        payload.initial_stock_lines = el.trackStock.checked
                            ? collectVariantStockLines(payload.variants)
                            : [];
                    }

                    if (el.enableBom.checked) {
                        payload.product_mode = 'bom';
                        payload.item_type = 'finished_good';
                        payload.bom_items = collectBomItems();
                    }

                    el.save.disabled = true;

                    try {
                        const response = await apiFetch('/api/v1/products', {
                            method: 'POST',
                            headers: {
                                ...headers,
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(payload),
                        });

                        if (!response.ok) {
                            const mapped = window.PosUi?.mapApiError
                                ? await window.PosUi.mapApiError(response, 'Gagal menyimpan produk.')
                                : null;
                            const validationMessage = Object.values(mapped?.payload?.errors || {})[0]?.[0];
                            throw new Error(validationMessage || mapped?.message || 'Gagal menyimpan produk.');
                        }

                        const addAnother = window.confirm('Produk berhasil disimpan.\n\nKlik OK untuk tambah produk lagi.\nKlik Cancel untuk kembali ke daftar produk.');
                        if (addAnother) {
                            resetProductForm();
                            return;
                        }

                        window.location.href = productListUrl;
                    } catch (error) {
                        showError(error.message || 'Gagal menyimpan produk.');
                    } finally {
                        el.save.disabled = false;
                    }
                };

                const handleImageChange = (event) => {
                    const file = event.target.files?.[0];
                    if (!file) {
                        return;
                    }
                    if (file.size > 5 * 1024 * 1024) {
                        showError('Ukuran foto maksimal 5MB.');
                        event.target.value = '';
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = () => {
                        imageBase64 = reader.result;
                        el.imagePreview.style.backgroundImage = `url('${reader.result}')`;
                        el.imageOverlay.classList.add('hidden');
                    };
                    reader.readAsDataURL(file);
                };

                el.minus.addEventListener('click', () => {
                    const value = Math.max(0, Number(el.stock.value || 0) - 1);
                    el.stock.value = value;
                });

                el.plus.addEventListener('click', () => {
                    const value = Number(el.stock.value || 0) + 1;
                    el.stock.value = value;
                });

                el.trackStock.addEventListener('change', () => {
                    const enabled = el.trackStock.checked;
                    el.stockSection.classList.toggle('opacity-50', !enabled);
                    el.stockSection.querySelectorAll('input, button').forEach((input) => {
                        input.disabled = !enabled;
                    });
                    if (!enabled) {
                        el.stock.value = '0';
                    }

                    updateInitialStockMode();
                });

                el.enableVariants.addEventListener('change', () => {
                    updateVariantEditorVisibility();
                    updateVariantHint();
                });

                el.addVariantLine.addEventListener('click', () => {
                    el.variantLineList.appendChild(buildVariantRow({}));
                    updateVariantHint();
                    renderVariantStockLines();
                });

                el.variantLineList.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('[data-variant-remove]');
                    if (!removeButton) {
                        return;
                    }

                    removeButton.closest('.rounded-xl')?.remove();
                    updateVariantHint();
                    renderVariantStockLines();
                });

                el.variantLineList.addEventListener('input', () => {
                    updateVariantHint();
                    renderVariantStockLines();
                });

                el.enableBom.addEventListener('change', () => {
                    updateBomEditorVisibility();
                });

                el.enableTiers.addEventListener('change', () => {
                    const enabled = el.enableTiers.checked;
                    el.tierList.classList.toggle('hidden', !enabled);
                    el.addTier.disabled = !enabled;
                    if (!enabled) {
                        el.tierList.innerHTML = '';
                    } else if (!el.tierList.children.length) {
                        addTier({});
                    }
                });

                el.skuGenerate.addEventListener('click', () => {
                    el.sku.value = generateSku();
                });
                el.image.addEventListener('change', handleImageChange);
                el.categoryQuickToggle.addEventListener('click', () => {
                    const shouldOpen = el.categoryQuickPanel.classList.contains('hidden');
                    setCategoryQuickStatus('Buat kategori baru tanpa keluar dari halaman ini.');
                    setCategoryQuickPanel(shouldOpen);
                });
                el.categoryQuickCancel.addEventListener('click', () => {
                    el.categoryQuickName.value = '';
                    setCategoryQuickStatus('Buat kategori baru tanpa keluar dari halaman ini.');
                    setCategoryQuickPanel(false);
                });
                el.categoryQuickSave.addEventListener('click', createCategoryQuick);
                el.categoryQuickName.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        createCategoryQuick();
                    }
                });

                el.advancedToggle.addEventListener('click', () => {
                    const isOpen = !el.advancedOptions.classList.contains('hidden');
                    el.advancedOptions.classList.toggle('hidden');
                    el.advancedToggle.setAttribute('aria-expanded', String(!isOpen));
                    el.advancedIcon.textContent = isOpen ? 'chevron_right' : 'expand_more';
                });

                el.addTier.addEventListener('click', () => {
                    addTier({});
                });

                el.addBomLine.addEventListener('click', async () => {
                    if (!rawMaterialProducts.length) {
                        showError('Belum ada produk raw material yang tersedia untuk BOM.');
                        return;
                    }

                    try {
                        await appendBomLine({});
                    } catch (error) {
                        showError(error.message || 'Gagal menambah line BOM.');
                    }
                });

                el.bomLineList.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('[data-bom-remove]');
                    if (!removeButton) {
                        return;
                    }

                    removeButton.closest('.rounded-xl')?.remove();
                    updateBomLineCountHint();
                });

                el.bomLineList.addEventListener('change', async (event) => {
                    const productSelect = event.target.closest('[data-bom-product]');
                    if (!productSelect) {
                        return;
                    }

                    const row = productSelect.closest('.rounded-xl');
                    await configureBomVariantField(row, productSelect.value || '', null);
                });

                el.tierList.addEventListener('click', (event) => {
                    const remove = event.target.closest('[data-tier-remove]');
                    if (remove) {
                        remove.closest('div')?.remove();
                    }
                });

                el.save.addEventListener('click', submit);

                ensureSku();

                Promise.all([
                    loadCategories(),
                    loadRawMaterialProducts().then((products) => {
                        rawMaterialProducts = products;
                        updateBomEditorVisibility();
                    }),
                ]).catch(() => {
                    showError('Gagal memuat lookup produk raw material.');
                });
            })();
        </script>
@endsection
