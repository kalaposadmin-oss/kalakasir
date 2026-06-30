@extends('layouts.app')

@php
    $menuPortal = session('auth_portal', 'pos') === 'backoffice' ? 'backoffice' : 'pos';
@endphp

@section('page_title', $title)
@section('ui_mode', $menuPortal)
@section('body_class', 'bg-[#f8f9ff] text-[#111c2d] antialiased')
@section('main_class', 'flex-1 overflow-y-auto app-bottom-safe-space')

@section('content')
    <div class="app-mobile-page px-5 pb-8 pt-5">
        <header class="mb-5 flex items-center gap-3">
            <a class="flex size-11 shrink-0 items-center justify-center rounded-full bg-white text-[#1e3a8a] shadow-sm ring-1 ring-[#e2e6f2]" href="/menu" aria-label="Kembali ke semua menu">
                <span class="material-symbols-outlined text-[24px]">arrow_back</span>
            </a>
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[.12em] text-[#777587]">Menu Toko · Kategori</p>
                <h1 class="truncate text-xl font-extrabold tracking-tight text-[#1e3a8a]">{{ $title }}</h1>
            </div>
        </header>

        <div class="app-hub-grid">
            @foreach($items as $item)
                <a class="app-hub-tile" href="{{ $item['href'] }}">
                    <span class="flex items-start justify-between gap-2">
                        <span class="app-hub-icon"><span class="material-symbols-outlined">{{ $item['icon'] }}</span></span>
                        @if (!empty($item['badge']))
                            <span class="rounded-full bg-amber-50 px-2 py-1 text-[9px] font-extrabold uppercase tracking-wide text-amber-700">{{ $item['badge'] }}</span>
                        @endif
                    </span>
                    <span class="mt-3 block truncate text-sm font-extrabold text-[#111c2d]">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endsection
