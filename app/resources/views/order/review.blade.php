@extends('layouts.app')

@section('page_title', 'Ringkasan Pesanan')

@section('ui_mode', 'public')

@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white shadow-2xl')

@section('header')
<header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-[var(--app-border)]">
    <div class="flex items-center gap-3 px-4 h-14">
        <a href="{{ url('/order/' . $tenantSlug) }}" class="flex items-center justify-center w-8 h-8 rounded-full hover:bg-slate-100 transition">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        </a>
        <h1 class="text-sm font-bold">Ringkasan Pesanan</h1>
    </div>
</header>
@endsection

@section('main_class', 'flex-1 overflow-y-auto pb-48')

@section('content')
<div id="review-content" class="p-4 space-y-4">
    {{-- Items rendered by JS --}}
</div>

<div id="review-empty" class="hidden flex flex-col items-center justify-center py-20 text-center">
    <span class="material-symbols-outlined text-[48px] text-slate-300">remove_shopping_cart</span>
    <p class="mt-3 text-sm text-slate-400">Keranjang kosong</p>
    <a href="{{ url('/order/' . $tenantSlug) }}" class="mt-4 px-4 py-2 rounded-lg bg-orange-500 text-white text-xs font-semibold">Kembali ke Katalog</a>
</div>

{{-- Customer form --}}
<div id="checkout-form" class="hidden fixed bottom-0 left-0 right-0 z-30 bg-white border-t border-[var(--app-border)] rounded-t-2xl shadow-2xl">
    <form id="order-form" method="POST" action="{{ url('/order/submit') }}" class="p-4 space-y-3">
        @csrf
        <input type="hidden" name="tenant_slug" value="{{ $tenantSlug }}" />
        <input type="hidden" name="items" id="items-input" />

        <h3 class="text-xs font-bold text-[var(--app-muted)] uppercase tracking-wide">Data Pemesan</h3>

        <div>
            <label class="text-[11px] text-[var(--app-muted)]">Nama</label>
            <input name="customer_name" type="text" required placeholder="Nama lengkap"
                   class="w-full h-9 px-3 rounded-lg bg-slate-50 border border-[var(--app-border)] text-sm outline-none focus:ring-2 focus:ring-orange-300" />
        </div>
        <div>
            <label class="text-[11px] text-[var(--app-muted)]">No. WhatsApp</label>
            <input name="customer_phone" type="tel" required placeholder="08xxxxxxxxxx"
                   class="w-full h-9 px-3 rounded-lg bg-slate-50 border border-[var(--app-border)] text-sm outline-none focus:ring-2 focus:ring-orange-300" />
        </div>
        <div>
            <label class="text-[11px] text-[var(--app-muted)]">Catatan <span class="text-slate-300">(opsional)</span></label>
            <textarea name="notes" rows="2" placeholder="Catatan tambahan…"
                      class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-[var(--app-border)] text-sm outline-none focus:ring-2 focus:ring-orange-300 resize-none"></textarea>
        </div>

        <div class="flex items-center justify-between pt-1">
            <div>
                <p class="text-[10px] text-[var(--app-muted)]">Total</p>
                <p id="form-total" class="text-base font-bold text-orange-600">Rp 0</p>
            </div>
            <button type="submit"
                    class="px-6 py-2.5 rounded-xl bg-orange-500 text-white text-sm font-semibold shadow-lg shadow-orange-500/20 hover:bg-orange-600 transition-colors active:scale-95">
                Kirim Pesanan
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const cart = JSON.parse(sessionStorage.getItem('order_cart') || '{}');
    const keys = Object.keys(cart);
    const $ = s => document.querySelector(s);

    if (keys.length === 0) {
        $('#review-content').classList.add('hidden');
        $('#review-empty').classList.remove('hidden');
        return;
    }

    $('#checkout-form').classList.remove('hidden');
    let total = 0;
    const container = $('#review-content');

    keys.forEach(id => {
        const item = cart[id];
        const subtotal = item.price * item.qty;
        total += subtotal;
        const div = document.createElement('div');
        div.className = 'flex items-center justify-between py-2 border-b border-slate-100';
        div.innerHTML = `
            <div class="min-w-0 flex-1 mr-3">
                <p class="text-sm font-semibold">${item.name}</p>
                <p class="text-[11px] text-[var(--app-muted)]">Rp ${item.price.toLocaleString('id-ID')} x ${item.qty}</p>
            </div>
            <p class="text-sm font-bold text-slate-700">Rp ${subtotal.toLocaleString('id-ID')}</p>`;
        container.appendChild(div);
    });

    const totalRow = document.createElement('div');
    totalRow.className = 'flex items-center justify-between pt-3';
    totalRow.innerHTML = `
        <span class="text-sm font-bold">Total</span>
        <span class="text-base font-bold text-orange-600">Rp ${total.toLocaleString('id-ID')}</span>`;
    container.appendChild(totalRow);

    $('#form-total').textContent = 'Rp ' + total.toLocaleString('id-ID');

    $('#order-form').addEventListener('submit', () => {
        $('#items-input').value = JSON.stringify(cart);
    });
})();
</script>
@endpush
