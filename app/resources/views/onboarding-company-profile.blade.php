@extends('layouts.app')

@section('page_title', 'Data Perusahaan')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto p-0 app-bottom-safe-space')

@section('content')
    <header class="flex items-center bg-white dark:bg-slate-900 p-4 pb-2 justify-between sticky top-0 z-10 shadow-sm">
        <a href="/register/step-1" class="text-slate-900 dark:text-slate-100 flex size-12 shrink-0 items-center justify-start cursor-pointer">
            <span class="material-symbols-outlined">arrow_back_ios</span>
        </a>
        <h2 class="text-slate-900 dark:text-slate-100 text-lg font-bold leading-tight tracking-tight flex-1 text-center pr-12">Data Perusahaan</h2>
    </header>

    <div class="bg-white px-4 py-5 dark:bg-slate-900">
        <div class="flex items-center justify-between gap-4">
            <p class="text-base font-medium leading-normal">Langkah 2: Data Perusahaan</p>
            <p class="text-sm font-normal leading-normal text-slate-500 dark:text-slate-400">2 dari 5</p>
        </div>
        <div class="mt-4 flex w-full flex-row items-center justify-center gap-2.5">
            <div class="h-2 w-8 rounded-full bg-primary"></div>
            <div class="h-2 w-8 rounded-full bg-primary"></div>
            <div class="h-2 w-8 rounded-full bg-primary/20"></div>
            <div class="h-2 w-8 rounded-full bg-primary/20"></div>
            <div class="h-2 w-8 rounded-full bg-primary/20"></div>
        </div>
    </div>

    <div class="px-4 py-5 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800">
        <h3 class="tracking-tight text-2xl font-bold leading-tight">Profil Bisnis</h3>
        <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed mt-2">Lengkapi informasi toko untuk memulai pengelolaan stok dan transaksi.</p>
    </div>

    @if ($errors->any())
        <div class="mx-4 mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form id="onboarding-step-2-form" method="POST" action="/register/step-2" class="flex flex-col gap-5 p-4 mt-2 pb-28 app-bottom-safe-space">
        @csrf
        <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold leading-normal">Nama Toko/Perusahaan <span class="text-red-500">*</span></label>
            <input name="store_name" value="{{ old('store_name', session('onboarding.step2.store_name')) }}" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-3.5" placeholder="Contoh: Kedai Kopi Maju" type="text" required />
        </div>

        <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold leading-normal">Alamat Operasional</label>
            <textarea name="operational_address" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-3.5 min-h-[100px]" placeholder="Jl. Thamrin No. 12, Jakarta Pusat">{{ old('operational_address', session('onboarding.step2.operational_address')) }}</textarea>
        </div>

        <div class="flex flex-col gap-2">
            <label class="text-sm font-semibold leading-normal">Kategori Bisnis</label>
            <div class="relative">
                <select name="business_category" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-3.5 appearance-none">
                    <option value="">Pilih Kategori</option>
                    <option value="fnb" {{ old('business_category', session('onboarding.step2.business_category')) === 'fnb' ? 'selected' : '' }}>Food and Beverages</option>
                    <option value="retail" {{ old('business_category', session('onboarding.step2.business_category')) === 'retail' ? 'selected' : '' }}>Retail</option>
                    <option value="service" {{ old('business_category', session('onboarding.step2.business_category')) === 'service' ? 'selected' : '' }}>Jasa/Service</option>
                    <option value="other" {{ old('business_category', session('onboarding.step2.business_category')) === 'other' ? 'selected' : '' }}>Lainnya</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                    <span class="material-symbols-outlined">expand_more</span>
                </div>
            </div>
        </div>

        <input type="hidden" name="timezone" value="{{ session('onboarding.step2.timezone', 'Asia/Jakarta') }}" />
    </form>

    <div class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-[430px] p-4 bg-white dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 app-bottom-safe-footer">
        <button form="onboarding-step-2-form" type="submit" class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2">
            Simpan dan Lanjutkan
            <span class="material-symbols-outlined">arrow_forward</span>
        </button>
    </div>
@endsection
