@extends('layouts.app')

@php
    $subscriptionCurrentTenant = \App\Support\TenantContext::get();
    $subscriptionCurrentUser = auth()->user();
    $subscriptionCurrentRole = null;

    if (! $subscriptionCurrentTenant && filled($subscriptionCurrentUser?->tenant_id)) {
        $subscriptionCurrentTenant = \App\Models\Tenant::query()->find($subscriptionCurrentUser->tenant_id);
    }

    if ($subscriptionCurrentTenant && $subscriptionCurrentUser) {
        $subscriptionCurrentRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $subscriptionCurrentTenant->id, (string) $subscriptionCurrentUser->id);

        if (! $subscriptionCurrentRole && filled($subscriptionCurrentUser->role ?? null)) {
            $subscriptionCurrentRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $subscriptionCurrentUser->role);
        }
    }

    abort_unless(in_array($subscriptionCurrentRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManageSubscription = in_array($subscriptionCurrentRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Langganan & Billing')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display min-h-screen')
@section('app_shell_class', 'relative flex h-screen w-full flex-col overflow-hidden bg-white dark:bg-background-dark')
@section('main_class', 'flex-1 overflow-y-auto p-0 lg:pl-72')

@section('header')
    <x-page-header title="Langganan & Billing" backUrl="/backoffice/dashboard" />
@endsection

@section('content')
    <div class="mx-auto w-full max-w-5xl p-4 space-y-4 lg:px-6">
        <section class="relative overflow-hidden rounded-2xl border border-teal-200/80 bg-gradient-to-br from-teal-50 via-white to-cyan-50 p-4 shadow-sm dark:border-teal-900/60 dark:from-slate-900 dark:via-slate-900 dark:to-slate-800">
            <div class="absolute -right-6 -top-6 h-20 w-20 rounded-full bg-teal-300/20 blur-xl"></div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-teal-700 dark:text-teal-400">Langganan Aktif</p>
            <p id="plan-title" class="mt-2 text-2xl font-bold leading-tight text-slate-900 dark:text-white">-</p>
            <p id="plan-meta" class="mt-1 text-xs text-slate-600 dark:text-slate-300">Memuat data...</p>
            <div id="pending-box" class="mt-3 hidden rounded-xl border border-amber-200 bg-amber-50/90 p-3 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                <p id="pending-title" class="font-semibold">Perubahan plan terjadwal.</p>
                <p id="pending-meta" class="mt-1">-</p>
            </div>
        </section>

        <section class="grid grid-cols-3 gap-2 text-xs">
            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-slate-500">Allowed</p>
                <p id="allowed-count" class="mt-1 text-lg font-bold text-emerald-600 dark:text-emerald-400">-</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-slate-500">Blocked</p>
                <p id="blocked-count" class="mt-1 text-lg font-bold text-rose-600 dark:text-rose-400">-</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-slate-500">Impact</p>
                <p id="impacted-count" class="mt-1 text-lg font-bold text-indigo-600 dark:text-indigo-400">-</p>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
            <p class="mb-2 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Aksi Cepat</p>
            <div class="space-y-2">
                @if ($canManageSubscription)
                    <a href="/backoffice/settings/subscription/change" class="block rounded-xl bg-primary px-3 py-2.5 text-center text-sm font-bold text-white">Ubah Paket</a>
                @endif
                <a href="/backoffice/settings/subscription/features" class="block rounded-xl border border-slate-200 px-3 py-2.5 text-center text-sm font-semibold dark:border-slate-700">Lihat Entitlement</a>
                <a href="/backoffice/settings/subscription/history" class="block rounded-xl border border-slate-200 px-3 py-2.5 text-center text-sm font-semibold dark:border-slate-700">Riwayat Perubahan</a>
                <a href="/backoffice/settings/subscription/invoices" class="block rounded-xl border border-slate-200 px-3 py-2.5 text-center text-sm font-semibold dark:border-slate-700">Invoice & Pembayaran</a>
            </div>
        </section>

        <p id="error-box" class="hidden rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"></p>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = {
                'Content-Type': 'application/json',
                ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
            };

            const el = {
                planTitle: document.getElementById('plan-title'),
                planMeta: document.getElementById('plan-meta'),
                allowedCount: document.getElementById('allowed-count'),
                blockedCount: document.getElementById('blocked-count'),
                impactedCount: document.getElementById('impacted-count'),
                pendingBox: document.getElementById('pending-box'),
                pendingTitle: document.getElementById('pending-title'),
                pendingMeta: document.getElementById('pending-meta'),
                error: document.getElementById('error-box'),
            };

            const formatDateTime = (value) => {
                if (!value) {
                    return '-';
                }
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return value;
                }
                return new Intl.DateTimeFormat('id-ID', {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                }).format(date);
            };

            const showError = (message) => {
                el.error.classList.remove('hidden');
                el.error.textContent = message;
            };

            const load = async () => {
                const response = await fetch('/api/v1/subscription/current', { headers });
                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    throw new Error(payload.message || 'Gagal memuat langganan.');
                }

                const payload = await response.json().catch(() => ({}));
                const data = window.PosUi.unwrapApiPayload(payload) || {};
                const planLabel = data.plan_label || data.plan;
                el.planTitle.textContent = `Plan ${data.plan} - ${planLabel}`;
                el.planMeta.textContent = `Status ${data.status || '-'} | Mulai ${data.started_at || '-'}`;
                el.allowedCount.textContent = `${data.allowed_count ?? 0}`;
                el.blockedCount.textContent = `${data.blocked_count ?? 0}`;
                el.impactedCount.textContent = `${data.impacted_menu_count ?? 0}`;

                const hasPendingPlan = Boolean(data.pending_plan);
                el.pendingBox.classList.toggle('hidden', !hasPendingPlan);
                if (hasPendingPlan) {
                    const pendingLabel = (data.plan_labels || {})[data.pending_plan] || data.pending_plan;
                    const mode = data.pending_change_mode || 'end_of_period';
                    const effectiveAt = formatDateTime(data.pending_change_effective_at);
                    el.pendingTitle.textContent = `Plan berikutnya: ${data.pending_plan} (${pendingLabel})`;
                    el.pendingMeta.textContent = `Mode: ${mode} | Efektif: ${effectiveAt}`;
                }

                localStorage.setItem('subscription:plan-sync', JSON.stringify({
                    plan: data.plan,
                    plan_label: data.plan_label,
                    status: data.status,
                    pending_plan: data.pending_plan || null,
                    pending_change_mode: data.pending_change_mode || null,
                    pending_change_effective_at: data.pending_change_effective_at || null,
                    updated_at: Date.now(),
                }));
            };

            window.addEventListener('storage', (event) => {
                if (event.key === 'subscription:plan-sync') {
                    load().catch((error) => showError(error.message));
                }
            });

            load().catch((error) => showError(error.message));
        })();
    </script>
@endsection
