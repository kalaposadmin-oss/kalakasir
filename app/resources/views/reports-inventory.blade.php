@extends('layouts.app')

@section('page_title', 'Laporan Persediaan')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative flex h-screen w-full flex-col overflow-hidden bg-white dark:bg-slate-900')
@section('main_class', 'flex-1 overflow-y-auto pb-24 lg:pb-8 lg:pl-72')

@section('header')
    <x-page-header title="Laporan Persediaan" backUrl="/backoffice/reports">
        <x-slot name="actions">
            <button id="btn-refresh-dashboard" class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-600" type="button">
                <span class="material-symbols-outlined text-[20px]">refresh</span>
            </button>
            @feature('reports.export_csv')
                <a class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-600" href="/api/v1/reports/inventory/export/csv">
                    <span class="material-symbols-outlined text-[20px]">download</span>
                </a>
            @endfeature
            @feature('reports.download_pdf')
                <a class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-600" href="/api/v1/reports/inventory/export/pdf">
                    <span class="material-symbols-outlined text-[20px]">picture_as_pdf</span>
                </a>
            @endfeature
        </x-slot>
    </x-page-header>
@endsection

@section('content')
    @php
        $inventoryReportTenant = \App\Support\TenantContext::get();
        $inventoryReportPlan = $inventoryReportTenant
            ? app(\App\Services\SubscriptionService::class)->currentPlan($inventoryReportTenant)
            : null;
        $canViewInventoryReport = \App\Support\FeatureGateConfig::isFeatureAllowed('reports.inventory_view', $inventoryReportPlan);
    @endphp

    @if ($canViewInventoryReport)
    <section class="mx-auto w-full max-w-6xl px-4 pt-4 lg:px-6">
        <x-period-filter id="inventory-report-period" defaultPreset="last7" />
        <div class="mt-3 flex gap-2 overflow-x-auto no-scrollbar">
            <button class="flex shrink-0 items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-slate-700 text-xs font-semibold relative">
                <span class="material-symbols-outlined text-sm">location_on</span>
                <span id="inventory-location-label">Semua Lokasi</span>
                <span class="material-symbols-outlined text-sm">expand_more</span>
                <select id="inventory-location-filter" class="absolute inset-0 opacity-0 cursor-pointer"></select>
            </button>
        </div>
        <div class="grid gap-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Ringkasan</p>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[11px] font-semibold text-slate-500">Jumlah SKU</p>
                        <p id="kpi-sku" class="text-xl font-bold text-slate-900">-</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[11px] font-semibold text-slate-500">SKU Stok Rendah</p>
                        <p id="kpi-low" class="text-xl font-bold text-slate-900">-</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[11px] font-semibold text-slate-500">SKU Habis</p>
                        <p id="kpi-oos" class="text-xl font-bold text-slate-900">-</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-[11px] font-semibold text-slate-500">Nilai Stok (Est.)</p>
                        <p id="kpi-value" class="text-xl font-bold text-slate-900">-</p>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Top 5 Stok Rendah</p>
                        <p class="text-xs text-slate-500">Prioritas reorder minggu ini</p>
                    </div>
                    <a class="text-xs font-semibold text-primary" href="/backoffice/inventory">Lihat Stok</a>
                </div>
                <div id="low-stock-list" class="mt-3 space-y-3">
                    <p class="text-xs text-slate-500">Memuat data...</p>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Pergerakan Stok</p>
                        <p id="movement-range-label" class="text-xs text-slate-500">Masuk vs Keluar (ringkas)</p>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-emerald-50 p-3">
                        <p class="text-[11px] font-semibold text-emerald-600">Masuk</p>
                        <p id="mv-in" class="text-xl font-bold text-emerald-700">-</p>
                    </div>
                    <div class="rounded-xl bg-rose-50 p-3">
                        <p class="text-[11px] font-semibold text-rose-600">Keluar</p>
                        <p id="mv-out" class="text-xl font-bold text-rose-700">-</p>
                    </div>
                </div>
                <div id="movement-type-list" class="mt-3 space-y-2"></div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Audit Trail Operasional</p>
                        <p class="text-xs text-slate-500">Siapa melakukan apa, kapan, dan pada entity operasional yang terkait</p>
                    </div>
                </div>
                <div id="audit-category-filter" class="mt-3 flex gap-2 overflow-x-auto no-scrollbar"></div>
                <div class="mt-3 grid gap-4 lg:grid-cols-2">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Movement Terbaru</p>
                        <div id="recent-movement-list" class="mt-2 space-y-2"></div>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Audit / Anomaly</p>
                        <div id="audit-trail-list" class="mt-2 space-y-2"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-4 grid gap-3">
            <a class="inline-flex w-full items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-semibold text-slate-700 shadow-sm" href="/backoffice/inventory">
                <span>Lihat Stok Rendah</span>
                <span class="material-symbols-outlined text-base">chevron_right</span>
            </a>
            @feature('purchase.stock_purchase')
                <button id="btn-reorder" class="inline-flex w-full items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-semibold text-slate-700 shadow-sm" type="button">
                    <span>Buat PO / Reorder</span>
                    <span class="material-symbols-outlined text-base">chevron_right</span>
                </button>
            @endfeature
        </div>
    </section>
    <div id="reorder-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-2xl rounded-t-3xl bg-white p-4 shadow-2xl">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-900">Preview Reorder</h3>
                <button id="reorder-close" class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-600" type="button">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <p class="mt-1 text-xs text-slate-500">Pilih item dan sesuaikan qty sebelum membuat draft PO.</p>
            <div id="reorder-list" class="mt-4 space-y-3 max-h-[45vh] overflow-y-auto"></div>
            <div class="mt-4 flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 text-sm">
                <span class="text-slate-500">Item terpilih</span>
                <span id="reorder-count" class="font-semibold text-slate-900">0</span>
            </div>
            <div class="mt-4 flex gap-3">
                <button id="reorder-cancel" class="flex-1 rounded-2xl border border-slate-200 py-3 text-sm font-semibold text-slate-600" type="button">Batal</button>
                <button id="reorder-submit" class="flex-[2] rounded-2xl bg-primary py-3 text-sm font-semibold text-white" type="button">Buat Draft PO</button>
            </div>
        </div>
    </div>
    @else
        <section class="mx-auto mt-6 w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
            <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                <span class="material-symbols-outlined text-3xl">lock</span>
            </div>
            <h2 class="text-lg font-semibold text-slate-900">Laporan Persediaan Belum Tersedia di Paket Saat Ini</h2>
            <p class="mt-2 text-sm text-slate-500">Upgrade paket untuk membuka ringkasan stok dan insight persediaan.</p>
            <a class="mt-4 inline-flex items-center gap-2 rounded-full bg-primary px-5 py-2 text-sm font-semibold text-white" href="/backoffice/settings/subscription/change">
                <span class="material-symbols-outlined text-base">upgrade</span>
                Lihat Opsi Upgrade
            </a>
        </section>
    @endif
@endsection

@push('scripts')
    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
            const period = document.getElementById('inventory-report-period');
            const locationFilter = document.getElementById('inventory-location-filter');
            const locationLabel = document.getElementById('inventory-location-label');
            const listEl = document.getElementById('low-stock-list');
            const movementTypeList = document.getElementById('movement-type-list');
            const recentMovementList = document.getElementById('recent-movement-list');
            const auditTrailList = document.getElementById('audit-trail-list');
            const auditCategoryFilter = document.getElementById('audit-category-filter');
            const movementRangeLabel = document.getElementById('movement-range-label');
            const reorderBtn = document.getElementById('btn-reorder');
            const reorderModal = document.getElementById('reorder-modal');
            const reorderList = document.getElementById('reorder-list');
            const reorderCount = document.getElementById('reorder-count');
            const reorderClose = document.getElementById('reorder-close');
            const reorderCancel = document.getElementById('reorder-cancel');
            const reorderSubmit = document.getElementById('reorder-submit');
            const refreshButton = document.getElementById('btn-refresh-dashboard');
            const exportCsvLink = document.querySelector('a[href^="/api/v1/reports/inventory/export/csv"]');
            const exportPdfLink = document.querySelector('a[href^="/api/v1/reports/inventory/export/pdf"]');
            let lowStockItems = [];
            const state = {
                period: { preset: 'last7', start_date: '', end_date: '' },
                locations: [],
                locationId: '',
                auditCategory: '',
            };

            const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            }).format(Number(value || 0));

            const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }).format(new Date(value));

            const formatQty = (value) => {
                const qty = Number(value || 0);
                return Number.isInteger(qty) ? String(qty) : qty.toFixed(2);
            };

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const buildExportUrl = (base) => {
                const url = new URL(base, window.location.origin);
                if (state.period.preset) url.searchParams.set('preset', state.period.preset);
                if (state.period.start_date) url.searchParams.set('start_date', state.period.start_date);
                if (state.period.end_date) url.searchParams.set('end_date', state.period.end_date);
                if (state.locationId) url.searchParams.set('location_id', state.locationId);
                return `${url.pathname}${url.search}`;
            };

            const syncExportLinks = () => {
                if (exportCsvLink) {
                    exportCsvLink.href = buildExportUrl('/api/v1/reports/inventory/export/csv');
                }
                if (exportPdfLink) {
                    exportPdfLink.href = buildExportUrl('/api/v1/reports/inventory/export/pdf');
                }
            };

            const buildLocationOptions = (locations) => {
                if (!locationFilter) {
                    return;
                }
                locationFilter.innerHTML = '';
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Semua Lokasi';
                locationFilter.appendChild(defaultOption);
                locations.forEach((location) => {
                    const option = document.createElement('option');
                    option.value = location.id;
                    option.textContent = location.is_default ? `${location.name} (default)` : location.name;
                    locationFilter.appendChild(option);
                });
                const hasSelectedLocation = locations.some((location) => location.id === state.locationId);
                if (!hasSelectedLocation) {
                    const defaultLocation = locations.find((location) => location.is_default);
                    state.locationId = defaultLocation?.id || locations[0]?.id || '';
                }

                locationFilter.value = state.locationId;
                const label = locationFilter.options[locationFilter.selectedIndex]?.textContent || 'Semua Lokasi';
                if (locationLabel) {
                    locationLabel.textContent = label;
                }
            };

            const renderLowStockList = (items) => {
                if (!listEl) {
                    return;
                }
                listEl.innerHTML = '';
                if (!items.length) {
                    listEl.innerHTML = '<p class="text-xs text-slate-500">Belum ada stok rendah.</p>';
                    return;
                }
                items.forEach((item) => {
                    const card = document.createElement('div');
                    card.className = 'flex items-center justify-between rounded-xl bg-slate-50 p-3';
                    card.innerHTML = `
                        <div>
                            <p class="text-sm font-semibold text-slate-900">${item.name || '-'}</p>
                            <p class="text-[11px] text-slate-500">SKU: ${item.sku || '-'}</p>
                        </div>
                        <span class="text-sm font-semibold text-rose-600">${item.qty_on_hand ?? 0}</span>
                    `;
                    listEl.appendChild(card);
                });
            };

            const renderAuditCategoryFilter = (categories) => {
                if (!auditCategoryFilter) {
                    return;
                }
                const rows = Array.isArray(categories) && categories.length
                    ? categories
                    : [
                        { value: '', label: 'Semua', selected: true },
                        { value: 'inventory', label: 'Inventory', selected: false },
                        { value: 'purchase', label: 'Purchase', selected: false },
                        { value: 'sales', label: 'Sales', selected: false },
                        { value: 'anomaly', label: 'Anomaly', selected: false },
                    ];

                auditCategoryFilter.innerHTML = '';
                rows.forEach((row) => {
                    const active = Boolean(row.selected);
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = active
                        ? 'shrink-0 rounded-full bg-slate-900 px-3 py-1.5 text-[11px] font-semibold text-white'
                        : 'shrink-0 rounded-full bg-slate-100 px-3 py-1.5 text-[11px] font-semibold text-slate-600';
                    button.textContent = row.label || row.value || 'Semua';
                    button.dataset.auditCategory = row.value || '';
                    button.addEventListener('click', () => {
                        const nextValue = button.dataset.auditCategory || '';
                        if (state.auditCategory === nextValue) {
                            return;
                        }
                        state.auditCategory = nextValue;
                        loadDashboard();
                    });
                    auditCategoryFilter.appendChild(button);
                });
            };

            const renderMovementTypes = (rows) => {
                if (!movementTypeList) {
                    return;
                }
                if (!rows.length) {
                    movementTypeList.innerHTML = '<p class="text-xs text-slate-500">Belum ada movement pada periode ini.</p>';
                    return;
                }
                movementTypeList.innerHTML = '';
                rows.forEach((row) => {
                    const card = document.createElement('div');
                    card.className = 'flex items-center justify-between rounded-xl bg-slate-50 p-3';
                    card.innerHTML = `
                        <div>
                            <p class="text-sm font-semibold text-slate-900">${row.label || row.reference_type || '-'}</p>
                            <p class="text-[11px] text-slate-500">Masuk ${formatQty(row.qty_in)} · Keluar ${formatQty(row.qty_out)}</p>
                        </div>
                        <span class="text-sm font-bold ${Number(row.net || 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'}">${Number(row.net || 0) >= 0 ? '+' : ''}${formatQty(row.net || 0)}</span>
                    `;
                    movementTypeList.appendChild(card);
                });
            };

            const renderRecentMovements = (rows) => {
                if (!recentMovementList) {
                    return;
                }
                if (!rows.length) {
                    recentMovementList.innerHTML = '<p class="text-xs text-slate-500">Belum ada movement terbaru.</p>';
                    return;
                }
                recentMovementList.innerHTML = '';
                rows.forEach((row) => {
                    const delta = Number(row.qty_in || 0) > 0 ? Number(row.qty_in || 0) : -Number(row.qty_out || 0);
                    const card = document.createElement('div');
                    card.className = 'rounded-xl bg-slate-50 p-3';
                    card.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">${row.product_name || '-'}</p>
                                <p class="text-[11px] text-slate-500">${row.label || row.reference_type || '-'} · ${row.location_name || '-'}</p>
                                <p class="text-[11px] text-slate-400">${row.created_at ? formatDate(row.created_at) : '-'}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold ${delta >= 0 ? 'text-emerald-600' : 'text-rose-600'}">${delta >= 0 ? '+' : ''}${formatQty(delta)}</p>
                                <p class="text-[11px] text-slate-400">Saldo ${formatQty(row.balance_after || 0)}</p>
                            </div>
                        </div>
                    `;
                    recentMovementList.appendChild(card);
                });
            };

            const renderAuditTrail = (rows) => {
                if (!auditTrailList) {
                    return;
                }
                if (!rows.length) {
                    auditTrailList.innerHTML = '<p class="text-xs text-slate-500">Belum ada audit trail pada periode ini.</p>';
                    return;
                }
                auditTrailList.innerHTML = '';
                rows.forEach((row) => {
                    const severity = String(row.severity || 'info').toLowerCase();
                    const severityClass = severity === 'high'
                        ? 'bg-rose-100 text-rose-700'
                        : severity === 'warning' || severity === 'medium'
                            ? 'bg-amber-100 text-amber-700'
                            : 'bg-slate-100 text-slate-600';
                    const actorLine = row.actor_name ? `oleh ${row.actor_name}` : 'oleh sistem';
                    const eventLine = row.title || row.label || row.type || '-';
                    const entityLine = [
                        row.entity_label || null,
                        row.summary || null,
                    ].filter(Boolean).join(' · ');
                    const card = document.createElement('div');
                    card.className = 'rounded-xl bg-slate-50 p-3';
                    const status = row.status || 'open';
                    const showSeverity = ['warning', 'medium', 'high', 'critical'].includes(severity);
                    card.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">${escapeHtml(actorLine)}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">${escapeHtml(eventLine)}</p>
                                <p class="mt-1 text-[11px] text-slate-500">${escapeHtml(entityLine || '-')}</p>
                                <p class="mt-1 text-[11px] text-slate-400">${row.happened_at ? formatDate(row.happened_at) : row.detected_at ? formatDate(row.detected_at) : '-'}</p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-2">
                                <span class="rounded-full px-2 py-1 text-[10px] font-bold ${status === 'resolved' || status === 'completed' || status === 'received' || status === 'closed' ? 'bg-emerald-100 text-emerald-700' : status === 'voided' || status === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'}">${escapeHtml(status)}</span>
                                ${showSeverity ? `<span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase ${severityClass}">${escapeHtml(severity)}</span>` : ''}
                            </div>
                        </div>
                    `;
                    auditTrailList.appendChild(card);
                });
            };

            const updateReorderCount = () => {
                if (!reorderCount || !reorderList) {
                    return;
                }
                const checked = reorderList.querySelectorAll('input[type="checkbox"]:checked').length;
                reorderCount.textContent = String(checked);
            };

            const renderReorderModal = (items) => {
                if (!reorderList) {
                    return;
                }
                reorderList.innerHTML = '';
                if (!items.length) {
                    reorderList.innerHTML = '<p class="text-xs text-slate-500">Belum ada item stok rendah.</p>';
                    updateReorderCount();
                    return;
                }
                items.forEach((item) => {
                    const suggested = Math.max((item.suggested_qty ?? 0), 0);
                    const supplierName = item.last_supplier_name || 'Belum ada pemasok';
                    const supplierClass = item.last_supplier_name ? 'text-slate-500' : 'text-amber-600';
                    const row = document.createElement('div');
                    row.className = 'rounded-2xl border border-slate-200 p-3';
                    row.innerHTML = `
                        <div class="flex items-start gap-3">
                            <input class="mt-1 size-4" type="checkbox" data-product-id="${item.product_id}" checked />
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-slate-900">${item.name || '-'}</p>
                                <p class="text-[11px] text-slate-500">SKU: ${item.sku || '-'} · Stok: ${item.qty_on_hand ?? 0} · Min: ${item.min_stock ?? 0}</p>
                                <p class="text-[11px] ${supplierClass}">Pemasok: ${supplierName}</p>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center gap-3">
                            <label class="text-[11px] font-semibold text-slate-500">Qty Saran</label>
                            <input class="reorder-qty h-9 w-24 rounded-lg border border-slate-200 px-2 text-sm" type="number" min="0" value="${suggested}" data-product-id="${item.product_id}" />
                        </div>
                    `;
                    reorderList.appendChild(row);
                });
                reorderList.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                    checkbox.addEventListener('change', updateReorderCount);
                });
                updateReorderCount();
            };

            const openModal = () => {
                if (!reorderModal) {
                    return;
                }
                reorderModal.classList.remove('hidden');
            };

            const closeModal = () => {
                if (!reorderModal) {
                    return;
                }
                reorderModal.classList.add('hidden');
            };

            const loadSummary = async () => {
                const url = new URL('/api/v1/reports/inventory/summary', window.location.origin);
                if (state.period.preset) url.searchParams.set('preset', state.period.preset);
                if (state.period.start_date) url.searchParams.set('start_date', state.period.start_date);
                if (state.period.end_date) url.searchParams.set('end_date', state.period.end_date);
                if (state.locationId) url.searchParams.set('location_id', state.locationId);
                if (state.auditCategory) url.searchParams.set('audit_category', state.auditCategory);
                const response = await fetch(url, { headers });
                if (!response.ok) {
                    throw new Error('Gagal memuat ringkasan persediaan.');
                }
                const payload = await response.json().catch(() => ({}));
                const data = window.PosUi.unwrapApiPayload(payload) || {};
                const sku = document.getElementById('kpi-sku');
                const low = document.getElementById('kpi-low');
                const oos = document.getElementById('kpi-oos');
                const value = document.getElementById('kpi-value');
                const mvIn = document.getElementById('mv-in');
                const mvOut = document.getElementById('mv-out');

                if (sku) sku.textContent = data.sku_count ?? '-';
                if (low) low.textContent = data.low_stock_count ?? '-';
                if (oos) oos.textContent = data.out_of_stock_count ?? '-';
                if (value) value.textContent = formatCurrency(data.total_value ?? 0);
                if (mvIn) mvIn.textContent = formatQty(data.movement_range?.in ?? 0);
                if (mvOut) mvOut.textContent = formatQty(data.movement_range?.out ?? 0);
                if (movementRangeLabel) {
                    movementRangeLabel.textContent = data.range?.label
                        ? `${data.range.label} (${data.range.start_date} - ${data.range.end_date})`
                        : 'Masuk vs Keluar (ringkas)';
                }
                state.auditCategory = data.audit_category || '';
                renderAuditCategoryFilter(Array.isArray(data.audit_categories) ? data.audit_categories : []);
                renderMovementTypes(Array.isArray(data.movement_range?.by_type) ? data.movement_range.by_type : []);
                renderRecentMovements(Array.isArray(data.recent_movements) ? data.recent_movements : []);
                renderAuditTrail(Array.isArray(data.audit_trail) ? data.audit_trail : []);
            };

            const loadLowStock = async () => {
                const response = await fetch('/api/v1/reports/inventory/low-stock?limit=10', { headers });
                if (!response.ok) {
                    throw new Error('Gagal memuat stok rendah.');
                }
                const payload = await response.json().catch(() => ({}));
                const data = window.PosUi.unwrapApiPayload(payload) || {};
                return Array.isArray(data.items) ? data.items : [];
            };

            const loadDashboard = async () => {
                try {
                    const [_, lowStock] = await Promise.all([loadSummary(), loadLowStock()]);
                    lowStockItems = lowStock;
                    renderLowStockList(lowStock.slice(0, 5));
                } catch (_) {
                    renderLowStockList([]);
                }
            };

            refreshButton?.addEventListener('click', () => {
                loadDashboard();
            });

            period?.addEventListener('period:change', (event) => {
                state.period = event.detail;
                syncExportLinks();
                loadDashboard();
            });

            locationFilter?.addEventListener('change', (event) => {
                state.locationId = event.target?.value || '';
                const label = locationFilter.options[locationFilter.selectedIndex]?.textContent || 'Semua Lokasi';
                if (locationLabel) {
                    locationLabel.textContent = label;
                }
                syncExportLinks();
                loadDashboard();
            });

            (async () => {
                try {
                    const data = await window.PosUi.getBootstrap({ headers, ttlMs: 20000 });
                    if (data) {
                        state.locations = Array.isArray(data.locations) ? data.locations : [];
                        buildLocationOptions(state.locations);
                    }
                } catch (_) {
                    // Ignore bootstrap failure for location filter.
                }
                syncExportLinks();
                loadDashboard();
            })();

            if (reorderBtn) {
                reorderBtn.addEventListener('click', () => {
                    renderReorderModal(lowStockItems);
                    openModal();
                });
            }

            reorderClose?.addEventListener('click', closeModal);
            reorderCancel?.addEventListener('click', closeModal);
            reorderModal?.addEventListener('click', (event) => {
                if (event.target === reorderModal) {
                    closeModal();
                }
            });

            reorderSubmit?.addEventListener('click', async () => {
                if (!reorderList) {
                    return;
                }
                const selected = Array.from(reorderList.querySelectorAll('input[type="checkbox"]:checked'))
                    .map((checkbox) => checkbox.getAttribute('data-product-id'))
                    .filter(Boolean);
                if (!selected.length) {
                    alert('Pilih minimal satu item.');
                    return;
                }

                const qtyInputs = Array.from(reorderList.querySelectorAll('.reorder-qty'));
                const items = qtyInputs
                    .filter((input) => selected.includes(input.getAttribute('data-product-id')))
                    .map((input) => ({
                        product_id: input.getAttribute('data-product-id'),
                        suggested_qty: Math.max(Number(input.value || 0), 0),
                    }))
                    .filter((item) => item.suggested_qty > 0);

                if (!items.length) {
                    alert('Qty saran harus lebih dari 0.');
                    return;
                }

                reorderSubmit.disabled = true;
                try {
                    const response = await fetch('/api/v1/purchases/prefill', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            ...headers,
                        },
                        body: JSON.stringify({ items }),
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        alert(payload.message || 'Gagal membuat draft PO.');
                        return;
                    }
                    if (payload.created_count > 1) {
                        alert(`${payload.created_count} draft PO dibuat untuk beberapa supplier.`);
                    }
                    if (payload.dropped_items?.length) {
                        const names = payload.dropped_items
                            .map((item) => item.name || item.sku || '-')
                            .join(', ');
                        alert(`Item diabaikan karena qty 0: ${names}`);
                    }
                    window.location.href = payload.redirect_url || '/backoffice/purchases/new';
                } catch (error) {
                    alert(error?.message || 'Gagal membuat draft PO.');
                } finally {
                    reorderSubmit.disabled = false;
                }
            });
        })();
    </script>
@endpush

