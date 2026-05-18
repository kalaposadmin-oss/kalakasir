<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use LogicException;
use Throwable;

class NavigationValidator
{
    public static function validateConfig(): void
    {
        $items = config('navigation.items', []);
        $sections = config('navigation.sections', []);

        foreach ($items as $item) {
            self::validateItem($item);
        }

        foreach ($sections as $area => $areaSections) {
            foreach ($areaSections as $title => $keys) {
                foreach ($keys as $key) {
                    if (! isset($items[$key])) {
                        throw new LogicException("Navigation section [{$area}.{$title}] references unknown item [{$key}].");
                    }

                    self::validateSectionItem($area, $items[$key]);
                }
            }
        }
    }

    public static function validateItem(array $item): void
    {
        $key = $item['key'] ?? 'unknown';
        $routeTarget = (string) ($item['route'] ?? '');

        if ($routeTarget === '') {
            throw new LogicException("Navigation item [{$key}] must define a route target.");
        }

        $itemArea = $item['area'] ?? null;
        $routePath = self::resolveTargetPath($routeTarget);

        if (! $itemArea || ! str_starts_with($routePath, '/'.$itemArea.'/')) {
            throw new LogicException("Navigation item [{$key}] must point to a route inside its declared area.");
        }
    }

    public static function validateSectionItem(string $renderArea, array $item): void
    {
        $itemArea = $item['area'] ?? null;
        $badge = $item['badge'] ?? null;
        $key = $item['key'] ?? 'unknown';

        if ($renderArea !== $itemArea && $badge !== 'Backoffice') {
            throw new LogicException("Navigation item [{$key}] crosses area [{$renderArea} -> {$itemArea}] without Backoffice badge.");
        }
    }

    private static function resolveTargetPath(string $target): string
    {
        if (Route::has($target)) {
            return route($target, [], false);
        }

        try {
            Route::getRoutes()->match(Request::create($target, 'GET'));
        } catch (Throwable) {
            throw new LogicException("Navigation target [{$target}] is not registered.");
        }

        return $target;
    }
}
