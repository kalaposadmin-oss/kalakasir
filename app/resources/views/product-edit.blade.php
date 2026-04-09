@extends('layouts.app')

@php
    $productEditTenant = \App\Support\TenantContext::get();
    $productEditUser = auth()->user();
    $productEditRole = null;

    if (! $productEditTenant && filled($productEditUser?->tenant_id)) {
        $productEditTenant = \App\Models\Tenant::query()->find($productEditUser->tenant_id);
    }

    if ($productEditTenant && $productEditUser) {
        $productEditRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $productEditTenant->id, (string) $productEditUser->id);

        if (! $productEditRole && filled($productEditUser->role ?? null)) {
            $productEditRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $productEditUser->role);
        }
    }

    abort_unless(in_array($productEditRole, ['admin', 'owner', 'superadmin'], true), 403);
@endphp

@php
    $productEditFrom = trim((string) request()->query('from', ''));
    $productEditBackUrl = '/backoffice/products';
    if ($productEditFrom !== '') {
        if (str_starts_with($productEditFrom, '/pos/')) {
            $productEditBackUrl = '/portal/switch/pos?next='.rawurlencode($productEditFrom);
        } elseif (str_starts_with($productEditFrom, '/backoffice/')) {
            $productEditBackUrl = $productEditFrom;
        }
    }
@endphp

@section('page_title', 'Ubah Produk')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex flex-1 flex-col overflow-hidden p-0')

@section('header')
    <x-page-header title="Ubah Produk" :backUrl="$productEditBackUrl" :showMenuButton="true" />
@endsection

@section('content')
        <div class="flex-1 overflow-y-auto px-4 py-4 pb-32">
            <section class="bg-white rounded-2xl shadow-xl ring-1 ring-slate-200 overflow-hidden">
                <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <h2 class="font-bold text-slate-800">Ubah Produk</h2>
                    <span class="text-xs bg-slate-200 px-2 py-1 rounded font-medium" id="edit-id">ID: -</span>
                </div>
                <form id="edit-form" class="p-4 space-y-5">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase">Nama Produk</label>
                        <input id="edit-name" class="w-full px-4 py-3 rounded-lg border-slate-200 focus:ring-primary focus:border-primary" type="text" value="" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase">SKU</label>
                            <input id="edit-sku" class="w-full px-4 py-3 rounded-lg border-slate-200 focus:ring-primary focus:border-primary" type="text" value="" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase">Harga (Rp)</label>
                            <input id="edit-price" class="w-full px-4 py-3 rounded-lg border-slate-200 focus:ring-primary focus:border-primary font-mono" type="number" value="" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase">Kategori</label>
                        <select id="edit-category" class="w-full px-4 py-3 rounded-lg border-slate-200 focus:ring-primary focus:border-primary appearance-none"></select>
                    </div>
                    <div id="edit-stock-section" class="space-y-1.5 hidden">
                        <label class="text-xs font-bold text-slate-500 uppercase">Tambah Stok Awal</label>
                        <div class="flex items-center gap-3">
                            <button id="edit-stock-minus" class="size-12 rounded-xl border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50" type="button">
                                <span class="material-symbols-outlined">remove</span>
                            </button>
                            <input id="edit-stock" class="flex-1 text-center rounded-xl border-slate-200 bg-white text-slate-900 focus:border-primary focus:ring-primary h-12 px-4 text-lg font-bold" type="number" min="0" step="0.0001" value="0" />
                            <button id="edit-stock-plus" class="size-12 rounded-xl border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50" type="button">
                                <span class="material-symbols-outlined">add</span>
                            </button>
                        </div>
                    </div>
                    <div id="edit-variant-stock-section" class="hidden rounded-xl border border-slate-100 bg-slate-50/60 p-4 space-y-3">
                        <div>
                            <p class="text-sm font-bold text-slate-800">Tambah Stok Awal Per Variant</p>
                            <p id="edit-variant-stock-hint" class="text-xs text-slate-500">Belum ada variant aktif.</p>
                        </div>
                        <div id="edit-variant-stock-line-list" class="space-y-3"></div>
                    </div>
                    <div id="edit-bom-card" class="rounded-xl border border-slate-100 bg-slate-50/60 p-4 space-y-4 hidden">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-bold text-slate-800">Gunakan BOM / Recipe</p>
                                <p class="text-xs text-slate-500">Pilih bahan baku dan variant raw material secara eksplisit per line.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input id="edit-enable-bom" type="checkbox" class="sr-only peer" />
                                <div class="w-11 h-6 rounded-full bg-slate-200 peer-checked:bg-primary"></div>
                                <div class="absolute left-1 top-1 size-4 rounded-full bg-white transition-transform peer-checked:translate-x-5"></div>
                            </label>
                        </div>
                        <div id="edit-bom-editor" class="hidden space-y-3">
                            <div class="rounded-xl bg-amber-50 px-3 py-3 text-xs leading-relaxed text-amber-900">
                                Untuk raw material non-variant, payload akan mengirim <span class="font-semibold">product_variant_id = null</span>. Untuk raw material variant-capable, variant wajib dipilih.
                            </div>
                            <div class="flex items-center justify-between">
                                <p id="edit-bom-hint" class="text-xs text-slate-500">Belum ada line BOM.</p>
                                <button id="edit-add-bom-line" class="text-xs font-semibold text-primary" type="button">Tambah Bahan Baku</button>
                            </div>
                            <div id="edit-bom-line-list" class="space-y-3"></div>
                        </div>
                    </div>
                    <div id="edit-bom-unavailable" class="hidden rounded-xl border border-slate-100 bg-slate-50/60 p-4 text-xs leading-relaxed text-slate-500">
                        Editor BOM hanya tersedia untuk produk finished good di layar ini. Produk raw material atau service tetap mempertahankan kontrak lamanya.
                    </div>
                    <p id="form-error" class="text-sm text-red-500 font-semibold hidden"></p>
                    <div class="flex gap-3 pt-2">
                        <button class="flex-1 py-3 px-4 rounded-xl border border-slate-200 text-slate-700 font-bold hover:bg-slate-50" type="button" id="cancel-edit">Batal</button>
                        <button class="flex-1 py-3 px-4 rounded-xl border border-rose-200 text-rose-600 font-bold hover:bg-rose-50" type="button" id="delete-product">Hapus</button>
                        <button class="flex-[2] py-3 px-4 rounded-xl bg-primary text-white font-bold shadow-lg shadow-primary/20 hover:opacity-90" type="submit">Simpan Perubahan</button>
                    </div>
                </form>
            </section>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
                const productId = document.querySelector('meta[name="product-id"]')?.content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    editId: document.getElementById('edit-id'),
                    editName: document.getElementById('edit-name'),
                    editSku: document.getElementById('edit-sku'),
                    editPrice: document.getElementById('edit-price'),
                    editCategory: document.getElementById('edit-category'),
                    stockSection: document.getElementById('edit-stock-section'),
                    stock: document.getElementById('edit-stock'),
                    stockMinus: document.getElementById('edit-stock-minus'),
                    stockPlus: document.getElementById('edit-stock-plus'),
                    variantStockSection: document.getElementById('edit-variant-stock-section'),
                    variantStockHint: document.getElementById('edit-variant-stock-hint'),
                    variantStockLineList: document.getElementById('edit-variant-stock-line-list'),
                    enableBom: document.getElementById('edit-enable-bom'),
                    bomCard: document.getElementById('edit-bom-card'),
                    bomUnavailable: document.getElementById('edit-bom-unavailable'),
                    bomEditor: document.getElementById('edit-bom-editor'),
                    bomHint: document.getElementById('edit-bom-hint'),
                    bomLineList: document.getElementById('edit-bom-line-list'),
                    addBomLine: document.getElementById('edit-add-bom-line'),
                    form: document.getElementById('edit-form'),
                    cancel: document.getElementById('cancel-edit'),
                    error: document.getElementById('form-error'),
                    deleteProduct: document.getElementById('delete-product'),
                };

                let currentProduct = null;
                let rawMaterialProducts = [];
                const productDetailCache = new Map();

                const showError = (message) => {
                    if (!message) {
                        el.error.classList.add('hidden');
                        el.error.textContent = '';
                        return;
                    }
                    el.error.textContent = message;
                    el.error.classList.remove('hidden');
                };

                const goBack = () => {
                    window.location.href = @json($productEditBackUrl);
                };

                const loadCategories = async () => {
                    const response = await fetch('/api/v1/categories', { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const data = await response.json();
                    return data.categories || [];
                };

                const loadRawMaterialProducts = async () => {
                    const response = await fetch('/api/v1/products', { headers });
                    if (!response.ok) {
                        return [];
                    }

                    const data = await response.json();
                    return (data.products || [])
                        .filter((product) => String(product.item_type || '') === 'raw_material')
                        .sort((left, right) => String(left.name || '').localeCompare(String(right.name || '')));
                };

                const loadProductDetailCached = async (targetProductId) => {
                    if (!targetProductId) {
                        return null;
                    }

                    if (productDetailCache.has(targetProductId)) {
                        return productDetailCache.get(targetProductId);
                    }

                    const response = await fetch(`/api/v1/products/${targetProductId}`, { headers });
                    if (!response.ok) {
                        throw new Error('Gagal memuat detail produk untuk BOM.');
                    }

                    const data = await response.json();
                    const product = data.product || null;
                    productDetailCache.set(targetProductId, product);

                    return product;
                };

                const bomProductOptionsHtml = (selectedId = '') => {
                    const options = ['<option value="">Pilih bahan baku</option>'];

                    rawMaterialProducts.forEach((product) => {
                        const selected = String(product.id) === String(selectedId || '') ? 'selected' : '';
                        const suffix = product.sku ? ` (${product.sku})` : '';
                        options.push(`<option value="${product.id}" ${selected}>${product.name}${suffix}</option>`);
                    });

                    return options.join('');
                };

                const setBomHint = (message) => {
                    el.bomHint.textContent = message;
                };

                const setVariantStockHint = (message) => {
                    el.variantStockHint.textContent = message;
                };

                const buildVariantStockRow = (variant, line = {}) => {
                    const row = document.createElement('div');
                    row.className = 'rounded-xl border border-slate-200 bg-white p-3 grid grid-cols-12 gap-3 items-center';
                    row.innerHTML = `
                        <input type="hidden" data-stock-variant-id value="${variant.id}" />
                        <div class="col-span-5">
                            <p class="text-sm font-semibold text-slate-900">${variant.name || 'Variant'}</p>
                            <p class="text-xs text-slate-500">${variant.sku || '-'}</p>
                        </div>
                        <div class="col-span-3 space-y-1.5">
                            <label class="text-[11px] font-semibold text-slate-500">Qty</label>
                            <input class="w-full rounded-xl border-slate-200 text-sm text-slate-900" data-stock-qty type="number" min="0" step="0.0001" value="${line.qty ?? ''}" placeholder="0" />
                        </div>
                        <div class="col-span-4 space-y-1.5">
                            <label class="text-[11px] font-semibold text-slate-500">Unit Cost</label>
                            <input class="w-full rounded-xl border-slate-200 text-sm text-slate-900" data-stock-unit-cost type="number" min="0" step="0.01" value="${line.unit_cost ?? ''}" placeholder="HPP default" />
                        </div>
                    `;

                    return row;
                };

                const renderVariantStockLines = (product) => {
                    el.variantStockLineList.innerHTML = '';
                    const variants = Array.isArray(product?.product_variants) ? product.product_variants : [];

                    if (!variants.length) {
                        setVariantStockHint('Belum ada variant aktif.');
                        return;
                    }

                    variants.forEach((variant) => {
                        el.variantStockLineList.appendChild(buildVariantStockRow(variant, { unit_cost: product?.hpp_average ?? '' }));
                    });
                    setVariantStockHint(`${variants.length} bucket variant siap diisi.`);
                };

                const updateInitialStockEditors = (product) => {
                    const trackStock = Boolean(product?.track_stock);
                    const hasVariants = Boolean(product?.has_variants);

                    el.stockSection.classList.toggle('hidden', !trackStock || hasVariants);
                    el.variantStockSection.classList.toggle('hidden', !trackStock || !hasVariants);

                    if (!trackStock) {
                        el.stock.value = '0';
                        el.variantStockLineList.innerHTML = '';
                        return;
                    }

                    if (hasVariants) {
                        renderVariantStockLines(product);
                    }
                };

                const collectVariantStockLines = (product) => {
                    const knownVariantIds = new Set((product?.product_variants || []).map((variant) => String(variant.id)));

                    return Array.from(el.variantStockLineList.children).map((row, index) => {
                        const variantId = row.querySelector('[data-stock-variant-id]')?.value || '';
                        const qty = Number(row.querySelector('[data-stock-qty]')?.value || 0);
                        const unitCostRaw = row.querySelector('[data-stock-unit-cost]')?.value || '';

                        if (!knownVariantIds.has(String(variantId))) {
                            throw new Error(`Line stok variant #${index + 1}: variant tidak valid.`);
                        }

                        return {
                            product_variant_id: variantId,
                            qty,
                            unit_cost: unitCostRaw === '' ? null : Number(unitCostRaw),
                        };
                    }).filter((line) => line.qty > 0);
                };

                const updateBomLineCountHint = () => {
                    if (!el.enableBom.checked) {
                        setBomHint('Belum ada line BOM.');
                        return;
                    }

                    if (!el.bomLineList.children.length) {
                        setBomHint(rawMaterialProducts.length
                            ? 'Belum ada line BOM.'
                            : 'Belum ada produk raw material yang bisa dipilih.');
                        return;
                    }

                    setBomHint(`${el.bomLineList.children.length} line BOM aktif.`);
                };

                const updateBomEditorVisibility = () => {
                    if (!currentProduct || String(currentProduct.item_type || '') !== 'finished_good') {
                        el.bomCard.classList.add('hidden');
                        el.bomUnavailable.classList.remove('hidden');
                        return;
                    }

                    el.bomUnavailable.classList.add('hidden');
                    el.bomCard.classList.remove('hidden');
                    el.bomEditor.classList.toggle('hidden', !el.enableBom.checked);

                    if (!el.enableBom.checked) {
                        el.bomLineList.innerHTML = '';
                        setBomHint('Belum ada line BOM.');
                    }
                };

                const createBomLineRow = (line = {}) => {
                    const row = document.createElement('div');
                    row.className = 'rounded-xl border border-slate-200 bg-white p-3 space-y-3';
                    row.innerHTML = `
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Line BOM</p>
                            <button class="text-xs font-semibold text-rose-500" type="button" data-bom-remove>Hapus</button>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-500">Raw Material</label>
                            <select class="w-full rounded-xl border-slate-200 text-sm text-slate-900" data-bom-product>
                                ${bomProductOptionsHtml(line.product_id || '')}
                            </select>
                        </div>
                        <div class="space-y-1.5 hidden" data-bom-variant-wrap>
                            <label class="text-xs font-semibold text-slate-500">Variant Raw Material</label>
                            <select class="w-full rounded-xl border-slate-200 text-sm text-slate-900" data-bom-variant>
                                <option value="">Pilih variant</option>
                            </select>
                            <p class="text-[11px] text-slate-400" data-bom-variant-hint></p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-500">Qty per 1 output</label>
                            <input class="w-full rounded-xl border-slate-200 text-sm text-slate-900" data-bom-qty type="number" min="0.0001" step="0.0001" value="${line.qty ?? ''}" />
                        </div>
                    `;

                    return row;
                };

                const configureBomVariantField = async (row, targetProductId, selectedVariantId = null) => {
                    const wrap = row.querySelector('[data-bom-variant-wrap]');
                    const variantSelect = row.querySelector('[data-bom-variant]');
                    const hint = row.querySelector('[data-bom-variant-hint]');

                    if (!targetProductId) {
                        wrap.classList.add('hidden');
                        variantSelect.innerHTML = '<option value="">Pilih variant</option>';
                        variantSelect.value = '';
                        hint.textContent = '';
                        return;
                    }

                    const productDetail = await loadProductDetailCached(targetProductId);
                    const variants = Array.isArray(productDetail?.product_variants) ? productDetail.product_variants : [];

                    if (!variants.length) {
                        wrap.classList.add('hidden');
                        variantSelect.innerHTML = '<option value="">Tidak perlu variant</option>';
                        variantSelect.value = '';
                        hint.textContent = 'Raw material ini non-variant, product_variant_id akan dikirim sebagai null.';
                        return;
                    }

                    wrap.classList.remove('hidden');
                    variantSelect.innerHTML = '<option value="">Pilih variant</option>';
                    variants.forEach((variant) => {
                        const option = document.createElement('option');
                        option.value = variant.id;
                        option.textContent = variant.sku ? `${variant.name} (${variant.sku})` : variant.name;
                        variantSelect.appendChild(option);
                    });

                    const stillValid = variants.some((variant) => String(variant.id) === String(selectedVariantId || ''));
                    variantSelect.value = stillValid ? String(selectedVariantId) : '';
                    hint.textContent = 'Raw material ini punya variants. Variant wajib dipilih eksplisit.';
                };

                const appendBomLine = async (line = {}) => {
                    const row = createBomLineRow(line);
                    el.bomLineList.appendChild(row);
                    await configureBomVariantField(row, line.product_id || '', line.product_variant_id || null);
                    updateBomLineCountHint();
                };

                const collectBomItems = () => {
                    const rows = Array.from(el.bomLineList.children);
                    if (!rows.length) {
                        throw new Error('Minimal satu line BOM wajib diisi saat mode BOM aktif.');
                    }

                    return rows.map((row, index) => {
                        const productId = row.querySelector('[data-bom-product]')?.value || '';
                        const variantWrap = row.querySelector('[data-bom-variant-wrap]');
                        const variantRequired = variantWrap && !variantWrap.classList.contains('hidden');
                        const variantId = row.querySelector('[data-bom-variant]')?.value || '';
                        const qty = Number(row.querySelector('[data-bom-qty]')?.value || 0);

                        if (!productId) {
                            throw new Error(`Line BOM #${index + 1}: raw material wajib dipilih.`);
                        }

                        if (!(qty > 0)) {
                            throw new Error(`Line BOM #${index + 1}: qty harus lebih dari 0.`);
                        }

                        if (variantRequired && !variantId) {
                            throw new Error(`Line BOM #${index + 1}: variant raw material wajib dipilih.`);
                        }

                        return {
                            product_id: productId,
                            product_variant_id: variantRequired ? variantId : null,
                            qty,
                        };
                    });
                };

                const loadProduct = async () => {
                    const response = await fetch(`/api/v1/products/${productId}`, { headers });
                    if (!response.ok) {
                        throw new Error('Produk tidak ditemukan.');
                    }
                    const data = await response.json();
                    return data.product;
                };

                const renderProduct = (product) => {
                    currentProduct = product;
                    el.editId.textContent = `ID: ${String(product.id).slice(0, 8).toUpperCase()}`;
                    el.editName.value = product.name || '';
                    el.editSku.value = product.sku || '';
                    el.editPrice.value = product.price || 0;
                    el.editCategory.value = product.category_id || '';
                    el.stock.value = '0';
                    updateInitialStockEditors(product);

                    const isFinishedGood = String(product.item_type || '') === 'finished_good';
                    const isBomMode = isFinishedGood && String(product.product_mode || '') === 'bom';
                    el.enableBom.checked = isBomMode;
                    updateBomEditorVisibility();

                    if (!isBomMode) {
                        updateBomLineCountHint();
                        return;
                    }

                    el.bomLineList.innerHTML = '';
                    const bomItems = Array.isArray(product.bom_items) ? product.bom_items : [];
                    if (!bomItems.length) {
                        updateBomLineCountHint();
                        return;
                    }

                    bomItems.reduce((promise, line) => {
                        return promise.then(() => appendBomLine(line));
                    }, Promise.resolve()).catch((error) => {
                        showError(error.message || 'Gagal memuat BOM produk.');
                    });
                };

                el.cancel.addEventListener('click', goBack);

                el.stockMinus.addEventListener('click', () => {
                    const value = Math.max(0, Number(el.stock.value || 0) - 1);
                    el.stock.value = value;
                });

                el.stockPlus.addEventListener('click', () => {
                    const value = Number(el.stock.value || 0) + 1;
                    el.stock.value = value;
                });

                el.enableBom.addEventListener('change', () => {
                    if (!currentProduct || String(currentProduct.item_type || '') !== 'finished_good') {
                        return;
                    }

                    updateBomEditorVisibility();
                    updateBomLineCountHint();
                });

                el.addBomLine.addEventListener('click', async () => {
                    if (!rawMaterialProducts.length) {
                        showError('Belum ada produk raw material yang tersedia untuk BOM.');
                        return;
                    }

                    await appendBomLine({});
                });

                el.bomLineList.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('[data-bom-remove]');
                    if (!removeButton) {
                        return;
                    }

                    removeButton.closest('.rounded-xl')?.remove();
                    updateBomLineCountHint();
                });

                el.bomLineList.addEventListener('change', async (event) => {
                    const productSelect = event.target.closest('[data-bom-product]');
                    if (!productSelect) {
                        return;
                    }

                    const row = productSelect.closest('.rounded-xl');
                    await configureBomVariantField(row, productSelect.value || '', null);
                });

                el.form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    showError('');

                    if (!currentProduct) {
                        showError('Produk tidak ditemukan.');
                        return;
                    }

                    const payload = {
                        name: el.editName.value.trim(),
                        sku: el.editSku.value.trim(),
                        price: Number(el.editPrice.value || 0),
                        category_id: el.editCategory.value || null,
                        hpp_average: currentProduct.hpp_average || 0,
                        initial_stock: currentProduct?.has_variants ? 0 : Number(el.stock.value || 0),
                    };

                    if (!payload.name || !payload.sku) {
                        showError('Nama produk dan SKU wajib diisi.');
                        return;
                    }

                    if (payload.price <= 0) {
                        showError('Harga harus lebih dari 0.');
                        return;
                    }

                    if (currentProduct?.has_variants) {
                        payload.initial_stock_lines = collectVariantStockLines(currentProduct);
                    }

                    if (String(currentProduct.item_type || '') === 'finished_good') {
                        if (el.enableBom.checked) {
                            payload.product_mode = 'bom';
                            payload.item_type = 'finished_good';
                            payload.bom_items = collectBomItems();
                        } else if (String(currentProduct.product_mode || '') === 'bom') {
                            payload.product_mode = 'default';
                            payload.item_type = 'finished_good';
                            payload.bom_items = [];
                        }
                    }

                    try {
                        const response = await fetch(`/api/v1/products/${currentProduct.id}`, {
                            method: 'PUT',
                            headers: {
                                ...headers,
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(payload),
                        });

                        if (!response.ok) {
                            const error = await response.json();
                            throw new Error(error?.message || 'Gagal menyimpan perubahan.');
                        }

                        goBack();
                    } catch (error) {
                        showError(error.message || 'Gagal menyimpan perubahan.');
                    }
                });

                el.deleteProduct.addEventListener('click', async () => {
                    showError('');

                    if (!currentProduct) {
                        showError('Produk tidak ditemukan.');
                        return;
                    }

                    const confirmed = confirm('Hapus produk ini?');
                    if (!confirmed) {
                        return;
                    }

                    try {
                        const response = await fetch(`/api/v1/products/${currentProduct.id}`, {
                            method: 'DELETE',
                            headers,
                        });

                        if (!response.ok) {
                            const error = await response.json();
                            throw new Error(error?.message || 'Gagal menghapus produk.');
                        }

                        goBack();
                    } catch (error) {
                        showError(error.message || 'Gagal menghapus produk.');
                    }
                });

                (async () => {
                    const [categories, product, rawMaterials] = await Promise.all([loadCategories(), loadProduct(), loadRawMaterialProducts()]);

                    rawMaterialProducts = rawMaterials;

                    el.editCategory.innerHTML = '<option value="">Pilih kategori</option>';
                    categories.forEach((category) => {
                        const option = document.createElement('option');
                        option.value = category.id;
                        option.textContent = category.name;
                        el.editCategory.appendChild(option);
                    });

                    renderProduct(product);
                })().catch((error) => {
                    showError(error.message || 'Gagal memuat data produk.');
                });
            })();
        </script>
@endsection
