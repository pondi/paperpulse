<!DOCTYPE html>
<html class="h-full" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

        @if(request()->is('scanner'))
        <!-- PWA Meta Tags (Scanner only) -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#f59e0b">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black">
        <meta name="apple-mobile-web-app-title" content="PaperPulse Scanner">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">
        @endif

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
    <body class="h-full bg-amber-50 dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100">
        @inertia
    </body>
</html>
