@extends('layouts.app')

@php
    $productsTenant = \App\Support\TenantContext::get();
    $productsUser = auth()->user();
    $productsRole = null;

    if (! $productsTenant && filled($productsUser?->tenant_id)) {
        $productsTenant = \App\Models\Tenant::query()->find($productsUser->tenant_id);
    }

    if ($productsTenant && $productsUser) {
        $productsRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $productsTenant->id, (string) $productsUser->id);

        if (! $productsRole && filled($productsUser->role ?? null)) {
            $productsRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $productsUser->role);
        }
    }

    abort_unless(in_array($productsRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManageProducts = in_array($productsRole, ['admin', 'owner', 'superadmin'], true);
    $productsPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $productsFrom = trim((string) request()->query('from', ''));
    $productsCurrentPath = $productsPortalPrefix.'/products';
    if ($productsFrom !== '') {
        $productsCurrentPath .= '?from='.rawurlencode($productsFrom);
    }

    $productsBackUrl = $productsPortalPrefix === '/pos' ? pos_default_url() : '/backoffice/dashboard';
    if ($productsFrom !== '') {
        if (str_starts_with($productsFrom, $productsPortalPrefix.'/')) {
            $productsBackUrl = $productsFrom;
        } elseif ($productsPortalPrefix === '/backoffice' && str_starts_with($productsFrom, '/pos/')) {
            $productsBackUrl = '/portal/switch/pos?next='.rawurlencode($productsFrom);
        } elseif ($productsPortalPrefix === '/pos' && str_starts_with($productsFrom, '/backoffice/')) {
            $productsBackUrl = '/portal/switch/backoffice?next='.rawurlencode($productsFrom);
        }
    }
    $productsBackUrl = normalize_pos_home_url($productsBackUrl);
@endphp

@section('page_title', 'Daftar Produk')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto pb-24')

@push('head')
    <style>
        .ios-blur { backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
@endpush

@section('header')
    <x-page-header title="Daftar Produk" :backUrl="$productsBackUrl" :showMenuButton="true">
        <x-slot:actions>
            @if ($canManageProducts)
                <a class="flex items-center justify-center size-9 rounded-full bg-primary text-white" href="{{ $productsPortalPrefix.'/products/new?from='.rawurlencode($productsCurrentPath) }}" aria-label="Tambah produk">
                    <span class="material-symbols-outlined">add</span>
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <div class="px-4 py-4 space-y-4">
        <div class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input id="search-input" class="app-search-control pl-10 pr-4 py-3" placeholder="Cari nama produk atau SKU..." type="text" />
        </div>
        <div id="category-filters" class="flex gap-2 overflow-x-auto pb-1 no-scrollbar"></div>
    </div>

    <div class="px-4 space-y-3" id="product-list"></div>

    <div id="product-success-toast" class="fixed bottom-6 left-1/2 z-50 hidden w-[calc(100%-2rem)] max-w-[390px] -translate-x-1/2 rounded-2xl border border-emerald-200 bg-white px-4 py-3 text-sm text-emerald-700 shadow-xl">
        <p class="font-semibold">Berhasil</p>
        <p class="mt-1 text-xs text-emerald-600" data-toast-message></p>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const tenantId = document.querySelector('meta[name="tenant-id"]').content;
        const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
        const currentPath = @json($productsCurrentPath);
        const portalPrefix = @json($productsPortalPrefix);
        const successFlashKey = 'kalakasir:product-create-success';

        const el = {
            list: document.getElementById('product-list'),
            filters: document.getElementById('category-filters'),
            search: document.getElementById('search-input'),
            successToast: document.getElementById('product-success-toast'),
            successMessage: document.querySelector('#product-success-toast [data-toast-message]'),
        };

        let successToastTimeoutId = null;

        const showSuccessToast = (message) => {
            if (!el.successToast || !el.successMessage) {
                return;
            }

            if (!message) {
                el.successToast.classList.add('hidden');
                el.successMessage.textContent = '';
                return;
            }

            el.successMessage.textContent = message;
            el.successToast.classList.remove('hidden');

            if (successToastTimeoutId) {
                window.clearTimeout(successToastTimeoutId);
            }

            successToastTimeoutId = window.setTimeout(() => {
                el.successToast.classList.add('hidden');
                el.successMessage.textContent = '';
            }, 4000);
        };

        const state = {
            products: [],
            categories: [],
            selectedCategory: 'all',
            term: '',
        };

        const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(value || 0);

        const formatQty = (value) => {
            const qty = Number(value || 0);
            return Number.isInteger(qty) ? String(qty) : qty.toFixed(2);
        };

        const formatQtyWithUnit = (value, unit) => {
            const qty = Number(value || 0);
            const rawUnit = String(unit || '').trim();
            const normalized = rawUnit.toLowerCase();
            const absQty = Math.abs(qty);
            let displayQty = qty;
            let displayUnit = rawUnit || 'unit';

            if (['g', 'gr', 'gram', 'grams'].includes(normalized) && absQty >= 1000) {
                displayQty = qty / 1000;
                displayUnit = 'kg';
            } else if (['mg', 'miligram', 'milligram'].includes(normalized) && absQty >= 1000) {
                displayQty = qty / 1000;
                displayUnit = 'g';
            } else if (['ml', 'mili', 'milliliter', 'mililiter'].includes(normalized) && absQty >= 1000) {
                displayQty = qty / 1000;
                displayUnit = 'L';
            }

            return `${formatQty(displayQty)} ${displayUnit}`;
        };

        const renderFilters = () => {
            el.filters.innerHTML = '';
            const allBtn = document.createElement('button');
            allBtn.className = 'flex h-9 items-center justify-center gap-2 rounded-full bg-primary px-4 text-white text-sm font-medium';
            allBtn.textContent = 'Semua';
            allBtn.dataset.category = 'all';
            el.filters.appendChild(allBtn);

            state.categories.forEach((category) => {
                const button = document.createElement('button');
                button.className = 'flex h-9 items-center justify-center gap-2 rounded-full bg-white px-4 text-sm font-bold text-slate-600 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-400 dark:ring-slate-800';
                button.textContent = category.name;
                button.dataset.category = category.id;
                el.filters.appendChild(button);
            });
        };

        const applyFilterStyles = () => {
            document.querySelectorAll('#category-filters button').forEach((btn) => {
                const isActive = btn.dataset.category === state.selectedCategory;
                btn.classList.toggle('bg-primary', isActive);
                btn.classList.toggle('text-white', isActive);
            });
        };

        const renderList = () => {
            const term = state.term.toLowerCase();
            const products = state.products.filter((product) => {
                if (state.selectedCategory !== 'all' && product.category_id !== state.selectedCategory) {
                    return false;
                }
                if (!term) {
                    return true;
                }
                return product.name.toLowerCase().includes(term)
                    || (product.sku || '').toLowerCase().includes(term);
            });

            el.list.innerHTML = '';

            if (!products.length) {
                el.list.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-8 text-center text-sm font-semibold text-slate-400">Belum ada produk.</div>';
                return;
            }

            products.forEach((product) => {
                const card = document.createElement('div');
                card.className = 'app-card flex items-center justify-between gap-3 p-4';
                    const image = product.image_url || '/images/placeholders/product.svg';
                const hppLabel = Number(product.hpp_average || 0) > 0
                    ? formatCurrency(product.hpp_average)
                    : 'Belum terbentuk';
                const stockLabel = product.track_stock
                    ? formatQtyWithUnit(product.qty_on_hand || 0, product.unit)
                    : 'Tidak dilacak';
                const stockClass = product.track_stock && Number(product.qty_on_hand || 0) <= Number(product.min_stock || 0)
                    ? 'bg-rose-50 text-rose-700 ring-rose-100'
                    : 'bg-emerald-50 text-emerald-700 ring-emerald-100';
                card.innerHTML = `
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="size-14 shrink-0 rounded-xl border border-slate-100 bg-cover bg-center dark:border-slate-800" style="background-image: url('${image}')"></div>
                        <div class="flex min-w-0 flex-col">
                            <h3 class="truncate text-sm font-black text-slate-950 dark:text-white">${product.name}</h3>
                            <p class="text-sm text-slate-500">SKU: ${product.sku || '-'} • <span class="font-semibold text-slate-700 dark:text-slate-300">${formatCurrency(product.price)}</span></p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.12em] ring-1 ${stockClass}">Stok ${stockLabel}</span>
                                <span class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">HPP ${hppLabel}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors" href="${portalPrefix}/products/${product.id}/edit?from=${encodeURIComponent(currentPath)}" aria-label="Edit produk" title="Edit produk">
                            <span class="material-symbols-outlined text-[20px]">edit</span>
                        </a>
                        <a class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors" href="${portalPrefix}/products/${product.id}?from=${encodeURIComponent(currentPath)}" aria-label="Detail produk" title="Detail produk">
                            <span class="material-symbols-outlined text-[20px]">info</span>
                        </a>
                    </div>
                `;
                el.list.appendChild(card);
            });
        };

        const loadProducts = async () => {
            const response = await fetch('/api/v1/products', { headers });
            if (!response.ok) {
                return [];
            }
            const data = await response.json();
            return data.products || [];
        };

        const loadCategories = async () => {
            const response = await fetch('/api/v1/categories', { headers });
            if (!response.ok) {
                return [];
            }
            const data = await response.json();
            return data.categories || [];
        };

        el.filters.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-category]');
            if (!button) {
                return;
            }
            state.selectedCategory = button.dataset.category;
            applyFilterStyles();
            renderList();
        });

        el.search.addEventListener('input', (event) => {
            state.term = event.target.value;
            renderList();
        });

        (async () => {
            const flashMessage = sessionStorage.getItem(successFlashKey);
            if (flashMessage) {
                sessionStorage.removeItem(successFlashKey);
                showSuccessToast(flashMessage);
            }

            const [products, categories] = await Promise.all([loadProducts(), loadCategories()]);
            state.products = products;
            state.categories = categories;

            renderFilters();
            applyFilterStyles();
            renderList();
        })();
    })();
</script>
@endpush
