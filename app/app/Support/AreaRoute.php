<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

class AreaRoute
{
    public static function pos(callable $routes): void
    {
        Route::prefix('pos')
            ->as('pos.')
            ->middleware(['canonical_portal_host:pos', 'area:pos'])
            ->group($routes);
    }

    public static function admin(callable $routes): void
    {
        Route::prefix('admin')
            ->as('admin.')
            ->middleware(['area:admin'])
            ->group($routes);
    }

    public static function owner(callable $routes): void
    {
        Route::prefix('owner')
            ->as('owner.')
            ->middleware(['area:owner'])
            ->group($routes);
    }

    public static function backoffice(callable $routes): void
    {
        Route::prefix('backoffice')
            ->as('backoffice.')
            ->middleware(['canonical_portal_host:backoffice', 'area:backoffice'])
            ->group($routes);
    }

    public static function superadmin(callable $routes): void
    {
        Route::prefix('superadmin')
            ->as('superadmin.')
            ->middleware(['area:superadmin'])
            ->group($routes);
    }
}
