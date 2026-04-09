@extends('layouts.app')

@php
    $loyaltyTenant = \App\Support\TenantContext::get();
    $loyaltyUser = auth()->user();
    $loyaltyRole = null;

    if (! $loyaltyTenant && filled($loyaltyUser?->tenant_id)) {
        $loyaltyTenant = \App\Models\Tenant::query()->find($loyaltyUser->tenant_id);
    }

    if ($loyaltyTenant && $loyaltyUser) {
        $loyaltyRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $loyaltyTenant->id, (string) $loyaltyUser->id);

        if (! $loyaltyRole && filled($loyaltyUser->role ?? null)) {
            $loyaltyRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $loyaltyUser->role);
        }
    }

    abort_unless(in_array($loyaltyRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManageLoyalty = in_array($loyaltyRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Loyalitas dan Member Tiers')
@section('body_class', 'bg-background-light dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-slate-950 shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
        <style>
            .tier-card-active {
                outline: 2px solid #135bec;
                outline-offset: 2px;
            }
            .no-scrollbar::-webkit-scrollbar {
                display: none;
            }
            .no-scrollbar {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
        </style>
@endpush

@section('header')
        <x-page-header title="Loyalitas Pelanggan" backUrl="/backoffice/dashboard" :showMenuButton="true" />
@endsection

@section('content')
        <div class="flex-1 overflow-y-auto pb-32">
            <section class="p-4">
                <div class="flex items-center justify-between mb-3 px-1">
                    <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wider">Level Keanggotaan</h2>
                    @if ($canManageLoyalty)
                        <button class="text-xs font-semibold text-primary">Kelola Tier</button>
                    @endif
                </div>
                <div class="flex gap-3 overflow-x-auto pb-2 no-scrollbar" id="tier-list"></div>
            </section>
            <div class="px-4 mb-4">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                    <input id="search-input" class="w-full pl-10 pr-4 py-3 rounded-xl border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-sm focus:ring-primary focus:border-primary" placeholder="Cari nama member atau ID..." type="text" />
                </div>
            </div>
            <div class="px-4 space-y-3">
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider">Daftar Member</h3>
                    <span class="material-symbols-outlined text-slate-400 text-xl">filter_list</span>
                </div>
                <div id="member-list" class="space-y-3"></div>
            </div>
        </div>
        @if ($canManageLoyalty)
            <div class="fixed bottom-24 right-6 left-6 max-w-[382px] mx-auto z-40">
                <button class="w-full bg-primary text-white py-4 rounded-2xl font-bold flex items-center justify-center gap-2 shadow-xl shadow-primary/30">
                    <span class="material-symbols-outlined">person_add</span>
                    Tambah Member Baru
                </button>
            </div>
        @endif

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    tiers: document.getElementById('tier-list'),
                    members: document.getElementById('member-list'),
                    search: document.getElementById('search-input'),
                };

                let members = [];

                const tierColors = {
                    Silver: 'text-silver',
                    Gold: 'text-gold',
                    Platinum: 'text-platinum',
                };

                const renderTiers = (tiers) => {
                    el.tiers.innerHTML = '';
                    tiers.forEach((tier, index) => {
                        const row = document.createElement('div');
                        row.className = `min-w-[140px] flex-1 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm${index === 1 ? ' tier-card-active' : ''}`;
                        row.innerHTML = `
                            <div class="size-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-3">
                                <span class="material-symbols-outlined ${tierColors[tier.name] || 'text-slate-400'}">workspace_premium</span>
                            </div>
                            <p class="text-xs font-bold uppercase mb-0.5 ${tierColors[tier.name] || 'text-slate-500'}">${tier.name}</p>
                            <p class="text-[10px] text-slate-500">Min. ${tier.min_points} Poin</p>
                        `;
                        el.tiers.appendChild(row);
                    });
                };

                const renderMembers = (rows) => {
                    el.members.innerHTML = '';
                    if (!rows.length) {
                        el.members.innerHTML = '<div class="text-sm text-slate-400">Belum ada member.</div>';
                        return;
                    }

                    rows.forEach((member) => {
                        const initials = member.name.split(' ').map((part) => part[0]).slice(0, 2).join('');
                        const tierColor = tierColors[member.tier] || 'text-slate-500';
                        const badgeClass = member.tier === 'Gold'
                            ? 'bg-yellow-100 text-yellow-700'
                            : member.tier === 'Platinum'
                                ? 'bg-purple-100 text-purple-700'
                                : 'bg-slate-100 text-slate-500';

                        const row = document.createElement('div');
                        row.className = 'bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 flex items-center gap-4 active:scale-[0.98] transition-transform';
                        row.innerHTML = `
                            <div class="size-12 rounded-full bg-primary/10 flex items-center justify-center ${tierColor} font-bold text-lg">
                                ${initials}
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-bold text-slate-900 dark:text-slate-100">${member.name}</h4>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${badgeClass} uppercase">${member.tier}</span>
                                </div>
                                <div class="flex items-center gap-1 mt-0.5 text-xs text-slate-500">
                                    <span class="material-symbols-outlined text-xs">database</span>
                                    <span>${member.points} Poin</span>
                                    <span class="mx-1">-</span>
                                    <span>${member.transactions} Transaksi</span>
                                </div>
                            </div>
                            <span class="material-symbols-outlined text-slate-300">chevron_right</span>
                        `;
                        el.members.appendChild(row);
                    });
                };

                const applyFilter = () => {
                    const term = el.search.value.toLowerCase();
                    if (!term) {
                        renderMembers(members);
                        return;
                    }
                    const filtered = members.filter((member) => member.name.toLowerCase().includes(term));
                    renderMembers(filtered);
                };

                el.search.addEventListener('input', applyFilter);

                (async () => {
                    const response = await fetch('/api/v1/loyalty', { headers });
                    if (!response.ok) {
                        el.members.innerHTML = '<div class="text-sm text-red-500">Gagal memuat data loyalty.</div>';
                        return;
                    }
                    const data = await response.json();
                    members = data.members || [];
                    renderTiers(data.tiers || []);
                    renderMembers(members);
                })();
            })();
        </script>
@endsection
