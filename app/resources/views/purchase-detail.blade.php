@extends('layouts.app')

@php
    $purchaseDetailTenant = \App\Support\TenantContext::get();
    $purchaseDetailUser = auth()->user();
    $purchaseDetailRole = null;

    if (! $purchaseDetailTenant && filled($purchaseDetailUser?->tenant_id)) {
        $purchaseDetailTenant = \App\Models\Tenant::query()->find($purchaseDetailUser->tenant_id);
    }

    if ($purchaseDetailTenant && $purchaseDetailUser) {
        $purchaseDetailRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $purchaseDetailTenant->id, (string) $purchaseDetailUser->id);

        if (! $purchaseDetailRole && filled($purchaseDetailUser->role ?? null)) {
            $purchaseDetailRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $purchaseDetailUser->role);
        }
    }

    abort_unless(in_array($purchaseDetailRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManagePurchaseDetail = in_array($purchaseDetailRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Detail dan Persetujuan PO')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto pb-8')

@push('head')
        <style type="text/tailwindcss">
            .status-badge {
                @apply px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider;
            }
            .purchase-detail-main-has-action {
                padding-bottom: calc(env(safe-area-inset-bottom) + 12rem) !important;
            }
            #purchase-action-bar:not(.hidden) + #receipt-section {
                padding-bottom: calc(env(safe-area-inset-bottom) + 7rem);
            }
        </style>
@endpush

@php
    $purchasePortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $resolvedPurchaseId = (string) ($purchaseId ?? request()->query('purchase_id', ''));
    $purchaseCurrentPath = $currentPath ?? ($resolvedPurchaseId !== '' ? $purchasePortalPrefix.'/purchases/'.$resolvedPurchaseId : $purchasePortalPrefix.'/purchases');
    $purchaseBackUrl = $backUrl ?? $purchasePortalPrefix.'/purchases';
    $purchaseFlowDefaultMode = app(\App\Support\PurchaseFlowResolver::class)->resolveMode(null, $purchaseDetailTenant?->purchase_mode ?? null);
@endphp

@section('header')
    <x-page-header
        title="PO -"
        titleId="po-number"
        subtitle="-"
        subtitleId="po-supplier"
        :backUrl="$purchaseBackUrl"
        :showMenuButton="true"
    >
        <x-slot:actions>
            <div id="status-badge" class="status-badge bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ \App\Support\PurchaseStatus::label(\App\Support\PurchaseStatus::SUBMITTED) }}</div>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <section class="px-4 pt-3">
        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tanggal Pesan</p>
                    <p class="mt-1 text-sm font-semibold" id="order-date">-</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Estimasi Tiba</p>
                    <p class="mt-1 text-sm font-semibold" id="arrival-date">-</p>
                </div>
                @if ($canManagePurchaseDetail)
                    <div class="col-span-2 flex items-start justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/60">
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Pembayaran</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100" id="payment-method-label">-</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400" id="payment-detail-label">-</p>
                        </div>
                        <button id="edit-payment" class="shrink-0 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-bold text-primary" type="button">Ubah</button>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <section class="mt-3 px-4">
        <div class="flex items-center justify-between mb-3 px-1">
            <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Daftar Barang</h3>
            <div class="flex items-center gap-2">
                @if ($canManagePurchaseDetail)
                    <span id="draft-next-hint" class="hidden rounded-full bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">Draft belum diproses</span>
                    <button id="edit-draft" class="hidden rounded-full bg-primary/10 px-3 py-1.5 text-xs font-bold text-primary" type="button">Edit Draft</button>
                    <button id="delete-draft" class="hidden rounded-full bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-600 dark:bg-rose-900/20 dark:text-rose-300" type="button">Hapus Draft</button>
                @endif
                <span class="text-xs font-medium text-slate-400" id="item-count">0 Produk</span>
            </div>
        </div>
        <div class="space-y-2" id="item-list"></div>
        <div class="mt-3 rounded-xl border border-primary/20 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex justify-between items-center mb-1">
                <span class="text-xs text-slate-500">Subtotal</span>
                <span class="text-sm font-medium" id="subtotal">Rp 0</span>
            </div>
            <div class="flex justify-between items-center mb-3">
                <span class="text-xs text-slate-500">Pajak</span>
                <span class="text-sm font-medium text-red-500" id="tax">Rp 0</span>
            </div>
            <div class="flex justify-between items-center mb-3">
                <span class="text-xs text-slate-500">Biaya Tambahan</span>
                <span class="text-sm font-medium" id="additional-cost-total">Rp 0</span>
            </div>
            <div class="flex justify-between items-center pt-3 border-t border-primary/20">
                <span class="font-bold text-slate-900 dark:text-white">Total Akhir</span>
                <span class="text-lg font-bold text-primary" id="total">Rp 0</span>
            </div>
            @if ($canManagePurchaseDetail)
                <button id="edit-tax" class="mt-3 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300" type="button">Atur Pajak</button>
            @endif
        </div>
    </section>
    <section class="mt-3 px-4">
        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-2 flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Biaya Tambahan</h3>
                <span class="text-[10px] font-bold uppercase text-slate-400">Tidak masuk stok</span>
            </div>
            <div id="additional-cost-list" class="space-y-2"></div>
        </div>
    </section>
    <section class="mt-3 px-4">
        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Timeline Aktivitas</h3>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <div class="flex gap-2 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/60">
                    <div class="mt-1 size-2.5 shrink-0 rounded-full bg-emerald-500"></div>
                    <div class="min-w-0">
                        <p class="text-sm font-bold">Dibuat</p>
                        <p class="truncate text-xs text-slate-500" id="created-info">-</p>
                    </div>
                </div>
                <div class="flex gap-2 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800/60">
                    <div id="status-dot" class="mt-1 size-2.5 shrink-0 rounded-full bg-amber-400"></div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold" id="status-title">{{ \App\Support\PurchaseStatus::label(\App\Support\PurchaseStatus::SUBMITTED) }}</p>
                        <p class="truncate text-xs text-slate-500" id="status-info">Sistem</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @if ($canManagePurchaseDetail)
        <div id="purchase-action-bar" class="fixed inset-x-0 bottom-0 z-30 hidden bg-white/95 px-4 pt-4 backdrop-blur-xl dark:bg-slate-900/95 lg:left-72 app-bottom-safe-footer">
            <div class="mx-auto w-full max-w-3xl">
                <div id="draft-actions" class="mx-auto hidden w-full max-w-md flex flex-col gap-3">
                    <button id="submit-draft" class="flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-4 font-bold text-white shadow-lg shadow-primary/30" type="button">
                        <span class="material-symbols-outlined text-base">send</span>
                        <span id="submit-draft-label">Proses PO</span>
                    </button>
                    <p id="submit-draft-helper" class="text-center text-xs font-medium text-slate-500 dark:text-slate-400">Setelah diproses, PO bisa dilanjutkan ke penerimaan barang.</p>
                </div>
                <div id="approval-actions" class="flex gap-3">
                    <button id="reject" class="flex-1 py-4 px-6 rounded-xl border-2 border-slate-200 dark:border-slate-700 font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50" type="button">Tolak</button>
                    <button id="approve" class="flex-[2] py-4 px-6 rounded-xl bg-primary text-white font-bold shadow-lg shadow-primary/30 flex items-center justify-center gap-2" type="button">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        <span id="approve-label">Setujui Pembelian</span>
                    </button>
                </div>
                <div id="fulfillment-actions" class="hidden flex flex-col gap-3">
                    <a id="open-receiving" class="flex items-center justify-center py-4 px-6 rounded-xl bg-primary text-white font-bold shadow-lg shadow-primary/30" href="#">Terima Barang</a>
                    <button id="close-purchase" class="flex items-center justify-center gap-2 py-4 px-6 rounded-xl border-2 border-amber-200 bg-amber-50 font-bold text-amber-700 hover:bg-amber-100 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-300" type="button">
                        <span class="material-symbols-outlined text-base">event_busy</span>
                        <span>Tidak Ada Susulan</span>
                    </button>
                </div>
                <div class="mt-4 flex justify-center">
                    <div class="w-32 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
                </div>
            </div>
        </div>
    @endif

    <section id="receipt-section" class="mt-3 px-4 pb-6">
        <h3 class="mb-3 px-1 text-sm font-bold text-slate-500 uppercase tracking-widest">Riwayat Penerimaan</h3>
        <div id="receipt-history" class="space-y-3"></div>
    </section>

    @if ($canManagePurchaseDetail)
        <div id="payment-modal" class="fixed inset-0 z-50 hidden items-end justify-center bg-slate-950/40 px-4 py-6 sm:items-center">
            <div class="w-full max-w-[430px] max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Ubah Pembayaran</p>
                        <h4 class="text-base font-bold text-slate-900 dark:text-slate-100">Edit Tempo / Akun Kas</h4>
                    </div>
                    <button id="close-payment-modal" class="size-9 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800" type="button">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>
                <form id="payment-form" class="space-y-4 px-5 pt-5 pb-[calc(env(safe-area-inset-bottom)+1.25rem)]">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Metode Pembayaran</label>
                        <select id="payment-method-select" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-800">
                            <option value="tempo">Tempo</option>
                            <option value="tunai">Tunai</option>
                        </select>
                    </div>
                    <div id="payment-due-wrapper">
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal Tempo</label>
                        <input id="payment-due-date" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-800" type="date" />
                    </div>
                    <div id="payment-cash-wrapper">
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Akun Kas</label>
                        <select id="payment-cash-account" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-800"></select>
                        <p id="payment-cash-hint" class="mt-1.5 text-xs text-slate-400">Pilih akun kas yang benar jika pembayaran tunai.</p>
                    </div>
                    <p id="payment-feedback" class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-600"></p>
                    <div class="flex gap-3 pt-1">
                        <button id="cancel-payment-edit" class="flex-1 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300" type="button">Batal</button>
                        <button id="save-payment" class="flex-[2] rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-white shadow-lg shadow-primary/20" type="button">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="tax-modal" class="fixed inset-0 z-50 hidden items-end justify-center bg-slate-950/40 px-4 py-6 sm:items-center">
            <div class="w-full max-w-[430px] max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Pajak Pembelian</p>
                        <h4 class="text-base font-bold text-slate-900 dark:text-slate-100">Atur Pajak PO</h4>
                    </div>
                    <button id="close-tax-modal" class="size-9 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800" type="button">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>
                <form id="tax-form" class="space-y-4 px-5 pt-5 pb-[calc(env(safe-area-inset-bottom)+1.25rem)]">
                    <label class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                        <span>
                            <span class="block text-sm font-bold text-slate-900 dark:text-slate-100">Kenakan pajak</span>
                            <span class="block text-xs text-slate-500">Matikan untuk pembelian non-PPN.</span>
                        </span>
                        <input id="tax-enabled-edit" class="size-5 rounded border-slate-300 text-primary focus:ring-primary/20" type="checkbox" />
                    </label>
                    <div id="tax-rate-edit-wrapper" class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Tarif Pajak (%)</label>
                            <input id="tax-rate-edit" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold dark:border-slate-700 dark:bg-slate-800" type="number" min="0" max="100" step="0.1" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Nominal Pajak</label>
                            <input id="tax-amount-edit" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold dark:border-slate-700 dark:bg-slate-800" type="number" min="0" step="1" />
                        </div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm dark:bg-slate-800/70">
                        <div class="flex justify-between text-slate-500"><span>Subtotal</span><span id="tax-preview-subtotal">Rp 0</span></div>
                        <div class="mt-1 flex justify-between text-slate-500"><span>Pajak</span><span id="tax-preview-tax">Rp 0</span></div>
                        <div class="mt-2 flex justify-between border-t border-slate-200 pt-2 font-bold text-slate-900 dark:border-slate-700 dark:text-slate-100"><span>Total Baru</span><span id="tax-preview-total">Rp 0</span></div>
                    </div>
                    <p id="tax-feedback" class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-600"></p>
                    <div class="flex gap-3 pt-1">
                        <button id="cancel-tax-edit" class="flex-1 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300" type="button">Batal</button>
                        <button id="save-tax" class="flex-[2] rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-white shadow-lg shadow-primary/20" type="button">Simpan Pajak</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="draft-modal" class="fixed inset-0 z-50 hidden items-end justify-center bg-slate-950/40 px-4 py-6 sm:items-center">
            <div class="w-full max-w-[560px] max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white px-5 py-4 dark:border-slate-800 dark:bg-slate-900">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Draft PO</p>
                        <h4 class="text-base font-bold text-slate-900 dark:text-slate-100">Edit Tanggal dan Item</h4>
                    </div>
                    <button id="close-draft-modal" class="size-9 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800" type="button">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>
                <form id="draft-form" class="space-y-4 px-5 pt-5 pb-[calc(env(safe-area-inset-bottom)+1.25rem)]">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal Pesanan</label>
                            <input id="draft-transaction-date" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold dark:border-slate-700 dark:bg-slate-800" type="date" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Pemasok</label>
                            <select id="draft-supplier" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold dark:border-slate-700 dark:bg-slate-800"></select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Catatan</label>
                        <textarea id="draft-notes" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-800" rows="2" placeholder="Opsional"></textarea>
                    </div>
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Item Produk</p>
                            <button id="draft-add-item" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-bold text-primary" type="button">
                                <span class="material-symbols-outlined text-sm">add</span>
                                Tambah
                            </button>
                        </div>
                        <div id="draft-items" class="space-y-3"></div>
                    </div>
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Biaya Tambahan</p>
                            <button id="draft-add-additional-cost" class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-3 py-1.5 text-xs font-bold text-orange-700" type="button">
                                <span class="material-symbols-outlined text-sm">add</span>
                                Tambah
                            </button>
                        </div>
                        <div id="draft-additional-costs" class="space-y-3"></div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm dark:bg-slate-800/70">
                        <div class="flex justify-between text-slate-500"><span>Subtotal Baru</span><span id="draft-preview-subtotal">Rp 0</span></div>
                        <div class="mt-1 flex justify-between text-slate-500"><span>Biaya Tambahan</span><span id="draft-preview-additional-cost">Rp 0</span></div>
                        <div class="mt-1 flex justify-between text-slate-500"><span>Pajak</span><span id="draft-preview-tax">Rp 0</span></div>
                        <div class="mt-2 flex justify-between border-t border-slate-200 pt-2 font-bold text-slate-900 dark:border-slate-700 dark:text-slate-100"><span>Total Baru</span><span id="draft-preview-total">Rp 0</span></div>
                    </div>
                    <p id="draft-feedback" class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-600"></p>
                    <div class="flex gap-3 pt-1">
                        <button id="cancel-draft-edit" class="flex-1 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300" type="button">Batal</button>
                        <button id="save-draft-edit" class="flex-[2] rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-white shadow-lg shadow-primary/20" type="button">Simpan Draft</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @include('partials.ui-helpers')

        <script>
            (() => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const purchaseStatusLabels = @json(\App\Support\PurchaseStatus::labels());
                const purchaseStatusAliases = @json(\App\Support\PurchaseStatus::aliases());
                const purchaseStatusDraft = @json(\App\Support\PurchaseStatus::DRAFT);
                const purchaseStatusSubmitted = @json(\App\Support\PurchaseStatus::SUBMITTED);
                const purchaseModeControlled = @json(\App\Support\PurchaseMode::CONTROLLED);
                const purchaseModePoShort = @json(\App\Support\PurchaseMode::PO_SHORT);
                const purchaseFlowDefaultMode = @json($purchaseFlowDefaultMode);
                const purchaseBackUrl = @json($purchaseBackUrl);
                const purchasePaymentMethodLabels = {
                    tempo: 'Tempo',
                    tunai: 'Tunai',
                };

                const normalizePurchaseStatus = (status) => {
                    const value = String(status || '').toLowerCase();
                    return purchaseStatusAliases[value] || value;
                };

                const normalizePurchaseMode = (mode) => String(mode || '').toLowerCase() || purchaseFlowDefaultMode;
                const normalizePaymentMethod = (method) => {
                    const value = String(method || '').toLowerCase();
                    return ['tempo', 'tunai'].includes(value) ? value : 'tempo';
                };

                const el = {
                    poNumber: document.getElementById('po-number'),
                    poSupplier: document.getElementById('po-supplier'),
                    statusBadge: document.getElementById('status-badge'),
                    orderDate: document.getElementById('order-date'),
                    arrivalDate: document.getElementById('arrival-date'),
                    itemCount: document.getElementById('item-count'),
                    itemList: document.getElementById('item-list'),
                    subtotal: document.getElementById('subtotal'),
                    tax: document.getElementById('tax'),
                    additionalCostTotal: document.getElementById('additional-cost-total'),
                    additionalCostList: document.getElementById('additional-cost-list'),
                    total: document.getElementById('total'),
                    createdInfo: document.getElementById('created-info'),
                    statusTitle: document.getElementById('status-title'),
                    statusInfo: document.getElementById('status-info'),
                    statusDot: document.getElementById('status-dot'),
                    paymentMethodLabel: document.getElementById('payment-method-label'),
                    paymentDetailLabel: document.getElementById('payment-detail-label'),
                    pageMain: document.querySelector('main'),
                    actionBar: document.getElementById('purchase-action-bar'),
                    approvalActions: document.getElementById('approval-actions'),
                    fulfillmentActions: document.getElementById('fulfillment-actions'),
                    draftActions: document.getElementById('draft-actions'),
                    submitDraft: document.getElementById('submit-draft'),
                    submitDraftLabel: document.getElementById('submit-draft-label'),
                    submitDraftHelper: document.getElementById('submit-draft-helper'),
                    approve: document.getElementById('approve'),
                    approveLabel: document.getElementById('approve-label'),
                    reject: document.getElementById('reject'),
                    openReceiving: document.getElementById('open-receiving'),
                    closePurchase: document.getElementById('close-purchase'),
                    editDraft: document.getElementById('edit-draft'),
                    deleteDraft: document.getElementById('delete-draft'),
                    draftNextHint: document.getElementById('draft-next-hint'),
                    draftModal: document.getElementById('draft-modal'),
                    closeDraftModal: document.getElementById('close-draft-modal'),
                    cancelDraftEdit: document.getElementById('cancel-draft-edit'),
                    draftForm: document.getElementById('draft-form'),
                    draftTransactionDate: document.getElementById('draft-transaction-date'),
                    draftSupplier: document.getElementById('draft-supplier'),
                    draftNotes: document.getElementById('draft-notes'),
                    draftItems: document.getElementById('draft-items'),
                    draftAddItem: document.getElementById('draft-add-item'),
                    draftAdditionalCosts: document.getElementById('draft-additional-costs'),
                    draftAddAdditionalCost: document.getElementById('draft-add-additional-cost'),
                    draftPreviewSubtotal: document.getElementById('draft-preview-subtotal'),
                    draftPreviewAdditionalCost: document.getElementById('draft-preview-additional-cost'),
                    draftPreviewTax: document.getElementById('draft-preview-tax'),
                    draftPreviewTotal: document.getElementById('draft-preview-total'),
                    draftFeedback: document.getElementById('draft-feedback'),
                    saveDraftEdit: document.getElementById('save-draft-edit'),
                    editPayment: document.getElementById('edit-payment'),
                    paymentModal: document.getElementById('payment-modal'),
                    closePaymentModal: document.getElementById('close-payment-modal'),
                    cancelPaymentEdit: document.getElementById('cancel-payment-edit'),
                    paymentForm: document.getElementById('payment-form'),
                    paymentMethodSelect: document.getElementById('payment-method-select'),
                    paymentDueWrapper: document.getElementById('payment-due-wrapper'),
                    paymentDueDate: document.getElementById('payment-due-date'),
                    paymentCashWrapper: document.getElementById('payment-cash-wrapper'),
                    paymentCashAccount: document.getElementById('payment-cash-account'),
                    paymentCashHint: document.getElementById('payment-cash-hint'),
                    paymentFeedback: document.getElementById('payment-feedback'),
                    savePayment: document.getElementById('save-payment'),
                    editTax: document.getElementById('edit-tax'),
                    taxModal: document.getElementById('tax-modal'),
                    closeTaxModal: document.getElementById('close-tax-modal'),
                    cancelTaxEdit: document.getElementById('cancel-tax-edit'),
                    taxForm: document.getElementById('tax-form'),
                    taxEnabledEdit: document.getElementById('tax-enabled-edit'),
                    taxRateEditWrapper: document.getElementById('tax-rate-edit-wrapper'),
                    taxRateEdit: document.getElementById('tax-rate-edit'),
                    taxAmountEdit: document.getElementById('tax-amount-edit'),
                    taxPreviewSubtotal: document.getElementById('tax-preview-subtotal'),
                    taxPreviewTax: document.getElementById('tax-preview-tax'),
                    taxPreviewTotal: document.getElementById('tax-preview-total'),
                    taxFeedback: document.getElementById('tax-feedback'),
                    saveTax: document.getElementById('save-tax'),
                    receiptHistory: document.getElementById('receipt-history'),
                };

                let purchaseId = @json($resolvedPurchaseId) || new URLSearchParams(window.location.search).get('purchase_id');
                let currentPurchaseMode = purchaseFlowDefaultMode;
                let currentPurchasePaymentMethod = 'tempo';
                let currentPurchaseDueDate = null;
                let currentPurchaseCashAccountId = null;
                let currentPurchaseSubtotal = 0;
                let currentPurchaseTaxRate = 0;
                let currentPurchaseTax = 0;
                let currentPurchaseAdditionalCostTotal = 0;
                let currentPurchaseTotal = 0;
                let currentAdditionalCosts = [];
                let currentPurchaseTransactionDate = null;
                let currentPurchaseLocationId = null;
                let currentPurchaseNotes = null;
                let currentSupplierId = null;
                let currentItems = [];
                let currentPurchaseFlow = {
                    can_approve: false,
                    can_receive: false,
                    can_close: false,
                    purchase_mode: purchaseFlowDefaultMode,
                };
                let cashAccountOptions = [];
                let supplierOptions = [];
                let productOptions = [];

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const formatQuantity = (value) => {
                    const number = Number(value || 0);
                    return new Intl.NumberFormat('id-ID', {
                        maximumFractionDigits: Number.isInteger(number) ? 0 : 4,
                    }).format(number);
                };

                const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                }).format(new Date(value));

                const formatInputDate = (value) => {
                    if (!value) {
                        return '';
                    }

                    return String(value).slice(0, 10);
                };

                const escapeHtml = (value) => String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');

                const unitGroups = [
                    ['mg', 'gram', 'kg'],
                    ['ml', 'liter'],
                    ['cm', 'meter'],
                    ['pcs', 'unit', 'dozen', 'lusin'],
                ];

                const getProductUnit = (productId) => {
                    const product = productOptions.find((item) => item.id === productId);
                    return String(product?.unit || 'Pcs').trim() || 'Pcs';
                };

                const getCompatibleUnits = (unit) => {
                    const normalized = String(unit || '').trim().toLowerCase();
                    const group = unitGroups.find((items) => items.includes(normalized));
                    return group || (normalized ? [normalized] : ['pcs']);
                };

                const buildProductOptions = (selectedId = '') => [
                    `<option value="" ${selectedId ? '' : 'selected'} disabled>Pilih produk</option>`,
                    ...productOptions.map((product) => `<option value="${product.id}" ${product.id === selectedId ? 'selected' : ''}>${escapeHtml(product.name)}</option>`),
                ].join('');

                const buildSupplierOptions = (selectedId = '') => supplierOptions
                    .map((supplier) => `<option value="${supplier.id}" ${supplier.id === selectedId ? 'selected' : ''}>${escapeHtml(supplier.name)}</option>`)
                    .join('');

                const getCashAccountLabel = (accountId) => {
                    const account = cashAccountOptions.find((item) => item.id === accountId);
                    return account ? `${account.code} - ${account.name}` : 'Akun kas belum dipilih';
                };

                const setPaymentSummary = () => {
                    if (!el.paymentMethodLabel || !el.paymentDetailLabel) {
                        return;
                    }

                    const paymentMethod = normalizePaymentMethod(currentPurchasePaymentMethod);
                    el.paymentMethodLabel.textContent = purchasePaymentMethodLabels[paymentMethod] || 'Tempo';

                    if (paymentMethod === 'tunai') {
                        el.paymentDetailLabel.textContent = getCashAccountLabel(currentPurchaseCashAccountId);
                    } else {
                        el.paymentDetailLabel.textContent = currentPurchaseDueDate
                            ? `Jatuh tempo ${formatDate(currentPurchaseDueDate)}`
                            : 'Tanggal tempo belum diisi';
                    }
                };

                const renderTotals = () => {
                    if (el.subtotal) {
                        el.subtotal.textContent = formatCurrency(currentPurchaseSubtotal);
                    }
                    if (el.tax) {
                        const label = currentPurchaseTaxRate > 0
                            ? `${formatCurrency(currentPurchaseTax)} (${formatQuantity(currentPurchaseTaxRate)}%)`
                            : formatCurrency(currentPurchaseTax);
                        el.tax.textContent = label;
                    }
                    if (el.total) {
                        el.total.textContent = formatCurrency(currentPurchaseTotal);
                    }
                    if (el.additionalCostTotal) {
                        el.additionalCostTotal.textContent = formatCurrency(currentPurchaseAdditionalCostTotal);
                    }
                };

                const renderAdditionalCosts = (costs = []) => {
                    if (!el.additionalCostList) {
                        return;
                    }

                    if (!costs.length) {
                        el.additionalCostList.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 px-3 py-3 text-xs text-slate-400 dark:border-slate-800">Tidak ada biaya tambahan di PO ini.</div>';
                        return;
                    }

                    el.additionalCostList.innerHTML = costs.map((cost) => `
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">
                            <div class="min-w-0">
                                <p class="truncate font-bold text-slate-900 dark:text-slate-100">${escapeHtml(cost.name || 'Biaya tambahan')}</p>
                                <p class="mt-0.5 text-[11px] text-slate-500">${escapeHtml(cost.category || 'packaging')} · ${cost.posted ? 'sudah dijurnal' : 'belum dijurnal'}</p>
                            </div>
                            <span class="shrink-0 font-bold text-slate-900 dark:text-slate-100">${formatCurrency(cost.amount || 0)}</span>
                        </div>
                    `).join('');
                };

                const syncTaxPreview = () => {
                    if (!el.taxEnabledEdit) {
                        return;
                    }

                    const enabled = el.taxEnabledEdit.checked;
                    el.taxRateEditWrapper?.classList.toggle('hidden', !enabled);
                    const rate = enabled ? Math.max(0, Number(el.taxRateEdit?.value || 0)) : 0;
                    if (enabled && el.taxAmountEdit && document.activeElement !== el.taxAmountEdit) {
                        el.taxAmountEdit.value = String(Math.round(currentPurchaseSubtotal * (rate / 100)));
                    }
                    const amount = enabled ? Math.max(0, Number(el.taxAmountEdit?.value || 0)) : 0;
                    el.taxPreviewSubtotal.textContent = formatCurrency(currentPurchaseSubtotal);
                    el.taxPreviewTax.textContent = formatCurrency(amount);
                    el.taxPreviewTotal.textContent = formatCurrency(currentPurchaseSubtotal + amount + currentPurchaseAdditionalCostTotal);
                };

                const openTaxModal = () => {
                    if (!el.taxModal) {
                        return;
                    }

                    const enabled = currentPurchaseTax > 0 || currentPurchaseTaxRate > 0;
                    el.taxEnabledEdit.checked = enabled;
                    el.taxRateEdit.value = String(currentPurchaseTaxRate || 11);
                    el.taxAmountEdit.value = String(Math.round(currentPurchaseTax || (enabled ? currentPurchaseSubtotal * ((currentPurchaseTaxRate || 11) / 100) : 0)));
                    el.taxFeedback?.classList.add('hidden');
                    syncTaxPreview();
                    el.taxModal.classList.remove('hidden');
                    el.taxModal.classList.add('flex');
                };

                const closeTaxModal = () => {
                    if (!el.taxModal) {
                        return;
                    }

                    el.taxModal.classList.add('hidden');
                    el.taxModal.classList.remove('flex');
                };

                const syncPaymentForm = () => {
                    if (!el.paymentMethodSelect) {
                        return;
                    }

                    const paymentMethod = normalizePaymentMethod(el.paymentMethodSelect.value);
                    const isTempo = paymentMethod === 'tempo';
                    el.paymentDueWrapper?.classList.toggle('hidden', !isTempo);
                    el.paymentCashWrapper?.classList.toggle('hidden', isTempo);
                };

                const loadCashAccounts = async () => {
                    try {
                        const response = await fetch('/api/v1/chart-of-accounts?type=asset', { headers });
                        if (!response.ok) {
                            return [];
                        }

                        const data = await response.json();
                        const accounts = Array.isArray(data.mapping_accounts) && data.mapping_accounts.length
                            ? data.mapping_accounts
                            : (Array.isArray(data.accounts) ? data.accounts : []);

                        cashAccountOptions = accounts.map((account) => ({
                            id: account.id,
                            code: account.code,
                            name: account.name,
                        }));

                        if (el.paymentCashAccount) {
                            el.paymentCashAccount.innerHTML = cashAccountOptions.length
                                ? cashAccountOptions.map((account) => `<option value="${account.id}">${account.code} - ${account.name}</option>`).join('')
                                : '<option value="">Akun kas tidak tersedia</option>';
                        }

                        return cashAccountOptions;
                    } catch (error) {
                        cashAccountOptions = [];
                        if (el.paymentCashAccount) {
                            el.paymentCashAccount.innerHTML = '<option value="">Akun kas tidak tersedia</option>';
                        }
                        return [];
                    }
                };

                const loadDraftEditOptions = async () => {
                    if (supplierOptions.length && productOptions.length) {
                        return;
                    }

                    const [suppliersResponse, productsResponse] = await Promise.all([
                        fetch('/api/v1/suppliers', { headers }),
                        fetch('/api/v1/products', { headers }),
                    ]);

                    if (suppliersResponse.ok) {
                        const data = await suppliersResponse.json();
                        supplierOptions = data.suppliers || [];
                    }

                    if (productsResponse.ok) {
                        const data = await productsResponse.json();
                        productOptions = data.products || [];
                    }
                };

                const updateDraftRowUnit = (row, productId, preferredUnit = null) => {
                    const unitSelect = row.querySelector('.draft-item-unit');
                    if (!unitSelect) {
                        return;
                    }

                    const stockUnit = getProductUnit(productId);
                    unitSelect.innerHTML = getCompatibleUnits(stockUnit)
                        .map((unit) => `<option value="${escapeHtml(unit)}">${escapeHtml(unit)}</option>`)
                        .join('');
                    unitSelect.value = String(preferredUnit || stockUnit).trim().toLowerCase();
                    if (!unitSelect.value && unitSelect.options.length) {
                        unitSelect.selectedIndex = 0;
                    }
                };

                const collectDraftAdditionalCosts = () => {
                    const costs = [];
                    el.draftAdditionalCosts?.querySelectorAll('.draft-additional-cost-row').forEach((row) => {
                        const name = row.querySelector('.draft-additional-cost-name')?.value.trim() || '';
                        const amount = Number(row.querySelector('.draft-additional-cost-amount')?.value || 0);
                        const category = row.querySelector('.draft-additional-cost-category')?.value || 'packaging';
                        if (name && amount > 0) {
                            costs.push({ name, category, amount });
                        }
                    });

                    return costs;
                };

                const draftAdditionalCostTotal = () => collectDraftAdditionalCosts()
                    .reduce((sum, cost) => sum + Number(cost.amount || 0), 0);

                const syncDraftTotals = () => {
                    let subtotal = 0;
                    el.draftItems?.querySelectorAll('.draft-item-row').forEach((row) => {
                        const qty = Number(row.querySelector('.draft-item-qty')?.value || 0);
                        const price = Number(row.querySelector('.draft-item-price')?.value || 0);
                        const lineTotal = qty * price;
                        subtotal += lineTotal;
                        const lineSubtotal = row.querySelector('.draft-item-subtotal');
                        if (lineSubtotal) {
                            lineSubtotal.textContent = formatCurrency(lineTotal);
                        }
                    });

                    const taxEnabled = currentPurchaseTax > 0 || currentPurchaseTaxRate > 0;
                    const taxRate = taxEnabled ? Number(currentPurchaseTaxRate || 0) : 0;
                    const tax = taxEnabled ? Math.round(subtotal * (taxRate / 100)) : 0;
                    const additionalCost = draftAdditionalCostTotal();
                    el.draftPreviewSubtotal.textContent = formatCurrency(subtotal);
                    el.draftPreviewAdditionalCost.textContent = formatCurrency(additionalCost);
                    el.draftPreviewTax.textContent = taxRate > 0 ? `${formatCurrency(tax)} (${formatQuantity(taxRate)}%)` : formatCurrency(tax);
                    el.draftPreviewTotal.textContent = formatCurrency(subtotal + tax + additionalCost);
                };

                const addDraftItemRow = (item = {}) => {
                    const row = document.createElement('div');
                    const productId = item.product_id || '';
                    const purchaseQty = Number(item.purchase_qty ?? item.qty ?? (productId ? 1 : 0));
                    const purchasePrice = Number(item.purchase_price ?? item.price ?? 0);
                    row.className = 'draft-item-row rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900';
                    row.innerHTML = `
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Produk</label>
                                <select class="draft-item-product w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold dark:border-slate-700 dark:bg-slate-800">
                                    ${buildProductOptions(productId)}
                                </select>
                            </div>
                            <button class="draft-remove-item mt-6 size-10 rounded-xl border border-rose-100 bg-rose-50 text-rose-500" type="button">
                                <span class="material-symbols-outlined text-lg">delete</span>
                            </button>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Qty</label>
                                <div class="flex gap-2">
                                    <input class="draft-item-qty min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-bold dark:border-slate-700 dark:bg-slate-800" type="number" min="0.0001" step="0.0001" value="${purchaseQty}" />
                                    <select class="draft-item-unit w-24 rounded-xl border border-slate-200 bg-slate-50 px-2 py-2.5 text-xs font-bold dark:border-slate-700 dark:bg-slate-800"></select>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Harga</label>
                                <input class="draft-item-price w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-bold dark:border-slate-700 dark:bg-slate-800" type="number" min="0" step="1" value="${purchasePrice}" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/70">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Subtotal Item</span>
                            <span class="draft-item-subtotal font-bold text-primary">${formatCurrency(purchaseQty * purchasePrice)}</span>
                        </div>
                    `;

                    if (item.product_id) {
                        el.draftItems.appendChild(row);
                    } else {
                        el.draftItems.prepend(row);
                    }
                    const productSelect = row.querySelector('.draft-item-product');
                    updateDraftRowUnit(row, productId, item.purchase_unit || item.unit || null);
                    productSelect?.addEventListener('change', () => {
                        updateDraftRowUnit(row, productSelect.value);
                        syncDraftTotals();
                    });
                    row.querySelectorAll('input, select').forEach((input) => input.addEventListener('input', syncDraftTotals));
                    row.querySelector('.draft-remove-item')?.addEventListener('click', () => {
                        row.remove();
                        syncDraftTotals();
                    });
                    syncDraftTotals();
                };

                const addDraftAdditionalCostRow = (cost = {}) => {
                    const row = document.createElement('div');
                    row.className = 'draft-additional-cost-row rounded-2xl border border-orange-100 bg-orange-50/70 p-3 dark:border-orange-900/40 dark:bg-orange-950/20';
                    row.innerHTML = `
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Nama Biaya</label>
                                <input class="draft-additional-cost-name w-full rounded-xl border border-orange-100 bg-white px-3 py-2.5 text-sm font-bold dark:border-orange-900/40 dark:bg-slate-900" type="text" value="${escapeHtml(cost.name || '')}" placeholder="Plastik / Kardus / Ongkir" />
                            </div>
                            <button class="draft-remove-additional-cost mt-6 size-10 rounded-xl border border-rose-100 bg-rose-50 text-rose-500" type="button">
                                <span class="material-symbols-outlined text-lg">delete</span>
                            </button>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Nominal</label>
                                <input class="draft-additional-cost-amount w-full rounded-xl border border-orange-100 bg-white px-3 py-2.5 text-sm font-bold dark:border-orange-900/40 dark:bg-slate-900" type="number" min="0" step="1" value="${Number(cost.amount || 0) || ''}" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Kategori</label>
                                <select class="draft-additional-cost-category w-full rounded-xl border border-orange-100 bg-white px-3 py-2.5 text-xs font-bold dark:border-orange-900/40 dark:bg-slate-900">
                                    <option value="packaging">Kemasan</option>
                                    <option value="shipping">Ongkir</option>
                                    <option value="admin">Admin</option>
                                    <option value="other">Lainnya</option>
                                </select>
                            </div>
                        </div>
                    `;
                    if (cost.name || cost.amount) {
                        el.draftAdditionalCosts?.appendChild(row);
                    } else {
                        el.draftAdditionalCosts?.prepend(row);
                    }
                    row.querySelector('.draft-additional-cost-category').value = cost.category || 'packaging';
                    row.querySelectorAll('input, select').forEach((input) => {
                        input.addEventListener('input', syncDraftTotals);
                        input.addEventListener('change', syncDraftTotals);
                    });
                    row.querySelector('.draft-remove-additional-cost')?.addEventListener('click', () => {
                        row.remove();
                        syncDraftTotals();
                    });
                    syncDraftTotals();
                };

                const openDraftModal = async () => {
                    if (!el.draftModal) {
                        return;
                    }

                    await loadDraftEditOptions();
                    el.draftSupplier.innerHTML = buildSupplierOptions(currentSupplierId);
                    el.draftTransactionDate.value = formatInputDate(currentPurchaseTransactionDate) || formatInputDate(new Date().toISOString());
                    el.draftNotes.value = currentPurchaseNotes || '';
                    el.draftItems.innerHTML = '';
                    (currentItems.length ? currentItems : [{}]).forEach((item) => addDraftItemRow(item));
                    if (el.draftAdditionalCosts) {
                        el.draftAdditionalCosts.innerHTML = '';
                        currentAdditionalCosts.forEach((cost) => addDraftAdditionalCostRow(cost));
                    }
                    if (el.draftFeedback) {
                        el.draftFeedback.classList.add('hidden');
                        el.draftFeedback.textContent = '';
                    }
                    syncDraftTotals();
                    el.draftModal.classList.remove('hidden');
                    el.draftModal.classList.add('flex');
                };

                const closeDraftModal = () => {
                    if (!el.draftModal) {
                        return;
                    }

                    el.draftModal.classList.add('hidden');
                    el.draftModal.classList.remove('flex');
                };

                const openPaymentModal = () => {
                    if (!el.paymentModal) {
                        return;
                    }

                    if (el.paymentMethodSelect) {
                        el.paymentMethodSelect.value = normalizePaymentMethod(currentPurchasePaymentMethod);
                    }
                    if (el.paymentDueDate) {
                        el.paymentDueDate.value = formatInputDate(currentPurchaseDueDate);
                    }
                    if (el.paymentCashAccount) {
                        el.paymentCashAccount.value = currentPurchaseCashAccountId || cashAccountOptions[0]?.id || '';
                    }

                    syncPaymentForm();
                    if (el.paymentFeedback) {
                        el.paymentFeedback.classList.add('hidden');
                        el.paymentFeedback.textContent = '';
                    }
                    el.paymentModal.classList.remove('hidden');
                    el.paymentModal.classList.add('flex');
                };

                const closePaymentModal = () => {
                    if (!el.paymentModal) {
                        return;
                    }

                    el.paymentModal.classList.add('hidden');
                    el.paymentModal.classList.remove('flex');
                };

                const loadPurchase = async () => {
                    if (!purchaseId) {
                        throw new Error('Detail PO membutuhkan ID pembelian yang valid.');
                    }
                    const response = await fetch(`/api/v1/purchases/${purchaseId}`, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat detail PO.');
                    }
                    return response.json();
                };

                const setStatus = (status, purchaseMode) => {
                    const normalizedStatus = normalizePurchaseStatus(status);
                    const normalizedMode = normalizePurchaseMode(purchaseMode);
                    const label = purchaseStatusLabels[normalizedStatus] || purchaseStatusLabels.draft;
                    el.statusBadge.textContent = label;
                    if (normalizedStatus === 'approved') {
                        el.statusBadge.className = 'status-badge bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
                        el.statusDot.className = 'mt-1 size-2.5 shrink-0 rounded-full bg-emerald-500';
                    } else if (normalizedStatus === 'received' || normalizedStatus === 'closed') {
                        el.statusBadge.className = 'status-badge bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
                        el.statusDot.className = 'mt-1 size-2.5 shrink-0 rounded-full bg-emerald-500';
                    } else if (normalizedStatus === 'partially_received') {
                        el.statusBadge.className = 'status-badge bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300';
                        el.statusDot.className = 'mt-1 size-2.5 shrink-0 rounded-full bg-sky-500';
                    } else if (normalizedStatus === 'cancelled') {
                        el.statusBadge.className = 'status-badge bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400';
                        el.statusDot.className = 'mt-1 size-2.5 shrink-0 rounded-full bg-rose-400';
                    } else if (normalizedStatus === 'submitted') {
                        el.statusBadge.className = 'status-badge bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
                        el.statusDot.className = 'mt-1 size-2.5 shrink-0 rounded-full bg-amber-400';
                    } else {
                        el.statusBadge.className = 'status-badge bg-slate-100 text-slate-600 dark:bg-slate-800/70 dark:text-slate-300';
                        el.statusDot.className = 'mt-1 size-2.5 shrink-0 rounded-full bg-slate-400';
                    }
                    if (normalizedStatus === 'submitted' && normalizedMode === purchaseModeControlled) {
                        el.statusTitle.textContent = 'Menunggu Persetujuan Manajer';
                    } else if (normalizedStatus === 'submitted' && normalizedMode === purchaseModePoShort) {
                        el.statusTitle.textContent = 'Siap Diterima';
                    } else if (normalizedStatus === 'received') {
                        el.statusTitle.textContent = 'Selesai - Semua Barang Diterima';
                    } else if (normalizedStatus === 'partially_received') {
                        el.statusTitle.textContent = 'Diterima Sebagian - Menunggu Susulan';
                    } else if (normalizedStatus === 'closed') {
                        el.statusTitle.textContent = 'Selesai - Sisa Tidak Disusulkan';
                    } else if (normalizedStatus === purchaseStatusDraft) {
                        el.statusTitle.textContent = 'Draft - Perlu Diproses';
                    } else {
                        el.statusTitle.textContent = label;
                    }
                    const showDraftAction = normalizedStatus === purchaseStatusDraft;
                    const showApproval = Boolean(currentPurchaseFlow.can_approve) && normalizedStatus === purchaseStatusSubmitted;
                    const showFulfillment = Boolean(currentPurchaseFlow.can_receive) || Boolean(currentPurchaseFlow.can_close);
                    const showActionBar = showDraftAction || showApproval || showFulfillment;

                    el.editDraft?.classList.toggle('hidden', normalizedStatus !== purchaseStatusDraft);
                    el.deleteDraft?.classList.toggle('hidden', normalizedStatus !== purchaseStatusDraft);
                    el.draftNextHint?.classList.toggle('hidden', normalizedStatus !== purchaseStatusDraft);
                    el.actionBar?.classList.toggle('hidden', !showActionBar);
                    el.pageMain?.classList.toggle('purchase-detail-main-has-action', showActionBar);
                    el.draftActions?.classList.toggle('hidden', !showDraftAction);
                    el.approvalActions?.classList.toggle('hidden', !showApproval);
                    el.fulfillmentActions?.classList.toggle('hidden', !showFulfillment);

                    if (el.submitDraft) {
                        el.submitDraft.disabled = !showDraftAction;
                    }
                    if (el.submitDraftLabel) {
                        el.submitDraftLabel.textContent = normalizedMode === purchaseModeControlled ? 'Ajukan Persetujuan PO' : 'Proses PO';
                    }
                    if (el.submitDraftHelper) {
                        el.submitDraftHelper.textContent = normalizedMode === purchaseModeControlled
                            ? 'Draft akan masuk tahap persetujuan sebelum barang bisa diterima.'
                            : 'Draft akan menjadi siap diproses, lalu bisa lanjut ke penerimaan barang.';
                    }

                    if (el.approve) {
                        el.approve.disabled = !showApproval;
                    }
                    if (el.reject) {
                        el.reject.disabled = !showApproval;
                    }
                    if (el.openReceiving) {
                        el.openReceiving.classList.toggle('hidden', !currentPurchaseFlow.can_receive);
                    }
                    if (el.closePurchase) {
                        el.closePurchase.classList.toggle('hidden', !currentPurchaseFlow.can_close);
                        el.closePurchase.innerHTML = '<span class="material-symbols-outlined text-base">event_busy</span><span>Tidak Ada Susulan</span>';
                    }
                    if (el.approveLabel) {
                        el.approveLabel.textContent = 'Setujui Pembelian';
                    }
                };

                const renderReceiptHistory = (receipts) => {
                    if (!el.receiptHistory) {
                        return;
                    }

                    if (!receipts.length) {
                        el.receiptHistory.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-white px-4 py-4 text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Belum ada batch penerimaan.</div>';
                        return;
                    }

                    el.receiptHistory.innerHTML = receipts.map((receipt) => `
                        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Batch Penerimaan</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">${formatDate(receipt.received_at)}</p>
                                    <p class="mt-1 text-xs text-slate-500">Oleh ${receipt.received_by_name || 'Sistem'}</p>
                                </div>
                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-bold uppercase text-emerald-700">${formatQuantity(receipt.total_purchase_qty || receipt.total_qty || 0)} item</span>
                            </div>
                            <div class="mt-2 space-y-2">
                                ${(receipt.items || []).map((item) => `
                                    <div class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/60">
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-slate-900 dark:text-slate-100">${item.product_name}</p>
                                            <p class="text-xs text-slate-500">SKU: ${item.product_sku || '-'}</p>
                                            ${item.product_variant_name ? `<p class="text-xs text-slate-500">Varian: ${item.product_variant_name}</p>` : ''}
                                        </div>
                                        <span class="shrink-0 font-bold text-primary">${formatQuantity(item.received_purchase_qty ?? item.received_qty)} / ${formatQuantity(item.ordered_purchase_qty ?? item.ordered_qty)} ${item.purchase_unit || ''}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `).join('');
                };

                const renderItems = (items) => {
                    el.itemList.innerHTML = '';
                    el.itemCount.textContent = `${items.length} Produk`;

                    items.forEach((item) => {
                        const row = document.createElement('div');
                        const purchaseQty = Number(item.purchase_qty ?? item.qty ?? 0);
                        const stockQty = Number(item.qty ?? 0);
                        const purchasePrice = Number(item.purchase_price ?? item.price ?? 0);
                        const lineTotal = purchaseQty * purchasePrice;
                        row.className = 'bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm';
                        row.innerHTML = `
                            <div class="flex justify-between items-start gap-3">
                                <div class="flex-1 pr-3">
                                    <h4 class="font-bold text-sm">${item.product_name}</h4>
                                    <p class="text-xs text-slate-500">SKU: ${item.product_sku || '-'} - Qty beli: ${formatQuantity(purchaseQty)} ${item.purchase_unit || item.unit || 'Pcs'} - Stok masuk: ${formatQuantity(stockQty)} ${item.unit || 'Pcs'}</p>
                                    ${item.product_variant_name ? `<p class="text-xs text-slate-500">Varian: ${item.product_variant_name}</p>` : ''}
                                </div>
                                <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary">${formatCurrency(lineTotal)}</span>
                            </div>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-3 py-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Qty</span>
                                    <span class="mt-1 block text-sm font-bold text-slate-900 dark:text-slate-100">${formatQuantity(purchaseQty)} ${item.purchase_unit || item.unit || 'Pcs'}</span>
                                </div>
                                <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-3 py-2">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Harga Satuan</span>
                                    <span class="mt-1 block text-sm font-bold text-slate-900 dark:text-slate-100">${formatCurrency(purchasePrice)}</span>
                                </div>
                                <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-3 py-2 text-right">
                                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Subtotal Item</span>
                                    <span class="mt-1 block text-sm font-bold text-primary">${formatCurrency(lineTotal)}</span>
                                </div>
                            </div>
                        `;
                        el.itemList.appendChild(row);
                    });
                };

                const applyPurchaseData = async (data) => {
                    const items = data.items || [];
                    const fallbackSubtotal = items.reduce((sum, item) => sum + (Number(item.purchase_qty ?? item.qty) * Number(item.purchase_price ?? item.price)), 0);
                    currentItems = items;
                    currentSupplierId = data.supplier?.id || null;
                    currentPurchaseSubtotal = Number(data.purchase.subtotal ?? fallbackSubtotal);
                    currentPurchaseTaxRate = Number(data.purchase.tax_rate || 0);
                    currentPurchaseTax = Number(data.purchase.tax || 0);
                    currentPurchaseAdditionalCostTotal = Number(data.purchase.additional_cost_total || 0);
                    currentPurchaseTotal = Number(data.purchase.total ?? (currentPurchaseSubtotal + currentPurchaseTax + currentPurchaseAdditionalCostTotal));
                    currentAdditionalCosts = data.additional_costs || [];
                    currentPurchaseMode = normalizePurchaseMode(data.purchase.purchase_mode || purchaseFlowDefaultMode);
                    currentPurchaseFlow = data.flow || currentPurchaseFlow;
                    currentPurchasePaymentMethod = data.purchase.payment_method || 'tempo';
                    currentPurchaseDueDate = data.purchase.due_date || null;
                    currentPurchaseCashAccountId = data.purchase.cash_account_id || null;
                    currentPurchaseTransactionDate = data.purchase.transaction_date || data.purchase.created_at || null;
                    currentPurchaseLocationId = data.purchase.location_id || null;
                    currentPurchaseNotes = data.purchase.notes || null;

                    await loadCashAccounts();

                    el.poNumber.textContent = `PO #${String(data.purchase.id).slice(0, 6).toUpperCase()}`;
                    el.poSupplier.textContent = `${data.supplier?.name || 'Pemasok'} - Lokasi: ${data.purchase.location_name || '-'}`;
                    el.orderDate.textContent = formatDate(currentPurchaseTransactionDate);
                    el.arrivalDate.textContent = formatDate(currentPurchaseTransactionDate);
                    el.createdInfo.textContent = `Sistem - ${formatDate(data.purchase.created_at)}`;
                    el.statusInfo.textContent = 'Sistem';
                    setPaymentSummary();
                    renderTotals();
                    renderItems(items);
                    renderAdditionalCosts(data.additional_costs || []);
                    renderReceiptHistory(data.receipts || []);
                    setStatus(data.purchase.status || purchaseStatusSubmitted, currentPurchaseMode);
                };

                const decide = async (status) => {
                    const response = await fetch(`/api/v1/purchases/${purchaseId}/decision`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                            'X-Requested-With': 'XMLHttpRequest',
                            ...headers,
                        },
                        body: JSON.stringify({ status }),
                    });
                    if (!response.ok) {
                        const mapped = await window.PosUi.mapApiError(response, 'Gagal memperbarui status PO.');
                        alert(mapped.message);
                        return;
                    }
                    const responseData = await response.json().catch(() => ({}));
                    setStatus(responseData.status || status);
                };

                if (el.approve && el.reject) {
                    el.approve.addEventListener('click', () => decide('approved'));
                    el.reject.addEventListener('click', () => decide('cancelled'));
                }

                el.submitDraft?.addEventListener('click', async () => {
                    const isControlled = normalizePurchaseMode(currentPurchaseMode) === purchaseModeControlled;
                    const allowed = await window.PosUi.confirmRiskActionAsync({
                        title: isControlled ? 'Ajukan Persetujuan PO' : 'Proses Draft PO',
                        message: isControlled
                            ? 'Draft PO akan diajukan untuk persetujuan. Setelah disetujui, barang bisa diterima.'
                            : 'Draft PO akan diproses menjadi siap diterima. Lanjutkan?',
                        confirmText: isControlled ? 'Ajukan' : 'Proses PO',
                        icon: 'send',
                    });
                    if (!allowed) {
                        return;
                    }

                    await window.PosUi.runWithSubmitGuard(el.submitDraft, async () => {
                        const response = await fetch(`/api/v1/purchases/${purchaseId}/submit`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                                'X-Requested-With': 'XMLHttpRequest',
                                ...headers,
                            },
                        });
                        const responseData = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            throw new Error(responseData.message || 'Gagal memproses draft PO.');
                        }

                        const refreshed = await loadPurchase();
                        currentPurchaseFlow = refreshed.flow || currentPurchaseFlow;
                        await applyPurchaseData(refreshed);
                    }, {
                        loadingHtml: '<span class="material-symbols-outlined animate-spin">progress_activity</span> MEMPROSES',
                    });
                });

                const buildReceivingActionUrl = () => {
                    return `${@json($purchasePortalPrefix)}/receiving/${encodeURIComponent(purchaseId)}/action?from=${encodeURIComponent(@json($purchaseCurrentPath))}`;
                };

                el.openReceiving?.addEventListener('click', () => {
                    window.location.href = buildReceivingActionUrl();
                });

                el.closePurchase?.addEventListener('click', async () => {
                    if (!purchaseId) {
                        return;
                    }

                    const allowed = await window.PosUi.confirmRiskActionAsync({
                        title: 'Tidak Ada Barang Susulan',
                        message: 'Gunakan ini hanya kalau supplier tidak akan mengirim sisa barang. PO akan diselesaikan tanpa menambah stok lagi.',
                        confirmText: 'Selesaikan PO',
                        icon: 'event_busy',
                    });
                    if (!allowed) {
                        return;
                    }

                    await window.PosUi.runWithSubmitGuard(el.closePurchase, async () => {
                        const response = await fetch(`/api/v1/purchases/${purchaseId}/close`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                                'X-Requested-With': 'XMLHttpRequest',
                                ...headers,
                            },
                        });

                        const responseData = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            throw new Error(responseData.message || 'Gagal menutup purchase.');
                        }

                        const refreshed = await loadPurchase();
                        currentPurchaseFlow = refreshed.flow || currentPurchaseFlow;
                        setStatus(refreshed.purchase.status || purchaseStatusSubmitted, refreshed.purchase.purchase_mode || currentPurchaseMode);
                    }, {
                        loadingHtml: '<span class="material-symbols-outlined animate-spin">progress_activity</span> MENUTUP',
                    });
                });

                if (el.editDraft && el.draftModal) {
                    el.editDraft.addEventListener('click', openDraftModal);
                    el.closeDraftModal?.addEventListener('click', closeDraftModal);
                    el.cancelDraftEdit?.addEventListener('click', closeDraftModal);
                    el.draftModal.addEventListener('click', (event) => {
                        if (event.target === el.draftModal) {
                            closeDraftModal();
                        }
                    });
                    el.draftAddItem?.addEventListener('click', () => addDraftItemRow({}));
                    el.draftAddAdditionalCost?.addEventListener('click', () => addDraftAdditionalCostRow({}));
                    el.saveDraftEdit?.addEventListener('click', (event) => {
                        event.preventDefault();
                        el.draftForm?.requestSubmit();
                    });
                    el.draftForm?.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        const items = [];
                        el.draftItems.querySelectorAll('.draft-item-row').forEach((row) => {
                            const productId = row.querySelector('.draft-item-product')?.value || '';
                            const qty = Number(row.querySelector('.draft-item-qty')?.value || 0);
                            const price = Number(row.querySelector('.draft-item-price')?.value || 0);
                            const purchaseUnit = row.querySelector('.draft-item-unit')?.value || getProductUnit(productId);
                            if (productId && qty > 0) {
                                items.push({
                                    product_id: productId,
                                    qty,
                                    purchase_unit: purchaseUnit,
                                    price,
                                });
                            }
                        });

                        if (!el.draftTransactionDate.value) {
                            el.draftFeedback.textContent = 'Tanggal pesanan wajib diisi.';
                            el.draftFeedback.classList.remove('hidden');
                            return;
                        }

                        if (!el.draftSupplier.value) {
                            el.draftFeedback.textContent = 'Pemasok wajib dipilih.';
                            el.draftFeedback.classList.remove('hidden');
                            return;
                        }

                        if (!items.length) {
                            el.draftFeedback.textContent = 'Tambahkan minimal satu produk dengan qty lebih dari 0.';
                            el.draftFeedback.classList.remove('hidden');
                            return;
                        }

                        const subtotal = items.reduce((sum, item) => sum + (Number(item.qty || 0) * Number(item.price || 0)), 0);
                        const taxEnabled = currentPurchaseTax > 0 || currentPurchaseTaxRate > 0;
                        const taxRate = taxEnabled ? Number(currentPurchaseTaxRate || 0) : 0;
                        const tax = taxEnabled ? Math.round(subtotal * (taxRate / 100)) : 0;
                        const additionalCosts = collectDraftAdditionalCosts();
                        const additionalCost = additionalCosts.reduce((sum, cost) => sum + Number(cost.amount || 0), 0);
                        const paymentMethod = normalizePaymentMethod(currentPurchasePaymentMethod);
                        const payload = {
                            supplier_id: el.draftSupplier.value,
                            location_id: currentPurchaseLocationId,
                            transaction_date: el.draftTransactionDate.value,
                            payment_method: paymentMethod,
                            due_date: paymentMethod === 'tempo' ? currentPurchaseDueDate : null,
                            cash_account_id: paymentMethod === 'tunai' ? currentPurchaseCashAccountId : null,
                            notes: el.draftNotes.value.trim() || null,
                            tax_enabled: taxEnabled,
                            tax_rate: taxRate,
                            tax,
                            additional_costs: additionalCosts,
                            items,
                        };

                        const allowed = await window.PosUi.confirmRiskActionAsync({
                            title: 'Simpan Perubahan Draft',
                            message: `Tanggal: ${formatDate(payload.transaction_date)}\nItem: ${items.length}\nBiaya tambahan: ${formatCurrency(additionalCost)}\nTotal Baru: ${formatCurrency(subtotal + tax + additionalCost)}`,
                            confirmText: 'Simpan Draft',
                            icon: 'edit_note',
                        });
                        if (!allowed) {
                            return;
                        }

                        await window.PosUi.runWithSubmitGuard(el.saveDraftEdit, async () => {
                            if (el.draftFeedback) {
                                el.draftFeedback.classList.add('hidden');
                                el.draftFeedback.textContent = '';
                            }

                            const response = await fetch(`/api/v1/purchases/${purchaseId}/draft`, {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                                    'X-Requested-With': 'XMLHttpRequest',
                                    ...headers,
                                },
                                body: JSON.stringify(payload),
                            });
                            const responseData = await response.json().catch(() => ({}));
                            if (!response.ok) {
                                const message = responseData.message || 'Gagal menyimpan draft PO.';
                                if (el.draftFeedback) {
                                    el.draftFeedback.textContent = message;
                                    el.draftFeedback.classList.remove('hidden');
                                }
                                throw new Error(message);
                            }

                            const refreshed = await loadPurchase();
                            await applyPurchaseData(refreshed);
                            closeDraftModal();
                        }, {
                            loadingHtml: '<span class="material-symbols-outlined animate-spin">progress_activity</span> MENYIMPAN',
                        });
                    });
                }

                el.deleteDraft?.addEventListener('click', async () => {
                    const allowed = await window.PosUi.confirmRiskActionAsync({
                        title: 'Hapus Draft PO',
                        message: 'Draft PO ini akan dihapus permanen dari daftar pembelian. Lanjutkan?',
                        confirmText: 'Hapus Draft',
                        icon: 'delete',
                        destructive: true,
                    });
                    if (!allowed) {
                        return;
                    }

                    await window.PosUi.runWithSubmitGuard(el.deleteDraft, async () => {
                        const response = await fetch(`/api/v1/purchases/${purchaseId}`, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                                'X-Requested-With': 'XMLHttpRequest',
                                ...headers,
                            },
                        });
                        const responseData = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            throw new Error(responseData.message || 'Gagal menghapus draft PO.');
                        }

                        window.location.href = purchaseBackUrl;
                    }, {
                        loadingHtml: '<span class="material-symbols-outlined animate-spin">progress_activity</span> MENGHAPUS',
                    });
                });

                if (el.editPayment && el.paymentModal) {
                    el.editPayment.addEventListener('click', openPaymentModal);
                    el.closePaymentModal?.addEventListener('click', closePaymentModal);
                    el.cancelPaymentEdit?.addEventListener('click', closePaymentModal);
                    el.paymentModal.addEventListener('click', (event) => {
                        if (event.target === el.paymentModal) {
                            closePaymentModal();
                        }
                    });

                    el.paymentMethodSelect?.addEventListener('change', syncPaymentForm);
                    el.savePayment?.addEventListener('click', (event) => {
                        event.preventDefault();
                        el.paymentForm?.requestSubmit();
                    });

                    el.paymentForm?.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        const paymentMethod = normalizePaymentMethod(el.paymentMethodSelect?.value);
                        const payload = { payment_method: paymentMethod };

                        if (paymentMethod === 'tempo') {
                            payload.due_date = el.paymentDueDate?.value || null;
                        } else {
                            payload.cash_account_id = el.paymentCashAccount?.value || null;
                        }

                        const runWithSubmitGuard = window.PosUi?.runWithSubmitGuard ?? (async (_button, task) => task());

                        const allowed = await window.PosUi.confirmRiskActionAsync({
                            title: 'Simpan Perubahan Pembayaran',
                            message: 'Perubahan ini akan memengaruhi laporan tempo dan rekap kas. Lanjutkan?',
                            confirmText: 'Simpan',
                            icon: 'payments',
                        });
                        if (!allowed) {
                            return;
                        }

                        await runWithSubmitGuard(el.savePayment, async () => {
                            if (el.paymentFeedback) {
                                el.paymentFeedback.classList.add('hidden');
                                el.paymentFeedback.textContent = '';
                            }

                            const response = await fetch(`/api/v1/purchases/${purchaseId}/payment`, {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                                    'X-Requested-With': 'XMLHttpRequest',
                                    ...headers,
                                },
                                body: JSON.stringify(payload),
                            });

                            const responseData = await response.json().catch(() => ({}));
                            if (!response.ok) {
                                const message = responseData.message || 'Gagal memperbarui pembayaran PO.';
                                if (el.paymentFeedback) {
                                    el.paymentFeedback.textContent = message;
                                    el.paymentFeedback.classList.remove('hidden');
                                }
                                throw new Error(message);
                            }

                            currentPurchasePaymentMethod = responseData.purchase?.payment_method || paymentMethod;
                            currentPurchaseDueDate = responseData.purchase?.due_date || null;
                            currentPurchaseCashAccountId = responseData.purchase?.cash_account_id || null;
                            setPaymentSummary();
                            closePaymentModal();
                        }, {
                            loadingHtml: '<span class="material-symbols-outlined animate-spin">progress_activity</span> MENYIMPAN',
                        });
                    });
                }

                if (el.editTax && el.taxModal) {
                    el.editTax.addEventListener('click', openTaxModal);
                    el.closeTaxModal?.addEventListener('click', closeTaxModal);
                    el.cancelTaxEdit?.addEventListener('click', closeTaxModal);
                    el.taxModal.addEventListener('click', (event) => {
                        if (event.target === el.taxModal) {
                            closeTaxModal();
                        }
                    });
                    el.taxEnabledEdit?.addEventListener('change', syncTaxPreview);
                    el.taxRateEdit?.addEventListener('input', () => {
                        if (el.taxAmountEdit) {
                            el.taxAmountEdit.value = String(Math.round(currentPurchaseSubtotal * (Math.max(0, Number(el.taxRateEdit.value || 0)) / 100)));
                        }
                        syncTaxPreview();
                    });
                    el.taxAmountEdit?.addEventListener('input', syncTaxPreview);
                    el.saveTax?.addEventListener('click', (event) => {
                        event.preventDefault();
                        el.taxForm?.requestSubmit();
                    });
                    el.taxForm?.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        const taxEnabled = el.taxEnabledEdit?.checked ?? false;
                        const taxRate = taxEnabled ? Math.max(0, Number(el.taxRateEdit?.value || 0)) : 0;
                        const tax = taxEnabled ? Math.max(0, Number(el.taxAmountEdit?.value || 0)) : 0;
                        const allowed = await window.PosUi.confirmRiskActionAsync({
                            title: 'Simpan Pajak PO',
                            message: `Subtotal: ${formatCurrency(currentPurchaseSubtotal)}\nBiaya tambahan: ${formatCurrency(currentPurchaseAdditionalCostTotal)}\nPajak: ${formatCurrency(tax)}\nTotal Baru: ${formatCurrency(currentPurchaseSubtotal + currentPurchaseAdditionalCostTotal + tax)}`,
                            confirmText: 'Simpan',
                            icon: 'percent',
                        });
                        if (!allowed) {
                            return;
                        }

                        await window.PosUi.runWithSubmitGuard(el.saveTax, async () => {
                            el.taxFeedback?.classList.add('hidden');
                            const response = await fetch(`/api/v1/purchases/${purchaseId}/tax`, {
                                method: 'PATCH',
                                headers: {
                                    'Content-Type': 'application/json',
                                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                                    'X-Requested-With': 'XMLHttpRequest',
                                    ...headers,
                                },
                                body: JSON.stringify({ tax_enabled: taxEnabled, tax_rate: taxRate, tax }),
                            });
                            const responseData = await response.json().catch(() => ({}));
                            if (!response.ok) {
                                const message = responseData.message || 'Gagal menyimpan pajak PO.';
                                if (el.taxFeedback) {
                                    el.taxFeedback.textContent = message;
                                    el.taxFeedback.classList.remove('hidden');
                                }
                                throw new Error(message);
                            }

                            currentPurchaseSubtotal = Number(responseData.purchase?.subtotal || currentPurchaseSubtotal);
                            currentPurchaseTaxRate = Number(responseData.purchase?.tax_rate || 0);
                            currentPurchaseTax = Number(responseData.purchase?.tax || 0);
                            currentPurchaseAdditionalCostTotal = Number(responseData.purchase?.additional_cost_total || currentPurchaseAdditionalCostTotal);
                            currentPurchaseTotal = Number(responseData.purchase?.total || (currentPurchaseSubtotal + currentPurchaseTax + currentPurchaseAdditionalCostTotal));
                            renderTotals();
                            closeTaxModal();
                        }, {
                            loadingHtml: '<span class="material-symbols-outlined animate-spin">progress_activity</span> MENYIMPAN',
                        });
                    });
                }

                (async () => {
                    try {
                        const data = await loadPurchase();
                        await applyPurchaseData(data);
                        return;
                        const items = data.items || [];
                        const fallbackSubtotal = items.reduce((sum, item) => sum + (Number(item.purchase_qty ?? item.qty) * Number(item.purchase_price ?? item.price)), 0);
                        currentPurchaseSubtotal = Number(data.purchase.subtotal ?? fallbackSubtotal);
                        currentPurchaseTaxRate = Number(data.purchase.tax_rate || 0);
                        currentPurchaseTax = Number(data.purchase.tax || 0);
                        currentPurchaseAdditionalCostTotal = Number(data.purchase.additional_cost_total || 0);
                        currentPurchaseTotal = Number(data.purchase.total ?? (currentPurchaseSubtotal + currentPurchaseTax + currentPurchaseAdditionalCostTotal));
                        currentAdditionalCosts = data.additional_costs || [];
                        currentPurchaseMode = normalizePurchaseMode(data.purchase.purchase_mode || purchaseFlowDefaultMode);
                        currentPurchaseFlow = data.flow || currentPurchaseFlow;
                        currentPurchasePaymentMethod = data.purchase.payment_method || 'tempo';
                        currentPurchaseDueDate = data.purchase.due_date || null;
                        currentPurchaseCashAccountId = data.purchase.cash_account_id || null;

                        await loadCashAccounts();

                        el.poNumber.textContent = `PO #${String(data.purchase.id).slice(0, 6).toUpperCase()}`;
                        el.poSupplier.textContent = `${data.supplier?.name || 'Pemasok'} · Lokasi: ${data.purchase.location_name || '-'}`;
                        el.orderDate.textContent = formatDate(data.purchase.created_at);
                        el.arrivalDate.textContent = formatDate(data.purchase.created_at);
                        el.createdInfo.textContent = `Sistem - ${formatDate(data.purchase.created_at)}`;
                        el.statusInfo.textContent = 'Sistem';
                        setPaymentSummary();
                        renderTotals();
                        renderItems(items);
                        renderAdditionalCosts(data.additional_costs || []);
                        renderReceiptHistory(data.receipts || []);
                        setStatus(data.purchase.status || purchaseStatusSubmitted, currentPurchaseMode);
                    } catch (error) {
                        el.itemList.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                    }
                })();
            })();
        </script>
    @endsection
