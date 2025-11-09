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

        <x-banner />

        <div class="relative min-h-screen">
            @php
                $legacyToasts = collect([
                    ['type' => 'success', 'message' => session('success')],
                    ['type' => 'error', 'message' => session('error')],
                    ['type' => 'warning', 'message' => session('warning')],
                    ['type' => 'info', 'message' => session('message')],
                ])->filter(fn ($toast) => filled($toast['message']))->values();

                $structuredToasts = collect(session('message'))
                    ->when(is_string(session('message')), fn ($collection) => collect([session('message')]))
                    ->flatMap(function ($value, $key) {
                        if (is_array($value)) {
                            return collect($value)->map(fn ($message, $type) => [
                                'type' => is_string($type) ? $type : 'info',
                                'message' => $message,
                            ]);
                        }

                        if (is_string($key) && in_array($key, ['success', 'error', 'warning', 'info'], true)) {
                            return [[
                                'type' => $key,
                                'message' => $value,
                            ]];
                        }

                        return [[
                            'type' => 'info',
                            'message' => $value,
                        ]];
                    })
                    ->filter(fn ($toast) => filled(data_get($toast, 'message')));

                $toastMessages = $legacyToasts->concat($structuredToasts)->values()->all();
            @endphp

            <x-toast-stack :toasts="$toastMessages" />

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
