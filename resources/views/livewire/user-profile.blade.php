<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        @if (Laravel\Fortify\Features::canUpdateProfileInformation())
            <livewire:profile.update-profile-information-form :profile="$profile" />
            <x-section-border />
        @endif

       
        <div class="mt-10 sm:mt-0">
            @livewire('profile.update-password-form', ['profile'=> $profile])
        </div>

        <x-section-border />
        
        <div class="mt-10 sm:mt-0">
            <form wire:submit="testNotification" method="post" class="pretty-form">
            @csrf
            <button>test notification</button>
            </form>
        </div>

        <x-section-border />

        @if(false)

        @can('updateOrganizationRole', $profile)
            <div class="mt-10 sm:mt-0">
                @livewire('profile.update-organization-role-form')
            </div>

            <x-section-border />
        @endcan

        @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
            <div class="mt-10 sm:mt-0">
                @livewire('profile.two-factor-authentication-form')
            </div>

            <x-section-border />
        @endif

        <div class="mt-10 sm:mt-0">
            @livewire('profile.logout-other-browser-sessions-form')
        </div>

        @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
            <x-section-border />

            <div class="mt-10 sm:mt-0">
                @livewire('profile.delete-user-form')
            </div>
        @endif

        @endif
    </div>
</div>