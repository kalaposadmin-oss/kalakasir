@extends('layouts.app')

@php
    $showMenu = true;
    $posDashboardTenant = \App\Support\TenantContext::get();
    $posDashboardUser = auth()->user();
    $posDashboardRole = null;

    if (! $posDashboardTenant && filled($posDashboardUser?->tenant_id)) {
        $posDashboardTenant = \App\Models\Tenant::query()->find($posDashboardUser->tenant_id);
    }

    if ($posDashboardTenant && $posDashboardUser) {
        $posDashboardRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $posDashboardTenant->id, (string) $posDashboardUser->id);
    }

    $showsCashTransactionShortcuts = $posDashboardTenant
        && in_array($posDashboardRole, ['cashier', 'staff', 'warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true)
        && app(\App\Services\SubscriptionService::class)->canAccessFeature($posDashboardTenant, 'finance.cash_transactions');
    $showsOrderOnlineShortcuts = $posDashboardTenant
        && in_array($posDashboardRole, ['supervisor', 'admin', 'owner', 'superadmin'], true);
    $showsPeriodClosingReminder = $posDashboardTenant
        && in_array($posDashboardRole, ['supervisor', 'admin', 'owner', 'superadmin'], true)
        && app(\App\Services\SubscriptionService::class)->canAccessFeature($posDashboardTenant, 'finance.lock_period');
@endphp

@section('page_title', 'Dashboard POS')
@section('body_class', 'bg-[#f8f9ff] font-display text-slate-900 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[768px] flex-col overflow-hidden bg-[#f8f9ff] shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto pb-28')
@php($bottom_nav = 'dashboard')

@push('head')
    <style>
        .ios-shadow,
        .ios-shadow-sm,
        .pos-home-card {
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.06), 0 1px 6px rgb(15 23 42 / 0.04);
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .pos-home-card {
            border: 1px solid rgb(226 232 240);
            border-radius: 1rem;
            background: #fff;
        }
        .dark .pos-home-card {
            border-color: rgb(30 41 59);
            background: rgb(15 23 42);
        }
        .pos-main-action,
        .pos-side-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            border-radius: .875rem;
            font-size: .8125rem;
            font-weight: 800;
            line-height: 1;
        }
        .pos-main-action {
            min-height: 3.25rem;
            width: 100%;
        }
        .pos-side-action {
            min-height: 2.75rem;
            border: 1px solid rgb(226 232 240);
            background: #fff;
            color: rgb(51 65 85);
        }
        .pos-side-action.hidden {
            display: none;
        }
        .dark .pos-side-action {
            border-color: rgb(30 41 59);
            background: rgb(2 6 23);
            color: rgb(226 232 240);
        }
        .pos-metric-card {
            min-width: 0;
            border: 1px solid rgb(226 232 240);
            border-radius: .875rem;
            background: #fff;
            padding: .75rem;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.05);
        }
        .dark .pos-metric-card {
            border-color: rgb(30 41 59);
            background: rgb(15 23 42);
        }
        .pos-app-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .625rem;
        }
        .pos-app-tile {
            display: flex;
            min-height: 4.75rem;
            min-width: 0;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 1px solid rgb(226 232 240);
            border-radius: .875rem;
            background: #fff;
            padding: .55rem .35rem;
            text-align: center;
            transition: transform 140ms ease, border-color 140ms ease, background-color 140ms ease;
        }
        .pos-app-tile:active {
            transform: scale(.97);
        }
        .dark .pos-app-tile {
            border-color: rgb(30 41 59);
            background: rgb(2 6 23);
        }
        .pos-app-icon {
            display: inline-flex;
            width: 2.25rem;
            height: 2.25rem;
            align-items: center;
            justify-content: center;
            border: 1px solid;
            border-radius: .75rem;
            flex-shrink: 0;
        }
        @media (max-width: 374px) {
            .pos-app-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        .pos-home-screen{--pos-blue:#1e3a8a;--pos-ink:#111c2d;--pos-line:#e2e6f2;min-height:100%;color:var(--pos-ink);font-family:Inter,ui-sans-serif,system-ui,sans-serif;background:#f8f9ff}
        .pos-home-screen .text-primary{color:var(--pos-blue)!important}.pos-home-screen .bg-primary{background-color:var(--pos-blue)!important}
        .pos-home-header{background:rgb(248 249 255/.92);box-shadow:0 1px 0 rgb(199 196 216/.35);backdrop-filter:blur(18px)}
        .pos-home-screen .pos-home-card,.pos-home-screen .pos-metric-card,.pos-home-screen .pos-app-tile{border-color:var(--pos-line);border-radius:1rem;box-shadow:0 2px 5px rgb(17 28 45/.05)}
        .pos-home-screen .pos-main-action{background:linear-gradient(135deg,var(--pos-blue),#273cf3 65%,#5b5cf6)!important;box-shadow:0 12px 24px rgb(53 72 245/.24)}
        .pos-home-content>.pos-summary-grid{order:1}.pos-home-content>.pos-quick-section{order:2}.pos-home-content>.pos-shift-section{order:3}.pos-home-content>.pos-dashboard-widgets{order:4}
        .pos-home-screen .pos-metric-card{min-height:5.75rem;padding:.8rem}.pos-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
        .pos-summary-grid>.pos-metric-card:not(.pos-sales-hero){display:grid;grid-template-columns:2.75rem minmax(0,1fr);grid-template-rows:auto auto;column-gap:.7rem;align-items:center}
        .pos-summary-grid>.pos-metric-card:not(.pos-sales-hero)>.material-symbols-outlined{grid-row:1/3;display:flex!important;width:2.75rem!important;height:2.75rem!important;align-items:center;justify-content:center;overflow:visible!important;padding:0!important;font-size:1.55rem!important;font-variation-settings:'FILL' 1,'wght' 500,'GRAD' 0,'opsz' 28}
        .pos-summary-grid>.pos-metric-card:not(.pos-sales-hero)>p{margin-top:0!important;line-height:1.2}.pos-summary-grid>.pos-metric-card:not(.pos-sales-hero)>p:last-child{align-self:start}
        .pos-summary-grid>.pos-sales-hero{grid-column:1/-1;min-height:14.5rem;border:0;border-radius:1.5rem;padding:1.75rem;color:#fff;background-color:#1e3a8a;background-image:radial-gradient(at 0% 0%,#1e00a9 0,transparent 50%),radial-gradient(at 100% 0%,#b1afff 0,transparent 50%),radial-gradient(at 100% 100%,#3525cd 0,transparent 50%),radial-gradient(at 0% 100%,#4d44e3 0,transparent 50%);box-shadow:0 16px 30px rgb(30 58 138/.22)}
        .pos-sales-hero .material-symbols-outlined{background:rgb(255 255 255/.18)!important;color:#fff!important}.pos-sales-hero p{color:#fff!important}.pos-sales-hero #kpi-sales{max-width:15rem;white-space:normal;font-size:2.25rem;line-height:1.08;letter-spacing:-.04em}
        .pos-sales-hero::after{content:'Ringkasan penjualan real-time';position:absolute;right:1.75rem;bottom:1.5rem;left:1.75rem;border-top:1px solid rgb(255 255 255/.18);padding-top:.75rem;color:rgb(255 255 255/.75);font-size:.7rem;font-weight:700}.pos-sales-hero{position:relative;overflow:hidden}.pos-summary-grid>:nth-child(4){grid-column:1/-1;min-height:5.25rem}
        .pos-home-screen .pos-app-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem}.pos-home-screen .pos-app-tile{min-height:7.1rem;padding:.7rem .25rem;border-radius:1rem}.pos-home-screen .pos-app-icon{width:3rem;height:3rem;border:0;border-radius:.75rem}.pos-home-screen .pos-app-tile span:last-child{font-size:.68rem}.pos-home-screen .pos-app-icon .material-symbols-outlined{font-size:1.8rem!important;font-variation-settings:'FILL' 1,'wght' 500,'GRAD' 0,'opsz' 28}.pos-home-screen .pos-main-action .material-symbols-outlined{font-size:1.55rem!important}.pos-home-screen .pos-side-action .material-symbols-outlined{font-size:1.4rem!important}.pos-home-screen .pos-bottom-item>.material-symbols-outlined{font-size:1.55rem!important}
        .pos-bottom-nav{position:absolute;right:0;bottom:0;left:0;z-index:35;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));border-top:1px solid var(--pos-line);border-radius:1.5rem 1.5rem 0 0;background:rgb(255 255 255/.97);padding:.5rem 1rem max(.65rem,env(safe-area-inset-bottom));box-shadow:0 -10px 30px rgb(17 28 45/.09);backdrop-filter:blur(18px)}
        .pos-bottom-item{display:flex;min-width:0;min-height:3.4rem;flex-direction:column;align-items:center;justify-content:center;gap:.15rem;border-radius:.9rem;color:#8b94ae;font-size:.62rem;font-weight:800}.pos-bottom-item.is-active{color:var(--pos-blue)}.pos-bottom-item .material-symbols-outlined{font-size:1.3rem}
        .pos-bottom-add{width:3.5rem;height:3rem;margin-top:-.2rem;border-radius:1rem;background:#1e3a8a;color:#fff;box-shadow:0 6px 14px rgb(30 58 138/.24)}
        @media(min-width:640px){.pos-home-content{padding:1.5rem 2rem 8rem}.pos-summary-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.pos-summary-grid>.pos-sales-hero{grid-column:1/-1}.pos-summary-grid>:nth-child(4){grid-column:auto;min-height:5.75rem}.pos-home-screen .pos-app-grid{grid-template-columns:repeat(5,minmax(0,1fr))}.pos-dashboard-widgets{grid-template-columns:repeat(2,minmax(0,1fr))}
        }
    </style>
@endpush

@section('content')
    <div class="pos-home-screen"><header class="pos-home-header sticky top-0 z-30 flex items-center justify-between px-5 py-4">
        <div class="flex min-w-0 items-center gap-3">
            <a class="size-10 shrink-0 overflow-hidden rounded-full border-2 border-white bg-[#e7eeff] shadow-sm" href="/menu" aria-label="Buka semua menu"><img alt="Profil kasir" class="h-full w-full object-cover" src="/images/placeholders/user.svg"></a>
            <div class="min-w-0"><p class="truncate text-[11px] font-semibold uppercase tracking-[.08em] text-[#777587]">Halo, {{ $posDashboardUser?->name ?: 'Kasir' }}</p><h1 class="truncate text-xl font-extrabold leading-tight tracking-tight text-[#1e3a8a]"><span class="sr-only">Dashboard Kasir - </span>{{ $posDashboardTenant?->name ?: 'QuickStore' }}</h1></div>
        </div>
        <div class="flex items-center gap-2">
            <a class="flex size-11 items-center justify-center rounded-full bg-[#eff4ff] text-[#1e3a8a] active:scale-95" href="/menu" aria-label="Buka semua menu">
                <span class="material-symbols-outlined text-[22px]">notifications</span>
            </a>
            <form method="POST" action="/logout">
                @csrf
                <button class="flex size-10 items-center justify-center rounded-full bg-slate-100 text-slate-600 active:opacity-60 dark:bg-slate-800 dark:text-slate-300" type="submit" aria-label="Logout">
                    <span class="material-symbols-outlined text-[21px]">logout</span>
                </button>
            </form>
        </div>
    </header>

    <main class="pos-home-content flex flex-col gap-4 p-4">
        @if (session('shift_required'))
            <div id="shift-required-toast" class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800/50 dark:bg-amber-950/30" role="alert">
                <span class="material-symbols-outlined flex-shrink-0 text-xl text-amber-600">warning</span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-amber-800 dark:text-amber-200">Shift Belum Dibuka</p>
                    <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-400">Buka shift dulu sebelum transaksi.</p>
                </div>
                <a href="/pos/shifts/open" class="flex-shrink-0 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-bold text-white active:opacity-80">
                    Buka Shift
                </a>
            </div>
        @endif

        @if ($showsPeriodClosingReminder)
            <div id="pos-period-closing-reminder" class="hidden rounded-2xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-800/50 dark:bg-amber-950/30" role="alert">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined flex-shrink-0 text-xl text-amber-600">notification_important</span>
                    <div class="min-w-0 flex-1">
                        <p id="pos-period-closing-title" class="text-sm font-bold text-amber-800 dark:text-amber-200">Periode sebelumnya perlu ditutup</p>
                        <p id="pos-period-closing-message" class="mt-0.5 text-xs leading-relaxed text-amber-700 dark:text-amber-300">Review checklist tutup buku sebelum laporan dipakai.</p>
                    </div>
                    <a href="/backoffice/finance/lock-periods" class="flex-shrink-0 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-bold text-white active:opacity-80">
                        Cek
                    </a>
                </div>
            </div>
        @endif

        <section class="pos-shift-section pos-home-card overflow-hidden">
            <div class="p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2">
                        <span id="shift-dot" class="flex size-2.5 rounded-full bg-amber-400"></span>
                        <div class="min-w-0">
                            <p id="shift-status" class="truncate text-xs font-black uppercase tracking-wide text-slate-700 dark:text-slate-200">Status: Shift Tidak Aktif</p>
                            <p id="shift-started" class="text-[10px] font-bold text-slate-400">Dimulai: -</p>
                        </div>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-300">Hari ini</span>
                </div>

                <a href="/pos/checkout" class="pos-main-action bg-primary text-white shadow-sm shadow-primary/20">
                    <span class="material-symbols-outlined text-[22px]">point_of_sale</span>
                    Mulai Transaksi
                </a>

                <div class="mt-2 grid grid-cols-2 gap-2">
                    <a id="open-shift-banner" href="/pos/shifts/open" class="pos-side-action hidden bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-300">
                        <span class="material-symbols-outlined text-[18px]">schedule</span>
                        Buka Shift
                    </a>
                    <a id="close-shift-banner" href="/pos/shifts/close" class="pos-side-action hidden bg-rose-50 text-rose-600 dark:bg-rose-950/20 dark:text-rose-300">
                        <span class="material-symbols-outlined text-[18px]">lock_open</span>
                        Tutup Shift
                    </a>
                    <a href="/pos/history" class="pos-side-action">
                        <span class="material-symbols-outlined text-[18px] text-primary">history</span>
                        Riwayat
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 border-t border-slate-100 bg-[#f8f9ff] p-4">
                <div class="rounded-2xl border border-blue-100 bg-white p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><span class="material-symbols-outlined text-[24px]">account_balance_wallet</span></span>
                        <div class="min-w-0"><p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Kas Awal</p><p id="opening-cash" class="mt-1 truncate text-sm font-black text-slate-900">Rp 0</p></div>
                    </div>
                </div>
                <div class="rounded-2xl border border-emerald-100 bg-white p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><span class="material-symbols-outlined text-[24px]">payments</span></span>
                        <div class="min-w-0"><p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Cash</p><p id="shift-cash-sales" class="mt-1 truncate text-sm font-black text-slate-900">Rp 0</p></div>
                    </div>
                </div>
                <div class="rounded-2xl border border-amber-100 bg-white p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700"><span class="material-symbols-outlined text-[24px]">request_quote</span></span>
                        <div class="min-w-0"><p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Piutang</p><p id="shift-receivable-cash" class="mt-1 truncate text-sm font-black text-slate-900">Rp 0</p></div>
                    </div>
                </div>
                <div class="rounded-2xl border border-indigo-100 bg-indigo-50/70 p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700"><span class="material-symbols-outlined text-[24px]">savings</span></span>
                        <div class="min-w-0"><p class="text-[10px] font-black uppercase tracking-wide text-indigo-500">Total Kas</p><p id="shift-sales" class="mt-1 truncate text-sm font-black text-indigo-800">Rp 0</p></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="pos-summary-grid grid gap-3">
            <div class="pos-sales-hero pos-metric-card"><span class="material-symbols-outlined rounded-xl p-2 text-xl">trending_up</span><p class="mt-4 text-sm font-semibold">Total Penjualan Hari Ini</p><p id="kpi-sales" class="mt-2 font-black">Rp 0</p></div>
            <div class="pos-metric-card bg-emerald-50/70"><span class="material-symbols-outlined rounded-xl bg-emerald-100 p-2 text-xl text-emerald-600">receipt_long</span><p class="mt-3 text-[10px] font-black uppercase tracking-wide text-emerald-600">Jumlah Transaksi</p><p id="kpi-orders" class="mt-1 truncate text-base font-black tracking-tight text-[#111a3b]">0</p></div>
            <div class="pos-metric-card bg-violet-50/70"><span class="material-symbols-outlined rounded-xl bg-violet-100 p-2 text-xl text-violet-600">calculate</span><p class="mt-3 text-[10px] font-black uppercase tracking-wide text-violet-600">Rata-rata Belanja</p><p id="kpi-average" class="mt-1 truncate text-base font-black tracking-tight text-[#111a3b]">Rp 0</p></div>
            <div class="pos-metric-card bg-amber-50/70"><span class="material-symbols-outlined rounded-xl bg-amber-100 p-2 text-xl text-amber-600">schedule</span><p class="mt-3 text-[10px] font-black uppercase tracking-wide text-amber-600">Status Shift</p><p id="shift-kpi-status" class="mt-1 truncate text-sm font-black tracking-tight text-[#111a3b]">Belum aktif</p></div>
        </section>

        <section class="pos-quick-section pos-home-card p-4">
            <div class="mb-3 flex items-end justify-between gap-3">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Aplikasi</p>
                    <h2 class="mt-1 text-sm font-black text-slate-900 dark:text-slate-100">Launcher Kasir</h2>
                </div>
                <span class="text-[10px] font-bold text-slate-400">Cepat pakai</span>
            </div>

            <div class="pos-app-grid">
                <a href="/pos/checkout" class="pos-app-tile bg-primary text-white">
                    <span class="pos-app-icon border-white/25 bg-white/15 text-white">
                        <span class="material-symbols-outlined text-[21px]">add_shopping_cart</span>
                    </span>
                    <span class="mt-1.5 w-full truncate text-[11px] font-black">Transaksi</span>
                </a>
                <a href="/pos/history" class="pos-app-tile">
                    <span class="pos-app-icon border-sky-100 bg-sky-50 text-sky-600 dark:border-sky-900/40 dark:bg-sky-950/25 dark:text-sky-300">
                        <span class="material-symbols-outlined text-[21px]">history</span>
                    </span>
                    <span class="mt-1.5 w-full truncate text-[11px] font-black text-slate-800 dark:text-slate-100">Riwayat</span>
                </a>
                <a href="/pos/receivables" class="pos-app-tile">
                    <span class="pos-app-icon border-amber-100 bg-amber-50 text-amber-600 dark:border-amber-900/40 dark:bg-amber-950/25 dark:text-amber-300">
                        <span class="material-symbols-outlined text-[21px]">request_quote</span>
                    </span>
                    <span class="mt-1.5 w-full truncate text-[11px] font-black text-slate-800 dark:text-slate-100">Piutang</span>
                </a>
                @if ($showsCashTransactionShortcuts)
                    <a href="/pos/cash-in" class="pos-app-tile">
                        <span class="pos-app-icon border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/40 dark:bg-emerald-950/25 dark:text-emerald-300">
                            <span class="material-symbols-outlined text-[21px]">payments</span>
                        </span>
                        <span class="mt-1.5 w-full truncate text-[11px] font-black text-slate-800 dark:text-slate-100">Kas</span>
                    </a>
                @endif
                @if ($showsOrderOnlineShortcuts)
                    <a href="/monitor/orders" class="pos-app-tile">
                        <span class="pos-app-icon border-sky-100 bg-sky-50 text-sky-600 dark:border-sky-900/40 dark:bg-sky-950/25 dark:text-sky-300">
                            <span class="material-symbols-outlined text-[21px]">storefront</span>
                        </span>
                        <span class="mt-1.5 w-full truncate text-[11px] font-black text-slate-800 dark:text-slate-100">Online</span>
                    </a>
                    <a href="/portal/switch/backoffice?next=%2Fbackoffice%2Fsettings%2Fpayment-settings" class="pos-app-tile">
                        <span class="pos-app-icon border-orange-100 bg-orange-50 text-primary dark:border-orange-900/40 dark:bg-orange-950/25">
                            <span class="material-symbols-outlined text-[21px]">payments</span>
                        </span>
                        <span class="mt-1.5 w-full truncate text-[11px] font-black text-slate-800 dark:text-slate-100">Bayar</span>
                    </a>
                @endif
                <a id="open-shift-quick" href="/pos/shifts/open" class="pos-app-tile">
                    <span class="pos-app-icon border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/40 dark:bg-emerald-950/25 dark:text-emerald-300">
                        <span class="material-symbols-outlined text-[21px]">schedule</span>
                    </span>
                    <span class="mt-1.5 w-full truncate text-[11px] font-black text-slate-800 dark:text-slate-100">Buka</span>
                </a>
                <a id="close-shift-quick" href="/pos/shifts/close" class="pos-app-tile">
                    <span class="pos-app-icon border-rose-100 bg-rose-50 text-rose-600 dark:border-rose-900/40 dark:bg-rose-950/25 dark:text-rose-300">
                        <span class="material-symbols-outlined text-[21px]">power_settings_new</span>
                    </span>
                    <span class="mt-1.5 w-full truncate text-[11px] font-black text-slate-800 dark:text-slate-100">Tutup</span>
                </a>
            </div>
        </section>

        <section class="pos-dashboard-widgets grid grid-cols-1 gap-3">
            <div class="pos-home-card p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-slate-400">
                        <span class="material-symbols-outlined text-sm text-primary">stars</span>
                        Terlaris
                    </h2>
                    <span class="text-[10px] font-bold text-slate-400">Bulan ini</span>
                </div>
                <ul id="top-products" class="space-y-2">
                    <li class="text-sm text-slate-500">Memuat data...</li>
                </ul>
            </div>
            <div class="rounded-2xl border border-orange-100 bg-orange-50/70 p-4 dark:border-orange-900/30 dark:bg-orange-950/10">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-orange-500">
                        <span class="material-symbols-outlined text-sm">warning</span>
                        Stok Menipis
                    </h2>
                    <a href="/pos/inventory" class="text-[10px] font-black text-orange-600">Cek stok</a>
                </div>
                <ul id="low-stock" class="space-y-2">
                    <li class="text-sm text-slate-500">Memuat data...</li>
                </ul>
            </div>
        </section>
    </main>
        <nav class="pos-bottom-nav" aria-label="Navigasi utama kasir">
            <a class="pos-bottom-item is-active" href="/pos/dashboard" aria-current="page"><span class="material-symbols-outlined">home</span><span>Beranda</span></a>
            <a class="pos-bottom-item" href="/pos/checkout"><span class="material-symbols-outlined">point_of_sale</span><span>Kasir</span></a>
            <a class="pos-bottom-item" href="/pos/history"><span class="material-symbols-outlined">receipt_long</span><span>Transaksi</span></a>
            <a class="pos-bottom-item" href="/pos/shifts/summary"><span class="material-symbols-outlined">schedule</span><span>Shift</span></a>
            <a class="pos-bottom-item" href="/menu" aria-label="Buka semua menu"><span class="pos-bottom-add flex items-center justify-center"><span class="material-symbols-outlined">apps</span></span><span>Menu</span></a>
        </nav>
    </div>@endsection

@push('scripts')
    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

            const toCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Number(value || 0));

            const disableCard = (element, disabled) => {
                if (!element) {
                    return;
                }
                element.classList.toggle('opacity-50', disabled);
                element.classList.toggle('cursor-not-allowed', disabled);
                if (disabled) {
                    element.setAttribute('aria-disabled', 'true');
                    element.addEventListener('click', preventClickOnce);
                } else {
                    element.removeAttribute('aria-disabled');
                    element.removeEventListener('click', preventClickOnce);
                }
            };

            const preventClickOnce = (event) => {
                event.preventDefault();
            };

            const setShiftState = (isActive, summary = null) => {
                const status = document.getElementById('shift-status');
                const started = document.getElementById('shift-started');
                const dot = document.getElementById('shift-dot');
                const openingCash = document.getElementById('opening-cash');
                const shiftSales = document.getElementById('shift-sales');
                const cashSalesEl = document.getElementById('shift-cash-sales');
                const receivableCashEl = document.getElementById('shift-receivable-cash');
                const closeBanner = document.getElementById('close-shift-banner');
                const openBanner = document.getElementById('open-shift-banner');
                const openQuick = document.getElementById('open-shift-quick');
                const closeQuick = document.getElementById('close-shift-quick');
                const shiftKpiStatus = document.getElementById('shift-kpi-status');

                if (isActive && summary) {
                    status.textContent = 'Status: Shift Aktif';
                    if (shiftKpiStatus) shiftKpiStatus.textContent = 'Sedang berjalan';
                    dot.classList.remove('bg-amber-400');
                    dot.classList.add('bg-primary', 'animate-pulse');
                    started.textContent = `Dimulai: ${summary.shift?.opened_at || '-'}`;
                    openingCash.textContent = toCurrency(summary.shift?.opening_cash || 0);
                    shiftSales.textContent = toCurrency(summary.expected_cash || 0);

                    if (cashSalesEl) cashSalesEl.textContent = toCurrency(summary.cash_sales || 0);
                    if (receivableCashEl) receivableCashEl.textContent = toCurrency(summary.receivable_cash || 0);

                    closeBanner.classList.remove('hidden');
                    openBanner.classList.add('hidden');
                    disableCard(openQuick, true);
                    disableCard(closeQuick, false);
                    return;
                }

                status.textContent = 'Status: Shift Tidak Aktif';
                if (shiftKpiStatus) shiftKpiStatus.textContent = 'Belum aktif';
                dot.classList.remove('bg-primary', 'animate-pulse');
                dot.classList.add('bg-amber-400');
                started.textContent = 'Dimulai: -';
                openingCash.textContent = toCurrency(0);
                shiftSales.textContent = toCurrency(0);
                if (cashSalesEl) cashSalesEl.textContent = toCurrency(0);
                if (receivableCashEl) receivableCashEl.textContent = toCurrency(0);
                closeBanner.classList.add('hidden');
                openBanner.classList.remove('hidden');
                disableCard(openQuick, false);
                disableCard(closeQuick, true);
            };

            const loadShift = async () => {
                try {
                    const response = await window.PosUi.fetchWithTimeout('/api/v1/shifts/summary', { headers }, 10000);
                    if (response.status === 404) {
                        setShiftState(false);
                        return;
                    }
                    if (!response.ok) {
                        setShiftState(false);
                        return;
                    }
                    const payload = await response.json().catch(() => ({}));
                    const data = window.PosUi.unwrapApiPayload(payload) || {};
                    setShiftState(true, data);
                } catch (_) {
                    setShiftState(false);
                }
            };

            const renderTopProducts = (rows) => {
                const topProducts = document.getElementById('top-products');
                if (!rows.length) {
                    topProducts.innerHTML = '<li class="text-sm text-slate-500">Belum ada penjualan bulan ini.</li>';
                    return;
                }

                topProducts.innerHTML = rows.map((row, index) => `
                    <li class="flex justify-between items-center text-sm ${index < rows.length - 1 ? 'border-b border-slate-50 dark:border-slate-800 pb-2' : ''}">
                        <div class="min-w-0 pr-3">
                            <p class="truncate text-slate-700 dark:text-slate-300">${row.name || '-'}</p>
                            <p class="text-[11px] text-slate-400">${toCurrency(row.revenue || 0)}</p>
                        </div>
                        <span class="font-bold whitespace-nowrap">${Number(row.qty || 0)} pcs</span>
                    </li>
                `).join('');
            };

            const renderLowStock = (rows) => {
                const lowStock = document.getElementById('low-stock');
                if (!rows.length) {
                    lowStock.innerHTML = '<li class="text-sm text-slate-500">Stok aman.</li>';
                    return;
                }

                lowStock.innerHTML = rows.map((row) => `
                    <li class="flex justify-between items-center text-sm">
                        <div class="min-w-0 pr-3">
                            <p class="truncate text-slate-700 dark:text-slate-300">${row.name || '-'}</p>
                            <p class="text-[11px] text-slate-400">Min. ${Number(row.min_stock || 0)}</p>
                        </div>
                        <span class="text-orange-600 font-bold whitespace-nowrap">Sisa ${Number(row.qty_on_hand || 0)}</span>
                    </li>
                `).join('');
            };

            const runWhenIdle = (task) => {
                if ('requestIdleCallback' in window) {
                    window.requestIdleCallback(task, { timeout: 1500 });
                    return;
                }

                window.setTimeout(task, 250);
            };

            const renderDashboardSummary = (data) => {
                const sales = Number(data?.sales_today || 0);
                const orders = Number(data?.orders_today || 0);
                const average = Number(data?.average_order_today ?? (orders > 0 ? (sales / orders) : 0));

                document.getElementById('kpi-sales').textContent = toCurrency(sales);
                document.getElementById('kpi-orders').textContent = new Intl.NumberFormat('id-ID').format(orders);
                document.getElementById('kpi-average').textContent = toCurrency(average);
            };

            const loadDashboardSummary = async () => {
                try {
                    const data = await window.PosUi.fetchJson('/api/v1/pos/dashboard?sections=summary', {
                        headers,
                    }, {
                        timeoutMs: 7000,
                        ttlMs: 15000,
                        cacheKey: 'pos-dashboard-summary',
                        unwrap: true,
                    });

                    renderDashboardSummary(data);
                } catch (_) {
                    // Keep fallback KPI values.
                }
            };

            const loadPeriodClosingReminder = async () => {
                const reminder = document.getElementById('pos-period-closing-reminder');
                if (!reminder) {
                    return;
                }

                try {
                    const data = await window.PosUi.fetchJson('/api/v1/lock-periods/assistant', {
                        headers,
                    }, {
                        timeoutMs: 8000,
                        ttlMs: 30000,
                        cacheKey: 'pos-dashboard-period-closing-assistant',
                        unwrap: true,
                    });
                    const severity = data?.status?.severity || 'safe';
                    if (severity === 'safe') {
                        reminder.classList.add('hidden');
                        return;
                    }

                    const title = document.getElementById('pos-period-closing-title');
                    const message = document.getElementById('pos-period-closing-message');
                    if (title) {
                        title.textContent = data?.status?.title || 'Periode sebelumnya perlu ditutup';
                    }
                    if (message) {
                        message.textContent = data?.status?.message || 'Review checklist tutup buku sebelum laporan dipakai.';
                    }
                    reminder.classList.remove('hidden');
                } catch (_) {
                    reminder.classList.add('hidden');
                }
            };

            const loadDashboardWidgets = async () => {
                try {
                    const data = await window.PosUi.fetchJson('/api/v1/pos/dashboard?sections=top_products,low_stock&top_limit=3&low_stock_limit=2', {
                        headers,
                    }, {
                        timeoutMs: 10000,
                        ttlMs: 60000,
                        cacheKey: 'pos-dashboard-widgets',
                        unwrap: true,
                    });
                    renderTopProducts(Array.isArray(data?.top_products) ? data.top_products : []);
                    renderLowStock(Array.isArray(data?.low_stock_items) ? data.low_stock_items : []);
                } catch (_) {
                    renderTopProducts([]);
                    renderLowStock([]);
                }
            };

            Promise.allSettled([loadShift(), loadDashboardSummary(), loadPeriodClosingReminder()]).then(() => {
                runWhenIdle(() => loadDashboardWidgets());
            });
        })();
    </script>
@endpush
