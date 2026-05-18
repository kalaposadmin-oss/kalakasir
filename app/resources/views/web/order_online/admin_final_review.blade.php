@extends('layouts.app')

@section('page_title', 'Final Review Pesanan')

@section('ui_mode', 'public')

@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white shadow-2xl')

@section('header')
<header class="sticky top-0 z-30 border-b border-orange-100 bg-white/95 backdrop-blur-xl">
    <div class="flex items-center gap-3 px-4 h-14">
        <a href="{{ url('/admin-order/' . $orderCode) }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-orange-50 text-orange-700 transition hover:bg-orange-100">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        </a>
        <div class="min-w-0">
            <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-orange-500">Final review</p>
            <h1 class="truncate text-sm font-bold">{{ $order->order_code }}</h1>
        </div>
    </div>
</header>
@endsection

@section('main_class', 'flex-1 overflow-y-auto pb-8 bg-[linear-gradient(to_bottom,#fff7ed_0%,#ffffff_34%)]')

@section('content')
@php
    $effectiveStatus = filled($order->cancelled_at) || $order->status === 'dibatalkan'
        ? 'dibatalkan'
        : (filled($order->completed_at) || $order->status === 'selesai' ? 'selesai' : (string) $order->status);
    $statusLabel = str_replace('_', ' ', strtoupper($effectiveStatus));
    $fulfillmentLabel = $order->fulfillment_type === 'delivery' ? 'Diantar' : 'Ambil di toko';
    $hasPayment = filled($order->payment_verified_at);
    $hasReceipt = filled($order->sale_id);
    $isCompleted = filled($order->completed_at) || $order->status === 'selesai';
    $isOverride = (bool) ($adminOverride ?? false);
    $orderLocked = (bool) ($orderLocked ?? false);
    $canCompleteOrder = ! $isCompleted && in_array($order->status, ['pembayaran_terverifikasi', 'diproses', 'siap_diambil', 'siap_dikirim'], true);
    $accountsEmpty = ($paymentAccounts ?? collect())->isEmpty();
@endphp

<div class="space-y-4 px-4 py-4">
    @if(session('status'))
    <section class="rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 shadow-sm">
        {{ session('status') }}
    </section>
    @endif

    @if(session('error'))
    <section class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 shadow-sm">
        {{ session('error') }}
    </section>
    @endif

    @if($errors->has('order_locked'))
    <section class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 shadow-sm">
        {{ $errors->first('order_locked') }}
    </section>
    @endif

    @if($orderLocked)
    <section class="rounded-3xl border border-red-100 bg-red-50 p-4 shadow-sm shadow-red-100/50">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-red-600 ring-1 ring-red-100">
                <span class="material-symbols-outlined text-[22px]">lock</span>
            </span>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-red-600">Order terkunci</p>
                <h3 class="mt-1 text-sm font-black text-red-950">Selesai berarti read-only.</h3>
                <p class="mt-1 text-xs leading-relaxed text-red-700">Edit data, ongkir, ACC ulang, kirim rekap pembayaran, dan batal order diblokir agar stok, kasir, dan retur tidak berubah diam-diam.</p>
            </div>
        </div>

        <form method="POST" action="{{ url('/admin-order/' . $orderCode . '/override') }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-red-500">PIN akses khusus</label>
                <input name="override_pin" type="password" inputmode="numeric" maxlength="6" required placeholder="PIN owner/admin" class="h-12 w-full rounded-2xl border border-red-100 bg-white px-4 text-center text-lg font-black outline-none transition focus:border-red-300 focus:ring-4 focus:ring-red-100" />
                @error('override_pin') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-red-500">Alasan koreksi</label>
                <textarea name="override_reason" rows="2" required placeholder="Contoh: koreksi transfer setelah konfirmasi owner" class="w-full resize-none rounded-2xl border border-red-100 bg-white px-4 py-3 text-sm outline-none transition focus:border-red-300 focus:ring-4 focus:ring-red-100">{{ old('override_reason') }}</textarea>
                @error('override_reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-red-600 text-sm font-bold text-white shadow-lg shadow-red-500/20 transition hover:bg-red-700">
                <span class="material-symbols-outlined text-[20px]">lock_open</span>
                Buka akses khusus
            </button>
        </form>
    </section>
    @elseif($isOverride && $isCompleted)
    <section class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold leading-relaxed text-amber-800 shadow-sm">
        Akses khusus aktif untuk order selesai. Koreksi setelah titik ini harus sesuai bukti transaksi.
    </section>
    @endif

    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-orange-500">Cek akhir</p>
                <h2 class="mt-1 truncate text-2xl font-black tracking-tight text-slate-950">{{ $order->order_code }}</h2>
                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $fulfillmentLabel }}{{ $order->fulfillment_time ? ' - ' . $order->fulfillment_time : '' }}</p>
            </div>
            <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold text-slate-700">{{ $statusLabel }}</span>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2">
            <div class="rounded-2xl bg-slate-50 p-3">
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Pembeli</p>
                <p class="mt-1 truncate text-sm font-bold text-slate-900">{{ $order->customer_name }}</p>
                <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ $order->customer_phone }}</p>
            </div>
            <div class="rounded-2xl bg-orange-50 p-3 text-right">
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-orange-500">Total bayar</p>
                <p class="mt-1 text-lg font-black text-orange-600">Rp {{ number_format((float) $order->grand_total, 0, ',', '.') }}</p>
                <p class="mt-0.5 text-[11px] text-orange-700">{{ $hasPayment ? 'Sudah di-ACC' : 'Menunggu ACC' }}</p>
            </div>
        </div>

        <div class="mt-3 space-y-2">
            @foreach(($orderItems ?? collect()) as $item)
            <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-100 bg-white px-3 py-2 text-xs">
                <span class="min-w-0 flex-1 truncate">{{ (int) $item->qty }}x {{ $item->product_name }}</span>
                <span class="font-bold text-slate-900">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</span>
            </div>
            @endforeach
        </div>

        <div class="mt-3 rounded-2xl bg-slate-50 p-3 text-xs">
            <div class="flex justify-between gap-3">
                <span class="text-slate-500">Subtotal produk</span>
                <span class="font-bold text-slate-900">Rp {{ number_format((float) $order->subtotal, 0, ',', '.') }}</span>
            </div>
            <div class="mt-2 flex justify-between gap-3">
                <span class="text-slate-500">Ongkir</span>
                <span class="font-bold text-slate-900">Rp {{ number_format((float) $order->shipping_cost, 0, ',', '.') }}</span>
            </div>
            <div class="mt-2 flex justify-between gap-3">
                <span class="text-slate-500">Diskon ongkir</span>
                <span class="font-bold text-emerald-700">-Rp {{ number_format((float) $order->shipping_discount, 0, ',', '.') }}</span>
            </div>
            <div class="mt-2 flex justify-between gap-3">
                <span class="text-slate-500">Biaya tambahan</span>
                <span class="font-bold text-slate-900">Rp {{ number_format((float) $order->extra_fee, 0, ',', '.') }}</span>
            </div>
            <div class="mt-3 flex justify-between border-t border-dashed border-slate-200 pt-3">
                <span class="font-bold text-slate-900">Total final</span>
                <span class="font-black text-orange-600">Rp {{ number_format((float) $order->grand_total, 0, ',', '.') }}</span>
            </div>
        </div>
    </section>

    @if($paymentSetting ?? null)
    <section class="rounded-3xl border border-amber-100 bg-amber-50 p-4 shadow-sm shadow-amber-100/50">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-amber-600">Tujuan bayar</p>
                <h3 class="mt-1 text-sm font-black text-slate-950">{{ $paymentSetting->bank_name ?? 'Rekening' }}</h3>
            </div>
            <span class="material-symbols-outlined text-[24px] text-amber-600">account_balance</span>
        </div>
        <div class="mt-3 rounded-2xl bg-white p-3 text-xs">
            <div class="flex justify-between gap-3">
                <span class="text-slate-500">No. rekening</span>
                <span class="font-bold text-right text-slate-900">{{ $paymentSetting->bank_account_number ?? '-' }}</span>
            </div>
            <div class="mt-2 flex justify-between gap-3">
                <span class="text-slate-500">Atas nama</span>
                <span class="font-bold text-right text-slate-900">{{ $paymentSetting->bank_account_name ?? '-' }}</span>
            </div>
            @if($paymentSetting->payment_note ?? null)
            <p class="mt-3 border-t border-dashed border-slate-200 pt-3 text-[11px] leading-relaxed text-slate-600">{{ $paymentSetting->payment_note }}</p>
            @endif
            @if($paymentSetting->qris_image_url ?? null)
            <a href="{{ $paymentSetting->qris_image_url }}" target="_blank" rel="noopener noreferrer" class="mt-3 flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-900">
                <span>Lihat gambar QRIS</span>
                <span class="material-symbols-outlined text-[18px] text-amber-600">qr_code_2</span>
            </a>
            @endif
        </div>
    </section>
    @endif

    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-slate-400">WhatsApp pembeli</p>
        <h3 class="mt-1 text-base font-black text-slate-950">Kirim rekap final</h3>
        <div class="mt-3 grid gap-2">
            @if(($customerWaUrl ?? null) && ! $orderLocked)
            <a href="{{ $customerWaUrl }}" target="_blank" rel="noopener noreferrer" class="flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-900 transition hover:bg-emerald-100">
                <span>Buka WhatsApp pembeli</span>
                <span class="material-symbols-outlined text-[19px] text-emerald-700">open_in_new</span>
            </a>
            @endif
            @if($orderLocked)
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold text-slate-500">
                Pengiriman ulang rekap pembayaran dikunci untuk order selesai.
            </div>
            @else
            <form method="POST" action="{{ url('/admin-order/' . $orderCode . '/final-review/send-payment-message') }}">
                @csrf
                <button type="submit" class="flex w-full items-center justify-between rounded-2xl bg-slate-950 px-4 py-3 text-left text-sm font-bold text-white transition hover:bg-slate-800">
                    <span>Kirim template rekap final</span>
                    <span class="material-symbols-outlined text-[19px]">send</span>
                </button>
            </form>
            @endif
        </div>
    </section>

    <section class="rounded-3xl border {{ $hasPayment ? 'border-emerald-200 bg-emerald-50' : 'border-orange-100 bg-white' }} p-4 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] {{ $hasPayment ? 'text-emerald-600' : 'text-orange-500' }}">Dana masuk</p>
                <h3 class="mt-1 text-base font-black text-slate-950">{{ $hasPayment ? 'Pembayaran sudah di-ACC' : 'Pilih akun penerimaan' }}</h3>
            </div>
            <span class="material-symbols-outlined text-[24px] {{ $hasPayment ? 'text-emerald-600' : 'text-orange-600' }}">{{ $hasPayment ? 'verified' : 'account_balance_wallet' }}</span>
        </div>

        @if($hasPayment)
        <p class="mt-3 rounded-2xl bg-white px-3 py-2 text-xs font-semibold text-emerald-800">Order sudah masuk ke kasir dan jurnal penerimaan sesuai akun yang dipilih saat ACC.</p>
        @elseif($orderLocked)
        <p class="mt-3 rounded-2xl bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500">ACC pembayaran dikunci untuk order yang sudah selesai.</p>
        @else
        <form method="POST" action="{{ url('/admin-order/' . $orderCode . '/mark-paid') }}" class="mt-3 space-y-3">
            @csrf
            <div>
                <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400" for="payment-method">Metode</label>
                <select id="payment-method" name="payment_method" class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900 outline-none focus:border-orange-300 focus:ring-4 focus:ring-orange-100">
                    <option value="bank" @selected(old('payment_method', 'bank') === 'bank')>Transfer Bank</option>
                    <option value="qris" @selected(old('payment_method') === 'qris')>QRIS</option>
                    <option value="cash" @selected(old('payment_method') === 'cash')>Tunai</option>
                </select>
                @error('payment_method') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400" for="payment-account">Masuk ke akun</label>
                <select id="payment-account" name="payment_account_id" class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-bold text-slate-900 outline-none focus:border-orange-300 focus:ring-4 focus:ring-orange-100" @disabled($accountsEmpty)>
                    @forelse(($paymentAccounts ?? collect()) as $account)
                    <option value="{{ $account['id'] }}" @selected(old('payment_account_id', $defaultPaymentAccountId ?? '') === $account['id'])>{{ $account['label'] }}</option>
                    @empty
                    <option value="">Akun kas/bank belum tersedia</option>
                    @endforelse
                </select>
                @error('payment_account_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="mt-2 text-[11px] leading-relaxed {{ $accountsEmpty ? 'text-red-600' : 'text-slate-500' }}">
                    {{ $accountsEmpty ? 'Atur mapping akun kas/bank di backoffice dahulu.' : 'Akun ini dipakai untuk payment, jurnal, dan rekam transaksi kasir.' }}
                </p>
            </div>
            <button type="submit" @disabled($accountsEmpty) class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-orange-500 text-sm font-bold text-white shadow-lg shadow-orange-500/20 transition hover:bg-orange-600 disabled:cursor-not-allowed disabled:opacity-60">
                <span class="material-symbols-outlined text-[20px]">paid</span>
                ACC pembayaran
            </button>
        </form>
        @endif
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-slate-400">Aksi order</p>
        <div class="mt-3 grid grid-cols-2 gap-2">
            @if($orderLocked)
            <div class="flex min-h-[70px] flex-col justify-between rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm font-bold text-slate-400">
                <span class="material-symbols-outlined text-[21px] text-slate-300">edit_off</span>
                Edit terkunci
            </div>
            <div class="flex min-h-[70px] flex-col justify-between rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm font-bold text-slate-400">
                <span class="material-symbols-outlined text-[21px] text-slate-300">local_shipping</span>
                Ongkir terkunci
            </div>
            @else
            <a href="{{ url('/admin-order/' . $orderCode . '/edit') }}" class="flex min-h-[70px] flex-col justify-between rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100">
                <span class="material-symbols-outlined text-[21px] text-orange-600">edit</span>
                Edit data
            </a>
            <a href="{{ url('/admin-order/' . $orderCode . '/shipping') }}" class="flex min-h-[70px] flex-col justify-between rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100">
                <span class="material-symbols-outlined text-[21px] text-orange-600">local_shipping</span>
                Ongkir
            </a>
            @endif
            @if($saleReceiptUrl ?? null)
            <a href="{{ $saleReceiptUrl }}" target="_blank" rel="noopener noreferrer" class="flex min-h-[70px] flex-col justify-between rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-bold text-emerald-900 transition hover:bg-emerald-100">
                <span class="material-symbols-outlined text-[21px] text-emerald-700">print</span>
                Cetak nota
            </a>
            @endif
            <a href="{{ url('/monitor/orders') }}" class="flex min-h-[70px] flex-col justify-between rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100">
                <span class="material-symbols-outlined text-[21px] text-slate-600">view_agenda</span>
                Monitor
            </a>
        </div>

        <div class="mt-3 grid gap-2">
            <form method="POST" action="{{ url('/admin-order/' . $orderCode . '/complete') }}">
                @csrf
                <button type="submit" @disabled($orderLocked || ! $canCompleteOrder) class="flex w-full items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-left text-sm font-bold text-emerald-900 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60">
                    <span>{{ $isCompleted ? 'Pesanan sudah selesai' : 'Tandai selesai' }}</span>
                    <span class="material-symbols-outlined text-[19px] text-emerald-700">task_alt</span>
                </button>
            </form>
            @if(! $orderLocked)
            <form id="cancel-order-form" method="POST" action="{{ url('/admin-order/' . $orderCode . '/cancel') }}">
                @csrf
                <button id="cancel-order-btn" type="submit" class="flex w-full items-center justify-between rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-left text-sm font-bold text-red-800 transition hover:bg-red-100">
                    <span>Batalkan pesanan</span>
                    <span class="material-symbols-outlined text-[19px] text-red-600">cancel</span>
                </button>
            </form>
            @endif
        </div>
    </section>
</div>

<script>
(function(){
    const form = document.getElementById('cancel-order-form');
    const button = document.getElementById('cancel-order-btn');
    if (!form || !button) return;

    button.addEventListener('click', (event) => {
        if (!confirm('Batalkan pesanan ini?')) {
            event.preventDefault();
        }
    });
})();
</script>
@endsection
