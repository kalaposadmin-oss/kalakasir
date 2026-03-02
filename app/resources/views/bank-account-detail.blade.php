@extends('layouts.app')

@section('page_title', 'Detail Bank')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto p-0')

@section('header')
    <x-page-header title="Detail Bank" backUrl="/settings/chart-of-accounts">
        <x-slot name="actions">
            <button class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300" type="button">
                <span class="material-symbols-outlined text-[20px]">account_balance</span>
            </button>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
    <div id="bank-detail" data-account-id="{{ $accountId }}" class="px-4 py-4">
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Akun Bank</p>
            <h2 id="bank-name" class="text-lg font-bold text-slate-900 dark:text-slate-100 mt-1">-</h2>
            <p id="bank-code" class="text-xs text-slate-500">Kode: -</p>
        </div>

        <div class="mt-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Riwayat Transaksi</p>
                <p class="text-[11px] text-slate-400">Terbaru 20 transaksi</p>
            </div>
            <div id="bank-transactions" class="space-y-2"></div>
            <p id="bank-empty" class="text-xs text-slate-400 hidden">Belum ada transaksi.</p>
            <p id="bank-error" class="text-xs text-red-500 font-semibold hidden"></p>
        </div>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
            const container = document.getElementById('bank-detail');
            const accountId = container?.dataset.accountId;

            const el = {
                name: document.getElementById('bank-name'),
                code: document.getElementById('bank-code'),
                list: document.getElementById('bank-transactions'),
                empty: document.getElementById('bank-empty'),
                error: document.getElementById('bank-error'),
            };

            const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(value || 0);

            const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }).format(new Date(value));

            const setError = (message) => {
                el.error.textContent = message;
                el.error.classList.toggle('hidden', !message);
            };

            const loadTransactions = async () => {
                const response = await fetch(`/api/v1/bank-accounts/${accountId}/transactions`, { headers });
                if (!response.ok) {
                    throw new Error('Gagal memuat transaksi bank.');
                }
                return response.json();
            };

            const renderTransactions = (transactions) => {
                el.list.innerHTML = '';
                if (!transactions.length) {
                    el.empty.classList.remove('hidden');
                    return;
                }
                el.empty.classList.add('hidden');

                transactions.forEach((trx) => {
                    const row = document.createElement('div');
                    row.className = 'p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex items-center justify-between';
                    row.innerHTML = `
                        <div>
                            <p class="text-xs text-slate-400">Transaksi #${trx.sale_id}</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Masuk • ${formatCurrency(trx.amount)}</p>
                            <p class="text-xs text-slate-500">${formatDate(trx.created_at)}</p>
                        </div>
                        <span class="text-xs font-bold text-emerald-600">+${formatCurrency(trx.amount)}</span>
                    `;
                    el.list.appendChild(row);
                });
            };

            const init = async () => {
                if (!accountId) {
                    setError('Akun bank tidak ditemukan.');
                    return;
                }

                try {
                    const payload = await loadTransactions();
                    const account = payload.account || {};
                    el.name.textContent = account.name || '-';
                    el.code.textContent = `Kode: ${account.code || '-'}`;
                    renderTransactions(payload.transactions || []);
                } catch (error) {
                    setError(error.message || 'Gagal memuat transaksi.');
                }
            };

            init();
        })();
    </script>
@endsection
