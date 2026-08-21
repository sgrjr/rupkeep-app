<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <a href="{{ route('home') }}">
                <x-authentication-card-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </x-slot>

        <div class="text-sm text-gray-600">
            {{ __('You are about to sign in as') }}
        </div>

        <div class="mt-1 text-base font-semibold text-gray-900">
            {{ $user->name }}
        </div>
        <div class="text-sm text-gray-500">
            {{ $user->email }}
        </div>

        <form method="POST" action="{{ route('login-link.confirm', $token) }}" class="mt-6">
            @csrf

            <x-button class="w-full justify-center">
                {{ __('Sign me in') }}
            </x-button>
        </form>

        <div class="mt-4 text-center text-xs text-gray-500">
            {{ $expirySentence }} {{ __('It can only be used once.') }}
        </div>

        <div class="mt-4 text-center text-sm">
            <a class="underline text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                {{ __('Not you? Sign in with a password instead.') }}
            </a>
        </div>
    </x-authentication-card>
</x-guest-layout>
