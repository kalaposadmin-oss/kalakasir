@extends('layouts.app')

@php
    $kasirTenant = \App\Support\TenantContext::get();
    $kasirUser = auth()->user();

    if (! $kasirTenant && filled($kasirUser?->tenant_id)) {
        $kasirTenant = \App\Models\Tenant::query()->find($kasirUser->tenant_id);
    }

    $canUsePosCustomerFeature = $kasirTenant
        ? app(\App\Services\SubscriptionService::class)->canAccessFeature($kasirTenant, 'pos.customer')
        : false;
@endphp

@section('page_title', 'Kasir POS - Checkout Utama')
@section('body_class', 'font-display text-slate-900')
@section('app_shell_class', 'kasir-device-shell relative mx-auto flex h-screen w-full flex-col overflow-hidden bg-slate-50 shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .kasir-device-shell {
            max-width: 1024px;
        }
        @media (min-width: 768px) {
            .kasir-device-shell {
                border-left: 1px solid #e2e8f0;
                border-right: 1px solid #e2e8f0;
            }
        }
        #cart-list > .rounded-2xl > div:first-child {
            align-items: start;
            display: grid;
            gap: .75rem;
            grid-template-columns: auto minmax(0, 1fr);
        }
        #cart-list > .rounded-2xl > div:first-child > div:nth-child(3) {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            grid-column: 1 / -1;
            justify-content: space-between;
            min-width: 0;
        }
        #cart-list .item-discount-toggle {
            min-height: 2.25rem;
            white-space: normal;
        }
        #cart-list .qty-input {
            width: 4rem;
        }
        #product-grid[data-view="grid"] {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        #product-grid[data-view="list"] {
            grid-template-columns: minmax(0, 1fr);
        }
        @media (min-width: 768px) and (max-width: 1024px) {
            #product-grid[data-view="grid"] {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            #product-grid[data-view="list"] {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            #bottom-nav {
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }
            #bottom-nav > div {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 15rem;
                align-items: center;
                gap: 1rem;
            }
            #bottom-nav > div > button {
                margin-top: 0;
                width: 100%;
            }
            #cart-drawer {
                left: auto;
                right: 0;
                width: min(28rem, 52vw);
                border-top-left-radius: 28px;
                border-top-right-radius: 0;
            }
            #customer-modal > div,
            #customer-sheet > div:last-child,
            #saved-order-sheet > div:last-child,
            #receivable-sheet > div:last-child {
                left: 50%;
                right: auto;
                width: min(32rem, calc(100vw - 3rem));
                transform: translateX(-50%);
                border-top-left-radius: 28px;
                border-top-right-radius: 28px;
            }
        }
        @media (max-width: 390px) {
            #product-grid[data-view="grid"] {
                grid-template-columns: minmax(0, 1fr);
            }
            #bottom-nav {
                padding-left: .75rem;
                padding-right: .75rem;
            }
            #bottom-nav > div {
                padding: .75rem;
            }
        }
    </style>
@endpush

@section('header')
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white px-4 pt-4 pb-3" data-auto-center-title="true">
        <div class="flex items-center gap-2.5">
            <a class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600" href="/menu" aria-label="Buka semua menu">
                <span class="material-symbols-outlined text-xl">apps</span>
            </a>
            <div class="relative min-w-0 flex-1">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-slate-400">search</span>
                <input id="search-input" class="w-full rounded-full border-none bg-slate-100 py-2.5 pl-10 pr-4 text-sm focus:ring-2 focus:ring-primary" placeholder="Cari produk atau scan..." type="text" />
            </div>
            <button class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary" type="button" aria-label="Scanner barcode">
                <span class="material-symbols-outlined text-lg">barcode_scanner</span>
            </button>
        </div>
        <div class="mt-3 flex items-center justify-between gap-2.5">
            <div>
                <p id="shift-status" class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">Shift Tutup</p>
            </div>
            <a id="quick-close-shift" href="/pos/shifts/close" class="inline-flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2 text-[11px] font-bold text-slate-700 hidden" title="Tutup Shift">
                <span class="material-symbols-outlined text-base">lock_clock</span>
                Endshift
            </a>
        </div>
    </header>
@endsection

@section('content')
    <div class="relative flex h-full flex-col bg-slate-50">
        <div class="min-h-0 flex-1 overflow-y-auto no-scrollbar">
            <section class="space-y-3 bg-white px-4 py-3">
                <div id="shift-guard" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p class="text-xs font-semibold text-amber-800">Shift belum aktif. Buka shift dulu untuk mulai transaksi.</p>
                    <a href="/pos/shifts/open" class="mt-2 inline-flex items-center gap-1 rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-800">
                        <span class="material-symbols-outlined text-sm">lock_open</span>
                        Buka Shift
                    </a>
                </div>

                <div id="editing-sale-banner" class="hidden rounded-2xl border border-primary/20 bg-primary/5 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-primary">Mode Edit Transaksi</p>
                    <p class="mt-1 text-xs text-slate-700">
                        Mengubah transaksi <span id="editing-sale-invoice" class="font-bold">-</span>.
                        Data awal sudah dimuat dan bisa diedit sebelum disimpan ulang.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button id="customer-quick-trigger" title="Pilih pelanggan" class="cursor-pointer inline-flex flex-1 items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-left shadow-sm" type="button">
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <span class="material-symbols-outlined text-[18px]">person</span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span id="customer-current-label" class="block truncate text-[11px] font-bold text-slate-800">Umum</span>
                        </span>
                    </button>
                    <div id="editing-sale-badge" class="hidden min-w-0 flex-1 rounded-xl border border-primary/20 bg-primary/5 px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-primary">Edit Transaksi</p>
                        <p id="editing-sale-badge-text" class="truncate text-[11px] font-semibold text-slate-700">Data awal dimuat</p>
                    </div>
                    <button id="saved-order-quick-trigger" title="Pesanan tersimpan" class="cursor-pointer inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 py-2 shadow-sm" type="button">
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                            <span class="material-symbols-outlined text-[18px]">ink_pen</span>
                        </span>
                        <span id="saved-order-count-label" class="text-[11px] font-bold text-slate-800">0</span>
                    </button>
                    <button id="receivable-quick-trigger" title="Piutang" class="cursor-pointer inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 py-2 shadow-sm" type="button">
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                            <span class="material-symbols-outlined text-[18px]">request_quote</span>
                        </span>
                        <span id="receivable-quick-label" class="text-[11px] font-bold text-slate-800">Rp 0</span>
                    </button>
                </div>
                <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700">
                        <span class="material-symbols-outlined text-[18px]">event</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Tanggal Transaksi</span>
                        <input id="transaction-date-input" class="mt-0.5 w-full border-0 bg-transparent p-0 text-xs font-bold text-slate-800 focus:ring-0" type="date" value="{{ now($kasirTenant?->timezone ?? config('app.timezone'))->toDateString() }}" max="{{ now($kasirTenant?->timezone ?? config('app.timezone'))->toDateString() }}" />
                    </span>
                </label>
            </section>

            <section class="space-y-4 px-4 py-4">
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="shrink-0 text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400">Katalog Produk</p>
                        <div class="flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1">
                            <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-500" id="shift-indicator"></span>
                            <p class="text-[10px] font-bold text-emerald-700" id="cashier-name">Kasir</p>
                            <span class="text-[10px] text-emerald-300">|</span>
                            <p class="text-[10px] text-emerald-600" id="shift-opened-time">-</p>
                        </div>
                        <div class="inline-flex shrink-0 rounded-2xl bg-slate-100 p-0.5 shadow-inner">
                            <button id="product-view-list" class="inline-flex items-center gap-1 rounded-xl px-2.5 py-1.5 text-[11px] font-bold text-slate-700" type="button">
                                <span class="material-symbols-outlined text-sm">format_list_bulleted</span>
                                Teks
                            </button>
                            <button id="product-view-grid" class="inline-flex items-center gap-1 rounded-xl px-2.5 py-1.5 text-[11px] font-bold text-slate-500" type="button">
                                <span class="material-symbols-outlined text-sm">grid_view</span>
                                Kartu
                            </button>
                        </div>
                    </div>

                    <div id="category-filters" class="flex gap-2 overflow-x-auto pb-1 no-scrollbar"></div>
                </div>

                <div id="product-grid" class="grid gap-3"></div>
            </section>
        </div>

        <div id="bottom-nav" class="hidden shrink-0 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <button id="cart-toggle" class="flex min-w-0 flex-1 items-center gap-3 rounded-2xl bg-white px-3 py-3 text-left shadow-sm" type="button">
                        <div class="relative flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <span class="material-symbols-outlined">shopping_bag</span>
                            <span id="cart-badge" class="absolute -right-1 -top-1 inline-flex min-h-[20px] min-w-[20px] items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-bold text-white">0</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-slate-900"><span id="summary-item-count">0</span> item di keranjang</p>
                            <p id="summary-caption" class="text-[11px] text-slate-500">Tambahkan produk ke keranjang.</p>
                        </div>
                    </button>
                    <div class="text-right">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Total</p>
                        <p id="summary-total" class="text-lg font-black text-slate-900">Rp0</p>
                    </div>
                </div>
                <button id="checkout-button-compact" class="mt-3 inline-flex w-full items-center justify-center gap-1 rounded-2xl bg-slate-200 px-4 py-3 text-sm font-bold text-slate-400" type="button" disabled>
                    Belum Siap
                    <span class="material-symbols-outlined text-lg">chevron_right</span>
                </button>
            </div>
        </div>

        <div id="cart-overlay" class="absolute inset-0 hidden bg-slate-950/30 backdrop-blur-[2px]">
            <div id="cart-drawer" class="absolute inset-x-0 bottom-0 flex h-full max-h-full flex-col overflow-hidden rounded-t-[28px] bg-white shadow-2xl">
                <div class="border-b border-slate-200 px-4 pt-3 pb-4">
                    <div class="mx-auto mb-3 h-1.5 w-14 rounded-full bg-slate-200"></div>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400">Keranjang</p>
                            <h3 class="text-lg font-bold text-slate-900">Ringkasan Checkout</h3>
                            <p class="text-xs text-slate-500"><span id="cart-count-label">0</span> item siap dibayar</p>
                        </div>
                        <div class="flex gap-2">
                            <button id="clear-cart" class="inline-flex items-center gap-1 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-600" type="button">
                                <span class="material-symbols-outlined text-sm">delete_sweep</span>
                                Kosongkan
                            </button>
                            <button id="cart-close" class="rounded-xl bg-slate-100 p-2 text-slate-500" type="button" aria-label="Tutup keranjang">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="min-h-0 flex-1 overflow-y-auto px-4 pt-4 pb-32 no-scrollbar">
                        <div id="cart-list" class="space-y-3"></div>

                        <details class="mt-4 rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-bold text-slate-900">Tambahan Pesanan</p>
                                    <p class="text-xs text-slate-500">Diskon, biaya tambahan, dan simpan pesanan.</p>
                                </div>
                                <span class="material-symbols-outlined text-slate-500">expand_more</span>
                            </summary>
                            <div class="mt-4 space-y-4">
                                <div id="saved-order-panel" class="rounded-3xl border border-slate-200 bg-white p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-bold text-slate-900">Diskon & Promo</p>
                                            <p class="text-xs text-slate-500">Atur promo transaksi dan diskon manual.</p>
                                        </div>
                                        <button id="discount-toggle" class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm" type="button">
                                            <span class="material-symbols-outlined text-sm">sell</span>
                                            <span id="discount-toggle-label">Diskon</span>
                                        </button>
                                    </div>
                                    <div id="discount-panel" class="mt-3 hidden space-y-4 rounded-2xl bg-slate-50 p-3 shadow-sm">
                                        <div>
                                            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Promo Aktif</p>
                                            <div id="discount-list" class="mt-2 space-y-2"></div>
                                            <p id="discount-empty" class="mt-2 rounded-xl border border-dashed border-slate-200 bg-white px-3 py-3 text-xs text-slate-500">Belum ada promo aktif untuk transaksi ini.</p>
                                        </div>
                                        <div>
                                            <div class="flex items-center justify-between gap-3">
                                                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400">Diskon Manual</p>
                                                <button id="transaction-discount-add" class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1.5 text-[11px] font-bold text-primary" type="button">
                                                    <span class="material-symbols-outlined text-sm">add</span>
                                                    Tambah
                                                </button>
                                            </div>
                                            <div id="transaction-discount-list" class="mt-2 space-y-2"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-bold text-slate-900">Biaya Tambahan</p>
                                            <p class="text-xs text-slate-500">Contoh: plastik, ongkir, atau jasa.</p>
                                        </div>
                                        <button id="fee-add" class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm" type="button">
                                            <span class="material-symbols-outlined text-sm">add_circle</span>
                                            <span id="fee-add-label">Biaya</span>
                                        </button>
                                    </div>
                                    <div id="fee-list" class="mt-3 space-y-2"></div>
                                </div>

                                <div class="rounded-3xl border border-slate-200 bg-white p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-bold text-slate-900">Simpan Pesanan</p>
                                            <p class="text-xs text-slate-500">Simpan pesanan atas nama pelanggan dan catatan.</p>
                                        </div>
                                        <button id="save-order" class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm" type="button">
                                            <span class="material-symbols-outlined text-sm">save</span>
                                            <span id="save-order-label">Simpan</span>
                                        </button>
                                    </div>
                                    <p id="save-order-mode-badge" class="mt-2 hidden rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-800">Mode edit aktif: perubahan akan memperbarui pesanan tersimpan yang sedang dibuka.</p>
                                    <div class="mt-3 space-y-2">
                                        <input id="order-holder-name" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-primary focus:ring-primary" placeholder="Atas nama pelanggan" type="text" maxlength="120" />
                                        <textarea id="order-note" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-primary focus:ring-primary" placeholder="Catatan pesanan (opsional)" rows="2" maxlength="500"></textarea>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>

                    <div class="shrink-0 border-t border-slate-200 bg-white px-4 py-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] space-y-2">
                        <details class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400">Ringkasan Bayar</p>
                                    <p class="mt-0.5 text-sm font-semibold text-slate-600"><span id="item-count" class="font-black text-slate-900">0</span> item</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Total</p>
                                    <span id="total" class="text-lg font-black text-slate-900">Rp0</span>
                                </div>
                            </summary>
                            <div class="mt-3 space-y-1.5 border-t border-dashed border-slate-200 pt-3 text-xs">
                                <div class="flex items-center justify-between text-slate-600">
                                    <span>Subtotal</span>
                                    <span id="subtotal" class="font-bold text-slate-900">Rp0</span>
                                </div>
                                <div class="flex items-center justify-between text-emerald-600">
                                    <span>Diskon item</span>
                                    <span id="item-discount-amount" class="font-bold">Rp0</span>
                                </div>
                                <div class="flex items-center justify-between text-emerald-600">
                                    <span>Diskon transaksi</span>
                                    <span id="transaction-discount-amount" class="font-bold">Rp0</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-600">
                                    <span>Total diskon</span>
                                    <span id="discount-amount" class="font-bold text-slate-900">Rp0</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-600">
                                    <span>Total biaya tambahan</span>
                                    <span id="fee-total" class="font-bold text-slate-900">Rp0</span>
                                </div>
                                <div class="flex items-center justify-between text-slate-600">
                                    <span id="tax-label">Pajak (0%)</span>
                                    <span id="tax" class="font-bold text-slate-900">Rp0</span>
                                </div>
                            </div>
                        </details>

                        <button id="checkout-button" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-2.5 text-sm font-bold text-white" type="button" disabled>
                            <span class="material-symbols-outlined text-lg">payments</span>
                            Lanjut ke Pembayaran
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="customer-modal" class="absolute inset-0 z-40 hidden bg-slate-950/30 backdrop-blur-[2px]">
            <div class="absolute inset-x-0 bottom-0 rounded-t-[28px] bg-white shadow-2xl">
                <div class="border-b border-slate-200 px-4 pt-3 pb-4">
                    <div class="mx-auto mb-3 h-1.5 w-14 rounded-full bg-slate-200"></div>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400">Pelanggan Baru</p>
                            <h3 class="text-lg font-bold text-slate-900">Tambah Pelanggan</h3>
                            <p class="text-xs text-slate-500">Simpan nama, nomor HP, dan alamat agar mudah dipilih saat checkout.</p>
                        </div>
                        <button id="customer-modal-close" class="rounded-xl bg-slate-100 p-2 text-slate-500" type="button" aria-label="Tutup form pelanggan">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                </div>
                <div class="space-y-3 px-4 py-4">
                    <label class="block space-y-1">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Nama</span>
                        <input id="customer-name-input" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary" type="text" maxlength="120" placeholder="Nama pelanggan" />
                    </label>
                    <label class="block space-y-1">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Nomor HP</span>
                        <input id="customer-phone-input" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary" type="tel" maxlength="30" placeholder="08xxxxxxxxxx" />
                    </label>
                    <label class="block space-y-1">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Alamat</span>
                        <textarea id="customer-address-input" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary" rows="3" maxlength="500" placeholder="Alamat pelanggan"></textarea>
                    </label>
                    <p id="customer-modal-feedback" class="hidden rounded-xl border px-3 py-2 text-xs font-medium"></p>
                </div>
                <div class="border-t border-slate-200 bg-white px-4 py-4">
                    <div class="flex gap-3">
                        <button id="customer-modal-cancel" class="inline-flex flex-1 items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600" type="button">
                            Batal
                        </button>
                        <button id="customer-save-button" class="inline-flex flex-1 items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-white" type="button">
                            <span class="material-symbols-outlined text-lg">save</span>
                            Simpan Pelanggan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="customer-sheet" class="absolute inset-0 z-30 hidden bg-slate-950/30 backdrop-blur-[2px]">
            <div id="customer-sheet-overlay" class="absolute inset-0"></div>
            <div class="absolute inset-x-0 bottom-0 rounded-t-[28px] bg-white shadow-2xl">
                <div class="border-b border-slate-200 px-4 pt-3 pb-4">
                    <div class="mx-auto mb-3 h-1.5 w-14 rounded-full bg-slate-200"></div>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400">Pelanggan</p>
                            <h3 class="text-lg font-bold text-slate-900">Pilih Pelanggan</h3>
                            <p id="customer-feature-notice" class="text-xs {{ $canUsePosCustomerFeature ? 'text-slate-500' : 'font-semibold text-amber-700' }}">
                                {{ $canUsePosCustomerFeature ? 'Pilih pelanggan untuk loyalty dan histori transaksi.' : 'Upgrade ke paket Medium atau Pro untuk memakai pelanggan tersimpan di POS.' }}
                            </p>
                        </div>
                        <button id="customer-sheet-close" class="rounded-xl bg-slate-100 p-2 text-slate-500" type="button" aria-label="Tutup pelanggan">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                </div>
                <div class="space-y-3 px-4 py-4">
                    <select id="customer-select" data-feature-enabled="{{ $canUsePosCustomerFeature ? 'true' : 'false' }}" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400" {{ $canUsePosCustomerFeature ? '' : 'disabled' }}>
                        <option value="">Pelanggan Umum</option>
                    </select>
                    <button id="customer-create-trigger" class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-2 text-xs font-bold text-primary shadow-sm disabled:cursor-not-allowed disabled:text-slate-400" type="button" {{ $canUsePosCustomerFeature ? '' : 'disabled' }}>
                        <span class="material-symbols-outlined text-sm">person_add</span>
                        Tambah Pelanggan
                    </button>
                </div>
            </div>
        </div>

        <div id="saved-order-sheet" class="absolute inset-0 z-30 hidden bg-slate-950/30 backdrop-blur-[2px]">
            <div id="saved-order-sheet-overlay" class="absolute inset-0"></div>
            <div class="absolute inset-x-0 bottom-0 max-h-[82%] overflow-hidden rounded-t-[28px] bg-white shadow-2xl">
                <div class="border-b border-slate-200 px-4 pt-3 pb-4">
                    <div class="mx-auto mb-3 h-1.5 w-14 rounded-full bg-slate-200"></div>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400">Pesanan Tersimpan</p>
                            <h3 class="text-lg font-bold text-slate-900">Muat Pesanan Lama</h3>
                            <p class="text-xs text-slate-500"><span id="saved-order-sheet-count">0</span> pesanan bisa dipanggil lagi dari sini.</p>
                        </div>
                        <button id="saved-order-sheet-close" class="rounded-xl bg-slate-100 p-2 text-slate-500" type="button" aria-label="Tutup pesanan tersimpan">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                </div>
                <div class="max-h-[60vh] overflow-y-auto px-4 py-4 no-scrollbar">
                    <div id="saved-order-list" class="space-y-2"></div>
                    <p id="saved-order-empty" class="rounded-xl border border-dashed border-slate-200 bg-white px-3 py-3 text-xs text-slate-500">Belum ada pesanan tersimpan.</p>
                </div>
            </div>
        </div>

        {{-- Piutang Bottom Sheet --}}
        <div id="receivable-sheet" class="absolute inset-0 z-30 hidden bg-slate-950/30 backdrop-blur-[2px]">
            <div id="receivable-sheet-overlay" class="absolute inset-0"></div>
            <div class="absolute inset-x-0 bottom-0 max-h-[85%] overflow-hidden rounded-t-[28px] bg-white shadow-2xl">
                <div class="border-b border-slate-200 px-4 pt-3 pb-4">
                    <div class="mx-auto mb-3 h-1.5 w-14 rounded-full bg-slate-200"></div>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400">Piutang</p>
                            <h3 class="text-lg font-bold text-slate-900">Riwayat Piutang</h3>
                            <p class="text-xs text-slate-500">Ringkasan piutang aktif per pelanggan.</p>
                        </div>
                        <button id="receivable-sheet-close" class="rounded-xl bg-slate-100 p-2 text-slate-500" type="button" aria-label="Tutup piutang">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                </div>

                {{-- Summary Cards --}}
                <div class="px-4 pt-4 pb-2">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-2xl border border-rose-100 bg-rose-50 p-3 dark:bg-rose-950/20 dark:border-rose-900/30">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-rose-400">Total Aktif</p>
                            <p id="recv-active-total" class="text-base font-black text-rose-600 tracking-tight mt-0.5">Rp 0</p>
                            <p id="recv-active-count" class="text-[10px] text-rose-400 mt-0.5">0 piutang</p>
                        </div>
                        <div class="rounded-2xl border border-amber-100 bg-amber-50 p-3 dark:bg-amber-950/20 dark:border-amber-900/30">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-500">Jatuh Tempo</p>
                            <p id="recv-overdue-total" class="text-base font-black text-amber-600 tracking-tight mt-0.5">Rp 0</p>
                            <p id="recv-overdue-label" class="text-[10px] text-amber-400 mt-0.5">belum ada</p>
                        </div>
                    </div>
                </div>

                {{-- Per Customer List --}}
                <div class="max-h-[50vh] overflow-y-auto px-4 py-3 no-scrollbar">
                    <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400 mb-2">Per Pelanggan</p>
                    <div id="recv-customer-list" class="space-y-2">
                        <p class="text-xs text-slate-400">Memuat data piutang...</p>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="border-t border-slate-200 px-4 py-3">
                    <a href="/pos/receivables" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-primary/10 px-4 py-3 text-sm font-bold text-primary active:opacity-80">
                        <span class="material-symbols-outlined text-lg">open_in_new</span>
                        Lihat Semua Piutang
                    </a>
                </div>
            </div>
        </div>
    </div>

    @php($hasKasirViteAsset = file_exists(public_path('build/manifest.json')) || file_exists(storage_path('vite.hot')))
    @if($hasKasirViteAsset)
        @vite('resources/js/pages/pos-kasir-page.js')
    @else
        <script defer src="{{ asset('js/pos-kasir-page-core.js') }}?v={{ filemtime(public_path('js/pos-kasir-page-core.js')) }}"></script>
    @endif

    @push('scripts')
    <script>
    (() => {
        const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
        const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

        const toCurrency = (v) => new Intl.NumberFormat('id-ID', {
            style: 'currency', currency: 'IDR', maximumFractionDigits: 0
        }).format(Number(v || 0));

        const sheet = document.getElementById('receivable-sheet');
        const trigger = document.getElementById('receivable-quick-trigger');
        const closeBtn = document.getElementById('receivable-sheet-close');
        const overlay = document.getElementById('receivable-sheet-overlay');
        const quickLabel = document.getElementById('receivable-quick-label');

        if (!sheet || !trigger) return;

        let loaded = false;

        const openSheet = () => {
            sheet.classList.remove('hidden');
            if (!loaded) loadReceivables();
        };
        const closeSheet = () => sheet.classList.add('hidden');

        trigger.addEventListener('click', openSheet);
        closeBtn?.addEventListener('click', closeSheet);
        overlay?.addEventListener('click', closeSheet);

        const loadReceivables = async () => {
            loaded = true;
            try {
                const fetchFn = window.PosUi?.fetchWithTimeout || fetch;
                const response = await fetchFn('/api/v1/receivables/summary', { headers }, 8000);
                if (!response.ok) throw new Error('API error');
                const json = await response.json();
                const data = window.PosUi?.unwrapApiPayload?.(json) ?? json?.data ?? json;
                renderSummary(data);
            } catch (_) {
                document.getElementById('recv-customer-list').innerHTML =
                    '<p class="text-xs text-slate-400">Gagal memuat data piutang.</p>';
            }
        };

        const renderSummary = (data) => {
            const activeTotal = Number(data?.active_total || 0);
            const overdueTotal = Number(data?.overdue_total || 0);
            const activeCount = Number(data?.active_count || 0);

            document.getElementById('recv-active-total').textContent = toCurrency(activeTotal);
            document.getElementById('recv-active-count').textContent = `${activeCount} piutang`;
            document.getElementById('recv-overdue-total').textContent = toCurrency(overdueTotal);
            document.getElementById('recv-overdue-label').textContent = overdueTotal > 0 ? 'perlu segera dilunasi' : 'belum ada';

            // Update quick-trigger label
            quickLabel.textContent = activeTotal > 0 ? toCurrency(activeTotal) : 'Rp 0';
            if (activeTotal > 0) {
                quickLabel.classList.add('text-rose-600');
                quickLabel.classList.remove('text-slate-900');
            }

            const customers = (Array.isArray(data?.per_customer) ? data.per_customer : [])
                .filter(c => Number(c?.active_remaining || 0) > 0);
            const list = document.getElementById('recv-customer-list');

            if (!customers.length) {
                list.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 bg-white px-3 py-4 text-center">' +
                    '<span class="material-symbols-outlined text-2xl text-slate-300">sentiment_satisfied</span>' +
                    '<p class="text-xs text-slate-400 mt-1">Tidak ada piutang aktif.</p></div>';
                return;
            }

            list.innerHTML = customers.map(c => {
                const remaining = Number(c.active_remaining || 0);
                const count = Number(c.active_count || 0);
                const isUrgent = remaining > 0;
                return `
                    <a href="/pos/receivables?search=${encodeURIComponent(c.customer_name || '')}"
                       class="flex items-center gap-3 rounded-2xl border ${isUrgent ? 'border-rose-100 bg-rose-50/50' : 'border-slate-100 bg-white'} px-3 py-3 active:opacity-80 transition-opacity">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl ${isUrgent ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-500'}">
                            <span class="material-symbols-outlined text-lg">person</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-slate-900">${c.customer_name || 'Tanpa Nama'}</p>
                            <p class="text-[11px] text-slate-400">${count} piutang aktif</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-black ${isUrgent ? 'text-rose-600' : 'text-slate-700'}">${toCurrency(remaining)}</p>
                            <p class="text-[10px] text-slate-400">sisa</p>
                        </div>
                        <span class="material-symbols-outlined text-slate-300 text-lg shrink-0">chevron_right</span>
                    </a>`;
            }).join('');
        };

        // Pre-fetch summary to populate the quick-trigger label
        const prefetch = async () => {
            try {
                const fetchFn = window.PosUi?.fetchWithTimeout || fetch;
                const response = await fetchFn('/api/v1/receivables/summary', { headers }, 6000);
                if (!response.ok) return;
                const json = await response.json();
                const data = window.PosUi?.unwrapApiPayload?.(json) ?? json?.data ?? json;
                const activeTotal = Number(data?.active_total || 0);
                quickLabel.textContent = activeTotal > 0 ? toCurrency(activeTotal) : 'Rp 0';
                if (activeTotal > 0) {
                    quickLabel.classList.add('text-rose-600');
                    quickLabel.classList.remove('text-slate-900');
                }
            } catch (_) {}
        };

        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => prefetch(), { timeout: 3000 });
        } else {
            setTimeout(prefetch, 1500);
        }
    })();
    </script>
    @endpush
@endsection
