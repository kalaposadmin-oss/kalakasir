@props([
    'context' => 'default',
])

<section
    data-settlement-reminder
    data-context="{{ $context }}"
    class="hidden rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-900 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/25 dark:text-amber-100"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-amber-600 shadow-sm ring-1 ring-amber-100 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-900">
                <span class="material-symbols-outlined text-[22px]">notification_important</span>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-black" data-settlement-title>Settlement QRIS belum dicatat</p>
                <p class="mt-1 text-xs leading-relaxed text-amber-700 dark:text-amber-200" data-settlement-copy>
                    Ada uang QRIS/bank yang perlu dicocokkan dengan mutasi real.
                </p>
                <div class="mt-2 flex flex-wrap gap-2 text-[11px] font-bold">
                    <span class="rounded-full bg-white px-2.5 py-1 text-amber-700 ring-1 ring-amber-100 dark:bg-amber-950/60 dark:text-amber-200 dark:ring-amber-900" data-settlement-count>0 tanggal</span>
                    <span class="rounded-full bg-white px-2.5 py-1 text-amber-700 ring-1 ring-amber-100 dark:bg-amber-950/60 dark:text-amber-200 dark:ring-amber-900" data-settlement-total>Rp 0</span>
                </div>
            </div>
        </div>
        <a
            href="/backoffice/finance/payment-settlements"
            data-settlement-link
            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-black text-white shadow-sm shadow-primary/25"
        >
            Catat Sekarang
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </a>
    </div>
</section>

@once
    @push('scripts')
        <script>
            (() => {
                const formatSettlementReminderCurrency = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    maximumFractionDigits: 0,
                }).format(Number(value || 0));

                const formatSettlementReminderDate = (value) => {
                    if (!value) return '-';
                    return new Intl.DateTimeFormat('id-ID', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                    }).format(new Date(`${value}T00:00:00`));
                };

                const renderSettlementReminder = (root, payload) => {
                    const items = Array.isArray(payload?.items) ? payload.items : [];
                    if (!items.length) {
                        root.classList.add('hidden');
                        return;
                    }

                    const first = items[0] || {};
                    const count = Number(payload.count || items.length || 0);
                    const total = Number(payload.total_remaining || 0);
                    const title = root.querySelector('[data-settlement-title]');
                    const copy = root.querySelector('[data-settlement-copy]');
                    const countEl = root.querySelector('[data-settlement-count]');
                    const totalEl = root.querySelector('[data-settlement-total]');
                    const link = root.querySelector('[data-settlement-link]');
                    const method = String(first.payment_method || 'qris').toUpperCase();
                    const firstDate = formatSettlementReminderDate(first.sales_date);

                    if (title) {
                        title.textContent = count > 1
                            ? `${count} settlement QRIS/bank belum dicatat`
                            : `Settlement ${method} ${firstDate} belum dicatat`;
                    }
                    if (copy) {
                        copy.textContent = count > 1
                            ? 'Cocokkan uang real masuk dengan mutasi bank supaya saldo bank dan beban admin tidak meleset.'
                            : `Total POS ${method} ${formatSettlementReminderCurrency(first.remaining_amount)} perlu dicocokkan dengan uang real yang masuk.`;
                    }
                    if (countEl) {
                        countEl.textContent = `${count} tugas`;
                    }
                    if (totalEl) {
                        totalEl.textContent = formatSettlementReminderCurrency(total);
                    }
                    if (link && first.settlement_url) {
                        link.href = first.settlement_url;
                    }

                    root.classList.remove('hidden');
                };

                const loadSettlementReminders = async () => {
                    const roots = Array.from(document.querySelectorAll('[data-settlement-reminder]'));
                    if (!roots.length) return;

                    const tenantId = document.querySelector('meta[name="tenant-id"]')?.content || '';
                    const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                    try {
                        const response = await fetch('/api/v1/payment-settlements/pending?days=14', { headers });
                        if (!response.ok) return;
                        const payload = await response.json();
                        roots.forEach((root) => renderSettlementReminder(root, payload));
                    } catch (error) {
                        roots.forEach((root) => root.classList.add('hidden'));
                    }
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', loadSettlementReminders, { once: true });
                } else {
                    loadSettlementReminders();
                }
            })();
        </script>
    @endpush
@endonce
