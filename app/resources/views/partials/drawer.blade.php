@php
    use App\Support\NavigationRegistry;

    $drawerArea = $area ?? 'pos';
    $drawerRole = $role ?? 'cashier';
    $drawerCanFeature = $canFeature ?? static fn (?string $feature): bool => $feature === null || true;
    $drawerSections = NavigationRegistry::forArea($drawerArea, $drawerRole, $drawerCanFeature);
@endphp

<nav class="space-y-6">
    @foreach ($drawerSections as $section)
        <section class="space-y-2">
            <h3 class="px-4 text-xs font-bold uppercase tracking-wider text-slate-400">
                {{ $section['title'] }}
            </h3>

            <div class="space-y-1">
                @foreach ($section['items'] as $item)
                    <a href="{{ $item['href'] }}" class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 hover:text-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white">
                        <span>{{ $item['label'] }}</span>

                        @if (! empty($item['badge']))
                            <span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-700">
                                {{ $item['badge'] }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</nav>
