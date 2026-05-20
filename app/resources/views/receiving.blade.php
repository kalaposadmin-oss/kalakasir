@php
    $receivingTenant = \App\Support\TenantContext::get();
    $receivingUser = auth()->user();
    $receivingRole = null;

    if (! $receivingTenant && filled($receivingUser?->tenant_id)) {
        $receivingTenant = \App\Models\Tenant::query()->find($receivingUser->tenant_id);
    }

    if ($receivingTenant && $receivingUser) {
        $receivingRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $receivingTenant->id, (string) $receivingUser->id);

        if (! $receivingRole && filled($receivingUser->role ?? null)) {
            $receivingRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $receivingUser->role);
        }
    }

    abort_unless(in_array($receivingRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManageReceivingFlow = in_array($receivingRole, ['admin', 'owner', 'superadmin'], true);
    $receivingPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $receivingPurchaseId = (string) ($purchaseId ?? request()->query('purchase_id', ''));
    $receivingCurrentPath = $currentPath ?? ($receivingPurchaseId !== '' ? $receivingPortalPrefix.'/receiving/'.$receivingPurchaseId : $receivingPortalPrefix.'/receiving');
    $receivingBackUrl = $backUrl ?? $receivingPortalPrefix.'/purchases';
    $receivingFlowDefaultMode = app(\App\Support\PurchaseFlowResolver::class)->resolveMode(null, $receivingTenant?->purchase_mode ?? null);
@endphp

@extends('layouts.app')

@section('page_title', 'Terima Barang')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100')
@section('main_class', 'flex-1 overflow-y-auto pb-24 app-bottom-safe-space')

@push('head')
@endpush

@section('header')
    <x-page-header title="Terima Barang" :backUrl="$receivingBackUrl" :showMenuButton="true">
        <x-slot:actions>
            @if ($canManageReceivingFlow)
                <a id="receiving-action-link" class="hidden items-center justify-center rounded-lg h-10 w-10 bg-primary/10 text-primary hover:bg-primary/20 transition-colors" href="#">
                    <span class="material-symbols-outlined">done_all</span>
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <div id="receiving-list-view" class="hidden">
        <div class="px-4 pt-4">
            <div class="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm p-4">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Daftar Penerimaan</p>
                <p class="mt-1 text-sm text-slate-500">Pilih purchase order yang ingin diproses penerimaannya.</p>
            </div>
        </div>
        <div class="px-4 py-4">
            <h3 class="text-slate-900 dark:text-slate-100 text-base font-bold leading-tight">Purchase Order Tersedia</h3>
            <p class="text-slate-500 text-xs font-medium mt-1" id="purchase-list-count">0 PO</p>
        </div>
        <div class="flex flex-col gap-3 px-4 pb-24 app-bottom-safe-space" id="purchase-list"></div>
    </div>

    <div id="receiving-detail-view" class="hidden">
        <div class="p-4">
            <div class="flex flex-col items-stretch justify-start rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="w-full bg-center bg-no-repeat aspect-[21/9] bg-cover" style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuDeHfyqGdfkFnMuGFtIXN_QbsR4upghE3THdltvYScctXSrIEj05qdye1sgytKEsnMEwLh6z6x8vfGyMcXVe7cducZI7poWvT9gOHvVEC0h41M2yiaBhin41LNSqQb-AOhrnzwT36lS3QDDfrvrYUT-ekEHGR0HnXLqdhSsS2HUjxrEPrzMQyg2_nJYNrvQT2JpJkk-VmsuHYUD9rmTH-tISLFnQW8Ylpw0F1s6rolJRx1PCrFflUd-2qTegqQykP2zvk7vYhpwGA");'></div>
                <div class="flex w-full flex-col p-4" id="purchase-header"></div>
            </div>
        </div>

        <div class="px-4 pb-2" id="payment-summary-wrap">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Data Pembayaran</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100" id="payment-method-label">-</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" id="payment-detail-label">-</p>
                    </div>
                    <button id="edit-payment" class="rounded-full bg-primary/10 px-3 py-1.5 text-xs font-bold text-primary" type="button">Ubah Pembayaran</button>
                </div>
                <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Koreksi tempo atau akun kas langsung dari layar penerimaan.</p>
            </div>
        </div>

        <div class="px-4 py-2 flex items-center justify-between">
            <h3 class="text-slate-900 dark:text-slate-100 text-base font-bold leading-tight">Daftar Barang Dipesan</h3>
            <span class="text-slate-500 text-xs font-medium" id="item-count">0 Item</span>
        </div>

        <div class="flex flex-col gap-3 px-4 pb-24 app-bottom-safe-space" id="item-list"></div>

        @if ($canManageReceivingFlow)
            <div class="fixed bottom-0 left-1/2 w-full max-w-[430px] -translate-x-1/2 p-4 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 z-20 app-bottom-safe-footer">
                <div class="max-w-md mx-auto flex gap-3">
                    <button id="close-purchase" class="hidden flex-1 items-center justify-center gap-2 h-12 rounded-xl border border-amber-200 bg-amber-50 text-amber-700 font-bold text-sm tracking-wide shadow-sm dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-300" type="button">
                        <span class="material-symbols-outlined text-base">event_busy</span>
                        <span>TIDAK ADA SUSULAN</span>
                    </button>
                    <div class="flex flex-[1.5] gap-2">
                        <button id="edit-payment-footer" class="hidden flex-1 items-center justify-center h-12 rounded-xl border border-primary/20 bg-primary/10 text-primary font-bold text-sm tracking-wide shadow-sm" type="button">
                            UBAH PEMBAYARAN
                        </button>
                        <a id="detail-action-button" class="hidden flex-[1.2] items-center justify-center h-12 rounded-xl bg-primary text-white font-bold text-sm tracking-wide shadow-lg shadow-primary/30" href="#">
                            KONFIRMASI STOK
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($canManageReceivingFlow)
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

        <div id="price-modal" class="fixed inset-0 z-50 hidden items-end justify-center bg-slate-950/40 px-4 py-6 sm:items-center">
            <div class="w-full max-w-[430px] overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Ubah Harga</p>
                        <h4 id="price-modal-title" class="text-base font-bold text-slate-900 dark:text-slate-100">-</h4>
                        <p id="price-modal-subtitle" class="mt-1 text-xs text-slate-500 dark:text-slate-400">-</p>
                    </div>
                    <button id="close-price-modal" class="size-9 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800" type="button">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>
                <form id="price-form" class="space-y-4 p-5">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">Harga Baru</label>
                        <input id="price-input" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-800" type="number" min="0" step="1" inputmode="numeric" />
                    </div>
                    <p id="price-feedback" class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-600"></p>
                    <div class="flex gap-3 pt-1">
                        <button id="cancel-price-edit" class="flex-1 rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300" type="button">Batal</button>
                        <button id="save-price" class="flex-[2] rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-white shadow-lg shadow-primary/20" type="submit">Simpan Harga</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @include('partials.ui-helpers')

    <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const purchaseStatusLabels = @json(\App\Support\PurchaseStatus::labels());
                const purchaseStatusAliases = @json(\App\Support\PurchaseStatus::aliases());
                const purchaseModeControlled = @json(\App\Support\PurchaseMode::CONTROLLED);
                const purchaseModePoShort = @json(\App\Support\PurchaseMode::PO_SHORT);
                const purchaseFlowDefaultMode = @json($receivingFlowDefaultMode);
                const purchasePaymentMethodLabels = { tempo: 'Tempo', tunai: 'Tunai' };

                const normalizePurchaseStatus = (status) => {
                    const value = String(status || '').toLowerCase();
                    return purchaseStatusAliases[value] || value;
                };

                const normalizePurchaseMode = (mode) => String(mode || '').toLowerCase() || purchaseFlowDefaultMode;
                const normalizePaymentMethod = (method) => {
                    const value = String(method || '').toLowerCase();
                    return ['tempo', 'tunai'].includes(value) ? value : 'tempo';
                };
                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(Number(value || 0));
                const purchaseId = @json($receivingPurchaseId) || new URLSearchParams(window.location.search).get('purchase_id');
                const currentPath = @json($receivingCurrentPath);

                const el = {
                    listView: document.getElementById('receiving-list-view'),
                    detailView: document.getElementById('receiving-detail-view'),
                    actionLink: document.getElementById('receiving-action-link'),
                    detailActionButton: document.getElementById('detail-action-button'),
                    closePurchase: document.getElementById('close-purchase'),
                    editPaymentFooter: document.getElementById('edit-payment-footer'),
                    paymentMethodLabel: document.getElementById('payment-method-label'),
                    paymentDetailLabel: document.getElementById('payment-detail-label'),
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
                    paymentFeedback: document.getElementById('payment-feedback'),
                    savePayment: document.getElementById('save-payment'),
                    priceModal: document.getElementById('price-modal'),
                    priceModalTitle: document.getElementById('price-modal-title'),
                    priceModalSubtitle: document.getElementById('price-modal-subtitle'),
                    priceForm: document.getElementById('price-form'),
                    priceInput: document.getElementById('price-input'),
                    priceFeedback: document.getElementById('price-feedback'),
                    closePriceModal: document.getElementById('close-price-modal'),
                    cancelPriceEdit: document.getElementById('cancel-price-edit'),
                    savePrice: document.getElementById('save-price'),
                    purchaseList: document.getElementById('purchase-list'),
                    purchaseListCount: document.getElementById('purchase-list-count'),
                    header: document.getElementById('purchase-header'),
                    list: document.getElementById('item-list'),
                    count: document.getElementById('item-count'),
                };

                let currentPurchasePaymentMethod = 'tempo';
                let currentPurchaseDueDate = null;
                let currentPurchaseCashAccountId = null;
                let cashAccountOptions = [];
                let currentPurchaseStatus = 'draft';
                let currentPurchaseMode = purchaseFlowDefaultMode;
                let currentPurchaseFlow = {
                    can_receive: false,
                    can_close: false,
                    purchase_mode: purchaseFlowDefaultMode,
                };
                let currentReceivingItems = [];
                let currentPriceIndex = null;

                const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                }).format(new Date(value));

                const formatQuantity = (value) => {
                    const quantity = Number(value || 0);
                    return new Intl.NumberFormat('id-ID', {
                        maximumFractionDigits: 4,
                    }).format(Number.isFinite(quantity) ? quantity : 0);
                };

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

                const canEditItemPrice = () => ['submitted', 'approved'].includes(normalizePurchaseStatus(currentPurchaseStatus));

                const getPriceEditMessage = () => canEditItemPrice()
                    ? 'Ubah harga sebelum penerimaan dimulai.'
                    : 'Harga terkunci setelah barang mulai diterima.';

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

                const openPriceModal = (item, index) => {
                    if (!el.priceModal || !canEditItemPrice()) {
                        return;
                    }

                    currentPriceIndex = index;
                    if (el.priceModalTitle) {
                        el.priceModalTitle.textContent = item.product_name;
                    }
                    if (el.priceModalSubtitle) {
                        el.priceModalSubtitle.textContent = `${formatQuantity(item.qty)} item • harga saat ini ${formatCurrency(item.price)}`;
                    }
                    if (el.priceInput) {
                        el.priceInput.value = Number(item.price || 0);
                    }
                    if (el.priceFeedback) {
                        el.priceFeedback.classList.add('hidden');
                        el.priceFeedback.textContent = '';
                    }
                    el.priceModal.classList.remove('hidden');
                    el.priceModal.classList.add('flex');
                    el.priceInput?.focus();
                };

                const closePriceModal = () => {
                    currentPriceIndex = null;
                    if (!el.priceModal) {
                        return;
                    }

                    el.priceModal.classList.add('hidden');
                    el.priceModal.classList.remove('flex');
                    if (el.priceFeedback) {
                        el.priceFeedback.classList.add('hidden');
                        el.priceFeedback.textContent = '';
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

                const loadPurchases = async () => {
                    const response = await fetch('/api/v1/purchases', { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const data = await response.json();
                    return (data.purchases || []).filter((purchase) => {
                        const status = normalizePurchaseStatus(purchase.status || 'draft');
                        const mode = normalizePurchaseMode(purchase.purchase_mode || purchaseFlowDefaultMode);

                        if (mode === purchaseModePoShort) {
                            return ['submitted', 'partially_received'].includes(status);
                        }

                        if (mode === purchaseModeControlled) {
                            return ['approved', 'partially_received'].includes(status);
                        }

                        return false;
                    });
                };

                const loadPurchaseDetail = async (purchaseId) => {
                    const response = await fetch(`/api/v1/purchases/${purchaseId}`, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat data PO.');
                    }
                    return response.json();
                };

                const portalPrefix = @json($receivingPortalPrefix);
                const listPath = `${portalPrefix}/receiving`;
                const buildDetailUrl = (selectedPurchaseId) => `${portalPrefix}/receiving/${encodeURIComponent(selectedPurchaseId)}?from=${encodeURIComponent(listPath)}`;
                const buildActionUrl = (selectedPurchaseId) => `${portalPrefix}/receiving/${encodeURIComponent(selectedPurchaseId)}/action?from=${encodeURIComponent(currentPath)}`;

                const syncFooterActions = () => {
                    const canReceive = Boolean(currentPurchaseFlow.can_receive);
                    const canClose = Boolean(currentPurchaseFlow.can_close);

                    el.detailActionButton?.classList.toggle('hidden', !canReceive);
                    el.detailActionButton?.classList.toggle('flex', canReceive);
                    el.closePurchase?.classList.toggle('hidden', !canClose);
                    el.closePurchase?.classList.toggle('flex', canClose);
                    if (el.closePurchase && canClose) {
                        el.closePurchase.innerHTML = '<span class="material-symbols-outlined text-base">event_busy</span><span>TIDAK ADA SUSULAN</span>';
                    }
                };

                const renderHeader = (data) => {
                    const supplier = data.supplier?.name || 'Pemasok';
                    el.header.innerHTML = `
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <p class="text-slate-500 dark:text-slate-400 text-xs font-medium uppercase tracking-wider">Nomor Pesanan</p>
                                <p class="text-slate-900 dark:text-slate-100 text-lg font-bold leading-tight">PO-${String(data.purchase.id).slice(0, 6).toUpperCase()}</p>
                            </div>
                            <span class="px-2 py-1 rounded bg-primary/10 text-primary text-[10px] font-bold uppercase">${purchaseStatusLabels[normalizePurchaseStatus(data.purchase.status)] || data.purchase.status}</span>
                        </div>
                        <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            <span class="material-symbols-outlined text-sm">store</span>
                            <p class="text-sm font-normal">Pemasok: ${supplier}</p>
                        </div>
                        <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400 mt-1">
                            <span class="material-symbols-outlined text-sm">place</span>
                            <p class="text-sm font-normal">Lokasi: ${data.purchase.location_name || '-'}</p>
                        </div>
                        <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400 mt-1">
                            <span class="material-symbols-outlined text-sm">calendar_today</span>
                            <p class="text-sm font-normal">Estimasi: ${formatDate(data.purchase.created_at)}</p>
                        </div>
                    `;
                };

                if (el.editPayment || el.editPaymentFooter) {
                    const openPayment = () => openPaymentModal();
                    el.editPayment?.addEventListener('click', openPayment);
                    el.editPaymentFooter?.addEventListener('click', openPayment);
                    el.closePaymentModal?.addEventListener('click', closePaymentModal);
                    el.cancelPaymentEdit?.addEventListener('click', closePaymentModal);
                    el.paymentModal?.addEventListener('click', (event) => {
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

                el.list?.addEventListener('click', (event) => {
                    const editPrice = event.target.closest('.edit-price');
                    if (!editPrice) {
                        return;
                    }

                    const index = Number(editPrice.dataset.index);
                    const item = currentReceivingItems[index];
                    if (item) {

                el.closePurchase?.addEventListener('click', async () => {
                    if (!purchaseId || !currentPurchaseFlow.can_close) {
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
                                'X-Requested-With': 'XMLHttpRequest',
                                ...headers,
                            },
                        });

                        const responseData = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            throw new Error(responseData.message || 'Gagal menutup purchase.');
                        }

                        const refreshed = await loadPurchaseDetail(purchaseId);
                        currentPurchaseStatus = refreshed.purchase.status || currentPurchaseStatus;
                        currentPurchaseMode = normalizePurchaseMode(refreshed.purchase.purchase_mode || currentPurchaseMode);
                        currentPurchaseFlow = refreshed.flow || currentPurchaseFlow;
                        syncFooterActions();
                    }, {
                        loadingHtml: '<span class="material-symbols-outlined animate-spin">progress_activity</span> MENUTUP',
                    });
                });
                        openPriceModal(item, index);
                    }
                });

                el.closePriceModal?.addEventListener('click', closePriceModal);
                el.cancelPriceEdit?.addEventListener('click', closePriceModal);
                el.priceModal?.addEventListener('click', (event) => {
                    if (event.target === el.priceModal) {
                        closePriceModal();
                    }
                });

                el.priceForm?.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    if (currentPriceIndex === null || !purchaseId || !canEditItemPrice()) {
                        return;
                    }

                    const item = currentReceivingItems[currentPriceIndex];
                    if (!item) {
                        return;
                    }

                    await window.PosUi.runWithSubmitGuard(el.savePrice, async () => {
                        const response = await fetch(`/api/v1/purchases/${purchaseId}/items/${item.id}/price`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                ...headers,
                            },
                            body: JSON.stringify({ price: Number(el.priceInput?.value || 0) }),
                        });

                        const responseData = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            const message = responseData.message || 'Gagal menyimpan harga item.';
                            if (el.priceFeedback) {
                                el.priceFeedback.textContent = message;
                                el.priceFeedback.classList.remove('hidden');
                            }
                            throw new Error(message);
                        }

                        const refreshed = await loadPurchaseDetail(purchaseId);
                        currentPurchaseStatus = refreshed.purchase.status || 'draft';
                        currentPurchaseMode = normalizePurchaseMode(refreshed.purchase.purchase_mode || purchaseFlowDefaultMode);
                        currentReceivingItems = (refreshed.items || []).map((refreshedItem) => ({
                            ...refreshedItem,
                            qty: Number(refreshedItem.qty || 0),
                            received: Number(refreshedItem.qty || 0),
                            price: Number(refreshedItem.price || 0),
                        }));
                        renderItems(currentReceivingItems);
                        closePriceModal();
                    }, {
                        loadingHtml: '<span class="material-symbols-outlined animate-spin">progress_activity</span> MENYIMPAN',
                    });
                });

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

                const renderPurchaseList = (purchases) => {
                    el.purchaseList.innerHTML = '';
                    el.purchaseListCount.textContent = `${purchases.length} PO`;

                    if (!purchases.length) {
                        el.purchaseList.innerHTML = '<div class="text-sm text-slate-400">Belum ada PO.</div>';
                        return;
                    }

                    purchases.forEach((purchase) => {
                        const row = document.createElement('a');
                        row.href = buildDetailUrl(purchase.id);
                        row.className = 'block bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm';
                        row.innerHTML = `
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Purchase Order</p>
                                    <h4 class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">PO-${String(purchase.id).slice(0, 6).toUpperCase()}</h4>
                                    <p class="mt-1 text-xs text-slate-500">${purchase.supplier_name || 'Pemasok'}</p>
                                </div>
                                <span class="px-2 py-1 rounded bg-primary/10 text-primary text-[10px] font-bold uppercase">${purchaseStatusLabels[normalizePurchaseStatus(purchase.status)] || 'Draf'}</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                                <span>${formatDate(purchase.created_at)}</span>
                                <span class="font-semibold text-primary">Lihat Detail</span>
                            </div>
                        `;
                        el.purchaseList.appendChild(row);
                    });
                };

                const renderItems = (items) => {
                    el.list.innerHTML = '';
                    el.count.textContent = `${items.length} Item`;

                    if (!items.length) {
                        el.list.innerHTML = '<div class="text-sm text-slate-400">Tidak ada item pada PO ini.</div>';
                        return;
                    }

                    items.forEach((item, index) => {
                    const image = item.product_image || '/images/placeholders/product.svg';
                        const row = document.createElement('div');
                        row.className = 'flex flex-col bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm gap-4';
                        row.innerHTML = `
                            <div class="flex gap-4">
                                <div class="bg-center bg-no-repeat aspect-square bg-cover rounded-lg size-16 shrink-0" style='background-image: url("${image}");'></div>
                                <div class="flex flex-1 flex-col justify-center">
                                    <p class="text-slate-900 dark:text-slate-100 text-sm font-semibold">${item.product_name}</p>
                                    <p class="text-slate-500 dark:text-slate-400 text-xs">SKU: ${item.product_sku || '-'}</p>
                                    ${item.product_variant_name ? `<p class="text-slate-500 dark:text-slate-400 text-xs">Varian: ${item.product_variant_name}</p>` : ''}
                                    <p class="text-primary text-xs font-medium mt-1">Dipesan: ${formatQuantity(item.qty)} unit</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                                <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-3 py-2">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Harga Satuan</p>
                                    <p class="text-sm font-bold text-slate-900 dark:text-slate-100">${formatCurrency(item.price)}</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-3 py-2 text-right">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Subtotal Item</p>
                                    <p class="text-sm font-bold text-slate-900 dark:text-slate-100">${formatCurrency(Number(item.qty || 0) * Number(item.price || 0))}</p>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <p class="text-slate-700 dark:text-slate-300 text-xs font-medium">Jumlah Diterima</p>
                                    <div class="flex items-center gap-2 text-slate-900 dark:text-slate-100 bg-slate-50 dark:bg-slate-800 rounded-lg p-1 border border-slate-200 dark:border-slate-700">
                                        <button class="flex h-8 w-8 items-center justify-center rounded-md bg-white dark:bg-slate-700 shadow-sm text-primary cursor-pointer">
                                            <span class="material-symbols-outlined text-lg">remove</span>
                                        </button>
                                        <input class="text-sm font-bold w-full p-0 text-center bg-transparent border-none focus:ring-0" type="number" value="${Number(item.qty || 0)}" />
                                        <button class="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-white shadow-sm cursor-pointer">
                                            <span class="material-symbols-outlined text-lg">add</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <p class="text-slate-700 dark:text-slate-300 text-xs font-medium">Kondisi</p>
                                    <select class="w-full rounded-lg text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 h-10 text-xs font-medium focus:ring-primary focus:border-primary px-2">
                                        <option value="good">Sesuai (Baik)</option>
                                        <option value="damaged">Rusak</option>
                                        <option value="missing">Kurang</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-3 pt-1">
                                <button class="edit-price inline-flex items-center justify-center rounded-lg border border-primary/20 bg-primary/10 px-3 py-2 text-xs font-bold text-primary ${canEditItemPrice() ? '' : 'cursor-not-allowed opacity-50'}" data-index="${index}" type="button" ${canEditItemPrice() ? '' : 'disabled'}>
                                    Ubah Harga
                                </button>
                                <span class="text-[11px] font-medium ${canEditItemPrice() ? 'text-emerald-600' : 'text-slate-400'}">${getPriceEditMessage()}</span>
                            </div>
                        `;
                        el.list.appendChild(row);
                    });
                };

                (async () => {
                    try {
                        const purchases = await loadPurchases();
                        if (!purchaseId) {
                            el.detailView.classList.add('hidden');
                            el.listView.classList.remove('hidden');
                            renderPurchaseList(purchases);
                            return;
                        }

                        el.listView.classList.add('hidden');
                        el.detailView.classList.remove('hidden');
                        if (el.actionLink) {
                            el.actionLink.classList.remove('hidden');
                            el.actionLink.classList.add('flex');
                            el.actionLink.href = buildActionUrl(purchaseId);
                        }

                        if (el.detailActionButton) {
                            el.detailActionButton.classList.remove('hidden');
                            el.detailActionButton.classList.add('flex');
                            el.detailActionButton.href = buildActionUrl(purchaseId);
                        }

                        const data = await loadPurchaseDetail(purchaseId);
                        currentPurchasePaymentMethod = data.purchase.payment_method || 'tempo';
                        currentPurchaseDueDate = data.purchase.due_date || null;
                        currentPurchaseCashAccountId = data.purchase.cash_account_id || null;
                        currentPurchaseStatus = data.purchase.status || 'draft';
                        currentPurchaseMode = normalizePurchaseMode(data.purchase.purchase_mode || purchaseFlowDefaultMode);
                        currentPurchaseFlow = data.flow || currentPurchaseFlow;
                        await loadCashAccounts();
                        renderHeader(data);
                        setPaymentSummary();
                        if (el.editPayment || el.editPaymentFooter) {
                            const showEdit = ['tempo', 'tunai'].includes(normalizePaymentMethod(currentPurchasePaymentMethod));
                            el.editPayment?.classList.toggle('hidden', !showEdit);
                            el.editPaymentFooter?.classList.toggle('hidden', !showEdit);
                        }
                        currentReceivingItems = (data.items || []).map((item) => ({
                            ...item,
                            qty: Number(item.qty || 0),
                            received: Number(item.qty || 0),
                            price: Number(item.price || 0),
                        }));
                        renderItems(currentReceivingItems);
                        syncFooterActions();
                    } catch (error) {
                        if (!purchaseId) {
                            el.purchaseList.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                            return;
                        }

                        el.list.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                    }
                })();
            })();
    </script>
@endsection
