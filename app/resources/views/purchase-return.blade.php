@extends('layouts.app')

@php
    $purchaseReturnTenant = \App\Support\TenantContext::get();
    $purchaseReturnUser = auth()->user();
    $purchaseReturnRole = null;

    if (! $purchaseReturnTenant && filled($purchaseReturnUser?->tenant_id)) {
        $purchaseReturnTenant = \App\Models\Tenant::query()->find($purchaseReturnUser->tenant_id);
    }

    if ($purchaseReturnTenant && $purchaseReturnUser) {
        $purchaseReturnRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $purchaseReturnTenant->id, (string) $purchaseReturnUser->id);

        if (! $purchaseReturnRole && filled($purchaseReturnUser->role ?? null)) {
            $purchaseReturnRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $purchaseReturnUser->role);
        }
    }

    abort_unless(in_array($purchaseReturnRole, ['admin', 'owner', 'superadmin'], true), 403);
    $purchaseReturnPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $purchaseReturnBackUrl = $purchaseReturnPortalPrefix.'/purchases';
@endphp

@section('page_title', 'Pencatatan Retur Pembelian')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto pb-32 app-bottom-safe-space')

@push('head')
        <style type="text/tailwindcss">
            .ios-blur {
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
            }
        </style>
@endpush

@section('header')
    <x-page-header title="Retur Pembelian" :backUrl="$purchaseReturnBackUrl" :showMenuButton="true">
        <x-slot:actions>
            <button class="text-primary font-semibold text-sm" onclick="location.href=@js($purchaseReturnBackUrl)">Batal</button>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <div class="p-4 space-y-6 app-bottom-safe-space">
        <section class="space-y-4">
            <div class="space-y-2">
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider ml-1">Pilih Pemasok / No. PO</label>
                <div class="relative group">
                    <select id="purchase-select" class="w-full h-14 rounded-2xl border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 py-3 pl-4 pr-10 text-sm appearance-none shadow-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                        <option value="">Cari PO atau Pemasok...</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-4 top-4 text-slate-400 pointer-events-none">unfold_more</span>
                </div>
            </div>
            <div id="info-card" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/50 rounded-2xl p-4 flex gap-3">
                <span class="material-symbols-outlined text-primary">info</span>
                <div class="text-xs text-blue-800 dark:text-blue-300 leading-relaxed">
                    Retur ini akan mengurangi stok tersedia dan menciptakan saldo kredit pada pemasok <span class="font-bold" id="supplier-name">-</span>.
                </div>
            </div>
        </section>
        <section class="space-y-3">
            <div class="flex justify-between items-end px-1">
                <h3 class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Daftar Produk PO</h3>
                <span class="text-[10px] font-medium text-slate-400 italic" id="product-count">0 Produk ditemukan</span>
            </div>
            <div id="product-list" class="space-y-3"></div>
        </section>
        <section class="space-y-4">
            <div class="space-y-2">
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider ml-1">Catatan Tambahan</label>
                <textarea id="return-notes" class="w-full rounded-2xl border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary min-h-[100px]" placeholder="Berikan keterangan detail mengenai kondisi barang..."></textarea>
            </div>
            <div class="bg-slate-900 dark:bg-slate-800 rounded-2xl p-5 text-white shadow-xl">
                <div class="flex justify-between items-center mb-3">
                    <span class="text-xs text-slate-400">Total Nilai Retur</span>
                    <span class="text-lg font-bold" id="return-total">Rp 0</span>
                </div>
                <div class="h-px bg-slate-700 w-full mb-3"></div>
                <div class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-emerald-400 text-sm">verified_user</span>
                    <p class="text-[10px] text-slate-400 leading-tight">Saldo hutang ke pemasok akan otomatis disesuaikan setelah retur disimpan.</p>
                </div>
            </div>
        </section>
    </div>
    <div class="fixed bottom-0 left-1/2 z-30 w-full max-w-[430px] -translate-x-1/2 border-t border-slate-200 bg-white/90 p-4 shadow-[0_-12px_28px_rgba(15,23,42,0.08)] ios-blur dark:border-slate-800 dark:bg-background-dark/90 app-bottom-safe-footer">
        <div class="mb-3 flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3 text-sm dark:bg-slate-900">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Siap disimpan</p>
                <p class="text-xs font-semibold text-slate-600 dark:text-slate-300" id="return-submit-summary">Pilih item retur dulu</p>
            </div>
            <p class="text-base font-black text-primary" id="return-submit-total">Rp 0</p>
        </div>
        <button id="submit-return" class="flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-primary text-white font-black shadow-lg shadow-primary/30 transition-transform active:scale-[0.98] disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none dark:disabled:bg-slate-700" type="button" disabled>
            <span class="material-symbols-outlined">assignment_return</span>
            Simpan Retur
        </button>
    </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    purchase: document.getElementById('purchase-select'),
                    supplier: document.getElementById('supplier-name'),
                    list: document.getElementById('product-list'),
                    count: document.getElementById('product-count'),
                    total: document.getElementById('return-total'),
                    submitTotal: document.getElementById('return-submit-total'),
                    submitSummary: document.getElementById('return-submit-summary'),
                    notes: document.getElementById('return-notes'),
                    submit: document.getElementById('submit-return'),
                };

                let purchaseData = null;
                let items = [];

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

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

                const updateTotal = () => {
                    const total = items.reduce((sum, item) => sum + (item.returnQty * item.price), 0);
                    const selectedCount = items.filter((item) => item.returnQty > 0).length;
                    el.total.textContent = formatCurrency(total);
                    el.submitTotal.textContent = formatCurrency(total);
                    el.submitSummary.textContent = selectedCount
                        ? `${selectedCount} produk akan mengurangi stok`
                        : 'Pilih item retur dulu';
                    el.submit.disabled = selectedCount === 0 || !purchaseData;
                };

                const renderItems = () => {
                    el.list.innerHTML = '';
                    el.count.textContent = `${items.length} Produk ditemukan`;

                    if (!items.length) {
                        el.list.innerHTML = '<div class="text-sm text-slate-400">Tidak ada produk pada PO ini.</div>';
                        return;
                    }

                    items.forEach((item, index) => {
                        const row = document.createElement('div');
                        row.className = 'bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm';
                        row.innerHTML = `
                            <div class="p-4 flex gap-4">
                                <div class="size-16 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-slate-400">inventory_2</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-bold text-sm text-slate-900 dark:text-slate-100 truncate">${item.product_name}</h4>
                                    <p class="text-xs text-slate-500">Diterima: ${item.receivedQtyLabel} - ${formatCurrency(item.price)}</p>
                                    <div class="mt-4 grid grid-cols-2 gap-3">
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold text-slate-400 uppercase">Jml. Retur</label>
                                            <div class="flex items-center bg-slate-50 dark:bg-slate-800 rounded-lg px-2 border border-slate-100 dark:border-slate-700">
                                                <input class="return-qty w-full bg-transparent border-none text-sm font-bold p-1 focus:ring-0" type="number" min="0" max="${item.maxReturnQty}" step="0.0001" value="${item.returnQty}" data-index="${index}" />
                                                <span class="text-[10px] font-bold text-slate-400">${item.unitLabel}</span>
                                            </div>
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold text-slate-400 uppercase">Alasan</label>
                                            <select class="return-reason w-full bg-slate-50 dark:bg-slate-800 rounded-lg border-slate-100 dark:border-slate-700 text-[11px] font-medium p-1.5 focus:ring-0" data-index="${index}">
                                                <option value="Rusak">Rusak</option>
                                                <option value="Kadaluarsa">Kadaluarsa</option>
                                                <option value="Tidak Sesuai">Tidak Sesuai</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-amber-50/50 dark:bg-amber-900/10 px-4 py-2 border-t border-amber-100/50 dark:border-amber-900/30 flex justify-between items-center">
                                <span class="text-[10px] text-amber-700 dark:text-amber-500 font-medium">Adjustment Stok:</span>
                                <span class="text-[11px] font-bold text-red-500">-${item.returnQty} ${item.unitLabel} (${purchaseData.purchase?.location_name || 'Lokasi PO'})</span>
                            </div>
                        `;
                        el.list.appendChild(row);
                    });
                    updateTotal();
                };

                const handlePurchaseChange = async () => {
                    const id = el.purchase.value;
                    if (!id) {
                        return;
                    }
                    try {
                        const data = await loadPurchaseDetail(id);
                        purchaseData = data;
                        el.supplier.textContent = data.supplier?.name || '-';
                        items = (data.items || []).map((item) => ({
                            ...item,
                            purchaseItemId: item.id,
                            maxReturnQty: Number(item.received_qty ?? item.qty ?? 0),
                            unitLabel: item.unit || 'unit',
                            receivedQtyLabel: `${Number(item.received_qty ?? item.qty ?? 0).toLocaleString('id-ID')} ${item.unit || 'unit'}`,
                            returnQty: 0,
                            reason: 'Rusak',
                        }));
                        renderItems();
                    } catch (error) {
                        el.list.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                    }
                };

                el.purchase.addEventListener('change', handlePurchaseChange);

                el.list.addEventListener('input', (event) => {
                    const qtyInput = event.target.closest('.return-qty');
                    const reasonSelect = event.target.closest('.return-reason');
                    if (qtyInput) {
                        const index = Number(qtyInput.dataset.index);
                        const maxReturnQty = Number(items[index].maxReturnQty || 0);
                        items[index].returnQty = Math.min(maxReturnQty, Math.max(0, Number(qtyInput.value || 0)));
                        renderItems();
                    }
                    if (reasonSelect) {
                        const index = Number(reasonSelect.dataset.index);
                        items[index].reason = reasonSelect.value;
                    }
                });

                el.submit.addEventListener('click', async () => {
                    if (!purchaseData) {
                        alert('Pilih PO terlebih dahulu.');
                        return;
                    }
                    const payloadItems = items
                        .filter((item) => item.returnQty > 0)
                        .map((item) => ({
                            purchase_item_id: item.purchaseItemId,
                            qty: item.returnQty,
                            price: item.price,
                            reason: item.reason,
                        }));

                    if (!payloadItems.length) {
                        if (window.PosUi?.confirmRiskActionAsync) {
                            await window.PosUi.confirmRiskActionAsync({
                                title: 'Belum ada item retur',
                                message: 'Isi jumlah retur minimal satu produk.',
                                confirmText: 'Mengerti',
                                cancelText: 'Tutup',
                                icon: 'info',
                            });
                        } else {
                            alert('Isi jumlah retur minimal satu produk.');
                        }
                        return;
                    }

                    const confirmMessage = `PO: PO-${String(purchaseData.purchase.id).slice(0, 6).toUpperCase()}\nSupplier: ${purchaseData.supplier?.name || '-'}\nNilai Retur: ${el.total.textContent}\n\nStok barang akan berkurang dan jurnal retur pembelian akan diposting ke laporan.`;
                    const allowed = window.PosUi?.confirmRiskActionAsync
                        ? await window.PosUi.confirmRiskActionAsync({
                            title: 'Simpan Retur Pembelian',
                            message: confirmMessage,
                            confirmText: 'Simpan Retur',
                            cancelText: 'Batal',
                            icon: 'assignment_return',
                        })
                        : window.confirm(confirmMessage);
                    if (!allowed) {
                        return;
                    }

                    el.submit.disabled = true;
                    el.submit.innerHTML = '<span class="material-symbols-outlined animate-spin">progress_activity</span> Menyimpan...';

                    const response = await fetch('/api/v1/purchase-returns', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            ...headers,
                        },
                        body: JSON.stringify({
                            purchase_id: purchaseData.purchase.id,
                            supplier_id: purchaseData.supplier?.id,
                            notes: el.notes.value.trim() || null,
                            items: payloadItems,
                        }),
                    });

                    if (!response.ok) {
                        const payload = await response.json().catch(() => ({}));
                        alert(payload.message || 'Gagal menyimpan retur.');
                        el.submit.disabled = false;
                        el.submit.innerHTML = '<span class="material-symbols-outlined">assignment_return</span> Simpan Retur';
                        return;
                    }

                    sessionStorage.setItem('purchase-return-flash', 'Retur berhasil disimpan dan stok sudah dikurangi.');
                    window.location.href = @json($purchaseReturnBackUrl);
                });

                (async () => {
                    const purchases = await loadPurchases();
                    el.purchase.innerHTML = '<option value="">Cari PO atau Pemasok...</option>';
                    purchases.forEach((purchase) => {
                        const option = document.createElement('option');
                        option.value = purchase.id;
                        option.textContent = `PO-${String(purchase.id).slice(0, 6).toUpperCase()} - ${purchase.supplier_name || 'Pemasok'}`;
                        el.purchase.appendChild(option);
                    });
                })();
            })();
        </script>
    @endsection
