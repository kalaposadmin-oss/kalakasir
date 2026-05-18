@extends('layouts.app')

@php
    $inventoryMethodTenant = \App\Support\TenantContext::get();
    $inventoryMethodUser = auth()->user();
    $inventoryMethodRole = null;

    if (! $inventoryMethodTenant && filled($inventoryMethodUser?->tenant_id)) {
        $inventoryMethodTenant = \App\Models\Tenant::query()->find($inventoryMethodUser->tenant_id);
    }

    if ($inventoryMethodTenant && $inventoryMethodUser) {
        $inventoryMethodRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $inventoryMethodTenant->id, (string) $inventoryMethodUser->id);

        if (! $inventoryMethodRole && filled($inventoryMethodUser->role ?? null)) {
            $inventoryMethodRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $inventoryMethodUser->role);
        }
    }

    abort_unless(in_array($inventoryMethodRole, ['admin', 'owner', 'superadmin'], true), 403);
    $inventoryMethodCanAccessValuation = $inventoryMethodTenant
        ? app(\App\Services\SubscriptionService::class)->canAccessFeature($inventoryMethodTenant, 'settings.inventory_valuation')
        : false;
    $inventoryMethodAdvancedHref = $inventoryMethodCanAccessValuation
        ? '/backoffice/settings/inventory-valuation'
        : '/backoffice/settings/subscription/change';
@endphp

@section('page_title', 'Metode Persediaan Dasar')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('main_class', 'flex-1 overflow-y-auto lg:pl-72')

@push('head')
    <style>
        .inventory-method-card {
            border: 1px solid rgb(226 232 240);
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }

        .inventory-method-card.active {
            border-color: rgb(19 91 236);
            box-shadow: 0 0 0 2px rgba(19, 91, 236, 0.12);
            transform: translateY(-1px);
        }
    </style>
@endpush

@section('header')
    <x-page-header
        title="Metode Persediaan Dasar"
        :breadcrumbs="['Pengaturan']"
        backUrl="/backoffice/dashboard"
        :showMenuButton="true"
    />
@endsection

@section('content')
    <div class="w-full px-4 py-4 lg:px-8">
        <div class="mx-auto max-w-5xl space-y-6">
            <section class="grid gap-6 xl:grid-cols-[1.3fr_0.9fr]">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-bold uppercase tracking-[0.24em] text-slate-400">Pengaturan Dasar</p>
                    <h2 class="mt-3 text-2xl font-bold text-slate-900 dark:text-white">Pilih metode hitung stok untuk operasional harian</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                        Surface ini khusus untuk pilihan metode dasar yang dipakai tenant: FIFO atau Average Cost. Area rebuild, audit, dan laporan valuation tetap dipisahkan ke fitur premium.
                    </p>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label id="card-fifo" class="inventory-method-card cursor-pointer overflow-hidden rounded-2xl bg-white dark:bg-slate-900">
                            <input id="method-fifo" class="sr-only" name="inventory-method" type="radio" value="fifo" />
                            <div class="aspect-[21/9] bg-gradient-to-br from-primary/20 to-primary/40 flex items-center justify-center">
                                <span class="material-symbols-outlined text-primary text-5xl opacity-60">inventory_2</span>
                            </div>
                            <div class="space-y-2 p-4">
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex rounded bg-primary/10 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-primary">Rekomendasi</span>
                                    <span data-check="fifo" class="material-symbols-outlined text-primary">check_circle</span>
                                </div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">FIFO</h3>
                                <p class="text-sm text-slate-600 dark:text-slate-400">Barang yang masuk lebih dulu dianggap keluar lebih dulu. Cocok untuk stok yang sensitif umur atau batch.</p>
                            </div>
                        </label>

                        <label id="card-average" class="inventory-method-card cursor-pointer overflow-hidden rounded-2xl bg-white dark:bg-slate-900">
                            <input id="method-average" class="sr-only" name="inventory-method" type="radio" value="average" />
                            <div class="aspect-[21/9] bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-700 dark:to-slate-800 flex items-center justify-center">
                                <span class="material-symbols-outlined text-slate-500 text-5xl opacity-60">calculate</span>
                            </div>
                            <div class="space-y-2 p-4">
                                <div class="flex items-center justify-between min-h-[20px]">
                                    <span></span>
                                    <span data-check="average" class="material-symbols-outlined text-slate-300 dark:text-slate-700">radio_button_unchecked</span>
                                </div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">Average Cost</h3>
                                <p class="text-sm text-slate-600 dark:text-slate-400">Harga pokok barang dihitung dari biaya rata-rata unit yang tersedia. Cocok untuk operasional sederhana dengan pembelian rutin.</p>
                            </div>
                        </label>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <button id="save-method" class="rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white shadow-lg shadow-primary/20">Simpan Metode</button>
                        <a href="/backoffice/settings/preferences" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 dark:border-slate-700 dark:text-slate-200">Kembali ke Preferensi</a>
                    </div>
                    <p id="method-feedback" class="mt-3 hidden rounded-xl px-4 py-3 text-sm"></p>
                </div>

                <aside class="space-y-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-bold uppercase tracking-[0.24em] text-slate-400">Status Saat Ini</p>
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Metode aktif</p>
                        <p id="current-method" class="mt-1 text-xl font-bold text-slate-900 dark:text-white">-</p>
                        <p id="last-updated" class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Perubahan terakhir: -</p>
                    </div>

                    <div class="rounded-2xl border border-primary/10 bg-primary/5 p-5 shadow-sm">
                        <div class="flex gap-3">
                            <span class="material-symbols-outlined shrink-0 text-primary">info</span>
                            <div class="space-y-2 text-sm text-primary/80">
                                <p>Perubahan metode akan menjalankan perhitungan ulang valuation untuk tenant.</p>
                                <p>Jika butuh audit, rebuild manual, atau laporan perbandingan valuation, gunakan halaman valuation lanjutan.</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-bold uppercase tracking-[0.24em] text-slate-400">Akses Lanjutan</p>
                        <h3 class="mt-3 text-base font-bold text-slate-900 dark:text-white">Valuation premium dipisahkan dari pengaturan dasar</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                            {{ $inventoryMethodCanAccessValuation
                                ? 'Paket dengan entitlement valuation lanjutan tetap memakai halaman terpisah untuk rebuild, audit log, dan export report.'
                                : 'Tenant tanpa entitlement valuation lanjutan perlu upgrade paket lebih dulu sebelum membuka rebuild, audit log, dan export report.' }}
                        </p>
                        <a href="{{ $inventoryMethodAdvancedHref }}" class="mt-4 inline-flex rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 dark:border-slate-700 dark:text-slate-200">{{ $inventoryMethodCanAccessValuation ? 'Buka Valuation Lanjutan' : 'Upgrade untuk Valuation Lanjutan' }}</a>
                    </div>
                </aside>
            </section>
        </div>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const headers = {
                'Content-Type': 'application/json',
                ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            };

            const el = {
                methodFifo: document.getElementById('method-fifo'),
                methodAverage: document.getElementById('method-average'),
                cardFifo: document.getElementById('card-fifo'),
                cardAverage: document.getElementById('card-average'),
                currentMethod: document.getElementById('current-method'),
                lastUpdated: document.getElementById('last-updated'),
                feedback: document.getElementById('method-feedback'),
                saveMethod: document.getElementById('save-method'),
            };

            let selectedMethod = 'average';

            const methodLabel = (method) => method === 'fifo' ? 'FIFO' : 'Average Cost';

            const showFeedback = (message, kind = 'success') => {
                el.feedback.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-700', 'bg-red-50', 'text-red-700');
                el.feedback.classList.add(kind === 'success' ? 'bg-emerald-50' : 'bg-red-50');
                el.feedback.classList.add(kind === 'success' ? 'text-emerald-700' : 'text-red-700');
                el.feedback.textContent = message;
            };

            const syncCards = () => {
                const isFifo = selectedMethod === 'fifo';
                el.methodFifo.checked = isFifo;
                el.methodAverage.checked = !isFifo;
                el.cardFifo.classList.toggle('active', isFifo);
                el.cardAverage.classList.toggle('active', !isFifo);

                document.querySelector('[data-check="fifo"]').textContent = isFifo ? 'check_circle' : 'radio_button_unchecked';
                document.querySelector('[data-check="average"]').textContent = isFifo ? 'radio_button_unchecked' : 'check_circle';
                document.querySelector('[data-check="fifo"]').className = `material-symbols-outlined ${isFifo ? 'text-primary' : 'text-slate-300 dark:text-slate-700'}`;
                document.querySelector('[data-check="average"]').className = `material-symbols-outlined ${isFifo ? 'text-slate-300 dark:text-slate-700' : 'text-primary'}`;
            };

            const formatDateTime = (value) => {
                if (!value) {
                    return '-';
                }

                return new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                }).format(new Date(value));
            };

            const loadState = async () => {
                const bootstrap = await window.PosUi.getBootstrap({ headers, ttlMs: 20000 }).catch(() => null);
                if (!bootstrap) {
                    showFeedback('Gagal memuat metode persediaan saat ini.', 'error');
                    return;
                }
                selectedMethod = bootstrap?.tenant?.inventory_valuation_method || 'average';
                el.currentMethod.textContent = methodLabel(selectedMethod);
                syncCards();

                const settingsResponse = await fetch('/api/v1/settings/tax-promos', { headers });
                if (!settingsResponse.ok) {
                    el.lastUpdated.textContent = 'Perubahan terakhir: data belum tersedia';
                    return;
                }

                const settingsPayload = await settingsResponse.json().catch(() => ({}));
                const lastChange = settingsPayload?.valuation_method_last_change;
                const actor = lastChange?.changed_by_name || 'sistem';
                el.lastUpdated.textContent = `Perubahan terakhir: ${formatDateTime(lastChange?.changed_at)} oleh ${actor}`;
            };

            el.cardFifo.addEventListener('click', () => {
                selectedMethod = 'fifo';
                syncCards();
            });

            el.cardAverage.addEventListener('click', () => {
                selectedMethod = 'average';
                syncCards();
            });

            el.saveMethod.addEventListener('click', async () => {
                el.saveMethod.disabled = true;
                el.saveMethod.textContent = 'Menyimpan...';

                const response = await fetch('/api/v1/settings/inventory-valuation-method', {
                    method: 'PATCH',
                    headers,
                    body: JSON.stringify({
                        method: selectedMethod,
                        confirm_rebuild: true,
                    }),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    showFeedback(payload?.message || 'Gagal menyimpan metode persediaan.', 'error');
                    el.saveMethod.disabled = false;
                    el.saveMethod.textContent = 'Simpan Metode';
                    return;
                }

                el.currentMethod.textContent = methodLabel(payload?.inventory_valuation_method || selectedMethod);
                showFeedback('Metode persediaan berhasil diperbarui. Rebuild valuation dipicu otomatis.');
                await loadState();
                el.saveMethod.disabled = false;
                el.saveMethod.textContent = 'Simpan Metode';
            });

            loadState().catch(() => {
                showFeedback('Gagal memuat pengaturan metode persediaan.', 'error');
            });
        })();
    </script>
@endsection
