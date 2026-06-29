@php
    $isPlatformNav = ($portal ?? '') === 'platform';
    $isBackofficeNav = ($portal ?? '') === 'backoffice';
    $navPath = '/'.ltrim(request()->path(), '/');

    if ($isPlatformNav) {
        $navItems = [
            ['key' => 'home', 'label' => 'Beranda', 'icon' => 'dashboard', 'href' => '/superadmin/dashboard'],
            ['key' => 'tenants', 'label' => 'Tenant', 'icon' => 'domain', 'href' => '/superadmin/tenants'],
            ['key' => 'health', 'label' => 'Sistem', 'icon' => 'monitor_heart', 'href' => '/superadmin/system-health'],
            ['key' => 'billing', 'label' => 'Billing', 'icon' => 'payments', 'href' => '/superadmin/billing'],
            ['key' => 'audit', 'label' => 'Audit', 'icon' => 'fact_check', 'href' => '/superadmin/audit-logs'],
        ];

        $activeNavKey = match (true) {
            str_starts_with($navPath, '/superadmin/dashboard') => 'home',
            str_starts_with($navPath, '/superadmin/tenants') => 'tenants',
            str_starts_with($navPath, '/superadmin/system-health') => 'health',
            str_starts_with($navPath, '/superadmin/billing') => 'billing',
            default => 'audit',
        };
    } elseif ($isBackofficeNav) {
        $navItems = [
            ['key' => 'home', 'label' => 'Beranda', 'icon' => 'dashboard', 'href' => '/backoffice/dashboard'],
            ['key' => 'sales', 'label' => 'Penjualan', 'icon' => 'receipt_long', 'href' => '/menu/sales'],
            ['key' => 'products', 'label' => 'Produk', 'icon' => 'inventory_2', 'href' => '/menu/management'],
            ['key' => 'reports', 'label' => 'Laporan', 'icon' => 'analytics', 'href' => '/menu/reports'],
            ['key' => 'menu', 'label' => 'Menu', 'icon' => 'apps', 'href' => '/menu'],
        ];

        $activeNavKey = match (true) {
            str_starts_with($navPath, '/backoffice/dashboard') => 'home',
            str_starts_with($navPath, '/menu/sales'),
            str_starts_with($navPath, '/backoffice/online-orders'),
            str_starts_with($navPath, '/backoffice/reports/sales'),
            str_starts_with($navPath, '/backoffice/reports/receivables') => 'sales',
            str_starts_with($navPath, '/menu/management'),
            str_starts_with($navPath, '/backoffice/products'),
            str_starts_with($navPath, '/backoffice/categories'),
            str_starts_with($navPath, '/backoffice/inventory'),
            str_starts_with($navPath, '/backoffice/materials'),
            str_starts_with($navPath, '/backoffice/suppliers'),
            str_starts_with($navPath, '/backoffice/purchases'),
            str_starts_with($navPath, '/backoffice/receiving') => 'products',
            str_starts_with($navPath, '/menu/reports'),
            str_starts_with($navPath, '/backoffice/reports') => 'reports',
            default => 'menu',
        };
    } else {
        $navItems = [
            ['key' => 'home', 'label' => 'Beranda', 'icon' => 'home', 'href' => '/pos/dashboard'],
            ['key' => 'cashier', 'label' => 'Kasir', 'icon' => 'point_of_sale', 'href' => '/pos/checkout'],
            ['key' => 'sales', 'label' => 'Transaksi', 'icon' => 'receipt_long', 'href' => '/pos/history'],
            ['key' => 'shift', 'label' => 'Shift', 'icon' => 'schedule', 'href' => '/pos/shifts/summary'],
            ['key' => 'menu', 'label' => 'Menu', 'icon' => 'apps', 'href' => '/menu'],
        ];

        $activeNavKey = match (true) {
            str_starts_with($navPath, '/pos/dashboard') => 'home',
            str_starts_with($navPath, '/pos/checkout') => 'cashier',
            str_starts_with($navPath, '/pos/history'),
            str_starts_with($navPath, '/pos/sales'),
            str_starts_with($navPath, '/pos/receivables') => 'sales',
            str_starts_with($navPath, '/pos/shifts') => 'shift',
            default => 'menu',
        };
    }
@endphp

<nav class="app-global-bottom-nav" aria-label="{{ $isPlatformNav ? 'Navigasi Platform' : ($isBackofficeNav ? 'Navigasi Backoffice' : 'Navigasi Kasir') }}">
    @foreach($navItems as $navItem)
        <a
            class="app-global-bottom-item {{ $activeNavKey === $navItem['key'] ? 'is-active' : '' }}"
            href="{{ $navItem['href'] }}"
            @if($activeNavKey === $navItem['key']) aria-current="page" @endif
        >
            <span class="material-symbols-outlined">{{ $navItem['icon'] }}</span>
            <span class="truncate">{{ $navItem['label'] }}</span>
        </a>
    @endforeach
</nav>
