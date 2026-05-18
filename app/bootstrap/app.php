<?php

use App\Models\Tenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

            return '/';
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
            'area' => \App\Http\Middleware\EnsureAreaRoute::class,
            'shift_open' => \App\Http\Middleware\EnsureShiftOpen::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $handleExpiredWebSession = static function (Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'CSRF token mismatch.',
                ], 419);
            }

            $target = match (true) {
                $request->is('backoffice'), $request->is('backoffice/login') => '/backoffice',
                $request->is('register*') => '/'.ltrim($request->path(), '/'),
                default => '/',
            };

            $safeInput = collect($request->except([
                '_token',
                'password',
                'password_confirmation',
            ]))->toArray();

            return redirect($target)
                ->withInput($safeInput)
                ->withErrors([
                    'session' => 'Sesi halaman sudah habis. Muat ulang lalu coba kirim lagi.',
                ]);
        };

        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            return $handleExpiredWebSession($request);
        });

        $exceptions->render(function (HttpException $exception, Request $request) use ($handleExpiredWebSession) {
            if ($exception->getStatusCode() !== 419) {
                return null;
            }

            return $handleExpiredWebSession($request);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return null;
        });
    })->create();
