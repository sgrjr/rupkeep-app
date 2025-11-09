@extends('layouts.guest')

@php
    $loginUrl = $loginUrl ?? route('login');
    $loginCodeUrl = $loginCodeUrl ?? route('login-code.create');
@endphp

@section('content')
    <div class="min-h-screen bg-slate-100/80 py-10">
        <div class="mx-auto max-w-3xl px-4">
            <section class="overflow-hidden rounded-3xl bg-white/95 p-8 shadow-xl">
                <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                    <div class="space-y-3">
                        <span class="inline-flex items-center gap-2 rounded-full bg-orange-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-orange-600">
                            {{ __('Customer Portal') }}
                        </span>
                        <h1 class="text-3xl font-semibold text-slate-900">{{ $title }}</h1>
                        <p class="text-sm leading-6 text-slate-600">{{ $message }}</p>
                    </div>
                    <div class="hidden h-24 w-24 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 to-orange-300 text-white shadow-lg md:flex">
                        <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 4v-4m0-2h.01" />
                        </svg>
                    </div>
                </div>

                <div class="mt-8 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('Sign in with password') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ __('Use your usual email and password to continue to your invoices.') }}</p>
                        <a href="{{ $loginUrl }}"
                           class="mt-4 inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-600">
                            {{ __('Continue to login') }}
                        </a>
                    </div>

                    <div class="rounded-2xl border border-orange-200 bg-orange-50/60 p-6 shadow-sm">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-orange-600">{{ __('Request one-time code') }}</h2>
                        <p class="mt-2 text-sm text-orange-700">{{ __('No password handy? Get a secure code sent to your inbox. Codes expire quickly for privacy.') }}</p>
                        <a href="{{ $loginCodeUrl }}"
                           class="mt-4 inline-flex items-center gap-2 rounded-full border border-orange-300 bg-white px-4 py-2 text-sm font-semibold text-orange-600 shadow-sm transition hover:border-orange-400 hover:text-orange-700">
                            {{ __('Email me a login code') }}
                        </a>
                    </div>
                </div>

                <p class="mt-8 text-[11px] uppercase tracking-wide text-slate-400">
                    {{ __('Tip: Already signed in on another device? Enter the code there and refresh this page.') }}
                </p>
            </section>
        </div>
    </div>
@endsection
