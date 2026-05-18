@extends('platform.layout')

@php
    $pageTitle = 'Billing / Invoices';
    $active = 'invoices';
    $invoices = $invoices ?? [];
    $filters = $filters ?? [];
    $page = (int) ($page ?? 1);
    $lastPage = (int) ($lastPage ?? 1);
    $totalCount = (int) ($total ?? 0);
    $openCount = collect($invoices)->where('status', 'open')->count();
    $overdueCount = collect($invoices)->where('status', 'overdue')->count();
@endphp

@section('platform_content')
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Billing / Invoices</h1>
            <p class="mt-1 text-sm text-slate-500">Pantau status invoice dan piutang tenant.</p>
        </div>
        <a href="/superadmin/support/search" class="inline-flex w-fit items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
            <span class="material-symbols-outlined text-[18px]">support_agent</span>
            Support search
        </a>
    </div>

    <form method="GET" action="/superadmin/billing/invoices" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[minmax(0,1.4fr)_repeat(2,minmax(0,0.8fr))_auto]">
        <input name="q" value="{{ $filters['q'] ?? '' }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" placeholder="Cari invoice atau tenant" />
        <select name="status" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">Semua status</option>
            @foreach (['draft', 'open', 'paid', 'overdue', 'void'] as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <select name="sort_by" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="created_at" @selected(($filters['sort_by'] ?? 'created_at') === 'created_at')>Terbaru</option>
            <option value="due_at" @selected(($filters['sort_by'] ?? '') === 'due_at')>Due date</option>
            <option value="amount" @selected(($filters['sort_by'] ?? '') === 'amount')>Amount</option>
            <option value="status" @selected(($filters['sort_by'] ?? '') === 'status')>Status</option>
        </select>
        <div class="flex items-center gap-2">
            <button class="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white" type="submit">Filter</button>
            <a href="/superadmin/billing/invoices" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Reset</a>
        </div>
    </form>

    <section class="grid gap-4 md:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Invoice</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($totalCount) }}</p>
            <p class="mt-1 text-xs text-slate-500">Bulan ini</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Open</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($openCount) }}</p>
            <p class="mt-1 text-xs text-amber-600">Perlu ditagih</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Overdue</p>
            <p class="mt-2 text-2xl font-bold text-rose-600">{{ number_format($overdueCount) }}</p>
            <p class="mt-1 text-xs text-slate-500">> 7 hari</p>
        </article>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Invoice List</p>
                <h3 class="text-sm font-bold text-slate-900">{{ number_format($totalCount) }} invoice ditemukan</h3>
            </div>
            <span class="text-xs font-semibold text-slate-500">Live data</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Invoice</th>
                        <th class="px-6 py-3">Tenant</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Amount</th>
                        <th class="px-6 py-3">Due</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($invoices as $invoice)
                        @php
                            $status = $invoice['status'] ?? 'open';
                            $statusClass = $status === 'overdue'
                                ? 'bg-rose-100 text-rose-700'
                                : ($status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700');
                        @endphp
                        <tr>
                            <td class="px-6 py-4">
                                <p class="font-semibold text-slate-900">{{ $invoice['number'] ?? '-' }}</p>
                                <p class="text-xs text-slate-400">{{ $invoice['invoice_id'] ?? '-' }}</p>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-600">{{ $invoice['tenant_name'] ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $statusClass }}">{{ ucfirst($status) }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ number_format((float) ($invoice['amount'] ?? 0), 0, ',', '.') }}</td>
                            <td class="px-6 py-4 text-xs text-slate-500">{{ $invoice['due_at'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-6 py-4 text-sm text-slate-500" colspan="5">Belum ada invoice.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('platform.partials.pagination')
    </section>

@endsection
