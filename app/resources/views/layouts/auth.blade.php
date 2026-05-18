<!DOCTYPE html>
<html class="light" lang="id">
    <head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport" />
        <title>@yield('page_title', 'Autentikasi')</title>
        <meta http-equiv="x-dns-prefetch-control" content="off" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            body {
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                min-height: 100dvh;
            }
        </style>
        @stack('head')
    </head>
    <body class="@yield('body_class', 'bg-background-light text-slate-900')">
        @yield('content')
        @stack('scripts')
    </body>
</html>
