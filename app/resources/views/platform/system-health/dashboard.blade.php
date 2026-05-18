@extends('platform.layout')

@php
    $t = static fn (string $key, array $replace = []) => trans('platform-system-health.'.$key, $replace, 'id');
    $pageTitle = $t('dashboard.page_title');
    $active = 'system-health';
    $initialSummary = $initialSummary ?? [];
    $tenantImpact = $tenantImpact ?? [];
    $latestActivity = $latestActivity ?? [];
    $runStatus = $runStatus ?? [];

    $criticalCount = (int) (($initialSummary['by_severity']['critical'] ?? 0));
    $highCount = (int) (($initialSummary['by_severity']['high'] ?? 0));
    $overallStatus = $criticalCount > 0 ? 'red' : ($highCount > 0 ? 'yellow' : (($initialSummary['unresolved_count'] ?? 0) > 0 ? 'green' : 'gray'));
    $jsTranslations = [
        'noData' => $t('common.no_data'),
        'global' => $t('common.global'),
        'highestSeverity' => [
            'critical' => $t('common.severity_labels.critical'),
            'high' => $t('common.severity_labels.high'),
            'normal' => $t('common.severity_labels.normal'),
        ],
        'overall' => [
            'critical' => $t('dashboard.hero.overall.critical'),
            'elevated' => $t('dashboard.hero.overall.elevated'),
            'stable' => $t('dashboard.hero.overall.stable'),
            'awaiting' => $t('dashboard.hero.overall.awaiting'),
        ],
        'states' => [
            'emptyTitle' => $t('dashboard.states.empty_title'),
            'emptyDescription' => $t('dashboard.states.empty_description'),
            'errorTitle' => $t('dashboard.states.error_title'),
            'errorDescription' => $t('dashboard.states.error_description'),
        ],
        'toasts' => [
            'refreshSuccess' => $t('dashboard.toasts.refresh_success'),
            'refreshError' => $t('dashboard.toasts.refresh_error'),
        ],
    ];
@endphp

@include('platform.system-health.partials.theme')

@section('platform_content')
    <div class="monitor-shell space-y-6">
        <section class="monitor-grid overflow-hidden rounded-[2rem] border border-slate-200/70 bg-[var(--monitor-bg)] p-6 shadow-[0_25px_80px_rgba(15,23,42,0.08)] lg:p-8">
            <div class="grid gap-6 xl:grid-cols-[1.4fr_0.9fr]">
                <div class="rounded-[1.75rem] border border-white/20 bg-[image:var(--monitor-hero)] p-6 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.15)] lg:p-8">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="max-w-2xl">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-white/70">{{ $t('dashboard.hero.eyebrow') }}</p>
                            <h1 class="mt-3 font-display text-3xl font-bold tracking-tight lg:text-4xl">{{ $t('dashboard.hero.title') }}</h1>
                            <p class="mt-4 max-w-xl text-sm leading-6 text-white/78">{{ $t('dashboard.hero.description') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 lg:justify-end">
                            <a href="/superadmin/system-health/incidents" class="rounded-2xl border border-white/25 bg-white/10 px-4 py-2 text-xs font-semibold text-white transition hover:bg-white/15">{{ $t('dashboard.hero.open_incidents') }}</a>
                            <a href="/superadmin/system-health/tenants" class="rounded-2xl bg-white px-4 py-2 text-xs font-semibold text-slate-900 shadow-sm">{{ $t('dashboard.hero.tenant_impact') }}</a>
                            <button type="button" id="monitor-refresh-dashboard" class="rounded-2xl border border-white/25 bg-transparent px-4 py-2 text-xs font-semibold text-white">{{ $t('dashboard.hero.refresh') }}</button>
                        </div>
                    </div>

                    <div class="mt-8 grid gap-5 md:grid-cols-[minmax(0,1fr)_20rem] md:items-end">
                        <div>
                            <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white/90">
                                <span class="h-2.5 w-2.5 rounded-full {{ $overallStatus === 'red' ? 'bg-rose-400' : ($overallStatus === 'yellow' ? 'bg-amber-300' : ($overallStatus === 'green' ? 'bg-emerald-300' : 'bg-slate-300')) }}"></span>
                                <span id="monitor-overall-label">{{ $overallStatus === 'red' ? $t('dashboard.hero.overall.critical') : ($overallStatus === 'yellow' ? $t('dashboard.hero.overall.elevated') : ($overallStatus === 'green' ? $t('dashboard.hero.overall.stable') : $t('dashboard.hero.overall.awaiting'))) }}</span>
                            </div>
                            <div class="mt-5 flex flex-wrap gap-5 text-sm text-white/75">
                                <div>
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-white/50">{{ $t('dashboard.hero.highest_severity') }}</p>
                                    <p id="monitor-highest-severity" class="mt-2 text-xl font-semibold text-white">{{ $criticalCount > 0 ? $t('common.severity_labels.critical') : ($highCount > 0 ? $t('common.severity_labels.high') : $t('common.severity_labels.normal')) }}</p>
                                </div>
                                <div>
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-white/50">{{ $t('dashboard.hero.affected_tenants') }}</p>
                                    <p id="monitor-affected-tenants" class="mt-2 text-xl font-semibold text-white">{{ number_format((int) ($initialSummary['affected_tenant_count'] ?? 0)) }}</p>
                                </div>
                                <div>
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-white/50">{{ $t('dashboard.hero.top_event') }}</p>
                                    <p id="monitor-top-event" class="mt-2 text-xl font-semibold text-white">{{ $initialSummary['top_event_names'][0]['event_name'] ?? $t('common.no_data') }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-[1.5rem] border border-white/15 bg-white/10 p-5 text-right shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]">
                            <p class="text-[11px] uppercase tracking-[0.2em] text-white/55">{{ $t('dashboard.hero.open_incidents') }}</p>
                            <p id="monitor-open-incidents" class="mt-4 text-5xl font-bold leading-none text-white">{{ number_format((int) ($initialSummary['unresolved_count'] ?? 0)) }}</p>
                            <div class="mt-4 rounded-2xl border border-white/10 bg-black/10 px-4 py-3 text-left">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white/65">{{ $t('dashboard.hero.review_cadence') }}</p>
                                <p class="mt-2 text-xs leading-5 text-white/68">{{ $t('dashboard.hero.review_description') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="monitor-glass rounded-[1.75rem] border border-[var(--monitor-line)] p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-slate-400">{{ $t('dashboard.run_status.eyebrow') }}</p>
                            <h2 class="mt-2 text-lg font-bold text-slate-900">{{ $t('dashboard.run_status.title') }}</h2>
                        </div>
                        <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold text-slate-600">
                            <input id="monitor-auto-refresh" type="checkbox" class="rounded border-slate-300 text-primary focus:ring-primary" />
                            {{ $t('dashboard.run_status.auto', ['seconds' => 45]) }}
                        </label>
                    </div>
                    <div class="mt-5 space-y-4">
                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">{{ $t('dashboard.run_status.last_validation') }}</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $runStatus['last_validation_success'] ? \Carbon\CarbonImmutable::parse($runStatus['last_validation_success'], 'UTC')->diffForHumans() : $t('dashboard.run_status.no_success') }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">{{ $t('dashboard.run_status.last_rebuild') }}</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $runStatus['last_rebuild_success'] ? \Carbon\CarbonImmutable::parse($runStatus['last_rebuild_success'], 'UTC')->diffForHumans() : $t('dashboard.run_status.no_success') }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                            <p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">{{ $t('dashboard.run_status.last_scan') }}</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $runStatus['last_scan_success'] ? \Carbon\CarbonImmutable::parse($runStatus['last_scan_success'], 'UTC')->diffForHumans() : $t('dashboard.run_status.no_success') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" id="monitor-kpi-grid">
            <a href="/superadmin/system-health/incidents?status=open&severity=critical" class="monitor-glass rounded-[1.5rem] border border-[var(--monitor-line)] p-5 shadow-sm transition hover:-translate-y-0.5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('dashboard.kpi.open_critical') }}</p>
                <p id="kpi-critical" class="mt-3 text-3xl font-bold text-rose-700">{{ number_format($criticalCount) }}</p>
                <p class="mt-2 text-xs text-rose-600">{{ $t('dashboard.kpi.open_critical_note') }}</p>
            </a>
            <a href="/superadmin/system-health/incidents?status=open&severity=high" class="monitor-glass rounded-[1.5rem] border border-[var(--monitor-line)] p-5 shadow-sm transition hover:-translate-y-0.5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('dashboard.kpi.open_high') }}</p>
                <p id="kpi-high" class="mt-3 text-3xl font-bold text-amber-700">{{ number_format($highCount) }}</p>
                <p class="mt-2 text-xs text-amber-700">{{ $t('dashboard.kpi.open_high_note') }}</p>
            </a>
            <a href="/superadmin/system-health/tenants" class="monitor-glass rounded-[1.5rem] border border-[var(--monitor-line)] p-5 shadow-sm transition hover:-translate-y-0.5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('dashboard.kpi.affected_tenants') }}</p>
                <p id="kpi-affected" class="mt-3 text-3xl font-bold text-slate-900">{{ number_format((int) ($initialSummary['affected_tenant_count'] ?? 0)) }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $t('dashboard.kpi.affected_tenants_note') }}</p>
            </a>
            <a href="/superadmin/system-health/incidents?status=open&event_name=strict_reader_blocked" class="monitor-glass rounded-[1.5rem] border border-[var(--monitor-line)] p-5 shadow-sm transition hover:-translate-y-0.5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('dashboard.kpi.strict_reader_blocks') }}</p>
                <p id="kpi-strict-reader" class="mt-3 text-3xl font-bold text-slate-900">{{ number_format((int) ($initialSummary['strict_reader_blocks_today'] ?? 0)) }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $t('dashboard.kpi.strict_reader_blocks_note') }}</p>
            </a>
            <a href="/superadmin/system-health/incidents?status=open&event_name=sales_cost_header_mismatch" class="monitor-glass rounded-[1.5rem] border border-[var(--monitor-line)] p-5 shadow-sm transition hover:-translate-y-0.5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('dashboard.kpi.sales_cost_mismatch') }}</p>
                <p id="kpi-mismatch" class="mt-3 text-3xl font-bold text-slate-900">{{ number_format((int) ($initialSummary['sales_cost_mismatch_count'] ?? 0)) }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $t('dashboard.kpi.sales_cost_mismatch_note') }}</p>
            </a>
            <div class="monitor-glass rounded-[1.5rem] border border-[var(--monitor-line)] p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('dashboard.kpi.last_validation_success') }}</p>
                <p class="mt-3 text-xl font-bold text-slate-900">{{ $runStatus['last_validation_success'] ? \Carbon\CarbonImmutable::parse($runStatus['last_validation_success'], 'UTC')->isoFormat('DD MMM, HH:mm') : $t('common.no_data') }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $t('dashboard.kpi.last_validation_success_note') }}</p>
            </div>
            <div class="monitor-glass rounded-[1.5rem] border border-[var(--monitor-line)] p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('dashboard.kpi.last_rebuild_success') }}</p>
                <p class="mt-3 text-xl font-bold text-slate-900">{{ $runStatus['last_rebuild_success'] ? \Carbon\CarbonImmutable::parse($runStatus['last_rebuild_success'], 'UTC')->isoFormat('DD MMM, HH:mm') : $t('common.no_data') }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $t('dashboard.kpi.last_rebuild_success_note') }}</p>
            </div>
            <a href="/superadmin/system-health/incidents?status=open&event_name=daily_summary_refresh_failed" class="monitor-glass rounded-[1.5rem] border border-[var(--monitor-line)] p-5 shadow-sm transition hover:-translate-y-0.5">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('dashboard.kpi.daily_summary_failures') }}</p>
                <p id="kpi-summary-fail" class="mt-3 text-3xl font-bold text-slate-900">{{ number_format((int) ($initialSummary['daily_summary_refresh_failures'] ?? 0)) }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $t('dashboard.kpi.daily_summary_failures_note') }}</p>
            </a>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.3fr_0.9fr]">
            <div class="monitor-glass rounded-[1.75rem] border border-[var(--monitor-line)] shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-6 py-5">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('dashboard.panels.critical_alert_eyebrow') }}</p>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $t('dashboard.panels.critical_alert_title') }}</h2>
                    </div>
                    <a href="/superadmin/system-health/incidents?status=open&severity=critical" class="text-xs font-semibold text-primary">{{ $t('dashboard.panels.see_all_critical') }}</a>
                </div>
                <div id="monitor-critical-panel" class="space-y-3 px-6 py-5">
                    @forelse (($initialSummary['latest_critical_events'] ?? []) as $critical)
                        <a href="/superadmin/system-health/incidents/{{ $critical['event_id'] }}" class="block rounded-2xl border border-rose-200 bg-rose-50/80 p-4 transition hover:bg-rose-50">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-bold text-rose-700">{{ $critical['event_name'] }}</p>
                                    <p class="mt-2 text-sm text-slate-700">{{ $critical['summary'] }}</p>
                                </div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-semibold text-rose-700">{{ $critical['occurrence_count'] }}x</span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-3 text-[11px] text-slate-500">
                                <span>{{ $critical['tenant_name'] ?? $critical['tenant_id'] ?? $t('common.global') }}</span>
                                <span>{{ $critical['detected_at'] ? \Carbon\CarbonImmutable::parse($critical['detected_at'], 'UTC')->diffForHumans() : '-' }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">{{ $t('dashboard.panels.critical_empty') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="space-y-6">
                <div class="monitor-glass rounded-[1.75rem] border border-[var(--monitor-line)] p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('dashboard.panels.tenant_impact_eyebrow') }}</p>
                            <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $t('dashboard.panels.tenant_impact_title') }}</h2>
                        </div>
                        <a href="/superadmin/system-health/tenants" class="text-xs font-semibold text-primary">{{ $t('dashboard.panels.open_view') }}</a>
                    </div>
                    <div class="mt-5 space-y-3">
                        @foreach (collect($tenantImpact)->take(5) as $tenant)
                            <a href="/superadmin/system-health/incidents?tenant_id={{ urlencode((string) $tenant['tenant_id']) }}" class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 transition hover:border-slate-300">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $tenant['tenant_name'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $t('dashboard.panels.tenant_summary', ['unresolved' => $tenant['unresolved_count'], 'critical' => $tenant['critical_count'], 'high' => $tenant['high_count']]) }}</p>
                                </div>
                                @include('platform.system-health.partials.health-badge', ['status' => $tenant['health_status']])
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="monitor-glass rounded-[1.75rem] border border-[var(--monitor-line)] p-6 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('dashboard.panels.top_events_eyebrow') }}</p>
                    <div class="mt-4 space-y-3" id="monitor-top-events-panel">
                        @forelse (($initialSummary['top_event_names'] ?? []) as $item)
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-semibold text-slate-900">{{ $item['event_name'] }}</p>
                                    <span class="text-xs font-semibold text-slate-500">{{ $t('dashboard.panels.top_events_total', ['total' => $item['total']]) }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">{{ $t('dashboard.panels.top_events_empty') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <div class="monitor-glass rounded-[1.75rem] border border-[var(--monitor-line)] shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-6 py-5">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('dashboard.panels.incident_table_eyebrow') }}</p>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $t('dashboard.panels.incident_table_title') }}</h2>
                    </div>
                    <a href="/superadmin/system-health/incidents" class="text-xs font-semibold text-primary">{{ $t('dashboard.panels.open_full_list') }}</a>
                </div>
                <div class="monitor-scrollbar overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.18em] text-slate-500">
                            <tr>
                                <th class="px-6 py-4">{{ $t('dashboard.table.severity') }}</th>
                                <th class="px-6 py-4">{{ $t('dashboard.table.event') }}</th>
                                <th class="px-6 py-4">{{ $t('dashboard.table.tenant') }}</th>
                                <th class="px-6 py-4">{{ $t('dashboard.table.occurrences') }}</th>
                                <th class="px-6 py-4">{{ $t('dashboard.table.detected') }}</th>
                            </tr>
                        </thead>
                        <tbody id="monitor-events-table" class="divide-y divide-slate-100 bg-white">
                            <tr>
                                <td class="px-6 py-5" colspan="5">
                                    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
                                        <span class="material-symbols-outlined text-slate-400">progress_activity</span>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-semibold text-slate-700">{{ $t('dashboard.states.loading_title') }}</p>
                                            <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-slate-200">
                                                <div class="h-full w-1/3 animate-pulse rounded-full bg-slate-300"></div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="monitor-glass rounded-[1.75rem] border border-[var(--monitor-line)] shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200/80 px-6 py-5">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('dashboard.panels.latest_activity_eyebrow') }}</p>
                        <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $t('dashboard.panels.latest_activity_title') }}</h2>
                    </div>
                </div>
                <div class="space-y-4 px-6 py-5">
                    @forelse ($latestActivity as $activity)
                        <a href="/superadmin/system-health/incidents/{{ $activity['event_id'] }}" class="block rounded-2xl border border-slate-200 bg-white px-4 py-3 transition hover:border-slate-300">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $activity['event_name'] }}</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ $activity['summary'] }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $activity['severity'] === 'critical' ? 'bg-rose-100 text-rose-700' : ($activity['severity'] === 'high' ? 'bg-amber-100 text-amber-700' : ($activity['severity'] === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-100 text-slate-600')) }}">{{ strtoupper($activity['severity']) }}</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-3 text-[11px] text-slate-500">
                                <span>{{ $activity['tenant_name'] ?? $t('common.global') }}</span>
                                <span>{{ \Carbon\CarbonImmutable::parse($activity['detected_at'], 'UTC')->diffForHumans() }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">{{ $t('dashboard.panels.activity_empty') }}</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            if (!window.PlatformUi) {
                return;
            }

            const endpoints = {
                summary: '/api/v1/platform/system-health/summary?status=open',
                events: '/api/v1/platform/system-health/events?status=open&per_page=8&sort_by=latest&sort_dir=desc',
            };

            const elements = {
                refreshButton: document.getElementById('monitor-refresh-dashboard'),
                autoRefresh: document.getElementById('monitor-auto-refresh'),
                openIncidents: document.getElementById('monitor-open-incidents'),
                overallLabel: document.getElementById('monitor-overall-label'),
                highestSeverity: document.getElementById('monitor-highest-severity'),
                affectedTenants: document.getElementById('monitor-affected-tenants'),
                topEvent: document.getElementById('monitor-top-event'),
                kpiCritical: document.getElementById('kpi-critical'),
                kpiHigh: document.getElementById('kpi-high'),
                kpiAffected: document.getElementById('kpi-affected'),
                kpiStrictReader: document.getElementById('kpi-strict-reader'),
                kpiMismatch: document.getElementById('kpi-mismatch'),
                kpiSummaryFail: document.getElementById('kpi-summary-fail'),
                tableBody: document.getElementById('monitor-events-table'),
            };

            const translations = @json($jsTranslations);

            let intervalId = null;

            const severityClass = (severity) => {
                switch ((severity || '').toLowerCase()) {
                    case 'critical': return 'bg-rose-100 text-rose-700';
                    case 'high': return 'bg-amber-100 text-amber-700';
                    case 'medium': return 'bg-yellow-100 text-yellow-700';
                    default: return 'bg-slate-100 text-slate-600';
                }
            };

            const humanTime = (value) => {
                if (!value) {
                    return '-';
                }
                return new Intl.DateTimeFormat('id-ID', {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                }).format(new Date(value));
            };

            const renderSummary = (data) => {
                const bySeverity = data.by_severity || {};
                const critical = Number(bySeverity.critical || 0);
                const high = Number(bySeverity.high || 0);
                const unresolved = Number(data.unresolved_count || 0);
                const topEvent = data.top_event_names?.[0]?.event_name || translations.noData;

                elements.openIncidents.textContent = unresolved.toLocaleString('id-ID');
                elements.highestSeverity.textContent = critical > 0 ? translations.highestSeverity.critical : (high > 0 ? translations.highestSeverity.high : translations.highestSeverity.normal);
                elements.affectedTenants.textContent = Number(data.affected_tenant_count || 0).toLocaleString('id-ID');
                elements.topEvent.textContent = topEvent;
                elements.kpiCritical.textContent = critical.toLocaleString('id-ID');
                elements.kpiHigh.textContent = high.toLocaleString('id-ID');
                elements.kpiAffected.textContent = Number(data.affected_tenant_count || 0).toLocaleString('id-ID');
                elements.kpiStrictReader.textContent = Number(data.strict_reader_blocks_today || 0).toLocaleString('id-ID');
                elements.kpiMismatch.textContent = Number(data.sales_cost_mismatch_count || 0).toLocaleString('id-ID');
                elements.kpiSummaryFail.textContent = Number(data.daily_summary_refresh_failures || 0).toLocaleString('id-ID');
                elements.overallLabel.textContent = critical > 0 ? translations.overall.critical : (high > 0 ? translations.overall.elevated : (unresolved > 0 ? translations.overall.stable : translations.overall.awaiting));
            };

            const renderStateRow = (tone, title, message) => {
                const styles = {
                    loading: 'border-slate-200 bg-slate-50 text-slate-600',
                    empty: 'border-slate-200 bg-slate-50 text-slate-600',
                    error: 'border-rose-200 bg-rose-50 text-rose-700',
                };
                const icons = {
                    loading: 'progress_activity',
                    empty: 'inbox',
                    error: 'warning',
                };

                return `<tr><td colspan="5" class="px-6 py-5"><div class="flex items-start gap-3 rounded-2xl border px-4 py-4 ${styles[tone]}"><span class="material-symbols-outlined mt-0.5 text-[18px]">${icons[tone]}</span><div><p class="font-semibold">${title}</p><p class="mt-1 text-xs leading-5">${message}</p></div></div></td></tr>`;
            };

            const renderEvents = (rows) => {
                if (!rows.length) {
                    elements.tableBody.innerHTML = renderStateRow('empty', translations.states.emptyTitle, translations.states.emptyDescription);
                    return;
                }

                elements.tableBody.innerHTML = rows.map((row) => `
                    <tr class="transition hover:bg-slate-50/70">
                        <td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-semibold ${severityClass(row.severity)}">${String(row.severity || 'low').toUpperCase()}</span></td>
                        <td class="px-6 py-4"><a href="/superadmin/system-health/incidents/${row.event_id}" class="font-semibold text-slate-900 hover:text-primary">${row.event_name}</a><p class="mt-1 text-xs text-slate-500">${row.summary || '-'}</p></td>
                        <td class="px-6 py-4 text-sm text-slate-600">${row.tenant_name || row.tenant_id || translations.global}</td>
                        <td class="px-6 py-4 text-sm font-semibold text-slate-900">${Number(row.occurrence_count || 0).toLocaleString('id-ID')}</td>
                        <td class="px-6 py-4 text-xs text-slate-500">${humanTime(row.detected_at)}</td>
                    </tr>`).join('');
            };

            const load = async () => {
                const summary = await window.PlatformUi.apiFetch(endpoints.summary);
                if (summary.response.ok && summary.payload?.data) {
                    renderSummary(summary.payload.data);
                }

                const events = await window.PlatformUi.apiFetch(endpoints.events);
                if (events.response.ok && events.payload?.data?.events) {
                    renderEvents(events.payload.data.events);
                    return;
                }

                elements.tableBody.innerHTML = renderStateRow('error', translations.states.errorTitle, translations.states.errorDescription);
            };

            const startAutoRefresh = () => {
                if (intervalId || document.visibilityState === 'hidden') {
                    return;
                }

                intervalId = window.setInterval(() => {
                    if (document.visibilityState === 'visible') {
                        load().catch(() => undefined);
                    }
                }, 90000);
            };

            const stopAutoRefresh = () => {
                if (intervalId) {
                    window.clearInterval(intervalId);
                    intervalId = null;
                }
            };

            elements.refreshButton?.addEventListener('click', () => {
                load().then(() => window.PlatformUi.showToast(translations.toasts.refreshSuccess, 'success')).catch((error) => {
                    window.PlatformUi.showToast(error?.message || translations.toasts.refreshError, 'error');
                });
            });

            elements.autoRefresh?.addEventListener('change', (event) => {
                if (event.target.checked) {
                    startAutoRefresh();
                    return;
                }

                if (intervalId) {
                    stopAutoRefresh();
                }
            });

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && elements.autoRefresh?.checked) {
                    load().catch(() => undefined);
                    startAutoRefresh();
                    return;
                }

                stopAutoRefresh();
            });

            load().catch(() => undefined);
        })();
    </script>
@endpush
