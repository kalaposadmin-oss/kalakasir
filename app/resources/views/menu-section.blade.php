@extends('layouts.app')

@section('page_title', 'Menu ' . $title . ' - POS SaaS')
@section('body_class', 'bg-background-light dark:bg-background-dark font-display')
@section('main_class', 'flex-1 overflow-y-auto')

@push('head')
        <style type="text/tailwindcss">
            @layer base {
                body {
                    font-family: 'Inter', sans-serif;
                    -webkit-tap-highlight-color: transparent;
                }
            }
            .scrollbar-hide::-webkit-scrollbar {
                display: none;
            }
            .menu-border {
                border-bottom: 1px solid #f1f5f9;
            }
        </style>
        <style>
            body {
                min-height: max(884px, 100dvh);
            }
        </style>
    
@endpush

@php
    $menuBackUrl = session('auth_portal', 'pos') === 'backoffice'
        ? '/backoffice/dashboard'
        : '/pos/dashboard';
@endphp

@section('header')
    <x-page-header
        :title="$title"
        :breadcrumbs="['Menu']"
        :backUrl="$menuBackUrl"
        :showMenuButton="true"
    />
@endsection

@section('content')
    <div class="flex flex-col scrollbar-hide">
        @foreach ($items as $item)
            <a class="flex items-center gap-6 px-6 py-6 hover:bg-slate-50 transition-colors menu-border group" href="{{ $item['href'] }}">
                <span class="material-symbols-outlined text-primary text-2xl">{{ $item['icon'] }}</span>
                <span class="text-slate-900 dark:text-slate-100 font-semibold text-base">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
@endsection
