@extends('layouts.app')

@php
    $showMenu = true;
    $cashPortal = $cashPortal ?? (request()->is('backoffice/*') ? 'backoffice' : 'pos');
    $cashBasePath = $cashBasePath ?? ($cashPortal === 'backoffice' ? '/backoffice/finance/cash' : '/pos/cash-in');
    $cashInUrl = $cashInUrl ?? $cashBasePath;
    $cashOutUrl = $cashOutUrl ?? ($cashPortal === 'backoffice' ? '/backoffice/finance/cash/out' : '/pos/cash-out');
    $cashUpdateBaseUrl = $cashUpdateBaseUrl ?? ($cashPortal === 'backoffice' ? '/backoffice/finance/cash' : '/pos/cash-transactions');
    $cashBackUrl = $cashBackUrl ?? ($cashPortal === 'backoffice' ? '/backoffice/finance/summary' : pos_default_url());
    $from = (string) request()->query('from', '');
    if ($from !== '') {
        $normalizedFrom = ltrim($from, '/');
        if (str_starts_with($normalizedFrom, 'menu/')) {
            $cashBackUrl = '/' . $normalizedFrom;
        }
    }

    $cashAccounts = $cashAccounts ?? [];
    $categoryOptions = $categoryOptions ?? [];
    $recentTransactions = $recentTransactions ?? [];
    $transactionHistory = $transactionHistory ?? [];
    $monthlySummary = $monthlySummary ?? ['cash_in' => 0, 'cash_out' => 0, 'net' => 0, 'count' => 0];
@endphp

@section('page_title', 'Kas')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-800 dark:text-slate-100 antialiased')
@section('app_shell_class', $cashPortal === 'backoffice' ? 'relative flex h-screen w-full flex-col overflow-hidden bg-background-light dark:bg-background-dark lg:pl-72' : 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', $cashPortal === 'backoffice' ? 'flex-1 overflow-y-auto pb-8' : 'flex-1 overflow-y-auto pb-24 app-bottom-safe-space')
@php($bottom_nav = 'dashboard')

@push('head')
    <style>
        .ios-shadow { box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.05); }
        .ios-shadow-sm { box-shadow: 0 2px 8px -1px rgba(0, 0, 0, 0.04); }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .no-scrollbar::-webkit-scrollbar { display: none; }

        /* Floating sheet */
        .cash-sheet-overlay { position:fixed;inset:0;z-index:50;opacity:0;pointer-events:none;transition:opacity .25s ease; }
        .cash-sheet-overlay.active { opacity:1;pointer-events:auto; }
        .cash-sheet-backdrop { position:absolute;inset:0;background:rgba(0,0,0,.35); }
        .cash-sheet {
            position:absolute;bottom:0;left:50%;transform:translateX(-50%) translateY(100%);
            width:100%;max-width:{{ $cashPortal === 'backoffice' ? '560px' : '430px' }};max-height:88vh;overflow-y:auto;
            border-radius:1.5rem 1.5rem 0 0;background:#fff;
            padding:1.25rem 1.25rem 2rem;
            box-shadow:0 -8px 30px rgba(0,0,0,.12);
            transition:transform .3s cubic-bezier(.32,.72,0,1);
        }
        .cash-sheet-overlay.active .cash-sheet { transform:translateX(-50%) translateY(0); }
        @media(prefers-color-scheme:dark){ .cash-sheet{ background:rgb(15 23 42); } }

        /* Pulse animation for totals */
        @keyframes fadeInUp {
            from { opacity:0; transform:translateY(8px); }
            to { opacity:1; transform:translateY(0); }
        }
        .fade-in-up { animation: fadeInUp .4s ease both; }
    </style>
@endpush

@section('header')
    <header class="sticky top-0 z-30 border-b border-slate-200/60 bg-white/80 px-4 py-4 backdrop-blur-xl dark:border-slate-800/60 dark:bg-background-dark/80">
        <div class="flex items-center justify-between gap-3">
            <a href="{{ $cashBackUrl }}" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300" aria-label="Kembali"
               onclick="try{var r=document.referrer?new URL(document.referrer):null;var t=new URL(this.href,window.location.origin);if(window.history.length>1&&r&&r.origin===window.location.origin&&r.pathname===t.pathname){window.history.back();return false;}}catch(e){}return true;">
                <span class="material-symbols-outlined text-[20px]">arrow_back_ios_new</span>
            </a>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-lg font-bold leading-tight tracking-tight text-slate-900 dark:text-slate-100">Kas</h1>
                <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Kas masuk & kas keluar</p>
            </div>
            <button class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300" type="button" aria-label="Menu" data-menu-trigger>
                <span class="material-symbols-outlined">menu</span>
            </button>
        </div>
    </header>
@endsection

@section('content')
    <main class="{{ $cashPortal === 'backoffice' ? 'mx-auto flex w-full max-w-6xl flex-col gap-4 p-4 lg:p-6' : 'flex flex-col gap-4 p-4' }}">
        {{-- Flash message --}}
        @if (session('status'))
            <div id="cash-flash" class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-sm font-medium text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-300 fade-in-up">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-100 bg-rose-50 p-4 text-sm font-medium text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-300 fade-in-up">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Date filter --}}
        <section class="rounded-2xl border border-slate-100 bg-white p-4 ios-shadow dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 flex-1">
                    <span class="material-symbols-outlined text-lg text-slate-400">calendar_month</span>
                    <input id="cash-filter-date" type="month" value="{{ now()->format('Y-m') }}" class="flex-1 rounded-xl border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-700 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200" />
                </div>
                <button id="cash-filter-btn" class="flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white active:scale-95 transition-transform" type="button">
                    <span class="material-symbols-outlined text-base">filter_list</span>
                    Filter
                </button>
            </div>
        </section>

        {{-- Monthly summary --}}
        <section class="rounded-2xl border border-slate-100 bg-gradient-to-br from-white to-slate-50 p-5 ios-shadow dark:border-slate-800 dark:from-slate-900 dark:to-slate-950">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Ringkasan Bulan Ini</p>
            <div class="mt-3 grid grid-cols-3 gap-2">
                <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/20">
                    <p class="text-[9px] font-bold uppercase tracking-[0.12em] text-emerald-600">Kas Masuk</p>
                    <p id="summary-cash-in" class="mt-1 text-sm font-black text-emerald-700 dark:text-emerald-300">Rp {{ number_format($monthlySummary['cash_in'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl bg-rose-50 p-3 dark:bg-rose-950/20">
                    <p class="text-[9px] font-bold uppercase tracking-[0.12em] text-rose-600">Kas Keluar</p>
                    <p id="summary-cash-out" class="mt-1 text-sm font-black text-rose-700 dark:text-rose-300">Rp {{ number_format($monthlySummary['cash_out'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl bg-primary/5 p-3 dark:bg-primary/10">
                    <p class="text-[9px] font-bold uppercase tracking-[0.12em] text-primary">Netto</p>
                    <p id="summary-net" class="mt-1 text-sm font-black text-primary">Rp {{ number_format($monthlySummary['net'], 0, ',', '.') }}</p>
                </div>
            </div>
            <p id="summary-count" class="mt-3 text-center text-[11px] font-semibold text-slate-400">{{ $monthlySummary['count'] }} transaksi bulan ini</p>
        </section>

        {{-- Action buttons --}}
        <section class="grid grid-cols-2 gap-3">
            <button id="btn-open-cash-in" type="button" class="flex flex-col items-center justify-center gap-2.5 rounded-2xl bg-emerald-50 p-5 ios-shadow border border-emerald-100 active:bg-emerald-100 active:scale-[0.97] transition-all dark:bg-emerald-950/20 dark:border-emerald-900/30">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500 text-white shadow-lg shadow-emerald-500/20">
                    <span class="material-symbols-outlined text-xl">call_received</span>
                </span>
                <span class="text-xs font-bold text-slate-800 dark:text-slate-100">Kas Masuk</span>
            </button>
            <button id="btn-open-cash-out" type="button" class="flex flex-col items-center justify-center gap-2.5 rounded-2xl bg-rose-50 p-5 ios-shadow border border-rose-100 active:bg-rose-100 active:scale-[0.97] transition-all dark:bg-rose-950/20 dark:border-rose-900/30">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-rose-500 text-white shadow-lg shadow-rose-500/20">
                    <span class="material-symbols-outlined text-xl">call_made</span>
                </span>
                <span class="text-xs font-bold text-slate-800 dark:text-slate-100">Kas Keluar</span>
            </button>
        </section>

        {{-- Transaction history --}}
        <section class="rounded-2xl border border-slate-100 bg-white p-5 ios-shadow dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Riwayat Transaksi</p>
                    <h3 class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">Kas Masuk & Keluar</h3>
                </div>
                <div class="flex items-center gap-2">
                    <select id="history-direction-filter" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold text-slate-600 focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300">
                        <option value="all">Semua</option>
                        <option value="cash-in">Kas Masuk</option>
                        <option value="cash-out">Kas Keluar</option>
                    </select>
                </div>
            </div>

            <div id="transaction-list" class="space-y-3">
                @forelse ($transactionHistory as $transaction)
                    <article
                        class="rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/60"
                        data-id="{{ $transaction['id'] }}"
                        data-direction="{{ $transaction['direction'] }}"
                        data-date="{{ $transaction['transaction_date'] }}"
                        data-account-id="{{ $transaction['account_id'] ?? '' }}"
                        data-category="{{ $transaction['category'] }}"
                        data-amount="{{ $transaction['amount'] }}"
                        data-description="{{ $transaction['description'] }}"
                        data-reference="{{ $transaction['reference'] ?? '' }}"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3 min-w-0">
                                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $transaction['direction'] === 'cash-in' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600' }}">
                                    <span class="material-symbols-outlined text-base">{{ $transaction['direction'] === 'cash-in' ? 'call_received' : 'call_made' }}</span>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-900 dark:text-slate-100 truncate">{{ $transaction['description'] }}</p>
                                    <p class="mt-1 text-[11px] text-slate-500">{{ $transaction['transaction_date'] }} · {{ $transaction['category'] }}@if (! empty($transaction['reference'])) · {{ $transaction['reference'] }}@endif</p>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold {{ $transaction['direction'] === 'cash-in' ? 'text-emerald-600' : 'text-rose-600' }}">{{ $transaction['direction'] === 'cash-in' ? '+' : '-' }}Rp {{ number_format($transaction['amount'], 0, ',', '.') }}</p>
                                <p class="text-[11px] text-slate-400">{{ $transaction['account_label'] }}</p>
                                @if ($transaction['can_edit'] ?? true)
                                    <button type="button" class="btn-edit-cash mt-2 inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-primary shadow-sm ring-1 ring-primary/15 dark:bg-slate-900">
                                        <span class="material-symbols-outlined text-sm">edit</span>
                                        Edit
                                    </button>
                                @else
                                    <span class="mt-2 inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-400 dark:bg-slate-800">Terkunci sistem</span>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5 text-center text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400">
                        Belum ada riwayat transaksi kas.
                    </div>
                @endforelse
            </div>
        </section>
    </main>

    {{-- ===== FLOATING SHEET for adding cash transaction ===== --}}
    <div id="cash-sheet-overlay" class="cash-sheet-overlay">
        <div class="cash-sheet-backdrop" id="cash-sheet-backdrop"></div>
        <div class="cash-sheet dark:bg-slate-900">
            {{-- Drag handle --}}
            <div class="mb-4 flex items-center justify-between">
                <div class="h-1.5 w-12 rounded-full bg-slate-300 dark:bg-slate-700"></div>
                <button id="cash-sheet-close" class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400" type="button">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>

            {{-- Sheet header --}}
            <div class="mb-4">
                <p id="sheet-mode-label" class="text-[11px] font-bold uppercase tracking-[0.18em] text-primary">Pemasukan Kas</p>
                <h2 id="sheet-title" class="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">Kas Masuk</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Transaksi otomatis membuat journal entry dan saldo akun ikut diperbarui.</p>
            </div>



            @if (count($cashAccounts) === 0)
                <div class="mb-4 rounded-2xl border border-amber-100 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-300">
                    Akun kas/bank belum tersedia untuk tenant ini.
                </div>
            @endif

            {{-- Form --}}
            <form id="cash-form" action="{{ $cashInUrl }}" method="POST" class="space-y-4">
                @csrf
                <input id="form-method" type="hidden" name="_method" value="PUT" disabled />
                <input id="form-mode" type="hidden" name="form_mode" value="{{ old('form_mode', 'create') }}" />
                <input id="edit-transaction-id" type="hidden" name="edit_transaction_id" value="{{ old('edit_transaction_id') }}" />
                <input type="hidden" name="direction" id="form-direction" value="cash-in" />
                <div class="grid grid-cols-2 gap-3">
                    <label class="space-y-1">
                        <span class="ml-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">Tanggal</span>
                        <input id="cash-transaction-date" name="transaction_date" type="date" value="{{ old('transaction_date', now()->toDateString()) }}" class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-900" />
                        @error('transaction_date') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                    </label>
                    <label class="space-y-1">
                        <span class="ml-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">Nominal</span>
                        <input id="cash-amount" name="amount" type="number" step="0.01" min="0" value="{{ old('amount') }}" placeholder="0" class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-900" />
                        @error('amount') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                    </label>
                </div>

                <label class="block space-y-1">
                    <span class="ml-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">Akun Kas / Bank</span>
                    <select id="cash-account-id" name="account_id" class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-900">
                        <option value="">Pilih akun</option>
                        @foreach ($cashAccounts as $account)
                            <option value="{{ $account['value'] }}" @selected(old('account_id') === $account['value'])>{{ $account['label'] }}</option>
                        @endforeach
                    </select>
                    @error('account_id') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                </label>

                <label class="block space-y-1">
                    <span class="ml-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">Kategori</span>
                    <select name="category" id="cash-category-select" class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-900">
                        <option value="">Pilih kategori</option>
                    </select>
                    @error('category') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                </label>

                <label class="block space-y-1">
                    <span class="ml-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">Deskripsi</span>
                    <textarea id="cash-description" name="description" rows="2" placeholder="Contoh: Setoran modal awal untuk kas operasional" class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-900">{{ old('description') }}</textarea>
                    @error('description') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                </label>

                <label class="block space-y-1">
                    <span class="ml-1 text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">Referensi (Opsional)</span>
                    <input id="cash-reference" name="reference" type="text" value="{{ old('reference') }}" placeholder="No. bukti, invoice, atau catatan singkat" class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-900" />
                    @error('reference') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                </label>

                <button id="cash-submit-btn" type="submit" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3.5 text-sm font-bold text-white active:opacity-90 active:scale-[0.98] transition-all">
                    <span id="submit-icon" class="material-symbols-outlined text-[20px]">add_card</span>
                    <span id="submit-label">Simpan Kas Masuk</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        (() => {
            // ===== Category options data from server =====
            const categoryOptionsIn = @json(collect($categoryOptions['cash-in'] ?? [])->values());
            const categoryOptionsOut = @json(collect($categoryOptions['cash-out'] ?? [])->values());
            const cashInUrl = @json($cashInUrl);
            const cashOutUrl = @json($cashOutUrl);
            const cashUpdateBaseUrl = @json($cashUpdateBaseUrl);
            const oldFormMode = @json(old('form_mode', 'create'));
            const oldDirection = @json(old('direction', 'cash-in'));
            const oldCategory = @json(old('category'));
            const oldEditTransactionId = @json(old('edit_transaction_id'));

            // ===== Sheet elements =====
            const overlay = document.getElementById('cash-sheet-overlay');
            const backdrop = document.getElementById('cash-sheet-backdrop');
            const closeBtn = document.getElementById('cash-sheet-close');
            const modeLabel = document.getElementById('sheet-mode-label');
            const sheetTitle = document.getElementById('sheet-title');
            const form = document.getElementById('cash-form');
            const formMethod = document.getElementById('form-method');
            const formMode = document.getElementById('form-mode');
            const editTransactionId = document.getElementById('edit-transaction-id');
            const directionInput = document.getElementById('form-direction');
            const categorySelect = document.getElementById('cash-category-select');
            const transactionDate = document.getElementById('cash-transaction-date');
            const amountInput = document.getElementById('cash-amount');
            const accountSelect = document.getElementById('cash-account-id');
            const descriptionInput = document.getElementById('cash-description');
            const referenceInput = document.getElementById('cash-reference');
            const submitIcon = document.getElementById('submit-icon');
            const submitLabel = document.getElementById('submit-label');
            let editingTransactionId = null;

            const openSheet = () => {
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            };
            const closeSheet = () => {
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            };

            backdrop.addEventListener('click', closeSheet);
            closeBtn.addEventListener('click', closeSheet);

            const setDirection = (direction, selectedCategory = '') => {
                const isIn = direction === 'cash-in';
                directionInput.value = direction;
                form.action = editingTransactionId
                    ? `${cashUpdateBaseUrl}/${editingTransactionId}`
                    : (isIn ? cashInUrl : cashOutUrl);

                modeLabel.textContent = isIn ? 'Pemasukan Kas' : 'Pengeluaran Kas';
                sheetTitle.textContent = editingTransactionId
                    ? (isIn ? 'Edit Kas Masuk' : 'Edit Kas Keluar')
                    : (isIn ? 'Kas Masuk' : 'Kas Keluar');
                submitIcon.textContent = isIn ? 'add_card' : 'remove_shopping_cart';
                submitLabel.textContent = editingTransactionId
                    ? (isIn ? 'Simpan Perubahan Kas Masuk' : 'Simpan Perubahan Kas Keluar')
                    : (isIn ? 'Simpan Kas Masuk' : 'Simpan Kas Keluar');

                // Populate categories
                const options = isIn ? categoryOptionsIn : categoryOptionsOut;
                categorySelect.innerHTML = '<option value="">Pilih kategori</option>';
                options.forEach(opt => {
                    const el = document.createElement('option');
                    el.value = opt.value;
                    el.textContent = opt.label;
                    categorySelect.appendChild(el);
                });
                categorySelect.value = selectedCategory || '';
            };

            const resetFormForCreate = (direction) => {
                editingTransactionId = null;
                formMode.value = 'create';
                editTransactionId.value = '';
                formMethod.disabled = true;
                form.reset();
                transactionDate.value = @json(now()->toDateString());
                setDirection(direction);
            };

            const startEdit = (item, shouldFillFields = true) => {
                editingTransactionId = item.id || oldEditTransactionId;
                formMode.value = 'edit';
                editTransactionId.value = editingTransactionId || '';
                formMethod.disabled = false;
                setDirection(item.direction || 'cash-in', item.category || '');

                if (shouldFillFields) {
                    transactionDate.value = item.date || '';
                    amountInput.value = item.amount || '';
                    accountSelect.value = item.accountId || '';
                    descriptionInput.value = item.description || '';
                    referenceInput.value = item.reference || '';
                }
            };

            // Open buttons — directly set direction, no tab switching needed
            document.getElementById('btn-open-cash-in').addEventListener('click', () => {
                resetFormForCreate('cash-in');
                openSheet();
            });
            document.getElementById('btn-open-cash-out').addEventListener('click', () => {
                resetFormForCreate('cash-out');
                openSheet();
            });

            document.querySelectorAll('.btn-edit-cash').forEach(button => {
                button.addEventListener('click', () => {
                    const item = button.closest('article[data-id]');
                    if (!item) return;
                    startEdit({
                        id: item.dataset.id,
                        direction: item.dataset.direction,
                        date: item.dataset.date,
                        accountId: item.dataset.accountId,
                        category: item.dataset.category,
                        amount: item.dataset.amount,
                        description: item.dataset.description,
                        reference: item.dataset.reference,
                    });
                    openSheet();
                });
            });

            // Auto-open sheet if there are validation errors (user was submitting)
            @if ($errors->any())
                if (oldFormMode === 'edit') {
                    startEdit({
                        id: oldEditTransactionId,
                        direction: oldDirection,
                        category: oldCategory,
                    }, false);
                } else {
                    editingTransactionId = null;
                    formMethod.disabled = true;
                    setDirection(oldDirection, oldCategory || '');
                }
                openSheet();
            @endif

            // ===== Direction filter for history =====
            const dirFilter = document.getElementById('history-direction-filter');
            const txList = document.getElementById('transaction-list');

            dirFilter.addEventListener('change', () => {
                const val = dirFilter.value;
                const items = txList.querySelectorAll('article[data-direction]');
                items.forEach(item => {
                    if (val === 'all' || item.dataset.direction === val) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });

            // ===== Date filter (month) =====
            const filterBtn = document.getElementById('cash-filter-btn');
            const filterDate = document.getElementById('cash-filter-date');

            filterBtn.addEventListener('click', () => {
                const val = filterDate.value;
                if (!val) return;
                const params = new URLSearchParams(window.location.search);
                params.set('month', val);
                window.location.href = window.location.pathname + '?' + params.toString();
            });

            // Auto-dismiss flash message
            const flash = document.getElementById('cash-flash');
            if (flash) {
                setTimeout(() => {
                    flash.style.opacity = '0';
                    flash.style.transition = 'opacity .4s ease';
                    setTimeout(() => flash.remove(), 400);
                }, 4000);
            }
        })();
    </script>
@endsection
