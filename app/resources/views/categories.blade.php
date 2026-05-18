@extends('layouts.app')

@php
    $categoriesTenant = \App\Support\TenantContext::get();
    $categoriesUser = auth()->user();
    $categoriesRole = null;

    if (! $categoriesTenant && filled($categoriesUser?->tenant_id)) {
        $categoriesTenant = \App\Models\Tenant::query()->find($categoriesUser->tenant_id);
    }

    if ($categoriesTenant && $categoriesUser) {
        $categoriesRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $categoriesTenant->id, (string) $categoriesUser->id);

        if (! $categoriesRole && filled($categoriesUser->role ?? null)) {
            $categoriesRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $categoriesUser->role);
        }
    }

    abort_unless(in_array($categoriesRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManageCategories = in_array($categoriesRole, ['admin', 'owner', 'superadmin'], true);
    $categoriesPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $categoriesBackUrl = $categoriesPortalPrefix.'/products';
@endphp

@section('page_title', 'POS SaaS')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0 app-bottom-safe-space')

@section('header')
    <x-page-header title="Kategori" :backUrl="$categoriesBackUrl">
        <x-slot name="actions">
            <button class="flex items-center justify-center p-2 rounded-full hover:bg-slate-100 transition-colors" type="button">
                <span class="material-symbols-outlined text-slate-900">more_vert</span>
            </button>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
        <div class="space-y-3 border-b border-slate-100 bg-white px-4 py-4">
            <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1">
                <button id="tab-product-categories" data-category-tab="products" class="h-10 rounded-lg bg-primary px-3 text-sm font-bold text-white shadow-sm" type="button">Produk</button>
                <button id="tab-customer-categories" data-category-tab="customers" class="h-10 rounded-lg px-3 text-sm font-bold text-slate-600" type="button">Pelanggan</button>
            </div>
            <label class="relative block w-full">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <span class="material-symbols-outlined">search</span>
                </span>
                <input id="search-input" class="form-input block w-full rounded-xl border-none bg-slate-100 py-3 pl-10 pr-4 text-slate-900 focus:ring-2 focus:ring-primary/20 placeholder:text-slate-400" placeholder="Cari kategori..." type="text" />
            </label>
        </div>

        <div class="px-4 py-3">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider" id="category-count">Total 0 Kategori</p>
        </div>

        <div class="flex-1 overflow-y-auto px-4 space-y-3 pb-24 app-bottom-safe-space" id="category-list"></div>

        @if ($canManageCategories)
            <button id="add-category" data-product-add-url="{{ $categoriesPortalPrefix }}/categories/new" class="fixed bottom-24 left-1/2 flex items-center justify-center size-14 bg-primary text-white rounded-full shadow-lg hover:scale-105 active:scale-95 transition-transform -translate-x-1/2" type="button" aria-label="Tambah kategori">
                <span class="material-symbols-outlined text-3xl">add</span>
            </button>
        @endif

        <div id="customer-category-overlay" class="fixed inset-0 z-[90] hidden bg-slate-950/40"></div>
        <div id="customer-category-sheet" class="fixed bottom-0 left-1/2 z-[95] hidden max-h-[82vh] w-full max-w-[430px] -translate-x-1/2 overflow-y-auto rounded-t-2xl bg-white p-5 shadow-2xl">
            <div class="mx-auto mb-4 h-1 w-12 rounded-full bg-slate-300"></div>
            <form id="customer-category-form" class="space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p id="customer-category-kicker" class="text-[10px] font-bold uppercase tracking-[0.24em] text-primary">Kategori pelanggan</p>
                        <h2 id="customer-category-title" class="mt-1 text-lg font-black text-slate-950">Tambah kategori pelanggan</h2>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">Dipakai untuk segmentasi seperti VIP, grosir, langganan, atau prospek.</p>
                    </div>
                    <button id="customer-category-close" class="flex size-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500" type="button" aria-label="Tutup">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>
                <label class="block space-y-1.5">
                    <span class="text-xs font-bold text-slate-500">Nama Kategori</span>
                    <input id="customer-category-name" class="h-12 w-full rounded-xl border-slate-200 bg-slate-50 px-4 text-sm font-semibold text-slate-900 focus:border-primary focus:ring-primary" placeholder="Contoh: VIP, Grosir, Prospek" type="text" />
                </label>
                <div class="space-y-2">
                    <p class="text-xs font-bold text-slate-500">Warna Label</p>
                    <div id="customer-category-colors" class="grid grid-cols-7 gap-2">
                        <button class="size-9 rounded-full bg-slate-500 ring-2 ring-slate-200" data-color="slate" type="button" aria-label="Slate"></button>
                        <button class="size-9 rounded-full bg-orange-500" data-color="orange" type="button" aria-label="Orange"></button>
                        <button class="size-9 rounded-full bg-emerald-500" data-color="emerald" type="button" aria-label="Emerald"></button>
                        <button class="size-9 rounded-full bg-sky-500" data-color="sky" type="button" aria-label="Sky"></button>
                        <button class="size-9 rounded-full bg-violet-500" data-color="violet" type="button" aria-label="Violet"></button>
                        <button class="size-9 rounded-full bg-rose-500" data-color="rose" type="button" aria-label="Rose"></button>
                        <button class="size-9 rounded-full bg-amber-500" data-color="amber" type="button" aria-label="Amber"></button>
                    </div>
                </div>
                <p id="customer-category-error" class="hidden rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700"></p>
                <div class="grid grid-cols-2 gap-3">
                    <button id="customer-category-delete" class="hidden h-12 rounded-xl border border-red-200 bg-red-50 px-4 text-sm font-bold text-red-700" type="button">Hapus</button>
                    <button id="customer-category-save" class="col-span-2 h-12 rounded-xl bg-primary px-4 text-sm font-bold text-white shadow-lg shadow-primary/20" type="submit">Simpan</button>
                </div>
            </form>
        </div>

        <script>
            (() => {
                const categoryPortalPrefix = @json($categoriesPortalPrefix);
                const canManageCategories = @json($canManageCategories);
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    search: document.getElementById('search-input'),
                    count: document.getElementById('category-count'),
                    list: document.getElementById('category-list'),
                    add: document.getElementById('add-category'),
                    tabs: Array.from(document.querySelectorAll('[data-category-tab]')),
                    overlay: document.getElementById('customer-category-overlay'),
                    sheet: document.getElementById('customer-category-sheet'),
                    form: document.getElementById('customer-category-form'),
                    closeSheet: document.getElementById('customer-category-close'),
                    sheetTitle: document.getElementById('customer-category-title'),
                    sheetKicker: document.getElementById('customer-category-kicker'),
                    customerCategoryName: document.getElementById('customer-category-name'),
                    customerCategoryColors: document.getElementById('customer-category-colors'),
                    customerCategoryError: document.getElementById('customer-category-error'),
                    customerCategoryDelete: document.getElementById('customer-category-delete'),
                    customerCategorySave: document.getElementById('customer-category-save'),
                };

                const state = {
                    activeTab: 'products',
                    productCategories: [],
                    customerCategories: [],
                    editingCustomerCategory: null,
                    selectedColor: 'slate',
                };

                const iconPool = ['restaurant', 'devices', 'styler', 'home_repair_service', 'medication', 'local_florist', 'sports_basketball', 'auto_awesome'];
                const productColorPool = [
                    'bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white',
                    'bg-orange-100 text-orange-600 group-hover:bg-orange-600 group-hover:text-white',
                    'bg-emerald-100 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white',
                    'bg-purple-100 text-purple-600 group-hover:bg-purple-600 group-hover:text-white',
                    'bg-rose-100 text-rose-600 group-hover:bg-rose-600 group-hover:text-white',
                    'bg-sky-100 text-sky-600 group-hover:bg-sky-600 group-hover:text-white',
                    'bg-amber-100 text-amber-600 group-hover:bg-amber-600 group-hover:text-white',
                    'bg-slate-100 text-slate-600 group-hover:bg-slate-600 group-hover:text-white',
                ];
                const customerColorClasses = {
                    slate: 'bg-slate-100 text-slate-700 ring-slate-200',
                    orange: 'bg-orange-100 text-orange-700 ring-orange-200',
                    emerald: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
                    sky: 'bg-sky-100 text-sky-700 ring-sky-200',
                    violet: 'bg-violet-100 text-violet-700 ring-violet-200',
                    rose: 'bg-rose-100 text-rose-700 ring-rose-200',
                    amber: 'bg-amber-100 text-amber-700 ring-amber-200',
                };

                const escapeHtml = (value) => String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');

                const setActiveTab = (tab) => {
                    state.activeTab = tab === 'customers' ? 'customers' : 'products';
                    el.tabs.forEach((button) => {
                        const active = button.dataset.categoryTab === state.activeTab;
                        button.classList.toggle('bg-primary', active);
                        button.classList.toggle('text-white', active);
                        button.classList.toggle('shadow-sm', active);
                        button.classList.toggle('text-slate-600', !active);
                    });
                    el.search.placeholder = state.activeTab === 'customers'
                        ? 'Cari kategori pelanggan...'
                        : 'Cari kategori produk...';
                    render();
                };

                const filteredItems = () => {
                    const term = el.search.value.trim().toLowerCase();
                    const source = state.activeTab === 'customers' ? state.customerCategories : state.productCategories;

                    return source.filter((item) => String(item.name || '').toLowerCase().includes(term));
                };

                const renderProductRow = (item, index) => {
                    const icon = iconPool[index % iconPool.length];
                    const color = productColorPool[index % productColorPool.length];
                    const row = document.createElement('div');
                    row.className = 'flex items-center gap-4 rounded-xl border border-slate-100 bg-white p-4 transition-all hover:border-primary/30 group';
                    row.innerHTML = `
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-xl ${color}">
                            <span class="material-symbols-outlined">${icon}</span>
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <p class="truncate text-base font-semibold text-slate-900">${escapeHtml(item.name)}</p>
                            <p class="line-clamp-1 text-xs text-slate-500">Kategori produk ${escapeHtml(String(item.name || '').toLowerCase())}</p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <span class="rounded border border-slate-100 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-600">${Number(item.product_count || 0)} Produk</span>
                            <span class="material-symbols-outlined text-sm text-slate-300">chevron_right</span>
                        </div>
                    `;
                    if (canManageCategories) {
                        row.addEventListener('click', () => {
                            window.location.href = `${categoryPortalPrefix}/categories/${item.id}/edit`;
                        });
                    }
                    el.list.appendChild(row);
                };

                const renderCustomerRow = (item) => {
                    const color = item.color || 'slate';
                    const colorClass = customerColorClasses[color] || customerColorClasses.slate;
                    const row = document.createElement('div');
                    row.className = 'flex items-center gap-4 rounded-xl border border-slate-100 bg-white p-4 transition-all hover:border-primary/30 group';
                    row.innerHTML = `
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-xl ring-1 ${colorClass}">
                            <span class="material-symbols-outlined">groups</span>
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <p class="truncate text-base font-semibold text-slate-900">${escapeHtml(item.name)}</p>
                            <p class="line-clamp-1 text-xs text-slate-500">Label pelanggan fleksibel</p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <span class="rounded border border-slate-100 bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-600">${Number(item.customer_count || 0)} Pelanggan</span>
                            <span class="material-symbols-outlined text-sm text-slate-300">chevron_right</span>
                        </div>
                    `;
                    if (canManageCategories) {
                        row.addEventListener('click', () => openCustomerCategorySheet(item));
                    }
                    el.list.appendChild(row);
                };

                const render = () => {
                    const filtered = filteredItems();
                    const label = state.activeTab === 'customers' ? 'Kategori Pelanggan' : 'Kategori Produk';
                    el.count.textContent = `Total ${filtered.length} ${label}`;
                    el.list.innerHTML = '';

                    if (!filtered.length) {
                        el.list.innerHTML = `<div class="py-8 text-center text-sm text-slate-400">Belum ada ${label.toLowerCase()}.</div>`;
                        return;
                    }

                    filtered.forEach((item, index) => {
                        if (state.activeTab === 'customers') {
                            renderCustomerRow(item);
                            return;
                        }

                        renderProductRow(item, index);
                    });
                };

                const loadProductCategories = async () => {
                    const response = await window.PosUi.fetchWithTimeout('/api/v1/categories', { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat kategori produk.');
                    }
                    const data = await response.json();
                    state.productCategories = data.categories || [];
                };

                const loadCustomerCategories = async () => {
                    const response = await window.PosUi.fetchWithTimeout('/api/v1/customer-categories', { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat kategori pelanggan.');
                    }
                    const data = await response.json();
                    state.customerCategories = data.customer_categories || [];
                };

                const showSheetError = (message = '') => {
                    el.customerCategoryError.textContent = message;
                    el.customerCategoryError.classList.toggle('hidden', !message);
                };

                const selectColor = (color = 'slate') => {
                    state.selectedColor = color;
                    el.customerCategoryColors.querySelectorAll('button[data-color]').forEach((button) => {
                        const active = button.dataset.color === color;
                        button.classList.toggle('ring-4', active);
                        button.classList.toggle('ring-primary/25', active);
                        button.classList.toggle('scale-105', active);
                    });
                };

                const closeCustomerCategorySheet = () => {
                    el.overlay.classList.add('hidden');
                    el.sheet.classList.add('hidden');
                    state.editingCustomerCategory = null;
                    showSheetError('');
                };

                const openCustomerCategorySheet = (category = null) => {
                    state.editingCustomerCategory = category;
                    el.sheetTitle.textContent = category ? 'Ubah kategori pelanggan' : 'Tambah kategori pelanggan';
                    el.sheetKicker.textContent = category ? 'Edit kategori pelanggan' : 'Kategori pelanggan';
                    el.customerCategoryName.value = category?.name || '';
                    el.customerCategoryDelete.classList.toggle('hidden', !category);
                    el.customerCategorySave.classList.toggle('col-span-2', !category);
                    selectColor(category?.color || 'slate');
                    showSheetError('');
                    el.overlay.classList.remove('hidden');
                    el.sheet.classList.remove('hidden');
                    window.setTimeout(() => el.customerCategoryName.focus(), 50);
                };

                const saveCustomerCategory = async () => {
                    const name = el.customerCategoryName.value.trim();
                    if (!name) {
                        showSheetError('Nama kategori pelanggan wajib diisi.');
                        el.customerCategoryName.focus();
                        return;
                    }

                    const editing = state.editingCustomerCategory;
                    const url = editing
                        ? `/api/v1/customer-categories/${editing.id}`
                        : '/api/v1/customer-categories';
                    const method = editing ? 'PUT' : 'POST';

                    el.customerCategorySave.disabled = true;
                    try {
                        const response = await window.PosUi.fetchWithTimeout(url, {
                            method,
                            headers: {
                                ...headers,
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                name,
                                color: state.selectedColor,
                            }),
                        });

                        if (!response.ok) {
                            const mapped = await window.PosUi.mapApiError(response, 'Gagal menyimpan kategori pelanggan.');
                            const validationMessage = Object.values(mapped?.payload?.errors || mapped?.payload?.details || {})[0]?.[0];
                            throw new Error(validationMessage || mapped?.message || 'Gagal menyimpan kategori pelanggan.');
                        }

                        const data = await response.json().catch(() => ({}));
                        const category = data.customer_category;
                        if (category) {
                            state.customerCategories = [
                                category,
                                ...state.customerCategories.filter((item) => String(item.id) !== String(category.id)),
                            ].sort((left, right) => String(left.name || '').localeCompare(String(right.name || '')));
                        } else {
                            await loadCustomerCategories();
                        }
                        closeCustomerCategorySheet();
                        setActiveTab('customers');
                    } catch (error) {
                        showSheetError(error?.message || 'Gagal menyimpan kategori pelanggan.');
                    } finally {
                        el.customerCategorySave.disabled = false;
                    }
                };

                const deleteCustomerCategory = async () => {
                    const editing = state.editingCustomerCategory;
                    if (!editing) {
                        return;
                    }

                    if (!window.confirm('Hapus kategori pelanggan ini? Pelanggan yang memakai kategori ini akan menjadi tanpa kategori.')) {
                        return;
                    }

                    el.customerCategoryDelete.disabled = true;
                    try {
                        const response = await window.PosUi.fetchWithTimeout(`/api/v1/customer-categories/${editing.id}`, {
                            method: 'DELETE',
                            headers,
                        });

                        if (!response.ok) {
                            const mapped = await window.PosUi.mapApiError(response, 'Gagal menghapus kategori pelanggan.');
                            throw new Error(mapped?.message || 'Gagal menghapus kategori pelanggan.');
                        }

                        state.customerCategories = state.customerCategories.filter((item) => String(item.id) !== String(editing.id));
                        closeCustomerCategorySheet();
                        render();
                    } catch (error) {
                        showSheetError(error?.message || 'Gagal menghapus kategori pelanggan.');
                    } finally {
                        el.customerCategoryDelete.disabled = false;
                    }
                };

                el.search.addEventListener('input', render);
                el.tabs.forEach((button) => {
                    button.addEventListener('click', () => setActiveTab(button.dataset.categoryTab));
                });
                el.add?.addEventListener('click', () => {
                    if (state.activeTab === 'customers') {
                        openCustomerCategorySheet();
                        return;
                    }

                    window.location.href = el.add.dataset.productAddUrl || `${categoryPortalPrefix}/categories/new`;
                });
                el.overlay.addEventListener('click', closeCustomerCategorySheet);
                el.closeSheet.addEventListener('click', closeCustomerCategorySheet);
                el.customerCategoryColors.addEventListener('click', (event) => {
                    const button = event.target.closest('button[data-color]');
                    if (button) {
                        selectColor(button.dataset.color);
                    }
                });
                el.form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    saveCustomerCategory();
                });
                el.customerCategoryDelete.addEventListener('click', deleteCustomerCategory);

                Promise.all([loadProductCategories(), loadCustomerCategories()])
                    .then(render)
                    .catch((error) => {
                        el.list.innerHTML = `<div class="py-8 text-center text-sm text-red-500">${escapeHtml(error?.message || 'Gagal memuat kategori.')}</div>`;
                    });
            })();
        </script>
@endsection
