@extends('layouts.app')

@php
    $helpAboutTenant = \App\Support\TenantContext::get();
    $helpAboutUser = auth()->user();
    $helpAboutRole = null;

    if (! $helpAboutTenant && filled($helpAboutUser?->tenant_id)) {
        $helpAboutTenant = \App\Models\Tenant::query()->find($helpAboutUser->tenant_id);
    }

    if ($helpAboutTenant && $helpAboutUser) {
        $helpAboutRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $helpAboutTenant->id, (string) $helpAboutUser->id);

        if (! $helpAboutRole && filled($helpAboutUser->role ?? null)) {
            $helpAboutRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $helpAboutUser->role);
        }
    }

    abort_unless(in_array($helpAboutRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);
@endphp

@section('page_title', 'Tentang Aplikasi - POS SaaS')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('main_class', 'flex-1 overflow-y-auto lg:pl-72')

@section('header')
    <x-page-header
        title="Tentang Aplikasi"
        :breadcrumbs="['Bantuan']"
        backUrl="/backoffice/help/center"
        :showMenuButton="true"
    />
@endsection

@section('content')
    <div class="w-full px-4 py-4 lg:px-8 space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-2xl font-bold text-slate-900 dark:text-white">Point of Sale SaaS</h3>
            <p class="mt-2 text-sm text-slate-500">Solusi manajemen bisnis retail modern dan terintegrasi.</p>
            <p id="about-plan" class="mt-3 inline-block rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Memuat data langganan...</p>
            <p id="about-tenant" class="mt-2 text-xs text-slate-400">Tenant: -</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Riwayat Pembaruan</h3>
            <div class="mt-4 space-y-3 text-sm">
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <p class="font-bold">v2.4.0 (Terbaru)</p>
                    <p class="mt-1 text-xs text-slate-500">Integrasi pembayaran QRIS dinamis dan optimasi performa loading riwayat transaksi.</p>
                </div>
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <p class="font-bold">v2.3.5</p>
                    <p class="mt-1 text-xs text-slate-500">Perbaikan cetak struk 58mm dan filter metode pembayaran.</p>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <a href="#" class="rounded-2xl border border-slate-200 bg-white p-4 text-center text-sm font-semibold shadow-sm hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800/40">Syarat & Ketentuan</a>
            <a href="#" class="rounded-2xl border border-slate-200 bg-white p-4 text-center text-sm font-semibold shadow-sm hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800/40">Kebijakan Privasi</a>
            <a href="#" class="rounded-2xl border border-slate-200 bg-white p-4 text-center text-sm font-semibold shadow-sm hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800/40">Informasi Lisensi</a>
        </section>

        <section class="border-t border-slate-200 pt-4 text-center text-xs text-slate-500 dark:border-slate-800">
            <p class="font-bold uppercase tracking-wider">Dikembangkan oleh TechJaya Nusantara</p>
            <p class="mt-2">© 2024 Point of Sale SaaS. Seluruh hak cipta dilindungi.</p>
        </section>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

            const el = {
                plan: document.getElementById('about-plan'),
                tenant: document.getElementById('about-tenant'),
            };

            const load = async () => {
                const [bootstrapRes, subRes] = await Promise.all([
                    window.PosUi.getBootstrap({ headers, ttlMs: 20000 }),
                    fetch('/api/v1/subscription/current', { headers }),
                ]);

                if (bootstrapRes) {
                    const bootstrap = bootstrapRes;
                    el.tenant.textContent = `Tenant: ${bootstrap?.tenant?.name || '-'} (${bootstrap?.tenant?.timezone || '-'})`;
                }

                if (subRes.ok) {
                    const sub = await subRes.json();
                    el.plan.textContent = `Plan ${sub?.plan || '-'} (${sub?.plan_label || '-'}) · Status ${sub?.status || '-'}`;
                    return;
                }

                el.plan.textContent = 'Versi v2.4.0 (Stable)';
            };

            load().catch(() => {
                el.plan.textContent = 'Versi v2.4.0 (Stable)';
            });
        })();
    </script>
@endsection
