@extends('layouts.guest')

@php
    /** @var array|null $primaryAction */
    $primaryAction = $primaryAction ?? null;
    $secondaryActions = $secondaryActions ?? [];
    $showLogout = $showLogout ?? false;
    $logoutRedirect = $logoutRedirect ?? null;
@endphp

@section('content')
    <div class="min-h-screen bg-slate-100/80 py-10">
        <div class="mx-auto max-w-3xl px-4">
            <section class="overflow-hidden rounded-3xl bg-white/95 p-8 text-center shadow-xl">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-orange-100 text-orange-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12V16.5zm9-4.5A9 9 0 1 1 3 12a9 9 0 0 1 18 0Z" />
                    </svg>
                </div>

                <h1 class="mt-5 text-2xl font-semibold text-slate-900">{{ $title }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $message }}</p>

                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    @if($primaryAction && isset($primaryAction['href']))
                        <a href="{{ $primaryAction['href'] }}"
                           class="inline-flex items-center gap-2 rounded-full bg-orange-500 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-600">
                            {{ $primaryAction['label'] ?? __('Continue') }}
                        </a>
                    @endif

                    @foreach($secondaryActions as $action)
                        <a href="{{ $action['href'] ?? '#' }}"
                           class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-5 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-orange-200 hover:text-orange-600">
                            {{ $action['label'] ?? __('Learn more') }}
                        </a>
                    @endforeach
                </div>

                @if($showLogout && $logoutRedirect)
                    <form method="POST" action="{{ $logoutRedirect }}" class="mt-6">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400 transition hover:border-slate-300 hover:text-orange-500">
                            {{ __('Sign out and switch accounts') }}
                        </button>
                    </form>
                @endif

                <p class="mt-6 text-[11px] uppercase tracking-wide text-slate-400">
                    {{ __('Need help? Contact the support team at Casco Bay Pilot Car.') }}
                </p>
            </section>
        </div>
    </div>
@endsection
