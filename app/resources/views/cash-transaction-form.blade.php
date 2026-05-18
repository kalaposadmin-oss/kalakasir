@extends('layouts.app')

@php
    $showMenu = true;
    $transactionDirection = $transactionDirection ?? 'cash-in';
    $transactionTitle = $transactionTitle ?? ($transactionDirection === 'cash-in' ? 'Kas Masuk' : 'Kas Keluar');
    $transactionSubtitle = $transactionSubtitle ?? 'Catat transaksi kas langsung di POS dan tetap masuk jurnal.';
    $transactionActionLabel = $transactionActionLabel ?? 'Simpan';
    $transactionModeLabel = $transactionModeLabel ?? ($transactionDirection === 'cash-in' ? 'Pemasukan kas' : 'Pengeluaran kas');
    $submitUrl = $submitUrl ?? ($transactionDirection === 'cash-in' ? '/pos/cash-in' : '/pos/cash-out');
    $alternateUrl = $alternateUrl ?? ($transactionDirection === 'cash-in' ? '/pos/cash-out/create' : '/pos/cash-in/create');
    $recentTransactions = $recentTransactions ?? [];
    $cashAccounts = $cashAccounts ?? [];
    $categoryOptions = $categoryOptions ?? [];
    $transactionHistory = $transactionHistory ?? [];
    $focusForm = (bool) ($focusForm ?? false);
    $posHomeUrl = pos_default_url();
@endphp

@section('page_title', $transactionTitle)
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-800 dark:text-slate-100 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto pb-24 app-bottom-safe-space')
@php($bottom_nav = 'dashboard')

@section('content')
    <header class="sticky top-0 z-30 flex items-center justify-between border-b border-slate-200/60 bg-white/80 px-4 py-4 backdrop-blur-xl dark:border-slate-800/60 dark:bg-background-dark/80">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-primary">{{ $transactionModeLabel }}</p>
            <h1 class="text-lg font-bold leading-tight tracking-tight text-slate-900 dark:text-slate-100">{{ $transactionTitle }}</h1>
        </div>
        <a href="{{ $posHomeUrl }}" class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-600 active:opacity-60 dark:bg-slate-800 dark:text-slate-300" aria-label="Kembali">
            <span class="material-symbols-outlined">close</span>
        </a>
    </header>

    <main class="space-y-4 p-4">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-sm font-medium text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <section class="rounded-3xl border border-slate-100 bg-gradient-to-br from-white to-slate-50 p-5 shadow-sm dark:border-slate-800 dark:from-slate-900 dark:to-slate-950">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Input cepat</p>
                    <h2 class="mt-1 text-base font-bold text-slate-900 dark:text-slate-100">{{ $transactionTitle }} langsung ke jurnal</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">Setiap transaksi otomatis membuat journal entry dan saldo akun ikut diperbarui.</p>
                </div>
                <span class="rounded-full bg-primary/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.15em] text-primary">{{ $transactionModeLabel }}</span>
            </div>

            <div class="mb-4 flex gap-2">
                <a href="/pos/cash-in/create" class="flex-1 rounded-2xl border px-3 py-2 text-center text-xs font-bold {{ $transactionDirection === 'cash-in' ? 'border-primary bg-primary text-white' : 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200' }}">Kas Masuk</a>
                <a href="/pos/cash-out/create" class="flex-1 rounded-2xl border px-3 py-2 text-center text-xs font-bold {{ $transactionDirection === 'cash-out' ? 'border-primary bg-primary text-white' : 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200' }}">Kas Keluar</a>
            </div>

            @if (count($cashAccounts) === 0)
                <div class="mb-4 rounded-2xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-300">
                    Akun kas/bank belum tersedia untuk tenant ini.
                </div>
            @endif

            <form action="{{ $submitUrl }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <label class="space-y-1">
                        <span class="ml-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">Tanggal</span>
                        <input id="cash-transaction-date" name="transaction_date" type="date" value="{{ old('transaction_date', now()->toDateString()) }}" class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-900" />
                        @error('transaction_date') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                    </label>
                    <label class="space-y-1">
                        <span class="ml-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">Nominal</span>
                        <input name="amount" type="number" step="0.01" min="0" value="{{ old('amount') }}" placeholder="0" class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-900" />
                        @error('amount') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                    </label>
                </div>

                <label class="block space-y-1">
                    <span class="ml-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">Akun Kas / Bank</span>
                    <select name="account_id" class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-900">
                        <option value="">Pilih akun</option>
                        @foreach ($cashAccounts as $account)
                            <option value="{{ $account['value'] }}" @selected(old('account_id') === $account['value'])>{{ $account['label'] }}</option>
                        @endforeach
                    </select>
                    @error('account_id') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                </label>

                <label class="block space-y-1">
                    <span class="ml-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">Kategori</span>
                    <select name="category" class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-900">
                        <option value="">Pilih kategori</option>
                        @foreach ($categoryOptions as $category)
                            <option value="{{ $category['value'] }}" @selected(old('category') === $category['value'])>{{ $category['label'] }}</option>
                        @endforeach
                    </select>
                    @error('category') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                </label>

                <label class="block space-y-1">
                    <span class="ml-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">Deskripsi</span>
                    <textarea name="description" rows="3" placeholder="Contoh: Setoran modal awal untuk kas operasional" class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-900">{{ old('description') }}</textarea>
                    @error('description') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                </label>

                <label class="block space-y-1">
                    <span class="ml-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">Reference Opsional</span>
                    <input name="reference" type="text" value="{{ old('reference') }}" placeholder="No. bukti, invoice, atau catatan singkat" class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-900" />
                    @error('reference') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                </label>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3.5 text-sm font-bold text-white active:opacity-90">
                    <span class="material-symbols-outlined text-[20px]">{{ $transactionDirection === 'cash-in' ? 'add_card' : 'remove_shopping_cart' }}</span>
                    {{ $transactionActionLabel }}
                </button>
            </form>
        </section>

        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.15em] text-slate-400">Riwayat terbaru</p>
                    <h3 class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">{{ $transactionTitle }}</h3>
                </div>
                <a href="{{ $posHomeUrl }}" class="text-xs font-bold text-primary">Dashboard</a>
            </div>

            <div class="space-y-3">
                @forelse ($recentTransactions as $transaction)
                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/60">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ $transaction['description'] }}</p>
                                <p class="mt-1 text-[11px] text-slate-500">{{ $transaction['transaction_date'] }} · {{ $transaction['category'] }}@if (! empty($transaction['reference'])) · {{ $transaction['reference'] }}@endif</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold {{ $transactionDirection === 'cash-in' ? 'text-emerald-600' : 'text-rose-600' }}">{{ $transactionDirection === 'cash-in' ? '+' : '-' }}Rp {{ number_format($transaction['amount'], 0, ',', '.') }}</p>
                                <p class="text-[11px] text-slate-400">Saldo: Rp {{ number_format($transaction['balance_after'], 0, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400">
                        Belum ada transaksi {{ $transactionDirection === 'cash-in' ? 'kas masuk' : 'kas keluar' }}.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.15em] text-slate-400">Riwayat lengkap</p>
                    <h3 class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">50 transaksi terakhir</h3>
                </div>
                <a href="{{ $transactionDirection === 'cash-in' ? '/pos/cash-out' : '/pos/cash-in' }}" class="text-xs font-bold text-primary">
                    Lihat {{ $transactionDirection === 'cash-in' ? 'kas keluar' : 'kas masuk' }}
                </a>
            </div>

            <div class="space-y-3">
                @forelse ($transactionHistory as $transaction)
                    <article class="rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/60">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ $transaction['description'] }}</p>
                                <p class="mt-1 text-[11px] text-slate-500">{{ $transaction['transaction_date'] }} · {{ $transaction['category'] }}@if (! empty($transaction['reference'])) · {{ $transaction['reference'] }}@endif</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold {{ $transactionDirection === 'cash-in' ? 'text-emerald-600' : 'text-rose-600' }}">{{ $transactionDirection === 'cash-in' ? '+' : '-' }}Rp {{ number_format($transaction['amount'], 0, ',', '.') }}</p>
                                <p class="text-[11px] text-slate-400">Saldo akhir: Rp {{ number_format($transaction['balance_after'], 0, ',', '.') }}</p>
                            </div>
                        </div>

                        <dl class="mt-3 grid grid-cols-1 gap-2 text-[11px] text-slate-500 sm:grid-cols-2">
                            <div>
                                <dt class="font-semibold text-slate-400">Akun kas/bank</dt>
                                <dd class="mt-0.5 text-slate-600 dark:text-slate-300">{{ $transaction['account_label'] }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-400">Akun lawan</dt>
                                <dd class="mt-0.5 text-slate-600 dark:text-slate-300">{{ $transaction['counter_account_label'] }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-400">Saldo sebelum</dt>
                                <dd class="mt-0.5 text-slate-600 dark:text-slate-300">Rp {{ number_format($transaction['balance_before'], 0, ',', '.') }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-slate-400">Dicatat oleh</dt>
                                <dd class="mt-0.5 text-slate-600 dark:text-slate-300">{{ $transaction['created_by_name'] ?: '-' }}</dd>
                            </div>
                        </dl>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400">
                        Belum ada riwayat transaksi {{ $transactionDirection === 'cash-in' ? 'kas masuk' : 'kas keluar' }}.
                    </div>
                @endforelse
            </div>
        </section>
    </main>

    @if ($focusForm)
        <script>
            (() => {
                const formField = document.getElementById('cash-transaction-date');
                if (!formField) {
                    return;
                }

                formField.focus({ preventScroll: true });
                formField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            })();
        </script>
    @endif
@endsection
