@extends('layouts.app')

@section('page_title', 'Form Buat PO Baru')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto px-4 py-6 space-y-6 pb-32')

@push('head')
        <style type="text/tailwindcss">
            .ios-blur {
                @apply backdrop-blur-md bg-white/80 dark:bg-background-dark/80;
            }
        </style>
@endpush

@section('header')
    <x-page-header title="Buat PO Baru" backUrl="/menu/purchases" :showMenuButton="true">
        <x-slot:actions>
            <button class="text-primary font-semibold text-base">Bantuan</button>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <section class="space-y-3">
        <h3 class="text-[13px] font-semibold text-slate-500 uppercase tracking-wider px-1">Informasi Pemasok</h3>
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="space-y-4">
                <div class="relative">
                    <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1">Pilih Pemasok</label>
                    <div class="relative">
                        <select id="supplier-select" class="w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 text-sm font-medium focus:ring-2 focus:ring-primary/20 appearance-none"></select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-1">
                    <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                        <span class="material-symbols-outlined text-slate-500 text-lg">event_note</span>
                    </div>
                    <div class="flex-1">
                        <span class="text-xs text-slate-400 block">Tanggal Pesanan</span>
                        <span class="text-sm font-medium" id="order-date">-</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="space-y-3">
        <h3 class="text-[13px] font-semibold text-slate-500 uppercase tracking-wider px-1">Pembayaran</h3>
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="relative">
                <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1">Metode Pembayaran</label>
                <div class="relative">
                    <select id="payment-method" class="w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 text-sm font-medium focus:ring-2 focus:ring-primary/20 appearance-none">
                        <option value="tempo">Tempo</option>
                        <option value="tunai">Tunai</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                </div>
            </div>
            <div id="due-date-wrapper" class="relative">
                <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1">Jatuh Tempo</label>
                <input id="due-date" class="w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 text-sm font-medium focus:ring-2 focus:ring-primary/20" type="date" />
            </div>
        </div>
    </section>
    <section class="space-y-3">
        <div class="flex items-center justify-between px-1">
            <h3 class="text-[13px] font-semibold text-slate-500 uppercase tracking-wider">Item Produk</h3>
            <button class="text-primary text-xs font-bold flex items-center gap-1" type="button">
                <span class="material-symbols-outlined text-sm">barcode_scanner</span>
                Scan Barcode
            </button>
        </div>
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4" id="item-container"></div>
        <button id="add-item" class="w-full h-11 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl text-slate-400 text-sm font-semibold flex items-center justify-center gap-2 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors" type="button">
            <span class="material-symbols-outlined">add_circle</span>
            Tambah Item Lain
        </button>
    </section>
    <section class="bg-primary/5 dark:bg-primary/10 rounded-2xl p-4 border border-primary/10 space-y-3">
        <div class="flex justify-between items-center text-sm">
            <span class="text-slate-500">Subtotal</span>
            <span class="font-medium" id="subtotal">Rp 0</span>
        </div>
        <div class="flex justify-between items-center text-sm">
            <span class="text-slate-500">Pajak (11%)</span>
            <span class="font-medium" id="tax">Rp 0</span>
        </div>
        <div class="pt-3 border-t border-primary/10 flex justify-between items-center">
            <span class="text-base font-bold text-slate-900 dark:text-white">Total Estimasi</span>
            <span class="text-lg font-bold text-primary tracking-tight" id="total">Rp 0</span>
        </div>
    </section>
    <section class="space-y-1.5 px-1">
        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Catatan Opsional</label>
        <textarea id="notes" class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 text-sm focus:ring-2 focus:ring-primary/20 placeholder:text-slate-400" placeholder="Tambahkan instruksi pengiriman..." rows="2"></textarea>
    </section>
    <div class="fixed bottom-0 w-full max-w-[430px] p-4 ios-blur border-t border-slate-200 dark:border-slate-800 pb-10">
        <div class="flex gap-3">
            <button id="save-draft" class="flex-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 py-4 rounded-2xl font-bold text-slate-600 dark:text-slate-300" type="button">Simpan Draft</button>
            <button id="submit-po" class="flex-[2] bg-primary text-white py-4 rounded-2xl font-bold shadow-lg shadow-primary/20 active:scale-[0.98] transition-transform" type="button">Buat Pesanan</button>
        </div>
        <div class="mt-6 flex justify-center">
            <div class="w-32 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
    </div>

        @include('partials.ui-helpers')
        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    supplier: document.getElementById('supplier-select'),
                    orderDate: document.getElementById('order-date'),
                    container: document.getElementById('item-container'),
                    addItem: document.getElementById('add-item'),
                    subtotal: document.getElementById('subtotal'),
                    tax: document.getElementById('tax'),
                    total: document.getElementById('total'),
                    notes: document.getElementById('notes'),
                    paymentMethod: document.getElementById('payment-method'),
                    dueDate: document.getElementById('due-date'),
                    dueDateWrapper: document.getElementById('due-date-wrapper'),
                    draft: document.getElementById('save-draft'),
                    submit: document.getElementById('submit-po'),
                };

                const state = {
                    products: [],
                };

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const setDateLabel = () => {
                    el.orderDate.textContent = new Intl.DateTimeFormat('id-ID', {
                        day: '2-digit',
                        month: 'long',
                        year: 'numeric',
                    }).format(new Date());
                };

                const setDefaultDueDate = () => {
                    const now = new Date();
                    now.setDate(now.getDate() + 14);
                    const yyyy = now.getFullYear();
                    const mm = String(now.getMonth() + 1).padStart(2, '0');
                    const dd = String(now.getDate()).padStart(2, '0');
                    el.dueDate.value = `${yyyy}-${mm}-${dd}`;
                };

                const syncPaymentFields = () => {
                    const isTempo = (el.paymentMethod.value || 'tempo') === 'tempo';
                    el.dueDateWrapper.classList.toggle('hidden', !isTempo);
                };

                const buildProductOptions = () => {
                    return state.products.map((product) => `<option value="${product.id}">${product.name}</option>`).join('');
                };

                const addItemRow = () => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'space-y-4';
                    wrapper.innerHTML = `
                        <div class="relative">
                            <label class="block text-xs font-medium text-slate-400 mb-1.5 ml-1">Pilih Produk</label>
                            <div class="relative">
                                <select class="product-select w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 text-sm font-medium focus:ring-2 focus:ring-primary/20 appearance-none">
                                    <option value="">Pilih Produk...</option>
                                    ${buildProductOptions()}
                                </select>
                                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-medium text-slate-400 ml-1">Jumlah</label>
                                <div class="flex items-center">
                                    <input class="item-qty w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl px-4 text-sm font-bold focus:ring-2 focus:ring-primary/20" placeholder="0" type="number" value="1" min="1" />
                                    <span class="ml-2 text-xs text-slate-500 font-medium">Pcs</span>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-medium text-slate-400 ml-1">Harga Beli</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">Rp</span>
                                    <input class="item-price w-full h-12 bg-slate-50 dark:bg-slate-800 border-none rounded-xl pl-10 pr-4 text-sm font-bold focus:ring-2 focus:ring-primary/20" placeholder="0" type="number" value="0" min="0" />
                                </div>
                            </div>
                        </div>
                    `;
                    el.container.appendChild(wrapper);

                    const select = wrapper.querySelector('.product-select');
                    const priceInput = wrapper.querySelector('.item-price');
                    select.addEventListener('change', () => {
                        const product = state.products.find((item) => item.id === select.value);
                        if (product) {
                            priceInput.value = product.price || 0;
                            recalcTotals();
                        }
                    });

                    wrapper.querySelectorAll('input').forEach((input) => {
                        input.addEventListener('input', recalcTotals);
                    });
                };

                const recalcTotals = () => {
                    let subtotal = 0;
                    el.container.querySelectorAll('.product-select').forEach((select) => {
                        const row = select.closest('div').parentElement;
                        const qtyInput = row.querySelector('.item-qty');
                        const priceInput = row.querySelector('.item-price');
                        const qty = parseFloat(qtyInput.value || '0');
                        const price = parseFloat(priceInput.value || '0');
                        subtotal += qty * price;
                    });
                    const tax = subtotal * 0.11;
                    const total = subtotal + tax;
                    el.subtotal.textContent = formatCurrency(subtotal);
                    el.tax.textContent = formatCurrency(tax);
                    el.total.textContent = formatCurrency(total);
                };

                const loadSuppliers = async () => {
                    const response = await fetch('/api/v1/suppliers', { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const data = await response.json();
                    return data.suppliers || [];
                };

                const loadProducts = async () => {
                    const response = await fetch('/api/v1/products', { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const data = await response.json();
                    return data.products || [];
                };

                const submit = async (status) => {
                    const supplierId = el.supplier.value;
                    if (!supplierId) {
                        alert('Pilih pemasok terlebih dahulu.');
                        return;
                    }

                    const items = [];
                    el.container.querySelectorAll('.product-select').forEach((select) => {
                        const wrapper = select.closest('div').parentElement;
                        const qtyInput = wrapper.querySelector('.item-qty');
                        const priceInput = wrapper.querySelector('.item-price');
                        if (!select.value) {
                            return;
                        }
                        items.push({
                            product_id: select.value,
                            qty: parseFloat(qtyInput.value || '0'),
                            price: parseFloat(priceInput.value || '0'),
                        });
                    });

                    if (!items.length) {
                        alert('Tambahkan minimal satu produk.');
                        return;
                    }

                    const totalQty = items.reduce((sum, item) => sum + Number(item.qty || 0), 0);
                    const totalValue = items.reduce((sum, item) => sum + (Number(item.qty || 0) * Number(item.price || 0)), 0);
                    const supplierName = el.supplier.options[el.supplier.selectedIndex]?.textContent || '-';
                    const paymentMethod = el.paymentMethod.value || 'tempo';
                    const dueDate = paymentMethod === 'tempo' ? (el.dueDate.value || null) : null;

                    if (paymentMethod === 'tempo' && !dueDate) {
                        alert('Tanggal jatuh tempo wajib diisi untuk pembelian tempo.');
                        return;
                    }

                    const allowed = window.PosUi.confirmRiskAction({
                        title: status === 'draft' ? 'Simpan Draft PO' : 'Konfirmasi Buat PO',
                        message: `Supplier: ${supplierName}\nItem: ${items.length}\nTotal Qty: ${totalQty}\nEstimasi Nilai: ${formatCurrency(totalValue)}`,
                    });
                    if (!allowed) {
                        return;
                    }

                    const triggerButton = status === 'draft' ? el.draft : el.submit;

                    await window.PosUi.runWithSubmitGuard(triggerButton, async () => {
                        try {
                            const response = await window.PosUi.fetchWithTimeout('/api/v1/purchases', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    ...headers,
                                },
                                body: JSON.stringify({
                                    supplier_id: supplierId,
                                    status,
                                    payment_method: paymentMethod,
                                    due_date: dueDate,
                                    notes: el.notes.value.trim() || null,
                                    items,
                                }),
                            }, 15000);

                            if (!response.ok) {
                                const mapped = await window.PosUi.mapApiError(response, 'Gagal membuat PO.');
                                if (mapped.code === 'INSUFFICIENT_CASH_BALANCE') {
                                    const available = mapped.details?.available_cash || 0;
                                    const required = mapped.details?.required_amount || 0;
                                    alert(`Saldo kas tidak cukup. Tersedia ${formatCurrency(available)}, dibutuhkan ${formatCurrency(required)}.`);
                                    return;
                                }
                                alert(mapped.message);
                                return;
                            }

                            alert('PO berhasil disimpan.');
                            window.location.href = '/suppliers';
                        } catch (error) {
                            if (error?.name === 'AbortError') {
                                alert('Permintaan timeout. Cek daftar PO sebelum mengulang simpan.');
                                return;
                            }
                            alert(error?.message || 'Gagal membuat PO.');
                        }
                    });
                };

                el.addItem.addEventListener('click', () => {
                    addItemRow();
                });

                el.paymentMethod.addEventListener('change', syncPaymentFields);

                el.draft.addEventListener('click', () => submit('draft'));
                el.submit.addEventListener('click', () => submit('menunggu'));

                (async () => {
                    setDateLabel();
                    setDefaultDueDate();
                    syncPaymentFields();
                    const [suppliers, products] = await Promise.all([loadSuppliers(), loadProducts()]);
                    el.supplier.innerHTML = '<option value="">Pilih Pemasok...</option>';
                    suppliers.forEach((supplier) => {
                        const option = document.createElement('option');
                        option.value = supplier.id;
                        option.textContent = supplier.name;
                        el.supplier.appendChild(option);
                    });
                    state.products = products;
                    addItemRow();
                    recalcTotals();
                })();
            })();
        </script>
    @endsection





