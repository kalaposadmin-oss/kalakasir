@php
    $routeIsBackoffice = request()->is('backoffice*') || request()->is('admin/opening-balance') || request()->is('superadmin*');
    $activePortal = $routeIsBackoffice || session('auth_portal', 'pos') === 'backoffice' ? 'backoffice' : 'pos';
    $menuCurrentPath = '/'.ltrim(request()->path(), '/');
    $menuDrawerTenant = \App\Support\TenantContext::get();
    $menuDrawerUser = auth()->user();
    $menuDrawerRole = null;
    $roleLabels = [
        'cashier' => 'Kasir',
        'staff' => 'Staff',
        'warehouse' => 'Gudang',
        'supervisor' => 'Supervisor',
        'admin' => 'Admin',
        'owner' => 'Owner',
        'superadmin' => 'Superadmin',
    ];

    if (! $menuDrawerTenant && filled($menuDrawerUser?->tenant_id)) {
        $menuDrawerTenant = \App\Models\Tenant::query()->find($menuDrawerUser->tenant_id);
    }

    if ($menuDrawerTenant && $menuDrawerUser) {
        $menuDrawerRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $menuDrawerTenant->id, (string) $menuDrawerUser->id);

        if (! $menuDrawerRole && filled($menuDrawerUser->role ?? null)) {
            $menuDrawerRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $menuDrawerUser->role);
        }
    }

    $menuDrawerRoleLabel = $roleLabels[$menuDrawerRole] ?? 'Pengguna';
    $menuDrawerUserName = $menuDrawerUser?->name ?: 'Pengguna Toko';
    $canManageProducts = in_array($menuDrawerRole, ['admin', 'owner', 'superadmin'], true);
    $canManageSettings = in_array($menuDrawerRole, ['supervisor', 'admin', 'owner', 'superadmin'], true);
    $canManageUsers = in_array($menuDrawerRole, ['admin', 'owner', 'superadmin'], true);
    $canManageBranches = in_array($menuDrawerRole, ['admin', 'owner', 'superadmin'], true);
    $canAccessPurchasing = in_array($menuDrawerRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true);
    $canAccessCustomers = in_array($menuDrawerRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true);
    $posHomeHref = pos_default_url();
    $posPortalOriginPath = $menuCurrentPath !== '/' ? normalize_pos_home_url($menuCurrentPath) : $posHomeHref;
    $posBackofficeHref = static function (string $targetPath) use ($posPortalOriginPath): string {
        $separator = str_contains($targetPath, '?') ? '&' : '?';

        return '/portal/switch/backoffice?next='.rawurlencode($targetPath.$separator.'from='.rawurlencode($posPortalOriginPath));
    };
    $posProductHref = '/pos/products';
    $posProductCreateHref = '/pos/products/new';
    $posMaterialsHref = '/pos/materials';
    $posCategoryHref = '/pos/categories';
    $posInventoryHref = '/pos/inventory';
    $posPurchasesHref = '/pos/purchases';
    $posProductionHref = '/pos/inventory/production-batches';
    $posCustomersHref = '/pos/customers';
    $posBranchesHref = '/pos/branches';
    $posUsersHref = '/pos/users';
    $menuDrawerSubscription = app(\App\Services\SubscriptionService::class);
    $canFeature = static function (string $featureKey) use ($menuDrawerTenant, $menuDrawerSubscription): bool {
        if (! $menuDrawerTenant || $featureKey === '') {
            return false;
        }

        return $menuDrawerSubscription->canAccessFeature($menuDrawerTenant, $featureKey);
    };
    $canAnyFeature = static function (array $featureKeys) use ($canFeature): bool {
        foreach ($featureKeys as $featureKey) {
            if ($canFeature((string) $featureKey)) {
                return true;
            }
        }

        return false;
    };
    $isMenuItemActive = static function (array $item) use ($menuCurrentPath): bool {
        $matches = (array) ($item['match'] ?? $item['href'] ?? '');
        $matchChildren = (bool) ($item['match_children'] ?? true);

        foreach ($matches as $candidate) {
            $match = trim((string) $candidate);
            if ($match === '') {
                continue;
            }

            $matchPath = parse_url($match, PHP_URL_PATH) ?: preg_replace('/\?.*$/', '', $match);
            if ($matchPath === $menuCurrentPath) {
                return true;
            }

            if ($matchChildren && $matchPath !== '/' && str_starts_with($menuCurrentPath, rtrim($matchPath, '/').'/')) {
                return true;
            }
        }

        return false;
    };
    $withBackofficeBadge = static function (array $item): array {
        $item['badge'] = 'Backoffice';

        return $item;
    };

    $backofficeMenuSections = [
        [
            'title' => 'Utama',
            'items' => array_values(array_filter([
                ($canFeature('dash.overview') ? ['href' => '/backoffice/dashboard', 'match' => '/backoffice/dashboard', 'match_children' => false, 'icon' => 'dashboard', 'label' => 'Dashboard', 'description' => 'Ringkasan toko'] : null),
                ($canFeature('sale.pos') ? ['href' => '/backoffice/online-orders', 'match' => '/backoffice/online-orders', 'icon' => 'orders', 'label' => 'Order Online', 'description' => 'Pesanan web'] : null),
            ])),
        ],
        [
            'title' => 'Master Data',
            'items' => array_values(array_filter([
                ($canFeature('product.list') ? ['href' => '/backoffice/products', 'match' => '/backoffice/products', 'match_children' => false, 'icon' => 'inventory_2', 'label' => 'Produk', 'description' => 'Katalog barang'] : null),
                ($canManageProducts && $canFeature('product.list') ? ['href' => '/backoffice/products/new', 'match' => '/backoffice/products/new', 'match_children' => false, 'icon' => 'add_box', 'label' => 'Tambah Produk', 'description' => 'Input produk baru'] : null),
                ($canManageProducts && $canFeature('product.list') ? ['href' => '/backoffice/products/import', 'match' => '/backoffice/products/import', 'match_children' => false, 'icon' => 'file_upload', 'label' => 'Import Produk', 'description' => 'Impor CSV/XLSX produk'] : null),
                ($canFeature('product.category') ? ['href' => '/backoffice/categories', 'match' => '/backoffice/categories', 'match_children' => false, 'icon' => 'grid_view', 'label' => 'Kategori', 'description' => 'Kelompok produk'] : null),
                ($canFeature('product.materials') ? ['href' => '/backoffice/materials', 'match' => '/backoffice/materials', 'match_children' => false, 'icon' => 'nutrition', 'label' => 'Bahan Baku', 'description' => 'Resep dan produksi'] : null),
                ($canAccessCustomers && $canFeature('customer.manage') ? ['href' => '/backoffice/customers', 'match' => '/backoffice/customers', 'match_children' => false, 'icon' => 'groups', 'label' => 'Pelanggan', 'description' => 'Data customer'] : null),
                ($canFeature('supplier.manage') ? ['href' => '/backoffice/suppliers', 'match' => '/backoffice/suppliers', 'match_children' => false, 'icon' => 'local_shipping', 'label' => 'Supplier', 'description' => 'Vendor pembelian'] : null),
                ($canManageBranches && $canFeature('settings.branches_manage') ? ['href' => '/backoffice/branches', 'match' => '/backoffice/branches', 'icon' => 'storefront', 'label' => 'Toko & Cabang', 'description' => 'Lokasi usaha'] : null),
                ($canManageUsers && $canFeature('settings.staff_basic') ? ['href' => '/backoffice/users', 'match' => '/backoffice/users', 'match_children' => false, 'icon' => 'badge', 'label' => 'Pengguna', 'description' => 'Staff dan role'] : null),
            ])),
        ],
        [
            'title' => 'Stok & Produksi',
            'items' => array_values(array_filter([
                ($canFeature('inventory.stock') ? ['href' => '/backoffice/inventory', 'match' => '/backoffice/inventory', 'match_children' => false, 'icon' => 'warehouse', 'label' => 'Inventaris', 'description' => 'Stok aktif'] : null),
                ($canFeature('inventory.stock') ? ['href' => '/backoffice/inventory/fixed-assets', 'match' => '/backoffice/inventory/fixed-assets', 'match_children' => false, 'icon' => 'home_repair_service', 'label' => 'Aset Tetap', 'description' => 'Peralatan dan depresiasi'] : null),
                ($canFeature('inventory.adjustment') ? ['href' => '/backoffice/inventory/stock-adjustment', 'match' => '/backoffice/inventory/stock-adjustment', 'match_children' => false, 'icon' => 'edit_note', 'label' => 'Penyesuaian', 'description' => 'Koreksi stok'] : null),
                ($canFeature('inventory.stock_opname') ? ['href' => '/backoffice/inventory/stock-opname', 'match' => '/backoffice/inventory/stock-opname', 'match_children' => false, 'icon' => 'fact_check', 'label' => 'Stock Opname', 'description' => 'Hitung fisik'] : null),
                ($canFeature('inventory.transfer') ? ['href' => '/backoffice/inventory/stock-transfers', 'match' => '/backoffice/inventory/stock-transfers', 'match_children' => false, 'icon' => 'sync_alt', 'label' => 'Transfer Stok', 'description' => 'Antar lokasi'] : null),
                ($canFeature('inventory.production_batches') ? ['href' => '/backoffice/inventory/production-batches', 'match' => '/backoffice/inventory/production-batches', 'icon' => 'precision_manufacturing', 'label' => 'Produksi', 'description' => 'Batch produksi'] : null),
            ])),
        ],
        [
            'title' => 'Pembelian',
            'items' => array_values(array_filter([
                ($canAccessPurchasing && $canAnyFeature(['history.purchases', 'purchase.order', 'purchase.receive', 'purchase.return']) ? ['href' => '/backoffice/purchases', 'match' => '/backoffice/purchases', 'icon' => 'receipt_long', 'label' => 'Daftar Pembelian', 'description' => 'PO dan histori'] : null),
                ($canAccessPurchasing && $canFeature('purchase.order') ? ['href' => '/backoffice/purchases/new', 'match' => '/backoffice/purchases/new', 'match_children' => false, 'icon' => 'add_shopping_cart', 'label' => 'Buat PO', 'description' => 'Pembelian baru'] : null),
                ($canAccessPurchasing && $canFeature('purchase.receive') ? ['href' => '/backoffice/receiving', 'match' => '/backoffice/receiving', 'icon' => 'inventory', 'label' => 'Penerimaan', 'description' => 'Barang masuk'] : null),
                ($canManageProducts && $canFeature('purchase.return') ? ['href' => '/backoffice/purchases/returns', 'match' => '/backoffice/purchases/returns', 'match_children' => false, 'icon' => 'assignment_return', 'label' => 'Retur Pembelian', 'description' => 'Retur supplier'] : null),
            ])),
        ],
        [
            'title' => 'Laporan',
            'items' => array_values(array_filter([
                ($canFeature('reports.summary') ? ['href' => '/backoffice/reports', 'match' => '/backoffice/reports', 'match_children' => false, 'icon' => 'bar_chart', 'label' => 'Ringkasan', 'description' => 'KPI laporan'] : null),
                ($canFeature('reports.summary') ? ['href' => '/backoffice/reports/sales', 'match' => '/backoffice/reports/sales', 'match_children' => false, 'icon' => 'list_alt', 'label' => 'Penjualan', 'description' => 'Transaksi dan export'] : null),
                ($canFeature('reports.summary') ? ['href' => '/backoffice/reports/receivables', 'match' => '/backoffice/reports/receivables', 'match_children' => false, 'icon' => 'request_quote', 'label' => 'Piutang', 'description' => 'Tagihan customer'] : null),
                ($canFeature('reports.inventory_view') ? ['href' => '/backoffice/reports/inventory', 'match' => '/backoffice/reports/inventory', 'match_children' => false, 'icon' => 'inventory_2', 'label' => 'Persediaan', 'description' => 'Stok dan valuasi'] : null),
                ($canFeature('reports.inventory_stock_card') ? ['href' => '/backoffice/reports/inventory/stock-card', 'match' => '/backoffice/reports/inventory/stock-card', 'icon' => 'receipt_long', 'label' => 'Kartu Stok', 'description' => 'Ledger produk'] : null),
                ($canFeature('finance.summary_basic') ? ['href' => '/backoffice/finance/summary', 'match' => '/backoffice/finance/summary', 'match_children' => false, 'icon' => 'account_balance_wallet', 'label' => 'Keuangan', 'description' => 'Laba rugi dan neraca'] : null),
                ($canFeature('finance.cash_transactions') ? ['href' => '/backoffice/finance/cash', 'match' => ['/backoffice/finance/cash', '/backoffice/finance/cash/out'], 'match_children' => false, 'icon' => 'payments', 'label' => 'Kas Masuk/Keluar', 'description' => 'Setoran dan biaya'] : null),
                ($canManageSettings && $canFeature('finance.bank_accounts') ? ['href' => '/backoffice/finance/payment-settlements', 'match' => '/backoffice/finance/payment-settlements', 'match_children' => false, 'icon' => 'price_check', 'label' => 'Settlement QRIS', 'description' => 'Uang masuk real'] : null),
            ])),
        ],
        [
            'title' => 'Pengaturan',
            'items' => array_values(array_filter([
                ($canManageSettings && $canFeature('finance.bank_accounts') ? ['href' => '/backoffice/settings/payment-settings', 'match' => ['/backoffice/settings/payment-settings', '/backoffice/settings/chart-of-accounts'], 'match_children' => true, 'icon' => 'payments', 'label' => 'Pembayaran & Rekening', 'description' => 'POS, QRIS, transfer'] : null),
                ($canManageSettings && $canFeature('finance.bank_accounts') ? ['href' => '/admin/opening-balance', 'match' => '/admin/opening-balance', 'match_children' => false, 'icon' => 'savings', 'label' => 'Saldo Awal', 'description' => 'Kas, bank, stok'] : null),
                ($canManageSettings && $canFeature('finance.lock_period') ? ['href' => '/backoffice/finance/lock-periods', 'match' => '/backoffice/finance/lock-periods', 'match_children' => false, 'icon' => 'lock_clock', 'label' => 'Kunci Periode', 'description' => 'Kontrol tutup buku'] : null),
                ($canManageSettings && $canFeature('settings.tax') ? ['href' => '/backoffice/settings/tax-promo', 'match' => '/backoffice/settings/tax-promo', 'match_children' => false, 'icon' => 'percent', 'label' => 'Pajak & Promo', 'description' => 'Diskon dan pajak'] : null),
                ($canManageSettings && $canFeature('settings.store_profile') ? ['href' => '/backoffice/settings/preferences', 'match' => '/backoffice/settings/preferences', 'match_children' => false, 'icon' => 'tune', 'label' => 'Preferensi', 'description' => 'Setelan toko'] : null),
                ($canManageSettings && $canFeature('settings.inventory_method_basic') ? ['href' => '/backoffice/settings/inventory-method', 'match' => '/backoffice/settings/inventory-method', 'match_children' => false, 'icon' => 'rule_settings', 'label' => 'Metode Persediaan', 'description' => 'FIFO atau average'] : null),
                ($canManageSettings && $canFeature('settings.integrations') ? ['href' => '/backoffice/settings/integrations', 'match' => '/backoffice/settings/integrations', 'match_children' => false, 'icon' => 'integration_instructions', 'label' => 'Integrasi', 'description' => 'Perangkat dan koneksi'] : null),
                ($canManageSettings && $canFeature('settings.subscription_billing') ? ['href' => '/backoffice/settings/subscription', 'match' => '/backoffice/settings/subscription', 'icon' => 'workspace_premium', 'label' => 'Langganan', 'description' => 'Paket dan invoice'] : null),
                ($canManageSettings && $canFeature('settings.inventory_valuation') ? ['href' => '/backoffice/settings/inventory-valuation', 'match' => '/backoffice/settings/inventory-valuation', 'match_children' => false, 'icon' => 'monitoring', 'label' => 'Valuation Lanjutan', 'description' => 'Audit nilai stok'] : null),
                ($canManageSettings && $canFeature('settings.inventory_valuation') ? ['href' => '/backoffice/settings/inventory-valuation-audit', 'match' => '/backoffice/settings/inventory-valuation-audit', 'match_children' => false, 'icon' => 'history', 'label' => 'Audit Valuation', 'description' => 'Riwayat valuation'] : null),
                ($canManageSettings && $canFeature('settings.observability') ? ['href' => '/backoffice/settings/observability', 'match' => '/backoffice/settings/observability', 'match_children' => false, 'icon' => 'insights', 'label' => 'Observability', 'description' => 'Kesehatan sistem'] : null),
            ])),
        ],
        [
            'title' => 'Bantuan',
            'items' => array_values(array_filter([
                ['href' => '/backoffice/help/center', 'match' => '/backoffice/help/center', 'match_children' => false, 'icon' => 'help', 'label' => 'Pusat Bantuan', 'description' => 'Panduan kerja'],
                ['href' => '/backoffice/help/support', 'match' => '/backoffice/help/support', 'match_children' => false, 'icon' => 'support_agent', 'label' => 'Support', 'description' => 'Hubungi bantuan'],
                ($canFeature('loyalty.program') ? ['href' => '/backoffice/loyalty', 'match' => '/backoffice/loyalty', 'match_children' => false, 'icon' => 'loyalty', 'label' => 'Loyalty', 'description' => 'Program pelanggan'] : null),
                ($canFeature('dash.overview') ? ['href' => '/backoffice/marketing/online-store', 'match' => '/backoffice/marketing/online-store', 'match_children' => false, 'icon' => 'storefront', 'label' => 'Toko Online', 'description' => 'Etalase web'] : null),
            ])),
        ],
        [
            'title' => 'Area',
            'items' => [
                ['href' => '/portal/switch/pos?next=/pos/checkout', 'match' => '/portal/switch/pos', 'match_children' => false, 'icon' => 'point_of_sale', 'label' => 'Area Kasir', 'description' => 'Transaksi harian'],
                ['href' => '/logout', 'match' => '/logout', 'match_children' => false, 'icon' => 'logout', 'label' => 'Keluar', 'description' => 'Akhiri sesi'],
            ],
        ],
    ];

    $posMenuSections = [
        [
            'title' => 'Transaksi',
            'items' => array_values(array_filter([
                ['href' => $posHomeHref, 'match' => '/pos/dashboard', 'match_children' => false, 'icon' => 'home', 'label' => 'Beranda', 'description' => 'Ringkasan kasir'],
                ['href' => '/pos/checkout', 'match' => '/pos/checkout', 'match_children' => false, 'icon' => 'point_of_sale', 'label' => 'Kasir', 'description' => 'Mulai transaksi'],
                ['href' => '/pos/history', 'match' => '/pos/history', 'match_children' => false, 'icon' => 'history', 'label' => 'Riwayat', 'description' => 'Transaksi selesai'],
                ($canFeature('history.sales') ? ['href' => '/pos/receivables', 'match' => '/pos/receivables', 'match_children' => false, 'icon' => 'request_quote', 'label' => 'Piutang', 'description' => 'Tagihan pelanggan'] : null),
                ($canAccessCustomers && $canFeature('customer.manage') ? ['href' => $posCustomersHref, 'match' => '/pos/customers', 'match_children' => false, 'icon' => 'groups', 'label' => 'Pelanggan', 'description' => 'Data pelanggan'] : null),
                ($canFeature('finance.cash_transactions') ? ['href' => '/pos/cash-in', 'match' => ['/pos/cash-in', '/pos/cash-out'], 'match_children' => false, 'icon' => 'payments', 'label' => 'Kas', 'description' => 'Kas masuk/keluar'] : null),
                ['href' => '/pos/shifts/open', 'match' => '/pos/shifts/open', 'match_children' => false, 'icon' => 'schedule', 'label' => 'Buka Shift', 'description' => 'Mulai shift'],
                ['href' => '/pos/shifts/summary', 'match' => '/pos/shifts', 'icon' => 'summarize', 'label' => 'Ringkasan Shift', 'description' => 'Riwayat shift'],
            ])),
        ],
        [
            'title' => 'Operasional',
            'items' => array_values(array_filter([
                ($canFeature('product.list') ? ['href' => $posProductHref, 'match' => '/pos/products', 'match_children' => false, 'icon' => 'inventory_2', 'label' => 'Produk', 'description' => 'Katalog barang'] : null),
                ($canManageProducts && $canFeature('product.list') ? ['href' => $posProductCreateHref, 'match' => '/pos/products/new', 'match_children' => false, 'icon' => 'add_box', 'label' => 'Tambah Produk', 'description' => 'Produk baru'] : null),
                ($canFeature('product.category') ? ['href' => $posCategoryHref, 'match' => '/pos/categories', 'match_children' => false, 'icon' => 'grid_view', 'label' => 'Kategori', 'description' => 'Kelompok produk'] : null),
                ($canFeature('product.materials') ? ['href' => $posMaterialsHref, 'match' => '/pos/materials', 'match_children' => false, 'icon' => 'nutrition', 'label' => 'Bahan Baku', 'description' => 'Resep dan produksi'] : null),
                ($canFeature('inventory.stock') ? ['href' => $posInventoryHref, 'match' => '/pos/inventory', 'match_children' => false, 'icon' => 'warehouse', 'label' => 'Inventaris', 'description' => 'Stok aktif'] : null),
                ($canFeature('inventory.adjustment') ? ['href' => '/pos/inventory/stock-adjustment', 'match' => '/pos/inventory/stock-adjustment', 'match_children' => false, 'icon' => 'edit_note', 'label' => 'Penyesuaian', 'description' => 'Koreksi stok'] : null),
                ($canFeature('inventory.stock_opname') ? ['href' => '/pos/inventory/stock-opname', 'match' => '/pos/inventory/stock-opname', 'match_children' => false, 'icon' => 'fact_check', 'label' => 'Stock Opname', 'description' => 'Hitung fisik'] : null),
                ($canAccessPurchasing && $canFeature('purchase.order') ? ['href' => '/pos/purchases/new', 'match' => '/pos/purchases/new', 'match_children' => false, 'icon' => 'add_shopping_cart', 'label' => 'Buat PO', 'description' => 'Pembelian baru'] : null),
                ($canAccessPurchasing && $canAnyFeature(['history.purchases', 'purchase.order', 'purchase.receive', 'purchase.return']) ? ['href' => $posPurchasesHref, 'match' => '/pos/purchases', 'icon' => 'local_shipping', 'label' => 'Pembelian', 'description' => 'PO dan receiving'] : null),
                ($canFeature('inventory.production_batches') ? ['href' => $posProductionHref, 'match' => '/pos/inventory/production-batches', 'icon' => 'precision_manufacturing', 'label' => 'Produksi', 'description' => 'Batch produksi'] : null),
            ])),
        ],
        [
            'title' => 'Admin',
            'items' => array_values(array_filter([
                ($canManageBranches && $canFeature('settings.branches_manage') ? ['href' => $posBranchesHref, 'match' => '/pos/branches', 'icon' => 'storefront', 'label' => 'Toko & Cabang', 'description' => 'Lokasi usaha'] : null),
                ($canManageUsers && $canFeature('settings.staff_basic') ? ['href' => $posUsersHref, 'match' => '/pos/users', 'match_children' => false, 'icon' => 'badge', 'label' => 'Pengguna', 'description' => 'Staff dan role'] : null),
                ($canManageSettings && $canFeature('finance.summary_basic') ? ['href' => '/pos/finance/summary', 'match' => '/pos/finance/summary', 'match_children' => false, 'icon' => 'account_balance_wallet', 'label' => 'Keuangan', 'description' => 'Laba rugi dan neraca'] : null),
            ])),
        ],
        [
            'title' => 'Backoffice',
            'items' => array_values(array_filter([
                ($canFeature('dash.overview') ? $withBackofficeBadge(['href' => $posBackofficeHref('/backoffice/dashboard'), 'match' => '/portal/switch/backoffice', 'match_children' => false, 'icon' => 'dashboard', 'label' => 'Dashboard', 'description' => 'Ringkasan bisnis']) : null),
                ($canFeature('product.list') ? $withBackofficeBadge(['href' => $posBackofficeHref('/backoffice/products'), 'match' => '/portal/switch/backoffice', 'match_children' => false, 'icon' => 'inventory_2', 'label' => 'Produk', 'description' => 'Katalog backoffice']) : null),
                ($canFeature('reports.summary') ? $withBackofficeBadge(['href' => $posBackofficeHref('/backoffice/reports'), 'match' => '/portal/switch/backoffice', 'match_children' => false, 'icon' => 'bar_chart', 'label' => 'Laporan', 'description' => 'Laporan lanjutan']) : null),
                ($canFeature('finance.summary_basic') ? $withBackofficeBadge(['href' => $posBackofficeHref('/backoffice/finance/summary'), 'match' => '/portal/switch/backoffice', 'match_children' => false, 'icon' => 'account_balance_wallet', 'label' => 'Keuangan', 'description' => 'Ringkasan finansial']) : null),
                ($canManageSettings && $canFeature('settings.store_profile') ? $withBackofficeBadge(['href' => $posBackofficeHref('/backoffice/settings/preferences'), 'match' => '/portal/switch/backoffice', 'match_children' => false, 'icon' => 'settings', 'label' => 'Pengaturan', 'description' => 'Setelan toko']) : null),
            ])),
        ],
        [
            'title' => 'Area',
            'items' => [
                ['href' => $posBackofficeHref('/backoffice/dashboard'), 'match' => '/portal/switch/backoffice', 'match_children' => false, 'icon' => 'storefront', 'label' => 'Area Backoffice', 'description' => 'Data master dan laporan'],
                ['href' => '/logout', 'match' => '/logout', 'match_children' => false, 'icon' => 'logout', 'label' => 'Keluar', 'description' => 'Akhiri sesi'],
            ],
        ],
    ];

    $menuSections = $activePortal === 'backoffice'
        ? $backofficeMenuSections
        : $posMenuSections;
@endphp

@if ($activePortal === 'backoffice')
    <aside class="fixed inset-y-0 left-0 z-40 hidden w-72 flex-col border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950 xl:flex">
        <header class="border-b border-slate-200 px-5 py-5 dark:border-slate-800">
            <a class="flex items-center gap-3" href="/backoffice/dashboard" data-prefetch="priority">
                <span class="flex size-10 items-center justify-center rounded-lg bg-primary text-base font-black text-white shadow-sm shadow-primary/25">K</span>
                <span class="min-w-0">
                    <span class="block text-sm font-black uppercase tracking-[0.16em] text-slate-950 dark:text-white">Kalakasir</span>
                    <span class="block truncate text-xs font-semibold text-slate-500 dark:text-slate-400" data-menu-store-name>{{ $menuDrawerTenant?->name ?: 'Backoffice' }}</span>
                </span>
            </a>
            <div class="mt-4 grid grid-cols-2 gap-2">
                <a class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white shadow-sm shadow-primary/20" href="/portal/switch/pos?next=/pos/checkout">
                    <span class="material-symbols-outlined text-[17px]">point_of_sale</span>
                    Kasir
                </a>
                <a class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-900" href="/backoffice/reports">
                    <span class="material-symbols-outlined text-[17px]">bar_chart</span>
                    Laporan
                </a>
            </div>
        </header>

        <nav class="app-menu-scroll min-h-0 flex-1 overflow-y-auto px-3 py-4">
            @include('partials.menu-items', ['menuRenderVariant' => 'desktop'])
        </nav>

        <footer class="border-t border-slate-200 p-4 dark:border-slate-800">
            <div class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-900">
                <div class="size-9 overflow-hidden rounded-full bg-white ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                    <img alt="User profile" class="h-full w-full object-cover" src="/images/placeholders/user.svg">
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-slate-900 dark:text-slate-100">{{ $menuDrawerUserName }}</p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $menuDrawerRoleLabel }} - <span data-menu-branch-name>Cabang utama</span></p>
                </div>
            </div>
        </footer>
    </aside>
@endif

<div id="menu-overlay" class="{{ $activePortal === 'backoffice' ? 'fixed xl:hidden' : 'absolute' }} inset-0 z-50 hidden overflow-hidden">
    <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" data-menu-close></div>
    <aside id="menu-drawer" class="app-mobile-drawer absolute left-0 top-0 flex h-full min-h-0 w-[21rem] max-w-[88vw] -translate-x-full flex-col overflow-hidden bg-white shadow-2xl transition-transform duration-300 dark:bg-slate-950">
        <header class="shrink-0 border-b border-slate-200 px-4 pb-4 pt-5 dark:border-slate-800">
            <div class="mb-4 flex items-center justify-between">
                <p class="text-xs font-bold uppercase tracking-[0.24em] text-slate-400">Menu</p>
                <button class="flex size-9 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-slate-100" data-menu-close type="button" aria-label="Tutup menu">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <div class="mb-4 flex items-center gap-3">
                <div class="flex size-12 items-center justify-center rounded-lg bg-primary text-white shadow-sm shadow-primary/25">
                    <span class="material-symbols-outlined text-[26px]">store</span>
                </div>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-black leading-tight text-slate-900 dark:text-slate-100" data-menu-store-name>{{ $menuDrawerTenant?->name ?: 'Toko' }}</h1>
                    <p class="truncate text-sm font-semibold text-primary" data-menu-branch-name>Cabang utama</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900">
                <div class="size-10 overflow-hidden rounded-full bg-white ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
                    <img alt="User profile" class="h-full w-full object-cover" src="/images/placeholders/user.svg">
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $menuDrawerUserName }}</p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $menuDrawerRoleLabel }} - {{ $activePortal === 'backoffice' ? 'Backoffice' : 'Kasir' }}</p>
                </div>
                <span class="material-symbols-outlined text-[18px] text-primary">verified_user</span>
            </div>
        </header>

        <nav class="app-menu-scroll min-h-0 flex-1 overflow-y-auto px-4 pt-5 app-bottom-safe-space">
            @include('partials.menu-items', ['menuRenderVariant' => 'mobile'])
        </nav>
    </aside>
</div>

<script>
    (() => {
        const bootMenu = () => {
            const overlay = document.getElementById('menu-overlay');
            const drawer = document.getElementById('menu-drawer');
            const triggers = document.querySelectorAll('[data-menu-trigger]');
            const storeNameTargets = document.querySelectorAll('[data-menu-store-name]');
            const branchNameTargets = document.querySelectorAll('[data-menu-branch-name]');
            const menuScrollers = document.querySelectorAll('.app-menu-scroll');
            const portalKey = @json($activePortal);
            const scrollStorageKey = `kalakasir:menu:${portalKey}:scroll`;
            const sectionStorageKey = `kalakasir:menu:${portalKey}:sections`;

            if (!overlay || !drawer) {
                return;
            }

            const readOpenSections = () => {
                try {
                    const raw = window.localStorage.getItem(sectionStorageKey);
                    if (raw === null) {
                        return null;
                    }

                    const parsed = JSON.parse(raw || '[]');
                    return Array.isArray(parsed) ? new Set(parsed) : null;
                } catch (error) {
                    return null;
                }
            };

            const writeOpenSections = () => {
                const openKeys = [...document.querySelectorAll('[data-menu-section]')]
                    .filter((section) => section.querySelector('[data-menu-section-toggle]')?.getAttribute('aria-expanded') === 'true')
                    .map((section) => section.dataset.menuSection)
                    .filter(Boolean);
                window.localStorage.setItem(sectionStorageKey, JSON.stringify([...new Set(openKeys)]));
            };

            const setSectionOpen = (section, open) => {
                const toggle = section.querySelector('[data-menu-section-toggle]');
                const items = section.querySelector('[data-menu-section-items]');
                if (!toggle || !items) {
                    return;
                }

                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                items.classList.toggle('hidden', !open);
            };

            const restoreMenuState = () => {
                const openSections = readOpenSections();
                if (openSections !== null) {
                    document.querySelectorAll('[data-menu-section]').forEach((section) => {
                        setSectionOpen(section, openSections.has(section.dataset.menuSection));
                    });
                } else {
                    document.querySelectorAll('[data-menu-section]').forEach((section) => {
                        if (section.querySelector('[data-menu-section-toggle]')?.getAttribute('aria-expanded') === 'true') {
                            setSectionOpen(section, true);
                        }
                    });
                }

                const activeLink = document.querySelector('.app-menu-scroll a.bg-primary');
                const activeSection = activeLink?.closest('[data-menu-section]');
                if (activeSection) {
                    setSectionOpen(activeSection, true);
                }

                const savedScroll = Number(window.sessionStorage.getItem(scrollStorageKey) || 0);
                menuScrollers.forEach((scroller) => {
                    if (savedScroll > 0) {
                        scroller.scrollTop = savedScroll;
                    }
                });

                window.requestAnimationFrame(() => {
                    const currentActiveLink = document.querySelector('.app-menu-scroll a.bg-primary');
                    if (currentActiveLink) {
                        currentActiveLink.scrollIntoView({ block: 'nearest' });
                    }
                });
            };

            const persistMenuScroll = (scroller) => {
                window.sessionStorage.setItem(scrollStorageKey, String(scroller.scrollTop || 0));
            };

            const setTriggerState = (isOpen) => {
                triggers.forEach((trigger) => {
                    trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                });
            };

            const openMenu = () => {
                overlay.classList.remove('hidden');
                requestAnimationFrame(() => drawer.classList.remove('-translate-x-full'));
                document.documentElement.classList.add('menu-open');
                setTriggerState(true);
            };

            const closeMenu = () => {
                drawer.classList.add('-translate-x-full');
                document.documentElement.classList.remove('menu-open');
                setTriggerState(false);
                window.setTimeout(() => {
                    overlay.classList.add('hidden');
                }, 180);
            };

            triggers.forEach((trigger) => {
                trigger.setAttribute('aria-haspopup', 'dialog');
                trigger.setAttribute('aria-expanded', 'false');
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    openMenu();
                });
            });

            document.querySelectorAll('[data-menu-section-toggle]').forEach((toggle) => {
                toggle.addEventListener('click', () => {
                    const section = toggle.closest('[data-menu-section]');
                    if (!section) {
                        return;
                    }

                    const isOpen = toggle.getAttribute('aria-expanded') === 'true';
                    setSectionOpen(section, !isOpen);
                    writeOpenSections();
                });
            });

            menuScrollers.forEach((scroller) => {
                scroller.addEventListener('scroll', () => persistMenuScroll(scroller), { passive: true });
                scroller.querySelectorAll('a[href]').forEach((link) => {
                    link.addEventListener('click', () => persistMenuScroll(scroller));
                });
            });

            restoreMenuState();

            document.addEventListener('click', (event) => {
                const closeButton = event.target.closest('[data-menu-close]');
                if (closeButton) {
                    event.preventDefault();
                    closeMenu();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !overlay.classList.contains('hidden')) {
                    closeMenu();
                }
            });

            const centerHeaderTitles = () => {
                const backIcons = ['arrow_back', 'arrow_back_ios', 'arrow_back_ios_new', 'close'];
                document.querySelectorAll('header').forEach((header) => {
                    if (header.dataset.autoCenterTitle !== 'true') {
                        return;
                    }
                    const title = header.querySelector('h1, h2');
                    if (!title || title.dataset.centeredTitle === 'true') {
                        return;
                    }
                    if (title.closest('.text-center')) {
                        return;
                    }
                    const iconEl = header.querySelector('.material-symbols-outlined');
                    const icon = iconEl?.textContent?.trim();
                    if (!icon || !backIcons.includes(icon)) {
                        return;
                    }

                    const row = header.querySelector('.flex.items-center.justify-between') || header;
                    row.classList.add('relative');

                    const centered = title.cloneNode(true);
                    centered.dataset.centeredTitle = 'true';
                    centered.classList.add('absolute', 'left-1/2', '-translate-x-1/2', 'text-center');
                    row.appendChild(centered);
                    title.classList.add('opacity-0', 'pointer-events-none');
                });
            };

            centerHeaderTitles();

            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            if (tenantId && window.PosUi?.getBootstrap) {
                window.PosUi.getBootstrap({ headers: { 'X-Tenant-Id': tenantId } })
                    .then((data) => {
                        if (!data) {
                            return;
                        }

                        storeNameTargets.forEach((target) => {
                            target.textContent = data.tenant?.name || 'Toko';
                        });

                        branchNameTargets.forEach((target) => {
                            target.textContent = data.branches?.[0]?.name || 'Cabang utama';
                        });
                    })
                    .catch(() => {});
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bootMenu, { once: true });
            return;
        }

        bootMenu();
    })();
</script>
