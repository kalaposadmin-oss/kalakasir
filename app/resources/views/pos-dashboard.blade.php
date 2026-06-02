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

    $showsCashTransactionShortcuts = $posDashboardTenant
        && in_array($posDashboardRole, ['cashier', 'staff', 'warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true)
        && app(\App\Services\SubscriptionService::class)->canAccessFeature($posDashboardTenant, 'finance.cash_transactions');
    $showsOrderOnlineShortcuts = $posDashboardTenant
        && in_array($posDashboardRole, ['supervisor', 'admin', 'owner', 'superadmin'], true);
    $showsPeriodClosingReminder = $posDashboardTenant
        && in_array($posDashboardRole, ['supervisor', 'admin', 'owner', 'superadmin'], true)
        && app(\App\Services\SubscriptionService::class)->canAccessFeature($posDashboardTenant, 'finance.lock_period');
@endphp

@section('page_title', 'Dashboard POS')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-800 dark:text-slate-100 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto pb-24')
@php($bottom_nav = 'dashboard')

@push('head')
    <style>
        .ios-shadow,
        .ios-shadow-sm,
        .pos-home-card {
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.06), 0 1px 6px rgb(15 23 42 / 0.04);
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .pos-home-card {
            border: 1px solid rgb(226 232 240);
            border-radius: 1rem;
            background: #fff;
        }
        .dark .pos-home-card {
            border-color: rgb(30 41 59);
            background: rgb(15 23 42);
        }
        .pos-main-action,
        .pos-side-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            border-radius: .875rem;
            font-size: .8125rem;
            font-weight: 800;
            line-height: 1;
        }
        .pos-main-action {
            min-height: 3.25rem;
            width: 100%;
        }
        .pos-side-action {
            min-height: 2.75rem;
            border: 1px solid rgb(226 232 240);
            background: #fff;
            color: rgb(51 65 85);
        }
        .pos-side-action.hidden {
            display: none;
        }
        .dark .pos-side-action {
            border-color: rgb(30 41 59);
            background: rgb(2 6 23);
            color: rgb(226 232 240);
        }
        .pos-metric-card {
            min-width: 0;
            border: 1px solid rgb(226 232 240);
            border-radius: .875rem;
            background: #fff;
            padding: .75rem;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.05);
        }
        .dark .pos-metric-card {
            border-color: rgb(30 41 59);
            background: rgb(15 23 42);
        }
        .pos-app-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .625rem;
        }
        .pos-app-tile {
            display: flex;
            min-height: 4.75rem;
            min-width: 0;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 1px solid rgb(226 232 240);
            border-radius: .875rem;
            background: #fff;
            padding: .55rem .35rem;
            text-align: center;
            transition: transform 140ms ease, border-color 140ms ease, background-color 140ms ease;
        }
        .pos-app-tile:active {
            transform: scale(.97);
        }
        .dark .pos-app-tile {
            border-color: rgb(30 41 59);
            background: rgb(2 6 23);
        }
        .pos-app-icon {
            display: inline-flex;
            width: 2.25rem;
            height: 2.25rem;
            align-items: center;
            justify-content: center;
            border: 1px solid;
            border-radius: .75rem;
            flex-shrink: 0;
        }
        @media (max-width: 374px) {
            .pos-app-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
    </style>
@endpush

@section('content')
    <header class="sticky top-0 z-30 flex items-center justify-between border-b border-slate-200/60 bg-white/85 px-4 py-3 backdrop-blur-xl dark:border-slate-800/60 dark:bg-background-dark/85">
        <div class="min-w-0">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-primary">POS</p>
            <h1 class="truncate text-lg font-black leading-tight tracking-tight text-slate-900 dark:text-slate-100">Dashboard Kasir</h1>
        </div>
        <div class="flex items-center gap-2">
            <button class="flex size-10 items-center justify-center rounded-full bg-slate-100 text-slate-600 active:opacity-60 dark:bg-slate-800 dark:text-slate-300" type="button" aria-label="Menu" data-menu-trigger>
                <span class="material-symbols-outlined text-[21px]">menu</span>
            </button>
            <form method="POST" action="/logout">
                @csrf
                <button class="flex size-10 items-center justify-center rounded-full bg-slate-100 text-slate-600 active:opacity-60 dark:bg-slate-800 dark:text-slate-300" type="submit" aria-label="Logout">
                    <span class="material-symbols-outlined text-[21px]">logout</span>
                </button>
            </form>
        </div>
    </header>

    <main class="flex flex-col gap-4 p-4">
        @if (session('shift_required'))
            <div id="shift-required-toast" class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800/50 dark:bg-amber-950/30" role="alert">
                <span class="material-symbols-outlined flex-shrink-0 text-xl text-amber-600">warning</span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-amber-800 dark:text-amber-200">Shift Belum Dibuka</p>
                    <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-400">Buka shift dulu sebelum transaksi.</p>
                </div>
                <a href="/pos/shifts/open" class="flex-shrink-0 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-bold text-white active:opacity-80">
                    Buka Shift
                </a>
            </div>
        @endif

        @if ($showsPeriodClosingReminder)
            <div id="pos-period-closing-reminder" class="hidden rounded-2xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800/50 dark:bg-amber-950/30" role="alert">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined flex-shrink-0 text-xl text-amber-600">notification_important</span>
                    <div class="min-w-0 flex-1">
                        <p id="pos-period-closing-title" class="text-sm font-bold text-amber-800 dark:text-amber-200">Periode sebelumnya perlu ditutup</p>
                        <p id="pos-period-closing-message" class="mt-0.5 text-xs leading-relaxed text-amber-700 dark:text-amber-300">Review checklist tutup buku sebelum laporan dipakai.</p>
                    </div>
                    <a href="/backoffice/finance/lock-periods" class="flex-shrink-0 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-bold text-white active:opacity-80">
                        Cek
                    </a>
                </div>
            </div>
        @endif

        <section class="pos-home-card overflow-hidden">
            <div class="p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2">
                        <span id="shift-dot" class="flex size-2.5 rounded-full bg-amber-400"></span>
                        <div class="min-w-0">
                            <p id="shift-status" class="truncate text-xs font-black uppercase tracking-wide text-slate-700 dark:text-slate-200">Status: Shift Tidak Aktif</p>
                            <p id="shift-started" class="text-[10px] font-bold text-slate-400">Dimulai: -</p>
                        </div>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">Hari ini</span>
                </div>

                <a href="/pos/checkout" class="pos-main-action bg-primary text-white shadow-sm shadow-primary/20">
                    <span class="material-symbols-outlined text-[22px]">point_of_sale</span>
                    Mulai Transaksi
                </a>

                <div class="mt-2 grid grid-cols-2 gap-2">
                    <a id="open-shift-banner" href="/pos/shifts/open" class="pos-side-action hidden bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-300">
                        <span class="material-symbols-outlined text-[18px]">schedule</span>
                        Buka Shift
                    </a>
                    <a id="close-shift-banner" href="/pos/shifts/close" class="pos-side-action hidden bg-rose-50 text-rose-600 dark:bg-rose-950/20 dark:text-rose-300">
                        <span class="material-symbols-outlined text-[18px]">lock_open</span>
                        Tutup Shift
                    </a>
                    <a href="/pos/history" class="pos-side-action">
                        <span class="material-symbols-outlined text-[18px] text-primary">history</span>
                        Riwayat
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-2 border-t border-slate-100 dark:border-slate-800">
                <div class="border-r border-b border-slate-100 p-3 dark:border-slate-800">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Kas Awal</p>
                    <p id="opening-cash" class="mt-1 truncate text-sm font-black text-slate-900 dark:text-slate-100">Rp 0</p>
                </div>
                <div class="border-b border-slate-100 p-3 dark:border-slate-800">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Cash</p>
                    <p id="shift-cash-sales" class="mt-1 truncate text-sm font-black text-slate-900 dark:text-slate-100">Rp 0</p>
                </div>
                <div class="border-r border-slate-100 p-3 dark:border-slate-800">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Piutang</p>
                    <p id="shift-receivable-cash" class="mt-1 truncate text-sm font-black text-slate-900 dark:text-slate-100">Rp 0</p>
                </div>
                <div class="p-3">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Total Kas</p>
                    <p id="shift-sales" class="mt-1 truncate text-sm font-black text-primary">Rp 0</p>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-3 gap-2">
            <div class="pos-metric-card">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Penjualan</p>
                <p id="kpi-sales" class="mt-1 truncate text-sm font-black tracking-tight text-slate-900 dark:text-white">Rp 0</p>
            </div>
            <div class="pos-metric-card">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Transaksi</p>
                <p id="kpi-orders" class="mt-1 truncate text-sm font-black tracking-tight text-slate-900 dark:text-white">0</p>
            </div>
            <div class="pos-metric-card">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Rata-rata</p>
                <p id="kpi-average" class="mt-1 truncate text-sm font-black tracking-tight text-slate-900 dark:text-white">Rp 0</p>
            </div>
        </section>

        <section class="pos-home-card p-4">
            <div class="mb-3 flex items-end justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Aplikasi</p>
                    <h2 class="mt-1 text-sm font-black text-slate-900 dark:text-slate-100">Launcher Kasir</h2>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Cepat pakai</span>
            </div>

            <div class="pos-app-grid">
                <a href="/pos/checkout" class="pos-app-tile bg-primary text-white">
                    <span class="pos-app-icon border-white/25 bg-white/15 text-white">
                        <span class="material-symbols-outlined text-[21px]">add_shopping_cart</span>
                    </span>
                    <span class="mt-1.5 w-full truncate text-[11px] font-black">Transaksi</span>
                </a>
                <a href="/pos/history" class="pos-app-tile">
                    <span class="pos-app-icon border-sky-100 bg-sky-50 text-sky-600 dark:border-sky-900/40 dark:bg-sky-950/25 dark:text-sky-300">
                        <span class="material-symbols-outlined text-[21px]">history</span>
                    </span>
                    <span class="mt-1.5 w-full truncate text-[11px] font-black text-slate-800 dark:text-slate-100">Riwayat</span>
                </a>
                <a href="/pos/receivables" class="pos-app-tile">
                    <span class="pos-app-icon border-amber-100 bg-amber-50 text-amber-600 dark:border-amber-900/40 dark:bg-amber-950/25 dark:text-amber-300">
                        <span class="material-symbols-outlined text-[21px]">request_quote</span>
                    </span>
                    <span class="mt-1.5 w-full truncate text-[11px] font-black text-slate-800 dark:text-slate-100">Piutang</span>
                </a>
                @if ($showsCashTransactionShortcuts)
                    <a href="/pos/cash-in" class="pos-app-tile">
                        <span class="pos-app-icon border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/40 dark:bg-emerald-950/25 dark:text-emerald-300">
                            <span class="material-symbols-outlined text-[21px]">payments</span>
                        </span>
                        <span class="mt-1.5 w-full truncate text-[11px] font-black text-slate-800 dark:text-slate-100">Kas</span>
                    </a>
                @endif
                @if ($showsOrderOnlineShortcuts)
                    <a href="/monitor/orders" class="pos-app-tile">
                        <span class="pos-app-icon border-sky-100 bg-sky-50 text-sky-600 dark:border-sky-900/40 dark:bg-sky-950/25 dark:text-sky-300">
                            <span class="material-symbols-outlined text-[21px]">storefront</span>
                        </span>
                        <span class="mt-1.5 w-full truncate text-[11px] font-black text-slate-800 dark:text-slate-100">Online</span>
                    </a>
                    <a href="/portal/switch/backoffice?next=%2Fbackoffice%2Fsettings%2Fpayment-settings" class="pos-app-tile">
                        <span class="pos-app-icon border-orange-100 bg-orange-50 text-primary dark:border-orange-900/40 dark:bg-orange-950/25">
                            <span class="material-symbols-outlined text-[21px]">payments</span>
                        </span>
                        <span class="mt-1.5 w-full truncate text-[11px] font-black text-slate-800 dark:text-slate-100">Bayar</span>
                    </a>
                @endif
                <a id="open-shift-quick" href="/pos/shifts/open" class="pos-app-tile">
                    <span class="pos-app-icon border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/40 dark:bg-emerald-950/25 dark:text-emerald-300">
                        <span class="material-symbols-outlined text-[21px]">schedule</span>
                    </span>
                    <span class="mt-1.5 w-full truncate text-[11px] font-black text-slate-800 dark:text-slate-100">Buka</span>
                </a>
                <a id="close-shift-quick" href="/pos/shifts/close" class="pos-app-tile">
                    <span class="pos-app-icon border-rose-100 bg-rose-50 text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/25 dark:text-rose-300">
                        <span class="material-symbols-outlined text-[21px]">power_settings_new</span>
                    </span>
                    <span class="mt-1.5 w-full truncate text-[11px] font-black text-slate-800 dark:text-slate-100">Tutup</span>
                </a>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-3">
            <div class="pos-home-card p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-slate-400">
                        <span class="material-symbols-outlined text-sm text-primary">stars</span>
                        Terlaris
                    </h2>
                    <span class="text-[10px] font-bold text-slate-400">Bulan ini</span>
                </div>
                <ul id="top-products" class="space-y-2">
                    <li class="text-sm text-slate-500">Memuat data...</li>
                </ul>
            </div>
            <div class="rounded-2xl border border-orange-100 bg-orange-50/70 p-4 dark:border-orange-900/30 dark:bg-orange-950/10">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-orange-500">
                        <span class="material-symbols-outlined text-sm">warning</span>
                        Stok Menipis
                    </h2>
                    <a href="/pos/inventory" class="text-[10px] font-black text-orange-600">Cek stok</a>
                </div>
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
                const cashSalesEl = document.getElementById('shift-cash-sales');
                const receivableCashEl = document.getElementById('shift-receivable-cash');
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

                    if (cashSalesEl) cashSalesEl.textContent = toCurrency(summary.cash_sales || 0);
                    if (receivableCashEl) receivableCashEl.textContent = toCurrency(summary.receivable_cash || 0);

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
                if (cashSalesEl) cashSalesEl.textContent = toCurrency(0);
                if (receivableCashEl) receivableCashEl.textContent = toCurrency(0);
                closeBanner.classList.add('hidden');
                openBanner.classList.remove('hidden');
                disableCard(openQuick, false);
                disableCard(closeQuick, true);
            };

            const loadShift = async () => {
                try {
                    const response = await window.PosUi.fetchWithTimeout('/api/v1/shifts/summary', { headers }, 10000);
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

            const renderTopProducts = (rows) => {
                const topProducts = document.getElementById('top-products');
                if (!rows.length) {
                    topProducts.innerHTML = '<li class="text-sm text-slate-500">Belum ada penjualan bulan ini.</li>';
                    return;
                }

                topProducts.innerHTML = rows.map((row, index) => `
                    <li class="flex justify-between items-center text-sm ${index < rows.length - 1 ? 'border-b border-slate-50 dark:border-slate-800 pb-2' : ''}">
                        <div class="min-w-0 pr-3">
                            <p class="truncate text-slate-700 dark:text-slate-300">${row.name || '-'}</p>
                            <p class="text-[11px] text-slate-400">${toCurrency(row.revenue || 0)}</p>
                        </div>
                        <span class="font-bold whitespace-nowrap">${Number(row.qty || 0)} pcs</span>
                    </li>
                `).join('');
            };

            const renderLowStock = (rows) => {
                const lowStock = document.getElementById('low-stock');
                if (!rows.length) {
                    lowStock.innerHTML = '<li class="text-sm text-slate-500">Stok aman.</li>';
                    return;
                }

                lowStock.innerHTML = rows.map((row) => `
                    <li class="flex justify-between items-center text-sm">
                        <div class="min-w-0 pr-3">
                            <p class="truncate text-slate-700 dark:text-slate-300">${row.name || '-'}</p>
                            <p class="text-[11px] text-slate-400">Min. ${Number(row.min_stock || 0)}</p>
                        </div>
                        <span class="text-orange-600 font-bold whitespace-nowrap">Sisa ${Number(row.qty_on_hand || 0)}</span>
                    </li>
                `).join('');
            };

            const runWhenIdle = (task) => {
                if ('requestIdleCallback' in window) {
                    window.requestIdleCallback(task, { timeout: 1500 });
                    return;
                }

                window.setTimeout(task, 250);
            };

            const renderDashboardSummary = (data) => {
                const sales = Number(data?.sales_today || 0);
                const orders = Number(data?.orders_today || 0);
                const average = Number(data?.average_order_today ?? (orders > 0 ? (sales / orders) : 0));

                document.getElementById('kpi-sales').textContent = toCurrency(sales);
                document.getElementById('kpi-orders').textContent = new Intl.NumberFormat('id-ID').format(orders);
                document.getElementById('kpi-average').textContent = toCurrency(average);
            };

            const loadDashboardSummary = async () => {
                try {
                    const data = await window.PosUi.fetchJson('/api/v1/pos/dashboard?sections=summary', {
                        headers,
                    }, {
                        timeoutMs: 7000,
                        ttlMs: 15000,
                        cacheKey: 'pos-dashboard-summary',
                        unwrap: true,
                    });

                    renderDashboardSummary(data);
                } catch (_) {
                    // Keep fallback KPI values.
                }
            };

            const loadPeriodClosingReminder = async () => {
                const reminder = document.getElementById('pos-period-closing-reminder');
                if (!reminder) {
                    return;
                }

                try {
                    const data = await window.PosUi.fetchJson('/api/v1/lock-periods/assistant', {
                        headers,
                    }, {
                        timeoutMs: 8000,
                        ttlMs: 30000,
                        cacheKey: 'pos-dashboard-period-closing-assistant',
                        unwrap: true,
                    });
                    const severity = data?.status?.severity || 'safe';
                    if (severity === 'safe') {
                        reminder.classList.add('hidden');
                        return;
                    }

                    const title = document.getElementById('pos-period-closing-title');
                    const message = document.getElementById('pos-period-closing-message');
                    if (title) {
                        title.textContent = data?.status?.title || 'Periode sebelumnya perlu ditutup';
                    }
                    if (message) {
                        message.textContent = data?.status?.message || 'Review checklist tutup buku sebelum laporan dipakai.';
                    }
                    reminder.classList.remove('hidden');
                } catch (_) {
                    reminder.classList.add('hidden');
                }
            };

            const loadDashboardWidgets = async () => {
                try {
                    const data = await window.PosUi.fetchJson('/api/v1/pos/dashboard?sections=top_products,low_stock&top_limit=3&low_stock_limit=2', {
                        headers,
                    }, {
                        timeoutMs: 10000,
                        ttlMs: 60000,
                        cacheKey: 'pos-dashboard-widgets',
                        unwrap: true,
                    });
                    renderTopProducts(Array.isArray(data?.top_products) ? data.top_products : []);
                    renderLowStock(Array.isArray(data?.low_stock_items) ? data.low_stock_items : []);
                } catch (_) {
                    renderTopProducts([]);
                    renderLowStock([]);
                }
            };

            Promise.allSettled([loadShift(), loadDashboardSummary(), loadPeriodClosingReminder()]).then(() => {
                runWhenIdle(() => loadDashboardWidgets());
            });
        })();
    </script>
@endpush
