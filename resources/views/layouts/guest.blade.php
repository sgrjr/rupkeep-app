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
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="welcome {{true || request()->has('customer_id')? 'dark-theme':'default-theme'}}">
        <div>
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

            @isset($slot)
                {{ $slot }}
            @else
                @yield('content')
            @endisset
        </div>
        @livewireScripts
    </body>
</html>
