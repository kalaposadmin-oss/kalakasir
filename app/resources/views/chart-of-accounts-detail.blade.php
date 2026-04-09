@extends('layouts.app')

@php
    $coaDetailTenant = \App\Support\TenantContext::get();
    $coaDetailUser = auth()->user();
    $coaDetailRole = null;

    if (! $coaDetailTenant && filled($coaDetailUser?->tenant_id)) {
        $coaDetailTenant = \App\Models\Tenant::query()->find($coaDetailUser->tenant_id);
    }

    if ($coaDetailTenant && $coaDetailUser) {
        $coaDetailRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $coaDetailTenant->id, (string) $coaDetailUser->id);

        if (! $coaDetailRole && filled($coaDetailUser->role ?? null)) {
            $coaDetailRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $coaDetailUser->role);
        }
    }

    abort_unless(in_array($coaDetailRole, ['admin', 'owner', 'superadmin'], true), 403);
@endphp

@section('page_title', 'Detail Akun')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto p-0')

@section('header')
    <x-page-header title="Detail Akun" backUrl="/backoffice/settings/chart-of-accounts">
        <x-slot name="actions">
            <button class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300" type="button">
                <span class="material-symbols-outlined text-[20px]">more_vert</span>
            </button>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
    <div id="coa-detail" data-account-id="{{ $accountId }}" class="px-4 py-4">
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            <div class="space-y-3">
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Kode Akun</label>
                    <input id="detail-code" class="w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-sm read-only:bg-slate-100 read-only:text-slate-500 read-only:cursor-not-allowed" type="text" readonly />
                    <p class="mt-1 text-[11px] text-slate-500">Kode dibuat otomatis dan tidak bisa diubah.</p>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Nama Akun</label>
                    <input id="detail-name" class="w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-sm" type="text" />
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Kelompok (Opsional)</label>
                    <select id="detail-parent" class="w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-sm">
                        <option value="">Tanpa Kelompok</option>
                    </select>
                </div>
                <p id="detail-error" class="text-xs text-red-500 font-semibold hidden"></p>
                <button id="detail-save" class="w-full rounded-lg bg-primary text-white py-2.5 text-sm font-bold" type="button">Simpan Perubahan</button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
            const container = document.getElementById('coa-detail');
            const accountId = container?.dataset.accountId;

            const el = {
                code: document.getElementById('detail-code'),
                name: document.getElementById('detail-name'),
                parent: document.getElementById('detail-parent'),
                save: document.getElementById('detail-save'),
                error: document.getElementById('detail-error'),
            };

            if (!accountId) {
                el.error.textContent = 'Akun tidak ditemukan.';
                el.error.classList.remove('hidden');
                el.save.disabled = true;
                return;
            }

            const groupCodeFromAccount = (code) => {
                if (!code) {
                    return '0000';
                }
                if (code.length >= 4) {
                    return `${code.slice(0, 2)}00`;
                }
                return code;
            };

            const setError = (message) => {
                el.error.textContent = message;
                el.error.classList.remove('hidden');
            };

            const clearError = () => {
                el.error.textContent = '';
                el.error.classList.add('hidden');
            };

            const loadAccount = async () => {
                const response = await fetch(`/api/v1/chart-of-accounts/${accountId}`, { headers });
                if (!response.ok) {
                    throw new Error('Gagal memuat akun.');
                }
                return response.json();
            };

            const loadParents = async () => {
                const url = new URL('/api/v1/chart-of-accounts', window.location.origin);
                url.searchParams.set('type', 'asset');
                const response = await fetch(url, { headers });
                if (!response.ok) {
                    throw new Error('Gagal memuat kelompok akun.');
                }
                return response.json();
            };

            const renderParents = (accounts) => {
                el.parent.innerHTML = '<option value="">Tanpa Kelompok</option>';
                const parents = accounts
                    .filter((account) => account.id !== Number(accountId))
                    .filter((account) => !account.parent_id && account.code)
                    .filter((account) => groupCodeFromAccount(account.code) === '1100')
                    .sort((a, b) => String(a.code).localeCompare(String(b.code)));

                parents.forEach((account) => {
                    const option = document.createElement('option');
                    option.value = account.id;
                    option.textContent = `${account.code} - ${account.name}`;
                    el.parent.appendChild(option);
                });
            };

            const saveAccount = async () => {
                clearError();
                const code = el.code.value.trim();
                const name = el.name.value.trim();

                if (!code || !name) {
                    setError('Kode dan nama akun wajib diisi.');
                    return;
                }

                if (!/^11\d+$/.test(code)) {
                    setError('Kode harus diawali 11 dan hanya angka.');
                    return;
                }

                const response = await fetch(`/api/v1/chart-of-accounts/${accountId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        ...headers,
                    },
                    body: JSON.stringify({
                        code,
                        name,
                        parent_id: el.parent.value || null,
                    }),
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    setError(payload?.message || 'Gagal menyimpan perubahan.');
                    return;
                }

                window.location.href = '/backoffice/settings/chart-of-accounts';
            };

            const init = async () => {
                try {
                    const [accountPayload, parentPayload] = await Promise.all([
                        loadAccount(),
                        loadParents(),
                    ]);

                    const account = accountPayload.account;
                    el.code.value = account?.code || '';
                    el.name.value = account?.name || '';
                    renderParents(parentPayload.accounts || []);
                    el.parent.value = account?.parent_id || '';
                } catch (error) {
                    setError(error.message || 'Gagal memuat data akun.');
                    el.save.disabled = true;
                }
            };

            el.save.addEventListener('click', saveAccount);
            init();
        })();
    </script>
@endsection
