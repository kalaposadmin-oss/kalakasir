<!DOCTYPE html>
<html class="light" lang="id">
    <head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport" />
        <meta name="color-scheme" content="light" />
        <meta name="theme-color" content="#1e3a8a" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <meta name="tenant-id" content="{{ $tenantId ?? '' }}" />
        <title>@yield('page_title', 'POS SaaS')</title>
        <meta http-equiv="x-dns-prefetch-control" content="off" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            body {
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                letter-spacing: 0;
                min-height: 100vh;
                min-height: 100svh;
                height: 100dvh;
                overflow: hidden;
                background: #f8f9ff;
            }
            :root {
                --app-device-bottom-inset: env(safe-area-inset-bottom, 0px);
                --app-bottom-safe-space: calc(8rem + var(--app-device-bottom-inset));
                --app-footer-safe-space: max(1.5rem, calc(1rem + var(--app-device-bottom-inset)));
                --app-page-bg: #f8f9ff;
                --app-panel-bg: #ffffff;
                --app-border: #e2e8f0;
                --app-muted: #64748b;
                --app-soft-shadow: 0 1px 2px rgb(15 23 42 / 0.06), 0 1px 6px rgb(15 23 42 / 0.04);
            }
            .app-shell-page {
                height: 100vh;
                height: 100svh;
                height: 100dvh;
                max-height: 100dvh;
            }
            .app-bottom-safe-space {
                padding-bottom: var(--app-bottom-safe-space);
                scroll-padding-bottom: var(--app-bottom-safe-space);
            }
            .app-bottom-safe-footer {
                padding-bottom: var(--app-footer-safe-space);
            }
            .app-bottom-floating {
                bottom: calc(1rem + var(--app-device-bottom-inset));
            }
            .material-symbols-outlined {
                width: 1em;
                min-width: 1em;
                height: 1em;
                overflow: hidden;
                flex-shrink: 0;
                font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24;
            }
            html:not(.material-symbols-ready) .material-symbols-outlined {
                visibility: hidden;
            }
            .app-shell-page main {
                background: var(--app-page-bg);
            }
        </style>
        <script>
            (() => {
                const markMaterialSymbolsReady = () => {
                    document.documentElement.classList.add('material-symbols-ready');
                };

                if (!('fonts' in document)) {
                    markMaterialSymbolsReady();
                    return;
                }

                const fontSpec = '24px "Material Symbols Outlined"';
                if (document.fonts.check(fontSpec)) {
                    markMaterialSymbolsReady();
                    return;
                }

                document.fonts.load(fontSpec).then(() => {
                    if (document.fonts.check(fontSpec)) {
                        markMaterialSymbolsReady();
                    }
                }).catch(() => {});
            })();
        </script>
        @include('partials.ui-helpers')
        @stack('head')
    </head>
    <body class="@yield('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100')">
        @php
            $routeArea = request()->segment(1);
            $areaUiMode = match ($routeArea) {
                'backoffice', 'superadmin' => 'backoffice',
                'pos', 'app', 'owner' => 'pos',
                default => '',
            };
            $layoutMainClass = trim($__env->yieldContent('main_class', 'flex-1 overflow-y-auto pb-24'));
            $layoutUiMode = trim($__env->yieldContent('ui_mode', $areaUiMode));
            $usesManagedPortalShell = in_array($layoutUiMode, ['pos', 'backoffice', 'platform'], true);
            $shellModeClass = match ($layoutUiMode) {
                'backoffice' => 'app-shell-backoffice',
                'platform' => 'app-shell-platform',
                default => 'app-shell-pos',
            };
            $defaultShellClass = $shellModeClass.' relative mx-auto flex h-screen w-full max-w-[768px] flex-col overflow-hidden bg-[#f8f9ff] shadow-2xl';
            $requestedShellClass = trim($__env->yieldContent('app_shell_class', ''));
            $effectiveShellClass = $usesManagedPortalShell
                ? $defaultShellClass
                : ($requestedShellClass !== '' ? $requestedShellClass : $defaultShellClass);
            $effectiveMainClass = trim(str_replace(['xl:pl-72', 'lg:pl-72'], '', $layoutMainClass));
            $globalBottomNavHiddenPaths = [
                'pos/dashboard',
                'pos/checkout',
                'pos/payment-method',
                'pos/receipt-preview',
                'pos/shifts/open',
                'pos/shifts/close',
            ];
            $showGlobalBottomNav = $usesManagedPortalShell
                && ! in_array(request()->path(), $globalBottomNavHiddenPaths, true)
                && ! request()->is('pos/sales/*/receipt');
            if ($showGlobalBottomNav && ! str_contains($effectiveMainClass, 'app-bottom-safe-space')) {
                $effectiveMainClass = trim($effectiveMainClass.' app-bottom-safe-space');
            }
        @endphp

        <div class="{{ $effectiveShellClass }} app-shell-page">
            @hasSection('header')
                <div>
                    @yield('header')
                </div>
            @endif

            <main class="{{ $effectiveMainClass }}">
                @yield('content')
            </main>

            @if($showGlobalBottomNav)
                @include('partials.bottom-navigation', ['portal' => $layoutUiMode])
            @endif
        </div>
        @stack('scripts')
    </body>
</html>
