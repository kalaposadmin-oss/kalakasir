@extends('layouts.app')

@php
    $stockTransferDetailTenant = \App\Support\TenantContext::get();
    $stockTransferDetailUser = auth()->user();
    $stockTransferDetailRole = null;

    if (! $stockTransferDetailTenant && filled($stockTransferDetailUser?->tenant_id)) {
        $stockTransferDetailTenant = \App\Models\Tenant::query()->find($stockTransferDetailUser->tenant_id);
    }

    if ($stockTransferDetailTenant && $stockTransferDetailUser) {
        $stockTransferDetailRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $stockTransferDetailTenant->id, (string) $stockTransferDetailUser->id);

        if (! $stockTransferDetailRole && filled($stockTransferDetailUser->role ?? null)) {
            $stockTransferDetailRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $stockTransferDetailUser->role);
        }
    }

    abort_unless(in_array($stockTransferDetailRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $stockTransferDetailPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $stockTransferDetailBackUrl = $backUrl ?? $stockTransferDetailPortalPrefix.'/inventory/stock-transfers';
    $stockTransferDetailId = (string) ($transferId ?? request()->route('transfer', ''));
@endphp

@section('page_title', 'Detail Mutasi Stok')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto pb-28 app-bottom-safe-space')

@section('header')
    <x-page-header title="Detail Mutasi" :backUrl="$stockTransferDetailBackUrl" :showMenuButton="true">
        <x-slot:actions>
            <span id="transfer-status" class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">Memuat</span>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <div class="px-4 py-4 space-y-4">
        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Referensi</p>
            <h2 id="transfer-reference" class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">-</h2>
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Dari</p>
                    <p id="transfer-from-branch" class="mt-1 font-semibold">-</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Ke</p>
                    <p id="transfer-to-branch" class="mt-1 font-semibold">-</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold uppercase tracking-widest text-slate-500">Item Transfer</h3>
                <span id="transfer-item-count" class="text-xs font-semibold text-slate-400">0 item</span>
            </div>
            <div id="transfer-item-list" class="mt-4 space-y-3"></div>
        </section>
    </div>

    <div class="fixed bottom-0 left-1/2 z-30 w-full max-w-[430px] -translate-x-1/2 border-t border-slate-200 bg-white/95 p-4 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/95 app-bottom-safe-footer">
        <button id="transfer-receive-action" class="hidden w-full rounded-xl bg-primary px-4 py-4 text-sm font-bold text-white shadow-lg shadow-primary/20" type="button">
            Terima Barang
        </button>
        <button id="transfer-disabled-action" class="hidden w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-100 px-4 py-4 text-sm font-bold text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500" disabled type="button">
            Aksi belum tersedia
        </button>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]').content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
            const transferId = @json($stockTransferDetailId);

            const el = {
                status: document.getElementById('transfer-status'),
                reference: document.getElementById('transfer-reference'),
                fromBranch: document.getElementById('transfer-from-branch'),
                toBranch: document.getElementById('transfer-to-branch'),
                itemCount: document.getElementById('transfer-item-count'),
                itemList: document.getElementById('transfer-item-list'),
                receive: document.getElementById('transfer-receive-action'),
                disabled: document.getElementById('transfer-disabled-action'),
            };

            const statusClassMap = {
                draft: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                approved: 'bg-blue-100 text-blue-700',
                in_transit: 'bg-blue-100 text-blue-700',
                pending_receive: 'bg-amber-100 text-amber-700',
                received: 'bg-emerald-100 text-emerald-700',
                closed: 'bg-slate-900 text-white',
                cancelled: 'bg-rose-100 text-rose-700',
            };

            const loadDetail = async () => {
                const response = await fetch(`/api/v1/stock-transfers/${encodeURIComponent(transferId)}`, { headers });
                if (!response.ok) {
                    throw new Error('Gagal memuat detail mutasi.');
                }

                return response.json();
            };

            const renderItems = (items) => {
                el.itemCount.textContent = `${items.length} item`;
                el.itemList.innerHTML = '';

                if (!items.length) {
                    el.itemList.innerHTML = '<div class="text-sm text-slate-400">Belum ada item transfer.</div>';
                    return;
                }

                items.forEach((item) => {
                    const row = document.createElement('div');
                    row.className = 'rounded-xl border border-slate-200 p-3 dark:border-slate-700';
                    row.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-slate-100">${item.product_name}</p>
                                <p class="text-xs text-slate-500">SKU: ${item.product_sku || '-'}</p>
                            </div>
                            <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary">${Number(item.qty || 0)} pcs</span>
                        </div>
                    `;
                    el.itemList.appendChild(row);
                });
            };

            const setActionState = (status) => {
                if (status === 'pending_receive' || status === 'in_transit') {
                    el.receive.classList.remove('hidden');
                    el.disabled.classList.add('hidden');
                    return;
                }

                el.receive.classList.add('hidden');
                el.disabled.classList.remove('hidden');
            };

            el.receive.addEventListener('click', async () => {
                const response = await fetch(`/api/v1/stock-transfers/${encodeURIComponent(transferId)}/receive`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        ...headers,
                    },
                    body: JSON.stringify({}),
                });

                if (!response.ok) {
                    alert('Gagal menerima mutasi stok.');
                    return;
                }

                window.location.reload();
            });

            (async () => {
                try {
                    const data = await loadDetail();
                    const status = String(data.transfer?.status || 'draft').toLowerCase();
                    el.status.className = `rounded-full px-3 py-1 text-[10px] font-bold uppercase ${statusClassMap[status] || statusClassMap.draft}`;
                    el.status.textContent = status.replaceAll('_', ' ');
                    el.reference.textContent = data.transfer?.reference || `TRF-${String(data.transfer?.id || '').slice(0, 6).toUpperCase()}`;
                    el.fromBranch.textContent = data.from_branch?.name || '-';
                    el.toBranch.textContent = data.to_branch?.name || '-';
                    renderItems(data.items || []);
                    setActionState(status);
                } catch (error) {
                    el.itemList.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
                    el.receive.classList.add('hidden');
                    el.disabled.classList.remove('hidden');
                }
            })();
        })();
    </script>
@endsection
