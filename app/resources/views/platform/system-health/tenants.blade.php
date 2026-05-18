@extends('platform.layout')

@php
    $t = static fn (string $key, array $replace = []) => trans('platform-system-health.'.$key, $replace, 'id');
    $pageTitle = $t('tenants.page_title');
    $active = 'tenant-health';
    $tenantImpact = $tenantImpact ?? [];
@endphp

@include('platform.system-health.partials.theme')

@section('platform_content')
    <div class="monitor-shell space-y-6">
        <section class="rounded-[2rem] border border-slate-200/70 bg-[var(--monitor-bg)] p-6 shadow-[0_24px_70px_rgba(15,23,42,0.08)] lg:p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-slate-400">{{ $t('tenants.eyebrow') }}</p>
                    <h1 class="mt-2 text-3xl font-bold text-slate-900">{{ $t('tenants.title') }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">{{ $t('tenants.description') }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="/superadmin/system-health" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700">{{ $t('tenants.buttons.overview') }}</a>
                    <a href="/superadmin/system-health/incidents" class="rounded-2xl bg-primary px-4 py-2 text-xs font-semibold text-white">{{ $t('tenants.buttons.open_incidents') }}</a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="monitor-glass rounded-[1.5rem] border border-[var(--monitor-line)] p-5 shadow-sm"><p class="text-[11px] uppercase tracking-[0.2em] text-slate-400">{{ $t('tenants.cards.red.label') }}</p><p class="mt-2 text-xl font-bold text-rose-700">{{ collect($tenantImpact)->where('health_status', 'red')->count() }}</p><p class="mt-1 text-xs text-slate-500">{{ $t('tenants.cards.red.description') }}</p></div>
            <div class="monitor-glass rounded-[1.5rem] border border-[var(--monitor-line)] p-5 shadow-sm"><p class="text-[11px] uppercase tracking-[0.2em] text-slate-400">{{ $t('tenants.cards.yellow.label') }}</p><p class="mt-2 text-xl font-bold text-amber-700">{{ collect($tenantImpact)->where('health_status', 'yellow')->count() }}</p><p class="mt-1 text-xs text-slate-500">{{ $t('tenants.cards.yellow.description') }}</p></div>
            <div class="monitor-glass rounded-[1.5rem] border border-[var(--monitor-line)] p-5 shadow-sm"><p class="text-[11px] uppercase tracking-[0.2em] text-slate-400">{{ $t('tenants.cards.green.label') }}</p><p class="mt-2 text-xl font-bold text-emerald-700">{{ collect($tenantImpact)->where('health_status', 'green')->count() }}</p><p class="mt-1 text-xs text-slate-500">{{ $t('tenants.cards.green.description') }}</p></div>
            <div class="monitor-glass rounded-[1.5rem] border border-[var(--monitor-line)] p-5 shadow-sm"><p class="text-[11px] uppercase tracking-[0.2em] text-slate-400">{{ $t('tenants.cards.gray.label') }}</p><p class="mt-2 text-xl font-bold text-slate-700">{{ collect($tenantImpact)->where('health_status', 'gray')->count() }}</p><p class="mt-1 text-xs text-slate-500">{{ $t('tenants.cards.gray.description') }}</p></div>
        </section>

        <section class="monitor-glass rounded-[1.75rem] border border-[var(--monitor-line)] shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-6 py-5">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('tenants.table.eyebrow') }}</p>
                    <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $t('tenants.table.title') }}</h2>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> {{ $t('tenants.table.legend_healthy') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span> {{ $t('tenants.table.legend_watch') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-rose-500"></span> {{ $t('tenants.table.legend_critical') }}</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-slate-400"></span> {{ $t('tenants.table.legend_no_data') }}</span>
                </div>
            </div>
            <div class="monitor-scrollbar overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-6 py-4">{{ $t('tenants.table.health') }}</th>
                            <th class="px-6 py-4">{{ $t('tenants.table.tenant') }}</th>
                            <th class="px-6 py-4">{{ $t('tenants.table.unresolved') }}</th>
                            <th class="px-6 py-4">{{ $t('tenants.table.critical') }}</th>
                            <th class="px-6 py-4">{{ $t('tenants.table.high') }}</th>
                            <th class="px-6 py-4">{{ $t('tenants.table.last_event') }}</th>
                            <th class="px-6 py-4">{{ $t('tenants.table.action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($tenantImpact as $tenant)
                            <tr class="transition hover:bg-slate-50/80">
                                <td class="px-6 py-4">@include('platform.system-health.partials.health-badge', ['status' => $tenant['health_status']])</td>
                                <td class="px-6 py-4"><p class="font-semibold text-slate-900">{{ $tenant['tenant_name'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $tenant['tenant_id'] }}</p></td>
                                <td class="px-6 py-4 font-semibold text-slate-900">{{ number_format((int) $tenant['unresolved_count']) }}</td>
                                <td class="px-6 py-4 font-semibold text-rose-700">{{ number_format((int) $tenant['critical_count']) }}</td>
                                <td class="px-6 py-4 font-semibold text-amber-700">{{ number_format((int) $tenant['high_count']) }}</td>
                                <td class="px-6 py-4 text-xs text-slate-500">{{ $tenant['last_event_at'] ? \Carbon\CarbonImmutable::parse($tenant['last_event_at'], 'UTC')->isoFormat('DD MMM YYYY, HH:mm') : $t('common.no_data') }}</td>
                                <td class="px-6 py-4"><a href="/superadmin/system-health/incidents?tenant_id={{ urlencode((string) $tenant['tenant_id']) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold text-slate-700">{{ $t('tenants.table.view_incidents') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
