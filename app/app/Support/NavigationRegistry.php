<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class NavigationRegistry
{
    public static function forArea(string $area, string $role, callable $canFeature): array
    {
        $items = collect(config('navigation.items', []));
        $sections = config("navigation.sections.{$area}", []);

        return collect($sections)
            ->map(function (array $keys, string $title) use ($items, $role, $canFeature) {
                $resolvedItems = collect($keys)
                    ->map(fn (string $key) => $items->get($key))
                    ->filter(fn ($item) => is_array($item))
                    ->filter(function (array $item) use ($role, $canFeature) {
                        if (! in_array($role, $item['roles'] ?? [], true)) {
                            return false;
                        }

                        $feature = $item['feature'] ?? null;

                        return $feature === null ? true : (bool) $canFeature($feature);
                    })
                    ->map(function (array $item) {
                        return [
                            'key' => $item['key'],
                            'label' => $item['label'],
                            'href' => self::resolveHref((string) $item['route']),
                            'badge' => $item['badge'] ?? null,
                            'area' => $item['area'],
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'title' => $title,
                    'items' => $resolvedItems,
                ];
            })
            ->filter(fn (array $section) => $section['items'] !== [])
            ->values()
            ->all();
    }

    private static function resolveHref(string $target): string
    {
        if ($target === '') {
            return '#';
        }

        return Route::has($target)
            ? route($target)
            : $target;
    }
}
