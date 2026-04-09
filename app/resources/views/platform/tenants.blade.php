@extends('platform.layout')

@php
    $pageTitle = 'Tenants';
    $active = 'tenants';
    $tenants = $tenants ?? [];
@endphp

@section('platform_content')
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Tenants</h1>
            <p class="mt-1 text-sm text-slate-500">Kelola status tenant dan kesehatan billing.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Export CSV</button>
            <button class="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm">Tambah Tenant</button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
            <span class="material-symbols-outlined text-[18px]">search</span>
            <span>Cari nama tenant atau ID</span>
        </div>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
            Status: Active
        </div>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
            Plan: Growth
        </div>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
            Health: Billing Issue
        </div>
        <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Reset</button>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Tenant List</p>
                <h3 class="text-sm font-bold text-slate-900">{{ number_format((int) ($total ?? 0)) }} tenants ditemukan</h3>
            </div>
            <button class="text-xs font-semibold text-primary">Lihat semua</button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Tenant</th>
                        <th class="px-6 py-3">Plan</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Health</th>
                        <th class="px-6 py-3">Users</th>
                        <th class="px-6 py-3">Last Active</th>
                        <th class="px-6 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($tenants as $tenant)
                        @php
                            $status = $tenant['status'] ?? 'active';
                            $statusClass = $status === 'active'
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-slate-100 text-slate-600';
                            $health = $tenant['health'] ?? 'healthy';
                            $healthClass = $health === 'billing_issue'
                                ? 'bg-amber-100 text-amber-700'
                                : ($health === 'suspended' ? 'bg-slate-100 text-slate-600' : 'bg-emerald-100 text-emerald-700');
                        @endphp
                        <tr>
                            <td class="px-6 py-4">
                                <p class="font-semibold text-slate-900">{{ $tenant['name'] ?? '-' }}</p>
                                <p class="text-xs text-slate-400">{{ $tenant['tenant_id'] ?? '-' }}</p>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-600">{{ $tenant['plan'] ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $statusClass }}">{{ ucfirst($status) }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $healthClass }}">{{ str_replace('_', ' ', ucfirst($health)) }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ number_format((int) ($tenant['user_count'] ?? 0)) }}</td>
                            <td class="px-6 py-4 text-xs text-slate-500">{{ $tenant['last_active_at'] ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2 text-xs">
                                    <a href="/platform/tenants/{{ $tenant['tenant_id'] ?? '' }}" class="font-semibold text-primary">Detail</a>
                                    <button
                                        class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-slate-600 js-tenant-action"
                                        data-tenant-id="{{ $tenant['tenant_id'] ?? '' }}"
                                        data-tenant-name="{{ $tenant['name'] ?? '-' }}"
                                        data-default-action="{{ $status === 'active' ? 'suspend' : 'unsuspend' }}"
                                    >Actions</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-6 py-4 text-sm text-slate-500" colspan="7">Belum ada tenant.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('platform.partials.states')

    <div id="tenant-action-modal" class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-900/20 px-4">
        <div class="pointer-events-auto w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                    <span class="material-symbols-outlined text-[20px]">warning</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-900" data-tenant-modal-title>Tenant Action</p>
                    <p class="text-xs text-slate-500" data-tenant-modal-subtitle>Semua aksi membutuhkan alasan dan idempotency key.</p>
                </div>
            </div>
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-500" data-tenant-modal-target>Tenant: -</div>
            <div class="mt-4">
                <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Action</label>
                <select class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs" data-tenant-modal-action>
                    <option value="suspend">Suspend Tenant</option>
                    <option value="unsuspend">Unsuspend Tenant</option>
                    <option value="force-logout">Force Logout Semua User</option>
                    <option value="reset-owner-password">Reset Owner Password</option>
                    <option value="unlock">Unlock Tenant</option>
                    <option value="impersonate">Impersonate Tenant</option>
                </select>
            </div>
            <textarea class="mt-4 w-full rounded-xl border border-slate-200 p-3 text-xs" rows="3" placeholder="Alasan tindakan" data-tenant-modal-reason></textarea>
            <p class="mt-2 text-[11px] text-slate-400" data-tenant-modal-hint>Impersonate akan memiliki expiry otomatis.</p>
            <div class="mt-5 flex items-center justify-end gap-2">
                <button class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600" data-tenant-modal-cancel>Batal</button>
                <button class="rounded-xl bg-rose-500 px-4 py-2 text-xs font-semibold text-white" data-tenant-modal-submit>Jalankan Aksi</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const modal = document.getElementById('tenant-action-modal');
            if (!modal || !window.PlatformUi) {
                return;
            }

            const state = {
                tenantId: null,
                tenantName: null,
                action: 'suspend',
            };

            const el = {
                title: modal.querySelector('[data-tenant-modal-title]'),
                subtitle: modal.querySelector('[data-tenant-modal-subtitle]'),
                target: modal.querySelector('[data-tenant-modal-target]'),
                action: modal.querySelector('[data-tenant-modal-action]'),
                reason: modal.querySelector('[data-tenant-modal-reason]'),
                hint: modal.querySelector('[data-tenant-modal-hint]'),
                cancel: modal.querySelector('[data-tenant-modal-cancel]'),
                submit: modal.querySelector('[data-tenant-modal-submit]'),
            };

            const openModal = ({ tenantId, tenantName, defaultAction }) => {
                state.tenantId = tenantId;
                state.tenantName = tenantName || '-';
                state.action = defaultAction || 'suspend';
                el.target.textContent = `Tenant: ${state.tenantName}`;
                el.action.value = state.action;
                el.reason.value = '';
                updateHint();
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            const updateHint = () => {
                if (el.action.value === 'impersonate') {
                    el.hint.textContent = 'Impersonate akan memiliki expiry otomatis (±30 menit).';
                } else {
                    el.hint.textContent = 'Alasan akan dicatat di audit log.';
                }
            };

            document.querySelectorAll('.js-tenant-action').forEach((button) => {
                button.addEventListener('click', () => {
                    openModal({
                        tenantId: button.dataset.tenantId,
                        tenantName: button.dataset.tenantName,
                        defaultAction: button.dataset.defaultAction,
                    });
                });
            });

            el.action.addEventListener('change', updateHint);
            el.cancel.addEventListener('click', closeModal);
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            el.submit.addEventListener('click', async () => {
                const reason = el.reason.value.trim();
                if (reason.length < 10) {
                    window.PlatformUi.showToast('Alasan minimal 10 karakter.', 'error');
                    return;
                }

                const action = el.action.value;
                const endpoint = `/api/v1/platform/tenants/${state.tenantId}/${action}`;
                const idempotencyKey = window.PlatformUi.createIdempotencyKey(action, state.tenantId);

                el.submit.disabled = true;
                el.submit.textContent = 'Memproses...';

                const { response, payload, requestId } = await window.PlatformUi.apiFetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Idempotency-Key': idempotencyKey,
                    },
                    body: JSON.stringify({ reason }),
                });

                el.submit.disabled = false;
                el.submit.textContent = 'Jalankan Aksi';

                if (!response.ok) {
                    let message = payload?.message || 'Gagal menjalankan aksi tenant.';
                    if (action === 'reset-owner-password' && response.status === 404 && payload?.code === 'NOT_FOUND') {
                        message = 'Owner tidak ditemukan. Pastikan tenant punya user dengan role owner/pemilik.';
                    }
                    const metaRequestId = payload?.request_id || requestId;
                    window.PlatformUi.showToast(message, 'error', metaRequestId);
                    return;
                }

                const expiry = payload?.data?.impersonation_expires_at;
                if (action === 'impersonate') {
                    closeModal();
                    window.location.href = '/portal/switch/backoffice?next=/backoffice/dashboard';
                    return;
                }

                const successMessage = expiry
                    ? `Impersonate aktif sampai ${expiry}.`
                    : 'Aksi tenant berhasil dijalankan.';

                window.PlatformUi.showToast(successMessage, 'success');
                closeModal();
                window.location.reload();
            });
        })();
    </script>
@endpush
