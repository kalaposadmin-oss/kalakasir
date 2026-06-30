@extends('layouts.app')

@section('page_title', 'Pendaftaran Akun Baru')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[768px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto p-0 app-bottom-safe-space')

@section('content')
    <div class="flex items-center bg-white dark:bg-background-dark p-4 pb-2 justify-between">
        <a href="/" class="text-slate-900 dark:text-slate-100 flex size-12 shrink-0 items-center justify-start cursor-pointer">
            <span class="material-symbols-outlined text-2xl">arrow_back_ios</span>
        </a>
        <h2 class="text-slate-900 dark:text-slate-100 text-lg font-bold leading-tight tracking-tight flex-1 text-center pr-12">Pendaftaran Akun Baru</h2>
    </div>

    <div class="flex flex-col gap-3 p-4">
        <div class="flex gap-6 justify-between items-center">
            <p class="text-slate-900 dark:text-slate-100 text-base font-medium leading-normal">Langkah 1: Registrasi Pengguna</p>
            <p class="text-slate-500 dark:text-slate-400 text-sm font-normal leading-normal">1 dari 5</p>
        </div>
        <div class="rounded-full bg-slate-200 dark:bg-slate-700 h-2 overflow-hidden">
            <div class="h-full bg-primary" style="width: 20%;"></div>
        </div>
    </div>

    <div class="px-4 pt-4 pb-2">
        <h3 class="text-slate-900 dark:text-slate-100 tracking-tight text-2xl font-bold leading-tight">Buat Akun Anda</h3>
        <p class="text-slate-600 dark:text-slate-400 text-base font-normal leading-normal mt-2">
            Lengkapi data pemilik atau pengelola utama toko. Akun pertama ini otomatis menjadi Owner agar bisa mengatur produk, stok, kas, dan tim.
        </p>
        <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-relaxed text-amber-900">
            Kasir dan staff lain ditambahkan nanti dari menu pengaturan pengguna setelah onboarding selesai.
        </div>
    </div>

    @if ($errors->any())
        <div class="mx-4 mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form id="onboarding-step-1-form" method="POST" action="/register/step-1" class="flex flex-col gap-4 px-4 py-4 pb-28 app-bottom-safe-space">
        @csrf
        <label class="flex flex-col gap-2">
            <span class="text-slate-900 dark:text-slate-100 text-sm font-semibold leading-normal">Nama Lengkap</span>
            <div class="relative">
                <input name="name" value="{{ old('name', session('onboarding.step1.name')) }}" class="form-input flex w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 h-14 p-[15px] pl-12 text-base placeholder:text-slate-400" placeholder="Masukkan nama lengkap" type="text" required />
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">person</span>
            </div>
        </label>

        <label class="flex flex-col gap-2">
            <span class="text-slate-900 dark:text-slate-100 text-sm font-semibold leading-normal">Alamat Email</span>
            <div class="relative">
                <input name="email" value="{{ old('email', session('onboarding.step1.email')) }}" class="form-input flex w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 h-14 p-[15px] pl-12 text-base placeholder:text-slate-400" placeholder="nama@email.com" type="email" required />
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">mail</span>
            </div>
        </label>

        <label class="flex flex-col gap-2">
            <span class="text-slate-900 dark:text-slate-100 text-sm font-semibold leading-normal">Nomor Telepon</span>
            <div class="relative">
                <input name="phone" value="{{ old('phone', session('onboarding.step1.phone')) }}" class="form-input flex w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 h-14 p-[15px] pl-12 text-base placeholder:text-slate-400" placeholder="0812xxxxxx" type="tel" />
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">phone_iphone</span>
            </div>
        </label>

        <label class="flex flex-col gap-2">
            <span class="text-slate-900 dark:text-slate-100 text-sm font-semibold leading-normal">Kata Sandi</span>
            <div class="relative">
                <input id="password" name="password" value="{{ old('password', session('onboarding.step1.password')) }}" class="form-input flex w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 h-14 p-[15px] pl-12 pr-12 text-base placeholder:text-slate-400" placeholder="Minimal 8 karakter" type="password" required />
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">lock</span>
                <button id="toggle-password" class="absolute inset-y-0 right-0 px-4 flex items-center" type="button" aria-label="Tampilkan kata sandi">
                    <span class="material-symbols-outlined text-slate-400">visibility_off</span>
                </button>
            </div>
        </label>

        <label class="flex flex-col gap-2">
            <span class="text-slate-900 dark:text-slate-100 text-sm font-semibold leading-normal">Konfirmasi Kata Sandi</span>
            <div class="relative">
                <input id="password-confirmation" name="password_confirmation" value="{{ old('password_confirmation', session('onboarding.step1.password')) }}" class="form-input flex w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 h-14 p-[15px] pl-12 pr-12 text-base placeholder:text-slate-400" placeholder="Ulangi kata sandi" type="password" required />
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">verified_user</span>
                <button id="toggle-password-confirmation" class="absolute inset-y-0 right-0 px-4 flex items-center" type="button" aria-label="Tampilkan konfirmasi kata sandi">
                    <span class="material-symbols-outlined text-slate-400">visibility_off</span>
                </button>
            </div>
        </label>

        <div class="flex items-start gap-3 mt-2">
            <input name="accept_terms" {{ old('accept_terms') ? 'checked' : '' }} class="rounded border-slate-300 text-primary focus:ring-primary mt-1" type="checkbox" value="1" required />
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                Saya menyetujui <span class="text-primary font-semibold">Syarat dan Ketentuan</span> serta <span class="text-primary font-semibold">Kebijakan Privasi</span>.
            </p>
        </div>
    </form>

    <div class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] p-4 bg-white dark:bg-background-dark border-t border-slate-100 dark:border-slate-800 app-bottom-safe-footer">
        <button form="onboarding-step-1-form" type="submit" class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2">
            Lanjut
            <span class="material-symbols-outlined text-xl">chevron_right</span>
        </button>
    </div>

    <script>
        (() => {
            const bindPasswordToggle = (toggleId, inputId) => {
                const toggle = document.getElementById(toggleId);
                const input = document.getElementById(inputId);
                if (!toggle || !input) {
                    return;
                }

                toggle.addEventListener('click', () => {
                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                    toggle.querySelector('span').textContent = isPassword ? 'visibility' : 'visibility_off';
                });
            };

            bindPasswordToggle('toggle-password', 'password');
            bindPasswordToggle('toggle-password-confirmation', 'password-confirmation');
        })();
    </script>
@endsection
