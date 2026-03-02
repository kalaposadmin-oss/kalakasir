<!DOCTYPE html>
<html class="light" lang="id">
    <head>
        <meta charset="utf-8" />
        <meta content="width=device-width, initial-scale=1.0" name="viewport" />
        <title>@yield('page_title', 'Autentikasi')</title>
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
        <style>
            body {
                font-family: "Inter", sans-serif;
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
