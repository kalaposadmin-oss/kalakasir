@extends('layouts.app')

@php
    $suppliersTenant = \App\Support\TenantContext::get();
    $suppliersUser = auth()->user();
    $suppliersRole = null;

    if (! $suppliersTenant && filled($suppliersUser?->tenant_id)) {
        $suppliersTenant = \App\Models\Tenant::query()->find($suppliersUser->tenant_id);
    }

    if ($suppliersTenant && $suppliersUser) {
        $suppliersRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $suppliersTenant->id, (string) $suppliersUser->id);

        if (! $suppliersRole && filled($suppliersUser->role ?? null)) {
            $suppliersRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $suppliersUser->role);
        }
    }

    abort_unless(in_array($suppliersRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManagePurchases = in_array($suppliersRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Pemasok & Pembelian (PO)')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
@endpush

@section('header')
    <x-page-header title="Pemasok & PO" backUrl="/backoffice/dashboard">
        <x-slot name="actions">
            <button class="flex items-center justify-center size-10 rounded-full bg-slate-100 dark:bg-slate-800">
                <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">search</span>
            </button>
            @if ($canManagePurchases)
                <a class="flex items-center justify-center size-10 rounded-full bg-primary text-white" href="/backoffice/purchases/new">
                    <span class="material-symbols-outlined">add</span>
                </a>
            @endif
        </x-slot>
    </x-page-header>
@endsection

@section('content')
        <div class="flex px-4 border-b border-slate-100 dark:border-slate-800">
            <button id="tab-suppliers" class="flex-1 py-3 text-sm font-semibold border-b-2 border-primary text-primary">Daftar Pemasok</button>
            <button id="tab-po" class="flex-1 py-3 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700">Riwayat PO</button>
        </div>

        <div class="flex-1 overflow-y-auto pb-24">
            <div class="grid grid-cols-2 gap-3 p-4">
                <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Total Pemasok</p>
                    <p class="text-xl font-bold" id="total-suppliers">0</p>
                </div>
                <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Total Hutang</p>
                    <p class="text-xl font-bold text-red-500" id="total-balance">Rp 0</p>
                </div>
            </div>

            @if ($canManagePurchases)
                <div class="px-4 mb-4">
                    <a class="w-full bg-primary text-white py-4 rounded-xl font-bold flex items-center justify-center gap-2 shadow-lg shadow-primary/20" href="/backoffice/purchases/new">
                        <span class="material-symbols-outlined">shopping_cart_checkout</span>
                        Tambah PO Baru
                    </a>
                </div>
            @endif

            <div id="supplier-section" class="px-4 space-y-3">
                <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest px-1">Daftar Pemasok</h3>
                <div id="supplier-list" class="space-y-3"></div>
            </div>

            <div id="po-section" class="px-4 space-y-3 hidden">
                <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest px-1">Riwayat PO</h3>
                <div id="po-list" class="space-y-3"></div>
            </div>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    totalSuppliers: document.getElementById('total-suppliers'),
                    totalBalance: document.getElementById('total-balance'),
                    supplierList: document.getElementById('supplier-list'),
                    poList: document.getElementById('po-list'),
                    supplierSection: document.getElementById('supplier-section'),
                    poSection: document.getElementById('po-section'),
                    tabSuppliers: document.getElementById('tab-suppliers'),
                    tabPo: document.getElementById('tab-po'),
                };

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const renderSuppliers = (suppliers) => {
                    el.supplierList.innerHTML = '';

                    if (!suppliers.length) {
                        el.supplierList.innerHTML = '<div class="text-sm text-slate-400">Belum ada pemasok.</div>';
                        return;
                    }

                    suppliers.forEach((supplier, index) => {
                        const colors = ['bg-primary/10 text-primary', 'bg-orange-100 text-orange-600', 'bg-blue-100 text-blue-600', 'bg-purple-100 text-purple-600'];
                        const badgeClass = supplier.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500';
                        const iconClass = colors[index % colors.length];
                        const row = document.createElement('div');
                        row.className = 'bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex items-start gap-4';
                        row.innerHTML = `
                            <div class="size-12 rounded-lg ${iconClass} flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined">storefront</span>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-bold text-slate-900 dark:text-slate-100">${supplier.name}</h4>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${badgeClass} uppercase">${supplier.status === 'active' ? 'Aktif' : 'Non-Aktif'}</span>
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">Kontak belum diisi</p>
                                <div class="mt-3 flex items-center justify-between border-t border-slate-50 dark:border-slate-800 pt-3">
                                    <span class="text-xs text-slate-400">Sisa Tagihan:</span>
                                    <span class="text-sm font-bold text-slate-900 dark:text-slate-100">${formatCurrency(supplier.balance)}</span>
                                </div>
                            </div>
                        `;
                        el.supplierList.appendChild(row);
                    });
                };

                const renderPurchases = (purchases) => {
                    el.poList.innerHTML = '';

                    if (!purchases.length) {
                        el.poList.innerHTML = '<div class="text-sm text-slate-400">Belum ada PO.</div>';
                        return;
                    }

                    purchases.forEach((purchase) => {
                        const row = document.createElement('a');
                        row.href = `/backoffice/purchases/${purchase.id}?from=${encodeURIComponent('/backoffice/suppliers')}`;
                        row.className = 'bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-primary/40 transition-colors';
                        row.innerHTML = `
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs text-slate-400">${purchase.supplier_name || 'Pemasok'}</p>
                                    <p class="text-sm font-bold text-slate-900 dark:text-slate-100">PO-${String(purchase.id).slice(0, 6).toUpperCase()}</p>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/10 text-primary uppercase">${purchase.status}</span>
                            </div>
                            <div class="mt-3 flex items-center justify-between border-t border-slate-50 dark:border-slate-800 pt-3">
                                <span class="text-xs text-slate-400">Total:</span>
                                <span class="text-sm font-bold">${formatCurrency(purchase.total)}</span>
                            </div>
                        `;
                        el.poList.appendChild(row);
                    });
                };

                const loadSuppliers = async () => {
                    const response = await fetch('/api/v1/suppliers', { headers });
                    if (!response.ok) {
                        return { suppliers: [], total: 0, total_balance: 0 };
                    }
                    return response.json();
                };

                const loadPurchases = async () => {
                    const response = await fetch('/api/v1/purchases', { headers });
                    if (!response.ok) {
                        return { purchases: [] };
                    }
                    return response.json();
                };

                const setTab = (tab) => {
                    const isSuppliers = tab === 'suppliers';
                    el.supplierSection.classList.toggle('hidden', !isSuppliers);
                    el.poSection.classList.toggle('hidden', isSuppliers);
                    el.tabSuppliers.classList.toggle('border-primary', isSuppliers);
                    el.tabSuppliers.classList.toggle('text-primary', isSuppliers);
                    el.tabPo.classList.toggle('border-primary', !isSuppliers);
                    el.tabPo.classList.toggle('text-primary', !isSuppliers);
                };

                el.tabSuppliers.addEventListener('click', () => setTab('suppliers'));
                el.tabPo.addEventListener('click', () => setTab('po'));

                (async () => {
                    const supplierData = await loadSuppliers();
                    const purchaseData = await loadPurchases();
                    el.totalSuppliers.textContent = supplierData.total || 0;
                    el.totalBalance.textContent = formatCurrency(supplierData.total_balance || 0);
                    renderSuppliers(supplierData.suppliers || []);
                    renderPurchases(purchaseData.purchases || []);
                })();
            })();
        </script>
@endsection
