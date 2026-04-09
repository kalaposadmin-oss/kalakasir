@extends('layouts.app')

@section('page_title', 'Kasir POS - Checkout Utama')
@section('body_class', 'font-display text-slate-900')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-slate-50 shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
        <style>
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
@endpush

@section('header')
        <header class="flex items-center justify-between px-4 pt-4 pb-3 bg-white border-b border-slate-200" data-auto-center-title="true">
            <div class="flex items-center gap-3">
                <button class="p-2 rounded-lg bg-slate-100 text-slate-500" type="button" data-menu-trigger>
                    <span class="material-symbols-outlined text-xl">menu</span>
                </button>
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined font-semibold">person</span>
                </div>
                <div>
                    <h1 class="text-sm font-bold leading-tight" id="cashier-name">Kasir</h1>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500" id="shift-indicator"></span>
                        <p class="text-[10px] uppercase tracking-wider text-slate-500 font-bold" id="shift-status">Shift Aktif</p>
                    </div>
                    <p class="text-[10px] text-slate-400" id="shift-opened-time">Mulai: -</p>
                </div>
            </div>
            <div class="flex gap-2">
                <button class="p-2 rounded-lg bg-slate-100 text-slate-500">
                    <span class="material-symbols-outlined text-xl">sync</span>
                </button>
                <a id="quick-close-shift" href="/shift/close" class="p-2 rounded-lg bg-slate-100 text-slate-500 hidden" title="Tutup Shift">
                    <span class="material-symbols-outlined text-xl">lock_clock</span>
                </a>
                <button class="p-2 rounded-lg bg-slate-100 text-slate-500">
                    <span class="material-symbols-outlined text-xl">settings</span>
                </button>
            </div>
        </header>
@endsection

@section('content')
        <div class="relative flex h-full flex-col">
            <div class="flex-1 flex flex-col overflow-hidden">
                <div class="px-4 py-3 space-y-3 bg-white">
                    <div id="shift-guard" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3">
                        <p class="text-xs font-semibold text-amber-800">Shift belum aktif. Buka shift dulu untuk mulai transaksi.</p>
                        <a href="/shift/open" class="mt-2 inline-flex items-center gap-1 rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-800">
                            <span class="material-symbols-outlined text-sm">lock_open</span>
                            Buka Shift
                        </a>
                    </div>
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">search</span>
                            <input id="search-input" class="w-full pl-10 pr-4 py-2.5 rounded-xl border-none bg-slate-100 focus:ring-2 focus:ring-primary text-sm" placeholder="Cari produk atau scan..." type="text" />
                        </div>
                        <button class="bg-primary/10 text-primary p-2.5 rounded-xl flex items-center justify-center">
                            <span class="material-symbols-outlined">barcode_scanner</span>
                        </button>
                    </div>
                    <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 p-2 rounded-xl">
                    @php($hasKasirViteAsset = file_exists(public_path('build/manifest.json')) || file_exists(storage_path('vite.hot')))
                    @if($hasKasirViteAsset)
                        @vite('resources/js/pages/pos-kasir-page.js')
                    @else
                        <script defer src="{{ asset('js/pos-kasir-page-core.js') }}"></script>
                    @endif
                </div>
