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
    $canAccessDashboardPurchases = in_array($backofficeDashboardRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true);
    $canAccessDashboardCustomers = in_array($backofficeDashboardRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true);
    $canManageDashboardSettings = in_array($backofficeDashboardRole, ['supervisor', 'admin', 'owner', 'superadmin'], true);
    $dashboardSubscription = app(\App\Services\SubscriptionService::class);
    $dashboardCanFeature = static function (string $featureKey) use ($backofficeDashboardTenant, $dashboardSubscription): bool {
        if (! $backofficeDashboardTenant || $featureKey === '') {
            return false;
        }

        return $dashboardSubscription->canAccessFeature($backofficeDashboardTenant, $featureKey);
    };
    $dashboardToneClasses = [
        'orange' => 'border-orange-100 bg-orange-50 text-orange-600 dark:border-orange-900/40 dark:bg-orange-950/25 dark:text-orange-300',
        'green' => 'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/40 dark:bg-emerald-950/25 dark:text-emerald-300',
        'blue' => 'border-sky-100 bg-sky-50 text-sky-600 dark:border-sky-900/40 dark:bg-sky-950/25 dark:text-sky-300',
        'indigo' => 'border-indigo-100 bg-indigo-50 text-indigo-600 dark:border-indigo-900/40 dark:bg-indigo-950/25 dark:text-indigo-300',
        'amber' => 'border-amber-100 bg-amber-50 text-amber-600 dark:border-amber-900/40 dark:bg-amber-950/25 dark:text-amber-300',
        'rose' => 'border-rose-100 bg-rose-50 text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/25 dark:text-rose-300',
        'slate' => 'border-slate-200 bg-slate-100 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
    ];
    $dashboardLauncherItems = array_values(array_filter([
        ($dashboardCanFeature('sale.pos') ? ['href' => '/portal/switch/pos?next=/pos/checkout', 'icon' => 'point_of_sale', 'label' => 'Kasir', 'meta' => 'Jual', 'tone' => 'orange'] : null),
        ($dashboardCanFeature('sale.pos') && in_array($backofficeDashboardRole, ['supervisor', 'admin', 'owner', 'superadmin'], true) ? ['href' => '/backoffice/online-orders', 'icon' => 'storefront', 'label' => 'Online', 'meta' => 'Order', 'tone' => 'blue'] : null),
        ($dashboardCanFeature('product.list') ? ['href' => '/backoffice/products', 'icon' => 'inventory_2', 'label' => 'Produk', 'meta' => 'Katalog', 'tone' => 'indigo'] : null),
        ($canManageDashboardPurchases && $dashboardCanFeature('product.list') ? ['href' => '/backoffice/products/new', 'icon' => 'add_box', 'label' => 'Tambah', 'meta' => 'Produk', 'tone' => 'green'] : null),
        ($canAccessDashboardCustomers && $dashboardCanFeature('customer.manage') ? ['href' => '/backoffice/customers', 'icon' => 'groups', 'label' => 'Pelanggan', 'meta' => 'Data', 'tone' => 'slate'] : null),
        ($dashboardCanFeature('inventory.stock') ? ['href' => '/backoffice/inventory', 'icon' => 'warehouse', 'label' => 'Stok', 'meta' => 'Aktif', 'tone' => 'amber'] : null),
        ($dashboardCanFeature('reports.inventory_view') ? ['href' => '/backoffice/reports/inventory', 'icon' => 'inventory', 'label' => 'Persediaan', 'meta' => 'Laporan', 'tone' => 'indigo'] : null),
        ($canAccessDashboardPurchases && $dashboardCanFeature('purchase.order') ? ['href' => '/backoffice/purchases/new', 'icon' => 'add_shopping_cart', 'label' => 'PO', 'meta' => 'Beli', 'tone' => 'orange'] : null),
        ($canAccessDashboardPurchases && $dashboardCanFeature('purchase.receive') ? ['href' => '/backoffice/receiving', 'icon' => 'move_to_inbox', 'label' => 'Terima', 'meta' => 'Barang', 'tone' => 'green'] : null),
        ($dashboardCanFeature('reports.summary') ? ['href' => '/backoffice/reports/sales', 'icon' => 'bar_chart', 'label' => 'Penjualan', 'meta' => 'Report', 'tone' => 'blue'] : null),
        ($dashboardCanFeature('finance.summary_basic') ? ['href' => '/backoffice/finance/summary', 'icon' => 'account_balance_wallet', 'label' => 'Keuangan', 'meta' => 'Laba', 'tone' => 'green'] : null),
        ($canManageDashboardSettings && $dashboardCanFeature('finance.bank_accounts') ? ['href' => '/backoffice/settings/payment-settings', 'icon' => 'payments', 'label' => 'Bayar', 'meta' => 'Rekening', 'tone' => 'orange'] : null),
        ($canManageDashboardSettings && $dashboardCanFeature('finance.bank_accounts') ? ['href' => '/admin/opening-balance', 'icon' => 'savings', 'label' => 'Saldo Awal', 'meta' => 'Setup', 'tone' => 'amber'] : null),
        ($canManageDashboardSettings && $dashboardCanFeature('settings.tax') ? ['href' => '/backoffice/settings/tax-promo', 'icon' => 'percent', 'label' => 'Promo', 'meta' => 'Pajak', 'tone' => 'rose'] : null),
        ($canManageDashboardSettings && $dashboardCanFeature('settings.subscription_billing') ? ['href' => '/backoffice/settings/subscription', 'icon' => 'workspace_premium', 'label' => 'Paket', 'meta' => 'Billing', 'tone' => 'slate'] : null),
    ]));
@endphp

@section('page_title', 'Dashboard Backoffice')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100')
@section('main_class', 'flex-1 overflow-y-auto pb-8 xl:pl-72')

@section('content')
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-950/95">
        <div class="flex min-h-[64px] items-center justify-between gap-4 px-4 py-3 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button class="flex size-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 shadow-sm ring-1 ring-slate-200/70 xl:hidden dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700" type="button" aria-label="Buka menu" data-menu-trigger>
                    <span class="material-symbols-outlined text-[20px]">menu</span>
                </button>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400">Backoffice</p>
                    <h2 class="truncate text-base font-black text-slate-950 dark:text-slate-100 lg:text-lg">Beranda Operasional</h2>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <span class="hidden rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 md:inline-flex">
                    {{ now()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y') }}
                </span>
                <a href="/portal/switch/pos?next=/pos/checkout" class="inline-flex items-center gap-2 rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white shadow-sm shadow-primary/25 lg:px-4">
                    <span class="material-symbols-outlined text-[18px]">point_of_sale</span>
                    Kasir
                </a>
            </div>
        </div>
    </header>

    <div class="backoffice-page backoffice-dashboard-page space-y-5">
        @if ($canManageDashboardSettings && $dashboardCanFeature('finance.lock_period'))
            <section id="period-closing-reminder" class="hidden rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-100">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/70 text-amber-600 dark:bg-slate-950/40">
                        <span class="material-symbols-outlined text-[20px]">notification_important</span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p id="period-closing-reminder-label" class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300">Tutup Buku</p>
                        <h3 id="period-closing-reminder-title" class="mt-1 text-sm font-black">Periode sebelumnya perlu ditutup</h3>
                        <p id="period-closing-reminder-message" class="mt-1 text-xs leading-5 text-amber-800 dark:text-amber-200">Review checklist tutup buku sebelum laporan bulan berjalan dipakai.</p>
                    </div>
                    <a href="/backoffice/finance/lock-periods" class="shrink-0 rounded-lg bg-amber-600 px-3 py-2 text-xs font-black text-white">Cek</a>
                </div>
            </section>
        @endif
        <section class="backoffice-home-panel overflow-hidden dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-5 p-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:p-5">
                <div class="min-w-0">
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-primary">Kontrol toko</p>
                    <h1 class="mt-1 text-xl font-black tracking-tight text-slate-950 dark:text-white lg:text-2xl">Beranda Operasional</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">Aksi utama dibuat seperti launcher aplikasi: ikon jelas, label pendek, dan tidak makan tempat.</p>
                </div>
                <div class="grid grid-cols-3 gap-2 sm:flex sm:flex-wrap sm:justify-end">
                    <a href="/portal/switch/pos?next=/pos/checkout" class="backoffice-top-action bg-primary text-white shadow-sm shadow-primary/20">
                        <span class="material-symbols-outlined text-[18px]">point_of_sale</span>
                        <span>Kasir</span>
                    </a>
                    <a href="/backoffice/reports/sales" class="backoffice-top-action border border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200">
                        <span class="material-symbols-outlined text-[18px] text-sky-600">bar_chart</span>
                        <span>Sales</span>
                    </a>
                    <a href="/backoffice/settings/payment-settings" class="backoffice-top-action border border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200">
                        <span class="material-symbols-outlined text-[18px] text-primary">payments</span>
                        <span>Bayar</span>
                    </a>
                </div>
            </div>

            <div class="border-t border-slate-100 px-4 py-4 dark:border-slate-800 lg:px-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Aplikasi</p>
                        <h3 class="mt-1 text-sm font-black text-slate-950 dark:text-white">Pusat Akses Cepat</h3>
                    </div>
                    <span class="hidden text-xs font-semibold text-slate-500 dark:text-slate-400 sm:inline">{{ count($dashboardLauncherItems) }} menu aktif</span>
                </div>

                <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6 2xl:grid-cols-8">
                    @foreach ($dashboardLauncherItems as $item)
                        @php($toneClass = $dashboardToneClasses[$item['tone'] ?? 'slate'] ?? $dashboardToneClasses['slate'])
                        <a href="{{ $item['href'] }}" class="backoffice-app-tile group dark:border-slate-800 dark:bg-slate-950/40">
                            <span class="backoffice-app-icon {{ $toneClass }}">
                                <span class="material-symbols-outlined text-[22px]">{{ $item['icon'] }}</span>
                            </span>
                            <span class="mt-2 block w-full truncate text-center text-xs font-black text-slate-800 dark:text-slate-100">{{ $item['label'] }}</span>
                            <span class="mt-0.5 block w-full truncate text-center text-[10px] font-semibold text-slate-400">{{ $item['meta'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="grid border-t border-slate-100 dark:border-slate-800 sm:grid-cols-2 xl:grid-cols-4">
                <article class="backoffice-mini-kpi">
                    <span class="backoffice-kpi-icon border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/40 dark:bg-emerald-950/25 dark:text-emerald-300">
                        <span class="material-symbols-outlined text-[20px]">payments</span>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Penjualan</p>
                        <p id="kpi-sales-today" class="truncate text-lg font-black text-slate-950 dark:text-white">Rp 0</p>
                    </div>
                </article>
                <article class="backoffice-mini-kpi">
                    <span class="backoffice-kpi-icon border-sky-100 bg-sky-50 text-sky-600 dark:border-sky-900/40 dark:bg-sky-950/25 dark:text-sky-300">
                        <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Transaksi</p>
                        <p id="kpi-orders-today" class="truncate text-lg font-black text-slate-950 dark:text-white">0</p>
                    </div>
                </article>
                <article class="backoffice-mini-kpi">
                    <span class="backoffice-kpi-icon border-amber-100 bg-amber-50 text-amber-600 dark:border-amber-900/40 dark:bg-amber-950/25 dark:text-amber-300">
                        <span class="material-symbols-outlined text-[20px]">schedule</span>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Shift</p>
                        <p id="kpi-shift-status" class="truncate text-sm font-black text-slate-500">Tidak ada shift aktif</p>
                    </div>
                </article>
                <article class="backoffice-mini-kpi">
                    <span class="backoffice-kpi-icon border-rose-100 bg-rose-50 text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/25 dark:text-rose-300">
                        <span class="material-symbols-outlined text-[20px]">warning</span>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">Stok Menipis</p>
                        <p id="kpi-low-stock" class="truncate text-lg font-black text-rose-600">0 Produk</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="backoffice-home-panel overflow-hidden dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800 lg:px-5">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Aktivitas</p>
                        <h3 class="mt-1 text-sm font-black text-slate-950 dark:text-white">Transaksi Terbaru</h3>
                    </div>
                    <a href="/backoffice/reports/sales" class="inline-flex items-center gap-1 text-xs font-black text-primary">
                        Lihat semua
                        <span class="material-symbols-outlined text-[15px]">arrow_forward</span>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 dark:bg-slate-800/50">
                            <tr>
                                <th class="px-4 py-3 lg:px-5">No. Transaksi</th>
                                <th class="px-4 py-3 lg:px-5">Waktu</th>
                                <th class="px-4 py-3 text-right lg:px-5">Total</th>
                                <th class="px-4 py-3 text-center lg:px-5">Status</th>
                            </tr>
                        </thead>
                        <tbody id="recent-sales-body" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr>
                                <td class="px-4 py-4 text-sm text-slate-500 lg:px-5" colspan="4">Memuat transaksi terbaru...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <aside class="backoffice-home-panel p-4 dark:border-slate-800 dark:bg-slate-900 lg:p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Langganan</p>
                        <h3 id="subscription-title" class="mt-2 truncate text-lg font-black text-slate-950 dark:text-white">Plan -</h3>
                    </div>
                    <span class="backoffice-kpi-icon border-orange-100 bg-orange-50 text-primary dark:border-orange-900/40 dark:bg-orange-950/25">
                        <span class="material-symbols-outlined text-[20px]">workspace_premium</span>
                    </span>
                </div>
                <p id="subscription-meta" class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">Memuat status langganan...</p>
                <div class="mt-5 grid grid-cols-2 gap-2">
                    <a href="/backoffice/settings/subscription" class="backoffice-small-link dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200">
                        <span class="material-symbols-outlined text-[16px]">settings</span>
                        Kelola
                    </a>
                    <a href="/backoffice/settings/subscription/invoices" class="backoffice-small-link dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200">
                        <span class="material-symbols-outlined text-[16px]">receipt</span>
                        Invoice
                    </a>
                </div>
            </aside>
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
                periodClosingReminder: document.getElementById('period-closing-reminder'),
                periodClosingReminderLabel: document.getElementById('period-closing-reminder-label'),
                periodClosingReminderTitle: document.getElementById('period-closing-reminder-title'),
                periodClosingReminderMessage: document.getElementById('period-closing-reminder-message'),
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

            const runWhenIdle = (task) => {
                if ('requestIdleCallback' in window) {
                    window.requestIdleCallback(task, { timeout: 1800 });
                    return;
                }

                window.setTimeout(task, 300);
            };

            const fetchDashboardJson = (url, options = {}) => window.PosUi.fetchJson(url, {
                headers,
            }, {
                timeoutMs: options.timeoutMs || 9000,
                ttlMs: options.ttlMs || 30000,
                cacheKey: options.cacheKey || url,
                unwrap: true,
            });

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
                const data = await fetchDashboardJson('/api/v1/analytics?preset=today', {
                    timeoutMs: 7000,
                    ttlMs: 15000,
                    cacheKey: 'backoffice-dashboard-analytics',
                });
                if (el.salesToday) {
                    el.salesToday.textContent = formatCurrency(data?.revenue?.today ?? 0);
                }
                if (el.ordersToday) {
                    el.ordersToday.textContent = `${data?.orders?.today ?? 0}`;
                }
            };

            const loadInventorySummary = async () => {
                try {
                    const data = await fetchDashboardJson('/api/v1/reports/inventory/summary', {
                        timeoutMs: 9000,
                        ttlMs: 60000,
                        cacheKey: 'backoffice-dashboard-inventory-summary',
                    });
                    if (el.lowStock) {
                        el.lowStock.textContent = `${data?.low_stock_count ?? 0} Produk`;
                    }
                    return;
                } catch (_) {
                    // Fallback below keeps the widget useful if summary endpoint is unavailable.
                }

                try {
                    const fallback = await fetchDashboardJson('/api/v1/inventory-alerts', {
                        timeoutMs: 9000,
                        ttlMs: 60000,
                        cacheKey: 'backoffice-dashboard-inventory-alerts',
                    });
                    if (el.lowStock) {
                        el.lowStock.textContent = `${fallback?.total_alerts ?? 0} Produk`;
                    }
                } catch (_) {
                    return;
                }
            };

            const loadShiftStatus = async () => {
                try {
                    const response = await window.PosUi.fetchWithTimeout('/api/v1/shifts/summary', { headers }, 7000);
                    if (!response.ok) {
                        throw new Error('Shift inactive');
                    }

                    const payload = await response.json().catch(() => ({}));
                    const data = window.PosUi.unwrapApiPayload(payload) || {};
                    const cashierName = data?.cashier?.name || '-';
                    if (el.shiftStatus) {
                        el.shiftStatus.classList.remove('text-slate-500');
                        el.shiftStatus.classList.add('text-emerald-600');
                        el.shiftStatus.textContent = `Aktif - ${cashierName}`;
                    }
                } catch (_) {
                    if (el.shiftStatus) {
                        el.shiftStatus.classList.remove('text-emerald-600');
                        el.shiftStatus.classList.add('text-slate-500');
                        el.shiftStatus.textContent = 'Tidak ada shift aktif';
                    }
                }
            };

            const loadSubscription = async () => {
                const data = await fetchDashboardJson('/api/v1/subscription/current', {
                    timeoutMs: 7000,
                    ttlMs: 60000,
                    cacheKey: 'backoffice-dashboard-subscription',
                });
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
                const data = await fetchDashboardJson('/api/v1/sales-history?preset=today&limit=8&summary=0', {
                    timeoutMs: 10000,
                    ttlMs: 30000,
                    cacheKey: 'backoffice-dashboard-recent-sales',
                });
                renderRecentSales(data?.sales || []);
            };

            const loadPeriodClosingReminder = async () => {
                if (!el.periodClosingReminder) {
                    return;
                }

                try {
                    const data = await fetchDashboardJson('/api/v1/lock-periods/assistant', {
                        timeoutMs: 8000,
                        ttlMs: 30000,
                        cacheKey: 'backoffice-dashboard-period-closing-assistant',
                    });
                    const severity = data?.status?.severity || 'safe';
                    if (severity === 'safe') {
                        el.periodClosingReminder.classList.add('hidden');
                        return;
                    }

                    el.periodClosingReminder.classList.remove('hidden');
                    el.periodClosingReminderLabel.textContent = data?.target_period?.label ? `Tutup Buku ${data.target_period.label}` : 'Tutup Buku';
                    el.periodClosingReminderTitle.textContent = data?.status?.title || 'Periode sebelumnya perlu ditutup';
                    el.periodClosingReminderMessage.textContent = data?.status?.message || 'Review checklist tutup buku sebelum laporan bulan berjalan dipakai.';
                } catch (_) {
                    el.periodClosingReminder.classList.add('hidden');
                }
            };

            Promise.allSettled([
                loadAnalytics(),
                loadShiftStatus(),
                loadPeriodClosingReminder(),
            ]).then(() => {
                runWhenIdle(() => {
                    loadInventorySummary().catch(() => undefined);
                    loadSubscription().catch(() => undefined);
                });
                runWhenIdle(() => {
                    loadRecentSales().catch(() => renderRecentSales([]));
                });
            });
        })();
    </script>
@endsection
