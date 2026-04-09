<!DOCTYPE html>
<html class="light" lang="id">
    <head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <meta name="tenant-id" content="{{ $tenantId ?? '' }}" />
        <title>@yield('page_title', 'POS SaaS')</title>
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
        <script id="tailwind-config-base">
            tailwind.config = {
                darkMode: "class",
                theme: {
                    extend: {
                        colors: {
                            primary: "#135bec",
                            "background-light": "#f6f6f8",
                            "background-dark": "#101622",
                        },
                        fontFamily: {
                            display: ["Inter", "sans-serif"],
                        },
                        borderRadius: {
                            DEFAULT: "0.25rem",
                            lg: "0.5rem",
                            xl: "0.75rem",
                            full: "9999px",
                        },
                    },
                },
            };
        </script>
        <style>
            body {
                font-family: "Inter", sans-serif;
                height: 100dvh;
                overflow: hidden;
            }
            .material-symbols-outlined {
                font-variation-settings: "FILL" 0, "wght" 400, "GRAD" 0, "opsz" 24;
            }
        </style>
        @stack('head')
    </head>
    <body class="@yield('body_class', 'bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100')">
        @php
            $layoutMainClass = trim($__env->yieldContent('main_class', 'flex-1 overflow-y-auto pb-24'));
            $layoutUiMode = trim($__env->yieldContent('ui_mode', ''));
            $usesWideBackofficeShell = $layoutUiMode === 'backoffice' && str_contains($layoutMainClass, 'lg:pl-72');
            $defaultShellClass = $usesWideBackofficeShell
                ? 'relative flex h-screen w-full flex-col overflow-hidden bg-background-light dark:bg-background-dark'
                : 'relative mx-auto flex h-screen w-full max-w-[430px] flex-col overflow-hidden bg-background-light dark:bg-background-dark shadow-2xl';
        @endphp

        <div class="@yield('app_shell_class', $defaultShellClass)">
            @yield('header')
            <main class="@yield('main_class', 'flex-1 overflow-y-auto pb-24')">
                @yield('content')
            </main>

            @if(($showMenu ?? true) === true)
                @include('partials.menu-drawer')
            @endif
        </div>

        @stack('scripts')
    </body>
</html>
