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
@section('main_class', 'flex-1 min-h-0 overflow-hidden p-0')

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
    $receivingPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $receivingCurrentPath = $currentPath ?? $receivingPortalPrefix.'/receiving/'.((string) ($purchaseId ?? request()->query('purchase_id', ''))).'/action';
    $receivingBackUrl = $backUrl ?? $receivingPortalPrefix.'/receiving';
    $resolvedPurchaseId = (string) ($purchaseId ?? request()->query('purchase_id', ''));
    $receivingFlowDefaultMode = app(\App\Support\PurchaseFlowResolver::class)->resolveMode(null, $receivingActionTenant?->purchase_mode ?? null);
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
    <div class="flex h-full min-h-0 flex-col">
        <div class="flex-1 min-h-0 overflow-y-auto pb-6">
            <div class="p-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/50 rounded-xl p-3 flex items-start gap-3">
                    <span class="material-symbols-outlined text-blue-600 mt-0.5">info</span>
                    <div class="text-xs text-blue-800 dark:text-blue-300 leading-relaxed">
                        Cocokkan jumlah barang fisik dengan data PO. Stok gudang akan diperbarui otomatis setelah disimpan.
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
        </div>

        <div class="shrink-0 bg-white/95 dark:bg-background-dark/95 backdrop-blur-xl border-t border-slate-200 dark:border-slate-800 px-4 pt-3 z-30 app-bottom-safe-footer">
            <footer class="space-y-3">
                <div class="flex items-center justify-between text-sm px-1">
                    <span class="text-slate-500 font-medium">Total Item Diterima:</span>
                    <span class="font-bold" id="item-total">0 / 0</span>
                </div>
                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 px-3 py-2.5 space-y-2">
                    <button id="breakdown-toggle" class="flex w-full items-center justify-between text-[11px] uppercase tracking-wider text-slate-400 font-bold" type="button">
                        <span>Rincian penerimaan</span>
                        <span class="flex items-center gap-2">
                            <span id="item-breakdown-count">0 item</span>
                            <span id="breakdown-toggle-icon" class="material-symbols-outlined text-sm">expand_more</span>
                        </span>
                    </button>
                    <div id="breakdown-panel" class="hidden space-y-1.5">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 break-words" id="item-breakdown-formula">0 = 0</p>
                        <div class="text-xs text-slate-500 dark:text-slate-400 space-y-0.5 max-h-20 overflow-y-auto pr-1" id="item-breakdown-list"></div>
                    </div>
                </div>
                <div class="flex gap-3">
                    <a class="flex-1 py-4 px-4 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl font-bold text-center" href="{{ $receivingBackUrl }}">Kembali</a>
                    <button id="save-receiving" class="flex-[2] py-4 px-4 bg-primary text-white rounded-xl font-bold shadow-lg shadow-primary/20 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" type="button">
                        <span class="material-symbols-outlined">save</span>
                        Simpan Penerimaan
                    </button>
                </div>
                <div class="flex justify-center pt-2">
                    <div class="w-32 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
                </div>
            </footer>
        </div>
    </div>

        <div id="price-modal" class="fixed inset-0 z-40 hidden items-end justify-center bg-slate-950/40 px-4 py-6 sm:items-center">
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

    @include('partials.ui-helpers')
    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]').content;
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
            const purchaseStatusLabels = @json(\App\Support\PurchaseStatus::labels());
            const purchaseStatusAliases = @json(\App\Support\PurchaseStatus::aliases());
            const purchaseModeControlled = @json(\App\Support\PurchaseMode::CONTROLLED);
            const purchaseModePoShort = @json(\App\Support\PurchaseMode::PO_SHORT);
            const purchaseFlowDefaultMode = @json($receivingFlowDefaultMode);

            const normalizePurchaseStatus = (status) => {
                const value = String(status || '').toLowerCase();
                return purchaseStatusAliases[value] || value;
            };

            const normalizePurchaseMode = (mode) => String(mode || '').toLowerCase() || purchaseFlowDefaultMode;

            const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Number(value || 0));

            const el = {
                subtitle: document.getElementById('po-subtitle'),
                list: document.getElementById('item-list'),
                total: document.getElementById('item-total'),
                breakdownCount: document.getElementById('item-breakdown-count'),
                breakdownFormula: document.getElementById('item-breakdown-formula'),
                breakdownList: document.getElementById('item-breakdown-list'),
                breakdownToggle: document.getElementById('breakdown-toggle'),
                breakdownPanel: document.getElementById('breakdown-panel'),
                breakdownToggleIcon: document.getElementById('breakdown-toggle-icon'),
                priceModal: document.getElementById('price-modal'),
                priceModalTitle: document.getElementById('price-modal-title'),
                priceModalSubtitle: document.getElementById('price-modal-subtitle'),
                priceForm: document.getElementById('price-form'),
                priceInput: document.getElementById('price-input'),
                priceFeedback: document.getElementById('price-feedback'),
                closePriceModal: document.getElementById('close-price-modal'),
                cancelPriceEdit: document.getElementById('cancel-price-edit'),
                savePrice: document.getElementById('save-price'),
                acceptAll: document.getElementById('accept-all'),
                saveReceiving: document.getElementById('save-receiving'),
            };

            let items = [];
            let currentPurchaseId = null;
            let currentPurchaseStatus = null;
            let currentPurchaseMode = purchaseFlowDefaultMode;
            let currentPriceIndex = null;

            const canReceivePurchase = () => {
                const status = normalizePurchaseStatus(currentPurchaseStatus);
                const mode = normalizePurchaseMode(currentPurchaseMode);

                if (mode === purchaseModePoShort) {
                    return ['submitted', 'partially_received'].includes(status);
                }

                if (mode === purchaseModeControlled) {
                    return ['approved', 'partially_received'].includes(status);
                }

                return false;
            };

            const getReceiveBlockMessage = () => currentPurchaseStatus
                ? `${purchaseStatusLabels[normalizePurchaseStatus(currentPurchaseStatus)] || currentPurchaseStatus} belum bisa diproses. ${normalizePurchaseMode(currentPurchaseMode) === purchaseModeControlled ? 'PO masih menunggu persetujuan sebelum barang bisa diterima.' : 'Status PO belum siap untuk penerimaan barang.'}`
                : 'Status PO belum dimuat.';

            const canEditItemPrice = () => {
                const status = normalizePurchaseStatus(currentPurchaseStatus);
                return ['submitted', 'approved'].includes(status);
            };

            const getPriceEditMessage = () => canEditItemPrice()
                ? 'Ubah harga sebelum penerimaan dimulai.'
                : 'Harga terkunci setelah barang mulai diterima.';

            const getParam = (key) => new URLSearchParams(window.location.search).get(key);

            const formatQuantity = (value) => {
                const quantity = Number(value || 0);
                return new Intl.NumberFormat('id-ID', {
                    maximumFractionDigits: 4,
                }).format(Number.isFinite(quantity) ? quantity : 0);
            };

            const buildBreakdown = () => {
                const orderedItems = items.map((item) => Number(item.qty || 0));
                const receivedItems = items.map((item) => Number(item.received || 0));
                const orderedTotal = orderedItems.reduce((sum, value) => sum + value, 0);
                const receivedTotal = receivedItems.reduce((sum, value) => sum + value, 0);

                const orderedFormula = orderedItems.length
                    ? orderedItems.map((value) => formatQuantity(value)).join(' + ')
                    : '0';
                const receivedFormula = receivedItems.length
                    ? receivedItems.map((value) => formatQuantity(value)).join(' + ')
                    : '0';

                el.breakdownCount.textContent = `${items.length} item`;
                el.breakdownFormula.textContent = `${receivedFormula} = ${formatQuantity(receivedTotal)} | Order: ${orderedFormula} = ${formatQuantity(orderedTotal)}`;
                el.breakdownList.innerHTML = items.length
                    ? items.map((item) => `
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate">${item.product_name}</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">${formatQuantity(item.received)}</span>
                        </div>
                    `).join('')
                    : '<div>Belum ada item.</div>';
            };

            el.breakdownToggle.addEventListener('click', () => {
                const isHidden = el.breakdownPanel.classList.contains('hidden');
                el.breakdownPanel.classList.toggle('hidden', !isHidden ? true : false);
                el.breakdownToggleIcon.textContent = isHidden ? 'expand_less' : 'expand_more';
            });

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
                const receivedQty = Number(received || 0);
                const orderedQty = Number(ordered || 0);

                if (receivedQty === 0) {
                    return '<span class="flex items-center gap-1 text-slate-400 font-bold text-xs uppercase">Belum</span>';
                }
                if (receivedQty >= orderedQty) {
                    return '<span class="flex items-center gap-1 text-emerald-600 font-bold text-xs"><span class="material-symbols-outlined text-sm">check_circle</span>Lengkap</span>';
                }
                return '<span class="flex items-center gap-1 text-amber-600 font-bold text-xs"><span class="material-symbols-outlined text-sm">error</span>Kurang</span>';
            };

            const updateTotals = () => {
                const orderedTotal = items.reduce((sum, item) => sum + Number(item.qty || 0), 0);
                const receivedTotal = items.reduce((sum, item) => sum + Number(item.received || 0), 0);
                el.total.textContent = `${formatQuantity(receivedTotal)} / ${formatQuantity(orderedTotal)}`;
                buildBreakdown();
            };

            const renderItems = () => {
                el.list.innerHTML = '';
                if (!items.length) {
                    el.list.innerHTML = `
                        ${!canReceivePurchase() ? `
                            <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800/60 dark:bg-amber-900/20 dark:text-amber-300">
                                ${getReceiveBlockMessage()}
                            </div>
                        ` : ''}
                        <div class="text-sm text-slate-400">Tidak ada item pada PO ini.</div>
                    `;
                    return;
                }

                const blockedNotice = !canReceivePurchase()
                    ? `
                        <div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800/60 dark:bg-amber-900/20 dark:text-amber-300">
                            ${getReceiveBlockMessage()}
                        </div>
                    `
                    : '';

                el.list.innerHTML = blockedNotice;

                items.forEach((item, index) => {
                    const row = document.createElement('div');
                    row.className = 'bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm';
                    row.innerHTML = `
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex-1">
                                <h4 class="font-bold text-slate-900 dark:text-slate-100">${item.product_name}</h4>
                                <p class="text-xs text-slate-500">SKU: ${item.product_sku || '-'} • Satuan beli: ${item.purchase_unit || item.unit || 'Pcs'} • Stok: ${item.unit || 'Pcs'}</p>
                                ${item.product_variant_name ? `<p class="text-xs text-slate-500">Varian: ${item.product_variant_name}</p>` : ''}
                            </div>
                            <div class="bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded text-right">
                                <p class="text-[10px] text-slate-400 font-bold uppercase">Order</p>
                                <p class="text-sm font-bold">${formatQuantity(item.qty)}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-3 py-2">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Harga Satuan</p>
                                <p class="text-sm font-bold text-slate-900 dark:text-slate-100">${formatCurrency(item.purchase_price || item.price)}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-3 py-2 text-right">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Subtotal Item</p>
                                <p class="text-sm font-bold text-slate-900 dark:text-slate-100">${formatCurrency(Number(item.qty || 0) * Number(item.purchase_price || item.price || 0))}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 bg-slate-50 dark:bg-slate-800/50 p-3 rounded-lg border border-dashed border-slate-200 dark:border-slate-700">
                            <div class="flex-1">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Jumlah Diterima</label>
                                <div class="flex items-center gap-3">
                                    <button class="qty-minus size-8 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300" data-index="${index}" type="button">
                                        <span class="material-symbols-outlined text-sm">remove</span>
                                    </button>
                                    <input class="qty-input flex-1 bg-transparent border-0 text-center font-bold text-lg p-0 focus:ring-0" data-index="${index}" type="number" value="${Number(item.received || 0)}" min="0" step="0.0001" />
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
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <button class="edit-price rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-xs font-bold text-primary ${canEditItemPrice() ? '' : 'cursor-not-allowed opacity-50'}" data-index="${index}" type="button" ${canEditItemPrice() ? '' : 'disabled'}>
                                Ubah Harga
                            </button>
                            <span class="text-[11px] font-medium ${canEditItemPrice() ? 'text-emerald-600' : 'text-slate-400'}">${getPriceEditMessage()}</span>
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
                const editPrice = event.target.closest('.edit-price');
                if (editPrice) {
                    const index = Number(editPrice.dataset.index);
                    if (canEditItemPrice()) {
                        currentPriceIndex = index;
                        const item = items[index];
                        el.priceModalTitle.textContent = item.product_name;
                        el.priceModalSubtitle.textContent = `${formatQuantity(item.qty)} ${item.purchase_unit || item.unit || 'item'} • ${item.product_variant_name || 'Varian belum dipilih'} • harga saat ini ${formatCurrency(item.purchase_price || item.price)}`;
                        el.priceInput.value = Number(item.purchase_price || item.price || 0);
                        el.priceFeedback.classList.add('hidden');
                        el.priceFeedback.textContent = '';
                        el.priceModal.classList.remove('hidden');
                        el.priceModal.classList.add('flex');
                        el.priceInput.focus();
                    }
                    return;
                }
                if (minus) {
                    const index = Number(minus.dataset.index);
                    items[index].received = Math.max(0, Number(items[index].received || 0) - 1);
                    renderItems();
                }
                if (plus) {
                    const index = Number(plus.dataset.index);
                    items[index].received = Number(items[index].received || 0) + 1;
                    renderItems();
                }
            });


            const hidePriceModal = () => {
                currentPriceIndex = null;
                el.priceModal.classList.add('hidden');
                el.priceModal.classList.remove('flex');
                el.priceFeedback.classList.add('hidden');
                el.priceFeedback.textContent = '';
            };

            el.closePriceModal.addEventListener('click', hidePriceModal);
            el.cancelPriceEdit.addEventListener('click', hidePriceModal);
            el.priceModal.addEventListener('click', (event) => {
                if (event.target === el.priceModal) {
                    hidePriceModal();
                }
            });

            el.priceForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (currentPriceIndex === null || !currentPurchaseId) {
                    return;
                }

                if (!canEditItemPrice()) {
                    return;
                }

                const item = items[currentPriceIndex];

                await window.PosUi.runWithSubmitGuard(el.savePrice, async () => {
                    const response = await fetch(`/api/v1/purchases/${currentPurchaseId}/items/${item.id}/price`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': csrfToken,
                            ...headers,
                        },
                        body: JSON.stringify({
                            price: Number(el.priceInput.value || 0),
                        }),
                    });

                    const responseData = await response.json();
                    if (!response.ok) {
                        throw new Error(responseData.message || 'Gagal menyimpan harga item');
                    }

                    const refreshed = await loadPurchaseDetail(currentPurchaseId);
                    currentPurchaseStatus = refreshed.purchase.status;
                    currentPurchaseMode = normalizePurchaseMode(refreshed.purchase.purchase_mode);
                    items = (refreshed.items || []).map((refreshedItem) => ({
                        ...refreshedItem,
                        qty: Number(refreshedItem.qty || 0),
                        received: Number(refreshedItem.qty || 0),
                        price: Number(refreshedItem.price || 0),
                    }));

                    renderItems();
                    hidePriceModal();
                }, {
                    loadingHtml: '<span class="material-symbols-outlined animate-spin">progress_activity</span> MENYIMPAN',
                    onError: (error) => {
                        el.priceFeedback.textContent = error.message;
                        el.priceFeedback.classList.remove('hidden');
                    },
                });
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
                    received: Number(item.qty || 0),
                }));
                renderItems();
            });

            el.saveReceiving.addEventListener('click', async () => {
                if (!currentPurchaseId) {
                    console.error('Purchase ID tidak ditemukan');
                    throw new Error('Purchase ID tidak ditemukan');
                }

                if (!canReceivePurchase()) {
                    return;
                }

                const totalItems = items.length;
                const orderedTotal = items.reduce((sum, item) => sum + Number(item.qty || 0), 0);
                const receivedTotal = items.reduce((sum, item) => sum + Number(item.received || 0), 0);

                const allowed = window.PosUi.confirmRiskAction({
                    title: 'Konfirmasi Penerimaan Barang',
                    message: `PO: ${el.subtitle.textContent}\nItem: ${totalItems}\nQty Order: ${formatQuantity(orderedTotal)}\nQty Diterima: ${formatQuantity(receivedTotal)}`,
                });
                if (!allowed) {
                    return;
                }

                await window.PosUi.runWithSubmitGuard(el.saveReceiving, async () => {
                    const payload = {
                        items: items.map((item) => ({
                            product_id: item.product_id,
                            qty: Number(item.received || 0),
                            purchase_unit: item.purchase_unit || item.unit || null,
                        })),
                    };

                    const response = await fetch(`/api/v1/purchases/${currentPurchaseId}/receive`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': csrfToken,
                            ...headers,
                        },
                        body: JSON.stringify(payload),
                    });

                    const responseData = await response.json();

                    if (!response.ok) {
                        throw new Error(responseData.message || 'Gagal menyimpan penerimaan barang');
                    }

                    window.location.href = `${@json($receivingPortalPrefix)}/receiving/${encodeURIComponent(currentPurchaseId)}?from=${encodeURIComponent(@json($receivingPortalPrefix.'/receiving'))}`;
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
                    currentPurchaseId = selectedId;
                    const data = await loadPurchaseDetail(selectedId);
                    currentPurchaseStatus = data.purchase.status;
                    currentPurchaseMode = normalizePurchaseMode(data.purchase.purchase_mode);
                    el.subtitle.textContent = `PO #${String(data.purchase.id).slice(0, 6).toUpperCase()} - ${data.supplier?.name || 'Pemasok'} - Lokasi: ${data.purchase.location_name || '-'}`;
                    items = (data.items || []).map((item) => ({
                        ...item,
                        base_qty: Number(item.qty || 0),
                        qty: Number(item.purchase_qty ?? item.qty ?? 0),
                        received: Number(item.remaining_purchase_qty ?? item.purchase_qty ?? item.qty ?? 0),
                        price: Number(item.price || 0),
                        purchase_price: Number(item.purchase_price ?? item.price ?? 0),
                    }));
                    el.saveReceiving.disabled = !canReceivePurchase();
                    el.saveReceiving.title = canReceivePurchase() ? '' : getReceiveBlockMessage();
                    renderItems();
                } catch (error) {
                    el.list.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                }
            })();
        })();
    </script>
@endsection
