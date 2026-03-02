@extends('layouts.app')

@section('page_title', 'Void & Refund Transaksi')
@section('body_class', 'font-display text-slate-900')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-slate-50 shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
        <style>
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
            .ios-input-shadow {
                box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
            }
        </style>
@endpush

@section('header')
        <x-page-header title="Void &amp; Refund" subtitle="INV/20231024/0042" backUrl="/menu/sales" :showMenuButton="false">
            <x-slot name="actions">
                <span class="px-2 py-1 bg-slate-100 text-slate-600 rounded text-[10px] font-bold">MEJA 08</span>
            </x-slot>
        </x-page-header>
@endsection

@section('content')
        <div class="relative flex h-full flex-col">
            <div class="flex-1 overflow-y-auto bg-slate-50 pb-32">
                <div class="px-4 py-4 space-y-4">
                    <div class="bg-blue-50 border border-blue-100 p-3 rounded-xl flex gap-3">
                        <span class="material-symbols-outlined text-primary text-xl">info</span>
                        <p class="text-xs text-blue-800 leading-relaxed">
                            Pilih item yang ingin dibatalkan atau dikembalikan. Masukkan alasan dan otorisasi manajer untuk melanjutkan.
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
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-widest px-1">Manager Approval PIN</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">lock</span>
                                <input id="refund-pin" class="w-full pl-12 pr-4 py-3 rounded-xl border-slate-200 bg-white text-sm focus:ring-primary focus:border-primary ios-input-shadow" maxlength="6" placeholder="Masukkan 6 digit PIN Manajer" type="password" />
                            </div>
                            <p class="text-[10px] text-slate-400 px-1 italic">Void memerlukan otorisasi level supervisor/manajer.</p>
                        </div>
                    </div>
                </div>
            </div>
            <section class="absolute bottom-0 left-0 right-0 bg-white border-t border-slate-200 shadow-[0_-10px_25px_-5px_rgba(0,0,0,0.05)] rounded-t-[2rem] overflow-hidden z-50">
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
                        <button class="flex-1 h-14 bg-white border border-slate-200 text-slate-600 font-bold rounded-2xl flex items-center justify-center active:bg-slate-50 transition-colors" onclick="location.href='/menu/sales'">
                            BATAL
                        </button>
                        <button id="process-refund" class="flex-[2] h-14 bg-red-500 text-white font-black px-6 rounded-2xl flex items-center justify-center gap-2 shadow-lg shadow-red-500/20 active:scale-95 transition-transform">
                            <span class="material-symbols-outlined font-bold">assignment_return</span>
                            PROSES REFUND
                        </button>
                    </div>
                    <nav class="border-t border-slate-200 pt-3">
                        <div class="flex justify-around items-center">
                            <a class="flex flex-col items-center gap-1 text-slate-400" href="/">
                                <span class="material-symbols-outlined">home</span>
                                <span class="text-[10px] font-medium">Beranda</span>
                            </a>
                            <a class="flex flex-col items-center gap-1 text-slate-400" href="/inventory">
                                <span class="material-symbols-outlined">inventory</span>
                                <span class="text-[10px] font-medium">Stok</span>
                            </a>
                            <a class="flex flex-col items-center gap-1 text-primary" href="/sales-history">
                                <span class="material-symbols-outlined">receipt</span>
                                <span class="text-[10px] font-bold">Transaksi</span>
                            </a>
                            <a class="flex flex-col items-center gap-1 text-slate-400" href="/finance-summary">
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
                    pin: document.getElementById('refund-pin'),
                    process: document.getElementById('process-refund'),
                };

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const state = {
                    saleId: new URLSearchParams(window.location.search).get('sale_id'),
                    items: [],
                };

                const loadSale = async () => {
                    const response = await fetch(`/api/v1/sales/${state.saleId}`, { headers });
                    if (!response.ok) {
                        const mapped = await window.PosUi.mapApiError(response, 'Gagal memuat detail transaksi.');
                        throw new Error(mapped.message);
                    }
                    return response.json();
                };

                const setTotals = () => {
                    const selectedItems = state.items.filter((item) => item.selected);
                    const total = selectedItems.reduce((sum, item) => sum + item.refundQty * item.price, 0);
                    const count = selectedItems.reduce((sum, item) => sum + item.refundQty, 0);
                    els.total.textContent = new Intl.NumberFormat('id-ID').format(total);
                    els.count.textContent = count;
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
                    const totalRefund = selectedItems.reduce((sum, item) => sum + (item.refundQty * item.price), 0);
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
                    if (!els.pin.value || els.pin.value.trim().length < 6) {
                        alert('PIN manajer wajib diisi 6 digit.');
                        return;
                    }

                    const allowed = window.PosUi.confirmRiskAction({
                        title: 'Konfirmasi Void/Refund',
                        message: `Item: ${totalItems}\nTotal: ${formatCurrency(totalRefund)}\nAksi ini akan tercatat di audit log. Lanjutkan?`,
                    });
                    if (!allowed) {
                        return;
                    }

                    await window.PosUi.runWithSubmitGuard(els.process, async () => {
                        try {
                            const response = await window.PosUi.fetchWithTimeout(`/api/v1/sales/${state.saleId}/void`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    ...headers,
                                },
                                body: JSON.stringify({
                                    reason_code: reasonCode,
                                    reason_note: reasonNote || null,
                                    reason: reasonCode + (reasonNote ? ` - ${reasonNote}` : ''),
                                    manager_pin: els.pin.value.trim(),
                                }),
                            }, 15000);
                            if (!response.ok) {
                                const mapped = await window.PosUi.mapApiError(response, 'Gagal memproses void/refund.');
                                alert(mapped.message);
                                return;
                            }
                            window.location.href = `/sales-detail?sale_id=${state.saleId}`;
                        } catch (error) {
                            if (error?.name === 'AbortError') {
                                alert('Permintaan timeout. Cek riwayat penjualan sebelum mengulang void.');
                                return;
                            }
                            alert(error?.message || 'Gagal memproses void/refund.');
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
                        state.items = (data.items || []).map((item) => ({
                            id: item.id,
                            name: item.product_name,
                            price: item.price,
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



