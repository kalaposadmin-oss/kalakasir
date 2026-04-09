@extends('layouts.app')

@php
    $receivingActionTenant = \App\Support\TenantContext::get();
    $receivingActionUser = auth()->user();
    $receivingActionRole = null;

    if (! $receivingActionTenant && filled($receivingActionUser?->tenant_id)) {
        $receivingActionTenant = \App\Models\Tenant::query()->find($receivingActionUser->tenant_id);
    }

    if ($receivingActionTenant && $receivingActionUser) {
        $receivingActionRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $receivingActionTenant->id, (string) $receivingActionUser->id);

        if (! $receivingActionRole && filled($receivingActionUser->role ?? null)) {
            $receivingActionRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $receivingActionUser->role);
        }
    }

    abort_unless(in_array($receivingActionRole, ['admin', 'owner', 'superadmin'], true), 403);
@endphp

@section('page_title', 'Aksi Terima Barang PO')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto pb-32')

@push('head')
        <style type="text/tailwindcss">
            .hide-scrollbar::-webkit-scrollbar {
                display: none;
            }
            input[type=number]::-webkit-inner-spin-button,
            input[type=number]::-webkit-outer-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }
        </style>
@endpush

@php
    $receivingCurrentPath = $currentPath ?? '/backoffice/receiving/'.((string) ($purchaseId ?? request()->query('purchase_id', ''))).'/action';
    $receivingBackUrl = $backUrl ?? '/backoffice/receiving';
    $resolvedPurchaseId = (string) ($purchaseId ?? request()->query('purchase_id', ''));
@endphp

@section('header')
    <x-page-header title="Terima Barang" subtitle="PO -" subtitleId="po-subtitle" :backUrl="$receivingBackUrl" :showMenuButton="true">
        <x-slot:actions>
            <button class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800">
                <span class="material-symbols-outlined text-slate-600 dark:text-slate-400 text-[20px]">qr_code_scanner</span>
            </button>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <div class="p-4">
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/50 rounded-xl p-3 flex items-start gap-3">
            <span class="material-symbols-outlined text-blue-600 mt-0.5">info</span>
            <div class="text-xs text-blue-800 dark:text-blue-300 leading-relaxed">
                Pastikan jumlah barang yang diterima sesuai dengan fisik barang. Stok gudang akan diperbarui secara otomatis setelah disimpan.
            </div>
        </div>
    </div>
    <div class="px-4 space-y-4">
        <div class="flex justify-between items-center px-1">
            <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Daftar Item PO</h3>
            <button id="accept-all" class="text-xs font-semibold text-primary" type="button">Terima Semua</button>
        </div>
        <div id="item-list" class="space-y-4"></div>
        <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm mt-4">
            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Catatan Penerimaan (Opsional)</label>
            <textarea class="w-full rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 text-sm focus:ring-primary focus:border-primary" placeholder="Contoh: Barang rusak 2 pouch, retur langsung..." rows="3"></textarea>
        </div>
    </div>
    <footer class="fixed bottom-0 w-full max-w-[430px] bg-white/95 dark:bg-background-dark/95 backdrop-blur-xl border-t border-slate-200 dark:border-slate-800 p-4 pb-8 space-y-3">
        <div class="flex items-center justify-between text-sm px-1">
            <span class="text-slate-500 font-medium">Total Item Diterima:</span>
            <span class="font-bold" id="item-total">0 / 0</span>
        </div>
        <div class="flex gap-3">
            <button class="flex-1 py-4 px-4 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl font-bold" type="button">Draft</button>
            <button id="save-receiving" class="flex-[2] py-4 px-4 bg-primary text-white rounded-xl font-bold shadow-lg shadow-primary/20 flex items-center justify-center gap-2" type="button">
                <span class="material-symbols-outlined">save</span>
                Simpan Penerimaan
            </button>
        </div>
        <div class="flex justify-center pt-2">
            <div class="w-32 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
    </footer>

        @include('partials.ui-helpers')
        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    subtitle: document.getElementById('po-subtitle'),
                    list: document.getElementById('item-list'),
                    total: document.getElementById('item-total'),
                    acceptAll: document.getElementById('accept-all'),
                    saveReceiving: document.getElementById('save-receiving'),
                };

                let items = [];

                const getParam = (key) => new URLSearchParams(window.location.search).get(key);

                const loadPurchases = async () => {
                    const response = await fetch('/api/v1/purchases', { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const data = await response.json();
                    return data.purchases || [];
                };

                const loadPurchaseDetail = async (purchaseId) => {
                    const response = await fetch(`/api/v1/purchases/${purchaseId}`, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat data PO.');
                    }
                    return response.json();
                };

                const renderStatus = (received, ordered) => {
                    if (received === 0) {
                        return '<span class="flex items-center gap-1 text-slate-400 font-bold text-xs uppercase">Belum</span>';
                    }
                    if (received >= ordered) {
                        return '<span class="flex items-center gap-1 text-emerald-600 font-bold text-xs"><span class="material-symbols-outlined text-sm">check_circle</span>Lengkap</span>';
                    }
                    return '<span class="flex items-center gap-1 text-amber-600 font-bold text-xs"><span class="material-symbols-outlined text-sm">error</span>Kurang</span>';
                };

                const updateTotals = () => {
                    const orderedTotal = items.reduce((sum, item) => sum + item.qty, 0);
                    const receivedTotal = items.reduce((sum, item) => sum + item.received, 0);
                    el.total.textContent = `${receivedTotal} / ${orderedTotal}`;
                };

                const renderItems = () => {
                    el.list.innerHTML = '';
                    if (!items.length) {
                        el.list.innerHTML = '<div class="text-sm text-slate-400">Tidak ada item pada PO ini.</div>';
                        return;
                    }

                    items.forEach((item, index) => {
                        const row = document.createElement('div');
                        row.className = 'bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm';
                        row.innerHTML = `
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1">
                                    <h4 class="font-bold text-slate-900 dark:text-slate-100">${item.product_name}</h4>
                                    <p class="text-xs text-slate-500">SKU: ${item.product_sku || '-'} • Satuan: ${item.unit || 'Pcs'}</p>
                                </div>
                                <div class="bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded text-right">
                                    <p class="text-[10px] text-slate-400 font-bold uppercase">Order</p>
                                    <p class="text-sm font-bold">${item.qty}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 bg-slate-50 dark:bg-slate-800/50 p-3 rounded-lg border border-dashed border-slate-200 dark:border-slate-700">
                                <div class="flex-1">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Jumlah Diterima</label>
                                    <div class="flex items-center gap-3">
                                        <button class="qty-minus size-8 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300" data-index="${index}" type="button">
                                            <span class="material-symbols-outlined text-sm">remove</span>
                                        </button>
                                        <input class="qty-input flex-1 bg-transparent border-0 text-center font-bold text-lg p-0 focus:ring-0" data-index="${index}" type="number" value="${item.received}" min="0" />
                                        <button class="qty-plus size-8 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300" data-index="${index}" type="button">
                                            <span class="material-symbols-outlined text-sm">add</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="w-px h-10 bg-slate-200 dark:border-slate-700"></div>
                                <div class="flex-none w-20">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Status</label>
                                    <div class="status-label" data-index="${index}">${renderStatus(item.received, item.qty)}</div>
                                </div>
                            </div>
                        `;
                        el.list.appendChild(row);
                    });
                    updateTotals();
                };

                const updateStatus = (index) => {
                    const label = el.list.querySelector(`.status-label[data-index="${index}"]`);
                    if (label) {
                        label.innerHTML = renderStatus(items[index].received, items[index].qty);
                    }
                    updateTotals();
                };

                el.list.addEventListener('click', (event) => {
                    const minus = event.target.closest('.qty-minus');
                    const plus = event.target.closest('.qty-plus');
                    if (minus) {
                        const index = Number(minus.dataset.index);
                        items[index].received = Math.max(0, items[index].received - 1);
                        renderItems();
                    }
                    if (plus) {
                        const index = Number(plus.dataset.index);
                        items[index].received = items[index].received + 1;
                        renderItems();
                    }
                });

                el.list.addEventListener('input', (event) => {
                    const input = event.target.closest('.qty-input');
                    if (!input) {
                        return;
                    }
                    const index = Number(input.dataset.index);
                    items[index].received = Math.max(0, Number(input.value || 0));
                    updateStatus(index);
                });

                el.acceptAll.addEventListener('click', () => {
                    items = items.map((item) => ({
                        ...item,
                        received: item.qty,
                    }));
                    renderItems();
                });

                el.saveReceiving.addEventListener('click', async () => {
                    const totalItems = items.length;
                    const orderedTotal = items.reduce((sum, item) => sum + Number(item.qty || 0), 0);
                    const receivedTotal = items.reduce((sum, item) => sum + Number(item.received || 0), 0);

                    const allowed = window.PosUi.confirmRiskAction({
                        title: 'Konfirmasi Penerimaan Barang',
                        message: `PO: ${el.subtitle.textContent}\nItem: ${totalItems}\nQty Order: ${orderedTotal}\nQty Diterima: ${receivedTotal}`,
                    });
                    if (!allowed) {
                        return;
                    }

                    await window.PosUi.runWithSubmitGuard(el.saveReceiving, async () => {
                        await new Promise((resolve) => setTimeout(resolve, 300));
                    }, {
                        loadingHtml: '<span class="material-symbols-outlined animate-spin">progress_activity</span> MENYIMPAN',
                    });
                });

                (async () => {
                    const purchases = await loadPurchases();
                    const selectedId = @json($resolvedPurchaseId) || getParam('purchase_id') || (purchases[0]?.id || null);
                    if (!selectedId) {
                        el.list.innerHTML = '<div class="text-sm text-slate-400">Belum ada PO.</div>';
                        return;
                    }
                    try {
                        const data = await loadPurchaseDetail(selectedId);
                        el.subtitle.textContent = `PO #${String(data.purchase.id).slice(0, 6).toUpperCase()} - ${data.supplier?.name || 'Pemasok'}`;
                        items = (data.items || []).map((item) => ({
                            ...item,
                            received: item.qty,
                        }));
                        renderItems();
                    } catch (error) {
                        el.list.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                    }
                })();
            })();
        </script>
    @endsection


