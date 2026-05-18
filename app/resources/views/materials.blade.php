@extends('layouts.app')

@php
    $materialsTenant = \App\Support\TenantContext::get();
    $materialsUser = auth()->user();
    $materialsRole = null;

    if (! $materialsTenant && filled($materialsUser?->tenant_id)) {
        $materialsTenant = \App\Models\Tenant::query()->find($materialsUser->tenant_id);
    }

    if ($materialsTenant && $materialsUser) {
        $materialsRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $materialsTenant->id, (string) $materialsUser->id);

        if (! $materialsRole && filled($materialsUser->role ?? null)) {
            $materialsRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $materialsUser->role);
        }
    }

    abort_unless(in_array($materialsRole, ['admin', 'owner', 'superadmin'], true), 403);
@endphp

@php
    $materialsCurrentPath = '/'.ltrim(request()->path(), '/');
    $materialsPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $materialsBackUrl = $materialsPortalPrefix === '/pos' ? pos_default_url() : '/backoffice/dashboard';
    $materialsCreateUrl = $materialsPortalPrefix.'/products/new?type=raw_material&from='.rawurlencode($materialsCurrentPath);
    $materialsProductionBatchesUrl = $materialsPortalPrefix === '/pos'
        ? '/pos/inventory/production-batches?from='.rawurlencode($materialsCurrentPath)
        : '/backoffice/inventory/production-batches?from='.rawurlencode($materialsCurrentPath);
@endphp

@section('page_title', 'Bahan Baku')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto p-0')

@section('header')
    <x-page-header title="Bahan Baku" :backUrl="$materialsBackUrl">
        <x-slot name="actions">
            <a href="{{ $materialsProductionBatchesUrl }}" class="flex size-10 items-center justify-center rounded-full bg-slate-100 text-slate-600 shadow-sm ring-1 ring-slate-200/70 dark:bg-slate-800 dark:text-slate-300" aria-label="Buka batch produksi" title="Batch Produksi">
                <span class="material-symbols-outlined">precision_manufacturing</span>
            </a>
            <a id="open-create" href="{{ $materialsCreateUrl }}" class="flex size-10 items-center justify-center rounded-full bg-primary text-white shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined">add</span>
            </a>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
    <div class="bg-background-light pb-24">
        <div class="space-y-3 border-b border-slate-200 bg-white px-4 py-4">
            <details class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 shadow-sm">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                    <span class="inline-flex min-w-0 items-center gap-2 font-bold text-slate-900">
                        <span class="material-symbols-outlined text-[20px] text-primary">info</span>
                        <span>Bahan baku untuk produksi & resep</span>
                    </span>
                    <span class="material-symbols-outlined text-slate-400">expand_more</span>
                </summary>
                <div class="mt-3 grid gap-2 text-xs leading-relaxed sm:grid-cols-3">
                    <div class="rounded-xl bg-white px-3 py-3 ring-1 ring-slate-200">
                        <p class="app-kicker">Langkah 1</p>
                        <p class="mt-1 font-bold text-slate-900">Buat bahan baku</p>
                        <p class="mt-1 text-slate-500">Tambahkan item bahan baku dari flow produk.</p>
                    </div>
                    <div class="rounded-xl bg-white px-3 py-3 ring-1 ring-slate-200">
                        <p class="app-kicker">Langkah 2</p>
                        <p class="mt-1 font-bold text-slate-900">Pakai di resep</p>
                        <p class="mt-1 text-slate-500">Hubungkan bahan ke BOM produk jadi.</p>
                    </div>
                    <div class="rounded-xl bg-white px-3 py-3 ring-1 ring-slate-200">
                        <p class="app-kicker">Langkah 3</p>
                        <p class="mt-1 font-bold text-slate-900">Produksi</p>
                        <p class="mt-1 text-slate-500">Batch produksi mengurangi stok bahan dan menambah stok hasil.</p>
                    </div>
                </div>
            </details>

            <div class="flex flex-wrap gap-2">
                <a href="{{ $materialsCreateUrl }}" class="app-primary-button">
                    <span class="material-symbols-outlined text-[20px]">add_box</span>
                    Tambah Bahan Baku
                </a>
                <a href="{{ $materialsProductionBatchesUrl }}" class="app-secondary-button">
                    <span class="material-symbols-outlined text-[20px]">precision_manufacturing</span>
                    Lihat Batch Produksi
                </a>
            </div>
        </div>

        <div class="bg-white px-4 py-4">
            <label class="relative block w-full">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <span class="material-symbols-outlined">search</span>
                </span>
                <input id="search-input" class="app-search-control block py-3 pl-10 pr-4 placeholder:text-slate-400" placeholder="Cari bahan baku..." type="text" />
            </label>
        </div>

        <div class="flex items-center justify-between px-4 pb-2">
            <p class="app-kicker" id="material-count">Total 0 Bahan Baku</p>
            <button id="refresh-list" class="text-xs font-bold text-primary" type="button">Refresh</button>
        </div>

        <div class="space-y-3 px-4" id="material-list"></div>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]').content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
            const portalPrefix = @json($materialsPortalPrefix);
            const materialsCreateUrl = @json($materialsCreateUrl);
            const materialsCurrentPath = @json($materialsCurrentPath);
            const materialsProductionBatchesUrl = @json($materialsProductionBatchesUrl);

            const el = {
                list: document.getElementById('material-list'),
                count: document.getElementById('material-count'),
                search: document.getElementById('search-input'),
                refresh: document.getElementById('refresh-list'),
            };

            const state = {
                materials: [],
            };

            const formatCount = (count) => `Total ${count} Bahan Baku`;

            const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(value || 0);

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const formatQty = (value, unit) => {
                const formatted = new Intl.NumberFormat('id-ID', {
                    maximumFractionDigits: 2,
                }).format(Number(value || 0));

                return [formatted, unit || ''].filter(Boolean).join(' ');
            };

            const withQuery = (url, params) => {
                const target = new URL(url, window.location.origin);
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== undefined && value !== null && value !== '') {
                        target.searchParams.set(key, value);
                    }
                });

                return `${target.pathname}${target.search}`;
            };

            const unwrapList = (payload, key) => {
                const root = window.PosUi?.unwrapApiPayload
                    ? window.PosUi.unwrapApiPayload(payload)
                    : (payload && typeof payload === 'object' && payload.data && typeof payload.data === 'object' && !Array.isArray(payload.data)
                        ? payload.data
                        : payload);
                const candidate = key && root && typeof root === 'object' ? root[key] : root;

                if (Array.isArray(candidate)) {
                    return candidate;
                }

                if (candidate && Array.isArray(candidate.data)) {
                    return candidate.data;
                }

                return [];
            };

            const renderList = () => {
                const term = el.search.value.trim().toLowerCase();
                const filtered = state.materials.filter((item) => String(item.name || '').toLowerCase().includes(term));
                el.count.textContent = formatCount(filtered.length);
                el.list.innerHTML = '';

                if (!filtered.length) {
                    el.list.innerHTML = `
                        <div class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-8 text-center shadow-sm">
                            <p class="text-sm font-bold text-slate-800">Belum ada bahan baku.</p>
                            <p class="mt-1 text-xs text-slate-500">Buat bahan baku untuk produksi & resep, lalu hubungkan ke BOM produk.</p>
                            <a href="${materialsCreateUrl}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-xs font-bold text-white">
                                <span class="material-symbols-outlined text-[18px]">add_box</span>
                                Tambah Bahan Baku
                            </a>
                        </div>
                    `;
                    return;
                }

                filtered.forEach((item) => {
                    const editProductId = item.product_id || item.id;
                    const usageProducts = Array.isArray(item.used_in_products) ? item.used_in_products : [];
                    const usageCount = Number(item.used_in_count ?? usageProducts.length ?? 0);
                    const hppValue = item.hpp_average ?? item.unit_cost ?? item.price ?? 0;
                    const stockValue = item.stock_on_hand ?? item.stock ?? item.qty_on_hand ?? 0;
                    const unit = item.unit || item.uom || '';
                    const detailUrl = `${portalPrefix}/products/${editProductId}?from=${encodeURIComponent(materialsCurrentPath)}`;
                    const editUrl = `${portalPrefix}/products/${editProductId}/edit?from=${encodeURIComponent(materialsCurrentPath)}`;
                    const productionUrl = withQuery(materialsProductionBatchesUrl, {
                        material_id: item.id,
                        product_id: editProductId,
                    });
                    const usageList = usageProducts.length
                        ? usageProducts.map((name) => `<span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">${escapeHtml(name)}</span>`).join('')
                        : '<span class="text-[11px] font-medium text-slate-400">Belum dipakai di resep.</span>';
                    const row = document.createElement('article');
                    row.className = 'app-card p-4';
                    row.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Bahan Baku</p>
                                <h3 class="mt-1 truncate text-base font-black text-slate-950">${escapeHtml(item.name || 'Tanpa Nama')}</h3>
                                <p class="mt-1 truncate text-xs text-slate-500">SKU: ${escapeHtml(item.sku || '-')}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-primary/10 px-2.5 py-1 text-[10px] font-black text-primary">Dipakai di ${usageCount} resep</span>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Stok</p>
                                <p class="mt-1 text-xs font-black text-slate-900">${escapeHtml(formatQty(stockValue, unit))}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Dipakai</p>
                                <p class="mt-1 text-xs font-black text-slate-900">${usageCount} resep</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2">
                                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Harga</p>
                                <p class="mt-1 text-xs font-black text-slate-900">${formatCurrency(hppValue)}</p>
                            </div>
                        </div>
                        <div class="mt-3 hidden flex-wrap gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3" data-usage-panel>
                            ${usageList}
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <a class="inline-flex items-center justify-center gap-1 rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700" href="${editUrl}">
                                <span class="material-symbols-outlined text-[16px]">edit</span>
                                Edit
                            </a>
                            <button class="inline-flex items-center justify-center gap-1 rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700" type="button" data-toggle-usage>
                                <span class="material-symbols-outlined text-[16px]">bar_chart</span>
                                Dipakai di
                            </button>
                            <a class="inline-flex items-center justify-center gap-1 rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700" href="${detailUrl}">
                                <span class="material-symbols-outlined text-[16px]">settings</span>
                                Detail
                            </a>
                        </div>
                        <a class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-primary/15 bg-primary/5 px-3 py-2.5 text-xs font-black text-primary" href="${productionUrl}">
                            <span class="material-symbols-outlined text-[17px]">precision_manufacturing</span>
                            Lihat penggunaan di produksi
                        </a>
                    `;
                    el.list.appendChild(row);
                });
            };

            const loadMaterials = async () => {
                let rows = [];

                const materialResponse = await fetch('/api/v1/materials?limit=300', { headers });
                if (materialResponse.ok) {
                    const payload = await materialResponse.json().catch(() => ({}));
                    rows = unwrapList(payload, 'materials');
                }

                if (!rows.length) {
                    const productResponse = await fetch('/api/v1/products', { headers });
                    if (productResponse.ok) {
                        const payload = await productResponse.json().catch(() => ({}));
                        rows = unwrapList(payload, 'products')
                            .filter((product) => String(product.item_type || '') === 'raw_material')
                            .map((product) => ({
                                ...product,
                                product_id: product.id,
                                unit_cost: product.hpp_average ?? 0,
                                stock_on_hand: product.stock_on_hand ?? product.stock ?? 0,
                            }));
                    }
                }

                if (!rows.length && !materialResponse.ok) {
                    el.list.innerHTML = '<div class="py-8 text-center text-sm text-red-500">Gagal memuat bahan baku.</div>';
                    el.count.textContent = formatCount(0);
                    return;
                }

                state.materials = rows.sort((left, right) => String(left.name || '').localeCompare(String(right.name || '')));
                renderList();
            };

            el.search.addEventListener('input', renderList);
            el.refresh.addEventListener('click', loadMaterials);
            el.list.addEventListener('click', (event) => {
                const toggle = event.target.closest('[data-toggle-usage]');
                if (!toggle) {
                    return;
                }

                const panel = toggle.closest('article')?.querySelector('[data-usage-panel]');
                panel?.classList.toggle('hidden');
                panel?.classList.toggle('flex');
            });

            loadMaterials();
        })();
    </script>
@endsection
