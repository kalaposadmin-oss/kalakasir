@extends('layouts.app')

@section('page_title', 'Void & Refund Transaksi')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-800 dark:text-slate-100')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 min-h-0 overflow-hidden p-0')

@push('head')
        <style>
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
            .ios-input-shadow {
                box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
            }
        </style>
@endpush

@php
    $voidRefundSaleId = (string) ($saleId ?? request()->query('sale_id', ''));
    $voidRefundCurrentPath = $currentPath ?? ($voidRefundSaleId !== '' ? '/pos/sales/'.$voidRefundSaleId.'/void-refund' : '/pos/void-refund');
    $submenuBackUrl = $backUrl ?? ($voidRefundSaleId !== '' ? '/pos/sales/'.$voidRefundSaleId : '/pos/history');
    $detailUrl = $detailUrl ?? ($voidRefundSaleId !== '' ? '/pos/sales/'.$voidRefundSaleId : '/pos/history');
    $salesHistoryHref = $historyUrl ?? '/pos/history';
    $voidRefundDashboardHref = pos_default_url();
    $voidRefundBackofficeHref = static function (string $targetPath) use ($voidRefundCurrentPath): string {
        $separator = str_contains($targetPath, '?') ? '&' : '?';

        return '/portal/switch/backoffice?next='.rawurlencode($targetPath.$separator.'from='.rawurlencode($voidRefundCurrentPath));
    };
    if ($submenuBackUrl !== $salesHistoryHref && str_starts_with($submenuBackUrl, '/pos/')) {
        $salesHistoryHref .= '?from='.urlencode($submenuBackUrl);
    }
@endphp

@section('header')
        <x-page-header title="Void & Refund" subtitle="INV/20231024/0042" :backUrl="$submenuBackUrl" :showMenuButton="true">
            <x-slot name="actions">
                <span class="px-2 py-1 bg-slate-100 text-slate-600 rounded text-[10px] font-bold">MEJA 08</span>
            </x-slot>
        </x-page-header>
@endsection

@section('content')
        <div class="relative flex h-full min-h-0 flex-col">
            <div class="flex-1 min-h-0 overflow-y-auto bg-slate-50 pb-6">
                <div class="px-4 py-4 space-y-4">
                    <div class="bg-blue-50 border border-blue-100 p-3 rounded-xl flex gap-3">
                        <span class="material-symbols-outlined text-primary text-xl">info</span>
                        <p class="text-xs text-blue-800 leading-relaxed">
                            Pilih item yang ingin di-refund. Nominal refund dihitung per line item, dan payout akan mengikuti alokasi metode pembayaran transaksi asal.
                        </p>
                    </div>
                    <div class="space-y-3">
                        <h2 class="text-xs font-bold text-slate-500 uppercase tracking-widest px-1">Daftar Item Transaksi</h2>
                        <div id="refund-items" class="space-y-3"></div>
                    </div>
                    <div class="space-y-4 pt-4 border-t border-slate-200">
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest px-1">Alasan Void/Refund</label>
                            <select id="refund-reason" class="w-full px-4 py-3 rounded-xl border-slate-200 bg-white text-sm focus:ring-primary focus:border-primary ios-input-shadow">
                                <option value="">Pilih alasan...</option>
                                <option value="V01">V01 - Salah input item</option>
                                <option value="V02">V02 - Salah harga/diskon</option>
                                <option value="V03">V03 - Duplicate input</option>
                                <option value="V04">V04 - Pelanggan batal sebelum bayar</option>
                                <option value="V99">V99 - Lainnya</option>
                            </select>
                            <textarea id="refund-note" class="w-full px-4 py-3 rounded-xl border-slate-200 bg-white text-sm focus:ring-primary focus:border-primary ios-input-shadow mt-2 h-20" placeholder="Keterangan tambahan (wajib jika pilih V99)"></textarea>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest px-1">Alokasi Payout Refund</label>
                            <div id="refund-payments" class="space-y-2"></div>
                            <div class="flex items-center justify-between px-1">
                                <p class="text-[10px] text-slate-400 italic">Untuk transaksi multi-payment, payout refund akan dibagi mengikuti metode pembayaran asal. Nilai ini bisa diubah manual.</p>
                                <button id="refund-payment-reset" type="button" class="text-[10px] font-semibold text-primary">Reset Otomatis</button>
                            </div>
                            <p id="refund-payment-warning" class="hidden px-1 text-[10px] font-semibold text-red-500"></p>
                        </div>
                    </div>
                </div>
            </div>
            <section class="shrink-0 bg-white border-t border-slate-200 shadow-[0_-10px_25px_-5px_rgba(0,0,0,0.05)] overflow-hidden z-50 app-bottom-safe-footer">
                <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto my-3"></div>
                <div class="px-6 py-4 space-y-4">
                    <div class="flex justify-between items-center">
                        <div class="space-y-0.5">
                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Total yang akan di-refund</p>
                            <div class="flex items-baseline gap-1">
                                <span class="text-lg font-bold text-slate-900">Rp</span>
                                <span id="refund-total" class="text-3xl font-black text-slate-900">0</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"><span id="refund-count">0</span> Item Terpilih</p>
                        </div>
                    </div>
                    <div class="flex gap-3 pb-4">
                        <button class="flex-1 h-14 bg-white border border-slate-200 text-slate-600 font-bold rounded-2xl flex items-center justify-center active:bg-slate-50 transition-colors" onclick="location.href='{{ $submenuBackUrl }}'">
                            BATAL
                        </button>
                        <button id="process-refund" class="flex-[2] h-14 bg-red-500 text-white font-black px-6 rounded-2xl flex items-center justify-center gap-2 shadow-lg shadow-red-500/20 active:scale-95 transition-transform">
                            <span class="material-symbols-outlined font-bold">assignment_return</span>
                            PROSES REFUND
                        </button>
                    </div>
                    <nav class="border-t border-slate-200 pt-3">
                        <div class="flex justify-around items-center">
                            <a class="flex flex-col items-center gap-1 text-slate-400" href="{{ $voidRefundDashboardHref }}">
                                <span class="material-symbols-outlined">home</span>
                                <span class="text-[10px] font-medium">Beranda</span>
                            </a>
                            <a class="flex flex-col items-center gap-1 text-slate-400" href="{{ $voidRefundBackofficeHref('/backoffice/inventory') }}">
                                <span class="material-symbols-outlined">inventory</span>
                                <span class="text-[10px] font-medium">Stok</span>
                            </a>
                            <a class="flex flex-col items-center gap-1 text-primary" href="{{ $salesHistoryHref }}">
                                <span class="material-symbols-outlined">receipt</span>
                                <span class="text-[10px] font-bold">Transaksi</span>
                            </a>
                            <a class="flex flex-col items-center gap-1 text-slate-400" href="{{ $voidRefundBackofficeHref('/backoffice/finance/summary') }}">
                                <span class="material-symbols-outlined">monitoring</span>
                                <span class="text-[10px] font-medium">Laporan</span>
                            </a>
                            <a class="flex flex-col items-center gap-1 text-slate-400" href="#" data-menu-trigger>
                                <span class="material-symbols-outlined">settings</span>
                                <span class="text-[10px] font-medium">Pengaturan</span>
                            </a>
                        </div>
                    </nav>
                </div>
            </section>
        </div>
        @include('partials.ui-helpers')
        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const els = {
                    items: document.getElementById('refund-items'),
                    total: document.getElementById('refund-total'),
                    count: document.getElementById('refund-count'),
                    reason: document.getElementById('refund-reason'),
                    note: document.getElementById('refund-note'),
                    payments: document.getElementById('refund-payments'),
                    paymentWarning: document.getElementById('refund-payment-warning'),
                    paymentReset: document.getElementById('refund-payment-reset'),
                    process: document.getElementById('process-refund'),
                };

                const roundCurrency = (value) => Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100;

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const state = {
                    saleId: @json($voidRefundSaleId),
                    sale: null,
                    items: [],
                    payments: [],
                    paymentDrafts: [],
                    paymentManualOverride: false,
                };

                const loadSale = async () => {
                    const response = await fetch(`/api/v1/sales/${state.saleId}`, { headers });
                    if (!response.ok) {
                        const mapped = await window.PosUi.mapApiError(response, 'Gagal memuat detail transaksi.');
                        throw new Error(mapped.message);
                    }
                    return response.json();
                };

                const allocationBasisTotal = () => state.items.reduce((sum, item) => sum + Math.max((item.price * item.maxQty) - item.lineDiscount, 0), 0);

                const estimatedLineRefund = (item) => {
                    const allocationBasis = allocationBasisTotal();
                    const saleDiscount = Number(state.sale?.discount || 0);
                    const saleTax = Number(state.sale?.tax || 0);
                    const grossAmount = item.refundQty * item.price;
                    const itemDiscountAmount = item.maxQty > 0 ? (item.lineDiscount * (item.refundQty / item.maxQty)) : 0;
                    const lineNetBeforeTransactionDiscount = Math.max((item.price * item.maxQty) - item.lineDiscount, 0);
                    const fullLineTransactionDiscount = allocationBasis > 0
                        ? saleDiscount * (lineNetBeforeTransactionDiscount / allocationBasis)
                        : 0;
                    const transactionDiscountAmount = item.maxQty > 0
                        ? fullLineTransactionDiscount * (item.refundQty / item.maxQty)
                        : 0;
                    const saleNetBeforeTax = Math.max(allocationBasis - saleDiscount, 0);
                    const fullLineTax = saleNetBeforeTax > 0
                        ? saleTax * (Math.max(lineNetBeforeTransactionDiscount - fullLineTransactionDiscount, 0) / saleNetBeforeTax)
                        : 0;
                    const taxAmount = item.maxQty > 0 ? fullLineTax * (item.refundQty / item.maxQty) : 0;

                    return roundCurrency(grossAmount - itemDiscountAmount - transactionDiscountAmount + taxAmount);
                };

                const defaultPaymentAllocations = (refundTotal) => {
                    let remaining = roundCurrency(refundTotal);

                    return state.payments.map((payment) => {
                        const allocated = roundCurrency(Math.min(remaining, Number(payment.amount || 0)));
                        remaining = roundCurrency(Math.max(remaining - allocated, 0));

                        return {
                            sale_payment_id: payment.id,
                            account_id: payment.account_id,
                            payment_method_id: payment.payment_method_id,
                            method: payment.method,
                            amount: allocated,
                        };
                    }).filter((payment) => payment.amount > 0);
                };

                const normalizePaymentDrafts = (refundTotal) => {
                    if (!state.paymentManualOverride) {
                        state.paymentDrafts = defaultPaymentAllocations(refundTotal);
                        return;
                    }

                    const draftMap = new Map(state.paymentDrafts.map((payment) => [payment.sale_payment_id || payment.method, payment]));
                    state.paymentDrafts = defaultPaymentAllocations(refundTotal).map((payment) => {
                        const key = payment.sale_payment_id || payment.method;
                        const draft = draftMap.get(key);

                        return {
                            ...payment,
                            amount: roundCurrency(draft ? draft.amount : payment.amount),
                        };
                    });
                };

                const buildPaymentAllocations = () => state.paymentDrafts
                    .map((payment) => ({
                        sale_payment_id: payment.sale_payment_id,
                        account_id: payment.account_id,
                        payment_method_id: payment.payment_method_id,
                        method: payment.method,
                        amount: roundCurrency(payment.amount),
                    }))
                    .filter((payment) => payment.amount > 0);

                const paymentDraftTotal = () => roundCurrency(state.paymentDrafts.reduce((sum, payment) => sum + Number(payment.amount || 0), 0));

                const updatePaymentWarning = (refundTotal) => {
                    const allocatedTotal = paymentDraftTotal();
                    if (allocatedTotal === refundTotal) {
                        els.paymentWarning.classList.add('hidden');
                        els.paymentWarning.textContent = '';
                        return;
                    }

                    els.paymentWarning.classList.remove('hidden');
                    els.paymentWarning.textContent = `Total payout ${formatCurrency(allocatedTotal)} harus sama dengan total refund ${formatCurrency(refundTotal)}.`;
                };

                const renderPayments = (refundTotal) => {
                    normalizePaymentDrafts(refundTotal);
                    const allocations = state.paymentDrafts;

                    if (!allocations.length) {
                        els.payments.innerHTML = '<div class="text-sm text-slate-400">Payout refund akan mengikuti transaksi asal bila data pembayaran tersedia.</div>';
                        els.paymentWarning.classList.add('hidden');
                        return;
                    }

                    els.payments.innerHTML = allocations.map((payment) => `
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
                            <div>
                                <div class="font-semibold text-slate-700">${String(payment.method || '').toUpperCase()}</div>
                                <div class="text-xs text-slate-400">Payout refund ${state.paymentManualOverride ? 'manual' : 'otomatis'}</div>
                            </div>
                            <label class="flex items-center gap-2">
                                <span class="text-xs text-slate-400">Rp</span>
                                <input data-payment-index="${index}" class="w-28 rounded-lg border border-slate-200 px-3 py-2 text-right font-bold text-slate-900" type="number" min="0" step="0.01" value="${payment.amount}" />
                            </label>
                        </div>
                    `).join('');

                    updatePaymentWarning(refundTotal);
                };

                const setTotals = () => {
                    const selectedItems = state.items.filter((item) => item.selected);
                    const total = roundCurrency(selectedItems.reduce((sum, item) => sum + estimatedLineRefund(item), 0));
                    const count = selectedItems.reduce((sum, item) => sum + item.refundQty, 0);
                    els.total.textContent = new Intl.NumberFormat('id-ID').format(total);
                    els.count.textContent = count;
                    renderPayments(total);
                };

                const renderItems = () => {
                    if (!state.items.length) {
                        els.items.innerHTML = '<div class="text-sm text-slate-400">Tidak ada item.</div>';
                        setTotals();
                        return;
                    }

                    els.items.innerHTML = '';
                    state.items.forEach((item) => {
                        const card = document.createElement('div');
                        card.className = `bg-white border ${item.selected ? 'border-primary' : 'border-slate-200 opacity-70'} p-4 rounded-2xl shadow-sm`;
                        card.dataset.id = item.id;
                        card.innerHTML = `
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1">
                                    <h3 class="text-sm font-bold text-slate-800">${item.name}</h3>
                                    <p class="text-xs text-slate-500">Harga: ${formatCurrency(item.price)}</p>
                                    <p class="text-xs text-slate-400">Estimasi refund: ${formatCurrency(estimatedLineRefund(item))}</p>
                                </div>
                                <button class="toggle-item flex items-center justify-center w-6 h-6 rounded-full ${item.selected ? 'bg-primary text-white' : 'border-2 border-slate-300 text-slate-400'}">
                                    <span class="material-symbols-outlined text-lg">${item.selected ? 'check' : 'remove'}</span>
                                </button>
                            </div>
                            <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                                <span class="text-xs font-medium text-slate-600">Jumlah Refund:</span>
                                <div class="flex items-center bg-slate-100 rounded-lg p-1">
                                    <button class="qty-minus w-8 h-8 flex items-center justify-center text-primary bg-white rounded-md shadow-sm">
                                        <span class="material-symbols-outlined text-base">remove</span>
                                    </button>
                                    <span class="w-10 text-center text-sm font-bold">${item.refundQty}</span>
                                    <button class="qty-plus w-8 h-8 flex items-center justify-center text-primary bg-white rounded-md shadow-sm">
                                        <span class="material-symbols-outlined text-base">add</span>
                                    </button>
                                </div>
                            </div>
                        `;
                        els.items.appendChild(card);
                    });
                    setTotals();
                };

                const updateItem = (id, updater) => {
                    const target = state.items.find((item) => item.id === id);
                    if (!target) {
                        return;
                    }
                    updater(target);
                    renderItems();
                };

                els.items.addEventListener('click', (event) => {
                    const card = event.target.closest('[data-id]');
                    if (!card) {
                        return;
                    }
                    const id = card.dataset.id;

                    if (event.target.closest('.toggle-item')) {
                        updateItem(id, (item) => {
                            item.selected = !item.selected;
                        });
                        return;
                    }

                    if (event.target.closest('.qty-plus')) {
                        updateItem(id, (item) => {
                            item.selected = true;
                            item.refundQty = Math.min(item.refundQty + 1, item.maxQty);
                        });
                        return;
                    }

                    if (event.target.closest('.qty-minus')) {
                        updateItem(id, (item) => {
                            item.refundQty = Math.max(item.refundQty - 1, 1);
                        });
                    }
                });

                els.payments.addEventListener('input', (event) => {
                    const input = event.target.closest('[data-payment-index]');
                    if (!input) {
                        return;
                    }

                    const index = Number(input.dataset.paymentIndex);
                    if (Number.isNaN(index) || !state.paymentDrafts[index]) {
                        return;
                    }

                    state.paymentManualOverride = true;
                    state.paymentDrafts[index].amount = roundCurrency(Math.max(Number(input.value || 0), 0));
                    updatePaymentWarning(roundCurrency(state.items.filter((item) => item.selected).reduce((sum, item) => sum + estimatedLineRefund(item), 0)));
                });

                els.paymentReset.addEventListener('click', () => {
                    state.paymentManualOverride = false;
                    setTotals();
                });

                els.process.addEventListener('click', async () => {
                    if (!state.saleId) {
                        alert('ID transaksi tidak ditemukan.');
                        return;
                    }
                    const selectedItems = state.items.filter((item) => item.selected);
                    if (!selectedItems.length) {
                        alert('Pilih item untuk diproses.');
                        return;
                    }
                    const totalRefund = roundCurrency(selectedItems.reduce((sum, item) => sum + estimatedLineRefund(item), 0));
                    const totalItems = selectedItems.reduce((sum, item) => sum + item.refundQty, 0);
                    const reasonCode = (els.reason.value || '').trim();
                    const reasonNote = els.note.value.trim();
                    if (!reasonCode) {
                        alert('Pilih kode alasan void/refund terlebih dahulu.');
                        return;
                    }
                    if (reasonCode === 'V99' && reasonNote.length < 20) {
                        alert('Untuk kode V99, catatan minimal 20 karakter wajib diisi.');
                        return;
                    }
                    if (!state.sale?.register_id) {
                        alert('Register transaksi tidak ditemukan.');
                        return;
                    }

                    const paymentAllocations = buildPaymentAllocations();
                    if (paymentAllocations.length > 0) {
                        const allocatedTotal = roundCurrency(paymentAllocations.reduce((sum, payment) => sum + payment.amount, 0));
                        if (allocatedTotal !== totalRefund) {
                            alert('Alokasi payout refund tidak seimbang dengan estimasi total refund.');
                            return;
                        }
                    }

                    const allowed = window.PosUi.confirmRiskAction({
                        title: 'Konfirmasi Refund',
                        message: `Item: ${totalItems}\nTotal: ${formatCurrency(totalRefund)}\nRefund akan tercatat di audit log dan reversal finance. Lanjutkan?`,
                    });
                    if (!allowed) {
                        return;
                    }

                    await window.PosUi.runWithSubmitGuard(els.process, async () => {
                        try {
                            const body = {
                                sale_id: state.saleId,
                                register_id: state.sale.register_id,
                                shift_id: state.sale.shift_id || null,
                                reason: reasonCode + (reasonNote ? ` - ${reasonNote}` : ''),
                                items: selectedItems.map((item) => ({
                                    sale_item_id: item.id,
                                    qty: item.refundQty,
                                })),
                            };

                            if (paymentAllocations.length > 0) {
                                body.payments = paymentAllocations;
                            }

                            const response = await window.PosUi.fetchWithTimeout('/api/v1/refunds', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    ...headers,
                                },
                                body: JSON.stringify(body),
                            }, 15000);
                            if (!response.ok) {
                                const mapped = await window.PosUi.mapApiError(response, 'Gagal memproses refund.');
                                alert(mapped.message);
                                return;
                            }
                            const detailTarget = @json($detailUrl);
                            const detailParams = new URLSearchParams();
                            const currentFrom = new URLSearchParams(window.location.search).get('from');
                            if (currentFrom) {
                                detailParams.set('from', currentFrom);
                            } else if (detailTarget.startsWith('/pos/sales/')) {
                                detailParams.set('from', @json($voidRefundCurrentPath));
                            }
                            window.location.href = `${detailTarget}?${detailParams.toString()}`;
                        } catch (error) {
                            if (error?.name === 'AbortError') {
                                alert('Permintaan timeout. Cek daftar refund sebelum mengulang proses.');
                                return;
                            }
                            alert(error?.message || 'Gagal memproses refund.');
                        }
                    });
                });

                (async () => {
                    if (!state.saleId) {
                        els.items.innerHTML = '<div class="text-sm text-slate-400">ID transaksi tidak ditemukan.</div>';
                        return;
                    }
                    try {
                        const data = await loadSale();
                        state.sale = data.sale || null;
                        state.payments = data.payments || [];
                        state.paymentDrafts = [];
                        state.paymentManualOverride = false;
                        state.items = (data.items || []).map((item) => ({
                            id: item.id,
                            name: item.product_name,
                            price: Number(item.price || 0),
                            lineDiscount: Number(item.discount || 0),
                            maxQty: item.qty,
                            refundQty: item.qty,
                            selected: true,
                        }));
                        renderItems();
                    } catch (error) {
                        els.items.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                    }
                })();
            })();
        </script>
    @endsection



