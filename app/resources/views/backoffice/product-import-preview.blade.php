@extends('layouts.app')

@section('page_title', 'Preview Import Produk')
@section('ui_mode', 'backoffice')
@section('main_class', 'flex-1 overflow-y-auto bg-slate-50 pb-12')

@section('header')
    <x-page-header title="Preview Import" backUrl="/backoffice/products/import" :showMenuButton="true" />
@endsection

@section('content')
    <div class="mx-auto w-full max-w-6xl px-4 py-6 lg:px-8">
        <div class="mb-5 flex flex-col gap-2">
            <p class="text-sm font-semibold uppercase tracking-wide text-primary">Cek Sebelum Proses</p>
            <h1 class="text-2xl font-bold text-slate-950">Preview Import Produk</h1>
            <p class="text-sm text-slate-600">
                File: <span class="font-semibold text-slate-800">{{ $originalName ?? '-' }}</span>
                <span class="mx-2 text-slate-300">/</span>
                {{ number_format((int) ($totalRows ?? count($rows ?? [])), 0, ',', '.') }} baris data
            </p>
        </div>

        @if($errors->any())
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <p class="font-semibold">Mapping belum valid</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(empty($rows))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Tidak ada baris data yang bisa ditampilkan. Cek kembali isi file lalu upload ulang.
            </div>
            <div class="mt-4">
                <a href="/backoffice/products/import" class="inline-flex h-11 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-white">
                    <span class="material-symbols-outlined text-[20px]">upload_file</span>
                    Upload Ulang
                </a>
            </div>
        @else
            <form method="POST" action="/backoffice/products/import/process" class="space-y-4">
                @csrf

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-4 py-3">
                        <h2 class="text-base font-bold text-slate-950">Mapping Kolom</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Pilih tujuan kolom untuk setiap header. Sistem sudah mencoba menebak mapping dari nama kolom file.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    @foreach($headers as $i => $header)
                                        @php
                                            $auto = \App\Services\ProductImportService::suggestFieldForHeader((string) $header);
                                        @endphp
                                        <th class="min-w-44 px-3 py-3 align-top">
                                            <label class="mb-2 block text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                {{ $header ?: 'Kolom '.($i + 1) }}
                                            </label>
                                            <select name="mapping[{{ $i }}]" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                                @foreach($fieldLabels as $value => $label)
                                                    <option value="{{ $value }}" {{ old('mapping.'.$i, $auto) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach($rows as $row)
                                    <tr>
                                        @foreach($headers as $i => $header)
                                            <td class="max-w-60 truncate px-3 py-3 text-slate-700" title="{{ $row[$i] ?? '' }}">
                                                {{ $row[$i] ?? '' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50/95 py-4 backdrop-blur">
                    <p class="text-sm text-slate-500">
                        Preview menampilkan maksimal 10 baris pertama.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <a href="/backoffice/products/import" class="inline-flex h-11 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">
                            <span class="material-symbols-outlined text-[20px]">restart_alt</span>
                            Upload Ulang
                        </a>
                        <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-white shadow-sm">
                            <span class="material-symbols-outlined text-[20px]">done_all</span>
                            Proses Import
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </div>
@endsection
