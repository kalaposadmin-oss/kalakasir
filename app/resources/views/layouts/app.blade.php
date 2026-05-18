<!DOCTYPE html>
<html class="light" lang="id">
    <head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <meta name="tenant-id" content="{{ $tenantId ?? '' }}" />
        <title>@yield('page_title', 'POS SaaS')</title>
        <meta http-equiv="x-dns-prefetch-control" content="off" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            body {
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                letter-spacing: 0;
                height: 100dvh;
                overflow: hidden;
                background: #f8fafc;
            }
            :root {
                --app-bottom-safe-space: calc(7rem + env(safe-area-inset-bottom));
                --app-page-bg: #f8fafc;
                --app-panel-bg: #ffffff;
                --app-border: #e2e8f0;
                --app-muted: #64748b;
                --app-soft-shadow: 0 1px 2px rgb(15 23 42 / 0.06), 0 1px 6px rgb(15 23 42 / 0.04);
            }
            .app-bottom-safe-space {
                padding-bottom: var(--app-bottom-safe-space);
                scroll-padding-bottom: var(--app-bottom-safe-space);
            }
            .app-bottom-safe-footer {
                padding-bottom: calc(1rem + env(safe-area-inset-bottom));
            }
            .app-bottom-floating {
                bottom: calc(1rem + env(safe-area-inset-bottom));
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
            $usesWideBackofficeShell = $layoutUiMode === 'backoffice';
            $defaultShellClass = $usesWideBackofficeShell
                ? 'app-shell-backoffice relative flex h-screen w-full flex-col overflow-hidden bg-background-light dark:bg-background-dark'
                : 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl';
            $requestedShellClass = trim($__env->yieldContent('app_shell_class', ''));
            $effectiveShellClass = $usesWideBackofficeShell
                ? $defaultShellClass
                : ($requestedShellClass !== '' ? $requestedShellClass : $defaultShellClass);
            $effectiveMainClass = $usesWideBackofficeShell && ! str_contains($layoutMainClass, 'lg:pl-72')
                ? trim($layoutMainClass.' lg:pl-72')
                : $layoutMainClass;
        @endphp

        <div class="{{ $effectiveShellClass }} app-shell-page">
            @if(($showMenu ?? true) === true)
                @include('partials.menu-drawer')
            @endif

            @hasSection('header')
                <div class="{{ $usesWideBackofficeShell ? 'lg:pl-72' : '' }}">
                    @yield('header')
                </div>
            @endif

            <main class="{{ $effectiveMainClass }}">
                @yield('content')
            </main>
        </div>
        @stack('scripts')
    </body>
</html>
