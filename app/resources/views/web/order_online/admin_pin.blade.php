@extends('layouts.app')

@section('page_title', 'Admin Order')

@section('ui_mode', 'public')

@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white shadow-2xl')

@section('header')
<header class="sticky top-0 z-30 border-b border-orange-100 bg-white/95 backdrop-blur-xl">
    <div class="flex items-center gap-3 px-4 h-14">
        <a href="{{ url('/monitor/orders') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-orange-50 text-orange-700 transition hover:bg-orange-100">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        </a>
        <div class="min-w-0">
            <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-orange-500">Admin order</p>
            <h1 class="truncate text-sm font-bold">{{ $order->order_code }}</h1>
        </div>
    </div>
</header>
@endsection

@section('main_class', 'flex-1 overflow-y-auto bg-[linear-gradient(to_bottom,#fff7ed_0%,#ffffff_34%)]')

@section('content')
@php
    $isVerified = (bool) ($adminVerified ?? false);
    $hasShipping = filled($order->shipping_filled_at) || ((float) $order->shipping_cost > 0) || filled($order->shipping_provider);
    $hasPayment = filled($order->payment_verified_at);
    $hasReceipt = filled($order->sale_id);
    $isCompleted = filled($order->completed_at) || $order->status === 'selesai';
    $isOverride = (bool) ($adminOverride ?? false);
    $orderLocked = (bool) ($orderLocked ?? false);
    $canCompleteOrder = $hasPayment || $hasReceipt || in_array($order->status, ['pembayaran_terverifikasi', 'diproses', 'siap_diambil', 'siap_dikirim'], true);
    $saleReceiptUrl = $hasReceipt ? url('/pos/sales/' . $order->sale_id . '/receipt') : null;
    $fulfillmentLabel = $order->fulfillment_type === 'delivery' ? 'Diantar' : 'Ambil di toko';
    $effectiveStatus = filled($order->cancelled_at) || $order->status === 'dibatalkan'
        ? 'dibatalkan'
        : (filled($order->completed_at) || $order->status === 'selesai' ? 'selesai' : (string) $order->status);
    $statusLabel = str_replace('_', ' ', strtoupper($effectiveStatus));
    if ($orderLocked) {
        $nextLabel = 'Order selesai terkunci';
    } elseif ($isOverride && $isCompleted) {
        $nextLabel = 'Akses khusus aktif';
    } elseif (! $isVerified) {
        $nextLabel = 'Verifikasi PIN';
    } elseif (! $hasShipping) {
        $nextLabel = 'Isi ongkir';
    } elseif (! $hasPayment) {
        $nextLabel = 'Final review dan ACC';
    } else {
        $nextLabel = $isCompleted ? 'Order selesai' : 'Tandai selesai';
    }
@endphp

<div class="space-y-4 px-4 py-4">
    @if(session('status'))
    <section class="rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 shadow-sm">
        {{ session('status') }}
    </section>
    @endif

    @if(session('error') || $errors->has('order_locked'))
    <section class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 shadow-sm">
        {{ session('error') ?: $errors->first('order_locked') }}
    </section>
    @endif

    <section class="rounded-3xl border border-orange-100 bg-white p-4 shadow-sm shadow-orange-100/50">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-orange-500">Ringkasan</p>
                <h2 class="mt-1 truncate text-2xl font-black tracking-tight text-slate-950">{{ $order->order_code }}</h2>
                <p class="mt-1 text-xs font-semibold text-slate-500">{{ $fulfillmentLabel }}{{ $order->fulfillment_time ? ' - ' . $order->fulfillment_time : '' }}</p>
            </div>
            <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold text-slate-700">{{ $statusLabel }}</span>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2">
            <div class="rounded-2xl bg-slate-50 p-3">
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Pemesan</p>
                <p class="mt-1 truncate text-sm font-bold text-slate-900">{{ $order->customer_name }}</p>
                <p class="mt-0.5 truncate text-[11px] text-slate-500">{{ $order->customer_phone }}</p>
            </div>
            <div class="rounded-2xl bg-orange-50 p-3 text-right">
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-orange-500">Total</p>
                <p class="mt-1 text-lg font-black text-orange-600">Rp {{ number_format((float) $order->grand_total, 0, ',', '.') }}</p>
                <p class="mt-0.5 text-[11px] text-orange-700">{{ ($orderItems ?? collect())->count() }} item</p>
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
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-slate-400">Langkah berikutnya</p>
                <h3 class="mt-1 text-base font-black text-slate-950">{{ $nextLabel }}</h3>
            </div>
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $orderLocked ? 'bg-red-50 text-red-600 ring-1 ring-red-100' : ($isVerified ? 'bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100' : 'bg-orange-50 text-orange-600 ring-1 ring-orange-100') }}">
                <span class="material-symbols-outlined text-[23px]">{{ $orderLocked ? 'lock' : ($isVerified ? 'verified' : 'lock') }}</span>
            </span>
        </div>

        @if($orderLocked)
        <div class="mt-4 rounded-2xl border border-red-100 bg-red-50 p-3 text-xs leading-relaxed text-red-700">
            Order sudah selesai, jadi data pesanan, barang, ongkir, pembayaran, dan pembatalan dikunci. Gunakan akses khusus hanya untuk koreksi darurat.
        </div>

        <form method="POST" action="{{ url('/admin-order/' . $orderCode . '/override') }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">PIN akses khusus</label>
                <input
                    type="password"
                    name="override_pin"
                    inputmode="numeric"
                    maxlength="6"
                    required
                    placeholder="PIN owner/admin"
                    class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-center text-lg font-black outline-none transition focus:border-red-300 focus:ring-4 focus:ring-red-100"
                />
                @error('override_pin') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Alasan koreksi</label>
                <textarea name="override_reason" rows="2" required placeholder="Contoh: koreksi ongkir setelah konfirmasi owner" class="w-full resize-none rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-red-300 focus:ring-4 focus:ring-red-100">{{ old('override_reason') }}</textarea>
                @error('override_reason') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-red-600 text-sm font-bold text-white shadow-lg shadow-red-500/20 transition hover:bg-red-700">
                <span class="material-symbols-outlined text-[20px]">lock_open</span>
                Buka akses khusus
            </button>
        </form>

        <div class="mt-3 grid gap-2">
            <a href="{{ url('/admin-order/' . $orderCode . '/final-review') }}" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100">
                <span>Lihat final review</span>
                <span class="material-symbols-outlined text-[19px] text-slate-600">visibility</span>
            </a>
            @if($saleReceiptUrl)
            <a href="{{ $saleReceiptUrl }}" target="_blank" rel="noopener noreferrer" class="flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-900 transition hover:bg-emerald-100">
                <span>Cetak nota kasir</span>
                <span class="material-symbols-outlined text-[19px] text-emerald-700">print</span>
            </a>
            @endif
        </div>
        @elseif(! $isVerified)
        @if($errors->any())
        <div class="mt-3 rounded-2xl border border-red-200 bg-red-50 p-3 text-xs text-red-600">
            {{ $errors->first('admin_pin') }}
        </div>
        @endif

        <form method="POST" action="{{ url('/admin-order/' . $orderCode . '/pin') }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">PIN Admin</label>
                <input
                    type="password"
                    name="admin_pin"
                    inputmode="numeric"
                    maxlength="6"
                    required
                    placeholder="Masukkan PIN"
                    class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-center text-lg font-black outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100"
                    autofocus
                />
            </div>
            <button type="submit" class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-orange-500 text-sm font-bold text-white shadow-lg shadow-orange-500/20 transition hover:bg-orange-600 active:scale-[0.98]">
                <span class="material-symbols-outlined text-[20px]">lock_open</span>
                Buka akses admin
            </button>
        </form>
        @else
        <div class="mt-4 grid grid-cols-2 gap-2">
            <a href="{{ url('/admin-order/' . $orderCode . '/edit') }}" class="flex min-h-[74px] flex-col justify-between rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm font-bold text-slate-900 transition hover:bg-slate-100">
                <span class="material-symbols-outlined text-[22px] text-orange-600">edit</span>
                Edit data
            </a>
            <a href="{{ url('/admin-order/' . $orderCode . '/shipping') }}" class="flex min-h-[74px] flex-col justify-between rounded-2xl border {{ $hasShipping ? 'border-slate-200 bg-slate-50' : 'border-orange-200 bg-orange-50' }} p-3 text-sm font-bold text-slate-900 transition hover:bg-orange-50">
                <span class="material-symbols-outlined text-[22px] text-orange-600">local_shipping</span>
                Isi ongkir
            </a>
            <a href="{{ url('/admin-order/' . $orderCode . '/final-review') }}" class="col-span-2 flex items-center justify-between rounded-2xl bg-slate-950 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800">
                <span>Final review dan ACC pembayaran</span>
                <span class="material-symbols-outlined text-[19px]">arrow_forward</span>
            </a>
            @if($saleReceiptUrl)
            <a href="{{ $saleReceiptUrl }}" target="_blank" rel="noopener noreferrer" class="col-span-2 flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-900 transition hover:bg-emerald-100">
                <span>Cetak nota kasir</span>
                <span class="material-symbols-outlined text-[19px] text-emerald-700">print</span>
            </a>
            @endif
        </div>

        @if($isOverride && $isCompleted)
        <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-xs font-semibold text-amber-800">
            Akses khusus aktif. Semua koreksi setelah order selesai harus dilakukan dengan hati-hati karena mempengaruhi kasir, stok, dan retur.
        </div>
        @endif

        @if(! $isCompleted)
        <div class="mt-3 grid gap-2">
            <form method="POST" action="{{ url('/admin-order/' . $orderCode . '/complete') }}">
                @csrf
                <button type="submit" @disabled(! $canCompleteOrder) class="flex w-full items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-left text-sm font-bold text-emerald-900 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60">
                    <span>Tandai selesai</span>
                    <span class="material-symbols-outlined text-[19px] text-emerald-700">task_alt</span>
                </button>
            </form>
            @unless($canCompleteOrder)
            <p class="px-1 text-[11px] leading-relaxed text-slate-500">Tombol selesai aktif setelah pembayaran di-ACC atau nota kasir sudah terbentuk.</p>
            @endunless
        </div>
        @endif
        @endif
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-slate-400">Status kerja</p>
        <div class="mt-3 space-y-2">
            <div class="flex items-center gap-3 rounded-2xl bg-slate-50 px-3 py-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-full {{ $isVerified ? 'bg-emerald-600 text-white' : 'bg-white text-slate-400 ring-1 ring-slate-200' }}"><span class="material-symbols-outlined text-[18px]">lock_open</span></span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold text-slate-900">PIN admin</p>
                    <p class="text-[11px] text-slate-500">{{ $isVerified ? 'Terverifikasi' : 'Belum diverifikasi' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-2xl bg-slate-50 px-3 py-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-full {{ $hasShipping ? 'bg-emerald-600 text-white' : 'bg-white text-slate-400 ring-1 ring-slate-200' }}"><span class="material-symbols-outlined text-[18px]">local_shipping</span></span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold text-slate-900">Ongkir dan biaya</p>
                    <p class="text-[11px] text-slate-500">{{ $hasShipping ? 'Sudah diisi' : 'Belum diisi' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-2xl bg-slate-50 px-3 py-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-full {{ $hasPayment ? 'bg-emerald-600 text-white' : 'bg-white text-slate-400 ring-1 ring-slate-200' }}"><span class="material-symbols-outlined text-[18px]">paid</span></span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold text-slate-900">Pembayaran</p>
                    <p class="text-[11px] text-slate-500">{{ $hasPayment ? 'Sudah di-ACC' : 'Belum di-ACC' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-2xl bg-slate-50 px-3 py-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-full {{ $hasReceipt ? 'bg-emerald-600 text-white' : 'bg-white text-slate-400 ring-1 ring-slate-200' }}"><span class="material-symbols-outlined text-[18px]">receipt_long</span></span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold text-slate-900">Nota kasir</p>
                    <p class="text-[11px] text-slate-500">{{ $hasReceipt ? 'Sudah tercatat di kasir' : 'Belum tercatat' }}</p>
                </div>
            </div>
        </div>
    </section>

    @if($isVerified && ! $orderLocked)
    <form id="cancel-order-form" method="POST" action="{{ url('/admin-order/' . $orderCode . '/cancel') }}">
        @csrf
        <button type="submit" class="flex w-full items-center justify-between rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-left text-sm font-bold text-red-800 shadow-sm shadow-red-100 transition hover:bg-red-100">
            <span>Batalkan pesanan</span>
            <span class="material-symbols-outlined text-[19px] text-red-600">cancel</span>
        </button>
    </form>
    @endif
</div>

@if($isVerified)
<script>
(function(){
    const form = document.getElementById('cancel-order-form');
    if (!form) return;
    form.addEventListener('submit', (event) => {
        if (!confirm('Batalkan pesanan ini?')) {
            event.preventDefault();
        }
    });
})();
</script>
@endif
@endsection
