@php
    $status = $status ?? 'gray';
    $label = match ($status) {
        'green' => trans('platform-system-health.common.health_badge.green', [], 'id'),
        'yellow' => trans('platform-system-health.common.health_badge.yellow', [], 'id'),
        'red' => trans('platform-system-health.common.health_badge.red', [], 'id'),
        default => trans('platform-system-health.common.health_badge.gray', [], 'id'),
    };
    $classes = match ($status) {
        'green' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'yellow' => 'border-amber-200 bg-amber-50 text-amber-700',
        'red' => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-slate-200 bg-slate-100 text-slate-600',
    };
    $dot = match ($status) {
        'green' => 'bg-emerald-500',
        'yellow' => 'bg-amber-500',
        'red' => 'bg-rose-500',
        default => 'bg-slate-400',
    };
@endphp

<span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] {{ $classes }}">
    <span class="h-2 w-2 rounded-full {{ $dot }}"></span>
    <span>{{ $label }}</span>
</span>