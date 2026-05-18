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
    $productCreatePortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $isProductCreatePosPortal = $productCreatePortalPrefix === '/pos';
    $productCreateFrom = trim((string) request()->query('from', ''));
    $productCreateRequestedType = trim((string) request()->query('type', ''));
    if (! in_array($productCreateRequestedType, ['finished_good', 'raw_material', 'service'], true)) {
        $productCreateRequestedType = 'finished_good';
    }
    $productCreateBackUrl = $productCreatePortalPrefix.'/products';
    $productCreateListUrl = $productCreatePortalPrefix.'/products';
    $productCreateTitle = match ($productCreateRequestedType) {
        'raw_material' => 'Tambah Bahan Baku',
        'service' => 'Tambah Service',
        default => 'Tambah Produk Baru',
    };
    $productCreateCanManageRecipe = $productCreateTenant
        ? app(\App\Services\SubscriptionService::class)->canAccessFeature($productCreateTenant, 'product.recipe_management')
        : false;
    if ($productCreateFrom !== '') {
        if (str_starts_with($productCreateFrom, '/pos/')) {
            $productCreateBackUrl = $isProductCreatePosPortal
                ? $productCreateFrom
                : '/portal/switch/pos?next='.rawurlencode($productCreateFrom);
            $productCreateListUrl = $productCreatePortalPrefix.'/products?from='.rawurlencode($productCreateFrom);
        } elseif (str_starts_with($productCreateFrom, '/backoffice/products')) {
            $productCreateBackUrl = $isProductCreatePosPortal
                ? '/portal/switch/backoffice?next='.rawurlencode($productCreateFrom)
                : $productCreateFrom;
            $productCreateListUrl = $isProductCreatePosPortal
                ? $productCreatePortalPrefix.'/products?from='.rawurlencode($productCreateFrom)
                : $productCreateFrom;
        } elseif (str_starts_with($productCreateFrom, '/backoffice/')) {
            $productCreateBackUrl = $isProductCreatePosPortal
                ? '/portal/switch/backoffice?next='.rawurlencode($productCreateFrom)
                : $productCreateFrom;
            $productCreateListUrl = $productCreatePortalPrefix.'/products?from='.rawurlencode($productCreateFrom);
        }
    }
    $productCreateBackUrl = normalize_pos_home_url($productCreateBackUrl);
@endphp

@section('page_title', 'POS SaaS')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex flex-1 flex-col overflow-hidden p-0 app-bottom-safe-space')

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

        .product-create-shell input:not([type="checkbox"]):not([type="hidden"]):not([type="file"]),
        .product-create-shell select {
            min-height: 3rem;
            width: 100%;
            font-size: 1rem;
        }

        .product-create-shell button {
            white-space: nowrap;
        }
    </style>
@endpush

@section('header')
    <x-page-header :title="$productCreateTitle" :backUrl="$productCreateBackUrl" :showMenuButton="true" />
@endsection

@section('content')
        <div class="product-create-shell flex-1 overflow-y-auto pb-32 app-bottom-safe-space">
            <div class="sticky top-0 z-10 border-b border-slate-100 bg-white/95 px-4 py-3 backdrop-blur dark:border-slate-800 dark:bg-background-dark/95">
                <div class="grid grid-cols-4 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                    <button class="product-create-tab h-9 rounded-lg bg-primary px-2 text-xs font-bold text-white shadow-sm" data-product-tab="main" type="button" aria-selected="true">Utama</button>
                    <button class="product-create-tab h-9 rounded-lg px-2 text-xs font-bold text-slate-600 dark:text-slate-300" data-product-tab="stock" type="button" aria-selected="false">Stok</button>
                    <button class="product-create-tab h-9 rounded-lg px-2 text-xs font-bold text-slate-600 dark:text-slate-300" data-product-tab="production" type="button" aria-selected="false">Produksi</button>
                    <button class="product-create-tab h-9 rounded-lg px-2 text-xs font-bold text-slate-600 dark:text-slate-300" data-product-tab="advanced" type="button" aria-selected="false">Lainnya</button>
                </div>
            </div>

            <div id="product-tab-main" data-product-tab-panel="main">
            <div class="px-4 py-3">
                <div class="relative group">
                    <div id="image-preview" class="w-full h-28 bg-slate-50 dark:bg-slate-800/50 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl flex flex-col items-center justify-center overflow-hidden transition-all hover:border-primary/50" style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuBYuCMEoCG-olGE_fxLtmDzd2bF2w2IDFLmzF5xj54APUEJc1lpXNRBOXm7B5Z6F2AcxRHbUUZVDOhEPRnGL-dQEETshdoiEmQfsGLZv8EArgbo1ghz7_ijoAuhIyUMrt1kXLXkHz1tip9vdyNOkymgyvC1mYlbPjlbFqQIkgmqQfzZq64Vpq_K7BGS3P3ZzCi7WBjPH-rC3Lqu7bfqWH6Q5ad6MqENP020ISAC8ehZ9L7iAtlchEQr293m2bhO2L2pkNIb-C5L4w"); background-size: cover; background-position: center;'>
                        <div id="image-overlay" class="absolute inset-0 bg-black/20 flex items-center justify-center gap-3 backdrop-blur-[2px]">
                            <div class="bg-white/90 dark:bg-slate-900/90 p-2.5 rounded-full shadow-lg">
                                <span class="material-symbols-outlined text-primary text-2xl">add_a_photo</span>
                            </div>
                            <div>
                                <p class="text-white text-sm font-semibold">Foto Produk</p>
                                <p class="text-white/80 text-xs">Opsional, PNG/JPG</p>
                            </div>
                        </div>
                        <input id="product-image" class="absolute inset-0 opacity-0 cursor-pointer" type="file" accept="image/*" />
                    </div>
                </div>
            </div>

            <div class="px-4 space-y-4 app-bottom-safe-space">
                <div class="flex flex-col gap-1.5">
                    <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold px-0.5">Nama Produk</label>
                    <input id="product-name" class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 text-base placeholder:text-slate-400" placeholder="Contoh: Kopi Susu Gula Aren" type="text" />
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
                            <div class="space-y-3">
                                <input id="category-quick-name" class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 text-base placeholder:text-slate-400" placeholder="Contoh: Minuman Dingin" type="text" />
                                <button id="category-quick-save" class="h-12 w-full rounded-xl bg-primary px-4 text-sm font-semibold text-white shadow-lg shadow-primary/20" type="button">Simpan Kategori</button>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <p id="category-quick-status" class="text-xs text-slate-500 dark:text-slate-400">Buat kategori baru tanpa keluar dari halaman ini.</p>
                                <button id="category-quick-cancel" class="text-xs font-medium text-slate-500 dark:text-slate-400" type="button">Tutup</button>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Tipe Produk</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Tentukan apakah item ini dijual di kasir, dipakai sebagai bahan baku, atau berupa jasa.</p>
                        </div>
                        <div class="relative">
                            <select id="product-type" class="select-no-native-arrow w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 pr-10 text-base">
                                <option value="finished_good">Barang Jual</option>
                                <option value="raw_material">Bahan Baku</option>
                                <option value="service">Jasa / Service</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                        </div>
                        <div id="product-type-note" class="rounded-xl bg-slate-50 dark:bg-slate-800/70 px-3 py-3 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                            Barang jual tampil di katalog kasir. Anda bisa menambahkan BOM jika produk ini dibuat dari bahan baku.
                        </div>
                    </div>
                    <div id="raw-material-meta-section" class="hidden rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Metadata Bahan Baku</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Satuan bahan dipilih dari daftar agar konsisten. Jika belum ada, pilih satuan lain lalu isi manual.</p>
                        </div>
                        <div class="flex flex-col gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold px-0.5">Satuan Bahan</label>
                                <div class="relative">
                                    <select id="raw-material-unit-select" class="select-no-native-arrow w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 pr-10 text-base"></select>
                                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                                </div>
                            </div>
                            <div id="raw-material-unit-custom-wrap" class="hidden flex flex-col gap-1.5">
                                <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold px-0.5">Satuan Baru</label>
                                <input id="raw-material-unit-custom" class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 text-base placeholder:text-slate-400" placeholder="Contoh: pack, dus, bungkus" type="text" />
                            </div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold px-0.5">Harga Jual (Eceran)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">Rp</span>
                                <input id="product-price" class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 pl-10 pr-4 text-base placeholder:text-slate-400" placeholder="0" type="number" />
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold px-0.5">HPP / Modal</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">Rp</span>
                                <input id="product-hpp" class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 pl-10 pr-4 text-base placeholder:text-slate-400" placeholder="0" type="number" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="product-tab-stock" data-product-tab-panel="stock" class="hidden px-4 py-4 space-y-4 app-bottom-safe-space">
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
                    <div class="flex items-center justify-between rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Status Produk</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Produk aktif akan tampil di alur operasional dan bisa masuk checklist batch.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input id="is-active" type="checkbox" class="sr-only peer" checked />
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
                            <div class="space-y-3">
                                <p id="variant-editor-hint" class="text-xs text-slate-500 dark:text-slate-400">Belum ada variant.</p>
                                <button id="add-variant-line" class="h-11 w-full rounded-xl bg-primary/10 px-4 text-sm font-semibold text-primary transition-colors hover:bg-primary/20" type="button">Tambah Variant</button>
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
            </div>

            <div id="product-tab-production" data-product-tab-panel="production" class="hidden px-4 py-4 space-y-4 app-bottom-safe-space">
                    <div id="production-readiness" class="hidden rounded-xl border border-teal-200 bg-teal-50 p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-teal-900">Checklist Batch Produksi</p>
                                <p id="production-readiness-message" class="text-xs text-teal-700">Memeriksa kesiapan batch secara live.</p>
                            </div>
                            <span id="production-readiness-badge" class="rounded-full bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-teal-700 ring-1 ring-teal-200">Menunggu</span>
                        </div>
                        <div id="production-readiness-summary" class="flex flex-wrap gap-2"></div>
                        <div id="production-readiness-checklist" class="space-y-2 text-xs text-teal-900"></div>
                        <p class="text-[11px] text-teal-700">Ubah tipe produk, stok, status aktif, atau line BOM untuk melihat kelayakan batch langsung.</p>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 {{ $productCreateCanManageRecipe ? 'hidden' : '' }}" id="bom-locked-notice">
                        Fitur BOM / recipe tersedia mulai paket Medium. Upgrade paket untuk mengaktifkan auto-consume bahan baku.
                    </div>
                    <div class="hidden rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 p-4 text-xs leading-relaxed text-slate-500 dark:text-slate-400" id="bom-unavailable-notice">
                        BOM / recipe hanya berlaku untuk barang jual. Bahan baku dan jasa tidak tampil sebagai output recipe di layar ini.
                    </div>
                    <div class="rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-4 {{ $productCreateCanManageRecipe ? '' : 'hidden' }}" id="bom-editor-card">
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
                            <label class="block space-y-1.5">
                                <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">Hasil Standar per 1 Resep</span>
                                <input id="recipe-output-qty" class="h-12 w-full rounded-xl border-slate-200 px-4 text-base text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" type="number" min="0.0001" step="0.0001" placeholder="Contoh: 10" />
                                <span class="block text-xs text-slate-500 dark:text-slate-400">Isi hasil jadi normal dari 1 resep. Angka ini dipakai untuk menghitung output harapan di batch produksi.</span>
                            </label>
                            <div class="space-y-3">
                                <p id="bom-editor-hint" class="text-xs text-slate-500 dark:text-slate-400">Belum ada line BOM.</p>
                                <button id="add-bom-line" class="h-11 w-full rounded-xl bg-primary/10 px-4 text-sm font-semibold text-primary transition-colors hover:bg-primary/20" type="button">Tambah Bahan Baku</button>
                            </div>
                            <div id="bom-line-list" class="space-y-3"></div>
                        </div>
                    </div>
            </div>

            <div id="product-tab-advanced" data-product-tab-panel="advanced" class="hidden px-4 py-4 space-y-4 app-bottom-safe-space">
                    <div class="flex flex-col gap-1.5 rounded-xl border border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between px-0.5">
                            <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold">SKU / Barcode</label>
                            <button id="sku-generate" class="text-xs font-semibold text-primary" type="button">Buat Otomatis</button>
                        </div>
                        <div class="flex items-center gap-2">
                            <input id="product-sku" class="min-w-0 flex-1 rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 text-base placeholder:text-slate-400" placeholder="Masukkan kode atau scan" type="text" />
                            <button class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors hover:bg-primary/20" type="button" aria-label="Scan barcode">
                                <span class="material-symbols-outlined">barcode_scanner</span>
                            </button>
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
                        <div class="space-y-3">
                            <span class="text-xs text-slate-400">Contoh: min 1 = ecer, min 25 = grosir.</span>
                            <button id="add-tier" class="h-11 w-full rounded-xl bg-primary/10 px-4 text-sm font-semibold text-primary transition-colors hover:bg-primary/20" type="button">Tambah Level Harga</button>
                        </div>
                        <div id="tier-list" class="space-y-3 hidden"></div>
                    </div>
                    <button id="advanced-toggle" class="flex items-center justify-between w-full py-2 group" type="button" aria-expanded="false">
                        <span class="text-slate-500 dark:text-slate-400 text-sm font-medium group-hover:text-primary transition-colors">Opsi Lanjutan (Pajak, Diskon, dll)</span>
                        <span id="advanced-icon" class="material-symbols-outlined text-slate-400 group-hover:text-primary transition-colors">chevron_right</span>
                    </button>
                    <div id="advanced-options" class="hidden rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-4">
                        <div class="space-y-1.5">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Pajak</label>
                            <div class="relative">
                                <input id="product-tax" class="h-12 w-full rounded-xl border-slate-200 bg-slate-50 px-4 pr-10 text-base text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" placeholder="0" type="number" />
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-slate-400">%</span>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Diskon</label>
                            <div class="relative">
                                <input id="product-discount" class="h-12 w-full rounded-xl border-slate-200 bg-slate-50 px-4 pr-10 text-base text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" placeholder="0" type="number" />
                                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-sm text-slate-400">%</span>
                            </div>
                        </div>
                        <p class="text-xs text-slate-400">Opsi lanjutan akan diterapkan setelah modul pajak dan diskon aktif.</p>
                    </div>
            </div>

            <div class="px-4 pt-2 app-bottom-safe-space">
                <p id="form-success" class="text-sm font-semibold hidden rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-700"></p>
                <p id="form-error" class="text-sm text-red-500 font-semibold hidden"></p>
            </div>
        </div>

        <div class="fixed bottom-0 left-1/2 w-full max-w-[430px] -translate-x-1/2 bg-white dark:bg-background-dark p-4 border-t border-slate-100 dark:border-slate-800 flex gap-3 app-bottom-safe-footer">
            <button id="cancel-product" class="flex-1 h-12 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" type="button">
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
                const productBackUrl = @json($productCreateBackUrl);
                const canManageRecipe = @json($productCreateCanManageRecipe);
                const initialProductType = @json($productCreateRequestedType);

                const el = {
                    name: document.getElementById('product-name'),
                    sku: document.getElementById('product-sku'),
                    skuGenerate: document.getElementById('sku-generate'),
                    productType: document.getElementById('product-type'),
                    productTypeNote: document.getElementById('product-type-note'),
                    rawMaterialMetaSection: document.getElementById('raw-material-meta-section'),
                    rawMaterialUnitSelect: document.getElementById('raw-material-unit-select'),
                    rawMaterialUnitCustomWrap: document.getElementById('raw-material-unit-custom-wrap'),
                    rawMaterialUnitCustom: document.getElementById('raw-material-unit-custom'),
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
                    isActive: document.getElementById('is-active'),
                    minus: document.getElementById('stock-minus'),
                    plus: document.getElementById('stock-plus'),
                    trackStock: document.getElementById('track-stock'),
                    stockSection: document.getElementById('stock-section'),
                    enableVariants: document.getElementById('enable-variants'),
                    variantEditor: document.getElementById('variant-editor'),
                    variantEditorCard: document.getElementById('variant-editor-card'),
                    variantEditorHint: document.getElementById('variant-editor-hint'),
                    variantLineList: document.getElementById('variant-line-list'),
                    addVariantLine: document.getElementById('add-variant-line'),
                    variantStockSection: document.getElementById('variant-stock-section'),
                    variantStockHint: document.getElementById('variant-stock-hint'),
                    variantStockLineList: document.getElementById('variant-stock-line-list'),
                    productionReadiness: document.getElementById('production-readiness'),
                    productionReadinessMessage: document.getElementById('production-readiness-message'),
                    productionReadinessBadge: document.getElementById('production-readiness-badge'),
                    productionReadinessSummary: document.getElementById('production-readiness-summary'),
                    productionReadinessChecklist: document.getElementById('production-readiness-checklist'),
                    bomCard: document.getElementById('bom-editor-card'),
                    bomLockedNotice: document.getElementById('bom-locked-notice'),
                    bomUnavailableNotice: document.getElementById('bom-unavailable-notice'),
                    enableBom: document.getElementById('enable-bom'),
                    bomEditor: document.getElementById('bom-editor'),
                    recipeOutputQty: document.getElementById('recipe-output-qty'),
                    bomHint: document.getElementById('bom-editor-hint'),
                    bomLineList: document.getElementById('bom-line-list'),
                    addBomLine: document.getElementById('add-bom-line'),
                    cancel: document.getElementById('cancel-product'),
                    save: document.getElementById('save-product'),
                    success: document.getElementById('form-success'),
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
                    tabButtons: Array.from(document.querySelectorAll('[data-product-tab]')),
                    tabPanels: Array.from(document.querySelectorAll('[data-product-tab-panel]')),
                };

                let imageBase64 = null;
                let rawMaterialProducts = [];
                const productDetailCache = new Map();
                const successFlashKey = 'kalakasir:product-create-success';
                const rawMaterialUnitOptions = ['gram', 'kg', 'mg', 'liter', 'ml', 'pcs', 'box', 'pack', 'roll', 'meter', 'cm', 'dozen', 'lusin', 'sachet', 'bungkus'];

                const getSelectedProductType = () => String(el.productType?.value || 'finished_good');

                const openProductTab = (targetTab = 'main') => {
                    const resolvedTab = el.tabPanels.some((panel) => panel.dataset.productTabPanel === targetTab)
                        ? targetTab
                        : 'main';

                    el.tabPanels.forEach((panel) => {
                        panel.classList.toggle('hidden', panel.dataset.productTabPanel !== resolvedTab);
                    });

                    el.tabButtons.forEach((button) => {
                        const isActive = button.dataset.productTab === resolvedTab;
                        button.setAttribute('aria-selected', String(isActive));
                        button.classList.toggle('bg-primary', isActive);
                        button.classList.toggle('text-white', isActive);
                        button.classList.toggle('shadow-sm', isActive);
                        button.classList.toggle('text-slate-600', !isActive);
                        button.classList.toggle('dark:text-slate-300', !isActive);
                    });
                };

                const resolveErrorTab = (message) => {
                    const normalized = String(message || '').toLowerCase();

                    if (normalized.includes('bom') || normalized.includes('recipe') || normalized.includes('resep')) {
                        return 'production';
                    }

                    if (normalized.includes('variant') || normalized.includes('stok')) {
                        return 'stock';
                    }

                    if (normalized.includes('sku') || normalized.includes('barcode')) {
                        return 'advanced';
                    }

                    return 'main';
                };

                const setToggleDisabled = (input, disabled) => {
                    if (!input) {
                        return;
                    }

                    input.disabled = disabled;
                    const wrapper = input.closest('label');
                    if (wrapper) {
                        wrapper.classList.toggle('opacity-50', disabled);
                        wrapper.classList.toggle('cursor-not-allowed', disabled);
                    }
                };

                const updateProductTypeNote = () => {
                    const productType = getSelectedProductType();

                    if (productType === 'raw_material') {
                        el.productTypeNote.textContent = 'Bahan baku dipakai untuk recipe atau batch produksi. Item ini tidak akan tampil di katalog kasir.';
                        return;
                    }

                    if (productType === 'service') {
                        el.productTypeNote.textContent = 'Jasa atau service tidak memakai stok dan tidak bisa dijadikan bahan baku recipe.';
                        return;
                    }

                    el.productTypeNote.textContent = 'Barang jual tampil di katalog kasir. Anda bisa menambahkan BOM jika produk ini dibuat dari bahan baku.';
                };

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
                    openProductTab(resolveErrorTab(message));
                };

                const showSuccess = (message) => {
                    if (!el.success) {
                        return;
                    }

                    if (!message) {
                        el.success.classList.add('hidden');
                        el.success.textContent = '';
                        return;
                    }

                    el.success.textContent = message;
                    el.success.classList.remove('hidden');

                    window.setTimeout(() => {
                        el.success.classList.add('hidden');
                        el.success.textContent = '';
                    }, 3500);
                };

                const invalidFieldClasses = ['border-red-400', 'ring-2', 'ring-red-100', 'dark:ring-red-900/40'];

                const clearFieldInvalidState = () => {
                    [
                        el.name,
                        el.price,
                        el.rawMaterialUnitSelect,
                        el.rawMaterialUnitCustom,
                        el.recipeOutputQty,
                        el.addVariantLine,
                        el.addBomLine,
                    ].forEach((field) => field?.classList.remove(...invalidFieldClasses));
                };

                const focusInvalidField = (field, tab = 'main') => {
                    openProductTab(tab);

                    if (!field) {
                        return;
                    }

                    field.classList.add(...invalidFieldClasses);
                    window.setTimeout(() => {
                        field.scrollIntoView({ block: 'center', behavior: 'smooth' });
                        field.focus?.({ preventScroll: true });
                    }, 80);
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

                const renderRawMaterialUnits = (selectedUnit = '') => {
                    el.rawMaterialUnitSelect.innerHTML = '';

                    const normalizedSelected = String(selectedUnit || '').trim().toLowerCase();
                    const hasKnownUnit = rawMaterialUnitOptions.includes(normalizedSelected);

                    rawMaterialUnitOptions.forEach((unit) => {
                        const option = document.createElement('option');
                        option.value = unit;
                        option.textContent = unit;
                        el.rawMaterialUnitSelect.appendChild(option);
                    });

                    const customOption = document.createElement('option');
                    customOption.value = '__custom__';
                    customOption.textContent = 'Satuan lain...';
                    el.rawMaterialUnitSelect.appendChild(customOption);

                    if (hasKnownUnit) {
                        el.rawMaterialUnitSelect.value = normalizedSelected;
                        el.rawMaterialUnitCustomWrap.classList.add('hidden');
                        el.rawMaterialUnitCustom.value = '';
                        return;
                    }

                    el.rawMaterialUnitSelect.value = '__custom__';
                    el.rawMaterialUnitCustomWrap.classList.remove('hidden');
                    el.rawMaterialUnitCustom.value = selectedUnit || '';
                };

                const resolveRawMaterialUnit = () => {
                    const selectedUnit = String(el.rawMaterialUnitSelect.value || '').trim();
                    if (selectedUnit === '__custom__') {
                        return String(el.rawMaterialUnitCustom.value || '').trim();
                    }

                    return selectedUnit;
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
                        .filter((product) => {
                            const mode = String(product.product_mode || '').toLowerCase();
                            const itemType = String(product.item_type || '').toLowerCase();

                            // Keep backward compatibility while matching backend BOM contract.
                            if (mode === 'add_on' && itemType !== 'service') {
                                return true;
                            }

                            return itemType === 'raw_material';
                        })
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
                    row.className = 'rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 p-3.5 space-y-3.5';
                    row.innerHTML = `
                        <input type="hidden" data-variant-id value="${variant.id || generateUuid()}" />
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Variant Produk</p>
                            <button class="text-xs font-semibold text-rose-500" type="button" data-variant-remove>Hapus</button>
                        </div>
                        <div class="space-y-3">
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-slate-500">SKU Variant</label>
                                <input class="h-12 w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white px-4 text-base text-slate-900 dark:bg-slate-900 dark:text-slate-100" data-variant-sku type="text" value="${variant.sku || ''}" placeholder="Contoh: MERAH-M" />
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-slate-500">Nama Variant</label>
                                <input class="h-12 w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white px-4 text-base text-slate-900 dark:bg-slate-900 dark:text-slate-100" data-variant-name type="text" value="${variant.name || ''}" placeholder="Contoh: Merah / Medium" />
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

                const syncProductTypeState = () => {
                    const productType = getSelectedProductType();
                    const isRawMaterial = productType === 'raw_material';
                    const isService = productType === 'service';
                    const isFinishedGood = productType === 'finished_good';

                    updateProductTypeNote();
                    el.rawMaterialMetaSection.classList.toggle('hidden', !isRawMaterial);

                    setToggleDisabled(el.trackStock, isService);
                    if (isService) {
                        el.trackStock.checked = false;
                    }

                    setToggleDisabled(el.enableVariants, isService);
                    el.variantEditorCard.classList.toggle('opacity-50', isService);
                    if (isService) {
                        el.enableVariants.checked = false;
                    }

                    if (isRawMaterial || isService) {
                        el.enableBom.checked = false;
                        el.bomLineList.innerHTML = '';
                    }

                    setToggleDisabled(el.enableBom, !isFinishedGood || !canManageRecipe);

                    updateVariantEditorVisibility();
                    updateVariantHint();
                    updateInitialStockMode();
                    updateBomEditorVisibility();
                    refreshProductionReadiness();
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
                    row.className = 'rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 p-3.5 space-y-3';
                    row.innerHTML = `
                        <input type="hidden" data-stock-variant-id value="${variant.id}" />
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">${variant.name || 'Variant'}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">${variant.sku || 'SKU variant belum diisi'}</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[11px] font-semibold text-slate-500">Qty</label>
                            <input class="h-12 w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white px-4 text-base text-slate-900 dark:bg-slate-900 dark:text-slate-100" data-stock-qty type="number" min="0" step="0.0001" value="${currentValue.qty ?? ''}" placeholder="0" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[11px] font-semibold text-slate-500">HPP per unit</label>
                            <input class="h-12 w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white px-4 text-base text-slate-900 dark:bg-slate-900 dark:text-slate-100" data-stock-unit-cost type="number" min="0" step="0.01" value="${currentValue.unit_cost ?? ''}" placeholder="Kosongkan untuk HPP default" />
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
                    row.className = 'rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 p-3.5 space-y-3.5';
                    row.innerHTML = `
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Line BOM</p>
                            <button class="text-xs font-semibold text-rose-500" type="button" data-bom-remove>Hapus</button>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-500">Raw Material</label>
                            <select class="h-12 w-full rounded-xl border-slate-200 bg-white px-4 text-base text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" data-bom-product>
                                ${bomProductOptionsHtml(line.product_id || '')}
                            </select>
                        </div>
                        <div class="space-y-1.5 hidden" data-bom-variant-wrap>
                            <label class="text-xs font-semibold text-slate-500">Variant Raw Material</label>
                            <select class="h-12 w-full rounded-xl border-slate-200 bg-white px-4 text-base text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" data-bom-variant>
                                <option value="">Pilih variant</option>
                            </select>
                            <p class="text-[11px] text-slate-400" data-bom-variant-hint></p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-500">Qty per 1 resep</label>
                            <input class="h-12 w-full rounded-xl border-slate-200 bg-white px-4 text-base text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" data-bom-qty type="number" min="0.0001" step="0.0001" value="${line.qty ?? ''}" placeholder="Contoh: 2" />
                        </div>
                    `;

                    return row;
                };

                const updateBomEditorVisibility = () => {
                    const productType = getSelectedProductType();

                    if (productType !== 'finished_good') {
                        el.bomCard?.classList.add('hidden');
                        el.bomLockedNotice?.classList.add('hidden');
                        el.bomUnavailableNotice?.classList.remove('hidden');
                        el.enableBom.checked = false;
                        setBomHint(productType === 'raw_material'
                            ? 'Bahan baku dipakai sebagai komponen recipe, bukan output recipe.'
                            : 'Produk service tidak mendukung BOM atau auto-consume.');
                        refreshProductionReadiness();
                        return;
                    }

                    el.bomUnavailableNotice?.classList.add('hidden');

                    if (!canManageRecipe) {
                        el.bomCard?.classList.add('hidden');
                        el.bomLockedNotice?.classList.remove('hidden');
                        el.enableBom.checked = false;
                        el.bomLineList.innerHTML = '';
                        setBomHint('Fitur BOM belum tersedia di paket ini.');
                        refreshProductionReadiness();
                        return;
                    }

                    const enabled = el.enableBom.checked;
                    el.bomEditor.classList.toggle('hidden', !enabled);
                    if (!enabled) {
                        el.bomLineList.innerHTML = '';
                        setBomHint('Belum ada line BOM.');
                        refreshProductionReadiness();
                        return;
                    }

                    el.trackStock.checked = true;
                    updateInitialStockMode();
                    refreshProductionReadiness();

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

                const resolveBomProductDraft = (productId) => {
                    const targetId = String(productId || '').trim();

                    if (!targetId) {
                        return null;
                    }

                    const listMatch = rawMaterialProducts.find((product) => String(product.id || '') === targetId);
                    if (listMatch) {
                        return listMatch;
                    }

                    return productDetailCache.get(targetId) || null;
                };

                const inspectBomItems = () => {
                    return Array.from(el.bomLineList.children).map((row, index) => {
                        const productId = row.querySelector('[data-bom-product]')?.value || '';
                        const product = resolveBomProductDraft(productId);
                        const variantWrap = row.querySelector('[data-bom-variant-wrap]');
                        const variantRequired = Boolean(variantWrap && !variantWrap.classList.contains('hidden'));
                        const variantId = row.querySelector('[data-bom-variant]')?.value || '';
                        const qty = Number(row.querySelector('[data-bom-qty]')?.value || 0);

                        return {
                            product_id: productId || null,
                            product_name: product?.name || `Line ${index + 1}`,
                            product_item_type: String(product?.item_type || '').trim(),
                            product_mode: String(product?.product_mode || '').trim(),
                            product_variant_id: variantRequired ? variantId || null : null,
                            variant_required: variantRequired,
                            qty,
                        };
                    });
                };

                const buildProductionDraft = () => {
                    const productType = getSelectedProductType();
                    const useBom = Boolean(canManageRecipe && productType === 'finished_good' && el.enableBom.checked);
                    const trackStock = productType === 'service' ? false : Boolean(el.trackStock.checked || useBom);

                    return {
                        is_active: Boolean(el.isActive?.checked),
                        item_type: productType,
                        product_mode: productType === 'raw_material'
                            ? 'add_on'
                            : (productType === 'service'
                                ? 'default'
                                : (useBom ? 'bom' : 'default')),
                        production_mode: productType === 'service' || productType === 'raw_material'
                            ? 'none'
                            : (useBom ? 'batch_production' : 'none'),
                        track_stock: trackStock,
                        recipe_output_qty: useBom ? Number(el.recipeOutputQty?.value || 0) : 0,
                        bom_items: useBom ? inspectBomItems() : [],
                    };
                };

                const shouldShowProductionReadiness = () => {
                    return Boolean(
                        canManageRecipe
                        && getSelectedProductType() === 'finished_good'
                        && el.enableBom?.checked
                    );
                };

                const renderProductionReadiness = () => {
                    if (!el.productionReadiness || !el.productionReadinessMessage || !el.productionReadinessBadge || !el.productionReadinessChecklist || !el.productionReadinessSummary) {
                        return;
                    }

                    const visible = shouldShowProductionReadiness();
                    el.productionReadiness.classList.toggle('hidden', !visible);

                    if (!visible) {
                        return;
                    }

                    const eligibility = window.PosUi.getProductionBatchEligibility(buildProductionDraft());
                    const isEligible = Boolean(eligibility?.eligible);
                    const checks = Array.isArray(eligibility?.checks) ? eligibility.checks : [];
                    const summary = eligibility?.summary || {};

                    el.productionReadiness.className = isEligible
                        ? 'rounded-xl border border-emerald-200 bg-emerald-50 p-4 space-y-3'
                        : 'rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-3';
                    el.productionReadinessBadge.className = isEligible
                        ? 'rounded-full bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-700 ring-1 ring-emerald-200'
                        : 'rounded-full bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-amber-700 ring-1 ring-amber-200';
                    el.productionReadinessBadge.textContent = isEligible ? 'Siap diproduksi' : 'Belum siap';
                    el.productionReadinessMessage.textContent = summary.message || (isEligible
                        ? 'Produk ini sudah memenuhi syarat batch produksi.'
                        : 'Lengkapi checklist di bawah untuk membuat produk siap batch produksi.');

                    const summaryPills = [
                        { label: summary.is_active ? 'Status: aktif' : 'Status: nonaktif', tone: summary.is_active ? 'emerald' : 'amber' },
                        { label: `Mode produk: ${summary.product_mode || 'default'}`, tone: summary.product_mode === 'bom' ? 'emerald' : 'slate' },
                        { label: `Mode produksi: ${summary.production_mode || 'none'}`, tone: summary.production_mode === 'batch_production' ? 'emerald' : 'slate' },
                        { label: `Track stock: ${summary.track_stock ? 'on' : 'off'}`, tone: summary.track_stock ? 'emerald' : 'slate' },
                        { label: `Hasil/resep: ${Number(summary.recipe_output_qty || 0) > 0 ? summary.recipe_output_qty : '-'}`, tone: Number(summary.recipe_output_qty || 0) > 0 ? 'emerald' : 'slate' },
                    ];

                    el.productionReadinessSummary.innerHTML = summaryPills.map((pill) => {
                        const toneClasses = {
                            emerald: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                            amber: 'bg-amber-100 text-amber-800 ring-amber-200',
                            slate: 'bg-white text-slate-600 ring-slate-200',
                        };

                        return `<span class="rounded-full px-3 py-1 text-[11px] font-semibold ring-1 ${toneClasses[pill.tone] || toneClasses.slate}">${pill.label}</span>`;
                    }).join('');

                    el.productionReadinessChecklist.innerHTML = checks.map((check) => {
                        const passed = Boolean(check.passed);
                        return `
                            <div class="flex items-start gap-2 rounded-xl bg-white px-3 py-2 ring-1 ${passed ? 'ring-emerald-200' : 'ring-amber-200'}">
                                <span class="mt-0.5 material-symbols-outlined text-[16px] ${passed ? 'text-emerald-600' : 'text-amber-600'}">${passed ? 'check_circle' : 'radio_button_unchecked'}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] ${passed ? 'text-emerald-700' : 'text-amber-700'}">${check.label || 'Checklist'}</p>
                                    <p class="text-[11px] ${passed ? 'text-emerald-700' : 'text-amber-800'}">${passed ? 'Sudah sesuai.' : (check.reason || 'Belum sesuai.')}</p>
                                </div>
                            </div>
                        `;
                    }).join('');
                };

                const refreshProductionReadiness = () => {
                    renderProductionReadiness();
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

                    const seenLineKeys = new Set();

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

                        const lineKey = `${productId}|${variantRequired ? variantId : '__no_variant__'}`;
                        if (seenLineKeys.has(lineKey)) {
                            throw new Error(`Line BOM #${index + 1}: bahan baku duplikat. Gabungkan qty pada satu line.`);
                        }
                        seenLineKeys.add(lineKey);

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
                    row.className = 'rounded-xl border border-slate-100 bg-slate-50 p-3 space-y-3 dark:border-slate-800 dark:bg-slate-800/60';
                    row.dataset.tierRow = 'true';
                    row.innerHTML = `
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Level Harga</p>
                            <button class="text-xs font-semibold text-rose-500" type="button" data-tier-remove>Hapus</button>
                        </div>
                        <input class="h-12 w-full rounded-xl border-slate-200 bg-white px-4 text-base text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" placeholder="Nama level, contoh: Grosir" value="${tier.label || ''}" data-tier-label />
                        <input class="h-12 w-full rounded-xl border-slate-200 bg-white px-4 text-base text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" placeholder="Minimal qty" type="number" value="${tier.min_qty ?? ''}" data-tier-min />
                        <input class="h-12 w-full rounded-xl border-slate-200 bg-white px-4 text-base text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" placeholder="Harga" type="number" value="${tier.price ?? ''}" data-tier-price />
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
                    el.productType.value = initialProductType || 'finished_good';
                    el.category.value = '';
                    el.categoryQuickName.value = '';
                    setCategoryQuickStatus('Buat kategori baru tanpa keluar dari halaman ini.');
                    setCategoryQuickPanel(false);
                    el.price.value = '';
                    el.hpp.value = '';
                    renderRawMaterialUnits('');
                    el.stock.value = '0';
                    el.isActive.checked = true;
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
                    el.recipeOutputQty.value = '';
                    el.bomLineList.innerHTML = '';
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
                    syncProductTypeState();
                    refreshProductionReadiness();
                    openProductTab('main');
                    el.name.focus();
                };

                const submit = async () => {
                    showError('');
                    clearFieldInvalidState();

                    ensureSku();

                    const payload = {
                        name: el.name.value.trim(),
                        sku: el.sku.value.trim(),
                        category_id: el.category.value || null,
                        price: Number(el.price.value || 0),
                        hpp_average: Number(el.hpp.value || 0),
                        unit: null,
                        supplier_id: null,
                        initial_stock: el.trackStock.checked && !el.enableVariants.checked ? Number(el.stock.value || 0) : 0,
                        image_base64: imageBase64,
                        track_stock: el.trackStock.checked,
                        is_active: el.isActive.checked,
                        price_tiers: el.enableTiers.checked ? collectTiers() : [],
                    };
                    const productType = getSelectedProductType();

                    if (!payload.name) {
                        showError('Nama produk wajib diisi di tab Utama.');
                        focusInvalidField(el.name, 'main');
                        return;
                    }

                    if (productType !== 'raw_material' && payload.price <= 0) {
                        showError('Harga jual wajib diisi lebih dari 0 di tab Utama.');
                        focusInvalidField(el.price, 'main');
                        return;
                    }

                    if (productType === 'raw_material') {
                        payload.price = Math.max(0, Number(payload.price || 0));
                    }

                    if (el.enableVariants.checked) {
                        try {
                            payload.variants = collectVariants();
                            payload.initial_stock_lines = el.trackStock.checked
                                ? collectVariantStockLines(payload.variants)
                                : [];
                        } catch (error) {
                            showError(error?.message || 'Lengkapi data variant terlebih dahulu.');
                            focusInvalidField(el.addVariantLine, 'stock');
                            return;
                        }
                    }

                    if (productType === 'raw_material') {
                        payload.unit = resolveRawMaterialUnit();
                        payload.recipe_output_qty = null;

                        if (!payload.unit) {
                            showError('Satuan bahan baku wajib diisi.');
                            focusInvalidField(el.rawMaterialUnitSelect.value === '__custom__' ? el.rawMaterialUnitCustom : el.rawMaterialUnitSelect, 'main');
                            return;
                        }

                        payload.product_mode = 'add_on';
                        payload.item_type = 'raw_material';
                        payload.production_mode = 'none';
                        payload.bom_items = [];
                    } else if (productType === 'service') {
                        payload.recipe_output_qty = null;
                        payload.product_mode = 'default';
                        payload.item_type = 'service';
                        payload.production_mode = 'none';
                        payload.track_stock = false;
                        payload.initial_stock = 0;
                        payload.initial_stock_lines = [];
                        payload.variants = [];
                    } else if (canManageRecipe && el.enableBom.checked) {
                        payload.recipe_output_qty = Number(el.recipeOutputQty?.value || 0);
                        if (!(payload.recipe_output_qty > 0)) {
                            showError('Hasil standar per 1 resep wajib lebih dari 0 saat BOM aktif.');
                            focusInvalidField(el.recipeOutputQty, 'production');
                            return;
                        }
                        payload.track_stock = true;
                        payload.product_mode = 'bom';
                        payload.item_type = 'finished_good';
                        payload.production_mode = 'batch_production';
                        try {
                            payload.bom_items = collectBomItems();
                        } catch (error) {
                            showError(error?.message || 'Lengkapi line BOM terlebih dahulu.');
                            focusInvalidField(el.addBomLine, 'production');
                            return;
                        }
                    } else {
                        payload.recipe_output_qty = null;
                        payload.product_mode = 'default';
                        payload.item_type = 'finished_good';
                        payload.production_mode = 'none';
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
                            showSuccess('Produk berhasil disimpan.');
                            resetProductForm();
                            return;
                        }

                        sessionStorage.setItem(successFlashKey, 'Produk berhasil disimpan.');

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
                    refreshProductionReadiness();
                });

                el.isActive.addEventListener('change', refreshProductionReadiness);

                el.productType.addEventListener('change', () => {
                    syncProductTypeState();
                });

                el.enableVariants.addEventListener('change', () => {
                    updateVariantEditorVisibility();
                    updateVariantHint();
                    refreshProductionReadiness();
                });

                el.addVariantLine.addEventListener('click', () => {
                    el.addVariantLine.classList.remove(...invalidFieldClasses);
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
                    refreshProductionReadiness();
                });

                el.variantLineList.addEventListener('input', () => {
                    updateVariantHint();
                    renderVariantStockLines();
                    refreshProductionReadiness();
                });

                el.enableBom.addEventListener('change', () => {
                    if (canManageRecipe && el.enableBom.checked) {
                        el.trackStock.checked = true;
                    }

                    updateBomEditorVisibility();
                    updateInitialStockMode();
                    refreshProductionReadiness();
                });
                el.recipeOutputQty?.addEventListener('input', refreshProductionReadiness);

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
                el.tabButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        openProductTab(button.dataset.productTab || 'main');
                    });
                });
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
                    el.addBomLine.classList.remove(...invalidFieldClasses);
                    if (!rawMaterialProducts.length) {
                        showError('Belum ada produk raw material yang tersedia untuk BOM.');
                        return;
                    }

                    try {
                        await appendBomLine({});
                        refreshProductionReadiness();
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
                    refreshProductionReadiness();
                });

                el.bomLineList.addEventListener('change', async (event) => {
                    const productSelect = event.target.closest('[data-bom-product]');
                    const variantSelect = event.target.closest('[data-bom-variant]');
                    const qtyInput = event.target.closest('[data-bom-qty]');

                    if (!productSelect && !variantSelect && !qtyInput) {
                        return;
                    }

                    const row = productSelect?.closest('.rounded-xl') || variantSelect?.closest('.rounded-xl') || qtyInput?.closest('.rounded-xl');

                    if (!productSelect) {
                        refreshProductionReadiness();
                        return;
                    }

                    await configureBomVariantField(row, productSelect.value || '', null);
                    refreshProductionReadiness();
                });

                el.bomLineList.addEventListener('input', () => {
                    refreshProductionReadiness();
                });

                el.tierList.addEventListener('click', (event) => {
                    const remove = event.target.closest('[data-tier-remove]');
                    if (remove) {
                        remove.closest('[data-tier-row]')?.remove();
                    }
                });

                [
                    el.name,
                    el.price,
                    el.rawMaterialUnitSelect,
                    el.rawMaterialUnitCustom,
                    el.recipeOutputQty,
                ].forEach((field) => {
                    field?.addEventListener('input', () => field.classList.remove(...invalidFieldClasses));
                    field?.addEventListener('change', () => field.classList.remove(...invalidFieldClasses));
                });

                el.cancel.addEventListener('click', () => {
                    window.location.href = productBackUrl;
                });
                el.save.addEventListener('click', submit);

                openProductTab('main');
                el.productType.value = initialProductType || 'finished_good';
                ensureSku();
                syncProductTypeState();
                renderRawMaterialUnits('');
                refreshProductionReadiness();

                el.rawMaterialUnitSelect.addEventListener('change', () => {
                    const isCustom = el.rawMaterialUnitSelect.value === '__custom__';
                    el.rawMaterialUnitCustomWrap.classList.toggle('hidden', !isCustom);
                    if (isCustom) {
                        el.rawMaterialUnitCustom.focus();
                        return;
                    }

                    el.rawMaterialUnitCustom.value = '';
                });

                Promise.all([
                    loadCategories(),
                    loadRawMaterialProducts().then((products) => {
                        rawMaterialProducts = products;
                        updateBomEditorVisibility();
                        refreshProductionReadiness();
                    }),
                ]).catch(() => {
                    showError('Gagal memuat lookup produk raw material.');
                });
            })();
        </script>
@endsection
