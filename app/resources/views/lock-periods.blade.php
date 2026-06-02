@extends('layouts.app')

@php
    $lockPeriodsTenant = \App\Support\TenantContext::get();
    $lockPeriodsUser = auth()->user();
    $lockPeriodsRole = null;

    if (! $lockPeriodsTenant && filled($lockPeriodsUser?->tenant_id)) {
        $lockPeriodsTenant = \App\Models\Tenant::query()->find($lockPeriodsUser->tenant_id);
    }

    if ($lockPeriodsTenant && $lockPeriodsUser) {
        $lockPeriodsRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $lockPeriodsTenant->id, (string) $lockPeriodsUser->id);

        if (! $lockPeriodsRole && filled($lockPeriodsUser->role ?? null)) {
            $lockPeriodsRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $lockPeriodsUser->role);
        }
    }

    abort_unless(in_array($lockPeriodsRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManageLockPeriods = in_array($lockPeriodsRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Tutup Buku Bulanan')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
        <style>
            .toggle-checkbox:checked + .toggle-label {
                background-color: rgb(var(--primary-500));
            }
            .toggle-checkbox:checked + .toggle-label .toggle-dot {
                transform: translateX(1.25rem);
            }
        </style>
@endpush

@section('header')
        <x-page-header title="Tutup Buku" backUrl="/backoffice/finance/summary">
            <x-slot name="actions">
                <button class="flex items-center justify-center size-10 rounded-full bg-slate-100 dark:bg-slate-800">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">info</span>
                </button>
            </x-slot>
        </x-page-header>
@endsection

@section('content')
        <div class="flex-1 overflow-y-auto pb-32">
            <div class="p-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-xl p-4 flex gap-3">
                    <span class="material-symbols-outlined text-primary">verified_user</span>
                    <div>
                        <p class="text-sm font-semibold text-primary">Informasi Keamanan</p>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">Tutup buku mengunci transaksi periode lama dan menyimpan snapshot saldo kas, stok, hutang, dan piutang.</p>
                    </div>
                </div>
            </div>
            <section class="px-4 mb-8">
                <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest px-1 mb-3">Tutup Periode</h3>
                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 ml-1">Tanggal Mulai</label>
                            <div class="relative">
                                <input id="start-date" class="w-full rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 py-3 px-4 text-sm focus:ring-primary focus:border-primary" type="date" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 ml-1">Tanggal Selesai</label>
                            <div class="relative">
                                <input id="end-date" class="w-full rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 py-3 px-4 text-sm focus:ring-primary focus:border-primary" type="date" />
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 ml-1">Nama Periode</label>
                        <input id="period-name" class="w-full rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 py-3 px-4 text-sm" placeholder="Contoh: April 2024" type="text" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1.5 ml-1">Catatan Tutup Buku</label>
                        <textarea id="period-reason" class="w-full rounded-xl border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 py-3 px-4 text-sm" placeholder="Contoh: Rekonsiliasi akhir bulan selesai" rows="2"></textarea>
                    </div>
                    @if ($canManageLockPeriods)
                        <button id="create-period" class="w-full bg-primary text-white py-3.5 rounded-xl font-bold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform">
                            <span class="material-symbols-outlined text-lg">verified_user</span>
                            Review & Tutup Buku
                        </button>
                    @endif
                </div>
            </section>
            <section class="px-4 space-y-3">
                <div class="flex justify-between items-center px-1 mb-3">
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Daftar Periode Akuntansi</h3>
                    <span class="text-[10px] font-bold text-slate-400" id="period-count">TOTAL 0 PERIODE</span>
                </div>
                <div id="period-list" class="space-y-3"></div>
            </section>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    start: document.getElementById('start-date'),
                    end: document.getElementById('end-date'),
                    name: document.getElementById('period-name'),
                    create: document.getElementById('create-period'),
                    reason: document.getElementById('period-reason'),
                    list: document.getElementById('period-list'),
                    count: document.getElementById('period-count'),
                };

                const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                }).format(new Date(value));

                const renderPeriods = (periods) => {
                    el.list.innerHTML = '';
                    el.count.textContent = `TOTAL ${periods.length} PERIODE`;

                    if (!periods.length) {
                        el.list.innerHTML = '<div class="text-sm text-slate-400">Belum ada periode.</div>';
                        return;
                    }

                    periods.forEach((period, index) => {
                        const locked = period.is_locked;
                        const statusLabel = locked ? 'Terkunci' : 'Terbuka';
                        const statusClass = locked ? 'text-red-500' : 'text-emerald-500';
                        const iconClass = locked ? 'lock_person' : 'lock_open_right';
                        const dotClass = locked ? 'bg-red-500' : 'bg-emerald-500';

                        const carry = period.close_summary?.carry_forward || null;
                        const carryHtml = carry ? `
                            <div class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-[10px] text-slate-500">
                                <span>Kas: <b>${formatCurrency(carry.cash_balance || 0)}</b></span>
                                <span>Stok: <b>${formatCurrency(carry.stock_value || 0)}</b></span>
                                <span>Hutang: <b>${formatCurrency(carry.supplier_debt || 0)}</b></span>
                                <span>Piutang: <b>${formatCurrency(carry.receivable_balance || 0)}</b></span>
                            </div>
                        ` : '';

                        const row = document.createElement('div');
                        row.className = `bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-sm${index === periods.length - 1 ? ' opacity-75' : ''}`;
                        row.innerHTML = `
                            <div class="flex items-center gap-4">
                                <div class="size-12 rounded-xl ${locked ? 'bg-slate-100 dark:bg-slate-800' : 'bg-primary/10'} flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined ${locked ? 'text-slate-400' : 'text-primary'}">${iconClass}</span>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-900 dark:text-slate-100">${formatDate(period.start_date)}</h4>
                                    <p class="text-[11px] text-slate-500">${formatDate(period.start_date)} - ${formatDate(period.end_date)}</p>
                                    <span class="inline-flex items-center text-[10px] font-bold ${statusClass} mt-1 uppercase">
                                        <span class="size-1.5 ${dotClass} rounded-full mr-1.5"></span> ${statusLabel}
                                    </span>
                                    ${carryHtml}
                                </div>
                            </div>
                            <div class="flex items-center">
                                @if ($canManageLockPeriods)
                                    <div class="relative inline-block w-10 align-middle select-none">
                                        <input class="toggle-checkbox hidden" id="toggle-${period.id}" type="checkbox" ${locked ? 'checked' : ''} data-id="${period.id}" />
                                        <label class="toggle-label block overflow-hidden h-5 rounded-full bg-slate-200 cursor-pointer transition-colors duration-200" for="toggle-${period.id}">
                                            <span class="toggle-dot block h-5 w-5 rounded-full bg-white shadow-md transform transition-transform duration-200 ${locked ? 'translate-x-5' : ''}"></span>
                                        </label>
                                    </div>
                                @endif
                            </div>
                        `;
                        el.list.appendChild(row);
                    });
                };

                const loadPeriods = async () => {
                    const response = await fetch('/api/v1/lock-periods', { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const data = await response.json();
                    return data.periods || [];
                };

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const apiMessage = async (response, fallback) => {
                    const payload = await response.json().catch(() => ({}));
                    const details = payload?.details && typeof payload.details === 'object'
                        ? Object.values(payload.details).flat().filter(Boolean)
                        : [];

                    return [payload?.message || fallback, ...details].filter(Boolean).join('\n');
                };

                @if ($canManageLockPeriods)
                el.create.addEventListener('click', async () => {
                    if (!el.start.value || !el.end.value) {
                        alert('Lengkapi tanggal periode.');
                        return;
                    }
                    const previewUrl = new URL('/api/v1/lock-periods/preview', window.location.origin);
                    previewUrl.searchParams.set('start_date', el.start.value);
                    previewUrl.searchParams.set('end_date', el.end.value);

                    const previewResponse = await fetch(previewUrl, { headers });
                    if (!previewResponse.ok) {
                        alert(await apiMessage(previewResponse, 'Gagal memuat preview tutup buku.'));
                        return;
                    }

                    const preview = await previewResponse.json();
                    const carry = preview.carry_forward || {};
                    const checks = preview.checks || {};

                    if (!checks.can_close) {
                        alert(`Periode belum bisa ditutup.\nShift terbuka: ${checks.open_shift_count || 0}\nStok minus: ${checks.negative_stock_count || 0}`);
                        return;
                    }

                    const confirmed = window.confirm([
                        'Tutup Buku',
                        '',
                        `Periode: ${formatDate(el.start.value)} - ${formatDate(el.end.value)}`,
                        `Kas akhir: ${formatCurrency(carry.cash_balance || 0)}`,
                        `Nilai stok: ${formatCurrency(carry.stock_value || 0)}`,
                        `Hutang: ${formatCurrency(carry.supplier_debt || 0)}`,
                        `Piutang: ${formatCurrency(carry.receivable_balance || 0)}`,
                        '',
                        'Transaksi pada periode ini akan dikunci. Lanjutkan?',
                    ].join('\n'));

                    if (!confirmed) {
                        return;
                    }

                    const response = await fetch('/api/v1/lock-periods/close', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            ...headers,
                        },
                        body: JSON.stringify({
                            start_date: el.start.value,
                            end_date: el.end.value,
                            name: el.name.value.trim() || null,
                            reason: el.reason.value.trim() || null,
                        }),
                    });
                    if (!response.ok) {
                        alert(await apiMessage(response, 'Gagal tutup buku.'));
                        return;
                    }
                    const periods = await loadPeriods();
                    renderPeriods(periods);
                });

                el.list.addEventListener('change', async (event) => {
                    const checkbox = event.target.closest('.toggle-checkbox');
                    if (!checkbox) {
                        return;
                    }
                    const periodId = checkbox.dataset.id;
                    let response;

                    if (!checkbox.checked) {
                        const reason = window.prompt('Alasan buka ulang periode?');
                        if (!reason || !reason.trim()) {
                            checkbox.checked = true;
                            return;
                        }

                        response = await fetch(`/api/v1/lock-periods/${periodId}/reopen`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                ...headers,
                            },
                            body: JSON.stringify({ reason: reason.trim() }),
                        });
                    } else {
                        response = await fetch(`/api/v1/lock-periods/${periodId}`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            ...headers,
                        },
                        body: JSON.stringify({ is_locked: checkbox.checked }),
                        });
                    }

                    if (!response.ok) {
                        alert(await apiMessage(response, 'Gagal memperbarui periode.'));
                        checkbox.checked = !checkbox.checked;
                        return;
                    }

                    renderPeriods(await loadPeriods());
                });
                @endif

                (async () => {
                    const periods = await loadPeriods();
                    renderPeriods(periods);
                })();
            })();
        </script>
@endsection
