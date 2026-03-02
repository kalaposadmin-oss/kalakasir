@extends('layouts.app')

@section('page_title', 'Akun Bank & Kas')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-y-auto p-0')

@push('head')
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
@endpush

@section('header')
    <x-page-header title="Akun Bank & Kas" backUrl="/menu/settings">
        <x-slot name="actions">
            <button class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300" type="button">
                <span class="material-symbols-outlined text-[20px]">search</span>
            </button>
        </x-slot>
    </x-page-header>
@endsection

@section('content')
    <div class="flex gap-3 px-4 py-4">
        <button id="add-account" class="flex-1 flex items-center justify-center rounded-lg h-11 px-4 bg-primary text-white text-sm font-bold shadow-sm active:opacity-90" type="button">
            <span class="material-symbols-outlined text-sm mr-2">add</span>
            <span class="truncate">Tambah Akun</span>
        </button>
    </div>

    <div id="add-form" class="mx-4 mb-2 hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Tambah Akun Baru</h3>
            <button id="add-cancel" class="text-xs font-semibold text-slate-400" type="button">Batal</button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Kode Akun</label>
                <input id="account-code" class="w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-sm read-only:bg-slate-100 read-only:text-slate-500 read-only:cursor-not-allowed" placeholder="Contoh: 1103" type="text" readonly />
                <p class="mt-1 text-[11px] text-slate-500">Kode dibuat otomatis agar konsisten.</p>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Nama Akun</label>
                <input id="account-name" class="w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-sm" placeholder="Contoh: Bank Mandiri" type="text" />
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Kelompok (Opsional)</label>
                <select id="account-parent" class="w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-sm">
                    <option value="">Tanpa Kelompok</option>
                </select>
            </div>
            <p id="add-error" class="text-xs text-red-500 font-semibold hidden"></p>
            <button id="add-save" class="w-full rounded-lg bg-primary text-white py-2.5 text-sm font-bold" type="button">Simpan Akun</button>
        </div>
    </div>

    <div class="px-4 pb-2">
        <div class="flex items-center justify-between bg-white dark:bg-slate-800/50 p-3 rounded-xl border border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-slate-400">filter_list</span>
                <span class="text-xs font-medium text-slate-600 dark:text-slate-400">Menampilkan: <span id="filter-label" class="text-slate-900 dark:text-slate-100">Akun Kas &amp; Bank</span></span>
            </div>
            <span class="text-xs text-primary font-bold">Ubah</span>
        </div>
    </div>

    <div id="coa-list" class="flex flex-col gap-px bg-slate-200 dark:bg-slate-800 mt-2"></div>

    <div class="h-10"></div>
    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

            const el = {
                list: document.getElementById('coa-list'),
                filterLabel: document.getElementById('filter-label'),
                addButton: document.getElementById('add-account'),
                addForm: document.getElementById('add-form'),
                addCancel: document.getElementById('add-cancel'),
                addSave: document.getElementById('add-save'),
                addError: document.getElementById('add-error'),
                code: document.getElementById('account-code'),
                name: document.getElementById('account-name'),
                parent: document.getElementById('account-parent'),
            };

            const state = {
                type: 'asset',
                accounts: [],
            };

            const groupNameMap = {
                '1100': 'Kas & Bank',
                '1200': 'Piutang Usaha',
                '1300': 'Persediaan Barang',
                '1600': 'Aset Tetap',
                '2100': 'Hutang Usaha',
                '3100': 'Ekuitas',
                '4100': 'Penjualan',
                '5100': 'Harga Pokok Penjualan',
                '6100': 'Beban Operasional',
            };


            const groupCodeFromAccount = (code) => {
                if (!code) {
                    return '0000';
                }
                if (code.length >= 4) {
                    return `${code.slice(0, 2)}00`;
                }
                return code;
            };

            const resetAddForm = () => {
                el.code.value = '';
                el.name.value = '';
                el.parent.value = '';
                el.addError.classList.add('hidden');
                el.addError.textContent = '';
            };

            const suggestNextCode = () => {
                const numericCodes = state.accounts
                    .map((account) => String(account.code || '').trim())
                    .filter((code) => /^\d+$/.test(code) && code.startsWith('11'))
                    .map((code) => Number(code))
                    .filter((code) => Number.isFinite(code));

                if (!numericCodes.length) {
                    return '1101';
                }

                const maxCode = Math.max(...numericCodes);
                const nextCode = maxCode + 1;
                const minLength = String(maxCode).length;
                return String(nextCode).padStart(minLength, '0');
            };

            const setAddFormVisible = (visible) => {
                el.addForm.classList.toggle('hidden', !visible);
                if (visible) {
                    if (!el.code.value) {
                        el.code.value = suggestNextCode();
                    }
                    el.code.focus();
                } else {
                    resetAddForm();
                }
            };

            const renderParentOptions = () => {
                const parents = state.accounts.filter((account) => {
                    if (account.parent_id || !account.code) {
                        return false;
                    }
                    return groupCodeFromAccount(account.code) === '1100';
                });
                const sorted = parents.sort((a, b) => String(a.code).localeCompare(String(b.code)));
                el.parent.innerHTML = '<option value="">Tanpa Kelompok</option>';
                sorted.forEach((account) => {
                    const option = document.createElement('option');
                    option.value = account.id;
                    option.textContent = `${account.code} - ${account.name}`;
                    el.parent.appendChild(option);
                });
            };

            const renderGroups = () => {
                el.list.innerHTML = '';

                if (!state.accounts.length) {
                    el.list.innerHTML = '<div class="bg-white dark:bg-slate-900 px-4 py-6 text-sm text-slate-400">Belum ada akun.</div>';
                    return;
                }

                const groups = new Map();

                state.accounts.forEach((account) => {
                    const groupCode = groupCodeFromAccount(account.code || '');
                    if (groupCode !== '1100') {
                        return;
                    }
                    if (!groups.has(groupCode)) {
                        groups.set(groupCode, []);
                    }
                    groups.get(groupCode).push(account);
                });

                if (!groups.size) {
                    el.list.innerHTML = '<div class="bg-white dark:bg-slate-900 px-4 py-6 text-sm text-slate-400">Belum ada akun kas/bank.</div>';
                    return;
                }

                [...groups.entries()].forEach(([groupCode, accounts]) => {
                    const groupName = groupNameMap[groupCode] || `Kelompok ${groupCode}`;
                    const header = document.createElement('div');
                    header.className = 'bg-white dark:bg-slate-900 px-4 py-3 flex items-center justify-between border-b border-slate-100 dark:border-slate-800';
                    header.innerHTML = `
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-slate-400">folder_open</span>
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest leading-none mb-1">${groupCode}</p>
                                <p class="font-bold text-slate-900 dark:text-slate-100">${groupName}</p>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-slate-400">expand_more</span>
                    `;
                    el.list.appendChild(header);

                    const groupBody = document.createElement('div');
                    groupBody.className = 'bg-background-light/50 dark:bg-background-dark/50';

                    const byParent = new Map();
                    accounts.forEach((account) => {
                        const parentKey = account.parent_id || 'root';
                        if (!byParent.has(parentKey)) {
                            byParent.set(parentKey, []);
                        }
                        byParent.get(parentKey).push(account);
                    });

                    const sortByCode = (items) =>
                        items.sort((a, b) => String(a.code || '').localeCompare(String(b.code || '')));

                    const renderRow = (account, level, index) => {
                        const row = document.createElement('a');
                        row.href = `/settings/chart-of-accounts/${account.id}`;
                        row.className = `bg-white dark:bg-slate-900 px-4 py-4 ml-6 border-l border-slate-200 dark:border-slate-800 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-800/70 transition${index > 0 ? ' border-t border-slate-50 dark:border-slate-800/50' : ''}`;
                        row.style.paddingLeft = level > 0 ? `${16 + level * 12}px` : '';
                        row.innerHTML = `
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-mono font-medium text-primary bg-primary/10 px-1.5 py-0.5 rounded">${account.code || '-'} </span>
                                    <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">${account.name || '-'}</span>
                                </div>
                                <p class="text-xs text-slate-500">Saldo: -</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-slate-400 text-xl">chevron_right</span>
                            </div>
                        `;
                        groupBody.appendChild(row);
                    };

                    const roots = sortByCode(byParent.get('root') || []);
                    let renderedIndex = 0;

                    roots.forEach((parent) => {
                        renderRow(parent, 0, renderedIndex++);
                        const children = sortByCode(byParent.get(parent.id) || []);
                        children.forEach((child) => {
                            renderRow(child, 1, renderedIndex++);
                        });
                    });

                    const orphanKeys = [...byParent.keys()].filter((key) => key !== 'root' && !roots.some((item) => String(item.id) === String(key)));
                    orphanKeys.forEach((key) => {
                        const orphans = sortByCode(byParent.get(key) || []);
                        orphans.forEach((orphan) => {
                            renderRow(orphan, 0, renderedIndex++);
                        });
                    });

                    el.list.appendChild(groupBody);
                });
                renderParentOptions();
            };

            const saveAccount = async () => {
                const code = el.code.value.trim();
                const name = el.name.value.trim();

                el.addError.classList.add('hidden');
                el.addError.textContent = '';

                if (!code || !name) {
                    el.addError.textContent = 'Kode dan nama akun wajib diisi.';
                    el.addError.classList.remove('hidden');
                    return;
                }

                if (!/^11\d+$/.test(code)) {
                    el.addError.textContent = 'Kode harus diawali 11 dan hanya angka.';
                    el.addError.classList.remove('hidden');
                    return;
                }

                const response = await fetch('/api/v1/chart-of-accounts', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        ...headers,
                    },
                    body: JSON.stringify({
                        code,
                        name,
                        type: state.type,
                        parent_id: el.parent.value || null,
                    }),
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    el.addError.textContent = payload?.message || 'Gagal menyimpan akun.';
                    el.addError.classList.remove('hidden');
                    return;
                }

                setAddFormVisible(false);
                loadAccounts()
                    .then((data) => {
                        state.accounts = data.accounts || [];
                        renderGroups();
                    })
                    .catch(() => {
                        el.list.innerHTML = '<div class="bg-white dark:bg-slate-900 px-4 py-6 text-sm text-red-500">Gagal memuat bagan akun.</div>';
                    });
            };

            const loadAccounts = async () => {
                const url = new URL('/api/v1/chart-of-accounts', window.location.origin);
                url.searchParams.set('type', state.type);
                const response = await fetch(url, { headers });
                if (!response.ok) {
                    throw new Error('Gagal memuat bagan akun.');
                }
                return response.json();
            };

            el.addButton.addEventListener('click', () => setAddFormVisible(true));
            el.addCancel.addEventListener('click', () => setAddFormVisible(false));
            el.addSave.addEventListener('click', () => saveAccount());

            el.filterLabel.textContent = 'Akun Kas & Bank';
            loadAccounts()
                .then((data) => {
                    state.accounts = data.accounts || [];
                    renderGroups();
                })
                .catch(() => {
                    el.list.innerHTML = '<div class="bg-white dark:bg-slate-900 px-4 py-6 text-sm text-red-500">Gagal memuat bagan akun.</div>';
                });
        })();
    </script>
@endsection
