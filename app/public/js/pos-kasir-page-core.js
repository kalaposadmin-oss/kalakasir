if (!window.__posKasirPageInitialized) {
    window.__posKasirPageInitialized = true;

    (() => {
        const tenantId = document.querySelector('meta[name="tenant-id"]')?.content || '';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
        const readCookie = (name) => document.cookie
            .split('; ')
            .find((row) => row.startsWith(`${name}=`))
            ?.split('=')
            .slice(1)
            .join('=') || '';
        const csrfHeaders = () => {
            const xsrfToken = readCookie('XSRF-TOKEN');
            if (xsrfToken) {
                return { 'X-XSRF-TOKEN': decodeURIComponent(xsrfToken) };
            }

            return csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {};
        };
        const fetchWriteWithFreshCsrf = async (url, options = {}) => {
            const makeOptions = () => ({
                ...options,
                credentials: 'same-origin',
                headers: {
                    ...(options.headers || {}),
                    ...headers,
                    ...csrfHeaders(),
                },
            });

            let response = await fetch(url, makeOptions());
            if (response.status === 419) {
                await fetch('/sanctum/csrf-cookie', {
                    credentials: 'same-origin',
                    headers,
                }).catch(() => null);
                response = await fetch(url, makeOptions());
            }

            return response;
        };

        const el = {
            cashierName: document.getElementById('cashier-name'),
            shiftIndicator: document.getElementById('shift-indicator'),
            shiftStatus: document.getElementById('shift-status'),
            shiftOpened: document.getElementById('shift-opened-time'),
            quickClose: document.getElementById('quick-close-shift'),
            search: document.getElementById('search-input'),
            customerQuickTrigger: document.getElementById('customer-quick-trigger'),
            customerCurrentLabel: document.getElementById('customer-current-label'),
            transactionDate: document.getElementById('transaction-date-input'),
            editingSaleBanner: document.getElementById('editing-sale-banner'),
            editingSaleInvoice: document.getElementById('editing-sale-invoice'),
            editingSaleBadge: document.getElementById('editing-sale-badge'),
            editingSaleBadgeText: document.getElementById('editing-sale-badge-text'),
            customerSheet: document.getElementById('customer-sheet'),
            customerSheetClose: document.getElementById('customer-sheet-close'),
            customerSheetOverlay: document.getElementById('customer-sheet-overlay'),
            customer: document.getElementById('customer-select'),
            customerCreateTrigger: document.getElementById('customer-create-trigger'),
            customerModal: document.getElementById('customer-modal'),
            customerModalClose: document.getElementById('customer-modal-close'),
            customerModalCancel: document.getElementById('customer-modal-cancel'),
            customerNameInput: document.getElementById('customer-name-input'),
            customerPhoneInput: document.getElementById('customer-phone-input'),
            customerAddressInput: document.getElementById('customer-address-input'),
            customerSaveButton: document.getElementById('customer-save-button'),
            customerModalFeedback: document.getElementById('customer-modal-feedback'),
            savedOrderQuickTrigger: document.getElementById('saved-order-quick-trigger'),
            savedOrderCountLabel: document.getElementById('saved-order-count-label'),
            savedOrderSheet: document.getElementById('saved-order-sheet'),
            savedOrderSheetClose: document.getElementById('saved-order-sheet-close'),
            savedOrderSheetOverlay: document.getElementById('saved-order-sheet-overlay'),
            savedOrderSheetCount: document.getElementById('saved-order-sheet-count'),
            receivableQuickTrigger: document.getElementById('receivable-quick-trigger'),
            receivableSheet: document.getElementById('receivable-sheet'),
            receivableSheetClose: document.getElementById('receivable-sheet-close'),
            receivableSheetOverlay: document.getElementById('receivable-sheet-overlay'),
            categoryFilters: document.getElementById('category-filters'),
            productGrid: document.getElementById('product-grid'),
            productViewList: document.getElementById('product-view-list'),
            productViewGrid: document.getElementById('product-view-grid'),
            cartList: document.getElementById('cart-list'),
            clearCart: document.getElementById('clear-cart'),
            itemCount: document.getElementById('item-count'),
            subtotal: document.getElementById('subtotal'),
            taxLabel: document.getElementById('tax-label'),
            tax: document.getElementById('tax'),
            itemDiscountAmount: document.getElementById('item-discount-amount'),
            transactionDiscountAmount: document.getElementById('transaction-discount-amount'),
            discountAmount: document.getElementById('discount-amount'),
            discountToggle: document.getElementById('discount-toggle'),
            discountToggleLabel: document.getElementById('discount-toggle-label'),
            discountPanel: document.getElementById('discount-panel'),
            discountList: document.getElementById('discount-list'),
            transactionDiscountList: document.getElementById('transaction-discount-list'),
            transactionDiscountAdd: document.getElementById('transaction-discount-add'),
            discountEmpty: document.getElementById('discount-empty'),
            feeList: document.getElementById('fee-list'),
            feeAdd: document.getElementById('fee-add'),
            feeAddLabel: document.getElementById('fee-add-label'),
            feeTotal: document.getElementById('fee-total'),
            total: document.getElementById('total'),
            checkout: document.getElementById('checkout-button'),
            checkoutCompact: document.getElementById('checkout-button-compact'),
            paymentLink: document.getElementById('payment-link'),
            cartToggle: document.getElementById('cart-toggle'),
            cartOverlay: document.getElementById('cart-overlay'),
            cartDrawer: document.getElementById('cart-drawer'),
            cartClose: document.getElementById('cart-close'),
            cartBadge: document.getElementById('cart-badge'),
            cartCountLabel: document.getElementById('cart-count-label'),
            summaryCount: document.getElementById('summary-item-count'),
            summaryTotal: document.getElementById('summary-total'),
            summaryCaption: document.getElementById('summary-caption'),
            bottomNav: document.getElementById('bottom-nav'),
            shiftGuard: document.getElementById('shift-guard'),
            customerFeatureNotice: document.getElementById('customer-feature-notice'),
            saveOrder: document.getElementById('save-order'),
            saveOrderLabel: document.getElementById('save-order-label'),
            saveOrderModeBadge: document.getElementById('save-order-mode-badge'),
            orderHolderName: document.getElementById('order-holder-name'),
            orderNote: document.getElementById('order-note'),
            savedOrderList: document.getElementById('saved-order-list'),
            savedOrderEmpty: document.getElementById('saved-order-empty'),
        };

        const canUseCustomerFeature = Boolean(el.customer) && el.customer.dataset.featureEnabled !== 'false';
        const productViewStorageKey = 'pos_product_view_mode';
        const normalizeProductViewMode = (value) => value === 'grid' ? 'grid' : 'list';
        const readStoredProductViewMode = () => {
            try {
                return normalizeProductViewMode(window.localStorage.getItem(productViewStorageKey));
            } catch (_) {
                return 'list';
            }
        };

        const state = {
            branchId: null,
            shiftId: null,
            locationId: null,
            registerId: null,
            customers: [],
            categories: [],
            products: [],
            registers: [],
            accountMapping: null,
            cart: new Map(),
            category: 'all',
            term: '',
            shiftBlocked: false,
            productRequestSeq: 0,
            productPagination: {
                currentPage: 1,
                perPage: 24,
                hasMore: false,
                total: 0,
            },
            productsLoading: false,
            taxRate: 0,
            roundingSettings: {
                mode: 'none',
                increment: 0,
            },
            promos: [],
            selectedPromos: new Set(),
            transactionDiscounts: [],
            fees: [],
            activeItemDiscountEditor: null,
            productViewMode: readStoredProductViewMode(),
            pendingShiftReconciliation: null,
            savedOrders: [],
            activeSavedOrderId: null,
            replacingSaleId: null,
            replacingTransactionDate: null,
            pendingDraftRestore: false,
            cartPointerStartedInsideDrawer: false,
            qtyInputTimer: null,
        };

        const cartStorageKey = 'pos_cart_draft';
        const savedOrdersStorageKey = 'pos_saved_orders_v1';

        const createLocalId = () => crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;

        const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(value || 0);

        const formatRate = (rate) => {
            const numeric = Number(rate || 0);
            if (!Number.isFinite(numeric)) {
                return '0';
            }
            return Number.isInteger(numeric) ? String(numeric) : numeric.toFixed(2).replace(/\.00$/, '');
        };

        const formatNumber = (value) => {
            const numeric = Number(value || 0);
            if (!Number.isFinite(numeric)) {
                return '0';
            }
            return Number.isInteger(numeric) ? String(numeric) : numeric.toFixed(2).replace(/\.00$/, '');
        };

        const setText = (target, value) => {
            if (target) {
                target.textContent = value;
            }
        };

        const todayDateString = () => {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        };

        const normalizeTransactionDate = (value) => {
            const text = String(value || '').trim();
            if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) {
                return todayDateString();
            }

            return text > todayDateString() ? todayDateString() : text;
        };

        const getSelectedTransactionDate = () => normalizeTransactionDate(el.transactionDate?.value || state.replacingTransactionDate || null);

        if (el.transactionDate) {
            const today = todayDateString();
            el.transactionDate.max = today;
            el.transactionDate.value = normalizeTransactionDate(el.transactionDate.value || today);
        }

        const syncEditingSaleBanner = () => {
            const isEditingSale = Boolean(state.replacingSaleId);
            el.editingSaleBanner?.classList.toggle('hidden', !isEditingSale);
            el.editingSaleBadge?.classList.toggle('hidden', !isEditingSale);
            if (!isEditingSale) {
                return;
            }

            const saleLabel = state.replacingSaleId
                ? `#${String(state.replacingSaleId).slice(0, 8).toUpperCase()}`
                : '-';
            setText(el.editingSaleInvoice, saleLabel);
            setText(el.editingSaleBadgeText, 'Keranjang lama sudah dimuat dan siap diedit.');
        };

        const updateSaveOrderModeUi = () => {
            const editing = Boolean(state.activeSavedOrderId);
            setText(el.saveOrderLabel, editing ? 'Update Pesanan' : 'Simpan');
            el.saveOrderModeBadge?.classList.toggle('hidden', !editing);

            if (!el.saveOrder) {
                return;
            }

            el.saveOrder.classList.toggle('bg-white', !editing);
            el.saveOrder.classList.toggle('bg-amber-50', editing);
            el.saveOrder.classList.toggle('text-slate-700', !editing);
            el.saveOrder.classList.toggle('text-amber-800', editing);
            el.saveOrder.classList.toggle('border', editing);
            el.saveOrder.classList.toggle('border-amber-200', editing);
            el.saveOrder.classList.toggle('shadow-sm', !editing);
        };

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');

        const formatDateOnly = (value) => {
            if (!value) {
                return null;
            }
            const parsed = new Date(value);
            return Number.isNaN(parsed.getTime()) ? null : parsed;
        };

        const getStock = (product) => {
            if (!product?.track_stock) {
                return null;
            }
            return Number(product.qty_on_hand || 0);
        };

        const resolveUnitPrice = (product, qty) => {
            const tiers = Array.isArray(product.price_tiers) ? product.price_tiers : [];
            const qtyValue = Number(qty || 0);
            let price = Number(product.price || 0);
            tiers.forEach((tier) => {
                const minQty = Number(tier.min_qty || 0);
                const tierPrice = Number(tier.price || 0);
                if (qtyValue >= minQty && tierPrice > 0) {
                    price = tierPrice;
                }
            });
            return price;
        };

        const activePriceTier = (product, qty) => {
            const tiers = Array.isArray(product?.price_tiers) ? product.price_tiers : [];
            const qtyValue = Number(qty || 0);
            return tiers.reduce((active, tier) => {
                const minQty = Number(tier.min_qty || 0);
                const tierPrice = Number(tier.price || 0);
                if (qtyValue >= minQty && tierPrice > 0 && (!active || minQty >= Number(active.min_qty || 0))) {
                    return tier;
                }

                return active;
            }, null);
        };

        const getProductInitials = (name) => {
            const words = String(name || '')
                .trim()
                .split(/\s+/)
                .filter(Boolean)
                .slice(0, 2);
            if (!words.length) {
                return 'PR';
            }
            return words.map((word) => word.charAt(0).toUpperCase()).join('');
        };

        const getProductMeta = (product) => {
            const parts = [];
            if (product?.category_name) {
                parts.push(product.category_name);
            } else if (product?.product_type === 'raw_material') {
                parts.push('Bahan');
            } else if (product?.product_type === 'finished_good') {
                parts.push('Produk');
            }
            if (product?.unit) {
                parts.push(product.unit);
            }
            return parts.join(' / ') || 'Produk aktif';
        };

        const getProductStockText = (product, stock, isOut) => {
            if (!product?.track_stock || stock === null) {
                return 'Non stok';
            }
            if (isOut) {
                return 'Stok habis';
            }
            return `Stok ${formatNumber(stock)}${product?.unit ? ` ${product.unit}` : ''}`;
        };

        const getProductVisualMarkup = (product, isOut) => {
            if (product?.image_url) {
                return `
                    <div class="relative aspect-[4/3] overflow-hidden rounded-2xl bg-slate-100">
                        <img alt="${product.name}" class="h-full w-full object-cover" src="${product.image_url}" />
                        ${isOut ? `
                            <div class="absolute inset-0 flex items-center justify-center bg-slate-950/25">
                                <span class="rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-rose-600">Habis</span>
                            </div>
                        ` : ''}
                    </div>
                `;
            }

            return `
                <div class="flex aspect-[4/3] items-center justify-center rounded-2xl border border-dashed ${isOut ? 'border-rose-200 bg-rose-50 text-rose-500' : 'border-primary/15 bg-primary/5 text-primary'}">
                    <div class="text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-sm font-black shadow-sm">${getProductInitials(product?.name)}</div>
                        <p class="mt-2 text-[10px] font-bold uppercase tracking-[0.18em]">${isOut ? 'Stok habis' : 'Mode ringkas'}</p>
                    </div>
                </div>
            `;
        };

        const getCartProductMedia = (product) => {
            if (product?.image_url) {
                return `
                    <div class="h-14 w-14 overflow-hidden rounded-xl bg-slate-100">
                        <img alt="${product.name}" class="h-full w-full object-cover" src="${product.image_url}" />
                    </div>
                `;
            }

            return `
                <div class="flex h-14 w-14 items-center justify-center rounded-xl border border-dashed border-primary/15 bg-primary/5 text-sm font-black text-primary">
                    ${getProductInitials(product?.name)}
                </div>
            `;
        };

        const syncProductViewControls = () => {
            [
                { node: el.productViewList, mode: 'list' },
                { node: el.productViewGrid, mode: 'grid' },
            ].forEach(({ node, mode }) => {
                if (!node) {
                    return;
                }
                const active = state.productViewMode === mode;
                node.classList.toggle('bg-white', active);
                node.classList.toggle('text-slate-900', active);
                node.classList.toggle('shadow-sm', active);
                node.classList.toggle('text-slate-500', !active);
                node.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        };

        const syncProductGridLayout = () => {
            if (!el.productGrid) {
                return;
            }
            el.productGrid.className = state.productViewMode === 'grid'
                ? 'grid grid-cols-2 gap-3'
                : 'grid grid-cols-1 gap-2';
        };

        const setProductViewMode = (mode) => {
            state.productViewMode = normalizeProductViewMode(mode);
            try {
                window.localStorage.setItem(productViewStorageKey, state.productViewMode);
            } catch (_) {
                // ignore storage failures
            }
            syncProductViewControls();
            syncProductGridLayout();
            renderProducts();
        };

        const saveCartDraft = () => {
            const items = [...state.cart.values()].map((item) => ({
                product_id: item.product.id,
                qty: item.qty,
                discounts: Array.isArray(item.discounts) ? item.discounts : [],
                note: String(item.note || '').trim(),
            }));
            if (!items.length) {
                if (state.pendingDraftRestore) {
                    return;
                }
                sessionStorage.removeItem(cartStorageKey);
                return;
            }
            sessionStorage.setItem(cartStorageKey, JSON.stringify({
                items,
                customer_id: el.customer ? (el.customer.value || null) : null,
                fees: state.fees,
                selected_promos: [...state.selectedPromos],
                transaction_discounts: state.transactionDiscounts,
                order_holder_name: el.orderHolderName ? String(el.orderHolderName.value || '').trim() : '',
                order_note: el.orderNote ? String(el.orderNote.value || '').trim() : '',
                replace_sale_id: state.replacingSaleId || null,
                transaction_date: getSelectedTransactionDate(),
                updated_at: new Date().toISOString(),
            }));
        };

        const loadCartDraft = () => {
            try {
                const raw = sessionStorage.getItem(cartStorageKey);
                return raw ? JSON.parse(raw) : null;
            } catch (_) {
                return null;
            }
        };

        const loadSavedOrders = () => {
            try {
                const raw = localStorage.getItem(savedOrdersStorageKey);
                const parsed = raw ? JSON.parse(raw) : [];
                if (!Array.isArray(parsed)) {
                    return [];
                }
                return parsed
                    .map((entry) => ({
                        id: String(entry?.id || createLocalId()),
                        holder_name: String(entry?.holder_name || '').trim(),
                        note: String(entry?.note || '').trim(),
                        items: Array.isArray(entry?.items) ? entry.items : [],
                        fees: Array.isArray(entry?.fees) ? entry.fees : [],
                        selected_promos: Array.isArray(entry?.selected_promos) ? entry.selected_promos : [],
                        transaction_discounts: Array.isArray(entry?.transaction_discounts) ? entry.transaction_discounts : [],
                        transaction_date: entry?.transaction_date || null,
                        customer_id: entry?.customer_id || null,
                        total: Number(entry?.total || 0),
                        item_count: Number(entry?.item_count || 0),
                        created_at: entry?.created_at || new Date().toISOString(),
                    }))
                    .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());
            } catch (_) {
                return [];
            }
        };

        const persistSavedOrders = () => {
            try {
                localStorage.setItem(savedOrdersStorageKey, JSON.stringify(state.savedOrders));
            } catch (_) {
                // ignore storage failures
            }
        };

        const upsertSavedOrder = (savedOrder) => {
            const normalized = {
                ...savedOrder,
                id: String(savedOrder?.id || createLocalId()),
            };
            state.savedOrders = [normalized, ...state.savedOrders.filter((entry) => String(entry.id) !== normalized.id)];
            persistSavedOrders();
            renderSavedOrders();
        };

        const loadSavedOrdersFromApi = async () => {
            const response = await fetch('/api/v1/pos/saved-orders?limit=100', { headers });
            if (!response.ok) {
                const mapped = await window.PosUi.mapApiError(response, 'Gagal memuat pesanan tersimpan.');
                throw new Error(mapped.message);
            }
            const payload = await response.json().catch(() => ({}));
            return Array.isArray(payload.saved_orders)
                ? payload.saved_orders.map((entry) => ({
                    ...entry,
                    id: String(entry?.id || createLocalId()),
                }))
                : [];
        };

        const saveOrderToApi = async (payload, savedOrderId = null) => {
            const response = await fetchWriteWithFreshCsrf(savedOrderId ? `/api/v1/pos/saved-orders/${savedOrderId}` : '/api/v1/pos/saved-orders', {
                method: savedOrderId ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                const mapped = await window.PosUi.mapApiError(response, 'Gagal menyimpan pesanan.');
                throw new Error(mapped.message);
            }

            const data = await response.json().catch(() => ({}));
            return data.saved_order || null;
        };

        const deleteSavedOrderFromApi = async (id) => {
            const response = await fetchWriteWithFreshCsrf(`/api/v1/pos/saved-orders/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok && response.status !== 404) {
                const mapped = await window.PosUi.mapApiError(response, 'Gagal menghapus pesanan.');
                throw new Error(mapped.message);
            }
        };

        const createDiscountLine = (type, value, label, source = 'manual', referenceId = null) => ({
            type: type === 'percent' ? 'percent' : 'fixed',
            value: Number(value || 0),
            label,
            source,
            reference_id: referenceId,
        });

        const applyDiscountLines = (baseAmount, discountLines) => {
            let remaining = Math.max(Number(baseAmount || 0), 0);
            const lines = [];
            let total = 0;

            discountLines.forEach((line) => {
                const value = Number(line?.value || 0);
                if (value <= 0 || remaining <= 0) {
                    return;
                }
                const basisAmount = remaining;
                const candidate = line.type === 'percent'
                    ? basisAmount * (value / 100)
                    : value;
                const appliedValue = Math.min(Math.max(candidate, 0), remaining);
                if (appliedValue <= 0) {
                    return;
                }
                remaining = Math.max(remaining - appliedValue, 0);
                total += appliedValue;
                lines.push({
                    ...line,
                    basis_amount: basisAmount,
                    applied_value: appliedValue,
                });
            });

            return {
                total,
                lines,
            };
        };

        const getItemDiscountLines = (item) => {
            const discounts = Array.isArray(item?.discounts) ? item.discounts : [];
            return discounts
                .map((discount) => createDiscountLine(
                    discount.type,
                    discount.value,
                    discount.label || 'Diskon item',
                    discount.source || 'manual',
                    discount.reference_id || null,
                ))
                .filter((discount) => Number(discount.value || 0) > 0);
        };

        const getTransactionDiscountLines = (baseAmount) => {
            const lines = [];

            state.promos.forEach((promo) => {
                if (!state.selectedPromos.has(promo.id)) {
                    return;
                }
                if (promo.status && promo.status !== 'active') {
                    return;
                }
                const endsAt = formatDateOnly(promo.ends_at);
                if (endsAt && endsAt < new Date()) {
                    return;
                }
                const minPurchase = Number(promo.min_purchase || 0);
                if (minPurchase > 0 && baseAmount < minPurchase) {
                    return;
                }
                lines.push(createDiscountLine(
                    promo.type,
                    promo.value,
                    promo.name || 'Promo',
                    'promo',
                    promo.id,
                ));
            });

            state.transactionDiscounts.forEach((discount) => {
                if (Number(discount.value || 0) <= 0) {
                    return;
                }
                lines.push(createDiscountLine(
                    discount.type,
                    discount.value,
                    discount.type === 'percent' ? 'Diskon transaksi (%)' : 'Diskon transaksi',
                    'manual',
                    null,
                ));
            });

            return lines;
        };

        const renderCategories = () => {
            el.categoryFilters.innerHTML = '';
            const allBtn = document.createElement('button');
            allBtn.className = 'whitespace-nowrap rounded-full bg-primary px-5 py-1.5 text-xs font-bold text-white';
            allBtn.dataset.category = 'all';
            allBtn.textContent = 'Semua';
            el.categoryFilters.appendChild(allBtn);

            state.categories.forEach((category) => {
                const button = document.createElement('button');
                button.className = 'whitespace-nowrap rounded-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-5 py-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400';
                button.dataset.category = category.id;
                button.textContent = category.name;
                el.categoryFilters.appendChild(button);
            });
            applyCategoryStyles();
        };

        const applyCategoryStyles = () => {
            el.categoryFilters.querySelectorAll('button').forEach((button) => {
                const active = button.dataset.category === state.category;
                button.classList.toggle('bg-primary', active);
                button.classList.toggle('text-white', active);
                button.classList.toggle('bg-slate-100', !active);
                button.classList.toggle('text-slate-600', !active);
            });
        };

        const renderProducts = () => {
            syncProductViewControls();
            syncProductGridLayout();
            el.productGrid.innerHTML = '';
            const products = state.products;

            if (!products.length && state.productsLoading) {
                el.productGrid.innerHTML = '<div class="col-span-full rounded-2xl bg-white px-4 py-5 text-sm text-slate-400 shadow-sm">Memuat katalog produk...</div>';
                return;
            }

            if (!products.length) {
                el.productGrid.innerHTML = '<div class="col-span-full rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-5 text-sm text-slate-400">Produk tidak ditemukan.</div>';
                return;
            }

            products.forEach((product) => {
                const stock = getStock(product);
                const item = state.cart.get(product.id);
                const card = document.createElement('button');
                card.type = 'button';
                const isOut = product.track_stock && stock !== null && stock <= 0;
                const unitPrice = resolveUnitPrice(product, 1);
                const tiers = Array.isArray(product.price_tiers) ? product.price_tiers : [];
                const firstTier = tiers.find((tier) => Number(tier.min_qty || 0) > 1) || activePriceTier(product, 1);
                const tierHint = firstTier
                    ? `<p class="mt-0.5 text-[10px] font-semibold text-emerald-600">${firstTier.label || `Min ${firstTier.min_qty}`} ${formatCurrency(firstTier.price || 0)}</p>`
                    : '';
                const metaLine = getProductMeta(product);
                const stockText = getProductStockText(product, stock, isOut);
                const isListMode = state.productViewMode === 'list';
                card.className = isListMode
                    ? `group relative overflow-hidden rounded-2xl border bg-white px-3 py-3 text-left shadow-sm ${item ? 'border-primary/35 ring-1 ring-primary/10' : 'border-slate-200'} ${isOut ? 'opacity-70' : 'transition-transform active:scale-[0.99]'}` 
                    : `group relative flex flex-col overflow-hidden rounded-3xl border bg-white p-3 text-left shadow-sm ${item ? 'border-primary/35 ring-1 ring-primary/10' : 'border-slate-200'} ${isOut ? 'opacity-70' : 'transition-transform active:scale-[0.98]'}`;

                if (isListMode) {
                    card.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-slate-900">${product.name}</p>
                                <p class="mt-1 text-[11px] text-slate-500">${metaLine}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-sm font-black text-slate-900">${formatCurrency(unitPrice)}</p>
                                ${tierHint}
                                <p class="mt-1 text-[10px] font-semibold ${isOut ? 'text-rose-600' : 'text-slate-400'}">${stockText}</p>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <span class="inline-flex items-center gap-1 rounded-full ${item ? 'bg-primary/10 text-primary' : 'bg-slate-100 text-slate-500'} px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.16em]">
                                <span class="material-symbols-outlined text-sm">${item ? 'shopping_bag' : 'sell'}</span>
                                ${item ? `${item.qty} di keranjang` : 'Siap tambah'}
                            </span>
                            <span class="inline-flex h-9 min-w-[92px] items-center justify-center gap-1 rounded-xl ${isOut ? 'bg-slate-100 text-slate-400' : 'bg-primary text-white'} px-3 text-xs font-bold">
                                <span class="material-symbols-outlined text-base">${isOut ? 'block' : 'add'}</span>
                                ${isOut ? 'Habis' : 'Tambah'}
                            </span>
                        </div>
                    `;
                } else {
                    card.innerHTML = `
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-2 text-sm font-bold text-slate-900">${product.name}</p>
                                <p class="mt-1 text-[11px] text-slate-500">${metaLine}</p>
                            </div>
                            <span class="inline-flex h-8 min-w-[32px] items-center justify-center rounded-xl ${item ? 'bg-primary text-white' : 'bg-primary/10 text-primary'} px-2 text-xs font-black">
                                ${item ? item.qty : '+'}
                            </span>
                        </div>
                        <div class="mt-3">
                            ${getProductVisualMarkup(product, isOut)}
                        </div>
                        <div class="mt-3 flex items-end justify-between gap-3">
                            <div>
                                <p class="text-sm font-black text-slate-900">${formatCurrency(unitPrice)}</p>
                                ${tierHint}
                                <p class="mt-1 text-[10px] font-semibold ${isOut ? 'text-rose-600' : 'text-slate-400'}">${stockText}</p>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full ${isOut ? 'bg-slate-100 text-slate-400' : 'bg-slate-100 text-slate-700'} px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.16em]">
                                <span class="material-symbols-outlined text-sm">${isOut ? 'block' : 'touch_app'}</span>
                                ${isOut ? 'Habis' : 'Tap pilih'}
                            </span>
                        </div>
                    `;
                }
                card.addEventListener('click', () => addToCart(product));
                el.productGrid.appendChild(card);
            });

            if (state.productPagination.hasMore) {
                const loadMore = document.createElement('button');
                loadMore.type = 'button';
                loadMore.className = 'col-span-full mt-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-primary shadow-sm';
                loadMore.textContent = state.productsLoading ? 'Memuat...' : 'Muat produk lainnya';
                loadMore.disabled = state.productsLoading;
                loadMore.addEventListener('click', () => loadProducts({ page: state.productPagination.currentPage + 1, append: true }));
                el.productGrid.appendChild(loadMore);
            }
        };

        const addToCart = (product) => {
            if (state.shiftBlocked) {
                alert('Shift belum aktif. Silakan buka shift terlebih dahulu.');
                return;
            }

            const wasEmpty = state.cart.size === 0;
            const existing = state.cart.get(product.id);
            const qty = existing ? existing.qty + 1 : 1;
            if (product.track_stock) {
                const stock = getStock(product);
                if (stock !== null && qty > stock) {
                    alert('Stok produk tidak cukup.');
                    return;
                }
            }
            const unitPrice = resolveUnitPrice(product, qty);
            state.cart.set(product.id, {
                product,
                qty,
                unit_price: unitPrice,
                discounts: existing?.discounts || [],
                note: String(existing?.note || '').trim(),
            });
            renderCart();
            renderProducts();
            if (wasEmpty) {
                openCart();
            }
        };

        const updateCartQty = (productId, delta) => {
            if (state.shiftBlocked) {
                return;
            }

            const existing = state.cart.get(productId);
            if (!existing) {
                return;
            }
            const next = existing.qty + delta;
            if (next <= 0) {
                state.cart.delete(productId);
            } else {
                if (existing.product.track_stock) {
                    const stock = getStock(existing.product);
                    if (stock !== null && next > stock) {
                        alert('Stok produk tidak cukup.');
                        return;
                    }
                }
                state.cart.set(productId, {
                    ...existing,
                    qty: next,
                    unit_price: resolveUnitPrice(existing.product, next),
                    note: String(existing.note || '').trim(),
                });
            }
            renderCart();
            renderProducts();
        };

        const setCartQty = (productId, rawQty) => {
            if (state.shiftBlocked) {
                return;
            }

            const existing = state.cart.get(productId);
            if (!existing) {
                return;
            }

            const normalized = String(rawQty || '').replace(',', '.').trim();
            const next = Number(normalized);
            if (!Number.isFinite(next) || next <= 0) {
                alert('Jumlah harus lebih dari 0.');
                renderCart();
                return;
            }

            if (existing.product.track_stock) {
                const stock = getStock(existing.product);
                if (stock !== null && next > stock) {
                    alert('Stok produk tidak cukup.');
                    renderCart();
                    return;
                }
            }

            state.cart.set(productId, {
                ...existing,
                qty: next,
                unit_price: resolveUnitPrice(existing.product, next),
                note: String(existing.note || '').trim(),
            });
            renderCart();
            renderProducts();
        };

        const summarizeDiscount = (discount) => {
            if (!discount) {
                return null;
            }
            return discount.type === 'percent'
                ? `${discount.label || 'Diskon'} ${Number(discount.value || 0)}%`
                : `${discount.label || 'Diskon'} ${formatCurrency(discount.value || 0)}`;
        };

        const computeFeeTotal = (productSubtotal) => {
            let total = 0;
            state.fees.forEach((fee) => {
                if (!fee.product_id) {
                    return;
                }
                const value = Number(fee.value || 0);
                if (value <= 0) {
                    return;
                }
                if (fee.type === 'percent') {
                    total += productSubtotal * (value / 100);
                    return;
                }
                total += value;
            });
            return Math.max(total, 0);
        };

        const roundMoney = (value) => Math.round((Number(value) || 0) * 100) / 100;

        const applySalesRounding = (amount) => {
            const rawAmount = roundMoney(amount);
            const settings = state.roundingSettings || {};
            const mode = settings.mode || 'none';
            const increment = Math.max(0, Math.floor(Number(settings.increment || 0)));

            if (mode === 'nearest') {
                return roundMoney(Math.round(rawAmount));
            }
            if (mode === 'up') {
                return roundMoney(Math.ceil(rawAmount));
            }
            if (mode === 'down') {
                return roundMoney(Math.floor(rawAmount));
            }
            if (mode === 'multiple' && increment > 0) {
                return roundMoney(Math.ceil(rawAmount / increment) * increment);
            }

            return rawAmount;
        };

        const computeCartPricing = () => {
            const cartItems = [...state.cart.values()];
            const lineItems = cartItems.map((item) => {
                const lineSubtotal = item.unit_price * item.qty;
                const appliedDiscounts = applyDiscountLines(lineSubtotal, getItemDiscountLines(item));

                return {
                    item,
                    lineSubtotal,
                    itemDiscountTotal: appliedDiscounts.total,
                    lineNetSubtotal: Math.max(lineSubtotal - appliedDiscounts.total, 0),
                    discountBreakdown: appliedDiscounts.lines,
                };
            });
            const productSubtotal = lineItems.reduce((sum, line) => sum + line.lineNetSubtotal, 0);
            const feeTotal = roundMoney(computeFeeTotal(productSubtotal));
            const transactionDiscounts = applyDiscountLines(productSubtotal, getTransactionDiscountLines(productSubtotal));
            const subtotal = roundMoney(productSubtotal + feeTotal);
            const transactionDiscountTotal = roundMoney(transactionDiscounts.total);
            const tax = roundMoney((subtotal - transactionDiscountTotal) * state.taxRate);
            const totalBeforeRounding = roundMoney(subtotal - transactionDiscountTotal + tax);
            const total = applySalesRounding(totalBeforeRounding);
            const roundingAdjustment = roundMoney(total - totalBeforeRounding);

            return {
                lineItems,
                productSubtotal,
                feeTotal,
                subtotal,
                itemDiscountTotal: lineItems.reduce((sum, line) => sum + line.itemDiscountTotal, 0),
                transactionDiscountTotal,
                totalDiscount: roundMoney(lineItems.reduce((sum, line) => sum + line.itemDiscountTotal, 0) + transactionDiscountTotal),
                transactionDiscounts: transactionDiscounts.lines,
                tax,
                totalBeforeRounding,
                roundingAdjustment,
                total,
                itemCount: cartItems.reduce((sum, item) => sum + item.qty, 0),
            };
        };

        const getCheckoutBlockReason = (items = [...state.cart.values()]) => {
            if (state.pendingShiftReconciliation) {
                return state.pendingShiftReconciliation.message || 'Selesaikan kas akhir shift kemarin terlebih dahulu.';
            }
            if (!state.shiftId) {
                return 'Buka shift dulu sebelum transaksi.';
            }
            if (!state.locationId) {
                return 'Lokasi transaksi belum siap.';
            }
            if (!state.registerId) {
                return 'Register transaksi belum siap.';
            }
            if (!state.accountMapping?.cash_account_id) {
                return 'Akun kas belum siap.';
            }
            if (!items.length) {
                return 'Tambahkan produk ke keranjang.';
            }
            return null;
        };

        const updateTotals = (pricing = computeCartPricing()) => {
            const items = [...state.cart.values()];
            const subtotal = pricing.subtotal;
            const tax = pricing.tax;
            const total = pricing.total;
            const itemCount = items.reduce((sum, item) => sum + item.qty, 0);
            setText(el.itemCount, itemCount);
            setText(el.cartBadge, itemCount);
            setText(el.cartCountLabel, itemCount);
            setText(el.summaryCount, itemCount);
            if (el.bottomNav) {
                el.bottomNav.classList.toggle('hidden', itemCount === 0);
            }
            setText(el.subtotal, formatCurrency(subtotal));
            setText(el.itemDiscountAmount, formatCurrency(pricing.itemDiscountTotal));
            setText(el.transactionDiscountAmount, formatCurrency(pricing.transactionDiscountTotal));
            setText(el.tax, formatCurrency(tax));
            setText(el.discountAmount, formatCurrency(pricing.totalDiscount));
            setText(el.feeTotal, formatCurrency(pricing.feeTotal));
            setText(el.total, formatCurrency(total));
            setText(el.summaryTotal, formatCurrency(total));
            const checkoutBlockReason = getCheckoutBlockReason(items);
            const isCheckoutDisabled = checkoutBlockReason !== null;
            el.checkout.disabled = isCheckoutDisabled;
            el.checkout.classList.toggle('opacity-60', el.checkout.disabled);
            el.checkout.classList.toggle('cursor-not-allowed', el.checkout.disabled);
            setText(el.summaryCaption, checkoutBlockReason ?? 'Siap dibayar. Cek keranjang lalu lanjut.');
            if (el.checkoutCompact) {
                el.checkoutCompact.disabled = el.checkout.disabled;
                el.checkoutCompact.classList.toggle('cursor-not-allowed', el.checkout.disabled);
                el.checkoutCompact.classList.toggle('bg-slate-200', el.checkout.disabled);
                el.checkoutCompact.classList.toggle('text-slate-400', el.checkout.disabled);
                el.checkoutCompact.classList.toggle('bg-primary', !el.checkout.disabled);
                el.checkoutCompact.classList.toggle('text-white', !el.checkout.disabled);
                el.checkoutCompact.innerHTML = `${isCheckoutDisabled ? 'Belum Siap' : 'Lanjut Bayar'} <span class="material-symbols-outlined text-lg">chevron_right</span>`;
            }
            if (el.paymentLink) {
                el.paymentLink.disabled = isCheckoutDisabled;
                el.paymentLink.classList.toggle('opacity-50', isCheckoutDisabled);
                el.paymentLink.classList.toggle('cursor-not-allowed', isCheckoutDisabled);
                el.paymentLink.classList.toggle('border-slate-200', isCheckoutDisabled);
                el.paymentLink.classList.toggle('text-slate-400', isCheckoutDisabled);
                el.paymentLink.classList.toggle('border-primary/20', !isCheckoutDisabled);
                el.paymentLink.classList.toggle('text-primary', !isCheckoutDisabled);
                el.paymentLink.title = checkoutBlockReason ?? 'Lanjut ke pembayaran';
            }
            const activePromoCount = state.selectedPromos.size + state.transactionDiscounts.filter((discount) => Number(discount.value || 0) > 0).length;
            const activeFeeCount = state.fees.filter((fee) => (fee.name || '').trim() !== '' || Number(fee.value || 0) > 0).length;
            setText(el.discountToggleLabel, activePromoCount > 0 ? `Diskon (${activePromoCount})` : 'Diskon');
            setText(el.feeAddLabel, activeFeeCount > 0 ? `Biaya (${activeFeeCount})` : 'Biaya');

            return pricing;
        };

        const renderCart = () => {
            const pricing = computeCartPricing();
            el.cartList.innerHTML = '';
            if (!state.cart.size) {
                el.cartList.innerHTML = `
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-center">
                        <p class="text-sm font-semibold text-slate-600">Keranjang masih kosong.</p>
                        <p class="mt-1 text-xs text-slate-400">Tambahkan produk dari daftar untuk mulai checkout.</p>
                        <button id="cart-empty-close" class="mt-3 inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-xs font-bold text-primary border border-primary/10" type="button">
                            <span class="material-symbols-outlined text-sm">storefront</span>
                            Kembali ke Produk
                        </button>
                    </div>
                `;
            } else {
                pricing.lineItems.forEach((line) => {
                    const { item } = line;
                    const itemDiscount = Array.isArray(item.discounts) ? item.discounts[0] : null;
                    const itemDiscountSummary = summarizeDiscount(itemDiscount);
                    const itemNote = String(item.note || '').trim();
                    const isDiscountEditorOpen = state.activeItemDiscountEditor === item.product.id;
                    const tier = activePriceTier(item.product, item.qty);
                    const tierBadge = tier
                        ? `<p class="mt-1 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">${tier.label || `Min ${tier.min_qty}`} aktif</p>`
                        : '';
                    const row = document.createElement('div');
                    row.className = 'rounded-2xl bg-slate-50 dark:bg-slate-800/50 p-3 border border-slate-100 dark:border-slate-700';
                    row.innerHTML = `
                        <div class="grid grid-cols-[auto_minmax(0,1fr)] gap-3">
                            ${getCartProductMedia(item.product)}
                            <div class="min-w-0">
                                <h4 class="break-words text-sm font-bold leading-5 text-slate-800 dark:text-slate-100">${item.product.name}</h4>
                                <p class="text-[11px] text-slate-500">${formatCurrency(item.unit_price)}</p>
                                ${tierBadge}
                                ${itemNote ? `<p class="mt-1 line-clamp-2 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-medium text-amber-800">Catatan: ${escapeHtml(itemNote)}</p>` : ''}
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <button class="item-discount-toggle inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700" data-id="${item.product.id}" type="button">
                                        <span class="material-symbols-outlined text-sm">sell</span>
                                        ${itemDiscountSummary ? 'Ubah Diskon' : 'Diskon Item'}
                                    </button>
                                    ${itemDiscountSummary ? `<span class="text-[10px] font-semibold text-emerald-700">${itemDiscountSummary}${line.itemDiscountTotal > 0 ? ` · ${formatCurrency(line.itemDiscountTotal)}` : ''}</span>` : ''}
                                </div>
                            </div>
                            <div class="col-span-2 flex flex-wrap items-center justify-between gap-2">
                                <button class="item-remove flex h-9 w-9 items-center justify-center rounded-full bg-rose-50 text-rose-500" data-id="${item.product.id}" type="button" aria-label="Hapus item">
                                    <span class="material-symbols-outlined text-base">delete</span>
                                </button>
                                <div class="flex shrink-0 items-center gap-2 rounded-full bg-white dark:bg-slate-900 px-2 py-1.5 border border-slate-200">
                                    <button class="qty-minus w-8 h-8 flex items-center justify-center text-primary bg-slate-100 rounded-full" data-id="${item.product.id}" type="button" aria-label="Kurangi jumlah">
                                        <span class="material-symbols-outlined text-base">remove</span>
                                    </button>
                                    <input class="qty-input h-8 w-16 rounded-full border border-slate-200 bg-slate-50 px-2 text-center text-sm font-bold text-slate-900 focus:border-primary focus:bg-white focus:ring-primary" data-id="${item.product.id}" type="number" min="0.01" step="1" inputmode="decimal" value="${formatNumber(item.qty)}" aria-label="Jumlah ${escapeHtml(item.product.name)}" />
                                    <button class="qty-plus w-8 h-8 flex items-center justify-center text-white bg-primary rounded-full" data-id="${item.product.id}" type="button" aria-label="Tambah jumlah">
                                        <span class="material-symbols-outlined text-base">add</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="text-[11px] text-slate-500">Total baris</span>
                            <div class="text-right">
                                ${line.itemDiscountTotal > 0 ? `<p class="text-[10px] font-semibold text-emerald-600">- ${formatCurrency(line.itemDiscountTotal)}</p>` : ''}
                                <span class="text-sm font-bold">${formatCurrency(line.lineNetSubtotal)}</span>
                            </div>
                        </div>
                        <div class="mt-3 space-y-1">
                            <label class="block space-y-1">
                                <span class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Catatan item</span>
                                <textarea class="item-note-input w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-900 focus:border-primary focus:ring-primary" data-id="${item.product.id}" rows="2" maxlength="500" placeholder="Misal: gula sedikit">${escapeHtml(item.note || '')}</textarea>
                            </label>
                        </div>
                        <div class="${isDiscountEditorOpen ? 'mt-3' : 'hidden'}" data-item-discount-editor="${item.product.id}">
                            <div class="flex items-center gap-2 rounded-2xl border border-emerald-100 bg-white p-2">
                                <select class="item-discount-type w-16 rounded-xl border-slate-200 bg-white px-2 py-2 text-xs" data-id="${item.product.id}">
                                    <option value="fixed" ${(itemDiscount?.type || 'fixed') === 'fixed' ? 'selected' : ''}>Rp</option>
                                    <option value="percent" ${(itemDiscount?.type || 'fixed') === 'percent' ? 'selected' : ''}>%</option>
                                </select>
                                <input class="item-discount-value flex-1 rounded-xl border-slate-200 bg-white px-3 py-2 text-xs" data-id="${item.product.id}" inputmode="decimal" placeholder="Masukkan diskon item" value="${itemDiscount?.value || ''}" />
                                <button class="item-discount-clear inline-flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500" data-id="${item.product.id}" type="button" aria-label="Hapus diskon item">
                                    <span class="material-symbols-outlined text-base">close</span>
                                </button>
                            </div>
                        </div>
                    `;
                    el.cartList.appendChild(row);
                });
            }
            updateTotals(pricing);
            saveCartDraft();
        };

        const buildSavedOrderCard = (order, options = {}) => {
            const holderName = escapeHtml(order.holder_name || 'Tanpa nama');
            const noteText = escapeHtml(order.note || '');
            const itemNotes = Array.isArray(order.items)
                ? order.items.map((item) => String(item.note || '').trim()).filter(Boolean)
                : [];
            const itemNotePreview = itemNotes.length
                ? escapeHtml(itemNotes.slice(0, 2).join(' • ') + (itemNotes.length > 2 ? ` • +${itemNotes.length - 2} lagi` : ''))
                : '';
            const compact = options.compact === true;
            const deleteButton = compact ? '' : `
                <button class="saved-order-delete inline-flex items-center gap-1 rounded-lg bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-600" data-id="${order.id}" type="button">
                    <span class="material-symbols-outlined text-sm">delete</span>
                    Hapus
                </button>
            `;

            return `
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-bold text-slate-900">${holderName}</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">${order.item_count} item • ${formatCurrency(order.total)}</p>
                        ${order.note ? `<p class="mt-1 line-clamp-2 text-[11px] text-slate-500">${noteText}</p>` : ''}
                        ${itemNotePreview ? `<p class="mt-1 line-clamp-2 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-medium text-amber-800">Catatan item: ${itemNotePreview}</p>` : ''}
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <button class="saved-order-load inline-flex items-center gap-1 rounded-lg bg-primary/10 px-2 py-1 text-[10px] font-bold text-primary" data-id="${order.id}" type="button">
                            <span class="material-symbols-outlined text-sm">restart_alt</span>
                            Muat
                        </button>
                        ${deleteButton}
                    </div>
                </div>
            `;
        };

        const renderSavedOrders = () => {
            if (el.savedOrderCountLabel) {
                el.savedOrderCountLabel.textContent = String(state.savedOrders.length);
            }
            if (el.savedOrderSheetCount) {
                el.savedOrderSheetCount.textContent = String(state.savedOrders.length);
            }
            if (!el.savedOrderList || !el.savedOrderEmpty) {
                return;
            }

            el.savedOrderList.innerHTML = '';
            el.savedOrderEmpty.classList.toggle('hidden', state.savedOrders.length > 0);

            state.savedOrders.forEach((order) => {
                const row = document.createElement('div');
                row.className = 'rounded-2xl border border-slate-200 bg-white px-3 py-3';
                row.innerHTML = buildSavedOrderCard(order);
                el.savedOrderList.appendChild(row);
            });

        };

        const clearCurrentOrder = () => {
            state.cart.clear();
            state.fees = [];
            state.selectedPromos.clear();
            state.transactionDiscounts = [];
            state.activeSavedOrderId = null;
            state.replacingSaleId = null;
            state.replacingTransactionDate = null;
            updateSaveOrderModeUi();
            syncEditingSaleBanner();
            if (el.orderHolderName) {
                el.orderHolderName.value = '';
            }
            if (el.orderNote) {
                el.orderNote.value = '';
            }
            sessionStorage.removeItem(cartStorageKey);
            state.activeItemDiscountEditor = null;
            renderFeeList();
            renderTransactionDiscountList();
            renderPromoList();
            renderCart();
            renderProducts();
        };

        const saveCurrentOrder = () => {
            if (state.shiftBlocked) {
                alert('Shift belum aktif. Silakan buka shift terlebih dahulu.');
                return;
            }

            const holderName = String(el.orderHolderName?.value || '').trim();
            if (!holderName) {
                alert('Isi Atas Nama sebelum menyimpan pesanan.');
                el.orderHolderName?.focus();
                return;
            }

            const items = [...state.cart.values()].map((item) => ({
                product_id: item.product.id,
                qty: item.qty,
                discounts: Array.isArray(item.discounts) ? item.discounts : [],
                note: String(item.note || '').trim(),
            }));

            if (!items.length) {
                alert('Keranjang masih kosong. Tambahkan produk terlebih dahulu.');
                return;
            }

            const pricing = computeCartPricing();
            const orderPayload = {
                branch_id: state.branchId,
                location_id: state.locationId,
                shift_id: state.shiftId,
                register_id: state.registerId,
                customer_id: el.customer ? (el.customer.value || null) : null,
                holder_name: holderName,
                note: String(el.orderNote?.value || '').trim(),
                items,
                fees: state.fees,
                selected_promos: [...state.selectedPromos],
                transaction_discounts: state.transactionDiscounts,
                transaction_date: getSelectedTransactionDate(),
                total: Number(pricing.total || 0),
                item_count: Number(pricing.itemCount || 0),
            };

            const savedOrderId = state.activeSavedOrderId;

            const fallbackOrder = {
                id: String(savedOrderId || createLocalId()),
                holder_name: orderPayload.holder_name,
                note: orderPayload.note,
                items: orderPayload.items,
                fees: orderPayload.fees,
                selected_promos: orderPayload.selected_promos,
                transaction_discounts: orderPayload.transaction_discounts,
                transaction_date: orderPayload.transaction_date,
                customer_id: orderPayload.customer_id,
                total: orderPayload.total,
                item_count: orderPayload.item_count,
                created_at: new Date().toISOString(),
            };

            window.PosUi.runWithSubmitGuard(el.saveOrder, async () => {
                try {
                    const savedOrder = await saveOrderToApi(orderPayload, savedOrderId);
                    upsertSavedOrder({
                        ...fallbackOrder,
                        ...(savedOrder || {}),
                        transaction_date: savedOrder?.transaction_date || fallbackOrder.transaction_date,
                    });
                    clearCurrentOrder();
                    alert('Pesanan berhasil disimpan.');
                } catch (_) {
                    upsertSavedOrder(fallbackOrder);
                    clearCurrentOrder();
                    alert('Pesanan disimpan lokal karena server belum merespons.');
                }
            });
        };

        const applySavedOrder = async (savedOrder) => {
            if (!savedOrder || !Array.isArray(savedOrder.items) || !savedOrder.items.length) {
                return;
            }

            const missingProductIds = [...new Set(savedOrder.items.map((item) => item.product_id))]
                .filter((productId) => !state.products.some((product) => product.id === productId));

            if (missingProductIds.length) {
                await loadProducts({ ids: missingProductIds });
            }

            const productMap = new Map(state.products.map((product) => [product.id, product]));

            state.cart.clear();
            savedOrder.items.forEach((item) => {
                const product = productMap.get(item.product_id);
                if (!product) {
                    return;
                }
                const qty = Math.max(1, Number(item.qty || 1));
                state.cart.set(product.id, {
                    product,
                    qty,
                    unit_price: resolveUnitPrice(product, qty),
                    discounts: Array.isArray(item.discounts) ? item.discounts : [],
                    note: String(item.note || '').trim(),
                });
            });
            state.activeSavedOrderId = savedOrder?.id ? String(savedOrder.id) : null;
            updateSaveOrderModeUi();

            state.fees = Array.isArray(savedOrder.fees) ? savedOrder.fees.map((fee) => ({
                id: fee.id || createLocalId(),
                name: fee.name || '',
                type: fee.type === 'percent' ? 'percent' : 'amount',
                value: Number(fee.value || 0),
                product_id: fee.product_id || null,
            })) : [];
            state.selectedPromos = new Set(Array.isArray(savedOrder.selected_promos) ? savedOrder.selected_promos : []);
            state.transactionDiscounts = Array.isArray(savedOrder.transaction_discounts)
                ? savedOrder.transaction_discounts.map((discount) => ({
                    id: discount.id || createLocalId(),
                    type: discount.type === 'percent' ? 'percent' : 'fixed',
                    value: Number(discount.value || 0),
                }))
                : [];

            if (el.customer) {
                el.customer.value = savedOrder.customer_id || '';
                renderCustomers();
            }
            if (el.orderHolderName) {
                el.orderHolderName.value = savedOrder.holder_name || '';
            }
            if (el.orderNote) {
                el.orderNote.value = savedOrder.note || '';
            }
            if (el.transactionDate) {
                el.transactionDate.value = normalizeTransactionDate(savedOrder.transaction_date || el.transactionDate.value);
            }

            state.activeItemDiscountEditor = null;
            renderFeeList();
            renderTransactionDiscountList();
            renderPromoList();
            renderCart();
            renderProducts();
            closeSavedOrderSheet();
        };

        const openCart = () => {
            if (state.shiftBlocked) {
                alert('Shift belum aktif. Silakan buka shift terlebih dahulu.');
                return;
            }

            el.cartOverlay.classList.remove('hidden');
            el.bottomNav?.classList.add('hidden');
        };

        const closeCart = () => {
            el.cartOverlay.classList.add('hidden');
            if (el.bottomNav && state.cart.size > 0) {
                el.bottomNav.classList.remove('hidden');
            }
        };

        const setSellingEnabled = (enabled) => {
            const disabled = !enabled;
            state.shiftBlocked = disabled;

            el.shiftGuard.classList.toggle('hidden', enabled);
            el.search.disabled = disabled;
            if (el.customer) {
                el.customer.disabled = disabled || !canUseCustomerFeature;
            }
            el.categoryFilters.classList.toggle('pointer-events-none', disabled);
            el.productGrid.classList.toggle('pointer-events-none', disabled);
            el.categoryFilters.classList.toggle('opacity-60', disabled);
            el.productGrid.classList.toggle('opacity-60', disabled);

            if (disabled) {
                closeCart();
                state.cart.clear();
                state.fees = [];
                state.selectedPromos.clear();
                state.transactionDiscounts = [];
                sessionStorage.removeItem(cartStorageKey);
                renderFeeList();
                renderTransactionDiscountList();
                renderPromoList();
                updateTotals();
            }
        };

        const renderCustomers = () => {
            if (!el.customer) {
                return;
            }
            const selectedValue = el.customer.value || '';
            el.customer.innerHTML = '<option value="">Pelanggan Umum</option>';
            state.customers.forEach((customer) => {
                const option = document.createElement('option');
                option.value = customer.id;
                option.textContent = customer.name;
                el.customer.appendChild(option);
            });
            el.customer.value = state.customers.some((customer) => customer.id === selectedValue)
                ? selectedValue
                : '';

            const currentCustomer = state.customers.find((customer) => String(customer.id) === String(el.customer.value || ''));
            if (el.customerCurrentLabel) {
                el.customerCurrentLabel.textContent = currentCustomer?.name || 'Pelanggan Umum';
            }
        };

        const loadBootstrap = async () => {
            const response = await fetch('/api/v1/bootstrap', { headers });
            if (!response.ok) {
                const mapped = await window.PosUi.mapApiError(response, 'Gagal memuat data kasir.');
                throw new Error(mapped.message);
            }
            const payload = await response.json().catch(() => ({}));
            return window.PosUi.unwrapApiPayload(payload) || {};
        };

        const mergeProducts = (products, append = false) => {
            const merged = append ? [...state.products] : [];
            const indexById = new Map(merged.map((product, index) => [product.id, index]));

            (products || []).forEach((product) => {
                if (indexById.has(product.id)) {
                    merged[indexById.get(product.id)] = product;
                    return;
                }
                indexById.set(product.id, merged.length);
                merged.push(product);
            });

            state.products = merged;
        };

        const loadCustomers = async () => {
            if (!el.customer || !canUseCustomerFeature) {
                if (el.customerFeatureNotice) {
                    el.customerFeatureNotice.textContent = el.customerFeatureNotice.textContent || 'Fitur pelanggan POS belum aktif untuk paket ini.';
                }
                return;
            }

            const response = await fetch('/api/v1/pos/customers?lite=1&limit=100', { headers });
            if (!response.ok) {
                return;
            }
            const payload = await response.json().catch(() => ({}));
            state.customers = payload.customers || [];
            renderCustomers();
        };

        const setCustomerModalFeedback = (message = '', tone = 'neutral') => {
            if (!el.customerModalFeedback) {
                return;
            }

            if (!message) {
                el.customerModalFeedback.className = 'hidden rounded-xl border px-3 py-2 text-xs font-medium';
                el.customerModalFeedback.textContent = '';
                return;
            }

            const toneClass = tone === 'error'
                ? 'border-rose-200 bg-rose-50 text-rose-700'
                : 'border-emerald-200 bg-emerald-50 text-emerald-700';

            el.customerModalFeedback.className = `rounded-xl border px-3 py-2 text-xs font-medium ${toneClass}`;
            el.customerModalFeedback.textContent = message;
        };

        const resetCustomerModal = () => {
            if (el.customerNameInput) {
                el.customerNameInput.value = '';
            }
            if (el.customerPhoneInput) {
                el.customerPhoneInput.value = '';
            }
            if (el.customerAddressInput) {
                el.customerAddressInput.value = '';
            }
            setCustomerModalFeedback('');
        };

        const closeCustomerModal = () => {
            if (!el.customerModal) {
                return;
            }
            el.customerModal.classList.add('hidden');
            resetCustomerModal();
        };

        const openCustomerSheet = () => {
            if (!el.customerSheet) {
                return;
            }
            el.customerSheet.classList.remove('hidden');
        };

        const closeCustomerSheet = () => {
            el.customerSheet?.classList.add('hidden');
        };

        const openCustomerModal = () => {
            if (!el.customerModal || !canUseCustomerFeature) {
                return;
            }
            el.customerModal.classList.remove('hidden');
            closeCustomerSheet();
            setCustomerModalFeedback('');
            setTimeout(() => {
                el.customerNameInput?.focus();
            }, 50);
        };

        const openSavedOrderSheet = () => {
            el.savedOrderSheet?.classList.remove('hidden');
        };

        const closeSavedOrderSheet = () => {
            el.savedOrderSheet?.classList.add('hidden');
        };

        const createCustomer = async () => {
            const name = String(el.customerNameInput?.value || '').trim();
            const phone = String(el.customerPhoneInput?.value || '').trim();
            const address = String(el.customerAddressInput?.value || '').trim();

            if (!name) {
                setCustomerModalFeedback('Nama pelanggan wajib diisi.', 'error');
                el.customerNameInput?.focus();
                return;
            }

            const response = await fetch('/api/v1/pos/customers', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    ...headers,
                },
                body: JSON.stringify({
                    name,
                    phone,
                    address,
                }),
            });

            if (!response.ok) {
                const mapped = await window.PosUi.mapApiError(response, 'Gagal menyimpan pelanggan.');
                throw new Error(mapped.message);
            }

            const payload = await response.json().catch(() => ({}));
            return payload.customer || null;
        };

        const loadProducts = async ({ page = 1, append = false, ids = [] } = {}) => {
            state.productsLoading = true;
            renderProducts();

            const requestSeq = ++state.productRequestSeq;
            const params = new URLSearchParams();
            params.set('lite', '1');
            params.set('per_page', String(state.productPagination.perPage || 24));

            if (state.locationId) {
                params.set('location_id', state.locationId);
            }
            if (state.category && state.category !== 'all') {
                params.set('category_id', state.category);
            }
            if (state.term.trim()) {
                params.set('q', state.term.trim());
            }
            if (Array.isArray(ids) && ids.length) {
                params.set('ids', ids.join(','));
            } else {
                params.set('page', String(page));
            }

            try {
                const response = await fetch(`/api/v1/products?${params.toString()}`, { headers });
                if (!response.ok) {
                    const mapped = await window.PosUi.mapApiError(response, 'Gagal memuat katalog produk.');
                    throw new Error(mapped.message);
                }
                const payload = await response.json().catch(() => ({}));
                if (requestSeq !== state.productRequestSeq) {
                    return;
                }

                mergeProducts(payload.products || [], append && !ids.length);

                const pagination = payload.pagination || {};
                state.productPagination = {
                    currentPage: Number(pagination.current_page || (ids.length ? 1 : page)),
                    perPage: Number(pagination.per_page || state.productPagination.perPage || 24),
                    hasMore: Boolean(pagination.has_more),
                    total: Number(pagination.total || state.products.length),
                };

                renderProducts();
            } catch (error) {
                el.productGrid.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
            } finally {
                if (requestSeq === state.productRequestSeq) {
                    state.productsLoading = false;
                    renderProducts();
                }
            }
        };

        const refreshContext = async () => {
            try {
                const data = await loadBootstrap();
                setupContext(data);
                await Promise.all([
                    loadCustomers(),
                    loadProducts({ page: 1, append: false }),
                    (async () => {
                        try {
                            state.savedOrders = await loadSavedOrdersFromApi();
                            persistSavedOrders();
                        } catch (_) {
                            // keep latest local snapshot when API is unavailable
                        }
                    })(),
                ]);
                renderSavedOrders();
            } catch (_) {
                // keep current state if refresh fails
            }
        };

        const loadTaxSettings = async () => {
            const response = await fetch('/api/v1/settings/tax-promos', { headers });
            if (!response.ok) {
                return { taxes: [], promos: [] };
            }
            return response.json().catch(() => ({ taxes: [], promos: [] }));
        };

        const applyTaxSettings = (taxes) => {
            const active = Array.isArray(taxes) ? taxes.find((tax) => tax.is_active) : null;
            const rateValue = Number(active?.rate || 0);
            state.taxRate = Number.isFinite(rateValue) ? rateValue / 100 : 0;
            el.taxLabel.textContent = `Pajak (${formatRate(rateValue)}%)`;
            updateTotals();
        };

        const applyRoundingSettings = (settings) => {
            const mode = ['none', 'nearest', 'up', 'down', 'multiple'].includes(settings?.mode)
                ? settings.mode
                : 'none';
            const increment = Math.max(0, Math.floor(Number(settings?.increment || 0)));
            state.roundingSettings = { mode, increment };
            updateTotals();
        };

        const applyPromoSettings = (promos) => {
            state.promos = Array.isArray(promos) ? promos : [];
            renderPromoList();
            updateTotals();
        };

        const renderPromoList = () => {
            el.discountList.innerHTML = '';
            if (!state.promos.length) {
                el.discountEmpty.classList.remove('hidden');
                return;
            }
            el.discountEmpty.classList.add('hidden');

            state.promos.forEach((promo) => {
                if (promo.status && promo.status !== 'active') {
                    return;
                }
                const row = document.createElement('label');
                row.className = 'flex items-center justify-between gap-3 text-sm text-slate-700';
                row.innerHTML = `
                    <div>
                        <p class="font-semibold">${promo.name}</p>
                        <p class="text-xs text-slate-500">${promo.type === 'percent' ? `${promo.value}%` : formatCurrency(promo.value)}${promo.min_purchase ? ` • min ${formatCurrency(promo.min_purchase)}` : ''}</p>
                    </div>
                    <input type="checkbox" class="rounded border-slate-300 text-primary" data-promo-id="${promo.id}" ${state.selectedPromos.has(promo.id) ? 'checked' : ''} />
                `;
                el.discountList.appendChild(row);
            });
            updateTotals();
        };

        const createFeeRow = (fee) => {
            const row = document.createElement('div');
            row.className = 'flex flex-wrap items-center gap-2';
            row.innerHTML = `
                <input class="min-w-[140px] flex-1 rounded-lg border-slate-200 bg-white px-3 py-2 text-xs" placeholder="Contoh: Plastik" value="${fee.name || ''}" />
                <select class="w-14 rounded-lg border-slate-200 bg-white px-2 py-2 text-xs">
                    <option value="amount" ${fee.type === 'amount' ? 'selected' : ''}>Rp</option>
                    <option value="percent" ${fee.type === 'percent' ? 'selected' : ''}>%</option>
                </select>
                <input class="w-20 rounded-lg border-slate-200 bg-white px-2 py-2 text-xs text-right" placeholder="0" value="${fee.value || ''}" />
                <button class="text-red-500" type="button" aria-label="Hapus biaya">
                    <span class="material-symbols-outlined text-lg">delete</span>
                </button>
            `;

            const [nameInput, typeSelect, valueInput, removeBtn] = row.querySelectorAll('input, select, button');

            const syncFee = () => {
                fee.name = nameInput.value.trim();
                fee.type = typeSelect.value === 'percent' ? 'percent' : 'amount';
                fee.value = Number(valueInput.value || 0);
                updateTotals();
                saveCartDraft();
            };

            const ensureFeeProduct = async () => {
                if (!fee.name) {
                    fee.product_id = null;
                    return;
                }
                try {
                    const response = await fetch('/api/v1/fee-products', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            ...headers,
                        },
                        body: JSON.stringify({ name: fee.name }),
                    });
                    if (!response.ok) {
                        return;
                    }
                    const payload = await response.json();
                    fee.product_id = payload.product?.id || null;
                } catch (_) {
                    fee.product_id = null;
                }
            };

            nameInput.addEventListener('change', async () => {
                syncFee();
                await ensureFeeProduct();
                saveCartDraft();
            });
            typeSelect.addEventListener('change', syncFee);
            valueInput.addEventListener('input', syncFee);
            removeBtn.addEventListener('click', () => {
                state.fees = state.fees.filter((item) => item.id !== fee.id);
                renderFeeList();
                updateTotals();
                saveCartDraft();
            });

            return row;
        };

        const renderFeeList = () => {
            el.feeList.innerHTML = '';
            state.fees.forEach((fee) => {
                el.feeList.appendChild(createFeeRow(fee));
            });
            updateTotals();
        };

        const createTransactionDiscountRow = (discount) => {
            const row = document.createElement('div');
            row.className = 'flex items-center gap-2';
            row.innerHTML = `
                <select class="transaction-discount-type w-16 rounded-lg border-slate-200 bg-white px-2 py-2 text-xs">
                    <option value="fixed" ${discount.type === 'fixed' ? 'selected' : ''}>Rp</option>
                    <option value="percent" ${discount.type === 'percent' ? 'selected' : ''}>%</option>
                </select>
                <input class="transaction-discount-value flex-1 rounded-lg border-slate-200 bg-white px-3 py-2 text-xs" inputmode="decimal" placeholder="Diskon transaksi" value="${discount.value || ''}" />
                <button class="transaction-discount-remove inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-500" type="button" aria-label="Hapus diskon transaksi">
                    <span class="material-symbols-outlined text-base">delete</span>
                </button>
            `;

            const [typeSelect, valueInput, removeButton] = row.querySelectorAll('select, input, button');

            const sync = () => {
                discount.type = typeSelect.value === 'percent' ? 'percent' : 'fixed';
                discount.value = Number(valueInput.value || 0);
                updateTotals();
                saveCartDraft();
            };

            typeSelect.addEventListener('change', sync);
            valueInput.addEventListener('input', sync);
            removeButton.addEventListener('click', () => {
                state.transactionDiscounts = state.transactionDiscounts.filter((item) => item.id !== discount.id);
                renderTransactionDiscountList();
                updateTotals();
                saveCartDraft();
            });

            return row;
        };

        const renderTransactionDiscountList = () => {
            el.transactionDiscountList.innerHTML = '';
            state.transactionDiscounts.forEach((discount) => {
                el.transactionDiscountList.appendChild(createTransactionDiscountRow(discount));
            });
            updateTotals();
        };

        const setupContext = (data) => {
            state.categories = data.categories || [];
            state.registers = data.registers || [];
            state.accountMapping = data.account_mapping || null;
            state.pendingShiftReconciliation = data.pending_shift_reconciliation || null;
            if (data.catalog?.default_product_page_size) {
                state.productPagination.perPage = Number(data.catalog.default_product_page_size) || 24;
            }

            state.branchId = data.branches?.[0]?.id || null;
            const branchLocations = (data.locations || []).filter((loc) => loc.branch_id === state.branchId);
            state.locationId = branchLocations.find((loc) => loc.is_default)?.id || branchLocations[0]?.id || null;
            const branchRegisters = (state.registers || []).filter((register) => register.branch_id === state.branchId);
            state.registerId = branchRegisters.find((register) => register.location_id === state.locationId)?.id
                || branchRegisters[0]?.id
                || null;

            const shifts = [...(data.shifts || [])].sort((left, right) => {
                const leftTime = left?.opened_at ? Date.parse(left.opened_at) : 0;
                const rightTime = right?.opened_at ? Date.parse(right.opened_at) : 0;

                if (rightTime !== leftTime) {
                    return rightTime - leftTime;
                }

                return String(right?.id || '').localeCompare(String(left?.id || ''));
            });
            const currentUserId = data.user?.id || null;
            let activeShift = null;

            if (currentUserId) {
                activeShift = shifts.find((shift) => shift.user_id === currentUserId && shift.branch_id === state.branchId)
                    || shifts.find((shift) => shift.user_id === currentUserId)
                    || null;
            } else {
                const storedShiftId = sessionStorage.getItem('pos_active_shift_id');
                if (storedShiftId) {
                    activeShift = shifts.find((shift) => shift.id === storedShiftId) || null;
                }
            }

            state.shiftId = activeShift?.id || null;

            if (state.pendingShiftReconciliation) {
                el.shiftIndicator.classList.remove('bg-success');
                el.shiftIndicator.classList.add('bg-slate-300');
                setText(el.shiftStatus, 'Perlu Rekonsiliasi');
                if (el.shiftOpened) {
                    el.shiftOpened.textContent = 'Kas shift kemarin belum disimpan.';
                }
                setSellingEnabled(false);
                setText(el.summaryCaption, state.pendingShiftReconciliation.message || 'Selesaikan kas akhir shift kemarin terlebih dahulu.');
                if (window.location.pathname === '/pos/checkout') {
                    window.location.replace('/pos/shifts/open?from=%2Fpos%2Fcheckout');
                    return;
                }
            } else if (!state.shiftId) {
                el.shiftIndicator.classList.remove('bg-success');
                el.shiftIndicator.classList.add('bg-slate-300');
                setText(el.shiftStatus, 'Shift Tutup');
                if (el.shiftOpened) {
                    el.shiftOpened.textContent = 'Mulai: -';
                }
                setSellingEnabled(false);
                if (el.quickClose) {
                    el.quickClose.classList.add('hidden');
                }
                sessionStorage.removeItem('pos_active_shift_id');
            } else {
                el.shiftIndicator.classList.remove('bg-slate-300');
                el.shiftIndicator.classList.add('bg-success');
                setText(el.shiftStatus, 'Shift Aktif');
                const openedAt = activeShift?.opened_at ? new Date(activeShift.opened_at) : null;
                const tz = data.tenant?.timezone || 'UTC';
                if (el.shiftOpened) {
                    el.shiftOpened.textContent = openedAt
                        ? `Mulai: ${openedAt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: tz })}`
                        : 'Mulai: -';
                }
                setSellingEnabled(true);
                if (el.quickClose) {
                    el.quickClose.classList.remove('hidden');
                }
                sessionStorage.setItem('pos_active_shift_id', state.shiftId);
            }

            if (data.user?.name) {
                el.cashierName.textContent = data.user.name;
            } else if (activeShift?.cashier_name) {
                el.cashierName.textContent = activeShift.cashier_name;
            }

            renderCategories();
            renderProducts();
            renderCart();
        };

        const restoreCartDraft = async () => {
            const draft = loadCartDraft();
            if (!draft || !Array.isArray(draft.items) || !draft.items.length) {
                state.pendingDraftRestore = false;
                return;
            }

            const missingProductIds = [...new Set(draft.items.map((item) => item.product_id))]
                .filter((productId) => !state.products.some((product) => product.id === productId));

            if (missingProductIds.length) {
                await loadProducts({ ids: missingProductIds });
            }

            const productMap = new Map(state.products.map((product) => [product.id, product]));
            draft.items.forEach((item) => {
                const product = productMap.get(item.product_id);
                if (!product) {
                    return;
                }
                const qty = Math.max(1, Number(item.qty || 1));
                state.cart.set(product.id, {
                    product,
                    qty,
                    unit_price: resolveUnitPrice(product, qty),
                    discounts: Array.isArray(item.discounts) ? item.discounts : [],
                    note: String(item.note || '').trim(),
                });
            });

            if (draft.customer_id && el.customer) {
                el.customer.value = draft.customer_id;
            }
            if (Array.isArray(draft.fees)) {
                state.fees = draft.fees.map((fee) => ({
                    id: fee.id || crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`,
                    name: fee.name || '',
                    type: fee.type === 'percent' ? 'percent' : 'amount',
                    value: Number(fee.value || 0),
                    product_id: fee.product_id || null,
                }));
            }
            if (Array.isArray(draft.selected_promos)) {
                state.selectedPromos = new Set(draft.selected_promos);
            }
            if (Array.isArray(draft.transaction_discounts)) {
                state.transactionDiscounts = draft.transaction_discounts.map((discount) => ({
                    id: discount.id || crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`,
                    type: discount.type === 'percent' ? 'percent' : 'fixed',
                    value: Number(discount.value || 0),
                }));
            }
            state.replacingSaleId = draft.replace_sale_id ? String(draft.replace_sale_id) : null;
            state.replacingTransactionDate = draft.transaction_date ? String(draft.transaction_date) : null;
            if (el.transactionDate) {
                el.transactionDate.value = normalizeTransactionDate(state.replacingTransactionDate || el.transactionDate.value);
            }
            syncEditingSaleBanner();
            if (el.orderHolderName) {
                el.orderHolderName.value = String(draft.order_holder_name || '');
            }
            if (el.orderNote) {
                el.orderNote.value = String(draft.order_note || '');
            }
            renderCustomers();
            renderTransactionDiscountList();
            renderCart();
            renderProducts();

            if (!state.shiftBlocked) {
                const returnToCart = sessionStorage.getItem('pos_return_to_cart') === '1';
                if (state.replacingSaleId || returnToCart) {
                    openCart();
                    setText(el.summaryCaption, 'Mode edit transaksi aktif. Data awal sudah dimuat.');
                }
                if (returnToCart) {
                    sessionStorage.removeItem('pos_return_to_cart');
                }
            }

            state.pendingDraftRestore = false;
        };

        const buildPaymentSnapshot = () => {
            const pricing = computeCartPricing();
            const items = pricing.lineItems.map((line) => ({
                id: line.item.product.id,
                name: line.item.product.name,
                qty: line.item.qty,
                price: line.item.unit_price,
                location_id: state.locationId,
                item_discount_total: line.itemDiscountTotal,
                note: String(line.item.note || '').trim(),
            }));
            const feeItems = state.fees
                .filter((fee) => fee.product_id && Number(fee.value || 0) > 0)
                .map((fee) => {
                    const amount = fee.type === 'percent'
                        ? pricing.productSubtotal * (Number(fee.value || 0) / 100)
                        : Number(fee.value || 0);
                    return {
                        product_id: fee.product_id,
                        location_id: state.locationId,
                        qty: 1,
                        price: amount,
                        discount: 0,
                        name: fee.name,
                        line_type: 'fee',
                    };
                });
            const checkoutItems = pricing.lineItems.map((line) => ({
                product_id: line.item.product.id,
                location_id: state.locationId,
                qty: line.item.qty,
                price: line.item.unit_price,
                line_type: 'product',
                discount: line.itemDiscountTotal,
                note: String(line.item.note || '').trim(),
                discounts: getItemDiscountLines(line.item),
            })).concat(feeItems.map((fee) => ({
                product_id: fee.product_id,
                location_id: fee.location_id,
                qty: 1,
                price: fee.price,
                line_type: 'fee',
                discount: 0,
            })));
            return {
                subtotal: pricing.subtotal,
                discount: pricing.transactionDiscountTotal,
                item_discount_total: pricing.itemDiscountTotal,
                transaction_discount_total: pricing.transactionDiscountTotal,
                total_discount: pricing.totalDiscount,
                total_before_rounding: pricing.totalBeforeRounding,
                rounding_adjustment: pricing.roundingAdjustment,
                rounding_settings: state.roundingSettings,
                saved_order_id: state.activeSavedOrderId || null,
                replace_sale_id: state.replacingSaleId || null,
                transaction_date: getSelectedTransactionDate(),
                transaction_discounts: pricing.transactionDiscounts.map((discount) => ({
                    type: discount.type,
                    value: discount.value,
                    label: discount.label,
                    source: discount.source,
                    reference_id: discount.reference_id,
                })),
                tax: pricing.tax,
                total: pricing.total,
                items: items.concat(feeItems.map((fee) => ({
                    id: fee.product_id,
                    name: fee.name,
                    qty: 1,
                    price: fee.price,
                    location_id: fee.location_id,
                }))),
                checkout_items: checkoutItems,
                fees: state.fees,
                selected_promos: [...state.selectedPromos],
                order_holder_name: el.orderHolderName ? String(el.orderHolderName.value || '').trim() : '',
                order_note: el.orderNote ? String(el.orderNote.value || '').trim() : '',
            };
        };

        const goToPayment = () => {
            if (state.shiftBlocked) {
                alert('Shift belum aktif. Silakan buka shift terlebih dahulu.');
                return;
            }

            const items = [...state.cart.values()];
            const checkoutBlockReason = getCheckoutBlockReason(items);
            if (checkoutBlockReason) {
                setText(el.summaryCaption, checkoutBlockReason);
                return;
            }

            const snapshot = buildPaymentSnapshot();
            snapshot.branch_id = state.branchId;
            snapshot.shift_id = state.shiftId;
            snapshot.location_id = state.locationId;
            snapshot.register_id = state.registerId;
            snapshot.customer_id = el.customer ? (el.customer.value || null) : null;
            snapshot.account_mapping = state.accountMapping || null;
            snapshot.idempotency_key = crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;
            snapshot.cashier = el.cashierName.textContent || null;
            snapshot.createdAt = new Date().toISOString();

            sessionStorage.setItem('pos_payment', JSON.stringify(snapshot));
            el.checkout.disabled = true;
            el.checkout.classList.add('opacity-60', 'cursor-not-allowed');
            el.checkout.innerHTML = '<span class="material-symbols-outlined animate-spin font-bold">progress_activity</span> Membuka Pembayaran...';
            if (el.checkoutCompact) {
                el.checkoutCompact.disabled = true;
                el.checkoutCompact.classList.add('opacity-60', 'cursor-not-allowed');
                el.checkoutCompact.innerHTML = 'Membuka... <span class="material-symbols-outlined text-lg">progress_activity</span>';
            }
            setText(el.summaryCaption, 'Membuka halaman pembayaran...');
            window.location.href = '/pos/payment-method';
        };

        let searchDebounceTimer = null;

        el.categoryFilters.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) {
                return;
            }
            state.category = button.dataset.category || 'all';
            applyCategoryStyles();
            loadProducts({ page: 1, append: false });
        });

        el.search.addEventListener('input', (event) => {
            state.term = event.target.value || '';
            window.clearTimeout(searchDebounceTimer);
            searchDebounceTimer = window.setTimeout(() => {
                loadProducts({ page: 1, append: false });
            }, 200);
        });

        el.transactionDate?.addEventListener('change', () => {
            el.transactionDate.value = normalizeTransactionDate(el.transactionDate.value);
            saveCartDraft();
        });

        el.cartList.addEventListener('click', (event) => {
            event.stopPropagation();

            const emptyClose = event.target.closest('#cart-empty-close');
            const remove = event.target.closest('.item-remove');
            const minus = event.target.closest('.qty-minus');
            const plus = event.target.closest('.qty-plus');
            const discountToggle = event.target.closest('.item-discount-toggle');
            const discountClear = event.target.closest('.item-discount-clear');
            if (emptyClose) {
                closeCart();
                el.search.focus();
                return;
            }
            if (remove) {
                state.cart.delete(remove.dataset.id);
                renderCart();
                renderProducts();
                return;
            }
            if (discountToggle) {
                const productId = discountToggle.dataset.id;
                state.activeItemDiscountEditor = state.activeItemDiscountEditor === productId ? null : productId;
                renderCart();
                if (state.activeItemDiscountEditor === productId) {
                    const input = el.cartList.querySelector(`.item-discount-value[data-id="${productId}"]`);
                    if (input instanceof HTMLInputElement) {
                        input.focus();
                    }
                }
                return;
            }
            if (discountClear) {
                const productId = discountClear.dataset.id;
                const existing = state.cart.get(productId);
                if (!existing) {
                    return;
                }
                state.cart.set(productId, {
                    ...existing,
                    discounts: [],
                });
                renderCart();
                return;
            }
            if (minus) {
                updateCartQty(minus.dataset.id, -1);
                openCart();
            }
            if (plus) {
                updateCartQty(plus.dataset.id, 1);
                openCart();
            }
        });

        el.cartList.addEventListener('focusin', (event) => {
            const qtyInput = event.target.closest('.qty-input');
            if (qtyInput instanceof HTMLInputElement) {
                qtyInput.select();
            }
        });

        el.cartList.addEventListener('keydown', (event) => {
            const qtyInput = event.target.closest('.qty-input');
            if (!(qtyInput instanceof HTMLInputElement)) {
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                qtyInput.blur();
            }
        });

        el.cartList.addEventListener('input', (event) => {
            const noteInput = event.target.closest('.item-note-input');
            if (noteInput) {
                const productId = noteInput.dataset.id;
                const existing = state.cart.get(productId);
                if (!existing) {
                    return;
                }
                state.cart.set(productId, {
                    ...existing,
                    note: String(noteInput.value || '').trim(),
                });
                return;
            }
            const qtyInput = event.target.closest('.qty-input');
            if (qtyInput) {
                window.clearTimeout(state.qtyInputTimer);
                state.qtyInputTimer = window.setTimeout(() => {
                    setCartQty(qtyInput.dataset.id, qtyInput.value);
                    openCart();
                }, 250);
                return;
            }
            const valueInput = event.target.closest('.item-discount-value');
            if (!valueInput) {
                return;
            }
            const productId = valueInput.dataset.id;
            const existing = state.cart.get(productId);
            if (!existing) {
                return;
            }
            const current = Array.isArray(existing.discounts) && existing.discounts[0]
                ? existing.discounts[0]
                : { type: 'fixed', value: 0, label: 'Diskon item', source: 'manual' };
            state.cart.set(productId, {
                ...existing,
                discounts: Number(valueInput.value || 0) > 0
                    ? [{ ...current, value: Number(valueInput.value || 0) }]
                    : [],
            });
            renderCart();
        });

        el.cartList.addEventListener('change', (event) => {
            const qtyInput = event.target.closest('.qty-input');
            if (qtyInput) {
                setCartQty(qtyInput.dataset.id, qtyInput.value);
                return;
            }

            const typeSelect = event.target.closest('.item-discount-type');
            if (!typeSelect) {
                return;
            }
            const productId = typeSelect.dataset.id;
            const existing = state.cart.get(productId);
            if (!existing) {
                return;
            }
            const current = Array.isArray(existing.discounts) && existing.discounts[0]
                ? existing.discounts[0]
                : { type: 'fixed', value: 0, label: 'Diskon item', source: 'manual' };
            state.cart.set(productId, {
                ...existing,
                discounts: [{ ...current, type: typeSelect.value === 'percent' ? 'percent' : 'fixed' }],
            });
            renderCart();
        });

        el.clearCart.addEventListener('click', () => {
            clearCurrentOrder();
        });

        el.cartToggle.addEventListener('click', openCart);
        el.cartClose.addEventListener('click', closeCart);
        el.cartOverlay.addEventListener('pointerdown', (event) => {
            state.cartPointerStartedInsideDrawer = event.composedPath().includes(el.cartDrawer);
        }, true);
        el.cartOverlay.addEventListener('touchstart', (event) => {
            state.cartPointerStartedInsideDrawer = event.composedPath().includes(el.cartDrawer);
        }, true);
        el.cartOverlay.addEventListener('click', (event) => {
            const clickedInsideDrawer = event.composedPath().includes(el.cartDrawer);
            if (!clickedInsideDrawer && !state.cartPointerStartedInsideDrawer) {
                closeCart();
            }
            state.cartPointerStartedInsideDrawer = false;
        });

        el.checkout.addEventListener('click', goToPayment);
        if (el.checkoutCompact) {
            el.checkoutCompact.addEventListener('click', goToPayment);
        }
        el.paymentLink?.addEventListener('click', (event) => {
            event.preventDefault();
            goToPayment();
        });
        el.saveOrder?.addEventListener('click', saveCurrentOrder);
        el.orderHolderName?.addEventListener('input', saveCartDraft);
        el.orderNote?.addEventListener('input', saveCartDraft);
        el.customerQuickTrigger?.addEventListener('click', openCustomerSheet);
        el.customerSheetClose?.addEventListener('click', closeCustomerSheet);
        el.customerSheetOverlay?.addEventListener('click', closeCustomerSheet);
        el.savedOrderQuickTrigger?.addEventListener('click', openSavedOrderSheet);
        el.savedOrderSheetClose?.addEventListener('click', closeSavedOrderSheet);
        el.savedOrderSheetOverlay?.addEventListener('click', closeSavedOrderSheet);
        el.receivableQuickTrigger?.addEventListener('click', () => {
            if (el.receivableSheet) {
                el.receivableSheet.classList.remove('hidden');
            }
        });
        el.receivableSheetClose?.addEventListener('click', () => {
            el.receivableSheet?.classList.add('hidden');
        });
        el.receivableSheetOverlay?.addEventListener('click', () => {
            el.receivableSheet?.classList.add('hidden');
        });
        el.customer?.addEventListener('change', () => {
            saveCartDraft();
            renderCustomers();
            closeCustomerSheet();
        });
        el.customerCreateTrigger?.addEventListener('click', openCustomerModal);
        el.customerModalClose?.addEventListener('click', closeCustomerModal);
        el.customerModalCancel?.addEventListener('click', closeCustomerModal);
        el.customerModal?.addEventListener('click', (event) => {
            if (event.target === el.customerModal) {
                closeCustomerModal();
            }
        });
        el.customerSaveButton?.addEventListener('click', async () => {
            if (!canUseCustomerFeature) {
                return;
            }

            await window.PosUi.runWithSubmitGuard(el.customerSaveButton, async () => {
                setCustomerModalFeedback('');
                const created = await createCustomer();
                if (!created) {
                    throw new Error('Pelanggan baru tidak ditemukan setelah disimpan.');
                }

                state.customers = [
                    created,
                    ...state.customers.filter((customer) => customer.id !== created.id),
                ].sort((left, right) => String(left.name || '').localeCompare(String(right.name || ''), 'id'));
                renderCustomers();
                if (el.customer) {
                    el.customer.value = created.id;
                }
                saveCartDraft();
                setCustomerModalFeedback('Pelanggan berhasil ditambahkan.', 'success');
                setTimeout(() => {
                    closeCustomerModal();
                }, 400);
            }, {
                loadingHtml: '<span class="material-symbols-outlined text-lg">progress_activity</span> Menyimpan...',
            }).catch((error) => {
                setCustomerModalFeedback(error.message || 'Gagal menyimpan pelanggan.', 'error');
            });
        });
        const handleSavedOrderAction = async (event) => {
            const loadButton = event.target.closest('.saved-order-load');
            const deleteButton = event.target.closest('.saved-order-delete');

            if (loadButton) {
                const orderId = loadButton.dataset.id;
                const savedOrder = state.savedOrders.find((entry) => String(entry.id) === String(orderId));
                if (!savedOrder) {
                    return;
                }
                await applySavedOrder(savedOrder);
                return;
            }

            if (deleteButton) {
                const orderId = deleteButton.dataset.id;
                try {
                    await deleteSavedOrderFromApi(orderId);
                } catch (error) {
                    alert(error?.message || 'Gagal menghapus pesanan tersimpan. Coba lagi.');
                    return;
                }
                state.savedOrders = state.savedOrders.filter((entry) => String(entry.id) !== String(orderId));
                if (String(state.activeSavedOrderId || '') === String(orderId)) {
                    state.activeSavedOrderId = null;
                    updateSaveOrderModeUi();
                }
                persistSavedOrders();
                renderSavedOrders();
            }
        };
        el.savedOrderList?.addEventListener('click', handleSavedOrderAction);
        el.productViewList?.addEventListener('click', () => setProductViewMode('list'));
        el.productViewGrid?.addEventListener('click', () => setProductViewMode('grid'));

        syncProductViewControls();
        syncProductGridLayout();
        state.savedOrders = loadSavedOrders();
        renderSavedOrders();

        (async () => {
            try {
                const [data, settings] = await Promise.all([
                    loadBootstrap(),
                    loadTaxSettings(),
                ]);
                const pendingDraft = loadCartDraft();
                state.pendingDraftRestore = Boolean(pendingDraft?.items?.length);
                setupContext(data);
                await Promise.all([
                    loadCustomers(),
                    loadProducts({ page: 1, append: false }),
                ]);
                try {
                    state.savedOrders = await loadSavedOrdersFromApi();
                    persistSavedOrders();
                    renderSavedOrders();
                } catch (_) {
                    // keep local saved orders as fallback
                }
                await restoreCartDraft();
                applyTaxSettings(settings.taxes || []);
                applyRoundingSettings(settings.rounding_settings || { mode: 'none', increment: 0 });
                applyPromoSettings(settings.promos || []);
                renderFeeList();
                renderTransactionDiscountList();
                if (!state.shiftBlocked) {
                    setTimeout(() => el.search.focus(), 50);
                }
            } catch (error) {
                el.productGrid.innerHTML = `<div class="text-sm text-red-500">${error.message}</div>`;
            }
        })();

        window.addEventListener('focus', refreshContext);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                refreshContext();
            }
        });

        el.discountToggle.addEventListener('click', () => {
            el.discountPanel.classList.toggle('hidden');
            if (!el.discountPanel.classList.contains('hidden')) {
                const firstCheckbox = el.discountList.querySelector('input[data-promo-id]');
                if (firstCheckbox instanceof HTMLInputElement) {
                    firstCheckbox.focus();
                }
            }
        });

        el.discountList.addEventListener('change', (event) => {
            const checkbox = event.target.closest('input[data-promo-id]');
            if (!checkbox) {
                return;
            }
            const promoId = checkbox.dataset.promoId;
            if (checkbox.checked) {
                state.selectedPromos.add(promoId);
            } else {
                state.selectedPromos.delete(promoId);
            }
            updateTotals();
            saveCartDraft();
        });

        el.transactionDiscountAdd.addEventListener('click', () => {
            state.transactionDiscounts.push({
                id: crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`,
                type: 'fixed',
                value: 0,
            });
            renderTransactionDiscountList();
            const latestInput = el.transactionDiscountList.lastElementChild?.querySelector('input');
            if (latestInput instanceof HTMLInputElement) {
                latestInput.focus();
            }
        });

        el.feeAdd.addEventListener('click', () => {
            const fee = {
                id: crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`,
                name: '',
                type: 'amount',
                value: 0,
                product_id: null,
            };
            state.fees.push(fee);
            renderFeeList();
            const latestRow = el.feeList.lastElementChild;
            const nameInput = latestRow?.querySelector('input');
            if (nameInput instanceof HTMLInputElement) {
                nameInput.focus();
            }
        });
    })();
}

