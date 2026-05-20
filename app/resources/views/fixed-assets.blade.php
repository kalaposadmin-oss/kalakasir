@extends('layouts.app')

@php
    $fixedAssetTenant = \App\Support\TenantContext::get();
    $fixedAssetUser = auth()->user();
    $fixedAssetRole = null;

    if (! $fixedAssetTenant && filled($fixedAssetUser?->tenant_id)) {
        $fixedAssetTenant = \App\Models\Tenant::query()->find($fixedAssetUser->tenant_id);
    }

    if ($fixedAssetTenant && $fixedAssetUser) {
        $fixedAssetRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $fixedAssetTenant->id, (string) $fixedAssetUser->id);

        if (! $fixedAssetRole && filled($fixedAssetUser->role ?? null)) {
            $fixedAssetRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $fixedAssetUser->role);
        }
    }

    abort_unless(in_array($fixedAssetRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $fixedAssetCanManage = in_array($fixedAssetRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Aset Tetap')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto pb-24')

@section('header')
    <x-page-header title="Aset Tetap" subtitle="Peralatan, maintenance, depresiasi, dan audit aset" backUrl="/backoffice/inventory" :showMenuButton="true">
        @if ($fixedAssetCanManage)
            <button class="inline-flex items-center gap-2 rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white shadow-sm shadow-primary/20" type="button" data-scroll-create>
                <span class="material-symbols-outlined text-[18px]">add_box</span>
                Tambah Aset
            </button>
        @endif
    </x-page-header>
@endsection

@section('content')
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-4 sm:px-6 lg:px-8">
        <div id="fixed-asset-alert" class="hidden rounded-lg border px-4 py-3 text-sm font-semibold"></div>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" id="fixed-asset-stats">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Aset</p>
                <p class="mt-2 text-2xl font-black" data-stat="count">0</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Nilai Beli</p>
                <p class="mt-2 text-2xl font-black" data-stat="purchase">Rp0</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Nilai Buku</p>
                <p class="mt-2 text-2xl font-black" data-stat="book">Rp0</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Butuh Maintenance</p>
                <p class="mt-2 text-2xl font-black text-amber-600" data-stat="maintenance">0</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Sisa Hutang</p>
                <p class="mt-2 text-2xl font-black text-rose-600" data-stat="payable">Rp0</p>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-black text-slate-950 dark:text-white">Daftar Peralatan</h2>
                    <p class="text-sm text-slate-500">Pisahkan oven, mixer, showcase, chiller, dan mesin dari stok bahan baku.</p>
                </div>
                <div class="flex items-center gap-2">
                    <select id="fixed-asset-status-filter" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                        <option value="">Semua status</option>
                        <option value="active">Aktif</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="retired">Pensiun</option>
                        <option value="lost">Hilang</option>
                    </select>
                    <button class="flex size-10 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-900" type="button" id="fixed-asset-refresh" aria-label="Muat ulang">
                        <span class="material-symbols-outlined text-[20px]">refresh</span>
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-900/70">
                        <tr>
                            <th class="px-4 py-3 text-left font-black">Aset</th>
                            <th class="px-4 py-3 text-left font-black">Kategori</th>
                            <th class="px-4 py-3 text-right font-black">Nilai Buku</th>
                            <th class="px-4 py-3 text-right font-black">Hutang</th>
                            <th class="px-4 py-3 text-left font-black">Kondisi</th>
                            <th class="px-4 py-3 text-left font-black">Maintenance</th>
                            <th class="px-4 py-3 text-right font-black">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="fixed-asset-table" class="divide-y divide-slate-100 dark:divide-slate-800"></tbody>
                </table>
            </div>
        </section>

        @if ($fixedAssetCanManage)
            <section id="fixed-asset-create" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <div class="mb-4">
                    <h2 class="text-base font-black text-slate-950 dark:text-white">Catat Aset Baru</h2>
                    <p class="text-sm text-slate-500">Gunakan untuk aset bernilai besar atau umur pakai panjang, bukan untuk bahan habis pakai.</p>
                </div>
                <form id="fixed-asset-form" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Kode</span>
                        <input name="asset_code" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="AST-OVN-001" required>
                    </label>
                    <label class="space-y-1 md:col-span-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Nama Barang</span>
                        <input name="name" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="Oven gas 2 deck" required>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Kategori</span>
                        <select name="asset_category" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900">
                            <option value="production_equipment">Produksi</option>
                            <option value="display_equipment">Display</option>
                            <option value="cooling_equipment">Pendingin</option>
                            <option value="office_equipment">Kantor</option>
                            <option value="vehicle">Kendaraan</option>
                            <option value="other">Lainnya</option>
                        </select>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Harga Beli</span>
                        <input name="purchase_price" type="number" min="0" step="0.01" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900" required>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Pembayaran</span>
                        <select name="payment_method" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900">
                            <option value="cash">Kas</option>
                            <option value="bank">Bank</option>
                            <option value="payable">Hutang</option>
                            <option value="unposted">Catat aset saja</option>
                        </select>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Tanggal Beli</span>
                        <input name="purchase_date" type="date" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900">
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Umur Pakai</span>
                        <input name="useful_life_months" type="number" min="1" step="1" value="48" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900" required>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Nilai Residu</span>
                        <input name="residual_value" type="number" min="0" step="0.01" value="0" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900">
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Kondisi</span>
                        <select name="condition" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900">
                            <option value="good">Baik</option>
                            <option value="needs_repair">Perlu Servis</option>
                            <option value="broken">Rusak</option>
                        </select>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Maintenance Berikutnya</span>
                        <input name="next_maintenance_at" type="date" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900">
                    </label>
                    <label class="space-y-1 md:col-span-2 xl:col-span-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Catatan</span>
                        <input name="notes" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="Nomor seri, supplier, garansi, atau lokasi">
                    </label>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-800 dark:bg-slate-900 md:col-span-2 xl:col-span-3" id="fixed-asset-preview">
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Kas/Bank Keluar</p>
                                <p class="font-black" data-preview="cash_out">Rp0</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Aset Bertambah</p>
                                <p class="font-black" data-preview="asset_increase">Rp0</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Depresiasi / Bulan</p>
                                <p class="font-black text-amber-700" data-preview="monthly">Rp0</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Hutang Bertambah</p>
                                <p class="font-black" data-preview="payable">Rp0</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Depresiasi / Tahun</p>
                                <p class="font-black" data-preview="annual">Rp0</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Nilai Buku 12 Bulan</p>
                                <p class="font-black" data-preview="book12">Rp0</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-end">
                        <button class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-black text-white shadow-sm shadow-primary/20" type="submit">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            Simpan Aset
                        </button>
                    </div>
                </form>
            </section>

            <section id="fixed-asset-payment-panel" class="hidden rounded-lg border border-rose-200 bg-white p-4 shadow-sm dark:border-rose-900 dark:bg-slate-950">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-black text-slate-950 dark:text-white">Bayar Hutang Aset</h2>
                        <p class="text-sm text-slate-500" data-payment-title>Pilih aset yang masih punya sisa hutang.</p>
                    </div>
                    <button class="flex size-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" type="button" data-payment-close aria-label="Tutup pembayaran">
                        <span class="material-symbols-outlined text-[19px]">close</span>
                    </button>
                </div>
                <form id="fixed-asset-payment-form" class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <input type="hidden" name="asset_id">
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Tanggal Bayar</span>
                        <input name="payment_date" type="date" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900" required>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Dibayar Dari</span>
                        <select name="payment_account_id" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900" required></select>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Nominal</span>
                        <input name="amount" type="number" min="0.01" step="0.01" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900" required>
                    </label>
                    <label class="space-y-1 md:col-span-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Catatan</span>
                        <input name="notes" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="Transfer, termin, atau nomor bukti bayar">
                    </label>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-4">
                        <div class="grid gap-2 sm:grid-cols-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Sisa Sebelum Bayar</p>
                                <p class="font-black" data-payment-preview="before">Rp0</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Dibayar Sekarang</p>
                                <p class="font-black text-rose-600" data-payment-preview="amount">Rp0</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Sisa Setelah Bayar</p>
                                <p class="font-black" data-payment-preview="after">Rp0</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-end">
                        <button class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-rose-600 px-4 text-sm font-black text-white shadow-sm shadow-rose-600/20" type="submit">
                            <span class="material-symbols-outlined text-[18px]">payments</span>
                            Bayar Hutang
                        </button>
                    </div>
                </form>
            </section>
        @endif
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content || '';
            const baseHeaders = {
                Accept: 'application/json',
                ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
            };
            const canManage = @json($fixedAssetCanManage);
            const el = {
                alert: document.getElementById('fixed-asset-alert'),
                table: document.getElementById('fixed-asset-table'),
                form: document.getElementById('fixed-asset-form'),
                filter: document.getElementById('fixed-asset-status-filter'),
                refresh: document.getElementById('fixed-asset-refresh'),
                create: document.getElementById('fixed-asset-create'),
                stats: document.getElementById('fixed-asset-stats'),
                preview: document.getElementById('fixed-asset-preview'),
                paymentPanel: document.getElementById('fixed-asset-payment-panel'),
                paymentForm: document.getElementById('fixed-asset-payment-form'),
                paymentTitle: document.querySelector('[data-payment-title]'),
            };

            let assets = [];
            let accountingDefaults = {};
            let selectedPaymentAsset = null;

            const rupiah = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Number(value || 0));

            const dateLabel = (value) => {
                if (!value) return '-';
                return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
            };

            const labelMaps = {
                asset_category: {
                    production_equipment: 'Produksi',
                    display_equipment: 'Display',
                    cooling_equipment: 'Pendingin',
                    office_equipment: 'Kantor',
                    vehicle: 'Kendaraan',
                    other: 'Lainnya',
                },
                condition: {
                    good: 'Baik',
                    needs_repair: 'Perlu Servis',
                    broken: 'Rusak',
                },
                status: {
                    active: 'Aktif',
                    maintenance: 'Maintenance',
                    retired: 'Pensiun',
                    lost: 'Hilang',
                },
            };

            const showAlert = (message, tone = 'success') => {
                el.alert.className = `rounded-lg border px-4 py-3 text-sm font-semibold ${
                    tone === 'success'
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : 'border-red-200 bg-red-50 text-red-700'
                }`;
                el.alert.textContent = message;
                window.clearTimeout(showAlert.timer);
                showAlert.timer = window.setTimeout(() => el.alert.classList.add('hidden'), 4000);
            };

            const apiJson = async (url, init = {}) => {
                const response = await window.PosUi.fetchWithTimeout(url, {
                    ...init,
                    headers: {
                        ...baseHeaders,
                        ...(init.body ? { 'Content-Type': 'application/json' } : {}),
                        ...(init.headers || {}),
                    },
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(payload.message || 'Permintaan gagal.');
                }
                return payload;
            };

            const serializeForm = (form) => {
                const data = Object.fromEntries(new FormData(form).entries());
                ['purchase_price', 'residual_value', 'useful_life_months'].forEach((key) => {
                    if (data[key] !== '') data[key] = Number(data[key]);
                });
                Object.keys(data).forEach((key) => {
                    if (data[key] === '') delete data[key];
                });
                return data;
            };

            const renderPreview = (preview = {}) => {
                if (!el.preview) return;
                el.preview.querySelector('[data-preview="cash_out"]').textContent = rupiah(preview.cash_or_bank_out || 0);
                el.preview.querySelector('[data-preview="asset_increase"]').textContent = rupiah(preview.fixed_asset_increase || 0);
                el.preview.querySelector('[data-preview="monthly"]').textContent = rupiah(preview.monthly_depreciation || 0);
                el.preview.querySelector('[data-preview="payable"]').textContent = rupiah(preview.payable_increase || 0);
                el.preview.querySelector('[data-preview="annual"]').textContent = rupiah(preview.annual_depreciation || 0);
                el.preview.querySelector('[data-preview="book12"]').textContent = rupiah(preview.book_value_after_12_months || 0);
            };

            const buildLocalPreview = () => {
                if (!el.form) return;
                const price = Number(el.form.elements.purchase_price.value || 0);
                const residual = Math.min(Number(el.form.elements.residual_value.value || 0), price);
                const months = Number(el.form.elements.useful_life_months.value || 0);
                const method = el.form.elements.payment_method.value || 'unposted';
                const monthly = months > 0 ? Math.max(0, price - residual) / months : 0;
                renderPreview({
                    cash_or_bank_out: ['cash', 'bank'].includes(method) ? price : 0,
                    fixed_asset_increase: price,
                    payable_increase: method === 'payable' ? price : 0,
                    monthly_depreciation: monthly,
                    annual_depreciation: monthly * 12,
                    book_value_after_12_months: Math.max(residual, price - (monthly * 12)),
                });
            };

            const maintenanceDue = (asset) => {
                if (!asset.next_maintenance_at || asset.status !== 'active') {
                    return false;
                }
                const dueDate = new Date(asset.next_maintenance_at);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                return dueDate <= today;
            };

            const renderStats = () => {
                const activeAssets = assets.filter((asset) => asset.status !== 'retired' && asset.status !== 'lost');
                const sum = (key) => activeAssets.reduce((total, asset) => total + Number(asset[key] || 0), 0);
                el.stats.querySelector('[data-stat="count"]').textContent = activeAssets.length;
                el.stats.querySelector('[data-stat="purchase"]').textContent = rupiah(sum('purchase_price'));
                el.stats.querySelector('[data-stat="book"]').textContent = rupiah(sum('book_value'));
                el.stats.querySelector('[data-stat="maintenance"]').textContent = activeAssets.filter(maintenanceDue).length;
                el.stats.querySelector('[data-stat="payable"]').textContent = rupiah(activeAssets.reduce((total, asset) => total + Number(asset.liability_remaining_amount || 0), 0));
            };

            const actionButtons = (asset) => {
                if (!canManage) {
                    return '<span class="text-xs font-semibold text-slate-400">Lihat saja</span>';
                }

                return `
                    <div class="flex justify-end gap-1">
                        ${Number(asset.liability_remaining_amount || 0) > 0 ? `
                            <button class="flex size-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100" type="button" data-action="payment" data-id="${asset.id}" title="Bayar hutang aset">
                                <span class="material-symbols-outlined text-[18px]">payments</span>
                            </button>
                        ` : ''}
                        <button class="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" type="button" data-action="maintenance" data-id="${asset.id}" title="Catat maintenance">
                            <span class="material-symbols-outlined text-[18px]">build</span>
                        </button>
                        <button class="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" type="button" data-action="depreciate" data-id="${asset.id}" title="Hitung depresiasi bulan ini">
                            <span class="material-symbols-outlined text-[18px]">trending_down</span>
                        </button>
                        <button class="flex size-8 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50" type="button" data-action="audit" data-id="${asset.id}" title="Catat audit fisik">
                            <span class="material-symbols-outlined text-[18px]">fact_check</span>
                        </button>
                    </div>
                `;
            };

            const renderTable = () => {
                const status = el.filter.value;
                const rows = assets.filter((asset) => !status || asset.status === status);

                if (!rows.length) {
                    el.table.innerHTML = '<tr><td class="px-4 py-8 text-center text-sm text-slate-500" colspan="7">Belum ada aset tetap.</td></tr>';
                    return;
                }

                el.table.innerHTML = rows.map((asset) => {
                    const due = maintenanceDue(asset);
                    return `
                        <tr class="align-top">
                            <td class="px-4 py-3">
                                <div class="font-black text-slate-950 dark:text-white">${asset.name || '-'}</div>
                                <div class="text-xs font-semibold text-slate-500">${asset.asset_code || '-'} - ${labelMaps.status[asset.status] || asset.status || '-'}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">${labelMaps.asset_category[asset.asset_category] || asset.asset_category || '-'}</td>
                            <td class="px-4 py-3 text-right font-bold">${rupiah(asset.book_value)}</td>
                            <td class="px-4 py-3 text-right">
                                ${Number(asset.liability_remaining_amount || 0) > 0
                                    ? `<div class="font-black text-rose-600">${rupiah(asset.liability_remaining_amount)}</div><div class="text-xs text-slate-500">dibayar ${rupiah(asset.liability_paid_amount || 0)}</div>`
                                    : '<span class="text-xs font-bold text-emerald-700">Lunas / Tidak ada</span>'}
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-bold ${asset.condition === 'good' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}">${labelMaps.condition[asset.condition] || asset.condition || '-'}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="${due ? 'font-black text-amber-600' : 'text-slate-600 dark:text-slate-300'}">${dateLabel(asset.next_maintenance_at)}</span>
                            </td>
                            <td class="px-4 py-3 text-right">${actionButtons(asset)}</td>
                        </tr>
                    `;
                }).join('');
            };

            const renderPaymentAccountOptions = () => {
                if (!el.paymentForm) return;
                const select = el.paymentForm.elements.payment_account_id;
                const options = [];
                if (accountingDefaults.cash_account_id) options.push({ value: accountingDefaults.cash_account_id, label: 'Kas' });
                if (accountingDefaults.bank_account_id) options.push({ value: accountingDefaults.bank_account_id, label: 'Bank' });
                select.innerHTML = options.map((option) => `<option value="${option.value}">${option.label}</option>`).join('');
            };

            const renderPaymentPreview = () => {
                if (!selectedPaymentAsset || !el.paymentForm) return;
                const before = Number(selectedPaymentAsset.liability_remaining_amount || 0);
                const amount = Math.min(Number(el.paymentForm.elements.amount.value || 0), before);
                const after = Math.max(0, before - amount);
                document.querySelector('[data-payment-preview="before"]').textContent = rupiah(before);
                document.querySelector('[data-payment-preview="amount"]').textContent = rupiah(amount);
                document.querySelector('[data-payment-preview="after"]').textContent = rupiah(after);
            };

            const openPaymentPanel = (asset) => {
                if (!el.paymentPanel || !el.paymentForm) return;
                selectedPaymentAsset = asset;
                el.paymentForm.reset();
                el.paymentForm.elements.asset_id.value = asset.id;
                el.paymentForm.elements.payment_date.value = new Date().toISOString().slice(0, 10);
                el.paymentForm.elements.amount.value = Number(asset.liability_remaining_amount || 0).toFixed(2);
                if (accountingDefaults.cash_account_id) {
                    el.paymentForm.elements.payment_account_id.value = accountingDefaults.cash_account_id;
                }
                el.paymentTitle.textContent = `${asset.name} - sisa hutang ${rupiah(asset.liability_remaining_amount || 0)}`;
                el.paymentPanel.classList.remove('hidden');
                renderPaymentPreview();
                el.paymentPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            };

            const loadAssets = async () => {
                el.table.innerHTML = '<tr><td class="px-4 py-8 text-center text-sm text-slate-500" colspan="7">Memuat aset...</td></tr>';
                const payload = await apiJson('/api/v1/fixed-assets');
                assets = payload.data || [];
                accountingDefaults = payload.accounting_defaults || {};
                renderPaymentAccountOptions();
                renderStats();
                renderTable();
            };

            const handleAssetAction = async (button) => {
                const asset = assets.find((item) => String(item.id) === String(button.dataset.id));
                if (!asset) return;

                if (button.dataset.action === 'payment') {
                    openPaymentPanel(asset);
                    return;
                }

                if (button.dataset.action === 'maintenance') {
                    const notes = window.prompt(`Catatan maintenance untuk ${asset.name}`, 'Servis rutin');
                    if (notes === null) return;
                    await apiJson(`/api/v1/fixed-assets/${asset.id}/maintenance`, {
                        method: 'POST',
                        body: JSON.stringify({
                            maintenance_date: new Date().toISOString().slice(0, 10),
                            maintenance_type: 'routine',
                            condition_after: 'good',
                            next_maintenance_at: '',
                            notes,
                        }),
                    });
                    showAlert('Maintenance aset tercatat.');
                }

                if (button.dataset.action === 'depreciate') {
                    if (!await window.PosUi.confirmRiskActionAsync({
                        title: 'Hitung Depresiasi',
                        message: `Aset: ${asset.name}\nPeriode: Bulan ini\nLanjut hitung penyusutan aset ini?`,
                        confirmText: 'Hitung',
                        icon: 'calculate',
                    })) return;
                    await apiJson(`/api/v1/fixed-assets/${asset.id}/depreciation`, {
                        method: 'POST',
                        body: JSON.stringify({ period_date: new Date().toISOString().slice(0, 10) }),
                    });
                    showAlert('Depresiasi aset berhasil dihitung.');
                }

                if (button.dataset.action === 'audit') {
                    const condition = window.prompt(`Kondisi fisik ${asset.name}`, asset.condition || 'good');
                    if (condition === null) return;
                    await apiJson(`/api/v1/fixed-assets/${asset.id}/audits`, {
                        method: 'POST',
                        body: JSON.stringify({
                            audit_date: new Date().toISOString().slice(0, 10),
                            condition: condition || asset.condition || 'good',
                            physical_status: 'found',
                        }),
                    });
                    showAlert('Audit fisik aset tercatat.');
                }

                await loadAssets();
            };

            el.refresh?.addEventListener('click', () => loadAssets().catch((error) => showAlert(error.message, 'error')));
            el.filter?.addEventListener('change', renderTable);
            document.querySelector('[data-scroll-create]')?.addEventListener('click', () => el.create?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            ['purchase_price', 'residual_value', 'useful_life_months', 'payment_method'].forEach((name) => {
                el.form?.elements[name]?.addEventListener('input', buildLocalPreview);
                el.form?.elements[name]?.addEventListener('change', buildLocalPreview);
            });
            document.querySelector('[data-payment-close]')?.addEventListener('click', () => {
                el.paymentPanel?.classList.add('hidden');
                selectedPaymentAsset = null;
            });
            el.paymentForm?.elements.amount?.addEventListener('input', renderPaymentPreview);
            el.paymentForm?.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!selectedPaymentAsset) return;
                const submit = event.submitter;
                try {
                    await window.PosUi.runWithSubmitGuard(submit, async () => {
                        await apiJson(`/api/v1/fixed-assets/${selectedPaymentAsset.id}/payments`, {
                            method: 'POST',
                            body: JSON.stringify(serializeForm(el.paymentForm)),
                        });
                    });
                    showAlert('Pembayaran hutang aset berhasil dicatat.');
                    el.paymentPanel?.classList.add('hidden');
                    selectedPaymentAsset = null;
                    await loadAssets();
                } catch (error) {
                    showAlert(error.message, 'error');
                }
            });
            el.table?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-action]');
                if (button) {
                    handleAssetAction(button).catch((error) => showAlert(error.message, 'error'));
                }
            });
            el.form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                const submit = event.submitter;
                try {
                    await window.PosUi.runWithSubmitGuard(submit, async () => {
                        await apiJson('/api/v1/fixed-assets', {
                            method: 'POST',
                            body: JSON.stringify(serializeForm(el.form)),
                        });
                    });
                    el.form.reset();
                    el.form.elements.useful_life_months.value = 48;
                    el.form.elements.residual_value.value = 0;
                    el.form.elements.payment_method.value = 'cash';
                    buildLocalPreview();
                    showAlert('Aset tetap berhasil dicatat.');
                    await loadAssets();
                } catch (error) {
                    showAlert(error.message, 'error');
                }
            });

            loadAssets().catch((error) => showAlert(error.message, 'error'));
            buildLocalPreview();
        })();
    </script>
@endsection
