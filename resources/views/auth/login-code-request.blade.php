<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <a href="{{ route('home') }}">
                <x-authentication-card-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </x-slot>

        <div class="mb-4 text-sm text-gray-600">
            {{ __('Enter your email and we will send you a one-time login code.') }}
        </div>

        @if (session('status'))
            <div class="mb-4 font-medium text-sm text-green-600">
                {{ session('status') }}
                @if(session('code_preview'))
                    <div class="mt-2 text-xs text-gray-500">
                        {{ __('DEV PREVIEW CODE: :code', ['code' => session('code_preview')]) }}
                    </div>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('login-code.store') }}">
            @csrf

            @if(isset($redirect))
                <input type="hidden" name="redirect" value="{{ $redirect }}">
            @endif

            <div>
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email"
                         :value="old('email')" required autofocus />
                <x-input-error for="email" class="mt-2" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <x-button>
                    {{ __('Send login code') }}
                </x-button>
            </div>
        </form>

        <div class="mt-4 text-center text-sm">
            <a class="underline text-gray-600 hover:text-gray-900" href="{{ route('login-code.verify-form', isset($redirect) ? ['redirect' => $redirect] : []) }}">
                {{ __('Already have a code? Enter it here.') }}
            </a>
        </div>
    </x-authentication-card>
</x-guest-layout>

