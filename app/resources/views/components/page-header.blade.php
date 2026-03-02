@props([
    'title' => '',
    'titleId' => null,
    'subtitle' => null,
    'subtitleId' => null,
    'backUrl' => null,
    'breadcrumbs' => [],
    'showMenuButton' => true,
])

<header class="sticky top-0 z-40 bg-white/80 dark:bg-background-dark/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800">
    <div class="flex items-center justify-between px-4 py-3">
        <div class="flex items-center gap-3 min-w-0">
            @if($backUrl)
                <a href="{{ $backUrl }}" class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    <span class="material-symbols-outlined text-[20px]">arrow_back_ios_new</span>
                </a>
            @endif

            <div class="min-w-0">
                @if(!empty($breadcrumbs))
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-400 truncate">
                        {{ implode(' / ', $breadcrumbs) }}
                    </p>
                @endif
                <h1 @if($titleId) id="{{ $titleId }}" @endif class="text-base font-bold tracking-tight text-slate-900 dark:text-slate-100 truncate">{{ $title }}</h1>
                @if($subtitle)
                    <p @if($subtitleId) id="{{ $subtitleId }}" @endif class="text-[11px] font-medium text-slate-500 dark:text-slate-400 truncate">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            {{ $actions ?? '' }}
            @if($showMenuButton)
                <button type="button" data-menu-trigger class="flex items-center justify-center size-9 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    <span class="material-symbols-outlined text-[20px]">menu</span>
                </button>
            @endif
        </div>
    </div>
</header>
