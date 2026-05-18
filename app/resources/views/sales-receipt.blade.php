@extends('layouts.app')

@section('page_title', 'Struk Penjualan')
@section('body_class', 'font-display text-slate-900')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-slate-100 shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
        @vite('resources/js/receipt-image.js')
        <style>
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
            .font-receipt { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
            .receipt-shadow {
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            }
            .receipt-tear {
                background-image: radial-gradient(circle at 10px 0, transparent 10px, white 11px);
                background-size: 20px 10px;
                background-position: bottom;
                background-repeat: repeat-x;
            }
        </style>
@endpush

@php
    $receiptSaleId = (string) ($saleId ?? request()->query('sale_id', ''));
    $receiptCurrentPath = $currentPath ?? ($receiptSaleId !== '' ? '/pos/sales/'.$receiptSaleId.'/receipt' : '/pos/receipt-preview');
    $receiptBackUrl = $backUrl ?? ($receiptSaleId !== '' ? '/pos/sales/'.$receiptSaleId : '/pos/payment-method');
@endphp

@section('header')
        <x-page-header title="Selesai" :backUrl="$receiptBackUrl" :showMenuButton="false">
            <x-slot name="actions">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Berhasil</span>
                </div>
            </x-slot>
        </x-page-header>
@endsection

@section('content')
        <div class="flex h-full flex-col">
            <div class="flex-1 overflow-y-auto px-6 py-6 no-scrollbar">
                <div class="flex flex-col items-center mb-8">
                    <div class="w-16 h-16 bg-emerald-500/10 text-emerald-600 rounded-full flex items-center justify-center mb-3">
                        <span class="material-symbols-outlined text-4xl font-bold">check_circle</span>
                    </div>
                    <h2 class="text-xl font-black text-slate-900">Pembayaran Berhasil</h2>
                    <p id="receipt-invoice" class="text-slate-500 text-sm mt-1">Struk #INV-20231024-001</p>
                </div>
                <div id="receipt-card" class="max-w-xs mx-auto bg-white p-6 receipt-shadow relative border border-slate-100 mb-8">
                    <div class="text-center mb-6">
                        <h3 class="font-receipt font-bold text-base uppercase">Toko Retail Maju</h3>
                        <p class="font-receipt text-[10px] text-slate-500">Jl. Sudirman No. 123, Jakarta</p>
                        <p class="font-receipt text-[10px] text-slate-500">Telp: 021-5550123</p>
                    </div>
                    <div class="border-t border-dashed border-slate-300 my-4"></div>
                    <div id="receipt-items" class="space-y-3 mb-6"></div>
                    <div class="border-t border-dashed border-slate-300 my-4"></div>
                    <div class="space-y-1 font-receipt text-xs">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span id="receipt-subtotal">Rp 189.000</span>
                        </div>
                        <div id="receipt-item-discount-row" class="hidden justify-between text-emerald-700">
                            <span>Diskon Item</span>
                            <span id="receipt-item-discount">- Rp 0</span>
                        </div>
                        <div id="receipt-transaction-discount-row" class="hidden justify-between text-emerald-700">
                            <span>Diskon Transaksi</span>
                            <span id="receipt-transaction-discount">- Rp 0</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Pajak (11%)</span>
                            <span id="receipt-tax">Rp 20.790</span>
                        </div>
                        <div class="flex justify-between font-bold text-sm pt-2">
                            <span>TOTAL</span>
                            <span id="receipt-total">Rp 209.790</span>
                        </div>
                    </div>
                    <div class="border-t border-dashed border-slate-300 my-4"></div>
                    <div class="space-y-1 font-receipt text-[10px] text-slate-500">
                        <div class="flex justify-between">
                            <span>Metode</span>
                            <span id="receipt-method" class="max-w-[9rem] text-right font-semibold text-slate-700">QRIS (ShopeePay)</span>
                        </div>
                        <div id="receipt-due-row" class="hidden justify-between">
                            <span>Jatuh Tempo</span>
                            <span id="receipt-due-date" class="text-right font-semibold text-slate-700">-</span>
                        </div>
                        <div id="receipt-remaining-row" class="hidden justify-between">
                            <span>Sisa Piutang</span>
                            <span id="receipt-remaining" class="text-right font-semibold text-slate-700">Rp 0</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Kasir</span>
                            <span id="receipt-cashier">Budi Santoso</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Waktu</span>
                            <span id="receipt-time">24/10/2023 14:22</span>
                        </div>
                    </div>
                    <div class="text-center mt-8 mb-2">
                        <p class="font-receipt text-[10px] italic">Terima kasih atas kunjungan Anda!</p>
                    </div>
                    <div class="absolute -bottom-2 left-0 right-0 flex overflow-hidden">
                        <div class="flex-1 h-2 receipt-tear"></div>
                    </div>
                </div>
            </div>
            <footer class="shrink-0 border-t border-slate-200 bg-white px-4 py-3 app-bottom-safe-footer shadow-[0_-10px_20px_-5px_rgba(15,23,42,0.08)]">
                <div class="grid grid-cols-2 gap-2">
                    <a class="flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 transition-colors active:bg-slate-200" href="{{ $receiptBackUrl }}">
                        <span class="material-symbols-outlined text-xl">arrow_back</span>
                        Detail
                    </a>
                    <button id="print-receipt" class="flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-3 text-sm font-bold text-slate-700 transition-colors active:bg-slate-200" type="button">
                        <span class="material-symbols-outlined text-xl">print</span>
                        Cetak Struk
                    </button>
                    <button id="whatsapp-share" class="col-span-2 flex items-center justify-center gap-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-600 transition-colors active:bg-emerald-100" type="button">
                        <span class="material-symbols-outlined text-xl">chat</span>
                        Kirim WhatsApp
                    </button>
                </div>
                <a class="mt-3 flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-6 py-4 text-base font-black text-white shadow-lg shadow-primary/20 transition-all active:scale-[0.98]" href="/pos/checkout">
                    <span class="material-symbols-outlined font-bold">add</span>
                    TRANSAKSI BARU
                </a>
            </footer>
        </div>
        <script>
            (() => {
                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const escapeHtml = (value) => String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');

                const formatDateTime = (value) => new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                }).format(new Date(value));

                const formatDate = (value) => {
                    if (!value) {
                        return '-';
                    }

                    const normalized = String(value).includes('T') ? String(value) : `${value}T00:00:00`;
                    return new Intl.DateTimeFormat('id-ID', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                    }).format(new Date(normalized));
                };

                const els = {
                    invoice: document.getElementById('receipt-invoice'),
                    items: document.getElementById('receipt-items'),
                    subtotal: document.getElementById('receipt-subtotal'),
                    itemDiscountRow: document.getElementById('receipt-item-discount-row'),
                    itemDiscount: document.getElementById('receipt-item-discount'),
                    transactionDiscountRow: document.getElementById('receipt-transaction-discount-row'),
                    transactionDiscount: document.getElementById('receipt-transaction-discount'),
                    tax: document.getElementById('receipt-tax'),
                    total: document.getElementById('receipt-total'),
                    method: document.getElementById('receipt-method'),
                    dueRow: document.getElementById('receipt-due-row'),
                    dueDate: document.getElementById('receipt-due-date'),
                    remainingRow: document.getElementById('receipt-remaining-row'),
                    remaining: document.getElementById('receipt-remaining'),
                    cashier: document.getElementById('receipt-cashier'),
                    time: document.getElementById('receipt-time'),
                    print: document.getElementById('print-receipt'),
                    whatsapp: document.getElementById('whatsapp-share'),
                    receiptCard: document.getElementById('receipt-card'),
                };

                let receiptState = null;

                const loadReceipt = () => {
                    try {
                        const raw = sessionStorage.getItem('pos_receipt');
                        return raw ? JSON.parse(raw) : null;
                    } catch (error) {
                        return null;
                    }
                };

                const normalizeMethodLabel = (method) => {
                    const raw = String(method || '').trim();
                    const normalized = raw.toLowerCase();

                    if (!raw) return '-';
                    if (normalized === 'receivable' || normalized === 'piutang' || normalized === 'piutang / bayar nanti') return 'Piutang / Bayar Nanti';
                    if (normalized === 'qris') return 'QRIS';
                    if (normalized === 'cash') return 'Tunai';
                    if (normalized === 'bank') return 'Transfer Bank';

                    return raw;
                };

                const paymentLabel = (payments, receivable = null) => {
                    const labels = [];
                    const methods = [...new Set((payments || []).map((item) => item.method).filter(Boolean))];

                    methods.forEach((method) => {
                        const label = normalizeMethodLabel(method);
                        if (label !== '-' && !labels.includes(label)) {
                            labels.push(label);
                        }
                    });

                    if (receivable && Number(receivable.remaining_amount ?? receivable.total_amount ?? 0) > 0) {
                        labels.push(labels.length ? 'Piutang' : 'Piutang / Bayar Nanti');
                    }

                    return labels.length ? labels.join(' + ') : '-';
                };

                const renderPaymentInfo = (receipt) => {
                    const receivable = receipt.receivable || null;
                    const hasReceivable = Boolean(receivable || receipt.receivable_id || String(receipt.method || '').toLowerCase() === 'receivable');
                    const dueDate = receivable?.due_date || receipt.due_date || '';
                    const remaining = Number(receivable?.remaining_amount ?? receipt.remaining_amount ?? 0);

                    els.method.textContent = normalizeMethodLabel(receipt.method);
                    els.dueRow?.classList.toggle('hidden', !dueDate);
                    els.dueRow?.classList.toggle('flex', Boolean(dueDate));
                    if (els.dueDate) {
                        els.dueDate.textContent = formatDate(dueDate);
                    }

                    els.remainingRow?.classList.toggle('hidden', !hasReceivable || remaining <= 0);
                    els.remainingRow?.classList.toggle('flex', hasReceivable && remaining > 0);
                    if (els.remaining) {
                        els.remaining.textContent = formatCurrency(remaining);
                    }
                };

                const renderItems = (items) => {
                    if (!Array.isArray(items) || !items.length) {
                        return;
                    }
                    els.items.innerHTML = '';
                    items.forEach((item) => {
                        const itemNote = String(item.note || item.notes || '').trim();
                        const row = document.createElement('div');
                        row.className = 'flex justify-between font-receipt text-xs';
                        row.innerHTML = `
                            <div class="flex-1">
                                <p>${item.name}</p>
                                <p class="text-slate-500">${item.qty} x ${formatCurrency(item.price)}</p>
                                ${itemNote ? `<p class="mt-1 text-[10px] text-amber-700">Note: ${escapeHtml(itemNote)}</p>` : ''}
                            </div>
                            <span class="font-bold">${formatCurrency(item.qty * item.price)}</span>
                        `;
                        els.items.appendChild(row);
                    });
                };

                const renderFromReceipt = (receipt) => {
                    if (!receipt) {
                        return;
                    }
                    receiptState = receipt;
                    if (receipt.invoice) {
                        els.invoice.textContent = `Struk #${receipt.invoice}`;
                    }
                    els.subtotal.textContent = formatCurrency(receipt.subtotal);
                    const itemDiscountTotal = Number(receipt.item_discount_total || 0);
                    const transactionDiscountTotal = Number(receipt.transaction_discount_total || 0);
                    els.itemDiscountRow.classList.toggle('hidden', itemDiscountTotal <= 0);
                    els.transactionDiscountRow.classList.toggle('hidden', transactionDiscountTotal <= 0);
                    els.itemDiscount.textContent = `- ${formatCurrency(itemDiscountTotal)}`;
                    els.transactionDiscount.textContent = `- ${formatCurrency(transactionDiscountTotal)}`;
                    els.tax.textContent = formatCurrency(receipt.tax);
                    els.total.textContent = formatCurrency(receipt.total);
                    renderPaymentInfo(receipt);
                    els.cashier.textContent = receipt.cashier || '-';
                    els.time.textContent = formatDateTime(receipt.createdAt || new Date());
                    renderItems(receipt.items || []);
                };

                const loadSale = async (saleId) => {
                    const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                    const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                    const response = await fetch(`/api/v1/sales/${saleId}`, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat detail transaksi.');
                    }
                    return response.json();
                };

                const init = async () => {
                    const saleId = @json($receiptSaleId) || new URLSearchParams(window.location.search).get('sale_id');
                    if (saleId) {
                        try {
                            const data = await loadSale(saleId);
                            const sale = data.sale || {};
                            receiptState = {
                                invoice: saleId,
                                subtotal: sale.subtotal,
                                item_discount_total: (data.discounts || [])
                                    .filter((discount) => String(discount.type || '').startsWith('item_'))
                                    .reduce((sum, discount) => sum + Number(discount.value || 0), 0),
                                transaction_discount_total: (data.discounts || [])
                                    .filter((discount) => String(discount.type || '').startsWith('transaction_'))
                                    .reduce((sum, discount) => sum + Number(discount.value || 0), 0),
                                tax: sale.tax,
                                total: sale.total,
                                method: paymentLabel(data.payments, data.receivable),
                                receivable: data.receivable || null,
                                cashier: sale.cashier_name || '-',
                                createdAt: sale.created_at || new Date().toISOString(),
                                items: (data.items || []).map((item) => ({
                                    name: item.product_name || item.name,
                                    qty: item.qty,
                                    price: item.price,
                                    note: item.note || item.notes || '',
                                })),
                            };
                            els.invoice.textContent = `Struk #${saleId}`;
                            renderFromReceipt(receiptState);
                            return;
                        } catch (error) {
                            // Fallback to session receipt if API fails.
                        }
                    }

                    renderFromReceipt(loadReceipt());
                };

                const buildWhatsAppMessage = () => {
                    const current = receiptState || {};
                    const currentDueDate = current.receivable?.due_date || current.due_date || '';
                    const lines = [
                        'Terima kasih sudah berbelanja 🙏',
                        current.invoice ? `No. Struk: ${current.invoice}` : null,
                        `Total: ${formatCurrency(current.total || 0)}`,
                        current.method ? `Metode: ${normalizeMethodLabel(current.method)}` : null,
                        currentDueDate ? `Jatuh tempo: ${formatDate(currentDueDate)}` : null,
                        current.createdAt ? `Waktu: ${formatDateTime(current.createdAt)}` : null,
                    ].filter(Boolean);

                    return lines.join('\n');
                };

                const buildReceiptImageBlob = async () => {
                    if (!els.receiptCard || typeof html2canvas !== 'function') {
                        return null;
                    }

                    const canvas = await html2canvas(els.receiptCard, {
                        backgroundColor: '#ffffff',
                        scale: 2,
                    });

                    return await new Promise((resolve) => {
                        canvas.toBlob((blob) => resolve(blob), 'image/png');
                    });
                };

                const downloadReceiptImage = (blob) => {
                    if (!blob) {
                        return;
                    }

                    const invoice = (receiptState?.invoice || 'receipt').replace(/[^a-zA-Z0-9_-]/g, '-');
                    const link = document.createElement('a');
                    const objectUrl = URL.createObjectURL(blob);
                    link.href = objectUrl;
                    link.download = `nota-${invoice}.png`;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(objectUrl);
                };

                els.print?.addEventListener('click', () => {
                    window.print();
                });

                els.whatsapp?.addEventListener('click', () => {
                    window.PosUi.runWithSubmitGuard(els.whatsapp, async () => {
                        const message = buildWhatsAppMessage();
                        const blob = await buildReceiptImageBlob();

                        if (blob) {
                            const file = new File([blob], 'nota-transaksi.png', { type: 'image/png' });

                            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                                await navigator.share({
                                    files: [file],
                                    title: 'Nota Transaksi',
                                    text: message,
                                });
                                return;
                            }

                            downloadReceiptImage(blob);
                        }

                        const url = `https://wa.me/?text=${encodeURIComponent(`${message}\n\nNota gambar sudah diunduh. Silakan lampirkan file gambar pada chat ini.`)}`;
                        window.open(url, '_blank', 'noopener,noreferrer');
                    });
                });

                init();
            })();
        </script>
    @endsection




