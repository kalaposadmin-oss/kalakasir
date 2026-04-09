@extends('layouts.app')

@php
    $inventoryValuationAuditTenant = \App\Support\TenantContext::get();
    $inventoryValuationAuditUser = auth()->user();
    $inventoryValuationAuditRole = null;

    if (! $inventoryValuationAuditTenant && filled($inventoryValuationAuditUser?->tenant_id)) {
        $inventoryValuationAuditTenant = \App\Models\Tenant::query()->find($inventoryValuationAuditUser->tenant_id);
    }

    if ($inventoryValuationAuditTenant && $inventoryValuationAuditUser) {
        $inventoryValuationAuditRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $inventoryValuationAuditTenant->id, (string) $inventoryValuationAuditUser->id);

        if (! $inventoryValuationAuditRole && filled($inventoryValuationAuditUser->role ?? null)) {
            $inventoryValuationAuditRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $inventoryValuationAuditUser->role);
        }
    }

    $inventoryValuationAuditSubscription = app(\App\Services\SubscriptionService::class);
    $inventoryValuationAuditStatus = $inventoryValuationAuditTenant
        ? $inventoryValuationAuditSubscription->currentStatus($inventoryValuationAuditTenant)
        : null;

    abort_unless(in_array($inventoryValuationAuditRole, ['admin', 'owner', 'superadmin'], true), 403);
    abort_unless(
        $inventoryValuationAuditTenant
        && $inventoryValuationAuditSubscription->canAccessFeature($inventoryValuationAuditTenant, 'settings.inventory_valuation')
        && in_array($inventoryValuationAuditStatus, ['active', 'trialing'], true),
        403
    );
@endphp

@section('page_title', 'Audit Valuation')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display min-h-screen')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@section('header')
        <x-page-header title="Audit Valuation" backUrl="/backoffice/settings/inventory-valuation">
            <x-slot name="actions">
                <button id="btn-refresh" class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-xs font-semibold">Refresh</button>
            </x-slot>
        </x-page-header>
@endsection

@section('content')
        <div class="flex-1 overflow-y-auto p-4 space-y-3 pb-8">
            <x-period-filter id="valuation-audit-period" defaultPreset="last7" />
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-3 space-y-2">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Filter Audit</p>
                <div class="grid grid-cols-1 gap-2">
                    <input id="filter-action" class="w-full rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs" placeholder="Action (contoh: valuation.rebuild)" type="text" />
                    <div class="relative">
                        <input id="filter-actor-search" class="w-full rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs pr-10" placeholder="Cari actor" type="text" />
                        <button id="filter-actor-clear" class="hidden absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] font-bold" type="button">CLEAR</button>
                    </div>
                    <select id="filter-actor" class="w-full rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs">
                        <option value="">Semua Actor</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button id="filter-apply" class="rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white" type="button">Terapkan</button>
                    <button id="filter-reset" class="rounded-lg border border-slate-200 dark:border-slate-800 px-3 py-2 text-xs font-bold text-slate-500" type="button">Reset</button>
                </div>
            </div>
            <div id="summary" class="text-xs text-slate-500">Memuat audit log...</div>
            <p id="range-warning" class="hidden text-[11px] text-amber-600">Rentang dibatasi otomatis.</p>
            <div id="log-list" class="space-y-2"></div>
            <p id="error-box" class="hidden rounded-lg p-3 text-sm bg-red-50 text-red-700"></p>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = {
                    'Content-Type': 'application/json',
                    ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
                };

                const el = {
                    summary: document.getElementById('summary'),
                    list: document.getElementById('log-list'),
                    error: document.getElementById('error-box'),
                    refresh: document.getElementById('btn-refresh'),
                    period: document.getElementById('valuation-audit-period'),
                    warning: document.getElementById('range-warning'),
                    action: document.getElementById('filter-action'),
                    actorSearch: document.getElementById('filter-actor-search'),
                    actorClear: document.getElementById('filter-actor-clear'),
                    actor: document.getElementById('filter-actor'),
                    apply: document.getElementById('filter-apply'),
                    reset: document.getElementById('filter-reset'),
                };

                const showError = (message) => {
                    el.error.classList.remove('hidden');
                    el.error.textContent = message;
                };

                const clearError = () => {
                    el.error.classList.add('hidden');
                    el.error.textContent = '';
                };

                const requestJson = async (url) => {
                    const response = await fetch(url, { headers });
                    if (!response.ok) {
                        const payload = await response.json().catch(() => ({}));
                        throw new Error(payload.message || 'Gagal memuat audit log.');
                    }
                    return response.json();
                };

                const loadActors = async () => {
                    const response = await fetch('/api/v1/users', { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const data = await response.json();
                    return data.staff || [];
                };

                const render = (logs) => {
                    if (!logs.length) {
                        el.list.innerHTML = '<div class="text-xs text-slate-500">Belum ada audit log valuation.</div>';
                        return;
                    }

                    el.list.innerHTML = logs.map((row) => {
                        const actor = row.actor_name || row.actor_id || '-';
                        const requestId = row.request_id || '-';
                        return `
                            <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-3 bg-white dark:bg-slate-900">
                                <p class="text-xs font-bold text-slate-800 dark:text-slate-100">${row.action}</p>
                                <p class="text-[11px] text-slate-500 mt-1">Actor: ${actor}</p>
                                <p class="text-[11px] text-slate-500">Request: ${requestId}</p>
                                <p class="text-[11px] text-slate-500">At: ${row.created_at}</p>
                            </div>
                        `;
                    }).join('');
                };

                const load = async () => {
                    clearError();
                    const params = new URLSearchParams({
                        limit: '50',
                        preset: state.period.preset || 'last7',
                        start_date: state.period.start_date || '',
                        end_date: state.period.end_date || '',
                    });
                    if (state.action) params.set('action', state.action);
                    if (state.actor_id) params.set('actor_id', state.actor_id);
                    const data = await requestJson(`/api/v1/settings/valuation-audit-logs?${params.toString()}`);
                    const logs = data.logs || [];
                    const rangeLabel = data.range?.start_date
                        ? `${data.range.label || 'Periode'} (${data.range.start_date} - ${data.range.end_date})`
                        : '-';
                    el.summary.textContent = `Total ditampilkan: ${data.count || 0} • ${rangeLabel}`;
                    el.warning.classList.toggle('hidden', !data.range?.limited);
                    render(logs);
                };

                const readQuery = () => {
                    const qs = new URLSearchParams(window.location.search);
                    return {
                        action: qs.get('action') || '',
                        actor_id: qs.get('actor_id') || '',
                    };
                };

                const state = {
                    period: { preset: 'last7', start_date: '', end_date: '' },
                    action: '',
                    actor_id: '',
                    actors: [],
                };

                const renderActorOptions = (filterText = '') => {
                    const keyword = filterText.trim().toLowerCase();
                    const options = state.actors.filter((user) => {
                        if (!keyword) return true;
                        const label = `${user.name} ${user.role || ''} ${user.branch || ''}`.toLowerCase();
                        return label.includes(keyword);
                    });

                    el.actorClear.classList.toggle('hidden', !keyword);

                    el.actor.innerHTML = '<option value="">Semua Actor</option>';
                    options.forEach((user) => {
                        const option = document.createElement('option');
                        option.value = user.id;
                        option.textContent = `${user.name} • ${user.role || 'Staf'} • ${user.branch || 'Pusat'}`;
                        el.actor.appendChild(option);
                    });

                    if (state.actor_id && !options.find((user) => String(user.id) === String(state.actor_id))) {
                        const option = document.createElement('option');
                        option.value = state.actor_id;
                        option.textContent = `ID: ${state.actor_id}`;
                        el.actor.appendChild(option);
                    }
                    el.actor.value = state.actor_id;
                };

                const syncQuery = () => {
                    const qs = new URLSearchParams(window.location.search);
                    if (state.action) qs.set('action', state.action); else qs.delete('action');
                    if (state.actor_id) qs.set('actor_id', state.actor_id); else qs.delete('actor_id');
                    history.replaceState(null, '', `${window.location.pathname}?${qs.toString()}`);
                };

                const init = async () => {
                    const query = readQuery();
                    state.action = query.action;
                    state.actor_id = query.actor_id;
                    el.action.value = state.action;
                    state.actors = await loadActors();
                    renderActorOptions();
                };

                el.refresh.addEventListener('click', () => load().catch((error) => showError(error.message)));
                el.period?.addEventListener('period:change', (event) => {
                    state.period = event.detail;
                    load().catch((error) => showError(error.message));
                });

                el.apply.addEventListener('click', () => {
                    state.action = el.action.value.trim();
                    state.actor_id = el.actor.value || '';
                    syncQuery();
                    load().catch((error) => showError(error.message));
                });

                el.reset.addEventListener('click', () => {
                    state.action = '';
                    state.actor_id = '';
                    el.action.value = '';
                    el.actor.value = '';
                    el.actorSearch.value = '';
                    renderActorOptions();
                    syncQuery();
                    load().catch((error) => showError(error.message));
                });

                el.actorSearch.addEventListener('input', () => {
                    renderActorOptions(el.actorSearch.value);
                });

                el.actorClear.addEventListener('click', () => {
                    el.actorSearch.value = '';
                    renderActorOptions('');
                    el.actorSearch.focus();
                });

                el.actorSearch.addEventListener('keydown', (event) => {
                    if (event.key !== 'Escape') {
                        return;
                    }
                    el.actorSearch.value = '';
                    renderActorOptions('');
                });

                init().then(() => {
                    load().catch((error) => showError(error.message));
                }).catch((error) => showError(error.message));
            })();
        </script>
@endsection
