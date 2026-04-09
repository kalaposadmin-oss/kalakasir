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
    $receivingPurchaseId = (string) ($purchaseId ?? request()->query('purchase_id', ''));
    $receivingCurrentPath = $currentPath ?? ($receivingPurchaseId !== '' ? '/backoffice/receiving/'.$receivingPurchaseId : '/backoffice/receiving');
    $receivingBackUrl = $backUrl ?? ($receivingPurchaseId !== '' ? '/backoffice/receiving' : '/backoffice/suppliers');
@endphp

@extends('layouts.app')

@section('page_title', 'Terima Barang')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100')
@section('main_class', 'flex-1 overflow-y-auto pb-24')

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
        <div class="flex flex-col gap-3 px-4 pb-24" id="purchase-list"></div>
    </div>

    <div id="receiving-detail-view" class="hidden">
        <div class="p-4">
            <div class="flex flex-col items-stretch justify-start rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="w-full bg-center bg-no-repeat aspect-[21/9] bg-cover" style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuDeHfyqGdfkFnMuGFtIXN_QbsR4upghE3THdltvYScctXSrIEj05qdye1sgytKEsnMEwLh6z6x8vfGyMcXVe7cducZI7poWvT9gOHvVEC0h41M2yiaBhin41LNSqQb-AOhrnzwT36lS3QDDfrvrYUT-ekEHGR0HnXLqdhSsS2HUjxrEPrzMQyg2_nJYNrvQT2JpJkk-VmsuHYUD9rmTH-tISLFnQW8Ylpw0F1s6rolJRx1PCrFflUd-2qTegqQykP2zvk7vYhpwGA");'></div>
                <div class="flex w-full flex-col p-4" id="purchase-header"></div>
            </div>
        </div>

        <div class="px-4 py-2 flex items-center justify-between">
            <h3 class="text-slate-900 dark:text-slate-100 text-base font-bold leading-tight">Daftar Barang Dipesan</h3>
            <span class="text-slate-500 text-xs font-medium" id="item-count">0 Item</span>
        </div>

        <div class="flex flex-col gap-3 px-4 pb-24" id="item-list"></div>

        @if ($canManageReceivingFlow)
            <div class="fixed bottom-0 left-0 right-0 p-4 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 z-20">
                <div class="max-w-md mx-auto flex gap-3">
                    <button class="flex-1 flex items-center justify-center h-12 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-sm tracking-wide shadow-sm">
                        SIMPAN DRAFT
                    </button>
                    <a id="detail-action-button" class="hidden flex-[1.5] items-center justify-center h-12 rounded-xl bg-primary text-white font-bold text-sm tracking-wide shadow-lg shadow-primary/30" href="#">
                        KONFIRMASI STOK
                    </a>
                </div>
            </div>
        @endif
    </div>

    <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const purchaseId = @json($receivingPurchaseId) || new URLSearchParams(window.location.search).get('purchase_id');
                const currentPath = @json($receivingCurrentPath);

                const el = {
                    listView: document.getElementById('receiving-list-view'),
                    detailView: document.getElementById('receiving-detail-view'),
                    actionLink: document.getElementById('receiving-action-link'),
                    detailActionButton: document.getElementById('detail-action-button'),
                    purchaseList: document.getElementById('purchase-list'),
                    purchaseListCount: document.getElementById('purchase-list-count'),
                    header: document.getElementById('purchase-header'),
                    list: document.getElementById('item-list'),
                    count: document.getElementById('item-count'),
                };

                const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                }).format(new Date(value));

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

                const buildDetailUrl = (selectedPurchaseId) => `/backoffice/receiving/${encodeURIComponent(selectedPurchaseId)}?from=${encodeURIComponent('/backoffice/receiving')}`;
                const buildActionUrl = (selectedPurchaseId) => `/backoffice/receiving/${encodeURIComponent(selectedPurchaseId)}/action?from=${encodeURIComponent(currentPath)}`;

                const renderHeader = (data) => {
                    const supplier = data.supplier?.name || 'Pemasok';
                    el.header.innerHTML = `
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <p class="text-slate-500 dark:text-slate-400 text-xs font-medium uppercase tracking-wider">Nomor Pesanan</p>
                                <p class="text-slate-900 dark:text-slate-100 text-lg font-bold leading-tight">PO-${String(data.purchase.id).slice(0, 6).toUpperCase()}</p>
                            </div>
                            <span class="px-2 py-1 rounded bg-primary/10 text-primary text-[10px] font-bold uppercase">${data.purchase.status}</span>
                        </div>
                        <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            <span class="material-symbols-outlined text-sm">store</span>
                            <p class="text-sm font-normal">Pemasok: ${supplier}</p>
                        </div>
                        <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400 mt-1">
                            <span class="material-symbols-outlined text-sm">calendar_today</span>
                            <p class="text-sm font-normal">Estimasi: ${formatDate(data.purchase.created_at)}</p>
                        </div>
                    `;
                };

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
                                <span class="px-2 py-1 rounded bg-primary/10 text-primary text-[10px] font-bold uppercase">${purchase.status || 'draft'}</span>
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

                    items.forEach((item) => {
                        const image = item.product_image || 'https://placehold.co/120x120?text=Produk';
                        const row = document.createElement('div');
                        row.className = 'flex flex-col bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm gap-4';
                        row.innerHTML = `
                            <div class="flex gap-4">
                                <div class="bg-center bg-no-repeat aspect-square bg-cover rounded-lg size-16 shrink-0" style='background-image: url("${image}");'></div>
                                <div class="flex flex-1 flex-col justify-center">
                                    <p class="text-slate-900 dark:text-slate-100 text-sm font-semibold">${item.product_name}</p>
                                    <p class="text-slate-500 dark:text-slate-400 text-xs">SKU: ${item.product_sku || '-'}</p>
                                    <p class="text-primary text-xs font-medium mt-1">Dipesan: ${item.qty} unit</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                                <div class="flex flex-col gap-1">
                                    <p class="text-slate-700 dark:text-slate-300 text-xs font-medium">Jumlah Diterima</p>
                                    <div class="flex items-center gap-2 text-slate-900 dark:text-slate-100 bg-slate-50 dark:bg-slate-800 rounded-lg p-1 border border-slate-200 dark:border-slate-700">
                                        <button class="flex h-8 w-8 items-center justify-center rounded-md bg-white dark:bg-slate-700 shadow-sm text-primary cursor-pointer">
                                            <span class="material-symbols-outlined text-lg">remove</span>
                                        </button>
                                        <input class="text-sm font-bold w-full p-0 text-center bg-transparent border-none focus:ring-0" type="number" value="${item.qty}" />
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
                        renderHeader(data);
                        renderItems(data.items || []);
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


