@extends('layouts.app')

@php
    $marketingReferralTenant = \App\Support\TenantContext::get();
    $marketingReferralUser = auth()->user();
    $marketingReferralRole = null;

    if (! $marketingReferralTenant && filled($marketingReferralUser?->tenant_id)) {
        $marketingReferralTenant = \App\Models\Tenant::query()->find($marketingReferralUser->tenant_id);
    }

    if ($marketingReferralTenant && $marketingReferralUser) {
        $marketingReferralRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $marketingReferralTenant->id, (string) $marketingReferralUser->id);

        if (! $marketingReferralRole && filled($marketingReferralUser->role ?? null)) {
            $marketingReferralRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $marketingReferralUser->role);
        }
    }

    abort_unless(in_array($marketingReferralRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManageReferral = in_array($marketingReferralRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Program Referral - POS SaaS')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('main_class', 'flex-1 overflow-y-auto lg:pl-72')

@section('header')
    <x-page-header
        title="Program Referral"
        :breadcrumbs="['Marketing']"
        backUrl="/backoffice/dashboard"
        :showMenuButton="true"
    />
@endsection

@section('content')
    <div class="w-full px-4 py-4 lg:px-8 space-y-6">
        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Kode Aktif</p>
                <p id="ref-kpi-codes" class="mt-2 text-3xl font-bold text-primary">42</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Referral</p>
                <p id="ref-kpi-members" class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">1,540</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Reward Terbayar</p>
                <p id="ref-kpi-reward" class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">Rp 8.450.000</p>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-4 xl:col-span-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Kode Referral</h3>
                    <div class="flex items-center gap-2">
                        <div class="relative hidden md:block">
                            <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-400">search</span>
                            <input type="text" class="w-64 rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs dark:border-slate-700 dark:bg-slate-800" placeholder="Cari kode..." />
                        </div>
                        @if ($canManageReferral)
                            <button class="rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white">Buat Kode Baru</button>
                        @endif
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 dark:bg-slate-800/40">
                                <tr>
                                    <th class="px-5 py-3">Kode</th>
                                    <th class="px-5 py-3">Pengguna</th>
                                    <th class="px-5 py-3">Total Reward</th>
                                    <th class="px-5 py-3">Dibuat</th>
                                    <th class="px-5 py-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody id="ref-code-body" class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr>
                                    <td class="px-5 py-4 text-sm text-slate-500" colspan="5">Memuat data referral...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <aside class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Riwayat Reward</h3>
                </div>
                <div id="ref-reward-history" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div class="px-5 py-4 text-xs text-slate-500">Memuat riwayat reward...</div>
                </div>
                <div class="border-t border-slate-100 px-5 py-3 text-center dark:border-slate-800">
                    <button class="text-[11px] font-bold uppercase tracking-wider text-slate-500 hover:text-primary">Lihat Semua Riwayat</button>
                </div>
            </aside>
        </section>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

            const el = {
                codes: document.getElementById('ref-kpi-codes'),
                members: document.getElementById('ref-kpi-members'),
                reward: document.getElementById('ref-kpi-reward'),
                codeBody: document.getElementById('ref-code-body'),
                rewardHistory: document.getElementById('ref-reward-history'),
            };

            const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Number(value || 0));

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

            const fallbackCodes = [
                { code: 'MERDEKA10', users: 124, reward: 1240000, created_at: '12 Agt 2024', status: 'aktif' },
                { code: 'PROMOHEBAT', users: 89, reward: 890000, created_at: '05 Agt 2024', status: 'aktif' },
                { code: 'EXPIRED50', users: 210, reward: 2100000, created_at: '20 Jan 2024', status: 'nonaktif' },
            ];

            const renderCodes = (rows) => {
                if (!rows.length) {
                    el.codeBody.innerHTML = '<tr><td class="px-5 py-4 text-sm text-slate-500" colspan="5">Belum ada data referral.</td></tr>';
                    return;
                }

                el.codeBody.innerHTML = rows.map((row) => {
                    const isActive = String(row.status || '').toLowerCase() === 'aktif';
                    const badgeClass = isActive
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                        : 'bg-slate-100 text-slate-500 dark:bg-slate-800';

                    return `
                        <tr>
                            <td class="px-5 py-4 font-semibold text-primary">${escapeHtml(row.code)}</td>
                            <td class="px-5 py-4">${Number(row.users || 0)} User</td>
                            <td class="px-5 py-4">${formatCurrency(row.reward || 0)}</td>
                            <td class="px-5 py-4 text-xs text-slate-500">${escapeHtml(row.created_at || '-')}</td>
                            <td class="px-5 py-4 text-center"><span class="rounded-full px-2 py-1 text-[10px] font-bold ${badgeClass}">${isActive ? 'Aktif' : 'Nonaktif'}</span></td>
                        </tr>
                    `;
                }).join('');
            };

            const renderHistory = (rows) => {
                if (!rows.length) {
                    el.rewardHistory.innerHTML = '<div class="px-5 py-4 text-xs text-slate-500">Belum ada riwayat reward.</div>';
                    return;
                }

                el.rewardHistory.innerHTML = rows.map((row) => `
                    <div class="px-5 py-4">
                        <p class="text-xs font-bold">${escapeHtml(row.name)} <span class="float-right text-emerald-600">+ ${formatCurrency(row.reward)}</span></p>
                        <p class="mt-1 text-[11px] text-slate-500">Kode ${escapeHtml(row.code)}</p>
                        <p class="mt-1 text-[10px] text-slate-400">${escapeHtml(row.time || '-')} · ${escapeHtml(row.type || 'Saldo')}</p>
                    </div>
                `).join('');
            };

            const hydrateFallback = () => {
                el.codes.textContent = '3';
                el.members.textContent = '423';
                el.reward.textContent = formatCurrency(4230000);
                renderCodes(fallbackCodes);
                renderHistory([
                    { name: 'Andi Wijaya', reward: 15000, code: 'MERDEKA10', time: 'Tadi, 14:20', type: 'Saldo Akun' },
                    { name: 'Budi Santoso', reward: 10000, code: 'PROMOHEBAT', time: '1 jam lalu', type: 'Kupon' },
                    { name: 'Siska Amelia', reward: 15000, code: 'MERDEKA10', time: '3 jam lalu', type: 'Saldo Akun' },
                ]);
            };

            const load = async () => {
                const response = await fetch('/api/v1/loyalty', { headers });
                if (!response.ok) {
                    hydrateFallback();
                    return;
                }

                const payload = await response.json().catch(() => ({}));
                const data = window.PosUi.unwrapApiPayload(payload) || {};
                const members = Array.isArray(data.members) ? data.members : [];
                const referralCodes = Array.isArray(data.referral_codes) && data.referral_codes.length
                    ? data.referral_codes
                    : members.slice(0, 8).map((member, index) => ({
                        code: `REF${String(index + 1).padStart(3, '0')}`,
                        users: member.transactions || 0,
                        reward: (member.points || 0) * 100,
                        created_at: '-',
                        status: 'aktif',
                    }));

                const activeCodeCount = referralCodes.filter((code) => String(code.status || 'aktif').toLowerCase() !== 'nonaktif').length;
                const totalReward = data.total_reward_paid
                    ?? referralCodes.reduce((sum, code) => sum + Number(code.reward || 0), 0);

                el.codes.textContent = `${activeCodeCount}`;
                el.members.textContent = new Intl.NumberFormat('id-ID').format(members.length);
                el.reward.textContent = formatCurrency(totalReward);

                renderCodes(referralCodes.slice(0, 8));
                const historyRows = Array.isArray(data.reward_history) && data.reward_history.length
                    ? data.reward_history
                    : members.slice(0, 3).map((member, index) => ({
                        name: member.name || `Member ${index + 1}`,
                        reward: (member.points || 0) * 100,
                        code: referralCodes[index]?.code || 'REF001',
                        time: 'Baru saja',
                        type: 'Saldo Akun',
                    }));
                renderHistory(historyRows.slice(0, 6));
            };

            load().catch(() => hydrateFallback());
        })();
    </script>
@endsection
