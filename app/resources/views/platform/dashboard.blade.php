@extends('platform.layout')

@php
    $pageTitle = 'Platform Dashboard';
    $active = 'dashboard';
    $dashboard = $dashboard ?? [];
    $totals = $dashboard['totals'] ?? [];
    $healthSummary = $dashboard['health_summary'] ?? [];
    $recentEvents = $dashboard['recent_events'] ?? [];

    $tenantTotal = (int) ($totals['tenant_total'] ?? 0);
    $tenantActive = (int) ($totals['tenant_active'] ?? 0);
    $tenantSuspended = (int) ($totals['tenant_suspended'] ?? 0);
    $userTotal = (int) ($totals['user_total'] ?? 0);
    $overdueInvoices = (int) ($dashboard['overdue_invoices'] ?? 0);
    $healthyCount = (int) ($healthSummary['healthy'] ?? 0);
    $billingIssueCount = (int) ($healthSummary['billing_issue'] ?? 0);
    $inactiveCount = (int) ($healthSummary['inactive'] ?? 0);
    $tenantActivePercent = $tenantTotal > 0 ? (int) round($tenantActive / $tenantTotal * 100) : 0;
    $healthPercent = $tenantTotal > 0 ? (int) round($healthyCount / $tenantTotal * 100) : 0;
    $generatedAt = now(config('app.timezone', 'Asia/Jakarta'))->format('d M Y H:i');

    $quickActions = [
        [
            'href' => '/superadmin/tenants',
            'icon' => 'domain',
            'label' => 'Tenant',
            'description' => 'Kelola status tenant, impersonate, force logout, dan reset owner.',
            'meta' => number_format($tenantTotal) . ' tenant terdaftar',
        ],
        [
            'href' => '/superadmin/users',
            'icon' => 'group',
            'label' => 'User Global',
            'description' => 'Pantau akun lintas tenant dan jalankan aksi keamanan user.',
            'meta' => number_format($userTotal) . ' user terdaftar',
        ],
        [
            'href' => '/superadmin/billing/invoices',
            'icon' => 'receipt_long',
            'label' => 'Billing',
            'description' => 'Lihat invoice, status pembayaran, dan overdue lintas tenant.',
            'meta' => number_format($overdueInvoices) . ' overdue',
        ],
        [
            'href' => '/superadmin/system-health',
            'icon' => 'monitoring',
            'label' => 'System Health',
            'description' => 'Pantau anomali finansial dan kualitas sinkronisasi tenant.',
            'meta' => number_format($billingIssueCount + $inactiveCount) . ' tenant perlu review',
        ],
        [
            'href' => '/superadmin/system-health/incidents?status=open',
            'icon' => 'warning',
            'label' => 'Insiden Terbuka',
            'description' => 'Lanjutkan investigasi kejadian yang belum ditutup.',
            'meta' => 'Prioritas operasional',
        ],
        [
            'href' => '/superadmin/system-health/tenants',
            'icon' => 'domain',
            'label' => 'Kesehatan Tenant',
            'description' => 'Bandingkan tenant sehat, inactive, billing issue, dan suspended.',
            'meta' => number_format($tenantTotal) . ' tenant terdaftar',
        ],
    ];
@endphp

@section('platform_content')
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Superadmin</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">Platform Dashboard</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">Ringkasan operasional lintas tenant, tanpa data simulasi atau aksi kosong.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="/superadmin/system-health" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                <span class="material-symbols-outlined text-[18px]">monitoring</span>
                System Health
            </a>
            <a href="/superadmin/system-health/incidents" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary/90">
                <span class="material-symbols-outlined text-[18px]">warning</span>
                Insiden
            </a>
        </div>
    </div>

    <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="font-semibold text-slate-900">Status platform saat ini</p>
            <p class="mt-1 text-xs text-slate-500">Diperbarui {{ $generatedAt }} berdasarkan data tenant, invoice, user, dan event platform.</p>
        </div>
        <div class="inline-flex w-fit items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
            <span class="material-symbols-outlined text-[16px]">verified</span>
            Data live
        </div>
    </div>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Tenant</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($tenantTotal) }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ number_format($tenantActive) }} aktif, {{ number_format($tenantSuspended) }} suspended</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tenant Aktif</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($tenantActive) }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $tenantActivePercent }}% dari total tenant</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Invoice Overdue</p>
            <p class="mt-2 text-2xl font-bold {{ $overdueInvoices > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ number_format($overdueInvoices) }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $overdueInvoices > 0 ? 'Perlu tindak lanjut billing' : 'Tidak ada overdue' }}</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total User</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($userTotal) }}</p>
            <p class="mt-1 text-xs text-slate-500">Pengguna terdaftar lintas tenant</p>
        </article>
    </section>

    <section class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Operasi Cepat</p>
                    <h2 class="mt-1 text-lg font-bold text-slate-900">Area yang paling sering dicek</h2>
                </div>
                <span class="w-fit rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500">Superadmin</span>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                @foreach ($quickActions as $action)
                    <a href="{{ $action['href'] }}" class="group rounded-xl border border-slate-200 bg-slate-50 p-4 transition hover:border-primary/30 hover:bg-primary/5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-primary shadow-sm">
                            <span class="material-symbols-outlined text-[20px]">{{ $action['icon'] }}</span>
                        </div>
                        <p class="mt-4 text-sm font-bold text-slate-900">{{ $action['label'] }}</p>
                        <p class="mt-2 min-h-12 text-xs leading-5 text-slate-500">{{ $action['description'] }}</p>
                        <p class="mt-3 text-xs font-semibold text-primary">{{ $action['meta'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Health Summary</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">Kesehatan Tenant</h2>
            <div class="mt-5 flex justify-center">
                <div class="flex h-36 w-36 items-center justify-center rounded-full border-8 border-primary/20 text-center">
                    <div>
                        <p class="text-2xl font-bold text-slate-900">{{ $healthPercent }}%</p>
                        <p class="text-xs text-slate-500">Healthy</p>
                    </div>
                </div>
            </div>
            <div class="mt-5 space-y-3 text-xs text-slate-600">
                <div class="flex justify-between gap-4">
                    <span>Healthy</span>
                    <span class="font-semibold text-slate-900">{{ number_format($healthyCount) }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span>Billing issue</span>
                    <span class="font-semibold text-slate-900">{{ number_format($billingIssueCount) }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span>Inactive</span>
                    <span class="font-semibold text-slate-900">{{ number_format($inactiveCount) }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span>Suspended</span>
                    <span class="font-semibold text-slate-900">{{ number_format($tenantSuspended) }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Recent Events</p>
                <h2 class="text-sm font-bold text-slate-900">Aktivitas Terakhir</h2>
            </div>
            <a href="/superadmin/system-health/incidents" class="inline-flex w-fit items-center gap-2 text-xs font-semibold text-primary">
                Lihat insiden
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3 sm:px-6">Waktu</th>
                        <th class="px-5 py-3 sm:px-6">Tenant</th>
                        <th class="px-5 py-3 sm:px-6">Event</th>
                        <th class="px-5 py-3 sm:px-6">Request ID</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentEvents as $event)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-4 text-xs text-slate-500 sm:px-6">{{ $event['created_at'] ?? '-' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 font-semibold text-slate-900 sm:px-6">{{ $event['tenant_id'] ?? '-' }}</td>
                            <td class="min-w-64 px-5 py-4 text-slate-600 sm:px-6">{{ $event['reason'] ?? '-' }}</td>
                            <td class="max-w-xs break-all px-5 py-4 font-mono text-xs text-slate-500 sm:px-6">{{ $event['request_id'] ?? 'system' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-5 py-6 text-sm text-slate-500 sm:px-6" colspan="4">Belum ada aktivitas platform yang tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
