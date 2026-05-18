@extends('layouts.app')

@section('page_title', 'Input Ongkir')

@section('ui_mode', 'public')

@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white shadow-2xl')

@section('header')
<header class="sticky top-0 z-30 border-b border-orange-100 bg-white/95 backdrop-blur-xl">
    <div class="flex items-center gap-3 px-4 h-14">
        <a href="{{ url('/admin-order/' . $orderCode . '/pin') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-orange-50 text-orange-700 transition hover:bg-orange-100">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        </a>
        <div class="min-w-0">
            <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-orange-500">Admin flow</p>
            <h1 class="text-sm font-bold truncate">Input Ongkir</h1>
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
                <span class="material-symbols-outlined text-[32px] text-orange-600">local_shipping</span>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-orange-500">Langkah ongkir</p>
                <h2 class="mt-1 text-lg font-black text-slate-900">Isi biaya kirim dan catatan.</h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">Data ini dipakai untuk menghitung total akhir sebelum WhatsApp dikirim.</p>
            </div>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-[var(--app-muted)]">Kode pesanan</p>
                <p class="mt-1 text-2xl font-black tracking-tight text-slate-900">{{ $order->order_code }}</p>
            </div>
            <span class="rounded-full bg-amber-100 px-3 py-1 text-[10px] font-semibold text-amber-700">{{ strtoupper($order->status) }}</span>
        </div>

        <div class="mt-4 rounded-2xl bg-slate-50 p-3">
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[var(--app-muted)]">Pemesan</p>
                    <p class="mt-1 font-bold text-slate-900">{{ $order->customer_name }}</p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[var(--app-muted)]">Subtotal</p>
                    <p class="mt-1 font-bold text-slate-900">Rp {{ number_format((float) $order->subtotal, 0, ',', '.') }}</p>
                </div>
            </div>
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

    <form method="POST" action="{{ url('/admin-order/' . $orderCode . '/shipping') }}" class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60 space-y-3">
        @csrf
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-orange-500">Rincian ongkir</p>
            <h2 class="mt-1 text-sm font-bold text-slate-900">Lengkapi biaya tambahan</h2>
        </div>

        <div>
            <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Kurir / Provider</label>
            <input name="shipping_provider" type="text" value="{{ old('shipping_provider', $order->shipping_provider) }}" placeholder="Contoh: Kurir internal"
                   class="w-full h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100" />
        </div>
        <div>
            <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Ongkir</label>
            <input name="shipping_cost" type="number" min="0" step="0.01" value="{{ old('shipping_cost', (float) $order->shipping_cost) }}"
                   class="w-full h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100" />
        </div>
        <div>
            <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Diskon Ongkir</label>
            <input name="shipping_discount" type="number" min="0" step="0.01" value="{{ old('shipping_discount', (float) $order->shipping_discount) }}"
                   class="w-full h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100" />
        </div>
        <div>
            <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Biaya Tambahan</label>
            <input name="extra_fee" type="number" min="0" step="0.01" value="{{ old('extra_fee', (float) $order->extra_fee) }}"
                   class="w-full h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100" />
        </div>
        <div>
            <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Catatan Admin</label>
            <textarea name="customer_note" rows="2" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100 resize-none">{{ old('customer_note', $order->customer_note) }}</textarea>
        </div>

        <button type="submit" class="w-full h-12 rounded-2xl bg-orange-500 text-white text-sm font-semibold shadow-lg shadow-orange-500/20 transition-colors hover:bg-orange-600 active:scale-[0.98]">
            Simpan dan lanjut ke final review
        </button>
    </form>
</div>
@endsection