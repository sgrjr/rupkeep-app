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
                    <header class="bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 text-white shadow-2xl">
                        <div class="mx-auto max-w-7xl py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <!-- Page Content -->
                <main class="flex-1 bg-slate-300">
                    {{ $slot }}
                </main>

                <!-- Footer with Feedback Form -->
                @auth
                    <footer class="border-t border-slate-400 bg-slate-200 backdrop-blur">
                        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <div class="text-xs text-slate-500">
                                    <p>{{ __('© :year :app. All rights reserved.', ['year' => date('Y'), 'app' => config('app.name')]) }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('documentation.index') }}" 
                                       class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                                        </svg>
                                        {{ __('Documentation') }}
                                    </a>
                                    <button type="button" onclick="document.getElementById('footer-feedback-modal').classList.remove('hidden')" 
                                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                                        </svg>
                                        {{ __('Feedback') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </footer>
                    
                    <!-- Footer Feedback Modal -->
                    <div id="footer-feedback-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" 
                         onclick="if(event.target === this) this.classList.add('hidden')"
                         x-data="{ closeModal() { this.classList.add('hidden'); } }"
                         @feedback-submitted.window="setTimeout(() => closeModal(), 2000)">
                        <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white shadow-xl" onclick="event.stopPropagation()">
                            <div class="border-b border-slate-200 bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 px-6 py-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-white">{{ __('Send Feedback') }}</h3>
                                    <button type="button" onclick="document.getElementById('footer-feedback-modal').classList.add('hidden')" class="text-white/80 hover:text-white">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="p-6">
                                <livewire:feedback-form :hideTrigger="true" :inline="false" />
                            </div>
                        </div>
                    </div>
                @endauth
            </div>
        </div>

        @stack('modals')

        @livewireScripts

        @include('components.push-notifications')
    </body>
</html>
