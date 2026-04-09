@extends('layouts.app')

@php
    $productionBatchTenant = \App\Support\TenantContext::get();
    $productionBatchUser = auth()->user();
    $productionBatchRole = null;

    if (! $productionBatchTenant && filled($productionBatchUser?->tenant_id)) {
        $productionBatchTenant = \App\Models\Tenant::query()->find($productionBatchUser->tenant_id);
    }

    if ($productionBatchTenant && $productionBatchUser) {
        $productionBatchRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $productionBatchTenant->id, (string) $productionBatchUser->id);

        if (! $productionBatchRole && filled($productionBatchUser->role ?? null)) {
            $productionBatchRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $productionBatchUser->role);
        }
    }

    abort_unless(in_array($productionBatchRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);
    $productionBatchCanViewObservability = in_array($productionBatchRole, ['admin', 'owner', 'superadmin'], true);

    $productionBatchFrom = trim((string) request()->query('from', ''));
    $productionBatchBackUrl = '/backoffice/inventory';
    if ($productionBatchFrom !== '') {
        if (str_starts_with($productionBatchFrom, '/pos/')) {
            $productionBatchBackUrl = '/portal/switch/pos?next='.rawurlencode($productionBatchFrom);
        } elseif (str_starts_with($productionBatchFrom, '/backoffice/')) {
            $productionBatchBackUrl = $productionBatchFrom;
        }
    }
@endphp

@section('page_title', 'Batch Produksi')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto pb-8 lg:pl-72')

@push('head')
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
@endpush

@section('header')
    <header class="sticky top-0 z-10 h-16 border-b border-slate-200 bg-white/95 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95 lg:px-8 lg:pl-[336px]">
        <div class="flex h-full items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ $productionBatchBackUrl }}" class="flex items-center justify-center size-9 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    <span class="material-symbols-outlined text-[20px]">arrow_back_ios_new</span>
                </a>
                <div>
                    <h2 class="text-base font-bold lg:text-lg">Batch Produksi</h2>
                    <p class="hidden text-xs text-slate-500 lg:block">Monitoring draft, hasil posting, dan variance produksi per cabang.</p>
                </div>
            </div>
            <a href="/backoffice/inventory" class="flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <span class="material-symbols-outlined text-sm">inventory_2</span>
                Inventaris
            </a>
        </div>
    </header>
@endsection

@section('content')
    <div class="mx-auto w-full max-w-7xl px-4 py-4 space-y-4 lg:px-8">
        <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Batch Production</p>
                    <h3 class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">Daftar Batch Produksi</h3>
                    <p class="mt-1 text-sm text-slate-500">Filter batch menurut produk, status, cabang, lokasi, dan rentang tanggal transaksi.</p>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <a id="production-batch-export-problematic" href="/api/v1/production-batches/export/problematic" class="rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200">Export Batch Bermasalah</a>
                    <button id="production-batch-reset" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300" type="button">Atur Ulang Filter</button>
                </div>
            </div>
            <div class="mt-4 grid gap-3 lg:grid-cols-7 xl:grid-cols-8">
                <label class="block">
                    <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Produk</span>
                    <select id="production-product-filter" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"></select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Status</span>
                    <select id="production-status-filter" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                        <option value="">Semua Status</option>
                        <option value="draft">Draft</option>
                        <option value="posted">Posted</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Cancel Reason</span>
                    <input id="production-cancel-reason-filter" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200" maxlength="2000" placeholder="Cari alasan pembatalan" type="text" />
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Cabang</span>
                    <select id="production-branch-filter" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"></select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Lokasi</span>
                    <select id="production-location-filter" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"></select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Open Alert</span>
                    <select id="production-anomaly-filter" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                        <option value="">Semua Batch</option>
                        <option value="1">Ada alert terbuka</option>
                        <option value="0">Tanpa alert terbuka</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Urutkan</span>
                    <select id="production-sort-filter" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                        <option value="newest">Batch terbaru</option>
                        <option value="latest_anomaly">Alert terbaru</option>
                        <option value="largest_variance">Variance terbesar</option>
                        <option value="oldest">Batch terlama</option>
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Tanggal Dari</span>
                        <input id="production-date-from" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200" type="date" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Tanggal Sampai</span>
                        <input id="production-date-to" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200" type="date" />
                    </label>
                </div>
            </div>
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-7" id="production-batch-stats">
            <div class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Batch</p>
                <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100" data-stat="total">0</p>
            </div>
            <div class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Draft</p>
                <p class="mt-2 text-2xl font-bold text-amber-600" data-stat="draft">0</p>
            </div>
            <div class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Posted</p>
                <p class="mt-2 text-2xl font-bold text-emerald-600" data-stat="posted">0</p>
            </div>
            <div class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Variance</p>
                <p class="mt-2 text-2xl font-bold text-rose-600" data-stat="variance">0</p>
            </div>
            <div class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Open Alert</p>
                <p class="mt-2 text-2xl font-bold text-amber-600" data-stat="open_alert">0</p>
            </div>
            <div class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Cancelled</p>
                <p class="mt-2 text-2xl font-bold text-slate-700 dark:text-slate-100" data-stat="cancelled">0</p>
            </div>
            <div class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Variance Qty</p>
                <p class="mt-2 text-2xl font-bold text-rose-600" data-stat="variance_qty_total">0</p>
            </div>
        </section>

        <section class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Riwayat Batch</h3>
                    <p id="production-batch-meta" class="text-xs text-slate-500">Memuat batch produksi...</p>
                </div>
            </div>
            <div id="production-batch-list" class="space-y-3"></div>
        </section>
    </div>

    <button id="production-batch-create-open" class="fixed bottom-6 right-6 z-20 inline-flex items-center gap-2 rounded-full bg-primary px-5 py-3 text-sm font-bold text-white shadow-xl shadow-primary/30 lg:bottom-8 lg:right-8" type="button">
        <span class="material-symbols-outlined text-base">add_box</span>
        Draft Batch Baru
    </button>

    <div id="production-batch-create-modal" class="fixed inset-0 z-50 hidden">
        <div id="production-batch-create-overlay" class="absolute inset-0 bg-slate-950/40"></div>
        <div class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-2xl rounded-t-[28px] bg-white p-5 shadow-2xl dark:bg-slate-950 lg:top-1/2 lg:bottom-auto lg:-translate-y-1/2 lg:rounded-[28px]">
            <div class="mx-auto mb-4 h-1.5 w-14 rounded-full bg-slate-200 dark:bg-slate-700"></div>
            <div class="mb-4 flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Batch Produksi</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">Buat Draft Batch Baru</h3>
                </div>
                <button id="production-batch-create-close" class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300" type="button">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <form id="production-batch-create-form" class="space-y-3">
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Cabang</span>
                        <select id="production-create-branch" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900"></select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Lokasi</span>
                        <select id="production-create-location" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900"></select>
                    </label>
                </div>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Produk Jadi</span>
                    <select id="production-create-product" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900"></select>
                </label>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Tanggal Transaksi</span>
                        <input id="production-create-date" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" type="date" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Expected Output Qty</span>
                        <input id="production-create-output" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" min="0.0001" step="0.0001" type="number" value="1" />
                    </label>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Recipe Version</span>
                        <input id="production-create-recipe-version" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" min="1" step="1" type="number" value="1" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Reference</span>
                        <input id="production-create-reference" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" maxlength="100" placeholder="PB-20260408-001" type="text" />
                    </label>
                </div>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Catatan</span>
                    <textarea id="production-create-notes" class="min-h-[96px] w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" maxlength="2000" placeholder="Catatan operator atau target produksi"></textarea>
                </label>
                <div id="production-create-feedback" class="hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600"></div>
                <div class="flex justify-end gap-3 pt-2">
                    <button class="rounded-full border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300" type="button" id="production-create-cancel">Batal</button>
                    <button class="rounded-full bg-primary px-5 py-2.5 text-sm font-bold text-white" type="submit" id="production-create-submit">Simpan Draft</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content || '';
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
            const currentPath = '/backoffice/inventory/production-batches';
            const params = new URLSearchParams(window.location.search);

            const el = {
                product: document.getElementById('production-product-filter'),
                status: document.getElementById('production-status-filter'),
                cancelReason: document.getElementById('production-cancel-reason-filter'),
                branch: document.getElementById('production-branch-filter'),
                location: document.getElementById('production-location-filter'),
                anomaly: document.getElementById('production-anomaly-filter'),
                sort: document.getElementById('production-sort-filter'),
                dateFrom: document.getElementById('production-date-from'),
                dateTo: document.getElementById('production-date-to'),
                reset: document.getElementById('production-batch-reset'),
                list: document.getElementById('production-batch-list'),
                meta: document.getElementById('production-batch-meta'),
                stats: document.getElementById('production-batch-stats'),
                createOpen: document.getElementById('production-batch-create-open'),
                createModal: document.getElementById('production-batch-create-modal'),
                createOverlay: document.getElementById('production-batch-create-overlay'),
                createClose: document.getElementById('production-batch-create-close'),
                createCancel: document.getElementById('production-create-cancel'),
                createForm: document.getElementById('production-batch-create-form'),
                createBranch: document.getElementById('production-create-branch'),
                createLocation: document.getElementById('production-create-location'),
                createProduct: document.getElementById('production-create-product'),
                createDate: document.getElementById('production-create-date'),
                createOutput: document.getElementById('production-create-output'),
                createRecipeVersion: document.getElementById('production-create-recipe-version'),
                createReference: document.getElementById('production-create-reference'),
                createNotes: document.getElementById('production-create-notes'),
                createFeedback: document.getElementById('production-create-feedback'),
                createSubmit: document.getElementById('production-create-submit'),
                exportProblematic: document.getElementById('production-batch-export-problematic'),
            };

            const state = {
                bootstrap: { products: [], branches: [], locations: [] },
                batches: [],
                meta: {},
                filters: {
                    product_id: params.get('product_id') || '',
                    status: params.get('status') || '',
                    cancel_reason: params.get('cancel_reason') || '',
                    branch_id: params.get('branch_id') || '',
                    location_id: params.get('location_id') || '',
                    has_open_anomaly: params.get('has_open_anomaly') || '',
                    sort_by: params.get('sort_by') || 'newest',
                    transaction_date_from: params.get('transaction_date_from') || '',
                    transaction_date_to: params.get('transaction_date_to') || '',
                    limit: params.get('limit') || '100',
                },
            };
            const canViewObservability = Boolean(@json($productionBatchCanViewObservability));

            const unwrap = (payload) => payload?.data || payload || {};
            const formatNumber = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(Number(value || 0));
            const formatDate = (value) => {
                if (!value) return '-';
                return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
            };

            const productName = (id) => state.bootstrap.products.find((row) => row.id === id)?.name || 'Produk';
            const branchName = (id) => state.bootstrap.branches.find((row) => row.id === id)?.name || 'Cabang';
            const locationName = (id) => state.bootstrap.locations.find((row) => row.id === id)?.name || 'Lokasi';

            const statusBadge = (status) => {
                const map = {
                    draft: 'bg-amber-100 text-amber-700',
                    posted: 'bg-emerald-100 text-emerald-700',
                    cancelled: 'bg-slate-200 text-slate-600',
                };
                return map[status] || 'bg-slate-100 text-slate-500';
            };

            const setOptions = (select, items, labelKey, includeAllLabel) => {
                select.innerHTML = '';
                const allOption = document.createElement('option');
                allOption.value = '';
                allOption.textContent = includeAllLabel;
                select.appendChild(allOption);
                items.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item[labelKey] || '-';
                    select.appendChild(option);
                });
            };

            const setCreateFeedback = (message, isError = true) => {
                if (!message) {
                    el.createFeedback.classList.add('hidden');
                    el.createFeedback.textContent = '';
                    return;
                }

                el.createFeedback.className = `rounded-2xl px-4 py-3 text-sm ${isError ? 'border border-red-200 bg-red-50 text-red-600' : 'border border-emerald-200 bg-emerald-50 text-emerald-700'}`;
                el.createFeedback.textContent = message;
            };

            const toggleCreateModal = (open) => {
                el.createModal.classList.toggle('hidden', !open);
                if (!open) {
                    setCreateFeedback('');
                }
            };

            const batchProductOptions = () => {
                const products = state.bootstrap.products || [];
                const batchOnly = products.filter((product) => product.production_mode === 'batch_production');
                return batchOnly.length ? batchOnly : products;
            };

            const syncCreateLocations = () => {
                const branchId = el.createBranch.value || '';
                const locations = (state.bootstrap.locations || []).filter((location) => !branchId || location.branch_id === branchId);
                setOptions(el.createLocation, locations, 'name', 'Pilih Lokasi');

                if (locations.some((location) => location.id === el.createLocation.dataset.selected)) {
                    el.createLocation.value = el.createLocation.dataset.selected;
                }
            };

            const initCreateForm = () => {
                setOptions(el.createBranch, state.bootstrap.branches || [], 'name', 'Pilih Cabang');
                setOptions(el.createProduct, batchProductOptions(), 'name', 'Pilih Produk Batch');
                el.createDate.value = new Date().toISOString().slice(0, 10);
                el.createBranch.dataset.selected = state.filters.branch_id || '';
                el.createLocation.dataset.selected = state.filters.location_id || '';
                if (el.createBranch.dataset.selected) {
                    el.createBranch.value = el.createBranch.dataset.selected;
                }
                syncCreateLocations();
            };

            const syncFilterInputs = () => {
                el.product.value = state.filters.product_id;
                el.status.value = state.filters.status;
                el.cancelReason.value = state.filters.cancel_reason;
                el.branch.value = state.filters.branch_id;
                el.location.value = state.filters.location_id;
                el.anomaly.value = state.filters.has_open_anomaly;
                el.sort.value = state.filters.sort_by;
                el.dateFrom.value = state.filters.transaction_date_from;
                el.dateTo.value = state.filters.transaction_date_to;
            };

            const syncQuery = () => {
                const query = new URLSearchParams();
                Object.entries(state.filters).forEach(([key, value]) => {
                    if (value) {
                        query.set(key, value);
                    }
                });
                history.replaceState(null, '', `${window.location.pathname}${query.toString() ? `?${query.toString()}` : ''}`);

                const exportQuery = new URLSearchParams();
                ['product_id', 'status', 'cancel_reason', 'branch_id', 'location_id', 'has_open_anomaly', 'transaction_date_from', 'transaction_date_to'].forEach((key) => {
                    if (state.filters[key]) {
                        exportQuery.set(key, state.filters[key]);
                    }
                });
                el.exportProblematic.href = `/api/v1/production-batches/export/problematic${exportQuery.toString() ? `?${exportQuery.toString()}` : ''}`;
            };

            const renderStats = () => {
                const kpis = state.meta.kpis || {};

                el.stats.querySelector('[data-stat="total"]').textContent = Number(kpis.total_batches || state.batches.length || 0);
                el.stats.querySelector('[data-stat="draft"]').textContent = Number(kpis.draft_batches || 0);
                el.stats.querySelector('[data-stat="posted"]').textContent = Number(kpis.posted_batches || 0);
                el.stats.querySelector('[data-stat="variance"]').textContent = Number(kpis.variance_batch_count || 0);
                el.stats.querySelector('[data-stat="open_alert"]').textContent = Number(kpis.open_alert_batches || 0);
                el.stats.querySelector('[data-stat="cancelled"]').textContent = Number(kpis.cancelled_batches || 0);
                el.stats.querySelector('[data-stat="variance_qty_total"]').textContent = formatNumber(kpis.variance_qty_total || 0);
            };

            const renderList = () => {
                renderStats();

                const count = Number(state.meta.count || state.batches.length || 0);
                const sortLabel = ({
                    newest: 'batch terbaru',
                    oldest: 'batch terlama',
                    latest_anomaly: 'alert terbaru',
                    largest_variance: 'variance terbesar',
                }[state.meta.sort_by || state.filters.sort_by] || 'batch terbaru');
                el.meta.textContent = `${count} batch ditampilkan${state.meta.limit ? ` • limit ${state.meta.limit}` : ''} • urut ${sortLabel}`;
                el.list.innerHTML = '';

                if (!state.batches.length) {
                    el.list.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-400">Belum ada batch yang cocok dengan filter saat ini.</div>';
                    return;
                }

                state.batches.forEach((batch) => {
                    const anomalySummary = batch.anomaly_summary || {};
                    const openAlertCount = Number(anomalySummary.open_count || 0);
                    const hasOpenAlert = !!anomalySummary.has_open_alert;
                    const latestOpenDetectedAt = anomalySummary.latest_open_detected_at
                        ? `Terdeteksi ${formatDate(anomalySummary.latest_open_detected_at)}`
                        : 'Perlu review variance';
                    const detailUrl = `/backoffice/inventory/production-batches/${encodeURIComponent(batch.id)}?from=${encodeURIComponent(currentPath)}`;
                    const observabilityUrl = batch.observability_url || `/backoffice/settings/observability?type=production_batch_yield_variance&resolved=0&reference_id=${encodeURIComponent(batch.id)}`;
                    const card = document.createElement('article');
                    card.className = 'rounded-3xl border border-slate-200 p-4 transition hover:border-primary/40 hover:shadow-md dark:border-slate-800 dark:hover:border-primary/40';
                    card.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">${batch.reference_no || batch.id.slice(0, 8).toUpperCase()}</p>
                                <h4 class="mt-1 text-base font-bold text-slate-900 dark:text-slate-100">${productName(batch.product_id)}</h4>
                                <p class="mt-1 text-xs text-slate-500">${branchName(batch.branch_id)} • ${locationName(batch.location_id)} • ${formatDate(batch.transaction_date)}</p>
                            </div>
                            <div class="flex flex-wrap justify-end gap-2">
                                ${hasOpenAlert ? `<span class="rounded-full bg-amber-100 px-3 py-1 text-[11px] font-bold uppercase text-amber-700">${openAlertCount} open alert</span>` : ''}
                                <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase ${statusBadge(batch.status)}">${batch.status}</span>
                            </div>
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-4">
                            <div class="rounded-2xl bg-slate-50 px-3 py-2 dark:bg-slate-950">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Output Plan</p>
                                <p class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">${formatNumber(batch.expected_output_qty)}</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-3 py-2 dark:bg-slate-950">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Output Aktual</p>
                                <p class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">${formatNumber(batch.actual_output_qty)}</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-3 py-2 dark:bg-slate-950">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Input Line</p>
                                <p class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">${batch.input_line_count}</p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-3 py-2 dark:bg-slate-950">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Variance</p>
                                <p class="mt-1 text-sm font-bold ${Math.abs(Number(batch.yield_variance_qty || 0)) > 0 ? 'text-rose-600' : 'text-slate-900 dark:text-slate-100'}">${formatNumber(batch.yield_variance_qty)}</p>
                            </div>
                        </div>
                        ${hasOpenAlert ? `
                            <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-800">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-amber-700">Open Alert</p>
                                <p class="mt-1 font-semibold">Batch ini masih memiliki alert yield variance yang belum di-resolve.</p>
                                <p class="mt-1 text-xs text-amber-700">${latestOpenDetectedAt}</p>
                            </div>
                        ` : ''}
                        ${batch.status === 'cancelled' && batch.cancel_reason ? `
                            <div class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 px-3 py-3 text-sm text-rose-700">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-rose-600">Cancel Reason</p>
                                <p class="mt-1 font-semibold">${batch.cancel_reason}</p>
                            </div>
                        ` : ''}
                        <div class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                            ${canViewObservability ? `<a href="${observabilityUrl}" class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-bold text-amber-700"><span class="material-symbols-outlined text-sm">monitoring</span>Observability</a>` : ''}
                            <a href="${detailUrl}" class="inline-flex items-center gap-2 rounded-full bg-slate-900 px-4 py-2 text-xs font-bold text-white"><span class="material-symbols-outlined text-sm">open_in_new</span>Detail Batch</a>
                        </div>
                    `;
                    el.list.appendChild(card);
                });
            };

            const loadBootstrap = async () => {
                const response = await fetch('/api/v1/bootstrap', { headers });
                if (!response.ok) {
                    throw new Error('Gagal memuat master data produksi.');
                }
                state.bootstrap = unwrap(await response.json().catch(() => ({})));
                setOptions(el.product, state.bootstrap.products || [], 'name', 'Semua Produk');
                setOptions(el.branch, state.bootstrap.branches || [], 'name', 'Semua Cabang');
                setOptions(el.location, state.bootstrap.locations || [], 'name', 'Semua Lokasi');
                syncFilterInputs();
                initCreateForm();
            };

            const loadBatches = async () => {
                const query = new URLSearchParams();
                Object.entries(state.filters).forEach(([key, value]) => {
                    if (value) query.set(key, value);
                });

                const response = await fetch(`/api/v1/production-batches?${query.toString()}`, { headers });
                if (!response.ok) {
                    throw new Error('Gagal memuat batch produksi.');
                }

                const payload = await response.json().catch(() => ({}));
                const data = unwrap(payload);
                state.batches = data.batches || [];
                state.meta = payload.meta || {};
                syncQuery();
                renderList();
            };

            const handleFilterChange = () => {
                state.filters.product_id = el.product.value || '';
                state.filters.status = el.status.value || '';
                state.filters.cancel_reason = (el.cancelReason.value || '').trim();
                state.filters.branch_id = el.branch.value || '';
                state.filters.location_id = el.location.value || '';
                state.filters.has_open_anomaly = el.anomaly.value || '';
                state.filters.sort_by = el.sort.value || 'newest';
                state.filters.transaction_date_from = el.dateFrom.value || '';
                state.filters.transaction_date_to = el.dateTo.value || '';
                loadBatches().catch((error) => {
                    el.list.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                });
            };

            [el.product, el.status, el.cancelReason, el.branch, el.location, el.anomaly, el.sort, el.dateFrom, el.dateTo].forEach((node) => {
                node?.addEventListener('change', handleFilterChange);
            });

            el.cancelReason?.addEventListener('input', handleFilterChange);

            el.reset?.addEventListener('click', () => {
                state.filters = {
                    product_id: '',
                    status: '',
                    cancel_reason: '',
                    branch_id: '',
                    location_id: '',
                    has_open_anomaly: '',
                    sort_by: 'newest',
                    transaction_date_from: '',
                    transaction_date_to: '',
                    limit: '100',
                };
                syncFilterInputs();
                loadBatches().catch((error) => {
                    el.list.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                });
            });

            el.createBranch?.addEventListener('change', () => {
                el.createLocation.dataset.selected = '';
                syncCreateLocations();
            });

            [el.createOpen, el.createClose, el.createCancel, el.createOverlay].forEach((node) => {
                node?.addEventListener('click', () => toggleCreateModal(node === el.createOpen));
            });

            el.createForm?.addEventListener('submit', async (event) => {
                event.preventDefault();
                setCreateFeedback('');
                el.createSubmit.disabled = true;
                el.createSubmit.textContent = 'Menyimpan...';

                const payload = {
                    branch_id: el.createBranch.value || null,
                    location_id: el.createLocation.value || null,
                    product_id: el.createProduct.value || null,
                    transaction_date: el.createDate.value || null,
                    expected_output_qty: Number(el.createOutput.value || 0),
                    recipe_version: Number(el.createRecipeVersion.value || 0),
                    reference_no: (el.createReference.value || '').trim() || null,
                    notes: (el.createNotes.value || '').trim() || null,
                };

                try {
                    const response = await fetch('/api/v1/production-batches', {
                        method: 'POST',
                        headers: {
                            ...headers,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });

                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const details = result.details ? Object.values(result.details).flat().join(' ') : '';
                        throw new Error(details || result.message || 'Gagal membuat draft batch.');
                    }

                    const batchId = result.batch_id;
                    window.location.href = `/backoffice/inventory/production-batches/${encodeURIComponent(batchId)}?from=${encodeURIComponent(currentPath)}`;
                } catch (error) {
                    setCreateFeedback(error.message || 'Gagal membuat draft batch.');
                } finally {
                    el.createSubmit.disabled = false;
                    el.createSubmit.textContent = 'Simpan Draft';
                }
            });

            Promise.all([loadBootstrap(), loadBatches()]).catch((error) => {
                el.meta.textContent = error.message;
                el.list.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
            });
        })();
    </script>
@endsection