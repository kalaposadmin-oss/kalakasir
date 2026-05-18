@extends('platform.layout')

@php
    $t = static fn (string $key, array $replace = []) => trans('platform-system-health.'.$key, $replace, 'id');
    $pageTitle = $t('incidents.page_title');
    $active = 'incidents';
    $filters = $filters ?? [];
    $catalog = $catalog ?? [];
    $tenants = $tenants ?? collect();
    $jsTranslations = [
        'global' => $t('common.global'),
        'statusLabels' => [
            'open' => $t('common.status_labels.open'),
            'resolved' => $t('common.status_labels.resolved'),
            'ignored' => $t('common.status_labels.ignored'),
        ],
        'sortLabels' => [
            'latest' => $t('common.sort_labels.latest'),
            'severity' => $t('common.sort_labels.severity'),
            'occurrence_count' => $t('common.sort_labels.occurrence_count'),
            'desc' => $t('common.sort_labels.desc'),
            'asc' => $t('common.sort_labels.asc'),
        ],
        'table' => [
            'emptyTitle' => $t('incidents.table.empty_title'),
            'emptyDescription' => $t('incidents.table.empty_description'),
            'loadingTitle' => $t('incidents.table.loading_title'),
            'loadingDescription' => $t('incidents.table.loading_description'),
            'errorTitle' => $t('incidents.table.error_title'),
            'errorDescription' => $t('incidents.table.error_description'),
            'found' => $t('incidents.table.found', ['total' => '__TOTAL__']),
            'sortedBy' => $t('incidents.table.sorted_by', ['sort_by' => '__SORT_BY__', 'sort_dir' => '__SORT_DIR__']),
            'page' => $t('incidents.table.page', ['page' => '__PAGE__', 'last_page' => '__LAST_PAGE__']),
            'detail' => $t('incidents.table.detail'),
        ],
        'modal' => [
            'resolveTitle' => $t('incidents.modal.resolve_title'),
            'ignoreTitle' => $t('incidents.modal.ignore_title'),
        ],
        'toasts' => [
            'resolveSuccess' => $t('incidents.toasts.resolve_success'),
            'ignoreSuccess' => $t('incidents.toasts.ignore_success'),
            'processError' => $t('incidents.toasts.process_error'),
            'refreshSuccess' => $t('incidents.toasts.refresh_success'),
        ],
    ];
@endphp

@include('platform.system-health.partials.theme')

@section('platform_content')
    <div class="monitor-shell space-y-6">
        <section class="rounded-[2rem] border border-slate-200/70 bg-[var(--monitor-bg)] p-6 shadow-[0_24px_70px_rgba(15,23,42,0.08)] lg:p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-slate-400">{{ $t('incidents.eyebrow') }}</p>
                    <h1 class="mt-2 text-3xl font-bold text-slate-900">{{ $t('incidents.title') }}</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">{{ $t('incidents.description') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="/superadmin/system-health" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700">{{ $t('incidents.buttons.overview') }}</a>
                    <button id="incidents-refresh" type="button" class="rounded-2xl bg-primary px-4 py-2 text-xs font-semibold text-white">{{ $t('incidents.buttons.refresh') }}</button>
                </div>
            </div>
        </section>

        <section class="monitor-glass rounded-[1.75rem] border border-[var(--monitor-line)] p-5 shadow-sm">
            <form id="incident-filter-form" class="space-y-4">
                <div class="grid gap-3 xl:grid-cols-[minmax(0,1.3fr)_repeat(4,minmax(0,0.8fr))]">
                    <div>
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $t('incidents.filters.search') }}</label>
                        <input name="search" value="{{ $filters['search'] ?? '' }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" placeholder="{{ $t('incidents.filters.search_placeholder') }}" />
                    </div>
                    <div>
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $t('incidents.filters.tenant') }}</label>
                        <select name="tenant_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                            <option value="">{{ $t('incidents.filters.all_tenants') }}</option>
                            @foreach ($tenants as $tenant)
                                <option value="{{ $tenant->id }}" @selected(($filters['tenant_id'] ?? '') === $tenant->id)>{{ $tenant->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $t('incidents.filters.severity') }}</label>
                        <select name="severity" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                            <option value="">{{ $t('incidents.filters.all_severity') }}</option>
                            @foreach (['critical', 'high', 'medium', 'low'] as $severity)
                                <option value="{{ $severity }}" @selected(($filters['severity'] ?? '') === $severity)>{{ $t('common.severity_labels.'.$severity) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $t('incidents.filters.status') }}</label>
                        <select name="status" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                            @foreach (['open', 'resolved', 'ignored'] as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? 'open') === $status)>{{ $t('common.status_labels.'.$status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $t('incidents.filters.event') }}</label>
                        <select name="event_name" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                            <option value="">{{ $t('incidents.filters.all_events') }}</option>
                            @foreach ($catalog as $eventName)
                                <option value="{{ $eventName }}" @selected(($filters['event_name'] ?? '') === $eventName)>{{ $eventName }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.1fr)_auto]">
                    <div>
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $t('incidents.filters.from') }}</label>
                        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" />
                    </div>
                    <div>
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $t('incidents.filters.to') }}</label>
                        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" />
                    </div>
                    <div>
                        <label class="mb-2 block text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $t('incidents.filters.sort') }}</label>
                        <div class="flex gap-2">
                            <select name="sort_by" class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                <option value="latest" @selected(($filters['sort_by'] ?? 'latest') === 'latest')>{{ $t('common.sort_labels.latest') }}</option>
                                <option value="severity" @selected(($filters['sort_by'] ?? '') === 'severity')>{{ $t('common.sort_labels.severity') }}</option>
                                <option value="occurrence_count" @selected(($filters['sort_by'] ?? '') === 'occurrence_count')>{{ $t('common.sort_labels.occurrence_count') }}</option>
                            </select>
                            <select name="sort_dir" class="min-w-0 w-28 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                                <option value="desc" @selected(($filters['sort_dir'] ?? 'desc') === 'desc')>{{ $t('common.sort_labels.desc') }}</option>
                                <option value="asc" @selected(($filters['sort_dir'] ?? '') === 'asc')>{{ $t('common.sort_labels.asc') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-end gap-2 lg:justify-end">
                        <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold text-slate-600">
                            <input id="incidents-auto-refresh" type="checkbox" class="rounded border-slate-300 text-primary focus:ring-primary" />
                            {{ $t('incidents.filters.auto', ['seconds' => 60]) }}
                        </label>
                        <button id="incident-filter-reset" type="button" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700">{{ $t('incidents.filters.reset') }}</button>
                        <button id="incident-filter-apply" type="button" class="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white">{{ $t('incidents.filters.apply') }}</button>
                    </div>
                </div>
            </form>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex items-start gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500">
                    <span class="material-symbols-outlined text-[16px] text-slate-400">filter_alt</span>
                    <span>{{ $t('incidents.filters.helper') }}</span>
                </div>
            </div>
        </section>

        <section class="monitor-glass rounded-[1.75rem] border border-[var(--monitor-line)] shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 px-6 py-5">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('incidents.table.eyebrow') }}</p>
                    <h2 id="incident-table-title" class="mt-1 text-lg font-bold text-slate-900">{{ $t('incidents.table.title') }}</h2>
                </div>
                <div class="text-xs text-slate-500" id="incident-table-meta">-</div>
            </div>
            <div class="monitor-scrollbar overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-6 py-4">{{ $t('incidents.table.severity') }}</th>
                            <th class="px-6 py-4">{{ $t('incidents.table.event') }}</th>
                            <th class="px-6 py-4">{{ $t('incidents.table.tenant') }}</th>
                            <th class="px-6 py-4">{{ $t('incidents.table.entity') }}</th>
                            <th class="px-6 py-4">{{ $t('incidents.table.summary') }}</th>
                            <th class="px-6 py-4">{{ $t('incidents.table.status') }}</th>
                            <th class="px-6 py-4">{{ $t('incidents.table.occurrences') }}</th>
                            <th class="px-6 py-4">{{ $t('incidents.table.detected') }}</th>
                            <th class="px-6 py-4">{{ $t('incidents.table.last_alerted') }}</th>
                            <th class="px-6 py-4">{{ $t('incidents.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="incident-table-body" class="divide-y divide-slate-100 bg-white">
                        <tr>
                            <td colspan="10" class="px-6 py-5">
                                <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
                                    <span class="material-symbols-outlined text-slate-400">progress_activity</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-slate-700">{{ $t('incidents.table.loading_title') }}</p>
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
            <div class="flex items-center justify-between gap-3 border-t border-slate-200/80 px-6 py-4">
                <button id="incident-prev-page" type="button" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700">{{ $t('incidents.table.previous') }}</button>
                <div id="incident-page-label" class="text-xs text-slate-500">{{ $t('incidents.table.page', ['page' => 1, 'last_page' => 1]) }}</div>
                <button id="incident-next-page" type="button" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700">{{ $t('incidents.table.next') }}</button>
            </div>
        </section>
    </div>

    <div id="incident-action-modal" class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-950/35 px-4">
        <div class="w-full max-w-lg rounded-[1.75rem] bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p id="incident-action-title" class="text-lg font-bold text-slate-900">{{ $t('incidents.modal.resolve_title') }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $t('incidents.modal.description') }}</p>
                </div>
                <button type="button" data-close-modal class="rounded-full border border-slate-200 p-2 text-slate-500">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
            <div id="incident-action-context" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">-</div>
            <textarea id="incident-action-reason" rows="4" class="mt-4 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700" placeholder="{{ $t('incidents.modal.reason_placeholder') }}"></textarea>
            <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button" data-close-modal class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700">{{ $t('incidents.modal.cancel') }}</button>
                <button type="button" id="incident-action-submit" class="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white">{{ $t('incidents.modal.submit') }}</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            if (!window.PlatformUi) {
                return;
            }

            const form = document.getElementById('incident-filter-form');
            const elements = {
                refresh: document.getElementById('incidents-refresh'),
                reset: document.getElementById('incident-filter-reset'),
                apply: document.getElementById('incident-filter-apply'),
                autoRefresh: document.getElementById('incidents-auto-refresh'),
                tableBody: document.getElementById('incident-table-body'),
                tableTitle: document.getElementById('incident-table-title'),
                tableMeta: document.getElementById('incident-table-meta'),
                pageLabel: document.getElementById('incident-page-label'),
                prev: document.getElementById('incident-prev-page'),
                next: document.getElementById('incident-next-page'),
                modal: document.getElementById('incident-action-modal'),
                modalTitle: document.getElementById('incident-action-title'),
                modalContext: document.getElementById('incident-action-context'),
                modalReason: document.getElementById('incident-action-reason'),
                modalSubmit: document.getElementById('incident-action-submit'),
            };

            const translations = @json($jsTranslations);

            const state = {
                page: 1,
                lastPage: 1,
                action: null,
                eventId: null,
                autoRefreshId: null,
            };

            const buildQuery = () => {
                const params = new URLSearchParams();
                new FormData(form).forEach((value, key) => {
                    if (value) {
                        params.set(key, value);
                    }
                });
                params.set('page', state.page);
                params.set('per_page', '15');
                return params.toString();
            };

            const severityClass = (severity) => {
                switch ((severity || '').toLowerCase()) {
                    case 'critical': return 'bg-rose-100 text-rose-700';
                    case 'high': return 'bg-amber-100 text-amber-700';
                    case 'medium': return 'bg-yellow-100 text-yellow-700';
                    default: return 'bg-slate-100 text-slate-600';
                }
            };

            const humanTime = (value) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '-';

            const openModal = (action, eventId, label) => {
                state.action = action;
                state.eventId = eventId;
                elements.modalTitle.textContent = action === 'resolve' ? translations.modal.resolveTitle : translations.modal.ignoreTitle;
                elements.modalContext.textContent = label;
                elements.modalReason.value = '';
                elements.modal.classList.remove('hidden');
                elements.modal.classList.add('flex');
            };

            const closeModal = () => {
                elements.modal.classList.add('hidden');
                elements.modal.classList.remove('flex');
                state.action = null;
                state.eventId = null;
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

                return `<tr><td colspan="10" class="px-6 py-5"><div class="flex items-start gap-3 rounded-2xl border px-4 py-4 ${styles[tone]}"><span class="material-symbols-outlined mt-0.5 text-[18px]">${icons[tone]}</span><div><p class="font-semibold">${title}</p><p class="mt-1 text-xs leading-5">${message}</p></div></div></td></tr>`;
            };

            const renderTable = (rows) => {
                if (!rows.length) {
                    elements.tableBody.innerHTML = renderStateRow('empty', translations.table.emptyTitle, translations.table.emptyDescription);
                    return;
                }

                elements.tableBody.innerHTML = rows.map((row) => `
                    <tr class="transition hover:bg-slate-50/80">
                        <td class="px-6 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-semibold ${severityClass(row.severity)}">${String(row.severity || 'low').toUpperCase()}</span></td>
                        <td class="px-6 py-4"><a href="/superadmin/system-health/incidents/${row.event_id}" class="font-semibold text-slate-900 hover:text-primary">${row.event_name}</a></td>
                        <td class="px-6 py-4 text-sm text-slate-600">${row.tenant_name || row.tenant_id || translations.global}</td>
                        <td class="px-6 py-4 text-xs text-slate-500">${row.entity_type || '-'} / ${row.entity_id || '-'}</td>
                        <td class="px-6 py-4 text-sm text-slate-600">${row.summary || '-'}</td>
                        <td class="px-6 py-4 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">${translations.statusLabels[row.status] || row.status}</td>
                        <td class="px-6 py-4 text-sm font-semibold text-slate-900">${Number(row.occurrence_count || 0).toLocaleString('id-ID')}</td>
                        <td class="px-6 py-4 text-xs text-slate-500">${humanTime(row.detected_at)}</td>
                        <td class="px-6 py-4 text-xs text-slate-500">${humanTime(row.last_alerted_at)}</td>
                        <td class="px-6 py-4">
                            <div class="flex gap-2">
                                <a href="/superadmin/system-health/incidents/${row.event_id}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold text-slate-700">${translations.table.detail}</a>
                                <button type="button" class="incident-action rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-[11px] font-semibold text-emerald-700" data-action="resolve" data-event-id="${row.event_id}" data-label="${row.event_name} · ${row.summary || ''}">${translations.modal.resolveTitle}</button>
                                <button type="button" class="incident-action rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-700" data-action="ignore" data-event-id="${row.event_id}" data-label="${row.event_name} · ${row.summary || ''}">${translations.modal.ignoreTitle}</button>
                            </div>
                        </td>
                    </tr>`).join('');

                elements.tableBody.querySelectorAll('.incident-action').forEach((button) => {
                    button.addEventListener('click', () => openModal(button.dataset.action, button.dataset.eventId, button.dataset.label));
                });
            };

            const load = async () => {
                elements.tableBody.innerHTML = renderStateRow('loading', translations.table.loadingTitle, translations.table.loadingDescription);
                const result = await window.PlatformUi.apiFetch(`/api/v1/platform/system-health/events?${buildQuery()}`);
                if (!result.response.ok) {
                    elements.tableBody.innerHTML = renderStateRow('error', translations.table.errorTitle, translations.table.errorDescription);
                    return;
                }

                const rows = result.payload?.data?.events || [];
                const meta = result.payload?.meta || {};
                state.lastPage = Number(meta.last_page || 1);
                elements.tableTitle.textContent = translations.table.found.replace('__TOTAL__', Number(meta.total || 0).toLocaleString('id-ID'));
                elements.tableMeta.textContent = translations.table.sortedBy
                    .replace('__SORT_BY__', translations.sortLabels[form.sort_by.value] || form.sort_by.value)
                    .replace('__SORT_DIR__', translations.sortLabels[form.sort_dir.value] || form.sort_dir.value);
                elements.pageLabel.textContent = translations.table.page
                    .replace('__PAGE__', String(state.page))
                    .replace('__LAST_PAGE__', String(state.lastPage));
                elements.prev.disabled = state.page <= 1;
                elements.next.disabled = state.page >= state.lastPage;
                renderTable(rows);
            };

            const submitAction = async () => {
                if (!state.action || !state.eventId) {
                    return;
                }

                const endpoint = `/api/v1/platform/system-health/events/${state.eventId}/${state.action}`;
                elements.modalSubmit.disabled = true;
                const result = await window.PlatformUi.apiFetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Idempotency-Key': window.PlatformUi.createIdempotencyKey(state.action, state.eventId),
                    },
                    body: JSON.stringify({
                        reason: elements.modalReason.value.trim() || null,
                    }),
                });
                elements.modalSubmit.disabled = false;

                if (!result.response.ok) {
                    window.PlatformUi.showToast(result.payload?.message || translations.toasts.processError, 'error', result.payload?.request_id || result.requestId);
                    return;
                }

                window.PlatformUi.showToast(state.action === 'resolve' ? translations.toasts.resolveSuccess : translations.toasts.ignoreSuccess, 'success');
                closeModal();
                load().catch(() => undefined);
            };

            elements.apply.addEventListener('click', () => {
                state.page = 1;
                load().catch(() => undefined);
            });
            elements.refresh.addEventListener('click', () => load().then(() => window.PlatformUi.showToast(translations.toasts.refreshSuccess, 'success')).catch(() => undefined));
            elements.reset.addEventListener('click', () => {
                form.reset();
                state.page = 1;
                load().catch(() => undefined);
            });
            elements.prev.addEventListener('click', () => {
                if (state.page <= 1) return;
                state.page -= 1;
                load().catch(() => undefined);
            });
            elements.next.addEventListener('click', () => {
                if (state.page >= state.lastPage) return;
                state.page += 1;
                load().catch(() => undefined);
            });
            elements.modal.querySelectorAll('[data-close-modal]').forEach((button) => button.addEventListener('click', closeModal));
            elements.modal.addEventListener('click', (event) => {
                if (event.target === elements.modal) {
                    closeModal();
                }
            });
            elements.modalSubmit.addEventListener('click', () => submitAction().catch(() => undefined));
            elements.autoRefresh.addEventListener('change', (event) => {
                if (state.autoRefreshId) {
                    clearInterval(state.autoRefreshId);
                    state.autoRefreshId = null;
                }

                if (event.target.checked && document.visibilityState === 'visible') {
                    state.autoRefreshId = setInterval(() => {
                        if (document.visibilityState === 'visible') {
                            load().catch(() => undefined);
                        }
                    }, 120000);
                }
            });

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') {
                    if (state.autoRefreshId) {
                        clearInterval(state.autoRefreshId);
                        state.autoRefreshId = null;
                    }
                    return;
                }

                if (elements.autoRefresh.checked && !state.autoRefreshId) {
                    load().catch(() => undefined);
                    state.autoRefreshId = setInterval(() => {
                        if (document.visibilityState === 'visible') {
                            load().catch(() => undefined);
                        }
                    }, 120000);
                }
            });

            load().catch(() => undefined);
        })();
    </script>
@endpush
