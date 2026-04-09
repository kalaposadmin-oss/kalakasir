<script>
    (() => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

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
                    headers,
                    signal: controller.signal,
                });
            } finally {
                clearTimeout(timer);
            }
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

        window.PosUi = {
            mapApiError,
            confirmRiskAction,
            fetchWithTimeout,
            runWithSubmitGuard,
            unwrapApiPayload,
            unwrapApiList,
            unwrapApiMeta,
        };
    })();
</script>
