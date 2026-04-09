@extends('layouts.app')

@php
    $marketingOnlineStoreTenant = \App\Support\TenantContext::get();
    $marketingOnlineStoreUser = auth()->user();
    $marketingOnlineStoreRole = null;

    if (! $marketingOnlineStoreTenant && filled($marketingOnlineStoreUser?->tenant_id)) {
        $marketingOnlineStoreTenant = \App\Models\Tenant::query()->find($marketingOnlineStoreUser->tenant_id);
    }

    if ($marketingOnlineStoreTenant && $marketingOnlineStoreUser) {
        $marketingOnlineStoreRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $marketingOnlineStoreTenant->id, (string) $marketingOnlineStoreUser->id);

        if (! $marketingOnlineStoreRole && filled($marketingOnlineStoreUser->role ?? null)) {
            $marketingOnlineStoreRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $marketingOnlineStoreUser->role);
        }
    }

    abort_unless(in_array($marketingOnlineStoreRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManageOnlineStore = in_array($marketingOnlineStoreRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Integrasi Toko Online - POS SaaS')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('main_class', 'flex-1 overflow-y-auto lg:pl-72')

@section('header')
    <x-page-header
        title="Integrasi Toko Online"
        :breadcrumbs="['Marketing']"
        backUrl="/backoffice/dashboard"
        :showMenuButton="true"
    />
@endsection

@section('content')
    <div class="w-full px-4 py-4 lg:px-8 space-y-6">
        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Order Online Hari Ini</p>
                <p id="online-kpi-orders" class="mt-2 text-3xl font-bold text-primary">0</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Produk Tersinkron</p>
                <p id="online-kpi-products" class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">0 SKU</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Aksi</p>
                @if ($canManageOnlineStore)
                    <button class="mt-2 rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white">Sinkron Sekarang</button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Saluran Penjualan Terhubung</h3>
                @if ($canManageOnlineStore)
                    <button class="text-xs font-bold text-primary hover:underline">+ Tambah Saluran</button>
                @endif
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <p class="font-bold">Shopee Indonesia</p>
                    <p class="mt-1 text-xs text-emerald-600">Terhubung</p>
                    @if ($canManageOnlineStore)
                        <div class="mt-3 flex gap-2">
                            <button class="rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-semibold dark:border-slate-700">Konfigurasi</button>
                            <button class="rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-semibold dark:border-slate-700">Mapping</button>
                        </div>
                    @endif
                </div>
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <p class="font-bold">Tokopedia</p>
                    <p class="mt-1 text-xs text-emerald-600">Terhubung</p>
                    @if ($canManageOnlineStore)
                        <div class="mt-3 flex gap-2">
                            <button class="rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-semibold dark:border-slate-700">Konfigurasi</button>
                            <button class="rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-semibold dark:border-slate-700">Mapping</button>
                        </div>
                    @endif
                </div>
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <p class="font-bold">Website WooCommerce</p>
                    <p class="mt-1 text-xs text-amber-600">Terputus</p>
                    @if ($canManageOnlineStore)
                        <button class="mt-3 rounded-lg bg-primary px-3 py-1.5 text-[11px] font-bold text-white">Hubungkan</button>
                    @endif
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-3">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Log Sinkronisasi Terbaru</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 dark:bg-slate-800/40">
                            <tr>
                                <th class="px-5 py-3">Aktivitas</th>
                                <th class="px-5 py-3">Channel</th>
                                <th class="px-5 py-3">Waktu</th>
                                <th class="px-5 py-3">Data</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody id="online-sync-log" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr>
                                <td class="px-5 py-4 text-sm text-slate-500" colspan="5">Memuat log sinkronisasi...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <aside class="space-y-3 xl:col-span-1">
                @if ($canManageOnlineStore)
                    <button class="w-full rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-primary dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-sm font-bold">Mapping Produk</p>
                        <p class="mt-1 text-xs text-slate-500">Hubungkan SKU antar channel</p>
                    </button>
                    <button class="w-full rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-primary dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-sm font-bold">Konfigurasi</p>
                        <p class="mt-1 text-xs text-slate-500">Atur margin dan pembulatan</p>
                    </button>
                    <button class="w-full rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-primary dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-sm font-bold">Aturan Otomasi</p>
                        <p class="mt-1 text-xs text-slate-500">Jadwal sinkron otomatis</p>
                    </button>
                @endif
            </aside>
        </section>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

            const el = {
                orders: document.getElementById('online-kpi-orders'),
                products: document.getElementById('online-kpi-products'),
                logBody: document.getElementById('online-sync-log'),
            };

            const number = (value) => new Intl.NumberFormat('id-ID').format(Number(value || 0));

            const renderLogs = (rows) => {
                if (!rows.length) {
                    el.logBody.innerHTML = '<tr><td class="px-5 py-4 text-sm text-slate-500" colspan="5">Belum ada log sinkronisasi.</td></tr>';
                    return;
                }

                el.logBody.innerHTML = rows.map((row) => {
                    const statusClass = row.status === 'failed' ? 'text-red-600' : 'text-emerald-600';
                    const statusLabel = row.status === 'failed' ? 'Gagal' : 'Berhasil';
                    return `
                        <tr>
                            <td class="px-5 py-4">${row.activity}</td>
                            <td class="px-5 py-4">${row.channel}</td>
                            <td class="px-5 py-4">${row.time}</td>
                            <td class="px-5 py-4">${row.payload}</td>
                            <td class="px-5 py-4"><span class="${statusClass}">${statusLabel}</span></td>
                        </tr>
                    `;
                }).join('');
            };

            const fallbackLogs = () => renderLogs([
                { activity: 'Sinkron Stok', channel: 'Shopee', time: 'Hari ini 10:45', payload: '1,240 Produk', status: 'ok' },
                { activity: 'Order Masuk', channel: 'Tokopedia', time: 'Hari ini 10:42', payload: '12 Pesanan', status: 'ok' },
                { activity: 'Update Harga', channel: 'Shopee', time: 'Hari ini 09:15', payload: '45 SKU', status: 'failed' },
            ]);

            const load = async () => {
                const [analyticsRes, productsRes] = await Promise.all([
                    fetch('/api/v1/analytics?preset=today', { headers }),
                    fetch('/api/v1/products', { headers }),
                ]);

                if (analyticsRes.ok) {
                    const analytics = await analyticsRes.json();
                    el.orders.textContent = number(analytics?.orders?.today ?? 0);
                }

                if (productsRes.ok) {
                    const productsPayload = await productsRes.json().catch(() => ({}));
                    const products = window.PosUi.unwrapApiList(productsPayload, 'products');
                    el.products.textContent = `${number(products.length)} SKU`;
                }

                // Endpoint khusus integrasi belum tersedia, jadi log diambil dari fallback terstruktur.
                fallbackLogs();
            };

            load().catch(() => fallbackLogs());
        })();
    </script>
@endsection
