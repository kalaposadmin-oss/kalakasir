@extends('platform.layout')

@php
    $pageTitle = 'User Detail';
    $active = 'users';
    $userDetail = $userDetail ?? ['user' => [], 'tenants' => []];
    $user = $userDetail['user'] ?? [];
    $tenants = $userDetail['tenants'] ?? [];
    $userRole = $userRole ?? null;
    $userStatus = $userStatus ?? 'active';
    $lastLoginAt = $lastLoginAt ?? null;
    $lastLoginLabel = 'never';
    if ($lastLoginAt) {
        $lastLoginTime = \Carbon\CarbonImmutable::parse($lastLoginAt, 'UTC');
        $lastLoginLabel = $lastLoginTime->diffForHumans().' - '.$lastLoginTime->format('d M Y, H:i');
    }
    $statusClass = $userStatus === 'active'
        ? 'bg-emerald-100 text-emerald-700'
        : 'bg-slate-100 text-slate-600';
    $roleKey = strtolower((string) $userRole);
    $roleBadgeClass = match ($roleKey) {
        'owner' => 'bg-amber-100 text-amber-800',
        'admin' => 'bg-slate-900 text-white',
        'supervisor', 'manager' => 'bg-blue-100 text-blue-700',
        'cashier' => 'bg-emerald-100 text-emerald-700',
        default => 'bg-slate-100 text-slate-600',
    };
@endphp

@section('platform_content')
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ $user['name'] ?? 'User' }}</h1>
            <p class="mt-1 text-sm text-slate-500">Detail akun user dan aktivitas terakhir.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase {{ $roleBadgeClass }}">
                {{ $userRole ?? 'unknown' }}
            </span>
            <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $statusClass }}">
                {{ ucfirst($userStatus) }}
            </span>
        </div>
    </div>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Email</p>
            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $user['email'] ?? '-' }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Last Login</p>
            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $lastLoginLabel }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tenant</p>
            <div class="mt-2 space-y-1 text-sm text-slate-900">
                @forelse ($tenants as $tenant)
                    <a class="font-semibold text-primary" href="/superadmin/tenants/{{ $tenant['tenant_id'] ?? '' }}">
                        {{ $tenant['name'] ?? '-' }}
                    </a>
                @empty
                    <span>-</span>
                @endforelse
            </div>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">User ID</p>
            <p class="mt-2 text-xs text-slate-600 break-all">{{ $user['user_id'] ?? '-' }}</p>
        </article>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Audit Log</p>
                <h3 class="text-sm font-bold text-slate-900">Aktivitas Terakhir</h3>
            </div>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($auditLogs as $log)
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $log['event_type'] ?? '-' }}</p>
                        <p class="text-xs text-slate-400">{{ $log['created_at'] ?? '-' }}</p>
                    </div>
                    <span class="text-xs text-slate-500">{{ $log['reference_type'] ?? '-' }} - {{ $log['reference_id'] ?? '-' }}</span>
                </div>
            @empty
                <div class="px-6 py-4 text-sm text-slate-500">Belum ada log.</div>
            @endforelse
        </div>
    </section>
@endsection
