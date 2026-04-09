@php
    $branchTenant = \App\Support\TenantContext::get();
    $branchUser = auth()->user();
    $branchRole = null;

    if (! $branchTenant && filled($branchUser?->tenant_id)) {
        $branchTenant = \App\Models\Tenant::query()->find($branchUser->tenant_id);
    }

    if ($branchTenant && $branchUser) {
        $branchRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $branchTenant->id, (string) $branchUser->id);

        if (! $branchRole && filled($branchUser->role ?? null)) {
            $branchRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $branchUser->role);
        }
    }

    abort_unless(in_array($branchRole, ['admin', 'owner', 'superadmin'], true), 403);

    $branchCurrentPath = $currentPath ?? ($branchId ? '/backoffice/branches/'.$branchId.'/edit' : '/backoffice/branches/edit');
    $branchBackUrl = $backUrl ?? '/backoffice/branches';
    $resolvedBranchId = (string) ($branchId ?? request()->query('branch_id', ''));
@endphp

@extends('layouts.app')

@section('page_title', 'Tambah & Edit Cabang')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
        <style>
            .ios-switch:checked ~ .ios-switch-dot {
                transform: translateX(1.25rem);
                background-color: #ffffff;
            }
            .ios-switch:checked ~ .ios-switch-bg {
                background-color: #135bec;
            }
        </style>
@endpush

@section('header')
    <x-page-header title="Kelola Cabang" :backUrl="$branchBackUrl" :showMenuButton="true">
        <x-slot name="actions">
            <button id="save-top" class="text-primary font-semibold text-sm">Simpan</button>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
        <div class="flex-1 overflow-y-auto pb-32">
            <div class="p-4 space-y-6">
                <div class="space-y-4">
                    <div class="flex items-center gap-2 px-1">
                        <span class="material-symbols-outlined text-primary text-xl">store</span>
                        <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wider">Informasi Cabang</h2>
                    </div>
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                        <div class="p-4 border-b border-slate-100 dark:border-slate-800">
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1.5">Nama Cabang</label>
                            <input id="branch-name" class="w-full bg-transparent border-none p-0 text-base focus:ring-0 placeholder:text-slate-300 font-medium" placeholder="Contoh: Cabang Sudirman" type="text" />
                        </div>
                        <div class="p-4 border-b border-slate-100 dark:border-slate-800">
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1.5">Nomor Telepon</label>
                            <div class="flex items-center gap-2">
                                <span class="text-slate-400 text-sm font-medium">+62</span>
                                <input id="branch-phone" class="w-full bg-transparent border-none p-0 text-base focus:ring-0 placeholder:text-slate-300 font-medium" placeholder="812-3456-7890" type="tel" />
                            </div>
                        </div>
                        <div class="p-4">
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1.5">Alamat Lengkap</label>
                            <textarea id="branch-address" class="w-full bg-transparent border-none p-0 text-base focus:ring-0 placeholder:text-slate-300 font-medium resize-none min-h-[80px]" placeholder="Jl. Jendral Sudirman No. 123..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center gap-2 px-1">
                        <span class="material-symbols-outlined text-primary text-xl">settings</span>
                        <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wider">Pengaturan</h2>
                    </div>
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                        <div class="p-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold">Jadikan Lokasi Utama</p>
                                <p class="text-[11px] text-slate-500">Cabang ini akan dipilih secara otomatis saat login</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input id="branch-default" class="sr-only ios-switch" type="checkbox" />
                                <div class="ios-switch-bg w-11 h-6 bg-slate-200 dark:bg-slate-700 rounded-full transition-colors duration-200"></div>
                                <div class="ios-switch-dot absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full shadow-sm transition-transform duration-200"></div>
                            </label>
                        </div>
                        <div class="h-px bg-slate-100 dark:bg-slate-800 mx-4"></div>
                        <div class="p-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-bold">Status Cabang Aktif</p>
                                <p class="text-[11px] text-slate-500">Nonaktifkan untuk menyembunyikan cabang dari sistem</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input id="branch-active" class="sr-only ios-switch" type="checkbox" checked />
                                <div class="ios-switch-bg w-11 h-6 bg-slate-200 dark:bg-slate-700 rounded-full transition-colors duration-200"></div>
                                <div class="ios-switch-dot absolute left-0.5 top-0.5 bg-white w-5 h-5 rounded-full shadow-sm transition-transform duration-200"></div>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="pt-4">
                    <button id="delete-branch" class="w-full flex items-center justify-center gap-2 text-red-500 font-semibold py-4 bg-red-50 dark:bg-red-900/10 rounded-2xl border border-red-100 dark:border-red-900/20">
                        <span class="material-symbols-outlined">delete</span>
                        Hapus Cabang
                    </button>
                </div>
            </div>
        </div>
        <div class="fixed bottom-0 left-1/2 w-full max-w-[430px] -translate-x-1/2 bg-white/90 dark:bg-background-dark/90 backdrop-blur-xl border-t border-slate-200 dark:border-slate-800 p-4 pb-8">
            <button id="save-bottom" class="w-full bg-primary text-white py-4 rounded-xl font-bold flex items-center justify-center gap-2 shadow-lg shadow-primary/20">Simpan Perubahan</button>
            <div class="mt-4 flex justify-center">
                <div class="w-32 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
            </div>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
                const currentPath = @json($branchCurrentPath);

                const el = {
                    name: document.getElementById('branch-name'),
                    phone: document.getElementById('branch-phone'),
                    address: document.getElementById('branch-address'),
                    isDefault: document.getElementById('branch-default'),
                    isActive: document.getElementById('branch-active'),
                    saveTop: document.getElementById('save-top'),
                    saveBottom: document.getElementById('save-bottom'),
                    delete: document.getElementById('delete-branch'),
                };

                let branchId = @json($resolvedBranchId) || new URLSearchParams(window.location.search).get('branch_id');
                let defaultLocationId = null;

                const loadBranch = async () => {
                    if (!branchId) {
                        return null;
                    }
                    const response = await fetch(`/api/v1/branches/${branchId}`, { headers });
                    if (!response.ok) {
                        return null;
                    }
                    return response.json();
                };

                const saveBranch = async () => {
                    const payload = {
                        name: el.name.value.trim(),
                        phone: el.phone.value.trim() || null,
                        address: el.address.value.trim() || null,
                        is_active: el.isActive.checked,
                        default_location_id: el.isDefault.checked ? defaultLocationId : null,
                    };

                    if (!payload.name) {
                        alert('Nama cabang wajib diisi.');
                        return;
                    }

                    const response = await fetch(branchId ? `/api/v1/branches/${branchId}` : '/api/v1/branches', {
                        method: branchId ? 'PUT' : 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            ...headers,
                        },
                        body: JSON.stringify(payload),
                    });

                    if (!response.ok) {
                        alert('Gagal menyimpan cabang.');
                        return;
                    }

                    const data = await response.json();
                    if (!branchId) {
                        branchId = data.branch_id;
                        if (branchId) {
                            const nextPath = `/backoffice/branches/${encodeURIComponent(branchId)}/edit`;
                            window.history.replaceState(null, '', nextPath);
                        }
                    }
                    alert('Perubahan disimpan.');
                };

                el.saveTop.addEventListener('click', saveBranch);
                el.saveBottom.addEventListener('click', saveBranch);

                el.delete.addEventListener('click', async () => {
                    if (!branchId) {
                        return;
                    }
                    if (!confirm('Nonaktifkan cabang ini?')) {
                        return;
                    }
                    el.isActive.checked = false;
                    await saveBranch();
                });

                (async () => {
                    const data = await loadBranch();
                    if (!data) {
                        el.delete.classList.add('hidden');
                        return;
                    }
                    el.name.value = data.branch?.name || '';
                    el.phone.value = data.branch?.phone ? data.branch.phone.replace(/^\+?62/, '') : '';
                    el.address.value = data.branch?.address || '';
                    el.isActive.checked = data.branch?.is_active ?? true;
                    defaultLocationId = data.default_location?.id || data.locations?.[0]?.id || null;
                    el.isDefault.checked = Boolean(defaultLocationId);
                })();
            })();
        </script>
@endsection
