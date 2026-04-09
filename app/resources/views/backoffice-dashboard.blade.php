@extends('layouts.app')

@php
    $backofficeDashboardTenant = \App\Support\TenantContext::get();
    $backofficeDashboardUser = auth()->user();
    $backofficeDashboardRole = null;

    if (! $backofficeDashboardTenant && filled($backofficeDashboardUser?->tenant_id)) {
        $backofficeDashboardTenant = \App\Models\Tenant::query()->find($backofficeDashboardUser->tenant_id);
    }

    if ($backofficeDashboardTenant && $backofficeDashboardUser) {
        $backofficeDashboardRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $backofficeDashboardTenant->id, (string) $backofficeDashboardUser->id);

        if (! $backofficeDashboardRole && filled($backofficeDashboardUser->role ?? null)) {
            $backofficeDashboardRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $backofficeDashboardUser->role);
        }
    }

    abort_unless(in_array($backofficeDashboardRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManageDashboardPurchases = in_array($backofficeDashboardRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Dashboard Backoffice')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100')
@section('main_class', 'flex-1 overflow-y-auto pb-8 lg:pl-72')

@section('content')
    <header class="sticky top-0 z-10 h-16 border-b border-slate-200 bg-white/95 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95 lg:px-8">
        <div class="flex h-full items-center justify-between">
            <div>
                <h2 class="text-base font-bold lg:text-lg">Beranda Operasional</h2>
                <p class="hidden text-xs text-slate-500 lg:block">Ringkasan bisnis harian, mingguan, dan bulanan</p>
            </div>
            <a href="/portal/switch/pos?next=/pos/checkout" class="inline-flex items-center gap-2 rounded-xl bg-primary px-3 py-2 text-xs font-bold text-white shadow-lg shadow-primary/30 lg:px-4">
                <span class="material-symbols-outlined text-base">point_of_sale</span>
                Buka Kasir
            </a>
        </div>
    </header>

    <div class="w-full space-y-6 px-4 py-5 lg:px-8">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Penjualan Hari Ini</p>
                <p id="kpi-sales-today" class="mt-2 text-2xl font-bold">Rp 0</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Jumlah Transaksi</p>
                <p id="kpi-orders-today" class="mt-2 text-2xl font-bold">0</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Status Shift</p>
                <p id="kpi-shift-status" class="mt-2 text-lg font-bold text-slate-500">Tidak ada shift aktif</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Stok Menipis</p>
                <p id="kpi-low-stock" class="mt-2 text-2xl font-bold text-red-600">0 Produk</p>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Shortcut Cepat</h3>
                    <a href="/backoffice/settings/subscription" class="text-xs font-semibold text-primary">Status Langganan</a>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <a href="/backoffice/reports/inventory" class="rounded-xl border border-slate-200 px-3 py-4 text-center text-xs font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">Laporan</a>
                    <a href="/backoffice/inventory" class="rounded-xl border border-slate-200 px-3 py-4 text-center text-xs font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">Stok</a>
                    @if ($canManageDashboardPurchases)
                        <a href="/backoffice/purchases/new" class="rounded-xl border border-slate-200 px-3 py-4 text-center text-xs font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">Pembelian</a>
                    @endif
                    <a href="/backoffice/settings/subscription/invoices" class="rounded-xl border border-slate-200 px-3 py-4 text-center text-xs font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">Billing</a>
                </div>
            </div>
            <aside class="rounded-2xl bg-gradient-to-br from-primary to-teal-700 p-6 text-white shadow-xl">
                <p class="text-xs font-bold uppercase tracking-widest text-teal-100">Langganan</p>
                <h3 id="subscription-title" class="mt-2 text-2xl font-bold">Plan -</h3>
                <p id="subscription-meta" class="mt-1 text-sm text-teal-50">Memuat status langganan...</p>
                <a href="/backoffice/settings/subscription" class="mt-5 inline-block rounded-lg bg-white px-4 py-2 text-xs font-bold text-primary">Kelola Langganan</a>
            </aside>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                <h3 class="text-sm font-bold">Transaksi Terbaru</h3>
                <a href="/backoffice/reports/sales" class="text-xs font-semibold text-primary">Lihat semua</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-6 py-3">No. Transaksi</th>
                            <th class="px-6 py-3">Waktu</th>
                            <th class="px-6 py-3 text-right">Total</th>
                            <th class="px-6 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody id="recent-sales-body" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr>
                            <td class="px-6 py-4 text-sm text-slate-500" colspan="4">Memuat transaksi terbaru...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = {
                'Content-Type': 'application/json',
                ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
            };

            const el = {
                salesToday: document.getElementById('kpi-sales-today'),
                ordersToday: document.getElementById('kpi-orders-today'),
                shiftStatus: document.getElementById('kpi-shift-status'),
                lowStock: document.getElementById('kpi-low-stock'),
                subscriptionTitle: document.getElementById('subscription-title'),
                subscriptionMeta: document.getElementById('subscription-meta'),
                recentSalesBody: document.getElementById('recent-sales-body'),
            };

            const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            }).format(Number(value || 0));

            const formatTime = (isoString) => {
                if (!isoString) {
                    return '-';
                }
                const date = new Date(isoString);
                if (Number.isNaN(date.getTime())) {
                    return '-';
                }
                return new Intl.DateTimeFormat('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                }).format(date);
            };

            const normalizeSaleStatus = (status) => {
                const value = String(status || '').toLowerCase();
                if (value === 'completed') {
                    return {
                        label: 'Lunas',
                        className: 'bg-emerald-100 text-emerald-700',
                    };
                }
                if (value === 'pending') {
                    return {
                        label: 'Menunggu',
                        className: 'bg-amber-100 text-amber-700',
                    };
                }
                if (value === 'void') {
                    return {
                        label: 'Void',
                        className: 'bg-rose-100 text-rose-700',
                    };
                }
                return {
                    label: status || '-',
                    className: 'bg-slate-100 text-slate-700',
                };
            };

            const formatTransactionNumber = (id) => {
                const raw = String(id || '').trim();
                if (!raw) {
                    return '#-';
                }
                if (raw.includes('-') && raw.length >= 8) {
                    return `#TRX-${raw.slice(-6).toUpperCase()}`;
                }
                return `#${raw}`;
            };

            const renderRecentSales = (sales = []) => {
                if (!el.recentSalesBody) {
                    return;
                }

                if (!Array.isArray(sales) || sales.length === 0) {
                    el.recentSalesBody.innerHTML = `
                        <tr>
                            <td class="px-6 py-4 text-sm text-slate-500" colspan="4">Belum ada transaksi pada periode ini.</td>
                        </tr>
                    `;
                    return;
                }

                el.recentSalesBody.innerHTML = sales.slice(0, 8).map((sale) => {
                    const status = normalizeSaleStatus(sale.status);
                    return `
                        <tr>
                            <td class="px-6 py-3 font-semibold">${formatTransactionNumber(sale.id)}</td>
                            <td class="px-6 py-3 text-xs text-slate-500">${formatTime(sale.created_at)}</td>
                            <td class="px-6 py-3 text-right font-bold">${formatCurrency(sale.total)}</td>
                            <td class="px-6 py-3 text-center"><span class="rounded-full px-2 py-1 text-[10px] font-bold ${status.className}">${status.label}</span></td>
                        </tr>
                    `;
                }).join('');
            };

            const loadAnalytics = async () => {
                const response = await fetch('/api/v1/analytics?preset=today', { headers });
                if (!response.ok) {
                    return;
                }
                const payload = await response.json().catch(() => ({}));
                const data = window.PosUi.unwrapApiPayload(payload) || {};
                if (el.salesToday) {
                    el.salesToday.textContent = formatCurrency(data?.revenue?.today ?? 0);
                }
                if (el.ordersToday) {
                    el.ordersToday.textContent = `${data?.orders?.today ?? 0}`;
                }
            };

            const loadInventorySummary = async () => {
                const response = await fetch('/api/v1/reports/inventory/summary', { headers });
                if (response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    const data = window.PosUi.unwrapApiPayload(payload) || {};
                    if (el.lowStock) {
                        el.lowStock.textContent = `${data?.low_stock_count ?? 0} Produk`;
                    }
                    return;
                }

                const fallbackResponse = await fetch('/api/v1/inventory-alerts', { headers });
                if (!fallbackResponse.ok) {
                    return;
                }
                const fallbackPayload = await fallbackResponse.json().catch(() => ({}));
                const fallback = window.PosUi.unwrapApiPayload(fallbackPayload) || {};
                if (el.lowStock) {
                    el.lowStock.textContent = `${fallback?.total_alerts ?? 0} Produk`;
                }
            };

            const loadShiftStatus = async () => {
                const response = await fetch('/api/v1/shifts/summary', { headers });
                if (!response.ok) {
                    if (el.shiftStatus) {
                        el.shiftStatus.classList.remove('text-emerald-600');
                        el.shiftStatus.classList.add('text-slate-500');
                        el.shiftStatus.textContent = 'Tidak ada shift aktif';
                    }
                    return;
                }

                const payload = await response.json().catch(() => ({}));
                const data = window.PosUi.unwrapApiPayload(payload) || {};
                const cashierName = data?.cashier?.name || '-';
                if (el.shiftStatus) {
                    el.shiftStatus.classList.remove('text-slate-500');
                    el.shiftStatus.classList.add('text-emerald-600');
                    el.shiftStatus.textContent = `Aktif - ${cashierName}`;
                }
            };

            const loadSubscription = async () => {
                const response = await fetch('/api/v1/subscription/current', { headers });
                if (!response.ok) {
                    return;
                }

                const payload = await response.json().catch(() => ({}));
                const data = window.PosUi.unwrapApiPayload(payload) || {};
                const title = `Plan ${data?.plan ?? '-'} - ${data?.plan_label ?? '-'}`;
                const status = data?.status || '-';
                const pendingPlan = data?.pending_plan;

                if (el.subscriptionTitle) {
                    el.subscriptionTitle.textContent = title;
                }

                if (el.subscriptionMeta) {
                    if (pendingPlan) {
                        const pendingLabel = data?.plan_labels?.[pendingPlan] || pendingPlan;
                        el.subscriptionMeta.textContent = `Status ${status}. Perubahan terjadwal ke ${pendingPlan} (${pendingLabel}).`;
                    } else {
                        el.subscriptionMeta.textContent = `Status ${status}.`;
                    }
                }
            };

            const loadRecentSales = async () => {
                const response = await fetch('/api/v1/sales-history?preset=today', { headers });
                if (!response.ok) {
                    renderRecentSales([]);
                    return;
                }
                const payload = await response.json().catch(() => ({}));
                const data = window.PosUi.unwrapApiPayload(payload) || {};
                renderRecentSales(data?.sales || []);
            };

            Promise.allSettled([
                loadAnalytics(),
                loadInventorySummary(),
                loadShiftStatus(),
                loadSubscription(),
                loadRecentSales(),
            ]);
        })();
    </script>
@endsection
