@extends('layouts.app')

@php
    $productDetailTenant = \App\Support\TenantContext::get();
    $productDetailUser = auth()->user();
    $productDetailRole = null;

    if (! $productDetailTenant && filled($productDetailUser?->tenant_id)) {
        $productDetailTenant = \App\Models\Tenant::query()->find($productDetailUser->tenant_id);
    }

    if ($productDetailTenant && $productDetailUser) {
        $productDetailRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $productDetailTenant->id, (string) $productDetailUser->id);

        if (! $productDetailRole && filled($productDetailUser->role ?? null)) {
            $productDetailRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $productDetailUser->role);
        }
    }

    abort_unless(in_array($productDetailRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManageProductDetail = in_array($productDetailRole, ['admin', 'owner', 'superadmin'], true);
    $canViewStockCardReport = $productDetailTenant
        ? app(\App\Services\SubscriptionService::class)->canAccessFeature($productDetailTenant, 'reports.inventory_stock_card')
        : false;
@endphp

@section('page_title', 'Detail Produk & Stok Lokasi')
@section('body_class', 'font-display text-slate-900')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white shadow-2xl')
@section('main_class', 'flex flex-1 flex-col overflow-hidden p-0 min-h-0')

@push('head')
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
@endpush

@php
    $productDetailPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $productCurrentPath = $currentPath ?? $productDetailPortalPrefix.'/products/'.((string) ($productId ?? request()->query('product_id', '')));
    $productBackUrl = $backUrl ?? $productDetailPortalPrefix.'/products';
    $resolvedProductId = (string) ($productId ?? request()->query('product_id', ''));
    $productEditUrl = $resolvedProductId !== ''
        ? $productDetailPortalPrefix.'/products/'.$resolvedProductId.'/edit?from='.rawurlencode($productCurrentPath)
        : null;
    $productDetailFrom = trim((string) request()->query('from', ''));

    if ($productDetailFrom !== '' && ! isset($backUrl)) {
        if (str_starts_with($productDetailFrom, $productDetailPortalPrefix.'/')) {
            $productBackUrl = $productDetailFrom;
        } elseif ($productDetailPortalPrefix === '/backoffice' && str_starts_with($productDetailFrom, '/pos/')) {
            $productBackUrl = '/portal/switch/pos?next='.rawurlencode($productDetailFrom);
        } elseif ($productDetailPortalPrefix === '/pos' && str_starts_with($productDetailFrom, '/backoffice/')) {
            $productBackUrl = '/portal/switch/backoffice?next='.rawurlencode($productDetailFrom);
        }
    }
    $productBackUrl = normalize_pos_home_url($productBackUrl);
@endphp

@section('header')
    <x-page-header title="Detail Produk" :backUrl="$productBackUrl">
        <x-slot name="actions">
            @if ($canManageProductDetail)
                <a class="p-2 text-primary" href="{{ $productEditUrl }}" aria-label="Edit produk" title="Edit produk">
                    <span class="material-symbols-outlined">edit</span>
                </a>
            @endif
        </x-slot>
    </x-page-header>
@endsection

@section('content')
        <div class="flex-1 min-h-0 overflow-y-auto pb-24 app-bottom-safe-space">
            <section class="p-4 bg-white">
                <div class="flex gap-4">
                    <div class="w-24 h-24 rounded-2xl bg-slate-100 overflow-hidden border border-slate-100 flex-shrink-0">
                            <img id="product-image" alt="Produk" class="w-full h-full object-cover" src="/images/placeholders/product.svg" />
                    </div>
                    <div class="flex flex-col justify-center">
                        <span id="product-meta" class="text-[10px] font-bold text-primary uppercase tracking-wider mb-1">Kategori • SKU: -</span>
                        <h2 id="product-name" class="text-xl font-bold text-slate-900 leading-tight">Nama Produk</h2>
                        <p id="product-price" class="text-lg font-bold text-slate-900 mt-1">Rp 0</p>
                    </div>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                        <p class="text-[11px] text-slate-500 font-medium mb-0.5">Total Stok</p>
                        <p class="text-lg font-bold text-slate-900"><span id="total-stock">0</span> <span id="total-stock-unit" class="text-xs font-medium text-slate-500">unit</span></p>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                        <p class="text-[11px] text-slate-500 font-medium mb-0.5">Status Produk</p>
                        <div class="flex items-center gap-1.5 mt-1">
                            <span id="status-dot" class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span id="status-label" class="text-sm font-bold text-emerald-600">Aktif</span>
                        </div>
                    </div>
                </div>
            </section>
            <section class="mt-2 bg-white">
                <div class="px-4 py-3 flex items-center justify-between border-b border-slate-50">
                    <h3 class="font-bold text-slate-800">Stok Berdasarkan Lokasi</h3>
                    <span id="location-count" class="text-[11px] font-bold text-slate-400 uppercase tracking-widest">0 LOKASI</span>
                </div>
                <div id="location-list" class="divide-y divide-slate-50"></div>
            </section>
            <section class="mt-4 px-4 pb-8">
                @if ($canViewStockCardReport)
                    <button id="stock-history-btn" class="w-full bg-white border-2 border-primary/10 text-primary py-4 px-4 rounded-2xl flex items-center justify-between active:bg-slate-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-primary">history</span>
                            <span class="font-bold">Riwayat Mutasi Stok</span>
                        </div>
                        <span class="material-symbols-outlined text-slate-400">chevron_right</span>
                    </button>
                @endif
                <div class="mt-4 p-4 rounded-2xl bg-slate-50 border border-slate-100">
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Informasi Tambahan</h4>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-500">Kategori</span>
                            <span id="info-category" class="text-sm font-medium text-slate-800">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-500">Satuan</span>
                            <span id="info-unit" class="text-sm font-medium text-slate-800">unit</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-500">Supplier Utama</span>
                            <span id="info-supplier" class="text-sm font-medium text-slate-800">-</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-slate-500">Batas Stok Minimum</span>
                            <span id="info-min-stock" class="text-sm font-medium text-slate-800 text-orange-500">-</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <footer class="p-4 bg-white border-t border-slate-100 flex gap-3 app-bottom-safe-footer">
            <button class="flex-1 py-4 bg-primary text-white font-bold rounded-2xl shadow-lg shadow-primary/20 active:scale-95 transition-transform flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">sync_alt</span>
                Transfer Stok
            </button>
            <button class="flex-1 py-4 bg-emerald-500 text-white font-bold rounded-2xl shadow-lg shadow-emerald-500/20 active:scale-95 transition-transform flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-xl">add_box</span>
                Penyesuaian
            </button>
        </footer>
        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const el = {
                    image: document.getElementById('product-image'),
                    meta: document.getElementById('product-meta'),
                    name: document.getElementById('product-name'),
                    price: document.getElementById('product-price'),
                    totalStock: document.getElementById('total-stock'),
                    totalStockUnit: document.getElementById('total-stock-unit'),
                    statusDot: document.getElementById('status-dot'),
                    statusLabel: document.getElementById('status-label'),
                    locationCount: document.getElementById('location-count'),
                    locationList: document.getElementById('location-list'),
                    infoCategory: document.getElementById('info-category'),
                    infoUnit: document.getElementById('info-unit'),
                    infoSupplier: document.getElementById('info-supplier'),
                    infoMinStock: document.getElementById('info-min-stock'),
                    stockHistoryBtn: document.getElementById('stock-history-btn'),
                };

                const productId = @json($resolvedProductId) || new URLSearchParams(window.location.search).get('product_id');
                const productDetailPortalPrefix = @json($productDetailPortalPrefix);

                @if ($canViewStockCardReport)
                    el.stockHistoryBtn?.addEventListener('click', () => {
                        const target = productId
                            ? `${productDetailPortalPrefix}/inventory/products/${encodeURIComponent(productId)}/stock-card?from=${encodeURIComponent(@json($productCurrentPath))}`
                            : `${productDetailPortalPrefix}/inventory/stock-card`;
                        window.location.href = target;
                    });
                @endif

                const loadProductDetail = async () => {
                    const response = await fetch(`/api/v1/products/${encodeURIComponent(productId)}`, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat data produk.');
                    }
                    return response.json();
                };

                const resolveUnitLabel = (value) => {
                    const unit = String(value || '').trim();
                    return unit !== '' ? unit : 'unit';
                };

                const renderLocations = (locations) => {
                    el.locationList.innerHTML = '';
                    if (!locations.length) {
                        el.locationList.innerHTML = '<div class="px-4 py-4 text-sm text-slate-400">Belum ada lokasi.</div>';
                        return;
                    }

                    locations.forEach((location) => {
                        const card = document.createElement('div');
                        card.className = 'px-4 py-4 flex items-center justify-between';
                        const status = location.qty <= 5 ? 'Stok Menipis' : 'Aman';
                        const statusClass = location.qty <= 5 ? 'text-orange-500' : 'text-emerald-600';
                        const statusDot = location.qty <= 5 ? 'bg-orange-50 text-orange-500' : 'bg-blue-50 text-primary';
                        card.innerHTML = `
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full ${statusDot} flex items-center justify-center">
                                    <span class="material-symbols-outlined">storefront</span>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-800">${location.name}</p>
                                    <p class="text-[11px] text-slate-500">Lokasi ID: ${location.id.slice(0, 6).toUpperCase()}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-slate-900">${location.qty} <span class="text-[11px] text-slate-400 font-normal">${resolveUnitLabel(location.unit)}</span></p>
                                <p class="text-[10px] font-bold ${statusClass} uppercase">${status}</p>
                            </div>
                        `;
                        el.locationList.appendChild(card);
                    });
                };

                const init = async () => {
                    try {
                        const data = await loadProductDetail();
                        const product = data.product || null;
                        if (!product) {
                            el.name.textContent = 'Produk tidak ditemukan';
                            return;
                        }

                        const unitLabel = resolveUnitLabel(product.unit);

                        const locations = (product.product_location_balances || []).map((location) => ({
                            ...location,
                            qty: Number(location.qty_on_hand || 0),
                            unit: unitLabel,
                        }));
                        const totalStock = locations.reduce((sum, item) => sum + (item.qty || 0), 0);

                        el.image.src = product.image_url || '/images/placeholders/product.svg';
                        el.meta.textContent = `${product.category_name || 'Kategori'} • SKU: ${product.sku || '-'}`;
                        el.name.textContent = product.name;
                        el.price.textContent = formatCurrency(product.price || 0);
                        el.totalStock.textContent = Math.max(0, Math.round(totalStock));
                        el.totalStockUnit.textContent = unitLabel;
                        el.locationCount.textContent = `${locations.length} LOKASI`;
                        el.infoCategory.textContent = product.category_name || '-';
                        el.infoUnit.textContent = unitLabel;
                        el.infoSupplier.textContent = product.supplier_name || '-';
                        el.infoMinStock.textContent = product.min_stock ? `${product.min_stock} ${unitLabel}` : '-';

                        renderLocations(locations);
                    } catch (error) {
                        el.name.textContent = error.message;
                    }
                };

                init();
            })();
        </script>
    @endsection
