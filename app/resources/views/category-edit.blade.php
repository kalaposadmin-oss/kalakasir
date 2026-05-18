@extends('layouts.app')

@php
    $categoryEditTenant = \App\Support\TenantContext::get();
    $categoryEditUser = auth()->user();
    $categoryEditRole = null;

    if (! $categoryEditTenant && filled($categoryEditUser?->tenant_id)) {
        $categoryEditTenant = \App\Models\Tenant::query()->find($categoryEditUser->tenant_id);
    }

    if ($categoryEditTenant && $categoryEditUser) {
        $categoryEditRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $categoryEditTenant->id, (string) $categoryEditUser->id);

        if (! $categoryEditRole && filled($categoryEditUser->role ?? null)) {
            $categoryEditRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $categoryEditUser->role);
        }
    }

    abort_unless(in_array($categoryEditRole, ['admin', 'owner', 'superadmin'], true), 403);
@endphp

@php
    $categoryEditListUrl = (request()->is('pos/*') ? '/pos' : '/backoffice').'/categories';
@endphp

@section('page_title', 'Ubah Kategori')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0 app-bottom-safe-space')

@push('head')
@endpush

@section('header')
    <x-page-header title="Ubah Kategori" :backUrl="$categoryEditListUrl" :showMenuButton="true" />
@endsection

@section('content')
        <div class="flex-1 overflow-y-auto px-6 py-6 space-y-6 pb-32 app-bottom-safe-space">
            <div class="flex items-center justify-between rounded-xl bg-slate-50 border border-slate-100 px-4 py-3">
                <p class="text-sm text-slate-500 font-medium">ID Kategori</p>
                <p id="category-id-label" class="text-xs bg-slate-200 px-2 py-1 rounded font-semibold text-slate-700">-</p>
            </div>
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
                    <button class="flex items-center justify-center size-16 rounded-2xl border-2 border-primary bg-primary/5 text-primary transition-all" data-icon="restaurant" type="button">
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
            <p id="form-error" class="text-sm text-red-500 font-semibold hidden"></p>
        </div>
        <div class="fixed bottom-0 left-1/2 z-30 w-full max-w-[430px] -translate-x-1/2 bg-white border-t border-slate-100 p-6 app-bottom-safe-footer">
            <div class="grid grid-cols-3 gap-3">
                <button id="cancel-btn" class="py-4 rounded-xl border border-slate-200 text-slate-700 font-bold hover:bg-slate-50" type="button">Batal</button>
                <button id="delete-btn" class="py-4 rounded-xl border border-rose-200 text-rose-600 font-bold hover:bg-rose-50" type="button">Hapus</button>
                <button id="save-btn" class="py-4 rounded-xl bg-primary text-white font-bold shadow-lg shadow-primary/20 active:scale-[0.98] transition-all" type="button">Simpan</button>
            </div>
        </div>
        <div class="fixed bottom-2 left-1/2 -translate-x-1/2 w-32 h-1 bg-slate-200 rounded-full"></div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
                const categoryId = document.querySelector('meta[name="category-id"]')?.content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    categoryIdLabel: document.getElementById('category-id-label'),
                    name: document.getElementById('category_name'),
                    description: document.getElementById('description'),
                    iconGrid: document.getElementById('icon-grid'),
                    error: document.getElementById('form-error'),
                    cancel: document.getElementById('cancel-btn'),
                    save: document.getElementById('save-btn'),
                    delete: document.getElementById('delete-btn'),
                };

                let selectedIcon = 'restaurant';

                const setSelectedIcon = (icon) => {
                    selectedIcon = icon || 'restaurant';
                    el.iconGrid.querySelectorAll('button[data-icon]').forEach((button) => {
                        const active = button.dataset.icon === selectedIcon;
                        button.classList.toggle('border-primary', active);
                        button.classList.toggle('bg-primary/5', active);
                        button.classList.toggle('text-primary', active);
                        button.classList.toggle('border-slate-100', !active);
                        button.classList.toggle('bg-slate-50', !active);
                        button.classList.toggle('text-slate-400', !active);
                    });
                };

                const showError = (message) => {
                    if (!message) {
                        el.error.classList.add('hidden');
                        el.error.textContent = '';
                        return;
                    }
                    el.error.textContent = message;
                    el.error.classList.remove('hidden');
                };

                const goBack = () => {
                    window.location.href = @json($categoryEditListUrl);
                };

                const loadCategory = async () => {
                    const response = await window.PosUi.fetchWithTimeout(`/api/v1/categories/${categoryId}`, { headers });
                    if (!response.ok) {
                        throw new Error('Kategori tidak ditemukan.');
                    }

                    const data = await response.json();
                    const category = data.category;
                    el.categoryIdLabel.textContent = `ID: ${String(category.id).slice(0, 8).toUpperCase()}`;
                    el.name.value = category.name || '';
                    el.description.value = category.description || '';
                    setSelectedIcon(category.icon || 'restaurant');
                };

                const saveCategory = async () => {
                    showError('');
                    const name = el.name.value.trim();
                    const description = el.description.value.trim();
                    if (!name) {
                        showError('Nama kategori wajib diisi.');
                        return;
                    }

                    el.save.disabled = true;

                    try {
                        const response = await window.PosUi.fetchWithTimeout(`/api/v1/categories/${categoryId}`, {
                            method: 'PUT',
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

                        goBack();
                    } catch (error) {
                        showError(error?.message || 'Gagal menyimpan kategori.');
                    } finally {
                        el.save.disabled = false;
                    }
                };

                const deleteCategory = async () => {
                    showError('');
                    const confirmed = window.confirm('Hapus kategori ini?');
                    if (!confirmed) {
                        return;
                    }

                    el.delete.disabled = true;

                    try {
                        const response = await window.PosUi.fetchWithTimeout(`/api/v1/categories/${categoryId}`, {
                            method: 'DELETE',
                            headers,
                        });

                        if (!response.ok) {
                            const mapped = await window.PosUi.mapApiError(response, 'Gagal menghapus kategori.');
                            throw new Error(mapped?.message || 'Gagal menghapus kategori.');
                        }

                        goBack();
                    } catch (error) {
                        showError(error?.message || 'Gagal menghapus kategori.');
                    } finally {
                        el.delete.disabled = false;
                    }
                };

                el.cancel.addEventListener('click', goBack);
                el.save.addEventListener('click', saveCategory);
                el.delete.addEventListener('click', deleteCategory);
                el.iconGrid.addEventListener('click', (event) => {
                    const button = event.target.closest('button[data-icon]');
                    if (!button) {
                        return;
                    }

                    setSelectedIcon(button.dataset.icon);
                });

                loadCategory().catch((error) => {
                    showError(error.message || 'Gagal memuat kategori.');
                });
            })();
        </script>
@endsection
