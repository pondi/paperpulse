<!DOCTYPE html>
<html class="h-full" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'PaperPulse') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Theme bootstrap: avoid FOUC by setting theme before styles load -->
        <script>
            (function() {
                try {
                    const saved = localStorage.getItem('theme');
                    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    const isDark = saved === 'dark' || (saved !== 'light' && prefersDark);
                    document.documentElement.classList.toggle('dark', isDark);
                } catch (e) {}
            })();
        </script>

        <!-- Scripts -->
        @routes
        @vite('resources/js/app.js')
        @inertiaHead
    </head>
    <body class="h-full bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
        @inertia
    </body>
</html>
