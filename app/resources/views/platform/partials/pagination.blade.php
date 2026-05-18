@php
    $page = max(1, (int) ($page ?? 1));
    $lastPage = max(1, (int) ($lastPage ?? 1));
    $total = max(0, (int) ($total ?? 0));
    $prevUrl = request()->fullUrlWithQuery(['page' => max(1, $page - 1)]);
    $nextUrl = request()->fullUrlWithQuery(['page' => min($lastPage, $page + 1)]);
@endphp

<div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
    <p>{{ number_format($total) }} data - halaman {{ $page }} dari {{ $lastPage }}</p>
    <div class="flex items-center gap-2">
        @if ($page > 1)
            <a href="{{ $prevUrl }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 transition hover:bg-slate-50">Sebelumnya</a>
        @else
            <span class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 font-semibold text-slate-300">Sebelumnya</span>
        @endif

        @if ($page < $lastPage)
            <a href="{{ $nextUrl }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 transition hover:bg-slate-50">Berikutnya</a>
        @else
            <span class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 font-semibold text-slate-300">Berikutnya</span>
        @endif
    </div>
</div>
