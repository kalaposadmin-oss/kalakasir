@php
    $items = [
        ['key' => 'dashboard', 'label' => 'Platform Dashboard', 'icon' => 'grid_view', 'href' => '/superadmin/dashboard'],
        ['key' => 'health', 'label' => 'Platform Health', 'icon' => 'monitor_heart', 'href' => '/superadmin/health'],
        ['key' => 'tenants', 'label' => 'Tenant', 'icon' => 'domain', 'href' => '/superadmin/tenants'],
        ['key' => 'users', 'label' => 'User Global', 'icon' => 'group', 'href' => '/superadmin/users'],
        ['key' => 'invoices', 'label' => 'Billing', 'icon' => 'receipt_long', 'href' => '/superadmin/billing/invoices'],
        ['key' => 'audit-logs', 'label' => 'Audit Logs', 'icon' => 'policy', 'href' => '/superadmin/audit-logs'],
        ['key' => 'anomalies', 'label' => 'Anomalies', 'icon' => 'crisis_alert', 'href' => '/superadmin/anomalies'],
        ['key' => 'support-search', 'label' => 'Support Search', 'icon' => 'support_agent', 'href' => '/superadmin/support/search'],
        ['key' => 'system-health', 'label' => trans('platform-system-health.common.sidebar_label', [], 'id'), 'icon' => 'monitoring', 'href' => '/superadmin/system-health'],
        ['key' => 'incidents', 'label' => 'Insiden Monitoring', 'icon' => 'warning', 'href' => '/superadmin/system-health/incidents'],
        ['key' => 'tenant-health', 'label' => 'Kesehatan Tenant', 'icon' => 'domain', 'href' => '/superadmin/system-health/tenants'],
    ];
@endphp

<aside data-platform-sidebar class="fixed left-0 top-0 z-30 flex h-screen w-72 -translate-x-full flex-col border-r border-slate-200 bg-white px-5 py-6 shadow-2xl transition-transform duration-200 ">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <span class="material-symbols-outlined text-[22px]">shield</span>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Platform</p>
                <p class="text-sm font-bold text-slate-900">Superadmin Console</p>
            </div>
        </div>

        <button type="button" data-platform-sidebar-close class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-50" aria-label="Tutup menu platform">
            <span class="material-symbols-outlined text-[20px]">close</span>
        </button>
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <p class="text-xs font-semibold text-slate-500">Aktif</p>
        <p class="mt-1 text-sm font-bold text-slate-900">Platform Admin</p>
        <p class="mt-2 text-xs text-slate-400">Akses penuh seluruh tenant</p>
    </div>

    <nav class="mt-6 flex-1 space-y-1 overflow-y-auto pr-1">
        @foreach ($items as $item)
            @php
                $isActive = ($active ?? '') === $item['key'];
            @endphp
            <a href="{{ $item['href'] }}"
                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition {{ $isActive ? 'bg-primary/10 text-primary' : 'text-slate-600 hover:bg-slate-50' }}">
                <span class="material-symbols-outlined text-[20px]">{{ $item['icon'] }}</span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
        <p class="text-xs font-semibold text-slate-500">Tindak lanjut cepat</p>
        <p class="mt-1 text-xs text-slate-500">Pantau anomali dan tenant yang butuh perhatian.</p>
        <a href="/superadmin/system-health/incidents?status=open&severity=critical" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
            <span class="material-symbols-outlined text-[16px]">priority_high</span>
            Insiden kritikal
        </a>
        <a href="/logout" class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
            <span class="material-symbols-outlined text-[16px]">logout</span>
            Keluar
        </a>
    </div>
</aside>
