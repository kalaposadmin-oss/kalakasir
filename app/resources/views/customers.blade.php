@extends('layouts.app')

@php
    $customersTenant = \App\Support\TenantContext::get();
    $customersUser = auth()->user();
    $customersRole = null;

    if (! $customersTenant && filled($customersUser?->tenant_id)) {
        $customersTenant = \App\Models\Tenant::query()->find($customersUser->tenant_id);
    }

    if ($customersTenant && $customersUser) {
        $customersRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $customersTenant->id, (string) $customersUser->id);

        if (! $customersRole && filled($customersUser->role ?? null)) {
            $customersRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $customersUser->role);
        }
    }

    abort_unless(in_array($customersRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManageCustomers = in_array($customersRole, ['admin', 'owner', 'superadmin'], true);
    $customersBackUrl = normalize_pos_home_url((string) ($backUrl ?? (session('auth_portal', 'pos') === 'pos' ? pos_default_url() : '/backoffice/dashboard')));
@endphp

@section('page_title', 'Daftar & Detail Pelanggan')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-slate-950 shadow-2xl')
@section('main_class', 'flex-1 flex flex-col overflow-hidden p-0')

@push('head')
        <style>
            .no-scrollbar::-webkit-scrollbar {
                display: none;
            }
            .no-scrollbar {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }

            /* Bottom sheet */
            .cust-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,.45);
                z-index: 90;
                opacity: 0;
                pointer-events: none;
                transition: opacity .25s ease;
            }
            .cust-overlay.active {
                opacity: 1;
                pointer-events: auto;
            }
            .cust-sheet {
                position: fixed;
                left: 50%;
                bottom: 0;
                transform: translateX(-50%) translateY(100%);
                width: 100%;
                max-width: 430px;
                max-height: 85vh;
                z-index: 95;
                background: #fff;
                border-radius: 1.25rem 1.25rem 0 0;
                box-shadow: 0 -8px 30px rgba(0,0,0,.12);
                transition: transform .3s cubic-bezier(.32,.72,0,1);
                display: flex;
                flex-direction: column;
            }
            .dark .cust-sheet {
                background: #0f172a;
            }
            .cust-sheet.active {
                transform: translateX(-50%) translateY(0);
            }
            .cust-sheet-body {
                flex: 1;
                overflow-y: auto;
                padding: 0 1.25rem 2rem;
            }
            .cust-sheet-handle {
                width: 2.5rem;
                height: .25rem;
                border-radius: 9999px;
                background: #cbd5e1;
                margin: .75rem auto;
                flex-shrink: 0;
            }

            /* Card hover */
            .cust-card {
                transition: transform .15s ease, box-shadow .15s ease;
                cursor: pointer;
            }
            .cust-card:active {
                transform: scale(.97);
            }

            /* Tier badge colors */
            .badge-gold {
                background: linear-gradient(135deg, #f59e0b, #d97706);
                color: #fff;
            }
            .badge-regular {
                background: #f1f5f9;
                color: #64748b;
            }
            .dark .badge-regular {
                background: #1e293b;
                color: #94a3b8;
            }

            /* Piutang badge */
            .badge-piutang {
                background: #fef2f2;
                color: #dc2626;
                border: 1px solid #fecaca;
            }
            .dark .badge-piutang {
                background: #450a0a;
                color: #fca5a5;
                border-color: #7f1d1d;
            }
        </style>
@endpush

@section('header')
    <x-page-header title="Pelanggan" :backUrl="$customersBackUrl" :showMenuButton="true">
        <x-slot name="actions">
            @if ($canManageCustomers)
                <button id="btn-add-customer" class="flex size-10 cursor-pointer items-center justify-center rounded-full bg-primary text-white" type="button" aria-label="Tambah pelanggan">
                    <span class="material-symbols-outlined">person_add</span>
                </button>
            @endif
        </x-slot>
    </x-page-header>
@endsection

@section('content')
        {{-- Search & Filter --}}
        <div class="flex-shrink-0 space-y-3 border-b border-slate-200 bg-white px-4 py-3 dark:bg-slate-900">
            <label class="flex flex-col w-full">
                <div class="app-search-control flex items-stretch">
                    <div class="text-slate-400 flex items-center justify-center pl-4">
                        <span class="material-symbols-outlined">search</span>
                    </div>
                    <input id="search-input" class="w-full border-none bg-transparent focus:ring-0 text-slate-900 dark:text-slate-100 placeholder:text-slate-400 px-3 text-sm" placeholder="Cari nama atau nomor telepon..." value="" />
                </div>
            </label>
            <div class="flex gap-2 overflow-x-auto pb-1 no-scrollbar" id="filter-bar">
                <button data-filter="all" class="flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full bg-primary px-4 text-white text-sm font-medium">Semua</button>
                <button data-filter="gold" class="flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full bg-slate-100 dark:bg-slate-800 px-4 text-slate-600 dark:text-slate-300 text-sm font-medium">Member Gold</button>
                <button data-filter="new" class="flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-full bg-slate-100 dark:bg-slate-800 px-4 text-slate-600 dark:text-slate-300 text-sm font-medium">Terbaru</button>
            </div>
        </div>

        {{-- Customer list (scrollable) --}}
        <div class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950">
            <div class="p-4 space-y-3 pb-8">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider" id="customer-count">Daftar Pelanggan (0)</p>
                <div id="customer-list" class="space-y-3"></div>
            </div>
        </div>

        {{-- Bottom sheet overlay --}}
        <div class="cust-overlay" id="cust-overlay"></div>

        {{-- Bottom sheet --}}
        <div class="cust-sheet" id="cust-sheet">
            <div class="cust-sheet-handle"></div>
            <div class="cust-sheet-body no-scrollbar" id="cust-sheet-body"></div>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                let csrfToken = csrfMeta?.content || '';
                const canManageCustomers = @json($canManageCustomers);

                const readCookie = (name) => {
                    const match = document.cookie
                        .split('; ')
                        .find((part) => part.startsWith(`${name}=`));

                    return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : '';
                };

                const requestHeaders = (extra = {}) => {
                    const xsrfToken = readCookie('XSRF-TOKEN');

                    return {
                        ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
                        ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : (csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        ...extra,
                    };
                };

                const refreshCsrfToken = async () => {
                    const response = await fetch('/sanctum/csrf-cookie', {
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (response.ok) {
                        csrfToken = '';
                        csrfMeta?.setAttribute('content', '');
                    }
                };

                const apiFetch = async (url, options = {}, allowRetry = true) => {
                    const response = await fetch(url, {
                        ...options,
                        credentials: 'same-origin',
                        headers: requestHeaders(options.headers || {}),
                    });

                    if (response.status === 419 && allowRetry) {
                        await refreshCsrfToken();

                        return apiFetch(url, options, false);
                    }

                    return response;
                };

                const el = {
                    search: document.getElementById('search-input'),
                    filterBar: document.getElementById('filter-bar'),
                    list: document.getElementById('customer-list'),
                    count: document.getElementById('customer-count'),
                    overlay: document.getElementById('cust-overlay'),
                    sheet: document.getElementById('cust-sheet'),
                    sheetBody: document.getElementById('cust-sheet-body'),
                    addBtn: document.getElementById('btn-add-customer'),
                };

                const state = {
                    customers: [],
                    customerCategories: [],
                    filter: 'all',
                    term: '',
                };

                const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(value || 0);

                const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                }).format(new Date(value));

                const initials = (name) => name
                    .split(' ')
                    .filter(Boolean)
                    .slice(0, 2)
                    .map((part) => part[0])
                    .join('')
                    .toUpperCase();

                const escapeHtml = (value) => String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');

                const customerCategoryColorClasses = {
                    slate: 'bg-slate-100 text-slate-600 ring-slate-200',
                    orange: 'bg-orange-100 text-orange-700 ring-orange-200',
                    emerald: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
                    sky: 'bg-sky-100 text-sky-700 ring-sky-200',
                    violet: 'bg-violet-100 text-violet-700 ring-violet-200',
                    rose: 'bg-rose-100 text-rose-700 ring-rose-200',
                    amber: 'bg-amber-100 text-amber-700 ring-amber-200',
                };

                const normalizeCustomerCategory = (category = {}) => ({
                    id: category.id || '',
                    name: category.name || '-',
                    color: customerCategoryColorClasses[category.color] ? category.color : 'slate',
                    customer_count: Number(category.customer_count || 0),
                });

                const categoryFromCustomer = (customer = {}) => {
                    if (customer.category) {
                        return normalizeCustomerCategory(customer.category);
                    }

                    if (customer.customer_category) {
                        return normalizeCustomerCategory(customer.customer_category);
                    }

                    if (customer.customer_category_name) {
                        return normalizeCustomerCategory({
                            id: customer.customer_category_id,
                            name: customer.customer_category_name,
                            color: customer.customer_category_color,
                        });
                    }

                    return null;
                };

                const normalizeCustomerRow = (customer = {}) => {
                    const category = categoryFromCustomer(customer);

                    return {
                        id: customer.id,
                        name: customer.name || '-',
                        phone: customer.phone || '',
                        address: customer.address || '',
                        customer_category_id: customer.customer_category_id || category?.id || '',
                        category,
                        total_spent: Number(customer.total_spent || 0),
                        total_receivable: Number(customer.total_receivable || 0),
                        order_count: Number(customer.order_count || 0),
                        created_at: customer.created_at || new Date().toISOString(),
                        tier: customer.tier || 'regular',
                        points: Number(customer.points || 0),
                        recent_sales: Array.isArray(customer.recent_sales) ? customer.recent_sales : [],
                    };
                };

                const customerCategoryBadge = (category, fallback = 'Tanpa kategori') => {
                    if (!category) {
                        return `<span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-400 ring-1 ring-slate-200">${fallback}</span>`;
                    }

                    const colorClass = customerCategoryColorClasses[category.color] || customerCategoryColorClasses.slate;

                    return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ring-1 ${colorClass}">${escapeHtml(category.name)}</span>`;
                };

                const customerCategoryOptions = (selectedId = '') => [
                    `<option value="">Tanpa kategori</option>`,
                    ...state.customerCategories.map((category) => `
                        <option value="${escapeHtml(category.id)}" ${String(category.id) === String(selectedId || '') ? 'selected' : ''}>${escapeHtml(category.name)}</option>
                    `),
                ].join('');

                const customerCategoryField = (selectedId = '') => `
                    <div data-customer-category-field class="rounded-xl border border-slate-200 bg-white p-3">
                        <div class="flex items-center justify-between gap-3">
                            <label class="block text-[11px] font-semibold text-slate-500">Kategori Pelanggan</label>
                            <span class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Opsional</span>
                        </div>
                        <select name="customer_category_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10">
                            ${customerCategoryOptions(selectedId)}
                        </select>
                        <div class="mt-2 grid grid-cols-[1fr_auto] gap-2">
                            <input data-customer-category-quick-name type="text" class="min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs outline-none focus:border-primary focus:ring-4 focus:ring-primary/10" placeholder="Kategori baru, contoh: Langganan" />
                            <button type="button" data-customer-category-quick-add class="rounded-xl bg-primary px-3 py-2 text-xs font-bold text-white transition active:scale-95">Tambah</button>
                        </div>
                        <p data-customer-category-feedback class="hidden mt-2 rounded-xl border px-3 py-2 text-[11px] font-medium"></p>
                    </div>
                `;

                const showCustomerCategoryFeedback = (root, message, type = 'success') => {
                    const feedback = root?.querySelector('[data-customer-category-feedback]');
                    if (!feedback) {
                        return;
                    }

                    feedback.textContent = message;
                    feedback.classList.remove('hidden', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-800', 'border-red-200', 'bg-red-50', 'text-red-800');
                    feedback.classList.add(type === 'success' ? 'border-emerald-200' : 'border-red-200');
                    feedback.classList.add(type === 'success' ? 'bg-emerald-50' : 'bg-red-50');
                    feedback.classList.add(type === 'success' ? 'text-emerald-800' : 'text-red-800');
                };

                const bindCustomerCategoryField = (root) => {
                    const field = root?.querySelector('[data-customer-category-field]');
                    if (!field) {
                        return;
                    }

                    const select = field.querySelector('select[name="customer_category_id"]');
                    const input = field.querySelector('[data-customer-category-quick-name]');
                    const addButton = field.querySelector('[data-customer-category-quick-add]');

                    const createCategory = async () => {
                        const name = String(input?.value || '').trim();
                        if (!name) {
                            showCustomerCategoryFeedback(field, 'Isi nama kategori pelanggan dulu.', 'error');
                            input?.focus();
                            return;
                        }

                        const originalLabel = addButton.textContent;
                        addButton.disabled = true;
                        addButton.textContent = 'Menyimpan...';

                        try {
                            const response = await apiFetch('/api/v1/customer-categories', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ name }),
                            });

                            if (!response.ok) {
                                let errorMessage = 'Gagal menambah kategori pelanggan.';

                                try {
                                    const errorPayload = await response.json();
                                    const validationMessage = Object.values(errorPayload?.errors || errorPayload?.details || {})[0]?.[0];
                                    errorMessage = validationMessage || errorPayload.message || errorPayload.error || errorMessage;
                                } catch (parseError) {
                                    const errorText = await response.text();
                                    if (errorText) {
                                        errorMessage = errorText;
                                    }
                                }

                                throw new Error(errorMessage);
                            }

                            const result = await response.json();
                            const category = normalizeCustomerCategory(result.customer_category || result.category || result);
                            state.customerCategories = [
                                category,
                                ...state.customerCategories.filter((row) => String(row.id) !== String(category.id)),
                            ];
                            select.innerHTML = customerCategoryOptions(category.id);
                            input.value = '';
                            showCustomerCategoryFeedback(field, 'Kategori dibuat dan langsung dipilih.');
                        } catch (error) {
                            showCustomerCategoryFeedback(field, error?.message || 'Gagal menambah kategori pelanggan.', 'error');
                        } finally {
                            addButton.disabled = false;
                            addButton.textContent = originalLabel;
                        }
                    };

                    addButton?.addEventListener('click', createCategory);
                    input?.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            createCategory();
                        }
                    });
                };

                /* ── Filtering ── */
                const filterCustomers = () => {
                    const term = state.term.toLowerCase();
                    let items = state.customers.filter((customer) => {
                        const matchTerm = customer.name.toLowerCase().includes(term)
                            || (customer.phone || '').toLowerCase().includes(term);
                        if (!matchTerm) {
                            return false;
                        }
                        if (state.filter === 'gold') {
                            return customer.tier === 'gold';
                        }
                        return true;
                    });

                    if (state.filter === 'new') {
                        items = items.slice().sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                    }

                    return items;
                };

                /* ── Bottom Sheet ── */
                const openSheet = (customer, options = {}) => {
                    el.overlay.classList.add('active');
                    el.sheet.classList.add('active');
                    document.body.style.overflow = 'hidden';

                    const badge = customer.tier === 'gold'
                        ? '<span class="badge-gold px-2 py-0.5 rounded-full text-[10px] font-bold uppercase">Gold Member</span>'
                        : '<span class="badge-regular px-2 py-0.5 rounded-full text-[10px] font-bold uppercase">Regular</span>';

                    const piutangBadge = (customer.total_receivable || 0) > 0
                        ? `<span class="badge-piutang px-2 py-0.5 rounded-full text-[10px] font-bold">Piutang ${formatCurrency(customer.total_receivable)}</span>`
                        : '';
                    const categoryBadge = customerCategoryBadge(customer.category);

                    const noticeBanner = options.notice
                        ? `<div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800">${escapeHtml(options.notice)}</div>`
                        : '';

                    const recent = (customer.recent_sales || []).map((sale) => `
                        <div class="flex justify-between items-center p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">INV-${String(sale.id).slice(0, 6).toUpperCase()}</p>
                                <p class="text-[11px] text-slate-500 mt-0.5">${formatDate(sale.created_at)}</p>
                            </div>
                            <p class="font-bold text-slate-900 dark:text-white text-sm">${formatCurrency(sale.total)}</p>
                        </div>
                    `).join('');

                    const editMode = options.editMode === true;
                    const editSection = canManageCustomers ? `
                        <div data-edit-section class="${editMode ? '' : 'hidden'} mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-slate-400">Edit pelanggan</p>
                                    <h4 class="mt-1 text-sm font-bold text-slate-900">Ubah data master</h4>
                                </div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-bold text-slate-500 ring-1 ring-slate-200">Admin</span>
                            </div>
                            <form data-customer-edit-form class="mt-4 space-y-3">
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-slate-500">Nama</label>
                                    <input name="name" type="text" value="${escapeHtml(customer.name)}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10" required />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-slate-500">No. WhatsApp</label>
                                    <input name="phone" type="text" value="${escapeHtml(customer.phone || '')}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10" placeholder="08xxxxxxxxxx" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-slate-500">Alamat</label>
                                    <textarea name="address" rows="3" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 resize-none" placeholder="Alamat pelanggan">${escapeHtml(customer.address || '')}</textarea>
                                </div>
                                ${customerCategoryField(customer.customer_category_id)}
                                <p data-edit-feedback class="hidden rounded-xl px-3 py-2 text-xs font-medium"></p>
                                <div class="flex gap-2">
                                    <button type="submit" data-edit-save class="flex-1 rounded-xl bg-slate-900 px-3 py-2.5 text-xs font-bold text-white transition active:scale-95">Simpan perubahan</button>
                                </div>
                            </form>
                        </div>
                    ` : '';

                    el.sheetBody.innerHTML = `
                        <!-- Profile header -->
                        <div class="flex items-center gap-4 mb-5">
                            <div class="size-14 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-xl flex-shrink-0">${initials(customer.name)}</div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white truncate">${escapeHtml(customer.name)}</h3>
                                <p class="text-sm text-slate-500">${escapeHtml(customer.phone || '-')}</p>
                                <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-500">${customer.address ? escapeHtml(customer.address) : 'Alamat belum diisi'}</p>
                                <div class="flex flex-wrap gap-1.5 mt-1.5">${badge} ${piutangBadge} ${categoryBadge}</div>
                            </div>
                            ${canManageCustomers ? `
                                <button type="button" data-edit-toggle class="${editMode ? 'hidden' : 'flex'} size-10 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition active:scale-95" aria-label="Edit pelanggan">
                                    <span class="material-symbols-outlined text-[20px]">edit</span>
                                </button>
                            ` : ''}
                        </div>

                        ${noticeBanner}

                        ${editSection}

                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-3 mb-5">
                            <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3 text-center">
                                <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">Total Belanja</p>
                                <p class="text-sm font-bold text-slate-900 dark:text-white">${formatCurrency(customer.total_spent)}</p>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3 text-center">
                                <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">Transaksi</p>
                                <p class="text-sm font-bold text-slate-900 dark:text-white">${customer.order_count || 0}x</p>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3 text-center">
                                <p class="text-[10px] text-slate-400 uppercase font-bold mb-1">Poin</p>
                                <p class="text-sm font-bold text-primary">${customer.points || 0}</p>
                            </div>
                        </div>

                        <!-- Riwayat transaksi -->
                        <div class="space-y-2.5">
                            <p class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Riwayat Transaksi</p>
                            ${recent || '<p class="text-sm text-slate-400">Belum ada transaksi.</p>'}
                        </div>

                        <div class="mt-5 rounded-2xl border border-orange-100 bg-gradient-to-br from-orange-50 to-white p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-orange-500">Link checkout</p>
                                    <h4 class="mt-1 text-sm font-bold text-slate-900 dark:text-white">Buka katalog dengan data customer ini</h4>
                                    <p class="mt-1 text-xs leading-relaxed text-slate-500">Link pendek permanen untuk disimpan di catatan WhatsApp. Buat ulang hanya jika link lama perlu dicabut.</p>
                                </div>
                                <button type="button" data-generate-order-link class="shrink-0 rounded-full bg-primary px-3 py-2 text-xs font-bold text-white shadow-sm shadow-primary/20 transition active:scale-95">Lihat link</button>
                            </div>

                            <div data-order-link-state class="mt-3 hidden rounded-xl border border-slate-200 bg-white p-3 dark:bg-slate-900 dark:border-slate-800">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Link siap dibagikan</p>
                                <textarea data-order-link-output readonly rows="3" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"></textarea>
                                <div class="mt-2 flex gap-2">
                                    <button type="button" data-copy-order-link class="flex-1 rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white transition active:scale-95">Salin link</button>
                                    <button type="button" data-copy-order-message class="flex-1 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white transition active:scale-95">Salin pesan WA</button>
                                    <button type="button" data-open-order-link class="rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700 transition active:scale-95 dark:bg-slate-800 dark:text-slate-200">Buka</button>
                                </div>
                                <button type="button" data-regenerate-order-link class="mt-2 w-full rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-xs font-bold text-red-700 transition active:scale-95">Buat ulang dan cabut link lama</button>
                                <p data-order-link-note class="mt-2 text-[11px] leading-relaxed text-slate-500"></p>
                            </div>
                        </div>
                    `;

                    const generateLinkBtn = el.sheetBody.querySelector('[data-generate-order-link]');
                    const linkState = el.sheetBody.querySelector('[data-order-link-state]');
                    const linkOutput = el.sheetBody.querySelector('[data-order-link-output]');
                    const copyLinkBtn = el.sheetBody.querySelector('[data-copy-order-link]');
                    const copyMessageBtn = el.sheetBody.querySelector('[data-copy-order-message]');
                    const openLinkBtn = el.sheetBody.querySelector('[data-open-order-link]');
                    const regenerateLinkBtn = el.sheetBody.querySelector('[data-regenerate-order-link]');
                    const linkNote = el.sheetBody.querySelector('[data-order-link-note]');
                    const editToggleBtn = el.sheetBody.querySelector('[data-edit-toggle]');
                    const editSectionEl = el.sheetBody.querySelector('[data-edit-section]');
                    const editForm = el.sheetBody.querySelector('[data-customer-edit-form]');
                    const editFeedback = el.sheetBody.querySelector('[data-edit-feedback]');
                    const editSaveBtn = el.sheetBody.querySelector('[data-edit-save]');

                    bindCustomerCategoryField(editForm);

                    let generatedOrderLink = '';
                    let generatedOrderMessage = '';

                    const showEditFeedback = (message, type = 'success') => {
                        if (!editFeedback) {
                            return;
                        }

                        editFeedback.textContent = message;
                        editFeedback.classList.remove('hidden', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-800', 'border-red-200', 'bg-red-50', 'text-red-800');
                        editFeedback.classList.add('border');
                        editFeedback.classList.add(type === 'success' ? 'border-emerald-200' : 'border-red-200');
                        editFeedback.classList.add(type === 'success' ? 'bg-emerald-50' : 'bg-red-50');
                        editFeedback.classList.add(type === 'success' ? 'text-emerald-800' : 'text-red-800');
                    };

                    const showLinkState = () => {
                        linkState.classList.remove('hidden');
                    };

                    const applyOrderLinkPayload = (payload) => {
                        generatedOrderLink = payload.order_link || '';
                        generatedOrderMessage = [
                            `Halo ${customer.name}, ini link order tetap Anda:`,
                            generatedOrderLink,
                            '',
                            'Silakan buka link tersebut kapan saja untuk order ulang. Terima kasih.'
                        ].join('\n');
                        linkOutput.value = generatedOrderLink;
                        linkNote.textContent = payload.message || 'Link permanen siap dipakai. Simpan di catatan WhatsApp pelanggan.';
                        showLinkState();
                    };

                    editToggleBtn?.addEventListener('click', () => {
                        editSectionEl?.classList.remove('hidden');
                        editToggleBtn.classList.add('hidden');
                        editForm?.querySelector('input[name="name"]')?.focus();
                    });

                    generateLinkBtn?.addEventListener('click', async () => {
                        const originalLabel = generateLinkBtn.textContent;
                        generateLinkBtn.disabled = true;
                        generateLinkBtn.textContent = 'Membuat...';

                        try {
                            const response = await apiFetch(`/api/v1/customers/${customer.id}/order-link`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({}),
                            });

                            if (!response.ok) {
                                let errorMessage = 'Gagal membuat link checkout.';

                                try {
                                    const errorPayload = await response.json();
                                    errorMessage = errorPayload.message || errorPayload.error || errorMessage;
                                } catch (parseError) {
                                    const errorText = await response.text();
                                    if (errorText) {
                                        errorMessage = errorText;
                                    }
                                }

                                throw new Error(errorMessage);
                            }

                            const payload = await response.json();
                            applyOrderLinkPayload(payload);
                        } catch (error) {
                            generatedOrderLink = '';
                            linkOutput.value = '';
                            linkNote.textContent = error?.message || 'Gagal membuat link checkout.';
                            showLinkState();
                        } finally {
                            generateLinkBtn.disabled = false;
                            generateLinkBtn.textContent = originalLabel;
                        }
                    });

                    regenerateLinkBtn?.addEventListener('click', async () => {
                        if (!await window.PosUi.confirmRiskActionAsync({
                            title: 'Buat Ulang Link',
                            message: 'Link lama yang tersimpan di WhatsApp tidak akan bisa dipakai lagi. Lanjutkan?',
                            confirmText: 'Buat Ulang',
                            icon: 'link',
                        })) {
                            return;
                        }

                        const originalLabel = regenerateLinkBtn.textContent;
                        regenerateLinkBtn.disabled = true;
                        regenerateLinkBtn.textContent = 'Membuat ulang...';

                        try {
                            const response = await apiFetch(`/api/v1/customers/${customer.id}/order-link/regenerate`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({}),
                            });

                            if (!response.ok) {
                                let errorMessage = 'Gagal membuat ulang link order.';

                                try {
                                    const errorPayload = await response.json();
                                    errorMessage = errorPayload.message || errorPayload.error || errorMessage;
                                } catch (parseError) {
                                    const errorText = await response.text();
                                    if (errorText) {
                                        errorMessage = errorText;
                                    }
                                }

                                throw new Error(errorMessage);
                            }

                            const payload = await response.json();
                            applyOrderLinkPayload(payload);
                        } catch (error) {
                            linkNote.textContent = error?.message || 'Gagal membuat ulang link order.';
                            showLinkState();
                        } finally {
                            regenerateLinkBtn.disabled = false;
                            regenerateLinkBtn.textContent = originalLabel;
                        }
                    });

                    copyLinkBtn?.addEventListener('click', async () => {
                        if (!generatedOrderLink) {
                            return;
                        }

                        try {
                            await navigator.clipboard.writeText(generatedOrderLink);
                            const originalLabel = copyLinkBtn.textContent;
                            copyLinkBtn.textContent = 'Tersalin';
                            setTimeout(() => {
                                copyLinkBtn.textContent = originalLabel;
                            }, 1500);
                        } catch (error) {
                            linkNote.textContent = 'Salin manual dari kotak link di atas.';
                        }
                    });

                    copyMessageBtn?.addEventListener('click', async () => {
                        if (!generatedOrderMessage) {
                            return;
                        }

                        try {
                            await navigator.clipboard.writeText(generatedOrderMessage);
                            const originalLabel = copyMessageBtn.textContent;
                            copyMessageBtn.textContent = 'Pesan tersalin';
                            setTimeout(() => {
                                copyMessageBtn.textContent = originalLabel;
                            }, 1500);
                        } catch (error) {
                            linkNote.textContent = 'Salin manual pesan WA dari kotak link setelah link dibuat.';
                        }
                    });

                    openLinkBtn?.addEventListener('click', () => {
                        if (generatedOrderLink) {
                            window.open(generatedOrderLink, '_blank', 'noopener');
                        }
                    });

                    editForm?.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        if (!editSaveBtn) {
                            return;
                        }

                        const formData = new FormData(editForm);
                        const payload = {
                            name: String(formData.get('name') || '').trim(),
                            phone: String(formData.get('phone') || '').trim(),
                            address: String(formData.get('address') || '').trim(),
                            customer_category_id: String(formData.get('customer_category_id') || '').trim() || null,
                        };

                        editSaveBtn.disabled = true;
                        editSaveBtn.textContent = 'Menyimpan...';

                        try {
                            const response = await apiFetch(`/api/v1/customers/${customer.id}`, {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify(payload),
                            });

                            if (!response.ok) {
                                let errorMessage = 'Gagal menyimpan data pelanggan.';

                                try {
                                    const errorPayload = await response.json();
                                    errorMessage = errorPayload.message || errorPayload.error || errorMessage;
                                } catch (parseError) {
                                    const errorText = await response.text();
                                    if (errorText) {
                                        errorMessage = errorText;
                                    }
                                }

                                throw new Error(errorMessage);
                            }

                            const result = await response.json();
                            const updatedCustomer = normalizeCustomerRow({
                                ...customer,
                                ...(result.customer || {}),
                            });

                            state.customers = state.customers.map((row) => row.id === customer.id ? { ...row, ...updatedCustomer } : row);
                            render();
                            openSheet(updatedCustomer, { editMode: true, notice: 'Data pelanggan berhasil diperbarui.' });
                        } catch (error) {
                            showEditFeedback(error?.message || 'Gagal menyimpan data pelanggan.', 'error');
                        } finally {
                            editSaveBtn.disabled = false;
                            editSaveBtn.textContent = 'Simpan perubahan';
                        }
                    });
                };

                const openCreateSheet = () => {
                    if (!canManageCustomers) {
                        return;
                    }

                    el.overlay.classList.add('active');
                    el.sheet.classList.add('active');
                    document.body.style.overflow = 'hidden';

                    el.sheetBody.innerHTML = `
                        <div class="mb-5 flex items-center gap-4">
                            <div class="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <span class="material-symbols-outlined text-[28px]">person_add</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-primary">Pelanggan baru</p>
                                <h3 class="mt-1 text-lg font-bold text-slate-900 dark:text-white">Tambah data pelanggan</h3>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500">Nama wajib diisi. Nomor WhatsApp dan alamat bisa dilengkapi nanti.</p>
                            </div>
                        </div>
                        <form data-customer-create-form class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div>
                                <label class="mb-1 block text-[11px] font-semibold text-slate-500">Nama</label>
                                <input name="name" type="text" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10" placeholder="Contoh: Aldi" required />
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-semibold text-slate-500">No. WhatsApp</label>
                                <input name="phone" type="text" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10" placeholder="08xxxxxxxxxx" />
                            </div>
                            <div>
                                <label class="mb-1 block text-[11px] font-semibold text-slate-500">Alamat</label>
                                <textarea name="address" rows="3" class="w-full resize-none rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10" placeholder="Alamat pelanggan"></textarea>
                            </div>
                            ${customerCategoryField('')}
                            <p data-create-feedback class="hidden rounded-xl border px-3 py-2 text-xs font-medium"></p>
                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <button type="button" data-create-cancel class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-bold text-slate-700 transition active:scale-95">Batal</button>
                                <button type="submit" data-create-save class="rounded-xl bg-slate-900 px-3 py-2.5 text-xs font-bold text-white transition active:scale-95">Simpan</button>
                            </div>
                        </form>
                    `;

                    const form = el.sheetBody.querySelector('[data-customer-create-form]');
                    const saveBtn = el.sheetBody.querySelector('[data-create-save]');
                    const cancelBtn = el.sheetBody.querySelector('[data-create-cancel]');
                    const feedback = el.sheetBody.querySelector('[data-create-feedback]');
                    const nameInput = form?.querySelector('input[name="name"]');

                    bindCustomerCategoryField(form);

                    const showCreateFeedback = (message, type = 'error') => {
                        if (!feedback) {
                            return;
                        }

                        feedback.textContent = message;
                        feedback.classList.remove('hidden', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-800', 'border-red-200', 'bg-red-50', 'text-red-800');
                        feedback.classList.add(type === 'success' ? 'border-emerald-200' : 'border-red-200');
                        feedback.classList.add(type === 'success' ? 'bg-emerald-50' : 'bg-red-50');
                        feedback.classList.add(type === 'success' ? 'text-emerald-800' : 'text-red-800');
                    };

                    cancelBtn?.addEventListener('click', closeSheet);
                    window.setTimeout(() => nameInput?.focus(), 50);

                    form?.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        const formData = new FormData(form);
                        const payload = {
                            name: String(formData.get('name') || '').trim(),
                            phone: String(formData.get('phone') || '').trim(),
                            address: String(formData.get('address') || '').trim(),
                            customer_category_id: String(formData.get('customer_category_id') || '').trim() || null,
                        };

                        if (!payload.name) {
                            showCreateFeedback('Nama pelanggan wajib diisi.');
                            nameInput?.focus();
                            return;
                        }

                        saveBtn.disabled = true;
                        saveBtn.textContent = 'Menyimpan...';

                        try {
                            const response = await apiFetch('/api/v1/customers', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify(payload),
                            });

                            if (!response.ok) {
                                let errorMessage = 'Gagal menambah pelanggan.';

                                try {
                                    const errorPayload = await response.json();
                                    const validationMessage = Object.values(errorPayload?.errors || errorPayload?.details || {})[0]?.[0];
                                    errorMessage = validationMessage || errorPayload.message || errorPayload.error || errorMessage;
                                } catch (parseError) {
                                    const errorText = await response.text();
                                    if (errorText) {
                                        errorMessage = errorText;
                                    }
                                }

                                throw new Error(errorMessage);
                            }

                            const result = await response.json();
                            const createdCustomer = normalizeCustomerRow(result.customer || payload);
                            state.customers = [
                                createdCustomer,
                                ...state.customers.filter((row) => String(row.id) !== String(createdCustomer.id)),
                            ];
                            render();
                            openSheet(createdCustomer, { notice: result.message || 'Pelanggan berhasil ditambahkan.' });
                        } catch (error) {
                            showCreateFeedback(error?.message || 'Gagal menambah pelanggan.');
                        } finally {
                            saveBtn.disabled = false;
                            saveBtn.textContent = 'Simpan';
                        }
                    });
                };

                const closeSheet = () => {
                    el.overlay.classList.remove('active');
                    el.sheet.classList.remove('active');
                    document.body.style.overflow = '';
                };

                el.overlay.addEventListener('click', closeSheet);

                /* ── Render Customer Cards ── */
                const renderList = (customers) => {
                    el.list.innerHTML = '';

                    if (customers.length === 0) {
                        el.list.innerHTML = '<div class="text-sm text-slate-400 text-center py-8">Belum ada pelanggan.</div>';
                        return;
                    }

                    customers.forEach((customer) => {
                        const card = document.createElement('div');
                        card.className = 'cust-card flex items-center gap-3 bg-white dark:bg-slate-900 px-4 py-3.5 rounded-xl border border-slate-100 dark:border-slate-800';

                        const tierBadge = customer.tier === 'gold'
                            ? '<span class="badge-gold px-1.5 py-0.5 rounded text-[9px] font-bold uppercase leading-none">GOLD</span>'
                            : '<span class="badge-regular px-1.5 py-0.5 rounded text-[9px] font-bold uppercase leading-none">REGULAR</span>';

                        const avatarBg = customer.tier === 'gold'
                            ? 'background: linear-gradient(135deg, #fef3c7, #fde68a); color: #b45309;'
                            : 'background: #eff6ff; color: #3b82f6;';

                        const piutangInfo = (customer.total_receivable || 0) > 0
                            ? `<span class="text-red-500 text-[10px] font-semibold">Piutang ${formatCurrency(customer.total_receivable)}</span>`
                            : '';

                        const spentInfo = (customer.total_spent || 0) > 0
                            ? `<span class="text-xs font-bold text-slate-900 dark:text-white">${formatCurrency(customer.total_spent)}</span>`
                            : '<span class="text-xs text-slate-400">Rp 0</span>';
                        const categoryInfo = customer.category
                            ? `<div class="mt-1 flex">${customerCategoryBadge(customer.category, '')}</div>`
                            : '';

                        card.innerHTML = `
                            <div class="size-11 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0" style="${avatarBg}">${initials(customer.name)}</div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-bold text-slate-900 dark:text-white truncate">${escapeHtml(customer.name)}</p>
                                    ${tierBadge}
                                </div>
                                <p class="text-xs text-slate-500 mt-0.5">${escapeHtml(customer.phone || '-')}</p>
                                ${categoryInfo}
                            </div>
                            <div class="text-right flex-shrink-0 flex flex-col items-end gap-0.5">
                                ${spentInfo}
                                ${piutangInfo}
                            </div>
                            <span class="material-symbols-outlined text-slate-300 dark:text-slate-600 text-lg flex-shrink-0">chevron_right</span>
                        `;

                        card.addEventListener('click', () => openSheet(customer));
                        el.list.appendChild(card);
                    });
                };

                const render = () => {
                    const filtered = filterCustomers();
                    el.count.textContent = `Daftar Pelanggan (${filtered.length})`;
                    renderList(filtered);
                };

                /* ── Load data ── */
                const loadCustomerCategories = async () => {
                    const response = await apiFetch('/api/v1/customer-categories');
                    if (!response.ok) {
                        state.customerCategories = [];
                        return;
                    }

                    const data = await response.json();
                    state.customerCategories = (data.customer_categories || []).map(normalizeCustomerCategory);
                };

                const loadCustomers = async () => {
                    const response = await apiFetch('/api/v1/customers');
                    if (!response.ok) {
                        el.list.innerHTML = '<div class="text-sm text-red-500">Gagal memuat pelanggan.</div>';
                        return;
                    }
                    const data = await response.json();
                    state.customers = (data.customers || []).map(normalizeCustomerRow);
                    render();
                };

                /* ── Events ── */
                el.search.addEventListener('input', (event) => {
                    state.term = event.target.value;
                    render();
                });

                el.filterBar.addEventListener('click', (event) => {
                    const button = event.target.closest('button[data-filter]');
                    if (!button) {
                        return;
                    }
                    state.filter = button.dataset.filter;
                    document.querySelectorAll('#filter-bar button').forEach((btn) => {
                        btn.classList.toggle('bg-primary', btn === button);
                        btn.classList.toggle('text-white', btn === button);
                        btn.classList.toggle('bg-slate-100', btn !== button);
                        btn.classList.toggle('text-slate-600', btn !== button);
                    });
                    render();
                });

                el.addBtn?.addEventListener('click', openCreateSheet);

                Promise.all([loadCustomerCategories(), loadCustomers()]).catch(() => {
                    el.list.innerHTML = '<div class="text-sm text-red-500">Gagal memuat pelanggan.</div>';
                });
            })();
        </script>
@endsection
