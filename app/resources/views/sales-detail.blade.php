@extends('layouts.app')

@section('page_title', 'Detail Transaksi & Void')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto pb-32')

@push('head')
        <style type="text/tailwindcss">
            .status-void {
                @apply bg-red-50 text-red-600 border border-red-100;
            }
        </style>
@endpush

@section('header')
    <x-page-header title="Detail Transaksi" backUrl="/menu/sales" :showMenuButton="true">
        <x-slot:actions>
            <button class="flex items-center justify-center size-10 rounded-full bg-slate-100 dark:bg-slate-800">
                <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">print</span>
            </button>
            <button class="flex items-center justify-center size-10 rounded-full bg-slate-100 dark:bg-slate-800">
                <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">share</span>
            </button>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <div id="void-banner" class="m-4 p-4 rounded-xl bg-red-50 border border-red-100 dark:bg-red-900/20 dark:border-red-900/30 hidden">
        <div class="flex items-center gap-2 text-red-700 dark:text-red-400 mb-2">
            <span class="material-symbols-outlined text-[20px]">error</span>
            <span class="text-sm font-bold uppercase tracking-wider">Transaksi Dibatalkan (Void)</span>
        </div>
        <div class="space-y-1">
            <p class="text-xs text-red-600/80 dark:text-red-400/80 leading-relaxed">
                <span class="font-semibold">Alasan:</span> <span id="void-reason">-</span>
            </p>
            <p class="text-xs text-red-600/80 dark:text-red-400/80">
                <span class="font-semibold">Disetujui:</span> <span id="void-approver">-</span>
            </p>
        </div>
    </div>
    <div class="mx-4 mb-6 bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="flex justify-between items-start mb-4">
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Nomor Struk</p>
                <h2 class="text-lg font-bold" id="sale-invoice">-</h2>
            </div>
            <span id="sale-status" class="text-[10px] font-bold px-2 py-1 rounded-md bg-slate-100 text-slate-600 uppercase">-</span>
        </div>
        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-slate-100 dark:border-slate-800">
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Waktu</p>
                <p class="text-xs font-medium" id="sale-time">-</p>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Kasir</p>
                <p class="text-xs font-medium" id="sale-cashier">-</p>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Pelanggan</p>
                <p class="text-xs font-medium" id="sale-customer">-</p>
            </div>
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Metode Bayar</p>
                <p class="text-xs font-medium" id="sale-payment">-</p>
            </div>
        </div>
    </div>
    <div class="px-4 mb-6">
        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 px-1" id="item-count">Daftar Item (0)</h3>
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden" id="item-list"></div>
    </div>
    <div class="px-4 mb-8">
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 space-y-3">
            <div class="flex justify-between text-sm text-slate-500">
                <span>Subtotal</span>
                <span class="font-medium text-slate-900 dark:text-slate-100" id="subtotal">Rp 0</span>
            </div>
            <div class="flex justify-between text-sm text-slate-500">
                <span>Pajak (PPN 11%)</span>
                <span class="font-medium text-slate-900 dark:text-slate-100" id="tax">Rp 0</span>
            </div>
            <div class="flex justify-between text-sm text-slate-500">
                <span>Diskon Promo</span>
                <span class="font-medium text-emerald-600" id="discount">Rp 0</span>
            </div>
            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center">
                <span class="text-base font-bold">Total Akhir</span>
                <span class="text-xl font-black text-primary" id="total">Rp 0</span>
            </div>
        </div>
    </div>
    <div class="px-4">
        <button id="void-button" class="w-full bg-white dark:bg-slate-900 border-2 border-red-500/20 text-red-600 py-4 rounded-xl font-bold flex items-center justify-center gap-2 hover:bg-red-50 transition-colors">
            <span class="material-symbols-outlined">cancel</span>
            Void Transaksi
        </button>
        <a id="void-refund-link" class="mt-3 w-full bg-slate-100 dark:bg-slate-800 text-slate-600 py-3 rounded-xl font-semibold flex items-center justify-center gap-2" href="/sales-void-refund">
            <span class="material-symbols-outlined text-base">assignment_return</span>
            Proses Void/Refund
        </a>
        <p class="text-[10px] text-center text-slate-400 mt-3 px-8 leading-tight uppercase tracking-widest">Pembatalan transaksi memerlukan persetujuan Manager</p>
    </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    voidBanner: document.getElementById('void-banner'),
                    voidReason: document.getElementById('void-reason'),
                    voidApprover: document.getElementById('void-approver'),
                    invoice: document.getElementById('sale-invoice'),
                    status: document.getElementById('sale-status'),
                    time: document.getElementById('sale-time'),
                    cashier: document.getElementById('sale-cashier'),
                    customer: document.getElementById('sale-customer'),
                    payment: document.getElementById('sale-payment'),
                    count: document.getElementById('item-count'),
                    list: document.getElementById('item-list'),
                    subtotal: document.getElementById('subtotal'),
                    tax: document.getElementById('tax'),
                    discount: document.getElementById('discount'),
                    total: document.getElementById('total'),
                    voidButton: document.getElementById('void-button'),
                    voidRefundLink: document.getElementById('void-refund-link'),
                };

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const formatDateTime = (value) => new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                }).format(new Date(value));

                const formatInvoice = (sale) => {
                    const date = new Date(sale.created_at);
                    const y = date.getFullYear();
                    const m = String(date.getMonth() + 1).padStart(2, '0');
                    const d = String(date.getDate()).padStart(2, '0');
                    return `INV/${y}${m}${d}/${String(sale.id).slice(0, 3).toUpperCase()}`;
                };

                const paymentLabel = (payments) => {
                    if (!payments || !payments.length) {
                        return '-';
                    }
                    const methods = [...new Set(payments.map((item) => item.method))];
                    return methods.map((method) => {
                        if (method === 'qris') return 'QRIS';
                        if (method === 'cash') return 'Tunai';
                        if (method === 'bank') return 'Debit';
                        return method;
                    }).join(', ');
                };

                const renderItems = (items) => {
                    el.list.innerHTML = '';
                    el.count.textContent = `Daftar Item (${items.length})`;

                    if (!items.length) {
                        el.list.innerHTML = '<div class="p-4 text-sm text-slate-400">Tidak ada item.</div>';
                        return;
                    }

                    items.forEach((item, index) => {
                        const row = document.createElement('div');
                        row.className = `p-4 flex items-center gap-4${index < items.length - 1 ? ' border-b border-slate-100 dark:border-slate-800' : ''}`;
                        row.innerHTML = `
                            <div class="size-10 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                                <span class="material-symbols-outlined">inventory_2</span>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-sm font-semibold">${item.product_name}</h4>
                                <p class="text-xs text-slate-500">${item.qty} x ${formatCurrency(item.price)}</p>
                            </div>
                            <div class="text-right">
                                ${item.discount > 0 ? `<p class="text-sm font-bold text-red-500 line-through text-[10px]">${formatCurrency(item.price * item.qty)}</p>` : ''}
                                <p class="text-sm font-bold">${formatCurrency(item.line_total)}</p>
                            </div>
                        `;
                        el.list.appendChild(row);
                    });
                };

                const loadSale = async (saleId) => {
                    const response = await fetch(`/api/v1/sales/${saleId}`, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat detail transaksi.');
                    }
                    return response.json();
                };

                const setVoidState = (sale) => {
                    const isVoid = sale.status === 'void';
                    el.voidBanner.classList.toggle('hidden', !isVoid);
                    el.voidReason.textContent = sale.void_reason || 'Tidak ada alasan.';
                    el.voidApprover.textContent = sale.voided_by_name || '-';
                    el.voidButton.disabled = isVoid;
                    el.voidButton.classList.toggle('opacity-60', isVoid);
                    el.voidButton.classList.toggle('cursor-not-allowed', isVoid);
                    el.status.textContent = isVoid ? 'Void' : 'Selesai';
                    el.status.className = isVoid
                        ? 'text-[10px] font-bold px-2 py-1 rounded-md bg-red-100 text-red-600 uppercase'
                        : 'text-[10px] font-bold px-2 py-1 rounded-md bg-slate-100 text-slate-600 uppercase';
                };

                const getParam = (key) => new URLSearchParams(window.location.search).get(key);

                const init = async () => {
                    const saleId = getParam('sale_id');
                    if (!saleId) {
                        el.list.innerHTML = '<div class="p-4 text-sm text-slate-400">ID transaksi tidak ditemukan.</div>';
                        return;
                    }

                    try {
                        const data = await loadSale(saleId);
                        const sale = data.sale;
                        el.voidRefundLink.href = `/sales-void-refund?sale_id=${saleId}`;
                        el.invoice.textContent = formatInvoice(sale);
                        el.time.textContent = formatDateTime(sale.created_at);
                        el.cashier.textContent = sale.cashier_name || '-';
                        el.customer.textContent = sale.customer_name || 'Guest (Umum)';
                        el.payment.textContent = paymentLabel(data.payments);
                        el.subtotal.textContent = formatCurrency(sale.subtotal);
                        el.tax.textContent = formatCurrency(sale.tax);
                        el.discount.textContent = sale.discount > 0 ? `- ${formatCurrency(sale.discount)}` : formatCurrency(0);
                        el.total.textContent = formatCurrency(sale.total);
                        setVoidState(sale);
                        renderItems(data.items || []);

                        el.voidButton.addEventListener('click', async () => {
                            if (sale.status === 'void') {
                                return;
                            }
                            const reason = prompt('Alasan void transaksi?') || '';
                            const response = await fetch(`/api/v1/sales/${saleId}/void`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    ...headers,
                                },
                                body: JSON.stringify({ reason }),
                            });
                            if (!response.ok) {
                                alert('Gagal melakukan void.');
                                return;
                            }
                            const refreshed = await loadSale(saleId);
                            setVoidState(refreshed.sale);
                        });
                    } catch (error) {
                        el.list.innerHTML = `<div class="p-4 text-sm text-red-500">${error.message}</div>`;
                    }
                };

                init();
            })();
        </script>
    @endsection






