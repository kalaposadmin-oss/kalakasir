@php
    $items = [
        ['key' => 'dashboard', 'label' => 'Platform Dashboard', 'icon' => 'grid_view', 'href' => '/platform/dashboard'],
        ['key' => 'health', 'label' => 'Platform Health', 'icon' => 'monitor_heart', 'href' => '/platform/health'],
        ['key' => 'tenants', 'label' => 'Tenants', 'icon' => 'domain', 'href' => '/platform/tenants'],
        ['key' => 'tenant-detail', 'label' => 'Tenant Detail', 'icon' => 'apartment', 'href' => '/platform/tenants/tenant-001'],
        ['key' => 'users', 'label' => 'Global Users', 'icon' => 'group', 'href' => '/platform/users'],
        ['key' => 'invoices', 'label' => 'Billing / Invoices', 'icon' => 'receipt_long', 'href' => '/platform/invoices'],
        ['key' => 'audit-logs', 'label' => 'Audit Logs', 'icon' => 'policy', 'href' => '/platform/audit-logs'],
        ['key' => 'anomalies', 'label' => 'Anomalies', 'icon' => 'warning', 'href' => '/platform/anomalies'],
        ['key' => 'support-search', 'label' => 'Support Search', 'icon' => 'support_agent', 'href' => '/platform/support-search'],
    ];
@endphp

<aside class="fixed left-0 top-0 z-30 hidden h-screen w-72 flex-col border-r border-slate-200 bg-white/95 px-5 py-6 backdrop-blur lg:flex">
    <div class="flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary">
            <span class="material-symbols-outlined text-[22px]">shield</span>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Platform</p>
            <p class="text-sm font-bold text-slate-900">Superadmin Console</p>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold text-slate-500">Aktif</p>
        <p class="mt-1 text-sm font-bold text-slate-900">Platform Admin</p>
        <p class="mt-2 text-xs text-slate-400">Akses penuh seluruh tenant</p>
    </div>

    <nav class="mt-6 flex-1 space-y-1">
        @foreach ($items as $item)
            @php
                $isActive = ($active ?? '') === $item['key'];
            @endphp
            <a href="{{ $item['href'] }}"
                class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold transition {{ $isActive ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:bg-slate-50' }}">
                <span class="material-symbols-outlined text-[20px]">{{ $item['icon'] }}</span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <p class="text-xs font-semibold text-slate-500">Butuh bantuan?</p>
        <p class="mt-1 text-xs text-slate-500">Buka dokumentasi platform atau hubungi ops.</p>
        <button class="mt-3 w-full rounded-xl border border-slate-200 bg-white py-2 text-xs font-semibold text-slate-700">Buka Docs</button>
    </div>
</aside>
