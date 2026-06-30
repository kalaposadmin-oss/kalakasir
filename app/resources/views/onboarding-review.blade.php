@extends('layouts.app')

@section('page_title', 'Review Onboarding')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[768px] flex-col overflow-hidden bg-white dark:bg-slate-900 shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto p-0 app-bottom-safe-space')

@section('content')
    @php
        $product = $stepFour['product'] ?? [];
        $skipProduct = (bool) ($stepFour['skip_product'] ?? false);
        $ownerName = $stepOne['name'] ?? '-';
        $storeName = $stepTwo['store_name'] ?? '-';
        $valuationLabel = $valuationLabels[$stepThree['valuation_method'] ?? ''] ?? '-';
        $selectedPlan = $selectedPlan ?? 'M';
        $productStatusLabel = $skipProduct ? 'Akan diatur nanti' : 'Siap dibuat saat selesai';
        $businessCategoryLabels = [
            'fnb' => 'Food and Beverages',
            'retail' => 'Retail',
            'service' => 'Jasa/Service',
            'other' => 'Lainnya',
        ];
        $valuationLabels = [
            'fifo' => 'FIFO (First-In, First-Out)',
            'average' => 'Average Cost (Biaya Rata-rata)',
        ];
    @endphp

    <header class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
        <a href="/register/step-4" class="flex size-10 items-center justify-center rounded-full text-slate-900 hover:bg-slate-100 dark:text-slate-100 dark:hover:bg-slate-800">
            <span class="material-symbols-outlined">arrow_back_ios_new</span>
        </a>
        <h2 class="text-lg font-bold leading-tight tracking-tight">Review Sebelum Masuk</h2>
        <div class="size-10"></div>
    </header>

    <div class="flex items-center justify-center gap-3 bg-white px-4 py-5 dark:bg-slate-900">
        <div class="h-2 w-8 rounded-full bg-primary"></div>
        <div class="h-2 w-8 rounded-full bg-primary"></div>
        <div class="h-2 w-8 rounded-full bg-primary"></div>
        <div class="h-2 w-8 rounded-full bg-primary"></div>
        <div class="h-2 w-8 rounded-full bg-primary"></div>
    </div>

    <div class="border-b border-slate-100 bg-white px-4 pb-5 dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm font-medium text-slate-500">Langkah 5 dari 5</p>
        <h3 class="mt-2 text-2xl font-bold tracking-tight">Periksa Ringkasan Onboarding</h3>
        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">Pastikan data akun, toko, metode stok, dan produk awal sudah sesuai. Jika perlu, Anda bisa kembali ke langkah terkait untuk mengubahnya.</p>
        <div class="mt-4 rounded-3xl bg-slate-950 px-4 py-4 text-white shadow-xl shadow-slate-900/10 dark:bg-slate-800">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">Siap Aktivasi</p>
            <h4 class="mt-2 text-xl font-bold leading-tight">{{ $storeName }}</h4>
            <p class="mt-1 text-sm leading-relaxed text-slate-300">Owner utama {{ $ownerName }} akan langsung masuk ke dashboard setelah konfirmasi akhir.</p>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                <span class="rounded-full bg-white/10 px-3 py-1.5">{{ $valuationLabel }}</span>
                <span class="rounded-full bg-white/10 px-3 py-1.5">{{ $productStatusLabel }}</span>
            </div>
        </div>
    </div>

    <div class="flex flex-col gap-4 px-4 py-4 pb-28">
        <section class="rounded-3xl border border-slate-200 bg-slate-50 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Akun Owner</p>
                    <h4 class="mt-1 text-lg font-bold">{{ $stepOne['name'] ?? '-' }}</h4>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Akun ini akan menjadi Owner utama dan bisa mengatur operasional toko setelah onboarding selesai.</p>
                </div>
                <a href="/register/step-1" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">Edit</a>
            </div>
            <dl class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                <div class="flex items-start justify-between gap-4 rounded-2xl bg-white px-3 py-2.5 dark:bg-slate-950/40">
                    <dt>Email</dt>
                    <dd class="text-right font-medium text-slate-900 dark:text-slate-100">{{ $stepOne['email'] ?? '-' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4 rounded-2xl bg-white px-3 py-2.5 dark:bg-slate-950/40">
                    <dt>Nomor Telepon</dt>
                    <dd class="text-right font-medium text-slate-900 dark:text-slate-100">{{ $stepOne['phone'] ?? '-' }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-slate-50 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Profil Toko</p>
                    <h4 class="mt-1 text-lg font-bold">{{ $stepTwo['store_name'] ?? '-' }}</h4>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Informasi dasar toko ini dipakai sebagai identitas operasional awal pada dashboard dan laporan.</p>
                </div>
                <a href="/register/step-2" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">Edit</a>
            </div>
            <dl class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                <div class="flex items-start justify-between gap-4 rounded-2xl bg-white px-3 py-2.5 dark:bg-slate-950/40">
                    <dt>Kategori Bisnis</dt>
                    <dd class="text-right font-medium text-slate-900 dark:text-slate-100">{{ $businessCategoryLabels[$stepTwo['business_category'] ?? ''] ?? '-' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4 rounded-2xl bg-white px-3 py-2.5 dark:bg-slate-950/40">
                    <dt>Zona Waktu</dt>
                    <dd class="text-right font-medium text-slate-900 dark:text-slate-100">{{ $stepTwo['timezone'] ?? 'Asia/Jakarta' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4 rounded-2xl bg-white px-3 py-2.5 dark:bg-slate-950/40">
                    <dt>Alamat Operasional</dt>
                    <dd class="max-w-[65%] text-right font-medium text-slate-900 dark:text-slate-100">{{ $stepTwo['operational_address'] ?? '-' }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-slate-50 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Metode Inventaris</p>
                    <h4 class="mt-1 text-lg font-bold">{{ $valuationLabel }}</h4>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Metode ini akan dipakai untuk pembentukan nilai stok awal dan perhitungan biaya berikutnya.</p>
                </div>
                <a href="/register/step-3" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">Edit</a>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-slate-50 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Produk Awal</p>
                    <h4 class="mt-1 text-lg font-bold">{{ $skipProduct ? 'Belum ditambahkan' : ($product['name'] ?? '-') }}</h4>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $skipProduct ? 'Anda bisa menambah produk pertama nanti dari dashboard.' : 'Produk ini akan dibuat otomatis saat Anda menekan tombol selesai.' }}</p>
                </div>
                <a href="/register/step-4" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">Edit</a>
            </div>

            @if ($skipProduct)
                <div class="mt-4 rounded-2xl bg-white px-3 py-3 text-sm leading-relaxed text-slate-600 dark:bg-slate-950/40 dark:text-slate-300">Anda memilih melewati input produk pertama. Produk bisa ditambahkan nanti dari dashboard setelah onboarding selesai.</div>
            @else
                <dl class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                    <div class="flex items-start justify-between gap-4 rounded-2xl bg-white px-3 py-2.5 dark:bg-slate-950/40">
                        <dt>SKU / Kode</dt>
                        <dd class="text-right font-medium text-slate-900 dark:text-slate-100">{{ $product['sku'] ?? '-' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 rounded-2xl bg-white px-3 py-2.5 dark:bg-slate-950/40">
                        <dt>Kategori</dt>
                        <dd class="text-right font-medium text-slate-900 dark:text-slate-100">{{ $product['category'] ?? '-' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 rounded-2xl bg-white px-3 py-2.5 dark:bg-slate-950/40">
                        <dt>Harga Pokok (HPP)</dt>
                        <dd class="text-right font-medium text-slate-900 dark:text-slate-100">{{ filled($product['hpp_average'] ?? null) ? 'Rp '.number_format((float) $product['hpp_average'], 0, ',', '.') : 'Belum diisi' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 rounded-2xl bg-white px-3 py-2.5 dark:bg-slate-950/40">
                        <dt>Harga Jual</dt>
                        <dd class="text-right font-medium text-slate-900 dark:text-slate-100">{{ filled($product['price'] ?? null) ? 'Rp '.number_format((float) $product['price'], 0, ',', '.') : 'Belum diisi' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 rounded-2xl bg-white px-3 py-2.5 dark:bg-slate-950/40">
                        <dt>Stok Awal</dt>
                        <dd class="text-right font-medium text-slate-900 dark:text-slate-100">{{ (float) ($product['initial_stock'] ?? 0) > 0 ? rtrim(rtrim(number_format((float) $product['initial_stock'], 2, '.', ''), '0'), '.') : '0' }}</dd>
                    </div>
                </dl>

                @if (! empty($product['variants'] ?? []))
                    <div class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-950/40">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Variant Produk</p>
                        <div class="mt-3 space-y-2">
                            @foreach ($product['variants'] as $index => $variant)
                                @php
                                    $stockLine = collect($product['initial_stock_lines'] ?? [])->firstWhere('product_variant_id', $variant['id'] ?? null);
                                @endphp
                                <div class="rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $variant['name'] ?? 'Variant '.($index + 1) }}</p>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $variant['sku'] ?? '-' }}</p>
                                        </div>
                                        <div class="text-right text-xs text-slate-500 dark:text-slate-400">
                                            <p>Qty: {{ $stockLine['qty'] ?? 0 }}</p>
                                            <p>Cost: {{ filled($stockLine['unit_cost'] ?? null) ? 'Rp '.number_format((float) $stockLine['unit_cost'], 0, ',', '.') : 'HPP default' }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </section>
    </div>

    <footer class="sticky bottom-0 border-t border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 app-bottom-safe-footer">
        <form method="POST" action="/register/complete" class="space-y-4">
            @csrf
            <section class="rounded-3xl border border-slate-200 bg-slate-50 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Pilih Paket</p>
                        <h4 class="mt-1 text-lg font-bold">Paket yang paling cocok untuk bisnis Anda</h4>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Pilih plan berdasarkan kebutuhan saat ini. Anda tetap bisa upgrade atau downgrade nanti dari menu langganan.</p>
                    </div>
                </div>

                @error('selected_plan')
                    <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700">
                        {{ $message }}
                    </div>
                @enderror

                <div class="mt-4 space-y-3">
                    @foreach ($planOptions as $plan)
                        @php
                            $isSelectedPlan = $selectedPlan === $plan['key'];
                            $accentClass = match ($plan['accent']) {
                                'primary' => $isSelectedPlan
                                    ? 'border-primary bg-primary/[0.08] ring-2 ring-primary/15'
                                    : 'border-slate-200 bg-white hover:border-primary/40 dark:border-slate-700 dark:bg-slate-950/40',
                                'amber' => $isSelectedPlan
                                    ? 'border-amber-400 bg-amber-50 ring-2 ring-amber-100 dark:border-amber-500 dark:bg-amber-950/20'
                                    : 'border-slate-200 bg-white hover:border-amber-300 dark:border-slate-700 dark:bg-slate-950/40',
                                default => $isSelectedPlan
                                    ? 'border-slate-500 bg-slate-100 ring-2 ring-slate-200 dark:border-slate-400 dark:bg-slate-800'
                                    : 'border-slate-200 bg-white hover:border-slate-400 dark:border-slate-700 dark:bg-slate-950/40',
                            };
                            $selectedAccentClass = match ($plan['accent']) {
                                'primary' => 'border-primary bg-primary/[0.08] ring-2 ring-primary/15',
                                'amber' => 'border-amber-400 bg-amber-50 ring-2 ring-amber-100 dark:border-amber-500 dark:bg-amber-950/20',
                                default => 'border-slate-500 bg-slate-100 ring-2 ring-slate-200 dark:border-slate-400 dark:bg-slate-800',
                            };
                            $unselectedAccentClass = match ($plan['accent']) {
                                'primary' => 'border-slate-200 bg-white hover:border-primary/40 dark:border-slate-700 dark:bg-slate-950/40',
                                'amber' => 'border-slate-200 bg-white hover:border-amber-300 dark:border-slate-700 dark:bg-slate-950/40',
                                default => 'border-slate-200 bg-white hover:border-slate-400 dark:border-slate-700 dark:bg-slate-950/40',
                            };
                        @endphp
                        <label
                            class="block cursor-pointer rounded-3xl border p-4 transition {{ $accentClass }}"
                            data-plan-option
                            data-selected-class="{{ $selectedAccentClass }}"
                            data-unselected-class="{{ $unselectedAccentClass }}"
                            aria-checked="{{ $isSelectedPlan ? 'true' : 'false' }}"
                        >
                            <input type="radio" name="selected_plan" value="{{ $plan['key'] }}" class="sr-only" data-plan-radio {{ $isSelectedPlan ? 'checked' : '' }} required>
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h5 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ $plan['key'] }} - {{ $plan['label'] }}</h5>
                                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $plan['recommended'] ? 'bg-primary text-white' : 'bg-slate-900/8 text-slate-600 dark:bg-white/10 dark:text-slate-300' }}">{{ $plan['badge'] }}</span>
                                    </div>
                                    <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">Cocok untuk: {{ $plan['fit_for'] }}</p>
                                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $plan['summary'] }}</p>
                                </div>
                                <span class="material-symbols-outlined {{ $isSelectedPlan ? 'text-primary' : 'text-slate-300 dark:text-slate-600' }}" data-plan-icon>{{ $isSelectedPlan ? 'check_circle' : 'radio_button_unchecked' }}</span>
                            </div>
                            <div class="mt-4 grid gap-2">
                                @foreach ($plan['highlights'] as $highlight)
                                    <div class="flex items-start gap-2 rounded-2xl bg-white/80 px-3 py-2 text-sm text-slate-700 dark:bg-slate-950/50 dark:text-slate-200">
                                        <span class="material-symbols-outlined text-base text-primary">done</span>
                                        <span>{{ $highlight }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </label>
                    @endforeach
                </div>
            </section>

            <div class="rounded-2xl bg-slate-50 px-3 py-3 text-xs leading-relaxed text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">Setelah konfirmasi, sistem akan membuat data awal yang diperlukan lalu mengarahkan Anda ke dashboard.</div>
            <button type="submit" class="w-full rounded-xl bg-primary py-4 text-base font-bold text-white shadow-lg shadow-primary/20">Konfirmasi dan Masuk Dashboard</button>
            <a href="/register/step-4" class="block w-full rounded-xl bg-white py-4 text-center text-base font-medium text-slate-500 dark:bg-slate-900 dark:text-slate-400">Kembali ke Form Produk</a>
        </form>
    </footer>

    <script>
        (() => {
            const planOptions = Array.from(document.querySelectorAll('[data-plan-option]'));

            if (planOptions.length === 0) {
                return;
            }

            const iconSelectedClasses = ['text-primary'];
            const iconUnselectedClasses = ['text-slate-300', 'dark:text-slate-600'];

            const syncPlanSelection = () => {
                for (const option of planOptions) {
                    const radio = option.querySelector('[data-plan-radio]');
                    const icon = option.querySelector('[data-plan-icon]');
                    const selectedClasses = (option.dataset.selectedClass ?? '').split(' ').filter(Boolean);
                    const unselectedClasses = (option.dataset.unselectedClass ?? '').split(' ').filter(Boolean);
                    const isChecked = Boolean(radio?.checked);

                    option.classList.remove(...selectedClasses, ...unselectedClasses);
                    option.classList.add(...(isChecked ? selectedClasses : unselectedClasses));
                    option.setAttribute('aria-checked', isChecked ? 'true' : 'false');

                    if (!icon) {
                        continue;
                    }

                    icon.textContent = isChecked ? 'check_circle' : 'radio_button_unchecked';
                    icon.classList.remove(...iconSelectedClasses, ...iconUnselectedClasses);
                    icon.classList.add(...(isChecked ? iconSelectedClasses : iconUnselectedClasses));
                }
            };

            for (const option of planOptions) {
                const radio = option.querySelector('[data-plan-radio]');

                option.addEventListener('click', () => {
                    if (radio) {
                        radio.checked = true;
                    }

                    syncPlanSelection();
                });

                radio?.addEventListener('change', syncPlanSelection);
            }

            syncPlanSelection();
        })();
    </script>
@endsection
