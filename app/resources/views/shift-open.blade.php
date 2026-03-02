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
    <x-page-header title="Buka Shift" backUrl="/menu/shift" :showMenuButton="true">
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
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Selamat Datang</h2>
        <p class="text-slate-500 text-sm mt-1">Silakan isi informasi modal awal untuk memulai shift Anda hari ini.</p>
    </div>
    <div class="space-y-6">
        <div id="cash-drawer-guard" class="hidden rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm font-semibold text-amber-800">Cash drawer belum disiapkan.</p>
            <p class="text-xs text-amber-700 mt-1">Buat akun kas warung terlebih dahulu sebelum membuka shift.</p>
            <a href="/settings/chart-of-accounts" class="mt-3 inline-flex items-center gap-1 rounded-lg bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-800">
                <span class="material-symbols-outlined text-sm">account_balance</span>
                Buat Akun Kas
            </a>
        </div>
        <div class="space-y-2">
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest ml-1">Lokasi Cabang</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <span class="material-symbols-outlined text-slate-400 text-xl">store</span>
                </div>
                <select id="branch-select" class="w-full bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 rounded-2xl py-4 pl-12 pr-10 text-sm font-medium focus:ring-primary focus:border-primary appearance-none ios-shadow"></select>
                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                    <span class="material-symbols-outlined text-slate-400">expand_more</span>
                </div>
            </div>
        </div>
        <div class="space-y-2">
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest ml-1">Modal Awal (Kas)</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <span class="text-lg font-bold text-slate-400">Rp</span>
                </div>
                <input id="opening-cash" class="w-full bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 rounded-2xl py-4 pl-12 pr-4 text-xl font-bold focus:ring-primary focus:border-primary ios-shadow" placeholder="0" type="number" value="0" />
            </div>
            <p class="text-[11px] text-slate-400 italic ml-1">*Pastikan jumlah uang di laci sesuai dengan input.</p>
        </div>
        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-2xl border border-blue-100 dark:border-blue-800/50 flex gap-3">
            <span class="material-symbols-outlined text-blue-500">info</span>
            <div class="text-xs text-blue-700 dark:text-blue-300 leading-relaxed" id="shift-info">Data shift akan tercatat otomatis.</div>
        </div>
    </div>
    <footer class="mt-8 p-6 bg-white/80 dark:bg-background-dark/80 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 pb-10">
        <button id="open-shift" class="w-full bg-primary text-white py-4 rounded-2xl font-bold text-lg shadow-lg shadow-primary/30 active:scale-[0.98] transition-transform">Buka Shift Sekarang</button>
        <div class="mt-6 flex justify-center">
            <div class="w-32 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
    </footer>
    <div class="absolute top-20 -right-20 w-40 h-40 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-40 -left-20 w-40 h-40 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    branch: document.getElementById('branch-select'),
                    cash: document.getElementById('opening-cash'),
                    info: document.getElementById('shift-info'),
                    badge: document.getElementById('shift-badge'),
                    button: document.getElementById('open-shift'),
                    guard: document.getElementById('cash-drawer-guard'),
                };

                let shiftOpened = false;

                const formatTime = (value) => new Intl.DateTimeFormat('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    timeZoneName: 'short',
                }).format(new Date(value));

                const loadOpeningData = async () => {
                    const response = await fetch('/api/v1/shifts/opening', { headers });
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                };

                const renderBranches = (branches) => {
                    el.branch.innerHTML = '';
                    if (!branches.length) {
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'Belum ada cabang';
                        el.branch.appendChild(option);
                        return;
                    }
                    branches.forEach((branch) => {
                        const option = document.createElement('option');
                        option.value = branch.id;
                        option.textContent = branch.name;
                        el.branch.appendChild(option);
                    });
                };

                el.button.addEventListener('click', async () => {
                    if (shiftOpened) {
                        location.href = '/kasir';
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
                        headers: {
                            'Content-Type': 'application/json',
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
                });

                (async () => {
                    const data = await loadOpeningData();
                    if (!data) {
                        el.info.textContent = 'Gagal memuat data shift.';
                        return;
                    }
                    if (!data.cash_drawer) {
                        el.guard.classList.remove('hidden');
                        el.button.disabled = true;
                        el.button.classList.add('opacity-60', 'cursor-not-allowed');
                    }
                    renderBranches(data.branches || []);
                    el.badge.textContent = `Shift #${String(data.shift_number || 1).padStart(2, '0')}`;
                    const cashierName = data.cashier?.name || 'Kasir';
                    const openedAt = data.opened_at ? formatTime(data.opened_at) : '-';
                    const cashDrawerName = data.cash_drawer?.name || 'Kas Warung';
                    el.info.innerHTML = `Data shift akan tercatat otomatis atas nama <strong>Kasir: ${cashierName}</strong> pada pukul <strong>${openedAt}</strong> menggunakan akun kas <strong>${cashDrawerName}</strong>.`;
                })();
            })();
        </script>
    @endsection





