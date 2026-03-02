@extends('layouts.app')

@section('page_title', 'Analytics')
@section('body_class', 'bg-slate-50 text-slate-900')
@section('main_class', 'flex-1 overflow-y-auto p-4')

@section('header')
    <x-page-header title="Analytics" subtitle="Ringkasan performa penjualan" backUrl="/menu/reports" :showMenuButton="true" />
@endsection

@section('content')
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Ringkasan</p>
                <h2 id="range-label" class="text-base font-bold text-slate-900">Bulan Ini</h2>
            </div>
        </div>

        <x-period-filter id="analytics-period" defaultPreset="thisMonth" />

        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
            <button class="px-3 py-1.5 rounded-full text-xs font-bold border border-primary text-primary bg-primary/5" data-analytics-tab="summary">Ringkasan</button>
            <button class="px-3 py-1.5 rounded-full text-xs font-bold border border-slate-200 text-slate-600 bg-white" data-analytics-tab="daily">Harian</button>
            <button class="px-3 py-1.5 rounded-full text-xs font-bold border border-slate-200 text-slate-600 bg-white" data-analytics-tab="payments">Pembayaran</button>
            <button class="px-3 py-1.5 rounded-full text-xs font-bold border border-slate-200 text-slate-600 bg-white" data-analytics-tab="top">Produk</button>
        </div>

        <div id="tab-summary" class="rounded-xl border border-slate-200 bg-white p-4" data-tab-panel="summary">
            <div id="summary-cards" class="grid grid-cols-2 gap-3"></div>
        </div>

        <div id="tab-daily" class="rounded-xl border border-slate-200 bg-white p-4 hidden" data-tab-panel="daily">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Ringkasan Harian</p>
                    <p class="text-xs text-slate-500">Omzet, transaksi, item</p>
                </div>
                <span class="text-[10px] text-slate-400" id="range-date">-</span>
            </div>
            <div id="daily-list" class="space-y-3"></div>
            <p id="daily-empty" class="text-xs text-slate-400 hidden">Belum ada transaksi.</p>
        </div>

        <div id="tab-payments" class="rounded-xl border border-slate-200 bg-white p-4 hidden" data-tab-panel="payments">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Metode Pembayaran</p>
                    <p class="text-xs text-slate-500">Komposisi pembayaran</p>
                </div>
            </div>
            <div id="payment-list" class="space-y-2"></div>
            <p id="payment-empty" class="text-xs text-slate-400 hidden">Belum ada pembayaran.</p>
        </div>

        <div id="tab-top" class="rounded-xl border border-slate-200 bg-white p-4 hidden" data-tab-panel="top">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Produk Terlaris</p>
                    <p class="text-xs text-slate-500">Top 5 berdasarkan omzet</p>
                </div>
            </div>
            <div id="top-products" class="space-y-3"></div>
            <p id="top-empty" class="text-xs text-slate-400 hidden">Belum ada transaksi.</p>
        </div>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

            const el = {
                rangeLabel: document.getElementById('range-label'),
                rangeDate: document.getElementById('range-date'),
                period: document.getElementById('analytics-period'),
                summaryCards: document.getElementById('summary-cards'),
                dailyList: document.getElementById('daily-list'),
                dailyEmpty: document.getElementById('daily-empty'),
                paymentList: document.getElementById('payment-list'),
                paymentEmpty: document.getElementById('payment-empty'),
                topProducts: document.getElementById('top-products'),
                topEmpty: document.getElementById('top-empty'),
            };

            const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(value || 0);

            const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
                day: '2-digit',
                month: 'short',
            }).format(new Date(value));

            const renderSummary = (summary) => {
                const cards = [
                    { label: 'Omzet', value: formatCurrency(summary.revenue) },
                    { label: 'Transaksi', value: summary.orders || 0 },
                    { label: 'Item Terjual', value: summary.items || 0 },
                    { label: 'AOV', value: formatCurrency(summary.aov) },
                    { label: 'Diskon', value: formatCurrency(summary.discount) },
                    { label: 'Biaya Tambahan', value: formatCurrency(summary.fee) },
                    { label: 'Pajak', value: formatCurrency(summary.tax) },
                    { label: 'Margin Kotor', value: formatCurrency(summary.profit) },
                ];
                el.summaryCards.innerHTML = '';
                cards.forEach((card) => {
                    const item = document.createElement('div');
                    item.className = 'rounded-xl border border-slate-200 bg-white p-3';
                    item.innerHTML = `
                        <p class="text-[10px] font-bold text-slate-400 uppercase">${card.label}</p>
                        <p class="text-base font-bold text-slate-900 mt-1">${card.value}</p>
                    `;
                    el.summaryCards.appendChild(item);
                });
            };

            const renderDaily = (daily) => {
                el.dailyList.innerHTML = '';
                if (!daily.length) {
                    el.dailyEmpty.classList.remove('hidden');
                    return;
                }
                el.dailyEmpty.classList.add('hidden');
                daily.slice().reverse().forEach((row) => {
                    const item = document.createElement('div');
                    item.className = 'flex items-center justify-between';
                    item.innerHTML = `
                        <div>
                            <p class="text-sm font-semibold text-slate-900">${formatDate(row.date)}</p>
                            <p class="text-xs text-slate-500">${row.orders} transaksi • ${row.items} item</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-slate-900">${formatCurrency(row.revenue)}</p>
                            <p class="text-xs text-slate-500">Diskon ${formatCurrency(row.discount)}</p>
                        </div>
                    `;
                    el.dailyList.appendChild(item);
                });
            };

            const renderPayments = (payments) => {
                el.paymentList.innerHTML = '';
                if (!payments.length) {
                    el.paymentEmpty.classList.remove('hidden');
                    return;
                }
                el.paymentEmpty.classList.add('hidden');

                payments.forEach((payment) => {
                    const item = document.createElement('div');
                    item.className = 'flex items-center justify-between';
                    item.innerHTML = `
                        <div>
                            <p class="text-sm font-semibold text-slate-900">${payment.method.toUpperCase()}</p>
                            <p class="text-xs text-slate-500">${payment.percent}%</p>
                        </div>
                        <p class="text-sm font-bold text-slate-900">${formatCurrency(payment.amount)}</p>
                    `;
                    el.paymentList.appendChild(item);
                });
            };

            const renderTopProducts = (products) => {
                el.topProducts.innerHTML = '';
                if (!products.length) {
                    el.topEmpty.classList.remove('hidden');
                    return;
                }
                el.topEmpty.classList.add('hidden');

                products.forEach((product) => {
                    const item = document.createElement('div');
                    item.className = 'flex items-center justify-between';
                    item.innerHTML = `
                        <div>
                            <p class="text-sm font-semibold text-slate-900">${product.name}</p>
                            <p class="text-xs text-slate-500">${product.qty} item • ${product.category_name || 'Produk'}</p>
                        </div>
                        <p class="text-sm font-bold text-slate-900">${formatCurrency(product.revenue)}</p>
                    `;
                    el.topProducts.appendChild(item);
                });
            };

            const loadAnalytics = async (preset, startDate, endDate) => {
                const url = new URL('/api/v1/analytics', window.location.origin);
                url.searchParams.set('preset', preset);
                url.searchParams.set('start_date', startDate);
                url.searchParams.set('end_date', endDate);
                const response = await fetch(url, { headers });
                if (!response.ok) {
                    throw new Error('Gagal memuat analytics.');
                }
                return response.json();
            };

            const renderAnalytics = (data) => {
                el.rangeLabel.textContent = data.range?.label || 'Ringkasan';
                el.rangeDate.textContent = data.range?.start_date ? `${data.range.start_date} - ${data.range.end_date}` : '-';
                renderSummary(data.summary || {
                    revenue: 0,
                    orders: 0,
                    items: 0,
                    aov: 0,
                    discount: 0,
                    fee: 0,
                    tax: 0,
                    profit: 0,
                });
                renderDaily(data.daily || []);
                renderPayments(data.payments || []);
                renderTopProducts(data.top_products || []);
            };

            let currentPreset = 'thisMonth';
            let currentFrom = '';
            let currentTo = '';

            const setActiveTab = (tab) => {
                document.querySelectorAll('[data-analytics-tab]').forEach((button) => {
                    const active = button.dataset.analyticsTab === tab;
                    button.classList.toggle('border-primary', active);
                    button.classList.toggle('text-primary', active);
                    button.classList.toggle('bg-primary/5', active);
                    button.classList.toggle('border-slate-200', !active);
                    button.classList.toggle('text-slate-600', !active);
                    button.classList.toggle('bg-white', !active);
                });

                document.querySelectorAll('[data-tab-panel]').forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.tabPanel !== tab);
                });
            };

            document.querySelectorAll('[data-analytics-tab]').forEach((button) => {
                button.addEventListener('click', () => {
                    setActiveTab(button.dataset.analyticsTab);
                });
            });

            const reloadAnalytics = async () => {
                try {
                    const data = await loadAnalytics(currentPreset, currentFrom, currentTo);
                    renderAnalytics(data);
                } catch (_) {
                    renderAnalytics({ summary: {}, daily: [], payments: [], top_products: [], range: {} });
                }
            };

            el.period?.addEventListener('period:change', (event) => {
                currentPreset = event.detail.preset || 'thisMonth';
                currentFrom = event.detail.start_date || '';
                currentTo = event.detail.end_date || '';
                reloadAnalytics();
            });

            (async () => {
                setActiveTab('summary');
                try {
                    await reloadAnalytics();
                } catch (_) {
                    renderAnalytics({ summary: {}, daily: [], payments: [], top_products: [], range: {} });
                }
            })();
        })();
    </script>
@endsection
