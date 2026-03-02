@extends('layouts.app')

@section('page_title', 'Pengaturan Stok Awal')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-auto min-h-screen w-full max-w-[430px] flex-col overflow-x-hidden bg-background-light dark:bg-background-dark shadow-2xl border-x border-slate-200 dark:border-slate-800')
@section('main_class', 'flex-1 overflow-y-auto p-0')

@section('content')
    <header class="sticky top-0 z-10 flex items-center bg-white dark:bg-slate-900 px-4 py-3 border-b border-slate-100 dark:border-slate-800 justify-between">
        <div class="flex items-center gap-3">
            <a href="/register/step-2" class="text-slate-900 dark:text-slate-100 flex size-10 shrink-0 items-center justify-center rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
                <span class="material-symbols-outlined">arrow_back_ios_new</span>
            </a>
            <h2 class="text-slate-900 dark:text-slate-100 text-lg font-bold leading-tight tracking-tight">Pengaturan Stok</h2>
        </div>
        <div class="size-10 flex items-center justify-center">
            <span class="material-symbols-outlined text-slate-500">more_horiz</span>
        </div>
    </header>

    <main class="flex-1">
        <div class="px-4 pt-6 pb-2">
            <h2 class="text-2xl font-bold leading-tight tracking-tight mb-2">Metode Penilaian Inventaris</h2>
            <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">
                Pilih metode penilaian stok yang akan digunakan untuk menghitung nilai aset dan HPP pada sistem Anda.
            </p>
            <p class="text-xs text-slate-500 mt-2">Langkah 3 dari 4</p>
        </div>

        @if ($errors->any())
            <div class="mx-4 mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form id="onboarding-step-3-form" method="POST" action="/register/step-3" class="p-4 flex flex-col gap-4">
            @csrf
            <label class="group relative flex flex-col bg-white dark:bg-slate-900 rounded-xl overflow-hidden shadow-sm border-2 border-primary ring-2 ring-primary/10 transition-all cursor-pointer">
                <input value="fifo" {{ old('valuation_method', 'fifo') === 'fifo' ? 'checked' : '' }} class="sr-only" name="valuation_method" type="radio" />
                <div class="w-full bg-slate-200 dark:bg-slate-800 aspect-[21/9] overflow-hidden">
                    <div class="w-full h-full bg-gradient-to-br from-primary/20 to-primary/40 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary text-5xl opacity-50">inventory_2</span>
                    </div>
                </div>
                <div class="p-4 flex flex-col gap-2">
                    <div class="flex items-center justify-between">
                        <span class="bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded">Rekomendasi</span>
                        <span class="material-symbols-outlined text-primary">check_circle</span>
                    </div>
                    <h3 class="text-lg font-bold">FIFO (First-In, First-Out)</h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm leading-normal">Stok yang masuk lebih dulu akan dikeluarkan lebih dulu.</p>
                </div>
            </label>

            <label class="group relative flex flex-col bg-white dark:bg-slate-900 rounded-xl overflow-hidden shadow-sm border border-slate-200 dark:border-slate-800 transition-all cursor-pointer hover:border-primary/50">
                <input value="average" {{ old('valuation_method') === 'average' ? 'checked' : '' }} class="sr-only" name="valuation_method" type="radio" />
                <div class="w-full bg-slate-200 dark:bg-slate-800 aspect-[21/9] overflow-hidden">
                    <div class="w-full h-full bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-700 dark:to-slate-800 flex items-center justify-center">
                        <span class="material-symbols-outlined text-slate-400 text-5xl opacity-50">calculate</span>
                    </div>
                </div>
                <div class="p-4 flex flex-col gap-2">
                    <div class="flex items-center justify-between min-h-[20px]">
                        <span></span>
                        <span class="material-symbols-outlined text-slate-300 dark:text-slate-700">radio_button_unchecked</span>
                    </div>
                    <h3 class="text-lg font-bold">Average Cost (Biaya Rata-rata)</h3>
                    <p class="text-slate-600 dark:text-slate-400 text-sm leading-normal">Nilai stok dihitung dari rata-rata biaya perolehan unit yang tersedia.</p>
                </div>
            </label>
        </form>

        <div class="px-4 py-2">
            <div class="bg-primary/5 rounded-lg p-4 border border-primary/10 flex gap-3">
                <span class="material-symbols-outlined text-primary shrink-0">info</span>
                <p class="text-xs text-primary/80 leading-normal">Metode dapat diubah lagi nanti di menu Pengaturan Inventaris.</p>
            </div>
        </div>
    </main>

    <footer class="sticky bottom-0 bg-white dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 p-4 pb-8">
        <button form="onboarding-step-3-form" type="submit" class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/20 transition-colors flex items-center justify-center gap-2">
            <span>Simpan Pengaturan</span>
        </button>
    </footer>
@endsection
