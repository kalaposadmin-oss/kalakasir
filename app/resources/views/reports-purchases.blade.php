@extends('layouts.app')

@php
    $purchaseReportsTenant = \App\Support\TenantContext::get();
    $purchaseReportsUser = auth()->user();
    $purchaseReportsRole = null;

    if (! $purchaseReportsTenant && filled($purchaseReportsUser?->tenant_id)) {
        $purchaseReportsTenant = \App\Models\Tenant::query()->find($purchaseReportsUser->tenant_id);
    }

    if ($purchaseReportsTenant && $purchaseReportsUser) {
        $purchaseReportsRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $purchaseReportsTenant->id, (string) $purchaseReportsUser->id);

        if (! $purchaseReportsRole && filled($purchaseReportsUser->role ?? null)) {
            $purchaseReportsRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $purchaseReportsUser->role);
        }
    }

    abort_unless(in_array($purchaseReportsRole, ['supervisor', 'admin', 'owner', 'superadmin'], true), 403);
@endphp

@section('page_title', 'Laporan Pembelian')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('main_class', 'flex-1 overflow-y-auto pb-8 lg:pl-72')

@section('header')
    <x-page-header title="Laporan Pembelian" :breadcrumbs="['Laporan']" :showMenuButton="true" backUrl="/backoffice/reports" />
@endsection

@section('content')
    <div class="w-full px-4 py-5 lg:px-8 space-y-6">
        <section class="grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Total PO</p>
                <p id="purchase-report-total-count" class="mt-3 text-3xl font-black text-slate-900 dark:text-white">0</p>
                <p class="mt-2 text-xs text-slate-500">Jumlah purchase order yang masuk ke laporan aktif.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Nilai Barang</p>
                <p id="purchase-report-subtotal-amount" class="mt-3 text-3xl font-black text-slate-900 dark:text-white">Rp 0</p>
                <p class="mt-2 text-xs text-slate-500">Subtotal barang yang menambah stok.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Biaya Tambahan</p>
                <p id="purchase-report-additional-cost-amount" class="mt-3 text-3xl font-black text-orange-600">Rp 0</p>
                <p class="mt-2 text-xs text-slate-500">Plastik, kardus, ongkir, dan biaya non-stok.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Total Tagihan</p>
                <p id="purchase-report-total-amount" class="mt-3 text-3xl font-black text-primary">Rp 0</p>
                <p class="mt-2 text-xs text-slate-500">Barang, pajak, dan biaya tambahan.</p>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Pembelian Formal</p>
                    <h2 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Riwayat Purchase Order</h2>
                </div>
                <a class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 dark:border-slate-700 dark:text-slate-200" href="/backoffice/suppliers">Buka Pemasok & PO</a>
            </div>
            <div class="mt-5 overflow-hidden rounded-2xl border border-slate-100 dark:border-slate-800">
                <div id="purchase-report-list" class="divide-y divide-slate-100 dark:divide-slate-800"></div>
            </div>
            <p id="purchase-report-empty" class="mt-4 hidden text-sm text-slate-400">Belum ada data purchase order untuk ditampilkan.</p>
        </section>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};
            const purchaseStatusLabels = @json(\App\Support\PurchaseStatus::labels());
            const purchaseStatusAliases = @json(\App\Support\PurchaseStatus::aliases());

            const normalizePurchaseStatus = (status) => {
                const value = String(status || '').toLowerCase();
                return purchaseStatusAliases[value] || value;
            };

            const el = {
                totalCount: document.getElementById('purchase-report-total-count'),
                subtotalAmount: document.getElementById('purchase-report-subtotal-amount'),
                additionalCostAmount: document.getElementById('purchase-report-additional-cost-amount'),
                totalAmount: document.getElementById('purchase-report-total-amount'),
                list: document.getElementById('purchase-report-list'),
                empty: document.getElementById('purchase-report-empty'),
            };

            const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(value || 0);

            const statusTone = (status) => {
                const normalizedStatus = normalizePurchaseStatus(status);

                if (normalizedStatus === 'approved') return 'bg-emerald-100 text-emerald-700';
                if (normalizedStatus === 'cancelled') return 'bg-rose-100 text-rose-700';
                if (normalizedStatus === 'received') return 'bg-blue-100 text-blue-700';
                if (normalizedStatus === 'partially_received') return 'bg-cyan-100 text-cyan-700';
                return 'bg-amber-100 text-amber-700';
            };

            const render = (purchases) => {
                el.list.innerHTML = '';

                if (!purchases.length) {
                    el.empty.classList.remove('hidden');
                    el.totalCount.textContent = '0';
                    el.subtotalAmount.textContent = formatCurrency(0);
                    el.additionalCostAmount.textContent = formatCurrency(0);
                    el.totalAmount.textContent = formatCurrency(0);
                    return;
                }

                el.empty.classList.add('hidden');
                el.totalCount.textContent = String(purchases.length);
                el.subtotalAmount.textContent = formatCurrency(purchases.reduce((sum, purchase) => sum + Number(purchase.subtotal || 0), 0));
                el.additionalCostAmount.textContent = formatCurrency(purchases.reduce((sum, purchase) => sum + Number(purchase.additional_cost_total || 0), 0));
                el.totalAmount.textContent = formatCurrency(purchases.reduce((sum, purchase) => sum + Number(purchase.total || 0), 0));

                purchases.forEach((purchase) => {
                    const additionalCost = Number(purchase.additional_cost_total || 0);
                    const row = document.createElement('a');
                    row.href = `/backoffice/purchases/${purchase.id}?from=${encodeURIComponent('/backoffice/reports/purchases')}`;
                    row.className = 'block bg-white px-4 py-4 transition hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/60';
                    row.innerHTML = `
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs text-slate-400">${purchase.supplier_name || 'Pemasok'}</p>
                                <p class="text-sm font-bold text-slate-900 dark:text-white">PO-${String(purchase.id).slice(0, 6).toUpperCase()}</p>
                            </div>
                            <span class="rounded-full px-2 py-1 text-[10px] font-bold uppercase ${statusTone(purchase.status)}">${purchaseStatusLabels[normalizePurchaseStatus(purchase.status)] || 'Draft'}</span>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Barang</span>
                                <span class="font-bold text-slate-900 dark:text-white">${formatCurrency(purchase.subtotal || 0)}</span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Biaya</span>
                                <span class="font-bold ${additionalCost > 0 ? 'text-orange-600' : 'text-slate-400'}">${formatCurrency(additionalCost)}</span>
                            </div>
                            <div class="text-right">
                                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Total</span>
                                <span class="font-bold text-slate-900 dark:text-white">${formatCurrency(purchase.total || 0)}</span>
                            </div>
                        </div>
                    `;
                    el.list.appendChild(row);
                });
            };

            (async () => {
                const response = await fetch('/api/v1/purchases', { headers });
                if (!response.ok) {
                    render([]);
                    return;
                }

                const payload = await response.json().catch(() => ({}));
                render(payload.purchases || []);
            })();
        })();
    </script>
@endsection
