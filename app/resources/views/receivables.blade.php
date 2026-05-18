@extends('layouts.app')

@php
    $receivableContext = $receivableContext ?? (session('auth_portal', 'pos') === 'pos' ? 'pos' : 'backoffice');
    $isPosReceivable = $receivableContext === 'pos';
    $backUrl = $isPosReceivable ? pos_default_url() : '/backoffice/reports';
    $saleDetailBaseUrl = $isPosReceivable ? '/pos/sales' : '/backoffice/sales';
@endphp

@section('page_title', 'Piutang')
@section('ui_mode', $isPosReceivable ? 'pos' : 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', $isPosReceivable ? 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl' : '')
@section('main_class', $isPosReceivable ? 'flex-1 overflow-y-auto pb-24 app-bottom-safe-space' : 'flex-1 overflow-y-auto pb-8 lg:pl-72')

@push('head')
    <style>
        .receivable-card-shadow { box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.05); }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .rcv-sheet-overlay { position:fixed;inset:0;z-index:50;opacity:0;pointer-events:none;transition:opacity .25s ease; }
        .rcv-sheet-overlay.active { opacity:1;pointer-events:auto; }
        .rcv-sheet-backdrop { position:absolute;inset:0;background:rgba(0,0,0,.35); }
        .rcv-sheet { position:absolute;bottom:0;left:50%;transform:translateX(-50%) translateY(100%);width:100%;max-width:430px;max-height:85vh;overflow-y:auto;border-radius:1.5rem 1.5rem 0 0;background:#fff;padding:1.25rem;box-shadow:0 -8px 30px rgba(0,0,0,.12);transition:transform .3s cubic-bezier(.32,.72,0,1); }
        .rcv-sheet-overlay.active .rcv-sheet { transform:translateX(-50%) translateY(0); }
        @media(prefers-color-scheme:dark){ .rcv-sheet{background:rgb(15 23 42);} }
    </style>
@endpush

@section('header')
    <x-page-header title="Piutang" :backUrl="$backUrl" :showMenuButton="true" />
@endsection

@section('content')
    <div class="mx-auto w-full max-w-7xl space-y-4 px-4 py-5 lg:px-8">
        <section class="grid grid-cols-3 gap-2 sm:gap-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-3 sm:p-4 receivable-card-shadow dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[9px] font-bold uppercase tracking-[0.12em] text-slate-400">Piutang Aktif</p>
                <p id="receivable-active-total" class="mt-1 text-sm font-black text-slate-900 dark:text-white sm:text-lg">Rp 0</p>
                <p id="receivable-active-count" class="mt-1 text-[10px] font-semibold text-slate-500">0 invoice</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-3 sm:p-4 receivable-card-shadow dark:border-amber-900/40 dark:bg-amber-950/20">
                <p class="text-[9px] font-bold uppercase tracking-[0.12em] text-amber-700">Jatuh Tempo</p>
                <p id="receivable-overdue-total" class="mt-1 text-sm font-black text-amber-800 dark:text-amber-200 sm:text-lg">Rp 0</p>
                <p class="mt-1 text-[10px] font-semibold text-amber-700">Perlu ditagih</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3 sm:p-4 receivable-card-shadow dark:border-emerald-900/40 dark:bg-emerald-950/20">
                <p class="text-[9px] font-bold uppercase tracking-[0.12em] text-emerald-700">Piutang Lunas</p>
                <p id="receivable-paid-total" class="mt-1 text-sm font-black text-emerald-800 dark:text-emerald-200 sm:text-lg">Rp 0</p>
                <p id="receivable-paid-count" class="mt-1 text-[10px] font-semibold text-emerald-700">0 invoice</p>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 receivable-card-shadow dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-1 flex-col gap-2 sm:flex-row">
                    <label class="relative flex-1">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">search</span>
                        <input id="receivable-search" class="w-full rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-950" type="search" placeholder="Cari customer" />
                    </label>
                    <select id="receivable-status-filter" class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-950">
                        <option value="open">Belum lunas</option>
                        <option value="overdue">Jatuh tempo</option>
                        <option value="unpaid">Belum dibayar</option>
                        <option value="partial">Cicilan berjalan</option>
                        <option value="paid">Lunas</option>
                        <option value="all">Semua</option>
                    </select>
                </div>
                <button id="receivable-refresh" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white" type="button">
                    <span class="material-symbols-outlined text-lg">refresh</span>
                    Muat
                </button>
            </div>
        </section>

        {{-- List panel --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-4 receivable-card-shadow dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Daftar Piutang</p>
                    <p id="receivable-list-meta" class="mt-1 text-xs text-slate-500">Memuat data...</p>
                </div>
                <div class="flex gap-2">
                    <button id="receivable-prev" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 disabled:opacity-40" type="button">Prev</button>
                    <button id="receivable-next" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 disabled:opacity-40" type="button">Next</button>
                </div>
            </div>
            <div id="receivable-list" class="space-y-3"></div>
            <p id="receivable-empty" class="hidden rounded-2xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500">Belum ada piutang sesuai filter.</p>
        </section>

        {{-- Detail panel: bottom-sheet for POS, inline for backoffice --}}
        @if ($isPosReceivable)
        <div id="rcv-sheet-overlay" class="rcv-sheet-overlay">
            <div class="rcv-sheet-backdrop" id="rcv-sheet-backdrop"></div>
            <div class="rcv-sheet dark:bg-slate-900">
                <div class="mb-4 flex items-center justify-between">
                    <div class="h-1.5 w-12 rounded-full bg-slate-300"></div>
                    <button id="rcv-sheet-close" class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-500" type="button">
                        <span class="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>
        @endif

        <aside id="receivable-detail" class="{{ $isPosReceivable ? '' : 'rounded-2xl border border-slate-200 bg-white p-4 receivable-card-shadow dark:border-slate-800 dark:bg-slate-900 mt-4' }}">
            <div id="receivable-detail-empty" class="{{ $isPosReceivable ? 'hidden' : '' }} py-10 text-center">
                <span class="material-symbols-outlined text-4xl text-slate-300">receipt_long</span>
                <p class="mt-2 text-sm font-semibold text-slate-500">Pilih piutang untuk melihat detail.</p>
            </div>
            <div id="receivable-detail-content" class="hidden space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Customer</p>
                        <h2 id="detail-customer" class="mt-1 text-lg font-black text-slate-900 dark:text-white">-</h2>
                        <p id="detail-sale" class="text-xs text-slate-500">Invoice -</p>
                    </div>
                    <span id="detail-status" class="rounded-full px-3 py-1 text-[10px] font-bold uppercase">-</span>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-950">
                        <p class="text-[10px] font-bold uppercase text-slate-400">Total</p>
                        <p id="detail-total" class="mt-1 text-sm font-black">Rp 0</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/20">
                        <p class="text-[10px] font-bold uppercase text-emerald-700">Dibayar</p>
                        <p id="detail-paid" class="mt-1 text-sm font-black text-emerald-700">Rp 0</p>
                    </div>
                    <div class="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/20">
                        <p class="text-[10px] font-bold uppercase text-amber-700">Sisa</p>
                        <p id="detail-remaining" class="mt-1 text-sm font-black text-amber-700">Rp 0</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 p-3 dark:border-slate-800">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-bold text-slate-900 dark:text-white">Pembayaran Piutang</p>
                        <p id="detail-due-date" class="text-xs font-semibold text-slate-500">Tempo: -</p>
                    </div>
                    <div class="mt-3 grid gap-2">
                        <input id="receivable-pay-amount" class="rounded-xl border border-slate-200 px-3 py-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-950" type="number" min="0" step="0.01" placeholder="Nominal bayar" />
                        <select id="receivable-pay-method" class="rounded-xl border border-slate-200 px-3 py-3 text-sm font-semibold focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-950">
                            <option value="cash">Tunai</option>
                            <option value="bank">Bank / EDC</option>
                        </select>
                        <textarea id="receivable-pay-notes" class="rounded-xl border border-slate-200 px-3 py-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-950" rows="2" placeholder="Catatan pembayaran (opsional)"></textarea>
                        <button id="receivable-pay-submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 py-3 text-sm font-black text-white disabled:opacity-50" type="button">
                            <span class="material-symbols-outlined text-lg">payments</span>
                            Bayar
                        </button>
                        <a id="receivable-sale-detail-link" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" href="{{ $saleDetailBaseUrl }}">
                            <span class="material-symbols-outlined text-lg">receipt_long</span>
                            Lihat Detail Transaksi
                        </a>
                        <p id="receivable-pay-feedback" class="hidden rounded-xl border px-3 py-2 text-xs font-semibold"></p>
                    </div>
                </div>
                <div>
                    <p class="mb-2 text-sm font-bold text-slate-900 dark:text-white">Riwayat Cicilan</p>
                    <div id="receivable-payment-history" class="space-y-2"></div>
                </div>
            </div>
        </aside>

        @if ($isPosReceivable)
            </div>
        </div>
        @endif
    </div>

    @include('partials.ui-helpers')
    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content || '';
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
            const state = {
                page: 1,
                lastPage: 1,
                selected: null,
                accountMapping: null,
            };

            const el = {
                activeTotal: document.getElementById('receivable-active-total'),
                activeCount: document.getElementById('receivable-active-count'),
                overdueTotal: document.getElementById('receivable-overdue-total'),
                paidTotal: document.getElementById('receivable-paid-total'),
                paidCount: document.getElementById('receivable-paid-count'),
                search: document.getElementById('receivable-search'),
                status: document.getElementById('receivable-status-filter'),
                refresh: document.getElementById('receivable-refresh'),
                list: document.getElementById('receivable-list'),
                empty: document.getElementById('receivable-empty'),
                meta: document.getElementById('receivable-list-meta'),
                prev: document.getElementById('receivable-prev'),
                next: document.getElementById('receivable-next'),
                detailEmpty: document.getElementById('receivable-detail-empty'),
                detailContent: document.getElementById('receivable-detail-content'),
                detailCustomer: document.getElementById('detail-customer'),
                detailSale: document.getElementById('detail-sale'),
                detailStatus: document.getElementById('detail-status'),
                detailTotal: document.getElementById('detail-total'),
                detailPaid: document.getElementById('detail-paid'),
                detailRemaining: document.getElementById('detail-remaining'),
                detailDueDate: document.getElementById('detail-due-date'),
                payAmount: document.getElementById('receivable-pay-amount'),
                payMethod: document.getElementById('receivable-pay-method'),
                payNotes: document.getElementById('receivable-pay-notes'),
                paySubmit: document.getElementById('receivable-pay-submit'),
                saleDetailLink: document.getElementById('receivable-sale-detail-link'),
                payFeedback: document.getElementById('receivable-pay-feedback'),
                paymentHistory: document.getElementById('receivable-payment-history'),
            };

            const saleDetailBaseUrl = @json($saleDetailBaseUrl);

            const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Number(value || 0));

            const formatDate = (value) => {
                if (!value) {
                    return '-';
                }
                const parsed = new Date(value);
                return Number.isNaN(parsed.getTime())
                    ? value
                    : parsed.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
            };

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

            const statusMeta = (row) => {
                if (row.is_overdue) {
                    return ['Jatuh tempo', 'bg-rose-100 text-rose-700'];
                }
                if (row.status === 'paid') {
                    return ['Lunas', 'bg-emerald-100 text-emerald-700'];
                }
                if (row.status === 'partial') {
                    return ['Cicilan', 'bg-amber-100 text-amber-700'];
                }
                return ['Belum dibayar', 'bg-slate-100 text-slate-700'];
            };

            const apiGet = async (url) => {
                const response = await window.PosUi.fetchWithTimeout(url, { headers }, 12000);
                if (!response.ok) {
                    const mapped = await window.PosUi.mapApiError(response, 'Gagal memuat data piutang.');
                    throw new Error(mapped.message);
                }
                return response.json();
            };

            const setFeedback = (message = '', tone = 'neutral') => {
                if (!message) {
                    el.payFeedback.className = 'hidden rounded-xl border px-3 py-2 text-xs font-semibold';
                    el.payFeedback.textContent = '';
                    return;
                }

                const toneClass = tone === 'error'
                    ? 'border-rose-200 bg-rose-50 text-rose-700'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-700';
                el.payFeedback.className = `rounded-xl border px-3 py-2 text-xs font-semibold ${toneClass}`;
                el.payFeedback.textContent = message;
            };

            const renderSummary = (summary = {}) => {
                el.activeTotal.textContent = formatCurrency(summary.active_total || 0);
                el.activeCount.textContent = `${summary.active_count || 0} invoice`;
                el.overdueTotal.textContent = formatCurrency(summary.overdue_total || 0);
                el.paidTotal.textContent = formatCurrency(summary.paid_total || 0);
                el.paidCount.textContent = `${summary.paid_count || 0} invoice`;
            };

            const renderList = (rows = [], meta = {}) => {
                el.list.innerHTML = '';
                el.empty.classList.toggle('hidden', rows.length > 0);
                state.page = Number(meta.page || 1);
                state.lastPage = Number(meta.last_page || 1);
                el.meta.textContent = `${meta.total || 0} data piutang`;
                el.prev.disabled = state.page <= 1;
                el.next.disabled = state.page >= state.lastPage;

                rows.forEach((row) => {
                    const [label, badgeClass] = statusMeta(row);
                    const card = document.createElement('button');
                    card.type = 'button';
                    card.className = 'w-full rounded-2xl border border-slate-200 bg-white p-4 text-left transition hover:border-primary/40 dark:border-slate-800 dark:bg-slate-950';
                    card.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-slate-900 dark:text-white">${escapeHtml(row.customer_name || 'Customer')}</p>
                                <p class="mt-1 text-xs text-slate-500">Invoice ${escapeHtml(row.sale_id || '-')}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold uppercase ${badgeClass}">${label}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
                            <div><span class="block text-slate-400">Total</span><strong>${formatCurrency(row.total_amount)}</strong></div>
                            <div><span class="block text-slate-400">Dibayar</span><strong>${formatCurrency(row.paid_amount)}</strong></div>
                            <div><span class="block text-slate-400">Sisa</span><strong class="text-amber-700">${formatCurrency(row.remaining_amount)}</strong></div>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">Jatuh tempo: ${formatDate(row.due_date)}</p>
                    `;
                    card.addEventListener('click', () => loadDetail(row.id));
                    el.list.appendChild(card);
                });
            };

            const isPosMode = @json($isPosReceivable);
            const sheetOverlay = document.getElementById('rcv-sheet-overlay');
            const sheetBackdrop = document.getElementById('rcv-sheet-backdrop');
            const sheetClose = document.getElementById('rcv-sheet-close');

            const openSheet = () => { if (sheetOverlay) { sheetOverlay.classList.add('active'); document.body.style.overflow = 'hidden'; } };
            const closeSheet = () => { if (sheetOverlay) { sheetOverlay.classList.remove('active'); document.body.style.overflow = ''; } };
            if (sheetBackdrop) sheetBackdrop.addEventListener('click', closeSheet);
            if (sheetClose) sheetClose.addEventListener('click', closeSheet);

            const renderDetail = (row) => {
                state.selected = row;
                setFeedback('');
                el.detailEmpty.classList.add('hidden');
                el.detailContent.classList.remove('hidden');
                if (isPosMode) openSheet();
                const [label, badgeClass] = statusMeta(row);
                el.detailCustomer.textContent = row.customer_name || 'Customer';
                el.detailSale.textContent = `Invoice ${row.sale_id || '-'} - ${formatDate(row.sale?.transaction_date || row.created_at)}`;
                el.detailStatus.textContent = label;
                el.detailStatus.className = `rounded-full px-3 py-1 text-[10px] font-bold uppercase ${badgeClass}`;
                el.detailTotal.textContent = formatCurrency(row.total_amount);
                el.detailPaid.textContent = formatCurrency(row.paid_amount);
                el.detailRemaining.textContent = formatCurrency(row.remaining_amount);
                el.detailDueDate.textContent = `Tempo: ${formatDate(row.due_date)}`;
                el.payAmount.value = row.status === 'paid' ? '' : row.remaining_amount;
                el.paySubmit.disabled = row.status === 'paid';
                if (el.saleDetailLink) {
                    el.saleDetailLink.href = `${saleDetailBaseUrl}/${encodeURIComponent(row.sale_id)}`;
                }

                const payments = Array.isArray(row.payments) ? row.payments : [];
                if (!payments.length) {
                    el.paymentHistory.innerHTML = '<p class="rounded-xl border border-dashed border-slate-200 px-3 py-3 text-xs text-slate-500">Belum ada pembayaran cicilan.</p>';
                    return;
                }

                el.paymentHistory.innerHTML = '';
                payments.forEach((payment) => {
                    const item = document.createElement('div');
                    item.className = 'rounded-xl border border-slate-200 px-3 py-3 text-xs dark:border-slate-800';
                    item.innerHTML = `
                        <div class="flex items-center justify-between gap-2">
                            <strong>${formatCurrency(payment.amount)}</strong>
                            <span class="rounded-full bg-slate-100 px-2 py-1 font-bold uppercase text-slate-600">${escapeHtml(payment.payment_method)}</span>
                        </div>
                        <p class="mt-1 text-slate-500">${formatDate(payment.created_at)}${payment.notes ? ` - ${escapeHtml(payment.notes)}` : ''}</p>
                    `;
                    el.paymentHistory.appendChild(item);
                });
            };

            const loadBootstrap = async () => {
                const payload = await apiGet('/api/v1/bootstrap');
                const data = window.PosUi.unwrapApiPayload(payload) || payload || {};
                state.accountMapping = data.account_mapping || null;
            };

            const loadList = async () => {
                el.meta.textContent = 'Memuat data...';
                const params = new URLSearchParams({
                    status: el.status.value || 'open',
                    per_page: '20',
                    page: String(state.page || 1),
                });
                const search = String(el.search.value || '').trim();
                if (search) {
                    params.set('search', search);
                }

                try {
                    const payload = await apiGet(`/api/v1/receivables?${params.toString()}`);
                    const data = window.PosUi.unwrapApiPayload(payload) || {};
                    renderSummary(data.summary || {});
                    renderList(data.receivables || [], payload.meta || {});
                } catch (error) {
                    el.meta.textContent = error.message || 'Gagal memuat data.';
                    renderList([], {});
                }
            };

            const loadDetail = async (id) => {
                const payload = await apiGet(`/api/v1/receivables/${id}`);
                const data = window.PosUi.unwrapApiPayload(payload) || {};
                renderDetail(data.receivable);
            };

            const submitPayment = async () => {
                if (!state.selected) {
                    return;
                }

                const amount = Number(el.payAmount.value || 0);
                if (!Number.isFinite(amount) || amount <= 0) {
                    setFeedback('Nominal pembayaran wajib lebih dari nol.', 'error');
                    return;
                }

                if (amount > Number(state.selected.remaining_amount || 0)) {
                    setFeedback('Nominal melebihi sisa piutang.', 'error');
                    return;
                }

                const method = el.payMethod.value || 'cash';
                const accountId = method === 'bank'
                    ? state.accountMapping?.bank_account_id
                    : state.accountMapping?.cash_account_id;

                if (!accountId) {
                    setFeedback(method === 'bank' ? 'Akun bank belum disetel.' : 'Akun kas belum disetel.', 'error');
                    return;
                }

                const allowed = window.PosUi.confirmRiskAction({
                    title: 'Konfirmasi Pembayaran Piutang',
                    message: `Catat pembayaran ${formatCurrency(amount)} untuk ${state.selected.customer_name || 'customer'}?`,
                });
                if (!allowed) {
                    return;
                }

                await window.PosUi.runWithSubmitGuard(el.paySubmit, async () => {
                    const response = await window.PosUi.fetchWithTimeout(`/api/v1/receivables/${state.selected.id}/payments`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            ...headers,
                        },
                        body: JSON.stringify({
                            amount,
                            payment_method: method,
                            account_id: accountId,
                            notes: String(el.payNotes.value || '').trim() || null,
                        }),
                    }, 15000);

                    if (!response.ok) {
                        const mapped = await window.PosUi.mapApiError(response, 'Gagal menyimpan pembayaran piutang.');
                        throw new Error(mapped.message);
                    }

                    const result = await response.json().catch(() => ({}));
                    const resultData = window.PosUi.unwrapApiPayload(result) || result || {};
                    const updatedReceivable = resultData.receivable || {};

                    setFeedback('Pembayaran piutang berhasil dicatat.', 'success');
                    el.payNotes.value = '';

                    if (updatedReceivable.status === 'paid' && (updatedReceivable.sale_id || state.selected.sale_id)) {
                        const saleId = updatedReceivable.sale_id || state.selected.sale_id;
                        closeSheet();
                        setTimeout(() => {
                            window.location.href = `/pos/sales/${encodeURIComponent(saleId)}/receipt`;
                        }, 600);
                        return;
                    }

                    await loadDetail(state.selected.id);
                    await loadList();
                }).catch((error) => setFeedback(error.message || 'Gagal menyimpan pembayaran piutang.', 'error'));
            };

            let searchTimer = null;
            el.search.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    state.page = 1;
                    loadList();
                }, 350);
            });
            el.status.addEventListener('change', () => {
                state.page = 1;
                loadList();
            });
            el.refresh.addEventListener('click', loadList);
            el.prev.addEventListener('click', () => {
                if (state.page > 1) {
                    state.page -= 1;
                    loadList();
                }
            });
            el.next.addEventListener('click', () => {
                if (state.page < state.lastPage) {
                    state.page += 1;
                    loadList();
                }
            });
            el.paySubmit.addEventListener('click', submitPayment);

            Promise.all([loadBootstrap(), loadList()]).catch(() => loadList());
        })();
    </script>
@endsection
