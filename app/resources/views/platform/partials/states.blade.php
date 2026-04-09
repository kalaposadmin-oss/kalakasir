<section class="grid gap-4 md:grid-cols-3">
    <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Empty State</p>
        <p class="mt-2 text-sm font-semibold text-slate-700">Belum ada data</p>
        <p class="mt-1 text-xs text-slate-500">Coba ubah filter atau periode.</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Loading State</p>
        <div class="mt-3 space-y-2">
            <div class="h-3 w-2/3 rounded-full bg-slate-100"></div>
            <div class="h-3 w-1/2 rounded-full bg-slate-100"></div>
        </div>
    </div>
    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-rose-400">Error State</p>
        <p class="mt-2 text-sm font-semibold text-rose-700">Gagal memuat data</p>
        <p class="mt-1 text-xs text-rose-600">Silakan coba lagi.</p>
    </div>
</section>
