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
@endphp

<header class="app-page-header sticky top-0 z-40 border-b border-[#e2e6f2] bg-[#f8f9ff]/95 backdrop-blur-xl">
    <div class="flex min-h-[68px] items-center justify-between px-5 py-3">
        <div class="flex items-center gap-3 min-w-0">
            @if($backUrl)
                <a
                    href="{{ $backUrl }}"
                    onclick="try{var referrer=document.referrer?new URL(document.referrer):null;var target=new URL(this.href,window.location.origin);if(window.history.length>1&&referrer&&referrer.origin===window.location.origin&&referrer.pathname===target.pathname){window.history.back();return false;}}catch(error){}return true;"
                    class="flex size-11 items-center justify-center rounded-full bg-white text-[#1e3a8a] shadow-sm ring-1 ring-[#e2e6f2] active:scale-95"
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
                <h1 @if($titleId) id="{{ $titleId }}" @endif class="truncate text-xl font-extrabold tracking-tight text-[#1e3a8a]">{{ $title }}</h1>
                @if($subtitle)
                    <p @if($subtitleId) id="{{ $subtitleId }}" @endif class="text-[11px] font-medium text-slate-500 dark:text-slate-400 truncate">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            {{ $actions ?? '' }}
            @if($showMenuButton)
                <a href="/menu" aria-label="Buka semua menu" class="flex size-11 items-center justify-center rounded-full bg-white text-[#1e3a8a] shadow-sm ring-1 ring-[#e2e6f2] active:scale-95">
                    <span class="material-symbols-outlined text-[22px]">apps</span>
                </a>
            @endif
        </div>
    </div>
</header>
