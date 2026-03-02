@extends('layouts.app')

@section('page_title', 'Mutasi Stok Antar Cabang')
@section('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display')
@section('app_shell_class', 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl')
@section('main_class', 'flex-1 overflow-hidden p-0')

@push('head')
        <style>
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
@endpush

@section('header')
        <header class="sticky top-0 z-50 bg-white/80 dark:bg-background-dark/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800" data-auto-center-title="true">
            <div class="flex items-center justify-between p-4 pb-2">
                <div class="flex items-center gap-3">
                    <button class="material-symbols-outlined text-slate-900 dark:text-slate-100 cursor-pointer" onclick="location.href='/menu/reports'">arrow_back_ios</button>
                    <h1 class="text-lg font-bold tracking-tight">Mutasi Stok</h1>
                </div>
                <div class="flex gap-2">
                    <button class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800">
                        <span class="material-symbols-outlined text-slate-600 dark:text-slate-400 text-[20px]">filter_list</span>
                    </button>
                    <button class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800">
                        <span class="material-symbols-outlined text-slate-600 dark:text-slate-400 text-[20px]">search</span>
                    </button>
                </div>
            </div>
            <div class="flex px-4 border-b border-slate-100 dark:border-slate-800">
                <button class="flex-1 py-3 text-sm font-semibold border-b-2 border-primary text-primary">Aktif</button>
                <button class="flex-1 py-3 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700">Selesai</button>
                <button class="flex-1 py-3 text-sm font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-700">Draft</button>
            </div>
        </header>
@endsection

@section('content')
        <div class="flex-1 overflow-y-auto pb-32">
            <div class="flex gap-3 p-4 overflow-x-auto no-scrollbar" id="transfer-stats"></div>
            <div class="px-4 space-y-3">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest px-1">Transfer Aktif</h3>
                <div id="transfer-list" class="space-y-3"></div>
            </div>
        </div>
        <div class="fixed bottom-24 right-[calc(50%-215px+16px)] z-40 max-w-[430px]">
            <button class="flex items-center gap-2 bg-primary text-white py-3 px-5 rounded-full font-bold shadow-xl shadow-primary/30">
                <span class="material-symbols-outlined">add_box</span>
                <span>Mutasi Baru</span>
            </button>
        </div>

        <script>
            (() => {
                const tenantId = document.querySelector('meta[name="tenant-id"]').content;
                const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

                const el = {
                    stats: document.getElementById('transfer-stats'),
                    list: document.getElementById('transfer-list'),
                };

                const formatDate = (value) => new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                }).format(new Date(value));

                const renderStats = (stats) => {
                    el.stats.innerHTML = `
                        <div class="min-w-[140px] bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm">
                            <p class="text-[10px] font-bold text-slate-500 uppercase mb-1">Dalam Perjalanan</p>
                            <p class="text-lg font-bold">${stats.in_transit || 0} <span class="text-xs font-normal text-slate-400">Pesanan</span></p>
                        </div>
                        <div class="min-w-[140px] bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm border-l-4 border-l-amber-400">
                            <p class="text-[10px] font-bold text-slate-500 uppercase mb-1">Perlu Diterima</p>
                            <p class="text-lg font-bold">${stats.pending_receive || 0} <span class="text-xs font-normal text-slate-400">Pesanan</span></p>
                        </div>
                    `;
                };

                const renderTransfers = (transfers) => {
                    el.list.innerHTML = '';

                    if (!transfers.length) {
                        el.list.innerHTML = '<div class="text-sm text-slate-400">Belum ada mutasi.</div>';
                        return;
                    }

                    transfers.forEach((transfer) => {
                        const status = transfer.status || 'draft';
                        const statusMap = {
                            in_transit: 'Dalam Perjalanan',
                            pending_receive: 'Perlu Konfirmasi',
                            received: 'Diterima',
                            draft: 'Draft',
                        };
                        const statusClassMap = {
                            in_transit: 'bg-blue-100 text-blue-700',
                            pending_receive: 'bg-amber-100 text-amber-700',
                            received: 'bg-emerald-100 text-emerald-700',
                            draft: 'bg-slate-100 text-slate-500',
                        };

                        const row = document.createElement('div');
                        row.className = 'bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm';
                        row.innerHTML = `
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">${transfer.reference || 'TRF'}</span>
                                    <span class="text-xs text-slate-500">${formatDate(transfer.created_at)}</span>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${statusClassMap[status] || 'bg-slate-100 text-slate-500'} uppercase">${statusMap[status] || 'Draft'}</span>
                            </div>
                            <div class="flex items-center gap-3 py-3 border-y border-slate-50 dark:border-slate-800">
                                <div class="flex-1">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">Dari</p>
                                    <p class="font-bold text-sm">${transfer.from_branch || '-'}</p>
                                </div>
                                <span class="material-symbols-outlined text-slate-300">arrow_forward</span>
                                <div class="flex-1 text-right">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">Ke</p>
                                    <p class="font-bold text-sm">${transfer.to_branch || '-'}</p>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-slate-400 text-lg">inventory_2</span>
                                    <span class="text-sm font-medium text-slate-600 dark:text-slate-400">${transfer.item_count} Produk (${transfer.qty_total} pcs)</span>
                                </div>
                                ${status === 'pending_receive'
                                    ? '<button class="bg-primary/10 text-primary px-3 py-1 rounded-lg text-xs font-bold">Terima Barang</button>'
                                    : '<button class="text-primary text-xs font-bold">Detail</button>'}
                            </div>
                        `;
                        el.list.appendChild(row);
                    });
                };

                (async () => {
                    const response = await fetch('/api/v1/stock-transfers', { headers });
                    if (!response.ok) {
                        el.list.innerHTML = '<div class="text-sm text-red-500">Gagal memuat mutasi.</div>';
                        return;
                    }
                    const data = await response.json();
                    renderStats(data.stats || {});
                    renderTransfers(data.transfers || []);
                })();
            })();
        </script>
@endsection
