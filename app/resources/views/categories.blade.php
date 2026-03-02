@extends('layouts.app')

@section('page_title', 'POS SaaS')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@section('header')
    <x-page-header title="Kategori Produk" backUrl="/menu/management">
        <x-slot name="actions">
            <button class="flex items-center justify-center p-2 rounded-full hover:bg-slate-100 transition-colors" type="button">
                <span class="material-symbols-outlined text-slate-900">more_vert</span>
            </button>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
        <div class="px-4 py-4 bg-white">
            <label class="relative block w-full">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <span class="material-symbols-outlined">search</span>
                </span>
                <input id="search-input" class="form-input block w-full rounded-xl border-none bg-slate-100 py-3 pl-10 pr-4 text-slate-900 focus:ring-2 focus:ring-primary/20 placeholder:text-slate-400" placeholder="Cari kategori produk..." type="text" />
            </label>
        </div>

        <div class="px-4 pb-2">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider" id="category-count">Total 0 Kategori</p>
        </div>

        <div class="flex-1 overflow-y-auto px-4 space-y-3 pb-24" id="category-list"></div>

        <button id="add-category" class="fixed bottom-24 left-1/2 flex items-center justify-center size-14 bg-primary text-white rounded-full shadow-lg hover:scale-105 active:scale-95 transition-transform -translate-x-1/2" type="button" onclick="location.href='/categories/new'">
            <span class="material-symbols-outlined text-3xl">add</span>
        </button>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    search: document.getElementById('search-input'),
                    count: document.getElementById('category-count'),
                    list: document.getElementById('category-list'),
                };

                const state = {
                    categories: [],
                };

                const iconPool = ['restaurant', 'devices', 'styler', 'home_repair_service', 'medication', 'local_florist', 'sports_basketball', 'auto_awesome'];
                const colorPool = [
                    'bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white',
                    'bg-orange-100 text-orange-600 group-hover:bg-orange-600 group-hover:text-white',
                    'bg-emerald-100 text-emerald-600 group-hover:bg-emerald-600 group-hover:text-white',
                    'bg-purple-100 text-purple-600 group-hover:bg-purple-600 group-hover:text-white',
                    'bg-rose-100 text-rose-600 group-hover:bg-rose-600 group-hover:text-white',
                    'bg-sky-100 text-sky-600 group-hover:bg-sky-600 group-hover:text-white',
                    'bg-amber-100 text-amber-600 group-hover:bg-amber-600 group-hover:text-white',
                    'bg-slate-100 text-slate-600 group-hover:bg-slate-600 group-hover:text-white',
                ];

                const render = () => {
                    const term = el.search.value.trim().toLowerCase();
                    const filtered = state.categories.filter((item) => item.name.toLowerCase().includes(term));

                    el.count.textContent = `Total ${filtered.length} Kategori`;
                    el.list.innerHTML = '';

                    if (!filtered.length) {
                        el.list.innerHTML = '<div class="py-8 text-center text-sm text-slate-400">Tidak ada kategori lain untuk ditampilkan</div>';
                        return;
                    }

                    filtered.forEach((item, index) => {
                        const icon = iconPool[index % iconPool.length];
                        const color = colorPool[index % colorPool.length];
                        const row = document.createElement('div');
                        row.className = 'flex items-center gap-4 bg-white p-4 rounded-xl border border-slate-100 hover:border-primary/30 transition-all group';
                        row.innerHTML = `
                            <div class="flex items-center justify-center rounded-xl shrink-0 size-12 ${color}">
                                <span class="material-symbols-outlined">${icon}</span>
                            </div>
                            <div class="flex flex-col flex-1">
                                <p class="text-base font-semibold text-slate-900">${item.name}</p>
                                <p class="text-xs text-slate-500 line-clamp-1">Kategori produk ${item.name.toLowerCase()}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span class="px-2 py-1 bg-slate-50 rounded text-[10px] font-bold text-slate-600 border border-slate-100">${item.product_count} Produk</span>
                                <span class="material-symbols-outlined text-slate-300 text-sm">chevron_right</span>
                            </div>
                        `;
                        row.addEventListener('click', () => {
                            window.location.href = `/categories/${item.id}/edit`;
                        });
                        el.list.appendChild(row);
                    });
                };

                const loadCategories = async () => {
                    const response = await fetch('/api/v1/categories', { headers });
                    if (!response.ok) {
                        el.list.innerHTML = '<div class="py-8 text-center text-sm text-red-500">Gagal memuat kategori.</div>';
                        return;
                    }
                    const data = await response.json();
                    state.categories = data.categories || [];
                    render();
                };

                el.search.addEventListener('input', render);

                loadCategories();
            })();
        </script>
@endsection
