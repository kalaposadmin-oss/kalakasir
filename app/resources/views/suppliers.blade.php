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
@section('main_class', 'flex-1 min-h-0 overflow-hidden p-0')

@section('header')
    <x-page-header title="Pemasok & PO" backUrl="/backoffice/dashboard">
        <x-slot name="actions">
            <button class="flex items-center justify-center size-10 rounded-full bg-slate-100 dark:bg-slate-800" type="button">
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
    <div class="flex h-full min-h-0 flex-col">
        <div class="flex shrink-0 px-4 border-b border-slate-100 dark:border-slate-800">
            <button id="tab-suppliers" class="flex-1 py-3 text-sm font-semibold border-b-2 border-primary text-primary" type="button">Daftar Pemasok</button>
            <button id="tab-po" class="flex-1 py-3 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700" type="button">Riwayat PO</button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto pb-28">
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
                <div class="flex items-center justify-between gap-3 px-1">
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Daftar Pemasok</h3>
                    <span id="supplier-summary" class="truncate text-xs font-semibold text-slate-400">Memuat...</span>
                </div>
                <div id="supplier-list" class="space-y-3"></div>
            </div>

            <div id="po-section" class="px-4 space-y-3 hidden">
                <div class="flex items-center justify-between gap-3 px-1">
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Riwayat PO</h3>
                    <span id="po-summary" class="truncate text-xs font-semibold text-slate-400">Memuat...</span>
                </div>
                <div id="po-list" class="space-y-3"></div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content || '';
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
                supplierSummary: document.getElementById('supplier-summary'),
                poSummary: document.getElementById('po-summary'),
            };

            const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Number(value || 0));

            const formatNumber = (value) => new Intl.NumberFormat('id-ID', {
                maximumFractionDigits: 2,
            }).format(Number(value || 0));

            const formatDate = (value) => {
                if (!value) {
                    return '-';
                }

                return new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                }).format(new Date(`${String(value).slice(0, 10)}T00:00:00`));
            };

            const statusLabel = (status) => ({
                draft: 'Draft',
                pending: 'Menunggu',
                ordered: 'Dipesan',
                approved: 'Disetujui',
                ready_to_receive: 'Siap Terima',
                partial_received: 'Sebagian',
                received: 'Diterima',
                closed: 'Selesai',
                cancelled: 'Batal',
            })[status] || String(status || '-').replaceAll('_', ' ');

            const statusClass = (status) => {
                if (['received', 'closed'].includes(status)) {
                    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
                }
                if (status === 'cancelled') {
                    return 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300';
                }
                if (['partial_received', 'ready_to_receive', 'approved'].includes(status)) {
                    return 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300';
                }

                return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
            };

            const renderSuppliers = (suppliers) => {
                el.supplierList.innerHTML = '';

                if (!suppliers.length) {
                    el.supplierSummary.textContent = '0 pemasok';
                    el.supplierList.innerHTML = '<div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-400 dark:border-slate-700">Belum ada pemasok.</div>';
                    return;
                }

                const totalProducts = suppliers.reduce((sum, supplier) => sum + Number(supplier.product_count || 0), 0);
                const totalPurchases = suppliers.reduce((sum, supplier) => sum + Number(supplier.purchase_total || 0), 0);
                el.supplierSummary.textContent = `${totalProducts} produk · ${formatCurrency(totalPurchases)}`;

                suppliers.forEach((supplier, index) => {
                    const colors = ['bg-primary/10 text-primary', 'bg-orange-100 text-orange-600', 'bg-sky-100 text-sky-600', 'bg-emerald-100 text-emerald-600'];
                    const hasDebt = Number(supplier.balance || 0) > 0;
                    const badgeClass = hasDebt
                        ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'
                        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
                    const row = document.createElement('div');
                    row.className = 'bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm';
                    row.innerHTML = `
                        <div class="flex items-start gap-3">
                            <div class="size-11 rounded-xl ${colors[index % colors.length]} flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[22px]">storefront</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <h4 class="truncate font-bold text-slate-900 dark:text-slate-100">${supplier.name}</h4>
                                        <p class="mt-0.5 text-xs text-slate-500">${Number(supplier.purchase_count || 0) ? `Terakhir belanja ${formatDate(supplier.last_purchase_date)}` : 'Belum ada riwayat belanja'}</p>
                                    </div>
                                    <span class="shrink-0 text-[10px] font-bold px-2 py-1 rounded-full ${badgeClass} uppercase">${hasDebt ? 'Ada Hutang' : 'Lunas'}</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 border-t border-slate-100 pt-3 text-center dark:border-slate-800">
                            <div class="rounded-lg bg-slate-50 px-2 py-2 dark:bg-slate-800/70">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Produk</p>
                                <p class="mt-1 text-sm font-black text-slate-900 dark:text-white">${formatNumber(supplier.product_count)}</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-2 py-2 dark:bg-slate-800/70">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">PO</p>
                                <p class="mt-1 text-sm font-black text-slate-900 dark:text-white">${formatNumber(supplier.purchase_count)}</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-2 py-2 dark:bg-slate-800/70">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Belanja</p>
                                <p class="mt-1 truncate text-sm font-black text-primary">${formatCurrency(supplier.purchase_total)}</p>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs">
                            <span class="font-semibold text-slate-400">Sisa tagihan</span>
                            <span class="font-black ${hasDebt ? 'text-rose-600' : 'text-slate-900 dark:text-slate-100'}">${formatCurrency(supplier.balance)}</span>
                        </div>
                    `;
                    el.supplierList.appendChild(row);
                });
            };

            const renderPurchases = (purchases) => {
                el.poList.innerHTML = '';

                if (!purchases.length) {
                    el.poSummary.textContent = '0 PO';
                    el.poList.innerHTML = '<div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-400 dark:border-slate-700">Belum ada PO.</div>';
                    return;
                }

                const totalValue = purchases.reduce((sum, purchase) => sum + Number(purchase.total || 0), 0);
                const totalItems = purchases.reduce((sum, purchase) => sum + Number(purchase.item_count || 0), 0);
                el.poSummary.textContent = `${purchases.length} PO · ${totalItems} item · ${formatCurrency(totalValue)}`;

                purchases.forEach((purchase) => {
                    const row = document.createElement('a');
                    const qty = Number(purchase.total_item_qty || 0);
                    const itemCount = Number(purchase.item_count || 0);
                    row.href = `/backoffice/purchases/${purchase.id}?from=${encodeURIComponent('/backoffice/suppliers')}`;
                    row.className = 'block bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm hover:border-primary/40 transition-colors';
                    row.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-xs font-semibold text-slate-400">${purchase.supplier_name || 'Pemasok'}</p>
                                <p class="mt-0.5 text-base font-black text-slate-900 dark:text-slate-100">${purchase.reference || `PO-${String(purchase.id).slice(0, 6).toUpperCase()}`}</p>
                                <p class="mt-1 text-xs text-slate-500">${formatDate(purchase.transaction_date || purchase.created_at)} · ${purchase.location_name || 'Lokasi utama'}</p>
                            </div>
                            <span class="shrink-0 text-[10px] font-bold px-2 py-1 rounded-full uppercase ${statusClass(purchase.status)}">${statusLabel(purchase.status)}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 border-t border-slate-100 pt-3 text-center dark:border-slate-800">
                            <div class="rounded-lg bg-slate-50 px-2 py-2 dark:bg-slate-800/70">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Item</p>
                                <p class="mt-1 text-sm font-black text-slate-900 dark:text-white">${formatNumber(itemCount)}</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 px-2 py-2 dark:bg-slate-800/70">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Qty</p>
                                <p class="mt-1 text-sm font-black text-slate-900 dark:text-white">${formatNumber(qty)}</p>
                            </div>
                            <div class="rounded-lg bg-orange-50 px-2 py-2 dark:bg-orange-950/25">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-orange-400">Total</p>
                                <p class="mt-1 truncate text-sm font-black text-primary">${formatCurrency(purchase.total)}</p>
                            </div>
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
                el.tabSuppliers.classList.toggle('border-transparent', !isSuppliers);
                el.tabSuppliers.classList.toggle('text-slate-500', !isSuppliers);
                el.tabPo.classList.toggle('border-primary', !isSuppliers);
                el.tabPo.classList.toggle('text-primary', !isSuppliers);
                el.tabPo.classList.toggle('border-transparent', isSuppliers);
                el.tabPo.classList.toggle('text-slate-500', isSuppliers);
            };

            el.tabSuppliers.addEventListener('click', () => setTab('suppliers'));
            el.tabPo.addEventListener('click', () => setTab('po'));

            (async () => {
                const [supplierData, purchaseData] = await Promise.all([loadSuppliers(), loadPurchases()]);
                el.totalSuppliers.textContent = supplierData.total || 0;
                el.totalBalance.textContent = formatCurrency(supplierData.total_balance || 0);
                renderSuppliers(supplierData.suppliers || []);
                renderPurchases(purchaseData.purchases || []);
            })();
        })();
    </script>
@endsection
