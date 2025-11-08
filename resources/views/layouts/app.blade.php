@props(['on','organizations'=>[]])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/css/default_dashboard_theme.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="theme-base antialiased {{ auth()->user()->dashboard_theme }}">

        @if(Session::has('message'))
        <p class="alert alert-info alert-dismissable fixed top-0 right-0">{{ Session::get('message') }}</p>
        @endif
        <x-banner />

        <div class="relative min-h-screen">
            <div class="min-h-screen content-body flex flex-col">
                <!-- x-navigation-menu -->
                <livewire:primary-navigation-menu />

                <!-- Page Heading -->
                @if (isset($header))
                    <header class="bg-white/90 backdrop-blur dark:bg-gray-800/95 shadow">
                        <div class="mx-auto max-w-7xl py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <!-- Page Content -->
                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @stack('modals')

        @livewireScripts
    </body>
</html>
