@extends('layouts.app')

@php
    $settingsPreferencesTenant = \App\Support\TenantContext::get();
    $settingsPreferencesUser = auth()->user();
    $settingsPreferencesRole = null;

    if (! $settingsPreferencesTenant && filled($settingsPreferencesUser?->tenant_id)) {
        $settingsPreferencesTenant = \App\Models\Tenant::query()->find($settingsPreferencesUser->tenant_id);
    }

    if ($settingsPreferencesTenant && $settingsPreferencesUser) {
        $settingsPreferencesRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $settingsPreferencesTenant->id, (string) $settingsPreferencesUser->id);

        if (! $settingsPreferencesRole && filled($settingsPreferencesUser->role ?? null)) {
            $settingsPreferencesRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $settingsPreferencesUser->role);
        }
    }

    abort_unless(in_array($settingsPreferencesRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManagePreferences = in_array($settingsPreferencesRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Preferensi Sistem - POS SaaS')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('main_class', 'flex-1 overflow-y-auto lg:pl-72')

@section('header')
    <x-page-header
        title="Preferensi Sistem & Cetak"
        :breadcrumbs="['Pengaturan']"
        backUrl="/backoffice/dashboard"
        :showMenuButton="true"
    />
@endsection

@section('content')
    <div class="w-full px-4 py-4 lg:px-8">
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Format & Regional</h3>
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Zona Waktu</label>
                            <select id="pref-timezone" class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50 text-sm dark:border-slate-700 dark:bg-slate-800">
                                <option>(GMT+07:00) Jakarta</option>
                                <option>(GMT+08:00) Makassar</option>
                                <option>(GMT+09:00) Jayapura</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Format Tanggal</label>
                            <select class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50 text-sm dark:border-slate-700 dark:bg-slate-800">
                                <option>DD/MM/YYYY</option>
                                <option>MM/DD/YYYY</option>
                                <option>YYYY-MM-DD</option>
                            </select>
                            <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-slate-800">Preview: <span id="pref-date-preview" class="font-semibold text-primary">31/12/2024</span></p>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Konfigurasi Struk</h3>
                    <div class="mt-4 space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Lebar Kertas</label>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <button class="rounded-xl border border-primary bg-teal-50 px-3 py-2 text-xs font-bold text-primary dark:bg-teal-900/20" @if (! $canManagePreferences) disabled @endif>80mm</button>
                                    <button class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold dark:border-slate-700" @if (! $canManagePreferences) disabled @endif>58mm</button>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Logo Struk</label>
                                <div class="mt-2 flex items-center gap-2">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-dashed border-slate-300 text-slate-400 dark:border-slate-700">L</div>
                                    @if ($canManagePreferences)
                                        <button class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold dark:border-slate-700">Ganti Logo</button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <textarea rows="3" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm dark:border-slate-700 dark:bg-slate-800" placeholder="Header nota"></textarea>
                        <textarea rows="2" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm dark:border-slate-700 dark:bg-slate-800" placeholder="Footer nota"></textarea>
                    </div>
                </section>

                @if ($canManagePreferences)
                    <div class="flex gap-2">
                        <button class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold dark:border-slate-700">Kembalikan Default</button>
                        <button class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white">Simpan Perubahan</button>
                    </div>
                @endif
            </div>

            <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Preview Struk</h4>
                <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-4 text-xs text-slate-700">
                    <p id="pref-store-name" class="text-center text-sm font-bold">WARUNG JAYA</p>
                    <p class="mt-1 text-center text-[11px]">Jl. Sudirman No. 123</p>
                    <p id="pref-datetime" class="text-center text-[11px]">31/12/2024 14:20</p>
                    <hr class="my-3 border-dashed" />
                    <p class="flex justify-between"><span>Nasi Goreng</span><span>25.000</span></p>
                    <p class="flex justify-between"><span>Es Teh</span><span>10.000</span></p>
                    <hr class="my-3 border-dashed" />
                    <p class="flex justify-between font-bold"><span>Total</span><span>35.000</span></p>
                    <p class="mt-3 text-center text-[10px]">Terima kasih atas kunjungan Anda</p>
                </div>
            </aside>
        </div>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
            const el = {
                timezone: document.getElementById('pref-timezone'),
                datePreview: document.getElementById('pref-date-preview'),
                dateTime: document.getElementById('pref-datetime'),
                storeName: document.getElementById('pref-store-name'),
            };

            const formatNow = (timeZone) => {
                try {
                    return new Intl.DateTimeFormat('id-ID', {
                        dateStyle: 'short',
                        timeStyle: 'short',
                        timeZone,
                    }).format(new Date());
                } catch {
                    return new Intl.DateTimeFormat('id-ID', {
                        dateStyle: 'short',
                        timeStyle: 'short',
                    }).format(new Date());
                }
            };

            const load = async () => {
                const response = await fetch('/api/v1/bootstrap', { headers });
                if (!response.ok) {
                    return;
                }
                const payload = await response.json().catch(() => ({}));
                const bootstrap = window.PosUi.unwrapApiPayload(payload) || {};
                const tz = bootstrap?.tenant?.timezone || 'Asia/Jakarta';
                const storeName = bootstrap?.tenant?.name || 'WARUNG JAYA';

                if (el.storeName) {
                    el.storeName.textContent = String(storeName).toUpperCase();
                }
                if (el.dateTime) {
                    el.dateTime.textContent = formatNow(tz);
                }
                if (el.timezone) {
                    const match = Array.from(el.timezone.options).find((option) => option.textContent.toLowerCase().includes('jakarta') && tz.includes('Jakarta'));
                    if (match) {
                        el.timezone.value = match.value;
                    }
                }
                if (el.datePreview) {
                    el.datePreview.textContent = new Intl.DateTimeFormat('id-ID').format(new Date());
                }
            };

            load().catch(() => null);
        })();
    </script>
@endsection
