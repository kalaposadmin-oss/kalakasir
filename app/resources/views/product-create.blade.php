@extends('layouts.app')

@section('page_title', 'POS SaaS')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@section('header')
    <x-page-header title="Tambah Produk Baru" backUrl="/menu/management" :showMenuButton="false">
        <x-slot name="actions">
            <a class="flex size-10 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300" href="#" data-menu-trigger>
                <span class="material-symbols-outlined">menu</span>
            </a>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
        <div class="flex-1 overflow-y-auto pb-32">
            <div class="px-4 py-4">
                <div class="relative group">
                    <div id="image-preview" class="w-full aspect-video bg-slate-50 dark:bg-slate-800/50 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl flex flex-col items-center justify-center overflow-hidden transition-all hover:border-primary/50" style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuBYuCMEoCG-olGE_fxLtmDzd2bF2w2IDFLmzF5xj54APUEJc1lpXNRBOXm7B5Z6F2AcxRHbUUZVDOhEPRnGL-dQEETshdoiEmQfsGLZv8EArgbo1ghz7_ijoAuhIyUMrt1kXLXkHz1tip9vdyNOkymgyvC1mYlbPjlbFqQIkgmqQfzZq64Vpq_K7BGS3P3ZzCi7WBjPH-rC3Lqu7bfqWH6Q5ad6MqENP020ISAC8ehZ9L7iAtlchEQr293m2bhO2L2pkNIb-C5L4w"); background-size: cover; background-position: center;'>
                        <div id="image-overlay" class="absolute inset-0 bg-black/20 flex flex-col items-center justify-center backdrop-blur-[2px]">
                            <div class="bg-white/90 dark:bg-slate-900/90 p-3 rounded-full shadow-lg mb-2">
                                <span class="material-symbols-outlined text-primary text-3xl">add_a_photo</span>
                            </div>
                            <p class="text-white text-sm font-semibold">Unggah Foto Produk</p>
                            <p class="text-white/80 text-xs">PNG, JPG up to 5MB</p>
                        </div>
                        <input id="product-image" class="absolute inset-0 opacity-0 cursor-pointer" type="file" accept="image/*" />
                    </div>
                </div>
            </div>

            <div class="px-4 space-y-5">
                <div class="flex flex-col gap-1.5">
                    <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold px-0.5">Nama Produk</label>
                    <input id="product-name" class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 text-base placeholder:text-slate-400" placeholder="Contoh: Kopi Susu Gula Aren" type="text" />
                </div>
                    <div class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between px-0.5">
                            <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold">SKU / Barcode</label>
                            <button id="sku-generate" class="text-xs font-semibold text-primary" type="button">Buat Otomatis</button>
                        </div>
                        <div class="flex items-center gap-2">
                            <input id="product-sku" class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 text-base placeholder:text-slate-400" placeholder="Masukkan kode atau scan" type="text" />
                            <button class="bg-primary/10 text-primary p-3 rounded-xl hover:bg-primary/20 transition-colors flex items-center justify-center" type="button">
                                <span class="material-symbols-outlined">barcode_scanner</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold px-0.5">Kategori</label>
                        <div class="relative">
                            <select id="category-select" class="w-full appearance-none rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 pr-10 text-base">
                                <option value="">Pilih Kategori</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold px-0.5">Harga Jual (Eceran)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">Rp</span>
                                <input id="product-price" class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 pl-10 pr-4 text-base placeholder:text-slate-400" placeholder="0" type="number" />
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold px-0.5">HPP (Modal)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">Rp</span>
                                <input id="product-hpp" class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 pl-10 pr-4 text-base placeholder:text-slate-400" placeholder="0" type="number" />
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Gunakan Stok</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Aktifkan jika produk memakai stok.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input id="track-stock" type="checkbox" class="sr-only peer" checked />
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:bg-primary"></div>
                            <div class="absolute left-1 top-1 size-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                        </label>
                    </div>
                    <div class="flex flex-col gap-1.5" id="stock-section">
                        <label class="text-slate-700 dark:text-slate-300 text-sm font-semibold px-0.5">Stok Awal</label>
                        <div class="flex items-center gap-3">
                            <button id="stock-minus" class="size-12 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-400 hover:bg-slate-50">
                                <span class="material-symbols-outlined">remove</span>
                            </button>
                            <input id="product-stock" class="flex-1 text-center rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 text-lg font-bold" type="number" value="0" />
                            <button id="stock-plus" class="size-12 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-400 hover:bg-slate-50">
                                <span class="material-symbols-outlined">add</span>
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3" id="price-tiers">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Tipe Harga</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Atur harga berdasarkan minimal qty.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input id="enable-tiers" type="checkbox" class="sr-only peer" />
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:bg-primary"></div>
                                <div class="absolute left-1 top-1 size-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-slate-400">Contoh: min 1 = ecer, min 25 = grosir.</span>
                            <button id="add-tier" class="text-xs font-semibold text-primary" type="button">Tambah</button>
                        </div>
                        <div id="tier-list" class="space-y-3 hidden"></div>
                    </div>
                    <button id="advanced-toggle" class="flex items-center justify-between w-full py-2 group" type="button" aria-expanded="false">
                        <span class="text-slate-500 dark:text-slate-400 text-sm font-medium group-hover:text-primary transition-colors">Opsi Lanjutan (Pajak, Diskon, dll)</span>
                        <span id="advanced-icon" class="material-symbols-outlined text-slate-400 group-hover:text-primary transition-colors">chevron_right</span>
                    </button>
                    <div id="advanced-options" class="hidden rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-3">
                        <div class="flex items-center gap-3">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300 w-20">Pajak</label>
                            <input id="product-tax" class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 h-10 px-3 text-sm" placeholder="0" type="number" />
                            <span class="text-xs text-slate-400">%</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-300 w-20">Diskon</label>
                            <input id="product-discount" class="flex-1 rounded-xl border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 h-10 px-3 text-sm" placeholder="0" type="number" />
                            <span class="text-xs text-slate-400">%</span>
                        </div>
                        <p class="text-xs text-slate-400">Opsi lanjutan akan diterapkan setelah modul pajak dan diskon aktif.</p>
                    </div>
                <p id="form-error" class="text-sm text-red-500 font-semibold hidden"></p>
            </div>
        </div>

        <div class="fixed bottom-0 left-1/2 w-full max-w-[430px] -translate-x-1/2 bg-white dark:bg-background-dark p-4 border-t border-slate-100 dark:border-slate-800 flex gap-3">
            <button class="flex-1 h-12 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" onclick="location.href='/menu/management'">
                Batal
            </button>
            <button id="save-product" class="flex-[2] h-12 rounded-xl bg-primary text-white font-bold hover:bg-primary/90 shadow-lg shadow-primary/20 transition-all active:scale-95">
                Simpan Produk
            </button>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    name: document.getElementById('product-name'),
                    sku: document.getElementById('product-sku'),
                    skuGenerate: document.getElementById('sku-generate'),
                    category: document.getElementById('category-select'),
                    price: document.getElementById('product-price'),
                    hpp: document.getElementById('product-hpp'),
                    stock: document.getElementById('product-stock'),
                    minus: document.getElementById('stock-minus'),
                    plus: document.getElementById('stock-plus'),
                    trackStock: document.getElementById('track-stock'),
                    stockSection: document.getElementById('stock-section'),
                    save: document.getElementById('save-product'),
                    error: document.getElementById('form-error'),
                    image: document.getElementById('product-image'),
                    imagePreview: document.getElementById('image-preview'),
                    imageOverlay: document.getElementById('image-overlay'),
                    advancedToggle: document.getElementById('advanced-toggle'),
                    advancedIcon: document.getElementById('advanced-icon'),
                    advancedOptions: document.getElementById('advanced-options'),
                    tierList: document.getElementById('tier-list'),
                    addTier: document.getElementById('add-tier'),
                    enableTiers: document.getElementById('enable-tiers'),
                };

                let imageBase64 = null;

                const generateSku = () => {
                    const rand = Math.random().toString(36).slice(2, 8).toUpperCase();
                    const date = new Date().toISOString().slice(2, 10).replace(/-/g, '');
                    return `SKU-${date}-${rand}`;
                };

                const ensureSku = () => {
                    if (!el.sku.value.trim()) {
                        el.sku.value = generateSku();
                    }
                };

                const showError = (message) => {
                    if (!message) {
                        el.error.classList.add('hidden');
                        el.error.textContent = '';
                        return;
                    }
                    el.error.textContent = message;
                    el.error.classList.remove('hidden');
                };

                const loadCategories = async () => {
                    const response = await fetch('/api/v1/categories', { headers });
                    if (!response.ok) {
                        return;
                    }
                    const data = await response.json();
                    const categories = data.categories || [];
                    categories.forEach((item) => {
                        const option = document.createElement('option');
                        option.value = item.id;
                        option.textContent = item.name;
                        el.category.appendChild(option);
                    });
                };

                const buildTierRow = (tier = {}) => {
                    const row = document.createElement('div');
                    row.className = 'grid grid-cols-12 gap-2 items-center';
                    row.innerHTML = `
                        <input class="col-span-5 rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 h-10 px-3 text-xs" placeholder="Nama" value="${tier.label || ''}" data-tier-label />
                        <input class="col-span-3 rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 h-10 px-2 text-xs" placeholder="Min" type="number" value="${tier.min_qty ?? ''}" data-tier-min />
                        <input class="col-span-3 rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 h-10 px-3 text-xs" placeholder="Harga" type="number" value="${tier.price ?? ''}" data-tier-price />
                        <button class="col-span-1 flex items-center justify-center size-8 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500" type="button" data-tier-remove>
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    `;
                    return row;
                };

                const addTier = (tier) => {
                    el.tierList.appendChild(buildTierRow(tier));
                };

                const collectTiers = () => {
                    const tiers = [];
                    el.tierList.querySelectorAll('div').forEach((row) => {
                        const label = row.querySelector('[data-tier-label]')?.value?.trim();
                        const minQty = row.querySelector('[data-tier-min]')?.value;
                        const price = row.querySelector('[data-tier-price]')?.value;
                        if (!label || !minQty || !price) {
                            return;
                        }
                        tiers.push({
                            label,
                            min_qty: Number(minQty),
                            price: Number(price),
                        });
                    });
                    return tiers.sort((a, b) => a.min_qty - b.min_qty);
                };

                const submit = async () => {
                    showError('');

                    ensureSku();

                    const payload = {
                        name: el.name.value.trim(),
                        sku: el.sku.value.trim(),
                        category_id: el.category.value || null,
                        price: Number(el.price.value || 0),
                        hpp_average: Number(el.hpp.value || 0),
                        initial_stock: el.trackStock.checked ? Number(el.stock.value || 0) : 0,
                        image_base64: imageBase64,
                        track_stock: el.trackStock.checked,
                        price_tiers: el.enableTiers.checked ? collectTiers() : [],
                    };

                    if (!payload.name) {
                        showError('Nama produk wajib diisi.');
                        return;
                    }

                    if (payload.price <= 0) {
                        showError('Harga jual harus lebih dari 0.');
                        return;
                    }

                    el.save.disabled = true;

                    try {
                        const response = await fetch('/api/v1/products', {
                            method: 'POST',
                            headers: {
                                ...headers,
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(payload),
                        });

                        if (!response.ok) {
                            const error = await response.json();
                            throw new Error(error?.message || 'Gagal menyimpan produk.');
                        }

                        el.name.value = '';
                        el.sku.value = '';
                        el.category.value = '';
                        el.price.value = '';
                        el.hpp.value = '';
                        el.stock.value = '0';
                        imageBase64 = null;
                        el.imagePreview.style.backgroundImage = '';
                        el.imageOverlay.classList.remove('hidden');
                        alert('Produk berhasil disimpan.');
                    } catch (error) {
                        showError(error.message || 'Gagal menyimpan produk.');
                    } finally {
                        el.save.disabled = false;
                    }
                };

                const handleImageChange = (event) => {
                    const file = event.target.files?.[0];
                    if (!file) {
                        return;
                    }
                    if (file.size > 5 * 1024 * 1024) {
                        showError('Ukuran foto maksimal 5MB.');
                        event.target.value = '';
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = () => {
                        imageBase64 = reader.result;
                        el.imagePreview.style.backgroundImage = `url('${reader.result}')`;
                        el.imageOverlay.classList.add('hidden');
                    };
                    reader.readAsDataURL(file);
                };

                el.minus.addEventListener('click', () => {
                    const value = Math.max(0, Number(el.stock.value || 0) - 1);
                    el.stock.value = value;
                });

                el.plus.addEventListener('click', () => {
                    const value = Number(el.stock.value || 0) + 1;
                    el.stock.value = value;
                });

                el.trackStock.addEventListener('change', () => {
                    const enabled = el.trackStock.checked;
                    el.stockSection.classList.toggle('opacity-50', !enabled);
                    el.stockSection.querySelectorAll('input, button').forEach((input) => {
                        input.disabled = !enabled;
                    });
                    if (!enabled) {
                        el.stock.value = '0';
                    }
                });

                el.enableTiers.addEventListener('change', () => {
                    const enabled = el.enableTiers.checked;
                    el.tierList.classList.toggle('hidden', !enabled);
                    el.addTier.disabled = !enabled;
                    if (!enabled) {
                        el.tierList.innerHTML = '';
                    } else if (!el.tierList.children.length) {
                        addTier({});
                    }
                });

                el.skuGenerate.addEventListener('click', () => {
                    el.sku.value = generateSku();
                });
                el.image.addEventListener('change', handleImageChange);

                el.advancedToggle.addEventListener('click', () => {
                    const isOpen = !el.advancedOptions.classList.contains('hidden');
                    el.advancedOptions.classList.toggle('hidden');
                    el.advancedToggle.setAttribute('aria-expanded', String(!isOpen));
                    el.advancedIcon.textContent = isOpen ? 'chevron_right' : 'expand_more';
                });

                el.addTier.addEventListener('click', () => {
                    addTier({});
                });

                el.tierList.addEventListener('click', (event) => {
                    const remove = event.target.closest('[data-tier-remove]');
                    if (remove) {
                        remove.closest('div')?.remove();
                    }
                });

                el.save.addEventListener('click', submit);

                ensureSku();

                loadCategories();
            })();
        </script>
@endsection
