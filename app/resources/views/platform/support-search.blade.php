@extends('platform.layout')

@php
    $pageTitle = 'Support Search';
    $active = 'support-search';
    $matches = $matches ?? [];
    $actions = $actions ?? [];
    $filters = $filters ?? [];
@endphp

@section('platform_content')
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Support Search</h1>
            <p class="mt-1 text-sm text-slate-500">Cari tenant, user, atau invoice dengan cepat.</p>
        </div>
        <a href="/superadmin/audit-logs" class="inline-flex w-fit items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
            <span class="material-symbols-outlined text-[18px]">policy</span>
            Audit logs
        </a>
    </div>

    <form method="GET" action="/superadmin/support/search" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[minmax(0,1fr)_auto_auto]">
        <input name="q" value="{{ $filters['q'] ?? '' }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" placeholder="Email, nomor invoice, nama tenant, atau ID" />
        <button class="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white" type="submit">Search</button>
        <a href="/superadmin/support/search" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Reset</a>
    </form>

    <section class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Search Results</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">{{ number_format(count($matches)) }} hasil ditemukan</h2>
            <div class="mt-5 space-y-3">
                @forelse ($matches as $match)
                    @php
                        $targetHref = match ($match['type'] ?? '') {
                            'tenant' => '/superadmin/tenants/'.($match['id'] ?? ''),
                            'user' => '/superadmin/users/'.($match['id'] ?? ''),
                            'invoice' => '/superadmin/billing/invoices?q='.urlencode((string) ($match['id'] ?? '')),
                            default => '#',
                        };
                    @endphp
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 p-4">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $match['label'] ?? '-' }}</p>
                            <p class="mt-1 break-all text-xs text-slate-400">{{ ucfirst($match['type'] ?? '-') }} - {{ $match['id'] ?? '-' }}</p>
                        </div>
                        <a href="{{ $targetHref }}" class="shrink-0 text-xs font-semibold text-primary">Lihat</a>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Masukkan kata kunci untuk mulai mencari.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Quick Actions</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">Shortcut Support</h2>
            <div class="mt-4 space-y-2 text-xs text-slate-600">
                <a href="/superadmin/audit-logs" class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-left transition hover:bg-slate-50">Lihat Audit Logs</a>
                <a href="/superadmin/users" class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-left transition hover:bg-slate-50">Reset Password User</a>
                <a href="/superadmin/tenants" class="block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-left transition hover:bg-slate-50">Force Logout Tenant</a>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Recent Actions</p>
                <h2 class="text-sm font-bold text-slate-900">Aksi Support Terakhir</h2>
            </div>
            <a href="/superadmin/audit-logs" class="text-xs font-semibold text-primary">Lihat Semua</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($actions as $action)
                <div class="flex items-center justify-between gap-4 px-6 py-4">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $action['action_type'] ?? '-' }}</p>
                        <p class="mt-1 break-all text-xs text-slate-400">{{ $action['tenant_id'] ?? '-' }} - {{ $action['created_at'] ?? '-' }}</p>
                    </div>
                    <span class="shrink-0 text-xs text-slate-500">{{ $action['actor_id'] ?? 'system' }}</span>
                </div>
            @empty
                <div class="px-6 py-4 text-sm text-slate-500">Belum ada aksi support.</div>
            @endforelse
        </div>
    </section>
@endsection
