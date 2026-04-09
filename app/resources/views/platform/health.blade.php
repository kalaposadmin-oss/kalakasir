@extends('platform.layout')

@php
    $pageTitle = 'Platform Health';
    $active = 'health';
    $health = $health ?? ['checks' => [], 'metrics' => [], 'generated_at' => null];
@endphp

@section('platform_content')
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Platform Health</h1>
            <p class="mt-1 text-sm text-slate-500">Ringkasan kondisi layanan dan indikator operasional.</p>
        </div>
        <div class="text-xs text-slate-500">
            Diperbarui: {{ $health['generated_at'] ?? '-' }}
        </div>
    </div>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach (($health['checks'] ?? []) as $check)
            @php
                $status = $check['status'] ?? 'ok';
                $statusClass = $status === 'ok'
                    ? 'bg-emerald-100 text-emerald-700'
                    : ($status === 'warn' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700');
            @endphp
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-900">{{ $check['label'] ?? '-' }}</p>
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase {{ $statusClass }}">
                        {{ $status }}
                    </span>
                </div>
                <p class="mt-3 text-xs text-slate-500">{{ $check['message'] ?? '-' }}</p>
            </article>
        @endforeach
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Tenant</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) ($health['metrics']['tenant_total'] ?? 0)) }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total User</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) ($health['metrics']['user_total'] ?? 0)) }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Active Sessions</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) ($health['metrics']['active_sessions'] ?? 0)) }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Overdue Invoices</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) ($health['metrics']['overdue_invoices'] ?? 0)) }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Response Latency</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) ($health['metrics']['response_latency_ms'] ?? 0)) }} ms</p>
        </article>
    </section>
@endsection
