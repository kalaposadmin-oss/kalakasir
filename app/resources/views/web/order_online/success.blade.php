@extends('layouts.app')

@section('page_title', 'Pesanan Berhasil')

@section('ui_mode', 'public')

@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white shadow-2xl')

@section('header')
<header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-[var(--app-border)]">
    <div class="flex items-center gap-3 px-4 h-14">
        <span class="material-symbols-outlined text-[20px] text-transparent">arrow_back</span>
        <h1 class="text-sm font-bold">Pesanan Berhasil</h1>
    </div>
</header>
@endsection

@section('main_class', 'flex-1 overflow-y-auto bg-[radial-gradient(circle_at_top,_rgba(34,197,94,0.10),_transparent_34%),linear-gradient(to_bottom,#f8fafc_0%,#ffffff_24%)]')

@section('content')
<div class="px-4 py-5 space-y-4">
    <section class="rounded-3xl border border-emerald-100 bg-white p-4 shadow-sm shadow-emerald-100/50">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl bg-emerald-50 ring-1 ring-emerald-100">
                <span class="material-symbols-outlined text-[32px] text-emerald-600">check_circle</span>
            </div>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-emerald-500">Order berhasil</p>
                <h2 class="mt-1 text-lg font-black text-slate-900">Pesanan diterima</h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">Order tersimpan dan stok sudah di-hold sementara.</p>
            </div>
        </div>
    </section>

    @if(($order ?? null))
    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
        <div class="text-center">
            <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-[var(--app-muted)]">Kode Pesanan</p>
            <p class="mt-1 text-2xl font-black tracking-tight text-slate-900">{{ $order->order_code }}</p>
        </div>

        <div class="mt-4 rounded-2xl bg-slate-50 p-3">
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[var(--app-muted)]">Pemesan</p>
                    <p class="mt-1 font-bold text-slate-900">{{ $order->customer_name }}</p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[var(--app-muted)]">Status</p>
                    <p class="mt-1 font-bold uppercase text-orange-600">{{ str_replace('_', ' ', $order->status) }}</p>
                </div>
            </div>
            <div class="mt-3 border-t border-dashed border-slate-200 pt-3 flex items-center justify-between">
                <span class="text-xs font-semibold text-[var(--app-muted)]">Total</span>
                <span class="text-sm font-black text-slate-900">Rp {{ number_format((float) $order->grand_total, 0, ',', '.') }}</span>
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

    <section class="space-y-3">
        @if($adminWaUrl ?? null)
        <a href="{{ $adminWaUrl }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm shadow-emerald-100 transition hover:bg-emerald-100">
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-600 text-white">
                <span class="material-symbols-outlined text-[22px]">chat</span>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-emerald-900">Kirim ke WhatsApp Admin</span>
                <span class="block text-[11px] text-emerald-700">Rekap pesanan + link PIN dan isi ongkir</span>
            </span>
            <span class="material-symbols-outlined text-[18px] text-emerald-700">open_in_new</span>
        </a>
        @else
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm shadow-amber-100">
            Nomor WhatsApp admin belum terset. Isi dulu di Pembayaran & Rekening atau nomor branch agar tombol kirim rekap ke admin muncul.
        </div>
        @endif

    </section>
    @endif
</div>

@if($clearCartOnLoad ?? false)
<script>
try {
    sessionStorage.removeItem('order_cart');
} catch (error) {
    // ignore storage failures
}
</script>
@endif
@endsection
