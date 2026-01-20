@props(['profile'=>null])

<x-form-section submit="updatePassword">
    <x-slot name="title">
        {{ __('Update Password') }}
    </x-slot>

    <x-slot name="description">
        @if(isset($user) && Auth::id() !== $user->id)
            {{ __('Set a new password for this user.') }}
        @elseif(isset($profile) && Auth::id() !== $profile->id)
            {{ __('Set a new password for this user.') }}
        @else
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        @endif
    </x-slot>

    <x-slot name="form">
        {{-- Only show current password field when user is updating their own password --}}
        @php
            $isAdminUpdating = (isset($user) && Auth::id() !== $user->id) || (isset($profile) && Auth::id() !== $profile->id);
        @endphp

        @if(!$isAdminUpdating)
            <div class="col-span-6 sm:col-span-4">
                <x-label for="current_password" value="{{ __('Current Password') }}" />
                <x-input id="current_password" type="password" class="mt-1 block w-full" wire:model="state.current_password" autocomplete="current-password" required/>
                <x-input-error for="current_password" class="mt-2" />
            </div>
        @endif

        <div class="col-span-6 sm:col-span-4">
            <x-label for="password" value="{{ __('New Password') }}" />
            <x-input id="password" type="password" class="mt-1 block w-full" wire:model="state.password" autocomplete="new-password" required/>
            <x-input-error for="password" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="password_confirmation" value="{{ __('Confirm Password') }}" />
            <x-input id="password_confirmation" type="password" class="mt-1 block w-full" wire:model="state.password_confirmation" autocomplete="new-password" required/>
            <x-input-error for="password_confirmation" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button>
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
