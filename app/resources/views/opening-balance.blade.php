@extends('layouts.app')

@section('page_title', 'Saldo Awal')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto pb-8 lg:pl-72')

@push('head')
    <style>
        .soft-grid {
            background-image:
                radial-gradient(circle at 20% 20%, rgba(19, 91, 236, 0.10), transparent 28%),
                radial-gradient(circle at 80% 0%, rgba(16, 185, 129, 0.10), transparent 24%),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.96));
        }

        .stat-pill {
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }
    </style>
@endpush

@section('header')
    <x-page-header
        title="Saldo Awal"
        :breadcrumbs="['Pengaturan', 'Keuangan']"
        backUrl="/backoffice/dashboard"
        :showMenuButton="true"
    >
        <x-slot name="actions">
            <a href="/backoffice/settings/chart-of-accounts" class="inline-flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300" title="Akun Kas & Bank">
                <span class="material-symbols-outlined text-[20px]">account_balance</span>
            </a>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
    <div class="w-full px-4 py-4 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 soft-grid">
                <div class="grid gap-0 lg:grid-cols-[1.2fr_0.8fr]">
                    <div class="p-6 lg:p-8">
                        <div class="inline-flex items-center gap-2 rounded-full border border-primary/15 bg-primary/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.22em] text-primary">
                            <span class="material-symbols-outlined text-sm">rocket_launch</span>
                            Setup keuangan awal
                        </div>
                        <h2 class="mt-4 max-w-2xl text-3xl font-bold tracking-tight text-slate-900 dark:text-white lg:text-4xl">
                            Catat posisi awal toko sebelum transaksi harian berjalan.
                        </h2>
                        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-400 lg:text-base">
                            Saldo Awal dipakai sekali saat mulai memakai aplikasi: stok awal, saldo kas/bank awal, dan modal pembuka dicatat dalam satu draft yang seimbang. Untuk tambahan modal setelah toko berjalan, gunakan menu Kas Masuk.
                        </p>

                        <div class="mt-5 grid gap-3 md:grid-cols-3">
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 text-sm leading-relaxed text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-200">
                                <p class="text-[11px] font-bold uppercase tracking-wider">Kapan dipakai</p>
                                <p class="mt-2">Saat pindah dari catatan lama ke aplikasi, sebelum penjualan dan pembelian rutin dicatat.</p>
                            </div>
                            <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-sm leading-relaxed text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-200">
                                <p class="text-[11px] font-bold uppercase tracking-wider">Tidak wajib</p>
                                <p class="mt-2">Toko baru boleh mulai tanpa saldo awal. Isi hanya kalau memang ada stok/kas/bank yang sudah berjalan.</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm leading-relaxed text-slate-600 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-300">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Bukan Kas Masuk</p>
                                <p class="mt-2">Kas Masuk untuk transaksi setelah aplikasi aktif, misalnya setoran modal baru dari owner.</p>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 stat-pill">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Status Draft</p>
                                <p id="hero-status" class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Belum ada draft</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 stat-pill">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Nilai Stok</p>
                                <p id="hero-stock-value" class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Rp 0</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/70 stat-pill">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Nilai Kas/Bank</p>
                                <p id="hero-fund-value" class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Rp 0</p>
                            </div>
                        </div>
                    </div>

                    <aside class="border-t border-slate-200 bg-slate-950 p-6 text-white lg:border-l lg:border-t-0 lg:p-8">
                        <p class="text-xs font-bold uppercase tracking-[0.24em] text-slate-400">Draft Aktif</p>
                        <div class="mt-4 space-y-3 rounded-2xl border border-white/10 bg-white/5 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Session ID</span>
                                <span id="session-id" class="text-sm font-bold text-white">-</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Tanggal Buka</span>
                                <span id="session-date" class="text-sm font-bold text-white">-</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Selisih</span>
                                <span id="session-delta" class="text-sm font-bold text-emerald-300">Rp 0</span>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-3">
                            <button id="create-draft-btn" class="inline-flex flex-1 items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-white shadow-lg shadow-primary/25 transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60" type="button">
                                <span class="material-symbols-outlined text-base">edit_square</span>
                                Buat Draft
                            </button>
                            <button id="validate-btn" class="inline-flex flex-1 items-center justify-center gap-2 rounded-2xl border border-white/15 bg-white/5 px-4 py-3 text-sm font-bold text-white transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-50" type="button" disabled>
                                <span class="material-symbols-outlined text-base">fact_check</span>
                                Validasi
                            </button>
                        </div>

                        <button id="post-btn" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-500 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-500/20 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50" type="button" disabled>
                            <span class="material-symbols-outlined text-base">publish</span>
                            Posting ke Buku Besar
                        </button>

                        <p class="mt-4 text-sm leading-relaxed text-slate-300">
                            Posting akan membuat jurnal saldo awal, kartu stok, inventory layer, dan kunci periode awal secara otomatis.
                        </p>
                        <p id="session-message" class="mt-4 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-200 hidden"></p>
                    </aside>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                <div class="space-y-6">
                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.24em] text-slate-400">Langkah 1</p>
                                <h3 class="mt-2 text-lg font-bold text-slate-900 dark:text-white">Buat draft yang seimbang</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">Pilih tanggal mulai pembukuan dan akun modal. Nominal modal boleh dikosongkan agar sistem otomatis menyamakan dengan total stok plus kas/bank awal.</p>
                            </div>
                            <span class="material-symbols-outlined text-3xl text-primary/70">schedule</span>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Tanggal Mulai</span>
                                <input id="opening-date" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100" type="date" />
                            </label>

                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Akun Modal</span>
                                <select id="equity-account" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100"></select>
                            </label>

                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Nominal Modal</span>
                                <input id="equity-amount" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100" type="number" min="0" step="0.01" placeholder="Opsional" />
                            </label>

                            <label class="space-y-2 md:col-span-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Catatan</span>
                                <textarea id="draft-notes" rows="3" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100" placeholder="Contoh: saldo awal dari hasil stock opname"></textarea>
                            </label>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-3">
                            <button id="create-draft-btn-bottom" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-bold text-white shadow-lg shadow-primary/20 transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60" type="button">
                                <span class="material-symbols-outlined text-base">add_circle</span>
                                Buat Draft
                            </button>
                            <p id="draft-hint" class="text-xs text-slate-500 dark:text-slate-400 self-center">Akun modal default dipakai jika pilihan dikosongkan. Nominal modal kosong berarti mengikuti total debit.</p>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.24em] text-slate-400">Langkah 2</p>
                                <h3 class="mt-2 text-lg font-bold text-slate-900 dark:text-white">Isi stok awal</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">Isi lokasi, produk, qty, dan unit cost. Nilai line dihitung dari qty x cost saat dikirim ke engine.</p>
                            </div>
                            <span class="material-symbols-outlined text-3xl text-emerald-600/70">inventory_2</span>
                        </div>

                        <div id="stock-unavailable" class="mt-4 hidden rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-200"></div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Lokasi</span>
                                <select id="stock-location" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100"></select>
                            </label>

                            <label class="space-y-2 md:col-span-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Cari Produk</span>
                                <input id="stock-product-search" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100" type="search" placeholder="Cari nama atau SKU produk" />
                            </label>

                            <label class="space-y-2 md:col-span-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Produk</span>
                                <select id="stock-product" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100"></select>
                            </label>

                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Qty</span>
                                <input id="stock-qty" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100" type="number" min="0" step="0.01" placeholder="0" />
                            </label>

                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Harga Modal Satuan</span>
                                <input id="stock-cost" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100" type="number" min="0" step="0.01" placeholder="0" />
                            </label>

                            <label class="space-y-2 md:col-span-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Catatan Referensi</span>
                                <input id="stock-note" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100" type="text" placeholder="Opsional" />
                            </label>
                        </div>

                        <div class="mt-5 flex flex-wrap items-center gap-3">
                            <button id="add-stock-btn" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-500 px-5 py-3 text-sm font-bold text-white transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50" type="button" disabled>
                                <span class="material-symbols-outlined text-base">add</span>
                                Tambah Stok Awal
                            </button>
                            <button id="cancel-stock-edit-btn" class="hidden inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" type="button" disabled>
                                <span class="material-symbols-outlined text-base">close</span>
                                Batal Edit
                            </button>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Stok awal hanya untuk barang yang sudah ada sebelum transaksi pertama di aplikasi.</p>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.24em] text-slate-400">Langkah 3</p>
                                <h3 class="mt-2 text-lg font-bold text-slate-900 dark:text-white">Isi saldo kas/bank awal</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">Gunakan ini untuk uang yang sudah ada saat aplikasi mulai dipakai. Untuk modal tambahan setelah hari berjalan, pakai menu Kas Masuk.</p>
                            </div>
                            <span class="material-symbols-outlined text-3xl text-primary/70">account_balance_wallet</span>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Jenis Dana</span>
                                <select id="fund-source-type" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100">
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank</option>
                                </select>
                            </label>

                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Akun Kas/Bank</span>
                                <select id="fund-account" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100"></select>
                            </label>

                            <label class="space-y-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Nominal</span>
                                <input id="fund-amount" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100" type="number" min="0" step="0.01" placeholder="0" />
                            </label>

                            <label class="space-y-2 md:col-span-2">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Catatan Referensi</span>
                                <input id="fund-note" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100" type="text" placeholder="Opsional" />
                            </label>
                        </div>

                        <div class="mt-5 flex flex-wrap items-center gap-3">
                            <button id="add-fund-btn" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800" type="button" disabled>
                                <span class="material-symbols-outlined text-base">add</span>
                                Tambah Dana Awal
                            </button>
                            <button id="cancel-fund-edit-btn" class="hidden inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" type="button" disabled>
                                <span class="material-symbols-outlined text-base">close</span>
                                Batal Edit
                            </button>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Dana awal harus masuk ke akun kas atau bank yang benar.</p>
                        </div>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.24em] text-slate-400">Ringkasan Draft</p>
                                <h3 class="mt-2 text-lg font-bold text-slate-900 dark:text-white">Cek keseimbangan saldo awal</h3>
                            </div>
                            <span class="material-symbols-outlined text-3xl text-amber-500/80">monitoring</span>
                        </div>

                        <div class="mt-5 grid gap-3">
                            <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-800/70">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Baris Stok</span>
                                    <span id="stock-count" class="text-sm font-bold text-slate-900 dark:text-white">0</span>
                                </div>
                                <p id="stock-total" class="mt-2 text-lg font-bold text-slate-900 dark:text-white">Rp 0</p>
                            </div>

                            <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-800/70">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Baris Dana</span>
                                    <span id="fund-count" class="text-sm font-bold text-slate-900 dark:text-white">0</span>
                                </div>
                                <p id="fund-total" class="mt-2 text-lg font-bold text-slate-900 dark:text-white">Rp 0</p>
                            </div>

                            <div class="rounded-2xl bg-slate-50 px-4 py-3 dark:bg-slate-800/70">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Target Modal</span>
                                    <span id="equity-target" class="text-sm font-bold text-slate-900 dark:text-white">Rp 0</span>
                                </div>
                                <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Jika nominal modal diisi manual, total stok plus kas/bank harus sama persis.</p>
                            </div>

                            <div class="rounded-2xl border border-primary/10 bg-primary/5 px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-xs font-bold uppercase tracking-wider text-primary/70">Selisih</span>
                                    <span id="balance-delta-label" class="text-sm font-bold text-primary">Rp 0</span>
                                </div>
                                <p class="mt-2 text-xs leading-relaxed text-primary/70">Target ideal nol sebelum tombol posting aktif.</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.24em] text-slate-400">Validasi</p>
                                <h3 class="mt-2 text-lg font-bold text-slate-900 dark:text-white">Periksa sebelum posting</h3>
                            </div>
                            <span class="material-symbols-outlined text-3xl text-emerald-600/80">rule</span>
                        </div>

                        <div class="mt-5 space-y-3 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/70">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Status</span>
                                <span id="validation-status" class="rounded-full bg-slate-200 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-700 dark:bg-slate-700 dark:text-slate-200">Belum divalidasi</span>
                            </div>
                            <p id="validation-message" class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">Tambahkan baris terlebih dahulu, lalu jalankan validasi untuk memastikan saldo awal siap posting.</p>
                            <div class="grid grid-cols-2 gap-3 pt-1 text-sm">
                                <div class="rounded-xl bg-white px-3 py-3 dark:bg-slate-900/80">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Debit</p>
                                    <p id="validation-debit" class="mt-2 font-bold text-slate-900 dark:text-white">Rp 0</p>
                                </div>
                                <div class="rounded-xl bg-white px-3 py-3 dark:bg-slate-900/80">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Credit</p>
                                    <p id="validation-credit" class="mt-2 font-bold text-slate-900 dark:text-white">Rp 0</p>
                                </div>
                            </div>
                        </div>

                        <button id="validate-draft-btn" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-600/20 transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50" type="button" disabled>
                            <span class="material-symbols-outlined text-base">verified</span>
                            Validasi Draft
                        </button>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.24em] text-slate-400">Posting</p>
                                <h3 class="mt-2 text-lg font-bold text-slate-900 dark:text-white">Posting dan kunci saldo awal</h3>
                            </div>
                            <span class="material-symbols-outlined text-3xl text-slate-500/80">lock</span>
                        </div>

                        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                            Posting memicu jurnal, layer inventori, dan penguncian periode awal. Setelah itu draft tidak boleh diubah lagi.
                        </p>

                        <button id="post-draft-btn" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-slate-950/20 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200" type="button" disabled>
                            <span class="material-symbols-outlined text-base">publish</span>
                            Posting Saldo Awal
                        </button>
                    </section>
                </aside>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.24em] text-slate-400">Stok Awal</p>
                            <h3 class="mt-1 text-base font-bold text-slate-900 dark:text-white">Draft stok yang akan diposting</h3>
                        </div>
                        <span id="stock-lines-count" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">0 baris</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 dark:bg-slate-800/50">
                                <tr>
                                    <th class="px-5 py-3">Lokasi</th>
                                    <th class="px-5 py-3">Produk</th>
                                    <th class="px-5 py-3 text-right">Qty</th>
                                    <th class="px-5 py-3 text-right">Modal</th>
                                    <th class="px-5 py-3 text-right">Nilai</th>
                                    <th class="px-5 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="stock-lines-body" class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr>
                                    <td class="px-5 py-4 text-sm text-slate-500" colspan="6">Belum ada baris stok awal.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.24em] text-slate-400">Kas/Bank Awal</p>
                            <h3 class="mt-1 text-base font-bold text-slate-900 dark:text-white">Draft dana yang akan diposting</h3>
                        </div>
                        <span id="fund-lines-count" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">0 baris</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 dark:bg-slate-800/50">
                                <tr>
                                    <th class="px-5 py-3">Jenis</th>
                                    <th class="px-5 py-3">Akun</th>
                                    <th class="px-5 py-3 text-right">Nominal</th>
                                    <th class="px-5 py-3">Catatan</th>
                                    <th class="px-5 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="fund-lines-body" class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr>
                                    <td class="px-5 py-4 text-sm text-slate-500" colspan="5">Belum ada baris dana awal.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.24em] text-slate-400">Histori</p>
                        <h3 class="mt-1 text-base font-bold text-slate-900 dark:text-white">Posting saldo awal yang sudah masuk buku</h3>
                        <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                            Di sini terlihat setiap posting saldo awal, termasuk kas/bank tujuan. Kalau ingin menambah modal setelah toko berjalan, pakai Kas Masuk agar histori transaksi berjalan tetap terpisah dari saldo pembuka.
                        </p>
                    </div>
                    <span id="opening-history-count" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">0 posting</span>
                </div>
                <div id="opening-history-list" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div class="px-5 py-5 text-sm text-slate-500 dark:text-slate-400">Memuat histori saldo awal...</div>
                </div>
            </section>
        </div>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content?.trim() || '';
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content?.trim() || '';
            const headers = {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            };

            const el = {
                openingDate: document.getElementById('opening-date'),
                equityAccount: document.getElementById('equity-account'),
                equityAmount: document.getElementById('equity-amount'),
                draftNotes: document.getElementById('draft-notes'),
                createDraftBtn: document.getElementById('create-draft-btn'),
                createDraftBtnBottom: document.getElementById('create-draft-btn-bottom'),
                validateBtn: document.getElementById('validate-btn'),
                postBtn: document.getElementById('post-btn'),
                sessionId: document.getElementById('session-id'),
                sessionDate: document.getElementById('session-date'),
                sessionDelta: document.getElementById('session-delta'),
                sessionStatus: document.getElementById('hero-status'),
                heroStockValue: document.getElementById('hero-stock-value'),
                heroFundValue: document.getElementById('hero-fund-value'),
                sessionMessage: document.getElementById('session-message'),
                stockUnavailable: document.getElementById('stock-unavailable'),
                stockLocation: document.getElementById('stock-location'),
                stockSearch: document.getElementById('stock-product-search'),
                stockProduct: document.getElementById('stock-product'),
                stockQty: document.getElementById('stock-qty'),
                stockCost: document.getElementById('stock-cost'),
                stockNote: document.getElementById('stock-note'),
                addStockBtn: document.getElementById('add-stock-btn'),
                cancelStockEditBtn: document.getElementById('cancel-stock-edit-btn'),
                fundSourceType: document.getElementById('fund-source-type'),
                fundAccount: document.getElementById('fund-account'),
                fundAmount: document.getElementById('fund-amount'),
                fundNote: document.getElementById('fund-note'),
                addFundBtn: document.getElementById('add-fund-btn'),
                cancelFundEditBtn: document.getElementById('cancel-fund-edit-btn'),
                stockCount: document.getElementById('stock-count'),
                stockTotal: document.getElementById('stock-total'),
                fundCount: document.getElementById('fund-count'),
                fundTotal: document.getElementById('fund-total'),
                equityTarget: document.getElementById('equity-target'),
                balanceDeltaLabel: document.getElementById('balance-delta-label'),
                validationStatus: document.getElementById('validation-status'),
                validationMessage: document.getElementById('validation-message'),
                validationDebit: document.getElementById('validation-debit'),
                validationCredit: document.getElementById('validation-credit'),
                validateDraftBtn: document.getElementById('validate-draft-btn'),
                postDraftBtn: document.getElementById('post-draft-btn'),
                stockLinesBody: document.getElementById('stock-lines-body'),
                fundLinesBody: document.getElementById('fund-lines-body'),
                stockLinesCount: document.getElementById('stock-lines-count'),
                fundLinesCount: document.getElementById('fund-lines-count'),
                openingHistoryList: document.getElementById('opening-history-list'),
                openingHistoryCount: document.getElementById('opening-history-count'),
            };

            const state = {
                bootstrap: null,
                accounts: [],
                products: [],
                stockLines: [],
                fundLines: [],
                session: null,
                validation: null,
                productsAvailable: true,
                busy: false,
                editingStockLineId: null,
                editingFundLineId: null,
                openingHistory: [],
            };

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

            const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            }).format(Number(value || 0));

            const formatDate = (value) => {
                if (!value) {
                    return '-';
                }

                const parsed = new Date(value);
                if (Number.isNaN(parsed.getTime())) {
                    return '-';
                }

                return new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                }).format(parsed);
            };

            const formatDateTime = (value) => {
                if (!value) {
                    return '-';
                }

                const parsed = new Date(value);
                if (Number.isNaN(parsed.getTime())) {
                    return '-';
                }

                return new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                }).format(parsed);
            };

            const todayValue = () => new Date().toLocaleDateString('en-CA');

            const localTotals = () => {
                const stockTotal = state.stockLines.reduce((sum, line) => sum + Number(line.line_value || 0), 0);
                const fundTotal = state.fundLines.reduce((sum, line) => sum + Number(line.amount || 0), 0);
                const equityTarget = Number(state.session?.equity_amount ?? stockTotal + fundTotal);
                const balanceDelta = Math.abs((stockTotal + fundTotal) - equityTarget);

                return {
                    stockTotal,
                    fundTotal,
                    equityTarget,
                    balanceDelta,
                    debitTotal: stockTotal + fundTotal,
                };
            };

            const setMessage = (text, kind = 'info') => {
                if (!el.sessionMessage) {
                    return;
                }

                el.sessionMessage.classList.remove('hidden', 'border-emerald-500/30', 'bg-emerald-500/10', 'border-amber-500/30', 'bg-amber-500/10', 'border-rose-500/30', 'bg-rose-500/10');
                el.sessionMessage.textContent = text;

                if (kind === 'success') {
                    el.sessionMessage.classList.add('border-emerald-500/30', 'bg-emerald-500/10');
                } else if (kind === 'warning') {
                    el.sessionMessage.classList.add('border-amber-500/30', 'bg-amber-500/10');
                } else if (kind === 'error') {
                    el.sessionMessage.classList.add('border-rose-500/30', 'bg-rose-500/10');
                }
            };

            const setValidationMessage = (text, kind = 'neutral') => {
                if (!el.validationMessage) {
                    return;
                }

                el.validationMessage.textContent = text;
                el.validationStatus.textContent = kind === 'valid' ? 'Valid' : kind === 'invalid' ? 'Invalid' : 'Belum divalidasi';
                el.validationStatus.className = 'rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider';

                if (kind === 'valid') {
                    el.validationStatus.classList.add('bg-emerald-100', 'text-emerald-700');
                } else if (kind === 'invalid') {
                    el.validationStatus.classList.add('bg-rose-100', 'text-rose-700');
                } else {
                    el.validationStatus.classList.add('bg-slate-200', 'text-slate-700', 'dark:bg-slate-700', 'dark:text-slate-200');
                }
            };

            const setBusy = (busy) => {
                state.busy = busy;
                el.createDraftBtn.disabled = busy;
                el.createDraftBtnBottom.disabled = busy;
                el.validateBtn.disabled = busy || !state.session;
                el.postBtn.disabled = busy || !state.session || !state.validation;
                el.addStockBtn.disabled = busy || !state.session || !state.productsAvailable || !state.products.length;
                el.addFundBtn.disabled = busy || !state.session;
                el.cancelStockEditBtn.disabled = busy || !state.editingStockLineId;
                el.cancelFundEditBtn.disabled = busy || !state.editingFundLineId;
                el.validateDraftBtn.disabled = busy || !state.session;
            };

            const syncEditorButtons = () => {
                if (state.editingStockLineId) {
                    el.addStockBtn.innerHTML = '<span class="material-symbols-outlined text-base">save</span>Simpan Stok Awal';
                    el.cancelStockEditBtn.classList.remove('hidden');
                } else {
                    el.addStockBtn.innerHTML = '<span class="material-symbols-outlined text-base">add</span>Tambah Stok Awal';
                    el.cancelStockEditBtn.classList.add('hidden');
                }

                if (state.editingFundLineId) {
                    el.addFundBtn.innerHTML = '<span class="material-symbols-outlined text-base">save</span>Simpan Dana Awal';
                    el.cancelFundEditBtn.classList.remove('hidden');
                } else {
                    el.addFundBtn.innerHTML = '<span class="material-symbols-outlined text-base">add</span>Tambah Dana Awal';
                    el.cancelFundEditBtn.classList.add('hidden');
                }
            };

            const resetStockEditor = () => {
                state.editingStockLineId = null;
                el.stockQty.value = '';
                el.stockCost.value = '';
                el.stockNote.value = '';
                syncEditorButtons();
            };

            const resetFundEditor = () => {
                state.editingFundLineId = null;
                el.fundAmount.value = '';
                el.fundNote.value = '';
                syncEditorButtons();
            };

            const startStockEdit = (lineId) => {
                const line = state.stockLines.find((item) => String(item.id) === String(lineId));
                if (!line) {
                    return;
                }

                state.editingStockLineId = String(line.id);
                el.stockLocation.value = String(line.location_id || '');
                el.stockSearch.value = '';
                renderProductOptions();
                el.stockProduct.value = String(line.product_id || '');
                el.stockQty.value = Number(line.qty || 0) > 0 ? String(line.qty) : '';
                el.stockCost.value = Number(line.unit_cost || 0) > 0 ? String(line.unit_cost) : '';
                el.stockNote.value = line.reference_note || '';
                syncEditorButtons();
                setMessage('Mode edit stok awal aktif. Simpan untuk menerapkan perubahan.', 'warning');
            };

            const startFundEdit = (lineId) => {
                const line = state.fundLines.find((item) => String(item.id) === String(lineId));
                if (!line) {
                    return;
                }

                state.editingFundLineId = String(line.id);
                el.fundSourceType.value = String(line.source_type || 'cash');
                el.fundAccount.value = String(line.account_id || '');
                el.fundAmount.value = Number(line.amount || 0) > 0 ? String(line.amount) : '';
                el.fundNote.value = line.reference_note || '';
                syncEditorButtons();
                setMessage('Mode edit dana awal aktif. Simpan untuk menerapkan perubahan.', 'warning');
            };

            const getAccountLabel = (accountId) => {
                const account = state.accounts.find((item) => String(item.id) === String(accountId));
                if (!account) {
                    return '-';
                }

                return `${account.code ? `${account.code} - ` : ''}${account.name || '-'}`;
            };

            const getLocationLabel = (locationId) => {
                const location = state.bootstrap?.locations?.find((item) => String(item.id) === String(locationId));
                if (!location) {
                    return '-';
                }

                return location.name || '-';
            };

            const getProductLabel = (productId) => {
                const product = state.products.find((item) => String(item.id) === String(productId));
                if (!product) {
                    return '-';
                }

                return `${product.sku ? `${product.sku} - ` : ''}${product.name || '-'}`;
            };

            const getDefaultEquityAccountId = () => {
                const equityAccounts = state.accounts.filter((account) => String(account.type || '').toLowerCase() === 'equity');
                return equityAccounts[0]?.id ?? '';
            };

            const getDefaultFundAccountId = () => {
                const sourceType = String(el.fundSourceType?.value || 'cash');
                const mapping = state.bootstrap?.account_mapping || {};
                const mappedId = sourceType === 'bank' ? mapping.bank_account_id : mapping.cash_account_id;
                if (mappedId) {
                    return String(mappedId);
                }

                const assetAccounts = state.accounts.filter((account) => String(account.type || '').toLowerCase() === 'asset');
                return assetAccounts[0]?.id ?? '';
            };

            const populateSelect = (select, options, placeholder) => {
                if (!select) {
                    return;
                }

                select.innerHTML = '';
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = placeholder;
                select.appendChild(empty);

                options.forEach((optionData) => {
                    const option = document.createElement('option');
                    option.value = String(optionData.value);
                    option.textContent = optionData.label;
                    select.appendChild(option);
                });
            };

            const renderBootstrapOptions = () => {
                populateSelect(
                    el.equityAccount,
                    state.accounts
                        .filter((account) => String(account.type || '').toLowerCase() === 'equity')
                        .map((account) => ({
                            value: account.id,
                            label: `${account.code ? `${account.code} - ` : ''}${account.name || '-'}`,
                        })),
                    state.accounts.length ? 'Pilih akun equity' : 'Tidak ada akun equity'
                );

                populateSelect(
                    el.stockLocation,
                    (state.bootstrap?.locations || []).map((location) => ({
                        value: location.id,
                        label: location.is_default ? `${location.name} (default)` : location.name,
                    })),
                    state.bootstrap?.locations?.length ? 'Pilih lokasi' : 'Tidak ada lokasi'
                );

                const mapping = state.bootstrap?.account_mapping || {};
                const mappedFundAccountIds = [mapping.cash_account_id, mapping.bank_account_id]
                    .filter(Boolean)
                    .map((accountId) => String(accountId));
                const fundSourceLabel = (accountId) => {
                    if (String(accountId) === String(mapping.bank_account_id || '')) {
                        return 'Bank';
                    }

                    if (String(accountId) === String(mapping.cash_account_id || '')) {
                        return 'Kas';
                    }

                    return 'Aset';
                };
                const fundOptions = state.accounts
                    .filter((account) => mappedFundAccountIds.length
                        ? mappedFundAccountIds.includes(String(account.id))
                        : String(account.type || '').toLowerCase() === 'asset')
                    .map((account) => ({
                        value: account.id,
                        label: `${account.code ? `${account.code} - ` : ''}${account.name || '-'} (${fundSourceLabel(account.id)})`,
                    }));

                populateSelect(el.fundAccount, fundOptions, fundOptions.length ? 'Pilih akun kas / bank' : 'Tidak ada akun kas / bank');

                if (el.equityAccount && !el.equityAccount.value && state.accounts.length) {
                    el.equityAccount.value = getDefaultEquityAccountId();
                }

                if (el.stockLocation && !el.stockLocation.value && (state.bootstrap?.locations || []).length) {
                    const defaultLocation = (state.bootstrap.locations || []).find((location) => location.is_default);
                    el.stockLocation.value = defaultLocation?.id || state.bootstrap.locations[0]?.id || '';
                }

                if (el.fundAccount && !el.fundAccount.value && state.accounts.length) {
                    el.fundAccount.value = getDefaultFundAccountId();
                }
            };

            const renderProductOptions = () => {
                if (!el.stockProduct) {
                    return;
                }

                const term = String(el.stockSearch?.value || '').trim().toLowerCase();
                const filtered = state.products.filter((product) => {
                    if (!term) {
                        return true;
                    }

                    return String(product.name || '').toLowerCase().includes(term)
                        || String(product.sku || '').toLowerCase().includes(term);
                });

                populateSelect(
                    el.stockProduct,
                    filtered.map((product) => ({
                        value: product.id,
                        label: `${product.sku ? `${product.sku} - ` : ''}${product.name || '-'}`,
                    })),
                    filtered.length ? 'Pilih produk' : 'Produk tidak ditemukan'
                );
            };

            const renderStockLines = () => {
                if (!el.stockLinesBody) {
                    return;
                }

                el.stockLinesCount.textContent = `${state.stockLines.length} baris`;

                if (!state.stockLines.length) {
                    el.stockLinesBody.innerHTML = `
                        <tr>
                            <td class="px-5 py-4 text-sm text-slate-500" colspan="6">Belum ada baris stok awal.</td>
                        </tr>
                    `;
                    return;
                }

                el.stockLinesBody.innerHTML = state.stockLines.map((line) => `
                    <tr>
                        <td class="px-5 py-4 text-sm font-medium text-slate-700 dark:text-slate-200">${escapeHtml(getLocationLabel(line.location_id))}</td>
                        <td class="px-5 py-4 text-sm font-semibold text-slate-900 dark:text-white">${escapeHtml(getProductLabel(line.product_id))}</td>
                        <td class="px-5 py-4 text-right text-sm text-slate-700 dark:text-slate-200">${escapeHtml(Number(line.qty || 0).toLocaleString('id-ID'))}</td>
                        <td class="px-5 py-4 text-right text-sm text-slate-700 dark:text-slate-200">${escapeHtml(formatCurrency(line.unit_cost))}</td>
                        <td class="px-5 py-4 text-right text-sm font-bold text-slate-900 dark:text-white">${escapeHtml(formatCurrency(line.line_value))}</td>
                        <td class="px-5 py-4 text-right">
                            <div class="inline-flex items-center gap-2">
                                <button type="button" class="edit-stock-line inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50" data-line-id="${escapeHtml(line.id || '')}" ${line.id ? '' : 'disabled'}>
                                    Edit
                                </button>
                                <button type="button" class="delete-stock-line inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50" data-line-id="${escapeHtml(line.id || '')}" ${line.id ? '' : 'disabled'}>
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');

                el.stockLinesBody.querySelectorAll('.edit-stock-line').forEach((button) => {
                    button.addEventListener('click', () => {
                        const lineId = button.getAttribute('data-line-id') || '';
                        if (lineId) {
                            startStockEdit(lineId);
                        }
                    });
                });

                el.stockLinesBody.querySelectorAll('.delete-stock-line').forEach((button) => {
                    button.addEventListener('click', () => {
                        const lineId = button.getAttribute('data-line-id') || '';
                        if (lineId) {
                            deleteStockLine(lineId);
                        }
                    });
                });
            };

            const renderFundLines = () => {
                if (!el.fundLinesBody) {
                    return;
                }

                el.fundLinesCount.textContent = `${state.fundLines.length} baris`;

                if (!state.fundLines.length) {
                    el.fundLinesBody.innerHTML = `
                        <tr>
                            <td class="px-5 py-4 text-sm text-slate-500" colspan="5">Belum ada baris dana awal.</td>
                        </tr>
                    `;
                    return;
                }

                el.fundLinesBody.innerHTML = state.fundLines.map((line) => `
                    <tr>
                        <td class="px-5 py-4 text-sm font-medium text-slate-700 dark:text-slate-200">${escapeHtml(String(line.source_type || '-').toUpperCase())}</td>
                        <td class="px-5 py-4 text-sm font-semibold text-slate-900 dark:text-white">${escapeHtml(getAccountLabel(line.account_id))}</td>
                        <td class="px-5 py-4 text-right text-sm font-bold text-slate-900 dark:text-white">${escapeHtml(formatCurrency(line.amount))}</td>
                        <td class="px-5 py-4 text-sm text-slate-500 dark:text-slate-400">${escapeHtml(line.reference_note || '-')}</td>
                        <td class="px-5 py-4 text-right">
                            <div class="inline-flex items-center gap-2">
                                <button type="button" class="edit-fund-line inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50" data-line-id="${escapeHtml(line.id || '')}" ${line.id ? '' : 'disabled'}>
                                    Edit
                                </button>
                                <button type="button" class="delete-fund-line inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50" data-line-id="${escapeHtml(line.id || '')}" ${line.id ? '' : 'disabled'}>
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');

                el.fundLinesBody.querySelectorAll('.edit-fund-line').forEach((button) => {
                    button.addEventListener('click', () => {
                        const lineId = button.getAttribute('data-line-id') || '';
                        if (lineId) {
                            startFundEdit(lineId);
                        }
                    });
                });

                el.fundLinesBody.querySelectorAll('.delete-fund-line').forEach((button) => {
                    button.addEventListener('click', () => {
                        const lineId = button.getAttribute('data-line-id') || '';
                        if (lineId) {
                            deleteFundLine(lineId);
                        }
                    });
                });
            };

            const renderOpeningHistory = () => {
                if (!el.openingHistoryList || !el.openingHistoryCount) {
                    return;
                }

                const history = Array.isArray(state.openingHistory) ? state.openingHistory : [];
                el.openingHistoryCount.textContent = `${history.length} posting`;

                if (!history.length) {
                    el.openingHistoryList.innerHTML = `
                        <div class="px-5 py-5 text-sm text-slate-500 dark:text-slate-400">
                            Belum ada saldo awal yang diposting.
                        </div>
                    `;
                    return;
                }

                el.openingHistoryList.innerHTML = history.map((item) => {
                    const funds = Array.isArray(item.funds) ? item.funds : [];
                    const fundRows = funds.length
                        ? funds.map((fund) => {
                            const account = `${fund.account_code ? `${fund.account_code} - ` : ''}${fund.account_name || 'Akun kas/bank'}`;
                            return `
                                <div class="flex items-center justify-between gap-3 rounded-xl bg-white px-3 py-2 text-sm dark:bg-slate-950/70">
                                    <span class="min-w-0 truncate font-semibold text-slate-700 dark:text-slate-200">${escapeHtml(String(fund.source_type || '').toUpperCase())} - ${escapeHtml(account)}</span>
                                    <span class="shrink-0 font-bold text-slate-900 dark:text-white">${escapeHtml(formatCurrency(fund.amount))}</span>
                                </div>
                            `;
                        }).join('')
                        : '<div class="rounded-xl bg-white px-3 py-2 text-sm text-slate-500 dark:bg-slate-950/70 dark:text-slate-400">Tidak ada dana kas/bank pada posting ini.</div>';
                    const journalLabel = item.journal_entry_id ? String(item.journal_entry_id).slice(0, 8) : '-';

                    return `
                        <article class="px-5 py-5">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Tanggal buka ${escapeHtml(formatDate(item.opening_date))}</p>
                                    <h4 class="mt-1 text-base font-bold text-slate-900 dark:text-white">Diposting ${escapeHtml(formatDateTime(item.posted_at))}</h4>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Jurnal: ${escapeHtml(journalLabel)}</p>
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-right text-xs sm:min-w-[360px]">
                                    <div class="rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-800/70">
                                        <p class="font-bold uppercase tracking-wider text-slate-400">Stok</p>
                                        <p class="mt-1 font-bold text-slate-900 dark:text-white">${escapeHtml(formatCurrency(item.stock_total))}</p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-800/70">
                                        <p class="font-bold uppercase tracking-wider text-slate-400">Kas/Bank</p>
                                        <p class="mt-1 font-bold text-slate-900 dark:text-white">${escapeHtml(formatCurrency(item.fund_total))}</p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-800/70">
                                        <p class="font-bold uppercase tracking-wider text-slate-400">Modal</p>
                                        <p class="mt-1 font-bold text-slate-900 dark:text-white">${escapeHtml(formatCurrency(item.equity_total))}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 space-y-2 rounded-2xl bg-slate-50 p-3 dark:bg-slate-800/50">
                                ${fundRows}
                            </div>
                        </article>
                    `;
                }).join('');
            };

            const renderSummary = () => {
                const totals = localTotals();
                const hasSession = Boolean(state.session);
                const sessionStatusLabel = state.session?.status ? String(state.session.status).toUpperCase() : 'BELUM ADA DRAFT';

                el.sessionStatus.textContent = hasSession ? sessionStatusLabel : 'Belum ada draft';
                el.sessionId.textContent = state.session?.id ? String(state.session.id) : '-';
                el.sessionDate.textContent = state.session?.opening_date ? formatDate(state.session.opening_date) : (el.openingDate?.value ? formatDate(el.openingDate.value) : '-');
                el.sessionDelta.textContent = formatCurrency(totals.balanceDelta);
                el.heroStockValue.textContent = formatCurrency(totals.stockTotal);
                el.heroFundValue.textContent = formatCurrency(totals.fundTotal);
                el.stockTotal.textContent = formatCurrency(totals.stockTotal);
                el.fundTotal.textContent = formatCurrency(totals.fundTotal);
                el.equityTarget.textContent = formatCurrency(totals.equityTarget);
                el.balanceDeltaLabel.textContent = formatCurrency(totals.balanceDelta);
                el.stockCount.textContent = String(state.stockLines.length);
                el.fundCount.textContent = String(state.fundLines.length);
                el.validationDebit.textContent = formatCurrency(state.validation?.total_debit ?? totals.debitTotal);
                el.validationCredit.textContent = formatCurrency(state.validation?.equity_total ?? totals.equityTarget);

                if (state.session?.status === 'posted') {
                    setValidationMessage('Saldo awal sudah diposting dan terkunci.', 'valid');
                } else if (state.validation) {
                    setValidationMessage('Draft valid dan siap diposting.', 'valid');
                } else {
                    setValidationMessage('Tambahkan baris lalu jalankan validasi untuk memastikan saldo seimbang.');
                }

                if (state.validation?.status === 'invalid') {
                    setValidationMessage(state.validation.message || 'Draft belum valid.', 'invalid');
                }

                setBusy(state.busy);
            };

            const applySession = (session) => {
                state.session = session || null;
                state.validation = null;
                state.editingStockLineId = null;
                state.editingFundLineId = null;
                state.stockLines = Array.isArray(session?.stock_lines) ? session.stock_lines.slice() : [];
                state.fundLines = Array.isArray(session?.fund_lines) ? session.fund_lines.slice() : [];

                if (el.equityAccount && session?.equity_account_id) {
                    el.equityAccount.value = String(session.equity_account_id);
                }
                if (el.equityAmount && session?.equity_amount !== null && session?.equity_amount !== undefined) {
                    el.equityAmount.value = String(session.equity_amount);
                }
                if (el.openingDate && session?.opening_date) {
                    el.openingDate.value = String(session.opening_date);
                }

                if (session?.status === 'posted') {
                    setMessage('Saldo awal berhasil diposting.', 'success');
                } else {
                    setMessage('Draft saldo awal siap diisi dengan stok dan dana awal.', 'success');
                }

                renderAll();
            };

            const renderAll = () => {
                renderBootstrapOptions();
                renderProductOptions();
                renderStockLines();
                renderFundLines();
                renderOpeningHistory();
                renderSummary();
                syncEditorButtons();
                if (!state.productsAvailable) {
                    el.stockUnavailable.classList.remove('hidden');
                }
            };

            const apiRequest = async (url, options = {}) => {
                const response = await fetch(url, {
                    ...options,
                    headers: {
                        ...headers,
                        ...(options.headers || {}),
                    },
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const message = payload?.message || payload?.error || 'Permintaan gagal.';
                    const error = new Error(message);
                    error.payload = payload;
                    error.status = response.status;
                    throw error;
                }

                return payload;
            };

            const loadBootstrap = async () => {
                const [bootstrapPayload, accountPayload] = await Promise.all([
                    apiRequest('/api/v1/bootstrap'),
                    apiRequest('/api/v1/chart-of-accounts'),
                ]);

                state.bootstrap = bootstrapPayload;
                state.accounts = Array.isArray(accountPayload?.accounts) ? accountPayload.accounts : [];

                renderBootstrapOptions();
                renderSummary();
            };

            const loadProducts = async () => {
                try {
                    const payload = await apiRequest('/api/v1/products?lite=1');
                    state.products = Array.isArray(payload?.products) ? payload.products : [];
                    state.productsAvailable = true;
                    if (el.stockUnavailable) {
                        el.stockUnavailable.classList.add('hidden');
                        el.stockUnavailable.textContent = '';
                    }
                    renderProductOptions();
                } catch (error) {
                    state.products = [];
                    state.productsAvailable = false;
                    if (el.stockUnavailable) {
                        el.stockUnavailable.textContent = error.message || 'Daftar produk tidak tersedia untuk tenant ini.';
                        el.stockUnavailable.classList.remove('hidden');
                    }
                }

                renderSummary();
            };

            const createDraft = async () => {
                if (!el.openingDate.value) {
                    setMessage('Tanggal mulai wajib diisi sebelum membuat draft.', 'error');
                    return;
                }

                if (state.session?.status === 'draft' && state.session?.id) {
                    setMessage('Draft aktif sudah ada. Lanjutkan mengisi line yang tersedia.', 'warning');
                    return;
                }

                setBusy(true);
                setMessage('Membuat draft saldo awal...', 'info');

                try {
                    const payload = await apiRequest('/admin/opening-balance', {
                        method: 'POST',
                        body: JSON.stringify({
                            opening_date: el.openingDate.value,
                            equity_account_id: el.equityAccount.value || null,
                            equity_amount: el.equityAmount.value !== '' ? Number(el.equityAmount.value) : null,
                            meta: {
                                note: el.draftNotes.value || null,
                                source: 'web-admin-opening-balance',
                            },
                        }),
                    });

                    applySession(payload?.session || null);
                    setMessage('Draft berhasil dibuat. Lanjutkan dengan stok awal dan dana awal.', 'success');
                } catch (error) {
                    setMessage(error.message || 'Gagal membuat draft saldo awal.', 'error');
                }

                setBusy(false);
                renderSummary();
            };

            const loadCurrentDraft = async () => {
                const payload = await apiRequest('/admin/opening-balance/current');

                if (payload?.session) {
                    applySession(payload.session);
                    setMessage('Draft saldo awal aktif berhasil dipulihkan.', 'success');
                    return true;
                }

                return false;
            };

            const loadOpeningHistory = async () => {
                const payload = await apiRequest('/admin/opening-balance/history');
                state.openingHistory = Array.isArray(payload?.history) ? payload.history : [];
                renderOpeningHistory();
            };

            const addStockLine = async () => {
                if (!state.session?.id) {
                    setMessage('Buat draft terlebih dahulu.', 'warning');
                    return;
                }

                const locationId = el.stockLocation.value;
                const productId = el.stockProduct.value;
                const qty = Number(el.stockQty.value || 0);
                const unitCost = Number(el.stockCost.value || 0);

                if (!locationId || !productId || qty <= 0 || unitCost <= 0) {
                    setMessage('Lokasi, produk, qty, dan harga modal satuan harus diisi.', 'error');
                    return;
                }

                setBusy(true);
                setMessage(state.editingStockLineId ? 'Memperbarui stok awal...' : 'Menambahkan stok awal...', 'info');

                try {
                    const endpoint = state.editingStockLineId
                        ? `/admin/opening-balance/${encodeURIComponent(state.session.id)}/stock/${encodeURIComponent(state.editingStockLineId)}`
                        : `/admin/opening-balance/${encodeURIComponent(state.session.id)}/stock`;
                    const method = state.editingStockLineId ? 'PATCH' : 'POST';

                    const payload = await apiRequest(endpoint, {
                        method,
                        body: JSON.stringify({
                            location_id: locationId,
                            product_id: productId,
                            product_variant_id: null,
                            qty,
                            unit_cost: unitCost,
                            reference_note: el.stockNote.value || null,
                        }),
                    });

                    const line = payload?.line || {
                        id: state.editingStockLineId,
                        location_id: locationId,
                        product_id: productId,
                        qty,
                        unit_cost: unitCost,
                        line_value: qty * unitCost,
                        reference_note: el.stockNote.value || null,
                    };

                    if (state.editingStockLineId) {
                        state.stockLines = state.stockLines.map((item) => String(item.id) === String(state.editingStockLineId) ? line : item);
                    } else {
                        state.stockLines.push(line);
                    }

                    resetStockEditor();
                    state.validation = null;
                    setMessage(method === 'PATCH' ? 'Stok awal berhasil diperbarui.' : 'Stok awal berhasil ditambahkan.', 'success');
                    renderAll();
                } catch (error) {
                    setMessage(error.message || (state.editingStockLineId ? 'Gagal memperbarui stok awal.' : 'Gagal menambahkan stok awal.'), 'error');
                }

                setBusy(false);
                renderSummary();
            };

            const addFundLine = async () => {
                if (!state.session?.id) {
                    setMessage('Buat draft terlebih dahulu.', 'warning');
                    return;
                }

                const accountId = el.fundAccount.value;
                const amount = Number(el.fundAmount.value || 0);
                const sourceType = el.fundSourceType.value;

                if (!accountId || amount <= 0) {
                    setMessage('Akun dan nominal dana awal harus diisi.', 'error');
                    return;
                }

                setBusy(true);
                setMessage(state.editingFundLineId ? 'Memperbarui dana awal...' : 'Menambahkan dana awal...', 'info');

                try {
                    const endpoint = state.editingFundLineId
                        ? `/admin/opening-balance/${encodeURIComponent(state.session.id)}/fund/${encodeURIComponent(state.editingFundLineId)}`
                        : `/admin/opening-balance/${encodeURIComponent(state.session.id)}/fund`;
                    const method = state.editingFundLineId ? 'PATCH' : 'POST';

                    const payload = await apiRequest(endpoint, {
                        method,
                        body: JSON.stringify({
                            account_id: accountId,
                            source_type: sourceType,
                            amount,
                            reference_note: el.fundNote.value || null,
                        }),
                    });

                    const line = payload?.line || {
                        id: state.editingFundLineId,
                        account_id: accountId,
                        source_type: sourceType,
                        amount,
                        reference_note: el.fundNote.value || null,
                    };

                    if (state.editingFundLineId) {
                        state.fundLines = state.fundLines.map((item) => String(item.id) === String(state.editingFundLineId) ? line : item);
                    } else {
                        state.fundLines.push(line);
                    }

                    resetFundEditor();
                    state.validation = null;
                    setMessage(method === 'PATCH' ? 'Dana awal berhasil diperbarui.' : 'Dana awal berhasil ditambahkan.', 'success');
                    renderAll();
                } catch (error) {
                    setMessage(error.message || (state.editingFundLineId ? 'Gagal memperbarui dana awal.' : 'Gagal menambahkan dana awal.'), 'error');
                }

                setBusy(false);
                renderSummary();
            };

            const deleteStockLine = async (lineId) => {
                if (!state.session?.id || !lineId) {
                    return;
                }

                if (!await window.PosUi.confirmRiskActionAsync({
                    title: 'Hapus Stok Awal',
                    message: 'Baris stok awal ini akan dihapus dari draft saldo awal. Lanjutkan?',
                    confirmText: 'Hapus',
                    icon: 'delete',
                })) {
                    return;
                }

                setBusy(true);
                setMessage('Menghapus stok awal...', 'info');

                try {
                    await apiRequest(`/admin/opening-balance/${encodeURIComponent(state.session.id)}/stock/${encodeURIComponent(lineId)}`, {
                        method: 'DELETE',
                    });

                    state.stockLines = state.stockLines.filter((line) => String(line.id) !== String(lineId));
                    if (String(state.editingStockLineId || '') === String(lineId)) {
                        resetStockEditor();
                    }
                    state.validation = null;
                    setMessage('Stok awal berhasil dihapus.', 'success');
                    renderAll();
                } catch (error) {
                    setMessage(error.message || 'Gagal menghapus stok awal.', 'error');
                }

                setBusy(false);
                renderSummary();
            };

            const deleteFundLine = async (lineId) => {
                if (!state.session?.id || !lineId) {
                    return;
                }

                if (!await window.PosUi.confirmRiskActionAsync({
                    title: 'Hapus Dana Awal',
                    message: 'Baris dana awal ini akan dihapus dari draft saldo awal. Lanjutkan?',
                    confirmText: 'Hapus',
                    icon: 'delete',
                })) {
                    return;
                }

                setBusy(true);
                setMessage('Menghapus dana awal...', 'info');

                try {
                    await apiRequest(`/admin/opening-balance/${encodeURIComponent(state.session.id)}/fund/${encodeURIComponent(lineId)}`, {
                        method: 'DELETE',
                    });

                    state.fundLines = state.fundLines.filter((line) => String(line.id) !== String(lineId));
                    if (String(state.editingFundLineId || '') === String(lineId)) {
                        resetFundEditor();
                    }
                    state.validation = null;
                    setMessage('Dana awal berhasil dihapus.', 'success');
                    renderAll();
                } catch (error) {
                    setMessage(error.message || 'Gagal menghapus dana awal.', 'error');
                }

                setBusy(false);
                renderSummary();
            };

            const validateDraft = async () => {
                if (!state.session?.id) {
                    setMessage('Buat draft terlebih dahulu.', 'warning');
                    return;
                }

                setBusy(true);
                setMessage('Menjalankan validasi draft...', 'info');

                try {
                    const payload = await apiRequest('/admin/opening-balance/validate', {
                        method: 'POST',
                        body: JSON.stringify({
                            session_id: state.session.id,
                        }),
                    });

                    state.validation = payload;
                    setValidationMessage('Draft valid dan siap diposting.', 'valid');
                    setMessage('Validasi berhasil. Draft siap diposting.', 'success');
                } catch (error) {
                    state.validation = {
                        status: 'invalid',
                        message: error.message || 'Draft belum valid.',
                    };
                    setValidationMessage(error.message || 'Draft belum valid.', 'invalid');
                    setMessage(error.message || 'Draft belum valid.', 'error');
                }

                setBusy(false);
                renderSummary();
            };

            const postDraft = async () => {
                if (!state.session?.id) {
                    setMessage('Buat draft terlebih dahulu.', 'warning');
                    return;
                }

                if (!state.validation) {
                    setMessage('Jalankan validasi sebelum posting.', 'warning');
                    return;
                }

                setBusy(true);
                setMessage('Posting saldo awal...', 'info');

                try {
                    const payload = await apiRequest('/admin/opening-balance/post', {
                        method: 'POST',
                        body: JSON.stringify({
                            session_id: state.session.id,
                        }),
                    });

                    applySession(payload?.session || state.session);
                    await loadOpeningHistory();
                    setMessage('Saldo awal berhasil diposting dan periode awal dikunci.', 'success');
                    el.validateBtn.disabled = true;
                    el.postBtn.disabled = true;
                    el.addStockBtn.disabled = true;
                    el.addFundBtn.disabled = true;
                    el.validateDraftBtn.disabled = true;
                } catch (error) {
                    setMessage(error.message || 'Gagal memposting saldo awal.', 'error');
                }

                setBusy(false);
                renderSummary();
            };

            const defaultOpeningDate = () => {
                if (el.openingDate && !el.openingDate.value) {
                    el.openingDate.value = todayValue();
                }
            };

            const bindEvents = () => {
                el.createDraftBtn.addEventListener('click', createDraft);
                el.createDraftBtnBottom.addEventListener('click', createDraft);
                el.addStockBtn.addEventListener('click', addStockLine);
                el.addFundBtn.addEventListener('click', addFundLine);
                el.cancelStockEditBtn.addEventListener('click', () => {
                    resetStockEditor();
                    setMessage('Mode edit stok awal dibatalkan.', 'info');
                });
                el.cancelFundEditBtn.addEventListener('click', () => {
                    resetFundEditor();
                    setMessage('Mode edit dana awal dibatalkan.', 'info');
                });
                el.validateBtn.addEventListener('click', validateDraft);
                el.validateDraftBtn.addEventListener('click', validateDraft);
                el.postBtn.addEventListener('click', postDraft);
                el.stockSearch.addEventListener('input', () => renderProductOptions());
                el.fundSourceType.addEventListener('change', () => {
                    const defaultFundAccountId = getDefaultFundAccountId();
                    if (defaultFundAccountId) {
                        el.fundAccount.value = defaultFundAccountId;
                    }
                });
            };

            const init = async () => {
                defaultOpeningDate();
                bindEvents();
                renderSummary();
                setBusy(true);

                try {
                    await loadBootstrap();
                } catch (error) {
                    setMessage(error.message || 'Gagal memuat data saldo awal.', 'error');
                }

                try {
                    await loadProducts();
                } catch (error) {
                    setMessage(error.message || 'Gagal memuat data produk.', 'error');
                }

                try {
                    await loadCurrentDraft();
                } catch (error) {
                    if ((error?.status ?? null) !== 404) {
                        setMessage(error.message || 'Gagal memulihkan draft saldo awal.', 'warning');
                    }
                }

                try {
                    await loadOpeningHistory();
                } catch (error) {
                    state.openingHistory = [];
                    renderOpeningHistory();
                    setMessage(error.message || 'Gagal memuat histori saldo awal.', 'warning');
                }

                renderAll();
                setBusy(false);
                setValidationMessage('Tambahkan baris lalu jalankan validasi untuk memastikan saldo awal siap posting.');
            };

            init().catch((error) => {
                setMessage(error.message || 'Gagal memuat halaman saldo awal.', 'error');
            });
        })();
    </script>
@endsection
