@php
    $user = auth()->user();
    $userName = $user?->name ?: 'Superadmin';
    $userEmail = $user?->email ?: 'Akses platform';
    $userInitial = strtoupper(substr($userName, 0, 1));
@endphp

<header class="sticky top-0 z-20 h-16 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="flex h-full items-center justify-between gap-3 px-4 sm:px-6">
        <div class="flex items-center gap-3">
            <button type="button" data-platform-sidebar-toggle class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 lg:hidden" aria-label="Buka menu platform">
                <span class="material-symbols-outlined text-[22px]">menu</span>
            </button>
            <div class="hidden h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary sm:flex">
                <span class="material-symbols-outlined text-[20px]">admin_panel_settings</span>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Platform Admin</p>
                <p class="text-sm font-semibold text-slate-900">Kontrol Global</p>
            </div>
        </div>
        <div class="flex min-w-0 items-center gap-2 sm:gap-3">
            <a href="/superadmin/system-health/incidents?status=open" class="hidden rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 lg:inline-flex">Insiden terbuka</a>
            <a href="/superadmin/system-health" class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50" aria-label="Buka system health">
                <span class="material-symbols-outlined text-[20px]">notifications</span>
            </a>
            <div class="flex min-w-0 items-center gap-2 rounded-full border border-slate-200 bg-white px-2 py-1.5 sm:px-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/15 text-xs font-bold text-primary">{{ $userInitial }}</span>
                <div class="hidden min-w-0 text-left sm:block">
                    <p class="max-w-32 truncate text-xs font-semibold text-slate-700">{{ $userName }}</p>
                    <p class="max-w-32 truncate text-[10px] text-slate-400">{{ $userEmail }}</p>
                </div>
            </div>
        </div>
    </div>
</header>
