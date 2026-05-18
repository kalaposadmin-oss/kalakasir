@extends('platform.layout')

@php
    $pageTitle = 'Global Users';
    $active = 'users';
    $users = $users ?? [];
    $filters = $filters ?? [];
    $page = (int) ($page ?? 1);
    $lastPage = (int) ($lastPage ?? 1);
@endphp

@section('platform_content')
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Global Users</h1>
            <p class="mt-1 text-sm text-slate-500">Pantau akun lintas tenant dan status akses.</p>
        </div>
        <a href="/superadmin/audit-logs" class="inline-flex w-fit items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
            <span class="material-symbols-outlined text-[18px]">policy</span>
            Audit logs
        </a>
    </div>

    <form method="GET" action="/superadmin/users" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,0.8fr))_auto]">
        <input name="q" value="{{ $filters['q'] ?? '' }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" placeholder="Cari nama atau email" />
        <select name="role" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">Semua role</option>
            @foreach (['owner', 'admin', 'supervisor', 'cashier'] as $role)
                <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ ucfirst($role) }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">Semua status</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
        </select>
        <select name="sort_by" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="created_at" @selected(($filters['sort_by'] ?? 'created_at') === 'created_at')>Terbaru</option>
            <option value="name" @selected(($filters['sort_by'] ?? '') === 'name')>Nama</option>
            <option value="last_login_at" @selected(($filters['sort_by'] ?? '') === 'last_login_at')>Last login</option>
        </select>
        <div class="flex items-center gap-2">
            <button class="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white" type="submit">Filter</button>
            <a href="/superadmin/users" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Reset</a>
        </div>
    </form>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Users</p>
                <h3 class="text-sm font-bold text-slate-900">{{ number_format((int) ($total ?? 0)) }} pengguna ditemukan</h3>
            </div>
            <span class="text-xs font-semibold text-slate-500">Live data</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-3">User</th>
                        <th class="px-6 py-3">Tenant</th>
                        <th class="px-6 py-3">Role</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Last Login</th>
                        <th class="px-6 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        @php
                            $status = $user['status'] ?? 'active';
                            $statusClass = $status === 'active'
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-slate-100 text-slate-600';
                        @endphp
                        <tr>
                            <td class="px-6 py-4">
                                <p class="font-semibold text-slate-900">{{ $user['name'] ?? '-' }}</p>
                                <p class="text-xs text-slate-400">{{ $user['email'] ?? '-' }}</p>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-600">{{ $user['tenant_name'] ?? '-' }}</td>
                            <td class="px-6 py-4 text-xs text-slate-600">{{ $user['role'] ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $statusClass }}">{{ ucfirst($status) }}</span>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">{{ $user['last_login_at'] ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2 text-xs">
                                    <a class="font-semibold text-primary" href="/superadmin/users/{{ $user['user_id'] ?? '' }}">Detail</a>
                                    <button
                                        class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-slate-600 js-user-action"
                                        data-user-id="{{ $user['user_id'] ?? '' }}"
                                        data-user-name="{{ $user['name'] ?? '-' }}"
                                        data-default-action="{{ $status === 'active' ? 'force-logout' : 'unlock' }}"
                                    >Actions</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-6 py-4 text-sm text-slate-500" colspan="6">Belum ada pengguna.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('platform.partials.pagination')
    </section>

    <div id="user-action-modal" class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-900/20 px-4">
        <div class="pointer-events-auto w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                    <span class="material-symbols-outlined text-[20px]">warning</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-900" data-user-modal-title>User Action</p>
                    <p class="text-xs text-slate-500" data-user-modal-subtitle>Semua aksi membutuhkan alasan dan idempotency key.</p>
                </div>
            </div>
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-500" data-user-modal-target>User: -</div>
            <div class="mt-4">
                <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Action</label>
                <select class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs" data-user-modal-action>
                    <option value="reset-password">Reset Password</option>
                    <option value="force-logout">Force Logout</option>
                    <option value="unlock">Unlock User</option>
                </select>
            </div>
            <textarea class="mt-4 w-full rounded-xl border border-slate-200 p-3 text-xs" rows="3" placeholder="Alasan tindakan" data-user-modal-reason></textarea>
            <div class="mt-5 flex items-center justify-end gap-2">
                <button class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600" data-user-modal-cancel>Batal</button>
                <button class="rounded-xl bg-rose-500 px-4 py-2 text-xs font-semibold text-white" data-user-modal-submit>Jalankan Aksi</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const modal = document.getElementById('user-action-modal');
            if (!modal || !window.PlatformUi) {
                return;
            }

            const state = {
                userId: null,
                userName: null,
                action: 'force-logout',
            };

            const el = {
                target: modal.querySelector('[data-user-modal-target]'),
                action: modal.querySelector('[data-user-modal-action]'),
                reason: modal.querySelector('[data-user-modal-reason]'),
                cancel: modal.querySelector('[data-user-modal-cancel]'),
                submit: modal.querySelector('[data-user-modal-submit]'),
            };

            const openModal = ({ userId, userName, defaultAction }) => {
                state.userId = userId;
                state.userName = userName || '-';
                state.action = defaultAction || 'force-logout';
                el.target.textContent = `User: ${state.userName}`;
                el.action.value = state.action;
                el.reason.value = '';
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            document.querySelectorAll('.js-user-action').forEach((button) => {
                button.addEventListener('click', () => {
                    openModal({
                        userId: button.dataset.userId,
                        userName: button.dataset.userName,
                        defaultAction: button.dataset.defaultAction,
                    });
                });
            });

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
                const endpoint = `/api/v1/platform/users/${state.userId}/${action}`;
                const idempotencyKey = window.PlatformUi.createIdempotencyKey(action, state.userId);

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
                    const message = payload?.message || 'Gagal menjalankan aksi user.';
                    const metaRequestId = payload?.request_id || requestId;
                    window.PlatformUi.showToast(message, 'error', metaRequestId);
                    return;
                }

                window.PlatformUi.showToast('Aksi user berhasil dijalankan.', 'success');
                closeModal();
                window.location.reload();
            });
        })();
    </script>
@endpush
