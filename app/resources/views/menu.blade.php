@extends('layouts.app')

@php
    $menuBackUrl = session('auth_portal', 'pos') === 'backoffice'
        ? '/backoffice/dashboard'
        : '/pos/dashboard';
    $menuPortal = session('auth_portal', 'pos') === 'backoffice' ? 'backoffice' : 'pos';
    $menuSectionMeta = [
        'management' => ['icon' => 'inventory_2', 'description' => 'Produk, stok, pelanggan, dan supplier'],
        'production' => ['icon' => 'precision_manufacturing', 'description' => 'Bahan baku, resep, batch, dan hasil produksi'],
        'sales' => ['icon' => 'receipt_long', 'description' => 'Penjualan, piutang, dan refund'],
        'purchases' => ['icon' => 'shopping_cart', 'description' => 'PO, penerimaan, dan retur'],
        'finance' => ['icon' => 'account_balance_wallet', 'description' => 'Kas, bank, dan ringkasan keuangan'],
        'shift' => ['icon' => 'schedule', 'description' => 'Buka, tutup, dan riwayat shift'],
        'reports' => ['icon' => 'analytics', 'description' => 'Seluruh laporan operasional'],
        'settings' => ['icon' => 'settings', 'description' => 'Toko, pengguna, dan konfigurasi'],
        'other' => ['icon' => 'apps', 'description' => 'Marketing, bantuan, dan fitur lainnya'],
    ];
@endphp

@section('page_title', 'Menu Utama')
@section('ui_mode', $menuPortal)
@section('body_class', 'bg-[#f8f9ff] text-[#111c2d] antialiased')
@section('main_class', 'flex-1 overflow-y-auto app-bottom-safe-space')

@section('header')
    <x-page-header title="Semua Menu" :backUrl="$menuBackUrl" :showMenuButton="false" />
@endsection

@section('content')
    <div class="app-mobile-page px-5 pb-8 pt-5">
        <div class="mb-5 rounded-2xl border border-[#e2e6f2] bg-white p-3 shadow-sm">
            <label class="flex items-center gap-3">
                <span class="material-symbols-outlined text-[22px] text-[#777587]">search</span>
                <input id="menu-hub-search" class="min-h-10 w-full border-0 bg-transparent p-0 text-sm text-[#111c2d] placeholder:text-[#8b94ae] focus:ring-0" type="search" placeholder="Cari menu atau fitur">
            </label>
        </div>

        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-extrabold text-[#111c2d]">Kategori Fitur</h2>
                <span class="text-xs font-semibold text-[#777587]">{{ count($sections ?? []) }} kategori</span>
            </div>
            <div class="app-hub-grid" id="menu-hub-grid">
                @foreach(($sections ?? []) as $sectionKey => $section)
                    @php($meta = $menuSectionMeta[$sectionKey] ?? ['icon' => 'apps', 'description' => 'Buka kelompok fitur'])
                    <a class="app-hub-tile" href="/menu/{{ $sectionKey }}" data-menu-search="{{ strtolower($section['title'].' '.$meta['description']) }}">
                        <span class="app-hub-icon"><span class="material-symbols-outlined">{{ $meta['icon'] }}</span></span>
                        <span class="mt-3 min-w-0">
                            <span class="block truncate text-sm font-extrabold">{{ $section['title'] }}</span>
                            <span class="mt-1 line-clamp-2 block text-[11px] font-medium leading-4 text-[#777587]">{{ $meta['description'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
            <p id="menu-hub-empty" class="hidden rounded-2xl border border-dashed border-[#c7c4d8] p-6 text-center text-sm text-[#777587]">Menu tidak ditemukan.</p>
        </section>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const input = document.getElementById('menu-hub-search');
    const cards = [...document.querySelectorAll('[data-menu-search]')];
    const empty = document.getElementById('menu-hub-empty');
    if (!input) return;
    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        let visible = 0;
        cards.forEach((card) => {
            const show = !query || card.dataset.menuSearch.includes(query);
            card.classList.toggle('hidden', !show);
            if (show) visible += 1;
        });
        empty?.classList.toggle('hidden', visible !== 0);
    });
})();
</script>
@endpush
