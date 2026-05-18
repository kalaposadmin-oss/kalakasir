@extends('layouts.app')

@section('page_title', 'Ongkir & Pengiriman — Order #' . $code)

@section('ui_mode', 'backoffice')

@section('body_class', 'bg-background-light dark:bg-background-dark')

@section('header')
<header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-[var(--app-border)]">
    <div class="flex items-center gap-3 px-4 h-14">
        <a href="{{ url('/admin-order/' . $code . '/pin') }}" class="flex items-center justify-center w-8 h-8 rounded-full hover:bg-slate-100 transition">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        </a>
        <div class="min-w-0">
            <h1 class="text-sm font-bold truncate">Ongkir & Pengiriman</h1>
            <p class="text-[11px] text-[var(--app-muted)]">Order #{{ $code }}</p>
        </div>
    </div>
</header>
@endsection

@section('main_class', 'flex-1 overflow-y-auto')

@section('content')
<div class="p-4 max-w-lg mx-auto space-y-4">
    {{-- Order summary --}}
    <div class="bg-white rounded-2xl border border-[var(--app-border)] shadow-sm p-4 space-y-3">
        <h3 class="text-xs font-bold text-[var(--app-muted)] uppercase tracking-wide">Ringkasan Order</h3>
        <div class="space-y-2">
            @foreach($items as $item)
                <div class="flex items-center justify-between text-sm">
                    <div class="min-w-0 flex-1 mr-2">
                        <p class="font-medium truncate">{{ $item['name'] }}</p>
                        <p class="text-[11px] text-[var(--app-muted)]">{{ $item['qty'] }}x @ Rp {{ number_format($item['price'], 0, ',', '.') }}</p>
                    </div>
                    <span class="font-semibold">Rp {{ number_format($item['qty'] * $item['price'], 0, ',', '.') }}</span>
                </div>
            @endforeach
        </div>
        <div class="border-t border-dashed border-[var(--app-border)] pt-2 flex items-center justify-between">
            <span class="text-sm font-bold">Subtotal</span>
            <span class="text-sm font-bold text-orange-600">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
        </div>
    </div>

    {{-- Customer info --}}
    <div class="bg-white rounded-2xl border border-[var(--app-border)] shadow-sm p-4 space-y-2">
        <h3 class="text-xs font-bold text-[var(--app-muted)] uppercase tracking-wide">Data Pemesan</h3>
        <div class="flex items-start gap-2">
            <span class="material-symbols-outlined text-[16px] text-[var(--app-muted)] mt-0.5">person</span>
            <div>
                <p class="text-sm font-medium">{{ $customerName }}</p>
                <p class="text-xs text-[var(--app-muted)]">{{ $customerPhone }}</p>
            </div>
        </div>
        @if($notes)
            <div class="flex items-start gap-2">
                <span class="material-symbols-outlined text-[16px] text-[var(--app-muted)] mt-0.5">sticky_note_2</span>
                <p class="text-xs text-slate-600">{{ $notes }}</p>
            </div>
        @endif
    </div>

    {{-- Shipping form --}}
    <form method="POST" action="{{ url('/admin-order/' . $code . '/finalize') }}" class="bg-white rounded-2xl border border-[var(--app-border)] shadow-sm p-4 space-y-4">
        @csrf
        <h3 class="text-xs font-bold text-[var(--app-muted)] uppercase tracking-wide">Pengiriman</h3>

        <div>
            <label class="text-[11px] text-[var(--app-muted)]">Metode Pengiriman</label>
            <select name="shipping_method" required
                    class="w-full h-10 px-3 rounded-lg bg-slate-50 border border-[var(--app-border)] text-sm outline-none focus:ring-2 focus:ring-orange-300">
                <option value="pickup">Ambil di Tokko (Pickup)</option>
                <option value="delivery">Antar ke Alamat</option>
                <option value="grab">Grab / Gojek</option>
            </select>
        </div>

        <div id="address-field" class="hidden">
            <label class="text-[11px] text-[var(--app-muted)]">Alamat Pengiriman</label>
            <textarea name="shipping_address" rows="2" placeholder="Masukkan alamat lengkap…"
                      class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-[var(--app-border)] text-sm outline-none focus:ring-2 focus:ring-orange-300 resize-none"></textarea>
        </div>

        <div>
            <label class="text-[11px] text-[var(--app-muted)]">Ongkos Kirim (Rp)</label>
            <input name="shipping_cost" type="number" min="0" step="500" value="0"
                   class="w-full h-10 px-3 rounded-lg bg-slate-50 border border-[var(--app-border)] text-sm outline-none focus:ring-2 focus:ring-orange-300" />
        </div>

        <div>
            <label class="text-[11px] text-[var(--app-muted)]">Estimasi Selesai</label>
            <input name="estimated_ready" type="text" placeholder="contoh: 30 menit, jam 3 sore"
                   class="w-full h-10 px-3 rounded-lg bg-slate-50 border border-[var(--app-border)] text-sm outline-none focus:ring-2 focus:ring-orange-300" />
        </div>

        <button type="submit"
                class="w-full h-12 rounded-xl bg-orange-500 text-white text-sm font-semibold hover:bg-orange-600 transition-colors active:scale-95">
            Lanjut ke Konfirmasi
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const sel = document.querySelector('select[name="shipping_method"]');
    const addrField = document.getElementById('address-field');
    sel.addEventListener('change', () => {
        addrField.classList.toggle('hidden', sel.value === 'pickup');
    });
})();
</script>
@endpush
