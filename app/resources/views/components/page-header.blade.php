@props([
    'title' => '',
    'titleId' => null,
    'subtitle' => null,
    'subtitleId' => null,
    'backUrl' => null,
    'breadcrumbs' => [],
    'showMenuButton' => true,
])

@php
    $isBackofficeHeader = request()->is('backoffice*') || request()->is('admin/opening-balance') || request()->is('superadmin*');
@endphp

<header class="app-page-header sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur-xl dark:border-slate-800 dark:bg-background-dark/90">
    <div class="flex min-h-[64px] items-center justify-between px-4 py-3 lg:px-8">
        <div class="flex items-center gap-3 min-w-0">
            @if($backUrl)
                <a
                    href="{{ $backUrl }}"
                    onclick="try{var referrer=document.referrer?new URL(document.referrer):null;var target=new URL(this.href,window.location.origin);if(window.history.length>1&&referrer&&referrer.origin===window.location.origin&&referrer.pathname===target.pathname){window.history.back();return false;}}catch(error){}return true;"
                    class="flex size-10 items-center justify-center rounded-full bg-slate-100 text-slate-600 shadow-sm ring-1 ring-slate-200/70 active:opacity-80 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700"
                >
                    <span class="material-symbols-outlined text-[20px]">arrow_back_ios_new</span>
                </a>
            @endif

            <div class="min-w-0">
                @if(!empty($breadcrumbs))
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 truncate">
                        {{ implode(' / ', $breadcrumbs) }}
                    </p>
                @endif
                <h1 @if($titleId) id="{{ $titleId }}" @endif class="truncate text-base font-black tracking-tight text-slate-950 dark:text-slate-100">{{ $title }}</h1>
                @if($subtitle)
                    <p @if($subtitleId) id="{{ $subtitleId }}" @endif class="text-[11px] font-medium text-slate-500 dark:text-slate-400 truncate">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            {{ $actions ?? '' }}
            @if($showMenuButton)
                <button type="button" data-menu-trigger aria-label="Buka menu" class="{{ $isBackofficeHeader ? 'xl:hidden' : '' }} flex size-10 items-center justify-center rounded-full bg-slate-100 text-slate-600 shadow-sm ring-1 ring-slate-200/70 active:opacity-80 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700">
                    <span class="material-symbols-outlined text-[20px]">menu</span>
                </button>
            @endif
        </div>
    </div>
</header>
