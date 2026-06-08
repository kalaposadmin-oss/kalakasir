@extends('layouts.app')

@section('page_title', 'Pilih Metode &amp; Split Bayar')
@section('body_class', 'font-display text-slate-900')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
        <style>
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
            .numpad-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem 0;
                font-size: 1.25rem;
                font-weight: 700;
                background-color: #ffffff;
                border: 1px solid #f1f5f9;
                border-radius: 0.75rem;
                transition: background-color 0.2s ease;
            }
            .numpad-btn:active {
                background-color: #f1f5f9;
            }
        </style>
@endpush

@php
    $paymentBackUrl = $backUrl ?? '/pos/checkout';
    $paymentCurrentPath = $currentPath ?? '/pos/payment-method';
    $paymentReceiptFallbackUrl = $receiptFallbackUrl ?? '/pos/receipt-preview';
@endphp

@section('header')
        <x-page-header title="Pilih Pembayaran" :backUrl="$paymentBackUrl" :showMenuButton="true">
            <x-slot name="actions">
                <button class="px-3 py-1.5 rounded-lg bg-primary/10 text-primary text-xs font-bold">SPLIT BILL</button>
            </x-slot>
        </x-page-header>
@endsection

@section('content')
        <div class="flex h-full flex-col">
            <div class="flex-1 flex flex-col overflow-y-auto no-scrollbar pb-24">
                <div class="px-6 py-8 bg-slate-50 border-b border-slate-100 text-center">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Total Tagihan</p>
                    <h2 id="total-amount" class="text-4xl font-black text-slate-900">Rp 209.790</h2>
                    <div class="mt-4 flex items-center justify-center gap-2">
                        <span id="payment-status" class="px-3 py-1 rounded-full bg-orange-100 text-orange-600 text-[10px] font-bold">BELUM LUNAS</span>
                        <p class="text-xs text-slate-500">Sisa: <span id="remaining-amount" class="font-bold">Rp 209.790</span></p>
                    </div>
                    <p id="payment-hint" class="mt-3 text-xs font-medium text-slate-500">Pilih Cash, QRIS, Bank, atau Piutang / Bayar Nanti.</p>
                    <button id="edit-items-button" class="hidden mt-3 inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600" type="button">
                        <span class="material-symbols-outlined text-sm">edit</span>
                        Ubah Item
                    </button>
                    <div id="payment-discount-summary" class="hidden mt-4 rounded-2xl border border-slate-200 bg-white/80 p-3 text-left">
                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span>Diskon Item</span>
                            <span id="payment-item-discount" class="font-semibold text-slate-700">Rp 0</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-xs text-slate-500">
                            <span>Diskon Transaksi</span>
                            <span id="payment-transaction-discount" class="font-semibold text-slate-700">Rp 0</span>
                        </div>
                    </div>
                    <div id="payment-snapshot-guard" class="hidden mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-left">
                        <p class="text-sm font-semibold text-amber-800">Data pembayaran tidak ditemukan.</p>
                        <p class="mt-1 text-xs text-amber-700">Kembali ke checkout lalu pilih bayar lagi.</p>
                        <a href="{{ $paymentBackUrl }}" class="mt-3 inline-flex items-center gap-2 rounded-xl bg-amber-100 px-3 py-2 text-xs font-bold text-amber-800">
                            <span class="material-symbols-outlined text-sm">arrow_back</span>
                            Kembali ke Checkout
                        </a>
                    </div>
                </div>
                <div class="p-4 space-y-3">
                    <p class="text-xs font-bold text-slate-400 uppercase ml-1">Metode Pembayaran</p>
                    <div class="border-2 border-slate-200 rounded-2xl overflow-hidden" data-payment-method="cash">
                        <div class="flex items-center justify-between p-4 bg-white">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-green-100 text-green-600 flex items-center justify-center">
                                    <span class="material-symbols-outlined">payments</span>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800">Tunai / Cash</p>
                                    <p class="text-xs text-slate-500">Bayar dengan uang fisik</p>
                                </div>
                            </div>
                            <span class="material-symbols-outlined text-primary hidden" data-method-active="cash">check_circle</span>
                            <span class="material-symbols-outlined text-slate-300" data-method-inactive="cash">chevron_right</span>
                        </div>
                        <div id="cash-input-panel" class="bg-slate-50 p-4 border-t border-slate-100 transition-all duration-300 overflow-hidden">
                            <div class="bg-white border border-slate-200 rounded-xl p-3 mb-4">
                                <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Jumlah Bayar</p>
                                <div class="flex justify-between items-center">
                                    <span id="pay-amount" class="text-lg font-black text-slate-900">Rp 210.000</span>
                                    <button id="reset-pay" class="text-primary text-xs font-bold" type="button">RESET</button>
                                </div>
                            </div>
                            <div class="flex justify-between items-center mb-4 px-1">
                                <p class="text-sm font-medium text-slate-600">Kembalian:</p>
                                <p id="change-amount" class="text-lg font-bold text-emerald-600">Rp 210</p>
                            </div>
                            <p id="payment-error" class="text-xs text-red-500 font-semibold hidden"></p>
                            <div class="grid grid-cols-3 gap-2">
                                <button class="numpad-btn">1</button>
                                <button class="numpad-btn">2</button>
                                <button class="numpad-btn">3</button>
                                <button class="numpad-btn">4</button>
                                <button class="numpad-btn">5</button>
                                <button class="numpad-btn">6</button>
                                <button class="numpad-btn">7</button>
                                <button class="numpad-btn">8</button>
                                <button class="numpad-btn">9</button>
                                <button class="numpad-btn">000</button>
                                <button class="numpad-btn">0</button>
                                <button class="numpad-btn bg-slate-50 text-slate-400">
                                    <span class="material-symbols-outlined">backspace</span>
                                </button>
                            </div>
                            <div id="quick-amount-list" class="flex gap-2 mt-4 overflow-x-auto no-scrollbar pb-1"></div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-white border-2 border-slate-200 rounded-2xl active:bg-slate-50" data-payment-method="qris">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                                <span class="material-symbols-outlined text-2xl">qr_code_2</span>
                            </div>
                            <div>
                                <p class="font-bold text-slate-800">QRIS / E-Wallet</p>
                                <p class="text-xs text-slate-500">Gopay, OVO, Dana, ShopeePay</p>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-primary hidden" data-method-active="qris">check_circle</span>
                        <span class="material-symbols-outlined text-slate-300" data-method-inactive="qris">chevron_right</span>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-white border-2 border-slate-200 rounded-2xl active:bg-slate-50" data-payment-method="bank">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center">
                                <span class="material-symbols-outlined">credit_card</span>
                            </div>
                            <div>
                                <p class="font-bold text-slate-800">Bank Transfer / EDC</p>
                                <p class="text-xs text-slate-500">BCA, Mandiri, BRI, BNI</p>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-primary hidden" data-method-active="bank">check_circle</span>
                        <span class="material-symbols-outlined text-slate-300" data-method-inactive="bank">chevron_right</span>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-white border-2 border-slate-200 rounded-2xl active:bg-slate-50" data-payment-method="receivable">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center">
                                <span class="material-symbols-outlined">event_note</span>
                            </div>
                            <div>
                                <p class="font-bold text-slate-800">Piutang / Bayar Nanti</p>
                                <p class="text-xs text-slate-500">Jatuh tempo, pelanggan, catatan, dan DP</p>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-primary hidden" data-method-active="receivable">check_circle</span>
                        <span class="material-symbols-outlined text-slate-300" data-method-inactive="receivable">chevron_right</span>
                    </div>
                    <div id="bank-account-picker" class="hidden mt-3 p-4 bg-white border border-slate-200 rounded-2xl">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-bold text-slate-400 uppercase">Pilih Akun Bank</p>
                            <a id="bank-manage-link" class="text-xs font-bold text-primary" href="/portal/switch/backoffice?next=%2Fbackoffice%2Fsettings%2Fchart-of-accounts">Kelola</a>
                        </div>
                        <div id="bank-account-list" class="space-y-2"></div>
                        <p id="bank-account-empty" class="text-xs text-slate-400 hidden">Belum ada akun bank. Tambahkan di COA.</p>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mt-6">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-primary text-sm mt-0.5">info</span>
                            <p class="text-xs text-blue-800 leading-relaxed">
                                Untuk piutang, pilih Bayar Nanti lalu isi jatuh tempo, pelanggan, catatan, dan DP jika ada.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-white border-t border-slate-100 shadow-[0_-10px_20px_rgba(0,0,0,0.02)] app-bottom-safe-footer">
                <button id="confirm-payment" class="w-full h-14 bg-emerald-500 text-white font-black rounded-2xl flex items-center justify-center gap-3 shadow-lg shadow-emerald-500/20 active:scale-95 transition-transform" type="button">
                    <span class="material-symbols-outlined font-bold">check_circle</span>
                    KONFIRMASI PEMBAYARAN
                </button>
                <p id="confirm-caption" class="mt-3 text-center text-xs font-medium text-slate-500">Masukkan nominal yang diterima kasir.</p>
                <div class="mt-3 flex justify-around">
                    <button id="save-order-from-payment" class="flex flex-col items-center gap-1 text-slate-500" type="button">
                        <span class="material-symbols-outlined">receipt</span>
                        <span class="text-[10px] font-bold">Simpan Pesanan</span>
                    </button>
                    <div class="flex flex-col items-center gap-1 opacity-40">
                        <span class="material-symbols-outlined">share</span>
                        <span class="text-[10px] font-bold">Bagikan Tagihan</span>
                    </div>
                </div>
            </div>
        </div>
        <div id="receivable-modal" class="fixed inset-0 z-40 hidden items-end justify-center bg-slate-950/30 backdrop-blur-[2px]">
            <div id="receivable-overlay" class="absolute inset-0"></div>
            <div class="relative flex max-h-[90%] w-full max-w-[430px] flex-col overflow-hidden rounded-t-[28px] bg-white shadow-2xl">
                <div class="border-b border-slate-200 px-4 pt-3 pb-4">
                    <div class="mx-auto mb-3 h-1.5 w-14 rounded-full bg-slate-200"></div>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-amber-600">Bayar Nanti</p>
                            <h3 class="text-lg font-bold text-slate-900">Detail Piutang</h3>
                            <p id="receivable-modal-summary" class="text-xs text-slate-500">Sisa tagihan akan dicatat sebagai piutang.</p>
                        </div>
                        <button id="receivable-close" class="rounded-xl bg-slate-100 p-2 text-slate-500" type="button" aria-label="Tutup piutang">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4 no-scrollbar">
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block space-y-1">
                            <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Tempo Hari</span>
                            <input id="receivable-days" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-primary focus:ring-primary" type="number" min="1" step="1" value="7" />
                        </label>
                        <label class="block space-y-1">
                            <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Tanggal</span>
                            <input id="receivable-due-date" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-primary focus:ring-primary" type="date" />
                        </label>
                    </div>
                    <label class="block space-y-1">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Pelanggan</span>
                        <select id="receivable-customer" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary">
                            <option value="">Pilih pelanggan</option>
                        </select>
                    </label>
                    <button id="receivable-customer-create" class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-2 text-xs font-bold text-primary shadow-sm" type="button">
                        <span class="material-symbols-outlined text-sm">person_add</span>
                        Tambah Pelanggan
                    </button>
                    <label class="block space-y-1">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Catatan</span>
                        <textarea id="receivable-note" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary" rows="3" maxlength="500" placeholder="Catatan piutang"></textarea>
                    </label>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <div class="grid grid-cols-2 gap-2">
                            <button id="receivable-dp-none" class="rounded-xl border border-amber-500 bg-amber-500 px-3 py-3 text-xs font-black text-white" type="button">
                                Tanpa DP
                            </button>
                            <button id="receivable-dp-some" class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-xs font-black text-slate-600" type="button">
                                Ada DP
                            </button>
                        </div>
                        <div id="receivable-dp-panel" class="mt-3 hidden space-y-3">
                            <label class="block space-y-1">
                                <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Nominal DP</span>
                                <input id="receivable-dp-amount" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 focus:border-primary focus:ring-primary" type="number" min="0" step="100" inputmode="numeric" placeholder="0" />
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <button class="receivable-dp-method rounded-xl border border-primary bg-primary/10 px-2 py-2 text-xs font-bold text-primary" data-dp-method="cash" type="button">Cash</button>
                                <button class="receivable-dp-method rounded-xl border border-slate-200 bg-white px-2 py-2 text-xs font-bold text-slate-600" data-dp-method="bank" type="button">Bank</button>
                                <button class="receivable-dp-method rounded-xl border border-slate-200 bg-white px-2 py-2 text-xs font-bold text-slate-600" data-dp-method="qris" type="button">QRIS</button>
                            </div>
                        </div>
                        <div class="mt-3 rounded-xl border border-amber-200 bg-white px-3 py-2">
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>DP</span>
                                <span id="receivable-dp-preview" class="font-bold text-slate-900">Rp 0</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-xs text-slate-500">
                                <span>Sisa Piutang</span>
                                <span id="receivable-remaining-preview" class="font-bold text-amber-700">Rp 0</span>
                            </div>
                        </div>
                    </div>
                    <p id="receivable-feedback" class="hidden rounded-xl border px-3 py-2 text-xs font-medium"></p>
                </div>
                <div class="border-t border-slate-200 bg-white px-4 py-4">
                    <div class="flex gap-3">
                        <button id="receivable-cancel" class="inline-flex flex-1 items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600" type="button">
                            Batal
                        </button>
                        <button id="receivable-apply" class="inline-flex flex-1 items-center justify-center gap-2 rounded-2xl bg-amber-500 px-4 py-3 text-sm font-bold text-white" type="button">
                            <span class="material-symbols-outlined text-lg">check_circle</span>
                            Pakai Piutang
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div id="customer-modal" class="fixed inset-0 z-50 hidden items-end justify-center bg-slate-950/30 backdrop-blur-[2px]">
            <div class="relative flex max-h-[90%] w-full max-w-[430px] flex-col overflow-hidden rounded-t-[28px] bg-white shadow-2xl">
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
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4 no-scrollbar">
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
        <div id="save-order-modal" class="fixed inset-0 z-40 hidden items-end justify-center bg-slate-950/30 backdrop-blur-[2px]">
            <div id="save-order-overlay" class="absolute inset-0"></div>
            <div class="relative flex max-h-[90%] w-full max-w-[430px] flex-col overflow-hidden rounded-t-[28px] bg-white shadow-2xl">
                <div class="border-b border-slate-200 px-4 pt-3 pb-4">
                    <div class="mx-auto mb-3 h-1.5 w-14 rounded-full bg-slate-200"></div>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-slate-400">Simpan Pesanan</p>
                            <h3 class="text-lg font-bold text-slate-900">Rekap Pesanan</h3>
                            <p class="text-xs text-slate-500">Pilih pelanggan jika perlu, cek atas nama, lalu simpan catatan pesanan.</p>
                        </div>
                        <button id="save-order-close" class="rounded-xl bg-slate-100 p-2 text-slate-500" type="button" aria-label="Tutup simpan pesanan">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4 no-scrollbar">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span>Total Tagihan</span>
                            <span id="save-order-total" class="font-bold text-slate-900">Rp 0</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-xs text-slate-500">
                            <span>Jumlah Item</span>
                            <span id="save-order-item-count" class="font-bold text-slate-900">0 item</span>
                        </div>
                        <p id="save-order-item-notes" class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] font-medium text-amber-800">Tidak ada catatan item.</p>
                    </div>
                    <label class="block space-y-1">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Pelanggan</span>
                        <select id="save-order-customer" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary">
                            <option value="">Pelanggan Umum</option>
                        </select>
                    </label>
                    <button id="save-order-customer-create" class="inline-flex items-center gap-1 rounded-full bg-white px-3 py-2 text-xs font-bold text-primary shadow-sm" type="button">
                        <span class="material-symbols-outlined text-sm">person_add</span>
                        Tambah Pelanggan
                    </button>
                    <label class="block space-y-1">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Atas Nama</span>
                        <input id="save-order-holder" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary" type="text" maxlength="120" placeholder="Nama pemesan" />
                    </label>
                    <label class="block space-y-1">
                        <span class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Catatan</span>
                        <textarea id="save-order-note" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary" rows="3" maxlength="500" placeholder="Catatan pesanan (opsional)"></textarea>
                    </label>
                    <p id="save-order-feedback" class="hidden rounded-xl border px-3 py-2 text-xs font-medium"></p>
                </div>
                <div class="border-t border-slate-200 bg-white px-4 py-4">
                    <div class="flex gap-3">
                        <button id="save-order-cancel" class="inline-flex flex-1 items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600" type="button">
                            Batal
                        </button>
                        <button id="save-order-submit" class="inline-flex flex-1 items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-white" type="button">
                            <span class="material-symbols-outlined text-lg">save</span>
                            Simpan
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @include('partials.ui-helpers')
        <script>
            (() => {
                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);
                const parseAmount = (value, fallback = 0) => {
                    if (typeof value === 'number') {
                        return Number.isFinite(value) ? value : fallback;
                    }

                    if (typeof value === 'string') {
                        const normalized = value.replace(/[^\d,-]/g, '').replace(',', '.');
                        const parsed = Number(normalized || 0);
                        return Number.isFinite(parsed) ? parsed : fallback;
                    }

                    return fallback;
                };

                const els = {
                    total: document.getElementById('total-amount'),
                    remaining: document.getElementById('remaining-amount'),
                    pay: document.getElementById('pay-amount'),
                    change: document.getElementById('change-amount'),
                    status: document.getElementById('payment-status'),
                    hint: document.getElementById('payment-hint'),
                    discountSummary: document.getElementById('payment-discount-summary'),
                    itemDiscount: document.getElementById('payment-item-discount'),
                    transactionDiscount: document.getElementById('payment-transaction-discount'),
                    snapshotGuard: document.getElementById('payment-snapshot-guard'),
                    reset: document.getElementById('reset-pay'),
                    error: document.getElementById('payment-error'),
                    confirm: document.getElementById('confirm-payment'),
                    confirmCaption: document.getElementById('confirm-caption'),
                    quickAmounts: document.getElementById('quick-amount-list'),
                    bankPicker: document.getElementById('bank-account-picker'),
                    bankList: document.getElementById('bank-account-list'),
                    bankEmpty: document.getElementById('bank-account-empty'),
                    receivableModal: document.getElementById('receivable-modal'),
                    receivableOverlay: document.getElementById('receivable-overlay'),
                    receivableClose: document.getElementById('receivable-close'),
                    receivableCancel: document.getElementById('receivable-cancel'),
                    receivableApply: document.getElementById('receivable-apply'),
                    receivableDays: document.getElementById('receivable-days'),
                    receivableDueDate: document.getElementById('receivable-due-date'),
                    receivableCustomer: document.getElementById('receivable-customer'),
                    receivableNote: document.getElementById('receivable-note'),
                    receivableDpNone: document.getElementById('receivable-dp-none'),
                    receivableDpSome: document.getElementById('receivable-dp-some'),
                    receivableDpPanel: document.getElementById('receivable-dp-panel'),
                    receivableDpAmount: document.getElementById('receivable-dp-amount'),
                    receivableDpPreview: document.getElementById('receivable-dp-preview'),
                    receivableRemainingPreview: document.getElementById('receivable-remaining-preview'),
                    receivableSummary: document.getElementById('receivable-modal-summary'),
                    receivableFeedback: document.getElementById('receivable-feedback'),
                    receivableCustomerCreate: document.getElementById('receivable-customer-create'),
                    saveOrderFromPayment: document.getElementById('save-order-from-payment'),
                    saveOrderModal: document.getElementById('save-order-modal'),
                    saveOrderOverlay: document.getElementById('save-order-overlay'),
                    saveOrderClose: document.getElementById('save-order-close'),
                    saveOrderCancel: document.getElementById('save-order-cancel'),
                    saveOrderSubmit: document.getElementById('save-order-submit'),
                    saveOrderCustomer: document.getElementById('save-order-customer'),
                    saveOrderCustomerCreate: document.getElementById('save-order-customer-create'),
                    saveOrderHolder: document.getElementById('save-order-holder'),
                    saveOrderNote: document.getElementById('save-order-note'),
                    saveOrderFeedback: document.getElementById('save-order-feedback'),
                    saveOrderTotal: document.getElementById('save-order-total'),
                    saveOrderItemCount: document.getElementById('save-order-item-count'),
                    saveOrderItemNotes: document.getElementById('save-order-item-notes'),
                    editItemsButton: document.getElementById('edit-items-button'),
                    customerModal: document.getElementById('customer-modal'),
                    customerModalClose: document.getElementById('customer-modal-close'),
                    customerModalCancel: document.getElementById('customer-modal-cancel'),
                    customerNameInput: document.getElementById('customer-name-input'),
                    customerPhoneInput: document.getElementById('customer-phone-input'),
                    customerAddressInput: document.getElementById('customer-address-input'),
                    customerSaveButton: document.getElementById('customer-save-button'),
                    customerModalFeedback: document.getElementById('customer-modal-feedback'),
                };

                const loadSnapshot = () => {
                    try {
                        const raw = sessionStorage.getItem('pos_payment');
                        return raw ? JSON.parse(raw) : null;
                    } catch (error) {
                        return null;
                    }
                };

                const cartStorageKey = 'pos_cart_draft';
                const syncDraftFromSnapshot = (source) => {
                    if (!source) {
                        return;
                    }

                    const checkoutItems = Array.isArray(source.checkout_items) ? source.checkout_items : [];
                    const items = checkoutItems
                        .filter((item) => item.line_type !== 'fee')
                        .map((item) => ({
                            product_id: item.product_id || null,
                            qty: Number(item.qty || 1),
                            discounts: Array.isArray(item.discounts) ? item.discounts : [],
                            note: String(item.note || '').trim(),
                        }))
                        .filter((item) => Boolean(item.product_id));

                    const fallbackItems = items.length
                        ? items
                        : (Array.isArray(source.items)
                            ? source.items.map((item) => ({
                                product_id: item.product_id || item.id || null,
                                qty: Number(item.qty || 1),
                                discounts: [],
                                note: '',
                            })).filter((item) => Boolean(item.product_id))
                            : []);

                    if (!fallbackItems.length) {
                        return;
                    }

                    sessionStorage.setItem(cartStorageKey, JSON.stringify({
                        items: fallbackItems,
                        customer_id: source.customer_id || null,
                        fees: Array.isArray(source.fees) ? source.fees : [],
                        selected_promos: Array.isArray(source.selected_promos) ? source.selected_promos : [],
                        transaction_discounts: Array.isArray(source.transaction_discounts) ? source.transaction_discounts : [],
                        order_holder_name: String(source.order_holder_name || '').trim(),
                        order_note: String(source.order_note || '').trim(),
                        replace_sale_id: source.replace_sale_id || null,
                        transaction_date: source.transaction_date || null,
                        updated_at: new Date().toISOString(),
                    }));
                };

                const snapshot = loadSnapshot();
                const initialTotal = parseAmount(snapshot?.total, parseAmount(els.total?.textContent));
                const tenantId = document.querySelector('meta[name="tenant-id"]')?.content || '';
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const readCookie = (name) => document.cookie
                    .split('; ')
                    .find((row) => row.startsWith(`${name}=`))
                    ?.split('=')
                    .slice(1)
                    .join('=') || '';
                const csrfHeaders = () => {
                    const xsrfToken = readCookie('XSRF-TOKEN');
                    if (xsrfToken) {
                        return { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) };
                    }

                    return csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {};
                };
                const fetchWriteWithFreshCsrf = async (url, options = {}, timeout = null) => {
                    const makeOptions = () => ({
                        ...options,
                        credentials: 'same-origin',
                        headers: {
                            ...(options.headers || {}),
                            ...headers,
                            ...csrfHeaders(),
                        },
                    });

                    let response = timeout
                        ? await window.PosUi.fetchWithTimeout(url, makeOptions(), timeout)
                        : await fetch(url, makeOptions());
                    if (response.status === 419) {
                        await fetch('/sanctum/csrf-cookie', {
                            credentials: 'same-origin',
                            headers,
                        }).catch(() => null);
                        response = timeout
                            ? await window.PosUi.fetchWithTimeout(url, makeOptions(), timeout)
                            : await fetch(url, makeOptions());
                    }

                    return response;
                };
                const formatDateInput = (date) => {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');

                    return `${year}-${month}-${day}`;
                };
                const addDays = (days) => {
                    const date = new Date();
                    date.setDate(date.getDate() + Number(days || 0));

                    return formatDateInput(date);
                };
                const defaultReceivableDays = 7;
                const state = {
                    total: initialTotal,
                    paidInput: '',
                    method: 'cash',
                    bankAccounts: [],
                    selectedBankId: null,
                    customers: [],
                    receivable: {
                        customerId: snapshot?.customer_id || null,
                        dueDays: defaultReceivableDays,
                        dueDate: addDays(defaultReceivableDays),
                        note: snapshot?.order_note || '',
                        hasDp: false,
                        dpAmount: 0,
                        dpMethod: 'cash',
                        configured: false,
                    },
                };

                const savedOrdersStorageKey = 'pos_saved_orders_v1';
                const createLocalId = () => crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;

                const showSheet = (element) => {
                    element?.classList.remove('hidden');
                    element?.classList.add('flex');
                };

                const hideSheet = (element) => {
                    element?.classList.add('hidden');
                    element?.classList.remove('flex');
                };

                const saveOrderToApi = async (payload, savedOrderId = null) => {
                    const response = await fetchWriteWithFreshCsrf(savedOrderId ? `/api/v1/pos/saved-orders/${savedOrderId}` : '/api/v1/pos/saved-orders', {
                        method: savedOrderId ? 'PUT' : 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(payload),
                    });

                    if (!response.ok) {
                        const mapped = await window.PosUi.mapApiError(response, 'Gagal menyimpan pesanan.');
                        throw new Error(mapped.message);
                    }

                    const data = await response.json().catch(() => ({}));
                    return data.saved_order || null;
                };

                const preloadedBanks = @json($bankAccounts ?? []);
                if (Array.isArray(preloadedBanks) && preloadedBanks.length) {
                    state.bankAccounts = preloadedBanks;
                    state.selectedBankId = state.bankAccounts[0]?.id || null;
                }

                const methodConfig = {
                    cash: { accountKey: 'cash_account_id', method: 'cash' },
                    bank: { accountKey: 'bank_account_id', method: 'bank' },
                    qris: { accountKey: 'bank_account_id', method: 'qris' },
                    receivable: { accountKey: null, method: 'receivable' },
                };

                const setMethod = (method) => {
                    if (!methodConfig[method]) {
                        return;
                    }
                    state.method = method;
                    const isCash = method === 'cash';
                    document.querySelectorAll('[data-payment-method]').forEach((card) => {
                        const active = card.dataset.paymentMethod === method;
                        card.classList.toggle('border-primary', active);
                        card.classList.toggle('bg-primary/5', active);
                        card.classList.toggle('shadow-sm', active);
                        card.classList.toggle('border-slate-200', !active);
                    });
                    document.querySelectorAll('[data-method-active]').forEach((icon) => {
                        icon.classList.toggle('hidden', icon.dataset.methodActive !== method);
                    });
                    document.querySelectorAll('[data-method-inactive]').forEach((icon) => {
                        icon.classList.toggle('hidden', icon.dataset.methodInactive === method);
                    });
                    // Collapse cash numpad when non-cash method is selected
                    const cashPanel = document.getElementById('cash-input-panel');
                    if (cashPanel) {
                        if (isCash) {
                            cashPanel.style.maxHeight = cashPanel.scrollHeight + 'px';
                            cashPanel.style.opacity = '1';
                            cashPanel.style.padding = '';
                            cashPanel.style.borderTopWidth = '';
                        } else {
                            cashPanel.style.maxHeight = '0';
                            cashPanel.style.opacity = '0';
                            cashPanel.style.padding = '0 1rem';
                            cashPanel.style.borderTopWidth = '0';
                        }
                    }
                    // Auto-set paid amount to total for non-cash (exact payment)
                    if (!isCash && state.method !== 'receivable') {
                        state.paidInput = String(Math.max(0, Math.floor(state.total || 0)));
                    }
                    els.bankPicker.classList.toggle('hidden', method !== 'bank');
                    els.hint.textContent = isCash
                        ? 'Gunakan angka cepat atau numpad untuk mempercepat pembayaran tunai.'
                        : method === 'bank'
                            ? 'Pilih akun bank/EDC lalu konfirmasi nominal yang dibayar.'
                            : method === 'qris'
                                ? 'Konfirmasi pembayaran QRIS atau e-wallet sesuai nominal tagihan.'
                                : 'Lengkapi detail piutang, lalu konfirmasi Bayar Nanti.';
                    updateAmounts();
                };

                const loadBankAccounts = async () => {
                    const response = await fetch('/api/v1/bank-accounts', { headers });
                    if (!response.ok) {
                        const payload = await response.json().catch(() => ({}));
                        throw new Error(payload?.message || 'Gagal memuat akun bank.');
                    }
                    return response.json();
                };

                const fallbackLoadBankAccounts = async () => {
                    const url = new URL('/api/v1/chart-of-accounts', window.location.origin);
                    url.searchParams.set('type', 'asset');
                    const response = await fetch(url, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat akun bank.');
                    }
                    const data = await response.json();
                    const accounts = data.accounts || [];
                    const parent = accounts.find((account) => account.code === '1102' && !account.parent_id)
                        || accounts.find((account) => !account.parent_id && String(account.name || '').toLowerCase().includes('bank'));
                    if (!parent) {
                        return [];
                    }
                    const children = accounts.filter((account) => account.parent_id === parent.id);
                    return children.length ? children : [parent];
                };

                const renderBankAccounts = () => {
                    els.bankList.innerHTML = '';
                    if (!state.bankAccounts.length) {
                        els.bankEmpty.classList.remove('hidden');
                        updateAmounts();
                        return;
                    }

                    els.bankEmpty.classList.add('hidden');
                    state.bankAccounts.forEach((account) => {
                        const isActive = state.selectedBankId === account.id;
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = `w-full flex items-center justify-between gap-3 p-3 rounded-xl border ${isActive ? 'border-primary bg-primary/5' : 'border-slate-200'} transition`;
                        button.innerHTML = `
                            <div class="text-left">
                                <p class="text-sm font-semibold text-slate-900">${account.name}</p>
                                <p class="text-xs text-slate-500">${account.code}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a class="text-[11px] font-bold text-primary" href="/portal/switch/backoffice?next=${encodeURIComponent('/backoffice/settings/bank-accounts/' + account.id)}" onclick="event.stopPropagation()">Riwayat</a>
                                <span class="material-symbols-outlined text-primary ${isActive ? '' : 'hidden'}">check_circle</span>
                            </div>
                        `;
                        button.addEventListener('click', () => {
                            state.selectedBankId = account.id;
                            renderBankAccounts();
                            updateAmounts();
                        });
                        els.bankList.appendChild(button);
                    });
                    updateAmounts();
                };

                const showError = (message) => {
                    if (!message) {
                        els.error.classList.add('hidden');
                        els.error.textContent = '';
                        return;
                    }
                    els.error.textContent = message;
                    els.error.classList.remove('hidden');
                    els.error.scrollIntoView({ block: 'nearest' });
                };

                const setSaveOrderFeedback = (message = '', tone = 'neutral') => {
                    if (!els.saveOrderFeedback) {
                        return;
                    }

                    if (!message) {
                        els.saveOrderFeedback.className = 'hidden rounded-xl border px-3 py-2 text-xs font-medium';
                        els.saveOrderFeedback.textContent = '';
                        return;
                    }

                    const toneClass = tone === 'error'
                        ? 'border-rose-200 bg-rose-50 text-rose-700'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-700';
                    els.saveOrderFeedback.className = `rounded-xl border px-3 py-2 text-xs font-medium ${toneClass}`;
                    els.saveOrderFeedback.textContent = message;
                };

                const loadCustomers = async () => {
                    const response = await fetch('/api/v1/pos/customers?lite=1&limit=100', { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const payload = await response.json().catch(() => ({}));
                    return Array.isArray(payload.customers) ? payload.customers : [];
                };

                const ensureCustomersLoaded = async () => {
                    if (!state.customers.length) {
                        state.customers = await loadCustomers();
                    }

                    return state.customers;
                };

                const renderCustomerOptions = (select, selectedValue = '', emptyLabel = 'Pilih pelanggan') => {
                    if (!select) {
                        return;
                    }

                    const normalizedSelected = String(selectedValue || '');
                    select.innerHTML = `<option value="">${emptyLabel}</option>`;
                    state.customers.forEach((customer) => {
                        const option = document.createElement('option');
                        option.value = customer.id;
                        option.textContent = customer.name;
                        select.appendChild(option);
                    });
                    const hasSelected = state.customers.some((customer) => String(customer.id) === normalizedSelected);
                    if (!hasSelected && normalizedSelected) {
                        const option = document.createElement('option');
                        option.value = normalizedSelected;
                        option.textContent = String(snapshot?.order_holder_name || 'Pelanggan terpilih');
                        select.appendChild(option);
                    }
                    select.value = hasSelected || normalizedSelected ? normalizedSelected : '';
                };

                const setCustomerModalFeedback = (message = '', tone = 'neutral') => {
                    if (!els.customerModalFeedback) {
                        return;
                    }

                    if (!message) {
                        els.customerModalFeedback.className = 'hidden rounded-xl border px-3 py-2 text-xs font-medium';
                        els.customerModalFeedback.textContent = '';
                        return;
                    }

                    const toneClass = tone === 'error'
                        ? 'border-rose-200 bg-rose-50 text-rose-700'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-700';
                    els.customerModalFeedback.className = `rounded-xl border px-3 py-2 text-xs font-medium ${toneClass}`;
                    els.customerModalFeedback.textContent = message;
                };

                const resetCustomerModal = () => {
                    if (els.customerNameInput) {
                        els.customerNameInput.value = '';
                    }
                    if (els.customerPhoneInput) {
                        els.customerPhoneInput.value = '';
                    }
                    if (els.customerAddressInput) {
                        els.customerAddressInput.value = '';
                    }
                    setCustomerModalFeedback('');
                };

                const openCustomerModal = (source = 'receivable') => {
                    if (!els.customerModal) {
                        return;
                    }

                    resetCustomerModal();
                    els.customerModal.dataset.source = source;
                    showSheet(els.customerModal);
                    setTimeout(() => {
                        els.customerNameInput?.focus();
                    }, 50);
                };

                const closeCustomerModal = () => {
                    hideSheet(els.customerModal);
                    if (els.customerModal) {
                        delete els.customerModal.dataset.source;
                    }
                    resetCustomerModal();
                };

                const rememberCustomer = (customer) => {
                    if (!customer?.id) {
                        return;
                    }

                    state.customers = [
                        customer,
                        ...state.customers.filter((entry) => String(entry.id) !== String(customer.id)),
                    ].sort((left, right) => String(left.name || '').localeCompare(String(right.name || ''), 'id'));
                };

                const createCustomer = async () => {
                    const name = String(els.customerNameInput?.value || '').trim();
                    const phone = String(els.customerPhoneInput?.value || '').trim();
                    const address = String(els.customerAddressInput?.value || '').trim();

                    if (!name) {
                        setCustomerModalFeedback('Nama pelanggan wajib diisi.', 'error');
                        els.customerNameInput?.focus();
                        return null;
                    }

                    const response = await fetch('/api/v1/pos/customers', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                            ...headers,
                        },
                        body: JSON.stringify({
                            name,
                            phone,
                            address,
                        }),
                    });

                    if (!response.ok) {
                        const mapped = await window.PosUi.mapApiError(response, 'Gagal menyimpan pelanggan.');
                        throw new Error(mapped.message);
                    }

                    const payload = await response.json().catch(() => ({}));
                    return payload.customer || null;
                };

                const selectCreatedCustomer = (customer, source = 'receivable') => {
                    if (!customer?.id) {
                        return;
                    }

                    rememberCustomer(customer);

                    if (source === 'save-order') {
                        renderCustomerOptions(els.saveOrderCustomer, customer.id, 'Pelanggan Umum');
                        if (els.saveOrderCustomer) {
                            els.saveOrderCustomer.value = customer.id;
                        }
                        syncHolderNameFromCustomer();
                        return;
                    }

                    renderCustomerOptions(els.receivableCustomer, customer.id, 'Pilih pelanggan');
                    if (els.receivableCustomer) {
                        els.receivableCustomer.value = customer.id;
                    }
                    setReceivableFeedback('Pelanggan baru dipilih. Lengkapi detail piutang lalu tekan Pakai Piutang.', 'success');
                };

                const renderSaveOrderCustomers = () => {
                    if (!els.saveOrderCustomer) {
                        return;
                    }

                    const selectedValue = String(els.saveOrderCustomer.value || snapshot?.customer_id || '');
                    renderCustomerOptions(els.saveOrderCustomer, selectedValue, 'Pelanggan Umum');
                };

                const closeSaveOrderModal = () => {
                    hideSheet(els.saveOrderModal);
                    setSaveOrderFeedback('');
                };

                const syncHolderNameFromCustomer = () => {
                    const selectedId = els.saveOrderCustomer?.value || '';
                    const customer = state.customers.find((entry) => String(entry.id) === String(selectedId));
                    if (!els.saveOrderHolder) {
                        return;
                    }

                    const currentValue = String(els.saveOrderHolder.value || '').trim();
                    const snapshotValue = String(snapshot?.order_holder_name || '').trim();
                    const previousAutoFill = String(els.saveOrderHolder.dataset.autofillName || '').trim();

                    if (!customer) {
                        if (currentValue && currentValue === previousAutoFill) {
                            els.saveOrderHolder.value = snapshotValue;
                        }
                        delete els.saveOrderHolder.dataset.autofillName;
                        return;
                    }

                    if (!currentValue || currentValue === snapshotValue || currentValue === previousAutoFill) {
                        els.saveOrderHolder.value = customer.name || '';
                    }
                    els.saveOrderHolder.dataset.autofillName = customer.name || '';
                };

                const openSaveOrderModal = async () => {
                    if (!snapshot) {
                        showError('Data pembayaran tidak ditemukan.');
                        return;
                    }

                    await ensureCustomersLoaded();

                    renderSaveOrderCustomers();

                    if (els.saveOrderCustomer) {
                        els.saveOrderCustomer.value = snapshot.customer_id || '';
                    }

                    const selectedCustomer = state.customers.find((entry) => String(entry.id) === String(snapshot.customer_id || ''));
                    if (els.saveOrderHolder) {
                        els.saveOrderHolder.value = String(snapshot.order_holder_name || selectedCustomer?.name || '').trim();
                        if (selectedCustomer?.name) {
                            els.saveOrderHolder.dataset.autofillName = selectedCustomer.name;
                        } else {
                            delete els.saveOrderHolder.dataset.autofillName;
                        }
                    }
                    if (els.saveOrderNote) {
                        els.saveOrderNote.value = String(snapshot.order_note || '').trim();
                    }
                    if (els.saveOrderTotal) {
                        els.saveOrderTotal.textContent = formatCurrency(snapshot.total || 0);
                    }
                    if (els.saveOrderItemCount) {
                        const itemCount = Array.isArray(snapshot.items)
                            ? snapshot.items.reduce((sum, item) => sum + Number(item.qty || 0), 0)
                            : 0;
                        els.saveOrderItemCount.textContent = `${itemCount} item`;
                    }
                    if (els.saveOrderItemNotes) {
                        const itemNotes = Array.isArray(snapshot.checkout_items)
                            ? snapshot.checkout_items.map((item) => String(item.note || '').trim()).filter(Boolean)
                            : [];
                        const preview = itemNotes.length
                            ? itemNotes.slice(0, 2).join(' • ') + (itemNotes.length > 2 ? ` • +${itemNotes.length - 2} lagi` : '')
                            : 'Tidak ada catatan item.';
                        els.saveOrderItemNotes.textContent = preview;
                    }

                    setSaveOrderFeedback('');
                    showSheet(els.saveOrderModal);
                    setTimeout(() => {
                        if (els.saveOrderHolder?.value) {
                            els.saveOrderNote?.focus();
                        } else {
                            els.saveOrderHolder?.focus();
                        }
                    }, 50);
                };

                const accountIdForPaymentMethod = (method) => {
                    if (method === 'bank') {
                        return state.selectedBankId || snapshot?.account_mapping?.bank_account_id || null;
                    }

                    if (method === 'qris') {
                        return snapshot?.account_mapping?.bank_account_id || state.selectedBankId || null;
                    }

                    return snapshot?.account_mapping?.cash_account_id || null;
                };

                const setReceivableFeedback = (message = '', tone = 'neutral') => {
                    if (!els.receivableFeedback) {
                        return;
                    }

                    if (!message) {
                        els.receivableFeedback.className = 'hidden rounded-xl border px-3 py-2 text-xs font-medium';
                        els.receivableFeedback.textContent = '';
                        return;
                    }

                    const toneClass = tone === 'error'
                        ? 'border-rose-200 bg-rose-50 text-rose-700'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-700';
                    els.receivableFeedback.className = `rounded-xl border px-3 py-2 text-xs font-medium ${toneClass}`;
                    els.receivableFeedback.textContent = message;
                };

                const setReceivableDpMethod = (method) => {
                    state.receivable.dpMethod = ['cash', 'bank', 'qris'].includes(method) ? method : 'cash';
                    document.querySelectorAll('.receivable-dp-method').forEach((button) => {
                        const active = button.dataset.dpMethod === state.receivable.dpMethod;
                        button.classList.toggle('border-primary', active);
                        button.classList.toggle('bg-primary/10', active);
                        button.classList.toggle('text-primary', active);
                        button.classList.toggle('border-slate-200', !active);
                        button.classList.toggle('bg-white', !active);
                        button.classList.toggle('text-slate-600', !active);
                    });
                };

                const setReceivableDpMode = (hasDp) => {
                    state.receivable.hasDp = Boolean(hasDp);
                    els.receivableDpPanel?.classList.toggle('hidden', !state.receivable.hasDp);
                    els.receivableDpPanel?.setAttribute('data-enabled', state.receivable.hasDp ? 'true' : 'false');
                    els.receivableDpNone?.classList.toggle('bg-amber-500', !state.receivable.hasDp);
                    els.receivableDpNone?.classList.toggle('text-white', !state.receivable.hasDp);
                    els.receivableDpNone?.classList.toggle('border-amber-500', !state.receivable.hasDp);
                    els.receivableDpNone?.classList.toggle('bg-white', state.receivable.hasDp);
                    els.receivableDpNone?.classList.toggle('text-slate-600', state.receivable.hasDp);
                    els.receivableDpNone?.classList.toggle('border-slate-200', state.receivable.hasDp);
                    els.receivableDpSome?.classList.toggle('bg-amber-500', state.receivable.hasDp);
                    els.receivableDpSome?.classList.toggle('text-white', state.receivable.hasDp);
                    els.receivableDpSome?.classList.toggle('border-amber-500', state.receivable.hasDp);
                    els.receivableDpSome?.classList.toggle('bg-white', !state.receivable.hasDp);
                    els.receivableDpSome?.classList.toggle('text-slate-600', !state.receivable.hasDp);
                    els.receivableDpSome?.classList.toggle('border-slate-200', !state.receivable.hasDp);

                    if (!state.receivable.hasDp && els.receivableDpAmount) {
                        els.receivableDpAmount.value = '';
                    }

                    updateReceivablePreview();
                };

                const updateReceivablePreview = () => {
                    const hasDp = els.receivableDpPanel?.getAttribute('data-enabled') === 'true';
                    const dpAmount = hasDp ? Math.max(0, Math.floor(Number(els.receivableDpAmount?.value || 0))) : 0;
                    const remaining = Math.max((state.total || 0) - dpAmount, 0);
                    if (els.receivableDpPreview) {
                        els.receivableDpPreview.textContent = formatCurrency(dpAmount);
                    }
                    if (els.receivableRemainingPreview) {
                        els.receivableRemainingPreview.textContent = formatCurrency(remaining);
                    }
                    if (els.receivableSummary) {
                        els.receivableSummary.textContent = dpAmount > 0
                            ? `DP ${formatCurrency(dpAmount)}, sisa ${formatCurrency(remaining)} jadi piutang.`
                            : `${formatCurrency(state.total || 0)} akan dicatat sebagai piutang.`;
                    }
                };

                const syncReceivableDateFromDays = () => {
                    const days = Math.max(1, Math.floor(Number(els.receivableDays?.value || defaultReceivableDays)));
                    if (els.receivableDays) {
                        els.receivableDays.value = String(days);
                    }
                    if (els.receivableDueDate) {
                        els.receivableDueDate.value = addDays(days);
                    }
                };

                const syncReceivableDaysFromDate = () => {
                    if (!els.receivableDueDate?.value || !els.receivableDays) {
                        return;
                    }

                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    const dueDate = new Date(`${els.receivableDueDate.value}T00:00:00`);
                    const diffDays = Math.max(1, Math.round((dueDate.getTime() - today.getTime()) / 86400000));
                    els.receivableDays.value = String(diffDays);
                };

                const openReceivableModal = async () => {
                    if (!snapshot) {
                        showError('Data pembayaran tidak ditemukan.');
                        return;
                    }

                    const initialState = JSON.stringify(state.receivable);
                    await ensureCustomersLoaded();
                    renderCustomerOptions(els.receivableCustomer, state.receivable.customerId || snapshot.customer_id || '', 'Pilih pelanggan');

                    if (els.receivableDays) {
                        els.receivableDays.value = String(state.receivable.dueDays || defaultReceivableDays);
                    }
                    if (els.receivableDueDate) {
                        els.receivableDueDate.value = state.receivable.dueDate || addDays(defaultReceivableDays);
                    }
                    if (els.receivableNote) {
                        els.receivableNote.value = String(state.receivable.note || snapshot.order_note || '').trim();
                    }
                    if (els.receivableDpAmount) {
                        els.receivableDpAmount.value = state.receivable.hasDp && state.receivable.dpAmount > 0
                            ? String(Math.floor(state.receivable.dpAmount))
                            : '';
                    }

                    setReceivableFeedback('');
                    if (els.receivableModal) {
                        els.receivableModal.dataset.initialState = initialState;
                    }
                    setReceivableDpMethod(state.receivable.dpMethod || 'cash');
                    setReceivableDpMode(Boolean(state.receivable.hasDp));
                    updateReceivablePreview();
                    showSheet(els.receivableModal);
                    setTimeout(() => {
                        if (els.receivableCustomer?.value) {
                            els.receivableNote?.focus();
                        } else {
                            els.receivableCustomer?.focus();
                        }
                    }, 50);
                };

                const closeReceivableModal = (revertIfBlank = false, restoreDraft = true) => {
                    hideSheet(els.receivableModal);
                    setReceivableFeedback('');
                    if (restoreDraft && els.receivableModal?.dataset.initialState) {
                        try {
                            state.receivable = JSON.parse(els.receivableModal.dataset.initialState);
                        } catch (_) {
                            // Keep the current in-memory state if the saved draft cannot be parsed.
                        }
                        updateAmounts();
                    }
                    if (els.receivableModal) {
                        delete els.receivableModal.dataset.initialState;
                    }
                    if (revertIfBlank && state.method === 'receivable' && !state.receivable.configured) {
                        setMethod('cash');
                    }
                };

                const applyReceivableDetails = async () => {
                    const customerId = String(els.receivableCustomer?.value || '').trim();
                    const dueDate = String(els.receivableDueDate?.value || '').trim();
                    const dueDays = Math.max(1, Math.floor(Number(els.receivableDays?.value || defaultReceivableDays)));
                    const note = String(els.receivableNote?.value || '').trim();
                    const hasDp = els.receivableDpPanel?.getAttribute('data-enabled') === 'true';
                    const dpAmount = hasDp ? Math.max(0, Math.floor(Number(els.receivableDpAmount?.value || 0))) : 0;

                    if (!customerId) {
                        setReceivableFeedback('Pilih pelanggan untuk transaksi piutang.', 'error');
                        els.receivableCustomer?.focus();
                        return;
                    }

                    if (!dueDate) {
                        setReceivableFeedback('Tanggal jatuh tempo wajib diisi.', 'error');
                        els.receivableDueDate?.focus();
                        return;
                    }

                    if (hasDp && dpAmount <= 0) {
                        setReceivableFeedback('Isi nominal DP atau pilih Tanpa DP.', 'error');
                        els.receivableDpAmount?.focus();
                        return;
                    }

                    if (dpAmount >= state.total) {
                        setReceivableFeedback('DP harus lebih kecil dari total tagihan.', 'error');
                        els.receivableDpAmount?.focus();
                        return;
                    }

                    if (dpAmount > 0 && !accountIdForPaymentMethod(state.receivable.dpMethod)) {
                        setReceivableFeedback('Akun untuk metode DP belum disiapkan.', 'error');
                        return;
                    }

                    state.receivable = {
                        ...state.receivable,
                        customerId,
                        dueDays,
                        dueDate,
                        note,
                        hasDp,
                        dpAmount,
                        configured: true,
                    };
                    updateAmounts();
                    await submitPayment({
                        triggerButton: els.receivableApply,
                        skipConfirmation: true,
                        errorTarget: 'receivable',
                    });
                };

                const setStatus = (isPaid, isReceivable = false) => {
                    if (isReceivable) {
                        els.status.textContent = 'PIUTANG';
                        els.status.className = 'px-3 py-1 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold';
                    } else if (isPaid) {
                        els.status.textContent = 'LUNAS';
                        els.status.className = 'px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold';
                    } else {
                        els.status.textContent = 'BELUM LUNAS';
                        els.status.className = 'px-3 py-1 rounded-full bg-orange-100 text-orange-600 text-[10px] font-bold';
                    }
                };

                const updateAmounts = () => {
                    state.total = parseAmount(snapshot?.total, state.total || parseAmount(els.total?.textContent));
                    const isReceivable = state.method === 'receivable';
                    const paid = isReceivable
                        ? Math.max(0, Math.floor(parseAmount(state.receivable.dpAmount)))
                        : parseAmount(state.paidInput);
                    const total = state.total || 0;
                    const remaining = Math.max(total - paid, 0);
                    const change = isReceivable ? 0 : Math.max(paid - total, 0);
                    els.total.textContent = formatCurrency(total);
                    els.remaining.textContent = formatCurrency(remaining);
                    els.pay.textContent = formatCurrency(paid);
                    els.change.textContent = formatCurrency(change);
                    setStatus(paid >= total && total > 0, isReceivable);
                    const hasValidAmount = isReceivable
                        ? total > 0 && paid >= 0 && paid < total
                        : paid >= total && total > 0;
                    const accountBlocked = !isReceivable && !accountIdForPaymentMethod(state.method);
                    const customerBlocked = isReceivable && !state.receivable.customerId;
                    const dueDateBlocked = isReceivable && !state.receivable.dueDate;
                    const receivableNotReady = isReceivable && !state.receivable.configured;
                    const disabled = !hasValidAmount || accountBlocked || customerBlocked || dueDateBlocked || receivableNotReady;
                    els.confirm.disabled = disabled;
                    els.confirm.classList.toggle('opacity-60', disabled);
                    els.confirm.classList.toggle('cursor-not-allowed', disabled);
                    els.confirm.innerHTML = `${disabled ? '<span class="material-symbols-outlined font-bold">check_circle</span> KONFIRMASI PEMBAYARAN' : `<span class="material-symbols-outlined font-bold">check_circle</span> ${isReceivable ? `BUAT PIUTANG ${formatCurrency(remaining)}` : `BAYAR ${formatCurrency(total)}`}`}`;
                    els.confirmCaption.textContent = disabled
                        ? (receivableNotReady
                            ? 'Buka detail piutang lalu lengkapi data Bayar Nanti.'
                            : customerBlocked
                                ? 'Pelanggan wajib dipilih untuk transaksi piutang.'
                                : dueDateBlocked
                                    ? 'Tanggal jatuh tempo wajib diisi.'
                                    : !hasValidAmount
                                        ? (isReceivable ? 'DP harus lebih kecil dari total tagihan.' : 'Nominal bayar harus cukup sebelum konfirmasi.')
                                        : 'Akun pembayaran belum disiapkan.')
                        : (isReceivable
                            ? `Sisa ${formatCurrency(remaining)} akan tersimpan sebagai piutang.`
                            : `Siap konfirmasi pembayaran ${state.method === 'cash' ? 'tunai' : state.method === 'qris' ? 'QRIS' : 'bank transfer'}.`);
                };

                const renderDiscountSummary = () => {
                    const itemDiscountTotal = Number(snapshot?.item_discount_total || 0);
                    const transactionDiscountTotal = Number(snapshot?.transaction_discount_total || 0);
                    const visible = itemDiscountTotal > 0 || transactionDiscountTotal > 0;
                    els.discountSummary.classList.toggle('hidden', !visible);
                    if (!visible) {
                        return;
                    }
                    els.itemDiscount.textContent = formatCurrency(itemDiscountTotal);
                    els.transactionDiscount.textContent = formatCurrency(transactionDiscountTotal);
                };

                const saveOrderForLater = () => {
                    if (!snapshot) {
                        showError('Data pembayaran tidak ditemukan.');
                        return;
                    }

                    const selectedCustomerId = String(els.saveOrderCustomer?.value || snapshot.customer_id || '').trim() || null;
                    const selectedCustomer = state.customers.find((entry) => String(entry.id) === String(selectedCustomerId || ''));
                    const normalizedHolderInput = String(els.saveOrderHolder?.value || '').trim();
                    const normalizedHolder = normalizedHolderInput || String(selectedCustomer?.name || snapshot.order_holder_name || '').trim();
                    if (!normalizedHolder) {
                        setSaveOrderFeedback('Pilih pelanggan atau isi atas nama untuk simpan pesanan.', 'error');
                        if (els.saveOrderCustomer && !selectedCustomerId) {
                            els.saveOrderCustomer.focus();
                        } else {
                            els.saveOrderHolder?.focus();
                        }
                        return;
                    }

                    if (els.saveOrderHolder && normalizedHolder !== normalizedHolderInput) {
                        els.saveOrderHolder.value = normalizedHolder;
                    }

                    const normalizedNote = String(els.saveOrderNote?.value || '').trim();
                    const savedOrderId = snapshot.saved_order_id || null;

                    const savedOrder = {
                        id: savedOrderId || createLocalId(),
                        holder_name: normalizedHolder,
                        note: normalizedNote,
                        items: Array.isArray(snapshot.checkout_items)
                            ? snapshot.checkout_items.map((item) => ({
                                product_id: item.product_id,
                                qty: Number(item.qty || 1),
                                note: String(item.note || '').trim(),
                                discounts: Array.isArray(item.discounts) ? item.discounts : [],
                            }))
                            : [],
                        fees: Array.isArray(snapshot.fees) ? snapshot.fees : [],
                        selected_promos: Array.isArray(snapshot.selected_promos) ? snapshot.selected_promos : [],
                        transaction_discounts: Array.isArray(snapshot.transaction_discounts) ? snapshot.transaction_discounts : [],
                        customer_id: selectedCustomerId,
                        location_id: snapshot.location_id || null,
                        total: Number(snapshot.total || 0),
                        item_count: Array.isArray(snapshot.items)
                            ? snapshot.items.reduce((sum, item) => sum + Number(item.qty || 0), 0)
                            : 0,
                        created_at: new Date().toISOString(),
                    };

                    const apiPayload = {
                        branch_id: snapshot.branch_id || null,
                        location_id: snapshot.location_id || null,
                        shift_id: snapshot.shift_id || null,
                        register_id: snapshot.register_id || null,
                        customer_id: selectedCustomerId,
                        holder_name: savedOrder.holder_name,
                        note: savedOrder.note,
                        items: savedOrder.items,
                        fees: savedOrder.fees,
                        selected_promos: savedOrder.selected_promos,
                        transaction_discounts: savedOrder.transaction_discounts,
                        total: savedOrder.total,
                        item_count: Math.max(1, savedOrder.item_count || 0),
                    };

                    let existing = [];
                    try {
                        const raw = localStorage.getItem(savedOrdersStorageKey);
                        const parsed = raw ? JSON.parse(raw) : [];
                        existing = Array.isArray(parsed) ? parsed : [];
                    } catch (_) {
                        existing = [];
                    }

                    window.PosUi.runWithSubmitGuard(els.saveOrderFromPayment, async () => {
                        try {
                            const created = await saveOrderToApi(apiPayload, savedOrderId);
                            existing = [created || savedOrder, ...existing.filter((entry) => String(entry.id) !== String(savedOrder.id))];
                        } catch (_) {
                            existing = [savedOrder, ...existing.filter((entry) => String(entry.id) !== String(savedOrder.id))];
                        }

                        localStorage.setItem(savedOrdersStorageKey, JSON.stringify(existing));
                        sessionStorage.removeItem('pos_payment');
                        closeSaveOrderModal();
                        window.location.href = @json($paymentBackUrl);
                    }).catch((error) => {
                        setSaveOrderFeedback(error.message || 'Gagal menyimpan pesanan.', 'error');
                    });
                };

                const appendDigit = (value) => {
                    showError('');
                    state.paidInput = `${state.paidInput}${value}`.replace(/^0+(\d)/, '$1');
                    updateAmounts();
                };

                const backspace = () => {
                    showError('');
                    state.paidInput = state.paidInput.slice(0, -1);
                    updateAmounts();
                };

                const setPaid = (value) => {
                    showError('');
                    state.paidInput = String(Math.max(0, Math.floor(value)));
                    updateAmounts();
                };

                const formatQuickAmountLabel = (value) => {
                    if (value >= 1000000) {
                        return `Rp ${Math.round(value / 1000000)}jt`;
                    }

                    if (value >= 1000) {
                        return `Rp ${Math.round(value / 1000)}rb`;
                    }

                    return formatCurrency(value);
                };

                const renderQuickAmounts = () => {
                    const total = Math.max(0, Math.floor(state.total || 0));
                    const candidates = [
                        Math.floor(total * 0.25),
                        Math.floor(total * 0.5),
                        total,
                        total + 5000,
                        total + 10000,
                        50000,
                        100000,
                        200000,
                        500000,
                        Math.ceil(total / 50000) * 50000,
                        Math.ceil(total / 100000) * 100000,
                    ].filter((value) => Number.isFinite(value) && value > 0);

                    const unique = [...new Set(candidates)];
                    const sorted = unique.sort((a, b) => a - b);

                    els.quickAmounts.innerHTML = '';

                    sorted.forEach((amount) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'quick-amount px-4 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 whitespace-nowrap';
                        button.dataset.amount = String(amount);
                        button.textContent = formatQuickAmountLabel(amount);
                        button.addEventListener('click', () => setPaid(amount));
                        els.quickAmounts.appendChild(button);
                    });

                    const exactButton = document.createElement('button');
                    exactButton.type = 'button';
                    exactButton.className = 'quick-amount px-4 py-2 bg-primary/10 border border-primary/20 rounded-lg text-xs font-bold text-primary whitespace-nowrap';
                    exactButton.dataset.amount = 'exact';
                    exactButton.textContent = 'Uang Pas';
                    exactButton.addEventListener('click', () => setPaid(total));
                    els.quickAmounts.appendChild(exactButton);
                };

                if (snapshot) {
                    syncDraftFromSnapshot(snapshot);
                    setPaid(state.total);
                    if (els.editItemsButton) {
                        els.editItemsButton.classList.remove('hidden');
                    }
                } else {
                    els.snapshotGuard.classList.remove('hidden');
                    els.hint.textContent = 'Data checkout belum tersedia. Kembali ke checkout untuk melanjutkan.';
                    updateAmounts();
                }

                renderQuickAmounts();
                renderDiscountSummary();

                document.querySelectorAll('[data-payment-method]').forEach((card) => {
                    card.addEventListener('click', () => {
                        const method = card.dataset.paymentMethod;
                        setMethod(method);
                        if (method === 'receivable') {
                            openReceivableModal();
                        }
                    });
                });

                if (state.bankAccounts.length) {
                    renderBankAccounts();
                } else {
                    loadBankAccounts()
                        .then((data) => {
                            state.bankAccounts = data.accounts || [];
                            state.selectedBankId = state.bankAccounts[0]?.id || null;
                            renderBankAccounts();
                        })
                        .catch(() => {
                            fallbackLoadBankAccounts()
                                .then((accounts) => {
                                    state.bankAccounts = accounts || [];
                                    state.selectedBankId = state.bankAccounts[0]?.id || null;
                                    renderBankAccounts();
                                })
                                .catch(() => {
                                    els.bankEmpty.textContent = 'Gagal memuat akun bank.';
                                    els.bankEmpty.classList.remove('hidden');
                                });
                        });
                }

                document.querySelectorAll('.numpad-btn').forEach((button) => {
                    button.addEventListener('click', () => {
                        const label = button.textContent.trim();
                        if (label === '') {
                            return;
                        }
                        if (label === 'backspace') {
                            backspace();
                            return;
                        }
                        if (label === '000') {
                            appendDigit('000');
                            return;
                        }
                        if (/^\d$/.test(label)) {
                            appendDigit(label);
                        }
                    });
                });

                els.reset.addEventListener('click', () => {
                    state.paidInput = '';
                    updateAmounts();
                });

                els.editItemsButton?.addEventListener('click', () => {
                    if (!snapshot) {
                        return;
                    }
                    syncDraftFromSnapshot(snapshot);
                    sessionStorage.setItem('pos_return_to_cart', '1');
                    window.location.href = @json($paymentBackUrl);
                });

                const submitPayment = async ({ triggerButton = els.confirm, skipConfirmation = false, errorTarget = 'page' } = {}) => {
                    const useReceivableFeedback = errorTarget === 'receivable';
                    const clearCheckoutError = () => {
                        if (useReceivableFeedback) {
                            setReceivableFeedback('');
                            return;
                        }

                        showError('');
                    };
                    const reportCheckoutError = (message) => {
                        if (useReceivableFeedback) {
                            setReceivableFeedback(message, 'error');
                            return;
                        }

                        showError(message);
                    };

                    clearCheckoutError();
                    if (!snapshot) {
                        reportCheckoutError('Data pembayaran tidak ditemukan.');
                        return;
                    }

                    const isReceivable = state.method === 'receivable';
                    state.total = parseAmount(snapshot?.total, state.total || parseAmount(els.total?.textContent));
                    const paid = isReceivable
                        ? Math.max(0, Math.floor(parseAmount(state.receivable.dpAmount)))
                        : parseAmount(state.paidInput);

                    if (isReceivable && !state.receivable.configured) {
                        await openReceivableModal();
                        return;
                    }

                    if (isReceivable && !state.receivable.customerId) {
                        reportCheckoutError('Pelanggan wajib dipilih untuk transaksi piutang.');
                        return;
                    }

                    if (isReceivable && !state.receivable.dueDate) {
                        reportCheckoutError('Tanggal jatuh tempo wajib diisi.');
                        return;
                    }

                    if (isReceivable && paid >= state.total) {
                        reportCheckoutError('DP harus lebih kecil dari total tagihan.');
                        return;
                    }

                    if (!isReceivable && paid < state.total) {
                        reportCheckoutError('Jumlah bayar kurang dari total.');
                        return;
                    }

                    const selectedConfig = methodConfig[state.method] || methodConfig.cash;
                    const accountId = isReceivable ? null : accountIdForPaymentMethod(state.method);
                    if (!isReceivable && !accountId) {
                        reportCheckoutError(state.method === 'cash' ? 'Akun kas belum disiapkan.' : 'Akun bank belum disiapkan.');
                        return;
                    }

                    const receivableDpMethod = state.receivable.dpMethod || 'cash';
                    const receivableDpAccountId = isReceivable && paid > 0
                        ? accountIdForPaymentMethod(receivableDpMethod)
                        : null;
                    if (isReceivable && paid > 0 && !receivableDpAccountId) {
                        reportCheckoutError('Akun untuk metode DP belum disiapkan.');
                        return;
                    }

                    if (!skipConfirmation) {
                        const allowed = await window.PosUi.confirmRiskActionAsync({
                            title: isReceivable ? 'Konfirmasi Piutang' : 'Konfirmasi Pembayaran',
                            message: isReceivable
                                ? 'Sisa tagihan akan disimpan sebagai piutang pelanggan.'
                                : 'Pastikan nominal bayar dan kembalian sudah benar.',
                            confirmText: 'Proses',
                            icon: isReceivable ? 'receipt_long' : 'payments',
                        });
                        if (!allowed) {
                            return;
                        }
                    }

                    const payments = isReceivable
                        ? (paid > 0 ? [{
                            account_id: receivableDpAccountId,
                            method: receivableDpMethod,
                            amount: paid,
                        }] : [])
                        : [{
                            account_id: accountId,
                            method: selectedConfig.method,
                            amount: state.total,
                        }];

                    const payload = {
                        idempotency_key: snapshot.idempotency_key || `${Date.now()}-${Math.random()}`,
                        branch_id: snapshot.branch_id,
                        shift_id: snapshot.shift_id,
                        register_id: snapshot.register_id,
                        customer_id: isReceivable ? state.receivable.customerId : (snapshot.customer_id || null),
                        order_holder_name: snapshot.order_holder_name || null,
                        order_note: isReceivable ? (state.receivable.note || null) : (snapshot.order_note || null),
                        payment_mode: isReceivable ? 'receivable' : 'full',
                        due_date: isReceivable ? state.receivable.dueDate : null,
                        saved_order_id: snapshot.saved_order_id || null,
                        replace_sale_id: snapshot.replace_sale_id || null,
                        transaction_date: snapshot.transaction_date || null,
                        subtotal: snapshot.subtotal,
                        discount: snapshot.discount || 0,
                        tax: snapshot.tax,
                        total: state.total,
                        items: snapshot.checkout_items || [],
                        transaction_discounts: snapshot.transaction_discounts || [],
                        payments,
                    };

                    await window.PosUi.runWithSubmitGuard(triggerButton, async () => {
                        try {
                            const response = await fetchWriteWithFreshCsrf('/api/v1/checkout', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify(payload),
                            }, 15000);

                            if (!response.ok) {
                                const mapped = await window.PosUi.mapApiError(response, 'Checkout gagal.');
                                throw new Error(mapped.message);
                            }

                            const result = await response.json().catch(() => ({}));
                            const saleId = result?.sale_id || null;
                            const receipt = {
                        ...snapshot,
                        method: isReceivable ? 'receivable' : selectedConfig.method,
                        paid,
                        change: isReceivable ? 0 : Math.max(paid - state.total, 0),
                                invoice: saleId,
                                receivable_id: result?.receivable_id || null,
                                payment_status: result?.payment_status || (isReceivable ? (paid > 0 ? 'partial' : 'unpaid') : 'paid'),
                                due_date: payload.due_date,
                                createdAt: new Date().toISOString(),
                            };
                            sessionStorage.setItem('pos_receipt', JSON.stringify(receipt));
                            sessionStorage.removeItem('pos_payment');
                            sessionStorage.removeItem('pos_cart_draft');
                            const receiptParams = new URLSearchParams({
                                from: @json($paymentCurrentPath),
                            });
                            window.location.href = saleId
                                ? `/pos/sales/${saleId}/receipt?${receiptParams.toString()}`
                                : `${@json($paymentReceiptFallbackUrl)}?${receiptParams.toString()}`;
                        } catch (error) {
                            if (error?.name === 'AbortError') {
                                reportCheckoutError('Permintaan timeout. Cek riwayat transaksi sebelum ulang checkout.');
                                return;
                            }
                            reportCheckoutError(error.message || 'Checkout gagal.');
                        }
                    });
                };

                els.confirm.addEventListener('click', () => submitPayment());

                els.saveOrderFromPayment?.addEventListener('click', openSaveOrderModal);
                els.saveOrderClose?.addEventListener('click', closeSaveOrderModal);
                els.saveOrderCancel?.addEventListener('click', closeSaveOrderModal);
                els.saveOrderOverlay?.addEventListener('click', closeSaveOrderModal);
                els.saveOrderCustomer?.addEventListener('change', syncHolderNameFromCustomer);
                els.saveOrderCustomerCreate?.addEventListener('click', () => openCustomerModal('save-order'));
                els.saveOrderSubmit?.addEventListener('click', saveOrderForLater);
                els.receivableClose?.addEventListener('click', () => closeReceivableModal(true));
                els.receivableCancel?.addEventListener('click', () => closeReceivableModal(true));
                els.receivableOverlay?.addEventListener('click', () => closeReceivableModal(true));
                els.receivableCustomerCreate?.addEventListener('click', () => openCustomerModal('receivable'));
                els.receivableApply?.addEventListener('click', applyReceivableDetails);
                els.receivableDpNone?.addEventListener('click', () => setReceivableDpMode(false));
                els.receivableDpSome?.addEventListener('click', () => setReceivableDpMode(true));
                els.receivableDpAmount?.addEventListener('input', updateReceivablePreview);
                els.receivableDays?.addEventListener('change', () => {
                    syncReceivableDateFromDays();
                    updateReceivablePreview();
                });
                els.receivableDueDate?.addEventListener('change', () => {
                    syncReceivableDaysFromDate();
                    updateReceivablePreview();
                });
                document.querySelectorAll('.receivable-dp-method').forEach((button) => {
                    button.addEventListener('click', () => setReceivableDpMethod(button.dataset.dpMethod));
                });
                els.customerModalClose?.addEventListener('click', closeCustomerModal);
                els.customerModalCancel?.addEventListener('click', closeCustomerModal);
                els.customerModal?.addEventListener('click', (event) => {
                    if (event.target === els.customerModal) {
                        closeCustomerModal();
                    }
                });
                els.customerSaveButton?.addEventListener('click', async () => {
                    await window.PosUi.runWithSubmitGuard(els.customerSaveButton, async () => {
                        setCustomerModalFeedback('');
                        const created = await createCustomer();
                        if (!created) {
                            throw new Error('Pelanggan baru tidak ditemukan setelah disimpan.');
                        }

                        selectCreatedCustomer(created, els.customerModal?.dataset.source || 'receivable');
                        setCustomerModalFeedback('Pelanggan berhasil ditambahkan.', 'success');
                        setTimeout(() => {
                            closeCustomerModal();
                        }, 400);
                    }, {
                        loadingHtml: '<span class="material-symbols-outlined text-lg">progress_activity</span> Menyimpan...',
                    }).catch((error) => {
                        setCustomerModalFeedback(error.message || 'Gagal menyimpan pelanggan.', 'error');
                    });
                });

                setMethod(state.method);
            })();
        </script>
@endsection
