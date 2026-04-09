@extends('layouts.app')

@php
    $subscriptionChangeTenant = \App\Support\TenantContext::get();
    $subscriptionChangeUser = auth()->user();
    $subscriptionChangeRole = null;

    if (! $subscriptionChangeTenant && filled($subscriptionChangeUser?->tenant_id)) {
        $subscriptionChangeTenant = \App\Models\Tenant::query()->find($subscriptionChangeUser->tenant_id);
    }

    if ($subscriptionChangeTenant && $subscriptionChangeUser) {
        $subscriptionChangeRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $subscriptionChangeTenant->id, (string) $subscriptionChangeUser->id);

        if (! $subscriptionChangeRole && filled($subscriptionChangeUser->role ?? null)) {
            $subscriptionChangeRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $subscriptionChangeUser->role);
        }
    }

    abort_unless(in_array($subscriptionChangeRole, ['admin', 'owner', 'superadmin'], true), 403);
@endphp

@section('page_title', 'Ubah Paket Langganan')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display min-h-screen')
@section('app_shell_class', 'relative flex h-screen w-full flex-col overflow-hidden bg-white dark:bg-background-dark')
@section('main_class', 'flex-1 overflow-y-auto p-0 lg:pl-72')

@section('header')
    <x-page-header title="Ubah Paket" backUrl="/backoffice/settings/subscription" />
@endsection

@section('content')
    <div class="mx-auto w-full max-w-5xl p-4 space-y-4 lg:px-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Perubahan Paket</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Gunakan mode perubahan sesuai kebutuhan operasional. Logika upgrade/downgrade tetap mengikuti policy sistem.</p>

            <div class="mt-4 space-y-3">
                <div>
                    <label for="plan" class="text-xs font-bold uppercase tracking-widest text-slate-500">Plan Tujuan</label>
                    <select id="plan" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                        <option value="S">S</option>
                        <option value="M">M</option>
                        <option value="P">P</option>
                    </select>
                </div>
                <div>
                    <label for="mode" class="text-xs font-bold uppercase tracking-widest text-slate-500">Mode Perubahan</label>
                    <select id="mode" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
                        <option value="auto">Auto (upgrade langsung, downgrade akhir periode)</option>
                        <option value="immediate">Immediate</option>
                        <option value="end_of_period">Akhir Periode</option>
                    </select>
                </div>
                <div>
                    <label for="reason" class="text-xs font-bold uppercase tracking-widest text-slate-500">Alasan (min 10 karakter)</label>
                    <textarea id="reason" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800" placeholder="Contoh: upgrade karena butuh laporan lanjutan"></textarea>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-2 flex items-center justify-between">
                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Preview Dampak</p>
                <span class="rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-bold text-teal-700 dark:bg-teal-900/40 dark:text-teal-300">Realtime</span>
            </div>
            <div id="impact-box" class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                Preview dampak akan muncul di sini.
            </div>
            <div id="impact-features" class="mt-2 hidden rounded-xl border border-slate-200 p-3 text-xs dark:border-slate-700">
                <p id="impact-added" class="text-emerald-700 dark:text-emerald-400">Aktif: -</p>
                <p id="impact-removed" class="mt-1 text-rose-700 dark:text-rose-400">Nonaktif: -</p>
            </div>
            <label id="downgrade-confirm-wrap" class="mt-2 hidden rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                <span id="downgrade-confirm-text" class="mb-2 block">Downgrade akan aktif di akhir periode.</span>
                <span class="inline-flex items-center gap-2">
                    <input id="downgrade-confirm" type="checkbox" class="rounded border-amber-400" />
                    Saya konfirmasi jadwalkan downgrade ini.
                </span>
            </label>
            <button id="btn-submit" class="mt-3 w-full rounded-xl bg-primary px-3 py-2.5 text-sm font-bold text-white">Simpan Perubahan Plan</button>
        </section>

        <p id="result-box" class="hidden rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700"></p>
        <p id="error-box" class="hidden rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"></p>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = {
                'Content-Type': 'application/json',
                ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
            };

            const el = {
                plan: document.getElementById('plan'),
                mode: document.getElementById('mode'),
                reason: document.getElementById('reason'),
                submit: document.getElementById('btn-submit'),
                impact: document.getElementById('impact-box'),
                impactFeatures: document.getElementById('impact-features'),
                impactAdded: document.getElementById('impact-added'),
                impactRemoved: document.getElementById('impact-removed'),
                downgradeConfirmWrap: document.getElementById('downgrade-confirm-wrap'),
                downgradeConfirmText: document.getElementById('downgrade-confirm-text'),
                downgradeConfirm: document.getElementById('downgrade-confirm'),
                result: document.getElementById('result-box'),
                error: document.getElementById('error-box'),
            };

            let latestPreview = null;

            const formatDateTime = (value) => {
                if (!value) {
                    return '-';
                }
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return value;
                }
                return new Intl.DateTimeFormat('id-ID', {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                }).format(date);
            };

            const hydratePlanOptions = (labels = {}) => {
                const planOrder = Object.keys(labels).length > 0 ? Object.keys(labels) : ['S', 'M', 'P'];
                const options = planOrder.map((plan) => {
                    const label = labels?.[plan] || plan;
                    return `<option value="${plan}">${plan} - ${label}</option>`;
                });
                el.plan.innerHTML = options.join('');
            };

            const loadCurrent = async () => {
                const response = await fetch('/api/v1/subscription/current', { headers });
                if (!response.ok) {
                    return;
                }
                const payload = await response.json().catch(() => ({}));
                const data = window.PosUi.unwrapApiPayload(payload) || {};
                hydratePlanOptions(data.plan_labels || {});
                if (data.plan) {
                    el.plan.value = data.plan;
                }
                await loadImpactPreview();
            };

            const loadImpactPreview = async () => {
                const params = new URLSearchParams({
                    target_plan: el.plan.value,
                    mode: el.mode.value,
                });
                const response = await fetch(`/api/v1/subscription/impact-preview?${params.toString()}`, { headers });
                if (!response.ok) {
                    el.impact.textContent = 'Tidak dapat memuat preview dampak.';
                    el.impactFeatures.classList.add('hidden');
                    el.downgradeConfirmWrap.classList.add('hidden');
                    latestPreview = null;
                    return;
                }

                const payload = await response.json().catch(() => ({}));
                const data = window.PosUi.unwrapApiPayload(payload) || {};
                latestPreview = data;
                const added = Array.isArray(data.features_added) ? data.features_added.length : 0;
                const removed = Array.isArray(data.features_removed) ? data.features_removed.length : 0;
                const mode = data.mode || '-';
                const effectiveAt = formatDateTime(data.effective_at || '-');
                const proration = data.proration?.amount ?? 0;

                el.impact.textContent = `Mode ${mode} | Efektif ${effectiveAt} | Fitur +${added} / -${removed} | Proration Rp ${Number(proration).toLocaleString('id-ID')}`;

                const addedKeys = (data.features_added || []).map((item) => item.key).slice(0, 6);
                const removedKeys = (data.features_removed || []).map((item) => item.key).slice(0, 6);
                el.impactAdded.textContent = `Aktif (${added}): ${addedKeys.length > 0 ? addedKeys.join(', ') : '-'}`;
                el.impactRemoved.textContent = `Nonaktif (${removed}): ${removedKeys.length > 0 ? removedKeys.join(', ') : '-'}`;
                el.impactFeatures.classList.remove('hidden');

                const requiresDowngradeConfirm = mode === 'end_of_period' && removed > 0;
                el.downgradeConfirmWrap.classList.toggle('hidden', !requiresDowngradeConfirm);
                if (requiresDowngradeConfirm) {
                    el.downgradeConfirmText.textContent = `Downgrade terjadwal dan aktif pada ${effectiveAt}.`;
                } else {
                    el.downgradeConfirm.checked = false;
                }
            };

            const showError = (message) => {
                el.error.classList.remove('hidden');
                el.error.textContent = message;
            };

            const showResult = (message) => {
                el.result.classList.remove('hidden');
                el.result.textContent = message;
            };

            el.submit.addEventListener('click', async () => {
                el.error.classList.add('hidden');
                el.result.classList.add('hidden');
                el.submit.disabled = true;
                el.submit.classList.add('opacity-70');

                try {
                    const requiresDowngradeConfirm = latestPreview?.mode === 'end_of_period' && Array.isArray(latestPreview?.features_removed) && latestPreview.features_removed.length > 0;
                    if (requiresDowngradeConfirm && !el.downgradeConfirm.checked) {
                        throw new Error('Centang konfirmasi downgrade terjadwal terlebih dahulu.');
                    }

                    const response = await fetch('/api/v1/subscription/plan', {
                        method: 'PATCH',
                        headers,
                        body: JSON.stringify({
                            plan: el.plan.value,
                            reason: el.reason.value,
                            mode: el.mode.value,
                        }),
                    });

                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(payload.message || 'Gagal mengubah plan.');
                    }

                    const data = window.PosUi.unwrapApiPayload(payload) || {};
                    showResult(`Plan berhasil diperbarui ${data.old_plan} -> ${data.plan}.`);
                    const isScheduled = Boolean(data.scheduled);
                    const syncPayload = {
                        plan: data.plan,
                        plan_label: data.plan_label,
                        pending_plan: isScheduled ? (data.requested_plan || null) : null,
                        pending_change_mode: isScheduled ? (data.mode || 'end_of_period') : null,
                        pending_change_effective_at: isScheduled ? (data.effective_at || null) : null,
                        updated_at: Date.now(),
                    };
                    localStorage.setItem('subscription:plan-sync', JSON.stringify(syncPayload));
                    window.dispatchEvent(new CustomEvent('subscription:plan-changed', { detail: syncPayload }));
                    await loadImpactPreview();
                } catch (error) {
                    showError(error.message);
                } finally {
                    el.submit.disabled = false;
                    el.submit.classList.remove('opacity-70');
                }
            });

            el.plan.addEventListener('change', () => loadImpactPreview().catch(() => {}));
            el.mode.addEventListener('change', () => loadImpactPreview().catch(() => {}));

            loadCurrent().catch(() => {});
        })();
    </script>
@endsection
