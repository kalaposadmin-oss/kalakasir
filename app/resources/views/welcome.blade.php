@extends('layouts.app')

@section('page_title', 'Dashboard Kasir')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100')

@section('header')
    <x-page-header title="Dashboard Kasir" subtitle="-" subtitleId="store-name" :showMenuButton="true">
        <x-slot:actions>
            <button class="flex size-9 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300" type="button">
                <span class="material-symbols-outlined text-[20px]">notifications</span>
            </button>
        </x-slot:actions>
    </x-page-header>
@endsection

@section('content')
    <section class="p-5">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Selamat Datang, <span id="cashier-name">Kasir</span>!</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm"><span id="branch-label">Cabang</span> • ID: <span id="branch-id">-</span></p>
        </div>
    </section>
    <section class="grid grid-cols-2 gap-3 px-5 mb-6">
        <div class="col-span-2 flex flex-col gap-2 rounded-xl p-5 bg-primary text-white shadow-lg shadow-primary/20">
            <div class="flex justify-between items-center">
                <p class="text-primary-100 text-sm font-medium opacity-90">Penjualan Hari Ini</p>
                <span class="material-symbols-outlined text-[20px] opacity-80">trending_up</span>
            </div>
            <p id="sales-today" class="text-2xl font-bold tracking-tight">Rp 0</p>
            <div class="flex items-center gap-1 mt-1">
                <span id="sales-delta" class="text-xs font-semibold bg-white/20 px-2 py-0.5 rounded-full">0%</span>
                <span class="text-[10px] opacity-80">vs kemarin</span>
            </div>
        </div>
        <div class="flex flex-col gap-2 rounded-xl p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-2 text-primary">
                <span class="material-symbols-outlined text-[18px]">schedule</span>
                <p class="text-slate-500 dark:text-slate-400 text-xs font-medium">Status Shift</p>
            </div>
            <p id="shift-status" class="text-lg font-bold text-slate-800 dark:text-slate-100">-</p>
            <p id="shift-label" class="text-[10px] text-emerald-500 font-bold uppercase tracking-wider">-</p>
        </div>
        <div class="flex flex-col gap-2 rounded-xl p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-2 text-primary">
                <span class="material-symbols-outlined text-[18px]">inventory_2</span>
                <p class="text-slate-500 dark:text-slate-400 text-xs font-medium">Total Produk</p>
            </div>
            <p id="total-products" class="text-lg font-bold text-slate-800 dark:text-slate-100">0 Items</p>
            <p class="text-[10px] text-slate-400">Inventory Terpantau</p>
        </div>
    </section>
    <section class="px-5 mb-8">
        <h3 class="text-slate-900 dark:text-slate-100 text-lg font-bold mb-4">Aksi Cepat</h3>
        <div class="grid grid-cols-1 gap-4">
            <a class="flex items-center justify-between w-full bg-primary hover:bg-primary/90 text-white p-5 rounded-xl shadow-md transition-all active:scale-95" href="/kasir">
                <div class="flex items-center gap-4">
                    <div class="bg-white/20 p-3 rounded-lg">
                        <span class="material-symbols-outlined text-[32px]">point_of_sale</span>
                    </div>
                    <div class="text-left">
                        <p class="text-lg font-bold">Mulai Kasir</p>
                        <p class="text-primary-100 text-xs">Input transaksi penjualan baru</p>
                    </div>
                </div>
                <span class="material-symbols-outlined">chevron_right</span>
            </a>
            <a class="flex items-center justify-between w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-xl transition-all active:scale-95" href="/shift/open">
                <div class="flex items-center gap-4">
                    <div class="bg-primary/10 dark:bg-primary/20 p-3 rounded-lg text-primary">
                        <span class="material-symbols-outlined text-[32px]">manage_history</span>
                    </div>
                    <div class="text-left">
                        <p class="text-lg font-bold text-slate-800 dark:text-slate-100">Buka Shift</p>
                        <p class="text-slate-500 text-xs">Atur modal awal laci kasir</p>
                    </div>
                </div>
                <span class="material-symbols-outlined text-slate-400">chevron_right</span>
            </a>
        </div>
    </section>
    <section class="px-5 pb-24">
        <div class="flex flex-col gap-2">
            <a class="flex items-center gap-4 p-4 bg-slate-100 dark:bg-slate-800/50 rounded-lg" href="/sales-history">
                <span class="material-symbols-outlined text-slate-500">receipt_long</span>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Riwayat Terakhir</p>
                    <p class="text-xs text-slate-500">Lihat 10 transaksi terakhir</p>
                </div>
                <span class="material-symbols-outlined text-slate-400 text-sm">arrow_forward_ios</span>
            </a>
            <a class="flex items-center gap-4 p-4 bg-slate-100 dark:bg-slate-800/50 rounded-lg" href="/products/new">
                <span class="material-symbols-outlined text-slate-500">add_box</span>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tambah Produk</p>
                    <p class="text-xs text-slate-500">Scan barcode produk baru</p>
                </div>
                <span class="material-symbols-outlined text-slate-400 text-sm">arrow_forward_ios</span>
            </a>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    (() => {
        const tenantId = document.querySelector('meta[name="tenant-id"]').content;
        const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

        const el = {
            storeName: document.getElementById('store-name'),
            cashierName: document.getElementById('cashier-name'),
            branchLabel: document.getElementById('branch-label'),
            branchId: document.getElementById('branch-id'),
            salesToday: document.getElementById('sales-today'),
            salesDelta: document.getElementById('sales-delta'),
            shiftStatus: document.getElementById('shift-status'),
            shiftLabel: document.getElementById('shift-label'),
            totalProducts: document.getElementById('total-products'),
        };

        const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(value || 0);

        const loadBootstrap = async () => {
            const response = await fetch('/api/v1/bootstrap', { headers });
            if (!response.ok) {
                throw new Error('Gagal memuat data awal.');
            }
            return response.json();
        };

        const loadAnalytics = async () => {
            const response = await fetch('/api/v1/analytics', { headers });
            if (!response.ok) {
                return null;
            }
            return response.json();
        };

        const updateShift = (shifts) => {
            const isOpen = Array.isArray(shifts) && shifts.length > 0;
            el.shiftStatus.textContent = isOpen ? 'Aktif' : 'Tutup';
            el.shiftLabel.textContent = isOpen ? 'Sedang Berjalan' : 'Belum Dibuka';
            el.shiftLabel.className = isOpen
                ? 'text-[10px] text-emerald-500 font-bold uppercase tracking-wider'
                : 'text-[10px] text-slate-400 font-bold uppercase tracking-wider';
        };

        const updateSales = (analytics) => {
            if (!analytics) {
                el.salesToday.textContent = formatCurrency(0);
                el.salesDelta.textContent = '0%';
                return;
            }
            const today = analytics.revenue?.today || 0;
            const yesterday = analytics.revenue?.yesterday || 0;
            const delta = yesterday > 0 ? ((today - yesterday) / yesterday) * 100 : 0;
            const sign = delta > 0 ? '+' : '';
            el.salesToday.textContent = formatCurrency(today);
            el.salesDelta.textContent = `${sign}${delta.toFixed(1)}%`;
        };

        (async () => {
            try {
                const [bootstrap, analytics] = await Promise.all([loadBootstrap(), loadAnalytics()]);
                el.storeName.textContent = bootstrap.tenant?.name || analytics?.branch_label || 'Toko';
                const branch = (bootstrap.branches || [])[0];
                el.branchLabel.textContent = branch?.name || analytics?.branch_label || 'Cabang';
                el.branchId.textContent = branch?.id ? branch.id.slice(0, 6).toUpperCase() : '-';
                el.totalProducts.textContent = `${(bootstrap.products || []).length} Items`;
                updateShift(bootstrap.shifts || []);
                updateSales(analytics);
            } catch (error) {
                updateShift([]);
                updateSales(null);
            }
        })();
    })();
</script>
@endpush





