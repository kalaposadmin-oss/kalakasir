@extends('layouts.app')

@section('page_title', 'Edit Data Pesanan')

@section('ui_mode', 'public')

@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white shadow-2xl')

@section('header')
<header class="sticky top-0 z-30 border-b border-orange-100 bg-white/95 backdrop-blur-xl">
    <div class="flex items-center gap-3 px-4 h-14">
        <a href="{{ url('/admin-order/' . $orderCode) }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-orange-50 text-orange-700 transition hover:bg-orange-100">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        </a>
        <div class="min-w-0">
            <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-orange-500">Admin flow</p>
            <h1 class="text-sm font-bold truncate">Edit Data Pesanan</h1>
        </div>
    </div>
</header>
@endsection

@section('main_class', 'flex-1 overflow-y-auto pb-6 bg-[radial-gradient(circle_at_top,_rgba(249,115,22,0.08),_transparent_36%),linear-gradient(to_bottom,#fff7ed_0%,#ffffff_28%)]')

@section('content')
<div class="px-4 py-4 space-y-4">
    <section class="rounded-3xl border border-orange-100 bg-white p-4 shadow-sm shadow-orange-100/50">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl bg-orange-50 ring-1 ring-orange-100">
                <span class="material-symbols-outlined text-[32px] text-orange-600">edit</span>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-orange-500">Ubah pesanan</p>
                <h2 class="mt-1 text-lg font-black text-slate-900">Edit data pelanggan dan catatan.</h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">Perubahan ini tidak mengubah daftar item, hanya data pesanan yang tampil di rekap final.</p>
            </div>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-[var(--app-muted)]">Kode pesanan</p>
                <p class="mt-1 text-2xl font-black tracking-tight text-slate-900">{{ $order->order_code }}</p>
            </div>
            <span class="rounded-full bg-orange-50 px-3 py-1 text-[10px] font-semibold text-orange-700">{{ strtoupper($order->status) }}</span>
        </div>

        <div class="mt-4 space-y-2">
            @foreach(($orderItems ?? collect()) as $item)
            <div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-3 py-2.5 text-xs">
                <span class="min-w-0 flex-1 pr-3">{{ (int) $item->qty }}x {{ $item->product_name }}</span>
                <span class="font-bold text-orange-600">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</span>
            </div>
            @endforeach
        </div>
    </section>

    <form method="POST" action="{{ url('/admin-order/' . $orderCode . '/edit') }}" class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60 space-y-4">
        @csrf
        <div>
            <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Nama Pemesan</label>
            <input name="customer_name" value="{{ old('customer_name', $order->customer_name) }}" type="text" required class="w-full h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100" />
        </div>
        <div>
            <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">No. WhatsApp</label>
            <input name="customer_phone" value="{{ old('customer_phone', $order->customer_phone) }}" type="text" required class="w-full h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100" />
        </div>
        <div>
            <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Metode</label>
            <select name="fulfillment_type" class="w-full h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100">
                <option value="pickup" @selected(old('fulfillment_type', $order->fulfillment_type) === 'pickup')>Ambil di toko</option>
                <option value="delivery" @selected(old('fulfillment_type', $order->fulfillment_type) === 'delivery')>Dikirim</option>
            </select>
        </div>
        <div>
            <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Jam</label>
            <input name="fulfillment_time" value="{{ old('fulfillment_time', $order->fulfillment_time) }}" type="text" class="w-full h-12 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100" />
        </div>
        <div>
            <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Alamat</label>
            <textarea name="address" rows="3" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100 resize-none">{{ old('address', $order->address) }}</textarea>
        </div>
        <div>
            <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Catatan Pesanan</label>
            <textarea name="customer_note" rows="3" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100 resize-none">{{ old('customer_note', $order->customer_note) }}</textarea>
        </div>

        <button type="submit" class="w-full h-12 rounded-2xl bg-orange-500 text-white text-sm font-semibold shadow-lg shadow-orange-500/20 transition-colors hover:bg-orange-600 active:scale-[0.98]">
            Simpan perubahan
        </button>
    </form>
</div>
@endsection