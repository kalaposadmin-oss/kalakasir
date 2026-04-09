@extends('platform.layout')

@php
    $pageTitle = 'Platform Dashboard';
    $active = 'dashboard';
    $dashboard = $dashboard ?? [];
@endphp

@section('platform_content')
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Platform Dashboard</h1>
            <p class="mt-1 text-sm text-slate-500">Ringkasan performa platform lintas tenant.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Bulan Ini</button>
            <button class="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm">Export Snapshot</button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
            <span class="material-symbols-outlined text-[18px]">calendar_today</span>
            1 Mar 2026 - 10 Mar 2026
        </div>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
            <span class="material-symbols-outlined text-[18px]">tune</span>
            Preset: Last 7 Days
        </div>
        <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Reset</button>
    </div>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Tenants</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) ($dashboard['totals']['tenant_total'] ?? 0)) }}</p>
            <p class="mt-1 text-xs text-emerald-600">+4.2% vs minggu lalu</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tenant Active</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) ($dashboard['totals']['tenant_active'] ?? 0)) }}</p>
            <p class="mt-1 text-xs text-slate-500">89% aktif</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Overdue Invoices</p>
            <p class="mt-2 text-2xl font-bold text-rose-600">{{ number_format((int) ($dashboard['overdue_invoices'] ?? 0)) }}</p>
            <p class="mt-1 text-xs text-slate-500">Perlu tindak lanjut</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Users</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) ($dashboard['totals']['user_total'] ?? 0)) }}</p>
            <p class="mt-1 text-xs text-slate-500">+320 minggu ini</p>
        </article>
    </section>

    <section class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Aktivitas Tenant</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-900">Trend Aktivasi & Risiko</h3>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <span class="h-2 w-2 rounded-full bg-primary"></span> Aktif
                    <span class="h-2 w-2 rounded-full bg-rose-300"></span> Risiko
                </div>
            </div>
            <div class="mt-6 h-48 rounded-2xl border border-dashed border-slate-200 bg-slate-50"></div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Health Summary</p>
            <h3 class="mt-1 text-lg font-bold text-slate-900">Kesehatan Tenant</h3>
            <div class="mt-5 flex justify-center">
                <div class="flex h-36 w-36 items-center justify-center rounded-full border-8 border-primary/20 text-center">
                    <div>
                        <p class="text-2xl font-bold text-slate-900">
                            {{ (int) round(((int) ($dashboard['health_summary']['healthy'] ?? 0)) / max(1, (int) ($dashboard['totals']['tenant_total'] ?? 0)) * 100) }}%
                        </p>
                        <p class="text-xs text-slate-500">Healthy</p>
                    </div>
                </div>
            </div>
            <div class="mt-5 space-y-2 text-xs text-slate-600">
                <div class="flex justify-between">
                    <span>Healthy</span>
                    <span class="font-semibold text-slate-900">{{ number_format((int) ($dashboard['health_summary']['healthy'] ?? 0)) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Billing Issue</span>
                    <span class="font-semibold text-slate-900">{{ number_format((int) ($dashboard['health_summary']['billing_issue'] ?? 0)) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Suspended</span>
                    <span class="font-semibold text-slate-900">{{ number_format((int) ($dashboard['totals']['tenant_suspended'] ?? 0)) }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Recent Events</p>
                <h3 class="text-sm font-bold text-slate-900">Aktivitas Terakhir</h3>
            </div>
            <button class="text-xs font-semibold text-primary">Lihat Semua</button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Waktu</th>
                        <th class="px-6 py-3">Tenant</th>
                        <th class="px-6 py-3">Event</th>
                        <th class="px-6 py-3">Actor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse (($dashboard['recent_events'] ?? []) as $event)
                        <tr>
                            <td class="px-6 py-4 text-xs text-slate-500">{{ $event['created_at'] ?? '-' }}</td>
                            <td class="px-6 py-4 font-semibold text-slate-900">{{ $event['tenant_id'] ?? '-' }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ $event['reason'] ?? '-' }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $event['request_id'] ?? 'system' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-6 py-4 text-sm text-slate-500" colspan="4">Belum ada aktivitas pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('platform.partials.states')
@endsection
