@extends('layouts.app')

@php
    $observabilityTenant = \App\Support\TenantContext::get();
    $observabilityUser = auth()->user();
    $observabilityRole = null;

    if (! $observabilityTenant && filled($observabilityUser?->tenant_id)) {
        $observabilityTenant = \App\Models\Tenant::query()->find($observabilityUser->tenant_id);
    }

    if ($observabilityTenant && $observabilityUser) {
        $observabilityRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $observabilityTenant->id, (string) $observabilityUser->id);

        if (! $observabilityRole && filled($observabilityUser->role ?? null)) {
            $observabilityRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $observabilityUser->role);
        }
    }

    abort_unless(in_array($observabilityRole, ['admin', 'owner', 'superadmin'], true), 403);
@endphp

@section('page_title', 'Observability Ops')
@section('body_class', 'bg-slate-50 text-slate-900')
@section('main_class', 'flex-1 overflow-y-auto p-4 space-y-3 pb-8')

@section('header')
    <x-page-header title="Observability" backUrl="/backoffice/dashboard" :showMenuButton="true">
        <x-slot:actions>
            <button id="btn-refresh" class="px-3 py-1.5 rounded-lg bg-slate-100 text-xs font-semibold">Refresh</button>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <x-period-filter id="observability-period" defaultPreset="last7" />

    <section class="rounded-xl border border-slate-200 bg-white p-3 space-y-3">
        <div>
            <h2 class="text-xs uppercase tracking-widest text-slate-500 font-bold">Scope Filter</h2>
            <p class="mt-1 text-sm text-slate-500">Persempit anomaly batch berdasarkan cabang dan lokasi operasional.</p>
        </div>
        <div class="grid gap-2 md:grid-cols-2">
            <select id="observability-branch-filter" class="rounded-lg border-slate-200 text-sm"></select>
            <select id="observability-location-filter" class="rounded-lg border-slate-200 text-sm"></select>
        </div>
    </section>

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

    <section class="rounded-xl border border-slate-200 bg-white p-3 space-y-3">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xs uppercase tracking-widest text-slate-500 font-bold">Production Batch Variance</h2>
                <p class="mt-1 text-sm text-slate-500">Pantau anomaly yield variance batch produksi dan buka daftar anomaly yang sudah difilter.</p>
            </div>
            <span id="production-batch-variance-count" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700">0 alert</span>
        </div>
        <div class="grid gap-2 md:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Open Alert</p>
                <p id="production-batch-variance-open" class="mt-1 text-2xl font-bold text-slate-900">0</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Quick Filter</p>
                <button id="production-batch-variance-filter" class="mt-2 inline-flex rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white" type="button">Lihat alert batch produksi</button>
            </div>
        </div>
    </section>

    <section id="anomaly-context-box" class="hidden rounded-xl border border-slate-200 bg-white p-3 space-y-2">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xs uppercase tracking-widest text-slate-500 font-bold">Context Filter</h2>
                <p id="anomaly-context-text" class="mt-1 text-sm text-slate-600">-</p>
            </div>
            <button id="anomaly-context-clear" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold" type="button">Lepas context</button>
        </div>
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
            <select id="filter-type" class="rounded-lg border-slate-200 text-sm col-span-2">
                <option value="">Semua Type</option>
                <option value="production_batch_yield_variance">production_batch_yield_variance</option>
                <option value="negative_stock_attempt">negative_stock_attempt</option>
                <option value="void_spike">void_spike</option>
                <option value="adjustment_spike">adjustment_spike</option>
            </select>
        </div>
        <div id="anomaly-list" class="space-y-2"></div>
    </section>

    <p id="error-box" class="hidden rounded-lg p-3 text-sm bg-red-50 text-red-700"></p>

        @include('partials.ui-helpers')
        <script>
            (() => {
                const pageQuery = new URLSearchParams(window.location.search);
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = {
                    'Content-Type': 'application/json',
                    ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
                };

                const el = {
                    period: document.getElementById('observability-period'),
                    branchFilter: document.getElementById('observability-branch-filter'),
                    locationFilter: document.getElementById('observability-location-filter'),
                    queueDepth: document.getElementById('queue-depth'),
                    queueFailed: document.getElementById('queue-failed'),
                    queueLag: document.getElementById('queue-lag'),
                    queueBadge: document.getElementById('queue-badge'),
                    severityList: document.getElementById('severity-list'),
                    typeList: document.getElementById('type-list'),
                    invariantList: document.getElementById('invariant-list'),
                    anomalyList: document.getElementById('anomaly-list'),
                    typeFilter: document.getElementById('filter-type'),
                    productionBatchVarianceCount: document.getElementById('production-batch-variance-count'),
                    productionBatchVarianceOpen: document.getElementById('production-batch-variance-open'),
                    productionBatchVarianceFilter: document.getElementById('production-batch-variance-filter'),
                    contextBox: document.getElementById('anomaly-context-box'),
                    contextText: document.getElementById('anomaly-context-text'),
                    contextClear: document.getElementById('anomaly-context-clear'),
                    severityFilter: document.getElementById('filter-severity'),
                    resolvedFilter: document.getElementById('filter-resolved'),
                    refresh: document.getElementById('btn-refresh'),
                    error: document.getElementById('error-box'),
                };

                const state = {
                    bootstrap: { branches: [], locations: [] },
                    period: { preset: 'last7', start_date: '', end_date: '' },
                    anomalyFilters: {
                        type: pageQuery.get('type') || '',
                        resolved: pageQuery.get('resolved') ?? '0',
                        reference_id: pageQuery.get('reference_id') || '',
                        context_label: pageQuery.get('context_label') || '',
                        branch_id: pageQuery.get('branch_id') || '',
                        location_id: pageQuery.get('location_id') || '',
                    },
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
                const parsePayload = (payload) => {
                    if (!payload) return {};
                    if (typeof payload === 'string') {
                        try {
                            return JSON.parse(payload);
                        } catch {
                            return {};
                        }
                    }

                    return typeof payload === 'object' ? payload : {};
                };
                const typeLabel = (type) => ({
                    production_batch_yield_variance: 'Production batch yield variance',
                    negative_stock_attempt: 'Percobaan stok negatif',
                    void_spike: 'Void spike',
                    adjustment_spike: 'Adjustment spike',
                }[type] || type || 'Anomaly');
                const formatNumber = (value, digits = 2) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: digits }).format(Number(value || 0));
                const productionBatchDetailUrl = (referenceId) => `/backoffice/inventory/production-batches/${encodeURIComponent(referenceId)}`;
                const setOptions = (select, items, labelKey, allLabel) => {
                    select.innerHTML = '';
                    const allOption = document.createElement('option');
                    allOption.value = '';
                    allOption.textContent = allLabel;
                    select.appendChild(allOption);

                    items.forEach((item) => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item[labelKey] || '-';
                        select.appendChild(option);
                    });
                };
                const syncLocationOptions = () => {
                    const branchId = state.anomalyFilters.branch_id || '';
                    const locations = (state.bootstrap.locations || []).filter((row) => !branchId || row.branch_id === branchId);
                    setOptions(el.locationFilter, locations, 'name', 'Semua Lokasi');
                    el.locationFilter.value = locations.some((row) => row.id === state.anomalyFilters.location_id)
                        ? state.anomalyFilters.location_id
                        : '';
                };
                const syncPageQuery = () => {
                    const query = new URLSearchParams(window.location.search);

                    ['type', 'resolved', 'reference_id', 'context_label', 'branch_id', 'location_id'].forEach((key) => query.delete(key));

                    if (state.anomalyFilters.type) query.set('type', state.anomalyFilters.type);
                    if (state.anomalyFilters.resolved !== '') query.set('resolved', state.anomalyFilters.resolved);
                    if (state.anomalyFilters.reference_id) query.set('reference_id', state.anomalyFilters.reference_id);
                    if (state.anomalyFilters.context_label) query.set('context_label', state.anomalyFilters.context_label);
                    if (state.anomalyFilters.branch_id) query.set('branch_id', state.anomalyFilters.branch_id);
                    if (state.anomalyFilters.location_id) query.set('location_id', state.anomalyFilters.location_id);

                    history.replaceState(null, '', `${window.location.pathname}${query.toString() ? `?${query.toString()}` : ''}`);
                };
                const renderContext = () => {
                    const hasContext = !!(state.anomalyFilters.reference_id || state.anomalyFilters.context_label);
                    el.contextBox.classList.toggle('hidden', !hasContext);

                    if (!hasContext) {
                        el.contextText.textContent = '-';
                        return;
                    }

                    const label = state.anomalyFilters.context_label || state.anomalyFilters.reference_id;
                    const scope = [state.anomalyFilters.branch_id ? 'branch filtered' : '', state.anomalyFilters.location_id ? 'location filtered' : '']
                        .filter(Boolean)
                        .join(' • ');
                    el.contextText.textContent = `Menampilkan anomaly untuk batch ${label}${scope ? ` • ${scope}` : ''}.`;
                };

                const loadBootstrap = async () => {
                    state.bootstrap = await window.PosUi.getBootstrap({ headers, ttlMs: 20000 }).catch(() => {
                        throw new Error('Gagal memuat master data observability.');
                    });
                    setOptions(el.branchFilter, state.bootstrap.branches || [], 'name', 'Semua Cabang');
                    el.branchFilter.value = state.anomalyFilters.branch_id || '';
                    syncLocationOptions();
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

                    const productionBatchVarianceCount = Number(byType.production_batch_yield_variance || 0);
                    el.productionBatchVarianceCount.textContent = `${productionBatchVarianceCount} alert`;
                    el.productionBatchVarianceOpen.textContent = productionBatchVarianceCount;

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
                        const payload = parsePayload(row.payload_json);
                        const isProductionBatchVariance = row.type === 'production_batch_yield_variance';
                        const productionBatchMeta = isProductionBatchVariance ? `
                            <div class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 space-y-1">
                                <p>Batch: <span class="font-semibold">${payload.reference_no || row.reference_id || '-'}</span></p>
                                <p>Expected vs Actual: <span class="font-semibold">${formatNumber(payload.expected_output_qty)} -> ${formatNumber(payload.actual_output_qty)}</span></p>
                                <p>Variance: <span class="font-semibold">${formatNumber(payload.yield_variance_qty)} (${formatNumber(payload.yield_variance_pct)}%)</span></p>
                                <p>Reason: <span class="font-semibold">${payload.variance_reason || '-'}</span></p>
                                ${row.reference_id ? `<a href="${productionBatchDetailUrl(row.reference_id)}" class="inline-flex text-[11px] font-semibold text-amber-900 underline">Buka detail batch</a>` : ''}
                            </div>
                        ` : '';
                        return `
                            <div class="rounded-lg border border-slate-200 p-3">
                                <p class="text-sm font-bold">${typeLabel(row.type)}</p>
                                <p class="text-xs text-slate-500">Severity: ${row.severity} • Detected: ${row.detected_at}</p>
                                <p class="text-xs text-slate-500">Status: ${resolved ? 'resolved' : 'open'}</p>
                                ${productionBatchMeta}
                                ${resolved ? '' : `<button data-resolve-id="${row.id}" class="mt-2 px-2 py-1 rounded bg-slate-100 text-xs font-semibold">Resolve</button>`}
                            </div>
                        `;
                    }).join('');
                };

                const loadAnomalies = async () => {
                    const params = new URLSearchParams();
                    if (el.severityFilter.value) params.set('severity', el.severityFilter.value);
                    if (el.typeFilter.value) params.set('type', el.typeFilter.value);
                    if (el.resolvedFilter.value !== '') params.set('resolved', el.resolvedFilter.value);
                    if (state.anomalyFilters.reference_id) params.set('reference_id', state.anomalyFilters.reference_id);
                    if (state.anomalyFilters.branch_id) params.set('branch_id', state.anomalyFilters.branch_id);
                    if (state.anomalyFilters.location_id) params.set('location_id', state.anomalyFilters.location_id);
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
                        renderContext();
                        const params = new URLSearchParams();
                        params.set('preset', state.period.preset || 'last7');
                        if (state.period.start_date) params.set('start_date', state.period.start_date);
                        if (state.period.end_date) params.set('end_date', state.period.end_date);
                        if (state.anomalyFilters.branch_id) params.set('branch_id', state.anomalyFilters.branch_id);
                        if (state.anomalyFilters.location_id) params.set('location_id', state.anomalyFilters.location_id);

                        const health = await request(`/api/v1/observability/health?${params.toString()}`);
                        renderHealth(health);
                        await loadAnomalies();
                    } catch (error) {
                        showError(error.message || 'Gagal memuat data.');
                    }
                };

                el.typeFilter.value = state.anomalyFilters.type;
                if (pageQuery.has('resolved')) {
                    el.resolvedFilter.value = state.anomalyFilters.resolved;
                }
                el.branchFilter.addEventListener('change', () => {
                    state.anomalyFilters.branch_id = el.branchFilter.value || '';
                    if (state.anomalyFilters.location_id) {
                        const exists = (state.bootstrap.locations || []).some((row) => row.id === state.anomalyFilters.location_id && (!state.anomalyFilters.branch_id || row.branch_id === state.anomalyFilters.branch_id));
                        if (!exists) {
                            state.anomalyFilters.location_id = '';
                        }
                    }
                    syncLocationOptions();
                    syncPageQuery();
                    refreshAll();
                });
                el.locationFilter.addEventListener('change', () => {
                    state.anomalyFilters.location_id = el.locationFilter.value || '';
                    syncPageQuery();
                    refreshAll();
                });

                el.refresh.addEventListener('click', refreshAll);
                el.severityFilter.addEventListener('change', () => loadAnomalies().catch((error) => showError(error.message)));
                el.resolvedFilter.addEventListener('change', () => {
                    state.anomalyFilters.resolved = el.resolvedFilter.value;
                    syncPageQuery();
                    loadAnomalies().catch((error) => showError(error.message));
                });
                el.typeFilter.addEventListener('change', () => {
                    state.anomalyFilters.type = el.typeFilter.value;
                    syncPageQuery();
                    loadAnomalies().catch((error) => showError(error.message));
                });
                el.productionBatchVarianceFilter.addEventListener('click', () => {
                    el.typeFilter.value = 'production_batch_yield_variance';
                    el.resolvedFilter.value = '0';
                    state.anomalyFilters.type = 'production_batch_yield_variance';
                    state.anomalyFilters.resolved = '0';
                    syncPageQuery();
                    loadAnomalies().catch((error) => showError(error.message));
                });
                el.contextClear?.addEventListener('click', () => {
                    state.anomalyFilters.reference_id = '';
                    state.anomalyFilters.context_label = '';
                    syncPageQuery();
                    refreshAll();
                });
                el.period?.addEventListener('period:change', (event) => {
                    state.period = event.detail;
                    refreshAll();
                });
                el.anomalyList.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-resolve-id]');
                    if (!button) return;
                    resolveAnomaly(button.dataset.resolveId).catch((error) => showError(error.message));
                });

                Promise.all([loadBootstrap(), refreshAll()]).catch((error) => showError(error.message || 'Gagal memuat observability.'));
            })();
        </script>
@endsection
