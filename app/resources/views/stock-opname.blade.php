@extends('layouts.app')

@php
    $stockOpnameTenant = \App\Support\TenantContext::get();
    $stockOpnameUser = auth()->user();
    $stockOpnameRole = null;

    if (! $stockOpnameTenant && filled($stockOpnameUser?->tenant_id)) {
        $stockOpnameTenant = \App\Models\Tenant::query()->find($stockOpnameUser->tenant_id);
    }

    if ($stockOpnameTenant && $stockOpnameUser) {
        $stockOpnameRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $stockOpnameTenant->id, (string) $stockOpnameUser->id);

        if (! $stockOpnameRole && filled($stockOpnameUser->role ?? null)) {
            $stockOpnameRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $stockOpnameUser->role);
        }
    }

    abort_unless(in_array($stockOpnameRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $stockOpnameIsPos = request()->is('pos/*');
    $stockOpnamePortalPrefix = $stockOpnameIsPos ? '/pos' : '/backoffice';
    $stockOpnameBackUrl = $stockOpnamePortalPrefix.'/inventory';
@endphp

@section('page_title', 'Stock Opname')
@section('ui_mode', $stockOpnameIsPos ? 'pos' : 'backoffice')
@section('body_class', $stockOpnameIsPos ? 'font-display text-slate-900' : 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', $stockOpnameIsPos ? 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-slate-50 shadow-2xl' : 'relative flex h-screen w-full flex-col overflow-hidden bg-white dark:bg-slate-900')
@section('main_class', $stockOpnameIsPos ? 'flex-1 overflow-hidden p-0' : 'flex-1 overflow-y-auto pb-28 lg:pb-8 lg:pl-72')

@push('head')
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
@endpush

@section('header')
    @if($stockOpnameIsPos)
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white px-4 pb-3 pt-4" data-auto-center-title="true">
            <div class="flex items-center gap-2.5">
                <a class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600" href="/menu" aria-label="Buka semua menu">
                    <span class="material-symbols-outlined text-xl">apps</span>
                </a>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400">Inventory Count</p>
                    <h1 class="truncate text-base font-black text-slate-950">Stock Opname</h1>
                </div>
                <button id="btn-history-top" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600" type="button" title="Riwayat Opname">
                    <span class="material-symbols-outlined text-lg">history</span>
                </button>
                <button id="btn-new-top" class="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-full bg-primary px-3 text-xs font-bold text-white" type="button">
                    <span class="material-symbols-outlined text-lg">add</span>
                    Buat
                </button>
            </div>
        </header>
    @else
        <x-page-header title="Stock Opname" :backUrl="$stockOpnameBackUrl" :showMenuButton="true">
            <x-slot name="actions">
                <button id="btn-history-top" class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-200" type="button" title="Riwayat Opname">
                    <span class="material-symbols-outlined text-[20px]">history</span>
                </button>
                <button id="btn-new-top" class="flex h-9 items-center gap-2 rounded-full bg-primary px-3 text-sm font-bold text-white" type="button">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    <span class="hidden sm:inline">Buat</span>
                </button>
            </x-slot>
        </x-page-header>
    @endif
@endsection

@section('content')
    <div class="{{ $stockOpnameIsPos ? 'relative flex h-full flex-col bg-slate-50' : 'mx-auto flex min-h-full w-full max-w-[430px] flex-col bg-slate-50 shadow-sm' }}">
        <div class="{{ $stockOpnameIsPos ? 'min-h-0 flex-1 overflow-y-auto no-scrollbar' : 'flex-1 overflow-y-auto no-scrollbar' }}">
            <section class="space-y-3 bg-white px-4 py-3">
                <div class="grid grid-cols-2 gap-2">
                    <button id="btn-new" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-3 py-3 text-sm font-bold text-white shadow-sm" type="button">
                        <span class="material-symbols-outlined text-[20px]">add_circle</span>
                        Buat Opname Baru
                    </button>
                    <button id="btn-history" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-700 shadow-sm" type="button">
                        <span class="material-symbols-outlined text-[20px]">history</span>
                        Riwayat Opname
                    </button>
                </div>

                <div id="alert-box" class="hidden rounded-2xl border px-4 py-3 text-sm"></div>
            </section>

            <section class="space-y-4 px-4 py-4 pb-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400">Draft</p>
                            <h2 class="text-sm font-black text-slate-900">Stock Opname</h2>
                        </div>
                        <span id="status-pill" class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-500">Belum dipilih</span>
                    </div>
                    <div class="mt-4 space-y-3">
                        <label class="block">
                            <span class="text-xs font-bold text-slate-500">Outlet/Gudang</span>
                            <select id="location-select" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-primary focus:ring-primary disabled:bg-slate-100 disabled:text-slate-500">
                                <option value="">Pilih lokasi</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold text-slate-500">Tanggal Opname</span>
                            <input id="opname-date" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-900 focus:border-primary focus:ring-primary disabled:bg-slate-100 disabled:text-slate-500" type="date">
                        </label>
                        <label class="block">
                            <span class="text-xs font-bold text-slate-500">Catatan</span>
                            <textarea id="opname-notes" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 focus:border-primary focus:ring-primary disabled:bg-slate-100 disabled:text-slate-500" rows="3" placeholder="Catatan sesi hitung stok"></textarea>
                        </label>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button id="btn-create-draft" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-3 py-3 text-sm font-bold text-white shadow-sm" type="button">
                            <span class="material-symbols-outlined text-[18px]">post_add</span>
                            Buat Draft
                        </button>
                        <button id="btn-reset-form" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-600 shadow-sm" type="button">
                            <span class="material-symbols-outlined text-[18px]">restart_alt</span>
                            Reset
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Item dihitung</p>
                        <p id="kpi-counted" class="mt-1 text-xl font-black text-slate-950">0/0</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Selisih Qty</p>
                        <p id="kpi-diff-qty" class="mt-1 text-xl font-black text-slate-950">0</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Nilai Selisih</p>
                        <p id="kpi-diff-value" class="mt-1 text-xl font-black text-slate-950">Rp0</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Status</p>
                        <p id="kpi-status" class="mt-1 truncate text-lg font-black text-slate-950">-</p>
                    </div>
                </div>

                <div id="finalized-banner" class="hidden rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800"></div>

                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="space-y-3 border-b border-slate-200 p-4">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400">Hitung Fisik</p>
                            <h2 class="text-sm font-black text-slate-900">Daftar Produk</h2>
                            <p id="table-subtitle" class="mt-1 text-xs text-slate-500">Buat atau pilih draft untuk mulai menghitung.</p>
                        </div>
                        <label class="relative flex h-11 items-center rounded-full bg-slate-100">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">search</span>
                            <input id="item-search" class="h-full w-full rounded-full border-0 bg-transparent py-2.5 pl-10 pr-4 text-sm focus:ring-2 focus:ring-primary" placeholder="Cari produk atau SKU">
                        </label>
                        <select id="count-filter" class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 focus:border-primary focus:ring-primary">
                            <option value="all">Semua item</option>
                            <option value="uncounted">Belum dihitung</option>
                            <option value="difference">Ada selisih</option>
                        </select>
                    </div>
                    <div class="px-4 py-4">
                        <table class="block w-full text-left text-sm">
                            <thead class="hidden">
                                <tr>
                                    <th class="px-4 py-3">Produk</th>
                                    <th class="px-4 py-3">SKU/Kode</th>
                                    <th class="px-4 py-3 text-right">Stok Sistem</th>
                                    <th class="px-4 py-3 text-right">Stok Fisik</th>
                                    <th class="px-4 py-3 text-right">Selisih</th>
                                    <th class="px-4 py-3 text-right">Nilai Selisih</th>
                                    <th class="px-4 py-3">Catatan Item</th>
                                </tr>
                            </thead>
                            <tbody id="items-body" class="block space-y-3">
                                <tr class="block rounded-2xl border border-slate-200 bg-slate-50">
                                    <td class="block px-4 py-8 text-center text-sm text-slate-500" colspan="7">Belum ada draft stock opname.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="space-y-3 border-t border-slate-200 p-4">
                        <p id="save-state" class="text-xs font-semibold text-slate-500">Draft belum dipilih.</p>
                        <label id="partial-panel" class="flex items-start gap-2 rounded-2xl bg-slate-50 p-3 text-xs text-slate-600">
                            <input id="allow-partial" class="mt-0.5 size-4 rounded border-slate-300 text-primary focus:ring-primary disabled:opacity-50" type="checkbox" disabled>
                            <span>
                                Saya sadar opname ini hanya sebagian item.
                                <span id="partial-hint" class="block text-[11px] text-slate-400">Semua item sudah dihitung.</span>
                            </span>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <button id="btn-save-draft" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-700 shadow-sm disabled:cursor-not-allowed disabled:opacity-50" type="button" disabled>
                                <span class="material-symbols-outlined text-[18px]">save</span>
                                Simpan Draft
                            </button>
                            <button id="btn-cancel-draft" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 px-3 py-3 text-sm font-bold text-rose-600 disabled:cursor-not-allowed disabled:opacity-50" type="button" disabled>
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                Batalkan Draft
                            </button>
                            <button id="btn-finalize" class="col-span-2 inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-white shadow-sm disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none" type="button" disabled>
                                <span class="material-symbols-outlined text-[18px]">task_alt</span>
                                Finalisasi
                            </button>
                        </div>
                    </div>
                </div>

                <div id="history-panel" class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400">Riwayat</p>
                            <h2 class="text-sm font-black text-slate-900">Riwayat Opname</h2>
                        </div>
                        <button id="btn-refresh-history" class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-500" type="button" aria-label="Muat ulang riwayat">
                            <span class="material-symbols-outlined text-[18px]">refresh</span>
                        </button>
                    </div>
                    <div id="history-list" class="mt-3 max-h-[28rem] space-y-2 overflow-y-auto pr-1 no-scrollbar">
                        <div class="rounded-2xl bg-slate-50 p-3 text-sm text-slate-500">Memuat riwayat...</div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div id="finalize-modal" class="fixed inset-0 z-50 hidden items-end justify-center bg-slate-950/60 px-0 sm:items-center sm:px-4">
        <div class="max-h-[90dvh] w-full max-w-[430px] overflow-y-auto rounded-t-[28px] bg-white p-5 shadow-2xl sm:rounded-[28px]">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-500">Finalisasi Opname</p>
                    <h3 class="mt-1 text-xl font-black text-slate-900">Periksa sebelum finalisasi</h3>
                    <p class="mt-1 text-sm text-slate-500">Pastikan data sudah benar. Selisih akan dihitung ulang saat finalisasi.</p>
                </div>
                <button id="btn-finalize-close" class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-slate-500" type="button">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <div id="finalize-error" class="mt-4 hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700"></div>

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Item berubah</p>
                    <p id="finalize-summary-changed" class="mt-1 text-lg font-black text-slate-900">0</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Belum dihitung</p>
                    <p id="finalize-summary-uncounted" class="mt-1 text-lg font-black text-slate-900">0</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Mapping Akun</p>
                    <p id="finalize-summary-mapping" class="mt-1 text-lg font-black text-slate-900">Lengkap</p>
                </div>
            </div>

            <div class="mt-4 rounded-2xl border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <div>
                        <p class="text-sm font-black text-slate-900">Daftar item berubah</p>
                        <p id="finalize-changed-meta" class="mt-1 text-xs text-slate-500">Menampilkan 0 item.</p>
                    </div>
                    <button id="btn-finalize-load-more" class="hidden rounded-full border border-slate-200 px-3 py-1 text-xs font-bold text-slate-600" type="button">Muat lagi</button>
                </div>
                <div class="max-h-64 overflow-y-auto">
                    <ul id="finalize-changed-list" class="divide-y divide-slate-100"></ul>
                    <div id="finalize-changed-empty" class="px-4 py-5 text-sm text-slate-500">Tidak ada perubahan stok sistem sejak draft.</div>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p id="finalize-partial-hint" class="text-xs text-slate-500">Pastikan semua item terisi sebelum finalisasi.</p>
                <div class="grid grid-cols-2 gap-2">
                    <button id="btn-finalize-cancel" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 shadow-sm" type="button">Batal</button>
                    <button id="btn-finalize-proceed" class="rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-white shadow-sm disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none" type="button" disabled>Lanjutkan</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content || '';
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const today = new Date().toISOString().slice(0, 10);
            const baseHeaders = {
                Accept: 'application/json',
                ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
            };
            const mutationHeaders = {
                ...baseHeaders,
                'Content-Type': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            };

            const el = {
                alert: document.getElementById('alert-box'),
                location: document.getElementById('location-select'),
                date: document.getElementById('opname-date'),
                notes: document.getElementById('opname-notes'),
                statusPill: document.getElementById('status-pill'),
                historyList: document.getElementById('history-list'),
                itemsBody: document.getElementById('items-body'),
                itemSearch: document.getElementById('item-search'),
                countFilter: document.getElementById('count-filter'),
                kpiCounted: document.getElementById('kpi-counted'),
                kpiDiffQty: document.getElementById('kpi-diff-qty'),
                kpiDiffValue: document.getElementById('kpi-diff-value'),
                kpiStatus: document.getElementById('kpi-status'),
                finalizedBanner: document.getElementById('finalized-banner'),
                tableSubtitle: document.getElementById('table-subtitle'),
                saveState: document.getElementById('save-state'),
                partialPanel: document.getElementById('partial-panel'),
                allowPartial: document.getElementById('allow-partial'),
                partialHint: document.getElementById('partial-hint'),
                finalizeModal: document.getElementById('finalize-modal'),
                finalizeClose: document.getElementById('btn-finalize-close'),
                finalizeCancel: document.getElementById('btn-finalize-cancel'),
                finalizeProceed: document.getElementById('btn-finalize-proceed'),
                finalizeError: document.getElementById('finalize-error'),
                finalizeSummaryChanged: document.getElementById('finalize-summary-changed'),
                finalizeSummaryUncounted: document.getElementById('finalize-summary-uncounted'),
                finalizeSummaryMapping: document.getElementById('finalize-summary-mapping'),
                finalizeChangedList: document.getElementById('finalize-changed-list'),
                finalizeChangedEmpty: document.getElementById('finalize-changed-empty'),
                finalizeChangedMeta: document.getElementById('finalize-changed-meta'),
                finalizeLoadMore: document.getElementById('btn-finalize-load-more'),
                finalizePartialHint: document.getElementById('finalize-partial-hint'),
                buttons: {
                    newTop: document.getElementById('btn-new-top'),
                    new: document.getElementById('btn-new'),
                    historyTop: document.getElementById('btn-history-top'),
                    history: document.getElementById('btn-history'),
                    refreshHistory: document.getElementById('btn-refresh-history'),
                    createDraft: document.getElementById('btn-create-draft'),
                    resetForm: document.getElementById('btn-reset-form'),
                    saveDraft: document.getElementById('btn-save-draft'),
                    cancelDraft: document.getElementById('btn-cancel-draft'),
                    finalize: document.getElementById('btn-finalize'),
                },
            };

            const state = {
                locations: [],
                opnames: [],
                current: null,
                items: [],
                dirty: new Set(),
                loading: false,
                finalizeCheck: {
                    page: 1,
                    perPage: 10,
                    total: 0,
                    hasMore: false,
                    items: [],
                    changedCount: 0,
                    uncountedCount: 0,
                    missingAccounts: false,
                    missingMessage: null,
                    canFinalize: false,
                },
            };

            const formatQty = (value) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 4 });
            const formatMoney = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Number(value || 0));
            const signedQty = (value) => {
                const number = Number(value || 0);
                return number > 0 ? `+${formatQty(number)}` : formatQty(number);
            };
            const statusLabel = (status) => ({
                draft: 'Draft',
                finalized: 'Final',
                cancelled: 'Dibatalkan',
            })[status] || '-';
            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            })[char]);

            const showAlert = (message, tone = 'info') => {
                if (!message) {
                    el.alert.className = 'hidden rounded-2xl border px-4 py-3 text-sm whitespace-pre-line';
                    el.alert.textContent = '';
                    return;
                }

                const tones = {
                    info: 'border-sky-200 bg-sky-50 text-sky-800',
                    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
                    danger: 'border-rose-200 bg-rose-50 text-rose-800',
                    warning: 'border-amber-200 bg-amber-50 text-amber-800',
                };
                el.alert.className = `rounded-2xl border px-4 py-3 text-sm font-semibold whitespace-pre-line ${tones[tone] || tones.info}`;
                el.alert.textContent = message;
            };

            const apiFetch = async (url, options = {}) => {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    ...options,
                    headers: {
                        ...(options.method && options.method !== 'GET' ? mutationHeaders : baseHeaders),
                        ...(options.headers || {}),
                    },
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(payload.message || 'Permintaan gagal diproses.');
                }
                return payload;
            };

            const loadBootstrap = async () => {
                const payload = await window.PosUi.getBootstrap({
                    headers: baseHeaders,
                    includeInventory: true,
                    ttlMs: 0,
                    forceFresh: true,
                });

                state.locations = payload.locations || payload.data?.locations || [];
                el.location.innerHTML = '<option value="">Pilih lokasi</option>';
                state.locations.forEach((location) => {
                    const option = document.createElement('option');
                    option.value = location.id;
                    option.textContent = location.name || 'Lokasi';
                    el.location.appendChild(option);
                });

                const defaultLocation = state.locations.find((location) => location.is_default) || state.locations[0];
                if (defaultLocation) {
                    el.location.value = defaultLocation.id;
                }
            };

            const loadHistory = async () => {
                const payload = await apiFetch('/api/v1/stock-opnames?limit=50');
                state.opnames = payload.opnames || [];
                renderHistory();
            };

            const loadDetail = async (opnameId) => {
                const payload = await apiFetch(`/api/v1/stock-opnames/${encodeURIComponent(opnameId)}`);
                state.current = payload.opname || null;
                state.items = payload.items || [];
                state.dirty.clear();
                syncFormFromCurrent();
                renderAll();
            };

            const syncFormFromCurrent = () => {
                if (!state.current) {
                    return;
                }

                el.location.value = state.current.location_id || '';
                el.date.value = state.current.opname_date || today;
                el.notes.value = state.current.notes || '';
            };

            const resetForm = () => {
                state.current = null;
                state.items = [];
                state.dirty.clear();
                el.date.value = today;
                el.notes.value = '';
                el.itemSearch.value = '';
                el.countFilter.value = 'all';
                el.allowPartial.checked = false;
                showAlert('');
                renderAll();
            };

            const statusPillClass = (status) => {
                if (status === 'finalized') {
                    return 'rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-700';
                }
                if (status === 'cancelled') {
                    return 'rounded-full bg-rose-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-rose-700';
                }
                if (status === 'draft') {
                    return 'rounded-full bg-amber-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-amber-700';
                }
                return 'rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-500';
            };

            const renderHistory = () => {
                if (!state.opnames.length) {
                    el.historyList.innerHTML = '<div class="rounded-2xl bg-slate-50 p-3 text-sm text-slate-500">Belum ada riwayat stock opname.</div>';
                    return;
                }

                el.historyList.innerHTML = state.opnames.map((opname) => {
                    const active = state.current?.id === opname.id;
                    const valueClass = Number(opname.total_difference_value || 0) < 0 ? 'text-rose-600' : 'text-emerald-600';
                    return `
                        <button class="w-full rounded-2xl border px-3 py-3 text-left shadow-sm transition ${active ? 'border-primary bg-primary/5' : 'border-slate-200 bg-white hover:bg-slate-50'}" data-history-id="${opname.id}" type="button">
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-black text-slate-900">${escapeHtml(opname.location_name || 'Lokasi')}</p>
                                <span class="${statusPillClass(opname.status)}">${statusLabel(opname.status)}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                                <span>${escapeHtml(opname.opname_date || '-')}</span>
                                <span class="font-bold ${valueClass}">${formatMoney(opname.total_difference_value)}</span>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-400">${opname.counted_item_count || 0}/${opname.item_count || 0} item dihitung</p>
                        </button>
                    `;
                }).join('');
            };

            const filteredItems = () => {
                const term = (el.itemSearch.value || '').trim().toLowerCase();
                const mode = el.countFilter.value;

                return state.items.filter((item) => {
                    const haystack = `${item.product_name || ''} ${item.product_sku || ''} ${item.variant_name || ''}`.toLowerCase();
                    const matchesTerm = !term || haystack.includes(term);
                    const counted = item.physical_qty !== null && item.physical_qty !== '';
                    const difference = Number(item.difference_qty || 0) !== 0;
                    const matchesMode = mode === 'all'
                        || (mode === 'uncounted' && !counted)
                        || (mode === 'difference' && difference);

                    return matchesTerm && matchesMode;
                });
            };

            const recomputeItem = (item) => {
                if (item.physical_qty === null || item.physical_qty === '') {
                    item.difference_qty = 0;
                    item.difference_value = 0;
                    return;
                }

                item.difference_qty = Number(item.physical_qty || 0) - Number(item.system_qty || 0);
                item.difference_value = Number(item.difference_qty || 0) * Number(item.unit_cost || 0);
            };

            const renderItems = () => {
                const emptyMarkup = (message) => `
                    <tr class="block rounded-2xl border border-dashed border-slate-200 bg-slate-50">
                        <td class="block px-4 py-8 text-center text-sm text-slate-500" colspan="7">${message}</td>
                    </tr>
                `;

                if (!state.current) {
                    el.itemsBody.innerHTML = emptyMarkup('Belum ada draft stock opname.');
                    return;
                }

                const rows = filteredItems();
                if (!rows.length) {
                    el.itemsBody.innerHTML = emptyMarkup('Tidak ada item sesuai filter.');
                    return;
                }

                const readonly = state.current.status !== 'draft';
                el.itemsBody.innerHTML = rows.map((item) => {
                    const difference = Number(item.difference_qty || 0);
                    const differenceClass = difference < 0 ? 'text-rose-600' : difference > 0 ? 'text-emerald-600' : 'text-slate-500';
                    const largeDiff = Math.abs(difference) >= Math.max(10, Number(item.system_qty || 0) * 0.25);
                    const productName = item.variant_name ? `${item.product_name} - ${item.variant_name}` : item.product_name;
                    const unit = escapeHtml(item.unit || '');
                    return `
                        <tr data-item-id="${item.id}" class="block rounded-2xl border p-3 shadow-sm ${largeDiff ? 'border-amber-200 bg-amber-50/80' : 'border-slate-200 bg-white'}">
                            <td class="block p-0" colspan="7">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-900">${escapeHtml(productName || '-')}</p>
                                        <p class="mt-0.5 text-[11px] font-semibold text-slate-400">${escapeHtml(item.product_sku || '-')}</p>
                                        ${largeDiff ? '<p class="mt-1 text-[11px] font-bold text-amber-700">Warning selisih besar</p>' : ''}
                                    </div>
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">${formatQty(item.system_qty)} ${unit}</span>
                                </div>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Stok Sistem</p>
                                        <p class="mt-1 text-sm font-black text-slate-900">${formatQty(item.system_qty)} ${unit}</p>
                                    </div>
                                    <label class="rounded-xl bg-slate-50 px-3 py-2">
                                        <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Stok Fisik</span>
                                        <input class="physical-input mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-right text-sm font-bold text-slate-900 focus:border-primary focus:ring-primary disabled:bg-slate-100 disabled:text-slate-500" data-item-id="${item.id}" type="number" min="0" step="0.0001" value="${item.physical_qty ?? ''}" ${readonly ? 'disabled' : ''}>
                                    </label>
                                </div>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Selisih</p>
                                        <p class="diff-qty mt-1 text-sm font-black ${differenceClass}">${signedQty(difference)}</p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 px-3 py-2">
                                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Nilai</p>
                                        <p class="diff-value mt-1 text-sm font-black ${differenceClass}">${formatMoney(item.difference_value)}</p>
                                    </div>
                                </div>
                                <input class="notes-input mt-3 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 focus:border-primary focus:ring-primary disabled:bg-slate-100 disabled:text-slate-500" data-item-id="${item.id}" value="${escapeHtml(item.notes || '')}" placeholder="Catatan alasan" ${readonly ? 'disabled' : ''}>
                            </td>
                        </tr>
                    `;
                }).join('');
            };

            const renderSummary = () => {
                const itemCount = state.items.length;
                const counted = state.items.filter((item) => item.physical_qty !== null && item.physical_qty !== '').length;
                const uncounted = Math.max(itemCount - counted, 0);
                const diffQty = state.items.reduce((sum, item) => sum + Number(item.difference_qty || 0), 0);
                const diffValue = state.items.reduce((sum, item) => sum + Number(item.difference_value || 0), 0);
                const status = state.current?.status || '';
                const readonly = status !== 'draft';

                el.kpiCounted.textContent = `${counted}/${itemCount}`;
                el.kpiDiffQty.textContent = signedQty(diffQty);
                el.kpiDiffValue.textContent = formatMoney(diffValue);
                el.kpiStatus.textContent = statusLabel(status);
                el.statusPill.className = statusPillClass(status);
                el.statusPill.textContent = status ? statusLabel(status) : 'Belum dipilih';
                el.tableSubtitle.textContent = state.current
                    ? `${state.current.location_name || 'Lokasi'} - ${state.current.opname_date || '-'}`
                    : 'Buat atau pilih draft untuk mulai menghitung.';
                el.saveState.textContent = state.current
                    ? (state.dirty.size > 0 ? `${state.dirty.size} item belum disimpan.` : 'Semua perubahan draft tersimpan.')
                    : 'Draft belum dipilih.';

                el.partialPanel.classList.toggle('hidden', !state.current);
                el.allowPartial.disabled = !state.current || readonly || uncounted === 0;
                if (el.allowPartial.disabled) {
                    el.allowPartial.checked = false;
                }
                el.partialHint.textContent = uncounted > 0
                    ? `${uncounted} item belum dihitung.`
                    : 'Semua item sudah dihitung.';

                el.buttons.saveDraft.disabled = !state.current || readonly || state.dirty.size === 0;
                el.buttons.cancelDraft.disabled = !state.current || readonly;
                el.buttons.finalize.disabled = !state.current || readonly || counted === 0 || state.dirty.size > 0 || (uncounted > 0 && !el.allowPartial.checked);

                el.location.disabled = Boolean(state.current);
                el.date.disabled = Boolean(state.current);
                el.notes.disabled = Boolean(state.current);

                if (state.current?.status === 'finalized') {
                    el.finalizedBanner.classList.remove('hidden');
                    el.finalizedBanner.textContent = `Sudah difinalisasi oleh ${state.current.finalized_by_name || '-'} pada ${state.current.finalized_at || '-'}. Data readonly dan semua selisih sudah masuk ledger.`;
                } else {
                    el.finalizedBanner.classList.add('hidden');
                    el.finalizedBanner.textContent = '';
                }
            };

            const renderAll = () => {
                renderHistory();
                renderItems();
                renderSummary();
            };

            const createDraft = async () => {
                if (!el.location.value) {
                    showAlert('Pilih outlet/gudang terlebih dahulu.', 'warning');
                    return;
                }

                state.loading = true;
                showAlert('Membuat draft stock opname...', 'info');
                try {
                    const payload = await apiFetch('/api/v1/stock-opnames', {
                        method: 'POST',
                        body: JSON.stringify({
                            location_id: el.location.value,
                            opname_date: el.date.value || today,
                            notes: el.notes.value || null,
                        }),
                    });
                    await loadHistory();
                    await loadDetail(payload.opname_id);
                    showAlert('Draft stock opname berhasil dibuat.', 'success');
                } catch (error) {
                    showAlert(error.message, 'danger');
                } finally {
                    state.loading = false;
                }
            };

            const saveDirty = async () => {
                if (!state.current || state.dirty.size === 0) {
                    return true;
                }

                showAlert('Menyimpan perubahan draft...', 'info');
                const ids = Array.from(state.dirty);
                try {
                    for (const itemId of ids) {
                        const item = state.items.find((candidate) => candidate.id === itemId);
                        if (!item || item.physical_qty === null || item.physical_qty === '') {
                            continue;
                        }
                        await apiFetch(`/api/v1/stock-opnames/${encodeURIComponent(state.current.id)}/items/${encodeURIComponent(item.id)}`, {
                            method: 'PATCH',
                            body: JSON.stringify({
                                physical_qty: item.physical_qty,
                                notes: item.notes || null,
                            }),
                        });
                        state.dirty.delete(item.id);
                    }
                    await loadDetail(state.current.id);
                    showAlert('Draft tersimpan.', 'success');
                    return true;
                } catch (error) {
                    showAlert(error.message, 'danger');
                    renderSummary();
                    return false;
                }
            };

            const fetchFinalizeCheck = async (page = 1) => {
                const params = new URLSearchParams({
                    page: String(page),
                    per_page: String(state.finalizeCheck.perPage),
                });
                return await apiFetch(`/api/v1/stock-opnames/${encodeURIComponent(state.current.id)}/finalize-check?${params.toString()}`);
            };

            const syncFinalizeCheck = (payload, append = false) => {
                const items = payload.changed_items || [];

                if (!append) {
                    state.finalizeCheck.items = items;
                } else {
                    state.finalizeCheck.items = state.finalizeCheck.items.concat(items);
                }

                state.finalizeCheck.page = payload.current_page || state.finalizeCheck.page;
                state.finalizeCheck.perPage = payload.per_page || state.finalizeCheck.perPage;
                state.finalizeCheck.total = payload.total || 0;
                state.finalizeCheck.hasMore = Boolean(payload.has_more);
                state.finalizeCheck.changedCount = Number(payload.changed_count || 0);
                state.finalizeCheck.uncountedCount = Number(payload.uncounted_count || 0);
                state.finalizeCheck.missingAccounts = Boolean(payload.missing_accounts);
                state.finalizeCheck.missingMessage = payload.missing_accounts_message || null;
                state.finalizeCheck.canFinalize = Boolean(payload.can_finalize);
            };

            const renderFinalizeModal = () => {
                const changedCount = state.finalizeCheck.changedCount || 0;
                const uncountedCount = state.finalizeCheck.uncountedCount || 0;
                const missingAccounts = state.finalizeCheck.missingAccounts;
                const missingMessage = state.finalizeCheck.missingMessage;
                const items = state.finalizeCheck.items || [];

                el.finalizeSummaryChanged.textContent = formatQty(changedCount);
                el.finalizeSummaryUncounted.textContent = formatQty(uncountedCount);
                el.finalizeSummaryMapping.textContent = missingAccounts ? 'Belum lengkap' : 'Lengkap';
                el.finalizeSummaryMapping.className = `mt-1 text-lg font-black ${missingAccounts ? 'text-rose-600' : 'text-emerald-600'}`;

                if (missingAccounts) {
                    el.finalizeError.classList.remove('hidden');
                    el.finalizeError.textContent = missingMessage || 'Finalisasi gagal: akun persediaan atau akun selisih stok belum dikonfigurasi. Silakan lengkapi pengaturan akun terlebih dahulu.';
                } else {
                    el.finalizeError.classList.add('hidden');
                    el.finalizeError.textContent = '';
                }

                if (items.length > 0) {
                    el.finalizeChangedList.innerHTML = items.map((item) => {
                        const name = item.variant_name ? `${item.product_name} - ${item.variant_name}` : item.product_name;
                        return `
                            <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                <div>
                                    <p class="font-semibold text-slate-900">${escapeHtml(name || '-')}</p>
                                    <p class="text-[11px] text-slate-400">${escapeHtml(item.sku || '-')}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-slate-500">${formatQty(item.snapshot_qty)} &rarr; ${formatQty(item.current_qty)}</p>
                                    <p class="text-[11px] text-slate-400">Fisik: ${item.physical_qty === null ? '-' : formatQty(item.physical_qty)}</p>
                                </div>
                            </li>
                        `;
                    }).join('');
                    el.finalizeChangedEmpty.classList.add('hidden');
                } else {
                    el.finalizeChangedList.innerHTML = '';
                    el.finalizeChangedEmpty.classList.remove('hidden');
                }

                const shownCount = items.length;
                el.finalizeChangedMeta.textContent = changedCount > 0
                    ? `Menampilkan ${shownCount} dari ${changedCount} item.`
                    : 'Menampilkan 0 item.';

                if (state.finalizeCheck.hasMore) {
                    el.finalizeLoadMore.classList.remove('hidden');
                } else {
                    el.finalizeLoadMore.classList.add('hidden');
                }

                const canProceed = state.finalizeCheck.canFinalize
                    && !missingAccounts
                    && !(uncountedCount > 0 && !el.allowPartial.checked);

                el.finalizeProceed.disabled = !canProceed;
                el.finalizePartialHint.textContent = uncountedCount > 0
                    ? 'Ada item yang belum dihitung. Centang opsi opname parsial untuk melanjutkan.'
                    : 'Semua item sudah dihitung. Anda bisa melanjutkan finalisasi.';
            };

            const openFinalizeModal = () => {
                el.finalizeModal.classList.remove('hidden');
                el.finalizeModal.classList.add('flex');
                renderFinalizeModal();
            };

            const closeFinalizeModal = () => {
                el.finalizeModal.classList.add('hidden');
                el.finalizeModal.classList.remove('flex');
            };

            const finalize = async () => {
                if (!state.current) {
                    return;
                }

                const saved = await saveDirty();
                if (!saved) {
                    return;
                }

                showAlert('Menyiapkan finalisasi...', 'info');
                try {
                    const payload = await fetchFinalizeCheck(1);
                    syncFinalizeCheck(payload, false);
                    showAlert('');
                    openFinalizeModal();
                } catch (error) {
                    showAlert(error.message, 'danger');
                }
            };

            const proceedFinalize = async () => {
                if (!state.current || el.finalizeProceed.disabled) {
                    return;
                }

                el.finalizeProceed.disabled = true;
                showAlert('Memfinalisasi stock opname...', 'info');
                try {
                    await apiFetch(`/api/v1/stock-opnames/${encodeURIComponent(state.current.id)}/finalize`, {
                        method: 'POST',
                        body: JSON.stringify({
                            allow_partial: Boolean(el.allowPartial.checked),
                        }),
                    });
                    closeFinalizeModal();
                    await loadHistory();
                    await loadDetail(state.current.id);
                    showAlert('Stock opname berhasil difinalisasi dan masuk kartu stok.', 'success');
                } catch (error) {
                    showAlert(error.message, 'danger');
                    renderFinalizeModal();
                } finally {
                    el.finalizeProceed.disabled = false;
                }
            };

            const loadMoreFinalizeChanges = async () => {
                if (!state.current || !state.finalizeCheck.hasMore) {
                    return;
                }

                const nextPage = state.finalizeCheck.page + 1;
                try {
                    const payload = await fetchFinalizeCheck(nextPage);
                    syncFinalizeCheck(payload, true);
                    renderFinalizeModal();
                } catch (error) {
                    showAlert(error.message, 'danger');
                }
            };

            const cancelDraft = async () => {
                if (!state.current || !await window.PosUi.confirmRiskActionAsync({
                    title: 'Batalkan Draft',
                    message: 'Draft stock opname ini akan dibatalkan. Lanjutkan?',
                    confirmText: 'Batalkan',
                    icon: 'cancel',
                })) {
                    return;
                }

                showAlert('Membatalkan draft...', 'info');
                try {
                    await apiFetch(`/api/v1/stock-opnames/${encodeURIComponent(state.current.id)}/cancel`, {
                        method: 'POST',
                    });
                    await loadHistory();
                    await loadDetail(state.current.id);
                    showAlert('Draft stock opname dibatalkan.', 'success');
                } catch (error) {
                    showAlert(error.message, 'danger');
                }
            };

            el.itemsBody.addEventListener('input', (event) => {
                const input = event.target.closest('.physical-input, .notes-input');
                if (!input || !state.current || state.current.status !== 'draft') {
                    return;
                }

                const item = state.items.find((candidate) => candidate.id === input.dataset.itemId);
                if (!item) {
                    return;
                }

                if (input.classList.contains('physical-input')) {
                    item.physical_qty = input.value === '' ? '' : Number(input.value);
                    recomputeItem(item);
                    const difference = Number(item.difference_qty || 0);
                    const differenceClass = difference < 0 ? 'text-rose-600' : difference > 0 ? 'text-emerald-600' : 'text-slate-500';
                    const row = input.closest('tr');
                    const diffQtyCell = row?.querySelector('.diff-qty');
                    const diffValueCell = row?.querySelector('.diff-value');
                    if (diffQtyCell) {
                        diffQtyCell.className = `diff-qty mt-1 text-sm font-black ${differenceClass}`;
                        diffQtyCell.textContent = signedQty(difference);
                    }
                    if (diffValueCell) {
                        diffValueCell.className = `diff-value mt-1 text-sm font-black ${differenceClass}`;
                        diffValueCell.textContent = formatMoney(item.difference_value);
                    }
                } else {
                    item.notes = input.value;
                }

                state.dirty.add(item.id);
                renderSummary();
            });

            el.historyList.addEventListener('click', (event) => {
                const button = event.target.closest('[data-history-id]');
                if (!button) {
                    return;
                }
                loadDetail(button.dataset.historyId).catch((error) => showAlert(error.message, 'danger'));
            });

            el.itemSearch.addEventListener('input', renderItems);
            el.countFilter.addEventListener('change', renderItems);
            el.buttons.createDraft.addEventListener('click', createDraft);
            el.buttons.saveDraft.addEventListener('click', saveDirty);
            el.buttons.finalize.addEventListener('click', finalize);
            el.buttons.cancelDraft.addEventListener('click', cancelDraft);
            el.buttons.resetForm.addEventListener('click', resetForm);
            el.buttons.refreshHistory.addEventListener('click', () => loadHistory().catch((error) => showAlert(error.message, 'danger')));
            el.buttons.new.addEventListener('click', resetForm);
            el.buttons.newTop.addEventListener('click', resetForm);
            el.buttons.history.addEventListener('click', () => document.getElementById('history-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            el.buttons.historyTop.addEventListener('click', () => document.getElementById('history-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            el.allowPartial.addEventListener('change', () => {
                renderSummary();
                if (!el.finalizeModal.classList.contains('hidden')) {
                    renderFinalizeModal();
                }
            });
            el.finalizeClose.addEventListener('click', closeFinalizeModal);
            el.finalizeCancel.addEventListener('click', closeFinalizeModal);
            el.finalizeProceed.addEventListener('click', proceedFinalize);
            el.finalizeLoadMore.addEventListener('click', loadMoreFinalizeChanges);

            const init = async () => {
                el.date.value = today;
                try {
                    await loadBootstrap();
                    await loadHistory();
                    const draft = state.opnames.find((opname) => opname.status === 'draft');
                    if (draft) {
                        await loadDetail(draft.id);
                    } else {
                        renderAll();
                    }
                } catch (error) {
                    showAlert(error.message, 'danger');
                    renderAll();
                }
            };

            init();
        })();
    </script>
@endpush
