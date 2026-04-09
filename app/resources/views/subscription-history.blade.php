@extends('layouts.app')

@php
    $subscriptionHistoryTenant = \App\Support\TenantContext::get();
    $subscriptionHistoryUser = auth()->user();
    $subscriptionHistoryRole = null;

    if (! $subscriptionHistoryTenant && filled($subscriptionHistoryUser?->tenant_id)) {
        $subscriptionHistoryTenant = \App\Models\Tenant::query()->find($subscriptionHistoryUser->tenant_id);
    }

    if ($subscriptionHistoryTenant && $subscriptionHistoryUser) {
        $subscriptionHistoryRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $subscriptionHistoryTenant->id, (string) $subscriptionHistoryUser->id);

        if (! $subscriptionHistoryRole && filled($subscriptionHistoryUser->role ?? null)) {
            $subscriptionHistoryRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $subscriptionHistoryUser->role);
        }
    }

    abort_unless(in_array($subscriptionHistoryRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);
@endphp

@section('page_title', 'Riwayat Langganan')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display min-h-screen')
@section('app_shell_class', 'relative flex h-screen w-full flex-col overflow-hidden bg-white dark:bg-background-dark')
@section('main_class', 'flex-1 overflow-y-auto p-0 lg:pl-72')

@section('header')
    <x-page-header title="Riwayat Langganan" backUrl="/backoffice/settings/subscription" />
@endsection

@section('content')
    <div class="mx-auto w-full max-w-5xl p-4 space-y-4 lg:px-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Audit Trail</p>
            <p id="meta" class="mt-1 text-xs text-slate-500">-</p>
        </section>

        <div id="list" class="space-y-2"></div>

        <div class="grid grid-cols-2 gap-2">
            <button id="btn-prev" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold dark:border-slate-700">Prev</button>
            <button id="btn-next" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold dark:border-slate-700">Next</button>
        </div>
        <p id="error-box" class="hidden rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"></p>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = {
                'Content-Type': 'application/json',
                ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
            };

            const state = {
                page: 1,
                lastPage: 1,
            };

            const el = {
                list: document.getElementById('list'),
                meta: document.getElementById('meta'),
                prev: document.getElementById('btn-prev'),
                next: document.getElementById('btn-next'),
                error: document.getElementById('error-box'),
            };

            const showError = (message) => {
                el.error.classList.remove('hidden');
                el.error.textContent = message;
            };

            const render = (rows) => {
                if (!rows.length) {
                    el.list.innerHTML = '<p class="rounded-xl border border-dashed border-slate-300 p-3 text-sm text-slate-400 dark:border-slate-700">Belum ada riwayat perubahan plan.</p>';
                    return;
                }

                el.list.innerHTML = rows.map((row) => `
                    <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start gap-2">
                            <span class="mt-0.5 rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-bold text-teal-700 dark:bg-teal-900/30 dark:text-teal-300">${row.change_mode || 'auto'}</span>
                            <p class="text-sm font-bold">${row.old_plan} -> ${row.new_plan}</p>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">${row.actor_name || row.actor_id || 'Sistem'} | ${row.created_at}</p>
                        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">${row.reason || '-'}</p>
                    </div>
                `).join('');
            };

            const load = async () => {
                const response = await fetch(`/api/v1/subscription/audit-logs?page=${state.page}&per_page=10`, { headers });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(payload.message || 'Gagal memuat riwayat.');
                }

                const meta = window.PosUi.unwrapApiMeta(payload);
                const data = window.PosUi.unwrapApiPayload(payload) || {};
                const rows = data.logs || data.audit_logs || data.items || [];

                state.lastPage = meta.last_page || 1;
                el.meta.textContent = `Page ${meta.page || 1} dari ${state.lastPage} | total ${meta.total || 0}`;
                el.prev.disabled = state.page <= 1;
                el.next.disabled = state.page >= state.lastPage;
                render(rows);
            };

            el.prev.addEventListener('click', () => {
                if (state.page <= 1) return;
                state.page -= 1;
                el.error.classList.add('hidden');
                load().catch((error) => showError(error.message));
            });

            el.next.addEventListener('click', () => {
                if (state.page >= state.lastPage) return;
                state.page += 1;
                el.error.classList.add('hidden');
                load().catch((error) => showError(error.message));
            });

            load().catch((error) => showError(error.message));
        })();
    </script>
@endsection
