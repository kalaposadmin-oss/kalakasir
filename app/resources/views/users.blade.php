@extends('layouts.app')

@php
    $usersTenant = \App\Support\TenantContext::get();
    $usersUser = auth()->user();
    $usersRole = null;

    if (! $usersTenant && filled($usersUser?->tenant_id)) {
        $usersTenant = \App\Models\Tenant::query()->find($usersUser->tenant_id);
    }

    if ($usersTenant && $usersUser) {
        $usersRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $usersTenant->id, (string) $usersUser->id);

        if (! $usersRole && filled($usersUser?->role ?? null)) {
            $usersRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $usersUser->role);
        }
    }

    abort_unless(in_array($usersRole, ['admin', 'owner', 'superadmin'], true), 403);

    $usersPortalPrefix = request()->is('pos/*') ? '/pos' : '/backoffice';
    $usersBackUrl = $usersPortalPrefix === '/pos' ? pos_default_url() : '/backoffice/dashboard';
@endphp

@section('page_title', 'Manajemen Pengguna dan Role')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative flex h-screen w-full flex-col overflow-hidden bg-background-light dark:bg-background-dark')
@section('main_class', 'flex-1 overflow-hidden p-0')

@section('header')
    <x-page-header title="Manajemen Pengguna" subtitle="Staff, role, dan status akses" :backUrl="$usersBackUrl">
        <x-slot name="actions">
            <button id="add-user-quick" class="inline-flex h-10 items-center justify-center gap-2 rounded-full bg-primary px-4 text-xs font-bold text-white shadow-sm shadow-primary/20" type="button">
                <span class="material-symbols-outlined text-[18px]">person_add</span>
                <span class="hidden sm:inline">Tambah</span>
            </button>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
    <div class="flex h-full flex-col overflow-hidden">
        <div class="border-b border-slate-200 bg-white px-4 dark:border-slate-800 dark:bg-slate-950 lg:px-8">
            <div class="flex max-w-7xl gap-2 py-3">
                <button data-users-tab="staff" type="button" class="users-tab inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white shadow-sm shadow-primary/20">
                    <span class="material-symbols-outlined text-[18px]">groups</span>
                    Daftar Staf
                </button>
                <button data-users-tab="roles" type="button" class="users-tab inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                    <span class="material-symbols-outlined text-[18px]">admin_panel_settings</span>
                    Role dan Hak Akses
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-4 lg:px-8">
            <div data-users-pane="staff" class="mx-auto grid max-w-7xl gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
                <section class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Staf</p>
                            <p class="mt-2 text-2xl font-black text-slate-950 dark:text-white" id="total-staff">0</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Sesi Aktif</p>
                            <p class="mt-2 text-2xl font-black text-emerald-600" id="active-sessions">0</p>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Area</p>
                            <p class="mt-2 text-2xl font-black text-slate-950 dark:text-white">{{ strtoupper(ltrim($usersPortalPrefix, '/')) }}</p>
                        </article>
                    </div>

                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                        <div class="flex gap-3">
                            <span class="material-symbols-outlined text-[22px]">info</span>
                            <p><span class="font-bold">Aturan role:</span> Owner dan Admin dapat mengelola pengguna. Kasir fokus transaksi, Staf Gudang fokus stok, dan Manager/Supervisor untuk pengawasan operasional.</p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 dark:border-slate-800 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Daftar Anggota Tim</p>
                                <h2 class="mt-1 text-lg font-black text-slate-950 dark:text-white">Staff toko</h2>
                            </div>
                            <div class="flex gap-2">
                                <div class="relative min-w-0 flex-1 md:w-72">
                                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-400">search</span>
                                    <input id="staff-search" type="search" class="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm text-slate-700 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200" placeholder="Cari nama atau email" />
                                </div>
                                <button id="add-user" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white shadow-sm shadow-primary/20" type="button">
                                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                                    Tambah
                                </button>
                            </div>
                        </div>
                        <div id="staff-list" class="grid gap-3 p-4 md:grid-cols-2"></div>
                    </div>
                </section>

                <section id="user-form-card" class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:sticky xl:top-4 xl:self-start">
                    <div class="border-b border-slate-100 p-4 dark:border-slate-800">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Form pengguna</p>
                                <h2 id="form-title" class="mt-1 text-lg font-black text-slate-950 dark:text-white">Edit pengguna</h2>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300" id="edit-id">ID: -</span>
                        </div>
                    </div>

                    <form id="edit-form" class="space-y-4 p-4">
                        <div class="space-y-1.5">
                            <label for="edit-name" class="text-xs font-bold uppercase tracking-wider text-slate-500">Nama</label>
                            <input id="edit-name" class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100" type="text" autocomplete="name" />
                        </div>
                        <div class="space-y-1.5">
                            <label for="edit-email" class="text-xs font-bold uppercase tracking-wider text-slate-500">Email</label>
                            <input id="edit-email" class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100" type="email" autocomplete="email" />
                        </div>
                        <div class="space-y-1.5">
                            <label for="edit-password" class="text-xs font-bold uppercase tracking-wider text-slate-500">Password</label>
                            <input id="edit-password" class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100" type="password" autocomplete="new-password" placeholder="Minimal 6 karakter" />
                            <p id="password-hint" class="text-xs text-slate-500">Kosongkan jika tidak ingin mengganti password.</p>
                        </div>
                        <div class="space-y-1.5">
                            <label for="edit-role" class="text-xs font-bold uppercase tracking-wider text-slate-500">Role</label>
                            <select id="edit-role" class="w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-primary focus:ring-primary dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"></select>
                            <p id="role-hint" class="text-xs text-slate-500">Pilih role sesuai tanggung jawab pengguna.</p>
                        </div>
                        <div class="flex items-center justify-between rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                            <div>
                                <p class="text-sm font-bold text-slate-900 dark:text-white">Status Akun</p>
                                <p class="text-xs text-slate-500">Nonaktif akan mengunci login pengguna.</p>
                            </div>
                            <button id="toggle-status" class="rounded-full px-3 py-1.5 text-xs font-bold" type="button">Aktif</button>
                        </div>
                        <p id="form-error" class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700"></p>
                        <p id="form-success" class="hidden rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700"></p>
                        <div class="flex gap-3 pt-1">
                            <button class="flex-1 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" type="button" id="cancel-edit">Batal</button>
                            <button id="submit-user" class="flex-[2] rounded-xl bg-primary px-4 py-3 text-sm font-black text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90" type="submit">Simpan</button>
                        </div>
                    </form>
                </section>
            </div>

            <div data-users-pane="roles" class="mx-auto hidden max-w-7xl space-y-4">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Role dan Hak Akses</p>
                            <h2 class="mt-1 text-xl font-black text-slate-950 dark:text-white">Aturan akses staff</h2>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">Gunakan daftar ini sebagai panduan saat membuat user baru. Role yang muncul mengikuti data role tenant dan paket langganan.</p>
                        </div>
                        <button id="add-user-from-roles" type="button" class="inline-flex w-fit items-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-black text-white shadow-sm shadow-primary/20">
                            <span class="material-symbols-outlined text-[18px]">person_add</span>
                            Tambah user
                        </button>
                    </div>
                </section>
                <section id="role-rules" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"></section>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content || '';
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const apiHeaders = {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            };

            const el = {
                totalStaff: document.getElementById('total-staff'),
                activeSessions: document.getElementById('active-sessions'),
                staffList: document.getElementById('staff-list'),
                staffSearch: document.getElementById('staff-search'),
                addUser: document.getElementById('add-user'),
                addUserQuick: document.getElementById('add-user-quick'),
                addUserFromRoles: document.getElementById('add-user-from-roles'),
                formCard: document.getElementById('user-form-card'),
                formTitle: document.getElementById('form-title'),
                editId: document.getElementById('edit-id'),
                editName: document.getElementById('edit-name'),
                editEmail: document.getElementById('edit-email'),
                editPassword: document.getElementById('edit-password'),
                passwordHint: document.getElementById('password-hint'),
                editRole: document.getElementById('edit-role'),
                roleHint: document.getElementById('role-hint'),
                toggleStatus: document.getElementById('toggle-status'),
                form: document.getElementById('edit-form'),
                cancel: document.getElementById('cancel-edit'),
                error: document.getElementById('form-error'),
                success: document.getElementById('form-success'),
                submit: document.getElementById('submit-user'),
                roleRules: document.getElementById('role-rules'),
                tabs: [...document.querySelectorAll('[data-users-tab]')],
                panes: [...document.querySelectorAll('[data-users-pane]')],
            };

            const roleCatalog = {
                owner: {
                    label: 'Owner',
                    icon: 'workspace_premium',
                    color: 'bg-amber-100 text-amber-700',
                    description: 'Akses penuh ke operasional, laporan, pengaturan, billing, dan manajemen tim.',
                },
                admin: {
                    label: 'Admin',
                    icon: 'verified_user',
                    color: 'bg-blue-100 text-blue-600',
                    description: 'Mengelola data master, stok, pengguna, pengaturan, dan operasional harian.',
                },
                manager: {
                    label: 'Manager',
                    icon: 'admin_panel_settings',
                    color: 'bg-orange-100 text-orange-600',
                    description: 'Memantau operasional, laporan, shift, dan aktivitas tim.',
                },
                supervisor: {
                    label: 'Supervisor',
                    icon: 'supervisor_account',
                    color: 'bg-orange-100 text-orange-600',
                    description: 'Membantu pengawasan transaksi, shift, dan aktivitas operasional.',
                },
                cashier: {
                    label: 'Kasir',
                    icon: 'point_of_sale',
                    color: 'bg-primary/10 text-primary',
                    description: 'Fokus transaksi POS, shift, pembayaran, dan riwayat penjualan.',
                },
                kasir: {
                    label: 'Kasir',
                    icon: 'point_of_sale',
                    color: 'bg-primary/10 text-primary',
                    description: 'Fokus transaksi POS, shift, pembayaran, dan riwayat penjualan.',
                },
                warehouse: {
                    label: 'Staf Gudang',
                    icon: 'inventory',
                    color: 'bg-purple-100 text-purple-600',
                    description: 'Mengelola stok, penerimaan barang, transfer, dan penyesuaian inventory.',
                },
                'staf gudang': {
                    label: 'Staf Gudang',
                    icon: 'inventory',
                    color: 'bg-purple-100 text-purple-600',
                    description: 'Mengelola stok, penerimaan barang, transfer, dan penyesuaian inventory.',
                },
            };

            const state = {
                staff: [],
                roles: [],
                selectedUser: null,
                status: 'active',
                mode: 'edit',
                activeTab: 'staff',
                search: '',
            };

            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const roleKey = (name) => String(name || '')
                .trim()
                .toLowerCase()
                .replaceAll('-', ' ');

            const roleMeta = (name) => {
                const key = roleKey(name);
                return roleCatalog[key] || {
                    label: String(name || 'Role'),
                    icon: 'badge',
                    color: 'bg-slate-100 text-slate-600',
                    description: 'Role custom sesuai pengaturan tenant.',
                };
            };

            const defaultRoleId = () => {
                const preferred = state.roles.find((role) => ['kasir', 'cashier'].includes(roleKey(role.name)));
                return preferred?.id || state.roles[0]?.id || '';
            };

            const setMessage = (target, message) => {
                if (!target) {
                    return;
                }

                if (!message) {
                    target.classList.add('hidden');
                    target.textContent = '';
                    return;
                }

                target.textContent = message;
                target.classList.remove('hidden');
            };

            const parseErrorMessage = async (response) => {
                const payload = await response.json().catch(() => ({}));
                const validation = payload?.errors && typeof payload.errors === 'object'
                    ? Object.values(payload.errors).flat().filter(Boolean)
                    : [];

                return validation[0] || payload?.message || 'Gagal menyimpan pengguna.';
            };

            const switchTab = (tabName) => {
                state.activeTab = tabName;
                el.tabs.forEach((tab) => {
                    const active = tab.dataset.usersTab === tabName;
                    tab.className = active
                        ? 'users-tab inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white shadow-sm shadow-primary/20'
                        : 'users-tab inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300';
                });
                el.panes.forEach((pane) => {
                    pane.classList.toggle('hidden', pane.dataset.usersPane !== tabName);
                });
            };

            const updateStatusButton = () => {
                const isActive = state.status === 'active';
                el.toggleStatus.textContent = isActive ? 'Aktif' : 'Nonaktif';
                el.toggleStatus.className = isActive
                    ? 'rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-bold text-emerald-700'
                    : 'rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-500';
            };

            const updateRoleHint = () => {
                const selectedRole = state.roles.find((item) => item.id === el.editRole.value);
                el.roleHint.textContent = roleMeta(selectedRole?.name).description || 'Pilih role sesuai tanggung jawab pengguna.';
            };

            const updateFormMode = () => {
                const creating = state.mode === 'create';
                el.formTitle.textContent = creating ? 'Tambah pengguna baru' : 'Edit pengguna';
                el.submit.textContent = creating ? 'Tambah pengguna' : 'Simpan perubahan';
                el.passwordHint.textContent = creating
                    ? 'Password wajib diisi untuk pengguna baru.'
                    : 'Kosongkan jika tidak ingin mengganti password.';
            };

            const setSelectedUser = (user) => {
                state.selectedUser = user;
                state.mode = user ? 'edit' : 'create';

                if (!user) {
                    el.editId.textContent = 'BARU';
                    el.editName.value = '';
                    el.editEmail.value = '';
                    el.editPassword.value = '';
                    el.editRole.value = defaultRoleId();
                    state.status = 'active';
                    updateStatusButton();
                    updateRoleHint();
                    updateFormMode();
                    return;
                }

                el.editId.textContent = `ID: ${String(user.id).slice(0, 8).toUpperCase()}`;
                el.editName.value = user.name || '';
                el.editEmail.value = user.email || '';
                el.editPassword.value = '';
                const matchedRole = state.roles.find((item) => roleKey(item.name) === roleKey(user.role));
                el.editRole.value = matchedRole?.id || defaultRoleId();
                state.status = user.status || 'active';
                updateStatusButton();
                updateRoleHint();
                updateFormMode();
            };

            const filteredStaff = () => {
                const needle = state.search.trim().toLowerCase();
                if (!needle) {
                    return state.staff;
                }

                return state.staff.filter((user) => {
                    return String(user.name || '').toLowerCase().includes(needle)
                        || String(user.email || '').toLowerCase().includes(needle)
                        || String(user.role || '').toLowerCase().includes(needle);
                });
            };

            const renderStaff = () => {
                const staff = filteredStaff();
                el.staffList.innerHTML = '';

                if (!staff.length) {
                    el.staffList.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 p-5 text-sm font-semibold text-slate-400 dark:border-slate-800">Belum ada staf yang cocok.</div>';
                    return;
                }

                staff.forEach((user) => {
                    const isActive = user.status === 'active';
                    const meta = roleMeta(user.role || 'Kasir');
                    const isSelected = state.selectedUser && state.selectedUser.id === user.id;

                    const row = document.createElement('button');
                    row.type = 'button';
                    row.className = `w-full rounded-xl border bg-white p-4 text-left transition active:scale-[0.99] dark:bg-slate-950 ${isSelected ? 'border-primary shadow-md shadow-primary/10' : 'border-slate-200 hover:border-primary/40 dark:border-slate-800'}`;
                    row.innerHTML = `
                        <div class="flex items-center gap-4">
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-xl ${meta.color}">
                                <span class="material-symbols-outlined text-[24px]">${meta.icon}</span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="truncate font-black text-slate-950 dark:text-white">${escapeHtml(user.name || '-')}</span>
                                    <span class="rounded-full px-2 py-1 text-[10px] font-black uppercase ${isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}">${isActive ? 'Aktif' : 'Nonaktif'}</span>
                                </span>
                                <span class="mt-1 block truncate text-sm text-slate-500">${escapeHtml(user.email || '-')}</span>
                                <span class="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">${escapeHtml(meta.label)} - ${escapeHtml(user.branch || 'Pusat')}</span>
                            </span>
                            <span class="material-symbols-outlined text-slate-300">chevron_right</span>
                        </div>
                    `;
                    row.addEventListener('click', () => {
                        setMessage(el.error, '');
                        setMessage(el.success, '');
                        setSelectedUser(user);
                        renderStaff();
                        el.formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                    el.staffList.appendChild(row);
                });
            };

            const renderRoles = () => {
                el.roleRules.innerHTML = '';

                if (!state.roles.length) {
                    el.roleRules.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 p-5 text-sm font-semibold text-slate-400 dark:border-slate-800">Role belum tersedia.</div>';
                    return;
                }

                state.roles.forEach((role) => {
                    const meta = roleMeta(role.name);
                    const card = document.createElement('article');
                    card.className = 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900';
                    card.innerHTML = `
                        <div class="flex items-start gap-4">
                            <span class="flex size-12 items-center justify-center rounded-xl ${meta.color}">
                                <span class="material-symbols-outlined text-[24px]">${meta.icon}</span>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-base font-black text-slate-950 dark:text-white">${escapeHtml(meta.label)}</span>
                                <span class="mt-1 block text-sm leading-6 text-slate-500">${escapeHtml(meta.description)}</span>
                            </span>
                        </div>
                    `;
                    el.roleRules.appendChild(card);
                });
            };

            const populateRoles = () => {
                el.editRole.innerHTML = '<option value="">Pilih role</option>';
                state.roles.forEach((role) => {
                    const option = document.createElement('option');
                    option.value = role.id;
                    option.textContent = roleMeta(role.name).label;
                    el.editRole.appendChild(option);
                });
                updateRoleHint();
                renderRoles();
            };

            const beginCreate = () => {
                switchTab('staff');
                setMessage(el.error, '');
                setMessage(el.success, '');
                setSelectedUser(null);
                renderStaff();
                window.setTimeout(() => {
                    el.formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    el.editName.focus({ preventScroll: true });
                }, 40);
            };

            const loadUsers = async () => {
                const response = await fetch('/api/v1/users', {
                    headers: apiHeaders,
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (!response.ok) {
                    el.staffList.innerHTML = '<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">Gagal memuat data staf. Muat ulang halaman lalu coba lagi.</div>';
                    return;
                }

                const data = await response.json();
                el.totalStaff.textContent = data.total_staff || 0;
                el.activeSessions.textContent = data.active_sessions || 0;
                state.staff = data.staff || [];
                state.roles = data.roles || [];
                populateRoles();

                if (state.selectedUser) {
                    const refreshed = state.staff.find((user) => user.id === state.selectedUser.id);
                    setSelectedUser(refreshed || state.staff[0] || null);
                } else if (state.mode !== 'create') {
                    setSelectedUser(state.staff[0] || null);
                } else {
                    setSelectedUser(null);
                }

                renderStaff();
            };

            el.tabs.forEach((tab) => tab.addEventListener('click', () => switchTab(tab.dataset.usersTab)));
            el.addUser.addEventListener('click', beginCreate);
            el.addUserQuick.addEventListener('click', beginCreate);
            el.addUserFromRoles.addEventListener('click', beginCreate);
            el.cancel.addEventListener('click', () => {
                setMessage(el.error, '');
                setMessage(el.success, '');
                setSelectedUser(state.staff[0] || null);
                renderStaff();
            });
            el.toggleStatus.addEventListener('click', () => {
                state.status = state.status === 'active' ? 'inactive' : 'active';
                updateStatusButton();
            });
            el.editRole.addEventListener('change', updateRoleHint);
            el.staffSearch.addEventListener('input', () => {
                state.search = el.staffSearch.value || '';
                renderStaff();
            });

            el.form.addEventListener('submit', async (event) => {
                event.preventDefault();
                setMessage(el.error, '');
                setMessage(el.success, '');

                const creating = state.mode === 'create' || !state.selectedUser;
                const payload = {
                    name: el.editName.value.trim(),
                    email: el.editEmail.value.trim(),
                    password: el.editPassword.value.trim(),
                    role_id: el.editRole.value || null,
                    status: state.status,
                };

                if (!payload.name || !payload.email) {
                    setMessage(el.error, 'Nama dan email wajib diisi.');
                    return;
                }

                if (creating && payload.password.length < 6) {
                    setMessage(el.error, 'Password wajib minimal 6 karakter untuk pengguna baru.');
                    return;
                }

                if (!creating && payload.password === '') {
                    delete payload.password;
                }

                if (!payload.role_id) {
                    setMessage(el.error, 'Role wajib dipilih.');
                    return;
                }

                const url = creating ? '/api/v1/users' : `/api/v1/users/${state.selectedUser.id}`;
                const method = creating ? 'POST' : 'PUT';
                const previousLabel = el.submit.textContent;
                el.submit.disabled = true;
                el.submit.textContent = 'Menyimpan...';

                try {
                    const response = await fetch(url, {
                        method,
                        credentials: 'same-origin',
                        headers: {
                            ...apiHeaders,
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });

                    if (!response.ok) {
                        throw new Error(await parseErrorMessage(response));
                    }

                    const result = await response.json().catch(() => ({}));
                    const savedUserId = result?.user?.id || state.selectedUser?.id || null;
                    setMessage(el.success, creating ? 'Pengguna baru berhasil ditambahkan.' : 'Perubahan pengguna berhasil disimpan.');
                    state.mode = 'edit';
                    state.selectedUser = savedUserId ? { id: savedUserId } : state.selectedUser;
                    await loadUsers();
                } catch (error) {
                    setMessage(el.error, error.message || 'Gagal menyimpan pengguna.');
                } finally {
                    el.submit.disabled = false;
                    el.submit.textContent = previousLabel;
                    updateFormMode();
                }
            });

            switchTab('staff');
            loadUsers();
        })();
    </script>
@endsection
