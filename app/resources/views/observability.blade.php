@extends('layouts.app')

@section('page_title', 'Observability Ops')
@section('body_class', 'bg-slate-50 text-slate-900')
@section('main_class', 'flex-1 overflow-y-auto p-4 space-y-3 pb-8')

@section('header')
    <x-page-header title="Observability" backUrl="/menu/settings" :showMenuButton="true">
        <x-slot:actions>
            <button id="btn-refresh" class="px-3 py-1.5 rounded-lg bg-slate-100 text-xs font-semibold">Refresh</button>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <x-period-filter id="observability-period" defaultPreset="last7" />

    <section class="rounded-xl border border-slate-200 bg-white p-3 space-y-2">
        <h2 class="text-xs uppercase tracking-widest text-slate-500 font-bold">Queue Health</h2>
        <p class="text-sm">Depth: <span id="queue-depth" class="font-bold">-</span></p>
        <p class="text-sm">Failed 5m: <span id="queue-failed" class="font-bold">-</span></p>
        <p class="text-sm">Lag P95: <span id="queue-lag" class="font-bold">-</span> <span id="queue-badge" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100">-</span></p>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-3 space-y-2">
        <h2 class="text-xs uppercase tracking-widest text-slate-500 font-bold">Anomaly Summary</h2>
        <div id="severity-list" class="text-sm space-y-1"></div>
        <div id="type-list" class="text-sm space-y-1"></div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-3 space-y-2">
        <h2 class="text-xs uppercase tracking-widest text-slate-500 font-bold">Invariant FIFO</h2>
        <div id="invariant-list" class="text-sm space-y-1"></div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-3 space-y-3">
        <h2 class="text-xs uppercase tracking-widest text-slate-500 font-bold">Anomaly Actions</h2>
        <div class="grid grid-cols-2 gap-2">
            <select id="filter-severity" class="rounded-lg border-slate-200 text-sm">
                <option value="">Semua Severity</option>
                <option value="critical">critical</option>
                <option value="high">high</option>
                <option value="med">med</option>
                <option value="low">low</option>
            </select>
            <select id="filter-resolved" class="rounded-lg border-slate-200 text-sm">
                <option value="0">Open</option>
                <option value="1">Resolved</option>
                <option value="">Semua</option>
            </select>
        </div>
        <div id="anomaly-list" class="space-y-2"></div>
    </section>

    <p id="error-box" class="hidden rounded-lg p-3 text-sm bg-red-50 text-red-700"></p>

        @include('partials.ui-helpers')
        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = {
                    'Content-Type': 'application/json',
                    ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
                };

                const el = {
                    period: document.getElementById('observability-period'),
                    queueDepth: document.getElementById('queue-depth'),
                    queueFailed: document.getElementById('queue-failed'),
                    queueLag: document.getElementById('queue-lag'),
                    queueBadge: document.getElementById('queue-badge'),
                    severityList: document.getElementById('severity-list'),
                    typeList: document.getElementById('type-list'),
                    invariantList: document.getElementById('invariant-list'),
                    anomalyList: document.getElementById('anomaly-list'),
                    severityFilter: document.getElementById('filter-severity'),
                    resolvedFilter: document.getElementById('filter-resolved'),
                    refresh: document.getElementById('btn-refresh'),
                    error: document.getElementById('error-box'),
                };

                const state = {
                    period: { preset: 'last7', start_date: '', end_date: '' },
                };

                const showError = (message) => {
                    el.error.textContent = message;
                    el.error.classList.remove('hidden');
                };

                const clearError = () => {
                    el.error.textContent = '';
                    el.error.classList.add('hidden');
                };

                const classifyLag = (seconds) => {
                    if (seconds >= 120) return { label: 'CRIT', className: 'bg-red-100 text-red-700' };
                    if (seconds >= 45) return { label: 'WARN', className: 'bg-amber-100 text-amber-700' };
                    return { label: 'OK', className: 'bg-emerald-100 text-emerald-700' };
                };

                const request = async (url, init = {}) => {
                    const response = await window.PosUi.fetchWithTimeout(url, {
                        ...init,
                        headers: {
                            ...headers,
                            ...(init.headers || {}),
                        },
                    }, 15000);

                    if (!response.ok) {
                        const mapped = await window.PosUi.mapApiError(response, 'Gagal memuat observability.');
                        throw new Error(mapped.message);
                    }

                    return response.json();
                };

                const renderHealth = (data) => {
                    el.queueDepth.textContent = data?.queue?.depth ?? 0;
                    el.queueFailed.textContent = data?.queue?.failed_jobs_5m ?? 0;
                    const lag = Number(data?.summary_lag?.p95_seconds || 0);
                    el.queueLag.textContent = `${lag}s`;
                    const badge = classifyLag(lag);
                    el.queueBadge.textContent = badge.label;
                    el.queueBadge.className = `px-2 py-0.5 rounded-full text-[10px] font-bold ${badge.className}`;

                    const bySeverity = data?.anomalies?.count_by_severity || {};
                    el.severityList.innerHTML = Object.keys(bySeverity).length
                        ? Object.entries(bySeverity).map(([k, v]) => `<p>${k}: <span class='font-bold'>${v}</span></p>`).join('')
                        : '<p class="text-slate-400">Tidak ada data severity.</p>';

                    const byType = data?.anomalies?.count_by_type || {};
                    el.typeList.innerHTML = Object.keys(byType).length
                        ? Object.entries(byType).slice(0, 6).map(([k, v]) => `<p class='truncate'>${k}: <span class='font-bold'>${v}</span></p>`).join('')
                        : '<p class="text-slate-400">Tidak ada data type.</p>';

                    const byInvariant = data?.anomalies?.inventory_invariants || {};
                    el.invariantList.innerHTML = Object.keys(byInvariant).length
                        ? Object.entries(byInvariant).map(([k, v]) => `<p class='truncate'>${k}: <span class='font-bold'>${v}</span></p>`).join('')
                        : '<p class="text-slate-400">Tidak ada temuan invariant.</p>';
                };

                const renderAnomalies = (rows) => {
                    if (!rows.length) {
                        el.anomalyList.innerHTML = '<p class="text-sm text-slate-400">Tidak ada anomaly.</p>';
                        return;
                    }

                    el.anomalyList.innerHTML = rows.map((row) => {
                        const resolved = !!row.resolved_at;
                        return `
                            <div class="rounded-lg border border-slate-200 p-3">
                                <p class="text-sm font-bold">${row.type}</p>
                                <p class="text-xs text-slate-500">Severity: ${row.severity} • Detected: ${row.detected_at}</p>
                                <p class="text-xs text-slate-500">Status: ${resolved ? 'resolved' : 'open'}</p>
                                ${resolved ? '' : `<button data-resolve-id="${row.id}" class="mt-2 px-2 py-1 rounded bg-slate-100 text-xs font-semibold">Resolve</button>`}
                            </div>
                        `;
                    }).join('');
                };

                const loadAnomalies = async () => {
                    const params = new URLSearchParams();
                    if (el.severityFilter.value) params.set('severity', el.severityFilter.value);
                    if (el.resolvedFilter.value !== '') params.set('resolved', el.resolvedFilter.value);
                    params.set('preset', state.period.preset || 'last7');
                    if (state.period.start_date) params.set('start_date', state.period.start_date);
                    if (state.period.end_date) params.set('end_date', state.period.end_date);
                    params.set('limit', '50');

                    const data = await request(`/api/v1/anomalies?${params.toString()}`);
                    renderAnomalies(data.anomalies || []);
                    if (data.range?.limited) {
                        showError(`Rentang dibatasi ${data.range.limit_days} hari (WIB).`);
                    }
                };

                const resolveAnomaly = async (id) => {
                    const ok = window.PosUi.confirmRiskAction({
                        title: 'Resolve Anomaly',
                        message: 'Tandai anomaly ini sebagai resolved?',
                    });
                    if (!ok) return;

                    await request(`/api/v1/anomalies/${id}/resolve`, {
                        method: 'POST',
                        body: JSON.stringify({ note: 'resolved from observability ui' }),
                    });

                    await loadAnomalies();
                };

                const refreshAll = async () => {
                    clearError();
                    try {
                        const params = new URLSearchParams();
                        params.set('preset', state.period.preset || 'last7');
                        if (state.period.start_date) params.set('start_date', state.period.start_date);
                        if (state.period.end_date) params.set('end_date', state.period.end_date);

                        const health = await request(`/api/v1/observability/health?${params.toString()}`);
                        renderHealth(health);
                        await loadAnomalies();
                    } catch (error) {
                        showError(error.message || 'Gagal memuat data.');
                    }
                };

                el.refresh.addEventListener('click', refreshAll);
                el.severityFilter.addEventListener('change', () => loadAnomalies().catch((error) => showError(error.message)));
                el.resolvedFilter.addEventListener('change', () => loadAnomalies().catch((error) => showError(error.message)));
                el.period?.addEventListener('period:change', (event) => {
                    state.period = event.detail;
                    refreshAll();
                });
                el.anomalyList.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-resolve-id]');
                    if (!button) return;
                    resolveAnomaly(button.dataset.resolveId).catch((error) => showError(error.message));
                });

                refreshAll();
            })();
        </script>
@endsection
