<div>

    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        
    <h1>
        <a href="{{route('organizations.show', ['organization' =>$profile->organization_id])}}">&larr;{{$profile->organization?->name}} Summary</a>
        @can('viewAny', \App\Models\PilotCarJob::class)
         | <a href="{{route('jobs.index', ['organization' =>$profile->organization_id, 'car_driver_id' => $profile->id ])}}">&larr; Logs</a>
        @endcan
    </h1>
    
        @if (Laravel\Fortify\Features::canUpdateProfileInformation())
            <livewire:profile.update-profile-information-form :profile="$profile" />
            <x-section-border />
        @endif

       
        <div class="mt-10 sm:mt-0">
            @livewire('profile.update-password-form', ['profile'=> $profile])
        </div>

        <x-section-border />
        
        <div class="mt-10 sm:mt-0">
            <div class="bg-white rounded-md shadow p-6">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Test Notification') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Send a test notification to verify your notification settings are working correctly.') }}</p>
                </div>
                
                @if($notificationTestStatus === 'success')
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm font-medium text-green-800">{{ $notificationTestMessage }}</p>
                        </div>
                        <button wire:click="clearNotificationTestStatus" class="mt-2 text-xs text-green-700 hover:text-green-900 underline">Dismiss</button>
                    </div>
                @elseif($notificationTestStatus === 'error')
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm font-medium text-red-800">{{ $notificationTestMessage }}</p>
                        </div>
                        <button wire:click="clearNotificationTestStatus" class="mt-2 text-xs text-red-700 hover:text-red-900 underline">Dismiss</button>
                    </div>
                @endif
                
                <form wire:submit.prevent="testNotification" class="pretty-form">
                @csrf
                <button 
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="testNotification"
                    class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="testNotification">Send Test Notification</span>
                    <span wire:loading wire:target="testNotification" class="inline-flex items-center gap-2">
                        <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-gray-600 border-t-transparent"></span>
                        Sending...
                    </span>
                </button>
                </form>
            </div>
        </div>

        @can('createUser', $profile->organization)
        <x-section-border />
        
        <div class="mt-10 sm:mt-0">
            <div class="bg-white rounded-md shadow p-6">
                <form wire:submit="mergeToUser" method="post" class="flex">
                @csrf
                <input name="user_id" type="number" required  wire:model="merged_user"
                wire:keydown.enter="mergeToUser"/>
                <button>Merge this User with Other User</button>
                </form>
            </div>
        </div>
        @endcan

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