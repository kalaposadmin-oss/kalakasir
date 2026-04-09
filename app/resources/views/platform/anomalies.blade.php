@extends('platform.layout')

@php
    $pageTitle = 'Anomalies';
    $active = 'anomalies';
    $anomalies = $anomalies ?? [];
@endphp

@section('platform_content')
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Anomalies</h1>
            <p class="mt-1 text-sm text-slate-500">Deteksi data tidak normal dan tindak lanjut.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Export CSV</button>
            <button class="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white shadow-sm">Bulk Resolve</button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
            <span class="material-symbols-outlined text-[18px]">search</span>
            <span>Cari reference atau tenant</span>
        </div>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">Severity: High</div>
        <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">Resolved: No</div>
        <button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600">Reset</button>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Anomaly List</p>
                <h3 class="text-sm font-bold text-slate-900">{{ number_format((int) ($total ?? 0)) }} anomaly ditemukan</h3>
            </div>
            <button class="text-xs font-semibold text-primary">Lihat semua</button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Severity</th>
                        <th class="px-6 py-3">Tenant</th>
                        <th class="px-6 py-3">Type</th>
                        <th class="px-6 py-3">Detected</th>
                        <th class="px-6 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($anomalies as $anomaly)
                        @php
                            $severity = strtolower((string) ($anomaly['severity'] ?? 'low'));
                            $severityClass = match ($severity) {
                                'critical' => 'bg-rose-100 text-rose-700',
                                'high' => 'bg-amber-100 text-amber-700',
                                'medium' => 'bg-yellow-100 text-yellow-700',
                                default => 'bg-slate-100 text-slate-600',
                            };
                        @endphp
                        <tr>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $severityClass }}">{{ ucfirst($severity) }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-slate-900">{{ $anomaly['tenant_id'] ?? '-' }}</td>
                            <td class="px-6 py-4 text-xs text-slate-600">{{ $anomaly['type'] ?? '-' }}</td>
                            <td class="px-6 py-4 text-xs text-slate-500">{{ $anomaly['detected_at'] ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <button
                                    class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-600 js-anomaly-resolve"
                                    data-anomaly-id="{{ $anomaly['anomaly_id'] ?? '' }}"
                                    data-anomaly-label="{{ $anomaly['type'] ?? 'Anomaly' }}"
                                >Resolve</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-6 py-4 text-sm text-slate-500" colspan="5">Belum ada anomaly.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('platform.partials.states')

    <div id="anomaly-resolve-modal" class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-900/20 px-4">
        <div class="pointer-events-auto w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                    <span class="material-symbols-outlined text-[20px]">report</span>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-900">Resolve Anomaly</p>
                    <p class="text-xs text-slate-500">Opsional: catat alasan penyelesaian.</p>
                </div>
            </div>
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-500" data-anomaly-modal-target>Anomaly: -</div>
            <textarea class="mt-4 w-full rounded-xl border border-slate-200 p-3 text-xs" rows="3" placeholder="Catatan penyelesaian (opsional)" data-anomaly-modal-reason></textarea>
            <div class="mt-5 flex items-center justify-end gap-2">
                <button class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600" data-anomaly-modal-cancel>Batal</button>
                <button class="rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white" data-anomaly-modal-submit>Resolve</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const modal = document.getElementById('anomaly-resolve-modal');
            if (!modal || !window.PlatformUi) {
                return;
            }

            const state = {
                anomalyId: null,
                label: null,
            };

            const el = {
                target: modal.querySelector('[data-anomaly-modal-target]'),
                reason: modal.querySelector('[data-anomaly-modal-reason]'),
                cancel: modal.querySelector('[data-anomaly-modal-cancel]'),
                submit: modal.querySelector('[data-anomaly-modal-submit]'),
            };

            const openModal = ({ anomalyId, label }) => {
                state.anomalyId = anomalyId;
                state.label = label || '-';
                el.target.textContent = `Anomaly: ${state.label}`;
                el.reason.value = '';
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            document.querySelectorAll('.js-anomaly-resolve').forEach((button) => {
                button.addEventListener('click', () => {
                    openModal({
                        anomalyId: button.dataset.anomalyId,
                        label: button.dataset.anomalyLabel,
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
                const endpoint = `/api/v1/platform/anomalies/${state.anomalyId}/resolve`;
                const idempotencyKey = window.PlatformUi.createIdempotencyKey('resolve', state.anomalyId);

                el.submit.disabled = true;
                el.submit.textContent = 'Memproses...';

                const { response, payload, requestId } = await window.PlatformUi.apiFetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Idempotency-Key': idempotencyKey,
                    },
                    body: JSON.stringify({ reason: reason || null }),
                });

                el.submit.disabled = false;
                el.submit.textContent = 'Resolve';

                if (!response.ok) {
                    const message = payload?.message || 'Gagal resolve anomaly.';
                    const metaRequestId = payload?.request_id || requestId;
                    window.PlatformUi.showToast(message, 'error', metaRequestId);
                    return;
                }

                window.PlatformUi.showToast('Anomaly berhasil di-resolve.', 'success');
                closeModal();
                window.location.reload();
            });
        })();
    </script>
@endpush
