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
                        Pusat pembayaran
                    </div>
                    <h2 class="mt-4 text-2xl font-black tracking-tight text-slate-950 dark:text-white lg:text-3xl">Atur uang pelanggan masuk ke mana.</h2>
                    <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                        Halaman ini menyatukan akun kas/bank untuk POS dengan instruksi pembayaran yang dikirim ke pelanggan. Owner cukup mengatur satu tempat, laporan tetap memakai akun pembukuan yang sama.
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

                <aside class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-100">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined mt-0.5 text-amber-600 dark:text-amber-300">info</span>
                        <div>
                            <h3 class="text-base font-black">Satu menu, dua fungsi</h3>
                            <p class="mt-2 text-sm leading-relaxed">
                                Akun kas/bank dipakai untuk jurnal dan laporan. Nomor rekening, QRIS, dan catatan dipakai untuk tampilan pelanggan/order online. Keduanya disimpan dari form yang sama agar tidak terasa dobel.
                            </p>
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
                                <p class="text-xs font-bold uppercase tracking-[0.22em] text-slate-400">Dipakai POS dan laporan</p>
                                <h3 class="mt-2 text-lg font-black text-slate-950 dark:text-white">Rekening penerimaan</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">Pilih akun yang dipakai saat kasir menerima pembayaran. Cash masuk ke akun kas, QRIS dan transfer masuk ke akun bank/QRIS.</p>
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
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Cash / Tunai masuk ke</span>
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
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">QRIS / transfer masuk ke</span>
                                <select name="bank_account_id" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm dark:border-slate-800 dark:bg-slate-800">
                                    <option value="">Pilih akun bank/QRIS</option>
                                    @foreach ($paymentAccounts as $account)
                                        <option value="{{ $account->id }}" @selected((string) $selectedBankAccountId === (string) $account->id)>
                                            {{ trim(($account->code ? $account->code.' - ' : '').$account->name) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300">
                            Butuh BCA, Mandiri, atau QRIS terpisah? Tambahkan akun di
                            <a href="/backoffice/settings/chart-of-accounts" class="font-bold text-primary">Kelola akun kas/bank lanjutan</a>,
                            lalu pilih sebagai rekening penerimaan di sini.
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.22em] text-slate-400">Dipakai pelanggan/order online</p>
                                <h3 class="mt-2 text-lg font-black text-slate-950 dark:text-white">Instruksi transfer dan QRIS</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">Data ini muncul di pesan WhatsApp pembayaran, final review order online, dan catatan pembayaran pelanggan.</p>
                            </div>
                            <span class="material-symbols-outlined text-3xl text-emerald-600/70">qr_code_2</span>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Nama Bank</span>
                                <input name="bank_name" value="{{ old('bank_name', $paymentSetting->bank_name ?? '') }}" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm dark:border-slate-800 dark:bg-slate-800" placeholder="Contoh: BCA" />
                            </label>
                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Nomor Rekening</span>
                                <input name="bank_account_number" value="{{ old('bank_account_number', $paymentSetting->bank_account_number ?? '') }}" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm dark:border-slate-800 dark:bg-slate-800" placeholder="1234567890" />
                            </label>
                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Atas Nama</span>
                                <input name="bank_account_name" value="{{ old('bank_account_name', $paymentSetting->bank_account_name ?? '') }}" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-3 text-sm dark:border-slate-800 dark:bg-slate-800" placeholder="Nama pemilik rekening" />
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
                                    <p class="mt-1 text-base font-black text-slate-950 dark:text-white">{{ old('bank_name', $paymentSetting->bank_name ?? '') ?: 'Nama bank belum diisi' }}</p>
                                </div>
                                <span class="material-symbols-outlined text-primary">account_balance</span>
                            </div>
                            <dl class="mt-4 space-y-2 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">No. rekening</dt>
                                    <dd class="font-bold text-slate-900 dark:text-white">{{ old('bank_account_number', $paymentSetting->bank_account_number ?? '') ?: '-' }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Atas nama</dt>
                                    <dd class="font-bold text-slate-900 dark:text-white">{{ old('bank_account_name', $paymentSetting->bank_account_name ?? '') ?: '-' }}</dd>
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
@endsection
