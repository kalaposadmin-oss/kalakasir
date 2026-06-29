@extends('layouts.app')

@section('page_title', 'Input Produk Pertama')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[768px] flex-col overflow-x-hidden overflow-y-auto bg-white dark:bg-slate-900 shadow-xl app-bottom-safe-space')
@section('main_class', 'flex-1 overflow-y-auto p-0 app-bottom-safe-space')

@section('content')
    @php
        $categorySuggestions = ['Minuman', 'Makanan', 'Snack', 'Sembako', 'Bumbu', 'Layanan', 'Lainnya'];
        $stepFourProduct = session('onboarding.step4.product', []);
        $selectedCategory = old('category', $stepFourProduct['category'] ?? '');
        $inputBaseClass = 'w-full rounded-xl border bg-slate-50 dark:bg-slate-800 p-4 placeholder:text-slate-400';
        $inputErrorClass = 'border-red-300 bg-red-50/70 dark:border-red-500/60 dark:bg-red-950/20';
        $inputDefaultClass = 'border-slate-200 dark:border-slate-700';
    @endphp

    <div class="flex items-center bg-white dark:bg-slate-900 p-4 pb-2 justify-between sticky top-0 z-10 border-b border-slate-100 dark:border-slate-800">
        <a href="/register/step-3" class="text-slate-900 dark:text-slate-100 flex size-12 shrink-0 items-center justify-center cursor-pointer">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <h2 class="text-slate-900 dark:text-slate-100 text-lg font-bold leading-tight tracking-tight flex-1 text-center pr-12">Input Produk</h2>
    </div>

    <div class="bg-white px-6 py-5 dark:bg-slate-900">
        <div class="flex items-center justify-between gap-4">
            <p class="text-base font-medium leading-normal text-slate-900 dark:text-slate-100">Langkah 4: Produk Awal</p>
            <p class="text-sm font-normal leading-normal text-slate-500 dark:text-slate-400">4 dari 5</p>
        </div>
        <div class="mt-4 flex w-full flex-row items-center justify-center gap-2.5">
            <div class="h-2 w-8 rounded-full bg-primary"></div>
            <div class="h-2 w-8 rounded-full bg-primary"></div>
            <div class="h-2 w-8 rounded-full bg-primary"></div>
            <div class="h-2 w-8 rounded-full bg-primary"></div>
            <div class="h-2 w-8 rounded-full bg-primary/20"></div>
        </div>
    </div>

    <div class="px-6">
        <h3 class="tracking-tight text-2xl font-bold leading-tight pt-2">Input Produk Pertama Anda</h3>
        <p class="text-slate-600 dark:text-slate-400 text-sm leading-normal pb-6 pt-2">Lengkapi detail produk di bawah ini untuk memulai jualan pertama Anda.</p>
    </div>

    @if ($errors->any())
        <div class="mx-6 mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            Silakan lengkapi field wajib dan perbaiki input yang masih invalid.
        </div>
    @endif

    <form id="onboarding-step-4-form" method="POST" action="/register/step-4" class="flex flex-col gap-5 px-6 pb-8">
        @csrf
        <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold">Nama Produk <span class="text-rose-500">*</span></label>
            <input name="name" value="{{ old('name', $stepFourProduct['name'] ?? '') }}" class="{{ $inputBaseClass }} {{ $errors->has('name') ? $inputErrorClass : $inputDefaultClass }}" placeholder="Contoh: Kopi Susu Gula Aren" type="text" required aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" />
            @error('name')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-semibold">SKU / Kode</label>
                <input name="sku" value="{{ old('sku', $stepFourProduct['sku'] ?? '') }}" class="{{ $inputBaseClass }} {{ $errors->has('sku') ? $inputErrorClass : $inputDefaultClass }}" placeholder="KS-001" type="text" aria-invalid="{{ $errors->has('sku') ? 'true' : 'false' }}" />
                @error('sku')
                    <p class="text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <label class="text-sm font-semibold">Kategori</label>
                <span class="text-[11px] text-slate-400">Bisa ketik kategori baru</span>
            </div>
            <input id="product-category" name="category" value="{{ $selectedCategory }}" list="product-category-suggestions" class="{{ $inputBaseClass }} {{ $errors->has('category') ? $inputErrorClass : $inputDefaultClass }}" placeholder="Contoh: Beras, Kopi, Frozen Food, Jasa Cuci" type="text" aria-invalid="{{ $errors->has('category') ? 'true' : 'false' }}" />
            <datalist id="product-category-suggestions">
                @foreach ($categorySuggestions as $suggestion)
                    <option value="{{ $suggestion }}"></option>
                @endforeach
            </datalist>
            <div class="flex flex-wrap gap-2" id="category-quick-picks">
                @foreach ($categorySuggestions as $suggestion)
                    <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300" data-category-value="{{ $suggestion }}">{{ $suggestion }}</button>
                @endforeach
            </div>
            <p class="text-[11px] leading-relaxed text-slate-500">Pilih salah satu saran atau langsung ketik kategori Anda sendiri. Sistem akan membuat kategori baru otomatis jika belum ada.</p>
            @error('category')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold">Harga Pokok (HPP)</label>
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-medium">Rp</span>
                <input name="hpp_average" value="{{ old('hpp_average', $stepFourProduct['hpp_average'] ?? '') }}" class="{{ $inputBaseClass }} {{ $errors->has('hpp_average') ? $inputErrorClass : $inputDefaultClass }} pl-12" placeholder="Kosongkan jika HPP akan terbentuk dari pembelian/produksi" type="number" step="0.01" min="0" aria-invalid="{{ $errors->has('hpp_average') ? 'true' : 'false' }}" />
            </div>
            <p class="text-[11px] leading-relaxed text-slate-500">Opsional. Boleh dikosongkan jika HPP baru akan terbentuk saat pembelian atau produksi pertama.</p>
            @error('hpp_average')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold">Harga Jual</label>
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-medium">Rp</span>
                <input name="price" value="{{ old('price', $stepFourProduct['price'] ?? '') }}" class="{{ $inputBaseClass }} {{ $errors->has('price') ? $inputErrorClass : $inputDefaultClass }} pl-12" placeholder="Kosongkan jika harga jual akan diatur nanti" type="number" step="0.01" min="0" aria-invalid="{{ $errors->has('price') ? 'true' : 'false' }}" />
            </div>
            <p class="text-[11px] leading-relaxed text-slate-500">Opsional. Boleh dikosongkan jika harga jual akan ditentukan setelah onboarding.</p>
            @error('price')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-4 space-y-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold">Produk Bervarian</p>
                    <p class="text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">Aktifkan jika produk onboarding perlu stok awal per variant.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input id="enable-variants" type="checkbox" class="sr-only peer" {{ old('variants') ? 'checked' : '' }} />
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:bg-primary"></div>
                    <div class="absolute left-1 top-1 size-4 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                </label>
            </div>
            <div id="variant-editor" class="hidden space-y-3">
                <div class="rounded-xl bg-sky-50 text-sky-900 px-3 py-3 text-xs leading-relaxed">
                    Saat mode variant aktif, form akan mengirim <span class="font-semibold">variants[]</span> dan <span class="font-semibold">initial_stock_lines[]</span>. Payload tidak lagi mengirim stok awal scalar ke bucket produk umum.
                </div>
                <div class="flex items-center justify-between">
                    <p id="variant-editor-hint" class="text-[11px] text-slate-500 dark:text-slate-400">Belum ada variant.</p>
                    <button id="add-variant-line" class="text-xs font-semibold text-primary" type="button">Tambah Variant</button>
                </div>
                <div id="variant-line-list" class="space-y-3"></div>
            </div>
        </div>

        <div id="stock-scalar-section" class="flex flex-col gap-1.5">
            <label class="text-sm font-semibold">Stok Awal <span class="text-rose-500">*</span></label>
            <div class="flex items-center gap-4">
                <button id="initial-stock-minus" class="size-12 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100" type="button">
                    <span class="material-symbols-outlined">remove</span>
                </button>
                <input id="initial-stock" name="initial_stock" class="flex-1 text-center rounded-xl border bg-slate-50 dark:bg-slate-800 p-4 font-bold {{ $errors->has('initial_stock') ? $inputErrorClass : $inputDefaultClass }}" type="number" step="0.01" min="0" value="{{ old('initial_stock', $stepFourProduct['initial_stock'] ?? '0') }}" required aria-invalid="{{ $errors->has('initial_stock') ? 'true' : 'false' }}" />
                <button id="initial-stock-plus" class="size-12 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100" type="button">
                    <span class="material-symbols-outlined">add</span>
                </button>
            </div>
            @error('initial_stock')
                <p class="text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div id="variant-stock-section" class="hidden flex flex-col gap-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-4">
            <div>
                <p class="text-sm font-semibold">Stok Awal Per Variant</p>
                <p id="variant-stock-hint" class="text-[11px] text-slate-500 dark:text-slate-400">Tambahkan variant untuk mengisi stok awal per bucket.</p>
            </div>
            <div id="variant-stock-line-list" class="space-y-3"></div>
        </div>

        <div id="variant-hidden-inputs"></div>
    </form>

    <div class="sticky bottom-0 mt-auto w-full p-6 bg-white/95 dark:bg-slate-900/95 backdrop-blur border-t border-slate-100 dark:border-slate-800 flex flex-col gap-3 app-bottom-safe-footer">
        <button form="onboarding-step-4-form" type="submit" data-submit-button class="w-full py-4 bg-primary text-white rounded-xl font-bold text-base shadow-lg shadow-primary/20 text-center disabled:cursor-not-allowed disabled:opacity-70">Lanjut ke Rekap</button>
        <form id="onboarding-step-4-skip-form" method="POST" action="/register/step-4">
            @csrf
            <input type="hidden" name="skip_product" value="1" />
            <button type="submit" data-skip-button class="w-full py-4 bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 rounded-xl font-medium text-base text-center disabled:cursor-not-allowed disabled:opacity-70">Lewati dan Lanjut ke Rekap</button>
        </form>
    </div>

    <script>
        (() => {
            const categoryInput = document.getElementById('product-category');
            const quickPicks = document.querySelectorAll('[data-category-value]');
            const form = document.getElementById('onboarding-step-4-form');
            const skipForm = document.getElementById('onboarding-step-4-skip-form');
            const enableVariants = document.getElementById('enable-variants');
            const variantEditor = document.getElementById('variant-editor');
            const variantEditorHint = document.getElementById('variant-editor-hint');
            const addVariantLine = document.getElementById('add-variant-line');
            const variantLineList = document.getElementById('variant-line-list');
            const stockScalarSection = document.getElementById('stock-scalar-section');
            const initialStockInput = document.getElementById('initial-stock');
            const initialStockMinus = document.getElementById('initial-stock-minus');
            const initialStockPlus = document.getElementById('initial-stock-plus');
            const variantStockSection = document.getElementById('variant-stock-section');
            const variantStockHint = document.getElementById('variant-stock-hint');
            const variantStockLineList = document.getElementById('variant-stock-line-list');
            const hiddenInputs = document.getElementById('variant-hidden-inputs');
            const oldVariants = @json(old('variants', $stepFourProduct['variants'] ?? []));
            const oldInitialStockLines = @json(old('initial_stock_lines', $stepFourProduct['initial_stock_lines'] ?? []));
            const submitButtons = document.querySelectorAll('[data-submit-button], [data-skip-button]');
            let submitLocked = false;

            if (!categoryInput || !quickPicks.length || !form || !enableVariants) {
                return;
            }

            const lockSubmission = () => {
                if (submitLocked) {
                    return false;
                }

                submitLocked = true;
                submitButtons.forEach((button) => {
                    button.disabled = true;
                });

                return true;
            };

            const unlockSubmission = () => {
                submitLocked = false;
                submitButtons.forEach((button) => {
                    button.disabled = false;
                });
            };

            const generateUuid = () => {
                if (window.crypto?.randomUUID) {
                    return window.crypto.randomUUID();
                }

                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
                    const random = Math.random() * 16 | 0;
                    const value = char === 'x' ? random : (random & 0x3 | 0x8);
                    return value.toString(16);
                });
            };

            const setVariantHint = (message) => {
                variantEditorHint.textContent = message;
            };

            const setVariantStockHint = (message) => {
                variantStockHint.textContent = message;
            };

            const variantRowsData = () => Array.from(variantLineList.children).map((row, index) => ({
                id: row.querySelector('[data-variant-id]')?.value || '',
                sku: row.querySelector('[data-variant-sku]')?.value?.trim() || '',
                name: row.querySelector('[data-variant-name]')?.value?.trim() || `Variant ${index + 1}`,
            }));

            const createVariantRow = (variant = {}) => {
                const row = document.createElement('div');
                row.className = 'rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3 space-y-3';
                row.innerHTML = `
                    <input type="hidden" data-variant-id value="${variant.id || generateUuid()}" />
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Variant</p>
                        <button class="text-xs font-semibold text-rose-500" type="button" data-variant-remove>Hapus</button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[11px] font-semibold text-slate-500">SKU Variant</label>
                            <input class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-3 text-sm" data-variant-sku type="text" value="${variant.sku || ''}" placeholder="Contoh: VAR-MERAH" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[11px] font-semibold text-slate-500">Nama Variant</label>
                            <input class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-3 text-sm" data-variant-name type="text" value="${variant.name || ''}" placeholder="Contoh: Merah" />
                        </div>
                    </div>
                `;

                return row;
            };

            const buildVariantStockRow = (variant, currentValue = {}) => {
                const row = document.createElement('div');
                row.className = 'rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3 grid grid-cols-12 gap-3 items-center';
                row.innerHTML = `
                    <input type="hidden" data-stock-variant-id value="${variant.id}" />
                    <div class="col-span-5">
                        <p class="text-sm font-semibold">${variant.name || 'Variant'}</p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">${variant.sku || '-'}</p>
                    </div>
                    <div class="col-span-3 flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold text-slate-500">Qty</label>
                        <input class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-3 text-sm" data-stock-qty type="number" min="0" step="0.0001" value="${currentValue.qty ?? ''}" placeholder="0" />
                    </div>
                    <div class="col-span-4 flex flex-col gap-1.5">
                        <label class="text-[11px] font-semibold text-slate-500">Unit Cost</label>
                        <input class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-3 text-sm" data-stock-unit-cost type="number" min="0" step="0.01" value="${currentValue.unit_cost ?? ''}" placeholder="HPP default" />
                    </div>
                `;

                return row;
            };

            const renderVariantStockLines = () => {
                const existingValues = new Map(
                    Array.from(variantStockLineList.children).map((row) => ([
                        row.querySelector('[data-stock-variant-id]')?.value || '',
                        {
                            qty: row.querySelector('[data-stock-qty]')?.value || '',
                            unit_cost: row.querySelector('[data-stock-unit-cost]')?.value || '',
                        },
                    ]))
                );

                variantStockLineList.innerHTML = '';
                const variants = variantRowsData();
                if (!variants.length) {
                    setVariantStockHint('Tambahkan variant untuk mengisi stok awal per bucket.');
                    return;
                }

                variants.forEach((variant) => {
                    const currentValue = existingValues.get(variant.id)
                        || oldInitialStockLines.find((line) => String(line.product_variant_id || '') === String(variant.id))
                        || {};
                    variantStockLineList.appendChild(buildVariantStockRow(variant, currentValue));
                });

                setVariantStockHint(`${variants.length} bucket variant siap diisi.`);
            };

            const updateVariantUi = () => {
                const enabled = enableVariants.checked;
                variantEditor.classList.toggle('hidden', !enabled);
                stockScalarSection.classList.toggle('hidden', enabled);
                variantStockSection.classList.toggle('hidden', !enabled);

                if (!enabled) {
                    initialStockInput.disabled = false;
                    variantLineList.innerHTML = '';
                    variantStockLineList.innerHTML = '';
                    hiddenInputs.innerHTML = '';
                    setVariantHint('Mode variant nonaktif.');
                    setVariantStockHint('Tambahkan variant untuk mengisi stok awal per bucket.');
                    return;
                }

                initialStockInput.value = '0';
                setVariantHint(variantLineList.children.length ? `${variantLineList.children.length} variant aktif.` : 'Belum ada variant.');
                renderVariantStockLines();
            };

            const syncVariantState = () => {
                const count = variantLineList.children.length;
                setVariantHint(count ? `${count} variant aktif.` : 'Belum ada variant.');
                renderVariantStockLines();
            };

            const collectVariantPayload = () => {
                const rows = variantRowsData();
                if (!rows.length) {
                    throw new Error('Minimal satu variant wajib diisi saat mode variant aktif.');
                }

                const seenSkus = new Set();
                return rows.map((variant, index) => {
                    if (!variant.sku || !variant.name) {
                        throw new Error(`Variant #${index + 1}: nama dan SKU wajib diisi.`);
                    }

                    const skuKey = variant.sku.toLowerCase();
                    if (seenSkus.has(skuKey)) {
                        throw new Error(`Variant #${index + 1}: SKU variant duplikat.`);
                    }

                    seenSkus.add(skuKey);
                    return variant;
                });
            };

            const collectVariantStockPayload = (variants) => {
                const variantIds = new Set(variants.map((variant) => String(variant.id)));
                return Array.from(variantStockLineList.children).map((row, index) => {
                    const variantId = row.querySelector('[data-stock-variant-id]')?.value || '';
                    const qty = Number(row.querySelector('[data-stock-qty]')?.value || 0);
                    const unitCostRaw = row.querySelector('[data-stock-unit-cost]')?.value || '';

                    if (!variantIds.has(String(variantId))) {
                        throw new Error(`Line stok variant #${index + 1}: variant tidak valid.`);
                    }

                    return {
                        product_variant_id: variantId,
                        qty,
                        unit_cost: unitCostRaw === '' ? '' : unitCostRaw,
                    };
                }).filter((line) => line.qty > 0);
            };

            const appendHiddenInput = (name, value) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                hiddenInputs.appendChild(input);
            };

            quickPicks.forEach((button) => {
                button.addEventListener('click', () => {
                    categoryInput.value = button.dataset.categoryValue || '';
                    categoryInput.focus();
                });
            });

            initialStockMinus?.addEventListener('click', () => {
                initialStockInput.value = String(Math.max(0, Number(initialStockInput.value || 0) - 1));
            });

            initialStockPlus?.addEventListener('click', () => {
                initialStockInput.value = String(Number(initialStockInput.value || 0) + 1);
            });

            enableVariants.addEventListener('change', () => {
                updateVariantUi();
            });

            addVariantLine.addEventListener('click', () => {
                variantLineList.appendChild(createVariantRow({}));
                syncVariantState();
            });

            variantLineList.addEventListener('click', (event) => {
                const removeButton = event.target.closest('[data-variant-remove]');
                if (!removeButton) {
                    return;
                }

                removeButton.closest('.rounded-xl')?.remove();
                syncVariantState();
            });

            variantLineList.addEventListener('input', () => {
                syncVariantState();
            });

            form.addEventListener('submit', (event) => {
                if (!lockSubmission()) {
                    event.preventDefault();
                    return;
                }

                hiddenInputs.innerHTML = '';

                if (!enableVariants.checked) {
                    return;
                }

                try {
                    const variants = collectVariantPayload();
                    const stockLines = collectVariantStockPayload(variants);
                    initialStockInput.value = '0';

                    variants.forEach((variant, index) => {
                        appendHiddenInput(`variants[${index}][id]`, variant.id);
                        appendHiddenInput(`variants[${index}][sku]`, variant.sku);
                        appendHiddenInput(`variants[${index}][name]`, variant.name);
                    });

                    stockLines.forEach((line, index) => {
                        appendHiddenInput(`initial_stock_lines[${index}][product_variant_id]`, line.product_variant_id);
                        appendHiddenInput(`initial_stock_lines[${index}][qty]`, String(line.qty));
                        if (line.unit_cost !== '') {
                            appendHiddenInput(`initial_stock_lines[${index}][unit_cost]`, String(line.unit_cost));
                        }
                    });
                } catch (error) {
                    unlockSubmission();
                    event.preventDefault();
                    alert(error.message || 'Payload variant onboarding tidak valid.');
                }
            });

            skipForm?.addEventListener('submit', (event) => {
                if (!lockSubmission()) {
                    event.preventDefault();
                }
            });

            if (Array.isArray(oldVariants) && oldVariants.length) {
                oldVariants.forEach((variant) => {
                    variantLineList.appendChild(createVariantRow(variant));
                });
            }

            updateVariantUi();
        })();
    </script>
@endsection
