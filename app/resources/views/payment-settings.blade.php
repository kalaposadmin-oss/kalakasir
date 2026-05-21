@extends('layouts.app')

@php
    $paymentSettingsTenant = \App\Support\TenantContext::get();
    $paymentSettingsUser = auth()->user();
    $paymentSettingsRole = null;

    if (! $paymentSettingsTenant && filled($paymentSettingsUser?->tenant_id)) {
        $paymentSettingsTenant = \App\Models\Tenant::query()->find($paymentSettingsUser->tenant_id);
    }

    if ($paymentSettingsTenant && $paymentSettingsUser) {
        $paymentSettingsRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $paymentSettingsTenant->id, (string) $paymentSettingsUser->id);

        if (! $paymentSettingsRole && filled($paymentSettingsUser->role ?? null)) {
            $paymentSettingsRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $paymentSettingsUser->role);
        }
    }

    abort_unless(in_array($paymentSettingsRole, ['admin', 'owner', 'superadmin'], true), 403);

    $paymentAccounts = collect($paymentAccounts ?? []);
    $selectedCashAccountId = old('cash_account_id', $accountMapping->cash_account_id ?? '');
    $selectedBankAccountId = old('bank_account_id', $accountMapping->bank_account_id ?? '');
    $cashAccount = $paymentAccounts->first(fn ($account) => (string) $account->id === (string) $selectedCashAccountId);
    $bankAccount = $paymentAccounts->first(fn ($account) => (string) $account->id === (string) $selectedBankAccountId);
    $accountLabel = fn ($account) => $account ? trim(($account->code ? $account->code.' - ' : '').$account->name) : 'Belum disetel';
    $bankDisplayName = old('bank_name', $paymentSetting->bank_name ?? '') ?: ($bankAccount->bank_name ?? $bankAccount->name ?? '');
    $bankDisplayNumber = old('bank_account_number', $paymentSetting->bank_account_number ?? '') ?: ($bankAccount->bank_account_number ?? '');
    $bankDisplayHolder = old('bank_account_name', $paymentSetting->bank_account_name ?? '') ?: ($bankAccount->bank_account_name ?? '');
@endphp

@section('page_title', 'Pembayaran & Rekening')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto pb-8 lg:pl-72')

@section('header')
    <x-page-header
        title="Pembayaran & Rekening"
        :breadcrumbs="['Pengaturan', 'Keuangan']"
        backUrl="/backoffice/dashboard"
        :showMenuButton="true"
    >
        <x-slot name="actions">
            <a href="/backoffice/settings/chart-of-accounts" class="inline-flex items-center justify-center size-9 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300" title="Kelola akun pembukuan">
                <span class="material-symbols-outlined text-[20px]">account_balance</span>
            </a>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
    <div class="w-full px-4 py-4 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <section class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.2em] text-primary">
                        <span class="material-symbols-outlined text-sm">payments</span>
                        Pengaturan penerimaan uang
                    </div>
                    <h2 class="mt-4 text-2xl font-black tracking-tight text-slate-950 dark:text-white lg:text-3xl">Atur akun laporan dan info transfer pelanggan.</h2>
                    <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                        Bagian atas memilih akun kas/bank untuk jurnal. Bagian bawah mengisi nomor rekening, QRIS, dan catatan yang dilihat pelanggan saat order online.
                    </p>
                    <div class="mt-5 grid gap-3 md:grid-cols-3">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Tunai POS</p>
                            <p class="mt-2 text-sm font-black text-slate-950 dark:text-white">{{ $accountLabel($cashAccount) }}</p>
                        </div>
                        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900/40 dark:bg-blue-950/20">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-300">QRIS / Transfer</p>
                            <p class="mt-2 text-sm font-black text-slate-950 dark:text-white">{{ $accountLabel($bankAccount) }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/60">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Status Instruksi</p>
                            <p class="mt-2 text-sm font-black text-slate-950 dark:text-white">{{ old('is_active', ($paymentSetting->is_active ?? true)) ? 'Aktif' : 'Nonaktif' }}</p>
                        </div>
                    </div>
                </div>

                <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined mt-0.5 text-primary">route</span>
                        <div class="min-w-0">
                            <h3 class="text-base font-black text-slate-950 dark:text-white">Alur singkat</h3>
                            <div class="mt-4 space-y-3 text-sm">
                                <div class="flex gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-black text-emerald-700">1</span>
                                    <div>
                                        <p class="font-black text-slate-900 dark:text-white">Pilih akun laporan</p>
                                        <p class="text-xs leading-relaxed text-slate-500">Tunai masuk kas, QRIS/transfer masuk bank.</p>
                                    </div>
                                </div>
                                <div class="flex gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-black text-blue-700">2</span>
                                    <div>
                                        <p class="font-black text-slate-900 dark:text-white">Isi tampilan pelanggan</p>
                                        <p class="text-xs leading-relaxed text-slate-500">Nama bank, nomor rekening, QRIS, dan catatan pembayaran.</p>
                                    </div>
                                </div>
                                <div class="flex gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-orange-100 text-xs font-black text-orange-700">3</span>
                                    <div>
                                        <p class="font-black text-slate-900 dark:text-white">Simpan sekali</p>
                                        <p class="text-xs leading-relaxed text-slate-500">POS, laporan, dan order online memakai pengaturan ini.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </section>

            @if (session('status'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-300">
                    <p class="font-bold">Ada data yang perlu diperbaiki.</p>
                    <p class="mt-1">{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="POST" action="/backoffice/settings/payment-settings" class="grid gap-6 xl:grid-cols-[1fr_0.85fr]">
                @csrf

                <section class="space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.22em] text-slate-400">Langkah 1 - laporan dan jurnal</p>
                                <h3 class="mt-2 text-lg font-black text-slate-950 dark:text-white">Akun penerimaan uang</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">Ini menentukan uang tercatat ke akun mana di laporan. Tidak harus sama dengan teks rekening yang dilihat pelanggan.</p>
                            </div>
                            <span class="material-symbols-outlined text-3xl text-primary/70">account_balance_wallet</span>
                        </div>

                        @if ($paymentAccounts->isEmpty())
                            <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                                Akun kas/bank belum tersedia. Buka kelola akun lanjutan untuk membuat akun pembukuan.
                            </div>
                        @endif

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Tunai POS dicatat ke akun</span>
                                <select name="cash_account_id" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm dark:border-slate-800 dark:bg-slate-800">
                                    <option value="">Pilih akun kas</option>
                                    @foreach ($paymentAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) $selectedCashAccountId === (string) $account->id)>
                                            {{ trim(($account->code ? $account->code.' - ' : '').$account->name) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">QRIS / transfer dicatat ke akun</span>
                                <select name="bank_account_id" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm dark:border-slate-800 dark:bg-slate-800">
                                    <option value="">Pilih akun bank/QRIS</option>
                                    @foreach ($paymentAccounts as $account)
                                        <option
                                            value="{{ $account->id }}"
                                            data-bank-name="{{ $account->bank_name ?: $account->name }}"
                                            data-bank-number="{{ $account->bank_account_number }}"
                                            data-bank-holder="{{ $account->bank_account_name }}"
                                            @selected((string) $selectedBankAccountId === (string) $account->id)
                                        >
                                            {{ trim(($account->code ? $account->code.' - ' : '').$account->name) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div id="selected-bank-profile" class="mt-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900 dark:border-blue-900/40 dark:bg-blue-950/20 dark:text-blue-100">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-300">Rekening akun terpilih</p>
                                    <p class="mt-1 font-black" data-bank-profile="name">{{ $bankAccount?->bank_name ?: $bankAccount?->name ?: 'Pilih akun bank/QRIS dulu' }}</p>
                                    <p class="mt-1 text-xs" data-bank-profile="number">
                                        {{ $bankAccount?->bank_account_number ? 'No. '.$bankAccount->bank_account_number : 'Nomor rekening belum diisi di detail akun.' }}
                                    </p>
                                    <p class="text-xs" data-bank-profile="holder">
                                        {{ $bankAccount?->bank_account_name ? 'a.n. '.$bankAccount->bank_account_name : 'Atas nama belum diisi.' }}
                                    </p>
                                </div>
                                <button type="button" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-2 text-xs font-black text-white" data-copy-bank-profile>
                                    Pakai untuk pelanggan
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">
                            Jika punya BCA, Mandiri, atau QRIS terpisah, tambahkan akun di
                            <a href="/backoffice/settings/chart-of-accounts" class="font-bold text-primary">Kelola akun kas/bank lanjutan</a>,
                            lalu pilih sebagai rekening penerimaan di sini.
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.22em] text-slate-400">Langkah 2 - tampilan pelanggan</p>
                                <h3 class="mt-2 text-lg font-black text-slate-950 dark:text-white">Instruksi transfer dan QRIS</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">Data ini hanya untuk informasi pelanggan: pesan WhatsApp, final review order online, dan catatan pembayaran.</p>
                            </div>
                            <span class="material-symbols-outlined text-3xl text-emerald-600/70">qr_code_2</span>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Nama Bank</span>
                                <input name="bank_name" value="{{ $bankDisplayName }}" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm dark:border-slate-800 dark:bg-slate-800" placeholder="Contoh: BCA" />
                            </label>
                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Nomor Rekening</span>
                                <input name="bank_account_number" value="{{ $bankDisplayNumber }}" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm dark:border-slate-800 dark:bg-slate-800" placeholder="1234567890" />
                            </label>
                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Atas Nama</span>
                                <input name="bank_account_name" value="{{ $bankDisplayHolder }}" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm dark:border-slate-800 dark:bg-slate-800" placeholder="Nama pemilik rekening" />
                            </label>
                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">WhatsApp Admin</span>
                                <input name="admin_whatsapp_number" value="{{ old('admin_whatsapp_number', $paymentSetting->admin_whatsapp_number ?? '') }}" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm dark:border-slate-800 dark:bg-slate-800" placeholder="628xxxxxxxxxx" />
                            </label>
                            <label class="space-y-2 md:col-span-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">URL Gambar QRIS</span>
                                <input name="qris_image_url" value="{{ old('qris_image_url', $paymentSetting->qris_image_url ?? '') }}" type="url" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm dark:border-slate-800 dark:bg-slate-800" placeholder="https://domain.com/qris-toko.png" />
                                <span class="block text-xs text-slate-500">Gunakan URL gambar yang bisa dibuka publik agar pelanggan bisa melihat QRIS.</span>
                            </label>
                            <label class="space-y-2 md:col-span-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Catatan Pembayaran</span>
                                <textarea name="payment_note" rows="4" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm dark:border-slate-800 dark:bg-slate-800" placeholder="Contoh: kirim bukti transfer ke admin setelah pembayaran">{{ old('payment_note', $paymentSetting->payment_note ?? '') }}</textarea>
                            </label>
                        </div>
                    </div>
                </section>

                <aside class="space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-slate-400">Preview pelanggan</p>
                        <h3 class="mt-2 text-lg font-black text-slate-950 dark:text-white">Informasi yang akan dibagikan</h3>

                        <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/60">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Transfer ke</p>
                                    <p class="mt-1 text-base font-black text-slate-950 dark:text-white">{{ $bankDisplayName ?: 'Nama bank belum diisi' }}</p>
                                </div>
                                <span class="material-symbols-outlined text-primary">account_balance</span>
                            </div>
                            <dl class="mt-4 space-y-2 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">No. rekening</dt>
                                    <dd class="font-bold text-slate-900 dark:text-white">{{ $bankDisplayNumber ?: '-' }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Atas nama</dt>
                                    <dd class="font-bold text-slate-900 dark:text-white">{{ $bankDisplayHolder ?: '-' }}</dd>
                                </div>
                            </dl>

                            @if(!empty(old('qris_image_url', $paymentSetting->qris_image_url ?? '')))
                                <div class="mt-4 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                    <img src="{{ old('qris_image_url', $paymentSetting->qris_image_url ?? '') }}" alt="QRIS pembayaran" class="mx-auto max-h-52 rounded-lg bg-white object-contain" />
                                </div>
                            @endif
                        </div>

                        <label class="mt-4 flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-3 text-sm font-bold dark:border-slate-800">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', ($paymentSetting->is_active ?? true))) class="rounded border-slate-300 text-primary focus:ring-primary" />
                            Aktifkan instruksi pembayaran pelanggan
                        </label>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-slate-400">Keamanan admin</p>
                        <h3 class="mt-2 text-lg font-black text-slate-950 dark:text-white">PIN verifikasi admin</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">PIN dipakai untuk verifikasi admin di alur order online. Kosongkan jika tidak ingin mengubah PIN.</p>
                        <input name="manager_pin" value="{{ old('manager_pin') }}" type="password" inputmode="numeric" maxlength="6" class="mt-4 w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm dark:border-slate-800 dark:bg-slate-800" placeholder="4-6 digit PIN" />
                        @if(!empty($paymentSettingsUser?->manager_pin_hash))
                            <p class="mt-2 text-xs font-bold text-emerald-600">PIN admin saat ini sudah tersimpan di akun ini.</p>
                        @endif
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-5 py-4 text-sm font-black text-white shadow-lg shadow-primary/20 transition hover:brightness-110">
                        <span class="material-symbols-outlined text-base">save</span>
                        Simpan Pembayaran & Rekening
                    </button>
                </aside>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const form = document.querySelector('form[action="/backoffice/settings/payment-settings"]');
            if (!form) return;

            const bankSelect = form.elements.bank_account_id;
            const nameInput = form.elements.bank_name;
            const numberInput = form.elements.bank_account_number;
            const holderInput = form.elements.bank_account_name;
            const profileName = document.querySelector('[data-bank-profile="name"]');
            const profileNumber = document.querySelector('[data-bank-profile="number"]');
            const profileHolder = document.querySelector('[data-bank-profile="holder"]');

            const selectedBankData = () => {
                const option = bankSelect?.selectedOptions?.[0];
                return {
                    name: option?.dataset.bankName || option?.textContent?.trim() || '',
                    number: option?.dataset.bankNumber || '',
                    holder: option?.dataset.bankHolder || '',
                };
            };

            const renderSelectedBank = () => {
                const bank = selectedBankData();
                profileName.textContent = bank.name || 'Pilih akun bank/QRIS dulu';
                profileNumber.textContent = bank.number ? `No. ${bank.number}` : 'Nomor rekening belum diisi di detail akun.';
                profileHolder.textContent = bank.holder ? `a.n. ${bank.holder}` : 'Atas nama belum diisi.';
            };

            const copySelectedBank = () => {
                const bank = selectedBankData();
                if (bank.name) nameInput.value = bank.name;
                if (bank.number) numberInput.value = bank.number;
                if (bank.holder) holderInput.value = bank.holder;
            };

            bankSelect?.addEventListener('change', renderSelectedBank);
            document.querySelector('[data-copy-bank-profile]')?.addEventListener('click', copySelectedBank);
            renderSelectedBank();
        })();
    </script>
@endsection
