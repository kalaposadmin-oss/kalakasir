@extends('layouts.app')

@php
    $subscriptionFeaturesTenant = \App\Support\TenantContext::get();
    $subscriptionFeaturesUser = auth()->user();
    $subscriptionFeaturesRole = null;

    if (! $subscriptionFeaturesTenant && filled($subscriptionFeaturesUser?->tenant_id)) {
        $subscriptionFeaturesTenant = \App\Models\Tenant::query()->find($subscriptionFeaturesUser->tenant_id);
    }

    if ($subscriptionFeaturesTenant && $subscriptionFeaturesUser) {
        $subscriptionFeaturesRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $subscriptionFeaturesTenant->id, (string) $subscriptionFeaturesUser->id);

        if (! $subscriptionFeaturesRole && filled($subscriptionFeaturesUser->role ?? null)) {
            $subscriptionFeaturesRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $subscriptionFeaturesUser->role);
        }
    }

    abort_unless(in_array($subscriptionFeaturesRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);
@endphp

@section('page_title', 'Entitlement Fitur')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display min-h-screen')
@section('app_shell_class', 'relative flex h-screen w-full flex-col overflow-hidden bg-white dark:bg-background-dark')
@section('main_class', 'flex-1 overflow-y-auto p-0 lg:pl-72')

@section('header')
    <x-page-header title="Entitlement" backUrl="/backoffice/settings/subscription" />
@endsection

@section('content')
    <div class="mx-auto w-full max-w-5xl p-4 space-y-3 lg:px-6">
        <section class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-3 space-y-2">
            <input id="search" type="text" placeholder="Cari key/module" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-sm" />
            <input id="module" type="text" placeholder="Filter module (contoh: settings)" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-sm" />
            <label class="flex items-center gap-2 text-sm">
                <input id="locked-only" type="checkbox" class="rounded border-slate-300" />
                Hanya yang terkunci
            </label>
            <button id="btn-apply" class="w-full rounded-lg bg-primary px-3 py-2 text-sm font-bold text-white">Terapkan Filter</button>
        </section>

        <div id="summary" class="text-xs text-slate-500">Memuat entitlement...</div>
        <div id="list" class="space-y-2"></div>
        <p id="error-box" class="hidden rounded-lg bg-red-50 p-3 text-sm text-red-700"></p>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = {
                'Content-Type': 'application/json',
                ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
            };

            const el = {
                search: document.getElementById('search'),
                module: document.getElementById('module'),
                lockedOnly: document.getElementById('locked-only'),
                apply: document.getElementById('btn-apply'),
                summary: document.getElementById('summary'),
                list: document.getElementById('list'),
                error: document.getElementById('error-box'),
            };

            const showError = (message) => {
                el.error.classList.remove('hidden');
                el.error.textContent = message;
            };

            const render = (rows) => {
                if (!rows.length) {
                    el.list.innerHTML = '<p class="text-sm text-slate-400">Tidak ada data entitlement.</p>';
                    return;
                }

                el.list.innerHTML = rows.map((row) => `
                    <div class="rounded-lg border border-slate-200 dark:border-slate-800 p-3 bg-white dark:bg-slate-900">
                        <p class="text-sm font-bold">${row.key}</p>
                        <p class="text-xs text-slate-500">${row.module} | ${row.ui_type || '-'}</p>
                        <p class="text-xs mt-1 ${row.allowed ? 'text-emerald-600' : 'text-red-600'}">${row.allowed ? 'Allowed' : 'Blocked'}</p>
                    </div>
                `).join('');
            };

            const load = async () => {
                const params = new URLSearchParams();
                if (el.search.value.trim()) params.set('search', el.search.value.trim());
                if (el.module.value.trim()) params.set('module', el.module.value.trim());
                if (el.lockedOnly.checked) params.set('lockedOnly', '1');

                const response = await fetch(`/api/v1/subscription/entitlements?${params.toString()}`, { headers });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(payload.message || 'Gagal memuat entitlement.');
                }

                const data = window.PosUi.unwrapApiPayload(payload) || {};
                el.summary.textContent = `Plan ${data.plan} | total ${data.count} | allowed ${data.allowed_count} | blocked ${data.blocked_count}`;
                render(data.entitlements || []);
            };

            el.apply.addEventListener('click', () => {
                el.error.classList.add('hidden');
                load().catch((error) => showError(error.message));
            });

            load().catch((error) => showError(error.message));
        })();
    </script>
@endsection
