<?php

use App\Models\Tenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api_platform.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            if ($request->is('api/*')) {
                return null;
            }

            $tenantId = Schema::hasTable('tenants')
                ? Tenant::query()->value('id')
                : null;

            if ($tenantId && Schema::hasTable('users')) {
                $hasUsers = DB::table('users')
                    ->where('tenant_id', $tenantId)
                    ->exists();

                if (! $hasUsers) {
                    return '/register/step-1';
                }
            }

            return '/login';
        });
        $middleware->statefulApi();
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);
        $middleware->append(\App\Http\Middleware\TenantResolver::class);
        $middleware->append(\App\Http\Middleware\PreventWritesDuringValuationRebuild::class);
        $middleware->append(\App\Http\Middleware\RequestContextMiddleware::class);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'feature_gate' => \App\Http\Middleware\FeatureGateMiddleware::class,
            'canonical_portal_host' => \App\Http\Middleware\CanonicalPortalHostMiddleware::class,
            'portal' => \App\Http\Middleware\PortalSeparationMiddleware::class,
            'subscription_status' => \App\Http\Middleware\SubscriptionStatusMiddleware::class,
            'web_access' => \App\Http\Middleware\WebAccessMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return null;
        });
    })->create();
