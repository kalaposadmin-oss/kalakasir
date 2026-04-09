@extends('layouts.app')

@php
    $showMenu = true;
    $posDashboardTenant = \App\Support\TenantContext::get();
    $posDashboardUser = auth()->user();
    $posDashboardRole = null;

    if (! $posDashboardTenant && filled($posDashboardUser?->tenant_id)) {
        $posDashboardTenant = \App\Models\Tenant::query()->find($posDashboardUser->tenant_id);
    }

    if ($posDashboardTenant && $posDashboardUser) {
        $posDashboardRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $posDashboardTenant->id, (string) $posDashboardUser->id);
    }

    $showsElevatedShortcuts = in_array($posDashboardRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Dashboard POS')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-800 dark:text-slate-100 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto pb-24')
@php($bottom_nav = 'dashboard')

@push('head')
    <style>
        .ios-shadow {
            box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.05);
        }
        .ios-shadow-sm {
            box-shadow: 0 2px 8px -1px rgba(0, 0, 0, 0.04);
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
    </style>
@endpush

@section('content')
    <header class="sticky top-0 z-30 flex items-center justify-between bg-white/70 px-4 py-4 backdrop-blur-xl border-b border-slate-200/50 dark:bg-background-dark/80 dark:border-slate-800/50">
        <div>
            <h1 class="text-lg font-bold leading-tight tracking-tight text-slate-900 dark:text-slate-100">Dashboard Kasir</h1>
            <p class="text-[11px] text-slate-500 font-medium">Akses cepat transaksi harian</p>
        </div>
        <div class="flex items-center gap-2">
            <button class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600 active:opacity-60 dark:bg-slate-800 dark:text-slate-300" type="button" aria-label="Menu" data-menu-trigger>
                <span class="material-symbols-outlined">menu</span>
            </button>
            <form method="POST" action="/logout">
                @csrf
                <button class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600 active:opacity-60 dark:bg-slate-800 dark:text-slate-300" type="submit" aria-label="Logout">
                    <span class="material-symbols-outlined">logout</span>
                </button>
            </form>
        </div>
    </header>

    <main class="flex flex-col gap-5 p-4">
        <section class="rounded-2xl bg-white p-5 ios-shadow border border-slate-100 dark:bg-slate-900 dark:border-slate-800">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span id="shift-dot" class="flex h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                    <span id="shift-status" class="text-sm font-bold text-slate-700 uppercase tracking-wide dark:text-slate-200">Status: Shift Tidak Aktif</span>
                </div>
                <span id="shift-started" class="text-[10px] font-bold text-slate-400">Dimulai: -</span>
            </div>
            <div class="space-y-2 mb-5">
                <div class="flex justify-between items-center text-sm">
                    <span class="text-slate-500">Kas Awal</span>
                    <span id="opening-cash" class="font-semibold">Rp 0</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500 text-sm">Penjualan Shift (Kas)</span>
                    <span id="shift-sales" class="text-lg font-bold text-primary tracking-tight">Rp 0</span>
                </div>
            </div>
            <a id="close-shift-banner" href="/pos/shifts/close" class="w-full py-3.5 bg-rose-50 text-rose-600 rounded-xl font-bold text-sm flex items-center justify-center gap-2 active:scale-[0.98] transition-transform dark:bg-rose-950/20 dark:text-rose-400 hidden">
                <span class="material-symbols-outlined text-[20px]">lock_open</span>
                Tutup Shift
            </a>
            <a id="open-shift-banner" href="/pos/shifts/open" class="w-full py-3.5 bg-emerald-50 text-emerald-700 rounded-xl font-bold text-sm flex items-center justify-center gap-2 active:scale-[0.98] transition-transform dark:bg-emerald-950/20 dark:text-emerald-300 hidden">
                <span class="material-symbols-outlined text-[20px]">schedule</span>
                Buka Shift
            </a>
        </section>

        <section>
            <div class="flex overflow-x-auto gap-3 pb-2 no-scrollbar">
                <div class="min-w-[145px] flex-shrink-0 flex flex-col gap-1 rounded-2xl bg-white p-4 ios-shadow border border-slate-100 dark:bg-slate-900 dark:border-slate-800">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Penjualan Hari Ini</p>
                    <p id="kpi-sales" class="text-base font-bold tracking-tight">Rp 0</p>
                </div>
                <div class="min-w-[100px] flex-shrink-0 flex flex-col gap-1 rounded-2xl bg-white p-4 ios-shadow border border-slate-100 dark:bg-slate-900 dark:border-slate-800">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Transaksi</p>
                    <p id="kpi-orders" class="text-base font-bold tracking-tight">0</p>
                </div>
                <div class="min-w-[120px] flex-shrink-0 flex flex-col gap-1 rounded-2xl bg-white p-4 ios-shadow border border-slate-100 dark:bg-slate-900 dark:border-slate-800">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Rata-rata</p>
                    <p id="kpi-average" class="text-base font-bold tracking-tight">Rp 0</p>
                </div>
            </div>
        </section>

        <section>
            <h2 class="mb-3 px-1 text-[11px] font-bold uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500">Aksi Cepat</h2>
            <div class="grid grid-cols-2 gap-3">
                <a href="/pos/checkout" class="flex flex-col items-center justify-center gap-3 rounded-2xl bg-primary p-6 text-white ios-shadow active:opacity-90">
                    <span class="material-symbols-outlined text-2xl font-bold">add_shopping_cart</span>
                    <span class="text-xs font-bold">Mulai Transaksi</span>
                </a>
                <a href="/pos/history" class="flex flex-col items-center justify-center gap-3 rounded-2xl bg-white p-6 ios-shadow border border-slate-100 active:bg-slate-50 dark:bg-slate-900 dark:border-slate-800">
                    <span class="material-symbols-outlined text-primary text-2xl">history</span>
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-100">Riwayat Penjualan</span>
                </a>
                <a id="open-shift-quick" href="/pos/shifts/open" class="flex flex-col items-center justify-center gap-3 rounded-2xl bg-white p-6 ios-shadow border border-slate-100 active:bg-slate-50 dark:bg-slate-900 dark:border-slate-800">
                    <span class="material-symbols-outlined text-primary text-2xl">schedule</span>
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-100">Buka Shift</span>
                </a>
                <a id="close-shift-quick" href="/pos/shifts/close" class="flex flex-col items-center justify-center gap-3 rounded-2xl bg-white p-6 ios-shadow border border-slate-100 active:bg-slate-50 dark:bg-slate-900 dark:border-slate-800">
                    <span class="material-symbols-outlined text-rose-400 text-2xl">power_settings_new</span>
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-100">Tutup Shift</span>
                </a>
            </div>
        </section>

        @include('partials.owner-pos-panel')

        <section class="grid grid-cols-1 gap-4">
            <div class="rounded-2xl bg-white p-4 ios-shadow border border-slate-100 dark:bg-slate-900 dark:border-slate-800">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">stars</span> Produk Terlaris
                </h2>
                <ul id="top-products" class="space-y-2">
                    <li class="text-sm text-slate-500">Memuat data...</li>
                </ul>
            </div>
            <div class="rounded-2xl bg-orange-50/50 p-4 border border-orange-100 dark:bg-orange-950/10 dark:border-orange-900/30">
                <h2 class="text-xs font-bold uppercase tracking-wider text-orange-500 mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">warning</span> Stok Menipis
                </h2>
                <ul id="low-stock" class="space-y-2">
                    <li class="text-sm text-slate-500">Memuat data...</li>
                </ul>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

            const toCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Number(value || 0));

            const disableCard = (element, disabled) => {
                if (!element) {
                    return;
                }
                element.classList.toggle('opacity-50', disabled);
                element.classList.toggle('cursor-not-allowed', disabled);
                if (disabled) {
                    element.setAttribute('aria-disabled', 'true');
                    element.addEventListener('click', preventClickOnce);
                } else {
                    element.removeAttribute('aria-disabled');
                    element.removeEventListener('click', preventClickOnce);
                }
            };

            const preventClickOnce = (event) => {
                event.preventDefault();
            };

            const setShiftState = (isActive, summary = null) => {
                const status = document.getElementById('shift-status');
                const started = document.getElementById('shift-started');
                const dot = document.getElementById('shift-dot');
                const openingCash = document.getElementById('opening-cash');
                const shiftSales = document.getElementById('shift-sales');
                const closeBanner = document.getElementById('close-shift-banner');
                const openBanner = document.getElementById('open-shift-banner');
                const openQuick = document.getElementById('open-shift-quick');
                const closeQuick = document.getElementById('close-shift-quick');

                if (isActive && summary) {
                    status.textContent = 'Status: Shift Aktif';
                    dot.classList.remove('bg-amber-400');
                    dot.classList.add('bg-primary', 'animate-pulse');
                    started.textContent = `Dimulai: ${summary.shift?.opened_at || '-'}`;
                    openingCash.textContent = toCurrency(summary.shift?.opening_cash || 0);
                    shiftSales.textContent = toCurrency(summary.expected_cash || 0);
                    closeBanner.classList.remove('hidden');
                    openBanner.classList.add('hidden');
                    disableCard(openQuick, true);
                    disableCard(closeQuick, false);
                    return;
                }

                status.textContent = 'Status: Shift Tidak Aktif';
                dot.classList.remove('bg-primary', 'animate-pulse');
                dot.classList.add('bg-amber-400');
                started.textContent = 'Dimulai: -';
                openingCash.textContent = toCurrency(0);
                shiftSales.textContent = toCurrency(0);
                closeBanner.classList.add('hidden');
                openBanner.classList.remove('hidden');
                disableCard(openQuick, false);
                disableCard(closeQuick, true);
            };

            const loadShift = async () => {
                try {
                    const response = await fetch('/api/v1/shifts/summary', { headers });
                    if (response.status === 404) {
                        setShiftState(false);
                        return;
                    }
                    if (!response.ok) {
                        setShiftState(false);
                        return;
                    }
                    const payload = await response.json().catch(() => ({}));
                    const data = window.PosUi.unwrapApiPayload(payload) || {};
                    setShiftState(true, data);
                } catch (_) {
                    setShiftState(false);
                }
            };

            const loadAnalytics = async () => {
                try {
                    const response = await fetch('/api/v1/analytics?preset=today', { headers });
                    if (!response.ok) {
                        return;
                    }
                    const payload = await response.json().catch(() => ({}));
                    const data = window.PosUi.unwrapApiPayload(payload) || {};
                    const sales = Number(data?.revenue?.today || 0);
                    const orders = Number(data?.orders?.today || 0);
                    const average = orders > 0 ? (sales / orders) : 0;

                    document.getElementById('kpi-sales').textContent = toCurrency(sales);
                    document.getElementById('kpi-orders').textContent = new Intl.NumberFormat('id-ID').format(orders);
                    document.getElementById('kpi-average').textContent = toCurrency(average);

                    const topProducts = document.getElementById('top-products');
                    const rows = Array.isArray(data?.top_products) ? data.top_products.slice(0, 3) : [];
                    if (!rows.length) {
                        topProducts.innerHTML = '<li class="text-sm text-slate-500">Belum ada penjualan hari ini.</li>';
                    } else {
                        topProducts.innerHTML = rows.map((row, index) => `
                            <li class="flex justify-between items-center text-sm ${index < rows.length - 1 ? 'border-b border-slate-50 dark:border-slate-800 pb-2' : ''}">
                                <span class="text-slate-700 dark:text-slate-300">${row.name || '-'}</span>
                                <span class="font-bold">${Number(row.qty || 0)} pcs</span>
                            </li>
                        `).join('');
                    }
                } catch (_) {
                    // Keep fallback placeholders.
                }
            };

            const loadLowStock = async () => {
                try {
                    const response = await fetch('/api/v1/reports/inventory/low-stock?limit=2', { headers });
                    const lowStock = document.getElementById('low-stock');
                    if (!response.ok) {
                        lowStock.innerHTML = '<li class="text-sm text-slate-500">Data stok menipis belum tersedia.</li>';
                        return;
                    }
                    const payload = await response.json().catch(() => ({}));
                    const data = window.PosUi.unwrapApiPayload(payload) || {};
                    const rows = Array.isArray(data?.items) ? data.items : [];
                    if (!rows.length) {
                        lowStock.innerHTML = '<li class="text-sm text-slate-500">Stok aman.</li>';
                        return;
                    }
                    lowStock.innerHTML = rows.map((row) => `
                        <li class="flex justify-between items-center text-sm">
                            <span class="text-slate-700 dark:text-slate-300">${row.name || '-'}</span>
                            <span class="text-orange-600 font-bold">Sisa ${Number(row.qty_on_hand || 0)}</span>
                        </li>
                    `).join('');
                } catch (_) {
                    // Keep fallback placeholders.
                }
            };

            Promise.all([loadShift(), loadAnalytics(), loadLowStock()]);
        })();
    </script>
@endpush
