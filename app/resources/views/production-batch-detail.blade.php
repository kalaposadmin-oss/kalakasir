@extends('layouts.app')

@php
    $productionBatchDetailTenant = \App\Support\TenantContext::get();
    $productionBatchDetailUser = auth()->user();
    $productionBatchDetailRole = null;

    if (! $productionBatchDetailTenant && filled($productionBatchDetailUser?->tenant_id)) {
        $productionBatchDetailTenant = \App\Models\Tenant::query()->find($productionBatchDetailUser->tenant_id);
    }

    if ($productionBatchDetailTenant && $productionBatchDetailUser) {
        $productionBatchDetailRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $productionBatchDetailTenant->id, (string) $productionBatchDetailUser->id);

        if (! $productionBatchDetailRole && filled($productionBatchDetailUser->role ?? null)) {
            $productionBatchDetailRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $productionBatchDetailUser->role);
        }
    }

    abort_unless(in_array($productionBatchDetailRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $resolvedBatchId = (string) ($batchId ?? request()->route('batch', request()->query('batch_id', '')));
    $productionBatchPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $productionBatchCurrentPath = $currentPath ?? $productionBatchPortalPrefix.'/inventory/production-batches/'.$resolvedBatchId;
    $productionBatchDetailBackUrl = $backUrl ?? $productionBatchPortalPrefix.'/inventory/production-batches';
    $productionBatchDetailCanViewObservability = $productionBatchDetailTenant
        && in_array($productionBatchDetailRole, ['admin', 'owner', 'superadmin'], true)
        && app(\App\Services\SubscriptionService::class)->canAccessFeature($productionBatchDetailTenant, 'settings.observability');
    $productionBatchDetailObservabilityBaseUrl = $productionBatchDetailCanViewObservability ? '/backoffice/settings/observability' : null;
    $productionBatchDetailCanResolveAnomaly = in_array($productionBatchDetailRole, ['admin', 'owner'], true);
    $productionBatchDetailCanCorrectPosted = in_array($productionBatchDetailRole, ['admin', 'owner', 'superadmin'], true);
    $productionBatchDetailCanRecordWaste = in_array($productionBatchDetailRole, ['admin', 'owner'], true);
    $productionBatchVariancePctThreshold = max(0, (float) config('services.anomaly.production_batch_yield_variance_pct_threshold', 10));
    $productionBatchVarianceQtyThreshold = max(0, (float) config('services.anomaly.production_batch_yield_variance_qty_threshold', 1));
    $productionBatchDetailUiMode = $productionBatchPortalPrefix === '/pos' ? 'pos' : 'backoffice';
    $productionBatchDetailMainClass = $productionBatchPortalPrefix === '/pos' ? 'flex-1 overflow-y-auto pb-24' : 'flex-1 overflow-y-auto pb-8 lg:pl-72';
    $productionBatchDetailHeaderClass = $productionBatchPortalPrefix === '/pos'
        ? 'sticky top-0 z-10 h-16 border-b border-slate-200 bg-white/95 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95 lg:px-8'
        : 'sticky top-0 z-10 h-16 border-b border-slate-200 bg-white/95 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95 lg:px-8 lg:pl-[336px]';
@endphp

@section('page_title', 'Detail Batch Produksi')
@section('ui_mode', $productionBatchDetailUiMode)
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', $productionBatchDetailMainClass)

@push('head')
    <style>
        .batch-detail-tab {
            border: 1px solid rgba(226, 232, 240, 0.95);
            background: #ffffff;
            color: #64748b;
            transition: all 160ms ease;
        }
        .batch-detail-tab.is-active {
            border-color: rgba(15, 118, 110, 0.2);
            background: linear-gradient(135deg, rgba(209, 250, 229, 0.95), rgba(236, 253, 245, 0.95));
            color: #0f766e;
            box-shadow: 0 10px 22px -18px rgba(15, 118, 110, 0.45);
        }
        .batch-detail-mobile-tabs-shell {
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.96), rgba(248, 250, 252, 0.82));
            backdrop-filter: blur(8px);
        }
    </style>
@endpush

@section('header')
    <header class="{{ $productionBatchDetailHeaderClass }}">
        <div class="flex h-full items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ $productionBatchDetailBackUrl }}" class="flex items-center justify-center size-9 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    <span class="material-symbols-outlined text-[20px]">arrow_back_ios_new</span>
                </a>
                <div>
                    <h2 class="text-base font-bold lg:text-lg">Detail Batch Produksi</h2>
                    <p id="batch-detail-subtitle" class="hidden text-xs text-slate-500 lg:block">Memuat data batch...</p>
                </div>
            </div>
            <a href="{{ $productionBatchPortalPrefix.'/inventory/production-batches' }}" class="flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <span class="material-symbols-outlined text-sm">list_alt</span>
                Semua Batch
            </a>
        </div>
    </header>
@endsection

@section('content')
    <div class="mx-auto w-full max-w-5xl px-4 py-4 space-y-4 lg:px-8">
        <div id="batch-detail-mobile-tabs" class="batch-detail-mobile-tabs-shell sticky top-[4.5rem] z-[5] -mx-1 flex gap-2 overflow-x-auto rounded-2xl px-1 pb-1 pt-1 no-scrollbar lg:hidden">
            <button class="batch-detail-tab is-active inline-flex shrink-0 items-center justify-center rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.16em]" data-batch-tab="overview" type="button">Ringkasan</button>
            <button class="batch-detail-tab inline-flex shrink-0 items-center justify-center rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.16em]" data-batch-tab="materials" type="button">Bahan</button>
            <button class="batch-detail-tab inline-flex shrink-0 items-center justify-center rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.16em]" data-batch-tab="notes" type="button">Catatan</button>
            <button class="batch-detail-tab inline-flex shrink-0 items-center justify-center rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.16em]" data-batch-tab="alerts" type="button">Alert</button>
        </div>

        <section data-batch-panel="overview" class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p id="batch-detail-reference" class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Batch</p>
                    <h3 id="batch-detail-product" class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">-</h3>
                    <p id="batch-detail-location" class="mt-2 text-sm text-slate-500">-</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span id="batch-detail-status" class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase text-slate-600">-</span>
                    <span id="batch-detail-date" class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase text-slate-600">-</span>
                </div>
            </div>
            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-950">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Output Harapan</p>
                    <p id="batch-detail-expected-output" class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">0</p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-950">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Hasil Aktual</p>
                    <p id="batch-detail-actual-output" class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">0</p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-950">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Yield Variance</p>
                    <p id="batch-detail-variance" class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">0</p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-950">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Jumlah Resep</p>
                    <p id="batch-detail-recipe-version" class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">0</p>
                </div>
            </div>

            <div id="batch-detail-actions" class="mt-5 hidden flex-wrap gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                <button id="batch-detail-edit-open" class="rounded-full border border-primary/20 bg-primary/10 px-4 py-2 text-sm font-bold text-primary" type="button">Edit Draft</button>
                <button id="batch-detail-post-open" class="rounded-full bg-emerald-500 px-4 py-2 text-sm font-bold text-white" type="button">Post Batch</button>
                <button id="batch-detail-cancel-open" class="rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-bold text-rose-600" type="button">Batalkan Draft</button>
            </div>
            @if ($productionBatchDetailCanCorrectPosted || $productionBatchDetailCanRecordWaste)
                <div id="batch-detail-posted-actions" class="mt-5 hidden flex-wrap gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                    @if ($productionBatchDetailCanRecordWaste)
                        <button id="batch-detail-waste-open" class="rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-bold text-amber-700" type="button">Catat Waste</button>
                    @endif
                    @if ($productionBatchDetailCanCorrectPosted)
                        <button id="batch-detail-correct-date-open" class="rounded-full border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-bold text-sky-700" type="button">Koreksi Tanggal</button>
                        <button id="batch-detail-void-posting-open" class="rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-bold text-rose-600" type="button">Void Posting</button>
                    @endif
                </div>
            @endif
        </section>

        <section data-batch-panel="alerts" id="batch-detail-anomaly-banner" class="rounded-3xl border border-emerald-200 bg-emerald-50 px-5 py-5 text-emerald-800 shadow-sm ring-1 ring-emerald-100/80">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-700/80">Monitoring Alert</p>
                    <h4 id="batch-detail-anomaly-status" class="mt-1 text-sm font-bold">Tidak ada alert batch yang terbuka.</h4>
                    <p id="batch-detail-anomaly-meta" class="mt-1 text-sm text-emerald-700/90">Belum ada anomaly yield variance yang aktif untuk batch ini.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span id="batch-detail-anomaly-count" class="rounded-full bg-white/80 px-3 py-1 text-[11px] font-bold uppercase text-emerald-700">0 open alert</span>
                    @if ($productionBatchDetailCanViewObservability)
                        <a id="batch-detail-observability-link" href="{{ $productionBatchDetailObservabilityBaseUrl }}" class="inline-flex items-center gap-2 rounded-full border border-emerald-300 bg-white px-4 py-2 text-xs font-bold text-emerald-700">
                            <span class="material-symbols-outlined text-sm">monitoring</span>
                            Buka Observability
                        </a>
                    @endif
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
            <section data-batch-panel="materials" class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800 xl:row-span-2">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Input Bahan Baku</h3>
                    <span id="batch-detail-input-count" class="text-xs font-bold uppercase tracking-widest text-slate-400">0 line</span>
                </div>
                <div id="batch-detail-inputs" class="space-y-3"></div>
            </section>

            <div class="space-y-4">
                <section data-batch-panel="materials" class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Output Jadi</h3>
                    </div>
                    <div id="batch-detail-outputs" class="space-y-3"></div>
                </section>

                <section data-batch-panel="notes" class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Catatan Batch</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500">Reference</dt>
                            <dd id="batch-detail-reference-full" class="text-right font-semibold text-slate-900 dark:text-slate-100">-</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500">Operator Note</dt>
                            <dd id="batch-detail-notes" class="text-right font-semibold text-slate-900 dark:text-slate-100">-</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500">Variance Reason</dt>
                            <dd id="batch-detail-variance-reason" class="text-right font-semibold text-slate-900 dark:text-slate-100">-</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500">Cancel Reason</dt>
                            <dd id="batch-detail-cancel-reason" class="text-right font-semibold text-slate-900 dark:text-slate-100">-</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-slate-500">Posted At</dt>
                            <dd id="batch-detail-posted-at" class="text-right font-semibold text-slate-900 dark:text-slate-100">-</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </section>

        <section data-batch-panel="alerts" id="batch-detail-anomaly-history-section" class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Riwayat Alert Batch</h3>
                    <p class="mt-1 text-xs text-slate-500">Pantau alert yield variance yang masih open maupun yang sudah resolved untuk batch ini.</p>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <label class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2 text-[11px] font-bold uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                        <span>Status</span>
                        <select id="batch-detail-anomaly-status-filter" class="bg-transparent text-[11px] font-bold uppercase text-slate-600 outline-none dark:text-slate-200">
                            <option value="all">Semua</option>
                            <option value="open">Open</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </label>
                    @if ($productionBatchDetailCanResolveAnomaly)
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase text-slate-500">Admin or owner can resolve</span>
                    @endif
                </div>
            </div>
            <div id="batch-detail-anomaly-history-meta" class="mb-3 text-xs text-slate-500">Memuat riwayat alert batch...</div>
            <div id="batch-detail-anomaly-history" class="space-y-3"></div>
            <div class="mt-4 flex justify-center">
                <button id="batch-detail-anomaly-load-more" class="hidden rounded-full border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300" type="button">Muat lebih banyak</button>
            </div>
        </section>
    </div>

    <div id="batch-detail-edit-modal" class="fixed inset-0 z-50 hidden">
        <div data-close="edit" class="absolute inset-0 bg-slate-950/40"></div>
        <div class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-2xl rounded-t-[28px] bg-white p-5 shadow-2xl dark:bg-slate-950 lg:top-1/2 lg:bottom-auto lg:-translate-y-1/2 lg:rounded-[28px]">
            <div class="mx-auto mb-4 h-1.5 w-14 rounded-full bg-slate-200 dark:bg-slate-700"></div>
            <div class="mb-4 flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Draft Batch</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">Edit Draft Batch</h3>
                </div>
                <button data-close="edit" class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300" type="button">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <form id="batch-detail-edit-form" class="space-y-3">
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Tanggal Transaksi</span>
                        <input id="batch-edit-date" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" type="date" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Output Harapan</span>
                        <input id="batch-edit-output" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" min="0.0001" step="0.0001" type="number" readonly />
                    </label>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Jumlah Resep</span>
                        <input id="batch-edit-recipe-version" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" min="1" step="1" type="number" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Reference</span>
                        <input id="batch-edit-reference" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" maxlength="100" type="text" />
                    </label>
                </div>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Catatan</span>
                    <textarea id="batch-edit-notes" class="min-h-[96px] w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" maxlength="2000"></textarea>
                </label>
                <div id="batch-edit-feedback" class="hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600"></div>
                <div class="flex justify-end gap-3 pt-2">
                    <button data-close="edit" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300" type="button">Batal</button>
                    <button id="batch-edit-submit" class="rounded-full bg-primary px-5 py-2.5 text-sm font-bold text-white" type="submit">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <div id="batch-detail-post-modal" class="fixed inset-0 z-50 hidden">
        <div data-close="post" class="absolute inset-0 bg-slate-950/40"></div>
        <div class="absolute inset-x-0 bottom-0 top-4 mx-auto w-full max-w-2xl overflow-y-auto overscroll-contain rounded-t-[28px] bg-white shadow-2xl dark:bg-slate-950 lg:inset-y-8 lg:rounded-[28px]">
            <div class="px-5 pt-5">
                <div class="mx-auto mb-4 h-1.5 w-14 rounded-full bg-slate-200 dark:bg-slate-700"></div>
            </div>
            <div class="mb-4 flex items-start justify-between gap-4 px-5">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Posting Batch</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">Konfirmasi Actual Yield</h3>
                </div>
                <button data-close="post" class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300" type="button">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <form id="batch-post-form" class="px-5 pb-28 space-y-3">
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Actual Output Qty</span>
                        <input id="batch-post-output" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" min="0.0001" step="0.0001" type="number" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Variance Reason</span>
                        <input id="batch-post-variance-reason" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" maxlength="2000" placeholder="Isi bila hasil aktual berbeda dari rencana" type="text" />
                    </label>
                </div>
                <div class="flex flex-wrap gap-2" id="batch-post-variance-reason-presets">
                    <button class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600" data-variance-reason="Susut produksi" type="button">Susut produksi</button>
                    <button class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600" data-variance-reason="Produk rusak" type="button">Produk rusak</button>
                    <button class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600" data-variance-reason="Over yield" type="button">Over yield</button>
                    <button class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-600" data-variance-reason="Salah input - koreksi draft" type="button">Salah input</button>
                </div>
                <div>
                    <p class="mb-2 text-[11px] font-bold uppercase tracking-widest text-slate-400">Actual Output Jadi</p>
                    <div id="batch-post-outputs" class="space-y-3"></div>
                </div>
                <div id="batch-post-variance-hint" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                    Variance reason wajib diisi bila selisih actual yield menyentuh threshold monitoring: qty {{ $productionBatchVarianceQtyThreshold }} atau {{ $productionBatchVariancePctThreshold }}%.
                </div>
                <div>
                    <p class="mb-2 text-[11px] font-bold uppercase tracking-widest text-slate-400">Actual Input</p>
                    <div id="batch-post-inputs" class="space-y-3"></div>
                </div>
                <div id="batch-post-feedback" class="hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600"></div>
                <div class="sticky bottom-0 -mx-5 flex justify-end gap-3 border-t border-slate-200 bg-white px-5 py-4 shadow-[0_-12px_24px_-20px_rgba(15,23,42,0.35)] dark:border-slate-800 dark:bg-slate-950">
                    <button data-close="post" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300" type="button">Batal</button>
                    <button id="batch-post-submit" class="rounded-full bg-emerald-500 px-5 py-2.5 text-sm font-bold text-white" type="submit">Post Batch</button>
                </div>
            </form>
        </div>
    </div>

    <div id="batch-detail-cancel-modal" class="fixed inset-0 z-50 hidden">
        <div data-close="cancel" class="absolute inset-0 bg-slate-950/40"></div>
        <div class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-lg rounded-t-[28px] bg-white p-5 shadow-2xl dark:bg-slate-950 lg:top-1/2 lg:bottom-auto lg:-translate-y-1/2 lg:rounded-[28px]">
            <div class="mx-auto mb-4 h-1.5 w-14 rounded-full bg-slate-200 dark:bg-slate-700"></div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Batalkan Draft</p>
            <h3 class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">Draft batch akan dibatalkan</h3>
            <p class="mt-2 text-sm text-slate-500">Aksi ini akan mengubah status draft menjadi cancelled dan draft tidak bisa diedit lagi.</p>
            <label class="mt-4 block">
                <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Cancel Reason</span>
                <textarea id="batch-cancel-reason" class="min-h-[96px] w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" maxlength="2000" placeholder="Tulis alasan pembatalan draft batch..."></textarea>
            </label>
            <div id="batch-cancel-feedback" class="mt-4 hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600"></div>
            <div class="mt-5 flex justify-end gap-3">
                <button data-close="cancel" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300" type="button">Kembali</button>
                <button id="batch-cancel-submit" class="rounded-full bg-rose-500 px-5 py-2.5 text-sm font-bold text-white" type="button">Ya, batalkan</button>
            </div>
        </div>
    </div>

    <div id="batch-detail-correct-date-modal" class="fixed inset-0 z-50 hidden">
        <div data-close="correct-date" class="absolute inset-0 bg-slate-950/40"></div>
        <div class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-lg rounded-t-[28px] bg-white p-5 shadow-2xl dark:bg-slate-950 lg:top-1/2 lg:bottom-auto lg:-translate-y-1/2 lg:rounded-[28px]">
            <div class="mx-auto mb-4 h-1.5 w-14 rounded-full bg-slate-200 dark:bg-slate-700"></div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Koreksi Tanggal</p>
            <h3 class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">Ubah tanggal transaksi batch</h3>
            <p class="mt-2 text-sm text-slate-500">Aksi ini hanya mengubah tanggal batch posted, tidak mengubah stok, HPP, atau qty produksi.</p>
            <label class="mt-4 block">
                <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Tanggal Baru</span>
                <input id="batch-correct-date-value" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" type="date" />
            </label>
            <label class="mt-3 block">
                <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Alasan</span>
                <textarea id="batch-correct-date-reason" class="min-h-[96px] w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" maxlength="2000" placeholder="Contoh: tanggal produksi salah input saat posting."></textarea>
            </label>
            <div id="batch-correct-date-feedback" class="mt-4 hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600"></div>
            <div class="mt-5 flex justify-end gap-3">
                <button data-close="correct-date" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300" type="button">Batal</button>
                <button id="batch-correct-date-submit" class="rounded-full bg-sky-600 px-5 py-2.5 text-sm font-bold text-white" type="button">Simpan Koreksi</button>
            </div>
        </div>
    </div>

    <div id="batch-detail-void-posting-modal" class="fixed inset-0 z-50 hidden">
        <div data-close="void-posting" class="absolute inset-0 bg-slate-950/40"></div>
        <div class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-lg rounded-t-[28px] bg-white p-5 shadow-2xl dark:bg-slate-950 lg:top-1/2 lg:bottom-auto lg:-translate-y-1/2 lg:rounded-[28px]">
            <div class="mx-auto mb-4 h-1.5 w-14 rounded-full bg-slate-200 dark:bg-slate-700"></div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-rose-500">Void Posting</p>
            <h3 class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">Balikkan stok dari batch posted</h3>
            <p class="mt-2 text-sm text-slate-500">Aksi ini membuat ledger reversal, mengembalikan bahan baku, dan mengeluarkan produk jadi. Void ditolak bila stok produk jadi sudah tidak cukup.</p>
            <label class="mt-4 block">
                <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Alasan Void</span>
                <textarea id="batch-void-posting-reason" class="min-h-[96px] w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" maxlength="2000" placeholder="Contoh: jumlah resep salah input dan perlu dibuat ulang."></textarea>
            </label>
            <div id="batch-void-posting-feedback" class="mt-4 hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600"></div>
            <div class="mt-5 flex justify-end gap-3">
                <button data-close="void-posting" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300" type="button">Batal</button>
                <button id="batch-void-posting-submit" class="rounded-full bg-rose-600 px-5 py-2.5 text-sm font-bold text-white" type="button">Void Posting</button>
            </div>
        </div>
    </div>

    <div id="batch-detail-waste-modal" class="fixed inset-0 z-50 hidden">
        <div data-close="waste" class="absolute inset-0 bg-slate-950/40"></div>
        <div class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-lg rounded-t-[28px] bg-white p-5 shadow-2xl dark:bg-slate-950 lg:top-1/2 lg:bottom-auto lg:-translate-y-1/2 lg:rounded-[28px]">
            <div class="mx-auto mb-4 h-1.5 w-14 rounded-full bg-slate-200 dark:bg-slate-700"></div>
            <p class="text-[11px] font-bold uppercase tracking-widest text-amber-500">Waste Produksi</p>
            <h3 class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">Catat produk rusak atau tidak laku</h3>
            <p class="mt-2 text-sm text-slate-500">Aksi ini mem-post penyesuaian stok keluar dan menghubungkannya ke batch produksi ini.</p>
            <label class="mt-4 block">
                <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Produk Jadi</span>
                <select id="batch-waste-product" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900"></select>
            </label>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <label class="block">
                    <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Qty Waste</span>
                    <input id="batch-waste-qty" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" min="0.0001" step="0.0001" type="number" />
                </label>
                <label class="block">
                    <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Tipe</span>
                    <select id="batch-waste-type" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900">
                        <option value="damaged">Rusak</option>
                        <option value="expired">Expired</option>
                        <option value="unsold">Tidak laku</option>
                        <option value="sample">Tester / sample</option>
                        <option value="other">Lainnya</option>
                    </select>
                </label>
            </div>
            <label class="mt-3 block">
                <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Catatan</span>
                <textarea id="batch-waste-notes" class="min-h-[96px] w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" maxlength="500" placeholder="Contoh: 2 pcs cookies rusak kemasan dan dibuang."></textarea>
            </label>
            <div id="batch-waste-feedback" class="mt-4 hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600"></div>
            <div class="mt-5 flex justify-end gap-3">
                <button data-close="waste" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300" type="button">Batal</button>
                <button id="batch-waste-submit" class="rounded-full bg-amber-500 px-5 py-2.5 text-sm font-bold text-white" type="button">Catat Waste</button>
            </div>
        </div>
    </div>

    <div id="batch-detail-resolve-modal" class="fixed inset-0 z-50 hidden">
        <div data-close="resolve" class="absolute inset-0 bg-slate-950/40"></div>
        <div class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-lg rounded-t-[28px] bg-white p-5 shadow-2xl dark:bg-slate-950 lg:top-1/2 lg:bottom-auto lg:-translate-y-1/2 lg:rounded-[28px]">
            <div class="mx-auto mb-4 h-1.5 w-14 rounded-full bg-slate-200 dark:bg-slate-700"></div>
            <div class="mb-4 flex items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Resolve Alert</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">Tutup alert yield variance</h3>
                    <p id="batch-resolve-meta" class="mt-2 text-sm text-slate-500">Alert yang dipilih akan ditandai selesai dan masuk audit trail.</p>
                </div>
                <button data-close="resolve" class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300" type="button">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <label class="block">
                <span class="mb-1 block text-[11px] font-bold uppercase tracking-widest text-slate-400">Catatan Resolve</span>
                <textarea id="batch-resolve-note" class="min-h-[96px] w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" maxlength="255" placeholder="Contoh: sudah diverifikasi supervisor produksi"></textarea>
            </label>
            <div id="batch-resolve-feedback" class="mt-4 hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600"></div>
            <div class="mt-5 flex justify-end gap-3">
                <button data-close="resolve" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300" type="button">Batal</button>
                <button id="batch-resolve-submit" class="rounded-full bg-slate-900 px-5 py-2.5 text-sm font-bold text-white" type="button">Resolve Alert</button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content || '';
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
            const batchId = @json($resolvedBatchId);
            const canViewObservability = Boolean(@json($productionBatchDetailCanViewObservability));
            const observabilityBaseUrl = @json($productionBatchDetailObservabilityBaseUrl);
            const canResolveAnomaly = Boolean(@json($productionBatchDetailCanResolveAnomaly));
            const canCorrectPosted = Boolean(@json($productionBatchDetailCanCorrectPosted));
            const canRecordWaste = Boolean(@json($productionBatchDetailCanRecordWaste));
            const varianceThresholds = {
                qty: Number(@json($productionBatchVarianceQtyThreshold)),
                pct: Number(@json($productionBatchVariancePctThreshold)),
            };

            const el = {
                tabNav: document.getElementById('batch-detail-mobile-tabs'),
                subtitle: document.getElementById('batch-detail-subtitle'),
                reference: document.getElementById('batch-detail-reference'),
                product: document.getElementById('batch-detail-product'),
                location: document.getElementById('batch-detail-location'),
                status: document.getElementById('batch-detail-status'),
                date: document.getElementById('batch-detail-date'),
                expectedOutput: document.getElementById('batch-detail-expected-output'),
                actualOutput: document.getElementById('batch-detail-actual-output'),
                variance: document.getElementById('batch-detail-variance'),
                recipeVersion: document.getElementById('batch-detail-recipe-version'),
                anomalyBanner: document.getElementById('batch-detail-anomaly-banner'),
                anomalyStatus: document.getElementById('batch-detail-anomaly-status'),
                anomalyMeta: document.getElementById('batch-detail-anomaly-meta'),
                anomalyCount: document.getElementById('batch-detail-anomaly-count'),
                observabilityLink: document.getElementById('batch-detail-observability-link'),
                anomalyHistory: document.getElementById('batch-detail-anomaly-history'),
                anomalyHistoryMeta: document.getElementById('batch-detail-anomaly-history-meta'),
                anomalyStatusFilter: document.getElementById('batch-detail-anomaly-status-filter'),
                anomalyLoadMore: document.getElementById('batch-detail-anomaly-load-more'),
                inputCount: document.getElementById('batch-detail-input-count'),
                inputs: document.getElementById('batch-detail-inputs'),
                outputs: document.getElementById('batch-detail-outputs'),
                referenceFull: document.getElementById('batch-detail-reference-full'),
                notes: document.getElementById('batch-detail-notes'),
                varianceReason: document.getElementById('batch-detail-variance-reason'),
                cancelReason: document.getElementById('batch-detail-cancel-reason'),
                postedAt: document.getElementById('batch-detail-posted-at'),
                actions: document.getElementById('batch-detail-actions'),
                postedActions: document.getElementById('batch-detail-posted-actions'),
                editOpen: document.getElementById('batch-detail-edit-open'),
                postOpen: document.getElementById('batch-detail-post-open'),
                cancelOpen: document.getElementById('batch-detail-cancel-open'),
                correctDateOpen: document.getElementById('batch-detail-correct-date-open'),
                voidPostingOpen: document.getElementById('batch-detail-void-posting-open'),
                wasteOpen: document.getElementById('batch-detail-waste-open'),
                editModal: document.getElementById('batch-detail-edit-modal'),
                postModal: document.getElementById('batch-detail-post-modal'),
                cancelModal: document.getElementById('batch-detail-cancel-modal'),
                correctDateModal: document.getElementById('batch-detail-correct-date-modal'),
                voidPostingModal: document.getElementById('batch-detail-void-posting-modal'),
                wasteModal: document.getElementById('batch-detail-waste-modal'),
                editForm: document.getElementById('batch-detail-edit-form'),
                editDate: document.getElementById('batch-edit-date'),
                editOutput: document.getElementById('batch-edit-output'),
                editRecipeVersion: document.getElementById('batch-edit-recipe-version'),
                editReference: document.getElementById('batch-edit-reference'),
                editNotes: document.getElementById('batch-edit-notes'),
                editFeedback: document.getElementById('batch-edit-feedback'),
                editSubmit: document.getElementById('batch-edit-submit'),
                postForm: document.getElementById('batch-post-form'),
                postOutput: document.getElementById('batch-post-output'),
                postOutputs: document.getElementById('batch-post-outputs'),
                postVarianceReason: document.getElementById('batch-post-variance-reason'),
                postVarianceReasonPresets: document.getElementById('batch-post-variance-reason-presets'),
                postVarianceHint: document.getElementById('batch-post-variance-hint'),
                postInputs: document.getElementById('batch-post-inputs'),
                postFeedback: document.getElementById('batch-post-feedback'),
                postSubmit: document.getElementById('batch-post-submit'),
                cancelReasonInput: document.getElementById('batch-cancel-reason'),
                cancelFeedback: document.getElementById('batch-cancel-feedback'),
                cancelSubmit: document.getElementById('batch-cancel-submit'),
                resolveModal: document.getElementById('batch-detail-resolve-modal'),
                resolveMeta: document.getElementById('batch-resolve-meta'),
                resolveNote: document.getElementById('batch-resolve-note'),
                resolveFeedback: document.getElementById('batch-resolve-feedback'),
                resolveSubmit: document.getElementById('batch-resolve-submit'),
                correctDateValue: document.getElementById('batch-correct-date-value'),
                correctDateReason: document.getElementById('batch-correct-date-reason'),
                correctDateFeedback: document.getElementById('batch-correct-date-feedback'),
                correctDateSubmit: document.getElementById('batch-correct-date-submit'),
                voidPostingReason: document.getElementById('batch-void-posting-reason'),
                voidPostingFeedback: document.getElementById('batch-void-posting-feedback'),
                voidPostingSubmit: document.getElementById('batch-void-posting-submit'),
                wasteProduct: document.getElementById('batch-waste-product'),
                wasteQty: document.getElementById('batch-waste-qty'),
                wasteType: document.getElementById('batch-waste-type'),
                wasteNotes: document.getElementById('batch-waste-notes'),
                wasteFeedback: document.getElementById('batch-waste-feedback'),
                wasteSubmit: document.getElementById('batch-waste-submit'),
            };

            const state = {
                bootstrap: { products: [], branches: [], locations: [] },
                detail: null,
                ui: {
                    activeTab: 'overview',
                },
                historyFilters: {
                    status: 'all',
                    limit: 20,
                },
                selectedAnomalyId: null,
            };
            const tabButtons = Array.from(document.querySelectorAll('[data-batch-tab]'));
            const tabPanels = Array.from(document.querySelectorAll('[data-batch-panel]'));

            const unwrap = (payload) => payload?.data || payload || {};
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
            const formatNumber = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(Number(value || 0));
            const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Number(value || 0));
            const normalizeQtyValue = (value) => {
                const numericValue = Number(value || 0);
                if (!Number.isFinite(numericValue)) {
                    return 0;
                }

                const nearestWhole = Math.round(numericValue);
                if (Math.abs(numericValue - nearestWhole) <= 0.0005) {
                    return nearestWhole;
                }

                return Number(numericValue.toFixed(4));
            };
            const formatDate = (value, includeTime = false) => {
                if (!value) return '-';
                return new Intl.DateTimeFormat('id-ID', includeTime
                    ? { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }
                    : { day: '2-digit', month: 'short', year: 'numeric' }
                ).format(new Date(value));
            };
            const productName = (id) => state.bootstrap.products.find((row) => row.id === id)?.name || 'Produk';
            const branchName = (id) => state.bootstrap.branches.find((row) => row.id === id)?.name || 'Cabang';
            const locationName = (id) => state.bootstrap.locations.find((row) => row.id === id)?.name || 'Lokasi';
            const badgeClass = (status) => ({
                draft: 'bg-amber-100 text-amber-700',
                posted: 'bg-emerald-100 text-emerald-700',
                cancelled: 'bg-slate-200 text-slate-600',
            }[status] || 'bg-slate-100 text-slate-500');
            const generateClientRequestId = () => {
                if (typeof window !== 'undefined' && window.crypto?.randomUUID) {
                    return window.crypto.randomUUID();
                }

                if (typeof window !== 'undefined' && window.crypto?.getRandomValues) {
                    const buffer = new Uint32Array(4);
                    window.crypto.getRandomValues(buffer);
                    return Array.from(buffer, (value) => value.toString(16).padStart(8, '0')).join('');
                }

                return `${Date.now().toString(16)}-${Math.random().toString(16).slice(2, 10)}`;
            };
            const varianceState = () => {
                const expectedQty = Number(state.detail?.batch?.expected_output_qty || 0);
                const actualQty = Number(el.postOutput.value || 0);
                const signedVarianceQty = normalizeQtyValue(actualQty - expectedQty);
                const varianceQty = Math.abs(signedVarianceQty);
                const signedVariancePct = expectedQty > 0 ? ((actualQty - expectedQty) * 100) / expectedQty : 0;
                const variancePct = Math.abs(signedVariancePct);
                const requiresReason = varianceQty >= varianceThresholds.qty || variancePct >= varianceThresholds.pct;
                const requiresConfirmation = variancePct >= 20;
                const blocksPosting = variancePct >= 50;

                return {
                    expectedQty,
                    actualQty,
                    signedVarianceQty,
                    signedVariancePct,
                    varianceQty,
                    variancePct,
                    requiresReason,
                    requiresConfirmation,
                    blocksPosting,
                };
            };

            const reasonIndicatesInputMistake = () => {
                const reason = (el.postVarianceReason.value || '').toLowerCase();

                return reason.includes('salah input') || reason.includes('typo') || reason.includes('koreksi');
            };

            const requiresVarianceReason = () => {
                const current = varianceState();

                return current.requiresReason;
            };
            const collectPostOutputs = () => {
                if (!el.postOutputs) {
                    return [];
                }

                return Array.from(el.postOutputs.querySelectorAll('input[data-output-product-id]')).map((input) => ({
                    product_id: input.dataset.outputProductId,
                    ...(input.dataset.outputProductVariantId ? { product_variant_id: input.dataset.outputProductVariantId } : {}),
                    actual_qty: Number(input.value || 0),
                }));
            };
            const syncPostOutputTotal = () => {
                const outputs = collectPostOutputs();
                if (!outputs.length) {
                    return Number(el.postOutput.value || 0);
                }

                const total = outputs.reduce((sum, row) => sum + Number(row.actual_qty || 0), 0);
                el.postOutput.value = normalizeQtyValue(total);
                refreshVarianceHint();

                return total;
            };
            const refreshVarianceHint = () => {
                const current = varianceState();
                const hasMistakeReason = reasonIndicatesInputMistake();
                const reasonRequired = current.requiresReason || current.requiresConfirmation;
                el.postVarianceReason.toggleAttribute('required', reasonRequired);

                const baseText = `Rencana ${formatNumber(current.expectedQty)} - aktual ${formatNumber(current.actualQty)} - selisih ${formatNumber(current.signedVarianceQty)} (${formatNumber(current.signedVariancePct)}%).`;

                if (hasMistakeReason) {
                    el.postVarianceHint.className = 'rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700';
                    el.postVarianceHint.textContent = `${baseText} Alasan menunjukkan salah input. Jangan posting batch; gunakan Edit Draft atau Batalkan Draft agar stok dan HPP tidak bergerak dari angka salah.`;
                    return;
                }

                if (current.blocksPosting) {
                    el.postVarianceHint.className = 'rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700';
                    el.postVarianceHint.textContent = `${baseText} Selisih terlalu besar dan kemungkinan salah input. Koreksi draft atau batalkan batch dulu.`;
                    return;
                }

                if (current.requiresConfirmation) {
                    el.postVarianceHint.className = 'rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800';
                    el.postVarianceHint.textContent = `${baseText} Selisih besar. Pastikan ini benar-benar hasil produksi, lalu isi alasan sebelum posting.`;
                    return;
                }

                if (current.requiresReason) {
                    el.postVarianceHint.className = 'rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700';
                    el.postVarianceHint.textContent = `${baseText} Alasan selisih wajib diisi karena menyentuh threshold monitoring (${varianceThresholds.qty} qty / ${varianceThresholds.pct}%).`;
                    return;
                }

                el.postVarianceHint.className = 'rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300';
                el.postVarianceHint.textContent = `${baseText} Selisih masih kecil; alasan opsional.`;
            };
            const buildObservabilityUrl = (detail) => {
                if (!canViewObservability || !observabilityBaseUrl) {
                    return '';
                }

                const baseUrl = detail?.observability_url || observabilityBaseUrl;
                const batch = detail?.batch || {};
                const url = new URL(baseUrl, window.location.origin);
                url.searchParams.set('type', 'production_batch_yield_variance');
                url.searchParams.set('resolved', '0');
                url.searchParams.set('reference_id', batch.id || batchId);
                url.searchParams.set('context_label', batch.reference_no || batch.id || batchId);

                return `${url.pathname}?${url.searchParams.toString()}`;
            };
            const anomalySeverityClass = (severity) => ({
                critical: 'bg-red-100 text-red-700',
                high: 'bg-amber-100 text-amber-700',
                med: 'bg-sky-100 text-sky-700',
                low: 'bg-slate-100 text-slate-700',
            }[severity] || 'bg-slate-100 text-slate-700');
            const applyMobileTabState = () => {
                const activeTab = state.ui.activeTab || 'overview';
                const isDesktop = window.matchMedia('(min-width: 1024px)').matches;

                tabButtons.forEach((button) => {
                    const matches = button.dataset.batchTab === activeTab;
                    button.classList.toggle('is-active', matches);
                    button.setAttribute('aria-pressed', matches ? 'true' : 'false');
                });

                tabPanels.forEach((panel) => {
                    if (isDesktop) {
                        panel.classList.remove('hidden');
                        return;
                    }

                    panel.classList.toggle('hidden', panel.dataset.batchPanel !== activeTab);
                });
            };
            const renderAnomalySummary = (detail) => {
                const summary = detail?.anomaly_summary || {};
                const openCount = Number(summary.open_count || 0);
                const resolvedCount = Number(summary.resolved_count || 0);
                const totalCount = Number(summary.total_count || 0);
                const hasOpenAlert = !!summary.has_open_alert;

                el.anomalyBanner.className = `rounded-3xl border px-5 py-5 shadow-sm ring-1 ${hasOpenAlert
                    ? 'border-amber-200 bg-amber-50 text-amber-900 ring-amber-100/80'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-800 ring-emerald-100/80'}`;
                el.anomalyCount.className = `rounded-full px-3 py-1 text-[11px] font-bold uppercase ${hasOpenAlert
                    ? 'bg-white/80 text-amber-800'
                    : 'bg-white/80 text-emerald-700'}`;
                el.anomalyCount.textContent = `${openCount} open alert`;
                el.anomalyStatus.textContent = hasOpenAlert
                    ? `${openCount} alert yield variance masih terbuka.`
                    : 'Tidak ada alert batch yang terbuka.';

                if (hasOpenAlert) {
                    const detectedAt = summary.latest_open_detected_at ? formatDate(summary.latest_open_detected_at, true) : '-';
                    el.anomalyMeta.textContent = `Alert terakhir terdeteksi ${detectedAt}. Segera review variance reason dan hasil output aktual.`;
                } else if (totalCount > 0 || resolvedCount > 0) {
                    el.anomalyMeta.textContent = `${resolvedCount} alert sebelumnya sudah ditutup untuk batch ini.`;
                } else {
                    el.anomalyMeta.textContent = 'Belum ada anomaly yield variance yang aktif untuk batch ini.';
                }

                if (canViewObservability && el.observabilityLink) {
                    el.observabilityLink.href = buildObservabilityUrl(detail);
                }
            };
            const renderAnomalyHistory = (detail) => {
                const rows = detail?.anomaly_history || [];
                const historyMeta = detail?.anomaly_history_meta || {};

                el.anomalyHistoryMeta.textContent = `${Number(historyMeta.total || rows.length || 0)} alert • filter ${historyMeta.status || 'all'}${historyMeta.has_more ? ` • ${rows.length} tampil` : ''}`;
                el.anomalyLoadMore.classList.toggle('hidden', !historyMeta.has_more);

                if (!rows.length) {
                    el.anomalyHistory.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-400">Belum ada riwayat alert yield variance untuk batch ini.</div>';
                    return;
                }

                el.anomalyHistory.innerHTML = rows.map((row) => {
                    const payload = parsePayload(row.payload_json);
                    const resolved = row.status === 'resolved';
                    const resolvedNote = row.resolved_note || payload.resolved_note || '-';
                    const varianceText = `${formatNumber(payload.yield_variance_qty)} (${formatNumber(payload.yield_variance_pct)}%)`;
                    const scopeText = [branchName(row.branch_id || payload.branch_id), locationName(row.location_id || payload.location_id)].filter((value) => value && value !== 'Cabang' && value !== 'Lokasi').join(' • ');

                    return `
                        <div class="rounded-2xl border border-slate-200 px-4 py-4 dark:border-slate-800">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase ${anomalySeverityClass(row.severity)}">${row.severity}</span>
                                        <span class="rounded-full px-3 py-1 text-[11px] font-bold uppercase ${resolved ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}">${row.status}</span>
                                    </div>
                                    <p class="mt-2 text-sm font-bold text-slate-900 dark:text-slate-100">Yield variance ${varianceText}</p>
                                    <p class="mt-1 text-xs text-slate-500">Detected ${formatDate(row.detected_at, true)}${resolved && row.resolved_at ? ` • Resolved ${formatDate(row.resolved_at, true)}` : ''}</p>
                                    <p class="mt-1 text-xs text-slate-500">${scopeText || 'Scope batch mengikuti lokasi posting saat anomaly terdeteksi.'}</p>
                                </div>
                                ${!resolved && canResolveAnomaly ? `<button data-anomaly-resolve-id="${row.id}" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-bold text-white" type="button">Resolve Alert</button>` : ''}
                            </div>
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <div class="rounded-2xl bg-slate-50 px-3 py-3 text-sm dark:bg-slate-950">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Variance Reason</p>
                                    <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">${payload.variance_reason || '-'}</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-3 py-3 text-sm dark:bg-slate-950">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Resolved Note</p>
                                    <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">${resolvedNote}</p>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            };

            const setFeedback = (element, message, isError = true) => {
                if (!message) {
                    element.classList.add('hidden');
                    element.textContent = '';
                    return;
                }

                element.className = `rounded-2xl px-4 py-3 text-sm ${isError ? 'border border-red-200 bg-red-50 text-red-600' : 'border border-emerald-200 bg-emerald-50 text-emerald-700'}`;
                element.textContent = message;
            };

            const toggleModal = (key, open) => {
                const modal = {
                    edit: el.editModal,
                    post: el.postModal,
                    cancel: el.cancelModal,
                    resolve: el.resolveModal,
                    'correct-date': el.correctDateModal,
                    'void-posting': el.voidPostingModal,
                    waste: el.wasteModal,
                }[key];

                modal?.classList.toggle('hidden', !open);
                if (!open) {
                    if (key === 'edit') setFeedback(el.editFeedback, '');
                    if (key === 'post') setFeedback(el.postFeedback, '');
                    if (key === 'cancel') setFeedback(el.cancelFeedback, '');
                    if (key === 'correct-date') setFeedback(el.correctDateFeedback, '');
                    if (key === 'void-posting') setFeedback(el.voidPostingFeedback, '');
                    if (key === 'waste') setFeedback(el.wasteFeedback, '');
                    if (key === 'resolve') {
                        setFeedback(el.resolveFeedback, '');
                        state.selectedAnomalyId = null;
                    }
                }
            };

            const requestJson = async (url, options = {}) => {
                const mergedHeaders = {
                    ...headers,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    ...(options.headers || {}),
                };

                const response = await fetch(url, {
                    ...options,
                    credentials: 'same-origin',
                    headers: mergedHeaders,
                });

                const result = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const details = result.details ? Object.values(result.details).flat().join(' ') : '';
                    throw new Error(details || result.message || 'Permintaan gagal.');
                }

                return result;
            };

            const getBootstrapProducts = () => {
                const products = state.bootstrap?.products;
                if (Array.isArray(products)) {
                    return products;
                }

                if (Array.isArray(products?.data)) {
                    return products.data;
                }

                return [];
            };

            const syncEditExpectedOutput = () => {
                const batch = state.detail?.batch || {};
                const recipeCount = Math.max(0, Number(el.editRecipeVersion?.value || batch.recipe_count || batch.recipe_version || 0));
                const product = getBootstrapProducts().find((item) => String(item.id || '') === String(batch.product_id || '')) || null;
                const recipeOutputQty = normalizeQtyValue(
                    product?.recipe_output_qty
                    ?? product?.production_batch_eligibility?.recipe_output_qty
                    ?? 0
                );
                const expectedOutputQty = recipeCount > 0 && recipeOutputQty > 0
                    ? normalizeQtyValue(recipeCount * recipeOutputQty)
                    : Number(batch.expected_output_qty || 0);

                el.editOutput.value = expectedOutputQty > 0 ? String(expectedOutputQty) : '';

                return expectedOutputQty;
            };

            const renderLines = (target, lines, kind) => {
                target.innerHTML = '';
                if (!lines.length) {
                    target.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-400">Belum ada line ${kind}.</div>`;
                    return;
                }

                lines.forEach((line) => {
                    const row = document.createElement('div');
                    row.className = 'rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800';
                    const unitCost = Number(line.unit_cost_snapshot || 0);
                    const actualQty = Number(line.actual_qty || 0);
                    const costSummary = kind === 'output' && unitCost > 0
                        ? `
                            <div class="mt-3 rounded-2xl bg-emerald-50 px-3 py-3 text-sm text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-700/80 dark:text-emerald-200/80">HPP Batch</p>
                                <p class="mt-1 font-semibold">Per unit ${formatCurrency(unitCost)} • Total ${formatCurrency(actualQty * unitCost)}</p>
                            </div>
                        `
                        : '';
                    row.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-slate-900 dark:text-slate-100">${line.product_name || productName(line.product_id)}</p>
                                <p class="text-xs text-slate-500">${line.product_sku || line.product_id}</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold uppercase text-slate-500">${kind}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-3 text-sm">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Planned</p>
                                <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">${formatNumber(line.planned_qty)}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Actual</p>
                                <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">${formatNumber(line.actual_qty)}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">UOM</p>
                                <p class="mt-1 font-semibold text-slate-900 dark:text-slate-100">${line.uom || '-'}</p>
                            </div>
                        </div>
                        ${costSummary}
                    `;
                    target.appendChild(row);
                });
            };

            const render = () => {
                const detail = state.detail || {};
                const batch = detail.batch || {};
                const inputs = detail.inputs || [];
                const outputs = detail.outputs || [];

                el.subtitle.textContent = `Batch ${batch.reference_no || batch.id || '-'} • ${branchName(batch.branch_id)} • ${locationName(batch.location_id)}`;
                el.reference.textContent = batch.reference_no || (batch.id || '').slice(0, 8).toUpperCase();
                el.product.textContent = productName(batch.product_id);
                el.location.textContent = `${branchName(batch.branch_id)} • ${locationName(batch.location_id)}`;
                el.status.className = `rounded-full px-3 py-1 text-[11px] font-bold uppercase ${badgeClass(batch.status)}`;
                el.status.textContent = batch.status || '-';
                el.date.textContent = batch.transaction_date ? formatDate(batch.transaction_date) : '-';
                el.expectedOutput.textContent = formatNumber(batch.expected_output_qty);
                el.actualOutput.textContent = formatNumber(batch.actual_output_qty);
                el.variance.textContent = `${formatNumber(batch.yield_variance_qty)} (${formatNumber(batch.yield_variance_pct)}%)`;
                el.recipeVersion.textContent = batch.recipe_count || batch.recipe_version || 0;
                el.inputCount.textContent = `${inputs.length} line`;
                el.referenceFull.textContent = batch.reference_no || '-';
                el.notes.textContent = batch.notes || '-';
                el.varianceReason.textContent = batch.variance_reason || '-';
                el.cancelReason.textContent = batch.cancel_reason || '-';
                el.postedAt.textContent = batch.posted_at ? formatDate(batch.posted_at, true) : '-';
                el.actions.classList.toggle('hidden', batch.status !== 'draft');
                el.postedActions?.classList.toggle('hidden', !((canCorrectPosted || canRecordWaste) && batch.status === 'posted'));
                renderAnomalySummary(detail);
                renderAnomalyHistory(detail);

                el.editDate.value = batch.transaction_date || '';
                el.editOutput.value = batch.expected_output_qty || '';
                el.editRecipeVersion.value = batch.recipe_count || batch.recipe_version || 1;
                el.editReference.value = batch.reference_no || '';
                el.editNotes.value = batch.notes || '';
                syncEditExpectedOutput();
                el.postOutput.value = batch.expected_output_qty || '';
                el.postVarianceReason.value = batch.variance_reason || '';
                el.cancelReasonInput.value = batch.cancel_reason || '';
                if (el.correctDateValue) el.correctDateValue.value = batch.transaction_date || '';
                if (el.correctDateReason) el.correctDateReason.value = '';
                if (el.voidPostingReason) el.voidPostingReason.value = '';
                if (el.wasteQty) el.wasteQty.value = '';
                if (el.wasteNotes) el.wasteNotes.value = '';
                if (el.wasteType) el.wasteType.value = 'damaged';
                if (el.wasteProduct) {
                    el.wasteProduct.innerHTML = '';
                    outputs.forEach((line) => {
                        const option = document.createElement('option');
                        option.value = `${line.product_id}|${line.product_variant_id || ''}`;
                        option.textContent = `${line.product_name || productName(line.product_id)} - hasil ${formatNumber(line.actual_qty || line.planned_qty || 0)}`;
                        el.wasteProduct.appendChild(option);
                    });
                    el.wasteSubmit?.toggleAttribute('disabled', outputs.length === 0 || batch.status !== 'posted');
                }
                el.postOutputs.innerHTML = '';
                outputs.forEach((line) => {
                    const row = document.createElement('label');
                    row.className = 'block rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800';
                    row.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-slate-900 dark:text-slate-100">${line.product_name || productName(line.product_id)}</p>
                                <p class="text-xs text-slate-500">Planned ${formatNumber(line.planned_qty)}${line.recipe_factor ? ` • faktor ${formatNumber(line.recipe_factor)} resep` : ''}</p>
                            </div>
                            <input class="mt-1 w-32 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" data-output-product-id="${line.product_id}" data-output-product-variant-id="${line.product_variant_id || ''}" min="0" step="0.0001" type="number" value="${line.planned_qty || 0}" />
                        </div>
                    `;
                    el.postOutputs.appendChild(row);
                });
                syncPostOutputTotal();
                el.postInputs.innerHTML = '';
                inputs.forEach((line) => {
                    const row = document.createElement('label');
                    row.className = 'block rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800';
                    row.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-slate-900 dark:text-slate-100">${line.product_name || productName(line.product_id)}</p>
                                <p class="text-xs text-slate-500">Planned ${formatNumber(line.planned_qty)} ${line.uom || ''}</p>
                            </div>
                            <input class="mt-1 w-32 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium dark:border-slate-700 dark:bg-slate-900" data-product-id="${line.product_id}" data-product-variant-id="${line.product_variant_id || ''}" min="0" step="0.0001" type="number" value="${line.planned_qty || 0}" />
                        </div>
                    `;
                    el.postInputs.appendChild(row);
                });

                renderLines(el.inputs, inputs, 'input');
                renderLines(el.outputs, outputs, 'output');
                refreshVarianceHint();
                applyMobileTabState();
            };

            const loadBootstrap = async () => {
                const response = await fetch(window.PosUi.bootstrapUrl({ includeInventory: true }), { headers });
                if (!response.ok) {
                    throw new Error('Gagal memuat master data batch.');
                }
                state.bootstrap = unwrap(await response.json().catch(() => ({})));
            };

            const loadDetail = async () => {
                const query = new URLSearchParams({
                    anomaly_status: state.historyFilters.status,
                    anomaly_limit: String(state.historyFilters.limit),
                });
                const response = await fetch(`/api/v1/production-batches/${encodeURIComponent(batchId)}?${query.toString()}`, { headers });
                if (!response.ok) {
                    throw new Error('Gagal memuat detail batch produksi.');
                }
                state.detail = await response.json().catch(() => ({}));
            };

            [
                [el.editOpen, 'edit', true],
                [el.postOpen, 'post', true],
                [el.cancelOpen, 'cancel', true],
                [el.correctDateOpen, 'correct-date', true],
                [el.voidPostingOpen, 'void-posting', true],
                [el.wasteOpen, 'waste', true],
            ].forEach(([node, key, open]) => {
                node?.addEventListener('click', () => toggleModal(key, open));
            });

            document.querySelectorAll('[data-close="edit"]').forEach((node) => node.addEventListener('click', () => toggleModal('edit', false)));
            document.querySelectorAll('[data-close="post"]').forEach((node) => node.addEventListener('click', () => toggleModal('post', false)));
            document.querySelectorAll('[data-close="cancel"]').forEach((node) => node.addEventListener('click', () => toggleModal('cancel', false)));
            document.querySelectorAll('[data-close="resolve"]').forEach((node) => node.addEventListener('click', () => toggleModal('resolve', false)));
            document.querySelectorAll('[data-close="correct-date"]').forEach((node) => node.addEventListener('click', () => toggleModal('correct-date', false)));
            document.querySelectorAll('[data-close="void-posting"]').forEach((node) => node.addEventListener('click', () => toggleModal('void-posting', false)));
            document.querySelectorAll('[data-close="waste"]').forEach((node) => node.addEventListener('click', () => toggleModal('waste', false)));
            el.editRecipeVersion?.addEventListener('input', syncEditExpectedOutput);
            el.postOutput?.addEventListener('input', refreshVarianceHint);
            el.postVarianceReason?.addEventListener('input', refreshVarianceHint);
            el.postVarianceReasonPresets?.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-variance-reason]');
                if (!trigger) {
                    return;
                }

                el.postVarianceReason.value = trigger.dataset.varianceReason || '';
                refreshVarianceHint();
            });
            el.postOutputs?.addEventListener('input', syncPostOutputTotal);
            el.tabNav?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-batch-tab]');
                if (!button) {
                    return;
                }

                state.ui.activeTab = button.dataset.batchTab || 'overview';
                applyMobileTabState();
            });
            el.anomalyStatusFilter?.addEventListener('change', async () => {
                state.historyFilters.status = el.anomalyStatusFilter.value || 'all';
                state.historyFilters.limit = 20;
                await loadDetail();
                render();
            });
            window.addEventListener('resize', applyMobileTabState);
            el.anomalyLoadMore?.addEventListener('click', async () => {
                state.historyFilters.limit += 20;
                await loadDetail();
                render();
            });

            el.editForm?.addEventListener('submit', async (event) => {
                event.preventDefault();
                setFeedback(el.editFeedback, '');
                el.editSubmit.disabled = true;
                el.editSubmit.textContent = 'Menyimpan...';

                try {
                    const expectedOutputQty = syncEditExpectedOutput();
                    await requestJson(`/api/v1/production-batches/${encodeURIComponent(batchId)}`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            transaction_date: el.editDate.value || null,
                            expected_output_qty: Number(expectedOutputQty || 0),
                            recipe_count: Number(el.editRecipeVersion.value || 0),
                            reference_no: (el.editReference.value || '').trim() || null,
                            notes: (el.editNotes.value || '').trim() || null,
                        }),
                    });

                    toggleModal('edit', false);
                    await loadDetail();
                    render();
                } catch (error) {
                    setFeedback(el.editFeedback, error.message || 'Gagal memperbarui draft batch.');
                } finally {
                    el.editSubmit.disabled = false;
                    el.editSubmit.textContent = 'Simpan Perubahan';
                }
            });

            el.postForm?.addEventListener('submit', async (event) => {
                event.preventDefault();
                setFeedback(el.postFeedback, '');

                const currentVariance = varianceState();
                if (reasonIndicatesInputMistake()) {
                    refreshVarianceHint();
                    setFeedback(el.postFeedback, 'Alasan menunjukkan salah input. Jangan posting batch; edit draft atau batalkan draft dulu.');
                    return;
                }

                if (currentVariance.blocksPosting) {
                    refreshVarianceHint();
                    setFeedback(el.postFeedback, 'Selisih hasil terlalu besar dan kemungkinan salah input. Koreksi draft atau batalkan batch dulu.');
                    return;
                }

                if ((currentVariance.requiresReason || currentVariance.requiresConfirmation) && !(el.postVarianceReason.value || '').trim()) {
                    refreshVarianceHint();
                    setFeedback(el.postFeedback, 'Alasan selisih wajib diisi sebelum posting hasil aktual yang berbeda dari rencana.');
                    return;
                }

                el.postSubmit.disabled = true;
                el.postSubmit.textContent = 'Posting...';

                try {
                    const outputs = collectPostOutputs();
                    syncPostOutputTotal();
                    const inputs = Array.from(el.postInputs.querySelectorAll('input[data-product-id]')).map((input) => ({
                        product_id: input.dataset.productId,
                        ...(input.dataset.productVariantId ? { product_variant_id: input.dataset.productVariantId } : {}),
                        actual_qty: Number(input.value || 0),
                    }));

                    await requestJson(`/api/v1/production-batches/${encodeURIComponent(batchId)}/post`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Idempotency-Key': `web-production-post-${generateClientRequestId()}`,
                        },
                        body: JSON.stringify({
                            actual_output_qty: Number(el.postOutput.value || 0),
                            variance_reason: (el.postVarianceReason.value || '').trim() || null,
                            inputs,
                            ...(outputs.length > 1 || outputs.some((row) => row.product_id !== state.detail?.batch?.product_id) ? { outputs } : {}),
                        }),
                    });

                    toggleModal('post', false);
                    await loadDetail();
                    render();
                } catch (error) {
                    setFeedback(el.postFeedback, error.message || 'Gagal mem-post batch.');
                } finally {
                    el.postSubmit.disabled = false;
                    el.postSubmit.textContent = 'Post Batch';
                }
            });

            el.cancelSubmit?.addEventListener('click', async () => {
                setFeedback(el.cancelFeedback, '');

                if (!(el.cancelReasonInput.value || '').trim()) {
                    setFeedback(el.cancelFeedback, 'Cancel reason wajib diisi saat membatalkan draft batch.');
                    return;
                }

                el.cancelSubmit.disabled = true;
                el.cancelSubmit.textContent = 'Membatalkan...';

                try {
                    await requestJson(`/api/v1/production-batches/${encodeURIComponent(batchId)}/cancel`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            cancel_reason: (el.cancelReasonInput.value || '').trim(),
                        }),
                    });

                    toggleModal('cancel', false);
                    await loadDetail();
                    render();
                } catch (error) {
                    setFeedback(el.cancelFeedback, error.message || 'Gagal membatalkan draft batch.');
                } finally {
                    el.cancelSubmit.disabled = false;
                    el.cancelSubmit.textContent = 'Ya, batalkan';
                }
            });

            el.correctDateSubmit?.addEventListener('click', async () => {
                setFeedback(el.correctDateFeedback, '');

                const transactionDate = el.correctDateValue?.value || '';
                const reason = (el.correctDateReason?.value || '').trim();
                if (!transactionDate) {
                    setFeedback(el.correctDateFeedback, 'Tanggal koreksi wajib diisi.');
                    return;
                }

                if (reason.length < 10) {
                    setFeedback(el.correctDateFeedback, 'Alasan koreksi minimal 10 karakter supaya audit jelas.');
                    return;
                }

                el.correctDateSubmit.disabled = true;
                el.correctDateSubmit.textContent = 'Menyimpan...';

                try {
                    await requestJson(`/api/v1/production-batches/${encodeURIComponent(batchId)}/correct-date`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            transaction_date: transactionDate,
                            reason,
                        }),
                    });

                    toggleModal('correct-date', false);
                    await loadDetail();
                    render();
                } catch (error) {
                    setFeedback(el.correctDateFeedback, error.message || 'Gagal menyimpan koreksi tanggal.');
                } finally {
                    el.correctDateSubmit.disabled = false;
                    el.correctDateSubmit.textContent = 'Simpan Koreksi';
                }
            });

            el.voidPostingSubmit?.addEventListener('click', async () => {
                setFeedback(el.voidPostingFeedback, '');

                const reason = (el.voidPostingReason?.value || '').trim();
                if (reason.length < 10) {
                    setFeedback(el.voidPostingFeedback, 'Alasan void minimal 10 karakter supaya audit jelas.');
                    return;
                }

                el.voidPostingSubmit.disabled = true;
                el.voidPostingSubmit.textContent = 'Mem-void...';

                try {
                    await requestJson(`/api/v1/production-batches/${encodeURIComponent(batchId)}/void-posting`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Idempotency-Key': `web-production-void-${generateClientRequestId()}`,
                        },
                        body: JSON.stringify({ reason }),
                    });

                    toggleModal('void-posting', false);
                    await loadDetail();
                    render();
                } catch (error) {
                    setFeedback(el.voidPostingFeedback, error.message || 'Gagal void posting batch.');
                } finally {
                    el.voidPostingSubmit.disabled = false;
                    el.voidPostingSubmit.textContent = 'Void Posting';
                }
            });

            el.wasteSubmit?.addEventListener('click', async () => {
                setFeedback(el.wasteFeedback, '');

                const selected = String(el.wasteProduct?.value || '');
                const [productId, productVariantId] = selected.split('|');
                const wasteQty = Number(el.wasteQty?.value || 0);
                const notes = (el.wasteNotes?.value || '').trim();
                const wasteType = el.wasteType?.value || 'damaged';

                if (!productId) {
                    setFeedback(el.wasteFeedback, 'Pilih produk jadi yang akan dicatat waste.');
                    return;
                }

                if (!(wasteQty > 0)) {
                    setFeedback(el.wasteFeedback, 'Qty waste harus lebih dari 0.');
                    return;
                }

                if (notes.length < 10) {
                    setFeedback(el.wasteFeedback, 'Catatan waste minimal 10 karakter supaya audit jelas.');
                    return;
                }

                el.wasteSubmit.disabled = true;
                el.wasteSubmit.textContent = 'Mencatat...';

                try {
                    await requestJson(`/api/v1/production-batches/${encodeURIComponent(batchId)}/waste`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Idempotency-Key': `web-production-waste-${generateClientRequestId()}`,
                        },
                        body: JSON.stringify({
                            waste_type: wasteType,
                            notes,
                            items: [
                                {
                                    product_id: productId,
                                    ...(productVariantId ? { product_variant_id: productVariantId } : {}),
                                    waste_qty: wasteQty,
                                },
                            ],
                        }),
                    });

                    toggleModal('waste', false);
                    await loadDetail();
                    render();
                } catch (error) {
                    setFeedback(el.wasteFeedback, error.message || 'Gagal mencatat waste produksi.');
                } finally {
                    el.wasteSubmit.disabled = false;
                    el.wasteSubmit.textContent = 'Catat Waste';
                }
            });

            el.anomalyHistory?.addEventListener('click', async (event) => {
                const button = event.target.closest('[data-anomaly-resolve-id]');
                if (!button || !canResolveAnomaly) {
                    return;
                }

                state.selectedAnomalyId = button.dataset.anomalyResolveId;
                const card = button.closest('.rounded-2xl');
                el.resolveMeta.textContent = card?.querySelector('.text-sm.font-bold')?.textContent
                    ? `${card.querySelector('.text-sm.font-bold').textContent} akan ditandai selesai dan masuk audit trail.`
                    : 'Alert yang dipilih akan ditandai selesai dan masuk audit trail.';
                el.resolveNote.value = 'resolved from batch detail';
                toggleModal('resolve', true);
            });

            el.resolveSubmit?.addEventListener('click', async () => {
                if (!state.selectedAnomalyId) {
                    return;
                }

                setFeedback(el.resolveFeedback, '');
                el.resolveSubmit.disabled = true;
                el.resolveSubmit.textContent = 'Resolving...';

                try {
                    await requestJson(`/api/v1/anomalies/${encodeURIComponent(state.selectedAnomalyId)}/resolve`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            note: (el.resolveNote.value || '').trim() || 'resolved from batch detail',
                        }),
                    });

                    toggleModal('resolve', false);
                    await loadDetail();
                    render();
                } catch (error) {
                    setFeedback(el.resolveFeedback, error.message || 'Gagal resolve alert batch.');
                } finally {
                    el.resolveSubmit.disabled = false;
                    el.resolveSubmit.textContent = 'Resolve Alert';
                }
            });

            Promise.all([loadBootstrap(), loadDetail()])
                .then(render)
                .catch((error) => {
                    el.subtitle.textContent = error.message;
                    el.product.textContent = error.message;
                    el.inputs.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                    el.outputs.innerHTML = '';
                });
        })();
    </script>
@endsection
