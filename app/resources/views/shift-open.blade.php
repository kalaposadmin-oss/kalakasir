@extends('layouts.app')

@section('page_title', 'Buka Shift Kasir')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'relative flex-1 overflow-y-auto px-6 pt-8')

@push('head')
        <style type="text/tailwindcss">
            .ios-shadow {
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            }
        </style>
@endpush

@section('header')
    <x-page-header title="Buka Shift" :backUrl="pos_default_url()" :showMenuButton="true">
        <x-slot:actions>
            <div id="shift-badge" class="text-xs font-semibold text-primary bg-primary/10 px-3 py-1 rounded-full">Shift #01</div>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <div class="mb-8 text-center">
        <div class="size-20 bg-primary/10 rounded-3xl flex items-center justify-center mx-auto mb-4">
            <span class="material-symbols-outlined text-primary text-4xl">point_of_sale</span>
        </div>
        <h2 id="opening-heading" class="text-2xl font-bold text-slate-900 dark:text-white">Selamat Datang</h2>
        <p id="opening-subtitle" class="text-slate-500 text-sm mt-1">Silakan isi informasi modal awal untuk memulai shift Anda hari ini.</p>
    </div>
    <div class="space-y-6">
        <div id="reconciliation-guard" class="hidden rounded-2xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-sm font-semibold text-rose-800">Shift kemarin ditutup otomatis oleh sistem.</p>
            <p id="reconciliation-copy" class="mt-1 text-xs leading-relaxed text-rose-700">Masukkan kas akhir fisik dari shift yang belum sempat ditutup manual. Ini harus diselesaikan dulu sebelum buka shift baru.</p>
        </div>
        <div id="cash-drawer-guard" class="hidden rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm font-semibold text-amber-800">Akun kas default sedang belum siap.</p>
            <p class="text-xs text-amber-700 mt-1">Normalnya akun Kas dibuat otomatis saat toko didaftarkan. Muat ulang halaman dulu. Jika tetap muncul, baru cek pengaturan akun kas di Backoffice.</p>
            <a href="/backoffice/settings/chart-of-accounts" class="mt-3 inline-flex items-center gap-1 rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-800">
                <span class="material-symbols-outlined text-sm">account_balance</span>
                Cek Akun Kas
            </a>
        </div>
        <div id="branch-section" class="space-y-2">
            <label id="branch-label" class="block text-xs font-bold text-slate-400 uppercase tracking-widest ml-1">Cabang Operasional</label>
            <div id="branch-select-wrap" class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <span class="material-symbols-outlined text-slate-400 text-xl">store</span>
                </div>
                <select id="branch-select" class="w-full bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 rounded-2xl py-4 pl-12 pr-10 text-sm font-medium focus:ring-primary focus:border-primary appearance-none ios-shadow"></select>
                <div id="branch-chevron" class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                    <span class="material-symbols-outlined text-slate-400">expand_more</span>
                </div>
            </div>
            <div id="branch-fixed-card" class="hidden items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm font-semibold text-slate-700 ios-shadow">
                <span class="material-symbols-outlined text-slate-400">storefront</span>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Cabang yang Dipakai</p>
                    <p id="branch-fixed-name" class="truncate text-sm font-semibold text-slate-800">Cabang Utama</p>
                </div>
            </div>
            <p id="branch-helper" class="ml-1 text-xs leading-relaxed text-slate-500">Saat pertama daftar, sistem biasanya membuat 1 cabang utama otomatis untuk toko Anda.</p>
        </div>
        <div class="space-y-2">
            <label id="cash-label" class="block text-xs font-bold text-slate-400 uppercase tracking-widest ml-1">Modal Awal (Kas)</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <span class="text-lg font-bold text-slate-400">Rp</span>
                </div>
                <input id="opening-cash" class="w-full bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 rounded-2xl py-4 pl-12 pr-4 text-xl font-bold focus:ring-primary focus:border-primary ios-shadow" placeholder="0" type="number" value="0" />
            </div>
            <div id="opening-cash-quick" class="flex gap-2 overflow-x-auto no-scrollbar pt-1"></div>
            <p class="text-[11px] text-slate-400 italic ml-1">*Pastikan jumlah uang di laci sesuai dengan input.</p>
        </div>
        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-2xl border border-blue-100 dark:border-blue-800/50 flex gap-3">
            <span class="material-symbols-outlined text-blue-500">info</span>
            <div class="text-xs text-blue-700 dark:text-blue-300 leading-relaxed" id="shift-info">Saat shift dibuka, sistem otomatis menyimpan nama petugas, waktu buka, dan akun kas yang dipakai.</div>
        </div>
    </div>
    <footer class="mt-8 p-6 bg-white/80 dark:bg-background-dark/80 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 pb-10">
        <button id="open-shift" class="w-full bg-primary text-white py-4 rounded-2xl font-bold text-lg shadow-lg shadow-primary/30 active:scale-[0.98] transition-transform">Buka Shift Sekarang</button>
        <p id="open-shift-caption" class="mt-3 text-center text-xs font-medium text-slate-500">Pilih cabang dan isi modal awal sebelum membuka shift.</p>
        <div class="mt-6 flex justify-center">
            <div class="w-32 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
    </footer>
    <div class="absolute top-20 -right-20 w-40 h-40 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-40 -left-20 w-40 h-40 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    branch: document.getElementById('branch-select'),
                    branchWrap: document.getElementById('branch-select-wrap'),
                    branchLabel: document.getElementById('branch-label'),
                    branchHelper: document.getElementById('branch-helper'),
                    branchChevron: document.getElementById('branch-chevron'),
                    branchFixedCard: document.getElementById('branch-fixed-card'),
                    branchFixedName: document.getElementById('branch-fixed-name'),
                    cash: document.getElementById('opening-cash'),
                    info: document.getElementById('shift-info'),
                    badge: document.getElementById('shift-badge'),
                    button: document.getElementById('open-shift'),
                    guard: document.getElementById('cash-drawer-guard'),
                    quickCash: document.getElementById('opening-cash-quick'),
                    caption: document.getElementById('open-shift-caption'),
                };

                let shiftOpened = false;
                let pendingReconciliation = null;

                const formatTime = (value) => new Intl.DateTimeFormat('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    timeZoneName: 'short',
                }).format(new Date(value));

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const loadOpeningData = async () => {
                    const response = await fetch('/api/v1/shifts/opening', { headers });
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                };

                const renderBranches = (branches) => {
                    el.branch.innerHTML = '';
                    el.branch.disabled = false;
                    el.branch.classList.remove('hidden');
                    el.branchWrap.classList.remove('hidden');
                    el.branch.classList.remove('bg-slate-50', 'text-slate-500', 'cursor-not-allowed');
                    el.branchChevron.classList.remove('hidden');
                    el.branchFixedCard.classList.add('hidden');
                    el.branchFixedCard.classList.remove('flex');

                    if (!branches.length) {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'Belum ada cabang';
                        el.branch.appendChild(option);
                        el.branch.disabled = true;
                        return;
                    }

                    if (branches.length > 1) {
                        const placeholder = document.createElement('option');
                        placeholder.value = '';
                        placeholder.textContent = 'Pilih cabang';
                        placeholder.selected = true;
                        el.branch.appendChild(placeholder);
                    }

                    branches.forEach((branch) => {
                        const option = document.createElement('option');
                        option.value = branch.id;
                        option.textContent = branch.name;
                        option.selected = branches.length === 1;
                        el.branch.appendChild(option);
                    });

                    if (branches.length === 1) {
                        el.branch.disabled = true;
                        el.branch.classList.add('bg-slate-50', 'text-slate-500', 'cursor-not-allowed');
                        el.branch.classList.add('hidden');
                        el.branchWrap.classList.add('hidden');
                        el.branchChevron.classList.add('hidden');
                        el.branchFixedCard.classList.remove('hidden');
                        el.branchFixedCard.classList.add('flex');
                        el.branchFixedName.textContent = branches[0].name || 'Cabang Utama';
                    }
                };

                const syncBranchCopy = (branches) => {
                    if (!branches.length) {
                        el.branchLabel.textContent = 'Cabang Operasional';
                        el.branchHelper.textContent = 'Sistem belum menemukan cabang aktif. Tambahkan cabang dari Backoffice sebelum membuka shift.';
                        return;
                    }

                    if (branches.length === 1) {
                        el.branchLabel.textContent = 'Cabang Aktif';
                        el.branchHelper.textContent = 'Cabang utama dibuat otomatis saat pendaftaran, jadi Anda bisa langsung lanjut buka shift tanpa memilih lagi.';
                        return;
                    }

                    el.branchLabel.textContent = 'Pilih Cabang Operasional';
                    el.branchHelper.textContent = 'Pilih cabang tempat kasir ini sedang beroperasi.';
                };

                const renderQuickCash = () => {
                    const presets = pendingReconciliation
                        ? [
                            Math.max(0, Math.floor(pendingReconciliation.expected_cash || 0)),
                            Math.max(0, Math.floor((pendingReconciliation.expected_cash || 0) - 5000)),
                            Math.max(0, Math.floor((pendingReconciliation.expected_cash || 0) + 5000)),
                        ]
                        : [0, 50000, 100000, 200000];
                    el.quickCash.innerHTML = '';
                    presets.forEach((amount) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'whitespace-nowrap rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 ios-shadow';
                        button.textContent = amount === 0 ? 'Tanpa Modal' : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
                        button.addEventListener('click', () => {
                            el.cash.value = String(amount);
                            updateButtonState();
                        });
                        el.quickCash.appendChild(button);
                    });
                };

                const updateButtonState = () => {
                    const hasBranch = Boolean(el.branch.value);
                    const isSingleBranchAutoSelected = el.branch.disabled && hasBranch;
                    const blocked = el.button.hasAttribute('data-hard-blocked');
                    const openingCash = Math.max(0, parseFloat(el.cash.value || '0'));
                    const disabled = blocked || (!pendingReconciliation && !hasBranch);
                    el.button.disabled = disabled;
                    el.button.classList.toggle('opacity-60', disabled);
                    el.button.classList.toggle('cursor-not-allowed', disabled);
                    el.caption.textContent = blocked
                        ? 'Akun kas belum siap. Selesaikan setup kas terlebih dahulu.'
                        : pendingReconciliation
                            ? `Input kas akhir shift kemarin sebesar ${formatCurrency(openingCash)} lalu selesaikan rekonsiliasi.`
                        : !hasBranch
                            ? 'Pilih cabang terlebih dahulu.'
                            : isSingleBranchAutoSelected
                                ? `Cabang utama dipakai otomatis. Shift akan dibuka dengan modal awal ${formatCurrency(openingCash)}.`
                            : `Shift akan dibuka dengan modal awal ${formatCurrency(openingCash)}.`;
                };

                const applyOpeningMode = (data) => {
                    pendingReconciliation = null;
                    el.guard.classList.toggle('hidden', Boolean(data.cash_drawer));
                    el.button.removeAttribute('data-hard-blocked');
                    if (!data.cash_drawer) {
                        el.button.setAttribute('data-hard-blocked', 'true');
                    }
                    document.getElementById('reconciliation-guard').classList.add('hidden');
                    document.getElementById('branch-section').classList.remove('hidden');
                    document.getElementById('opening-heading').textContent = 'Selamat Datang';
                    document.getElementById('opening-subtitle').textContent = 'Silakan isi informasi modal awal untuk memulai shift Anda hari ini.';
                    document.getElementById('cash-label').textContent = 'Modal Awal (Kas)';
                    el.button.textContent = 'Buka Shift Sekarang';
                    el.cash.value = '0';
                    renderBranches(data.branches || []);
                    syncBranchCopy(data.branches || []);
                    el.badge.textContent = `Shift #${String(data.shift_number || 1).padStart(2, '0')}`;
                    const cashierName = data.cashier?.name || 'Kasir';
                    const openedAt = data.opened_at ? formatTime(data.opened_at) : '-';
                    const cashDrawerName = data.cash_drawer?.name || 'Kas Warung';
                    el.info.innerHTML = `Saat shift dibuka, sistem akan otomatis mencatat <strong>petugas: ${cashierName}</strong>, <strong>waktu buka: ${openedAt}</strong>, dan <strong>akun kas: ${cashDrawerName}</strong>.`;
                    renderQuickCash();
                    updateButtonState();
                };

                const applyReconciliationMode = (data) => {
                    pendingReconciliation = data.pending_reconciliation || null;
                    document.getElementById('reconciliation-guard').classList.remove('hidden');
                    document.getElementById('reconciliation-copy').textContent = pendingReconciliation?.message || 'Selesaikan kas akhir shift kemarin terlebih dahulu.';
                    document.getElementById('branch-section').classList.add('hidden');
                    document.getElementById('opening-heading').textContent = 'Selesaikan Shift Kemarin';
                    document.getElementById('opening-subtitle').textContent = 'Shift sebelumnya ditutup otomatis setelah lewat 00.00. Masukkan kas akhir fisik dulu sebelum buka shift baru.';
                    document.getElementById('cash-label').textContent = 'Kas Akhir Shift Sebelumnya';
                    el.button.removeAttribute('data-hard-blocked');
                    el.button.textContent = 'Simpan Kas Akhir Shift Kemarin';
                    el.badge.textContent = 'Perlu Rekonsiliasi';
                    el.cash.value = String(Math.max(0, Math.floor(pendingReconciliation?.expected_cash || 0)));
                    const cashierName = data.cashier?.name || 'Kasir';
                    el.info.innerHTML = `Shift <strong>${cashierName}</strong> yang dibuka ${pendingReconciliation?.opened_at || '-'} ditutup otomatis pada ${pendingReconciliation?.closed_at || '-'} dengan ekspektasi kas <strong>${formatCurrency(pendingReconciliation?.expected_cash || 0)}</strong>.`;
                    renderQuickCash();
                    updateButtonState();
                };

                el.button.addEventListener('click', async () => {
                    if (shiftOpened) {
                        location.href = '/pos/checkout';
                        return;
                    }

                    if (pendingReconciliation?.shift_id) {
                        const closingCash = parseFloat(el.cash.value || '0');
                        const response = await fetch('/api/v1/shifts/reconcile-overnight', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                                ...headers,
                            },
                            body: JSON.stringify({
                                shift_id: pendingReconciliation.shift_id,
                                closing_cash: closingCash,
                            }),
                        });
                        if (!response.ok) {
                            let message = 'Gagal menyimpan kas akhir shift kemarin.';
                            try {
                                const errorBody = await response.json();
                                if (errorBody?.message) {
                                    message = errorBody.message;
                                }
                            } catch (_) {
                            }
                            alert(message);
                            return;
                        }

                        const refreshed = await loadOpeningData();
                        if (refreshed) {
                            applyOpeningMode(refreshed);
                        }
                        return;
                    }

                    const branchId = el.branch.value;
                    if (!branchId) {
                        alert('Pilih cabang terlebih dahulu.');
                        return;
                    }
                    const openingCash = parseFloat(el.cash.value || '0');
                    const response = await fetch('/api/v1/shifts/open', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                            ...headers,
                        },
                        body: JSON.stringify({
                            branch_id: branchId,
                            opening_cash: openingCash,
                        }),
                    });
                    if (!response.ok) {
                        let message = 'Gagal membuka shift.';
                        try {
                            const errorBody = await response.json();
                            if (errorBody?.message) {
                                message = errorBody.message;
                            }
                        } catch (_) {
                        }

                        alert(message);
                        return;
                    }
                    shiftOpened = true;
                    el.button.textContent = 'Lanjut ke Kasir';
                    el.caption.textContent = 'Shift berhasil dibuka. Lanjut ke checkout untuk mulai transaksi.';
                });

                el.branch.addEventListener('change', updateButtonState);
                el.cash.addEventListener('input', updateButtonState);

                (async () => {
                    const data = await loadOpeningData();
                    if (!data) {
                        el.info.textContent = 'Gagal memuat data shift.';
                        return;
                    }
                    if (data.pending_reconciliation) {
                        applyReconciliationMode(data);
                        return;
                    }
                    applyOpeningMode(data);
                })();
            })();
        </script>
    @endsection
