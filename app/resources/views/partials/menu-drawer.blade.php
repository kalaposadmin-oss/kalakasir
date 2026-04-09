@php
    $activePortal = session('auth_portal', 'pos') === 'backoffice' ? 'backoffice' : 'pos';
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
    $posPortalOriginPath = $menuCurrentPath !== '/' ? $menuCurrentPath : '/pos/dashboard';
    $posBackofficeHref = static function (string $targetPath) use ($posPortalOriginPath): string {
        $separator = str_contains($targetPath, '?') ? '&' : '?';

        return '/portal/switch/backoffice?next='.rawurlencode($targetPath.$separator.'from='.rawurlencode($posPortalOriginPath));
    };

    $isMenuItemActive = static function (array $item) use ($menuCurrentPath): bool {
        $match = trim((string) ($item['match'] ?? $item['href'] ?? ''));
        if ($match === '') {
            return false;
        }

        $match = preg_replace('/\?.*$/', '', $match);
        if ($match === $menuCurrentPath) {
            return true;
        }

        return $match !== '/' && str_starts_with($menuCurrentPath, rtrim($match, '/').'/');
    };

    $backofficeMenuSections = [
        [
            'title' => 'Operasional',
            'items' => array_values(array_filter([
                ['href' => '/backoffice/dashboard', 'match' => '/backoffice/dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard', 'description' => 'Ringkasan bisnis'],
                ['href' => '/backoffice/products', 'match' => '/backoffice/products', 'icon' => 'inventory_2', 'label' => 'Daftar Produk', 'description' => 'Lihat katalog barang'],
                $canManageProducts ? ['href' => '/backoffice/products/new', 'match' => '/backoffice/products/new', 'icon' => 'add_box', 'label' => 'Tambah Produk', 'description' => 'Buat produk baru'] : null,
                ['href' => '/backoffice/categories', 'match' => '/backoffice/categories', 'icon' => 'grid_view', 'label' => 'Kategori', 'description' => 'Kelompokkan produk'],
                ['href' => '/backoffice/inventory', 'match' => '/backoffice/inventory', 'icon' => 'warehouse', 'label' => 'Stok & Inventaris', 'description' => 'Pantau stok aktif'],
                $canManageProducts ? ['href' => '/backoffice/purchases/new', 'match' => '/backoffice/purchases', 'icon' => 'local_shipping', 'label' => 'Pembelian', 'description' => 'Buat dan kelola PO'] : null,
                ['href' => '/backoffice/branches', 'match' => '/backoffice/branches', 'icon' => 'storefront', 'label' => 'Toko & Cabang', 'description' => 'Atur lokasi operasional'],
                $canManageUsers ? ['href' => '/backoffice/users', 'match' => '/backoffice/users', 'icon' => 'groups', 'label' => 'Pengguna', 'description' => 'Atur staff dan role'] : null,
            ])),
        ],
        [
            'title' => 'Laporan',
            'items' => [
                ['href' => '/backoffice/reports', 'match' => '/backoffice/reports', 'icon' => 'bar_chart', 'label' => 'Laporan', 'description' => 'Penjualan dan inventaris'],
            ],
        ],
        [
            'title' => 'Pengaturan',
            'items' => array_values(array_filter([
                $canManageSettings ? ['href' => '/backoffice/settings/chart-of-accounts', 'match' => '/backoffice/settings/chart-of-accounts', 'icon' => 'account_balance', 'label' => 'Akun Kas & Bank', 'description' => 'COA dan akun pembayaran'] : null,
                $canManageSettings ? ['href' => '/backoffice/settings/tax-promo', 'match' => '/backoffice/settings/tax-promo', 'icon' => 'percent', 'label' => 'Pajak & Promo', 'description' => 'Atur pajak dan diskon'] : null,
                $canManageSettings ? ['href' => '/backoffice/settings/preferences', 'match' => '/backoffice/settings/preferences', 'icon' => 'tune', 'label' => 'Preferensi', 'description' => 'Pengaturan toko'] : null,
                $canManageSettings ? ['href' => '/backoffice/settings/integrations', 'match' => '/backoffice/settings/integrations', 'icon' => 'integration_instructions', 'label' => 'Integrasi', 'description' => 'Perangkat dan koneksi'] : null,
                $canManageSettings ? ['href' => '/backoffice/settings/subscription', 'match' => '/backoffice/settings/subscription', 'icon' => 'workspace_premium', 'label' => 'Langganan', 'description' => 'Billing dan paket'] : null,
            ])),
        ],
        [
            'title' => 'Portal',
            'items' => [
                ['href' => '/portal/switch/pos?next=/pos/checkout', 'match' => '/portal/switch/pos', 'icon' => 'point_of_sale', 'label' => 'Buka POS', 'description' => 'Masuk ke kasir'],
                ['href' => '/logout', 'match' => '/logout', 'icon' => 'logout', 'label' => 'Keluar', 'description' => 'Akhiri sesi login'],
            ],
        ],
    ];

    $posMenuSections = [
        [
            'title' => 'Transaksi',
            'items' => [
                ['href' => '/pos/dashboard', 'match' => '/pos/dashboard', 'icon' => 'home', 'label' => 'Beranda', 'description' => 'Ringkasan kasir'],
                ['href' => '/pos/checkout', 'match' => '/pos/checkout', 'icon' => 'point_of_sale', 'label' => 'Kasir', 'description' => 'Mulai transaksi'],
                ['href' => '/pos/history', 'match' => '/pos/history', 'icon' => 'history', 'label' => 'Riwayat', 'description' => 'Lihat transaksi selesai'],
                ['href' => '/pos/shifts/open', 'match' => '/pos/shifts/open', 'icon' => 'schedule', 'label' => 'Buka Shift', 'description' => 'Mulai shift kasir'],
                ['href' => '/pos/shifts/summary', 'match' => '/pos/shifts', 'icon' => 'summarize', 'label' => 'Ringkasan Shift', 'description' => 'Riwayat buka/tutup shift'],
            ],
        ],
        [
            'title' => 'Akses Cepat',
            'items' => array_values(array_filter([
                $canManageSettings ? ['href' => $posBackofficeHref('/backoffice/products'), 'match' => '/portal/switch/backoffice', 'icon' => 'inventory_2', 'label' => 'Kelola Produk', 'description' => 'Buka daftar produk di backoffice'] : null,
                $canManageProducts ? ['href' => $posBackofficeHref('/backoffice/products/new'), 'match' => '/portal/switch/backoffice', 'icon' => 'add_box', 'label' => 'Tambah Produk', 'description' => 'Tambah produk baru'] : null,
                $canManageSettings ? ['href' => $posBackofficeHref('/backoffice/inventory'), 'match' => '/portal/switch/backoffice', 'icon' => 'warehouse', 'label' => 'Stok & Inventaris', 'description' => 'Cek stok dan pergerakan barang'] : null,
                $canManageSettings ? ['href' => $posBackofficeHref('/backoffice/settings/preferences'), 'match' => '/portal/switch/backoffice', 'icon' => 'settings', 'label' => 'Pengaturan Toko', 'description' => 'Akses setelan backoffice'] : null,
                $canManageSettings ? ['href' => $posBackofficeHref('/backoffice/settings/chart-of-accounts'), 'match' => '/portal/switch/backoffice', 'icon' => 'account_balance', 'label' => 'Akun Kas & Bank', 'description' => 'Kelola akun pembayaran'] : null,
            ])),
        ],
        [
            'title' => 'Portal',
            'items' => [
                ['href' => $posBackofficeHref('/backoffice/dashboard'), 'match' => '/portal/switch/backoffice', 'icon' => 'storefront', 'label' => 'Backoffice', 'description' => 'Pindah ke manajemen toko'],
                ['href' => '/logout', 'match' => '/logout', 'icon' => 'logout', 'label' => 'Keluar', 'description' => 'Akhiri sesi login'],
            ],
        ],
    ];

    $menuSections = $activePortal === 'backoffice'
        ? $backofficeMenuSections
        : $posMenuSections;
@endphp

<div id="menu-overlay" class="absolute inset-0 z-50 hidden overflow-hidden">
    <div class="relative h-full w-full">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <aside id="menu-drawer" class="absolute left-0 top-0 h-full w-[85%] max-w-[320px] flex-col bg-white dark:bg-slate-900 shadow-2xl flex overflow-hidden -translate-x-full transition-transform">
            <header class="relative p-6 pt-12 border-b border-primary/10 bg-primary/5">
                <button type="button" data-menu-close class="absolute right-4 top-4 flex size-9 items-center justify-center rounded-full bg-white/80 text-slate-700 shadow-sm transition hover:bg-white">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
                <div class="flex items-center gap-4 mb-6">
                    <div class="size-14 rounded-full bg-primary flex items-center justify-center text-white shadow-lg shadow-primary/30">
                        <span class="material-symbols-outlined text-3xl">store</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h1 id="menu-store-name" class="text-slate-900 dark:text-slate-100 text-lg font-bold leading-tight truncate">Toko</h1>
                        <p id="menu-branch-name" class="text-primary text-sm font-medium">Cabang</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-3 bg-white dark:bg-slate-800 rounded-xl border border-primary/10">
                    <div class="size-10 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                        <img alt="User profile" class="w-full h-full object-cover" src="https://placehold.co/80x80?text=User" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-slate-900 dark:text-slate-100 text-sm font-semibold truncate">{{ $menuDrawerUserName }}</p>
                        <p class="text-slate-500 dark:text-slate-400 text-xs">{{ $menuDrawerRoleLabel }} • {{ $activePortal === 'backoffice' ? 'Backoffice' : 'POS' }}</p>
                    </div>
                    <span class="material-symbols-outlined text-primary text-sm">verified_user</span>
                </div>
            </header>
            <nav class="flex-1 overflow-y-auto px-4 py-6">
                <div class="space-y-6">
                    @foreach ($menuSections as $section)
                        @if (!empty($section['items']))
                            <section>
                                <p class="mb-2 px-2 text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">{{ $section['title'] }}</p>
                                <ul class="space-y-2">
                                    @foreach ($section['items'] as $item)
                                        @php($isActive = $isMenuItemActive($item))
                                        <li>
                                            <a class="flex items-center gap-4 rounded-2xl px-4 py-3 transition-colors {{ $isActive ? 'bg-primary/10 text-primary ring-1 ring-primary/15' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800' }}" href="{{ $item['href'] }}">
                                                <span class="material-symbols-outlined text-[24px] {{ $isActive ? 'text-primary' : '' }}">{{ $item['icon'] }}</span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-sm font-semibold">{{ $item['label'] }}</span>
                                                    @if (!empty($item['description']))
                                                        <span class="block truncate text-[11px] {{ $isActive ? 'text-primary/80' : 'text-slate-500 dark:text-slate-400' }}">{{ $item['description'] }}</span>
                                                    @endif
                                                </span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                    @endforeach
                </div>
            </nav>
         </aside>
     </div>
    <script>
        (() => {
            const overlay = document.getElementById('menu-overlay');
            const drawer = document.getElementById('menu-drawer');
            const triggers = document.querySelectorAll('[data-menu-trigger]');
            const storeName = document.getElementById('menu-store-name');
            const branchName = document.getElementById('menu-branch-name');

            const openMenu = () => {
                overlay.classList.remove('hidden');
                drawer.classList.remove('-translate-x-full');
            };

            const closeMenu = () => {
                overlay.classList.add('hidden');
                drawer.classList.add('-translate-x-full');
            };

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-menu-trigger]');
                if (trigger) {
                    event.preventDefault();
                    openMenu();
                    return;
                }
                const closeButton = event.target.closest('[data-menu-close]');
                if (closeButton) {
                    event.preventDefault();
                    closeMenu();
                }
            });
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) {
                    closeMenu();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
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
            if (tenantId) {
                fetch('/api/v1/bootstrap', { headers: { 'X-Tenant-Id': tenantId } })
                    .then((response) => response.ok ? response.json() : null)
                    .then((data) => {
                        if (!data) {
                            return;
                        }
                        storeName.textContent = data.tenant?.name || 'Toko';
                        branchName.textContent = data.branches?.[0]?.name || 'Cabang';

                    })
                    .catch(() => {});
            }
        })();
    </script>
