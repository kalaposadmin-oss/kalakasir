@php
    $menuRenderVariant = $menuRenderVariant ?? 'mobile';
    $isDesktopMenu = $menuRenderVariant === 'desktop';
@endphp

<div class="{{ $isDesktopMenu ? 'space-y-5' : 'space-y-6' }}">
    @foreach ($menuSections as $section)
        @if (!empty($section['items']))
            <section>
                <p class="{{ $isDesktopMenu ? 'mb-2 px-3 text-[10px]' : 'mb-2 px-2 text-[11px]' }} font-bold uppercase tracking-[0.22em] text-slate-400">
                    {{ $section['title'] }}
                </p>
                <ul class="{{ $isDesktopMenu ? 'space-y-1' : 'space-y-2' }}">
                    @foreach ($section['items'] as $item)
                        @php($isActive = $isMenuItemActive($item))
                        <li>
                            <a
                                class="group flex items-center gap-3 rounded-lg transition {{ $isDesktopMenu ? 'px-3 py-2.5' : 'px-4 py-3' }} {{ $isActive ? 'bg-primary text-white shadow-sm shadow-primary/20' : 'text-slate-600 hover:bg-white hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}"
                                href="{{ $item['href'] }}"
                                @if(in_array(parse_url($item['href'], PHP_URL_PATH), ['/pos/dashboard', '/pos/checkout', '/pos/history', '/backoffice/dashboard', '/backoffice/reports', '/backoffice/reports/sales', '/backoffice/reports/inventory'], true)) data-prefetch="priority" @endif
                            >
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-md {{ $isActive ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-slate-200 group-hover:text-slate-800 dark:bg-slate-800 dark:text-slate-400 dark:group-hover:bg-slate-700 dark:group-hover:text-white' }}">
                                    <span class="material-symbols-outlined text-[19px]">{{ $item['icon'] }}</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="block truncate text-sm font-semibold">{{ $item['label'] }}</span>
                                        @if (!empty($item['badge']))
                                            <span class="shrink-0 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-700">{{ $item['badge'] }}</span>
                                        @endif
                                    </span>
                                    @if (! $isDesktopMenu && !empty($item['description']))
                                        <span class="block truncate text-[11px] {{ $isActive ? 'text-white/80' : 'text-slate-500 dark:text-slate-400' }}">{{ $item['description'] }}</span>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endforeach
</div>
