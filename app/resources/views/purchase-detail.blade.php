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
            <span class="text-xs font-medium text-slate-400" id="item-count">0 Produk</span>
        </div>
        <div class="space-y-2" id="item-list"></div>
        <div class="mt-3 rounded-xl border border-primary/20 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex justify-between items-center mb-1">
                <span class="text-xs text-slate-500">Subtotal</span>
                <span class="text-sm font-medium" id="subtotal">Rp 0</span>
            </div>
            <div class="flex justify-between items-center mb-3">
                <span class="text-xs text-slate-500">Pajak (PPN 11%)</span>
                <span class="text-sm font-medium text-red-500" id="tax">Rp 0</span>
            </div>
            <div class="flex justify-between items-center pt-3 border-t border-primary/20">
                <span class="font-bold text-slate-900 dark:text-white">Total Akhir</span>
                <span class="text-lg font-bold text-primary" id="total">Rp 0</span>
            </div>
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
        <div id="purchase-action-bar" class="fixed bottom-0 left-1/2 z-30 hidden w-full max-w-[430px] -translate-x-1/2 bg-white/95 px-4 pt-4 backdrop-blur-xl dark:bg-slate-900/95 app-bottom-safe-footer">
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
    @endif

    @include('partials.ui-helpers')

        <script>
            (() => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const purchaseStatusLabels = @json(\App\Support\PurchaseStatus::labels());
                const purchaseStatusAliases = @json(\App\Support\PurchaseStatus::aliases());
                const purchaseStatusSubmitted = @json(\App\Support\PurchaseStatus::SUBMITTED);
                const purchaseModeControlled = @json(\App\Support\PurchaseMode::CONTROLLED);
                const purchaseModePoShort = @json(\App\Support\PurchaseMode::PO_SHORT);
                const purchaseFlowDefaultMode = @json($purchaseFlowDefaultMode);
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
                    total: document.getElementById('total'),
                    createdInfo: document.getElementById('created-info'),
                    statusTitle: document.getElementById('status-title'),
                    statusInfo: document.getElementById('status-info'),
                    statusDot: document.getElementById('status-dot'),
                    paymentMethodLabel: document.getElementById('payment-method-label'),
                    paymentDetailLabel: document.getElementById('payment-detail-label'),
                    actionBar: document.getElementById('purchase-action-bar'),
                    approvalActions: document.getElementById('approval-actions'),
                    fulfillmentActions: document.getElementById('fulfillment-actions'),
                    approve: document.getElementById('approve'),
                    approveLabel: document.getElementById('approve-label'),
                    reject: document.getElementById('reject'),
                    openReceiving: document.getElementById('open-receiving'),
                    closePurchase: document.getElementById('close-purchase'),
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
                    receiptHistory: document.getElementById('receipt-history'),
                };

                let purchaseId = @json($resolvedPurchaseId) || new URLSearchParams(window.location.search).get('purchase_id');
                let currentPurchaseMode = purchaseFlowDefaultMode;
                let currentPurchasePaymentMethod = 'tempo';
                let currentPurchaseDueDate = null;
                let currentPurchaseCashAccountId = null;
                let currentPurchaseFlow = {
                    can_approve: false,
                    can_receive: false,
                    can_close: false,
                    purchase_mode: purchaseFlowDefaultMode,
                };
                let cashAccountOptions = [];

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
                    } else {
                        el.statusTitle.textContent = label;
                    }
                    const showApproval = Boolean(currentPurchaseFlow.can_approve) && normalizedStatus === purchaseStatusSubmitted;
                    const showFulfillment = Boolean(currentPurchaseFlow.can_receive) || Boolean(currentPurchaseFlow.can_close);
                    const showActionBar = showApproval || showFulfillment;

                    el.actionBar?.classList.toggle('hidden', !showActionBar);
                    el.approvalActions?.classList.toggle('hidden', !showApproval);
                    el.fulfillmentActions?.classList.toggle('hidden', !showFulfillment);

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

                    const allowed = window.PosUi.confirmRiskAction({
                        title: 'Tidak Ada Barang Susulan',
                        message: 'Gunakan ini hanya kalau supplier tidak akan mengirim sisa barang. PO akan diselesaikan tanpa menambah stok lagi.',
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

                        const confirmRiskAction = window.PosUi?.confirmRiskAction ?? (({ title = 'Konfirmasi', message = 'Lanjutkan aksi ini?' } = {}) => {
                            return window.confirm(`${title}\n\n${message}`);
                        });
                        const runWithSubmitGuard = window.PosUi?.runWithSubmitGuard ?? (async (_button, task) => task());

                        const allowed = confirmRiskAction({
                            title: 'Simpan Perubahan Pembayaran',
                            message: 'Perubahan ini akan memengaruhi laporan tempo dan rekap kas. Lanjutkan?',
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

                (async () => {
                    try {
                        const data = await loadPurchase();
                        const items = data.items || [];
                        const subtotal = items.reduce((sum, item) => sum + (Number(item.purchase_qty ?? item.qty) * Number(item.purchase_price ?? item.price)), 0);
                        const tax = subtotal * 0.11;
                        const total = subtotal + tax;
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
                        el.subtotal.textContent = formatCurrency(subtotal);
                        el.tax.textContent = formatCurrency(tax);
                        el.total.textContent = formatCurrency(total);
                        renderItems(items);
                        renderReceiptHistory(data.receipts || []);
                        setStatus(data.purchase.status || purchaseStatusSubmitted, currentPurchaseMode);
                    } catch (error) {
                        el.itemList.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                    }
                })();
            })();
        </script>
    @endsection
