@extends('platform.layout')

@php
    $pageTitle = 'Audit Logs';
    $active = 'audit-logs';
    $auditLogs = $auditLogs ?? [];
@endphp

@section('platform_content')
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Audit Logs</h1>
            <p class="mt-1 text-sm text-slate-500">Pantau semua aksi penting platform.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Export CSV</button>
            <button class="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm">Buat Snapshot</button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
            <span class="material-symbols-outlined text-[18px]">search</span>
            <span>Cari event, actor, tenant</span>
        </div>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">Event: tenant_suspended</div>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">Tanggal: 7 hari</div>
        <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Reset</button>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Audit Stream</p>
                <h3 class="text-sm font-bold text-slate-900">{{ number_format((int) ($total ?? 0)) }} event ditemukan</h3>
            </div>
            <button class="text-xs font-semibold text-primary">Filter Lanjutan</button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Waktu</th>
                        <th class="px-6 py-3">Event</th>
                        <th class="px-6 py-3">Tenant</th>
                        <th class="px-6 py-3">Actor</th>
                        <th class="px-6 py-3">Request</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($auditLogs as $log)
                        @php
                            $payload = is_array($log['payload_json'] ?? null) ? $log['payload_json'] : json_decode((string) ($log['payload_json'] ?? ''), true);
                            $payload = $payload ?: [];
                        @endphp
                        <tr>
                            <td class="px-6 py-4 text-xs text-slate-500">{{ $log['created_at'] ?? '-' }}</td>
                            <td class="px-6 py-4 text-sm font-semibold text-slate-900">{{ $log['event_type'] ?? '-' }}</td>
                            <td class="px-6 py-4 text-xs text-slate-600">{{ $log['tenant_id'] ?? '-' }}</td>
                            <td class="px-6 py-4 text-xs text-slate-500">{{ $log['actor_id'] ?? 'system' }}</td>
                            <td class="px-6 py-4 text-xs text-slate-500">{{ $payload['request_id'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-6 py-4 text-sm text-slate-500" colspan="5">Belum ada audit log.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('platform.partials.states')
@endsection
