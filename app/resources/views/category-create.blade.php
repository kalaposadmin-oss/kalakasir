@extends('layouts.app')

@php
    $categoryCreateTenant = \App\Support\TenantContext::get();
    $categoryCreateUser = auth()->user();
    $categoryCreateRole = null;

    if (! $categoryCreateTenant && filled($categoryCreateUser?->tenant_id)) {
        $categoryCreateTenant = \App\Models\Tenant::query()->find($categoryCreateUser->tenant_id);
    }

    if ($categoryCreateTenant && $categoryCreateUser) {
        $categoryCreateRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $categoryCreateTenant->id, (string) $categoryCreateUser->id);

        if (! $categoryCreateRole && filled($categoryCreateUser->role ?? null)) {
            $categoryCreateRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $categoryCreateUser->role);
        }
    }

    abort_unless(in_array($categoryCreateRole, ['admin', 'owner', 'superadmin'], true), 403);
@endphp

@php
    $categoryCreateListUrl = (request()->is('pos/*') ? '/pos' : '/backoffice').'/categories';
@endphp

@section('page_title', 'Tambah Kategori')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
        <style>
            .icon-selected {
                border-color: var(--color-primary);
                background-color: rgba(249, 115, 22, 0.1);
                color: var(--color-primary);
            }
        </style>
@endpush

@section('header')
    <x-page-header title="Tambah Kategori" :backUrl="$categoryCreateListUrl" :showMenuButton="true" />
@endsection

@section('content')
        <div class="flex-1 overflow-y-auto px-6 py-6 space-y-8 pb-32">
            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-700" for="category_name">Nama Kategori</label>
                <input class="block w-full rounded-xl border-slate-200 bg-slate-50 py-3 px-4 text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary placeholder:text-slate-400 transition-all" id="category_name" name="category_name" placeholder="Contoh: Makanan Ringan" type="text" />
            </div>
            <div class="space-y-2">
                <label class="text-sm font-semibold text-slate-700" for="description">Deskripsi (Opsional)</label>
                <textarea class="block w-full rounded-xl border-slate-200 bg-slate-50 py-3 px-4 text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary placeholder:text-slate-400 transition-all resize-none" id="description" name="description" placeholder="Jelaskan isi dari kategori ini..." rows="3"></textarea>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <label class="text-sm font-semibold text-slate-700">Pilih Ikon</label>
                    <span class="text-xs text-slate-400">Tersedia 8 Ikon</span>
                </div>
                <div class="grid grid-cols-4 gap-3" id="icon-grid">
                    <button class="flex items-center justify-center size-16 rounded-2xl border-2 border-primary bg-primary/5 text-primary transition-all icon-selected" data-icon="restaurant" type="button">
                        <span class="material-symbols-outlined text-3xl">restaurant</span>
                    </button>
                    <button class="flex items-center justify-center size-16 rounded-2xl border-2 border-slate-100 bg-slate-50 text-slate-400 hover:border-slate-300 transition-all" data-icon="devices" type="button">
                        <span class="material-symbols-outlined text-3xl">devices</span>
                    </button>
                    <button class="flex items-center justify-center size-16 rounded-2xl border-2 border-slate-100 bg-slate-50 text-slate-400 hover:border-slate-300 transition-all" data-icon="styler" type="button">
                        <span class="material-symbols-outlined text-3xl">styler</span>
                    </button>
                    <button class="flex items-center justify-center size-16 rounded-2xl border-2 border-slate-100 bg-slate-50 text-slate-400 hover:border-slate-300 transition-all" data-icon="home_repair_service" type="button">
                        <span class="material-symbols-outlined text-3xl">home_repair_service</span>
                    </button>
                    <button class="flex items-center justify-center size-16 rounded-2xl border-2 border-slate-100 bg-slate-50 text-slate-400 hover:border-slate-300 transition-all" data-icon="medication" type="button">
                        <span class="material-symbols-outlined text-3xl">medication</span>
                    </button>
                    <button class="flex items-center justify-center size-16 rounded-2xl border-2 border-slate-100 bg-slate-50 text-slate-400 hover:border-slate-300 transition-all" data-icon="shopping_bag" type="button">
                        <span class="material-symbols-outlined text-3xl">shopping_bag</span>
                    </button>
                    <button class="flex items-center justify-center size-16 rounded-2xl border-2 border-slate-100 bg-slate-50 text-slate-400 hover:border-slate-300 transition-all" data-icon="local_cafe" type="button">
                        <span class="material-symbols-outlined text-3xl">local_cafe</span>
                    </button>
                    <button class="flex items-center justify-center size-16 rounded-2xl border-2 border-slate-100 bg-slate-50 text-slate-400 hover:border-slate-300 transition-all" data-icon="auto_stories" type="button">
                        <span class="material-symbols-outlined text-3xl">auto_stories</span>
                    </button>
                </div>
            </div>
        </div>
        <p id="form-error" class="fixed bottom-24 left-1/2 z-30 hidden w-[calc(100%-3rem)] max-w-[382px] -translate-x-1/2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-600"></p>
        <div class="fixed bottom-0 left-1/2 z-30 w-full max-w-[430px] -translate-x-1/2 bg-white border-t border-slate-100 p-6 app-bottom-safe-footer">
            <button id="save-category" class="w-full bg-primary text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/20 active:scale-[0.98] transition-all flex items-center justify-center gap-2" type="button">
                <span>Simpan Kategori</span>
            </button>
        </div>
        <div class="fixed bottom-2 left-1/2 -translate-x-1/2 w-32 h-1 bg-slate-200 rounded-full"></div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    name: document.getElementById('category_name'),
                    description: document.getElementById('description'),
                    iconGrid: document.getElementById('icon-grid'),
                    save: document.getElementById('save-category'),
                    error: document.getElementById('form-error'),
                };

                let selectedIcon = 'restaurant';

                const showError = (message) => {
                    if (!message) {
                        el.error.classList.add('hidden');
                        el.error.textContent = '';
                        return;
                    }

                    el.error.textContent = message;
                    el.error.classList.remove('hidden');
                };

                const setSelectedIcon = (icon) => {
                    selectedIcon = icon;
                    el.iconGrid.querySelectorAll('button').forEach((button) => {
                        const active = button.dataset.icon === icon;
                        button.classList.toggle('icon-selected', active);
                        button.classList.toggle('border-slate-100', !active);
                        button.classList.toggle('bg-slate-50', !active);
                        button.classList.toggle('text-slate-400', !active);
                    });
                };

                el.iconGrid.addEventListener('click', (event) => {
                    const button = event.target.closest('button[data-icon]');
                    if (!button) {
                        return;
                    }
                    setSelectedIcon(button.dataset.icon);
                });

                const createCategory = async () => {
                    showError('');
                    const name = el.name.value.trim();
                    const description = el.description.value.trim();
                    if (!name) {
                        showError('Nama kategori wajib diisi.');
                        el.name.focus();
                        return;
                    }

                    el.save.disabled = true;

                    try {
                        const response = await window.PosUi.fetchWithTimeout('/api/v1/categories', {
                            method: 'POST',
                            headers: {
                                ...headers,
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                name,
                                description: description || null,
                                icon: selectedIcon,
                            }),
                        });

                        if (!response.ok) {
                            const mapped = await window.PosUi.mapApiError(response, 'Gagal menyimpan kategori.');
                            const validationMessage = Object.values(mapped?.payload?.errors || mapped?.payload?.details || {})[0]?.[0];
                            throw new Error(validationMessage || mapped?.message || 'Gagal menyimpan kategori.');
                        }

                        window.location.href = @json($categoryCreateListUrl);
                    } catch (error) {
                        showError(error?.message || 'Gagal menyimpan kategori.');
                    } finally {
                        el.save.disabled = false;
                    }
                };

                el.save.addEventListener('click', createCategory);
            })();
        </script>
@endsection
