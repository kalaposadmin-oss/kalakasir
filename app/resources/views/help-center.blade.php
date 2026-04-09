@extends('layouts.app')

@php
    $helpCenterTenant = \App\Support\TenantContext::get();
    $helpCenterUser = auth()->user();
    $helpCenterRole = null;

    if (! $helpCenterTenant && filled($helpCenterUser?->tenant_id)) {
        $helpCenterTenant = \App\Models\Tenant::query()->find($helpCenterUser->tenant_id);
    }

    if ($helpCenterTenant && $helpCenterUser) {
        $helpCenterRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $helpCenterTenant->id, (string) $helpCenterUser->id);

        if (! $helpCenterRole && filled($helpCenterUser->role ?? null)) {
            $helpCenterRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $helpCenterUser->role);
        }
    }

    abort_unless(in_array($helpCenterRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);
@endphp

@section('page_title', 'Pusat Bantuan - POS SaaS')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('main_class', 'flex-1 overflow-y-auto lg:pl-72')

@section('header')
    <x-page-header
        title="Pusat Bantuan"
        :breadcrumbs="['Bantuan']"
        backUrl="/backoffice/dashboard"
        :showMenuButton="true"
    />
@endsection

@section('content')
    <div class="w-full px-4 py-4 lg:px-8 space-y-6">
        <section class="rounded-2xl bg-primary p-6 text-white">
            <h3 class="text-2xl font-bold">Apa yang bisa kami bantu?</h3>
            <p class="mt-2 text-sm text-teal-100">Cari panduan fitur dan FAQ operasional.</p>
            <input id="help-search" type="text" placeholder="Cari topik bantuan" class="mt-4 w-full rounded-xl border-none bg-white px-4 py-3 text-sm text-slate-900" />
        </section>

        <section id="help-category-cards" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div data-help-category="memulai" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-primary dark:border-slate-800 dark:bg-slate-900">
                <p class="font-bold">Memulai</p>
                <p class="mt-1 text-xs text-slate-500">Panduan setup toko pertama.</p>
            </div>
            <div data-help-category="inventori" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-primary dark:border-slate-800 dark:bg-slate-900">
                <p class="font-bold">Inventori</p>
                <p class="mt-1 text-xs text-slate-500">Stok, kategori, dan varian produk.</p>
            </div>
            <div data-help-category="penjualan" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-primary dark:border-slate-800 dark:bg-slate-900">
                <p class="font-bold">Penjualan</p>
                <p class="mt-1 text-xs text-slate-500">Transaksi, struk, dan pembayaran.</p>
            </div>
            <div data-help-category="billing" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-primary dark:border-slate-800 dark:bg-slate-900">
                <p class="font-bold">Billing</p>
                <p class="mt-1 text-xs text-slate-500">Langganan dan fitur premium.</p>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-3 xl:col-span-2">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Artikel Populer</h3>
                <div id="help-article-list" class="space-y-3">
                    <a class="block rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800/40" href="#">Cara Menghubungkan Printer Bluetooth</a>
                </div>
            </div>
            <aside class="rounded-2xl bg-slate-900 p-5 text-white">
                <h4 class="font-bold">Dokumentasi Teknis</h4>
                <p class="mt-2 text-xs text-slate-300">Pelajari fitur secara mendalam lewat dokumentasi lengkap.</p>
                <button class="mt-4 w-full rounded-lg bg-primary px-3 py-2 text-xs font-bold">Buka Dokumentasi</button>
            </aside>
        </section>
    </div>

    <script>
        (() => {
            const el = {
                search: document.getElementById('help-search'),
                list: document.getElementById('help-article-list'),
                categories: document.querySelectorAll('[data-help-category]'),
            };

            const articles = [
                { title: 'Cara Menghubungkan Printer Bluetooth', category: 'penjualan', eta: '5 menit baca' },
                { title: 'Manajemen Stok Minimum & Notifikasi', category: 'inventori', eta: '3 menit baca' },
                { title: 'Menerima Pembayaran via QRIS', category: 'penjualan', eta: '4 menit baca' },
                { title: 'Setup Toko Pertama Kali', category: 'memulai', eta: '6 menit baca' },
                { title: 'Aktivasi dan Penagihan Langganan', category: 'billing', eta: '4 menit baca' },
            ];

            let activeCategory = 'all';

            const render = () => {
                const term = (el.search?.value || '').trim().toLowerCase();
                const filtered = articles.filter((article) => {
                    const byCategory = activeCategory === 'all' || article.category === activeCategory;
                    const byTerm = !term || article.title.toLowerCase().includes(term);
                    return byCategory && byTerm;
                });

                if (!filtered.length) {
                    el.list.innerHTML = '<div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900">Tidak ada artikel yang cocok.</div>';
                    return;
                }

                el.list.innerHTML = filtered.map((article) => `
                    <a class="block rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800/40" href="#">
                        <p class="font-semibold">${article.title}</p>
                        <p class="mt-1 text-xs text-slate-500">${article.eta}</p>
                    </a>
                `).join('');
            };

            el.search?.addEventListener('input', render);
            el.categories.forEach((card) => {
                card.addEventListener('click', () => {
                    activeCategory = card.dataset.helpCategory || 'all';
                    el.categories.forEach((node) => node.classList.remove('border-primary'));
                    card.classList.add('border-primary');
                    render();
                });
            });

            render();
        })();
    </script>
@endsection
