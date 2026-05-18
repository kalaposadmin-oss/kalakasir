@php
    $ownerPanelTenant = $posDashboardTenant ?? \App\Support\TenantContext::get();
    $ownerPanelUser = auth()->user();
    $ownerPanelRole = $posDashboardRole ?? null;

    if (! $ownerPanelTenant && filled($ownerPanelUser?->tenant_id)) {
        $ownerPanelTenant = \App\Models\Tenant::query()->find($ownerPanelUser->tenant_id);
    }

    if (! $ownerPanelRole && $ownerPanelTenant && $ownerPanelUser) {
        $ownerPanelRoleResolver = app(\App\Services\UserRoleResolverService::class);
        $ownerPanelRole = $ownerPanelRoleResolver->resolveNormalizedRole(
            (string) $ownerPanelTenant->id,
            (string) $ownerPanelUser->id
        );

        if (! $ownerPanelRole && filled($ownerPanelUser->role ?? null)) {
            $ownerPanelRole = $ownerPanelRoleResolver->normalize((string) $ownerPanelUser->role);
        }
    }

    $ownerPanelOriginPath = request()->getPathInfo() ?: pos_default_url();
    $ownerPanelBackofficeHref = static function (string $targetPath) use ($ownerPanelOriginPath): string {
        $separator = str_contains($targetPath, '?') ? '&' : '?';

        return '/portal/switch/backoffice?next='.rawurlencode($targetPath.$separator.'from='.rawurlencode($ownerPanelOriginPath));
    };
    $ownerPanelSubscription = app(\App\Services\SubscriptionService::class);
    $ownerPanelCanFeature = static function (string $featureKey) use ($ownerPanelTenant, $ownerPanelSubscription): bool {
        return $ownerPanelTenant
            && $featureKey !== ''
            && $ownerPanelSubscription->canAccessFeature($ownerPanelTenant, $featureKey);
    };
    $showOwnerPanel = in_array($ownerPanelRole, ['admin', 'owner', 'superadmin'], true);
    $ownerPanelItems = array_values(array_filter([
        ($ownerPanelCanFeature('product.list') ? ['href' => '/pos/products', 'icon' => 'inventory_2', 'label' => 'Produk', 'description' => 'Kelola katalog'] : null),
        ($ownerPanelCanFeature('inventory.stock') ? ['href' => $ownerPanelBackofficeHref('/backoffice/inventory'), 'icon' => 'warehouse', 'label' => 'Inventaris', 'description' => 'Pantau stok'] : null),
        ($ownerPanelCanFeature('reports.summary') ? ['href' => $ownerPanelBackofficeHref('/backoffice/reports/sales'), 'icon' => 'bar_chart', 'label' => 'Penjualan', 'description' => 'Lihat laporan'] : null),
    ]));
@endphp

@if ($showOwnerPanel && count($ownerPanelItems) > 0)
    <section class="rounded-2xl border border-primary/10 bg-white p-4 ios-shadow dark:border-primary/20 dark:bg-slate-900">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-primary">Mode Owner</p>
                <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Kontrol operasional</h2>
            </div>
            <span class="flex size-9 items-center justify-center rounded-full bg-primary/10 text-primary">
                <span class="material-symbols-outlined text-[20px]">admin_panel_settings</span>
            </span>
        </div>
        <div class="grid grid-cols-3 gap-2">
            @foreach ($ownerPanelItems as $item)
                <a href="{{ $item['href'] }}" class="flex min-h-24 flex-col justify-between rounded-xl border border-slate-100 bg-slate-50 p-3 text-slate-700 transition active:scale-[0.98] dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200" @if(in_array(parse_url($item['href'], PHP_URL_PATH), ['/pos/products', '/backoffice/inventory', '/backoffice/reports/sales'], true)) data-prefetch="priority" @endif>
                    <span class="material-symbols-outlined text-[22px] text-primary">{{ $item['icon'] }}</span>
                    <span>
                        <span class="block text-xs font-bold leading-tight">{{ $item['label'] }}</span>
                        <span class="mt-1 block text-[10px] leading-snug text-slate-500 dark:text-slate-400">{{ $item['description'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif
