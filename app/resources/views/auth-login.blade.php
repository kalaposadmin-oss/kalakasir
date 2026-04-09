@extends('layouts.app')

@section('page_title', ($loginTitle ?? 'Login') . ' - POS Inventory System')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto p-0')

@section('content')
    @php
        $loginAction = $loginAction ?? '/login';
        $loginTitle = $loginTitle ?? 'Selamat Datang';
        $loginSubtitle = $loginSubtitle ?? 'Masuk untuk mengelola bisnis Anda';
        $showRegisterLink = $showRegisterLink ?? true;
        $registerHref = $registerHref ?? '/register';
        $portalLabel = $portalLabel ?? null;
        $portalTone = $portalTone ?? 'primary';
        $portalBadgeClass = $portalTone === 'slate'
            ? 'rounded-full bg-slate-900/10 px-3 py-1 text-[11px] font-semibold tracking-[0.12em] text-slate-700 dark:bg-slate-100/10 dark:text-slate-200'
            : 'rounded-full bg-primary/10 px-3 py-1 text-[11px] font-semibold tracking-[0.12em] text-primary';
    @endphp

    <div class="relative flex min-h-screen w-full flex-col px-6 py-12 items-center justify-between">
        <div class="w-full flex flex-col items-center mt-8">
            @if ($portalLabel)
                <span class="{{ $portalBadgeClass }}">{{ $portalLabel }}</span>
            @endif
            <div class="size-20 bg-primary/10 rounded-2xl flex items-center justify-center mb-6">
                <span class="material-symbols-outlined text-primary text-[48px]">storefront</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $loginTitle }}</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 text-center">{{ $loginSubtitle }}</p>
        </div>

        <div class="w-full max-w-sm flex-1 flex flex-col justify-center mt-10">
            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form id="login-form" method="POST" action="{{ $loginAction }}" class="space-y-5">
                @csrf
                <div class="space-y-1.5">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-300 ml-1" for="email">Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <span class="material-symbols-outlined text-slate-400 text-[20px]">mail</span>
                        </div>
                        <input name="email" value="{{ old('email') }}" class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-xl text-sm transition-all duration-200" id="email" placeholder="nama@toko.com" type="email" required />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <div class="flex justify-between items-center ml-1">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-300" for="password">Kata Sandi</label>
                        <a class="text-xs font-semibold text-primary hover:underline" href="#">Lupa Kata Sandi?</a>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <span class="material-symbols-outlined text-slate-400 text-[20px]">lock</span>
                        </div>
                        <input name="password" class="block w-full pl-11 pr-12 py-3.5 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-xl text-sm transition-all duration-200" id="password" placeholder="••••••••" type="password" required />
                        <button id="toggle-password" class="absolute inset-y-0 right-0 pr-3.5 flex items-center" type="button" aria-label="Tampilkan kata sandi">
                            <span class="material-symbols-outlined text-slate-400 text-[20px]">visibility_off</span>
                        </button>
                    </div>
                </div>

                <button class="w-full bg-primary text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/25 active:scale-[0.98] transition-all duration-200 mt-4" type="submit">
                    Masuk
                </button>
            </form>

            <div class="mt-8 flex flex-col items-center">
                <div class="flex items-center gap-4 w-full mb-6">
                    <div class="h-px bg-slate-200 dark:border-slate-800 flex-1"></div>
                    <span class="text-xs text-slate-400 font-medium">Atau masuk dengan</span>
                    <div class="h-px bg-slate-200 dark:border-slate-800 flex-1"></div>
                </div>
                <button class="size-14 rounded-full border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-50" type="button">
                    <span class="material-symbols-outlined text-[32px]">fingerprint</span>
                </button>
            </div>
        </div>

        <div class="w-full text-center pb-4">
            @if ($showRegisterLink)
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Belum punya akun?
                    <a class="text-primary font-bold ml-1" href="{{ $registerHref }}">Daftar Sekarang</a>
                </p>
            @else
                <div class="space-y-1 text-sm text-slate-500 dark:text-slate-400">
                    <p>
                        Belum punya akun?
                        <a class="text-primary font-bold ml-1" href="{{ $registerHref }}">Daftar di /register</a>
                    </p>
                    <p>
                        Portal utama tersedia di
                        <a class="text-primary font-bold ml-1" href="/login">/login</a>
                    </p>
                </div>
            @endif
        </div>
    </div>

    <script>
        (() => {
            const toggle = document.getElementById('toggle-password');
            const input = document.getElementById('password');
            if (!toggle || !input) {
                return;
            }
            toggle.addEventListener('click', () => {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                toggle.querySelector('span').textContent = isPassword ? 'visibility' : 'visibility_off';
            });
        })();
    </script>
@endsection
