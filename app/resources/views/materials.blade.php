@extends('layouts.app')

@php
    $materialsTenant = \App\Support\TenantContext::get();
    $materialsUser = auth()->user();
    $materialsRole = null;

    if (! $materialsTenant && filled($materialsUser?->tenant_id)) {
        $materialsTenant = \App\Models\Tenant::query()->find($materialsUser->tenant_id);
    }

    if ($materialsTenant && $materialsUser) {
        $materialsRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $materialsTenant->id, (string) $materialsUser->id);

        if (! $materialsRole && filled($materialsUser->role ?? null)) {
            $materialsRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $materialsUser->role);
        }
    }

    abort_unless(in_array($materialsRole, ['admin', 'owner', 'superadmin'], true), 403);
@endphp

@section('page_title', 'Manajemen Bahan')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 antialiased')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-white dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@section('header')
    <x-page-header title="Manajemen Bahan" backUrl="/backoffice/dashboard">
        <x-slot name="actions">
            <button id="open-create" class="flex items-center justify-center size-10 rounded-full bg-primary text-white" type="button">
                <span class="material-symbols-outlined">add</span>
            </button>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
        <div class="px-4 py-4 bg-white">
            <label class="relative block w-full">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <span class="material-symbols-outlined">search</span>
                </span>
                <input id="search-input" class="form-input block w-full rounded-xl border-none bg-slate-100 py-3 pl-10 pr-4 text-slate-900 focus:ring-2 focus:ring-primary/20 placeholder:text-slate-400" placeholder="Cari nama bahan..." type="text" />
            </label>
        </div>

        <div class="px-4 pb-2 flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider" id="material-count">Total 0 Bahan</p>
            <button id="refresh-list" class="text-xs font-semibold text-primary" type="button">Refresh</button>
        </div>

        <div class="flex-1 overflow-y-auto px-4 space-y-3 pb-24" id="material-list"></div>

        <div id="material-modal" class="fixed inset-0 z-50 hidden">
            <div class="absolute inset-0 bg-slate-900/40"></div>
            <div class="absolute left-1/2 top-1/2 w-[92%] max-w-[420px] -translate-x-1/2 -translate-y-1/2 rounded-2xl bg-white dark:bg-slate-900 p-5 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h2 id="modal-title" class="text-base font-bold text-slate-900 dark:text-slate-100">Tambah Bahan</h2>
                    <button id="close-modal" class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800" type="button">
                        <span class="material-symbols-outlined text-slate-600 dark:text-slate-300">close</span>
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Nama Bahan</label>
                        <input id="material-name" class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 text-sm" placeholder="Contoh: Gula Pasir" type="text" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Satuan</label>
                        <div class="relative">
                            <select id="material-unit" class="w-full appearance-none rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 pr-10 text-sm">
                                <option value="pcs">pcs</option>
                                <option value="gram">gram</option>
                                <option value="ml">ml</option>
                                <option value="liter">liter</option>
                                <option value="kg">kg</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Harga per Satuan</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm">Rp</span>
                            <input id="material-cost" class="w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 pl-10 pr-4 text-sm" placeholder="0" type="number" min="0" step="0.01" />
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Supplier Default</label>
                        <div class="relative">
                            <select id="material-supplier" class="w-full appearance-none rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:border-primary focus:ring-primary h-12 px-4 pr-10 text-sm">
                                <option value="">Pilih Supplier (Opsional)</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                        </div>
                    </div>
                    <p id="form-error" class="text-sm font-semibold text-red-500 hidden"></p>
                </div>

                <div class="mt-5 flex items-center gap-3">
                    <button id="delete-material" class="flex-1 h-11 rounded-xl border border-red-200 text-red-600 font-semibold hover:bg-red-50 transition-colors hidden" type="button">Hapus</button>
                    <button id="save-material" class="flex-[2] h-11 rounded-xl bg-primary text-white font-semibold hover:bg-primary/90 transition-colors" type="button">Simpan</button>
                </div>
            </div>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    list: document.getElementById('material-list'),
                    count: document.getElementById('material-count'),
                    search: document.getElementById('search-input'),
                    refresh: document.getElementById('refresh-list'),
                    openCreate: document.getElementById('open-create'),
                    modal: document.getElementById('material-modal'),
                    closeModal: document.getElementById('close-modal'),
                    modalTitle: document.getElementById('modal-title'),
                    name: document.getElementById('material-name'),
                    unit: document.getElementById('material-unit'),
                    cost: document.getElementById('material-cost'),
                    supplier: document.getElementById('material-supplier'),
                    save: document.getElementById('save-material'),
                    delete: document.getElementById('delete-material'),
                    error: document.getElementById('form-error'),
                };

                const state = {
                    materials: [],
                    suppliers: [],
                    activeId: null,
                };

                const formatCount = (count) => `Total ${count} Bahan`;

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const showError = (message) => {
                    if (!message) {
                        el.error.classList.add('hidden');
                        el.error.textContent = '';
                        return;
                    }
                    el.error.textContent = message;
                    el.error.classList.remove('hidden');
                };

                const openModal = (material = null) => {
                    state.activeId = material?.id || null;
                    el.modalTitle.textContent = material ? 'Ubah Bahan' : 'Tambah Bahan';
                    el.name.value = material?.name || '';
                    el.unit.value = material?.unit || 'pcs';
                    el.cost.value = material?.unit_cost ?? 0;
                    el.supplier.value = material?.supplier_id || '';
                    el.delete.classList.toggle('hidden', !material);
                    showError('');
                    el.modal.classList.remove('hidden');
                    setTimeout(() => el.name.focus(), 0);
                };

                const closeModal = () => {
                    el.modal.classList.add('hidden');
                    state.activeId = null;
                    showError('');
                };

                const renderList = () => {
                    const term = el.search.value.trim().toLowerCase();
                    const filtered = state.materials.filter((item) => item.name.toLowerCase().includes(term));
                    el.count.textContent = formatCount(filtered.length);
                    el.list.innerHTML = '';

                    if (!filtered.length) {
                        el.list.innerHTML = '<div class="py-8 text-center text-sm text-slate-400">Belum ada bahan.</div>';
                        return;
                    }

                    filtered.forEach((item) => {
                        const supplierName = state.suppliers.find((supplier) => supplier.id === item.supplier_id)?.name || '-';
                        const row = document.createElement('button');
                        row.type = 'button';
                        row.className = 'w-full text-left bg-white p-4 rounded-xl border border-slate-100 hover:border-primary/30 transition-colors flex items-center justify-between';
                        const name = document.createElement('div');
                        name.className = 'flex flex-col';
                        const title = document.createElement('span');
                        title.className = 'text-base font-semibold text-slate-900';
                        title.textContent = item.name;
                        const unit = document.createElement('span');
                        unit.className = 'text-xs text-slate-500';
                        unit.textContent = `Satuan: ${item.unit || '-'} • Supplier: ${supplierName}`;
                        name.appendChild(title);
                        name.appendChild(unit);

                        const badge = document.createElement('span');
                        badge.className = 'px-2 py-1 rounded-full text-[10px] font-bold bg-primary/10 text-primary';
                        badge.textContent = formatCurrency(item.unit_cost || 0);

                        row.appendChild(name);
                        row.appendChild(badge);

                        row.addEventListener('click', () => openModal(item));
                        el.list.appendChild(row);
                    });
                };

                const loadMaterials = async () => {
                    const term = el.search.value.trim();
                    const url = new URL('/api/v1/materials', window.location.origin);
                    if (term.length >= 2) {
                        url.searchParams.set('search', term);
                    }
                    url.searchParams.set('limit', '200');
                    const response = await fetch(url.toString(), { headers });
                    if (!response.ok) {
                        el.list.innerHTML = '<div class="py-8 text-center text-sm text-red-500">Gagal memuat bahan.</div>';
                        el.count.textContent = formatCount(0);
                        return;
                    }
                    const payload = await response.json().catch(() => ({}));
                    state.materials = window.PosUi.unwrapApiList(payload, 'materials');
                    renderList();
                };

                const loadSuppliers = async () => {
                    const response = await fetch('/api/v1/suppliers', { headers });
                    if (!response.ok) {
                        state.suppliers = [];
                        el.supplier.innerHTML = '<option value="">Pilih Supplier (Opsional)</option>';
                        return;
                    }
                    const payload = await response.json().catch(() => ({}));
                    state.suppliers = window.PosUi.unwrapApiList(payload, 'suppliers');
                    const options = ['<option value="">Pilih Supplier (Opsional)</option>'];
                    state.suppliers.forEach((supplier) => {
                        options.push(`<option value="${supplier.id}">${supplier.name}</option>`);
                    });
                    el.supplier.innerHTML = options.join('');
                };

                const saveMaterial = async () => {
                    showError('');
                    const name = el.name.value.trim();
                    const unit = el.unit.value;
                    const cost = Number(el.cost.value || 0);
                    const supplierId = el.supplier.value || null;
                    if (!name) {
                        showError('Nama bahan wajib diisi.');
                        return;
                    }

                    el.save.disabled = true;
                    try {
                        const payload = { name, unit, unit_cost: cost, supplier_id: supplierId };
                        const isEdit = Boolean(state.activeId);
                        const url = isEdit ? `/api/v1/materials/${state.activeId}` : '/api/v1/materials';
                        const response = await fetch(url, {
                            method: isEdit ? 'PUT' : 'POST',
                            headers: {
                                ...headers,
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(payload),
                        });

                        if (!response.ok) {
                            const data = await response.json().catch(() => ({}));
                            throw new Error(data.message || 'Gagal menyimpan bahan.');
                        }

                        await loadMaterials();
                        closeModal();
                    } catch (error) {
                        showError(error.message || 'Gagal menyimpan bahan.');
                    } finally {
                        el.save.disabled = false;
                    }
                };

                const deleteMaterial = async () => {
                    if (!state.activeId) {
                        return;
                    }
                    if (!confirm('Hapus bahan ini?')) {
                        return;
                    }

                    el.delete.disabled = true;
                    try {
                        const response = await fetch(`/api/v1/materials/${state.activeId}`, {
                            method: 'DELETE',
                            headers,
                        });

                        if (!response.ok) {
                            const data = await response.json().catch(() => ({}));
                            throw new Error(data.message || 'Gagal menghapus bahan.');
                        }

                        await loadMaterials();
                        closeModal();
                    } catch (error) {
                        showError(error.message || 'Gagal menghapus bahan.');
                    } finally {
                        el.delete.disabled = false;
                    }
                };

                el.openCreate.addEventListener('click', () => openModal());
                el.closeModal.addEventListener('click', closeModal);
                el.modal.addEventListener('click', (event) => {
                    if (event.target === el.modal || event.target === el.modal.firstElementChild) {
                        closeModal();
                    }
                });
                el.search.addEventListener('input', () => {
                    renderList();
                    if (el.search.value.trim().length >= 2 || !el.search.value.trim()) {
                        loadMaterials();
                    }
                });
                el.refresh.addEventListener('click', loadMaterials);
                el.save.addEventListener('click', saveMaterial);
                el.delete.addEventListener('click', deleteMaterial);

                Promise.all([loadSuppliers(), loadMaterials()]);
            })();
        </script>
@endsection
