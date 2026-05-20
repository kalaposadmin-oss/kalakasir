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
    $productEditPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $isProductEditPosPortal = $productEditPortalPrefix === '/pos';
    $productEditFrom = trim((string) request()->query('from', ''));
    $productEditBackUrl = $productEditPortalPrefix.'/products';
    $productEditCanManageRecipe = $productEditTenant
        ? app(\App\Services\SubscriptionService::class)->canAccessFeature($productEditTenant, 'product.recipe_management')
        : false;
    if ($productEditFrom !== '') {
        if (str_starts_with($productEditFrom, '/pos/')) {
            $productEditBackUrl = $isProductEditPosPortal
                ? $productEditFrom
                : '/portal/switch/pos?next='.rawurlencode($productEditFrom);
        } elseif (str_starts_with($productEditFrom, '/backoffice/')) {
            $productEditBackUrl = $isProductEditPosPortal
                ? '/portal/switch/backoffice?next='.rawurlencode($productEditFrom)
                : $productEditFrom;
        }
    }
    $productEditBackUrl = normalize_pos_home_url($productEditBackUrl);
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
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase">Supplier</label>
                        <select id="edit-supplier" class="w-full px-4 py-3 rounded-lg border-slate-200 focus:ring-primary focus:border-primary appearance-none">
                            <option value="">Pilih supplier</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase">Tipe Produk</label>
                        <select id="edit-type" class="w-full px-4 py-3 rounded-lg border-slate-200 focus:ring-primary focus:border-primary appearance-none">
                            <option value="finished_good">Barang Jual</option>
                            <option value="raw_material">Bahan Baku</option>
                            <option value="packaging">Packaging</option>
                            <option value="supply">Perlengkapan</option>
                            <option value="small_tool">Alat Kecil</option>
                            <option value="service">Jasa / Service</option>
                        </select>
                        <div id="edit-type-note" class="rounded-xl bg-slate-50 px-3 py-3 text-xs leading-relaxed text-slate-500">
                            Barang jual tampil di kasir. Bahan baku dipakai untuk recipe dan tidak dijual langsung di POS.
                        </div>
                    </div>
                    <div id="edit-raw-material-meta" class="hidden rounded-xl border border-slate-100 bg-slate-50/60 p-4 space-y-3">
                        <div>
                            <p class="text-sm font-bold text-slate-800">Satuan Produk</p>
                            <p class="text-xs text-slate-500">Satuan ini dipakai untuk stok, pembelian, dan resep. Pilih satuan dasar terkecil agar konversi otomatis tetap rapi.</p>
                        </div>
                        <div class="space-y-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase">Satuan Stok / Resep</label>
                                <select id="edit-unit-select" class="w-full px-4 py-3 rounded-lg border-slate-200 focus:ring-primary focus:border-primary appearance-none"></select>
                            </div>
                            <div id="edit-unit-custom-wrap" class="hidden space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase">Satuan Baru</label>
                                <input id="edit-unit-custom" class="w-full px-4 py-3 rounded-lg border-slate-200 focus:ring-primary focus:border-primary" type="text" placeholder="Contoh: pack, dus, bungkus" value="" />
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-100 bg-white/80 p-4 space-y-3">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-bold text-slate-800">Status Produk</p>
                                <p class="text-xs text-slate-500">Status aktif dan stok memengaruhi kelayakan batch secara live.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input id="edit-is-active" type="checkbox" class="sr-only peer" checked />
                                <div class="w-11 h-6 rounded-full bg-slate-200 peer-checked:bg-primary"></div>
                                <div class="absolute left-1 top-1 size-4 rounded-full bg-white transition-transform peer-checked:translate-x-5"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between gap-4 border-t border-slate-100 pt-3">
                            <div>
                                <p class="text-sm font-bold text-slate-800">Gunakan Stok</p>
                                <p class="text-xs text-slate-500">Matikan jika item ini tidak perlu stok manual.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input id="edit-track-stock" type="checkbox" class="sr-only peer" checked />
                                <div class="w-11 h-6 rounded-full bg-slate-200 peer-checked:bg-primary"></div>
                                <div class="absolute left-1 top-1 size-4 rounded-full bg-white transition-transform peer-checked:translate-x-5"></div>
                            </label>
                        </div>
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
                    <div id="edit-production-readiness" class="hidden rounded-xl border border-teal-200 bg-teal-50 p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-bold text-teal-900">Checklist Batch Produksi</p>
                                    <span id="edit-production-readiness-progress" class="rounded-full bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-teal-700 ring-1 ring-teal-200">0%</span>
                                </div>
                                <p id="edit-production-readiness-message" class="text-xs text-teal-700">Memeriksa apakah produk ini siap masuk batch produksi.</p>
                                <p id="edit-production-readiness-collapsed-summary" class="hidden mt-1 text-[11px] font-semibold text-teal-700"></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span id="edit-production-readiness-badge" class="rounded-full bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-teal-700 ring-1 ring-teal-200">Menunggu</span>
                                <button id="edit-production-readiness-toggle" class="inline-flex size-9 items-center justify-center rounded-full bg-white text-teal-700 ring-1 ring-teal-200 transition hover:bg-teal-100" type="button" aria-expanded="true" aria-controls="edit-production-readiness-body" title="Minimalkan checklist batch">
                                    <span id="edit-production-readiness-toggle-icon" class="material-symbols-outlined text-[18px]">expand_less</span>
                                </button>
                            </div>
                        </div>
                        <div id="edit-production-readiness-body" class="space-y-3">
                            <div id="edit-production-readiness-summary" class="flex flex-wrap gap-2"></div>
                            <div id="edit-production-readiness-checklist" class="space-y-2 text-xs text-teal-900"></div>
                            <p class="text-[11px] text-teal-700">Checklist ini hanya muncul untuk mode produksi batch yang memakai stok produk jadi.</p>
                            <p class="text-[11px] font-semibold text-teal-800">Checklist ini mengikuti perubahan di form saat ini. Status di modul batch baru ikut berubah setelah Anda menekan Simpan Perubahan.</p>
                        </div>
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
                            <label class="block space-y-1.5">
                                <span class="text-sm font-bold text-slate-800">Mode Resep</span>
                                <select id="edit-recipe-production-mode" class="w-full rounded-xl border-slate-200 text-sm text-slate-900">
                                    <option value="auto_consume_on_sell">Langsung jual, tanpa stok produk jadi</option>
                                    <option value="batch_production">Produksi batch, pakai stok produk jadi</option>
                                </select>
                            </label>
                            <label class="block space-y-1.5">
                                <span class="text-sm font-bold text-slate-800">Hasil Standar per 1 Resep</span>
                                <input id="edit-recipe-output-qty" class="w-full rounded-xl border-slate-200 text-sm text-slate-900" type="number" min="0.0001" step="0.0001" placeholder="Contoh: 10" />
                                <span class="block text-xs text-slate-500">Isi hasil jadi normal dari 1 resep. Angka ini dipakai untuk output harapan batch.</span>
                            </label>
                            <div class="flex items-center justify-between">
                                <p id="edit-bom-hint" class="text-xs text-slate-500">Belum ada line BOM.</p>
                                <button id="edit-add-bom-line" class="text-xs font-semibold text-primary" type="button">Tambah Bahan Baku</button>
                            </div>
                            <p id="edit-bom-inline-error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-600"></p>
                            <div id="edit-bom-line-list" class="space-y-3"></div>
                        </div>
                    </div>
                    <div id="edit-bom-unavailable" class="hidden rounded-xl border border-slate-100 bg-slate-50/60 p-4 text-xs leading-relaxed text-slate-500">
                        Editor BOM hanya tersedia untuk produk finished good di layar ini. Produk raw material atau service tetap mempertahankan kontrak lamanya.
                    </div>
                    <div id="edit-bom-locked" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-relaxed text-amber-900">
                        Fitur BOM / recipe tersedia mulai paket Medium. Tenant paket Starter tidak bisa mengubah konfigurasi recipe atau produksi.
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
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const productId = @json((string) ($productId ?? request()->query('product_id', '')));
                const initialProduct = @json($initialProduct ?? null);
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const canManageRecipe = @json($productEditCanManageRecipe);

                const el = {
                    editId: document.getElementById('edit-id'),
                    editName: document.getElementById('edit-name'),
                    editSku: document.getElementById('edit-sku'),
                    editPrice: document.getElementById('edit-price'),
                    editCategory: document.getElementById('edit-category'),
                    editSupplier: document.getElementById('edit-supplier'),
                    editType: document.getElementById('edit-type'),
                    editTypeNote: document.getElementById('edit-type-note'),
                    rawMaterialMeta: document.getElementById('edit-raw-material-meta'),
                    editUnitSelect: document.getElementById('edit-unit-select'),
                    editUnitCustomWrap: document.getElementById('edit-unit-custom-wrap'),
                    editUnitCustom: document.getElementById('edit-unit-custom'),
                    isActive: document.getElementById('edit-is-active'),
                    trackStock: document.getElementById('edit-track-stock'),
                    stockSection: document.getElementById('edit-stock-section'),
                    stock: document.getElementById('edit-stock'),
                    stockMinus: document.getElementById('edit-stock-minus'),
                    stockPlus: document.getElementById('edit-stock-plus'),
                    variantStockSection: document.getElementById('edit-variant-stock-section'),
                    variantStockHint: document.getElementById('edit-variant-stock-hint'),
                    variantStockLineList: document.getElementById('edit-variant-stock-line-list'),
                    productionReadiness: document.getElementById('edit-production-readiness'),
                    productionReadinessMessage: document.getElementById('edit-production-readiness-message'),
                    productionReadinessProgress: document.getElementById('edit-production-readiness-progress'),
                    productionReadinessCollapsedSummary: document.getElementById('edit-production-readiness-collapsed-summary'),
                    productionReadinessBadge: document.getElementById('edit-production-readiness-badge'),
                    productionReadinessToggle: document.getElementById('edit-production-readiness-toggle'),
                    productionReadinessToggleIcon: document.getElementById('edit-production-readiness-toggle-icon'),
                    productionReadinessBody: document.getElementById('edit-production-readiness-body'),
                    productionReadinessSummary: document.getElementById('edit-production-readiness-summary'),
                    productionReadinessChecklist: document.getElementById('edit-production-readiness-checklist'),
                    enableBom: document.getElementById('edit-enable-bom'),
                    bomCard: document.getElementById('edit-bom-card'),
                    bomUnavailable: document.getElementById('edit-bom-unavailable'),
                    bomLocked: document.getElementById('edit-bom-locked'),
                    bomEditor: document.getElementById('edit-bom-editor'),
                    recipeProductionMode: document.getElementById('edit-recipe-production-mode'),
                    recipeOutputQty: document.getElementById('edit-recipe-output-qty'),
                    bomHint: document.getElementById('edit-bom-hint'),
                    bomInlineError: document.getElementById('edit-bom-inline-error'),
                    bomLineList: document.getElementById('edit-bom-line-list'),
                    addBomLine: document.getElementById('edit-add-bom-line'),
                    form: document.getElementById('edit-form'),
                    cancel: document.getElementById('cancel-edit'),
                    error: document.getElementById('form-error'),
                    deleteProduct: document.getElementById('delete-product'),
                };
                const submitButton = el.form.querySelector('button[type="submit"]');
                const readinessUiState = {
                    collapsed: false,
                };

                let currentProduct = null;
                let rawMaterialProducts = [];
                const productDetailCache = new Map();
                const rawMaterialUnitOptions = ['mg', 'gram', 'kg', 'ml', 'liter', 'cm', 'meter', 'pcs', 'unit', 'dozen', 'lusin', 'box', 'pack', 'roll', 'sachet', 'bungkus'];
                const stockUnitDefaults = {
                    raw_material: 'gram',
                    packaging: 'pcs',
                    supply: 'pcs',
                    small_tool: 'pcs',
                    finished_good: 'pcs',
                };
                const stockedInternalTypes = ['raw_material', 'packaging', 'supply', 'small_tool'];
                const recipeComponentTypes = ['raw_material', 'packaging'];
                const isStockedInternalType = (type) => stockedInternalTypes.includes(String(type || ''));
                const isRecipeComponentType = (type) => recipeComponentTypes.includes(String(type || ''));
                const isSellableType = (type) => ['finished_good', 'service'].includes(String(type || ''));

                const getSelectedProductType = () => String(el.editType?.value || 'finished_good');
                const normalizeRecipeProductionMode = (value) => {
                    const mode = String(value || '').trim();
                    return ['auto_consume_on_sell', 'batch_production'].includes(mode)
                        ? mode
                        : 'auto_consume_on_sell';
                };
                const getSelectedRecipeProductionMode = () => normalizeRecipeProductionMode(el.recipeProductionMode?.value);
                const recipeAutoConsumesOnSell = () => Boolean(
                    canManageRecipe
                    && getSelectedProductType() === 'finished_good'
                    && el.enableBom?.checked
                    && getSelectedRecipeProductionMode() === 'auto_consume_on_sell'
                );
                const recipeUsesFinishedStock = () => Boolean(
                    canManageRecipe
                    && getSelectedProductType() === 'finished_good'
                    && el.enableBom?.checked
                    && getSelectedRecipeProductionMode() === 'batch_production'
                );

                const normalizeRecipeOutputQtyValue = (value) => {
                    const parsed = Number(value ?? 0);
                    return Number.isFinite(parsed) && parsed > 0 ? String(parsed) : '';
                };

                const updateTypeNote = () => {
                    const productType = getSelectedProductType();

                    if (productType === 'raw_material') {
                        el.editTypeNote.textContent = 'Bahan baku dipakai untuk recipe atau produksi batch dan tidak tampil di katalog kasir.';
                        return;
                    }

                    if (productType === 'packaging') {
                        el.editTypeNote.textContent = 'Packaging dipakai untuk kemasan produk dan bisa ikut dihitung dalam HPP recipe.';
                        return;
                    }

                    if (productType === 'supply') {
                        el.editTypeNote.textContent = 'Perlengkapan adalah barang habis pakai operasional. Distok untuk kontrol, tidak masuk POS atau recipe.';
                        return;
                    }

                    if (productType === 'small_tool') {
                        el.editTypeNote.textContent = 'Alat kecil dicatat sebagai stok/inventaris ringan. Untuk alat mahal gunakan modul aset tetap.';
                        return;
                    }

                    if (productType === 'service') {
                        el.editTypeNote.textContent = 'Jasa tidak memakai stok dan tidak dipakai sebagai bahan baku recipe.';
                        return;
                    }

                    el.editTypeNote.textContent = 'Barang jual tampil di kasir. Anda bisa mengaktifkan BOM jika produk ini dibuat dari bahan baku.';
                };

                const updateRawMaterialMetaVisibility = () => {
                    el.rawMaterialMeta.classList.toggle('hidden', getSelectedProductType() === 'service');
                };

                const setToggleDisabled = (input, disabled) => {
                    if (!input) {
                        return;
                    }

                    input.disabled = disabled;
                    const wrapper = input.closest('label');
                    if (wrapper) {
                        wrapper.classList.toggle('opacity-50', disabled);
                        wrapper.classList.toggle('cursor-not-allowed', disabled);
                    }
                };

                const syncRecipeStockState = () => {
                    const productType = getSelectedProductType();
                    const autoConsume = recipeAutoConsumesOnSell();
                    const batchProduction = recipeUsesFinishedStock();

                    if (batchProduction) {
                        el.trackStock.checked = true;
                    }

                    if (autoConsume) {
                        el.trackStock.checked = false;
                    }

                    const recipeOutputWrap = el.recipeOutputQty?.closest('label');
                    recipeOutputWrap?.classList.toggle('hidden', !batchProduction);
                    if (!batchProduction && el.recipeOutputQty) {
                        el.recipeOutputQty.value = '';
                    }

                    setToggleDisabled(el.trackStock, productType === 'service' || autoConsume || batchProduction);
                };

                const renderCategoryOptions = (categories, selectedCategoryId = '', selectedCategoryName = '') => {
                    el.editCategory.innerHTML = '<option value="">Pilih kategori</option>';

                    const normalizedSelectedId = String(selectedCategoryId || '');
                    const existingCategory = categories.find((category) => String(category.id) === normalizedSelectedId);
                    const label = String(selectedCategoryName || existingCategory?.name || '').trim();

                    if (normalizedSelectedId && label) {
                        const selectedOption = document.createElement('option');
                        selectedOption.value = normalizedSelectedId;
                        selectedOption.textContent = label;
                        selectedOption.selected = true;
                        el.editCategory.appendChild(selectedOption);
                    }

                    categories.forEach((category) => {
                        if (String(category.id) === normalizedSelectedId) {
                            return;
                        }

                        const option = document.createElement('option');
                        option.value = category.id;
                        option.textContent = category.name;
                        el.editCategory.appendChild(option);
                    });

                    if (!normalizedSelectedId) {
                        el.editCategory.value = '';
                    }
                };

                const renderSupplierOptions = (suppliers, selectedSupplierId = '', selectedSupplierName = '') => {
                    el.editSupplier.innerHTML = '<option value="">Pilih supplier</option>';

                    const normalizedSelectedId = String(selectedSupplierId || '');
                    const existingSupplier = suppliers.find((supplier) => String(supplier.id) === normalizedSelectedId);
                    const label = String(selectedSupplierName || existingSupplier?.name || '').trim();

                    if (normalizedSelectedId && label) {
                        const selectedOption = document.createElement('option');
                        selectedOption.value = normalizedSelectedId;
                        selectedOption.textContent = label;
                        selectedOption.selected = true;
                        el.editSupplier.appendChild(selectedOption);
                    }

                    suppliers.forEach((supplier) => {
                        if (String(supplier.id) === normalizedSelectedId) {
                            return;
                        }

                        const option = document.createElement('option');
                        option.value = supplier.id;
                        option.textContent = supplier.name;
                        el.editSupplier.appendChild(option);
                    });

                    if (!normalizedSelectedId) {
                        el.editSupplier.value = '';
                    }
                };

                const productUsesRecipeManagement = (product) => {
                    const bomItems = Array.isArray(product?.bom_items) ? product.bom_items : [];
                    return String(product?.product_mode || '') === 'bom'
                        || bomItems.length > 0
                        || ['auto_consume_on_sell', 'batch_production'].includes(String(product?.production_mode || ''));
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

                const loadSuppliers = async () => {
                    const response = await fetch('/api/v1/suppliers', { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const data = await response.json();
                    return data.suppliers || [];
                };

                const renderRawMaterialUnits = (selectedUnit = '') => {
                    el.editUnitSelect.innerHTML = '';

                    const normalizedSelected = String(selectedUnit || '').trim().toLowerCase();
                    const hasKnownUnit = rawMaterialUnitOptions.includes(normalizedSelected);

                    rawMaterialUnitOptions.forEach((unit) => {
                        const option = document.createElement('option');
                        option.value = unit;
                        option.textContent = unit;
                        el.editUnitSelect.appendChild(option);
                    });

                    const customOption = document.createElement('option');
                    customOption.value = '__custom__';
                    customOption.textContent = 'Satuan lain...';
                    el.editUnitSelect.appendChild(customOption);

                    if (hasKnownUnit) {
                        el.editUnitSelect.value = normalizedSelected;
                        el.editUnitCustomWrap.classList.add('hidden');
                        el.editUnitCustom.value = '';
                        return;
                    }

                    el.editUnitSelect.value = '__custom__';
                    el.editUnitCustomWrap.classList.remove('hidden');
                    el.editUnitCustom.value = selectedUnit || '';
                };

                const resolveRawMaterialUnit = () => {
                    const selectedUnit = String(el.editUnitSelect.value || '').trim();
                    if (selectedUnit === '__custom__') {
                        return String(el.editUnitCustom.value || '').trim();
                    }

                    return selectedUnit;
                };

                const confirmStockUnitBeforeSave = async (productType, unit) => {
                    if (productType === 'service') {
                        return true;
                    }

                    const normalizedUnit = String(unit || '').trim().toLowerCase();
                    const typeLabel = productType === 'raw_material' ? 'bahan baku' : 'produk';
                    const examples = {
                        gram: 'Contoh: beli 1 kg akan otomatis masuk sebagai 1000 gram. Jika resep butuh 100 gram, stok langsung berkurang 100 gram.',
                        mg: 'Contoh: semua stok dan resep akan dihitung dalam mg. Pakai ini hanya jika resep memang memakai ukuran sangat kecil.',
                        ml: 'Contoh: beli 1 liter akan otomatis masuk sebagai 1000 ml. Jika resep butuh 100 ml, stok langsung berkurang 100 ml.',
                        cm: 'Contoh: beli 1 meter akan otomatis masuk sebagai 100 cm. Jika resep butuh 25 cm, stok langsung berkurang 25 cm.',
                        pcs: 'Contoh: stok dan resep dihitung per pcs. Gunakan untuk barang hitungan seperti telur, cup, atau kemasan satuan.',
                        unit: 'Contoh: stok dan resep dihitung per unit. Gunakan jika memang tidak ada satuan yang lebih spesifik.',
                    };
                    const notSmallestHints = {
                        kg: 'Perhatian: kg biasanya bukan satuan terkecil untuk resep. Jika resep memakai gram, sebaiknya pilih gram.',
                        liter: 'Perhatian: liter biasanya bukan satuan terkecil untuk resep. Jika resep memakai ml, sebaiknya pilih ml.',
                        meter: 'Perhatian: meter biasanya bukan satuan terkecil untuk resep. Jika resep memakai cm, sebaiknya pilih cm.',
                        dozen: 'Perhatian: dozen berisi 12 pcs. Jika resep memakai pcs, sebaiknya pilih pcs.',
                        lusin: 'Perhatian: lusin berisi 12 pcs. Jika resep memakai pcs, sebaiknya pilih pcs.',
                    };

                    const message = [
                        `Satuan dasar stok/resep untuk ${typeLabel} ini adalah: ${unit || '-'}.`,
                        examples[normalizedUnit] || notSmallestHints[normalizedUnit] || 'Pastikan satuan ini adalah satuan yang akan dipakai saat stok dan resep dihitung.',
                        'Lanjut simpan dengan satuan ini?',
                    ].join('\n\n');

                    if (window.PosUi?.confirmRiskActionAsync) {
                        return window.PosUi.confirmRiskActionAsync({
                            title: 'Pastikan Satuan Dasar',
                            message,
                            confirmText: 'Lanjut Simpan',
                            icon: 'straighten',
                        });
                    }

                    return window.confirm(message);
                };

                const loadRawMaterialProducts = async () => {
                    const response = await fetch('/api/v1/products', { headers });
                    if (!response.ok) {
                        return [];
                    }

                    const data = await response.json();
                    return (data.products || [])
                        .filter((product) => {
                            const mode = String(product.product_mode || '').toLowerCase();
                            const itemType = String(product.item_type || '').toLowerCase();

                            if (mode === 'add_on' && isRecipeComponentType(itemType)) {
                                return true;
                            }

                            return isRecipeComponentType(itemType);
                        })
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

                const setBomInlineError = (message) => {
                    if (!el.bomInlineError) {
                        return;
                    }

                    if (!message) {
                        el.bomInlineError.classList.add('hidden');
                        el.bomInlineError.textContent = '';
                        return;
                    }

                    el.bomInlineError.textContent = message;
                    el.bomInlineError.classList.remove('hidden');
                };

                const setBomRowConflictTip = (row, message) => {
                    if (!row) {
                        return;
                    }

                    const tip = row.querySelector('[data-bom-conflict-tip]');
                    if (!tip) {
                        return;
                    }

                    if (!message) {
                        tip.classList.add('hidden');
                        tip.textContent = '';
                        return;
                    }

                    tip.textContent = message;
                    tip.classList.remove('hidden');
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
                    const productType = getSelectedProductType();
                    if (productType === 'service') {
                        el.trackStock.checked = false;
                    }

                    syncRecipeStockState();
                    const trackStockEnabled = Boolean(el.trackStock?.checked);

                    if (productType === 'service') {
                        el.stockSection.classList.add('hidden');
                        el.variantStockSection.classList.add('hidden');
                        el.stock.value = '0';
                        el.variantStockLineList.innerHTML = '';
                        return;
                    }

                    const trackStock = trackStockEnabled;
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

                const resolveBomProductDraft = (productId) => {
                    const targetId = String(productId || '').trim();

                    if (!targetId) {
                        return null;
                    }

                    const listMatch = rawMaterialProducts.find((product) => String(product.id || '') === targetId);
                    if (listMatch) {
                        return listMatch;
                    }

                    return productDetailCache.get(targetId) || null;
                };

                const inspectBomItems = () => {
                    return Array.from(el.bomLineList.children).map((row, index) => {
                        const productId = row.querySelector('[data-bom-product]')?.value || '';
                        const product = resolveBomProductDraft(productId);
                        const variantWrap = row.querySelector('[data-bom-variant-wrap]');
                        const variantRequired = Boolean(variantWrap && !variantWrap.classList.contains('hidden'));
                        const variantId = row.querySelector('[data-bom-variant]')?.value || '';
                        const qty = Number(row.querySelector('[data-bom-qty]')?.value || 0);

                        return {
                            product_id: productId || null,
                            product_name: product?.name || `Line ${index + 1}`,
                            product_item_type: String(product?.item_type || '').trim(),
                            product_mode: String(product?.product_mode || '').trim(),
                            product_variant_id: variantRequired ? variantId || null : null,
                            variant_required: variantRequired,
                            qty,
                        };
                    });
                };

                const buildProductionDraft = () => {
                    const productType = getSelectedProductType();
                    const useBom = Boolean(canManageRecipe && productType === 'finished_good' && el.enableBom.checked);
                    const productionMode = useBom ? getSelectedRecipeProductionMode() : 'none';
                    const trackStock = productType === 'service'
                        ? false
                        : (useBom
                            ? productionMode === 'batch_production'
                            : Boolean(el.trackStock?.checked));

                    return {
                        is_active: Boolean(el.isActive?.checked),
                        item_type: productType,
                        product_mode: isStockedInternalType(productType)
                            ? 'add_on'
                            : (productType === 'service'
                                ? 'default'
                                : (useBom ? 'bom' : 'default')),
                        production_mode: productType === 'service' || isStockedInternalType(productType)
                            ? 'none'
                            : productionMode,
                        track_stock: trackStock,
                        recipe_output_qty: useBom ? Number(el.recipeOutputQty?.value || 0) : 0,
                        bom_items: useBom ? inspectBomItems() : [],
                    };
                };

                const shouldShowProductionReadiness = () => {
                    return Boolean(
                        canManageRecipe
                        && getSelectedProductType() === 'finished_good'
                        && el.enableBom?.checked
                        && getSelectedRecipeProductionMode() === 'batch_production'
                    );
                };

                const applyProductionReadinessCollapseState = () => {
                    if (!el.productionReadinessBody || !el.productionReadinessToggle || !el.productionReadinessToggleIcon || !el.productionReadinessCollapsedSummary) {
                        return;
                    }

                    const collapsed = readinessUiState.collapsed;
                    el.productionReadinessBody.classList.toggle('hidden', collapsed);
                    el.productionReadinessCollapsedSummary.classList.toggle('hidden', !collapsed);
                    el.productionReadinessToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    el.productionReadinessToggle.title = collapsed ? 'Tampilkan checklist batch' : 'Minimalkan checklist batch';
                    el.productionReadinessToggleIcon.textContent = collapsed ? 'expand_more' : 'expand_less';
                };

                const renderProductionReadiness = () => {
                    if (!el.productionReadiness || !el.productionReadinessMessage || !el.productionReadinessBadge || !el.productionReadinessChecklist || !el.productionReadinessSummary || !el.productionReadinessProgress || !el.productionReadinessCollapsedSummary) {
                        return;
                    }

                    const visible = shouldShowProductionReadiness();
                    el.productionReadiness.classList.toggle('hidden', !visible);

                    if (!visible) {
                        return;
                    }

                    const eligibility = window.PosUi.getProductionBatchEligibility(buildProductionDraft());
                    const isEligible = Boolean(eligibility?.eligible);
                    const checks = Array.isArray(eligibility?.checks) ? eligibility.checks : [];
                    const summary = eligibility?.summary || {};
                    const totalChecks = checks.length;
                    const passedChecks = checks.filter((check) => Boolean(check?.passed)).length;
                    const failedChecks = Math.max(0, totalChecks - passedChecks);
                    const progressPct = totalChecks > 0 ? Math.round((passedChecks / totalChecks) * 100) : 0;
                    const compactSummary = totalChecks === 0
                        ? 'Checklist belum tersedia.'
                        : (failedChecks === 0
                            ? `100% siap. Semua ${totalChecks} syarat terpenuhi.`
                            : `Kurang ${failedChecks} syarat dari ${totalChecks} syarat.`);

                    el.productionReadiness.className = isEligible
                        ? 'rounded-xl border border-emerald-200 bg-emerald-50 p-4 space-y-3'
                        : 'rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-3';
                    el.productionReadinessBadge.className = isEligible
                        ? 'rounded-full bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-700 ring-1 ring-emerald-200'
                        : 'rounded-full bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-amber-700 ring-1 ring-amber-200';
                    el.productionReadinessProgress.className = isEligible
                        ? 'rounded-full bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-700 ring-1 ring-emerald-200'
                        : 'rounded-full bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-amber-700 ring-1 ring-amber-200';
                    el.productionReadinessBadge.textContent = isEligible ? 'Siap diproduksi' : 'Belum siap';
                    el.productionReadinessProgress.textContent = `${progressPct}%`;
                    el.productionReadinessMessage.textContent = summary.message || (isEligible
                        ? 'Produk ini sudah memenuhi syarat batch produksi.'
                        : 'Lengkapi checklist di bawah untuk membuat produk siap batch produksi.');
                    el.productionReadinessCollapsedSummary.textContent = compactSummary;

                    const summaryPills = [
                        { label: summary.is_active ? 'Status: aktif' : 'Status: nonaktif', tone: summary.is_active ? 'emerald' : 'amber' },
                        { label: `Mode produk: ${summary.product_mode || 'default'}`, tone: summary.product_mode === 'bom' ? 'emerald' : 'slate' },
                        { label: `Mode produksi: ${summary.production_mode || 'none'}`, tone: summary.production_mode === 'batch_production' ? 'emerald' : 'slate' },
                        { label: `Track stock: ${summary.track_stock ? 'on' : 'off'}`, tone: summary.track_stock ? 'emerald' : 'slate' },
                        { label: `Hasil/resep: ${Number(summary.recipe_output_qty || 0) > 0 ? summary.recipe_output_qty : '-'}`, tone: Number(summary.recipe_output_qty || 0) > 0 ? 'emerald' : 'slate' },
                    ];

                    el.productionReadinessSummary.innerHTML = summaryPills.map((pill) => {
                        const toneClasses = {
                            emerald: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                            amber: 'bg-amber-100 text-amber-800 ring-amber-200',
                            slate: 'bg-white text-slate-600 ring-slate-200',
                        };

                        return `<span class="rounded-full px-3 py-1 text-[11px] font-semibold ring-1 ${toneClasses[pill.tone] || toneClasses.slate}">${pill.label}</span>`;
                    }).join('');

                    el.productionReadinessChecklist.innerHTML = checks.map((check) => {
                        const passed = Boolean(check.passed);
                        return `
                            <div class="flex items-start gap-2 rounded-xl bg-white px-3 py-2 ring-1 ${passed ? 'ring-emerald-200' : 'ring-amber-200'}">
                                <span class="mt-0.5 material-symbols-outlined text-[16px] ${passed ? 'text-emerald-600' : 'text-amber-600'}">${passed ? 'check_circle' : 'radio_button_unchecked'}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] ${passed ? 'text-emerald-700' : 'text-amber-700'}">${check.label || 'Checklist'}</p>
                                    <p class="text-[11px] ${passed ? 'text-emerald-700' : 'text-amber-800'}">${passed ? 'Sudah sesuai.' : (check.reason || 'Belum sesuai.')}</p>
                                </div>
                            </div>
                        `;
                    }).join('');

                    applyProductionReadinessCollapseState();
                };

                const refreshProductionReadiness = () => {
                    renderProductionReadiness();
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

                const buildBomLineKey = (row) => {
                    const productId = row.querySelector('[data-bom-product]')?.value || '';
                    if (!productId) {
                        return null;
                    }

                    const variantWrap = row.querySelector('[data-bom-variant-wrap]');
                    const variantRequired = variantWrap && !variantWrap.classList.contains('hidden');
                    if (!variantRequired) {
                        return `${productId}|__no_variant__`;
                    }

                    const variantId = row.querySelector('[data-bom-variant]')?.value || '';
                    if (!variantId) {
                        return null;
                    }

                    return `${productId}|${variantId}`;
                };

                const duplicateBomSelections = () => {
                    const firstSeenLineByKey = new Map();
                    const duplicates = [];

                    Array.from(el.bomLineList.children).forEach((row, index) => {
                        const key = buildBomLineKey(row);
                        if (!key) {
                            return;
                        }

                        const lineNumber = index + 1;
                        if (firstSeenLineByKey.has(key)) {
                            duplicates.push({
                                line: lineNumber,
                                duplicateOf: firstSeenLineByKey.get(key),
                            });
                            return;
                        }

                        firstSeenLineByKey.set(key, lineNumber);
                    });

                    return duplicates;
                };

                const syncDuplicateBomRowStates = () => {
                    const firstSeenLineByKey = new Map();
                    const duplicateRows = new Set();
                    const duplicateRowMetadata = new Map();

                    Array.from(el.bomLineList.children).forEach((row, index) => {
                        row.classList.remove('ring-2', 'ring-rose-200', 'bg-rose-50', 'border-rose-300');

                        const duplicateNote = row.querySelector('[data-bom-duplicate-note]');
                        if (duplicateNote) {
                            duplicateNote.classList.add('hidden');
                            duplicateNote.textContent = '';
                        }

                        const key = buildBomLineKey(row);
                        if (!key) {
                            return;
                        }

                        const lineNumber = index + 1;
                        if (firstSeenLineByKey.has(key)) {
                            duplicateRows.add(row);
                            duplicateRowMetadata.set(row, `Duplikat bahan dari line #${firstSeenLineByKey.get(key)}.`);
                            return;
                        }

                        firstSeenLineByKey.set(key, lineNumber);
                    });

                    duplicateRows.forEach((row) => {
                        row.classList.add('ring-2', 'ring-rose-200', 'bg-rose-50', 'border-rose-300');

                        const productSelect = row.querySelector('[data-bom-product]');
                        if (productSelect) {
                            productSelect.classList.add('border-rose-300', 'focus:border-rose-400', 'focus:ring-rose-200');
                        }

                        const variantSelect = row.querySelector('[data-bom-variant]');
                        if (variantSelect) {
                            variantSelect.classList.add('border-rose-300', 'focus:border-rose-400', 'focus:ring-rose-200');
                        }

                        const duplicateNote = row.querySelector('[data-bom-duplicate-note]');
                        if (duplicateNote) {
                            duplicateNote.textContent = duplicateRowMetadata.get(row) || 'Duplikat bahan. Gabungkan qty pada line sebelumnya.';
                            duplicateNote.classList.remove('hidden');
                        }
                    });

                    return duplicateRows.size > 0;
                };

                const findDuplicateBomConflict = (changedRow) => {
                    const key = buildBomLineKey(changedRow);
                    if (!key) {
                        return null;
                    }

                    return Array.from(el.bomLineList.children)
                        .find((row) => row !== changedRow && buildBomLineKey(row) === key) || null;
                };

                const clearDuplicateBomErrorIfPresent = () => {
                    const currentError = String(el.error.textContent || '').toLowerCase();
                    if (currentError.includes('bahan baku duplikat')) {
                        showError('');
                    }

                    setBomInlineError('');
                };

                const validateBomDuplicatesRealtime = () => {
                    const duplicates = duplicateBomSelections();
                    if (!duplicates.length) {
                        syncDuplicateBomRowStates();
                        clearDuplicateBomErrorIfPresent();
                        updateBomLineCountHint();
                        return true;
                    }

                    syncDuplicateBomRowStates();
                    const firstDuplicate = duplicates[0];
                    setBomHint(`Duplikat bahan terdeteksi pada line #${firstDuplicate.line}.`);
                    setBomInlineError(`Line #${firstDuplicate.line} duplikat dengan line #${firstDuplicate.duplicateOf}. Gabungkan qty pada satu line.`);
                    showError(`Line BOM #${firstDuplicate.line}: bahan baku duplikat dengan line #${firstDuplicate.duplicateOf}. Gabungkan qty pada satu line.`);
                    return false;
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
                    const productType = getSelectedProductType();
                    setToggleDisabled(el.enableBom, productType !== 'finished_good' || !canManageRecipe);

                    if (productType !== 'finished_good') {
                        el.bomCard.classList.add('hidden');
                        el.bomUnavailable.classList.remove('hidden');
                        el.bomLocked.classList.add('hidden');
                        el.enableBom.checked = false;
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                        refreshProductionReadiness();
                        return;
                    }

                    if (!canManageRecipe) {
                        el.bomUnavailable.classList.add('hidden');
                        el.bomCard.classList.add('hidden');
                        el.bomLocked.classList.remove('hidden');
                        el.enableBom.checked = false;
                        el.bomLineList.innerHTML = '';
                        setBomHint('Fitur BOM belum tersedia di paket ini.');

                        if (submitButton) {
                            submitButton.disabled = productUsesRecipeManagement(currentProduct);
                        }

                        refreshProductionReadiness();

                        return;
                    }

                    el.bomUnavailable.classList.add('hidden');
                    el.bomLocked.classList.add('hidden');
                    el.bomCard.classList.remove('hidden');
                    el.bomEditor.classList.toggle('hidden', !el.enableBom.checked);
                    syncRecipeStockState();
                    if (submitButton) {
                        submitButton.disabled = false;
                    }

                    if (!el.enableBom.checked) {
                        el.bomLineList.innerHTML = '';
                        setBomHint('Belum ada line BOM.');
                        refreshProductionReadiness();
                    }

                    refreshProductionReadiness();
                };

                const createBomLineRow = (line = {}) => {
                    const row = document.createElement('div');
                    row.className = 'rounded-xl border border-slate-200 bg-white p-3 space-y-3';
                    row.innerHTML = `
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Line BOM</p>
                            <button class="text-xs font-semibold text-rose-500" type="button" data-bom-remove>Hapus</button>
                        </div>
                        <p class="hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-[11px] font-semibold text-rose-600" data-bom-duplicate-note></p>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-500">Raw Material</label>
                            <select class="w-full rounded-xl border-slate-200 text-sm text-slate-900" data-bom-product>
                                ${bomProductOptionsHtml(line.product_id || '')}
                            </select>
                            <p class="hidden text-[11px] font-semibold text-rose-600" data-bom-conflict-tip></p>
                        </div>
                        <div class="space-y-1.5 hidden" data-bom-variant-wrap>
                            <label class="text-xs font-semibold text-slate-500">Variant Raw Material</label>
                            <select class="w-full rounded-xl border-slate-200 text-sm text-slate-900" data-bom-variant>
                                <option value="">Pilih variant</option>
                            </select>
                            <p class="text-[11px] text-slate-400" data-bom-variant-hint></p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-500">Qty per 1 resep</label>
                            <input class="w-full rounded-xl border-slate-200 text-sm text-slate-900" data-bom-qty type="number" min="0.0001" step="0.0001" value="${line.qty ?? ''}" />
                        </div>
                    `;

                    return row;
                };

                const ensureBomProductOptionExists = async (row, productId) => {
                    const productSelect = row.querySelector('[data-bom-product]');
                    if (!productSelect || !productId) {
                        return;
                    }

                    const targetId = String(productId);
                    const hasOption = Array.from(productSelect.options)
                        .some((option) => String(option.value || '') === targetId);

                    if (hasOption) {
                        productSelect.value = targetId;
                        return;
                    }

                    const productDetail = await loadProductDetailCached(targetId);
                    if (!productDetail) {
                        return;
                    }

                    const option = document.createElement('option');
                    option.value = targetId;
                    option.textContent = productDetail.sku
                        ? `${productDetail.name} (${productDetail.sku})`
                        : (productDetail.name || 'Raw Material');
                    productSelect.appendChild(option);
                    productSelect.value = targetId;
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
                    await ensureBomProductOptionExists(row, line.product_id || '');
                    await configureBomVariantField(row, line.product_id || '', line.product_variant_id || null);
                    validateBomDuplicatesRealtime();
                    refreshProductionReadiness();
                };

                const collectBomItems = () => {
                    const rows = Array.from(el.bomLineList.children);
                    if (!rows.length) {
                        throw new Error('Minimal satu line BOM wajib diisi saat mode BOM aktif.');
                    }

                    const seenLineKeys = new Set();

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

                        const lineKey = `${productId}|${variantRequired ? variantId : '__no_variant__'}`;
                        if (seenLineKeys.has(lineKey)) {
                            throw new Error(`Line BOM #${index + 1}: bahan baku duplikat. Gabungkan qty pada satu line.`);
                        }
                        seenLineKeys.add(lineKey);

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
                    renderCategoryOptions([], product.category_id || '', product.category_name || '');
                    renderSupplierOptions([], product.supplier_id || '', product.supplier_name || '');
                    el.editType.value = String(product.item_type || 'finished_good');
                    renderRawMaterialUnits(product.unit || stockUnitDefaults[getSelectedProductType()] || 'pcs');
                    el.isActive.checked = product.is_active !== false;
                    el.trackStock.checked = product.track_stock !== false;
                    el.recipeProductionMode.value = normalizeRecipeProductionMode(product.production_mode);
                    el.recipeOutputQty.value = normalizeRecipeOutputQtyValue(product.recipe_output_qty);
                    updateTypeNote();
                    updateRawMaterialMetaVisibility();

                    const isFinishedGood = getSelectedProductType() === 'finished_good';
                    const isBomMode = isFinishedGood && String(product.product_mode || '') === 'bom';
                    el.enableBom.checked = isBomMode;
                    updateBomEditorVisibility();
                    el.stock.value = '0';
                    updateInitialStockEditors(product);
                    el.recipeOutputQty.value = normalizeRecipeOutputQtyValue(product.recipe_output_qty);
                    refreshProductionReadiness();

                    if (!isBomMode) {
                        updateBomLineCountHint();
                        refreshProductionReadiness();
                        return;
                    }

                    el.bomLineList.innerHTML = '';
                    const bomItems = Array.isArray(product.bom_items) ? product.bom_items : [];
                    if (!bomItems.length) {
                        updateBomLineCountHint();
                        refreshProductionReadiness();
                        return;
                    }

                    bomItems.reduce((promise, line) => {
                        return promise.then(() => appendBomLine(line));
                    }, Promise.resolve())
                        .then(() => {
                            el.recipeOutputQty.value = normalizeRecipeOutputQtyValue(product.recipe_output_qty);
                            updateBomLineCountHint();
                            refreshProductionReadiness();
                        })
                        .catch((error) => {
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
                    if (!currentProduct || getSelectedProductType() !== 'finished_good') {
                        return;
                    }

                    syncRecipeStockState();
                    updateBomEditorVisibility();
                    updateInitialStockEditors(currentProduct);
                    updateBomLineCountHint();
                    refreshProductionReadiness();
                });
                el.recipeProductionMode?.addEventListener('change', () => {
                    syncRecipeStockState();
                    updateInitialStockEditors(currentProduct);
                    refreshProductionReadiness();
                });
                el.recipeOutputQty?.addEventListener('input', refreshProductionReadiness);

                el.editType.addEventListener('change', () => {
                    const productType = getSelectedProductType();
                    const currentUnit = resolveRawMaterialUnit();
                    if (isStockedInternalType(productType) && (!currentUnit || ['pcs', 'unit'].includes(String(currentUnit).toLowerCase()))) {
                        renderRawMaterialUnits(stockUnitDefaults[productType] || 'pcs');
                    } else if (productType === 'finished_good' && !currentUnit) {
                        renderRawMaterialUnits('pcs');
                    }

                    updateTypeNote();
                    updateRawMaterialMetaVisibility();
                    updateInitialStockEditors(currentProduct);
                    updateBomEditorVisibility();
                    updateBomLineCountHint();
                    refreshProductionReadiness();
                });

                el.productionReadinessToggle?.addEventListener('click', () => {
                    readinessUiState.collapsed = !readinessUiState.collapsed;
                    applyProductionReadinessCollapseState();
                });

                el.isActive.addEventListener('change', refreshProductionReadiness);

                el.trackStock.addEventListener('change', () => {
                    updateInitialStockEditors(currentProduct);
                    refreshProductionReadiness();
                });

                el.editUnitSelect.addEventListener('change', () => {
                    const isCustom = el.editUnitSelect.value === '__custom__';
                    el.editUnitCustomWrap.classList.toggle('hidden', !isCustom);
                    if (isCustom) {
                        el.editUnitCustom.focus();
                        return;
                    }

                    el.editUnitCustom.value = '';
                });

                el.addBomLine.addEventListener('click', async () => {
                    if (!rawMaterialProducts.length) {
                        showError('Belum ada produk raw material yang tersedia untuk BOM.');
                        return;
                    }

                    await appendBomLine({});
                    refreshProductionReadiness();
                });

                el.bomLineList.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('[data-bom-remove]');
                    if (!removeButton) {
                        return;
                    }

                    removeButton.closest('.rounded-xl')?.remove();
                    validateBomDuplicatesRealtime();
                    refreshProductionReadiness();
                });

                el.bomLineList.addEventListener('change', async (event) => {
                    const productSelect = event.target.closest('[data-bom-product]');
                    const variantSelect = event.target.closest('[data-bom-variant]');
                    const row = productSelect?.closest('.rounded-xl') || variantSelect?.closest('.rounded-xl');

                    if (productSelect) {
                        await configureBomVariantField(row, productSelect.value || '', null);
                    }

                    if (row) {
                        const duplicateConflict = findDuplicateBomConflict(row);
                        if (duplicateConflict) {
                            const productField = row.querySelector('[data-bom-product]');
                            const variantField = row.querySelector('[data-bom-variant]');

                            if (productField) {
                                productField.value = '';
                                productField.focus();
                            }

                            if (variantField) {
                                variantField.value = '';
                            }

                            await configureBomVariantField(row, '', null);
                            setBomRowConflictTip(row, 'Bahan ini sudah dipakai di line lain. Pilih bahan yang berbeda.');
                            setBomInlineError('');
                        }

                        validateBomDuplicatesRealtime();
                        refreshProductionReadiness();
                    }
                });

                el.bomLineList.addEventListener('input', () => {
                    validateBomDuplicatesRealtime();
                    refreshProductionReadiness();
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
                        unit: currentProduct.unit || null,
                        supplier_id: el.editSupplier.value || null,
                        initial_stock: currentProduct?.has_variants || getSelectedProductType() === 'service' ? 0 : Number(el.stock.value || 0),
                        track_stock: el.trackStock.checked,
                        is_active: el.isActive.checked,
                    };
                    const productType = getSelectedProductType();

                    if (!payload.name || !payload.sku) {
                        showError('Nama produk dan SKU wajib diisi.');
                        return;
                    }

                    if (isSellableType(productType) && payload.price <= 0) {
                        showError('Harga harus lebih dari 0.');
                        return;
                    }

                    if (!isSellableType(productType)) {
                        payload.price = Math.max(0, Number(payload.price || 0));
                    }

                    if (productType !== 'service') {
                        payload.unit = resolveRawMaterialUnit();

                        if (!payload.unit) {
                            showError('Satuan produk wajib diisi.');
                            return;
                        }
                    }

                    if (currentProduct?.has_variants && productType !== 'service') {
                        payload.initial_stock_lines = el.trackStock.checked ? collectVariantStockLines(currentProduct) : [];
                    }

                    if (isStockedInternalType(productType)) {
                        payload.recipe_output_qty = null;

                        payload.product_mode = 'add_on';
                        payload.item_type = productType;
                        payload.production_mode = 'none';
                        payload.bom_items = [];
                    } else if (productType === 'service') {
                        payload.recipe_output_qty = null;
                        payload.product_mode = 'default';
                        payload.item_type = 'service';
                        payload.production_mode = 'none';
                        payload.track_stock = false;
                        payload.initial_stock = 0;
                        payload.initial_stock_lines = [];
                        payload.bom_items = [];
                        payload.unit = null;
                    } else if (canManageRecipe) {
                        if (el.enableBom.checked) {
                            if (!validateBomDuplicatesRealtime()) {
                                return;
                            }

                            const productionMode = getSelectedRecipeProductionMode();
                            payload.recipe_output_qty = productionMode === 'batch_production'
                                ? Number(el.recipeOutputQty?.value || 0)
                                : null;
                            if (productionMode === 'batch_production' && !(payload.recipe_output_qty > 0)) {
                                showError('Hasil standar per 1 resep wajib lebih dari 0 untuk produksi batch.');
                                return;
                            }
                            payload.product_mode = 'bom';
                            payload.item_type = 'finished_good';
                            payload.production_mode = productionMode;
                            payload.track_stock = productionMode === 'batch_production';
                            if (!payload.track_stock) {
                                payload.initial_stock = 0;
                                payload.initial_stock_lines = [];
                            }
                            payload.bom_items = collectBomItems();
                        } else if (String(currentProduct.product_mode || '') === 'bom') {
                            payload.recipe_output_qty = null;
                            payload.product_mode = 'default';
                            payload.item_type = 'finished_good';
                            payload.production_mode = 'none';
                            payload.bom_items = [];
                        } else {
                            payload.recipe_output_qty = null;
                            payload.product_mode = 'default';
                            payload.item_type = 'finished_good';
                            payload.production_mode = 'none';
                        }
                    } else {
                        payload.recipe_output_qty = null;
                        payload.product_mode = 'default';
                        payload.item_type = 'finished_good';
                        payload.production_mode = 'none';
                    }

                    if (payload.product_mode === 'bom' && payload.production_mode === 'batch_production') {
                        payload.track_stock = true;
                    }

                    if (!await confirmStockUnitBeforeSave(productType, payload.unit)) {
                        return;
                    }

                    try {
                        const response = await fetch(`/api/v1/products/${currentProduct.id}`, {
                            method: 'PUT',
                            headers: {
                                ...headers,
                                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(payload),
                        });

                        if (!response.ok) {
                            const error = await response.json();
                            const detailMessage = Object.values(error?.details || {})
                                .flat()
                                .find((message) => typeof message === 'string' && message.trim() !== '');
                            throw new Error(detailMessage || error?.message || 'Gagal menyimpan perubahan.');
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
                            headers: {
                                ...headers,
                                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                            },
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
                    const loadedProduct = productId
                        ? await loadProduct().catch(() => null)
                        : null;
                    const resolvedProduct = loadedProduct
                        ? { ...(initialProduct || {}), ...loadedProduct }
                        : initialProduct;

                    if (!resolvedProduct) {
                        throw new Error('Produk tidak ditemukan.');
                    }

                    renderProduct(resolvedProduct);

                    if (!canManageRecipe && productUsesRecipeManagement(resolvedProduct)) {
                        showError('Produk ini memakai recipe/produksi dan hanya bisa diubah mulai paket Medium.');
                    }

                    const [categoriesResult, suppliersResult, rawMaterialsResult] = await Promise.allSettled([
                        loadCategories(),
                        loadSuppliers(),
                        loadRawMaterialProducts(),
                    ]);

                    const categories = categoriesResult.status === 'fulfilled'
                        ? categoriesResult.value
                        : [];
                    const suppliers = suppliersResult.status === 'fulfilled'
                        ? suppliersResult.value
                        : [];
                    rawMaterialProducts = rawMaterialsResult.status === 'fulfilled'
                        ? rawMaterialsResult.value
                        : [];

                    renderCategoryOptions(
                        categories,
                        currentProduct?.category_id || resolvedProduct.category_id || '',
                        currentProduct?.category_name || resolvedProduct.category_name || ''
                    );

                    renderSupplierOptions(
                        suppliers,
                        currentProduct?.supplier_id || resolvedProduct.supplier_id || '',
                        currentProduct?.supplier_name || resolvedProduct.supplier_name || ''
                    );

                    if (rawMaterialsResult.status === 'rejected' && el.enableBom?.checked) {
                        setBomHint('Daftar raw material gagal dimuat. Detail produk tetap tampil, tetapi editor BOM perlu dimuat ulang.');
                    }
                })().catch((error) => {
                    showError(error.message || 'Gagal memuat data produk.');
                });
            })();
        </script>
@endsection
