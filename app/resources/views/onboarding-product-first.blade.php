@extends('layouts.app')

@section('page_title', 'Input Produk Pertama')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-x-hidden overflow-y-auto bg-white dark:bg-slate-900 shadow-xl')
@section('main_class', 'flex-1 overflow-y-auto p-0')

@section('content')
    <div class="flex items-center bg-white dark:bg-slate-900 p-4 pb-2 justify-between sticky top-0 z-10 border-b border-slate-100 dark:border-slate-800">
        <a href="/register/step-3" class="text-slate-900 dark:text-slate-100 flex size-12 shrink-0 items-center justify-center cursor-pointer">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <h2 class="text-slate-900 dark:text-slate-100 text-lg font-bold leading-tight tracking-tight flex-1 text-center pr-12">Input Produk</h2>
    </div>

    <div class="flex w-full flex-row items-center justify-center gap-3 py-6">
        <div class="h-1.5 w-6 rounded-full bg-primary/20"></div>
        <div class="h-1.5 w-6 rounded-full bg-primary/20"></div>
        <div class="h-1.5 w-6 rounded-full bg-primary/20"></div>
        <div class="h-1.5 w-10 rounded-full bg-primary"></div>
    </div>

    <div class="px-6">
        <h3 class="tracking-tight text-2xl font-bold leading-tight pt-2">Input Produk Pertama Anda</h3>
        <p class="text-slate-600 dark:text-slate-400 text-sm leading-normal pb-6 pt-2">Lengkapi detail produk di bawah ini untuk memulai jualan pertama Anda.</p>
    </div>

    @if ($errors->any())
        <div class="mx-6 mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form id="onboarding-step-4-form" method="POST" action="/register/step-4" class="flex flex-col gap-5 px-6 pb-28">
        @csrf
        <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold">Nama Produk</label>
            <input name="name" value="{{ old('name') }}" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-4 placeholder:text-slate-400" placeholder="Contoh: Kopi Susu Gula Aren" type="text" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-semibold">SKU / Kode</label>
                <input name="sku" value="{{ old('sku') }}" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-4 placeholder:text-slate-400" placeholder="KS-001" type="text" />
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-semibold">Kategori</label>
                <div class="relative">
                    <select name="category" class="w-full appearance-none rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-4">
                        <option value="">Pilih kategori</option>
                        <option value="Minuman" {{ old('category') === 'Minuman' ? 'selected' : '' }}>Minuman</option>
                        <option value="Makanan" {{ old('category') === 'Makanan' ? 'selected' : '' }}>Makanan</option>
                        <option value="Lainnya" {{ old('category') === 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">expand_more</span>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold">Harga Pokok (HPP)</label>
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-medium">Rp</span>
                <input name="hpp_average" value="{{ old('hpp_average') }}" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-4 pl-12 placeholder:text-slate-400" placeholder="10.000" type="number" step="0.01" min="0" />
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold">Harga Jual</label>
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-medium">Rp</span>
                <input name="price" value="{{ old('price') }}" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-4 pl-12 placeholder:text-slate-400" placeholder="18.000" type="number" step="0.01" min="0" />
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold">Stok Awal</label>
            <div class="flex items-center gap-4">
                <button class="size-12 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100" type="button">
                    <span class="material-symbols-outlined">remove</span>
                </button>
                <input name="initial_stock" class="flex-1 text-center rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-4 font-bold" type="number" step="0.01" min="0" value="{{ old('initial_stock', '0') }}" />
                <button class="size-12 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100" type="button">
                    <span class="material-symbols-outlined">add</span>
                </button>
            </div>
        </div>
    </form>

    <div class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] p-6 bg-white dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 flex flex-col gap-3">
        <button form="onboarding-step-4-form" type="submit" class="w-full py-4 bg-primary text-white rounded-xl font-bold text-base shadow-lg shadow-primary/20 text-center">Simpan dan Selesai</button>
        <form method="POST" action="/register/step-4">
            @csrf
            <input type="hidden" name="skip_product" value="1" />
            <button type="submit" class="w-full py-4 bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 rounded-xl font-medium text-base text-center">Lewati dan Selesai</button>
        </form>
    </div>
@endsection
