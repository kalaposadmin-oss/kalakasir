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
    $productsFrom = trim((string) request()->query('from', ''));
    $productsCurrentPath = '/backoffice/products';
    if ($productsFrom !== '') {
        $productsCurrentPath .= '?from='.rawurlencode($productsFrom);
    }

    $productsBackUrl = '/backoffice/dashboard';
    if ($productsFrom !== '') {
        if (str_starts_with($productsFrom, '/pos/')) {
            $productsBackUrl = '/portal/switch/pos?next='.rawurlencode($productsFrom);
        } elseif (str_starts_with($productsFrom, '/backoffice/')) {
            $productsBackUrl = $productsFrom;
        }
    }
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
                <a class="flex items-center justify-center size-9 rounded-full bg-primary text-white" href="{{ '/backoffice/products/new?from='.rawurlencode($productsCurrentPath) }}" aria-label="Tambah produk">
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
            <input id="search-input" class="w-full pl-10 pr-4 py-3 bg-white dark:bg-slate-900 border-none rounded-xl shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 focus:ring-2 focus:ring-primary text-sm" placeholder="Cari nama produk atau SKU..." type="text" />
        </div>
        <div id="category-filters" class="flex gap-2 overflow-x-auto pb-1 no-scrollbar"></div>
    </div>

    <div class="px-4 space-y-3" id="product-list"></div>
@endsection

@push('scripts')
<script>
    (() => {
        const tenantId = document.querySelector('meta[name="tenant-id"]').content;
        const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
        const currentPath = @json($productsCurrentPath);

        const el = {
            list: document.getElementById('product-list'),
            filters: document.getElementById('category-filters'),
            search: document.getElementById('search-input'),
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

        const renderFilters = () => {
            el.filters.innerHTML = '';
            const allBtn = document.createElement('button');
            allBtn.className = 'flex h-9 items-center justify-center gap-2 rounded-full bg-primary px-4 text-white text-sm font-medium';
            allBtn.textContent = 'Semua';
            allBtn.dataset.category = 'all';
            el.filters.appendChild(allBtn);

            state.categories.forEach((category) => {
                const button = document.createElement('button');
                button.className = 'flex h-9 items-center justify-center gap-2 rounded-full bg-white dark:bg-slate-900 ring-1 ring-slate-200 dark:ring-slate-800 px-4 text-slate-600 dark:text-slate-400 text-sm font-medium';
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
                el.list.innerHTML = '<div class="text-sm text-slate-400">Belum ada produk.</div>';
                return;
            }

            products.forEach((product) => {
                const card = document.createElement('div');
                card.className = 'bg-white dark:bg-slate-900 p-3 rounded-xl shadow-sm ring-1 ring-slate-200 dark:ring-slate-800 flex items-center justify-between';
                const image = product.image_url || 'https://placehold.co/120x120?text=Produk';
                card.innerHTML = `
                    <div class="flex items-center gap-4">
                        <div class="size-16 rounded-lg bg-cover bg-center shrink-0 border border-slate-100 dark:border-slate-800" style="background-image: url('${image}')"></div>
                        <div class="flex flex-col">
                            <h3 class="font-bold text-slate-900 dark:text-white">${product.name}</h3>
                            <p class="text-sm text-slate-500">SKU: ${product.sku || '-'} • <span class="font-semibold text-slate-700 dark:text-slate-300">${formatCurrency(product.price)}</span></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors" href="/backoffice/products/${product.id}/edit?from=${encodeURIComponent(currentPath)}" aria-label="Edit produk" title="Edit produk">
                            <span class="material-symbols-outlined text-[20px]">edit</span>
                        </a>
                        <a class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors" href="/backoffice/products/${product.id}?from=${encodeURIComponent(currentPath)}" aria-label="Detail produk" title="Detail produk">
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



