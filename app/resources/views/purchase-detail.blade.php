@extends('layouts.app')

@php
    $purchaseDetailTenant = \App\Support\TenantContext::get();
    $purchaseDetailUser = auth()->user();
    $purchaseDetailRole = null;

    if (! $purchaseDetailTenant && filled($purchaseDetailUser?->tenant_id)) {
        $purchaseDetailTenant = \App\Models\Tenant::query()->find($purchaseDetailUser->tenant_id);
    }

    if ($purchaseDetailTenant && $purchaseDetailUser) {
        $purchaseDetailRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $purchaseDetailTenant->id, (string) $purchaseDetailUser->id);

        if (! $purchaseDetailRole && filled($purchaseDetailUser->role ?? null)) {
            $purchaseDetailRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $purchaseDetailUser->role);
        }
    }

    abort_unless(in_array($purchaseDetailRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManagePurchaseDetail = in_array($purchaseDetailRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Detail dan Persetujuan PO')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto pb-32')

@push('head')
        <style type="text/tailwindcss">
            .status-badge {
                @apply px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider;
            }
        </style>
@endpush

@php
    $purchaseCurrentPath = $currentPath ?? '/backoffice/purchases/'.((string) ($purchaseId ?? request()->query('purchase_id', '')));
    $purchaseBackUrl = $backUrl ?? '/backoffice/suppliers';
    $resolvedPurchaseId = (string) ($purchaseId ?? request()->query('purchase_id', ''));
@endphp

@section('header')
    <x-page-header
        title="PO -"
        titleId="po-number"
        subtitle="-"
        subtitleId="po-supplier"
        :backUrl="$purchaseBackUrl"
        :showMenuButton="true"
    >
        <x-slot:actions>
            <div id="status-badge" class="status-badge bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Menunggu</div>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <div class="p-4 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 grid grid-cols-2 gap-4">
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Tanggal Pesan</p>
            <p class="text-sm font-semibold" id="order-date">-</p>
        </div>
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Estimasi Tiba</p>
            <p class="text-sm font-semibold" id="arrival-date">-</p>
        </div>
    </div>
    <section class="mt-4 px-4">
        <div class="flex items-center justify-between mb-3 px-1">
            <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Daftar Barang</h3>
            <span class="text-xs font-medium text-slate-400" id="item-count">0 Produk</span>
        </div>
        <div class="space-y-2" id="item-list"></div>
        <div class="mt-4 bg-primary/5 rounded-xl p-4 border border-primary/20">
            <div class="flex justify-between items-center mb-1">
                <span class="text-xs text-slate-500">Subtotal</span>
                <span class="text-sm font-medium" id="subtotal">Rp 0</span>
            </div>
            <div class="flex justify-between items-center mb-3">
                <span class="text-xs text-slate-500">Pajak (PPN 11%)</span>
                <span class="text-sm font-medium text-red-500" id="tax">Rp 0</span>
            </div>
            <div class="flex justify-between items-center pt-3 border-t border-primary/20">
                <span class="font-bold text-slate-900 dark:text-white">Total Akhir</span>
                <span class="text-lg font-bold text-primary" id="total">Rp 0</span>
            </div>
        </div>
    </section>
    <section class="mt-6 px-4">
        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest px-1 mb-4">Timeline Aktivitas</h3>
        <div class="relative space-y-6 pl-4">
            <div class="absolute left-[7px] top-2 bottom-2 w-0.5 bg-slate-200 dark:bg-slate-800"></div>
            <div class="relative flex gap-4">
                <div class="absolute -left-[21px] mt-1 size-4 rounded-full bg-emerald-500 ring-4 ring-white dark:ring-slate-900"></div>
                <div class="flex-1">
                    <p class="text-sm font-bold">Dibuat</p>
                    <p class="text-xs text-slate-500" id="created-info">-</p>
                </div>
            </div>
            <div class="relative flex gap-4">
                <div id="status-dot" class="absolute -left-[21px] mt-1 size-4 rounded-full bg-amber-400 ring-4 ring-white dark:ring-slate-900"></div>
                <div class="flex-1">
                    <p class="text-sm font-bold" id="status-title">Menunggu Persetujuan</p>
                    <p class="text-xs text-slate-500" id="status-info">Sistem</p>
                </div>
            </div>
        </div>
    </section>
    @if ($canManagePurchaseDetail)
        <div class="fixed bottom-0 w-full max-w-[430px] bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl border-t border-slate-200 dark:border-slate-800 pt-4 pb-10 px-4">
            <div class="flex gap-3">
                <button id="reject" class="flex-1 py-4 px-6 rounded-xl border-2 border-slate-200 dark:border-slate-700 font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50">Tolak</button>
                <button id="approve" class="flex-[2] py-4 px-6 rounded-xl bg-primary text-white font-bold shadow-lg shadow-primary/30 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    Setujui PO
                </button>
            </div>
            <div class="mt-4 flex justify-center">
                <div class="w-32 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
            </div>
        </div>
    @endif

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    poNumber: document.getElementById('po-number'),
                    poSupplier: document.getElementById('po-supplier'),
                    statusBadge: document.getElementById('status-badge'),
                    orderDate: document.getElementById('order-date'),
                    arrivalDate: document.getElementById('arrival-date'),
                    itemCount: document.getElementById('item-count'),
                    itemList: document.getElementById('item-list'),
                    subtotal: document.getElementById('subtotal'),
                    tax: document.getElementById('tax'),
                    total: document.getElementById('total'),
                    createdInfo: document.getElementById('created-info'),
                    statusTitle: document.getElementById('status-title'),
                    statusInfo: document.getElementById('status-info'),
                    statusDot: document.getElementById('status-dot'),
                    approve: document.getElementById('approve'),
                    reject: document.getElementById('reject'),
                };

                let purchaseId = @json($resolvedPurchaseId) || new URLSearchParams(window.location.search).get('purchase_id');

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                }).format(new Date(value));

                const loadPurchase = async () => {
                    if (!purchaseId) {
                        const response = await fetch('/api/v1/purchases', { headers });
                        const data = await response.json();
                        purchaseId = data.purchases?.[0]?.id || null;
                    }
                    if (!purchaseId) {
                        return null;
                    }
                    const response = await fetch(`/api/v1/purchases/${purchaseId}`, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat detail PO.');
                    }
                    return response.json();
                };

                const setStatus = (status) => {
                    const label = status === 'approved'
                        ? 'Disetujui'
                        : status === 'rejected'
                            ? 'Ditolak'
                            : 'Menunggu';
                    el.statusBadge.textContent = label;
                    if (status === 'approved') {
                        el.statusBadge.className = 'status-badge bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
                        el.statusDot.className = 'absolute -left-[21px] mt-1 size-4 rounded-full bg-emerald-500 ring-4 ring-white dark:ring-slate-900';
                    } else if (status === 'rejected') {
                        el.statusBadge.className = 'status-badge bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400';
                        el.statusDot.className = 'absolute -left-[21px] mt-1 size-4 rounded-full bg-red-400 ring-4 ring-white dark:ring-slate-900';
                    } else {
                        el.statusBadge.className = 'status-badge bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
                        el.statusDot.className = 'absolute -left-[21px] mt-1 size-4 rounded-full bg-amber-400 ring-4 ring-white dark:ring-slate-900';
                    }
                    el.statusTitle.textContent = label === 'Menunggu' ? 'Menunggu Persetujuan Manajer' : label;
                };

                const renderItems = (items) => {
                    el.itemList.innerHTML = '';
                    el.itemCount.textContent = `${items.length} Produk`;

                    items.forEach((item) => {
                        const row = document.createElement('div');
                        row.className = 'bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800';
                        row.innerHTML = `
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-bold text-sm">${item.product_name}</h4>
                                <span class="text-xs font-bold text-primary">${formatCurrency(item.price)}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-slate-500">Jumlah: ${item.qty} Pcs</span>
                                <span class="text-sm font-bold">${formatCurrency(item.qty * item.price)}</span>
                            </div>
                        `;
                        el.itemList.appendChild(row);
                    });
                };

                const decide = async (status) => {
                    const response = await fetch(`/api/v1/purchases/${purchaseId}/decision`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            ...headers,
                        },
                        body: JSON.stringify({ status }),
                    });
                    if (!response.ok) {
                        alert('Gagal memperbarui status PO.');
                        return;
                    }
                    setStatus(status);
                };

                if (el.approve && el.reject) {
                    el.approve.addEventListener('click', () => decide('approved'));
                    el.reject.addEventListener('click', () => decide('rejected'));
                }

                (async () => {
                    try {
                        const data = await loadPurchase();
                        if (!data) {
                            el.itemList.innerHTML = '<div class="text-sm text-slate-400">Belum ada PO.</div>';
                            return;
                        }
                        const items = data.items || [];
                        const subtotal = items.reduce((sum, item) => sum + (item.qty * item.price), 0);
                        const tax = subtotal * 0.11;
                        const total = subtotal + tax;

                        el.poNumber.textContent = `PO #${String(data.purchase.id).slice(0, 6).toUpperCase()}`;
                        el.poSupplier.textContent = data.supplier?.name || 'Pemasok';
                        el.orderDate.textContent = formatDate(data.purchase.created_at);
                        el.arrivalDate.textContent = formatDate(data.purchase.created_at);
                        el.createdInfo.textContent = `Sistem - ${formatDate(data.purchase.created_at)}`;
                        el.statusInfo.textContent = 'Sistem';
                        el.subtotal.textContent = formatCurrency(subtotal);
                        el.tax.textContent = formatCurrency(tax);
                        el.total.textContent = formatCurrency(total);
                        renderItems(items);
                        setStatus(data.purchase.status || 'menunggu');
                    } catch (error) {
                        el.itemList.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                    }
                })();
            })();
        </script>
    @endsection


