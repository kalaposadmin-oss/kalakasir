@extends('layouts.app')

@php
    $onlineOrdersTenant = \App\Support\TenantContext::get();
    $onlineOrdersUser = auth()->user();
    $onlineOrdersRole = null;

    if (! $onlineOrdersTenant && filled($onlineOrdersUser?->tenant_id)) {
        $onlineOrdersTenant = \App\Models\Tenant::query()->find($onlineOrdersUser->tenant_id);
    }

    if ($onlineOrdersTenant && $onlineOrdersUser) {
        $onlineOrdersRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $onlineOrdersTenant->id, (string) $onlineOrdersUser->id);

        if (! $onlineOrdersRole && filled($onlineOrdersUser->role ?? null)) {
            $onlineOrdersRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $onlineOrdersUser->role);
        }
    }

    abort_unless(in_array($onlineOrdersRole, ['supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $onlineOrdersTenantId = (string) $onlineOrdersTenant->id;
    $onlineOrdersTimezone = $onlineOrdersTenant->timezone ?: config('app.timezone', 'Asia/Jakarta');
    $onlineOrdersToday = now($onlineOrdersTimezone);
    $onlineOrdersTodayStart = $onlineOrdersToday->copy()->startOfDay()->utc();
    $onlineOrdersTodayEnd = $onlineOrdersToday->copy()->endOfDay()->utc();
    $onlineOrdersStatus = request('status', 'all');
    $onlineOrdersSearch = trim((string) request('q', ''));
    $onlineOrdersRevenueStatuses = ['pembayaran_terverifikasi', 'diproses', 'siap_diambil', 'siap_dikirim', 'selesai'];
    $onlineOrdersStatusLabels = [
        'menunggu_ongkir' => 'Menunggu Ongkir',
        'menunggu_pembayaran' => 'Menunggu Pembayaran',
        'pembayaran_terverifikasi' => 'Pembayaran Terverifikasi',
        'diproses' => 'Diproses',
        'siap_diambil' => 'Siap Diambil',
        'siap_dikirim' => 'Siap Dikirim',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];
    $onlineOrdersStatusClasses = [
        'menunggu_ongkir' => 'bg-amber-100 text-amber-700',
        'menunggu_pembayaran' => 'bg-sky-100 text-sky-700',
        'pembayaran_terverifikasi' => 'bg-emerald-100 text-emerald-700',
        'diproses' => 'bg-indigo-100 text-indigo-700',
        'siap_diambil' => 'bg-teal-100 text-teal-700',
        'siap_dikirim' => 'bg-teal-100 text-teal-700',
        'selesai' => 'bg-slate-100 text-slate-700',
        'dibatalkan' => 'bg-rose-100 text-rose-700',
    ];

    $onlineOrdersBaseQuery = \App\Models\OnlineOrder::query()
        ->where('tenant_id', $onlineOrdersTenantId);

    $onlineOrdersTodayCount = (clone $onlineOrdersBaseQuery)
        ->whereBetween('created_at', [$onlineOrdersTodayStart, $onlineOrdersTodayEnd])
        ->count();
    $onlineOrdersTodayRevenue = (clone $onlineOrdersBaseQuery)
        ->whereBetween('created_at', [$onlineOrdersTodayStart, $onlineOrdersTodayEnd])
        ->whereIn('status', $onlineOrdersRevenueStatuses)
        ->sum('grand_total');
    $onlineOrdersNeedAction = (clone $onlineOrdersBaseQuery)
        ->whereIn('status', ['menunggu_ongkir', 'menunggu_pembayaran'])
        ->count();
    $onlineOrdersSyncedSales = (clone $onlineOrdersBaseQuery)
        ->whereNotNull('sale_id')
        ->count();
    $onlineOrdersSyncedCustomers = (clone $onlineOrdersBaseQuery)
        ->whereNotNull('customer_id')
        ->count();

    $onlineOrdersListQuery = \App\Models\OnlineOrder::query()
        ->with('customer')
        ->withCount('items')
        ->where('tenant_id', $onlineOrdersTenantId);

    if ($onlineOrdersStatus !== 'all' && array_key_exists($onlineOrdersStatus, $onlineOrdersStatusLabels)) {
        $onlineOrdersListQuery->where('status', $onlineOrdersStatus);
    }

    if ($onlineOrdersSearch !== '') {
        $onlineOrdersLike = '%'.$onlineOrdersSearch.'%';
        $onlineOrdersListQuery->where(function ($query) use ($onlineOrdersLike) {
            $query->where('order_code', 'like', $onlineOrdersLike)
                ->orWhere('customer_name', 'like', $onlineOrdersLike)
                ->orWhere('customer_phone', 'like', $onlineOrdersLike);
        });
    }

    $onlineOrdersRows = $onlineOrdersListQuery
        ->orderByDesc('created_at')
        ->limit(100)
        ->get();
@endphp

@section('page_title', 'Order Online')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100')
@section('main_class', 'flex-1 overflow-y-auto pb-8 lg:pl-72')

@section('header')
    <x-page-header title="Order Online" subtitle="Pantau pesanan web, pelanggan, dan status sinkron POS." backUrl="/backoffice/dashboard" :showMenuButton="true">
        <a class="inline-flex items-center gap-2 rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white shadow-sm shadow-primary/25" href="/order?tenant_id={{ urlencode($onlineOrdersTenantId) }}" target="_blank" rel="noopener">
            <span class="material-symbols-outlined text-[17px]">open_in_new</span>
            Web Pesan
        </a>
    </x-page-header>
@endsection

@section('content')
    <div class="backoffice-page space-y-6">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article class="backoffice-stat-card dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Order Hari Ini</p>
                <p class="mt-2 text-2xl font-black text-slate-950 dark:text-white">{{ number_format($onlineOrdersTodayCount, 0, ',', '.') }}</p>
            </article>
            <article class="backoffice-stat-card dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Omzet Online</p>
                <p class="mt-2 text-2xl font-black text-emerald-600">Rp {{ number_format((float) $onlineOrdersTodayRevenue, 0, ',', '.') }}</p>
            </article>
            <article class="backoffice-stat-card dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Perlu Diproses</p>
                <p class="mt-2 text-2xl font-black text-amber-600">{{ number_format($onlineOrdersNeedAction, 0, ',', '.') }}</p>
            </article>
            <article class="backoffice-stat-card dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Masuk Laporan POS</p>
                <p class="mt-2 text-2xl font-black text-sky-600">{{ number_format($onlineOrdersSyncedSales, 0, ',', '.') }}</p>
            </article>
            <article class="backoffice-stat-card dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Pelanggan Tersinkron</p>
                <p class="mt-2 text-2xl font-black text-primary">{{ number_format($onlineOrdersSyncedCustomers, 0, ',', '.') }}</p>
            </article>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form class="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px_auto]" method="GET" action="/backoffice/online-orders">
                <div class="relative">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-400">search</span>
                    <input class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm dark:border-slate-700 dark:bg-slate-800" name="q" value="{{ $onlineOrdersSearch }}" placeholder="Cari kode, nama, atau nomor pelanggan" type="search">
                </div>
                <select class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800" name="status">
                    <option value="all">Semua Status</option>
                    @foreach ($onlineOrdersStatusLabels as $statusKey => $statusLabel)
                        <option value="{{ $statusKey }}" @selected($onlineOrdersStatus === $statusKey)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
                <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white dark:bg-white dark:text-slate-950" type="submit">
                    <span class="material-symbols-outlined text-[18px]">filter_alt</span>
                    Filter
                </button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <div>
                    <h3 class="text-sm font-black text-slate-950 dark:text-white">Daftar Pesanan</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Maksimal 100 pesanan terbaru sesuai filter.</p>
                </div>
                <a class="text-xs font-bold text-primary" href="/backoffice/reports/sales">Laporan Penjualan</a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-5 py-3">Kode</th>
                            <th class="px-5 py-3">Pelanggan</th>
                            <th class="px-5 py-3">Waktu</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3 text-center">Status</th>
                            <th class="px-5 py-3 text-center">Sinkron</th>
                            <th class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($onlineOrdersRows as $order)
                            @php
                                $statusKey = (string) $order->status;
                                $statusLabel = $onlineOrdersStatusLabels[$statusKey] ?? $statusKey;
                                $statusClass = $onlineOrdersStatusClasses[$statusKey] ?? 'bg-slate-100 text-slate-700';
                            @endphp
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="font-black text-slate-950 dark:text-white">{{ $order->order_code }}</p>
                                    <p class="mt-1 text-[11px] text-slate-500">{{ $order->items_count }} item</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $order->customer?->name ?: $order->customer_name }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $order->customer?->phone ?: $order->customer_phone }}</p>
                                </td>
                                <td class="px-5 py-4 text-xs text-slate-500">
                                    {{ optional($order->created_at)->timezone($onlineOrdersTimezone)->format('d M Y H:i') }}
                                </td>
                                <td class="px-5 py-4 text-right font-bold">Rp {{ number_format((float) $order->grand_total, 0, ',', '.') }}</td>
                                <td class="px-5 py-4 text-center">
                                    <span class="rounded-full px-2 py-1 text-[10px] font-bold {{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    @if ($order->sale_id)
                                        <a class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-bold text-emerald-700" href="/backoffice/sales/{{ $order->sale_id }}">POS</a>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-500">Belum</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="inline-flex gap-2">
                                        <a class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:border-primary/40 dark:border-slate-700 dark:text-slate-200" href="/backoffice/online-orders/{{ $order->id }}">Detail</a>
                                        @if (! in_array($statusKey, ['selesai', 'dibatalkan'], true))
                                            <a class="rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white" href="/backoffice/online-orders/{{ $order->id }}/process">Proses</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-5 py-8 text-center text-sm text-slate-500" colspan="7">Belum ada pesanan online sesuai filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
