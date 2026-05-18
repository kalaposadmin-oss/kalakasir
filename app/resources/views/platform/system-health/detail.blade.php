@extends('platform.layout')

@php
    $t = static fn (string $key, array $replace = []) => trans('platform-system-health.'.$key, $replace, 'id');
    $pageTitle = $t('detail.page_title');
    $active = 'incidents';
    $event = $event ?? [];
    $relatedEvents = $relatedEvents ?? [];
    $healthStatus = match ($event['severity'] ?? 'low') {
        'critical' => 'red',
        'high' => 'yellow',
        'medium', 'low' => 'green',
        default => 'gray',
    };
    $explanationKey = 'detail.event_explanations.'.($event['event_name'] ?? 'default');
    $explanation = $t($explanationKey);
    if ($explanation === 'platform-system-health.'.$explanationKey) {
        $explanation = $t('detail.event_explanations.default');
    }
    $jsTranslations = [
        'modal' => [
            'resolveTitle' => $t('detail.modal.resolve_title'),
            'ignoreTitle' => $t('detail.modal.ignore_title'),
        ],
        'toasts' => [
            'copySuccess' => $t('detail.toasts.copy_success'),
            'copyError' => $t('detail.toasts.copy_error'),
            'processError' => $t('detail.toasts.process_error'),
            'resolveSuccess' => $t('detail.toasts.resolve_success'),
            'ignoreSuccess' => $t('detail.toasts.ignore_success'),
        ],
    ];
@endphp

@include('platform.system-health.partials.theme')

@section('platform_content')
    <div class="monitor-shell space-y-6">
        <section class="rounded-[2rem] border border-slate-200/70 bg-[var(--monitor-bg)] p-6 shadow-[0_24px_70px_rgba(15,23,42,0.08)] lg:p-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 max-w-3xl">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-slate-400">{{ $t('detail.eyebrow') }}</p>
                    <h1 class="mt-2 break-words text-3xl font-bold tracking-tight text-slate-900">{{ $event['event_name'] ?? '-' }}</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">{{ $explanation }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @include('platform.system-health.partials.health-badge', ['status' => $healthStatus])
                    <a href="/superadmin/system-health/incidents" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700">{{ $t('detail.back') }}</a>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="space-y-6">
                <div class="monitor-glass rounded-[1.75rem] border border-[var(--monitor-line)] p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('detail.summary.eyebrow') }}</p>
                            <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $t('detail.summary.title') }}</h2>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="detail-action rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700" data-action="resolve">{{ $t('detail.actions.resolve') }}</button>
                            <button type="button" class="detail-action rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-700" data-action="ignore">{{ $t('detail.actions.ignore') }}</button>
                        </div>
                    </div>
                    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">{{ $t('detail.summary.severity') }}</p><p class="mt-2 text-base font-semibold text-slate-900">{{ $t('common.severity_labels.'.($event['severity'] ?? 'low')) }}</p></div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">{{ $t('detail.summary.tenant') }}</p><p class="mt-2 text-base font-semibold text-slate-900">{{ $event['tenant_name'] ?? $event['tenant_id'] ?? $t('common.global') }}</p></div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">{{ $t('detail.summary.entity') }}</p><p class="mt-2 break-words text-base font-semibold text-slate-900">{{ ($event['entity_type'] ?? '-') . ' / ' . ($event['entity_id'] ?? '-') }}</p></div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">{{ $t('detail.summary.status') }}</p><p class="mt-2 text-base font-semibold text-slate-900">{{ $t('common.status_labels.'.($event['status'] ?? 'open')) }}</p></div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">{{ $t('detail.summary.first_detected') }}</p><p class="mt-2 text-sm font-semibold text-slate-900">{{ !empty($event['first_detected_at']) ? \Carbon\CarbonImmutable::parse($event['first_detected_at'], 'UTC')->isoFormat('DD MMM YYYY, HH:mm') : '-' }}</p></div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">{{ $t('detail.summary.last_detected') }}</p><p class="mt-2 text-sm font-semibold text-slate-900">{{ !empty($event['detected_at']) ? \Carbon\CarbonImmutable::parse($event['detected_at'], 'UTC')->isoFormat('DD MMM YYYY, HH:mm') : '-' }}</p></div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">{{ $t('detail.summary.resolved_at') }}</p><p class="mt-2 text-sm font-semibold text-slate-900">{{ !empty($event['resolved_at']) ? \Carbon\CarbonImmutable::parse($event['resolved_at'], 'UTC')->isoFormat('DD MMM YYYY, HH:mm') : '-' }}</p></div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-[11px] uppercase tracking-[0.18em] text-slate-400">{{ $t('detail.summary.occurrence_count') }}</p><p class="mt-2 text-base font-semibold text-slate-900">{{ number_format((int) ($event['occurrence_count'] ?? 0)) }}</p></div>
                    </div>
                </div>

                <div class="monitor-glass rounded-[1.75rem] border border-[var(--monitor-line)] p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('detail.payload.eyebrow') }}</p>
                            <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $t('detail.payload.title') }}</h2>
                        </div>
                        <button id="copy-payload" type="button" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700">{{ $t('detail.payload.copy_json') }}</button>
                    </div>
                    <details class="mt-5 rounded-2xl border border-slate-200 bg-slate-950 text-slate-100" open>
                        <summary class="cursor-pointer list-none border-b border-white/10 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">{{ $t('detail.payload.payload_json') }}</summary>
                        <pre id="payload-json" class="monitor-scrollbar max-h-[28rem] overflow-auto px-4 py-4 text-xs leading-6">{{ json_encode($event['payload_json'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </details>
                </div>

                <div class="monitor-glass rounded-[1.75rem] border border-[var(--monitor-line)] p-6 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('detail.occurrences.eyebrow') }}</p>
                    <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $t('detail.occurrences.title') }}</h2>
                    <div class="mt-5 space-y-4">
                        @forelse (($event['occurrences'] ?? []) as $occurrence)
                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-slate-900">{{ $t('detail.occurrences.number', ['number' => $occurrence['occurrence_number']]) }}</p>
                                    <p class="text-xs text-slate-500">{{ !empty($occurrence['detected_at']) ? \Carbon\CarbonImmutable::parse($occurrence['detected_at'], 'UTC')->isoFormat('DD MMM YYYY, HH:mm') : '-' }}</p>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $occurrence['summary'] }}</p>
                                <details class="mt-3 rounded-2xl border border-slate-200 bg-slate-50">
                                    <summary class="cursor-pointer list-none px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $t('detail.occurrences.payload_snapshot') }}</summary>
                                    <pre class="monitor-scrollbar max-h-56 overflow-auto border-t border-slate-200 px-4 py-4 text-xs leading-6 text-slate-700">{{ json_encode($occurrence['payload_json'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            </div>
                        @empty
                            <div class="flex items-start gap-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500"><span class="material-symbols-outlined mt-0.5 text-[18px] text-slate-400">timeline</span><div><p class="font-semibold text-slate-700">{{ $t('detail.occurrences.empty_title') }}</p><p class="mt-1 text-xs leading-5 text-slate-500">{{ $t('detail.occurrences.empty_description') }}</p></div></div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="monitor-glass rounded-[1.75rem] border border-[var(--monitor-line)] p-6 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('detail.quick_actions.eyebrow') }}</p>
                    <div class="mt-4 space-y-3">
                        @if (!empty($event['tenant_id']))
                            <a href="/superadmin/system-health/incidents?tenant_id={{ urlencode((string) $event['tenant_id']) }}" class="block rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900">{{ $t('detail.quick_actions.tenant_filtered') }}</a>
                        @endif
                        @if (!empty($event['event_name']))
                            <a href="/superadmin/system-health/incidents?event_name={{ urlencode((string) $event['event_name']) }}" class="block rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900">{{ $t('detail.quick_actions.same_event') }}</a>
                        @endif
                        <a href="/superadmin/system-health/tenants" class="block rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900">{{ $t('detail.quick_actions.tenant_impact') }}</a>
                    </div>
                </div>

                <div class="monitor-glass rounded-[1.75rem] border border-[var(--monitor-line)] p-6 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ $t('detail.related.eyebrow') }}</p>
                    <div class="mt-4 space-y-3">
                        @forelse ($relatedEvents as $related)
                            <a href="/superadmin/system-health/incidents/{{ $related['event_id'] }}" class="block rounded-2xl border border-slate-200 bg-white px-4 py-3 transition hover:border-slate-300">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ $related['event_name'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $related['summary'] }}</p>
                                    </div>
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $related['severity'] === 'critical' ? 'bg-rose-100 text-rose-700' : ($related['severity'] === 'high' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">{{ strtoupper($related['severity']) }}</span>
                                </div>
                            </a>
                        @empty
                            <div class="flex items-start gap-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500"><span class="material-symbols-outlined mt-0.5 text-[18px] text-slate-400">hub</span><div><p class="font-semibold text-slate-700">{{ $t('detail.related.empty_title') }}</p><p class="mt-1 text-xs leading-5 text-slate-500">{{ $t('detail.related.empty_description') }}</p></div></div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div id="detail-action-modal" class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-950/35 px-4">
        <div class="w-full max-w-lg rounded-[1.75rem] bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p id="detail-action-title" class="text-lg font-bold text-slate-900">{{ $t('detail.modal.resolve_title') }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $t('detail.modal.description') }}</p>
                </div>
                <button type="button" data-close-detail-modal class="rounded-full border border-slate-200 p-2 text-slate-500"><span class="material-symbols-outlined text-[18px]">close</span></button>
            </div>
            <textarea id="detail-action-reason" rows="4" class="mt-4 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700" placeholder="{{ $t('detail.modal.reason_placeholder') }}"></textarea>
            <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button" data-close-detail-modal class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700">{{ $t('detail.modal.cancel') }}</button>
                <button type="button" id="detail-action-submit" class="rounded-2xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white">{{ $t('detail.modal.submit') }}</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            if (!window.PlatformUi) {
                return;
            }

            const eventId = @json($event['event_id'] ?? null);
            const payloadPre = document.getElementById('payload-json');
            const copyPayloadButton = document.getElementById('copy-payload');
            const modal = document.getElementById('detail-action-modal');
            const title = document.getElementById('detail-action-title');
            const reason = document.getElementById('detail-action-reason');
            const submit = document.getElementById('detail-action-submit');
            const translations = @json($jsTranslations);
            let action = null;

            copyPayloadButton?.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(payloadPre?.textContent || '{}');
                    window.PlatformUi.showToast(translations.toasts.copySuccess, 'success');
                } catch (error) {
                    window.PlatformUi.showToast(translations.toasts.copyError, 'error');
                }
            });

            document.querySelectorAll('.detail-action').forEach((button) => {
                button.addEventListener('click', () => {
                    action = button.dataset.action;
                    title.textContent = action === 'resolve' ? translations.modal.resolveTitle : translations.modal.ignoreTitle;
                    reason.value = '';
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                });
            });

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                action = null;
            };

            modal.querySelectorAll('[data-close-detail-modal]').forEach((button) => button.addEventListener('click', closeModal));
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            submit?.addEventListener('click', async () => {
                if (!action || !eventId) {
                    return;
                }

                submit.disabled = true;
                const result = await window.PlatformUi.apiFetch(`/api/v1/platform/system-health/events/${eventId}/${action}`, {
                    method: 'POST',
                    headers: {
                        'Idempotency-Key': window.PlatformUi.createIdempotencyKey(action, eventId),
                    },
                    body: JSON.stringify({ reason: reason.value.trim() || null }),
                });
                submit.disabled = false;

                if (!result.response.ok) {
                    window.PlatformUi.showToast(result.payload?.message || translations.toasts.processError, 'error', result.payload?.request_id || result.requestId);
                    return;
                }

                window.PlatformUi.showToast(action === 'resolve' ? translations.toasts.resolveSuccess : translations.toasts.ignoreSuccess, 'success');
                window.location.reload();
            });
        })();
    </script>
@endpush
