<div id="menu-overlay" class="fixed inset-0 z-50 hidden">
    <div class="relative mx-auto h-full max-w-[430px]">
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
                        <p class="text-slate-900 dark:text-slate-100 text-sm font-semibold truncate">Admin</p>
                        <p class="text-slate-500 dark:text-slate-400 text-xs">Administrator</p>
                    </div>
                    <span class="material-symbols-outlined text-primary text-sm">verified_user</span>
                </div>
            </header>
            <nav class="flex-1 overflow-y-auto px-4 py-6">
                <ul class="space-y-2">
                    <li data-menu-role-min="admin">
                        <a class="flex items-center gap-4 px-4 py-4 rounded-2xl bg-primary/10 text-primary transition-colors" href="/menu/management">
                            <span class="material-symbols-outlined text-[26px]">inventory_2</span>
                            <span class="font-semibold text-base">Manajemen</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" href="/menu/sales">
                            <span class="material-symbols-outlined text-[26px]">point_of_sale</span>
                            <span class="font-semibold text-base">Transaksi Penjualan</span>
                        </a>
                    </li>
                    <li data-menu-role-min="admin">
                        <a class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" href="/menu/purchases">
                            <span class="material-symbols-outlined text-[26px]">local_shipping</span>
                            <span class="font-semibold text-base">Pembelian ke Supplier</span>
                        </a>
                    </li>
                    <li data-menu-role-min="admin">
                        <a class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" href="/menu/finance">
                            <span class="material-symbols-outlined text-[26px]">account_balance_wallet</span>
                            <span class="font-semibold text-base">Keuangan</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" href="/menu/shift">
                            <span class="material-symbols-outlined text-[26px]">schedule</span>
                            <span class="font-semibold text-base">Shift</span>
                        </a>
                    </li>
                    <li data-menu-role-min="admin">
                        <a class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" href="/menu/reports">
                            <span class="material-symbols-outlined text-[26px]">bar_chart</span>
                            <span class="font-semibold text-base">Laporan</span>
                        </a>
                    </li>
                    <li data-menu-role-min="admin">
                        <a class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" href="/menu/settings">
                            <span class="material-symbols-outlined text-[26px]">settings</span>
                            <span class="font-semibold text-base">Pengaturan</span>
                        </a>
                    </li>
                    <li>
                        <a class="flex items-center gap-4 px-4 py-4 rounded-2xl text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors" href="/menu/other">
                            <span class="material-symbols-outlined text-[26px]">more_horiz</span>
                            <span class="font-semibold text-base">Lainnya</span>
                        </a>
                    </li>
                </ul>
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

                        const role = data.user?.role || null;
                        if (role) {
                            const isAdminMenuVisible = ['admin', 'owner'].includes(String(role).toLowerCase());
                            setAdminMenuVisible(isAdminMenuVisible);
                        } else {
                            setAdminMenuVisible(true);
                        }
                    })
                    .catch(() => {});

                const setAdminMenuVisible = (visible) => {
                    document.querySelectorAll('[data-menu-role-min="admin"]').forEach((item) => {
                        item.classList.toggle('hidden', !visible);
                    });
                };
            }
        })();
    </script>