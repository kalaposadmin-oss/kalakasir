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
    $marketingOnlineStoreTodayOrders = 0;
    $marketingOnlineStoreProductCount = 0;

    if ($marketingOnlineStoreTenant) {
        $marketingOnlineStoreTimezone = $marketingOnlineStoreTenant->timezone ?: config('app.timezone', 'Asia/Jakarta');
        $marketingOnlineStoreStart = now($marketingOnlineStoreTimezone)->startOfDay()->utc();
        $marketingOnlineStoreEnd = now($marketingOnlineStoreTimezone)->endOfDay()->utc();
        $marketingOnlineStoreTodayOrders = \App\Models\OnlineOrder::query()
            ->where('tenant_id', $marketingOnlineStoreTenant->id)
            ->whereBetween('created_at', [$marketingOnlineStoreStart, $marketingOnlineStoreEnd])
            ->count();
        $marketingOnlineStoreProductCount = \App\Models\Product::query()
            ->where('tenant_id', $marketingOnlineStoreTenant->id)
            ->count();
    }
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
                <p class="mt-2 text-3xl font-bold text-primary">{{ number_format($marketingOnlineStoreTodayOrders, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Produk Toko</p>
                <p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">{{ number_format($marketingOnlineStoreProductCount, 0, ',', '.') }} SKU</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Aksi</p>
                @if ($canManageOnlineStore)
                    <button class="mt-2 rounded-lg bg-slate-200 px-4 py-2 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400" type="button" disabled>Integrasi belum aktif</button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Saluran Penjualan</h3>
                @if ($canManageOnlineStore)
                    <button class="text-xs font-bold text-slate-400" type="button" disabled>Tambah Saluran</button>
                @endif
            </div>
            <div class="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                Belum ada saluran penjualan yang dikonfigurasi. Modul integrasi marketplace akan menampilkan channel asli setelah endpoint integrasi tersedia.
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
                    <button class="w-full rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm dark:border-slate-800 dark:bg-slate-900" type="button" disabled>
                        <p class="text-sm font-bold">Mapping Produk</p>
                        <p class="mt-1 text-xs text-slate-500">Tersedia setelah channel aktif</p>
                    </button>
                    <button class="w-full rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm dark:border-slate-800 dark:bg-slate-900" type="button" disabled>
                        <p class="text-sm font-bold">Konfigurasi</p>
                        <p class="mt-1 text-xs text-slate-500">Belum ada konfigurasi</p>
                    </button>
                    <button class="w-full rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm dark:border-slate-800 dark:bg-slate-900" type="button" disabled>
                        <p class="text-sm font-bold">Aturan Otomasi</p>
                        <p class="mt-1 text-xs text-slate-500">Menunggu integrasi aktif</p>
                    </button>
                @endif
            </aside>
        </section>
    </div>

    <script>
        (() => {
            const el = {
                logBody: document.getElementById('online-sync-log'),
            };

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

            const load = () => {
                renderLogs([]);
            };

            load();
        })();
    </script>
@endsection
