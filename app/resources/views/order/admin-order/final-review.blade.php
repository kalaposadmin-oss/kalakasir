@extends('layouts.app')

@section('page_title', 'Konfirmasi Pesanan — Order #' . $code)

@section('ui_mode', 'backoffice')

@section('body_class', 'bg-background-light dark:bg-background-dark')

@section('header')
<header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-[var(--app-border)]">
    <div class="flex items-center gap-3 px-4 h-14">
        <a href="{{ url('/admin-order/' . $code . '/shipping') }}" class="flex items-center justify-center w-8 h-8 rounded-full hover:bg-slate-100 transition">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        </a>
        <div class="min-w-0">
            <h1 class="text-sm font-bold truncate">Konfirmasi Pesanan</h1>
            <p class="text-[11px] text-[var(--app-muted)]">Order #{{ $code }}</p>
        </div>
    </div>
</header>
@endsection

@section('main_class', 'flex-1 overflow-y-auto pb-32')

@section('content')
<div class="p-4 max-w-lg mx-auto space-y-4">
    {{-- Status --}}
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-start gap-3">
        <span class="material-symbols-outlined text-[24px] text-amber-500">pending</span>
        <div>
            <p class="text-sm font-semibold text-amber-800">Menunggu Konfirmasi</p>
            <p class="text-xs text-amber-600 mt-0.5">Periksa kembali sebelum mengirim ke pelanggan</p>
        </div>
    </div>

    {{-- Items --}}
    <div class="bg-white rounded-2xl border border-[var(--app-border)] shadow-sm p-4 space-y-2">
        <h3 class="text-xs font-bold text-[var(--app-muted)] uppercase tracking-wide">Item Pesanan</h3>
        @foreach($items as $item)
            <div class="flex items-center justify-between py-1.5 {{ !$loop->last ? 'border-b border-slate-50' : '' }}">
                <div class="flex items-center gap-2 min-w-0 flex-1 mr-2">
                    <span class="shrink-0 w-5 h-5 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-[10px] font-bold">{{ $item['qty'] }}</span>
                    <span class="text-sm truncate">{{ $item['name'] }}</span>
                </div>
                <span class="text-sm font-semibold">Rp {{ number_format($item['qty'] * $item['price'], 0, ',', '.') }}</span>
            </div>
        @endforeach
        <div class="border-t border-dashed border-[var(--app-border)] pt-2 flex justify-between">
            <span class="text-sm text-[var(--app-muted)]">Subtotal</span>
            <span class="text-sm font-semibold">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
        </div>
    </div>

    {{-- Shipping --}}
    <div class="bg-white rounded-2xl border border-[var(--app-border)] shadow-sm p-4 space-y-2">
        <h3 class="text-xs font-bold text-[var(--app-muted)] uppercase tracking-wide">Pengiriman</h3>
        <div class="flex items-center justify-between text-sm">
            <span class="text-[var(--app-muted)]">Metode</span>
            <span class="font-medium">{{ ucfirst($shippingMethod) }}</span>
        </div>
        @if($shippingAddress)
            <div class="flex items-start gap-2 text-sm">
                <span class="text-[var(--app-muted)]">Alamat</span>
                <span class="font-medium text-right flex-1">{{ $shippingAddress }}</span>
            </div>
        @endif
        <div class="flex items-center justify-between text-sm">
            <span class="text-[var(--app-muted)]">Ongkir</span>
            <span class="font-medium {{ $shippingCost > 0 ? 'text-orange-600' : '' }}">{{ $shippingCost > 0 ? 'Rp ' . number_format($shippingCost, 0, ',', '.') : 'GRATIS' }}</span>
        </div>
        @if($estimatedReady)
            <div class="flex items-center justify-between text-sm">
                <span class="text-[var(--app-muted)]">Estimasi</span>
                <span class="font-medium">{{ $estimatedReady }}</span>
            </div>
        @endif
    </div>

    {{-- Customer --}}
    <div class="bg-white rounded-2xl border border-[var(--app-border)] shadow-sm p-4 space-y-2">
        <h3 class="text-xs font-bold text-[var(--app-muted)] uppercase tracking-wide">Pemesan</h3>
        <div class="flex items-center justify-between text-sm">
            <span class="text-[var(--app-muted)]">Nama</span>
            <span class="font-medium">{{ $customerName }}</span>
        </div>
        <div class="flex items-center justify-between text-sm">
            <span class="text-[var(--app-muted)]">WhatsApp</span>
            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $customerPhone) }}" target="_blank"
               class="font-medium text-green-600 hover:underline flex items-center gap-1">
                {{ $customerPhone }}
                <span class="material-symbols-outlined text-[14px]">open_in_new</span>
            </a>
        </div>
        @if($notes)
            <div class="pt-1">
                <p class="text-[11px] text-[var(--app-muted)]">Catatan</p>
                <p class="text-sm">{{ $notes }}</p>
            </div>
        @endif
    </div>

    {{-- Grand total --}}
    <div class="bg-orange-50 border border-orange-200 rounded-2xl p-4 flex items-center justify-between">
        <div>
            <p class="text-xs text-orange-600 font-semibold">TOTAL PEMBAYARAN</p>
            <p class="text-[11px] text-orange-400">Subtotal + Ongkir</p>
        </div>
        <p class="text-xl font-bold text-orange-600">Rp {{ number_format($grandTotal, 0, ',', '.') }}</p>
    </div>
</div>

{{-- Action buttons --}}
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-[var(--app-border)] p-4">
    <div class="max-w-lg mx-auto flex gap-3">
        <form method="POST" action="{{ url('/admin-order/' . $code . '/reject') }}" class="flex-1">
            @csrf @method('DELETE')
            <button type="submit"
                    class="w-full h-11 rounded-xl border border-red-200 text-red-500 text-sm font-semibold bg-white hover:bg-red-50 transition-colors active:scale-95">
                Tolak
            </button>
        </form>
        <form method="POST" action="{{ url('/admin-order/' . $code . '/confirm') }}" class="flex-[2]">
            @csrf
            <button type="submit"
                    class="w-full h-11 rounded-xl bg-green-500 text-white text-sm font-semibold hover:bg-green-600 transition-colors active:scale-95 flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                Konfirmasi & Kirim WA
            </button>
        </form>
    </div>
</div>
@endsection
