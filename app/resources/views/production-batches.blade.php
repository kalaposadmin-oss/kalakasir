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
    $productionBatchPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $productionBatchUiMode = $productionBatchPortalPrefix === '/pos' ? 'pos' : 'backoffice';
    $productionBatchMainClass = $productionBatchPortalPrefix === '/pos' ? 'flex-1 overflow-y-auto pb-24' : 'flex-1 overflow-y-auto pb-8 lg:pl-72';
    $productionBatchCurrentPath = $productionBatchPortalPrefix.'/inventory/production-batches';
    $productionBatchBackUrl = $productionBatchPortalPrefix === '/pos' ? '/pos/inventory' : '/backoffice/inventory';
    $productionBatchCanViewObservability = $productionBatchTenant
        && in_array($productionBatchRole, ['admin', 'owner', 'superadmin'], true)
        && app(\App\Services\SubscriptionService::class)->canAccessFeature($productionBatchTenant, 'settings.observability');
    $productionBatchObservabilityBaseUrl = $productionBatchCanViewObservability ? '/backoffice/settings/observability' : null;

    $productionBatchFrom = trim((string) request()->query('from', ''));
    if ($productionBatchFrom !== '') {
        if (str_starts_with($productionBatchFrom, $productionBatchPortalPrefix.'/')) {
            $productionBatchBackUrl = $productionBatchFrom;
        } elseif ($productionBatchPortalPrefix === '/backoffice' && str_starts_with($productionBatchFrom, '/pos/')) {
            $productionBatchBackUrl = '/portal/switch/pos?next='.rawurlencode($productionBatchFrom);
        } elseif ($productionBatchPortalPrefix === '/pos' && str_starts_with($productionBatchFrom, '/backoffice/')) {
            $productionBatchBackUrl = '/portal/switch/backoffice?next='.rawurlencode($productionBatchFrom);
        }
    }
    $productionBatchBackUrl = normalize_pos_home_url($productionBatchBackUrl);
    $productionBatchMaterialsUrl = $productionBatchPortalPrefix.'/materials';
    $productionBatchProductsUrl = $productionBatchPortalPrefix.'/products';
    $productionBatchInventoryUrl = $productionBatchPortalPrefix.'/inventory';
    $productionBatchDetailBaseUrl = $productionBatchPortalPrefix.'/inventory/production-batches';
    $productionBatchBranchManagementUrl = '/backoffice/branches';
@endphp

@section('page_title', 'Batch Produksi')
@section('ui_mode', $productionBatchUiMode)
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', $productionBatchMainClass)

@push('head')
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .production-shell {
            background: #eef1f0;
        }
        .production-panel {
            border: 0;
            background: transparent;
            box-shadow: none;
        }
        .production-panel-dark {
            background: transparent;
        }
        .production-card {
            border: 1px solid rgba(225, 229, 228, 0.95);
            background: #f8faf9;
            box-shadow: 0 2px 6px -4px rgba(15, 23, 42, 0.2);
        }
        .production-card-dark {
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.98), rgba(15, 23, 42, 0.94));
        }
        .production-hero {
            border: 0;
            background:
                linear-gradient(135deg, rgba(192, 231, 219, 0.98), rgba(185, 225, 214, 0.98));
            box-shadow: 0 16px 32px -26px rgba(8, 88, 72, 0.28);
        }
        .production-hero-media {
            background:
                linear-gradient(180deg, rgba(7, 12, 20, 0.10), rgba(7, 12, 20, 0.50)),
                url('/images/placeholders/production-batch.svg') center/cover no-repeat;
        }
        .production-hero-dark {
            background:
                radial-gradient(circle at top right, rgba(56, 189, 248, 0.12), transparent 22%),
                linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.94) 56%, rgba(8, 15, 31, 0.94));
        }
        .kpi-accent-total { border-left: 3px solid rgba(15, 118, 110, 0.45); }
        .kpi-accent-draft { border-left: 3px solid rgba(59, 130, 246, 0.42); }
        .kpi-accent-posted { border-left: 3px solid rgba(16, 185, 129, 0.42); }
        .kpi-accent-variance { border-left: 3px solid rgba(244, 63, 94, 0.42); }
        .kpi-accent-open { border-left: 3px solid rgba(180, 83, 9, 0.42); }
        .kpi-accent-cancelled { border-left: 3px solid rgba(71, 85, 105, 0.42); }
        .kpi-accent-varianceqty { border-left: 3px solid rgba(244, 63, 94, 0.42); }
        .batch-card {
            border: 1px solid rgba(225, 229, 228, 0.95);
            background: #f8faf9;
            box-shadow: 0 2px 6px -4px rgba(15, 23, 42, 0.2);
        }
        .batch-card:hover {
            transform: translateY(0);
            border-color: rgba(225, 229, 228, 0.95);
        }
        .production-alert-banner {
            background: #eadfd8;
            border: 1px solid rgba(221, 207, 198, 0.9);
        }
        .production-action-fab {
            left: 50%;
            transform: translateX(-50%);
            bottom: 5.2rem;
            min-width: 17rem;
            background: #7bae9e;
            box-shadow: 0 16px 28px -18px rgba(123, 174, 158, 0.75);
        }
        .production-filter-sheet {
            background: #ffffff;
        }
        .production-filter-block {
            border: 1px solid rgba(226, 232, 240, 0.95);
            background: #f8fafc;
        }
        .production-mobile-field {
            min-height: 3rem;
        }
        .production-mobile-footer {
            box-shadow: 0 -12px 24px -20px rgba(15, 23, 42, 0.28);
        }
        .production-btn-secondary {
            border: 1px solid rgba(226, 232, 240, 0.95);
            background: #f1f5f9;
            color: #64748b;
        }
        .production-btn-secondary:hover {
            background: #e2e8f0;
        }
        .production-btn-primary {
            background: #7bae9e;
            color: #ffffff;
            box-shadow: 0 10px 22px -12px rgba(123, 174, 158, 0.75);
        }
        .production-btn-primary:hover {
            filter: brightness(1.03);
        }
    </style>
@endpush

@section('header')
    <x-page-header
        title="Batch Produksi"
        :backUrl="$productionBatchBackUrl"
        subtitle="Monitoring draf, posting, dan variance produksi"
        :showMenuButton="true"
    >
        <x-slot name="actions">
            <a
                href="{{ $productionBatchInventoryUrl }}"
                class="flex items-center justify-center size-9 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                aria-label="Buka inventaris"
                title="Inventaris"
            >
                <span class="material-symbols-outlined text-[20px]">inventory_2</span>
            </a>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
    <div class="mx-auto w-full max-w-[560px] px-4 py-4 space-y-5 production-shell lg:max-w-7xl lg:px-8 lg:space-y-4">
        <section class="production-hero overflow-hidden rounded-3xl text-sm text-white dark:production-hero-dark dark:text-indigo-50">
            <div class="production-hero-media relative min-h-[220px] overflow-hidden sm:min-h-[250px]">
                <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-black/20 to-transparent"></div>
                <div class="relative flex h-full min-h-[220px] items-end p-4 sm:min-h-[250px] sm:p-6">
                    <div class="max-w-md">
                        <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-white/70">Produksi</p>
                        <h3 class="mt-1 text-[28px] font-bold leading-tight tracking-tight drop-shadow-sm sm:text-[32px]">Buat dan Pantau Produksi</h3>
                        <p class="mt-2 max-w-xl text-sm leading-relaxed text-white/85 sm:text-[15px]">Rencanakan produksi, cek kebutuhan bahan, lalu selesaikan saat hasil aktual sudah siap masuk stok.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-4" id="production-batch-stats">
            <div class="production-card kpi-accent-total w-full rounded-2xl p-4 transition dark:production-card-dark">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Total</p>
                <p class="mt-2 text-3xl font-bold leading-none text-slate-900 dark:text-slate-100" data-stat="total">0</p>
            </div>
            <div class="production-card kpi-accent-draft w-full rounded-2xl p-4 transition dark:production-card-dark">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Draf</p>
                <p class="mt-2 text-3xl font-bold leading-none text-amber-600" data-stat="draft">0</p>
            </div>
            <div class="production-card kpi-accent-posted w-full rounded-2xl p-4 transition dark:production-card-dark">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Diposting</p>
                <p class="mt-2 text-3xl font-bold leading-none text-emerald-600" data-stat="posted">0</p>
            </div>
            <div class="production-card kpi-accent-variance w-full rounded-2xl p-4 transition dark:production-card-dark">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Selisih</p>
                <p class="mt-2 text-3xl font-bold leading-none text-rose-600" data-stat="variance">0</p>
            </div>
            <div class="hidden production-card kpi-accent-cancelled rounded-3xl p-3 transition hover:-translate-y-0.5 hover:shadow-md dark:production-card-dark">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Dibatalkan</p>
                <p class="mt-2 text-xl font-bold text-slate-700 dark:text-slate-100" data-stat="cancelled">0</p>
            </div>
            <div class="hidden production-card kpi-accent-varianceqty rounded-3xl p-3 transition hover:-translate-y-0.5 hover:shadow-md dark:production-card-dark">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Selisih Qty</p>
                <p class="mt-2 text-xl font-bold text-rose-600" data-stat="variance_qty_total">0</p>
            </div>
            <div class="production-alert-banner col-span-2 rounded-2xl p-4 transition">
                <div class="flex items-center gap-3">
                    <div class="flex size-11 items-center justify-center rounded-2xl bg-black/10 text-[#2c1a10]">
                        <span class="material-symbols-outlined text-[22px]">warning</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-base font-bold text-slate-900">Open Alert</p>
                        <p class="mt-1 text-sm text-slate-700">Ada <span class="font-bold" data-stat="open_alert">0</span> batch yang perlu rekonsiliasi selisih segera.</p>
                    </div>
                    <a href="#production-batch-list" class="inline-flex shrink-0 items-center justify-center rounded-full bg-[#26170f] px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-black">
                        Review
                    </a>
                </div>
            </div>
        </section>

        <section class="production-panel rounded-3xl p-0 dark:production-panel-dark lg:p-0">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-xl font-bold uppercase tracking-tight text-slate-900">Bahan Baku Tersedia</h3>
                </div>
                <a href="{{ $productionBatchMaterialsUrl }}" class="text-base font-bold text-teal-600 hover:text-teal-700">
                    View Inventory
                </a>
            </div>
            <div id="production-material-chip-list" class="mt-4 flex gap-3 overflow-x-auto pb-1 no-scrollbar">
                <div class="rounded-full bg-white px-3 py-2 text-xs font-semibold text-slate-500 shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300">Memuat bahan baku...</div>
            </div>
        </section>

        <section class="production-panel rounded-3xl p-3 dark:production-panel-dark lg:p-4">
            <div class="mb-3 mt-3 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-xl font-bold uppercase tracking-tight text-[#121212]">Daftar Batch Produksi</h3>
                    <p id="production-batch-meta" class="mt-1 hidden text-xs text-slate-500">Memuat batch produksi...</p>
                </div>
                <button id="production-filter-open" class="inline-flex size-10 items-center justify-center rounded-full bg-transparent text-slate-500" type="button" aria-label="Buka filter detail">
                    <span class="material-symbols-outlined text-[18px]">filter_list</span>
                </button>
            </div>
            <div id="production-batch-list" class="space-y-3"></div>
        </section>
    </div>

    <div id="production-filter-modal" class="fixed inset-0 z-40 hidden">
        <div id="production-filter-overlay" class="absolute inset-0 bg-slate-950/45"></div>
        <div class="production-filter-sheet absolute inset-x-0 bottom-0 mx-auto w-full max-w-[430px] rounded-t-3xl shadow-2xl lg:top-1/2 lg:bottom-auto lg:-translate-y-1/2 lg:rounded-3xl">
            <div class="mx-auto mt-3 h-1.5 w-14 rounded-full bg-slate-200 dark:bg-slate-700 lg:mt-4"></div>
            <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 lg:px-6">
                <div>
                    <h3 class="mt-1 text-xl font-bold tracking-tight text-slate-900">Filter Riwayat Produksi</h3>
                </div>
                <button id="production-filter-close" class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-600" type="button">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <div class="max-h-[calc(100dvh-8rem)] overflow-y-auto px-5 py-5 lg:px-6">
                <div class="grid gap-3">
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Produk</span>
                        <select id="production-product-filter" class="production-filter-block w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"></select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Status</span>
                        <select id="production-status-filter" class="production-filter-block w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <option value="">Semua status</option>
                            <option value="draft">Draf</option>
                            <option value="posted">Diposting</option>
                            <option value="cancelled">Dibatalkan</option>
                        </select>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Cabang</span>
                            <select id="production-branch-filter" class="production-filter-block w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"></select>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Lokasi</span>
                            <select id="production-location-filter" class="production-filter-block w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"></select>
                        </label>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Dari Tanggal</span>
                            <input id="production-date-from" class="production-filter-block w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" type="date" />
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Sampai Tanggal</span>
                            <input id="production-date-to" class="production-filter-block w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" type="date" />
                        </label>
                    </div>
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Urutkan</span>
                        <select id="production-sort-filter" class="production-filter-block w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <option value="newest">Batch terbaru</option>
                            <option value="latest_anomaly">Alert terbaru</option>
                            <option value="largest_variance">Selisih terbesar</option>
                            <option value="oldest">Batch terlama</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Alasan Batal</span>
                        <input id="production-cancel-reason-filter" class="production-filter-block w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" maxlength="2000" placeholder="Cari alasan batal" type="text" />
                    </label>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 border-t border-slate-200 px-5 pb-[max(1rem,env(safe-area-inset-bottom))] pt-4 lg:px-6">
                <button id="production-batch-reset" class="production-btn-secondary rounded-2xl px-4 py-3 text-sm font-bold shadow-sm transition" type="button">Reset</button>
                <button id="production-filter-apply" class="production-btn-primary rounded-2xl px-5 py-3 text-sm font-bold transition" type="button">Terapkan Filter</button>
            </div>
        </div>
    </div>

    <button id="production-batch-create-open" class="production-action-fab fixed z-20 inline-flex items-center justify-center gap-2 rounded-2xl px-6 py-4 text-base font-bold uppercase tracking-[0.04em] text-white lg:text-sm" type="button">
        <span class="material-symbols-outlined text-[20px]">add</span>
        <span>Buat Produksi</span>
    </button>

    <div id="production-batch-create-modal" class="fixed inset-0 z-50 hidden">
        <div id="production-batch-create-overlay" class="absolute inset-0 bg-slate-950/45"></div>
        <div class="absolute inset-x-0 bottom-0 mx-auto w-full max-w-[430px] overflow-hidden rounded-t-3xl bg-white shadow-2xl">
            <div class="mx-auto mt-3 h-1.5 w-14 rounded-full bg-slate-200"></div>
            <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Batch Produksi</p>
                    <h3 class="mt-1 text-lg font-bold tracking-tight text-slate-900">Buat Rencana Produksi</h3>
                </div>
                <button id="production-batch-create-close" class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-500" type="button">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <form id="production-batch-create-form" class="max-h-[calc(100dvh-7rem)] space-y-3 overflow-y-auto px-5 py-5">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Cabang</span>
                        <select id="production-create-branch" class="production-filter-block production-mobile-field w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <option value="" selected>Memuat cabang...</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Lokasi</span>
                        <select id="production-create-location" class="production-filter-block production-mobile-field w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <option value="" selected>Memuat lokasi...</option>
                        </select>
                        <span class="mt-1 block text-[11px] text-slate-500">Wajib dipilih untuk pratinjau resep dan posting batch.</span>
                    </label>
                </div>
                <label class="block">
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Produk yang Diproduksi</span>
                    <select id="production-create-product" class="production-filter-block production-mobile-field w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="" selected>Memuat produk...</option>
                    </select>
                </label>
                <div id="production-create-helper" class="hidden rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-500"></div>
                <div id="production-create-eligibility-summary" class="hidden rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-900">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-amber-600">Produk belum siap</p>
                        <span id="production-create-eligibility-count" class="rounded-full bg-white px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-amber-700 ring-1 ring-amber-200"></span>
                    </div>
                    <div id="production-create-eligibility-list" class="mt-3 space-y-2"></div>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Tanggal Produksi</span>
                        <input id="production-create-date" class="production-filter-block production-mobile-field w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" type="date" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Jumlah Pembuatan Resep</span>
                        <input id="production-create-recipe-version" class="production-filter-block production-mobile-field w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" min="1" step="1" type="number" value="1" />
                        <span class="mt-1 block text-[11px] text-slate-500">Contoh: isi 3 kalau resep dibuat tiga kali.</span>
                    </label>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Perkiraan Hasil</span>
                        <input id="production-create-output" class="production-filter-block production-mobile-field w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" min="0.0001" step="0.0001" type="number" value="" readonly />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Referensi</span>
                        <input id="production-create-reference" class="production-filter-block production-mobile-field w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" maxlength="100" placeholder="PB-20260408-001" type="text" />
                    </label>
                </div>
                <div id="production-create-plan-summary" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-600">Rencana Produksi</p>
                    <p class="mt-1 font-semibold">Pilih produk dan isi jumlah pembuatan resep untuk melihat perkiraan hasil.</p>
                </div>
                <div id="production-create-recipe-preview" class="rounded-3xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600 shadow-sm">
                    <div class="rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Cek Bahan</p>
                        <p class="mt-1 font-medium leading-relaxed">Bahan otomatis mengikuti resep produk. Pilih produk, lokasi, dan jumlah resep untuk melihat kebutuhan bahan.</p>
                    </div>
                </div>
                <details id="production-output-advanced" class="rounded-3xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                        <span>
                            <span class="block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Hasil Jadi Lanjutan</span>
                            <span class="mt-1 block text-sm font-bold text-slate-900">Ubah kalau satu resep menghasilkan beberapa produk</span>
                        </span>
                        <span class="material-symbols-outlined text-[18px] text-slate-400">expand_more</span>
                    </summary>
                    <div class="mt-3 flex items-start justify-between gap-3">
                        <p class="text-xs leading-relaxed text-slate-500">Default mengikuti produk yang diproduksi. Tambahkan hasil lain hanya untuk produksi dengan beberapa output.</p>
                        <button id="production-output-add" class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-orange-50 text-primary ring-1 ring-orange-100" type="button" title="Tambah hasil jadi">
                            <span class="material-symbols-outlined text-[18px]">add</span>
                        </button>
                    </div>
                    <div id="production-output-summary" class="mt-3 rounded-2xl bg-slate-50 px-3 py-3 text-xs font-semibold text-slate-600">
                        Total hasil mengikuti perkiraan hasil produk yang dipilih.
                    </div>
                    <div id="production-output-rows" class="mt-3 space-y-3"></div>
                </details>
                <label class="block">
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400 sm:text-[11px]">Catatan</span>
                    <textarea id="production-create-notes" class="production-filter-block min-h-[96px] w-full rounded-2xl px-4 py-3 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" maxlength="2000" placeholder="Catatan operator atau target produksi"></textarea>
                </label>
                <div id="production-create-feedback" class="hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600"></div>
                <div class="production-mobile-footer sticky bottom-0 -mx-5 grid grid-cols-2 gap-3 border-t border-slate-200 bg-white px-5 pb-[max(1rem,env(safe-area-inset-bottom))] pt-4">
                    <button class="production-btn-secondary rounded-2xl px-4 py-3 text-sm font-bold shadow-sm transition" type="button" id="production-create-cancel">Batal</button>
                    <button class="production-btn-primary rounded-2xl px-5 py-3 text-sm font-bold transition" type="submit" id="production-create-submit">Simpan Rencana</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content || '';
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
            const currentPath = @json($productionBatchCurrentPath);
            const detailBaseUrl = @json($productionBatchDetailBaseUrl);
            const productionBatchProductsUrl = @json($productionBatchProductsUrl);
            const productionBatchBranchManagementUrl = @json($productionBatchBranchManagementUrl);
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
                filterOpen: document.getElementById('production-filter-open'),
                filterModal: document.getElementById('production-filter-modal'),
                filterOverlay: document.getElementById('production-filter-overlay'),
                filterClose: document.getElementById('production-filter-close'),
                filterApply: document.getElementById('production-filter-apply'),
                list: document.getElementById('production-batch-list'),
                meta: document.getElementById('production-batch-meta'),
                materials: document.getElementById('production-material-chip-list'),
                materialCount: document.getElementById('production-material-chip-count'),
                activeFilters: document.getElementById('production-filter-active'),
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
                createRecipePreview: document.getElementById('production-create-recipe-preview'),
                createDate: document.getElementById('production-create-date'),
                createOutput: document.getElementById('production-create-output'),
                createRecipeVersion: document.getElementById('production-create-recipe-version'),
                createPlanSummary: document.getElementById('production-create-plan-summary'),
                createReference: document.getElementById('production-create-reference'),
                createNotes: document.getElementById('production-create-notes'),
                createFeedback: document.getElementById('production-create-feedback'),
                createHelper: document.getElementById('production-create-helper'),
                createEligibilitySummary: document.getElementById('production-create-eligibility-summary'),
                createEligibilityCount: document.getElementById('production-create-eligibility-count'),
                createEligibilityList: document.getElementById('production-create-eligibility-list'),
                outputAdd: document.getElementById('production-output-add'),
                outputRows: document.getElementById('production-output-rows'),
                outputSummary: document.getElementById('production-output-summary'),
                createSubmit: document.getElementById('production-create-submit'),
                exportProblematic: document.getElementById('production-batch-export-problematic'),
            };

            const state = {
                bootstrap: { products: [], branches: [], locations: [], materials: [] },
                createBootstrap: {
                    status: 'idle',
                    branches: 'loading',
                    locations: 'loading',
                    products: 'loading',
                    error: '',
                },
                bootstrapRequest: null,
                batches: [],
                meta: {},
                recipePreview: {
                    status: 'no_product_selected',
                    can_post: false,
                    ingredients: [],
                    warning_message: '',
                    message: 'Pilih produk yang diproduksi untuk melihat resep.',
                },
                outputRows: [],
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
            const observabilityBaseUrl = @json($productionBatchObservabilityBaseUrl);

            const unwrap = (payload) => payload?.data || payload || {};
            const formatNumber = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(Number(value || 0));
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
            const normalizeFactorValue = (value) => {
                const numericValue = Number(value || 0);
                if (!Number.isFinite(numericValue)) {
                    return 0;
                }

                return Number(numericValue.toFixed(8));
            };
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

            const statusLabel = (status) => ({
                draft: 'Draf',
                posted: 'Diposting',
                cancelled: 'Dibatalkan',
            }[status] || 'Status');

            const filterLabelMap = {
                product_id: 'Produk',
                status: 'Status',
                cancel_reason: 'Alasan Batal',
                branch_id: 'Cabang',
                location_id: 'Lokasi',
                has_open_anomaly: 'Alert Terbuka',
                sort_by: 'Urutkan',
                transaction_date_from: 'Tanggal Dari',
                transaction_date_to: 'Tanggal Sampai',
            };

            const renderActiveFilters = () => {
                if (!el.activeFilters) return;

                const active = Object.entries(state.filters)
                    .filter(([, value]) => Boolean(value) && value !== 'newest' && value !== '100')
                    .map(([key, value]) => {
                        const label = filterLabelMap[key] || key;
                        let displayValue = value;

                        if (key === 'status') {
                            displayValue = statusLabel(value);
                        } else if (key === 'has_open_anomaly') {
                            displayValue = value === '1' ? 'Ada alert' : 'Tanpa alert';
                        } else if (key === 'sort_by') {
                            displayValue = ({ newest: 'Terbaru', latest_anomaly: 'Alert terbaru', largest_variance: 'Selisih terbesar', oldest: 'Terlama' }[value] || value);
                        }

                        return `<button type="button" data-clear-filter="${key}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-slate-100"><span class="text-slate-400">${label}</span><span class="text-slate-900 dark:text-slate-100">${displayValue}</span><span class="material-symbols-outlined text-[14px] text-slate-400">close</span></button>`;
                    });

                el.activeFilters.innerHTML = active.length
                    ? `<div class="flex w-full flex-wrap items-center gap-2"><span class="rounded-full bg-slate-100 px-3 py-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500 dark:bg-slate-800 dark:text-slate-300">Filter aktif ${active.length}</span><button type="button" data-reset-filters class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-slate-100">Reset semua</button>${active.join('')}</div>`
                    : '<div class="rounded-full border border-dashed border-slate-300 px-3 py-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400 dark:border-slate-700">Belum ada filter aktif</div>';
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
                    option.textContent = item.__label || item[labelKey] || '-';
                    option.disabled = item.__disabled === true;
                    select.appendChild(option);
                });

                select.disabled = false;
            };

            const setStatusOption = (select, message) => {
                if (!select) {
                    return;
                }

                select.innerHTML = '';
                const option = document.createElement('option');
                option.value = '';
                option.textContent = message;
                select.appendChild(option);
                select.disabled = true;
            };

            const normalizeRows = (value) => (Array.isArray(value) ? value : []);

            const setLoadingOptions = () => {
                setStatusOption(el.createBranch, 'Memuat cabang...');
                setStatusOption(el.createLocation, 'Memuat lokasi...');
                setStatusOption(el.createProduct, 'Memuat produk...');
            };

            const resolveDefaultLocationId = (locations, currentLocationId = '') => {
                if (currentLocationId && locations.some((location) => location.id === currentLocationId)) {
                    return currentLocationId;
                }

                const defaultLocation = locations.find((location) => location.is_default);
                return defaultLocation?.id || locations[0]?.id || '';
            };

            const markDefaultLocationOption = (select, locations) => {
                if (!select) {
                    return;
                }

                const defaultLocation = locations.find((location) => location.is_default);
                if (!defaultLocation) {
                    return;
                }

                Array.from(select.options).forEach((option) => {
                    if (!option.value) {
                        return;
                    }

                    const location = locations.find((row) => row.id === option.value);
                    if (location) {
                        option.textContent = location.is_default ? `${location.name} (default)` : location.name;
                    }
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

            const setCreateHelper = (message, href = '', linkLabel = 'Buka daftar produk') => {
                if (!el.createHelper) {
                    return;
                }

                if (!message) {
                    el.createHelper.classList.add('hidden');
                    el.createHelper.innerHTML = '';
                    return;
                }

                const linkHtml = href
                    ? ` <a class="font-semibold text-primary underline underline-offset-2" href="${href}">${linkLabel}</a>`
                    : '';

                el.createHelper.classList.remove('hidden');
                el.createHelper.innerHTML = `${message}${linkHtml}`;
            };

            const previewCopy = {
                noProduct: 'Pilih produk yang akan diproduksi untuk melihat kebutuhan bahan.',
                noLocation: 'Pilih lokasi dulu agar pratinjau stok mengikuti gudang yang benar.',
                loading: 'Menghitung kebutuhan bahan...',
                emptyBom: 'Resep aktif belum tersedia untuk produk ini. Lengkapi resep sebelum membuat produksi.',
                shortage: 'Bahan belum cukup untuk menyelesaikan produksi. Rencana masih bisa disimpan, tapi posting akan ditolak sampai stok cukup.',
                technical: 'Pratinjau resep gagal dimuat. Coba muat ulang.',
                stale: 'Stok berubah setelah pratinjau. Muat ulang cek bahan sebelum menyelesaikan produksi.',
                validation: 'Data preview belum valid. Periksa input cabang, lokasi, dan produk.',
            };

            const previewNoticeClass = (tone) => ({
                info: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200',
                warn: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200',
                danger: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200',
                success: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200',
            }[tone] || 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-200');

            const renderPreviewNotice = (message, tone = 'info', title = '') => `
                <div class="rounded-2xl border px-3 py-3 text-sm ${previewNoticeClass(tone)}">
                    ${title ? `<p class="text-[10px] font-bold uppercase tracking-[0.18em] opacity-70">${title}</p>` : ''}
                    <p class="${title ? 'mt-1' : ''} font-medium leading-relaxed">${message}</p>
                </div>
            `;

            const getPreviewMessage = () => state.recipePreview?.message || '';

            const setPreviewState = (nextState) => {
                state.recipePreview = {
                    status: 'technical_error',
                    can_post: false,
                    ingredients: [],
                    recipe_count: 0,
                    recipe_output_qty: 0,
                    expected_output_qty: 0,
                    warning_message: '',
                    message: previewCopy.technical,
                    ...nextState,
                };
                updateSubmitState();
                renderCreateRecipePreview();
            };

            const getCreateProductDraft = () => {
                const productId = String(el.createProduct?.value || '');
                return (state.bootstrap.products || []).find((product) => String(product.id || '') === productId) || null;
            };

            const productRecipeOutputQty = (product) => normalizeQtyValue(
                product?.recipe_output_qty
                ?? product?.production_batch_eligibility?.recipe_output_qty
                ?? product?.production_batch_eligibility?.summary?.recipe_output_qty
                ?? 0
            );

            const syncCreateExpectedOutput = () => {
                const product = getCreateProductDraft();
                const recipeCount = Math.max(0, Number(el.createRecipeVersion?.value || 0));
                const recipeOutputQty = productRecipeOutputQty(product);
                const expectedOutputQty = recipeCount > 0 && recipeOutputQty > 0
                    ? normalizeQtyValue(recipeCount * recipeOutputQty)
                    : 0;

                el.createOutput.value = expectedOutputQty > 0 ? String(expectedOutputQty) : '';
                renderCreatePlanSummary(product, recipeCount, recipeOutputQty, expectedOutputQty);

                return {
                    recipe_count: recipeCount,
                    recipe_output_qty: recipeOutputQty,
                    expected_output_qty: expectedOutputQty,
                };
            };

            const renderCreatePlanSummary = (product, recipeCount, recipeOutputQty, expectedOutputQty) => {
                if (!el.createPlanSummary) {
                    return;
                }

                if (!product) {
                    el.createPlanSummary.className = 'rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600';
                    el.createPlanSummary.innerHTML = `
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Rencana Produksi</p>
                        <p class="mt-1 font-semibold">Pilih produk dan isi jumlah pembuatan resep untuk melihat perkiraan hasil.</p>
                    `;
                    return;
                }

                const validPlan = recipeCount > 0 && recipeOutputQty > 0 && expectedOutputQty > 0;
                el.createPlanSummary.className = `rounded-2xl border px-4 py-3 text-sm ${validPlan ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900'}`;
                el.createPlanSummary.innerHTML = validPlan
                    ? `
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-600">Rencana Produksi</p>
                        <p class="mt-1 font-semibold">${formatNumber(recipeCount)} kali resep ${product.name} menghasilkan sekitar ${formatNumber(expectedOutputQty)} ${product.unit || 'unit'}.</p>
                    `
                    : `
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-amber-600">Rencana Belum Lengkap</p>
                        <p class="mt-1 font-semibold">Produk ini belum punya hasil standar per resep. Lengkapi master produk lebih dulu.</p>
                    `;
            };

            const previewIngredientLine = (item, preview = {}) => {
                const recipeCount = Number(preview.recipe_count || el.createRecipeVersion?.value || 0);
                return `Per resep ${formatNumber(item.recipe_qty)} ${item.unit || ''} - untuk ${formatNumber(recipeCount)} kali resep butuh ${formatNumber(item.required_qty)} ${item.unit || ''}`;
            };

            const toggleCreateModal = (open) => {
                el.createModal.classList.toggle('hidden', !open);
                if (open) {
                    loadBootstrap().catch((error) => {
                        setCreateHelper(error.message || 'Bootstrap batch gagal dimuat. Muat ulang halaman untuk mencoba lagi.', false);
                        setStatusOption(el.createBranch, 'Gagal memuat cabang');
                        setStatusOption(el.createLocation, 'Gagal memuat lokasi');
                        setStatusOption(el.createProduct, 'Gagal memuat produk');
                    });
                }
                if (!open) {
                    setCreateFeedback('');
                }
            };

            const flattenLocationsFromBranches = (branches) => {
                return (branches || []).flatMap((branch) => {
                    const branchLocations = Array.isArray(branch?.locations) ? branch.locations : [];

                    return branchLocations.map((location) => ({
                        ...location,
                        branch_id: branch.id,
                        branch_name: branch.name,
                    }));
                });
            };

            const toggleFilterModal = (open) => {
                el.filterModal?.classList.toggle('hidden', !open);
            };

            const batchProductOptions = () => {
                const products = state.bootstrap.products || [];
                return products
                    .filter((product) => {
                        const eligibility = window.PosUi.getProductionBatchEligibility(product);
                        const summary = eligibility?.summary || {};
                        const bomItems = Array.isArray(product.bom_items) ? product.bom_items : [];
                        const bomLineCount = bomItems.length || Number(summary.bom_line_count ?? summary.bom_count ?? 0);
                        const recipeOutputQty = Number(
                            product.recipe_output_qty
                            ?? summary.recipe_output_qty
                            ?? product.production_batch_eligibility?.summary?.recipe_output_qty
                            ?? 0
                        );

                        return window.PosUi.isProductionBatchEligibleProduct(product)
                            && bomLineCount > 0
                            && recipeOutputQty > 0;
                    })
                    .map((product) => ({
                        ...product,
                        __disabled: false,
                        __label: product.name,
                    }));
            };

            const finishedOutputProductOptions = () => {
                const recipeProductId = String(el.createProduct?.value || '');

                return (state.bootstrap.products || [])
                    .filter((product) => {
                        const itemType = String(product.item_type || '').trim();
                        return itemType === 'finished_good'
                            && product.is_active !== false
                            && product.track_stock !== false;
                    })
                    .map((product) => ({
                        ...product,
                        __is_recipe_product: recipeProductId !== '' && String(product.id || '') === recipeProductId,
                        __label: product.name,
                    }))
                    .sort((a, b) => Number(a.__is_recipe_product) - Number(b.__is_recipe_product));
            };

            const outputRowId = () => `output-${Date.now()}-${Math.random().toString(16).slice(2)}`;

            const ensureDefaultOutputRows = () => {
                if (state.outputRows.length) {
                    return;
                }

                const recipePlan = syncCreateExpectedOutput();
                const defaultFactor = recipePlan.expected_output_qty > 0 && recipePlan.recipe_count > 0
                    ? normalizeQtyValue(recipePlan.recipe_count / recipePlan.expected_output_qty)
                    : 1;
                const recipeProductId = String(el.createProduct?.value || '');
                state.outputRows = [{
                    id: outputRowId(),
                    product_id: recipeProductId,
                    planned_qty: recipePlan.expected_output_qty || 1,
                    recipe_factor: defaultFactor,
                }];
            };

            const syncDefaultOutputRowFromPlan = () => {
                const recipeProductId = String(el.createProduct?.value || '');
                if (!recipeProductId || state.outputRows.length !== 1) {
                    return;
                }

                const currentRow = state.outputRows[0];
                if (String(currentRow.product_id || '') !== recipeProductId) {
                    return;
                }

                const recipePlan = syncCreateExpectedOutput();
                const defaultFactor = recipePlan.expected_output_qty > 0 && recipePlan.recipe_count > 0
                    ? normalizeQtyValue(recipePlan.recipe_count / recipePlan.expected_output_qty)
                    : 1;

                state.outputRows = [{
                    ...currentRow,
                    planned_qty: recipePlan.expected_output_qty || currentRow.planned_qty,
                    recipe_factor: defaultFactor,
                }];
            };

            const outputPlanSummary = () => {
                const recipeCount = Number(el.createRecipeVersion?.value || 0);
                const usage = state.outputRows.reduce((sum, row) => {
                    return sum + (Number(row.planned_qty || 0) * Number(row.recipe_factor || 0));
                }, 0);
                const remaining = recipeCount - usage;

                return { recipeCount, usage, remaining };
            };

            const duplicateOutputProductIds = () => {
                const seen = new Set();
                const duplicates = new Set();

                state.outputRows.forEach((row) => {
                    const productId = String(row.product_id || '');
                    if (!productId) {
                        return;
                    }

                    if (seen.has(productId)) {
                        duplicates.add(productId);
                        return;
                    }

                    seen.add(productId);
                });

                return duplicates;
            };

            const outputProductDraft = (productId) => (state.bootstrap.products || [])
                .find((product) => String(product.id || '') === String(productId || '')) || null;

            const outputRecipeFactorForRow = (row) => {
                const recipeProduct = getCreateProductDraft();
                const outputProduct = outputProductDraft(row.product_id);
                const standardOutputQty = productRecipeOutputQty(outputProduct) || productRecipeOutputQty(recipeProduct);

                return standardOutputQty > 0 ? normalizeFactorValue(1 / standardOutputQty) : 0;
            };

            const outputRecipeUsageForRow = (row) => {
                const recipeProduct = getCreateProductDraft();
                const outputProduct = outputProductDraft(row.product_id);
                const standardOutputQty = productRecipeOutputQty(outputProduct) || productRecipeOutputQty(recipeProduct);

                return standardOutputQty > 0
                    ? normalizeQtyValue(Number(row.planned_qty || 0) / standardOutputQty)
                    : normalizeQtyValue(Number(row.planned_qty || 0) * Number(row.recipe_factor || 0));
            };

            const autoAllocateOutputRecipeFactors = (syncDom = false) => {
                state.outputRows = state.outputRows.map((row) => ({
                    ...row,
                    recipe_factor: outputRecipeFactorForRow(row) || row.recipe_factor,
                }));

                if (syncDom) {
                    el.outputRows?.querySelectorAll('[data-output-row]').forEach((node, index) => {
                        const factorInput = node.querySelector('[data-output-field="recipe_factor"]');
                        if (factorInput && state.outputRows[index]) {
                            factorInput.value = state.outputRows[index].recipe_factor || '';
                        }

                        const usageInput = node.querySelector('[data-output-field="recipe_usage_display"]');
                        if (usageInput && state.outputRows[index]) {
                            usageInput.value = outputRecipeUsageForRow(state.outputRows[index]) || '';
                        }
                    });
                }

                updateOutputSummary();
            };

            const updateOutputSummary = () => {
                if (!el.outputSummary) {
                    return;
                }

                const summary = outputPlanSummary();
                const over = summary.remaining < -0.0001;
                const hasDuplicate = duplicateOutputProductIds().size > 0;
                el.outputSummary.className = `mt-3 rounded-2xl px-3 py-3 text-xs font-semibold ${over || hasDuplicate ? 'bg-rose-50 text-rose-700 ring-1 ring-rose-200' : 'bg-slate-50 text-slate-600'}`;
                el.outputSummary.textContent = hasDuplicate
                    ? 'Produk hasil duplikat. Gabungkan qty pada satu baris atau pilih produk jadi yang berbeda.'
                    : `Resep dibuat ${formatNumber(summary.recipeCount)} - dipakai output ${formatNumber(summary.usage)} - sisa ${formatNumber(Math.max(0, summary.remaining))}`;
            };

            const renderOutputRows = () => {
                if (!el.outputRows) {
                    return;
                }

                ensureDefaultOutputRows();
                const options = finishedOutputProductOptions();
                const optionHtml = (selectedId) => options.map((product) => {
                    const selected = String(product.id) === String(selectedId) ? 'selected' : '';
                    return `<option value="${product.id}" ${selected}>${product.__label || product.name}</option>`;
                }).join('');

                el.outputRows.innerHTML = state.outputRows.map((row, index) => `
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3" data-output-row="${row.id}">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Hasil ${index + 1}</p>
                            <button class="inline-flex size-8 items-center justify-center rounded-full bg-white text-slate-500 ring-1 ring-slate-200" type="button" data-output-remove="${row.id}" title="Hapus hasil" ${state.outputRows.length <= 1 ? 'disabled' : ''}>
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </button>
                        </div>
                        <label class="mt-2 block">
                            <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Produk Jadi</span>
                            <select class="production-filter-block w-full rounded-2xl px-3 py-2.5 text-sm font-medium text-slate-800" data-output-field="product_id">
                                <option value="">Pilih produk jadi</option>
                                ${optionHtml(row.product_id)}
                            </select>
                        </label>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <label class="block">
                                <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Qty</span>
                                <input class="production-filter-block w-full rounded-2xl px-3 py-2.5 text-sm font-medium text-slate-800" min="0.0001" step="0.0001" type="number" value="${row.planned_qty || ''}" data-output-field="planned_qty" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Porsi Resep</span>
                                <input type="hidden" value="${row.recipe_factor || ''}" data-output-field="recipe_factor" />
                                <input class="production-filter-block w-full rounded-2xl px-3 py-2.5 text-sm font-medium text-slate-500" min="0.0001" step="0.0001" type="number" value="${outputRecipeUsageForRow(row) || ''}" data-output-field="recipe_usage_display" readonly />
                            </label>
                        </div>
                    </div>
                `).join('');

                updateOutputSummary();
                updateSubmitState();
            };

            const collectOutputRows = () => {
                if (!el.outputRows) {
                    return [];
                }

                const rows = [];
                el.outputRows.querySelectorAll('[data-output-row]').forEach((node) => {
                    const id = node.dataset.outputRow;
                    const productId = node.querySelector('[data-output-field="product_id"]')?.value || '';
                    const plannedQty = Number(node.querySelector('[data-output-field="planned_qty"]')?.value || 0);
                    const recipeFactor = Number(node.querySelector('[data-output-field="recipe_factor"]')?.value || 0);
                    rows.push({ id, product_id: productId, planned_qty: plannedQty, recipe_factor: recipeFactor });
                });

                state.outputRows = rows;
                updateOutputSummary();

                return rows;
            };

            const outputRowsAreValid = () => {
                const rows = collectOutputRows();
                const summary = outputPlanSummary();
                const seen = new Set();

                if (!rows.length || summary.recipeCount <= 0 || summary.remaining < -0.0001) {
                    return false;
                }

                return rows.every((row) => {
                    if (!row.product_id || !(row.planned_qty > 0) || !(row.recipe_factor > 0) || seen.has(row.product_id)) {
                        return false;
                    }

                    seen.add(row.product_id);
                    return true;
                });
            };

            const batchEligibilityDisplayCandidates = () => {
                const products = state.bootstrap.products || [];

                return products.filter((product) => {
                    if (window.PosUi.isProductionBatchEligibleProduct(product)) {
                        return true;
                    }

                    const itemType = String(product.item_type || '').trim();
                    const productMode = String(product.product_mode || '').trim();
                    const productionMode = String(product.production_mode || '').trim();

                    return itemType === 'finished_good'
                        || productMode === 'bom'
                        || productionMode === 'batch_production';
                });
            };

            const renderCreateEligibilitySummary = () => {
                if (!el.createEligibilitySummary || !el.createEligibilityCount || !el.createEligibilityList) {
                    return;
                }

                const products = batchEligibilityDisplayCandidates();
                const ineligibleProducts = products.filter((product) => !window.PosUi.isProductionBatchEligibleProduct(product));

                if (!ineligibleProducts.length) {
                    el.createEligibilitySummary.classList.add('hidden');
                    el.createEligibilityCount.textContent = '';
                    el.createEligibilityList.innerHTML = '';
                    return;
                }

                el.createEligibilitySummary.classList.remove('hidden');
                el.createEligibilityCount.textContent = `${ineligibleProducts.length} produk`;

                el.createEligibilityList.innerHTML = ineligibleProducts.slice(0, 4).map((product) => {
                    const eligibility = window.PosUi.getProductionBatchEligibility(product);
                    const reason = Array.isArray(eligibility.reasons) && eligibility.reasons.length
                        ? eligibility.reasons[0]
                        : 'Lengkapi checklist produk agar bisa masuk batch produksi.';
                    const editUrl = productionBatchProductsUrl
                        ? `${productionBatchProductsUrl}/${encodeURIComponent(product.id)}/edit?from=${encodeURIComponent(currentPath)}`
                        : '';
                    const actionHtml = editUrl
                        ? `<a href="${editUrl}" class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-amber-700 transition hover:bg-amber-200">Perbaiki</a>`
                        : '<span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-amber-700">Periksa</span>';

                    return `
                        <div class="rounded-2xl bg-white px-3 py-3 ring-1 ring-amber-200">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold text-amber-950">${product.name}</p>
                                    <p class="mt-1 text-[11px] text-amber-800">${reason}</p>
                                </div>
                                ${actionHtml}
                            </div>
                        </div>
                    `;
                }).join('');

                if (ineligibleProducts.length > 4) {
                    el.createEligibilityList.insertAdjacentHTML('beforeend', `
                        <p class="text-[11px] text-amber-700">Dan ${ineligibleProducts.length - 4} produk lain belum siap.</p>
                    `);
                }
            };

            const materialNameById = (id) => {
                const material = (state.bootstrap.materials || []).find((row) => String(row.product_id || row.id) === String(id));
                return material?.name || 'Bahan baku';
            };

            const canCreateDraftFromPreview = () => {
                const status = state.recipePreview?.status || '';
                const ingredients = Array.isArray(state.recipePreview?.ingredients) ? state.recipePreview.ingredients : [];

                return ['preview_valid', 'stock_shortage'].includes(status)
                    && ingredients.length > 0;
            };

            const updateSubmitState = () => {
                const canPost = canCreateDraftFromPreview()
                    && outputRowsAreValid();
                el.createSubmit.disabled = !canPost;
                el.createSubmit.classList.toggle('opacity-60', !canPost);
                el.createSubmit.classList.toggle('cursor-not-allowed', !canPost);
                el.createSubmit.title = canPost ? '' : (getPreviewMessage() || 'Lengkapi produk dan lokasi terlebih dahulu.');
            };

            const renderMaterialChips = () => {
                const materials = state.bootstrap.materials || [];
                if (el.materialCount) {
                    el.materialCount.textContent = `${materials.length} item`;
                }

                if (el.materials) {
                    el.materials.className = 'mt-4 flex gap-3 overflow-x-auto pb-1 no-scrollbar';
                }

                if (!materials.length) {
                    el.materials.innerHTML = '<div class="w-full rounded-2xl border border-dashed border-slate-300 bg-[#f8faf9] px-4 py-6 text-center text-sm text-slate-500">Belum ada bahan baku aktif.</div>';
                    return;
                }

                const materialsUrl = @json($productionBatchMaterialsUrl);
                el.materials.innerHTML = materials.slice(0, 8).map((material) => {
                    const label = material.name || 'Bahan baku';
                    const unit = material.unit || 'unit';
                    const availableQty = Number(material.available_qty ?? material.stock_qty ?? material.quantity ?? material.qty ?? 0);
                    const badgeText = String(material.stock_status || material.status || (availableQty <= 0 ? 'Out' : availableQty < 100 ? 'Low' : 'High')).toUpperCase();
                    const badgeClass = badgeText === 'OUT'
                        ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-200'
                        : badgeText === 'LOW'
                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-200'
                            : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-200';
                    const availabilityText = Number.isFinite(availableQty) && availableQty > 0
                        ? `${formatNumber(availableQty)} ${unit} tersedia`
                        : `Stok ${unit} aktif`;
                    return `
                        <a href="${materialsUrl}" class="production-card block min-w-[238px] rounded-2xl p-3 transition hover:brightness-[0.99]">
                            <div class="flex items-start gap-3">
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-teal-50 text-teal-700">
                                    <span class="material-symbols-outlined text-[24px]">science</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <h4 class="truncate text-[15px] font-bold text-slate-900">${label}</h4>
                                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] ${badgeClass}">${badgeText}</span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-500">${availabilityText}</p>
                                </div>
                            </div>
                        </a>`;
                }).join('');
            };

            const loadCreateRecipePreview = async () => {
                const productId = el.createProduct.value || '';
                const recipePlan = syncCreateExpectedOutput();
                const qty = Number(el.createRecipeVersion.value || 0);
                const locationId = el.createLocation.value || '';

                if (!productId) {
                    setPreviewState({
                        status: 'no_product_selected',
                        can_post: false,
                        ingredients: [],
                        warning_message: '',
                        message: previewCopy.noProduct,
                    });
                    setCreateFeedback('');
                    return;
                }

                if (!locationId) {
                    setPreviewState({
                        status: 'no_location_selected',
                        can_post: false,
                        ingredients: [],
                        warning_message: '',
                        message: previewCopy.noLocation,
                    });
                    setCreateFeedback('');
                    return;
                }

                state.recipePreview = {
                    status: 'loading',
                    can_post: false,
                    ingredients: [],
                    recipe_count: recipePlan.recipe_count,
                    recipe_output_qty: recipePlan.recipe_output_qty,
                    expected_output_qty: recipePlan.expected_output_qty,
                    warning_message: '',
                    message: previewCopy.loading,
                };
                updateSubmitState();
                el.createRecipePreview.innerHTML = previewCopy.loading;

                try {
                    const previewUrl = new URL('/api/v1/production/recipe-preview', window.location.origin);
                    previewUrl.searchParams.set('product_id', productId);
                    previewUrl.searchParams.set('recipe_count', String(qty || 1));
                    previewUrl.searchParams.set('location_id', locationId);

                    const response = await fetch(previewUrl.toString(), { headers });
                    const payload = unwrap(await response.json().catch(() => ({})));

                    if (!response.ok) {
                        const details = payload?.details || {};
                        const locationMessage = Array.isArray(details.location_id) ? details.location_id.join(' ') : '';
                        const productMessage = Array.isArray(details.product_id) ? details.product_id.join(' ') : '';
                        const message = locationMessage || productMessage || payload?.message || previewCopy.validation;

                        if (response.status === 422 && locationMessage) {
                            setPreviewState({
                                status: 'no_location_selected',
                                can_post: false,
                                ingredients: [],
                                warning_message: '',
                                message: locationMessage,
                            });
                            setCreateFeedback(locationMessage, true);
                            return;
                        }

                        setPreviewState({
                            status: 'validation_error',
                            can_post: false,
                            ingredients: [],
                            warning_message: '',
                            message,
                        });
                        setCreateFeedback(message, true);
                        return;
                    }

                    const ingredients = Array.isArray(payload?.ingredients) ? payload.ingredients : [];
                    const bomItems = Array.isArray(payload?.bom_items) ? payload.bom_items : [];
                    const shortageDetected = ingredients.some((item) => Boolean(item.shortage_flag));
                    const previewStatus = shortageDetected ? 'stock_shortage' : (bomItems.length === 0 ? 'empty_bom' : 'preview_valid');

                    state.recipePreview = {
                        status: previewStatus,
                        can_post: payload?.can_post === true,
                        ingredients,
                        recipe_count: Number(payload?.recipe_count ?? recipePlan.recipe_count ?? qty ?? 0),
                        recipe_output_qty: Number(payload?.recipe_output_qty ?? recipePlan.recipe_output_qty ?? 0),
                        expected_output_qty: Number(payload?.expected_output_qty ?? recipePlan.expected_output_qty ?? 0),
                        warning_message: payload?.warning_message || '',
                        message: payload?.warning_message || (previewStatus === 'empty_bom' ? previewCopy.emptyBom : ''),
                    };
                    updateSubmitState();
                    setCreateFeedback(payload?.warning_message || '', Boolean(payload?.warning_message));

                    if (!ingredients.length) {
                        const emptyMessage = bomItems.length === 0 ? previewCopy.emptyBom : previewCopy.noProduct;
                        el.createRecipePreview.innerHTML = renderPreviewNotice(emptyMessage, 'warn', 'Resep Belum Ada');
                        return;
                    }

                    const rows = ingredients.map((item) => {
                        const stockClass = item.shortage_flag ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700';
                        const stockLabel = item.shortage_flag ? `Kurang ${formatNumber(item.shortage_qty)} ${item.unit || ''}`.trim() : 'Stok cukup';
                        return `
                            <li class="flex items-start justify-between gap-3 rounded-2xl bg-white px-3 py-2.5 ring-1 ring-slate-200 dark:bg-slate-950 dark:ring-slate-800">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">${item.material_name}</p>
                                    <p class="mt-1 text-[11px] text-slate-500">${previewIngredientLine(item, state.recipePreview)}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="inline-flex rounded-full px-2 py-1 text-[10px] font-bold ${stockClass}">${stockLabel}</span>
                                    <p class="mt-1 text-[11px] text-slate-500">Tersedia ${formatNumber(item.available_qty)} ${item.unit || ''}</p>
                                </div>
                            </li>`;
                    }).join('');

                    el.createRecipePreview.innerHTML = `
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-200">
                                <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                Bahan cukup
                            </div>
                            <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-outline">Perkiraan hasil ${formatNumber(state.recipePreview.expected_output_qty || 0)}</span>
                        </div>
                        <div class="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-slate-400">
                            <span class="material-symbols-outlined text-[16px]">recipe</span>
                            Bahan yang dipakai
                        </div>
                        <ul class="space-y-2">${rows}</ul>
                    `;
                    renderCreateRecipePreview();
                } catch (error) {
                    state.recipePreview = {
                        status: 'technical_error',
                        can_post: false,
                        ingredients: [] ,
                        warning_message: '',
                        message: error.message || previewCopy.technical,
                    };
                    updateSubmitState();
                    setCreateFeedback(error.message || previewCopy.technical, true);
                    el.createRecipePreview.innerHTML = renderPreviewNotice(error.message || previewCopy.technical, 'danger', 'Gagal Memuat');
                    renderCreateRecipePreview();
                }
            };

            const renderCreateRecipePreview = () => {
                const preview = state.recipePreview || {};

                if (preview.status === 'loading') {
                    el.createRecipePreview.innerHTML = renderPreviewNotice(previewCopy.loading, 'info', 'Memuat Pratinjau');
                    return;
                }

                if (preview.status === 'no_product_selected') {
                    el.createRecipePreview.innerHTML = renderPreviewNotice(previewCopy.noProduct, 'info', 'Pilih Produk');
                    return;
                }

                if (preview.status === 'no_location_selected') {
                    el.createRecipePreview.innerHTML = renderPreviewNotice(previewCopy.noLocation, 'info', 'Pilih Lokasi');
                    return;
                }

                if (preview.status === 'validation_error') {
                    el.createRecipePreview.innerHTML = renderPreviewNotice(preview.message || previewCopy.validation, 'danger', 'Data Tidak Valid');
                    return;
                }

                if (preview.status === 'stock_shortage') {
                    const ingredients = Array.isArray(preview.ingredients) ? preview.ingredients : [];
                    const rows = ingredients.map((item) => {
                        const stockClass = item.shortage_flag ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700';
                        const stockLabel = item.shortage_flag ? `Kurang ${formatNumber(item.shortage_qty)} ${item.unit || ''}`.trim() : 'Stok cukup';
                        return `
                            <li class="flex items-start justify-between gap-3 rounded-2xl bg-white px-3 py-2.5 ring-1 ring-slate-200 dark:bg-slate-950 dark:ring-slate-800">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">${item.material_name}</p>
                                    <p class="mt-1 text-[11px] text-slate-500">${previewIngredientLine(item, preview)}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="inline-flex rounded-full px-2 py-1 text-[10px] font-bold ${stockClass}">${stockLabel}</span>
                                    <p class="mt-1 text-[11px] text-slate-500">Tersedia ${formatNumber(item.available_qty)} ${item.unit || ''}</p>
                                </div>
                            </li>`;
                    }).join('');

                    el.createRecipePreview.innerHTML = `
                        <div class="mb-3 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                            ${preview.message || previewCopy.shortage}
                        </div>
                        <div class="mb-2 flex items-center justify-between gap-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">
                            <span>${ingredients.length} bahan - perkiraan hasil ${formatNumber(preview.expected_output_qty || 0)}</span>
                            <span>Draft bisa disimpan</span>
                        </div>
                        <div class="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-slate-400">
                            <span class="material-symbols-outlined text-[16px]">recipe</span>
                            Bahan yang dipakai
                        </div>
                        <ul class="space-y-2">${rows}</ul>
                    `;
                    return;
                }

                if (preview.status === 'preview_valid') {
                    const ingredients = Array.isArray(preview.ingredients) ? preview.ingredients : [];
                    const rows = ingredients.map((item) => {
                        const stockClass = item.shortage_flag ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700';
                        const stockLabel = item.shortage_flag ? `Kurang ${formatNumber(item.shortage_qty)} ${item.unit || ''}`.trim() : 'Stok cukup';
                        return `
                            <li class="flex items-start justify-between gap-3 rounded-2xl bg-white px-3 py-2.5 ring-1 ring-slate-200 dark:bg-slate-950 dark:ring-slate-800">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">${item.material_name}</p>
                                    <p class="mt-1 text-[11px] text-slate-500">${previewIngredientLine(item, preview)}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="inline-flex rounded-full px-2 py-1 text-[10px] font-bold ${stockClass}">${stockLabel}</span>
                                    <p class="mt-1 text-[11px] text-slate-500">Tersedia ${formatNumber(item.available_qty)} ${item.unit || ''}</p>
                                </div>
                            </li>`;
                    }).join('');

                    el.createRecipePreview.innerHTML = `
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-200">
                                <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                Bahan cukup
                            </div>
                            <span class="text-[10px] font-bold uppercase tracking-[0.18em] text-outline">${formatNumber(preview.recipe_count || 0)} kali resep - hasil ${formatNumber(preview.expected_output_qty || 0)}</span>
                        </div>
                        ${preview.message ? `<div class="mb-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">${preview.message}</div>` : ''}
                        <div class="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-slate-400">
                            <span class="material-symbols-outlined text-[16px]">recipe</span>
                            Bahan yang dipakai
                        </div>
                        <ul class="space-y-2">${rows}</ul>
                    `;
                    return;
                }

                if (preview.status === 'stale_preview') {
                    el.createRecipePreview.innerHTML = renderPreviewNotice(preview.message || previewCopy.stale, 'warn', 'Pratinjau Kedaluwarsa');
                    return;
                }

                if (preview.status === 'technical_error') {
                    el.createRecipePreview.innerHTML = renderPreviewNotice(preview.message || previewCopy.technical, 'danger', 'Gagal Memuat');
                    return;
                }

                const ingredients = Array.isArray(preview.ingredients) ? preview.ingredients : [];

                if (!ingredients.length) {
                    el.createRecipePreview.innerHTML = renderPreviewNotice(preview.message || previewCopy.emptyBom, 'warn', 'Resep Belum Ada');
                    return;
                }

                    const rows = ingredients.map((item) => {
                        const stockClass = item.shortage_flag ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700';
                        const stockLabel = item.shortage_flag ? `Kurang ${formatNumber(item.shortage_qty)} ${item.unit || ''}`.trim() : 'Stok cukup';
                        return `
                            <li class="flex items-start justify-between gap-3 rounded-2xl bg-white px-3 py-2.5 ring-1 ring-slate-200 dark:bg-slate-950 dark:ring-slate-800">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">${item.material_name}</p>
                                    <p class="mt-1 text-[11px] text-slate-500">${previewIngredientLine(item, preview)}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="inline-flex rounded-full px-2 py-1 text-[10px] font-bold ${stockClass}">${stockLabel}</span>
                                    <p class="mt-1 text-[11px] text-slate-500">Tersedia ${formatNumber(item.available_qty)} ${item.unit || ''}</p>
                                </div>
                            </li>`;
                    }).join('');

                    el.createRecipePreview.innerHTML = `
                        <div class="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-slate-400">
                            <span class="material-symbols-outlined text-[16px]">recipe</span>
                            Bahan yang dipakai
                        </div>
                        <ul class="space-y-2">${rows}</ul>
                    `;
            };

            const syncCreateLocations = () => {
                const branchId = el.createBranch.value || '';
                const allLocations = state.bootstrap.locations || [];

                if (!allLocations.length) {
                    const locationStatus = state.createBootstrap.locations;
                    const message = locationStatus === 'error'
                        ? 'Gagal memuat lokasi'
                        : (branchId ? 'Belum ada lokasi untuk cabang ini' : 'Belum ada lokasi aktif');
                    setStatusOption(el.createLocation, message);
                    return;
                }

                const locations = allLocations.filter((location) => !branchId || location.branch_id === branchId);

                if (!locations.length) {
                    setStatusOption(el.createLocation, branchId ? 'Belum ada lokasi untuk cabang ini' : 'Belum ada lokasi aktif');
                    return;
                }

                setOptions(el.createLocation, locations, 'name', 'Pilih Lokasi');
                markDefaultLocationOption(el.createLocation, locations);
                const defaultLocationId = resolveDefaultLocationId(locations, el.createLocation.dataset.selected || '');

                el.createLocation.value = defaultLocationId;
            };

            const initCreateForm = () => {
                const branches = state.bootstrap.branches || [];
                const locations = state.bootstrap.locations || [];
                const eligibleProducts = batchProductOptions();

                if (!branches.length) {
                    const branchMessage = state.createBootstrap.branches === 'error'
                        ? 'Gagal memuat cabang'
                        : 'Belum ada cabang aktif';
                    setStatusOption(el.createBranch, branchMessage);
                } else {
                    setOptions(el.createBranch, branches, 'name', 'Pilih Cabang');
                }

                if (!eligibleProducts.length) {
                    const productMessage = state.createBootstrap.products === 'error'
                        ? 'Gagal memuat produk'
                        : 'Belum ada produk siap produksi';
                    setStatusOption(el.createProduct, productMessage);
                } else {
                    setOptions(el.createProduct, eligibleProducts, 'name', 'Pilih Produk yang Diproduksi');
                }

                el.createDate.value = new Date().toISOString().slice(0, 10);
                el.createRecipeVersion.value = el.createRecipeVersion.value || '1';
                state.outputRows = [];
                el.createBranch.dataset.selected = state.filters.branch_id || '';
                el.createLocation.dataset.selected = state.filters.location_id || '';
                if (el.createBranch.dataset.selected) {
                    el.createBranch.value = el.createBranch.dataset.selected;
                }
                syncCreateLocations();
                syncCreateExpectedOutput();
                renderOutputRows();

                if (!branches.length || !locations.length) {
                    setCreateFeedback('', false);
                    setCreateHelper(
                        'Data cabang atau lokasi belum tersedia. Buat cabang dan lokasi dulu agar batch bisa dipilih.',
                        productionBatchBranchManagementUrl,
                        'Kelola cabang & lokasi'
                    );
                } else if (!eligibleProducts.length) {
                    setCreateFeedback('Belum ada produk siap produksi di tenant ini.', false);
                    setCreateHelper(
                        'Perbaiki master produk agar muncul di dropdown batch.',
                        productionBatchProductsUrl,
                        'Buka daftar produk'
                    );
                } else if (state.createBootstrap.error) {
                    setCreateFeedback('', false);
                    setCreateHelper(state.createBootstrap.error);
                } else {
                    setCreateFeedback('');
                    setCreateHelper('');
                }

                if (el.createProduct.value && el.createLocation.value) {
                    loadCreateRecipePreview();
                } else if (el.createProduct.value) {
                    setPreviewState({
                        status: 'no_location_selected',
                        can_post: false,
                        ingredients: [],
                        warning_message: '',
                        message: previewCopy.noLocation,
                    });
                } else {
                    setPreviewState({
                        status: 'no_product_selected',
                        can_post: false,
                        ingredients: [],
                        warning_message: '',
                        message: previewCopy.noProduct,
                    });
                }
            };

            const syncFilterInputs = () => {
                el.product.value = state.filters.product_id;
                el.status.value = state.filters.status;
                el.cancelReason.value = state.filters.cancel_reason;
                el.branch.value = state.filters.branch_id;
                el.location.value = state.filters.location_id;
                if (el.anomaly) {
                    el.anomaly.value = state.filters.has_open_anomaly;
                }
                el.sort.value = state.filters.sort_by;
                el.dateFrom.value = state.filters.transaction_date_from;
                el.dateTo.value = state.filters.transaction_date_to;
                renderActiveFilters();
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
                if (el.exportProblematic) {
                    el.exportProblematic.href = `/api/v1/production-batches/export/problematic${exportQuery.toString() ? `?${exportQuery.toString()}` : ''}`;
                }
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

            const resetFilters = () => {
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
                renderActiveFilters();
                loadBatches().catch((error) => {
                    el.list.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                });
            };

            const clearFilter = (key) => {
                if (!(key in state.filters)) {
                    return;
                }

                if (key === 'sort_by') {
                    state.filters[key] = 'newest';
                } else if (key === 'limit') {
                    state.filters[key] = '100';
                } else {
                    state.filters[key] = '';
                }

                syncFilterInputs();
                renderActiveFilters();
                loadBatches().catch((error) => {
                    el.list.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                });
            };

            const renderList = () => {
                renderStats();
                renderActiveFilters();

                const count = Number(state.meta.count || state.batches.length || 0);
                const sortLabel = ({
                    newest: 'batch terbaru',
                    oldest: 'batch terlama',
                    latest_anomaly: 'alert terbaru',
                    largest_variance: 'selisih terbesar',
                }[state.meta.sort_by || state.filters.sort_by] || 'batch terbaru');
                const statusSummary = state.meta.status_summary || {};
                const summaryText = [
                    `Draf ${Number(statusSummary.draft || 0)}`,
                    `Diposting ${Number(statusSummary.posted || 0)}`,
                    `Dibatalkan ${Number(statusSummary.cancelled || 0)}`,
                ].join(' • ');
                el.meta.innerHTML = `<span>${count} batch • urut ${sortLabel}</span><span class="hidden lg:inline"> • ${summaryText}</span>`;
                el.list.innerHTML = '';

                if (!state.batches.length) {
                    el.list.innerHTML = '<div class="rounded-3xl border border-dashed border-outline-variant/60 bg-surface-container-low px-4 py-8 text-center text-sm text-slate-500 shadow-sm dark:border-slate-700 dark:bg-slate-950/40">Belum ada batch yang cocok dengan filter saat ini.</div>';
                    return;
                }

                state.batches.forEach((batch) => {
                    const anomalySummary = batch.anomaly_summary || {};
                    const openAlertCount = Number(anomalySummary.open_count || 0);
                    const hasOpenAlert = !!anomalySummary.has_open_alert;
                    const detailUrl = `${detailBaseUrl}/${encodeURIComponent(batch.id)}?from=${encodeURIComponent(currentPath)}`;
                    const batchDate = batch.transaction_date ? new Date(batch.transaction_date) : null;
                    const monthLabel = batchDate
                        ? new Intl.DateTimeFormat('id-ID', { month: 'short' }).format(batchDate).toUpperCase()
                        : '---';
                    const dayLabel = batchDate
                        ? new Intl.DateTimeFormat('id-ID', { day: '2-digit' }).format(batchDate)
                        : '--';
                    const card = document.createElement('a');
                    card.href = detailUrl;
                    card.className = 'batch-card group block overflow-hidden rounded-2xl transition';
                    card.innerHTML = `
                        <div class="flex items-start gap-3 p-4">
                            <div class="flex size-14 shrink-0 flex-col items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                                <span class="text-[9px] font-bold uppercase tracking-[0.18em]">${monthLabel}</span>
                                <span class="text-lg font-bold leading-none">${dayLabel}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <h4 class="truncate text-[17px] font-bold leading-tight text-slate-900">${batch.reference_no || batch.id.slice(0, 8).toUpperCase()}</h4>
                                        <p class="mt-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">${productName(batch.product_id)}</p>
                                        <p class="mt-1 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">${formatNumber(batch.actual_output_qty)} ${batch.output_unit || 'Unit'}</p>
                                    </div>
                                    <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.16em] ${statusBadge(batch.status)}">${statusLabel(batch.status)}</span>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold ${hasOpenAlert ? 'text-rose-600' : 'text-slate-500'}">${batch.status === 'draft' ? 'In Preparation' : `Variance: ${formatNumber(batch.yield_variance_qty)}`}</p>
                                    <span class="material-symbols-outlined text-slate-300">chevron_right</span>
                                </div>
                            </div>
                        </div>
                        <div class="px-4 pb-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-300">${hasOpenAlert ? `${openAlertCount} alert terbuka` : ''}</div>
                    `;
                    el.list.appendChild(card);
                });
            };

            const loadBootstrap = async () => {
                if (state.bootstrapRequest) {
                    return state.bootstrapRequest;
                }

                setLoadingOptions();
                state.createBootstrap = {
                    status: 'loading',
                    branches: 'loading',
                    locations: 'loading',
                    products: 'loading',
                    error: '',
                };

                state.bootstrapRequest = (async () => {
                    const [bootstrapResult, branchesResult, materialsResult] = await Promise.allSettled([
                        window.PosUi.fetchWithTimeout(window.PosUi.bootstrapUrl({ includeInventory: true }), { headers, cache: 'no-store' }, 12000),
                        window.PosUi.fetchWithTimeout('/api/v1/branches', { headers, cache: 'no-store' }, 12000),
                        window.PosUi.fetchWithTimeout('/api/v1/materials?limit=300', { headers, cache: 'no-store' }, 12000),
                    ]);

                    const bootstrapResponse = bootstrapResult.status === 'fulfilled' ? bootstrapResult.value : null;
                    const branchesResponse = branchesResult.status === 'fulfilled' ? branchesResult.value : null;
                    const materialsResponse = materialsResult.status === 'fulfilled' ? materialsResult.value : null;

                    const bootstrapPayload = bootstrapResponse?.ok ? unwrap(await bootstrapResponse.json().catch(() => ({}))) : {};
                    const branchesPayload = branchesResponse?.ok ? unwrap(await branchesResponse.json().catch(() => ({}))) : {};
                    const branchRows = normalizeRows(branchesPayload.branches);
                    const branchLocations = flattenLocationsFromBranches(branchRows);
                    const bootstrapBranches = normalizeRows(bootstrapPayload.branches);
                    const bootstrapLocations = normalizeRows(bootstrapPayload.locations);
                    const bootstrapProducts = normalizeRows(bootstrapPayload.products);

                    state.bootstrap = {
                        ...bootstrapPayload,
                        branches: branchRows.length ? branchRows : bootstrapBranches,
                        locations: branchLocations.length ? branchLocations : bootstrapLocations,
                        products: bootstrapProducts,
                    };

                    if (materialsResponse?.ok) {
                        const materialsPayload = unwrap(await materialsResponse.json().catch(() => ({})));
                        state.bootstrap.materials = normalizeRows(materialsPayload.materials);
                    } else {
                        state.bootstrap.materials = [];
                    }

                    const branches = normalizeRows(state.bootstrap.branches);
                    const locations = normalizeRows(state.bootstrap.locations);
                    const products = normalizeRows(state.bootstrap.products);
                    const bootstrapFailed = bootstrapResult.status === 'rejected' || !bootstrapResponse?.ok;
                    const branchesFailed = branchesResult.status === 'rejected' || !branchesResponse?.ok;

                    state.createBootstrap = {
                        status: 'ready',
                        branches: branches.length ? 'ready' : (branchesFailed ? 'error' : 'empty'),
                        locations: locations.length ? 'ready' : (branchesFailed ? 'error' : 'empty'),
                        products: products.length ? 'ready' : (bootstrapFailed ? 'error' : 'empty'),
                        error: '',
                    };

                    if (bootstrapFailed || branchesFailed || materialsResult.status === 'rejected' || (materialsResponse && !materialsResponse.ok)) {
                        const failedLabels = [
                            bootstrapFailed ? 'bootstrap utama' : '',
                            branchesFailed ? 'cabang/lokasi' : '',
                            (materialsResult.status === 'rejected' || (materialsResponse && !materialsResponse.ok)) ? 'bahan baku' : '',
                        ].filter(Boolean);

                        if (!branches.length || !locations.length || !products.length) {
                            state.createBootstrap.error = `Sebagian data batch gagal dimuat (${failedLabels.join(', ')}). Muat ulang halaman untuk mencoba lagi.`;
                        }
                    }

                    setOptions(el.product, products, 'name', 'Semua Produk');
                    setOptions(el.branch, branches, 'name', 'Semua Cabang');
                    setOptions(el.location, locations, 'name', 'Semua Lokasi');
                    markDefaultLocationOption(el.location, locations);
                    state.filters.location_id = resolveDefaultLocationId(locations, state.filters.location_id);
                    renderMaterialChips();
                    syncFilterInputs();
                    initCreateForm();

                    try {
                        renderCreateEligibilitySummary();
                    } catch (error) {
                        state.createBootstrap.error = error.message || 'Ringkasan eligibility produk gagal dirender.';
                        initCreateForm();
                    }
                })();

                try {
                    return await state.bootstrapRequest;
                } finally {
                    state.bootstrapRequest = null;
                }
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
                state.filters.has_open_anomaly = el.anomaly?.value || '';
                state.filters.sort_by = el.sort.value || 'newest';
                state.filters.transaction_date_from = el.dateFrom.value || '';
                state.filters.transaction_date_to = el.dateTo.value || '';
                renderActiveFilters();
                loadBatches().catch((error) => {
                    el.list.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                });
            };

            [el.product, el.status, el.cancelReason, el.branch, el.location, el.anomaly, el.sort, el.dateFrom, el.dateTo].forEach((node) => {
                node?.addEventListener('change', handleFilterChange);
            });

            el.cancelReason?.addEventListener('input', handleFilterChange);

            el.reset?.addEventListener('click', resetFilters);

            el.filterOpen?.addEventListener('click', () => toggleFilterModal(true));
            el.filterClose?.addEventListener('click', () => toggleFilterModal(false));
            el.filterOverlay?.addEventListener('click', () => toggleFilterModal(false));
            el.filterApply?.addEventListener('click', () => toggleFilterModal(false));

            el.activeFilters?.addEventListener('click', (event) => {
                const clearTrigger = event.target.closest('[data-clear-filter]');
                if (clearTrigger) {
                    clearFilter(clearTrigger.dataset.clearFilter);
                    return;
                }

                const trigger = event.target.closest('[data-reset-filters]');
                if (!trigger) return;
                resetFilters();
            });

            el.createBranch?.addEventListener('change', () => {
                el.createLocation.dataset.selected = '';
                syncCreateLocations();
                setPreviewState({
                    status: el.createProduct.value ? 'no_location_selected' : 'no_product_selected',
                    can_post: false,
                    ingredients: [],
                    warning_message: '',
                    message: el.createProduct.value ? previewCopy.noLocation : previewCopy.noProduct,
                });
            });

            el.createProduct?.addEventListener('change', () => {
                syncCreateExpectedOutput();
                state.outputRows = [];
                renderOutputRows();
                if (!el.createProduct.value) {
                    setPreviewState({
                        status: 'no_product_selected',
                        can_post: false,
                        ingredients: [],
                        warning_message: '',
                        message: previewCopy.noProduct,
                    });

                    return;
                }

                loadCreateRecipePreview();
            });

            el.createRecipeVersion?.addEventListener('input', () => {
                syncCreateExpectedOutput();
                collectOutputRows();
                syncDefaultOutputRowFromPlan();
                autoAllocateOutputRecipeFactors();
                renderOutputRows();
                updateSubmitState();
                if (el.createProduct.value && el.createLocation.value) {
                    loadCreateRecipePreview();
                }
            });

            el.createLocation?.addEventListener('change', () => {
                if (!el.createLocation.value) {
                    setPreviewState({
                        status: el.createProduct.value ? 'no_location_selected' : 'no_product_selected',
                        can_post: false,
                        ingredients: [],
                        warning_message: '',
                        message: el.createProduct.value ? previewCopy.noLocation : previewCopy.noProduct,
                    });

                    return;
                }

                loadCreateRecipePreview();
            });

            [el.createOpen, el.createClose, el.createCancel, el.createOverlay].forEach((node) => {
                node?.addEventListener('click', () => toggleCreateModal(node === el.createOpen));
            });

            el.outputAdd?.addEventListener('click', () => {
                collectOutputRows();
                state.outputRows.push({
                    id: outputRowId(),
                    product_id: '',
                    planned_qty: '',
                    recipe_factor: '',
                });
                autoAllocateOutputRecipeFactors();
                renderOutputRows();
            });

            el.outputRows?.addEventListener('input', () => {
                collectOutputRows();
                autoAllocateOutputRecipeFactors(true);
                updateSubmitState();
            });

            el.outputRows?.addEventListener('change', () => {
                collectOutputRows();
                autoAllocateOutputRecipeFactors(true);
                updateSubmitState();
            });

            el.outputRows?.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-output-remove]');
                if (!trigger) {
                    return;
                }

                collectOutputRows();
                state.outputRows = state.outputRows.filter((row) => row.id !== trigger.dataset.outputRemove);
                autoAllocateOutputRecipeFactors();
                renderOutputRows();
            });

            window.addEventListener('pageshow', (event) => {
                if (!event.persisted) {
                    return;
                }

                loadBootstrap().catch(() => {
                    // Keep current UI state if refresh-on-restore fails.
                });
            });

            el.createForm?.addEventListener('submit', async (event) => {
                event.preventDefault();
                const canSubmit = canCreateDraftFromPreview();
                const outputsValid = outputRowsAreValid();
                if (!canSubmit || !outputsValid) {
                    const summary = outputPlanSummary();
                    const outputMessage = summary.remaining < -0.0001
                        ? 'Total hasil jadi melebihi jumlah resep yang dibuat.'
                        : 'Lengkapi produk, qty, dan faktor resep pada hasil jadi.';
                    setCreateFeedback(outputsValid ? (getPreviewMessage() || 'Lengkapi produk dan lokasi terlebih dahulu.') : outputMessage, true);
                    return;
                }
                setCreateFeedback('');
                el.createSubmit.disabled = true;
                el.createSubmit.textContent = 'Menyimpan...';
                const recipePlan = syncCreateExpectedOutput();
                const outputRows = collectOutputRows();

                const payload = {
                    branch_id: el.createBranch.value || null,
                    location_id: el.createLocation.value || null,
                    product_id: el.createProduct.value || null,
                    transaction_date: el.createDate.value || null,
                    recipe_count: Number(el.createRecipeVersion.value || 0),
                    outputs: outputRows.map((row) => ({
                        product_id: row.product_id,
                        planned_qty: Number(row.planned_qty || 0),
                        recipe_factor: Number(row.recipe_factor || 0),
                    })),
                    reference_no: (el.createReference.value || '').trim() || null,
                    notes: (el.createNotes.value || '').trim() || null,
                };

                try {
                    const response = await fetch('/api/v1/production-batches', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            ...headers,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });

                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const details = result.details ? Object.values(result.details).flat().join(' ') : '';
                        const message = details || result.message || 'Gagal membuat draf batch.';

                        if (String(message).toLowerCase().includes('stok') || String(message).toLowerCase().includes('stock')) {
                            state.recipePreview = {
                                ...(state.recipePreview || {}),
                                status: 'stale_preview',
                                can_post: false,
                                message: previewCopy.stale,
                            };
                            updateSubmitState();
                            renderCreateRecipePreview();
                        }

                        throw new Error(message);
                    }

                    const batchId = result.batch_id;
                    window.location.href = `${detailBaseUrl}/${encodeURIComponent(batchId)}?from=${encodeURIComponent(currentPath)}`;
                } catch (error) {
                    setCreateFeedback(error.message || 'Gagal membuat draf batch.');
                } finally {
                    el.createSubmit.disabled = false;
                    el.createSubmit.textContent = 'Simpan Rencana';
                }
            });

            loadBootstrap().catch((error) => {
                setCreateHelper(error.message || 'Bootstrap batch gagal dimuat. Muat ulang halaman untuk mencoba lagi.', false);
                setStatusOption(el.createBranch, 'Gagal memuat cabang');
                setStatusOption(el.createLocation, 'Gagal memuat lokasi');
                setStatusOption(el.createProduct, 'Gagal memuat produk');
            });

            loadBatches().catch((error) => {
                el.meta.textContent = error.message;
                el.list.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
            });
        })();
    </script>
@endsection
