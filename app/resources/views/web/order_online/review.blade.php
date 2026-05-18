@extends('layouts.app')

@section('page_title', 'Ringkasan Pesanan')

@section('ui_mode', 'public')

@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white shadow-2xl')

@section('header')
<header class="sticky top-0 z-30 border-b border-orange-100 bg-white/95 backdrop-blur-xl">
    <div class="flex items-center gap-3 px-4 h-14">
        <a href="{{ url('/order') }}" class="flex items-center justify-center w-9 h-9 rounded-full bg-orange-50 text-orange-700 hover:bg-orange-100 transition">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        </a>
        <div class="min-w-0">
            <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-orange-500">Checkout</p>
            <h1 class="text-sm font-bold truncate">Ringkasan Pesanan</h1>
        </div>
    </div>
</header>
@endsection

@section('main_class', 'flex-1 overflow-y-auto pb-[36rem] bg-[radial-gradient(circle_at_top,_rgba(249,115,22,0.10),_transparent_40%),linear-gradient(to_bottom,#fff7ed_0%,#ffffff_32%)]')

@section('content')
<div class="px-4 py-4 space-y-4">
    @if(($prefillCustomer ?? null))
    <section class="rounded-3xl border border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-orange-50 p-4 shadow-sm shadow-emerald-100/40">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-emerald-600">Customer tersambung</p>
                <h2 class="mt-1 text-lg font-black leading-tight text-slate-900">{{ $prefillCustomer['name'] }}</h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">Identitas pelanggan lama sudah dipulihkan otomatis dari link admin.</p>
            </div>
            <a href="{{ url('/order/reset-customer') }}" class="shrink-0 rounded-full border border-emerald-200 bg-white px-3 py-2 text-xs font-bold text-emerald-700 transition hover:bg-emerald-50">Ganti customer</a>
        </div>
        <div class="mt-3 flex flex-wrap gap-2 text-xs text-emerald-700">
            <span class="rounded-full bg-emerald-100 px-2.5 py-1 font-semibold">{{ $prefillCustomer['phone'] ?: 'Nomor belum tercatat' }}</span>
            @if(($prefillCustomer['address'] ?? '') !== '')
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 font-semibold">Alamat tersimpan</span>
            @endif
        </div>
    </section>
    @endif

    <section class="rounded-3xl border border-orange-100 bg-gradient-to-br from-orange-50 via-white to-amber-50 p-4 shadow-sm shadow-orange-100">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-orange-500">Checkout cepat</p>
                <h2 class="mt-1 text-lg font-black leading-tight text-slate-900">Cek item lalu kirim pesanan.</h2>
                <p class="mt-1 text-[12px] leading-relaxed text-slate-600">Pastikan nama, nomor WhatsApp, dan metode ambil sudah benar sebelum dikirim.</p>
            </div>
            <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-2xl bg-white text-center shadow-sm ring-1 ring-orange-100">
                <div>
                    <div id="summary-item-count" class="text-xl font-black leading-none text-orange-600">0</div>
                    <div class="mt-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Item</div>
                </div>
            </div>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-orange-100">
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Subtotal</p>
                <p id="hero-subtotal" class="mt-1 text-sm font-black text-orange-600">Rp 0</p>
            </div>
            <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-orange-100">
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Metode ambil</p>
                <p id="summary-fulfillment-label" class="mt-1 text-sm font-black text-slate-900">Pilih di form</p>
            </div>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-[var(--app-muted)]">Detail pesanan</p>
                <h3 class="mt-1 text-sm font-bold text-slate-900">Item yang akan dikirim</h3>
            </div>
            <span class="rounded-full bg-orange-50 px-3 py-1 text-[10px] font-semibold text-orange-700">Review</span>
        </div>
        <div id="review-content" class="mt-4 space-y-3">
            {{-- Items rendered by JS --}}
        </div>
    </section>

    <div id="review-empty" class="hidden rounded-3xl border border-dashed border-slate-300 bg-white/90 p-6 text-center shadow-sm">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
            <span class="material-symbols-outlined text-[30px]">remove_shopping_cart</span>
        </div>
        <p class="mt-4 text-sm font-semibold text-slate-700">Keranjang kosong</p>
        <p class="mt-1 text-xs leading-relaxed text-slate-500">Tambahkan produk dulu dari katalog agar bisa lanjut checkout.</p>
        <a href="{{ url('/order') }}" class="mt-4 inline-flex items-center justify-center rounded-full bg-orange-500 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-orange-500/20 transition hover:bg-orange-600">
            Kembali ke Katalog
        </a>
    </div>

    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/60">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-[var(--app-muted)]">Data pemesan</p>
                <h3 class="mt-1 text-sm font-bold text-slate-900">Lengkapi identitas pembeli</h3>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-semibold text-slate-600">Wajib</span>
        </div>

        <div id="checkout-form" class="hidden mt-4">
            <form id="order-form" method="POST" action="{{ url('/order/submit') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="items" id="items-input" />

                <div class="space-y-3">
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Nama</label>
                        <input name="customer_name" type="text" required value="{{ old('customer_name', $prefillCustomer['name'] ?? '') }}" placeholder="Nama lengkap"
                               class="w-full h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">No. WhatsApp</label>
                        <input name="customer_phone" type="tel" required value="{{ old('customer_phone', $prefillCustomer['phone'] ?? '') }}" placeholder="08xxxxxxxxxx"
                               class="w-full h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Pengambilan</label>
                        <select id="fulfillment-type" name="fulfillment_type" class="w-full h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100">
                            <option value="pickup">Ambil di toko</option>
                            <option value="delivery">Antar ke alamat</option>
                        </select>
                    </div>
                    <div>
                        <label id="fulfillment-time-label" class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Waktu Pengambilan</label>
                        <input
                            id="fulfillment-time"
                            name="fulfillment_time"
                            type="time"
                            @if(($pickupWindow['enabled'] ?? false) === true)
                                min="{{ $pickupWindow['start_time'] }}"
                                max="{{ $pickupWindow['end_time'] }}"
                                required
                            @endif
                            class="w-full h-11 rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100"
                        />
                        <p id="fulfillment-time-hint" class="mt-1.5 text-[11px] leading-4 text-slate-400">
                            @if(($pickupWindow['enabled'] ?? false) === true)
                                Aktif {{ $pickupWindow['window_label'] }}
                            @else
                                Isi jam pengambilan yang diinginkan.
                            @endif
                        </p>
                    </div>
                    <div id="pickup-location-note" class="rounded-2xl border border-orange-100 bg-orange-50 px-4 py-3 text-xs leading-relaxed text-orange-900">
                        <span class="font-semibold">Alamat Google Home Bakery:</span>
                        <span>{{ $pickupLocationLabel ?? ($tenantName ?? 'Lokasi toko') }}</span>
                    </div>
                    <div id="delivery-address-group">
                        <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Alamat</label>
                        <textarea id="delivery-address" name="address" rows="2" placeholder="Alamat lengkap jika antar"
                                  class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100 resize-none">{{ old('address', $prefillCustomer['address'] ?? '') }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-[var(--app-muted)]">Catatan <span class="text-slate-300">(opsional)</span></label>
                        <textarea name="customer_note" rows="2" placeholder="Catatan tambahan..."
                                  class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-orange-300 focus:ring-4 focus:ring-orange-100 resize-none"></textarea>
                    </div>
                </div>

                <div class="sticky bottom-3 rounded-3xl border border-orange-100 bg-orange-50/80 p-3 shadow-[0_10px_30px_rgba(249,115,22,0.12)] backdrop-blur-xl">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-orange-500">Total pesanan</p>
                            <p id="form-total" class="mt-1 text-lg font-black text-orange-600">Rp 0</p>
                        </div>
                        <button type="submit"
                                class="inline-flex h-11 items-center justify-center rounded-2xl bg-orange-500 px-5 text-sm font-semibold text-white shadow-lg shadow-orange-500/20 transition hover:bg-orange-600 active:scale-[0.98]">
                            Kirim Pesanan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const cart = JSON.parse(sessionStorage.getItem('order_cart') || '{}');
    const keys = Object.keys(cart);
    const $ = s => document.querySelector(s);
    const pickupWindow = @json($pickupWindow ?? []);
    const fulfillmentTypeEl = $('#fulfillment-type');
    const fulfillmentTimeEl = $('#fulfillment-time');
    const fulfillmentTimeLabelEl = $('#fulfillment-time-label');
    const fulfillmentTimeHintEl = $('#fulfillment-time-hint');
    const summaryFulfillmentEl = $('#summary-fulfillment-label');
    const deliveryAddressEl = $('#delivery-address');
    const deliveryAddressGroupEl = $('#delivery-address-group');
    const pickupLocationNoteEl = $('#pickup-location-note');

    if (keys.length === 0) {
        $('#review-content').classList.add('hidden');
        $('#review-empty').classList.remove('hidden');
        return;
    }

    $('#checkout-form').classList.remove('hidden');
    let total = 0;
    const container = $('#review-content');
    const items = [];
    const itemCountEl = $('#summary-item-count');
    const heroSubtotalEl = $('#hero-subtotal');

    keys.forEach(id => {
        const item = cart[id];
        const subtotal = item.price * item.qty;
        total += subtotal;
        items.push({
            product_id: id,
            qty: item.qty,
            product_variant_id: item.product_variant_id || null,
        });
        const div = document.createElement('div');
        div.className = 'flex items-start gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-3';
        div.innerHTML = `
            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-2xl bg-white text-sm font-black text-orange-600 shadow-sm">
                ${item.qty}
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-slate-900">${item.name}</p>
                <p class="mt-0.5 text-[11px] text-[var(--app-muted)]">Rp ${item.price.toLocaleString('id-ID')} x ${item.qty}</p>
            </div>
            <p class="text-sm font-bold text-orange-600">Rp ${subtotal.toLocaleString('id-ID')}</p>`;
        container.appendChild(div);
    });

    const totalRow = document.createElement('div');
    totalRow.className = 'flex items-center justify-between rounded-2xl bg-white px-4 py-3 shadow-sm';
    totalRow.innerHTML = `
        <span class="text-sm font-semibold text-slate-600">Total</span>
        <span class="text-base font-black text-orange-600">Rp ${total.toLocaleString('id-ID')}</span>`;
    container.appendChild(totalRow);

    $('#form-total').textContent = 'Rp ' + total.toLocaleString('id-ID');
    itemCountEl.textContent = String(keys.reduce((sum, id) => sum + Number(cart[id].qty || 0), 0));
    heroSubtotalEl.textContent = 'Rp ' + total.toLocaleString('id-ID');

    const displayTime = (value) => String(value || '').replace(':', '.');
    const updateFulfillmentSummary = () => {
        if (!summaryFulfillmentEl || !fulfillmentTypeEl) {
            return;
        }

        const typeLabel = fulfillmentTypeEl.value === 'delivery' ? 'Antar ke alamat' : 'Ambil di toko';
        const selectedTime = fulfillmentTimeEl?.value ? ` - ${displayTime(fulfillmentTimeEl.value)}` : '';
        summaryFulfillmentEl.textContent = `${typeLabel}${selectedTime}`;
    };

    const syncFulfillmentFields = () => {
        const isPickup = fulfillmentTypeEl?.value !== 'delivery';

        if (fulfillmentTimeLabelEl) {
            fulfillmentTimeLabelEl.textContent = isPickup ? 'Waktu Pengambilan' : 'Waktu Antar';
        }

        if (fulfillmentTimeEl) {
            fulfillmentTimeEl.required = Boolean(isPickup && pickupWindow.enabled);

            if (isPickup && pickupWindow.enabled) {
                fulfillmentTimeEl.min = pickupWindow.start_time || '';
                fulfillmentTimeEl.max = pickupWindow.end_time || '';
            } else {
                fulfillmentTimeEl.removeAttribute('min');
                fulfillmentTimeEl.removeAttribute('max');
            }
        }

        if (fulfillmentTimeHintEl) {
            fulfillmentTimeHintEl.textContent = isPickup && pickupWindow.enabled
                ? `Aktif ${pickupWindow.window_label}`
                : (isPickup ? 'Isi jam pengambilan yang diinginkan.' : 'Isi perkiraan jam antar jika diperlukan.');
        }

        if (deliveryAddressEl) {
            deliveryAddressEl.required = !isPickup;
            deliveryAddressEl.disabled = isPickup;
        }

        if (deliveryAddressGroupEl) {
            deliveryAddressGroupEl.classList.toggle('hidden', isPickup);
        }

        if (pickupLocationNoteEl) {
            pickupLocationNoteEl.classList.toggle('hidden', !isPickup);
        }

        updateFulfillmentSummary();
    };

    fulfillmentTypeEl?.addEventListener('change', syncFulfillmentFields);
    fulfillmentTimeEl?.addEventListener('input', updateFulfillmentSummary);
    syncFulfillmentFields();

    $('#order-form').addEventListener('submit', () => {
        $('#items-input').value = JSON.stringify(items);
    });
})();
</script>
@endpush
