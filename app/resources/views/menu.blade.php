@extends('layouts.app')

@section('page_title', 'Menu - POS SaaS')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('main_class', 'flex-1 overflow-y-auto px-4 pb-16')

@section('header')
    <x-page-header title="Menu" :showMenuButton="true" />
@endsection

@section('content')
    <section class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800 p-5 shadow-sm">
        <p class="text-xs font-semibold text-primary uppercase tracking-[0.2em]">Info</p>
        <h1 class="mt-3 text-lg font-semibold text-slate-900 dark:text-slate-100">Menu utama sekarang ada di sidebar.</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Klik ikon menu (garis tiga) untuk membuka semua grup menu, transaksi, dan pengaturan.</p>
        <div class="mt-5 flex flex-wrap gap-3">
            <a class="inline-flex items-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-semibold text-white" href="/kasir">
                <span class="material-symbols-outlined text-base">point_of_sale</span>
                Buka Kasir
            </a>
            <a class="inline-flex items-center gap-2 rounded-full border border-slate-200/70 dark:border-slate-700 px-4 py-2 text-sm font-semibold text-slate-600 dark:text-slate-300" href="/analytics">
                <span class="material-symbols-outlined text-base">bar_chart</span>
                Lihat Laporan
            </a>
        </div>
    </section>
@endsection
