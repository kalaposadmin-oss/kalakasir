@extends('layouts.app')

@php
    $stockAlertTenant = \App\Support\TenantContext::get();
    $stockAlertUser = auth()->user();
    $stockAlertRole = null;

    if (! $stockAlertTenant && filled($stockAlertUser?->tenant_id)) {
        $stockAlertTenant = \App\Models\Tenant::query()->find($stockAlertUser->tenant_id);
    }

    $stockAlertPlan = $stockAlertTenant
        ? app(\App\Services\SubscriptionService::class)->currentPlan($stockAlertTenant)
        : null;
    $canViewStockAlerts = \App\Support\FeatureGateConfig::isFeatureAllowed('reports.inventory_view', $stockAlertPlan);

    if ($stockAlertTenant && $stockAlertUser) {
        $stockAlertRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $stockAlertTenant->id, (string) $stockAlertUser->id);

        if (! $stockAlertRole && filled($stockAlertUser->role ?? null)) {
            $stockAlertRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $stockAlertUser->role);
        }
    }

    abort_unless($canViewStockAlerts, 403);
    abort_unless(in_array($stockAlertRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);
@endphp

@section('page_title', 'Alert Stok dan Reorder')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
        <style>
            .ios-blur {
                backdrop-filter: blur(20px);
                background-color: rgba(255, 255, 255, 0.8);
            }
        </style>
@endpush

@section('header')
        <x-page-header title="Alert Stok dan Reorder" backUrl="/backoffice/reports/inventory">
            <x-slot name="actions">
                <button class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-400 text-[20px]">filter_list</span>
                </button>
                <button class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-400 text-[20px]">history</span>
                </button>
            </x-slot>
        </x-page-header>
@endsection

@section('content')
        <div class="bg-amber-50 dark:bg-amber-900/20 px-4 py-2 flex items-center gap-2 border-b border-amber-100 dark:border-amber-900/30">
            <span class="material-symbols-outlined text-amber-600 dark:text-amber-500 text-sm">warning</span>
            <p class="text-[11px] font-medium text-amber-800 dark:text-amber-400" id="alert-summary">0 produk mencapai batas stok minimum hari ini.</p>
        </div>
        <div class="flex-1 overflow-y-auto pb-32">
            <div class="p-4">
                <button class="w-full bg-primary text-white py-4 rounded-2xl font-bold flex items-center justify-center gap-2 shadow-lg shadow-primary/20 active:scale-[0.98] transition-transform">
                    <span class="material-symbols-outlined">add_shopping_cart</span>
                    Buat PO Massal
                </button>
            </div>
            <div class="px-4 space-y-4">
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest">Daftar Reorder</h3>
                    <span class="text-[11px] text-slate-400" id="alert-count">Total: 0 Item</span>
                </div>
                <div id="alert-list" class="space-y-4"></div>
            </div>
        </div>
        <div id="selection-bar" class="fixed bottom-28 left-1/2 -translate-x-1/2 w-[calc(100%-48px)] max-w-[380px] bg-slate-900/90 text-white p-3 rounded-xl flex items-center justify-between shadow-2xl backdrop-blur-md hidden">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">info</span>
                <span class="text-xs" id="selection-count">0 produk terpilih untuk PO massal</span>
            </div>
            <button class="text-xs font-bold text-primary">Tinjau</button>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    summary: document.getElementById('alert-summary'),
                    count: document.getElementById('alert-count'),
                    list: document.getElementById('alert-list'),
                    selectionBar: document.getElementById('selection-bar'),
                    selectionCount: document.getElementById('selection-count'),
                };

                let alerts = [];

                const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(value || 0);

                const updateSelection = () => {
                    const selected = alerts.filter((item) => item.selected).length;
                    el.selectionCount.textContent = `${selected} produk terpilih untuk PO massal`;
                    el.selectionBar.classList.toggle('hidden', selected === 0);
                };

                const renderAlerts = () => {
                    el.list.innerHTML = '';
                    el.count.textContent = `Total: ${alerts.length} Item`;
                    el.summary.textContent = `${alerts.length} produk mencapai batas stok minimum hari ini.`;

                    if (!alerts.length) {
                        el.list.innerHTML = '<div class="text-sm text-slate-400">Belum ada alert stok.</div>';
                        el.selectionBar.classList.add('hidden');
                        return;
                    }

                    alerts.forEach((item, index) => {
                        const severity = item.stock_now <= item.min_stock * 0.5 ? 'danger' : 'warning';
                        const stockClass = severity === 'danger' ? 'text-danger' : 'text-warning';
                        const supplier = item.supplier_name || 'Pemasok';

                        const row = document.createElement('div');
                        row.className = 'bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm';
                        row.innerHTML = `
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex gap-3">
                                    <div class="size-12 bg-slate-100 dark:bg-slate-800 rounded-xl flex items-center justify-center border border-slate-100 dark:border-slate-700">
                                        <span class="material-symbols-outlined text-slate-400">image</span>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-slate-900 dark:text-slate-100 leading-tight">${item.name}</h4>
                                        <p class="text-[11px] text-slate-500 mt-0.5">SKU: ${item.sku} - ${supplier}</p>
                                    </div>
                                </div>
                                <input class="alert-check rounded text-primary focus:ring-primary size-5 mt-1" type="checkbox" ${item.selected ? 'checked' : ''} data-index="${index}" />
                            </div>
                            <div class="grid grid-cols-3 gap-2 bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                                <div class="text-center border-r border-slate-200 dark:border-slate-700">
                                    <p class="text-[10px] text-slate-500 uppercase font-medium">Stok Kini</p>
                                    <p class="text-sm font-bold ${stockClass}">${formatNumber(item.stock_now)} Unit</p>
                                </div>
                                <div class="text-center border-r border-slate-200 dark:border-slate-700">
                                    <p class="text-[10px] text-slate-500 uppercase font-medium">Minimum</p>
                                    <p class="text-sm font-bold text-slate-700 dark:text-slate-300">${formatNumber(item.min_stock)} Unit</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-[10px] text-primary uppercase font-bold">Saran Pesan</p>
                                    <p class="text-sm font-bold text-primary">${formatNumber(item.suggested_qty)} Unit</p>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between px-1">
                                <div class="flex items-center gap-1 text-[10px] text-slate-400">
                                    <span class="material-symbols-outlined text-[14px]">history</span>
                                    ${item.note}
                                </div>
                                <button class="text-[11px] font-bold text-primary flex items-center gap-1">
                                    Edit Detail <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                                </button>
                            </div>
                        `;
                        el.list.appendChild(row);
                    });

                    updateSelection();
                };

                el.list.addEventListener('change', (event) => {
                    const checkbox = event.target.closest('.alert-check');
                    if (!checkbox) {
                        return;
                    }
                    const index = Number(checkbox.dataset.index);
                    alerts[index].selected = checkbox.checked;
                    updateSelection();
                });

                (async () => {
                    const response = await fetch('/api/v1/inventory-alerts', { headers });
                    if (!response.ok) {
                        el.list.innerHTML = '<div class="text-sm text-red-500">Gagal memuat alert stok.</div>';
                        return;
                    }
                    const data = await response.json();
                    alerts = data.alerts || [];
                    renderAlerts();
                })();
            })();
        </script>
@endsection
