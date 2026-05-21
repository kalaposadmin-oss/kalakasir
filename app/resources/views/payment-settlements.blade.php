@extends('layouts.app')

@php
    $settlementTenant = \App\Support\TenantContext::get();
    $settlementUser = auth()->user();
    $settlementRole = null;

    if (! $settlementTenant && filled($settlementUser?->tenant_id)) {
        $settlementTenant = \App\Models\Tenant::query()->find($settlementUser->tenant_id);
    }

    if ($settlementTenant && $settlementUser) {
        $settlementRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $settlementTenant->id, (string) $settlementUser->id);

        if (! $settlementRole && filled($settlementUser->role ?? null)) {
            $settlementRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $settlementUser->role);
        }
    }

    abort_unless(in_array($settlementRole, ['admin', 'owner', 'superadmin'], true), 403);
@endphp

@section('page_title', 'Settlement QRIS')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto pb-10 lg:pl-72')

@section('header')
    <x-page-header title="Settlement QRIS" subtitle="Catat uang real masuk dan potongan admin" backUrl="/backoffice/finance/summary" :showMenuButton="true" />
@endsection

@section('content')
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-4 lg:px-8">
        <div id="settlement-alert" class="hidden rounded-lg border px-4 py-3 text-sm font-semibold"></div>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-primary">Rekonsiliasi pembayaran</p>
                    <h2 class="mt-2 text-xl font-black text-slate-950 dark:text-white">Cocokkan QRIS POS dengan uang masuk bank.</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Contoh: POS mencatat QRIS Rp100.000, bank masuk Rp99.000. Sistem otomatis mencatat beban admin Rp1.000 ke laporan.</p>
                </div>
                <a href="/backoffice/settings/payment-settings" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">
                    <span class="material-symbols-outlined text-[18px]">settings</span>
                    Atur Rekening
                </a>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1fr_0.9fr]">
            <form id="settlement-form" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Tanggal Penjualan</span>
                        <input name="sales_date" type="date" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900" required>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Tanggal Uang Masuk</span>
                        <input name="settlement_date" type="date" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900" required>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Metode</span>
                        <select name="payment_method" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900">
                            <option value="qris">QRIS</option>
                            <option value="bank">Transfer Bank</option>
                        </select>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Bank Uang Masuk</span>
                        <select name="bank_account_id" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900" required></select>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Akun Settlement POS</span>
                        <select name="settlement_account_id" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900"></select>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Akun Beban Admin</span>
                        <select name="fee_account_id" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900"></select>
                    </label>
                    <label class="space-y-1 md:col-span-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Uang Real Masuk Bank</span>
                        <input name="received_amount" type="number" min="0" step="0.01" class="h-11 w-full rounded-lg border border-slate-200 px-3 text-sm font-black dark:border-slate-700 dark:bg-slate-900" placeholder="99000" required>
                    </label>
                    <label class="space-y-1 md:col-span-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Catatan</span>
                        <input name="notes" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="Settlement QRIS dari mutasi bank">
                    </label>
                </div>

                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Total POS</p>
                            <p class="mt-1 text-lg font-black" data-preview="expected">Rp0</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Real Masuk</p>
                            <p class="mt-1 text-lg font-black text-emerald-700" data-preview="received">Rp0</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Admin QRIS</p>
                            <p class="mt-1 text-lg font-black text-rose-600" data-preview="fee">Rp0</p>
                        </div>
                    </div>
                </div>

                <button class="mt-4 inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-black text-white shadow-sm shadow-primary/20" type="submit">
                    <span class="material-symbols-outlined text-[18px]">price_check</span>
                    Catat Settlement
                </button>
            </form>

            <aside class="space-y-4">
                <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Jurnal yang dibuat</p>
                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3 rounded-lg bg-emerald-50 px-3 py-2 text-emerald-800">
                            <span>Debit Bank</span>
                            <strong data-journal="bank">Rp0</strong>
                        </div>
                        <div class="flex justify-between gap-3 rounded-lg bg-rose-50 px-3 py-2 text-rose-800">
                            <span>Debit Beban Admin</span>
                            <strong data-journal="fee">Rp0</strong>
                        </div>
                        <div class="flex justify-between gap-3 rounded-lg bg-slate-100 px-3 py-2 text-slate-800">
                            <span>Kredit Settlement QRIS</span>
                            <strong data-journal="expected">Rp0</strong>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Riwayat Terakhir</p>
                    <div id="settlement-history" class="mt-3 space-y-2"></div>
                </section>
            </aside>
        </section>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content || '';
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const headers = {
                Accept: 'application/json',
                ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            };

            const el = {
                alert: document.getElementById('settlement-alert'),
                form: document.getElementById('settlement-form'),
                history: document.getElementById('settlement-history'),
            };

            let state = { expected: 0, accounts: [], defaults: {} };

            const rupiah = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Number(value || 0));

            const showAlert = (message, tone = 'success') => {
                el.alert.className = `rounded-lg border px-4 py-3 text-sm font-semibold ${tone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'}`;
                el.alert.textContent = message;
                el.alert.classList.remove('hidden');
            };

            const apiJson = async (url, init = {}) => {
                const response = await fetch(url, {
                    ...init,
                    headers: {
                        ...headers,
                        ...(init.body ? { 'Content-Type': 'application/json' } : {}),
                        ...(init.headers || {}),
                    },
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || 'Permintaan gagal.');
                return payload;
            };

            const renderPreview = () => {
                const received = Number(el.form.elements.received_amount.value || 0);
                const fee = Math.max(0, Number(state.expected || 0) - received);
                document.querySelector('[data-preview="expected"]').textContent = rupiah(state.expected);
                document.querySelector('[data-preview="received"]').textContent = rupiah(received);
                document.querySelector('[data-preview="fee"]').textContent = rupiah(fee);
                document.querySelector('[data-journal="bank"]').textContent = rupiah(received);
                document.querySelector('[data-journal="fee"]').textContent = rupiah(fee);
                document.querySelector('[data-journal="expected"]').textContent = rupiah(state.expected);
            };

            const renderAccounts = () => {
                const assetAccounts = state.accounts.filter((account) => account.type === 'asset');
                const expenseAccounts = state.accounts.filter((account) => account.type === 'expense');
                const option = (account) => `<option value="${account.id}">${account.code || ''} - ${account.name || '-'}</option>`;
                el.form.elements.bank_account_id.innerHTML = assetAccounts.map(option).join('');
                el.form.elements.settlement_account_id.innerHTML = assetAccounts.map(option).join('');
                el.form.elements.fee_account_id.innerHTML = expenseAccounts.map(option).join('');
                el.form.elements.bank_account_id.value = state.defaults.bank_account_id || '';
                el.form.elements.settlement_account_id.value = state.defaults.settlement_account_id || '';
                el.form.elements.fee_account_id.value = state.defaults.fee_account_id || '';
            };

            const renderHistory = (rows = []) => {
                if (!rows.length) {
                    el.history.innerHTML = '<p class="text-sm text-slate-500">Belum ada settlement.</p>';
                    return;
                }
                el.history.innerHTML = rows.map((row) => `
                    <div class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-900">
                        <div class="flex items-center justify-between gap-3">
                            <strong>${String(row.payment_method || '').toUpperCase()} ${row.sales_date}</strong>
                            <span class="font-black text-primary">${rupiah(row.received_amount)}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Admin ${rupiah(row.admin_fee_amount)} - settlement ${row.settlement_date}</p>
                    </div>
                `).join('');
            };

            const loadSummary = async () => {
                const url = new URL('/api/v1/payment-settlements', window.location.origin);
                url.searchParams.set('sales_date', el.form.elements.sales_date.value);
                url.searchParams.set('payment_method', el.form.elements.payment_method.value);
                const payload = await apiJson(url.toString());
                state.expected = Number(payload.summary?.remaining_amount || payload.summary?.expected_amount || 0);
                state.accounts = payload.accounts || [];
                state.defaults = payload.defaults || {};
                renderAccounts();
                renderHistory(payload.settlements || []);
                renderPreview();
            };

            const today = new Date().toISOString().slice(0, 10);
            el.form.elements.sales_date.value = today;
            el.form.elements.settlement_date.value = today;

            ['sales_date', 'payment_method'].forEach((name) => {
                el.form.elements[name].addEventListener('change', () => loadSummary().catch((error) => showAlert(error.message, 'error')));
            });
            el.form.elements.received_amount.addEventListener('input', renderPreview);
            el.form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const data = Object.fromEntries(new FormData(el.form).entries());
                data.received_amount = Number(data.received_amount || 0);
                try {
                    await apiJson('/api/v1/payment-settlements', {
                        method: 'POST',
                        body: JSON.stringify(data),
                    });
                    showAlert('Settlement berhasil dicatat. Laporan bank dan beban admin sudah diperbarui.');
                    await loadSummary();
                } catch (error) {
                    showAlert(error.message, 'error');
                }
            });

            loadSummary().catch((error) => showAlert(error.message, 'error'));
        })();
    </script>
@endsection
