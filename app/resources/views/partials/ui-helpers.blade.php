<script>
    (() => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const memoryJsonCache = new Map();
        const inflightJsonRequests = new Map();
        const SESSION_CACHE_PREFIX = 'pos-ui-json-cache:v1:';

        const errorMap = {
            LOCK_PERIOD: 'Periode transaksi terkunci. Gunakan tanggal yang diizinkan atau hubungi admin.',
            OUT_OF_STOCK: 'Stok tidak mencukupi. Kurangi jumlah item atau pilih lokasi lain.',
            FIFO_LAYER_EMPTY: 'Layer FIFO tidak tersedia. Hubungi admin untuk rekonsiliasi valuation.',
            VALIDATION_ERROR: 'Data input tidak valid. Periksa kembali field yang wajib.',
            TENANT_MISMATCH: 'Sesi tenant tidak sesuai. Muat ulang halaman lalu login ulang jika perlu.',
            TENANT_NOT_RESOLVED: 'Tenant tidak ditemukan. Pastikan header tenant/sesi aktif.',
            VALUATION_REBUILD_IN_PROGRESS: 'Operasi sementara dikunci karena rebuild valuation sedang berjalan.',
            MANAGER_PIN_REQUIRED: 'PIN manager wajib diisi untuk aksi ini.',
            MANAGER_PIN_INVALID: 'PIN manager tidak valid. Periksa kembali PIN.',
            MANAGER_PIN_LOCKED: 'Terlalu banyak percobaan PIN. Coba lagi beberapa saat.',
            FORBIDDEN: 'Anda tidak memiliki akses untuk aksi ini.',
        };

        const parseJsonSafe = async (response) => {
            try {
                return await response.json();
            } catch (_) {
                return {};
            }
        };

        const unwrapApiPayload = (payload) => {
            if (!payload || typeof payload !== 'object') {
                return payload;
            }

            if (payload.data && typeof payload.data === 'object' && !Array.isArray(payload.data)) {
                return payload.data;
            }

            return payload;
        };

        const unwrapApiList = (payload, key) => {
            const root = unwrapApiPayload(payload);
            const candidate = key && root && typeof root === 'object' ? root[key] : root;

            if (Array.isArray(candidate)) {
                return candidate;
            }

            if (candidate && Array.isArray(candidate.data)) {
                return candidate.data;
            }

            return [];
        };

        const unwrapApiMeta = (payload) => {
            if (!payload || typeof payload !== 'object') {
                return {};
            }

            if (payload.meta && typeof payload.meta === 'object') {
                return payload.meta;
            }

            if (payload.data && payload.data.meta && typeof payload.data.meta === 'object') {
                return payload.data.meta;
            }

            return {};
        };

        const mapApiError = async (response, fallbackMessage = 'Permintaan gagal.') => {
            const payload = await parseJsonSafe(response);
            const code = payload?.code || null;
            const mapped = code && errorMap[code] ? errorMap[code] : null;

            return {
                code,
                payload,
                message: mapped || payload?.message || fallbackMessage,
            };
        };

        const confirmRiskAction = ({ title = 'Konfirmasi', message = 'Lanjutkan aksi ini?' } = {}) => {
            return window.confirm(`${title}\n\n${message}`);
        };

        const fetchWithTimeout = async (url, init = {}, timeoutMs = 15000) => {
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), Math.max(1000, timeoutMs));
            const method = String(init.method || 'GET').toUpperCase();
            const headers = {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                ...(init.headers || {}),
            };

            try {
                return await fetch(url, {
                    ...init,
                    credentials: init.credentials || 'same-origin',
                    cache: init.cache || ((method === 'GET' || method === 'HEAD') ? 'no-store' : undefined),
                    headers,
                    signal: controller.signal,
                });
            } finally {
                clearTimeout(timer);
            }
        };

        const resolveCacheKey = (keyParts) => {
            if (!Array.isArray(keyParts) || keyParts.length === 0) {
                return '';
            }

            return keyParts
                .map((part) => {
                    if (part === null || part === undefined) {
                        return '';
                    }

                    if (typeof part === 'string') {
                        return part;
                    }

                    try {
                        return JSON.stringify(part);
                    } catch (_) {
                        return String(part);
                    }
                })
                .join('::');
        };

        const readSessionCache = (cacheKey, ttlMs) => {
            if (!cacheKey || !(ttlMs > 0)) {
                return null;
            }

            const inMemory = memoryJsonCache.get(cacheKey);
            if (inMemory && (Date.now() - inMemory.timestamp) < ttlMs) {
                return inMemory.value;
            }

            try {
                const raw = window.sessionStorage.getItem(`${SESSION_CACHE_PREFIX}${cacheKey}`);
                if (!raw) {
                    return null;
                }

                const parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object') {
                    return null;
                }

                if ((Date.now() - Number(parsed.timestamp || 0)) >= ttlMs) {
                    window.sessionStorage.removeItem(`${SESSION_CACHE_PREFIX}${cacheKey}`);
                    return null;
                }

                memoryJsonCache.set(cacheKey, {
                    timestamp: Number(parsed.timestamp || Date.now()),
                    value: parsed.value,
                });

                return parsed.value ?? null;
            } catch (_) {
                return null;
            }
        };

        const writeSessionCache = (cacheKey, value) => {
            if (!cacheKey) {
                return;
            }

            const entry = {
                timestamp: Date.now(),
                value,
            };

            memoryJsonCache.set(cacheKey, entry);

            try {
                window.sessionStorage.setItem(`${SESSION_CACHE_PREFIX}${cacheKey}`, JSON.stringify(entry));
            } catch (_) {
                // Ignore storage quota/unavailable issues.
            }
        };

        const fetchJson = async (url, init = {}, options = {}) => {
            const {
                timeoutMs = 15000,
                ttlMs = 0,
                cacheKey = '',
                forceFresh = false,
                fallbackToCacheOnError = true,
                unwrap = false,
            } = options;

            const resolvedCacheKey = resolveCacheKey([cacheKey || url, init.headers || {}]);

            if (!forceFresh && ttlMs > 0) {
                const cached = readSessionCache(resolvedCacheKey, ttlMs);
                if (cached !== null) {
                    return cached;
                }
            }

            if (!forceFresh && inflightJsonRequests.has(resolvedCacheKey)) {
                return inflightJsonRequests.get(resolvedCacheKey);
            }

            const task = (async () => {
                try {
                    const response = await fetchWithTimeout(url, init, timeoutMs);
                    if (!response.ok) {
                        throw new Error('Permintaan gagal.');
                    }

                    const payload = await response.json().catch(() => ({}));
                    const data = unwrap ? unwrapApiPayload(payload) : payload;

                    if (ttlMs > 0) {
                        writeSessionCache(resolvedCacheKey, data);
                    }

                    return data;
                } catch (error) {
                    if (fallbackToCacheOnError && ttlMs > 0) {
                        const cached = readSessionCache(resolvedCacheKey, Number.MAX_SAFE_INTEGER);
                        if (cached !== null) {
                            return cached;
                        }
                    }

                    throw error;
                } finally {
                    inflightJsonRequests.delete(resolvedCacheKey);
                }
            })();

            inflightJsonRequests.set(resolvedCacheKey, task);

            return task;
        };

        const runWithSubmitGuard = async (button, task, options = {}) => {
            if (!button) {
                return task();
            }

            const {
                loadingHtml = '<span class="material-symbols-outlined animate-spin">progress_activity</span> MEMPROSES',
            } = options;

            if (button.disabled) {
                return;
            }

            const previousHtml = button.innerHTML;
            button.disabled = true;
            button.classList.add('opacity-60', 'cursor-not-allowed');
            button.innerHTML = loadingHtml;

            try {
                return await task();
            } finally {
                button.disabled = false;
                button.classList.remove('opacity-60', 'cursor-not-allowed');
                button.innerHTML = previousHtml;
            }
        };

        const bootstrapUrl = ({ includeInventory = false, include = [] } = {}) => {
            const includes = Array.isArray(include)
                ? include.filter((value) => Boolean(value && String(value).trim()))
                : [];

            if (includeInventory) {
                includes.push('inventory');
            }

            const uniqueIncludes = [...new Set(includes)];
            const params = new URLSearchParams();

            if (uniqueIncludes.length > 0) {
                params.set('include', uniqueIncludes.join(','));
            }

            const query = params.toString();

            return query ? `/api/v1/bootstrap?${query}` : '/api/v1/bootstrap';
        };

        const getBootstrap = async ({
            includeInventory = false,
            include = [],
            headers = {},
            timeoutMs = 15000,
            ttlMs = includeInventory ? 0 : 60000,
            forceFresh = false,
        } = {}) => {
            const url = bootstrapUrl({ includeInventory, include });

            return fetchJson(url, {
                headers,
                cache: 'no-store',
            }, {
                timeoutMs,
                ttlMs,
                forceFresh,
                unwrap: true,
                cacheKey: ['bootstrap', url],
                fallbackToCacheOnError: true,
            });
        };

        const getProductionBatchEligibilityPayload = (product = {}) => {
            if (product.production_batch_eligibility && typeof product.production_batch_eligibility === 'object') {
                return product.production_batch_eligibility;
            }

            return null;
        };

        const normalizeProductionBatchDraft = (product = {}) => {
            const bomItems = Array.isArray(product.bom_items)
                ? product.bom_items
                : (Array.isArray(product.bomItems) ? product.bomItems : []);

            return {
                is_active: product.is_active !== false && product.isActive !== false,
                item_type: String(product.item_type || product.itemType || '').trim(),
                product_mode: String(product.product_mode || product.productMode || '').trim(),
                production_mode: String(product.production_mode || product.productionMode || '').trim(),
                track_stock: product.track_stock !== false && product.trackStock !== false,
                recipe_output_qty: Number(product.recipe_output_qty ?? product.recipeOutputQty ?? 0),
                bom_items: bomItems,
            };
        };

        const evaluateProductionBatchEligibility = (product = {}) => {
            const state = normalizeProductionBatchDraft(product);
            const bomItems = state.bom_items;
            const checks = [];
            const reasons = [];
            const duplicateKeys = new Set();

            const pushCheck = (label, passed, reason) => {
                const check = { label, passed: Boolean(passed), reason: passed ? '' : reason };
                checks.push(check);

                if (!check.passed && reason) {
                    reasons.push(reason);
                }

                return check;
            };

            pushCheck('Produk aktif', state.is_active, 'Aktifkan produk terlebih dahulu.');
            pushCheck('Tipe produk jadi', state.item_type === 'finished_good', 'Ubah tipe produk menjadi finished_good.');
            pushCheck('Track stock aktif', state.track_stock, 'Aktifkan track stock.');
            pushCheck('Mode produk BOM', state.product_mode === 'bom', 'Set mode produk ke BOM saat memakai recipe.');
            pushCheck('Mode produksi batch', state.production_mode === 'batch_production', 'Set mode produksi ke batch_production.');
            pushCheck('Hasil standar per 1 resep', state.recipe_output_qty > 0, 'Isi hasil standar per 1 resep terlebih dahulu.');

            if (!bomItems.length) {
                pushCheck('Minimal 1 line BOM', false, 'Tambahkan minimal satu bahan baku.');
            } else {
                let firstIngredientIssue = '';

                bomItems.forEach((item, index) => {
                    const productId = String(item.product_id || item.productId || '').trim();
                    const lineQty = Number(item.qty ?? item.quantity ?? 0);
                    const productType = String(item.product_item_type || item.item_type || '').trim();
                    const variantRequired = Boolean(item.variant_required || item.variantRequired || item.product_variant_required || item.productVariantRequired);
                    const variantId = String(item.product_variant_id || item.productVariantId || '').trim();
                    const lineKey = `${productId}|${variantRequired ? variantId || '__missing_variant__' : '__no_variant__'}`;

                    if (duplicateKeys.has(lineKey) && !firstIngredientIssue) {
                        firstIngredientIssue = `Line BOM #${index + 1}: bahan baku duplikat. Gabungkan qty pada satu line.`;
                    }

                    duplicateKeys.add(lineKey);

                    if (!productId && !firstIngredientIssue) {
                        firstIngredientIssue = `Line BOM #${index + 1}: raw material wajib dipilih.`;
                    } else if (!(lineQty > 0) && !firstIngredientIssue) {
                        firstIngredientIssue = `Line BOM #${index + 1}: qty harus lebih dari 0.`;
                    } else if (productType !== 'raw_material' && !firstIngredientIssue) {
                        firstIngredientIssue = `Line BOM #${index + 1}: bahan baku harus bertipe raw_material.`;
                    } else if (variantRequired && !variantId && !firstIngredientIssue) {
                        firstIngredientIssue = `Line BOM #${index + 1}: variant raw material wajib dipilih.`;
                    }
                });

                pushCheck('Validitas bahan baku', firstIngredientIssue === '', firstIngredientIssue || 'Periksa kembali line BOM yang diisi.');
            }

            const eligible = checks.every((check) => check.passed);
            const failedReason = reasons[0] || 'Lengkapi checklist di bawah untuk membuat produk siap batch produksi.';

            return {
                eligible,
                reason_count: reasons.length,
                reasons,
                checks,
                summary: {
                    is_active: state.is_active,
                    item_type: state.item_type || 'finished_good',
                    product_mode: state.product_mode || 'default',
                    production_mode: state.production_mode || 'none',
                    track_stock: state.track_stock,
                    recipe_output_qty: state.recipe_output_qty,
                    bom_count: bomItems.length,
                    mode_label: `product_mode=${state.product_mode || 'default'} · production_mode=${state.production_mode || 'none'}`,
                    message: eligible ? 'Produk ini sudah memenuhi syarat batch produksi.' : failedReason,
                },
            };
        };

        const isProductionBatchEligibleProduct = (product = {}) => {
            const eligibility = getProductionBatchEligibilityPayload(product);
            if (eligibility && typeof eligibility.eligible === 'boolean') {
                return eligibility.eligible;
            }

            if (product.production_batch_eligible === true) {
                return true;
            }

            const productionMode = String(product.production_mode || '').trim();
            if (productionMode !== 'batch_production') {
                return false;
            }

            const itemType = String(product.item_type || '').trim();
            const trackStock = product.track_stock !== false;

            return trackStock && (itemType === '' || itemType === 'finished_good');
        };

        const getProductionBatchEligibility = (product = {}) => {
            const eligibility = getProductionBatchEligibilityPayload(product);
            if (eligibility) {
                return eligibility;
            }

            return evaluateProductionBatchEligibility(product);
        };

        const isProductionBatchDisplayCandidateProduct = (product = {}) => {
            return isProductionBatchEligibleProduct(product);
        };

        const prefetchedDocuments = new Set();
        const priorityPrefetchPaths = new Set([
            '/pos/dashboard',
            '/pos/checkout',
            '/pos/history',
            '/backoffice/dashboard',
            '/backoffice/reports',
            '/backoffice/reports/sales',
            '/backoffice/reports/inventory',
        ]);

        const canPrefetchDocument = (url) => {
            if (!(url instanceof URL)) {
                return false;
            }

            if (url.origin !== window.location.origin) {
                return false;
            }

            if (!/^https?:$/.test(url.protocol)) {
                return false;
            }

            if (document.visibilityState === 'hidden') {
                return false;
            }

            if (navigator.onLine === false || navigator.connection?.saveData) {
                return false;
            }

            if (url.pathname === window.location.pathname && url.search === window.location.search) {
                return false;
            }

            if (url.hash && url.pathname === window.location.pathname) {
                return false;
            }

            return true;
        };

        const shouldPrefetchLink = (link, url) => {
            if (!link || link.dataset.prefetch === 'off') {
                return false;
            }

            if (link.dataset.prefetch === 'priority') {
                return true;
            }

            return priorityPrefetchPaths.has(url.pathname);
        };

        const prefetchDocument = (href) => {
            let timeoutId = null;

            try {
                const url = new URL(href, window.location.origin);
                const cacheKey = url.toString();

                if (!canPrefetchDocument(url) || prefetchedDocuments.has(cacheKey)) {
                    return;
                }

                prefetchedDocuments.add(cacheKey);

                const controller = new AbortController();
                timeoutId = window.setTimeout(() => controller.abort(), 2500);

                fetch(cacheKey, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'force-cache',
                    signal: controller.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Purpose': 'prefetch',
                    },
                })
                    .catch(() => {
                        prefetchedDocuments.delete(cacheKey);
                    })
                    .finally(() => {
                        if (timeoutId) {
                            window.clearTimeout(timeoutId);
                        }
                    });
            } catch (_) {
                // Ignore malformed or unsupported urls.
            }
        };

        const bindNavigationPrefetch = () => {
            if (window.__kalakasirPrefetchBound) {
                return;
            }

            window.__kalakasirPrefetchBound = true;

            document.addEventListener('pointerenter', (event) => {
                const link = event.target instanceof Element
                    ? event.target.closest('a[href]')
                    : null;

                if (!link) {
                    return;
                }

                const url = new URL(link.href, window.location.origin);
                if (shouldPrefetchLink(link, url)) {
                    prefetchDocument(url.toString());
                }
            }, true);

            document.addEventListener('touchstart', (event) => {
                const link = event.target instanceof Element
                    ? event.target.closest('a[href]')
                    : null;

                if (!link) {
                    return;
                }

                const url = new URL(link.href, window.location.origin);
                if (shouldPrefetchLink(link, url)) {
                    prefetchDocument(url.toString());
                }
            }, { passive: true, capture: true });
        };

        bindNavigationPrefetch();

        window.PosUi = {
            bootstrapUrl,
            fetchJson,
            getBootstrap,
            evaluateProductionBatchEligibility,
            getProductionBatchEligibility,
            isProductionBatchEligibleProduct,
            isProductionBatchDisplayCandidateProduct,
            mapApiError,
            confirmRiskAction,
            fetchWithTimeout,
            prefetchDocument,
            runWithSubmitGuard,
            unwrapApiPayload,
            unwrapApiList,
            unwrapApiMeta,
        };
    })();
</script>
