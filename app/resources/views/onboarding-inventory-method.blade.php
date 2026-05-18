@extends('layouts.app')

@section('page_title', 'Pengaturan Stok Awal')
@section('body_class', 'bg-slate-50 text-slate-900 font-display')
@section('app_shell_class', 'relative flex h-screen w-full flex-col overflow-hidden bg-slate-50')
@section('main_class', 'flex-1 overflow-y-auto p-0')

@section('content')
    @php
        $selectedValuationMethod = old('valuation_method', session('onboarding.step2.valuation_method', 'fifo'));
    @endphp

    <form id="onboarding-step-3-form" method="POST" action="/register/step-3" class="min-h-full">
        @csrf

        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <a href="/register/step-2" class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50" aria-label="Kembali">
                        <span class="material-symbols-outlined text-[20px]">arrow_back_ios_new</span>
                    </a>
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-primary">Langkah 3 dari 5</p>
                        <h1 class="truncate text-lg font-black text-slate-950">Pengaturan Stok Awal</h1>
                    </div>
                </div>
                <a href="/register/step-4" class="hidden text-sm font-bold text-slate-500 transition hover:text-slate-900 sm:inline-flex">Lewati nanti</a>
            </div>
        </header>

        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-6 sm:px-6 lg:grid-cols-[0.85fr_1.15fr] lg:px-8 lg:py-10">
            <aside class="space-y-6">
                <div>
                    <p class="text-sm font-bold text-primary">Metode persediaan</p>
                    <h2 class="mt-2 text-3xl font-black leading-tight text-slate-950 lg:text-4xl">Pilih cara sistem menghitung HPP dan nilai stok.</h2>
                    <p class="mt-3 max-w-xl text-sm leading-6 text-slate-600">Pengaturan ini menjadi dasar laporan stok, harga pokok penjualan, dan valuation. Pilih yang paling cocok untuk cara toko mengelola barang.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                <span class="material-symbols-outlined text-[22px]">verified</span>
                            </span>
                            <div>
                                <p class="text-sm font-black text-slate-900">Aman untuk laporan</p>
                                <p class="text-xs text-slate-500">Dipakai otomatis saat transaksi.</p>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-lg bg-orange-50 text-primary">
                                <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                            </span>
                            <div>
                                <p class="text-sm font-black text-slate-900">Siap untuk stok awal</p>
                                <p class="text-xs text-slate-500">Dilanjutkan ke input produk pertama.</p>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="flex size-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                                <span class="material-symbols-outlined text-[22px]">tune</span>
                            </span>
                            <div>
                                <p class="text-sm font-black text-slate-900">Bisa ditinjau lagi</p>
                                <p class="text-xs text-slate-500">Admin dapat cek ulang di pengaturan.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm leading-6 text-primary">
                    <div class="flex gap-3">
                        <span class="material-symbols-outlined text-[22px]">info</span>
                        <p>Untuk toko makanan, retail, dan stok batch sederhana, FIFO biasanya paling mudah diaudit. Average cocok jika harga beli sering berubah dan stok dicampur.</p>
                    </div>
                </div>
            </aside>

            <section class="space-y-4">
                @if ($errors->any())
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Pilih metode</p>
                            <h3 class="mt-1 text-xl font-black text-slate-950">Metode Penilaian Inventaris</h3>
                        </div>
                        <span class="hidden rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 sm:inline-flex">Wajib dipilih</span>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <label id="method-card-fifo" class="group cursor-pointer rounded-xl border bg-white p-4 transition {{ $selectedValuationMethod === 'fifo' ? 'border-primary ring-4 ring-primary/10' : 'border-slate-200 hover:border-primary/50' }}">
                            <input id="method-fifo" value="fifo" {{ $selectedValuationMethod === 'fifo' ? 'checked' : '' }} class="sr-only" name="valuation_method" type="radio" />
                            <div class="flex gap-4">
                                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <span class="material-symbols-outlined text-[26px]">inventory_2</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="text-base font-black text-slate-950">FIFO</span>
                                        <span class="rounded-full bg-primary/10 px-2 py-1 text-[10px] font-black uppercase tracking-wider text-primary">Rekomendasi</span>
                                    </span>
                                    <span class="mt-1 block text-sm font-semibold text-slate-600">First-In, First-Out</span>
                                    <span class="mt-2 block text-sm leading-6 text-slate-500">Barang yang masuk lebih dulu dianggap keluar lebih dulu. Cocok untuk stok yang punya urutan pembelian jelas.</span>
                                </span>
                                <span data-check="fifo" class="material-symbols-outlined shrink-0 {{ $selectedValuationMethod === 'fifo' ? 'text-primary' : 'text-slate-300' }}">{{ $selectedValuationMethod === 'fifo' ? 'check_circle' : 'radio_button_unchecked' }}</span>
                            </div>
                        </label>

                        <label id="method-card-average" class="group cursor-pointer rounded-xl border bg-white p-4 transition {{ $selectedValuationMethod === 'average' ? 'border-primary ring-4 ring-primary/10' : 'border-slate-200 hover:border-primary/50' }}">
                            <input id="method-average" value="average" {{ $selectedValuationMethod === 'average' ? 'checked' : '' }} class="sr-only" name="valuation_method" type="radio" />
                            <div class="flex gap-4">
                                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                                    <span class="material-symbols-outlined text-[26px]">calculate</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="text-base font-black text-slate-950">Average Cost</span>
                                    <span class="mt-1 block text-sm font-semibold text-slate-600">Biaya rata-rata</span>
                                    <span class="mt-2 block text-sm leading-6 text-slate-500">Nilai stok dihitung dari rata-rata biaya perolehan unit yang tersedia.</span>
                                </span>
                                <span data-check="average" class="material-symbols-outlined shrink-0 {{ $selectedValuationMethod === 'average' ? 'text-primary' : 'text-slate-300' }}">{{ $selectedValuationMethod === 'average' ? 'check_circle' : 'radio_button_unchecked' }}</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <a href="/register/step-2" class="inline-flex justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Kembali</a>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-black text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90">
                        Simpan & lanjut
                        <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                    </button>
                </div>
            </section>
        </div>
    </form>

    <script>
        (() => {
            const methodFifo = document.getElementById('method-fifo');
            const methodAverage = document.getElementById('method-average');
            const cardFifo = document.getElementById('method-card-fifo');
            const cardAverage = document.getElementById('method-card-average');
            const fifoCheck = document.querySelector('[data-check="fifo"]');
            const avgCheck = document.querySelector('[data-check="average"]');

            if (!methodFifo || !methodAverage || !cardFifo || !cardAverage || !fifoCheck || !avgCheck) {
                return;
            }

            const setCardState = (card, check, selected) => {
                card.classList.toggle('border-primary', selected);
                card.classList.toggle('ring-4', selected);
                card.classList.toggle('ring-primary/10', selected);
                card.classList.toggle('border-slate-200', !selected);
                card.classList.toggle('hover:border-primary/50', !selected);
                check.textContent = selected ? 'check_circle' : 'radio_button_unchecked';
                check.className = `material-symbols-outlined shrink-0 ${selected ? 'text-primary' : 'text-slate-300'}`;
            };

            const syncMethodCards = () => {
                setCardState(cardFifo, fifoCheck, methodFifo.checked);
                setCardState(cardAverage, avgCheck, methodAverage.checked);
            };

            methodFifo.addEventListener('change', syncMethodCards);
            methodAverage.addEventListener('change', syncMethodCards);
            syncMethodCards();
        })();
    </script>
@endsection
