@extends('layouts.app')

@php
    $subscriptionInvoicesTenant = \App\Support\TenantContext::get();
    $subscriptionInvoicesUser = auth()->user();
    $subscriptionInvoicesRole = null;

    if (! $subscriptionInvoicesTenant && filled($subscriptionInvoicesUser?->tenant_id)) {
        $subscriptionInvoicesTenant = \App\Models\Tenant::query()->find($subscriptionInvoicesUser->tenant_id);
    }

    if ($subscriptionInvoicesTenant && $subscriptionInvoicesUser) {
        $subscriptionInvoicesRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $subscriptionInvoicesTenant->id, (string) $subscriptionInvoicesUser->id);

        if (! $subscriptionInvoicesRole && filled($subscriptionInvoicesUser->role ?? null)) {
            $subscriptionInvoicesRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $subscriptionInvoicesUser->role);
        }
    }

    abort_unless(in_array($subscriptionInvoicesRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);

    $canManageSubscriptionInvoices = in_array($subscriptionInvoicesRole, ['admin', 'owner', 'superadmin'], true);
@endphp

@section('page_title', 'Invoice & Pembayaran')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display min-h-screen')
@section('app_shell_class', 'relative flex h-screen w-full flex-col overflow-hidden bg-white dark:bg-background-dark')
@section('main_class', 'flex-1 overflow-y-auto p-0 lg:pl-72')

@section('header')
    <x-page-header title="Invoice & Pembayaran" backUrl="/backoffice/settings/subscription" />
@endsection

@section('content')
    <div class="mx-auto w-full max-w-5xl p-4 space-y-4 lg:px-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center gap-2">
                <label for="status-filter" class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Status</label>
                <select id="status-filter" class="rounded-xl border border-slate-200 bg-slate-50 px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800">
                    <option value="">Semua</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partial</option>
                    <option value="paid">Paid</option>
                    <option value="void">Void</option>
                </select>
                <button id="btn-reload" class="ml-auto rounded-xl bg-primary px-3 py-1.5 text-xs font-semibold text-white">Reload</button>
            </div>
            <p class="mt-2 text-xs text-slate-500">
                Klik invoice untuk detail.
                @if ($canManageSubscriptionInvoices)
                    Gunakan "Mark Paid" hanya untuk rekonsiliasi manual.
                @else
                    Rekonsiliasi manual hanya tersedia untuk role dengan otorisasi billing.
                @endif
            </p>
        </section>

        <div id="invoice-list" class="space-y-2"></div>

        <section id="invoice-detail" class="hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Detail Invoice</p>
            <p id="detail-head" class="mt-1 text-sm font-bold">-</p>
            <p id="detail-meta" class="mt-1 text-xs text-slate-500">-</p>
            <div id="detail-items" class="mt-2 space-y-1 rounded-xl bg-slate-50 p-2 text-xs dark:bg-slate-800"></div>
            <div class="mt-3 grid @if ($canManageSubscriptionInvoices) grid-cols-2 @else grid-cols-1 @endif gap-2">
                <a id="btn-download" href="#" class="rounded-xl border border-slate-200 px-3 py-2 text-center text-xs font-semibold dark:border-slate-700">Download PDF</a>
                @if ($canManageSubscriptionInvoices)
                    <button id="btn-mark-paid" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white">Mark Paid</button>
                @endif
            </div>
        </section>

        <p id="error-box" class="hidden rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"></p>
        <p id="success-box" class="hidden rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700"></p>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = {
                'Content-Type': 'application/json',
                ...(tenantId ? { 'X-Tenant-Id': tenantId } : {}),
            };

            const state = {
                selectedInvoiceId: null,
            };

            const el = {
                list: document.getElementById('invoice-list'),
                status: document.getElementById('status-filter'),
                reload: document.getElementById('btn-reload'),
                detail: document.getElementById('invoice-detail'),
                detailHead: document.getElementById('detail-head'),
                detailMeta: document.getElementById('detail-meta'),
                detailItems: document.getElementById('detail-items'),
                download: document.getElementById('btn-download'),
                markPaid: document.getElementById('btn-mark-paid'),
                error: document.getElementById('error-box'),
                success: document.getElementById('success-box'),
            };

            const showError = (message) => {
                el.error.classList.remove('hidden');
                el.error.textContent = message;
            };

            const showSuccess = (message) => {
                el.success.classList.remove('hidden');
                el.success.textContent = message;
            };

            const clearAlerts = () => {
                el.error.classList.add('hidden');
                el.success.classList.add('hidden');
            };

            const loadList = async () => {
                clearAlerts();
                const params = new URLSearchParams();
                if (el.status.value) {
                    params.set('status', el.status.value);
                }

                const response = await fetch(`/api/v1/subscription/invoices?${params.toString()}`, { headers });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(payload.message || 'Gagal memuat invoice.');
                }
                const data = window.PosUi.unwrapApiPayload(payload) || {};
                const rows = Array.isArray(data.invoices) ? data.invoices : (Array.isArray(data.items) ? data.items : []);

                if (!rows.length) {
                    el.list.innerHTML = '<p class="rounded-xl border border-dashed border-slate-300 p-3 text-xs text-slate-500 dark:border-slate-700">Belum ada invoice.</p>';
                    return;
                }

                el.list.innerHTML = rows.map((invoice) => {
                    const badgeClass = invoice.status === 'paid'
                        ? 'bg-emerald-100 text-emerald-700'
                        : (invoice.status === 'partial' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700');

                    return `
                        <button data-id="${invoice.id}" class="invoice-item w-full rounded-xl border border-slate-200 bg-white p-3 text-left shadow-sm transition hover:border-teal-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-teal-800">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold">${invoice.number}</p>
                                <span class="ml-auto rounded-full px-2 py-0.5 text-[10px] font-bold ${badgeClass}">${invoice.status}</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Periode ${invoice.period_start} - ${invoice.period_end}</p>
                            <p class="text-xs text-slate-500">Due: ${invoice.due_at || '-'} | Total: ${invoice.amount_due}</p>
                        </button>
                    `;
                }).join('');

                document.querySelectorAll('.invoice-item').forEach((node) => {
                    node.addEventListener('click', () => loadDetail(node.dataset.id));
                });
            };

            const loadDetail = async (invoiceId) => {
                clearAlerts();
                const response = await fetch(`/api/v1/subscription/invoices/${invoiceId}`, { headers });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(payload.message || 'Gagal memuat detail invoice.');
                }
                const data = window.PosUi.unwrapApiPayload(payload) || {};
                state.selectedInvoiceId = invoiceId;
                const invoice = data.invoice || payload.invoice || {};
                const items = data.items || payload.items || [];

                el.detail.classList.remove('hidden');
                el.detailHead.textContent = `${invoice.number} (${invoice.status})`;
                el.detailMeta.textContent = `Total ${invoice.amount_due} | Paid ${invoice.amount_paid} | Due ${invoice.due_at || '-'}`;
                el.detailItems.innerHTML = items.length
                    ? items.map((item) => `<p>- ${item.description} x${item.qty} @${item.unit_price} = ${item.total_price}</p>`).join('')
                    : '<p class="text-slate-500">Tidak ada item.</p>';

                el.download.href = `/api/v1/subscription/invoices/${invoiceId}/download`;
                el.markPaid.disabled = invoice.status === 'paid';
                el.markPaid.classList.toggle('opacity-50', invoice.status === 'paid');
            };

            const markPaid = async () => {
                if (!state.selectedInvoiceId) {
                    return;
                }

                clearAlerts();
                const reference = prompt('Masukkan reference pembayaran manual', `MANUAL-${Date.now()}`);
                if (!reference) {
                    return;
                }

                const response = await fetch(`/api/v1/subscription/invoices/${state.selectedInvoiceId}/mark-paid`, {
                    method: 'POST',
                    headers: {
                        ...headers,
                        'X-Idempotency-Key': `mark-paid-${state.selectedInvoiceId}-${reference}`,
                    },
                    body: JSON.stringify({
                        provider: 'manual',
                        reference,
                    }),
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(payload.message || 'Gagal mark paid.');
                }

                showSuccess(payload.already_paid ? 'Invoice sudah berstatus paid.' : 'Invoice berhasil ditandai paid.');
                await loadList();
                await loadDetail(state.selectedInvoiceId);
            };

            el.reload.addEventListener('click', () => loadList().catch((error) => showError(error.message)));
            el.status.addEventListener('change', () => loadList().catch((error) => showError(error.message)));
            el.markPaid.addEventListener('click', () => markPaid().catch((error) => showError(error.message)));

            loadList().catch((error) => showError(error.message));
        })();
    </script>
@endsection
