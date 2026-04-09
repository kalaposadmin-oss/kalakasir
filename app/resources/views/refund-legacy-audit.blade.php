@extends('layouts.app')

@section('page_title', 'Audit Refund Legacy')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display min-h-screen')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@section('header')
        <x-page-header title="Audit Refund Legacy" backUrl="/backoffice/finance/summary">
            <x-slot name="actions">
                <button id="btn-refresh" class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-xs font-semibold">Refresh</button>
            </x-slot>
        </x-page-header>
@endsection

@section('content')
        <div class="flex-1 overflow-y-auto p-4 space-y-3 pb-8">
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-3 space-y-3">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Ringkasan Audit</p>
                <div id="summary-cards" class="grid grid-cols-2 gap-2"></div>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-3 space-y-3">
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <select id="classification-filter" class="w-full rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs">
                        <option value="">Semua Klasifikasi</option>
                        <option value="resolvable_single_match">Resolvable</option>
                        <option value="ambiguous_multi_match">Ambiguous</option>
                        <option value="orphan_no_match">Orphan</option>
                    </select>
                    <select id="branch-filter" class="w-full rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs">
                        <option value="">Semua Cabang</option>
                    </select>
                    <input id="date-from-filter" type="date" class="w-full rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs" />
                    <input id="date-to-filter" type="date" class="w-full rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs" />
                </div>
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[11px] text-slate-500">Filter berdasarkan klasifikasi, cabang, dan tanggal refund.</p>
                    <div class="flex items-center gap-2">
                        <button id="btn-remediate-selected" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white disabled:opacity-50" disabled>Remediasi Terpilih</button>
                        <button id="btn-remediate-filter" class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold text-white">Remediasi Filter</button>
                        <a id="btn-export" class="rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white" href="#">Export CSV</a>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-[11px] text-slate-500">
                    <input id="select-all-resolvable" type="checkbox" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                    <span id="selected-count-label">0 row terpilih</span>
                </label>
                <p id="status-box" class="hidden rounded-lg p-3 text-sm bg-emerald-50 text-emerald-700"></p>
                <div id="refund-legacy-list" class="space-y-2"></div>
                <div class="flex items-center justify-between gap-2 text-[11px] text-slate-500">
                    <button id="btn-prev-page" class="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-50" disabled>Sebelumnya</button>
                    <span id="page-status">Halaman 1</span>
                    <button id="btn-next-page" class="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-50" disabled>Berikutnya</button>
                </div>
                <p id="error-box" class="hidden rounded-lg p-3 text-sm bg-red-50 text-red-700"></p>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-3 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Histori Remediasi</p>
                    <div class="flex items-center gap-2 text-[11px] text-slate-500">
                        <a id="btn-history-export" class="rounded-lg bg-primary px-3 py-1.5 text-[11px] font-bold text-white" href="#">Export Histori</a>
                        <button id="btn-history-prev" class="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-50" disabled>Sebelumnya</button>
                        <button id="btn-history-next" class="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-50" disabled>Berikutnya</button>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <input id="history-actor-filter" class="w-full rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs" placeholder="Filter Actor ID" />
                    <input id="history-date-from-filter" type="date" class="w-full rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs" />
                    <input id="history-date-to-filter" type="date" class="w-full rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs" />
                </div>
                <div id="history-list" class="space-y-2"></div>
                <p id="history-page-status" class="text-[11px] text-slate-500">Halaman 1</p>
            </div>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]')?.content || '';
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    refresh: document.getElementById('btn-refresh'),
                    remediateSelected: document.getElementById('btn-remediate-selected'),
                    remediateFilter: document.getElementById('btn-remediate-filter'),
                    export: document.getElementById('btn-export'),
                    filter: document.getElementById('classification-filter'),
                    branch: document.getElementById('branch-filter'),
                    dateFrom: document.getElementById('date-from-filter'),
                    dateTo: document.getElementById('date-to-filter'),
                    selectAll: document.getElementById('select-all-resolvable'),
                    selectedCount: document.getElementById('selected-count-label'),
                    status: document.getElementById('status-box'),
                    prevPage: document.getElementById('btn-prev-page'),
                    nextPage: document.getElementById('btn-next-page'),
                    pageStatus: document.getElementById('page-status'),
                    historyList: document.getElementById('history-list'),
                    historyExport: document.getElementById('btn-history-export'),
                    historyPrev: document.getElementById('btn-history-prev'),
                    historyNext: document.getElementById('btn-history-next'),
                    historyActor: document.getElementById('history-actor-filter'),
                    historyDateFrom: document.getElementById('history-date-from-filter'),
                    historyDateTo: document.getElementById('history-date-to-filter'),
                    historyPageStatus: document.getElementById('history-page-status'),
                    summary: document.getElementById('summary-cards'),
                    list: document.getElementById('refund-legacy-list'),
                    error: document.getElementById('error-box'),
                };

                const state = {
                    classification: '',
                    branchId: '',
                    dateFrom: '',
                    dateTo: '',
                    rows: [],
                    selectedRefundItemIds: [],
                    page: 1,
                    lastPage: 1,
                    historyPage: 1,
                    historyLastPage: 1,
                    historyActorId: '',
                    historyDateFrom: '',
                    historyDateTo: '',
                };

                const setSelectedIds = (ids) => {
                    state.selectedRefundItemIds = Array.from(new Set(ids.filter(Boolean)));
                };

                const syncSelectionUi = () => {
                    const resolvableIds = state.rows
                        .filter((row) => row.classification === 'resolvable_single_match')
                        .map((row) => row.refund_item_id);
                    const selectedCount = state.selectedRefundItemIds.length;

                    el.selectedCount.textContent = `${selectedCount} row terpilih`;
                    el.remediateSelected.disabled = selectedCount === 0;
                    el.selectAll.checked = resolvableIds.length > 0 && selectedCount === resolvableIds.length;
                    el.selectAll.indeterminate = selectedCount > 0 && selectedCount < resolvableIds.length;
                };

                const syncPagingUi = () => {
                    el.pageStatus.textContent = `Halaman ${state.page} dari ${state.lastPage}`;
                    el.prevPage.disabled = state.page <= 1;
                    el.nextPage.disabled = state.page >= state.lastPage;
                };

                const syncHistoryPagingUi = () => {
                    el.historyPageStatus.textContent = `Halaman ${state.historyPage} dari ${state.historyLastPage}`;
                    el.historyPrev.disabled = state.historyPage <= 1;
                    el.historyNext.disabled = state.historyPage >= state.historyLastPage;
                };

                const historyFilterParams = () => {
                    const params = new URLSearchParams({
                        page: String(state.historyPage),
                        per_page: '5',
                    });

                    if (state.historyActorId) {
                        params.set('actor_id', state.historyActorId);
                    }
                    if (state.historyDateFrom) {
                        params.set('date_from', state.historyDateFrom);
                    }
                    if (state.historyDateTo) {
                        params.set('date_to', state.historyDateTo);
                    }

                    return params;
                };

                const showError = (message) => {
                    el.error.classList.remove('hidden');
                    el.error.textContent = message;
                };

                const clearError = () => {
                    el.error.classList.add('hidden');
                    el.error.textContent = '';
                };

                const showStatus = (message) => {
                    el.status.classList.remove('hidden');
                    el.status.textContent = message;
                };

                const clearStatus = () => {
                    el.status.classList.add('hidden');
                    el.status.textContent = '';
                };

                const requestJson = async (url, options = {}) => {
                    const response = await fetch(url, {
                        method: options.method || 'GET',
                        headers: {
                            ...headers,
                            ...(options.body ? {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            } : {}),
                            ...(options.headers || {}),
                        },
                        body: options.body ? JSON.stringify(options.body) : undefined,
                    });
                    if (!response.ok) {
                        const payload = await response.json().catch(() => ({}));
                        throw new Error(payload.message || 'Gagal memuat audit refund legacy.');
                    }

                    return response.json();
                };

                const renderSummary = (summary) => {
                    const cards = [
                        ['Legacy Missing', summary.legacy_missing_sale_item_id || 0],
                        ['Resolvable', summary.resolvable_single_match || 0],
                        ['Ambiguous', summary.ambiguous_multi_match || 0],
                        ['Orphan', summary.orphan_no_match || 0],
                    ];

                    el.summary.innerHTML = cards.map(([label, value]) => `
                        <div class="rounded-xl bg-slate-50 dark:bg-slate-800 p-3">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">${label}</div>
                            <div class="mt-1 text-xl font-black text-slate-900 dark:text-slate-100">${value}</div>
                        </div>
                    `).join('');
                };

                const badgeMeta = (classification) => {
                    if (classification === 'resolvable_single_match') {
                        return {
                            label: 'Resolvable',
                            className: 'legacy-badge bg-emerald-100 text-emerald-700',
                        };
                    }

                    if (classification === 'ambiguous_multi_match') {
                        return {
                            label: 'Ambiguous',
                            className: 'legacy-badge bg-amber-100 text-amber-700',
                        };
                    }

                    return {
                        label: 'Orphan',
                        className: 'legacy-badge bg-rose-100 text-rose-700',
                    };
                };

                const renderRows = (rows) => {
                    state.rows = rows;
                    const availableIds = new Set(rows.map((row) => row.refund_item_id));
                    setSelectedIds(state.selectedRefundItemIds.filter((id) => availableIds.has(id)));

                    if (!rows.length) {
                        el.list.innerHTML = '<div class="text-xs text-slate-500">Tidak ada refund legacy untuk filter ini.</div>';
                        syncSelectionUi();
                        return;
                    }

                    el.list.innerHTML = rows.map((row) => `
                        <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-3 bg-white dark:bg-slate-900">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    ${row.classification === 'resolvable_single_match' ? `<input class="row-selector rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" type="checkbox" data-refund-item-id="${row.refund_item_id}" ${state.selectedRefundItemIds.includes(row.refund_item_id) ? 'checked' : ''} />` : ''}
                                    <span class="${badgeMeta(row.classification).className} inline-flex rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide">${badgeMeta(row.classification).label}</span>
                                </div>
                                ${row.classification === 'resolvable_single_match' ? `<button class="btn-remediate-row rounded-lg bg-emerald-600 px-2.5 py-1.5 text-[10px] font-bold text-white" data-refund-item-id="${row.refund_item_id}">Remediasi</button>` : ''}
                            </div>
                            <p class="text-[11px] text-slate-500 mt-1">Refund: ${row.refund_id}</p>
                            <p class="text-[11px] text-slate-500">Sale: ${row.sale_id}</p>
                            <p class="text-[11px] text-slate-500">Branch: ${row.branch_name || row.branch_id || '-'}</p>
                            <p class="text-[11px] text-slate-500">Product: ${row.product_id}</p>
                            <p class="text-[11px] text-slate-500">Qty: ${row.qty} • Match Count: ${row.sale_item_match_count}</p>
                            <p class="text-[11px] text-slate-500">Tanggal Refund: ${row.refund_created_at || '-'}</p>
                        </div>
                    `).join('');

                    syncSelectionUi();
                    syncPagingUi();
                };

                const renderHistory = (entries) => {
                    if (!entries.length) {
                        el.historyList.innerHTML = '<div class="text-xs text-slate-500">Belum ada histori remediasi.</div>';
                        syncHistoryPagingUi();
                        return;
                    }

                    el.historyList.innerHTML = entries.map((entry) => {
                        const payload = entry.payload_json || {};
                        const filters = payload.filters || {};
                        return `
                            <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-3 bg-white dark:bg-slate-900">
                                <p class="text-xs font-bold text-slate-800 dark:text-slate-100">${entry.actor_name || entry.actor_id || 'Unknown Actor'}</p>
                                <p class="text-[11px] text-slate-500 mt-1">Waktu: ${entry.created_at || '-'}</p>
                                <p class="text-[11px] text-slate-500">Refund Item: ${entry.reference_id}</p>
                                <p class="text-[11px] text-slate-500">Updated: ${payload.updated || 0} • Skipped: ${payload.skipped || 0}</p>
                                <p class="text-[11px] text-slate-500">Filter: ${filters.classification || 'all'} / ${filters.branch_id || 'all branch'} / ${filters.date_from || '-'} s.d. ${filters.date_to || '-'}</p>
                            </div>
                        `;
                    }).join('');

                    syncHistoryPagingUi();
                };

                const renderBranchOptions = (branches) => {
                    const options = [
                        '<option value="">Semua Cabang</option>',
                        ...branches.map((branch) => {
                            const selected = state.branchId === branch.id ? ' selected' : '';
                            return `<option value="${branch.id}"${selected}>${branch.name}</option>`;
                        }),
                    ];

                    el.branch.innerHTML = options.join('');
                };

                const filterPayload = (limit = '100') => {
                    const payload = { limit, page: String(state.page), per_page: String(limit) };
                    if (state.classification) {
                        payload.classification = state.classification;
                    }
                    if (state.branchId) {
                        payload.branch_id = state.branchId;
                    }
                    if (state.dateFrom) {
                        payload.date_from = state.dateFrom;
                    }
                    if (state.dateTo) {
                        payload.date_to = state.dateTo;
                    }

                    return payload;
                };

                const syncExportHref = () => {
                    const params = new URLSearchParams(filterPayload());

                    el.export.href = `/api/v1/settings/refund-legacy-audit/export?${params.toString()}`;
                    el.historyExport.href = `/api/v1/settings/refund-legacy-audit/history/export?${historyFilterParams().toString()}`;
                };

                const load = async () => {
                    clearError();
                    const params = new URLSearchParams(filterPayload());

                    try {
                        const data = await requestJson(`/api/v1/settings/refund-legacy-audit?${params.toString()}`);
                        state.page = data.page || 1;
                        state.lastPage = data.last_page || 1;
                        renderSummary(data.summary || {});
                        renderRows(data.rows || []);
                        syncExportHref();
                    } catch (error) {
                        showError(error.message || 'Gagal memuat audit refund legacy.');
                    }
                };

                const loadHistory = async () => {
                    try {
                        const params = historyFilterParams();
                        const data = await requestJson(`/api/v1/settings/refund-legacy-audit/history?${params.toString()}`);
                        state.historyPage = data.page || 1;
                        state.historyLastPage = data.last_page || 1;
                        renderHistory(data.entries || []);
                        syncExportHref();
                    } catch (error) {
                        el.historyList.innerHTML = '<div class="text-xs text-slate-500">Gagal memuat histori remediasi.</div>';
                    }
                };

                const remediate = async (extraPayload = {}, label = 'filter aktif') => {
                    clearError();
                    clearStatus();

                    const preview = await requestJson('/api/v1/settings/refund-legacy-audit/remediate', {
                        method: 'POST',
                        body: {
                            ...filterPayload('1000'),
                            ...extraPayload,
                            dry_run: true,
                        },
                    });

                    if (!preview.matched_resolvable) {
                        showStatus(`Tidak ada row resolvable untuk ${label}. Kandidat skip: ${preview.skipped_candidates || 0}.`);
                        return;
                    }

                    const confirmed = window.confirm(`Remediasi ${preview.matched_resolvable} row resolvable untuk ${label}? Kandidat skip: ${preview.skipped_candidates || 0}.`);
                    if (!confirmed) {
                        return;
                    }

                    const result = await requestJson('/api/v1/settings/refund-legacy-audit/remediate', {
                        method: 'POST',
                        body: {
                            ...filterPayload('1000'),
                            ...extraPayload,
                            dry_run: false,
                        },
                    });

                    showStatus(`Remediasi selesai. Updated ${result.updated}, skipped ${result.skipped}, kandidat non-resolvable ${result.skipped_candidates}.`);
                    await load();
                    await loadHistory();
                };

                const loadBranches = async () => {
                    try {
                        const data = await requestJson('/api/v1/branches');
                        renderBranchOptions(data.branches || []);
                    } catch (error) {
                        el.branch.innerHTML = '<option value="">Semua Cabang</option>';
                    }
                };

                el.refresh.addEventListener('click', load);
                el.remediateSelected.addEventListener('click', () => {
                    remediate({ refund_item_ids: state.selectedRefundItemIds }, `${state.selectedRefundItemIds.length} row terpilih`)
                        .then(() => setSelectedIds([]))
                        .catch((error) => showError(error.message || 'Gagal remediasi refund legacy.'));
                });
                el.remediateFilter.addEventListener('click', () => {
                    remediate({}, 'filter aktif').catch((error) => showError(error.message || 'Gagal remediasi refund legacy.'));
                });
                el.filter.addEventListener('change', () => {
                    state.classification = el.filter.value || '';
                    state.page = 1;
                    load();
                });
                el.branch.addEventListener('change', () => {
                    state.branchId = el.branch.value || '';
                    state.page = 1;
                    load();
                });
                el.dateFrom.addEventListener('change', () => {
                    state.dateFrom = el.dateFrom.value || '';
                    state.page = 1;
                    load();
                });
                el.dateTo.addEventListener('change', () => {
                    state.dateTo = el.dateTo.value || '';
                    state.page = 1;
                    load();
                });
                el.prevPage.addEventListener('click', () => {
                    if (state.page <= 1) {
                        return;
                    }

                    state.page -= 1;
                    load();
                });
                el.nextPage.addEventListener('click', () => {
                    if (state.page >= state.lastPage) {
                        return;
                    }

                    state.page += 1;
                    load();
                });
                el.historyPrev.addEventListener('click', () => {
                    if (state.historyPage <= 1) {
                        return;
                    }

                    state.historyPage -= 1;
                    loadHistory();
                });
                el.historyNext.addEventListener('click', () => {
                    if (state.historyPage >= state.historyLastPage) {
                        return;
                    }

                    state.historyPage += 1;
                    loadHistory();
                });
                el.historyActor.addEventListener('change', () => {
                    state.historyActorId = (el.historyActor.value || '').trim();
                    state.historyPage = 1;
                    loadHistory();
                });
                el.historyDateFrom.addEventListener('change', () => {
                    state.historyDateFrom = el.historyDateFrom.value || '';
                    state.historyPage = 1;
                    loadHistory();
                });
                el.historyDateTo.addEventListener('change', () => {
                    state.historyDateTo = el.historyDateTo.value || '';
                    state.historyPage = 1;
                    loadHistory();
                });
                el.selectAll.addEventListener('change', () => {
                    if (!el.selectAll.checked) {
                        setSelectedIds([]);
                        renderRows(state.rows);
                        return;
                    }

                    setSelectedIds(
                        state.rows
                            .filter((row) => row.classification === 'resolvable_single_match')
                            .map((row) => row.refund_item_id)
                    );
                    renderRows(state.rows);
                });
                el.list.addEventListener('click', (event) => {
                    const button = event.target.closest('.btn-remediate-row');
                    if (!button) {
                        return;
                    }

                    const refundItemId = button.dataset.refundItemId;
                    if (!refundItemId) {
                        return;
                    }

                    remediate({ refund_item_ids: [refundItemId] }, `refund item ${refundItemId}`).catch((error) => showError(error.message || 'Gagal remediasi refund legacy.'));
                });
                el.list.addEventListener('change', (event) => {
                    const checkbox = event.target.closest('.row-selector');
                    if (!checkbox) {
                        return;
                    }

                    const refundItemId = checkbox.dataset.refundItemId;
                    if (!refundItemId) {
                        return;
                    }

                    const nextIds = new Set(state.selectedRefundItemIds);
                    if (checkbox.checked) {
                        nextIds.add(refundItemId);
                    } else {
                        nextIds.delete(refundItemId);
                    }

                    setSelectedIds(Array.from(nextIds));
                    syncSelectionUi();
                });

                syncExportHref();
                loadBranches().finally(() => Promise.all([load(), loadHistory()]));
            })();
        </script>
@endsection