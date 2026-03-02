@extends('layouts.app')

@section('page_title', 'Manajemen Lokasi & Cabang')
@section('body_class', 'font-display bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-slate-950 shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@section('header')
    <x-page-header title="Manajemen Cabang" backUrl="/menu/settings">
        <x-slot name="actions">
            <a class="flex items-center justify-center rounded-full size-10 bg-primary/10 text-primary hover:bg-primary/20 transition-colors" href="/branches/edit">
                <span class="material-symbols-outlined">add</span>
            </a>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
        <div class="px-4 py-4 space-y-4">
            <label class="flex flex-col w-full">
                <div class="flex w-full items-stretch rounded-xl h-12 bg-slate-100 dark:bg-slate-900 border border-transparent focus-within:border-primary/30 transition-all">
                    <div class="text-slate-400 flex items-center justify-center pl-4">
                        <span class="material-symbols-outlined">search</span>
                    </div>
                    <input id="search-input" class="form-input flex w-full border-none bg-transparent focus:ring-0 h-full placeholder:text-slate-400 px-3 text-sm font-medium" placeholder="Cari nama cabang atau lokasi..." value="" />
                </div>
            </label>
            <div class="flex gap-2 overflow-x-auto pb-1 no-scrollbar">
                <button class="flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full bg-primary text-white px-5 text-xs font-semibold">Semua Cabang</button>
                <button class="flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 px-5 text-xs font-semibold border border-slate-200 dark:border-slate-800">Aktif</button>
                <button class="flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 px-5 text-xs font-semibold border border-slate-200 dark:border-slate-800">Gudang</button>
            </div>
        </div>

        <div class="mx-4 mb-4 p-4 rounded-xl bg-primary/5 border border-primary/10 flex items-center gap-4" id="default-banner"></div>

        <div class="flex-1 px-4 space-y-3 pb-24">
            <h3 class="text-slate-400 text-[11px] font-bold uppercase tracking-widest px-1" id="branch-count">Daftar Cabang (0)</h3>
            <div id="branch-list" class="space-y-3"></div>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    banner: document.getElementById('default-banner'),
                    list: document.getElementById('branch-list'),
                    count: document.getElementById('branch-count'),
                    search: document.getElementById('search-input'),
                };

                const state = {
                    branches: [],
                    timezone: 'UTC',
                    term: '',
                };

                const renderBanner = () => {
                    const defaultBranch = state.branches.find((branch) => branch.default_location) || state.branches[0];
                    if (!defaultBranch) {
                        el.banner.innerHTML = '<div class="text-sm text-slate-400">Belum ada cabang.</div>';
                        return;
                    }
                    const defaultLocation = defaultBranch.default_location?.name || 'Lokasi Utama';
                    el.banner.innerHTML = `
                        <div class="size-10 rounded-full bg-primary/10 flex items-center justify-center text-primary shrink-0">
                            <span class="material-symbols-outlined">share_location</span>
                        </div>
                        <div class="flex-1">
                            <p class="text-[10px] uppercase tracking-wider font-bold text-primary/70">Lokasi Perangkat Ini</p>
                            <p class="text-sm font-bold text-slate-900 dark:text-slate-100">${defaultLocation}</p>
                        </div>
                        <a class="text-xs font-bold text-primary px-3 py-1.5 rounded-lg bg-white shadow-sm border border-primary/10" href="/branches/edit?branch_id=${defaultBranch.id}">Ubah</a>
                    `;
                };

                const renderBranches = () => {
                    const term = state.term.toLowerCase();
                    const filtered = state.branches.filter((branch) => {
                        if (!term) {
                            return true;
                        }
                        if (branch.name.toLowerCase().includes(term)) {
                            return true;
                        }
                        return branch.locations.some((location) => location.name.toLowerCase().includes(term));
                    });

                    el.count.textContent = `Daftar Cabang (${filtered.length})`;
                    el.list.innerHTML = '';

                    filtered.forEach((branch, index) => {
                        const isDefault = Boolean(branch.default_location);
                        const isActive = branch.is_active !== false;
                        const row = document.createElement('div');
                        row.className = `p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 shadow-sm${!isActive ? ' opacity-60' : ''}${!isDefault && index === 3 ? ' opacity-70' : ''}`;
                        row.innerHTML = `
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="size-2 rounded-full bg-emerald-500"></span>
                                    <h4 class="font-bold text-slate-900 dark:text-slate-100">${branch.name}</h4>
                                </div>
                                <div class="flex gap-1">
                                    <a class="size-8 flex items-center justify-center text-slate-400 hover:text-primary transition-colors" href="/branches/edit?branch_id=${branch.id}">
                                        <span class="material-symbols-outlined text-xl">edit</span>
                                    </a>
                                    <button class="size-8 flex items-center justify-center text-slate-400 hover:text-red-500 transition-colors" data-branch-id="${branch.id}">
                                        <span class="material-symbols-outlined text-xl">delete</span>
                                    </button>
                                </div>
                            </div>
                            <div class="space-y-2 mb-4">
                                <div class="flex items-start gap-2 text-slate-500 dark:text-slate-400">
                                    <span class="material-symbols-outlined text-sm mt-0.5">location_on</span>
                                    <p class="text-xs leading-relaxed">${branch.address || 'Alamat belum diisi'}</p>
                                </div>
                                <div class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                                    <span class="material-symbols-outlined text-sm">schedule</span>
                                    <p class="text-xs">${state.timezone}</p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between pt-3 border-t border-slate-50 dark:border-slate-800">
                                ${isDefault ? '<span class="text-[10px] font-bold text-primary bg-primary/10 px-2 py-0.5 rounded uppercase">Default Device</span>' : '<div class="w-1"></div>'}
                                ${isDefault
                                    ? '<button class="text-xs font-semibold text-slate-400 flex items-center gap-1 opacity-50 cursor-not-allowed"><span class="material-symbols-outlined text-sm">check_circle</span>Terpilih</button>'
                                    : '<button class="text-xs font-semibold text-primary flex items-center gap-1 hover:underline"><span class="material-symbols-outlined text-sm">pin</span>Set Default</button>'}
                            </div>
                        `;
                        el.list.appendChild(row);
                    });
                };

                const loadBranches = async () => {
                    const response = await fetch('/api/v1/branches', { headers });
                    if (!response.ok) {
                        return [];
                    }
                    const data = await response.json();
                    return data.branches || [];
                };

                const loadBootstrap = async () => {
                    const response = await fetch('/api/v1/bootstrap', { headers });
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                };

                el.search.addEventListener('input', (event) => {
                    state.term = event.target.value;
                    renderBranches();
                });

                el.list.addEventListener('click', async (event) => {
                    const target = event.target.closest('button[data-branch-id]');
                    if (!target) {
                        return;
                    }
                    const branchId = target.dataset.branchId;
                    if (!branchId) {
                        return;
                    }
                    if (!confirm('Nonaktifkan cabang ini?')) {
                        return;
                    }

                    const branch = state.branches.find((item) => item.id === branchId);
                    if (!branch) {
                        return;
                    }

                    await fetch(`/api/v1/branches/${branchId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            ...headers,
                        },
                        body: JSON.stringify({
                            name: branch.name,
                            phone: branch.phone,
                            address: branch.address,
                            is_active: false,
                            default_location_id: branch.default_location?.id || null,
                        }),
                    });

                    state.branches = await loadBranches();
                    renderBanner();
                    renderBranches();
                });

                (async () => {
                    const [branches, bootstrap] = await Promise.all([loadBranches(), loadBootstrap()]);
                    state.branches = branches;
                    state.timezone = bootstrap?.tenant?.timezone || 'UTC';
                    renderBanner();
                    renderBranches();
                })();
            })();
        </script>
@endsection
