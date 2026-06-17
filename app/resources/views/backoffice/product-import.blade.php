@extends('layouts.app')

@section('page_title', 'Import Produk')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100')
@section('main_class', 'flex-1 overflow-y-auto pb-12 lg:pl-72')

@section('header')
    <x-page-header title="Import Produk" backUrl="/backoffice/products" :showMenuButton="true" />
@endsection

@section('content')
    <div class="mx-auto w-full max-w-5xl px-4 py-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-2">
            <p class="text-sm font-semibold uppercase tracking-wide text-primary">Katalog Produk</p>
            <h1 class="text-2xl font-bold text-slate-950">Import Produk</h1>
            <p class="max-w-2xl text-sm leading-6 text-slate-600">
                Upload file CSV untuk menambahkan atau memperbarui produk secara massal. Baris pertama dipakai sebagai header, lalu Anda bisa mencocokkan kolom sebelum import diproses.
            </p>
        </div>

        @if(session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                <p class="font-semibold">Import selesai</p>
                <p class="mt-1">{{ session('status') }}</p>
            </div>
        @endif

        @if(session('import_errors') && count(session('import_errors')) > 0)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">Beberapa baris dilewati</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach(session('import_errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-semibold">Import belum bisa diproses</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-5 flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <span class="material-symbols-outlined">upload_file</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-950">Upload File</h2>
                        <p class="text-sm text-slate-500">Maksimal 10 MB per file.</p>
                    </div>
                </div>

                <form action="{{ url('/backoffice/products/import') }}" method="post" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <div>
                        <label for="file-input" class="mb-2 block text-sm font-semibold text-slate-700">File Produk</label>
                        <input
                            id="file-input"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 file:mr-4 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            type="file"
                            name="file"
                            accept=".csv,.txt,.xls,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            required
                        />
                        <p class="mt-2 text-xs leading-5 text-slate-500">
                            CSV contoh memakai pemisah titik koma agar langsung terbuka rapi di Excel Indonesia. XLS/XLSX bisa digunakan jika paket spreadsheet sudah tersedia di server.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-white shadow-sm">
                            <span class="material-symbols-outlined text-[20px]">preview</span>
                            Preview Kolom
                        </button>
                        <a href="/backoffice/products/import/template" download class="inline-flex h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">
                            <span class="material-symbols-outlined text-[20px]">download</span>
                            Contoh CSV Excel
                        </a>
                        <a href="/backoffice/products" class="inline-flex h-11 items-center gap-2 rounded-lg border border-transparent px-4 text-sm font-semibold text-slate-600">
                            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                            Kembali
                        </a>
                    </div>
                </form>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-slate-950">Format Kolom</h2>
                <div class="mt-4 overflow-hidden rounded-lg border border-slate-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Kolom</th>
                                <th class="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr><td class="px-3 py-2 font-mono text-xs">name</td><td class="px-3 py-2">Wajib</td></tr>
                            <tr><td class="px-3 py-2 font-mono text-xs">sku</td><td class="px-3 py-2">Opsional, dibuat otomatis jika kosong</td></tr>
                            <tr><td class="px-3 py-2 font-mono text-xs">item_type</td><td class="px-3 py-2">Tipe produk: barang_jual, bahan_baku, atau jasa</td></tr>
                            <tr><td class="px-3 py-2 font-mono text-xs">price</td><td class="px-3 py-2">Harga jual</td></tr>
                            <tr><td class="px-3 py-2 font-mono text-xs">hpp_average</td><td class="px-3 py-2">Wajib jika stok awal diisi</td></tr>
                            <tr><td class="px-3 py-2 font-mono text-xs">qty_on_hand</td><td class="px-3 py-2">Stok awal</td></tr>
                            <tr><td class="px-3 py-2 font-mono text-xs">category</td><td class="px-3 py-2">Dibuat otomatis jika belum ada</td></tr>
                            <tr><td class="px-3 py-2 font-mono text-xs">supplier</td><td class="px-3 py-2">Nama supplier, dibuat otomatis jika belum ada</td></tr>
                            <tr><td class="px-3 py-2 font-mono text-xs">unit</td><td class="px-3 py-2">Satuan produk</td></tr>
                            <tr><td class="px-3 py-2 font-mono text-xs">min_stock</td><td class="px-3 py-2">Stok minimum</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">
                    Produk dengan SKU yang sama akan diperbarui. Jika SKU kosong, sistem akan mencari produk dengan nama yang sama atau membuat SKU otomatis dari nama produk.
                </div>
            </section>
        </div>
    </div>
@endsection
