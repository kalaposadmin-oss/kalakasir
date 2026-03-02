@extends('layouts.app')

@section('page_title', 'Manajemen Pengguna dan Role')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@section('header')
    <x-page-header title="Manajemen Pengguna" backUrl="/menu/settings">
        <x-slot name="actions">
            <button class="flex items-center justify-center size-10 rounded-full bg-slate-100 dark:bg-slate-800">
                <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">search</span>
            </button>
            <button id="add-user-quick" class="flex items-center justify-center size-10 rounded-full bg-primary text-white" type="button">
                <span class="material-symbols-outlined">person_add</span>
            </button>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
        <div class="flex px-4 border-b border-slate-100 dark:border-slate-800">
            <button class="flex-1 py-3 text-sm font-semibold border-b-2 border-primary text-primary">Daftar Staf</button>
            <button class="flex-1 py-3 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700">Role dan Hak Akses</button>
        </div>
        <div class="flex-1 overflow-y-auto pb-24">
            <div class="grid grid-cols-2 gap-3 p-4">
                <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Total Staf</p>
                    <p class="text-xl font-bold" id="total-staff">0</p>
                </div>
                <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Sesi Aktif</p>
                    <p class="text-xl font-bold text-emerald-500" id="active-sessions">0</p>
                </div>
            </div>
            <div class="px-4 mb-4">
                <button id="add-user" class="w-full bg-primary text-white py-4 rounded-xl font-bold flex items-center justify-center gap-2 shadow-lg shadow-primary/20" type="button">
                    <span class="material-symbols-outlined">person_add</span>
                    Tambah Pengguna Baru
                </button>
            </div>
            <div class="px-4 space-y-3">
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Daftar Anggota Tim</h3>
                    <span class="text-[10px] text-slate-400">Terakhir update: Hari ini</span>
                </div>
                <div id="staff-list" class="space-y-3"></div>
            </div>
            <section class="mx-4 mt-6 mb-28 bg-white dark:bg-slate-900 rounded-2xl shadow-xl ring-1 ring-slate-200 dark:ring-slate-800 overflow-hidden">
                <div class="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center">
                    <h2 class="font-bold text-slate-800 dark:text-white">Kelola Pengguna</h2>
                    <span class="text-xs bg-slate-200 dark:bg-slate-700 px-2 py-1 rounded font-medium" id="edit-id">ID: -</span>
                </div>
                <form id="edit-form" class="p-4 space-y-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase">Nama</label>
                        <input id="edit-name" class="w-full px-4 py-3 rounded-lg border-slate-200 dark:border-slate-800 dark:bg-slate-950 focus:ring-primary focus:border-primary" type="text" value="" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase">Email</label>
                        <input id="edit-email" class="w-full px-4 py-3 rounded-lg border-slate-200 dark:border-slate-800 dark:bg-slate-950 focus:ring-primary focus:border-primary" type="email" value="" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase">Password</label>
                        <input id="edit-password" class="w-full px-4 py-3 rounded-lg border-slate-200 dark:border-slate-800 dark:bg-slate-950 focus:ring-primary focus:border-primary" type="password" placeholder="Isi untuk ganti / buat baru" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-500 uppercase">Role</label>
                        <select id="edit-role" class="w-full px-4 py-3 rounded-lg border-slate-200 dark:border-slate-800 dark:bg-slate-950 focus:ring-primary focus:border-primary"></select>
                    </div>
                    <div class="flex items-center justify-between rounded-xl border border-slate-200 dark:border-slate-800 p-3">
                        <div>
                            <p class="text-sm font-semibold">Status Akun</p>
                            <p class="text-xs text-slate-500">Aktif atau non-aktif</p>
                        </div>
                        <button id="toggle-status" class="px-3 py-1.5 rounded-full text-xs font-bold" type="button">Aktif</button>
                    </div>
                    <p id="form-error" class="text-sm text-red-500 font-semibold hidden"></p>
                    <div class="flex gap-3 pt-2">
                        <button class="flex-1 py-3 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold hover:bg-slate-50" type="button" id="cancel-edit">Batal</button>
                        <button class="flex-[2] py-3 px-4 rounded-xl bg-primary text-white font-bold shadow-lg shadow-primary/20 hover:opacity-90" type="submit">Simpan</button>
                    </div>
                </form>
            </section>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    totalStaff: document.getElementById('total-staff'),
                    activeSessions: document.getElementById('active-sessions'),
                    staffList: document.getElementById('staff-list'),
                    addUser: document.getElementById('add-user'),
                    addUserQuick: document.getElementById('add-user-quick'),
                    editId: document.getElementById('edit-id'),
                    editName: document.getElementById('edit-name'),
                    editEmail: document.getElementById('edit-email'),
                    editPassword: document.getElementById('edit-password'),
                    editRole: document.getElementById('edit-role'),
                    toggleStatus: document.getElementById('toggle-status'),
                    form: document.getElementById('edit-form'),
                    cancel: document.getElementById('cancel-edit'),
                    error: document.getElementById('form-error'),
                };

                const roleIconMap = {
                    Kasir: 'account_circle',
                    Manager: 'admin_panel_settings',
                    'Staf Gudang': 'inventory',
                    Admin: 'verified_user',
                };

                const roleColorMap = {
                    Kasir: 'bg-primary/10 text-primary',
                    Manager: 'bg-orange-100 text-orange-600',
                    'Staf Gudang': 'bg-purple-100 text-purple-600',
                    Admin: 'bg-blue-100 text-blue-600',
                };

                const state = {
                    staff: [],
                    roles: [],
                    selectedUser: null,
                    status: 'active',
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

                const updateStatusButton = () => {
                    const isActive = state.status === 'active';
                    el.toggleStatus.textContent = isActive ? 'Aktif' : 'Non-aktif';
                    el.toggleStatus.className = isActive
                        ? 'px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700'
                        : 'px-3 py-1.5 rounded-full text-xs font-bold bg-slate-100 text-slate-500';
                };

                const setSelectedUser = (user) => {
                    state.selectedUser = user;
                    if (!user) {
                        el.editId.textContent = 'ID: -';
                        el.editName.value = '';
                        el.editEmail.value = '';
                        el.editPassword.value = '';
                        el.editRole.value = '';
                        state.status = 'active';
                        updateStatusButton();
                        return;
                    }
                    el.editId.textContent = `ID: ${String(user.id).slice(0, 8).toUpperCase()}`;
                    el.editName.value = user.name || '';
                    el.editEmail.value = user.email || '';
                    el.editPassword.value = '';
                    const role = state.roles.find((item) => item.name === user.role);
                    el.editRole.value = role?.id || '';
                    state.status = user.status || 'active';
                    updateStatusButton();
                };

                const renderStaff = (staff) => {
                    el.staffList.innerHTML = '';

                    if (!staff.length) {
                        el.staffList.innerHTML = '<div class="text-sm text-slate-400">Belum ada staf.</div>';
                        return;
                    }

                    staff.forEach((user) => {
                        const isActive = user.status === 'active';
                        const role = user.role || 'Kasir';
                        const icon = roleIconMap[role] || 'account_circle';
                        const color = roleColorMap[role] || 'bg-slate-100 text-slate-500';
                        const badgeClass = isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500';
                        const badgeText = isActive ? 'Aktif' : 'Non-aktif';
                        const isSelected = state.selectedUser && state.selectedUser.id === user.id;

                        const row = document.createElement('button');
                        row.type = 'button';
                        row.className = `w-full text-left bg-white dark:bg-slate-900 p-4 rounded-xl border ${isSelected ? 'border-primary shadow-md' : 'border-slate-200 dark:border-slate-800'} flex items-center gap-4 transition-active active:scale-[0.98]`;
                        row.innerHTML = `
                            <div class="size-12 rounded-full ${color} flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-2xl">${icon}</span>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-center">
                                    <h4 class="font-bold text-slate-900 dark:text-slate-100">${user.name}</h4>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${badgeClass} uppercase">${badgeText}</span>
                                </div>
                                <p class="text-sm text-slate-500 mt-0.5">${role} - ${user.branch || 'Pusat'}</p>
                            </div>
                            <span class="material-symbols-outlined text-slate-300">chevron_right</span>
                        `;
                        row.addEventListener('click', () => {
                            setSelectedUser(user);
                            renderStaff(state.staff);
                        });
                        el.staffList.appendChild(row);
                    });
                };

                const populateRoles = () => {
                    el.editRole.innerHTML = '<option value="">Pilih role</option>';
                    state.roles.forEach((role) => {
                        const option = document.createElement('option');
                        option.value = role.id;
                        option.textContent = role.name;
                        el.editRole.appendChild(option);
                    });
                };

                const loadUsers = async () => {
                    const response = await fetch('/api/v1/users', { headers });
                    if (!response.ok) {
                        el.staffList.innerHTML = '<div class="text-sm text-red-500">Gagal memuat data staf.</div>';
                        return;
                    }
                    const data = await response.json();
                    el.totalStaff.textContent = data.total_staff || 0;
                    el.activeSessions.textContent = data.active_sessions || 0;
                    state.staff = data.staff || [];
                    state.roles = data.roles || [];
                    populateRoles();
                    setSelectedUser(state.staff[0] || null);
                    renderStaff(state.staff);
                };

                el.addUser.addEventListener('click', () => {
                    setSelectedUser(null);
                    renderStaff(state.staff);
                });

                el.addUserQuick.addEventListener('click', () => {
                    setSelectedUser(null);
                    renderStaff(state.staff);
                });

                el.cancel.addEventListener('click', () => {
                    setSelectedUser(state.staff[0] || null);
                    renderStaff(state.staff);
                });

                el.toggleStatus.addEventListener('click', () => {
                    state.status = state.status === 'active' ? 'inactive' : 'active';
                    updateStatusButton();
                });

                el.form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    showError('');

                    const payload = {
                        name: el.editName.value.trim(),
                        email: el.editEmail.value.trim(),
                        password: el.editPassword.value.trim(),
                        role_id: el.editRole.value || null,
                        status: state.status,
                    };

                    if (!payload.name || !payload.email) {
                        showError('Nama dan email wajib diisi.');
                        return;
                    }

                    if (!state.selectedUser && !payload.password) {
                        showError('Password wajib untuk pengguna baru.');
                        return;
                    }

                    if (!payload.role_id) {
                        showError('Role wajib dipilih.');
                        return;
                    }

                    try {
                        const url = state.selectedUser
                            ? `/api/v1/users/${state.selectedUser.id}`
                            : '/api/v1/users';
                        const method = state.selectedUser ? 'PUT' : 'POST';

                        if (!payload.password) {
                            delete payload.password;
                        }

                        const response = await fetch(url, {
                            method,
                            headers: {
                                ...headers,
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(payload),
                        });

                        if (!response.ok) {
                            const error = await response.json();
                            throw new Error(error?.message || 'Gagal menyimpan pengguna.');
                        }

                        await loadUsers();
                    } catch (error) {
                        showError(error.message || 'Gagal menyimpan pengguna.');
                    }
                });

                (async () => {
                    await loadUsers();
                })();
            })();
        </script>
    @endsection






