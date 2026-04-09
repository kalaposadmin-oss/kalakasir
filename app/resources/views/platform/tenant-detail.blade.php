@extends('platform.layout')

@php
    $pageTitle = 'Tenant Detail';
    $active = 'tenant-detail';
    $tenantDetail = $tenantDetail ?? ['tenant' => [], 'usage_summary' => [], 'users_preview' => [], 'recent_events' => []];
    $tenantName = $tenantDetail['tenant']['name'] ?? 'Tenant';
    $tenantStatus = $tenantDetail['tenant']['status'] ?? '-';
    $tenantPlan = $tenantDetail['tenant']['plan'] ?? '-';
@endphp

@section('platform_content')
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ $tenantName }}</h1>
            <p class="mt-1 text-sm text-slate-500">Detail tenant dan aktivitas terbaru.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button
                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 js-tenant-action"
                data-tenant-id="{{ $targetTenantId ?? '' }}"
                data-tenant-name="{{ $tenantName }}"
                data-default-action="impersonate"
            >Impersonate</button>
            <button
                class="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm js-tenant-action"
                data-tenant-id="{{ $targetTenantId ?? '' }}"
                data-tenant-name="{{ $tenantName }}"
                data-default-action="suspend"
            >Danger Actions</button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
            <span class="material-symbols-outlined text-[18px]">calendar_today</span>
            1 Mar 2026 - 10 Mar 2026
        </div>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">Preset: Last 7 Days</div>
        <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Reset</button>
    </div>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Status</p>
            <p class="mt-2 text-lg font-bold text-emerald-600">{{ ucfirst($tenantStatus) }}</p>
            <p class="mt-1 text-xs text-slate-500">Plan {{ $tenantPlan }}</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Health</p>
            <p class="mt-2 text-lg font-bold text-amber-600">Monitoring</p>
            <p class="mt-1 text-xs text-slate-500">Pantau anomaly & invoice</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">User Count</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format((int) ($tenantDetail['user_count'] ?? count($tenantDetail['users_preview'] ?? []))) }}</p>
            <p class="mt-1 text-xs text-slate-500">Total user aktif & nonaktif</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Revenue 7D</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">Rp {{ number_format((float) ($tenantDetail['usage_summary']['revenue'] ?? 0), 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ number_format((int) ($tenantDetail['usage_summary']['orders'] ?? 0)) }} transaksi</p>
        </article>
    </section>

    <section class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Usage Summary</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-900">Order & Revenue</h3>
                </div>
                <button class="text-xs font-semibold text-primary">30 Hari</button>
            </div>
            <div class="mt-6 h-48 rounded-2xl border border-dashed border-slate-200 bg-slate-50"></div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Users Preview</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-900">Users di tenant ini</h3>
                </div>
                <a
                    class="text-xs font-semibold text-primary"
                    href="/platform/users?tenant_id={{ $targetTenantId ?? '' }}"
                >Lihat semua user</a>
            </div>
            @if (empty($tenantDetail['owner_exists']))
                <div class="mt-3 inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-semibold text-amber-700">
                    <span class="material-symbols-outlined text-[14px]">warning</span>
                    Owner tidak ditemukan
                </div>
            @endif
            <ul class="mt-5 space-y-3">
                @forelse ($tenantDetail['users_preview'] ?? [] as $user)
                    @php
                        $status = $user['status'] ?? 'active';
                        $statusClass = $status === 'active'
                            ? 'bg-emerald-100 text-emerald-700'
                            : 'bg-slate-100 text-slate-600';
                        $roleLabel = $user['role'] ?? '-';
                        $roleKey = strtolower((string) $roleLabel);
                        $roleBadgeClass = match ($roleKey) {
                            'owner' => 'bg-amber-100 text-amber-800',
                            'admin' => 'bg-slate-900 text-white',
                            'supervisor', 'manager' => 'bg-blue-100 text-blue-700',
                            'cashier' => 'bg-emerald-100 text-emerald-700',
                            default => 'bg-slate-100 text-slate-600',
                        };
                        $lastLoginRaw = $user['last_login_at'] ?? null;
                        $createdAtRaw = $user['created_at'] ?? null;
                        $lastLoginLabel = 'never';
                        if ($lastLoginRaw) {
                            $lastLoginTime = \Carbon\CarbonImmutable::parse($lastLoginRaw, 'UTC');
                            $lastLoginLabel = $lastLoginTime->diffForHumans().' · '.$lastLoginTime->format('d M Y, H:i');
                        }
                        $createdLabel = $createdAtRaw
                            ? \Carbon\CarbonImmutable::parse($createdAtRaw, 'UTC')->format('d M Y, H:i')
                            : '-';
                    @endphp
                    <li class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-slate-900">{{ $user['name'] ?? '-' }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $roleBadgeClass }}">
                                        {{ $roleLabel !== '-' ? $roleLabel : 'unknown' }}
                                    </span>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusClass }}">
                                        {{ ucfirst($status) }}
                                    </span>
                                    @if (! empty($user['is_owner']))
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">⭐ Owner</span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-400">{{ $user['email'] ?? '-' }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                                    <span>Last login: {{ $lastLoginLabel }}</span>
                                    <span>Created: {{ $createdLabel }}</span>
                                </div>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-500">Belum ada user.</li>
                @endforelse
            </ul>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Recent Events</p>
                <h3 class="text-sm font-bold text-slate-900">Aktivitas Terakhir Tenant</h3>
            </div>
            <button class="text-xs font-semibold text-primary">Lihat Audit</button>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($tenantDetail['recent_events'] ?? [] as $event)
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $event['reason'] ?? 'Update langganan' }}</p>
                        <p class="text-xs text-slate-400">{{ $event['created_at'] ?? '-' }}</p>
                    </div>
                    <span class="text-xs text-slate-500">{{ $event['request_id'] ?? 'system' }}</span>
                </div>
            @empty
                <div class="px-6 py-4 text-sm text-slate-500">Belum ada event.</div>
            @endforelse
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
