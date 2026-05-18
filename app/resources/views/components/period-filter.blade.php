@props([
    'id' => 'period-filter',
    'defaultPreset' => 'last7',
    'defaultStart' => null,
    'defaultEnd' => null,
    'timezoneLabel' => 'WIB',
    'showReset' => true,
    'formId' => null,
    'autoSubmit' => false,
])

<div id="{{ $id }}" class="rounded-xl border border-slate-200 bg-white p-3 space-y-3" data-period-filter data-form-id="{{ $formId }}" data-auto-submit="{{ $autoSubmit ? 'true' : 'false' }}">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Periode</p>
            <p class="text-sm font-semibold text-slate-900" data-period-summary>7 Hari Terakhir</p>
            <p class="text-[10px] text-slate-400">Timezone: {{ $timezoneLabel }}</p>
        </div>
        @if($showReset)
            <button class="text-xs font-bold text-primary" data-period-reset>Reset</button>
        @endif
    </div>

    <div class="flex flex-wrap gap-2">
        <button class="px-3 py-1.5 rounded-full text-xs font-bold border border-slate-200 bg-white" data-period-preset="today">Hari Ini</button>
        <button class="px-3 py-1.5 rounded-full text-xs font-bold border border-slate-200 bg-white" data-period-preset="yesterday">Kemarin</button>
        <button class="px-3 py-1.5 rounded-full text-xs font-bold border border-slate-200 bg-white" data-period-preset="last7">7 Hari Terakhir</button>
        <button class="px-3 py-1.5 rounded-full text-xs font-bold border border-slate-200 bg-white" data-period-preset="last30">30 Hari Terakhir</button>
        <button class="px-3 py-1.5 rounded-full text-xs font-bold border border-slate-200 bg-white" data-period-preset="thisMonth">Bulan Ini</button>
        <button class="px-3 py-1.5 rounded-full text-xs font-bold border border-slate-200 bg-white" data-period-preset="lastMonth">Bulan Lalu</button>
        <button class="px-3 py-1.5 rounded-full text-xs font-bold border border-slate-200 bg-white" data-period-preset="custom">Custom</button>
    </div>

    <div class="hidden gap-2 items-center" data-period-custom>
        <input class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-xs" type="date" data-period-from />
        <input class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-xs" type="date" data-period-to />
        <button class="rounded-lg bg-primary px-3 py-2 text-xs font-bold text-white" data-period-apply type="button">Apply</button>
    </div>

    <div class="flex items-center gap-2">
        <span class="text-[11px] text-slate-500">Filter aktif:</span>
        <span class="text-[11px] font-bold text-slate-700 px-2 py-0.5 bg-slate-100 rounded-full" data-period-chip>-</span>
    </div>
    <p class="text-[11px] text-amber-600 hidden" data-period-warning>Maksimum 90 hari. Rentang disesuaikan otomatis.</p>

    <input type="hidden" name="preset" data-period-input="preset" value="{{ $defaultPreset }}" />
    <input type="hidden" name="start_date" data-period-input="start_date" value="{{ $defaultStart }}" />
    <input type="hidden" name="end_date" data-period-input="end_date" value="{{ $defaultEnd }}" />
</div>

@push('scripts')
<script>
(() => {
    const root = document.querySelector('[data-period-filter]#{{ $id }}');
    if (!root) return;

    const el = {
        summary: root.querySelector('[data-period-summary]'),
        chip: root.querySelector('[data-period-chip]'),
        custom: root.querySelector('[data-period-custom]'),
        from: root.querySelector('[data-period-from]'),
        to: root.querySelector('[data-period-to]'),
        apply: root.querySelector('[data-period-apply]'),
        reset: root.querySelector('[data-period-reset]'),
        presets: root.querySelectorAll('[data-period-preset]'),
        warning: root.querySelector('[data-period-warning]'),
        inputs: root.querySelectorAll('[data-period-input]'),
    };

    const defaults = {
        preset: '{{ $defaultPreset }}',
        start: '{{ $defaultStart }}',
        end: '{{ $defaultEnd }}',
    };

    const labels = {
        today: 'Hari Ini',
        yesterday: 'Kemarin',
        last7: '7 Hari Terakhir',
        last30: '30 Hari Terakhir',
        thisMonth: 'Bulan Ini',
        lastMonth: 'Bulan Lalu',
        custom: 'Periode Custom',
    };

    const readQuery = () => {
        const qs = new URLSearchParams(window.location.search);
        return {
            preset: qs.get('preset') || defaults.preset,
            start: qs.get('start_date') || defaults.start || '',
            end: qs.get('end_date') || defaults.end || '',
            page: qs.get('page') || '',
        };
    };

    let { preset, start, end, page } = readQuery();

    const toDate = (value) => {
        if (!value) return null;
        const parsed = new Date(value);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    };

    const formatWib = (date) => new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(date);

    const parseWib = (value) => {
        if (!value) return null;
        return new Date(`${value}T00:00:00+07:00`);
    };

    const computePresetRange = (presetKey) => {
        const now = new Date();
        const todayWib = formatWib(now);
        const todayDate = parseWib(todayWib);
        if (!todayDate) {
            return { start: todayWib, end: todayWib };
        }

        const start = new Date(todayDate.getTime());
        const end = new Date(todayDate.getTime());

        switch (presetKey) {
            case 'today':
                break;
            case 'yesterday':
                start.setDate(start.getDate() - 1);
                end.setDate(end.getDate() - 1);
                break;
            case 'last7':
                start.setDate(start.getDate() - 6);
                break;
            case 'last30':
                start.setDate(start.getDate() - 29);
                break;
            case 'thisMonth':
                start.setDate(1);
                break;
            case 'lastMonth': {
                start.setDate(1);
                start.setMonth(start.getMonth() - 1);
                end.setDate(1);
                end.setDate(end.getDate() - 1);
                break;
            }
            default:
                break;
        }

        return { start: formatWib(start), end: formatWib(end) };
    };

    const diffDays = (from, to) => {
        const startDate = toDate(from);
        const endDate = toDate(to);
        if (!startDate || !endDate) return 0;
        const diff = endDate.getTime() - startDate.getTime();
        return Math.floor(diff / (24 * 60 * 60 * 1000)) + 1;
    };

    const formatPeriodDateRange = (from, to) => {
        const startDate = parseWib(from);
        const endDate = parseWib(to || from);

        if (!startDate || !endDate) {
            return 'Periode Custom';
        }

        const dayFormatter = new Intl.DateTimeFormat('id-ID', { day: '2-digit', timeZone: 'Asia/Jakarta' });
        const monthFormatter = new Intl.DateTimeFormat('id-ID', { month: 'short', timeZone: 'Asia/Jakarta' });
        const yearFormatter = new Intl.DateTimeFormat('id-ID', { year: 'numeric', timeZone: 'Asia/Jakarta' });
        const startDay = dayFormatter.format(startDate);
        const endDay = dayFormatter.format(endDate);
        const startMonth = monthFormatter.format(startDate);
        const endMonth = monthFormatter.format(endDate);
        const startYear = yearFormatter.format(startDate);
        const endYear = yearFormatter.format(endDate);

        if (from === (to || from)) {
            return `${endDay} ${endMonth} ${endYear}`;
        }

        if (startMonth === endMonth && startYear === endYear) {
            return `${startDay}-${endDay} ${endMonth} ${endYear}`;
        }

        if (startYear === endYear) {
            return `${startDay} ${startMonth} - ${endDay} ${endMonth} ${endYear}`;
        }

        return `${startDay} ${startMonth} ${startYear} - ${endDay} ${endMonth} ${endYear}`;
    };

    const updateInputs = () => {
        el.inputs.forEach((input) => {
            if (input.dataset.periodInput === 'preset') input.value = preset;
            if (input.dataset.periodInput === 'start_date') input.value = start;
            if (input.dataset.periodInput === 'end_date') input.value = end;
        });
    };

    const updateUI = () => {
        if (preset !== 'custom') {
            const computed = computePresetRange(preset);
            start = computed.start;
            end = computed.end;
        }
        el.custom.classList.toggle('hidden', preset !== 'custom');
        const customLabel = preset === 'custom' ? formatPeriodDateRange(start, end) : null;
        el.summary.textContent = customLabel || labels[preset] || 'Periode';
        el.chip.textContent = customLabel || labels[preset] || '-';
        el.presets.forEach(btn => {
            const active = btn.dataset.periodPreset === preset;
            btn.classList.toggle('border-primary', active);
            btn.classList.toggle('text-primary', active);
            btn.classList.toggle('bg-primary/5', active);
            btn.classList.toggle('border-slate-200', !active);
            btn.classList.toggle('text-slate-600', !active);
            btn.classList.toggle('bg-white', !active);
        });
        const days = preset === 'custom' ? diffDays(start, end) : 0;
        el.warning.classList.toggle('hidden', !(preset === 'custom' && days > 90));
        updateInputs();
    };

    const buildCanonicalQuery = (extra = {}) => {
        const current = new URLSearchParams(window.location.search);
        const next = new URLSearchParams();

        const orderedKeys = ['preset', 'start_date', 'end_date', 'page'];
        const values = {
            preset,
            start_date: start,
            end_date: end,
            page: '1',
            ...extra,
        };

        orderedKeys.forEach((key) => {
            const value = values[key];
            if (value) next.set(key, value);
        });

        [...current.keys()]
            .filter((key) => !orderedKeys.includes(key))
            .forEach((key) => {
                if (values[key] !== undefined) {
                    if (values[key]) next.set(key, values[key]);
                } else {
                    next.set(key, current.get(key));
                }
            });

        Object.keys(values)
            .filter((key) => !orderedKeys.includes(key) && values[key] !== undefined)
            .forEach((key) => {
                if (values[key]) next.set(key, values[key]);
            });

        return next;
    };

    const syncQuery = () => {
        const next = buildCanonicalQuery();
        history.replaceState(null, '', `${window.location.pathname}?${next.toString()}`);
    };

    const emitChange = () => {
        root.dispatchEvent(new CustomEvent('period:change', {
            detail: { preset, start_date: start, end_date: end },
        }));

        const formId = root.dataset.formId;
        const autoSubmit = root.dataset.autoSubmit === 'true';
        if (formId && autoSubmit) {
            const form = document.getElementById(formId);
            form?.requestSubmit?.();
        }
    };

    el.presets.forEach(btn => {
        btn.addEventListener('click', () => {
            preset = btn.dataset.periodPreset;
            page = '1';
            updateUI();
            syncQuery();
            emitChange();
        });
    });

    el.apply?.addEventListener('click', () => {
        preset = 'custom';
        start = el.from.value || '';
        end = el.to.value || start || '';
        page = '1';
        updateUI();
        syncQuery();
        emitChange();
    });

    el.reset?.addEventListener('click', () => {
        preset = defaults.preset;
        start = defaults.start || '';
        end = defaults.end || '';
        page = '1';
        updateUI();
        syncQuery();
        emitChange();
    });

    if (!el.from.value && start) el.from.value = start;
    if (!el.to.value && end) el.to.value = end;

    updateUI();
    syncQuery();
    emitChange();
})();
</script>
@endpush
