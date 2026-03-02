@extends('layouts.app')

@section('page_title', 'Ringkasan Tutup Shift Kasir')
@section('body_class', 'font-display text-slate-900')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-slate-50 shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
        <style>
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
            .ios-blur {
                backdrop-filter: blur(20px);
                background-color: rgba(255, 255, 255, 0.8);
            }
        </style>
@endpush

@section('header')
        <x-page-header title="Ringkasan Tutup Shift" :breadcrumbs="['Laporan Audit']" backUrl="/menu/shift" :showMenuButton="false" />
@endsection

@section('content')
        <div class="relative flex h-full flex-col">
            <div class="flex-1 overflow-y-auto pb-32">
                <div class="px-4 py-6">
                    <div id="history-view" class="hidden">
                        <h2 class="text-base font-bold text-slate-800 mb-3">Riwayat Shift</h2>
                        <div id="history-list" class="space-y-3"></div>
                        <p id="history-empty" class="text-sm text-slate-400 hidden">Belum ada riwayat shift.</p>
                    </div>
                    <div id="detail-view">
                        <p class="text-sm text-slate-500 mb-4">ID Shift: <span id="shift-code">-</span></p>
                        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm mb-6">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-600">
                                <span class="material-symbols-outlined text-2xl">person</span>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800" id="shift-cashier">-</h3>
                                <p class="text-xs text-slate-500 font-medium" id="shift-opened">Mulai: -</p>
                                <p class="text-xs text-slate-500 font-medium" id="shift-closed">Selesai: -</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-100">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Penjualan</p>
                                <p class="text-lg font-bold text-slate-900" id="shift-sales-total">Rp 0</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Transaksi</p>
                                <p class="text-lg font-bold text-slate-900" id="shift-sales-count">0 Transaksi</p>
                            </div>
                        </div>
                    </div>
                        <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-3 px-1">Rekonsiliasi Kas</h2>
                        <div class="space-y-4">
                        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Ekspektasi Sistem</p>
                                    <p class="text-base font-bold text-slate-900" id="expected-cash">Rp 0</p>
                                </div>
                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Kas Aktual</p>
                                    <p class="text-base font-bold text-slate-900" id="actual-cash">Rp 0</p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between bg-slate-50 p-3 rounded-xl border border-slate-200 mt-4" id="difference-card">
                                <span class="text-sm font-semibold text-slate-600" id="diff-label">Selisih Kas</span>
                                <span class="text-sm font-bold text-slate-900" id="diff-cash">Rp 0</span>
                            </div>
                            <div class="pt-3">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Catatan</p>
                                <p class="text-sm text-slate-600" id="close-note">-</p>
                            </div>
                        </div>
                            <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wider mt-8 mb-3 px-1">Metode Pembayaran</h2>
                            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
                            <div id="payment-list"></div>
                            <div class="p-4 bg-slate-50 flex items-center justify-between">
                                <span class="text-sm font-bold text-slate-800">Total Akhir</span>
                                <span class="text-base font-black text-primary" id="payment-total">Rp 0</span>
                            </div>
                        </div>
                            <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wider mt-8 mb-3 px-1">Ringkasan Temuan</h2>
                            <div id="findings-list" class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm"></div>
                        </div>
                    </div>
                </div>
            </div>
            <section class="absolute bottom-0 left-0 right-0 bg-white border-t border-slate-200 p-4 pb-8 space-y-3">
                <div class="flex gap-3">
                    <button class="w-14 h-14 rounded-2xl border border-slate-200 flex items-center justify-center text-slate-600 active:bg-slate-100">
                        <span class="material-symbols-outlined">print</span>
                    </button>
                    <button class="flex-1 h-14 bg-primary text-white font-bold rounded-2xl shadow-lg shadow-primary/30 flex items-center justify-center gap-2 active:scale-[0.98] transition-transform" onclick="location.href='/menu/shift'">
                        <span class="material-symbols-outlined font-bold">done</span>
                        SELESAI
                    </button>
                </div>
            </section>
        </div>
        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    historyView: document.getElementById('history-view'),
                    historyList: document.getElementById('history-list'),
                    historyEmpty: document.getElementById('history-empty'),
                    detailView: document.getElementById('detail-view'),
                    shiftCode: document.getElementById('shift-code'),
                    cashier: document.getElementById('shift-cashier'),
                    opened: document.getElementById('shift-opened'),
                    closed: document.getElementById('shift-closed'),
                    salesTotal: document.getElementById('shift-sales-total'),
                    salesCount: document.getElementById('shift-sales-count'),
                    expectedCash: document.getElementById('expected-cash'),
                    actualCash: document.getElementById('actual-cash'),
                    diffCard: document.getElementById('difference-card'),
                    diffLabel: document.getElementById('diff-label'),
                    diffCash: document.getElementById('diff-cash'),
                    closeNote: document.getElementById('close-note'),
                    paymentList: document.getElementById('payment-list'),
                    paymentTotal: document.getElementById('payment-total'),
                    findingsList: document.getElementById('findings-list'),
                };

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const formatDateTime = (value) => {
                    if (!value) {
                        return '-';
                    }
                    return new Intl.DateTimeFormat('id-ID', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    }).format(new Date(value));
                };

                const labelForMethod = (method) => {
                    if (method === 'cash') return 'Tunai';
                    if (method === 'qris') return 'QRIS';
                    if (method === 'bank') return 'Transfer/EDC';
                    return method;
                };

                const iconForMethod = (method) => {
                    if (method === 'cash') return 'payments';
                    if (method === 'qris') return 'qr_code_2';
                    if (method === 'bank') return 'credit_card';
                    return 'payments';
                };

                const colorForMethod = (method) => {
                    if (method === 'cash') return 'bg-emerald-100 text-emerald-600';
                    if (method === 'qris') return 'bg-blue-100 text-blue-600';
                    if (method === 'bank') return 'bg-purple-100 text-purple-600';
                    return 'bg-slate-100 text-slate-600';
                };

                const renderPayments = (payments) => {
                    el.paymentList.innerHTML = '';
                    if (!payments.length) {
                        el.paymentList.innerHTML = '<div class="p-4 text-sm text-slate-400">Belum ada pembayaran.</div>';
                        return;
                    }
                    payments.forEach((payment, index) => {
                        const row = document.createElement('div');
                        row.className = `p-4 flex items-center justify-between${index < payments.length - 1 ? ' border-b border-slate-100' : ''}`;
                        const method = payment.method;
                        row.innerHTML = `
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg ${colorForMethod(method)} flex items-center justify-center">
                                    <span class="material-symbols-outlined text-xl">${iconForMethod(method)}</span>
                                </div>
                                <span class="text-sm font-semibold text-slate-700">${labelForMethod(method)}</span>
                            </div>
                            <span class="text-sm font-bold text-slate-900">${formatCurrency(payment.total)}</span>
                        `;
                        el.paymentList.appendChild(row);
                    });
                };

                const findingTone = (severity) => {
                    if (severity === 'high') return 'bg-red-50 text-red-700 border-red-100';
                    if (severity === 'warn') return 'bg-amber-50 text-amber-700 border-amber-100';
                    if (severity === 'ok') return 'bg-emerald-50 text-emerald-700 border-emerald-100';
                    return 'bg-blue-50 text-blue-700 border-blue-100';
                };

                const renderFindings = (findings) => {
                    el.findingsList.innerHTML = '';
                    if (!findings || !findings.length) {
                        el.findingsList.innerHTML = '<div class="p-4 text-sm text-slate-400">Tidak ada temuan.</div>';
                        return;
                    }

                    findings.forEach((finding, index) => {
                        const row = document.createElement('div');
                        row.className = `p-4 border ${findingTone(finding.severity)}${index < findings.length - 1 ? ' border-b' : ''}`;
                        row.innerHTML = `
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs font-bold uppercase tracking-wider">${finding.code || 'FINDING'}</p>
                                ${typeof finding.amount === 'number' ? `<span class="text-xs font-semibold">${formatCurrency(finding.amount)}</span>` : ''}
                            </div>
                            <p class="text-sm font-medium mt-1">${finding.message || '-'}</p>
                        `;
                        el.findingsList.appendChild(row);
                    });
                };

                const updateDifferenceStyle = (difference) => {
                    const diffValue = Math.abs(difference || 0);
                    const label = difference === 0 ? 'Selisih Kas' : difference < 0 ? 'Selisih Kas (Kurang)' : 'Selisih Kas (Lebih)';
                    el.diffLabel.textContent = label;
                    el.diffCash.textContent = `${difference < 0 ? '-' : ''}${formatCurrency(diffValue)}`;

                    if (difference === 0) {
                        el.diffCard.className = 'flex items-center justify-between bg-emerald-50 p-3 rounded-xl border border-emerald-100';
                        el.diffLabel.className = 'text-sm font-semibold text-emerald-600';
                        el.diffCash.className = 'text-sm font-bold text-emerald-600';
                    } else if (difference < 0) {
                        el.diffCard.className = 'flex items-center justify-between bg-red-50 p-3 rounded-xl border border-red-100';
                        el.diffLabel.className = 'text-sm font-semibold text-red-600';
                        el.diffCash.className = 'text-sm font-bold text-red-600';
                    } else {
                        el.diffCard.className = 'flex items-center justify-between bg-amber-50 p-3 rounded-xl border border-amber-100';
                        el.diffLabel.className = 'text-sm font-semibold text-amber-600';
                        el.diffCash.className = 'text-sm font-bold text-amber-600';
                    }
                };

                const loadSummary = async () => {
                    const shiftId = new URLSearchParams(window.location.search).get('shift_id');
                    const url = shiftId ? `/api/v1/shifts/close-summary?shift_id=${shiftId}` : '/api/v1/shifts/close-summary';
                    const response = await fetch(url, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat ringkasan shift.');
                    }
                    return response.json();
                };

                const loadHistory = async () => {
                    const response = await fetch('/api/v1/shifts/closed', { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat riwayat shift.');
                    }
                    return response.json();
                };

                const renderHistory = (rows) => {
                    el.historyList.innerHTML = '';
                    if (!rows.length) {
                        el.historyEmpty.classList.remove('hidden');
                        return;
                    }
                    el.historyEmpty.classList.add('hidden');

                    rows.forEach((row) => {
                        const opened = formatDateTime(row.opened_at);
                        const closed = formatDateTime(row.closed_at);
                        const card = document.createElement('a');
                        card.href = `/shift/close-summary?shift_id=${row.id}`;
                        card.className = 'block bg-white rounded-2xl border border-slate-200 p-4 shadow-sm';
                        card.innerHTML = `
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-slate-400 uppercase tracking-wider">${row.branch || 'Cabang'}</p>
                                    <h3 class="text-sm font-bold text-slate-800">${row.cashier || 'Kasir'}</h3>
                                </div>
                                <span class="text-xs font-semibold text-slate-500">#${String(row.id).slice(0, 6).toUpperCase()}</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                                <span>Mulai: ${opened}</span>
                                <span>Selesai: ${closed}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-sm">
                                <span class="text-slate-600">${row.sales_count} transaksi</span>
                                <span class="font-bold text-slate-900">${formatCurrency(row.sales_total)}</span>
                            </div>
                        `;
                        el.historyList.appendChild(card);
                    });
                };

                (async () => {
                    const shiftId = new URLSearchParams(window.location.search).get('shift_id');

                    if (!shiftId) {
                        el.detailView.classList.add('hidden');
                        el.historyView.classList.remove('hidden');
                        try {
                            const data = await loadHistory();
                            renderHistory(data.shifts || []);
                        } catch (error) {
                            el.historyList.innerHTML = `<div class="p-4 text-sm text-red-500">${error.message}</div>`;
                        }
                        return;
                    }

                    el.detailView.classList.remove('hidden');
                    el.historyView.classList.add('hidden');

                    try {
                        const data = await loadSummary();
                        const shift = data.shift || {};
                        el.shiftCode.textContent = `#${String(shift.id || '-').slice(0, 8).toUpperCase()}`;
                        el.cashier.textContent = data.cashier?.name || '-';
                        el.opened.textContent = `Mulai: ${formatDateTime(shift.opened_at)}`;
                        el.closed.textContent = `Selesai: ${formatDateTime(shift.closed_at)}`;
                        el.salesTotal.textContent = formatCurrency(data.sales?.total || 0);
                        el.salesCount.textContent = `${data.sales?.count || 0} Transaksi`;
                        el.expectedCash.textContent = formatCurrency(shift.system_cash_total || 0);
                        el.actualCash.textContent = formatCurrency(shift.closing_cash || 0);
                        el.closeNote.textContent = shift.close_note || '-';
                        el.paymentTotal.textContent = formatCurrency(data.sales?.total || 0);
                        updateDifferenceStyle(shift.difference || 0);
                        renderPayments(data.payments || []);
                        renderFindings(data.findings || []);
                    } catch (error) {
                        el.paymentList.innerHTML = `<div class="p-4 text-sm text-red-500">${error.message}</div>`;
                        el.findingsList.innerHTML = `<div class="p-4 text-sm text-red-500">${error.message}</div>`;
                    }
                })();
            })();
        </script>
    @endsection



