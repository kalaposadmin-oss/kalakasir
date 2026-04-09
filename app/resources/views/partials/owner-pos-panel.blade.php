@php
    $ownerPanelTenant = \App\Support\TenantContext::get();
    $ownerPanelUser = auth()->user();
    $ownerPanelRole = null;

    if (! $ownerPanelTenant && filled($ownerPanelUser?->tenant_id)) {
        $ownerPanelTenant = \App\Models\Tenant::query()->find($ownerPanelUser->tenant_id);
    }

    if ($ownerPanelTenant && $ownerPanelUser) {
        $ownerPanelRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $ownerPanelTenant->id, (string) $ownerPanelUser->id);
    }

    $ownerPanelVisible = in_array($ownerPanelRole, ['admin', 'owner', 'superadmin'], true);
    $ownerPanelCompact = (bool) ($compact ?? false);
    $ownerPanelTitle = $title ?? 'Mode Owner';
    $ownerPanelSubtitle = $subtitle ?? 'Tetap di alur POS, lalu buka alat manajemen saat diperlukan.';
    $ownerPanelOriginPath = request()->getPathInfo() ?: '/pos/dashboard';
    $ownerPanelBackofficeHref = static function (string $targetPath) use ($ownerPanelOriginPath): string {
        $separator = str_contains($targetPath, '?') ? '&' : '?';

        return '/portal/switch/backoffice?next='.rawurlencode($targetPath.$separator.'from='.rawurlencode($ownerPanelOriginPath));
    };
    $ownerPanelItems = [
        [
            'href' => $ownerPanelBackofficeHref('/backoffice/products'),
            'icon' => 'inventory_2',
            'label' => 'Kelola Produk',
            'description' => 'Lihat katalog dan item aktif.',
        ],
        [
            'href' => $ownerPanelBackofficeHref('/backoffice/products/new'),
            'icon' => 'add_box',
            'label' => 'Tambah Produk',
            'description' => 'Masukkan barang baru.',
        ],
        [
            'href' => $ownerPanelBackofficeHref('/backoffice/inventory'),
            'icon' => 'warehouse',
            'label' => 'Stok & Inventaris',
            'description' => 'Pantau stok dan mutasi.',
        ],
        [
            'href' => $ownerPanelBackofficeHref('/backoffice/reports/sales'),
            'icon' => 'monitoring',
            'label' => 'Laporan Penjualan',
            'description' => 'Cek penjualan dan tren harian.',
        ],
        [
            'href' => $ownerPanelBackofficeHref('/backoffice/settings/preferences'),
            'icon' => 'settings',
            'label' => 'Pengaturan Toko',
            'description' => 'Atur preferensi operasional.',
        ],
    ];
@endphp

@if ($ownerPanelVisible)
    <section class="rounded-2xl border border-slate-100 bg-white {{ $ownerPanelCompact ? 'p-3' : 'p-4' }} ios-shadow dark:border-slate-800 dark:bg-slate-900">
        <div class="{{ $ownerPanelCompact ? 'mb-2' : 'mb-3' }}">
            <h2 class="text-[11px] font-bold uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500">{{ $ownerPanelTitle }}</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $ownerPanelSubtitle }}</p>
        </div>
        <div class="grid grid-cols-2 {{ $ownerPanelCompact ? 'gap-2' : 'gap-3' }}">
            @foreach ($ownerPanelItems as $ownerPanelItem)
                <a href="{{ $ownerPanelItem['href'] }}" class="rounded-2xl border border-slate-100 bg-slate-50 {{ $ownerPanelCompact ? 'p-2.5' : 'p-3' }} transition-colors active:bg-slate-100 dark:border-slate-800 dark:bg-slate-950/60 dark:active:bg-slate-800">
                    <span class="material-symbols-outlined text-primary {{ $ownerPanelCompact ? 'text-[20px]' : 'text-[22px]' }}">{{ $ownerPanelItem['icon'] }}</span>
                    <p class="{{ $ownerPanelCompact ? 'mt-1.5 text-[13px]' : 'mt-2 text-sm' }} font-bold text-slate-900 dark:text-slate-100">{{ $ownerPanelItem['label'] }}</p>
                    @unless ($ownerPanelCompact)
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ $ownerPanelItem['description'] }}</p>
                    @endunless
                </a>
            @endforeach
        </div>
    </section>
@endif