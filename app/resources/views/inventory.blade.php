@extends('layouts.app')

@section('page_title', 'Daftar Stok Inventaris')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'relative flex-1 overflow-y-auto pb-24')

@section('header')
    <x-page-header title="Stok Inventaris" :breadcrumbs="['Inventaris']" :showMenuButton="true">
        <x-slot:actions>
            <button class="flex cursor-pointer items-center justify-center rounded-lg h-10 bg-transparent text-slate-900 dark:text-slate-100">
                <span class="material-symbols-outlined text-2xl">notifications</span>
            </button>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <div class="bg-white dark:bg-background-dark px-4 py-4 space-y-4">
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
    <div id="inventory-list" class="flex-1 overflow-y-auto pb-24"></div>
    <button class="absolute bottom-24 right-6 flex items-center justify-center size-14 rounded-full bg-primary text-white shadow-lg shadow-primary/40 z-20">
        <span class="material-symbols-outlined text-3xl">add</span>
    </button>
        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    list: document.getElementById('inventory-list'),
                    search: document.getElementById('search-input'),
                };

                const state = {
                    products: [],
                    inventory: [],
                    term: '',
                };

                const loadBootstrap = async () => {
                    const response = await fetch('/api/v1/bootstrap', { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat data stok.');
                    }
                    return response.json();
                };

                const getQty = (productId) => state.inventory
                    .filter((item) => item.product_id === productId)
                    .reduce((sum, item) => sum + (item.qty_on_hand || 0), 0);

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
                        el.list.innerHTML = '<div class="px-4 py-6 text-sm text-slate-400">Belum ada stok.</div>';
                        return;
                    }

                    el.list.innerHTML = '';
                    products.forEach((product) => {
                        const qty = Math.round(getQty(product.id));
                        const status = qty === 0 ? 'Stok Habis' : qty <= 5 ? 'Stok Menipis' : 'Stok Tersedia';
                        const statusColor = qty === 0 ? 'bg-red-500' : qty <= 5 ? 'bg-amber-500' : 'bg-emerald-500';
                        const qtyColor = qty === 0 ? 'text-red-600' : qty <= 5 ? 'text-amber-600' : 'text-slate-900 dark:text-slate-100';
                        const image = product.image_url || 'https://placehold.co/120x120?text=Produk';

                        const card = document.createElement('div');
                        card.className = 'flex items-center gap-4 bg-white dark:bg-background-dark px-4 py-4 border-b border-slate-50 dark:border-slate-800/50';
                        card.innerHTML = `
                            <div class="bg-center bg-no-repeat aspect-square bg-cover rounded-lg size-14 bg-slate-100 dark:bg-slate-800" style="background-image: url('${image}')"></div>
                            <div class="flex flex-col flex-1">
                                <p class="text-slate-900 dark:text-slate-100 text-base font-semibold leading-snug">${product.name}</p>
                                <p class="text-slate-500 text-xs mt-0.5 uppercase tracking-wide">SKU: ${product.sku || '-'}</p>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span class="size-2 rounded-full ${statusColor}"></span>
                                    <p class="text-slate-500 text-xs font-medium">${status}</p>
                                </div>
                            </div>
                            <div class="flex flex-col items-end shrink-0">
                                <p class="${qtyColor} text-lg font-bold">${qty}</p>
                                <p class="text-slate-400 text-[10px] uppercase font-bold">Unit</p>
                            </div>
                        `;
                        el.list.appendChild(card);
                    });
                };

                el.search.addEventListener('input', (event) => {
                    state.term = event.target.value || '';
                    render();
                });

                (async () => {
                    try {
                        const data = await loadBootstrap();
                        state.products = data.products || [];
                        state.inventory = data.inventory || [];
                        render();
                    } catch (error) {
                        el.list.innerHTML = `<div class="px-4 py-6 text-sm text-red-500">${error.message}</div>`;
                    }
                })();
            })();
        </script>
@endsection
