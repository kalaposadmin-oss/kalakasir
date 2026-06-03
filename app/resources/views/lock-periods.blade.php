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
@section('ui_mode', 'backoffice')
@section('main_class', 'flex-1 overflow-y-auto pb-32')

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
        <div class="mx-auto w-full max-w-6xl">
            <div class="p-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-xl p-4 flex gap-3">
                    <span class="material-symbols-outlined text-primary">verified_user</span>
                    <div>
                        <p class="text-sm font-semibold text-primary">Informasi Keamanan</p>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">Tutup buku mengunci transaksi periode lama dan menyimpan snapshot saldo kas, stok, hutang, dan piutang.</p>
                    </div>
                </div>
            </div>
            <section class="px-4 mb-6">
                <div id="closing-assistant-banner" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start gap-3">
                        <div id="assistant-icon-wrap" class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 dark:bg-slate-800">
                            <span class="material-symbols-outlined">event_available</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p id="assistant-status-label" class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Memuat status</p>
                            <h3 id="assistant-title" class="mt-1 text-base font-bold text-slate-900 dark:text-white">Menyiapkan asisten tutup buku...</h3>
                            <p id="assistant-message" class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Sistem sedang mengecek periode yang perlu ditutup.</p>
                        </div>
                    </div>
                    <div id="assistant-restrictions" class="mt-3 hidden rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-800"></div>
                </div>
            </section>
            <section class="px-4 mb-8">
                <div class="flex justify-between items-center px-1 mb-3">
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Langkah Tutup Buku</h3>
                    <span class="text-[10px] font-bold text-slate-400" id="assistant-period-label">-</span>
                </div>
                <div class="mb-3 rounded-2xl border border-orange-100 bg-orange-50 p-4 text-xs leading-relaxed text-orange-900 dark:border-orange-900/40 dark:bg-orange-950/20 dark:text-orange-100">
                    <p class="font-bold">Cara pakai checklist</p>
                    <p class="mt-1">Buka langkah yang perlu dicek, koreksi data di halaman asal kalau angkanya salah, lalu centang <b>Sudah saya cek</b>. Tombol tutup buku dipakai setelah semua langkah beres.</p>
                </div>
                <div id="assistant-checklist" class="space-y-3">
                    <div class="text-sm text-slate-400">Memuat checklist tutup buku...</div>
                </div>
            </section>
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
                            <span data-close-label>Review & Tutup Buku</span>
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
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const writeHeaders = {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                    ...headers,
                };
                const reviewState = {
                    requiredKeys: [],
                    checkedKeys: new Set(),
                    blocked: false,
                };

                const el = {
                    start: document.getElementById('start-date'),
                    end: document.getElementById('end-date'),
                    name: document.getElementById('period-name'),
                    create: document.getElementById('create-period'),
                    reason: document.getElementById('period-reason'),
                    list: document.getElementById('period-list'),
                    count: document.getElementById('period-count'),
                    assistantBanner: document.getElementById('closing-assistant-banner'),
                    assistantIconWrap: document.getElementById('assistant-icon-wrap'),
                    assistantStatusLabel: document.getElementById('assistant-status-label'),
                    assistantTitle: document.getElementById('assistant-title'),
                    assistantMessage: document.getElementById('assistant-message'),
                    assistantRestrictions: document.getElementById('assistant-restrictions'),
                    assistantPeriodLabel: document.getElementById('assistant-period-label'),
                    assistantChecklist: document.getElementById('assistant-checklist'),
                };

                const syncCloseButtonState = () => {
                    if (!el.create) {
                        return;
                    }

                    const allChecked = reviewState.requiredKeys.every((key) => reviewState.checkedKeys.has(key));
                    const disabled = reviewState.blocked || !allChecked;
                    el.create.disabled = disabled;
                    el.create.classList.toggle('opacity-50', disabled);
                    el.create.classList.toggle('cursor-not-allowed', disabled);
                    el.create.querySelector('[data-close-label]')?.replaceChildren(
                        document.createTextNode(reviewState.blocked
                            ? 'Perbaiki Data Dulu'
                            : (allChecked ? 'Review & Tutup Buku' : 'Centang Checklist Dulu'))
                    );
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

                const loadAssistant = async () => {
                    const response = await fetch('/api/v1/lock-periods/assistant', { headers });
                    if (!response.ok) {
                        return null;
                    }

                    return response.json();
                };

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const formatMetric = (step) => {
                    const value = step?.metric_value ?? 0;
                    if (['sales_review', 'cash_bank', 'debt_receivable'].includes(step?.key)) {
                        return formatCurrency(value);
                    }

                    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value || 0);
                };

                const checklistTone = (status) => {
                    if (status === 'blocked') {
                        return {
                            icon: 'error',
                            card: 'border-rose-200 bg-rose-50 dark:border-rose-900/40 dark:bg-rose-950/20',
                            iconWrap: 'bg-rose-100 text-rose-600',
                            label: 'Wajib dibereskan',
                            labelClass: 'text-rose-600',
                        };
                    }
                    if (status === 'warning') {
                        return {
                            icon: 'warning',
                            card: 'border-amber-200 bg-amber-50 dark:border-amber-900/40 dark:bg-amber-950/20',
                            iconWrap: 'bg-amber-100 text-amber-600',
                            label: 'Perlu review',
                            labelClass: 'text-amber-700',
                        };
                    }
                    if (status === 'ready') {
                        return {
                            icon: 'verified',
                            card: 'border-primary/20 bg-primary/5',
                            iconWrap: 'bg-primary/10 text-primary',
                            label: 'Siap',
                            labelClass: 'text-primary',
                        };
                    }

                    return {
                        icon: status === 'review' ? 'fact_check' : 'check_circle',
                        card: 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900',
                        iconWrap: status === 'review' ? 'bg-blue-100 text-blue-600' : 'bg-emerald-100 text-emerald-600',
                        label: status === 'review' ? 'Cek manual' : 'Aman',
                        labelClass: status === 'review' ? 'text-blue-600' : 'text-emerald-600',
                    };
                };

                const renderAssistant = (assistant) => {
                    if (!assistant) {
                        el.assistantTitle.textContent = 'Status tutup buku belum tersedia';
                        el.assistantMessage.textContent = 'Coba muat ulang halaman atau cek koneksi.';
                        el.assistantChecklist.innerHTML = '<div class="text-sm text-slate-400">Checklist belum bisa dimuat.</div>';
                        return;
                    }

                    const status = assistant.status || {};
                    const target = assistant.target_period || {};
                    const severity = status.severity || 'safe';
                    const severityLabel = {
                        safe: 'Aman',
                        review: 'Awal bulan',
                        due: 'Perlu ditutup',
                        urgent: 'Wajib ditutup',
                    }[severity] || 'Status';
                    const icon = {
                        safe: 'verified_user',
                        review: 'event_available',
                        due: 'notification_important',
                        urgent: 'priority_high',
                    }[severity] || 'event_available';
                    const iconClass = {
                        safe: 'bg-emerald-100 text-emerald-600',
                        review: 'bg-blue-100 text-blue-600',
                        due: 'bg-amber-100 text-amber-700',
                        urgent: 'bg-rose-100 text-rose-600',
                    }[severity] || 'bg-slate-100 text-slate-500';

                    el.assistantIconWrap.className = `flex size-11 shrink-0 items-center justify-center rounded-xl ${iconClass}`;
                    el.assistantIconWrap.innerHTML = `<span class="material-symbols-outlined">${icon}</span>`;
                    el.assistantStatusLabel.textContent = severityLabel;
                    el.assistantTitle.textContent = status.title || 'Asisten tutup buku';
                    el.assistantMessage.textContent = status.message || '';
                    el.assistantPeriodLabel.textContent = target.label || '-';

                    if (target.start_date && target.end_date) {
                        el.start.value = target.start_date;
                        el.end.value = target.end_date;
                        if (!el.name.value) {
                            el.name.value = target.label || '';
                        }
                    }

                    const restrictions = Array.isArray(status.recommended_restrictions) ? status.recommended_restrictions : [];
                    if (restrictions.length) {
                        el.assistantRestrictions.classList.remove('hidden');
                        el.assistantRestrictions.innerHTML = `<b>Mode pembatasan admin:</b><ul class="mt-1 list-disc pl-4">${restrictions.map((item) => `<li>${item}</li>`).join('')}</ul>`;
                    } else {
                        el.assistantRestrictions.classList.add('hidden');
                        el.assistantRestrictions.innerHTML = '';
                    }

                    const checklist = Array.isArray(assistant.checklist) ? assistant.checklist : [];
                    if (!checklist.length) {
                        el.assistantChecklist.innerHTML = '<div class="text-sm text-slate-400">Belum ada checklist.</div>';
                        reviewState.requiredKeys = [];
                        reviewState.checkedKeys.clear();
                        reviewState.blocked = false;
                        syncCloseButtonState();
                        return;
                    }

                    reviewState.requiredKeys = checklist.map((step, index) => step.key || `step-${index}`);
                    reviewState.checkedKeys.clear();
                    reviewState.blocked = checklist.some((step) => step.status === 'blocked');

                    el.assistantChecklist.innerHTML = checklist.map((step, index) => {
                        const tone = checklistTone(step.status);
                        const stepKey = step.key || `step-${index}`;
                        const reviewScope = step.review_scope || '';
                        const correctionAction = step.correction_action || '';
                        const actionLabel = step.action_label || 'Buka langkah ini';
                        const mustFix = step.status === 'blocked';
                        const userTask = mustFix
                            ? 'Perbaiki masalah ini dulu. Setelah datanya benar, muat ulang halaman dan cek lagi.'
                            : (step.status === 'review' || step.status === 'warning'
                                ? 'Cek data ini. Kalau angkanya salah, buka halaman koreksi lalu kembali ke sini.'
                                : 'Pastikan ringkasan ini sudah sesuai kondisi usaha.');
                        const guidanceHtml = reviewScope || correctionAction
                            ? `<div class="mt-3 grid gap-2 text-xs leading-relaxed">
                                ${reviewScope ? `<div class="rounded-xl bg-white/70 px-3 py-2 dark:bg-slate-950/40"><span class="font-bold text-slate-700 dark:text-slate-200">Data dicek:</span> <span class="text-slate-500 dark:text-slate-400">${reviewScope}</span></div>` : ''}
                                ${correctionAction ? `<div class="rounded-xl bg-white/70 px-3 py-2 dark:bg-slate-950/40"><span class="font-bold text-slate-700 dark:text-slate-200">Kalau salah:</span> <span class="text-slate-500 dark:text-slate-400">${correctionAction}</span></div>` : ''}
                            </div>`
                            : '';
                        return `
                            <article class="rounded-2xl border p-4 shadow-sm ${tone.card}">
                                <div class="flex items-start gap-3">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl ${tone.iconWrap}">
                                        <span class="material-symbols-outlined text-[20px]">${tone.icon}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Langkah ${index + 1}</p>
                                                <h4 class="mt-1 text-sm font-bold text-slate-900 dark:text-white">${step.title || '-'}</h4>
                                            </div>
                                            <span class="shrink-0 text-[10px] font-bold uppercase ${tone.labelClass}">${tone.label}</span>
                                        </div>
                                        <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">${step.description || ''}</p>
                                        <div class="mt-3 rounded-xl border border-slate-200 bg-white/80 px-3 py-2 text-xs leading-relaxed text-slate-600 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-300">
                                            <span class="font-bold text-slate-800 dark:text-slate-100">Yang harus dilakukan:</span> ${userTask}
                                        </div>
                                        <div class="mt-3 flex items-center justify-between gap-3 rounded-xl bg-white/70 px-3 py-2 text-xs dark:bg-slate-950/40">
                                            <span class="font-semibold text-slate-500">${step.metric_label || 'Nilai'}</span>
                                            <span class="font-bold text-slate-900 dark:text-white">${formatMetric(step)}</span>
                                        </div>
                                        ${guidanceHtml}
                                        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            ${step.action_url ? `<a class="inline-flex items-center gap-1 text-xs font-bold text-primary" href="${step.action_url}">${actionLabel} <span class="material-symbols-outlined text-[15px]">arrow_forward</span></a>` : '<span class="text-xs font-semibold text-slate-400">Tidak ada halaman koreksi khusus</span>'}
                                            <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 ${mustFix ? 'opacity-50' : ''}">
                                                <input class="review-checkbox size-4 rounded border-slate-300 text-primary focus:ring-primary" type="checkbox" data-step-key="${stepKey}" ${mustFix ? 'disabled' : ''}>
                                                <span>Sudah saya cek</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        `;
                    }).join('');

                    syncCloseButtonState();
                };

                const apiMessage = async (response, fallback) => {
                    const payload = await response.json().catch(() => ({}));
                    const details = payload?.details && typeof payload.details === 'object'
                        ? Object.values(payload.details).flat().filter(Boolean)
                        : [];

                    return [payload?.message || fallback, ...details].filter(Boolean).join('\n');
                };

                @if ($canManageLockPeriods)
                el.assistantChecklist.addEventListener('change', (event) => {
                    const checkbox = event.target.closest('.review-checkbox');
                    if (!checkbox) {
                        return;
                    }

                    if (checkbox.checked) {
                        reviewState.checkedKeys.add(checkbox.dataset.stepKey);
                    } else {
                        reviewState.checkedKeys.delete(checkbox.dataset.stepKey);
                    }

                    syncCloseButtonState();
                });

                el.create.addEventListener('click', async () => {
                    if (reviewState.blocked) {
                        alert('Masih ada data yang wajib dibereskan. Buka langkah yang bermasalah, koreksi datanya, lalu muat ulang halaman.');
                        return;
                    }
                    if (!reviewState.requiredKeys.every((key) => reviewState.checkedKeys.has(key))) {
                        alert('Centang semua langkah "Sudah saya cek" sebelum tutup buku.');
                        return;
                    }
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
                        headers: writeHeaders,
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
                            headers: writeHeaders,
                            body: JSON.stringify({ reason: reason.trim() }),
                        });
                    } else {
                        response = await fetch(`/api/v1/lock-periods/${periodId}`, {
                        method: 'PATCH',
                        headers: writeHeaders,
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
                    const [assistant, periods] = await Promise.all([
                        loadAssistant(),
                        loadPeriods(),
                    ]);
                    renderAssistant(assistant);
                    renderPeriods(periods);
                })();
            })();
        </script>
@endsection
