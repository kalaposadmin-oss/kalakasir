@extends('layouts.app')

@php
    $helpSupportTenant = \App\Support\TenantContext::get();
    $helpSupportUser = auth()->user();
    $helpSupportRole = null;

    if (! $helpSupportTenant && filled($helpSupportUser?->tenant_id)) {
        $helpSupportTenant = \App\Models\Tenant::query()->find($helpSupportUser->tenant_id);
    }

    if ($helpSupportTenant && $helpSupportUser) {
        $helpSupportRole = app(\App\Services\UserRoleResolverService::class)
            ->resolveNormalizedRole((string) $helpSupportTenant->id, (string) $helpSupportUser->id);

        if (! $helpSupportRole && filled($helpSupportUser->role ?? null)) {
            $helpSupportRole = app(\App\Services\UserRoleResolverService::class)
                ->normalize((string) $helpSupportUser->role);
        }
    }

    abort_unless(in_array($helpSupportRole, ['warehouse', 'supervisor', 'admin', 'owner', 'superadmin'], true), 403);
@endphp

@section('page_title', 'Hubungi Support - POS SaaS')
@section('ui_mode', 'backoffice')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('main_class', 'flex-1 overflow-y-auto lg:pl-72')

@section('header')
    <x-page-header
        title="Dukungan & Tiket Bantuan"
        :breadcrumbs="['Bantuan']"
        backUrl="/backoffice/help/center"
        :showMenuButton="true"
    />
@endsection

@section('content')
    <div class="w-full px-4 py-4 lg:px-8 space-y-6">
        <section class="flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs dark:border-emerald-900/50 dark:bg-emerald-900/20">
            <p id="support-sla" class="font-bold text-emerald-700 dark:text-emerald-300">Rata-rata respon support: &lt; 30 menit</p>
            <button class="rounded-lg bg-primary px-3 py-2 font-bold text-white">Buat Tiket Baru</button>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-bold">WhatsApp</p>
                <p class="mt-1 text-xs text-slate-500">Respon cepat 08:00 - 22:00</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-bold">Email Support</p>
                <p class="mt-1 text-xs text-slate-500">support@pos-saas.com</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-bold">Telepon Bantuan</p>
                <p class="mt-1 text-xs text-slate-500">021-500-999</p>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Riwayat Tiket Bantuan</h3>
                <select class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-800">
                    <option>Semua Status</option>
                    <option>Open</option>
                    <option>Resolved</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 dark:bg-slate-800/40">
                        <tr>
                            <th class="px-5 py-3">No Tiket</th>
                            <th class="px-5 py-3">Subjek</th>
                            <th class="px-5 py-3">Prioritas</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Update Terakhir</th>
                        </tr>
                    </thead>
                    <tbody id="support-ticket-body" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr>
                            <td class="px-5 py-4 text-sm text-slate-500" colspan="5">Memuat tiket bantuan...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-xs text-blue-700 dark:border-blue-900/50 dark:bg-blue-900/20 dark:text-blue-300">
            Lebih dari 80% masalah umum bisa selesai melalui Pusat Bantuan tanpa perlu membuat tiket.
        </section>
    </div>

    <script>
        (() => {
            const tenantId = document.querySelector('meta[name="tenant-id"]')?.content;
            const headers = tenantId ? { 'X-Tenant-Id': tenantId } : {};

            const el = {
                sla: document.getElementById('support-sla'),
                ticketBody: document.getElementById('support-ticket-body'),
            };

            const fallbackTickets = [
                { id: '#TKT-240501', subject: 'Gagal integrasi printer Bluetooth', priority: 'Tinggi', status: 'open', updated: '12 menit lalu' },
                { id: '#TKT-240488', subject: 'Pertanyaan outlet baru', priority: 'Sedang', status: 'resolved', updated: 'Kemarin 14:20' },
                { id: '#TKT-240452', subject: 'Kesalahan stok opname di dashboard', priority: 'Sedang', status: 'resolved', updated: '3 hari lalu' },
            ];

            const renderTickets = (tickets) => {
                if (!tickets.length) {
                    el.ticketBody.innerHTML = '<tr><td class="px-5 py-4 text-sm text-slate-500" colspan="5">Belum ada tiket bantuan.</td></tr>';
                    return;
                }

                el.ticketBody.innerHTML = tickets.map((ticket) => {
                    const isOpen = String(ticket.status || '').toLowerCase() === 'open';
                    return `
                        <tr>
                            <td class="px-5 py-4 font-semibold text-primary">${ticket.id}</td>
                            <td class="px-5 py-4">${ticket.subject}</td>
                            <td class="px-5 py-4">${ticket.priority}</td>
                            <td class="px-5 py-4 ${isOpen ? 'text-amber-600' : 'text-emerald-600'}">${isOpen ? 'Open' : 'Resolved'}</td>
                            <td class="px-5 py-4 text-xs text-slate-500">${ticket.updated}</td>
                        </tr>
                    `;
                }).join('');
            };

            const load = async () => {
                // Endpoint tiket support belum tersedia; gunakan fallback terstruktur.
                renderTickets(fallbackTickets);

                const bootstrap = await fetch('/api/v1/bootstrap', { headers });
                if (bootstrap.ok) {
                    const payload = await bootstrap.json();
                    const plan = String(payload?.tenant?.plan || '').toUpperCase();
                    if (plan === 'P') {
                        el.sla.textContent = 'Rata-rata respon support: < 15 menit';
                    }
                }
            };

            load().catch(() => renderTickets(fallbackTickets));
        })();
    </script>
@endsection
