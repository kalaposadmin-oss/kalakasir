@extends('layouts.app')

@php
    $purchaseIndexTenant = \App\Support\TenantContext::get();
    $purchaseIndexUser = auth()->user();
    $purchaseIndexRole = null;

    if (! $purchaseIndexTenant && filled($purchaseIndexUser?->tenant_id)) {
        $purchaseIndexTenant = \App\Models\Tenant::query()->find($purchaseIndexUser->tenant_id);
    }

    if ($purchaseIndexTenant && $purchaseIndexUser) {
        $purchaseIndexRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $purchaseIndexTenant->id, (string) $purchaseIndexUser->id);

        if (! $purchaseIndexRole && filled($purchaseIndexUser->role ?? null)) {
            $purchaseIndexRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $purchaseIndexUser->role);
        }
    }

    abort_unless(in_array($purchaseIndexRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $purchaseIndexPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $purchaseIndexBackUrl = $purchaseIndexPortalPrefix === '/pos' ? pos_default_url() : '/backoffice/dashboard';
    $purchaseIndexCanCreate = in_array($purchaseIndexRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Daftar Pembelian')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 min-h-0 overflow-hidden p-0')

@section('header')
    <x-page-header title="Daftar Pembelian" :backUrl="$purchaseIndexBackUrl" :showMenuButton="true">
        <x-slot:actions>
            @if ($purchaseIndexCanCreate)
                <a class="flex items-center justify-center size-10 rounded-full bg-primary text-white" href="{{ $purchaseIndexPortalPrefix.'/purchases/new' }}" aria-label="Buat pembelian baru">
                    <span class="material-symbols-outlined">add</span>
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <div class="flex h-full min-h-0 flex-col gap-3 px-4 py-4">
        <label class="block">
            <span class="app-search-control relative flex items-center px-3 dark:bg-slate-900 dark:ring-slate-800">
                <span class="material-symbols-outlined text-slate-400">search</span>
                <input id="purchase-search" class="w-full border-0 bg-transparent px-3 text-sm focus:ring-0" placeholder="Cari nomor PO atau pemasok..." type="text" />
            </span>
        </label>
        <div class="flex items-center justify-between">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-400" id="purchase-count">0 PO</p>
            <div class="flex items-center gap-3">
                @if ($purchaseIndexCanCreate)
                    <a class="text-xs font-semibold text-primary" href="{{ $purchaseIndexPortalPrefix.'/purchases/new' }}">Buat PO Baru</a>
                @endif
                <a class="text-xs font-semibold text-primary" href="{{ $purchaseIndexPortalPrefix.'/receiving' }}">Buka Receiving</a>
            </div>
        </div>
        <section class="grid grid-cols-3 gap-2" id="purchase-summary">
            <div class="rounded-xl border border-slate-200 bg-white px-3 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total PO</p>
                <p class="mt-1 text-sm font-black text-slate-950 dark:text-white" data-summary="po">0</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white px-3 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Item</p>
                <p class="mt-1 text-sm font-black text-slate-950 dark:text-white" data-summary="items">0</p>
            </div>
            <div class="rounded-xl border border-orange-200 bg-orange-50 px-3 py-3 shadow-sm dark:border-orange-900/40 dark:bg-orange-950/20">
                <p class="text-[10px] font-bold uppercase tracking-wider text-orange-700 dark:text-orange-300">Nilai</p>
                <p class="mt-1 text-sm font-black text-orange-700 dark:text-orange-300" data-summary="value">Rp0</p>
            </div>
        </section>
        <div id="purchase-list" class="grid min-h-0 flex-1 grid-cols-1 gap-3 overflow-y-auto pb-28 sm:grid-cols-2"></div>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]').content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
            const portalPrefix = @json($purchaseIndexPortalPrefix);
            const purchaseStatusLabels = @json(\App\Support\PurchaseStatus::labels());
            const purchaseStatusAliases = @json(\App\Support\PurchaseStatus::aliases());

            const normalizePurchaseStatus = (status) => {
                const value = String(status || '').toLowerCase();
                return purchaseStatusAliases[value] || value;
            };

            const el = {
                count: document.getElementById('purchase-count'),
                list: document.getElementById('purchase-list'),
                search: document.getElementById('purchase-search'),
                summary: document.getElementById('purchase-summary'),
            };

            const state = {
                purchases: [],
                term: '',
            };

            const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            }).format(new Date(value));

            const rupiah = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Number(value || 0));

            const formatQty = (value) => new Intl.NumberFormat('id-ID', {
                maximumFractionDigits: 2,
            }).format(Number(value || 0));

            const statusClassMap = {
                draft: 'bg-slate-100 text-slate-600',
                submitted: 'bg-amber-100 text-amber-700',
                approved: 'bg-blue-100 text-blue-700',
                partially_received: 'bg-cyan-100 text-cyan-700',
                received: 'bg-emerald-100 text-emerald-700',
                closed: 'bg-slate-900 text-white',
                cancelled: 'bg-rose-100 text-rose-700',
            };

            const render = () => {
                const term = state.term.trim().toLowerCase();
                const rows = state.purchases.filter((purchase) => {
                    if (!term) {
                        return true;
                    }

                    return [
                        purchase.reference || '',
                        purchase.supplier_name || '',
                        String(purchase.id || ''),
                    ].some((value) => value.toLowerCase().includes(term));
                });

                el.count.textContent = `${rows.length} PO`;
                el.summary.querySelector('[data-summary="po"]').textContent = rows.length;
                el.summary.querySelector('[data-summary="items"]').textContent = rows.reduce((total, purchase) => total + Number(purchase.item_count || 0), 0);
                el.summary.querySelector('[data-summary="value"]').textContent = rupiah(rows.reduce((total, purchase) => total + Number(purchase.total || 0), 0));
                el.list.innerHTML = '';

                if (!rows.length) {
                    el.list.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Belum ada data pembelian.</div>';
                    return;
                }

                rows.forEach((purchase) => {
                    const status = normalizePurchaseStatus(purchase.status || 'draft');
                    const statusLabel = purchaseStatusLabels[status] || purchaseStatusLabels.draft;
                    const subtotal = Number(purchase.subtotal || 0);
                    const additionalCost = Number(purchase.additional_cost_total || 0);
                    const detailPath = portalPrefix === '/pos'
                        ? `${portalPrefix}/receiving/${encodeURIComponent(purchase.id)}`
                        : `${portalPrefix}/purchases/${encodeURIComponent(purchase.id)}`;
                    const detailUrl = `${detailPath}?from=${encodeURIComponent(`${portalPrefix}/purchases`)}`;
                    const row = document.createElement('a');
                    row.href = detailUrl;
                    row.className = 'app-card block p-3 transition hover:border-primary/30';
                    row.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Purchase Order</p>
                                <h3 class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">${purchase.reference || `PO-${String(purchase.id).slice(0, 6).toUpperCase()}`}</h3>
                                <p class="mt-1 text-xs text-slate-500">${purchase.supplier_name || 'Pemasok belum dipilih'}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase ${statusClassMap[status] || statusClassMap.draft}">${statusLabel}</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                            <span>${formatDate(purchase.created_at)}</span>
                            <span class="font-semibold text-primary">Lihat Detail</span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-2 text-xs dark:bg-slate-800/60">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Item</p>
                                <p class="mt-1 font-black text-slate-900 dark:text-white">${Number(purchase.item_count || 0).toLocaleString('id-ID')}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Qty</p>
                                <p class="mt-1 font-black text-slate-900 dark:text-white">${formatQty(purchase.total_item_qty || 0)}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total</p>
                                <p class="mt-1 font-black text-primary">${rupiah(purchase.total || 0)}</p>
                            </div>
                        </div>
                        ${additionalCost > 0 ? `
                            <div class="mt-2 flex items-center justify-between rounded-xl border border-orange-100 bg-orange-50 px-3 py-2 text-xs dark:border-orange-900/40 dark:bg-orange-950/20">
                                <span class="font-semibold text-orange-700 dark:text-orange-300">Biaya tambahan</span>
                                <span class="font-black text-orange-700 dark:text-orange-300">${rupiah(additionalCost)}</span>
                            </div>
                        ` : `
                            <div class="mt-2 flex items-center justify-between px-1 text-[11px] text-slate-400">
                                <span>Nilai barang</span>
                                <span>${rupiah(subtotal || purchase.total || 0)}</span>
                            </div>
                        `}
                    `;
                    el.list.appendChild(row);
                });
            };

            const loadPurchases = async () => {
                const response = await fetch('/api/v1/purchases', { headers });
                if (!response.ok) {
                    el.list.innerHTML = '<div class="rounded-2xl bg-white px-4 py-10 text-center text-sm text-red-500 dark:bg-slate-900">Gagal memuat daftar pembelian.</div>';
                    return;
                }

                const data = await response.json();
                state.purchases = data.purchases || [];
                render();
            };

            el.search.addEventListener('input', (event) => {
                state.term = event.target.value;
                render();
            });

            loadPurchases();
        })();
    </script>
@endsection
