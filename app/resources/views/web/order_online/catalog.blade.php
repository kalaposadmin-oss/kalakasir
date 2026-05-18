@extends('layouts.app')

@section('page_title', ($tenantName ?? $settings['store_name'] ?? 'Kala Kasir') . ' — Pesan Online')

@section('ui_mode', 'public')

@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white shadow-2xl')

@section('header')
<header class="sticky top-0 z-30 border-b border-orange-100 bg-white/95 backdrop-blur-xl">
    <div class="flex items-center justify-between px-4 h-14">
        <div class="flex items-center gap-2 min-w-0">
            @if($tenantLogo ?? null)
                <img src="{{ $tenantLogo ?? asset('/images/store-default.png') }}" alt="{{ $tenantName ?? $settings['store_name'] ?? 'Kala Kasir' }}" class="h-8 w-8 rounded-lg object-cover flex-shrink-0" />
            @else
                <span class="material-symbols-outlined text-[28px] text-orange-500">storefront</span>
            @endif
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-orange-500">Checkout</p>
                <h1 class="text-sm font-bold truncate">{{ $tenantName ?? $settings['store_name'] ?? 'Kala Kasir' }}</h1>
            </div>
        </div>
        <button id="cart-badge-btn"
            type="button"
            class="relative flex items-center gap-1 px-3 py-1.5 rounded-full bg-orange-50 text-orange-700 text-xs font-semibold ring-1 ring-orange-100 transition-colors hover:bg-orange-100">
            <span class="material-symbols-outlined text-[18px]">shopping_cart</span>
            <span id="cart-count">0</span>
        </button>
    </div>

    <div class="px-4 pb-2">
        <div class="rounded-2xl border border-orange-100 bg-gradient-to-r from-orange-50 via-white to-amber-50 p-3 shadow-sm shadow-orange-100">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-orange-500">Pesan online</p>
                    <h2 class="mt-1 text-sm font-black text-slate-900">Pilih menu favorit dan checkout cepat.</h2>
                </div>
                <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-orange-100">
                    <span class="material-symbols-outlined text-[20px] text-orange-500">bakery_dining</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Search --}}
    <div class="px-4 pb-2">
        <div class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-400">search</span>
            <input id="product-search" type="text" placeholder="Cari menu…"
                   class="w-full h-9 pl-9 pr-3 rounded-lg bg-slate-100 text-sm outline-none focus:ring-2 focus:ring-orange-300 transition" />
        </div>
    </div>

    {{-- Category tabs --}}
    @if(($categories ?? collect())->isNotEmpty())
    <div id="category-tabs" class="flex gap-2 px-4 pb-2 overflow-x-auto scrollbar-none">
        <button class="cat-tab active shrink-0 px-3 py-1 rounded-full text-xs font-semibold bg-orange-500 text-white transition"
                data-cat="all">Semua</button>
        @foreach($categories as $cat)
            <button class="cat-tab shrink-0 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 transition"
                    data-cat="{{ $cat->id }}">{{ $cat->name }}</button>
        @endforeach
    </div>
    @endif
</header>
@endsection

@section('main_class', 'flex-1 overflow-y-auto pb-36 bg-[radial-gradient(circle_at_top,_rgba(249,115,22,0.08),_transparent_38%),linear-gradient(to_bottom,#fff7ed_0%,#ffffff_30%)]')

@section('content')
@if(($prefillCustomer ?? null))
<div class="px-4 pt-4">
    <div class="rounded-3xl border border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-orange-50 p-4 shadow-sm shadow-emerald-100/40">
        <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-emerald-600">Customer tersambung</p>
        <h2 class="mt-1 text-sm font-black text-slate-900">{{ $prefillCustomer['name'] }}</h2>
        <p class="mt-1 text-xs leading-relaxed text-slate-600">Data pelanggan lama sudah diisi otomatis. Lanjut pilih produk, lalu ringkasan pesanan akan memakai identitas ini.</p>
        <div class="mt-3 flex items-center gap-2 text-xs text-emerald-700">
            <span class="rounded-full bg-emerald-100 px-2.5 py-1 font-semibold">{{ $prefillCustomer['phone'] ?: 'Nomor belum tercatat' }}</span>
        </div>
    </div>
</div>
@endif

<div id="product-grid" class="grid grid-cols-2 gap-3 p-4">
    @forelse(($products ?? collect()) as $product)
        <div class="product-card overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/60 transition-transform active:scale-[0.97]"
             data-cat="{{ $product->category_id ?? 'uncat' }}"
               data-name="{{ strtolower($product->name) }}"
               data-stock-state="{{ $product->stock_state ?? 'unknown' }}">
            @if($product->image_url ?? null)
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                     class="w-full aspect-[4/3] object-cover" loading="lazy" />
            @else
                <div class="w-full aspect-[4/3] bg-gradient-to-br from-orange-50 to-amber-50 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[36px] text-orange-300">bakery_dining</span>
                </div>
            @endif
            <div class="p-3">
                <p class="text-xs font-semibold leading-tight line-clamp-2 min-h-[2rem] text-slate-900">{{ $product->name }}</p>
                @if(($product->description ?? null))
                    <p class="text-[10px] text-[var(--app-muted)] mt-0.5 line-clamp-1">{{ $product->description }}</p>
                @endif
                <div class="mt-3 flex items-center justify-between gap-2">
                    <span class="text-sm font-black text-orange-600">Rp {{ number_format($product->selling_price, 0, ',', '.') }}</span>
                    @if(($product->stock_state ?? 'unknown') === 'sold_out')
                        <span class="text-[10px] font-semibold text-red-500 uppercase tracking-wide">Habis</span>
                    @else
                        <button class="add-btn flex items-center justify-center w-8 h-8 rounded-full bg-orange-500 text-white shadow-sm shadow-orange-500/20 transition-colors hover:bg-orange-600 active:scale-90"
                                data-id="{{ $product->id }}"
                                data-name="{{ $product->name }}"
                                data-price="{{ $product->selling_price }}">
                            <span class="material-symbols-outlined text-[18px]">add</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-span-2 flex flex-col items-center justify-center py-20 text-center">
            <span class="material-symbols-outlined text-[48px] text-slate-300">storefront</span>
            <p class="mt-3 text-sm text-slate-400">Belum ada produk yang tersedia</p>
        </div>
    @endforelse
</div>

{{-- Cart slide-up panel --}}
<div id="cart-panel" class="hidden fixed inset-0 z-40">
    <div id="cart-panel-backdrop" class="absolute inset-0 bg-black/40"></div>
    <div class="absolute bottom-0 left-0 right-0 max-h-[75vh] rounded-t-[1.75rem] border-t border-slate-200 bg-white shadow-2xl flex flex-col">
        <div class="flex items-center justify-between px-4 pt-4 pb-2">
            <h2 class="text-sm font-bold">Keranjang</h2>
            <button id="cart-close-btn" type="button" class="text-xs font-semibold text-[var(--app-muted)]">Tutup</button>
        </div>
        <div id="cart-items" class="flex-1 overflow-y-auto px-4 pb-4">
            <p id="cart-empty" class="text-center text-xs text-slate-400 py-8">Keranjang kosong</p>
        </div>
        <div id="cart-footer" class="hidden border-t border-slate-200 px-4 py-3 space-y-2 bg-slate-50/80">
            <div class="flex items-center justify-between text-sm">
                <span class="text-[var(--app-muted)]">Total</span>
                <span id="cart-total" class="font-black text-orange-600">Rp 0</span>
            </div>
            <a id="checkout-link" href="{{ url('/order/review') }}"
               class="block w-full h-11 rounded-2xl bg-orange-500 text-white text-center text-sm font-semibold leading-[44px] shadow-lg shadow-orange-500/20 transition-colors hover:bg-orange-600">
                Lihat Ringkasan
            </a>
        </div>
    </div>
</div>

{{-- Empty-state floating button --}}
<button id="fab-review"
    type="button"
    class="hidden fixed bottom-5 left-1/2 -translate-x-1/2 z-30 flex items-center gap-2 px-5 py-2.5 rounded-full bg-orange-500 text-white text-sm font-semibold shadow-lg shadow-orange-500/30 transition-transform active:scale-95">
    <span class="material-symbols-outlined text-[18px]">shopping_bag</span>
    <span>Review Pesanan (<span id="fab-count">0</span>)</span>
</button>
@endsection

@push('scripts')
<script>
(function(){
    const cart = JSON.parse(sessionStorage.getItem('order_cart') || '{}');
    const $ = s => document.querySelector(s);
    const $$ = s => document.querySelectorAll(s);
    const cartPanel = $('#cart-panel');

    function openCart() { cartPanel.classList.remove('hidden'); }
    function closeCart() { cartPanel.classList.add('hidden'); }

    function save() { sessionStorage.setItem('order_cart', JSON.stringify(cart)); }

    function render() {
        const keys = Object.keys(cart);
        const count = keys.reduce((s,k) => s + cart[k].qty, 0);
        const total = keys.reduce((s,k) => s + cart[k].price * cart[k].qty, 0);

        $('#cart-count').textContent = count;

        // FAB
        if (count > 0) { $('#fab-review').classList.remove('hidden'); $('#fab-count').textContent = count; }
        else { $('#fab-review').classList.add('hidden'); }

        // Cart panel items
        const container = $('#cart-items');
        const emptyEl = $('#cart-empty');
        container.querySelectorAll('.cart-row').forEach(el => el.remove());

        if (keys.length === 0) {
            emptyEl.classList.remove('hidden');
            $('#cart-footer').classList.add('hidden');
            return;
        }
        emptyEl.classList.add('hidden');
        $('#cart-footer').classList.remove('hidden');
        $('#cart-total').textContent = 'Rp ' + total.toLocaleString('id-ID');

        keys.forEach(id => {
            const item = cart[id];
            const row = document.createElement('div');
            row.className = 'cart-row flex items-center justify-between py-2 border-b border-slate-100';
            row.innerHTML = `
                <div class="min-w-0 flex-1 mr-2">
                    <p class="text-xs font-semibold truncate">${item.name}</p>
                    <p class="text-[10px] text-[var(--app-muted)]">Rp ${item.price.toLocaleString('id-ID')} x ${item.qty}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button class="cart-dec w-6 h-6 rounded-full border border-slate-200 flex items-center justify-center text-xs" data-id="${id}">-</button>
                    <span class="text-xs font-semibold w-4 text-center">${item.qty}</span>
                    <button class="cart-inc w-6 h-6 rounded-full bg-orange-500 text-white flex items-center justify-center text-xs" data-id="${id}">+</button>
                </div>`;
            container.insertBefore(row, emptyEl);
        });
    }

    // Add to cart
    $$('.add-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const card = btn.closest('.product-card');
            if (card && card.dataset.stockState === 'sold_out') {
                return;
            }
            const id = btn.dataset.id;
            if (!cart[id]) cart[id] = { name: btn.dataset.name, price: +btn.dataset.price, qty: 0 };
            cart[id].qty++;
            save(); render();
        });
    });

    $('#cart-badge-btn').addEventListener('click', openCart);
    $('#cart-panel-backdrop').addEventListener('click', closeCart);
    $('#cart-close-btn').addEventListener('click', closeCart);
    $('#fab-review').addEventListener('click', () => { location.href = '{{ url('/order/review') }}'; });

    // Cart +/-
    $('#cart-items').addEventListener('click', e => {
        const inc = e.target.closest('.cart-inc');
        const dec = e.target.closest('.cart-dec');
        if (inc) { cart[inc.dataset.id].qty++; save(); render(); }
        if (dec) { cart[dec.dataset.id].qty--; if (cart[dec.dataset.id].qty <= 0) delete cart[dec.dataset.id]; save(); render(); }
    });

    // Search
    $('#product-search').addEventListener('input', e => {
        const q = e.target.value.toLowerCase();
        $$('.product-card').forEach(c => {
            c.style.display = c.dataset.name.includes(q) ? '' : 'none';
        });
    });

    // Category tabs
    $$('.cat-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            $$('.cat-tab').forEach(t => { t.classList.remove('bg-orange-500','text-white'); t.classList.add('bg-slate-100','text-slate-600'); });
            tab.classList.remove('bg-slate-100','text-slate-600');
            tab.classList.add('bg-orange-500','text-white');
            const cat = tab.dataset.cat;
            $$('.product-card').forEach(c => { c.style.display = (cat === 'all' || c.dataset.cat === cat) ? '' : 'none'; });
        });
    });

    render();
})();
</script>
@endpush
