<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <a href="{{ route('home') }}">
                <x-authentication-card-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </x-slot>

        <div class="mb-4 text-sm text-gray-600">
            {{ __('Enter the login code that was emailed to you. Codes expire quickly for security.') }}
        </div>

        <form method="POST" action="{{ route('login-code.verify') }}">
            @csrf

            <div>
                <x-label for="code" value="{{ __('Login Code') }}" />
                <x-input id="code" class="block mt-1 w-full tracking-widest uppercase" type="text" name="code"
                         :value="old('code')" required autofocus maxlength="12" autocomplete="one-time-code" />
                <x-input-error for="code" class="mt-2" />
            </div>

            <div class="flex items-center justify-between mt-4">
                <a class="underline text-sm text-gray-600 hover:text-gray-900"
                   href="{{ route('login-code.create') }}">
                    {{ __('Need a new code?') }}
                </a>

                <x-button>
                    {{ __('Sign in') }}
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>

