@extends('layouts.app')

@php
    $taxPromoTenant = \App\Support\TenantContext::get();
    $taxPromoUser = auth()->user();
    $taxPromoRole = null;

    if (! $taxPromoTenant && filled($taxPromoUser?->tenant_id)) {
        $taxPromoTenant = \App\Models\Tenant::query()->find($taxPromoUser->tenant_id);
    }

    if ($taxPromoTenant && $taxPromoUser) {
        $taxPromoRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $taxPromoTenant->id, (string) $taxPromoUser->id);

        if (! $taxPromoRole && filled($taxPromoUser->role ?? null)) {
            $taxPromoRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $taxPromoUser->role);
        }
    }

    abort_unless(in_array($taxPromoRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManageTaxPromo = in_array($taxPromoRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Pengaturan Pajak dan Promo')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display min-h-screen')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0 app-bottom-safe-space')

@push('head')
        <style>
            .toggle-checkbox:checked {
                right: 0;
                border-color: rgb(var(--primary-500));
            }
            .toggle-checkbox:checked + .toggle-label {
                background-color: rgb(var(--primary-500));
            }
        </style>
@endpush

@section('header')
        <x-page-header title="Pajak dan Promo" backUrl="/backoffice/dashboard">
            <x-slot name="actions">
                <button class="flex items-center justify-center size-10 rounded-full bg-slate-50 dark:bg-slate-800">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">help_outline</span>
                </button>
            </x-slot>
        </x-page-header>
@endsection

@section('content')
        <div class="flex-1 overflow-y-auto pb-32 app-bottom-safe-space">
            <section class="p-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Pengaturan Pajak</h2>
                    @if ($canManageTaxPromo)
                        <button class="text-primary text-sm font-bold flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">add_circle</span>
                            Tambah
                        </button>
                    @endif
                </div>
                <div class="space-y-3" id="tax-list"></div>
            </section>
            <div class="h-2 bg-slate-50 dark:bg-slate-900"></div>
            <section class="p-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Manajemen Promo</h2>
                    @if ($canManageTaxPromo)
                        <button class="bg-primary/10 text-primary px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">add</span>
                            Buat Promo
                        </button>
                    @endif
                </div>
                <div class="space-y-4" id="promo-list"></div>
            </section>
            <div class="h-8"></div>
        </div>
        @if ($canManageTaxPromo)
            <div class="fixed bottom-[88px] left-1/2 -translate-x-1/2 w-full max-w-[430px] px-6 z-40 app-bottom-safe-space">
                <button class="w-full bg-primary text-white py-4 rounded-2xl font-bold flex items-center justify-center gap-2 shadow-xl shadow-primary/30">
                    <span class="material-symbols-outlined">add_task</span>
                    Tambah Pajak Baru
                </button>
            </div>
        @endif

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    taxes: document.getElementById('tax-list'),
                    promos: document.getElementById('promo-list'),
                };

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const formatPromoValue = (promo) => {
                    if (promo.type === 'percent') {
                        return `Diskon ${promo.value}%`;
                    }
                    return `Potongan ${formatCurrency(promo.value)}`;
                };

                const renderTaxes = (taxes) => {
                    el.taxes.innerHTML = '';
                    if (!taxes.length) {
                        el.taxes.innerHTML = '<div class="text-sm text-slate-400">Belum ada pajak.</div>';
                        return;
                    }

                    taxes.forEach((tax) => {
                        const row = document.createElement('div');
                        row.className = `bg-slate-50 dark:bg-slate-900/50 p-4 rounded-2xl border border-slate-100 dark:border-slate-800 flex items-center justify-between${tax.is_active ? '' : ' opacity-70'}`;
                        row.innerHTML = `
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">percent</span>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-900 dark:text-slate-100 text-sm">${tax.name} (${tax.rate}%)</p>
                                    <p class="text-[11px] text-slate-500">${tax.description || 'Pajak'}</p>
                                </div>
                            </div>
                            @if ($canManageTaxPromo)
                                <div class="relative inline-block w-12 align-middle select-none transition duration-200 ease-in">
                                    <input class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-2 border-slate-200 appearance-none cursor-pointer right-6 checked:right-0 checked:border-primary transition-all duration-300" id="tax-${tax.id}" type="checkbox" ${tax.is_active ? 'checked' : ''} data-id="${tax.id}" />
                                    <label class="toggle-label block overflow-hidden h-6 rounded-full bg-slate-200 dark:bg-slate-700 cursor-pointer transition-colors duration-300" for="tax-${tax.id}"></label>
                                </div>
                            @else
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${tax.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'} uppercase">${tax.is_active ? 'Aktif' : 'Nonaktif'}</span>
                            @endif
                        `;
                        el.taxes.appendChild(row);
                    });
                };

                const renderPromos = (promos) => {
                    el.promos.innerHTML = '';
                    if (!promos.length) {
                        el.promos.innerHTML = '<div class="text-sm text-slate-400">Belum ada promo.</div>';
                        return;
                    }

                    promos.forEach((promo) => {
                        const isExpired = promo.status === 'expired';
                        const borderClass = promo.type === 'percent' ? 'bg-emerald-500' : 'bg-orange-500';
                        const statusLabel = isExpired ? 'Kedaluwarsa' : 'Aktif';
                        const statusClass = isExpired ? 'bg-slate-200 text-slate-600' : 'bg-emerald-100 text-emerald-700';
                        const valueBadge = promo.type === 'percent'
                            ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400'
                            : 'bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400';
                        const wrapClass = isExpired ? 'bg-slate-50 dark:bg-slate-900/30 grayscale opacity-60' : 'bg-white dark:bg-slate-900';

                        const row = document.createElement('div');
                        row.className = `relative overflow-hidden ${wrapClass} rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm`;
                        row.innerHTML = `
                            <div class="absolute top-0 left-0 w-1.5 h-full ${isExpired ? 'bg-slate-400' : borderClass}"></div>
                            <div class="p-4 pl-6">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h3 class="font-bold text-slate-900 dark:text-slate-100">${promo.name}</h3>
                                        <p class="text-xs text-slate-500">Berlaku s/d ${promo.ends_at || '-'}</p>
                                    </div>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${statusClass} uppercase">${statusLabel}</span>
                                </div>
                                <div class="flex items-center gap-4 mt-3 pt-3 border-t border-slate-50 dark:border-slate-800">
                                    <div class="${valueBadge} px-3 py-1.5 rounded-lg">
                                        <span class="text-xs font-bold">${formatPromoValue(promo)}</span>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-[10px] text-slate-400 uppercase font-bold tracking-tight">${promo.code ? 'Kode Promo' : 'Kategori'}</p>
                                        <p class="text-xs font-bold">${promo.code || promo.category || '-'}</p>
                                    </div>
                                    @if ($canManageTaxPromo)
                                        <button class="material-symbols-outlined text-slate-400">more_vert</button>
                                    @endif
                                </div>
                            </div>
                        `;
                        el.promos.appendChild(row);
                    });
                };

                @if ($canManageTaxPromo)
                el.taxes.addEventListener('change', async (event) => {
                    const checkbox = event.target.closest('.toggle-checkbox');
                    if (!checkbox) {
                        return;
                    }
                    const taxId = checkbox.dataset.id;
                    const response = await fetch(`/api/v1/taxes/${taxId}`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            ...headers,
                        },
                        body: JSON.stringify({ is_active: checkbox.checked }),
                    });
                    if (!response.ok) {
                        alert('Gagal memperbarui pajak.');
                        checkbox.checked = !checkbox.checked;
                    }
                });
                @endif

                (async () => {
                    const response = await fetch('/api/v1/settings/tax-promos', { headers });
                    if (!response.ok) {
                        el.taxes.innerHTML = '<div class="text-sm text-red-500">Gagal memuat pajak.</div>';
                        el.promos.innerHTML = '<div class="text-sm text-red-500">Gagal memuat promo.</div>';
                        return;
                    }
                    const data = await response.json();
                    renderTaxes(data.taxes || []);
                    renderPromos(data.promos || []);
                })();
            })();
        </script>
@endsection
