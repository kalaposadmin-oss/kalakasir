@extends('layouts.app')

@php
    $inventoryValuationTenant = \App\Support\TenantContext::get();
    $inventoryValuationUser = auth()->user();
    $inventoryValuationRole = null;

    if (! $inventoryValuationTenant && filled($inventoryValuationUser?->tenant_id)) {
        $inventoryValuationTenant = \App\Models\Tenant::query()->find($inventoryValuationUser->tenant_id);
    }

    if ($inventoryValuationTenant && $inventoryValuationUser) {
        $inventoryValuationRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $inventoryValuationTenant->id, (string) $inventoryValuationUser->id);

        if (! $inventoryValuationRole && filled($inventoryValuationUser->role ?? null)) {
            $inventoryValuationRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $inventoryValuationUser->role);
        }
    }

    $inventoryValuationSubscription = app(\App\Services\SubscriptionService::class);
    $inventoryValuationStatus = $inventoryValuationTenant
        ? $inventoryValuationSubscription->currentStatus($inventoryValuationTenant)
        : null;

    abort_unless(in_array($inventoryValuationRole, ['admin', 'owner', 'superadmin'], true), 403);
    abort_unless(
        $inventoryValuationTenant
        && $inventoryValuationSubscription->canAccessFeature($inventoryValuationTenant, 'settings.inventory_valuation')
        && in_array($inventoryValuationStatus, ['active', 'trialing'], true),
        403
    );
@endphp

@section('page_title', 'Valuation Persediaan')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display min-h-screen')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto p-0')

@push('head')
        @include('partials.ui-helpers')
        <style>
            .method-card {
                border: 1px solid rgb(226 232 240);
                transition: border-color .2s ease, box-shadow .2s ease;
            }
            .method-card.active {
                border-color: rgb(19 91 236);
                box-shadow: 0 0 0 2px rgba(19, 91, 236, 0.12);
            }
        </style>
@endpush

@section('header')
        <x-page-header title="Pengaturan Stok" backUrl="/backoffice/dashboard" />
@endsection

@section('content')
        <div class="flex-1 overflow-y-auto">
            <section class="px-4 pt-6 pb-2">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Metode Penilaian Inventaris</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Pilih metode penilaian stok untuk menghitung nilai persediaan dan HPP.</p>
                <p class="mt-2 text-xs text-slate-500">Metode aktif: <span id="method-current" class="font-semibold">-</span></p>
                <p id="method-last-change" class="mt-1 text-[11px] text-slate-500">Perubahan terakhir: -</p>
            </section>

            <section class="p-4 space-y-4">
                <label id="card-fifo" class="method-card active block overflow-hidden rounded-xl bg-white dark:bg-slate-900 cursor-pointer">
                    <input id="method-fifo" class="sr-only" name="valuation_method" type="radio" value="fifo" />
                    <div class="aspect-[21/9] bg-gradient-to-br from-primary/20 to-primary/40 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary text-5xl opacity-60">inventory_2</span>
                    </div>
                    <div class="p-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex rounded bg-primary/10 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-primary">Rekomendasi</span>
                            <span data-check="fifo" class="material-symbols-outlined text-primary">check_circle</span>
                        </div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">FIFO (First-In, First-Out)</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Stok yang masuk lebih dulu akan dikeluarkan lebih dulu.</p>
                    </div>
                </label>

                <label id="card-average" class="method-card block overflow-hidden rounded-xl bg-white dark:bg-slate-900 cursor-pointer">
                    <input id="method-average" class="sr-only" name="valuation_method" type="radio" value="average" />
                    <div class="aspect-[21/9] bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-700 dark:to-slate-800 flex items-center justify-center">
                        <span class="material-symbols-outlined text-slate-500 text-5xl opacity-60">calculate</span>
                    </div>
                    <div class="p-4 space-y-2">
                        <div class="flex items-center justify-between min-h-[20px]">
                            <span></span>
                            <span data-check="average" class="material-symbols-outlined text-slate-300 dark:text-slate-700">radio_button_unchecked</span>
                        </div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Average Cost (Biaya Rata-rata)</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Nilai stok dihitung dari rata-rata biaya perolehan unit yang tersedia.</p>
                    </div>
                </label>

                <div class="rounded-lg border border-primary/10 bg-primary/5 p-4 flex gap-3">
                    <span class="material-symbols-outlined text-primary shrink-0">info</span>
                    <p class="text-xs text-primary/80">Perubahan metode akan memicu rebuild historis. Pastikan transaksi kritis sudah selesai sebelum menyimpan.</p>
                </div>

                <div class="rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Impact Preview</p>
                        <span id="impact-pill" class="rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:text-slate-300">-</span>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                        <p class="text-slate-500">Sales completed</p><p id="impact-sales" class="text-right font-semibold">-</p>
                        <p class="text-slate-500">Sale items</p><p id="impact-items" class="text-right font-semibold">-</p>
                        <p class="text-slate-500">Stock ledger rows</p><p id="impact-ledger" class="text-right font-semibold">-</p>
                        <p class="text-slate-500">Produk tracked</p><p id="impact-products" class="text-right font-semibold">-</p>
                    </div>
                    <p id="impact-range" class="mt-2 text-[11px] text-slate-500">-</p>
                </div>

                <button id="btn-save-method" class="w-full rounded-xl bg-primary py-3.5 font-bold text-white shadow-lg shadow-primary/20">Simpan Pengaturan</button>
            </section>

            <section class="mx-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3">
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-500">Rebuild Async</h2>
                <button id="btn-rebuild" class="w-full bg-primary text-white py-2.5 rounded-xl font-semibold">Mulai Rebuild Background</button>
                <div class="grid grid-cols-2 gap-2">
                    <button id="btn-rebuild-cancel" class="px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-sm font-semibold">Batalkan Rebuild</button>
                    <button id="btn-rebuild-retry" class="px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-sm font-semibold">Retry Rebuild</button>
                </div>
                <div class="text-sm space-y-1">
                    <p>Status: <span id="rebuild-status" class="font-semibold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800">-</span></p>
                    <p>Request ID: <span id="rebuild-request" class="font-mono text-xs">-</span></p>
                    <p>Runtime: <span id="rebuild-runtime" class="font-semibold">-</span></p>
                    <p id="rebuild-message" class="text-xs text-slate-500">-</p>
                </div>
            </section>

            <section class="mx-4 mt-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3">
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-500">Rekonsiliasi</h2>
                <button id="btn-refresh-recon" class="w-full px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-sm font-semibold">Refresh Ringkasan</button>
                <div class="text-sm space-y-1">
                    <p>Drift Qty: <span id="recon-drift" class="font-semibold">0</span></p>
                    <p>Cost Nol: <span id="recon-zero" class="font-semibold">0</span></p>
                    <p>HPP Nol: <span id="recon-hpp" class="font-semibold">0</span></p>
                </div>
            </section>

            <section class="mx-4 mt-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3">
                <h2 class="text-sm font-bold uppercase tracking-widest text-slate-500">Report</h2>
                <button id="btn-export" class="w-full px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-sm font-semibold">Generate Report Baru</button>
                <div class="grid grid-cols-2 gap-2">
                    <button id="btn-export-cancel" class="px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-sm font-semibold">Batalkan Export</button>
                    <button id="btn-export-retry" class="px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-sm font-semibold">Retry Export</button>
                </div>
                <p>Request Export Terakhir: <span id="export-request" class="font-mono text-xs">-</span></p>
                <div id="report-list" class="space-y-2 text-sm"></div>
            </section>

            <a href="/backoffice/settings/inventory-valuation-audit" class="mx-4 mt-4 block text-center rounded-xl border border-slate-200 dark:border-slate-800 p-3 text-sm font-semibold bg-white dark:bg-slate-900">Buka Dashboard Audit Valuation</a>

            <p id="error-box" class="mx-4 mt-4 hidden rounded-lg p-3 text-sm bg-red-50 text-red-700"></p>
            <p id="toast-box" class="mx-4 mt-4 mb-8 hidden rounded-lg p-3 text-sm bg-emerald-50 text-emerald-700"></p>

            <div id="rebuild-ack-modal" class="hidden fixed inset-0 z-50 bg-slate-900/50 p-4">
                <div class="mx-auto mt-24 w-full max-w-sm rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4">
                    <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Konfirmasi Final</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Ketik <span class="font-bold">REBUILD</span> untuk melanjutkan perubahan metode valuation.</p>
                    <input id="rebuild-ack-input" class="mt-3 w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-sm" placeholder="Ketik REBUILD" type="text" />
                    <p id="rebuild-ack-error" class="mt-2 hidden text-xs text-red-600">Input harus persis REBUILD.</p>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button id="rebuild-ack-cancel" class="rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm font-semibold">Batal</button>
                        <button id="rebuild-ack-confirm" class="rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white">Lanjutkan</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = {
                    'Content-Type': 'application/json',
                    ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
                };

                const el = {
                    methodCurrent: document.getElementById('method-current'),
                    methodLastChange: document.getElementById('method-last-change'),
                    methodFifo: document.getElementById('method-fifo'),
                    methodAverage: document.getElementById('method-average'),
                    cardFifo: document.getElementById('card-fifo'),
                    cardAverage: document.getElementById('card-average'),
                    btnSaveMethod: document.getElementById('btn-save-method'),
                    impactPill: document.getElementById('impact-pill'),
                    impactSales: document.getElementById('impact-sales'),
                    impactItems: document.getElementById('impact-items'),
                    impactLedger: document.getElementById('impact-ledger'),
                    impactProducts: document.getElementById('impact-products'),
                    impactRange: document.getElementById('impact-range'),
                    btnRebuild: document.getElementById('btn-rebuild'),
                    btnRebuildCancel: document.getElementById('btn-rebuild-cancel'),
                    btnRebuildRetry: document.getElementById('btn-rebuild-retry'),
                    rebuildStatus: document.getElementById('rebuild-status'),
                    rebuildRequest: document.getElementById('rebuild-request'),
                    rebuildRuntime: document.getElementById('rebuild-runtime'),
                    rebuildMessage: document.getElementById('rebuild-message'),
                    btnRefreshRecon: document.getElementById('btn-refresh-recon'),
                    reconDrift: document.getElementById('recon-drift'),
                    reconZero: document.getElementById('recon-zero'),
                    reconHpp: document.getElementById('recon-hpp'),
                    btnExport: document.getElementById('btn-export'),
                    btnExportCancel: document.getElementById('btn-export-cancel'),
                    btnExportRetry: document.getElementById('btn-export-retry'),
                    exportRequest: document.getElementById('export-request'),
                    reportList: document.getElementById('report-list'),
                    errorBox: document.getElementById('error-box'),
                    toastBox: document.getElementById('toast-box'),
                    ackModal: document.getElementById('rebuild-ack-modal'),
                    ackInput: document.getElementById('rebuild-ack-input'),
                    ackError: document.getElementById('rebuild-ack-error'),
                    ackCancel: document.getElementById('rebuild-ack-cancel'),
                    ackConfirm: document.getElementById('rebuild-ack-confirm'),
                };

                let lastExportRequestId = null;
                let lastRebuildRequestId = null;
                let currentMethod = 'average';
                let selectedMethod = 'average';

                const showError = (message) => {
                    el.errorBox.classList.remove('hidden');
                    el.errorBox.textContent = message;
                };

                const clearError = () => {
                    el.errorBox.classList.add('hidden');
                    el.errorBox.textContent = '';
                };

                const showToast = (message) => {
                    el.toastBox.classList.remove('hidden');
                    el.toastBox.textContent = message;
                    setTimeout(() => {
                        el.toastBox.classList.add('hidden');
                    }, 2500);
                };

                const methodLabel = (method) => method === 'fifo' ? 'FIFO' : 'Average';

                const formatDateTime = (value) => {
                    if (!value) return '-';
                    return new Intl.DateTimeFormat('id-ID', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    }).format(new Date(value));
                };

                const parseChangeMeta = (row) => {
                    if (!row || !row.meta_json) return null;
                    try {
                        return typeof row.meta_json === 'string' ? JSON.parse(row.meta_json) : row.meta_json;
                    } catch (_) {
                        return null;
                    }
                };

                const renderMethodSelection = () => {
                    const isFifo = selectedMethod === 'fifo';
                    el.methodFifo.checked = isFifo;
                    el.methodAverage.checked = !isFifo;
                    el.cardFifo.classList.toggle('active', isFifo);
                    el.cardAverage.classList.toggle('active', !isFifo);

                    const fifoCheck = document.querySelector('[data-check="fifo"]');
                    const avgCheck = document.querySelector('[data-check="average"]');
                    if (fifoCheck) fifoCheck.textContent = isFifo ? 'check_circle' : 'radio_button_unchecked';
                    if (avgCheck) avgCheck.textContent = isFifo ? 'radio_button_unchecked' : 'check_circle';
                };

                const loadImpactPreview = async () => {
                    const data = await requestJson(`/api/v1/settings/valuation-impact-preview?target_method=${encodeURIComponent(selectedMethod)}`);
                    const impact = data.impact || {};
                    const willRebuild = !!data.will_rebuild;
                    el.impactPill.textContent = willRebuild ? 'REBUILD REQUIRED' : 'NO REBUILD';
                    el.impactPill.className = willRebuild
                        ? 'rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700'
                        : 'rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700';
                    el.impactSales.textContent = `${impact.sales_completed ?? 0}`;
                    el.impactItems.textContent = `${impact.sale_items ?? 0}`;
                    el.impactLedger.textContent = `${impact.stock_ledger_rows ?? 0}`;
                    el.impactProducts.textContent = `${impact.tracked_products ?? 0}`;
                    const firstAt = impact.timeline?.first_tx_at;
                    const lastAt = impact.timeline?.last_tx_at;
                    el.impactRange.textContent = `Rentang data: ${formatDateTime(firstAt)} sampai ${formatDateTime(lastAt)}`;
                };

                const setActionLocked = (locked) => {
                    const disabled = !!locked;
                    [el.btnSaveMethod, el.btnRebuild, el.btnExport, el.btnExportCancel, el.btnExportRetry].forEach((button) => {
                        button.disabled = disabled;
                        button.classList.toggle('opacity-60', disabled);
                        button.classList.toggle('cursor-not-allowed', disabled);
                    });
                };

                const statusClass = (status) => {
                    if (status === 'ok' || status === 'success') return 'bg-emerald-100 text-emerald-700';
                    if (status === 'failed') return 'bg-red-100 text-red-700';
                    if (status === 'cancelled') return 'bg-orange-100 text-orange-700';
                    if (status === 'running' || status === 'queued') return 'bg-blue-100 text-blue-700';
                    return 'bg-slate-100 text-slate-700';
                };

                const formatRuntime = (runtimeMs) => {
                    if (typeof runtimeMs !== 'number' || Number.isNaN(runtimeMs)) return '-';
                    if (runtimeMs < 1000) return `${runtimeMs} ms`;
                    return `${(runtimeMs / 1000).toFixed(2)} s`;
                };

                const requestJson = async (url, init = {}) => {
                    const response = await fetch(url, {
                        ...init,
                        headers: {
                            ...headers,
                            ...(init.headers || {}),
                        },
                    });

                    if (!response.ok) {
                        let msg = 'Permintaan gagal.';
                        try {
                            const payload = await response.json();
                            msg = payload.message || msg;
                        } catch (_) {}
                        throw new Error(msg);
                    }

                    return response.json();
                };

                const loadMethod = async () => {
                    const data = await requestJson('/api/v1/settings/tax-promos');
                    const method = data.inventory_valuation_method || 'average';
                    currentMethod = method;
                    selectedMethod = method;
                    el.methodCurrent.textContent = methodLabel(method);

                    const lastChange = data.valuation_method_last_change;
                    if (lastChange) {
                        const meta = parseChangeMeta(lastChange);
                        const fromMethod = meta?.from ? methodLabel(meta.from) : '-';
                        const toMethod = meta?.to ? methodLabel(meta.to) : methodLabel(method);
                        const actor = lastChange.changed_by_name || lastChange.changed_by || 'Sistem';
                        const at = formatDateTime(lastChange.changed_at);
                        el.methodLastChange.textContent = `Perubahan terakhir: ${fromMethod} -> ${toMethod} oleh ${actor} (${at})`;
                    } else {
                        el.methodLastChange.textContent = 'Perubahan terakhir: belum ada';
                    }

                    renderMethodSelection();
                };

                const confirmRebuildAck = () => new Promise((resolve) => {
                    el.ackModal.classList.remove('hidden');
                    el.ackInput.value = '';
                    el.ackError.classList.add('hidden');
                    setTimeout(() => el.ackInput.focus(), 0);

                    const close = (accepted) => {
                        el.ackModal.classList.add('hidden');
                        el.ackCancel.removeEventListener('click', onCancel);
                        el.ackConfirm.removeEventListener('click', onConfirm);
                        el.ackInput.removeEventListener('keydown', onKeydown);
                        resolve(accepted);
                    };

                    const onCancel = () => close(false);
                    const onConfirm = () => {
                        if (el.ackInput.value !== 'REBUILD') {
                            el.ackError.classList.remove('hidden');
                            el.ackInput.focus();
                            return;
                        }
                        close(true);
                    };
                    const onKeydown = (event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            onConfirm();
                        }
                        if (event.key === 'Escape') {
                            event.preventDefault();
                            onCancel();
                        }
                    };

                    el.ackCancel.addEventListener('click', onCancel);
                    el.ackConfirm.addEventListener('click', onConfirm);
                    el.ackInput.addEventListener('keydown', onKeydown);
                });

                const setMethod = async (method) => {
                    clearError();
                    if (method === currentMethod) {
                        showToast('Metode sudah sama, tidak ada perubahan.');
                        return;
                    }
                    if (!window.PosUi.confirmRiskAction({
                        title: 'Konfirmasi Ganti Metode',
                        message: `Metode akan diubah ke ${methodLabel(method)} dan rebuild historis akan dijalankan. Lanjutkan?`,
                    })) {
                        return;
                    }

                    const acked = await confirmRebuildAck();
                    if (!acked) {
                        showError('Konfirmasi dibatalkan. Ketik REBUILD untuk melanjutkan.');
                        return;
                    }

                    await requestJson('/api/v1/settings/inventory-valuation-method', {
                        method: 'PATCH',
                        body: JSON.stringify({ method, confirm_rebuild: true, idempotency_key: `method-${method}-${Date.now()}` }),
                    });
                    await loadMethod();
                    await loadRebuildStatus();
                    showToast(`Metode valuation diubah ke ${methodLabel(method)}.`);
                };

                const loadRebuildStatus = async () => {
                    const data = await requestJson('/api/v1/settings/valuation-rebuild-status');
                    const meta = data.meta || {};
                    const status = meta.status || 'idle';
                    el.rebuildStatus.textContent = meta.status || 'idle';
                    el.rebuildStatus.className = `font-semibold px-2 py-0.5 rounded-full ${statusClass(status)}`;
                    el.rebuildRequest.textContent = meta.request_id || '-';
                    el.rebuildRuntime.textContent = formatRuntime(meta.runtime_ms);
                    el.rebuildMessage.textContent = meta.message || (data.locked ? 'Sedang berjalan.' : '-');
                    lastRebuildRequestId = meta.request_id || null;
                    el.btnRebuildCancel.disabled = !lastRebuildRequestId || !['running'].includes(status);
                    el.btnRebuildRetry.disabled = !lastRebuildRequestId || !['failed', 'cancelled'].includes(status);
                    setActionLocked(data.locked || meta.status === 'running');
                };

                const triggerRebuild = async () => {
                    clearError();
                    if (!window.PosUi.confirmRiskAction({
                        title: 'Konfirmasi Rebuild',
                        message: 'Write API dapat terkunci sementara selama rebuild berjalan. Lanjutkan?',
                    })) {
                        return;
                    }
                    await requestJson('/api/v1/settings/valuation-rebuild', {
                        method: 'POST',
                        body: JSON.stringify({ trigger: 'web_settings', idempotency_key: `rebuild-${Date.now()}` }),
                    });
                    await loadRebuildStatus();
                    showToast('Rebuild valuation dimasukkan ke antrian.');
                };

                const cancelRebuild = async () => {
                    if (!lastRebuildRequestId) return;
                    await requestJson('/api/v1/settings/valuation-rebuild-cancel', {
                        method: 'POST',
                        body: JSON.stringify({ request_id: lastRebuildRequestId, reason: 'cancel from web settings' }),
                    });
                    await loadRebuildStatus();
                    showToast('Request rebuild dibatalkan.');
                };

                const retryRebuild = async () => {
                    if (!lastRebuildRequestId) return;
                    await requestJson('/api/v1/settings/valuation-rebuild-retry', {
                        method: 'POST',
                        body: JSON.stringify({ request_id: lastRebuildRequestId, idempotency_key: `rebuild-retry-${Date.now()}` }),
                    });
                    await loadRebuildStatus();
                    showToast('Retry rebuild dimasukkan ke antrian.');
                };

                const loadReconciliation = async () => {
                    const data = await requestJson('/api/v1/valuation/reconciliation');
                    const summary = data.summary || {};
                    el.reconDrift.textContent = `${summary.qty_drift_rows || 0}`;
                    el.reconZero.textContent = `${summary.zero_cost_rows || 0}`;
                    el.reconHpp.textContent = `${summary.sale_zero_hpp_rows || 0}`;
                };

                const loadReports = async () => {
                    const data = await requestJson('/api/v1/settings/valuation-reports');
                    const reports = data.reports || [];
                    if (!reports.length) {
                        el.reportList.innerHTML = '<p class="text-slate-500 text-xs">Belum ada report.</p>';
                        return;
                    }

                    el.reportList.innerHTML = reports
                        .slice(0, 8)
                        .map((report) => (
                            `<a class="block p-2 rounded-lg bg-slate-50 dark:bg-slate-800 text-primary" href="/api/v1/settings/valuation-reports/${encodeURIComponent(report.name)}">${report.name}</a>`
                        ))
                        .join('');
                };

                const exportReport = async () => {
                    clearError();
                    const queued = await requestJson('/api/v1/settings/valuation-reports/export', {
                        method: 'POST',
                        body: JSON.stringify({ limit: 200, idempotency_key: `export-${Date.now()}` }),
                    });
                    lastExportRequestId = queued.request_id || lastExportRequestId;
                    el.exportRequest.textContent = lastExportRequestId || '-';
                    showToast('Export report dimasukkan ke antrian.');
                };

                const cancelExport = async () => {
                    if (!lastExportRequestId) return;
                    await requestJson('/api/v1/settings/valuation-reports/export-cancel', {
                        method: 'POST',
                        body: JSON.stringify({ request_id: lastExportRequestId, reason: 'cancel from web settings' }),
                    });
                    showToast('Request export dibatalkan.');
                };

                const retryExport = async () => {
                    if (!lastExportRequestId) return;
                    const queued = await requestJson('/api/v1/settings/valuation-reports/export-retry', {
                        method: 'POST',
                        body: JSON.stringify({ request_id: lastExportRequestId, idempotency_key: `export-retry-${Date.now()}` }),
                    });
                    lastExportRequestId = queued.request_id || lastExportRequestId;
                    el.exportRequest.textContent = lastExportRequestId || '-';
                    showToast('Retry export dimasukkan ke antrian.');
                };

                el.methodFifo.addEventListener('change', () => {
                    selectedMethod = 'fifo';
                    renderMethodSelection();
                    loadImpactPreview().catch((error) => showError(error.message));
                });
                el.methodAverage.addEventListener('change', () => {
                    selectedMethod = 'average';
                    renderMethodSelection();
                    loadImpactPreview().catch((error) => showError(error.message));
                });
                el.btnSaveMethod.addEventListener('click', () => setMethod(selectedMethod).catch((error) => showError(error.message)));
                el.btnRebuild.addEventListener('click', () => triggerRebuild().catch((error) => showError(error.message)));
                el.btnRebuildCancel.addEventListener('click', () => cancelRebuild().catch((error) => showError(error.message)));
                el.btnRebuildRetry.addEventListener('click', () => retryRebuild().catch((error) => showError(error.message)));
                el.btnRefreshRecon.addEventListener('click', () => loadReconciliation().catch((error) => showError(error.message)));
                el.btnExport.addEventListener('click', () => exportReport().then(loadReports).catch((error) => showError(error.message)));
                el.btnExportCancel.addEventListener('click', () => cancelExport().catch((error) => showError(error.message)));
                el.btnExportRetry.addEventListener('click', () => retryExport().then(loadReports).catch((error) => showError(error.message)));

                (async () => {
                    try {
                        await loadMethod();
                        await loadImpactPreview();
                        await loadRebuildStatus();
                        await loadReconciliation();
                        await loadReports();
                        setInterval(() => {
                            loadRebuildStatus().catch(() => {});
                        }, 7000);
                    } catch (error) {
                        showError(error.message || 'Gagal memuat data valuation.');
                    }
                })();
            })();
        </script>
@endsection
