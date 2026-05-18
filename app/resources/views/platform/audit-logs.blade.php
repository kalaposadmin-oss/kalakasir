@extends('platform.layout')

@php
    $pageTitle = 'Audit Logs';
    $active = 'audit-logs';
    $auditLogs = $auditLogs ?? [];
    $filters = $filters ?? [];
    $exportFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
    $exportQuery = $exportFilters ? '?'.http_build_query($exportFilters) : '';
    $page = (int) ($page ?? 1);
    $lastPage = (int) ($lastPage ?? 1);
@endphp

@section('platform_content')
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Audit Logs</h1>
            <p class="mt-1 text-sm text-slate-500">Pantau semua aksi penting platform.</p>
        </div>
        <a href="/api/v1/platform/audit-logs/export{{ $exportQuery }}" class="inline-flex w-fit items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
            <span class="material-symbols-outlined text-[18px]">download</span>
            Export CSV
        </a>
    </div>

    <form method="GET" action="/superadmin/audit-logs" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[minmax(0,1.3fr)_repeat(3,minmax(0,0.8fr))_auto]">
        <input name="q" value="{{ $filters['q'] ?? '' }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" placeholder="Cari event atau reference" />
        <input name="event_type" value="{{ $filters['event_type'] ?? '' }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" placeholder="Event type" />
        <input name="tenant_id" value="{{ $filters['tenant_id'] ?? '' }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" placeholder="Tenant ID" />
        <select name="sort_by" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="created_at" @selected(($filters['sort_by'] ?? 'created_at') === 'created_at')>Terbaru</option>
            <option value="event_type" @selected(($filters['sort_by'] ?? '') === 'event_type')>Event type</option>
        </select>
        <div class="flex items-center gap-2">
            <button class="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white" type="submit">Filter</button>
            <a href="/superadmin/audit-logs" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Reset</a>
        </div>
    </form>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Audit Stream</p>
                <h3 class="text-sm font-bold text-slate-900">{{ number_format((int) ($total ?? 0)) }} event ditemukan</h3>
            </div>
            <span class="text-xs font-semibold text-slate-500">Live data</span>
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
        @include('platform.partials.pagination')
    </section>

@endsection
