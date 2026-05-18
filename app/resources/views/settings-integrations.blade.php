@extends('layouts.app')

@php
    $settingsIntegrationsTenant = \App\Support\TenantContext::get();
    $settingsIntegrationsUser = auth()->user();
    $settingsIntegrationsRole = null;

    if (! $settingsIntegrationsTenant && filled($settingsIntegrationsUser?->tenant_id)) {
        $settingsIntegrationsTenant = \App\Models\Tenant::query()->find($settingsIntegrationsUser->tenant_id);
    }

    if ($settingsIntegrationsTenant && $settingsIntegrationsUser) {
        $settingsIntegrationsRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $settingsIntegrationsTenant->id, (string) $settingsIntegrationsUser->id);

        if (! $settingsIntegrationsRole && filled($settingsIntegrationsUser->role ?? null)) {
            $settingsIntegrationsRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $settingsIntegrationsUser->role);
        }
    }

    abort_unless(in_array($settingsIntegrationsRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);
    abort_unless(
        $settingsIntegrationsTenant
        && app(\App\Services\SubscriptionService::class)->canAccessFeature($settingsIntegrationsTenant, 'settings.integrations'),
        403
    );

    $canManageIntegrations = in_array($settingsIntegrationsRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Katalog Integrasi - POS SaaS')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('main_class', 'flex-1 overflow-y-auto lg:pl-72')

@section('header')
    <x-page-header
        title="Katalog Integrasi"
        :breadcrumbs="['Pengaturan']"
        backUrl="/backoffice/dashboard"
        :showMenuButton="true"
    />
@endsection

@section('content')
    <div class="w-full px-4 py-4 lg:px-8 space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Metode Pembayaran (Payment Gateway)</h3>
                <span id="integration-count" class="rounded-full bg-teal-50 px-2 py-1 text-[10px] font-bold uppercase text-primary dark:bg-teal-900/20">2 tersedia</span>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <p class="font-bold">Xendit</p>
                    <p class="mt-1 text-xs text-emerald-600">Terhubung</p>
                    @if ($canManageIntegrations)
                        <button class="mt-3 w-full rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold dark:bg-slate-800">Konfigurasi</button>
                    @endif
                </div>
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <p class="font-bold">Midtrans</p>
                    <p class="mt-1 text-xs text-slate-500">Belum terhubung</p>
                    @if ($canManageIntegrations)
                        <button class="mt-3 w-full rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white">Hubungkan</button>
                    @endif
                </div>
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <p class="font-bold">Doku</p>
                    <p class="mt-1 text-xs text-slate-500">Belum terhubung</p>
                    @if ($canManageIntegrations)
                        <button class="mt-3 w-full rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white">Hubungkan</button>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 id="integration-detail-title" class="text-sm font-bold text-slate-900 dark:text-white">Detail Koneksi: Xendit</h3>
            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div>
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-500">API Secret Key</label>
                    <input type="password" value="xnd_production_********" readonly class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50 text-sm dark:border-slate-700 dark:bg-slate-800" />
                </div>
                <div>
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Webhook URL</label>
                    <input type="text" value="https://api.example.com/webhooks/xendit" readonly class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50 text-sm dark:border-slate-700 dark:bg-slate-800" />
                </div>
            </div>
            @if ($canManageIntegrations)
                <div class="mt-4 flex gap-2">
                    <button class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white">Simpan Perubahan</button>
                    <button class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold dark:border-slate-700">Test Connection</button>
                    <button class="rounded-lg border border-red-200 px-4 py-2 text-xs font-bold text-red-600">Putuskan</button>
                </div>
            @endif
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Ekspedisi & Logistik</h3>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">JNE Express · Belum terhubung</div>
                    <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">SiCepat · Belum terhubung</div>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Sistem Akuntansi</h3>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">Jurnal · Belum terhubung</div>
                    <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">Xero · Belum terhubung</div>
                </div>
            </div>
        </section>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

            const el = {
                count: document.getElementById('integration-count'),
                detailTitle: document.getElementById('integration-detail-title'),
            };

            const load = async () => {
                const [bootstrapRes, entRes] = await Promise.all([
                    window.PosUi.getBootstrap({ headers, ttlMs: 20000 }),
                    fetch('/api/v1/subscription/entitlements', { headers }),
                ]);

                if (bootstrapRes) {
                    const bootstrap = bootstrapRes;
                    const tenantName = bootstrap?.tenant?.name || 'Tenant';
                    el.detailTitle.textContent = `Detail Koneksi: Xendit (${tenantName})`;
                }

                if (entRes.ok) {
                    const entitlements = await entRes.json();
                    const list = Array.isArray(entitlements?.entitlements) ? entitlements.entitlements : [];
                    const integrationFeatures = list.filter((item) => String(item?.key || '').includes('integration') || String(item?.key || '').includes('payment'));
                    const availableCount = integrationFeatures.length > 0 ? integrationFeatures.length : 2;
                    el.count.textContent = `${availableCount} tersedia`;
                }
            };

            load().catch(() => null);
        })();
    </script>
@endsection
