@extends('layouts.app')

@section('page_title', 'Penyesuaian Stok')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
@endpush

@section('header')
        <x-page-header title="Penyesuaian Stok" backUrl="/menu/management">
            <x-slot name="actions">
                <button class="flex items-center justify-center rounded-lg h-10 bg-primary px-4 text-white gap-2 text-sm font-bold leading-normal transition-colors hover:bg-primary/90">
                    <span>Simpan</span>
                </button>
            </x-slot>
        </x-page-header>
@endsection

@section('content')
        <div class="flex flex-col gap-4 p-4">
            <div class="flex flex-col gap-2">
                <h3 class="text-slate-900 dark:text-slate-100 text-sm font-semibold uppercase tracking-wider">Detail Produk</h3>
                <div class="bg-white dark:bg-slate-900 rounded-xl overflow-hidden shadow-sm border border-slate-200 dark:border-slate-800">
                    <div class="flex items-center gap-4 p-4 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                        <div class="bg-slate-100 dark:bg-slate-800 rounded-lg size-16 flex items-center justify-center shrink-0 border border-slate-200 dark:border-slate-700">
                            <span class="material-symbols-outlined text-slate-400 text-3xl">inventory_2</span>
                        </div>
                        <div class="flex flex-col justify-center flex-1">
                            <p class="text-primary text-base font-semibold leading-normal">Pilih Produk</p>
                            <p class="text-slate-500 dark:text-slate-400 text-sm font-normal">Cari nama produk atau scan barcode</p>
                        </div>
                        <div class="shrink-0">
                            <span class="material-symbols-outlined text-slate-400">chevron_right</span>
                        </div>
                    </div>
                    <div class="px-4 pb-4 border-t border-slate-100 dark:border-slate-800 mt-2">
                        <div class="flex justify-between items-center py-3 border-b border-slate-100 dark:border-slate-800">
                            <p class="text-slate-500 dark:text-slate-400 text-sm">Stok Saat Ini</p>
                            <p class="text-slate-900 dark:text-slate-100 text-sm font-medium">0 unit</p>
                        </div>
                        <div class="flex justify-between items-center py-3">
                            <p class="text-slate-500 dark:text-slate-400 text-sm">Gudang</p>
                            <div class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs text-primary">location_on</span>
                                <p class="text-slate-900 dark:text-slate-100 text-sm font-medium">Gudang Utama</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <h3 class="text-slate-900 dark:text-slate-100 text-sm font-semibold uppercase tracking-wider">Informasi Koreksi</h3>
                <div class="bg-white dark:bg-slate-900 rounded-xl p-4 shadow-sm border border-slate-200 dark:border-slate-800 space-y-4">
                    <div class="flex bg-slate-100 dark:bg-slate-800 p-1 rounded-lg">
                        <button class="flex-1 py-2 text-xs font-bold rounded-md bg-white dark:bg-slate-700 shadow-sm text-primary">TAMBAH/KURANG</button>
                        <button class="flex-1 py-2 text-xs font-bold rounded-md text-slate-500 dark:text-slate-400">STOK AKHIR</button>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Jumlah Penyesuaian</label>
                        <div class="relative flex items-center">
                            <button class="absolute left-3 text-slate-400 hover:text-primary transition-colors">
                                <span class="material-symbols-outlined">remove_circle</span>
                            </button>
                            <input class="w-full bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg py-3 px-12 text-center text-xl font-bold focus:ring-primary focus:border-primary" placeholder="0" type="number" />
                            <button class="absolute right-3 text-primary hover:text-primary/80 transition-colors">
                                <span class="material-symbols-outlined">add_circle</span>
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400 italic">Gunakan tanda minus (-) untuk mengurangi stok</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Alasan Koreksi <span class="text-red-500">*</span></label>
                        <select class="w-full bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg py-2.5 px-3 focus:ring-primary focus:border-primary text-sm">
                            <option value="">Pilih Alasan</option>
                            <option value="rusak">Barang Rusak</option>
                            <option value="hilang">Barang Hilang</option>
                            <option value="salah_input">Kesalahan Input</option>
                            <option value="opname">Stock Opname Berkala</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Catatan Tambahan</label>
                        <textarea class="w-full bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg py-2 px-3 focus:ring-primary focus:border-primary text-sm" placeholder="Tulis keterangan detail di sini..." rows="3"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Lampiran Foto (Opsional)</label>
                        <div class="flex items-center justify-center w-full">
                            <label class="flex flex-col items-center justify-center w-full h-24 border-2 border-slate-200 dark:border-slate-700 border-dashed rounded-lg cursor-pointer bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <span class="material-symbols-outlined text-slate-400 mb-1">add_a_photo</span>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Ambil foto atau pilih galeri</p>
                                </div>
                                <input class="hidden" type="file" />
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4 pb-8 space-y-3">
                <button class="w-full bg-primary text-white py-4 rounded-xl font-bold text-base shadow-lg shadow-primary/20 hover:shadow-xl transition-all active:scale-[0.98]">
                    Konfirmasi Penyesuaian
                </button>
                <button class="w-full bg-transparent text-slate-500 dark:text-slate-400 py-3 rounded-xl font-medium text-sm hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    Batalkan
                </button>
            </div>
        </div>
        <div class="h-16"></div>
@endsection
