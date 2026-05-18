@php
    $loginAction = $loginAction ?? '/login';
    $loginTitle = $loginTitle ?? 'Selamat Datang';
    $loginSubtitle = $loginSubtitle ?? 'Masuk untuk mengelola bisnis Anda';
    $showRegisterLink = $showRegisterLink ?? true;
    $registerHref = $registerHref ?? '/register';
    $portalLabel = $portalLabel ?? null;
    $portalTone = $portalTone ?? 'primary';
    $isSuperadminLogin = $portalLabel === 'SUPERADMIN';
    $isBackofficeLogin = in_array($portalLabel, ['BACKOFFICE', 'SUPERADMIN'], true);
    $loginButtonLabel = $loginButtonLabel ?? ($isSuperadminLogin ? 'Masuk Superadmin' : 'Masuk Backoffice');
    $accessBoxTitle = $accessBoxTitle ?? ($isSuperadminLogin ? 'Akses superadmin' : 'Akses backoffice');
    $accessBoxDescription = $accessBoxDescription ?? ($isSuperadminLogin
        ? 'Gunakan akun superadmin yang sudah terdaftar.'
        : 'Gunakan akun owner, admin, supervisor, atau gudang yang sudah terdaftar.');
    $workspaceLabel = $isSuperadminLogin ? 'Platform Control' : 'Owner Workspace';
    $heroTitle = $isSuperadminLogin
        ? 'Kontrol platform yang rapi, aman, dan siap dipantau.'
        : 'Kendali toko yang rapi, cepat, dan siap dipakai.';
    $heroDescription = $isSuperadminLogin
        ? 'Masuk ke konsol superadmin untuk memantau tenant, insiden, billing, dan kesehatan sistem dari satu layar.'
        : 'Masuk ke area laporan, stok, produk, pembelian, dan pengaturan tanpa tampilan kasir yang sempit.';
    $showMenu = false;
    $portalBadgeClass = $portalTone === 'slate'
        ? 'rounded-full bg-slate-900/10 px-3 py-1 text-[11px] font-semibold tracking-[0.12em] text-slate-700 dark:bg-slate-100/10 dark:text-slate-200'
        : 'rounded-full bg-primary/10 px-3 py-1 text-[11px] font-semibold tracking-[0.12em] text-primary';
@endphp

@extends('layouts.app')

@section('page_title', ($loginTitle ?? 'Login') . ' - POS Inventory System')
@section('ui_mode', 'auth')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100')
@section('app_shell_class', $isBackofficeLogin ? 'relative flex min-h-screen w-full flex-col overflow-hidden bg-slate-50 dark:bg-background-dark' : 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', $isBackofficeLogin ? 'flex-1 overflow-y-auto p-0 bg-slate-50 dark:bg-background-dark' : 'flex-1 overflow-y-auto p-0')

@section('content')
    @if ($isBackofficeLogin)
        <div class="grid min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-100 lg:grid-cols-[minmax(0,1.05fr)_minmax(480px,0.95fr)]">
            <section class="hidden border-r border-slate-200 bg-white px-10 py-8 dark:border-slate-800 dark:bg-slate-950 lg:flex lg:flex-col">
                <div class="flex items-center justify-between gap-4">
                    <a class="flex items-center gap-3" href="/backoffice">
                        <span class="flex size-11 items-center justify-center rounded-lg bg-primary text-base font-black text-white shadow-sm shadow-primary/25">K</span>
                        <span>
                            <span class="block text-sm font-black uppercase tracking-[0.18em]">Kalakasir</span>
                            <span class="block text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $isSuperadminLogin ? 'Superadmin' : 'Backoffice' }}</span>
                        </span>
                    </a>
                    <a class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:border-primary/40 dark:border-slate-800 dark:text-slate-200" href="/">
                        <span class="material-symbols-outlined text-[17px] text-primary">point_of_sale</span>
                        Buka POS
                    </a>
                </div>

                <div class="flex flex-1 flex-col justify-center">
                    <p class="text-[11px] font-bold uppercase tracking-[0.26em] text-primary">{{ $workspaceLabel }}</p>
                    <h1 class="mt-4 max-w-xl text-4xl font-black leading-tight tracking-tight text-slate-950 dark:text-white">
                        {{ $heroTitle }}
                    </h1>
                    <p class="mt-4 max-w-lg text-sm leading-7 text-slate-500 dark:text-slate-400">
                        {{ $heroDescription }}
                    </p>

                    <div class="mt-8 grid max-w-2xl gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900">
                            <span class="material-symbols-outlined text-[24px] text-primary">dashboard</span>
                            <p class="mt-3 text-sm font-black">Dashboard</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $isSuperadminLogin ? 'Status lintas tenant.' : 'Ringkasan harian toko.' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900">
                            <span class="material-symbols-outlined text-[24px] text-emerald-600">inventory_2</span>
                            <p class="mt-3 text-sm font-black">{{ $isSuperadminLogin ? 'Tenant' : 'Inventaris' }}</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $isSuperadminLogin ? 'Kesehatan tenant.' : 'Stok dan data master.' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900">
                            <span class="material-symbols-outlined text-[24px] text-sky-600">bar_chart</span>
                            <p class="mt-3 text-sm font-black">{{ $isSuperadminLogin ? 'Insiden' : 'Laporan' }}</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $isSuperadminLogin ? 'Monitoring sistem.' : 'Penjualan dan keuangan.' }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 border-t border-slate-200 pt-5 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    <div>
                        <p class="text-lg font-black text-slate-950 dark:text-white">Web</p>
                        <p class="mt-1">Desktop ready</p>
                    </div>
                    <div>
                        <p class="text-lg font-black text-slate-950 dark:text-white">{{ $isSuperadminLogin ? 'Secure' : 'Multi-role' }}</p>
                        <p class="mt-1">{{ $isSuperadminLogin ? 'Role superadmin' : 'Owner, admin, gudang' }}</p>
                    </div>
                    <div>
                        <p class="text-lg font-black text-slate-950 dark:text-white">Realtime</p>
                        <p class="mt-1">Data operasional</p>
                    </div>
                </div>
            </section>

            <section class="flex min-h-screen items-center justify-center px-5 py-8 sm:px-8 lg:px-12">
                <div class="w-full max-w-[460px]">
                    <div class="mb-8 lg:hidden">
                        <div class="flex items-center justify-between gap-4">
                            <a class="flex items-center gap-3" href="/backoffice">
                                <span class="flex size-10 items-center justify-center rounded-lg bg-primary text-sm font-black text-white">K</span>
                                <span>
                                    <span class="block text-sm font-black uppercase tracking-[0.16em]">Kalakasir</span>
                                    <span class="block text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $isSuperadminLogin ? 'Superadmin' : 'Backoffice' }}</span>
                                </span>
                            </a>
                            <a class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 dark:border-slate-800 dark:text-slate-200" href="/">
                                POS
                            </a>
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8">
                        <div class="mb-7">
                            @if ($portalLabel)
                                <span class="{{ $portalBadgeClass }}">{{ $portalLabel }}</span>
                            @endif
                            <h1 class="mt-4 text-2xl font-black tracking-tight text-slate-950 dark:text-white">{{ $loginTitle }}</h1>
                            <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $loginSubtitle }}</p>
                        </div>

                        @if ($errors->any())
                            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <form id="login-form" method="POST" action="{{ $loginAction }}" class="space-y-5">
                            @csrf
                            <div class="space-y-1.5">
                                <label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="email">Email</label>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                        <span class="material-symbols-outlined text-[20px] text-slate-400">mail</span>
                                    </div>
                                    <input name="email" value="{{ old('email') }}" class="block w-full rounded-lg border border-slate-200 bg-slate-50 py-3.5 pl-11 pr-4 text-sm font-semibold text-slate-900 outline-none transition focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:focus:bg-slate-950" id="email" placeholder="nama@toko.com" type="email" required autofocus />
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="password">Kata Sandi</label>
                                    <a class="text-xs font-bold text-primary hover:underline" href="#">Lupa Kata Sandi?</a>
                                </div>
                                <div class="relative">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                        <span class="material-symbols-outlined text-[20px] text-slate-400">lock</span>
                                    </div>
                                    <input name="password" class="block w-full rounded-lg border border-slate-200 bg-slate-50 py-3.5 pl-11 pr-12 text-sm font-semibold text-slate-900 outline-none transition focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:focus:bg-slate-950" id="password" placeholder="********" type="password" required />
                                    <button id="toggle-password" class="absolute inset-y-0 right-0 flex items-center pr-3.5" type="button" aria-label="Tampilkan kata sandi">
                                        <span class="material-symbols-outlined text-[20px] text-slate-400">visibility_off</span>
                                    </button>
                                </div>
                            </div>

                            <button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3.5 text-sm font-black text-white shadow-lg shadow-primary/20 transition hover:bg-primary-dark active:scale-[0.99]" type="submit">
                                {{ $loginButtonLabel }}
                                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                            </button>
                        </form>

                        <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-800 dark:bg-slate-950">
                            <div class="flex items-start gap-3">
                                <span class="material-symbols-outlined mt-0.5 text-[20px] text-primary">admin_panel_settings</span>
                                <div>
                                    <p class="font-bold text-slate-900 dark:text-slate-100">{{ $accessBoxTitle }}</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $accessBoxDescription }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
                        Butuh kasir untuk transaksi harian?
                        <a class="font-black text-primary hover:underline" href="/">Buka POS</a>
                    </p>
                </div>
            </section>
        </div>
    @else
        <div class="relative flex min-h-screen w-full flex-col items-center justify-between px-6 py-12">
            <div class="mt-8 flex w-full flex-col items-center">
                @if ($portalLabel)
                    <span class="{{ $portalBadgeClass }}">{{ $portalLabel }}</span>
                @endif
                <div class="mb-6 flex size-20 items-center justify-center rounded-2xl bg-primary/10">
                    <span class="material-symbols-outlined text-[48px] text-primary">storefront</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $loginTitle }}</h1>
                <p class="mt-2 text-center text-slate-500 dark:text-slate-400">{{ $loginSubtitle }}</p>
                @if ($portalLabel === 'POS')
                    <p class="mt-4 text-center text-sm text-slate-500 dark:text-slate-400">
                        Butuh laporan, data master, atau pengaturan toko?
                        <a class="font-semibold text-primary hover:underline" href="/backoffice">Buka Backoffice</a>
                    </p>
                @endif
            </div>

            <div class="mt-10 flex w-full max-w-sm flex-1 flex-col justify-center">
                @if ($errors->any())
                    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if ($loginAction === '/login' && ! $portalLabel)
                    <div class="mb-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-left dark:border-slate-800 dark:bg-slate-900/60">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">2 area kerja</p>
                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-xl border border-primary/20 bg-white px-3 py-3 dark:border-primary/25 dark:bg-slate-950">
                                <p class="font-semibold text-slate-900 dark:text-white">POS</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Untuk kasir dan operasional transaksi harian.</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white px-3 py-3 dark:border-slate-700 dark:bg-slate-950">
                                <p class="font-semibold text-slate-900 dark:text-white">Backoffice</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Untuk owner, admin, laporan, dan pengaturan toko.</p>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <a class="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-primary/20 transition hover:brightness-110" href="/">Masuk POS</a>
                            <a class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-900" href="/backoffice">Masuk Backoffice</a>
                        </div>
                    </div>
                @endif

                <form id="login-form" method="POST" action="{{ $loginAction }}" class="space-y-5">
                    @csrf
                    <div class="space-y-1.5">
                        <label class="ml-1 text-sm font-semibold text-slate-700 dark:text-slate-300" for="email">Email</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                <span class="material-symbols-outlined text-[20px] text-slate-400">mail</span>
                            </div>
                            <input name="email" value="{{ old('email') }}" class="block w-full rounded-xl border-slate-200 bg-slate-50 py-3.5 pl-11 pr-4 text-sm transition-all duration-200 dark:border-slate-700 dark:bg-slate-800" id="email" placeholder="nama@toko.com" type="email" required />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <div class="ml-1 flex items-center justify-between">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300" for="password">Kata Sandi</label>
                            <a class="text-xs font-semibold text-primary hover:underline" href="#">Lupa Kata Sandi?</a>
                        </div>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                <span class="material-symbols-outlined text-[20px] text-slate-400">lock</span>
                            </div>
                            <input name="password" class="block w-full rounded-xl border-slate-200 bg-slate-50 py-3.5 pl-11 pr-12 text-sm transition-all duration-200 dark:border-slate-700 dark:bg-slate-800" id="password" placeholder="********" type="password" required />
                            <button id="toggle-password" class="absolute inset-y-0 right-0 flex items-center pr-3.5" type="button" aria-label="Tampilkan kata sandi">
                                <span class="material-symbols-outlined text-[20px] text-slate-400">visibility_off</span>
                            </button>
                        </div>
                    </div>

                    <button class="mt-4 w-full rounded-xl bg-primary py-4 font-bold text-white shadow-lg shadow-primary/25 transition-all duration-200 active:scale-[0.98]" type="submit">
                        Masuk
                    </button>
                </form>

                <div class="mt-8 flex flex-col items-center">
                    <div class="mb-6 flex w-full items-center gap-4">
                        <div class="h-px flex-1 bg-slate-200 dark:border-slate-800"></div>
                        <span class="text-xs font-medium text-slate-400">Atau masuk dengan</span>
                        <div class="h-px flex-1 bg-slate-200 dark:border-slate-800"></div>
                    </div>
                    <button class="flex size-14 items-center justify-center rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300" type="button">
                        <span class="material-symbols-outlined text-[32px]">fingerprint</span>
                    </button>
                </div>
            </div>

            <div class="w-full pb-4 text-center">
                @if ($showRegisterLink)
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Belum punya akun?
                        <a class="ml-1 font-bold text-primary" href="{{ $registerHref }}">Daftar Sekarang</a>
                    </p>
                @else
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Belum punya akun?
                        <a class="ml-1 font-bold text-primary" href="{{ $registerHref }}">Daftar bisnis baru</a>
                    </p>
                @endif
            </div>
        </div>
    @endif

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
