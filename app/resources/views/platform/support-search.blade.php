@extends('platform.layout')

@php
    $pageTitle = 'Support Search';
    $active = 'support-search';
    $matches = $matches ?? [];
    $actions = $actions ?? [];
@endphp

@section('platform_content')
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Support Search</h1>
            <p class="mt-1 text-sm text-slate-500">Cari tenant, user, atau invoice dengan cepat.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Reset</button>
            <button class="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm">New Case</button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
            <span class="material-symbols-outlined text-[18px]">search</span>
            <span>Masukkan email, nomor invoice, atau nama tenant</span>
        </div>
        <button class="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white">Search</button>
    </div>

    <section class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Search Results</p>
            <h3 class="mt-1 text-lg font-bold text-slate-900">{{ number_format(count($matches)) }} hasil ditemukan</h3>
            <div class="mt-5 space-y-3">
                @forelse ($matches as $match)
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200 p-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $match['label'] ?? '-' }}</p>
                            <p class="text-xs text-slate-400">{{ ucfirst($match['type'] ?? '-') }} · {{ $match['id'] ?? '-' }}</p>
                        </div>
                        <button class="text-xs font-semibold text-primary">Lihat</button>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Belum ada hasil pencarian.</div>
                @endforelse
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Quick Actions</p>
            <h3 class="mt-1 text-lg font-bold text-slate-900">Shortcut Support</h3>
            <div class="mt-4 space-y-2 text-xs text-slate-600">
                <button class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-left">Lihat Audit Logs</button>
                <button class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-left">Reset Password User</button>
                <button class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-left">Force Logout Tenant</button>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Recent Actions</p>
                <h3 class="text-sm font-bold text-slate-900">Aksi Support Terakhir</h3>
            </div>
            <button class="text-xs font-semibold text-primary">Lihat Semua</button>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($actions as $action)
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $action['action_type'] ?? '-' }}</p>
                        <p class="text-xs text-slate-400">{{ $action['tenant_id'] ?? '-' }} · {{ $action['created_at'] ?? '-' }}</p>
                    </div>
                    <span class="text-xs text-slate-500">{{ $action['actor_id'] ?? 'system' }}</span>
                </div>
            @empty
                <div class="px-6 py-4 text-sm text-slate-500">Belum ada aksi support.</div>
            @endforelse
        </div>
    </section>

    @include('platform.partials.states')
@endsection
