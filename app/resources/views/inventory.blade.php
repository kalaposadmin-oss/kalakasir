@extends('layouts.app')

@php
    $inventoryTenant = \App\Support\TenantContext::get();
    $inventoryUser = auth()->user();
    $inventoryRole = null;

    if (! $inventoryTenant && filled($inventoryUser?->tenant_id)) {
        $inventoryTenant = \App\Models\Tenant::query()->find($inventoryUser->tenant_id);
    }

    if ($inventoryTenant && $inventoryUser) {
        $inventoryRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $inventoryTenant->id, (string) $inventoryUser->id);

        if (! $inventoryRole && filled($inventoryUser->role ?? null)) {
            $inventoryRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $inventoryUser->role);
        }
    }

    abort_unless(in_array($inventoryRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $inventoryPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $inventoryCanOpenStockCard = $inventoryTenant
        && app(\App\Services\SubscriptionService::class)->canAccessFeature($inventoryTenant, 'reports.inventory_stock_card');
    $inventoryFrom = trim((string) request()->query('from', ''));
    $inventoryBackUrl = $inventoryPortalPrefix === '/pos' ? pos_default_url() : '/backoffice/dashboard';
    if ($inventoryFrom !== '') {
        if (str_starts_with($inventoryFrom, $inventoryPortalPrefix.'/')) {
            $inventoryBackUrl = $inventoryFrom;
        } elseif ($inventoryPortalPrefix === '/backoffice' && str_starts_with($inventoryFrom, '/pos/')) {
            $inventoryBackUrl = '/portal/switch/pos?next='.rawurlencode($inventoryFrom);
        } elseif ($inventoryPortalPrefix === '/pos' && str_starts_with($inventoryFrom, '/backoffice/')) {
            $inventoryBackUrl = '/portal/switch/backoffice?next='.rawurlencode($inventoryFrom);
        }
    }
    $inventoryBackUrl = normalize_pos_home_url($inventoryBackUrl);
@endphp

@section('page_title', 'Daftar Stok Inventaris')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'relative flex-1 overflow-y-auto pb-24 app-bottom-safe-space')

@section('header')
    <x-page-header title="Stok Inventaris" :breadcrumbs="['Inventaris']" :backUrl="$inventoryBackUrl" :showMenuButton="true">
        <x-slot:actions>
            <button class="flex cursor-pointer items-center justify-center rounded-lg h-10 bg-transparent text-slate-900 dark:text-slate-100">
                <span class="material-symbols-outlined text-2xl">notifications</span>
            </button>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <div class="space-y-3 bg-[#f8f9ff] px-4 py-4">
        <div class="flex gap-2">
            <label class="flex flex-1 min-w-40 h-12">
                <div class="flex w-full flex-1 items-stretch rounded-xl h-full bg-slate-100 dark:bg-slate-800/50">
                    <div class="text-slate-500 flex items-center justify-center pl-4">
                        <span class="material-symbols-outlined text-xl">search</span>
                    </div>
                    <input id="search-input" class="form-input flex w-full min-w-0 flex-1 border-none bg-transparent focus:ring-0 h-full placeholder:text-slate-500 px-3 text-base font-normal" placeholder="Cari produk..." value="" />
                    <div class="text-primary flex items-center justify-center pr-4">
                        <span class="material-symbols-outlined text-xl">barcode_scanner</span>
                    </div>
                </div>
            </label>
            <button class="flex items-center justify-center w-12 h-12 rounded-xl bg-slate-100 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400">
                <span class="material-symbols-outlined text-xl">tune</span>
            </button>
        </div>
        <div class="flex items-center gap-3 rounded-2xl border border-slate-100 bg-slate-50 px-3 py-3 dark:border-slate-800 dark:bg-slate-900/50">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Lokasi</p>
                <select id="location-filter" class="mt-1 w-full border-none bg-transparent p-0 text-sm font-semibold text-slate-900 focus:ring-0 dark:text-slate-100">
                    <option value="">Semua Lokasi</option>
                </select>
            </div>
            <div class="shrink-0 rounded-full bg-white px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-primary shadow-sm dark:bg-slate-800">
                <span id="inventory-location-label">Semua Lokasi</span>
            </div>
        </div>
        <div class="flex border-b border-slate-100 dark:border-slate-800 gap-6">
            <a class="flex flex-col items-center justify-center border-b-2 border-primary text-primary pb-3" href="#">
                <p class="text-sm font-bold">Semua</p>
            </a>
            <a class="flex flex-col items-center justify-center border-b-2 border-transparent text-slate-500 pb-3" href="#">
                <p class="text-sm font-medium">Stok Rendah</p>
            </a>
            <a class="flex flex-col items-center justify-center border-b-2 border-transparent text-slate-500 pb-3" href="#">
                <p class="text-sm font-medium">Habis</p>
            </a>
            <a class="flex flex-col items-center justify-center border-b-2 border-transparent text-slate-500 pb-3" href="#">
                <p class="text-sm font-medium">Kategori</p>
            </a>
        </div>
    </div>
    <div id="inventory-list" class="grid grid-cols-1 gap-3 px-4 pb-24 sm:grid-cols-2 app-bottom-safe-space"></div>
        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const inventoryLocationIdFromQuery = new URLSearchParams(window.location.search).get('location_id') || '';
                const inventoryPortalPrefix = @json($inventoryPortalPrefix);
                const inventoryCanOpenStockCard = @json($inventoryCanOpenStockCard);

                const el = {
                    list: document.getElementById('inventory-list'),
                    search: document.getElementById('search-input'),
                    locationFilter: document.getElementById('location-filter'),
                    locationLabel: document.getElementById('inventory-location-label'),
                };

                const state = {
                    products: [],
                    inventory: [],
                    locations: [],
                    term: '',
                    locationId: inventoryLocationIdFromQuery,
                };

                const loadBootstrap = async () => {
                    return window.PosUi.getBootstrap({
                        headers,
                        includeInventory: true,
                        ttlMs: 0,
                        forceFresh: true,
                    });
                };

                const resolveDefaultLocationId = (locations) => {
                    const defaultLocation = locations.find((location) => location.is_default);
                    return defaultLocation?.id || locations[0]?.id || '';
                };

                const syncLocationLabel = () => {
                    if (!el.locationLabel || !el.locationFilter) {
                        return;
                    }

                    const label = el.locationFilter.options[el.locationFilter.selectedIndex]?.textContent || 'Semua Lokasi';
                    el.locationLabel.textContent = label;
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
                        state.locationId = resolveDefaultLocationId(locations);
                    }

                    el.locationFilter.value = state.locationId;
                    syncLocationLabel();
                };

                const getQty = (productId) => state.inventory
                    .filter((item) => item.product_id === productId && (!state.locationId || item.location_id === state.locationId))
                    .reduce((sum, item) => sum + (item.qty_on_hand || 0), 0);

                const resolveUnitLabel = (value) => {
                    const unit = String(value || '').trim();
                    return unit !== '' ? unit : 'unit';
                };

                const buildInventoryDetailUrl = (productId) => {
                    const basePath = inventoryCanOpenStockCard
                        ? `${inventoryPortalPrefix}/inventory/products/${encodeURIComponent(productId)}/stock-card`
                        : `${inventoryPortalPrefix}/products/${encodeURIComponent(productId)}`;
                    const url = new URL(basePath, window.location.origin);
                    if (state.locationId) {
                        url.searchParams.set('location_id', state.locationId);
                    }
                    url.searchParams.set('from', window.location.pathname + window.location.search);
                    return `${url.pathname}${url.search}`;
                };

                const render = () => {
                    const term = state.term.toLowerCase();
                    const products = state.products.filter((product) => {
                        if (!term) {
                            return true;
                        }
                        return product.name.toLowerCase().includes(term)
                            || (product.sku || '').toLowerCase().includes(term);
                    });

                    if (!products.length) {
                        const emptyMessage = state.locationId ? 'Belum ada stok di lokasi ini.' : 'Belum ada stok.';
                        el.list.innerHTML = `<div class="px-4 py-6 text-sm text-slate-400">${emptyMessage}</div>`;
                        return;
                    }

                    el.list.innerHTML = '';
                    products.forEach((product) => {
                        const qty = Math.round(getQty(product.id));
                        const unitLabel = resolveUnitLabel(product.unit);
                        const status = qty === 0 ? 'Stok Habis' : qty <= 5 ? 'Stok Menipis' : 'Stok Tersedia';
                        const statusColor = qty === 0 ? 'bg-red-500' : qty <= 5 ? 'bg-amber-500' : 'bg-emerald-500';
                        const qtyColor = qty === 0 ? 'text-red-600' : qty <= 5 ? 'text-amber-600' : 'text-slate-900 dark:text-slate-100';
                        const image = product.image_url || '/images/placeholders/product.svg';

                        const card = document.createElement('a');
                        card.href = buildInventoryDetailUrl(product.id);
                        card.className = 'app-card flex items-center gap-3 p-3 transition-colors active:bg-slate-50';
                        card.innerHTML = `
                            <div class="bg-center bg-no-repeat aspect-square bg-cover rounded-lg size-14 bg-slate-100 dark:bg-slate-800" style="background-image: url('${image}')"></div>
                            <div class="flex flex-col flex-1">
                                <p class="text-slate-900 dark:text-slate-100 text-base font-semibold leading-snug">${product.name}</p>
                                <p class="text-slate-500 text-xs mt-0.5 uppercase tracking-wide">SKU: ${product.sku || '-'}</p>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span class="size-2 rounded-full ${statusColor}"></span>
                                    <p class="text-slate-500 text-xs font-medium">${status}</p>
                                </div>
                                <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-primary">Lihat detail stok</p>
                            </div>
                            <div class="flex flex-col items-end shrink-0">
                                <p class="${qtyColor} text-lg font-bold">${qty}</p>
                                <p class="text-slate-400 text-[10px] uppercase font-bold">${unitLabel}</p>
                                <span class="material-symbols-outlined mt-1 text-slate-300">chevron_right</span>
                            </div>
                        `;
                        el.list.appendChild(card);
                    });
                };

                el.search.addEventListener('input', (event) => {
                    state.term = event.target.value || '';
                    render();
                });

                el.locationFilter?.addEventListener('change', (event) => {
                    state.locationId = event.target.value || '';
                    syncLocationLabel();
                    render();

                    const url = new URL(window.location.href);
                    if (state.locationId) {
                        url.searchParams.set('location_id', state.locationId);
                    } else {
                        url.searchParams.delete('location_id');
                    }
                    window.history.replaceState({}, '', url);
                });

                (async () => {
                    try {
                        const data = await loadBootstrap();
                        state.products = (data.products || []).filter((product) => product.track_stock !== false);
                        state.inventory = data.inventory || [];
                        state.locations = data.locations || [];
                        buildLocationOptions(state.locations);
                        render();
                    } catch (error) {
                        el.list.innerHTML = `<div class="px-4 py-6 text-sm text-red-500">${error.message}</div>`;
                    }
                })();
            })();
        </script>
@endsection
