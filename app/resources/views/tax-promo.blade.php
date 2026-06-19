@extends('layouts.app')

@php
    $taxPromoTenant = \App\Support\TenantContext::get();
    $taxPromoUser = auth()->user();
    $taxPromoRole = null;

    if (! $taxPromoTenant && filled($taxPromoUser?->tenant_id)) {
        $taxPromoTenant = \App\Models\Tenant::query()->find($taxPromoUser->tenant_id);
    }

    if ($taxPromoTenant && $taxPromoUser) {
        $taxPromoRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $taxPromoTenant->id, (string) $taxPromoUser->id);

        if (! $taxPromoRole && filled($taxPromoUser->role ?? null)) {
            $taxPromoRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $taxPromoUser->role);
        }
    }

    abort_unless(in_array($taxPromoRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManageTaxPromo = in_array($taxPromoRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Pengaturan Pajak dan Promo')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display min-h-screen')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0 app-bottom-safe-space')

@push('head')
    <style>
        .toggle-checkbox:checked {
            right: 0;
            border-color: rgb(var(--primary-500));
        }
        .toggle-checkbox:checked + .toggle-label {
            background-color: rgb(var(--primary-500));
        }
    </style>
@endpush

@section('header')
    <x-page-header title="Pajak dan Promo" backUrl="/backoffice/dashboard">
        <x-slot name="actions">
            <button class="flex items-center justify-center size-10 rounded-full bg-slate-50 dark:bg-slate-800" type="button">
                <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">help_outline</span>
            </button>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
    <div class="flex-1 overflow-y-auto pb-32 app-bottom-safe-space">
        <section class="p-4">
            <div class="space-y-3" id="tax-list"></div>
            @if ($canManageTaxPromo)
                <button id="tax-add-button" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white shadow-sm shadow-primary/20" type="button">
                    <span class="material-symbols-outlined text-sm">add_task</span>
                    Tambah Pajak Baru
                </button>
            @endif
        </section>
        <div class="h-2 bg-slate-50 dark:bg-slate-900"></div>
        <section class="p-4">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Pembulatan Transaksi</h2>
                @if ($canManageTaxPromo)
                    <button id="rounding-save" class="bg-primary/10 text-primary px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1" type="button">
                        <span class="material-symbols-outlined text-sm">save</span>
                        Simpan
                    </button>
                @endif
            </div>
            <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="rounded-xl bg-slate-50 px-3 py-3 text-xs leading-5 text-slate-600 dark:bg-slate-800 dark:text-slate-300" id="rounding-status">Memuat pengaturan pembulatan...</div>
                <label class="grid gap-2">
                    <span class="text-xs font-bold uppercase tracking-widest text-slate-500">Mode</span>
                    <select id="rounding-mode" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-semibold dark:border-slate-700 dark:bg-slate-800">
                        <option value="none">Tidak dibulatkan</option>
                        <option value="nearest">Ke rupiah terdekat</option>
                        <option value="up">Naik ke rupiah</option>
                        <option value="down">Turun ke rupiah</option>
                        <option value="multiple">Kelipatan tertentu</option>
                    </select>
                </label>
                <label class="grid gap-2" id="rounding-increment-wrap">
                    <span class="text-xs font-bold uppercase tracking-widest text-slate-500">Nominal Pembulatan</span>
                    <input id="rounding-increment" type="number" min="1" step="1" inputmode="numeric" placeholder="100" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-semibold dark:border-slate-700 dark:bg-slate-800" />
                </label>
                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-[11px] leading-5 text-slate-500 dark:border-slate-700 dark:bg-slate-800/60">
                    <p class="font-bold uppercase tracking-widest text-slate-500">Panduan cepat</p>
                    <p class="mt-1">Untuk membulatkan Rp 36.667 menjadi Rp 36.700, pilih <strong>Kelipatan tertentu</strong> lalu isi nominal <strong>100</strong>.</p>
                </div>
                <p id="rounding-save-feedback" class="min-h-4 text-[11px] font-semibold text-slate-500"></p>
            </div>
        </section>
        <div class="h-2 bg-slate-50 dark:bg-slate-900"></div>
        <section class="p-4">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Manajemen Promo</h2>
            </div>
            <div class="space-y-4" id="promo-list"></div>
        </section>

        <div id="tax-sheet-overlay" class="hidden fixed inset-0 z-50 bg-slate-950/50">
            <div id="tax-sheet-backdrop" class="absolute inset-0"></div>
            <div class="absolute inset-x-0 bottom-0 mx-auto max-w-[430px] rounded-t-3xl bg-white p-4 shadow-2xl dark:bg-slate-900">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Tambah Pajak Baru</h3>
                        <p class="text-xs text-slate-500">Isi nama, deskripsi, dan tarif pajak.</p>
                    </div>
                    <button id="tax-sheet-close" type="button" class="size-9 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        <span class="material-symbols-outlined text-sm">close</span>
                    </button>
                </div>
                <div id="tax-sheet-feedback" class="hidden mb-3 rounded-xl border px-3 py-2 text-xs font-medium"></div>
                <div class="space-y-3">
                    <label class="grid gap-2">
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-500">Nama Pajak</span>
                        <input id="tax-name" type="text" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-semibold dark:border-slate-700 dark:bg-slate-800" placeholder="PPN" />
                    </label>
                    <label class="grid gap-2">
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-500">Deskripsi</span>
                        <input id="tax-description" type="text" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-semibold dark:border-slate-700 dark:bg-slate-800" placeholder="Pajak penjualan" />
                    </label>
                    <label class="grid gap-2">
                        <span class="text-xs font-bold uppercase tracking-widest text-slate-500">Tarif (%)</span>
                        <input id="tax-rate" type="number" min="0" step="0.01" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm font-semibold dark:border-slate-700 dark:bg-slate-800" placeholder="11" />
                    </label>
                </div>
                <div class="mt-4 flex gap-2">
                    <button id="tax-sheet-cancel" type="button" class="flex-1 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300">Batal</button>
                    <button id="tax-sheet-save" type="button" class="flex-1 rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white">Simpan</button>
                </div>
            </div>
        </div>

        <div class="h-8"></div>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content || '';
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

            if (!window.fetch) {
                return;
            }

            const el = {
                taxes: document.getElementById('tax-list'),
                promos: document.getElementById('promo-list'),
                roundingMode: document.getElementById('rounding-mode'),
                roundingIncrement: document.getElementById('rounding-increment'),
                roundingIncrementWrap: document.getElementById('rounding-increment-wrap'),
                roundingSave: document.getElementById('rounding-save'),
                roundingStatus: document.getElementById('rounding-status'),
                roundingSaveFeedback: document.getElementById('rounding-save-feedback'),
                taxAddButton: document.getElementById('tax-add-button'),
                taxSheetOverlay: document.getElementById('tax-sheet-overlay'),
                taxSheetBackdrop: document.getElementById('tax-sheet-backdrop'),
                taxSheetClose: document.getElementById('tax-sheet-close'),
                taxSheetCancel: document.getElementById('tax-sheet-cancel'),
                taxSheetSave: document.getElementById('tax-sheet-save'),
                taxSheetFeedback: document.getElementById('tax-sheet-feedback'),
                taxName: document.getElementById('tax-name'),
                taxDescription: document.getElementById('tax-description'),
                taxRate: document.getElementById('tax-rate'),
            };

            const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(value || 0);

            const fetchWithFallback = async (url, options = {}, timeout = 15000) => {
                const makeOptions = () => ({
                    ...options,
                    credentials: 'same-origin',
                    headers: {
                        ...(options.headers || {}),
                        ...headers,
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const controller = new AbortController();
                const timer = window.setTimeout(() => controller.abort(), timeout);
                try {
                    return await fetch(url, {
                        ...makeOptions(),
                        signal: controller.signal,
                    });
                } finally {
                    window.clearTimeout(timer);
                }
            };

            const refreshCsrfCookie = async () => {
                await fetch('/sanctum/csrf-cookie', {
                    credentials: 'same-origin',
                    headers,
                }).catch(() => null);
            };

            const fetchWriteWithFreshCsrf = async (url, options = {}, timeout = 15000) => {
                const makeOptions = () => ({
                    ...options,
                    credentials: 'same-origin',
                    headers: {
                        ...(options.headers || {}),
                        ...headers,
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                await refreshCsrfCookie();
                let response = await fetchWithFallback(url, makeOptions(), timeout);
                if (response.status === 419) {
                    await refreshCsrfCookie();
                    response = await fetchWithFallback(url, makeOptions(), timeout);
                }

                return response;
            };

            const updateRoundingUi = () => {
                if (!el.roundingMode || !el.roundingIncrementWrap || !el.roundingStatus) {
                    return;
                }

                const mode = el.roundingMode.value || 'none';
                const increment = String(el.roundingIncrement?.value || '').trim();
                const isMultiple = mode === 'multiple';
                el.roundingIncrementWrap.classList.toggle('opacity-60', !isMultiple);
                if (el.roundingIncrement) {
                    el.roundingIncrement.disabled = !isMultiple;
                }

                const examples = {
                    none: {
                        title: 'Tidak dibulatkan',
                        body: 'Total transaksi dipakai apa adanya tanpa perubahan.',
                        example: 'Contoh: Rp 36.667 tetap Rp 36.667.',
                    },
                    nearest: {
                        title: 'Ke rupiah terdekat',
                        body: 'Dipakai kalau total masih memiliki angka sen/desimal dan ingin dibulatkan ke rupiah paling dekat.',
                        example: 'Contoh: Rp 36.666,40 menjadi Rp 36.666; Rp 36.666,60 menjadi Rp 36.667.',
                    },
                    up: {
                        title: 'Naik ke rupiah',
                        body: 'Dipakai kalau total masih memiliki angka sen/desimal dan selalu ingin dinaikkan ke rupiah bulat berikutnya.',
                        example: 'Contoh: Rp 36.666,10 menjadi Rp 36.667. Rp 36.667 tetap Rp 36.667.',
                    },
                    down: {
                        title: 'Turun ke rupiah',
                        body: 'Dipakai kalau total masih memiliki angka sen/desimal dan selalu ingin diturunkan ke rupiah bulat sebelumnya.',
                        example: 'Contoh: Rp 36.666,90 menjadi Rp 36.666. Rp 36.667 tetap Rp 36.667.',
                    },
                    multiple: {
                        title: 'Kelipatan tertentu',
                        body: `Total transaksi dinaikkan ke kelipatan nominal yang diisi (${increment || 'misalnya 100, 500, atau 1000'}).`,
                        example: 'Contoh nominal 100: Rp 36.667 menjadi Rp 36.700. Contoh nominal 500: Rp 36.667 menjadi Rp 37.000.',
                    },
                };
                const activeExample = examples[mode] || examples.none;

                el.roundingStatus.innerHTML = `
                    <p class="font-bold text-slate-700 dark:text-slate-100">${activeExample.title}</p>
                    <p class="mt-1">${activeExample.body}</p>
                    <p class="mt-1 font-semibold text-primary">${activeExample.example}</p>
                `;
            };

            const applyRoundingSettings = (settings) => {
                if (!el.roundingMode || !el.roundingIncrement) {
                    return;
                }
                el.roundingMode.value = settings?.mode || 'none';
                el.roundingIncrement.value = settings?.increment || '100';
                updateRoundingUi();
            };

            const renderTaxes = (taxes) => {
                el.taxes.innerHTML = '';
                if (!taxes.length) {
                    el.taxes.innerHTML = '<div class="text-sm text-slate-400">Belum ada pajak.</div>';
                    return;
                }

                taxes.forEach((tax) => {
                    const row = document.createElement('div');
                    row.className = `bg-slate-50 dark:bg-slate-900/50 p-4 rounded-2xl border border-slate-100 dark:border-slate-800 flex items-center justify-between${tax.is_active ? '' : ' opacity-70'}`;
                    row.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="size-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">percent</span>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900 dark:text-slate-100 text-sm">${tax.name} (${tax.rate}%)</p>
                                <p class="text-[11px] text-slate-500">${tax.description || 'Pajak'}</p>
                            </div>
                        </div>
                        @if ($canManageTaxPromo)
                            <div class="relative inline-block w-12 align-middle select-none transition duration-200 ease-in">
                                <input class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-2 border-slate-200 appearance-none cursor-pointer right-6 checked:right-0 checked:border-primary transition-all duration-300" id="tax-${tax.id}" type="checkbox" ${tax.is_active ? 'checked' : ''} data-id="${tax.id}" />
                                <label class="toggle-label block overflow-hidden h-6 rounded-full bg-slate-200 dark:bg-slate-700 cursor-pointer transition-colors duration-300" for="tax-${tax.id}"></label>
                            </div>
                        @else
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${tax.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'} uppercase">${tax.is_active ? 'Aktif' : 'Nonaktif'}</span>
                        @endif
                    `;
                    el.taxes.appendChild(row);
                });
            };

            const renderPromos = (promos) => {
                el.promos.innerHTML = '';
                if (!promos.length) {
                    el.promos.innerHTML = '<div class="text-sm text-slate-400">Belum ada promo.</div>';
                    return;
                }

                promos.forEach((promo) => {
                    const isExpired = promo.status === 'expired';
                    const borderClass = promo.type === 'percent' ? 'bg-emerald-500' : 'bg-orange-500';
                    const statusLabel = isExpired ? 'Kedaluwarsa' : 'Aktif';
                    const statusClass = isExpired ? 'bg-slate-200 text-slate-600' : 'bg-emerald-100 text-emerald-700';
                    const valueBadge = promo.type === 'percent'
                        ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400'
                        : 'bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400';
                    const wrapClass = isExpired ? 'bg-slate-50 dark:bg-slate-900/30 grayscale opacity-60' : 'bg-white dark:bg-slate-900';

                    const row = document.createElement('div');
                    row.className = `relative overflow-hidden ${wrapClass} rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm`;
                    row.innerHTML = `
                        <div class="absolute top-0 left-0 w-1.5 h-full ${isExpired ? 'bg-slate-400' : borderClass}"></div>
                        <div class="p-4 pl-6">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-bold text-slate-900 dark:text-slate-100">${promo.name}</h3>
                                    <p class="text-xs text-slate-500">Berlaku s/d ${promo.ends_at || '-'}</p>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${statusClass} uppercase">${statusLabel}</span>
                            </div>
                            <div class="flex items-center gap-4 mt-3 pt-3 border-t border-slate-50 dark:border-slate-800">
                                <div class="${valueBadge} px-3 py-1.5 rounded-lg">
                                    <span class="text-xs font-bold">${promo.type === 'percent' ? `Diskon ${promo.value}%` : `Potongan ${formatCurrency(promo.value)}`}</span>
                                </div>
                                <div class="flex-1">
                                    <p class="text-[10px] text-slate-400 uppercase font-bold tracking-tight">${promo.code ? 'Kode Promo' : 'Kategori'}</p>
                                    <p class="text-xs font-bold">${promo.code || promo.category || '-'}</p>
                                </div>
                                @if ($canManageTaxPromo)
                                    <button class="material-symbols-outlined text-slate-400" type="button">more_vert</button>
                                @endif
                            </div>
                        </div>
                    `;
                    el.promos.appendChild(row);
                });
            };

            @if ($canManageTaxPromo)
            const openTaxSheet = () => {
                el.taxSheetFeedback?.classList.add('hidden');
                if (el.taxName) el.taxName.value = '';
                if (el.taxDescription) el.taxDescription.value = '';
                if (el.taxRate) el.taxRate.value = '';
                el.taxSheetOverlay?.classList.remove('hidden');
            };

            const closeTaxSheet = () => {
                el.taxSheetOverlay?.classList.add('hidden');
            };

            el.taxAddButton?.addEventListener('click', openTaxSheet);
            el.roundingMode?.addEventListener('change', updateRoundingUi);
            el.roundingIncrement?.addEventListener('input', updateRoundingUi);
            el.taxSheetBackdrop?.addEventListener('click', closeTaxSheet);
            el.taxSheetClose?.addEventListener('click', closeTaxSheet);
            el.taxSheetCancel?.addEventListener('click', closeTaxSheet);
            el.taxSheetSave?.addEventListener('click', async () => {
                const name = String(el.taxName?.value || '').trim();
                const description = String(el.taxDescription?.value || '').trim();
                const rate = Number(el.taxRate?.value || 0);

                if (!name) {
                    el.taxSheetFeedback?.classList.remove('hidden');
                    el.taxSheetFeedback.textContent = 'Nama pajak wajib diisi.';
                    el.taxSheetFeedback.className = 'mb-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700';
                    return;
                }
                if (!Number.isFinite(rate) || rate < 0) {
                    el.taxSheetFeedback?.classList.remove('hidden');
                    el.taxSheetFeedback.textContent = 'Tarif pajak tidak valid.';
                    el.taxSheetFeedback.className = 'mb-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700';
                    return;
                }

                const response = await fetchWriteWithFreshCsrf('/api/v1/taxes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        name,
                        description,
                        rate,
                        is_active: true,
                    }),
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    el.taxSheetFeedback?.classList.remove('hidden');
                    el.taxSheetFeedback.textContent = payload.message || 'Gagal menyimpan pajak.';
                    el.taxSheetFeedback.className = 'mb-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700';
                    if (el.taxSheetSave) {
                        el.taxSheetSave.disabled = false;
                    }
                    return;
                }

                closeTaxSheet();
                const refresh = await fetch('/api/v1/settings/tax-promos', { headers });
                if (refresh.ok) {
                    const data = await refresh.json();
                    renderTaxes(data.taxes || []);
                    renderPromos(data.promos || []);
                }
            });
            el.roundingSave?.addEventListener('click', async () => {
                const mode = el.roundingMode?.value || 'none';
                const increment = Number(el.roundingIncrement?.value || 0);
                if (el.roundingSave) {
                    el.roundingSave.disabled = true;
                }
                if (el.roundingSaveFeedback) {
                    el.roundingSaveFeedback.textContent = 'Menyimpan...';
                }

                const response = await fetchWriteWithFreshCsrf('/api/v1/settings/rounding', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        mode,
                        increment: Number.isFinite(increment) ? increment : 0,
                    }),
                });
                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    if (el.roundingSaveFeedback) {
                        el.roundingSaveFeedback.textContent = payload.message || 'Gagal menyimpan pembulatan.';
                    }
                    if (el.roundingSave) {
                        el.roundingSave.disabled = false;
                    }
                    return;
                }
                const payload = await response.json().catch(() => ({}));
                applyRoundingSettings(payload.rounding_settings || { mode, increment: mode === 'multiple' ? increment : 0 });
                if (el.roundingSaveFeedback) {
                    el.roundingSaveFeedback.textContent = 'Tersimpan.';
                }
                if (el.roundingSave) {
                    el.roundingSave.disabled = false;
                }
            });

            el.taxes?.addEventListener('change', async (event) => {
                const checkbox = event.target.closest('.toggle-checkbox');
                if (!checkbox) {
                    return;
                }
                const taxId = checkbox.dataset.id;
                const response = await fetchWriteWithFreshCsrf(`/api/v1/taxes/${taxId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ is_active: checkbox.checked }),
                });
                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    checkbox.checked = !checkbox.checked;
                    if (el.roundingSaveFeedback) {
                        el.roundingSaveFeedback.textContent = payload.message || 'Gagal memperbarui pajak.';
                    }
                }
            });
            @endif

            (async () => {
                const response = await fetch('/api/v1/settings/tax-promos', { headers });
                if (!response.ok) {
                    el.taxes.innerHTML = '<div class="text-sm text-red-500">Gagal memuat pajak.</div>';
                    el.promos.innerHTML = '<div class="text-sm text-red-500">Gagal memuat promo.</div>';
                    return;
                }
                const data = await response.json();
                renderTaxes(data.taxes || []);
                applyRoundingSettings(data.rounding_settings || { mode: 'none', increment: 0 });
                renderPromos(data.promos || []);
            })();
        })();
    </script>
@endsection









