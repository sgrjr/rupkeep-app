<div>
    <x-app-layout>
        <x-slot name="header">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-slate-800">
                    {{ __('Onboard New Organization') }}
                </h2>
                @if($organizationId)
                    <a href="{{ route('organizations.show', ['organization' => $organizationId]) }}" 
                       class="text-sm font-medium text-orange-600 hover:text-orange-700">
                        {{ __('View Organization') }} →
                    </a>
                @endif
            </div>
        </x-slot>

        <div class="mx-auto max-w-4xl py-6">
            <!-- Progress Steps -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    @foreach([1 => __('Organization'), 2 => __('Import Data'), 3 => __('Users'), 4 => __('Vehicles'), 5 => __('Preferences'), 6 => __('Welcome Emails')] as $step => $label)
                        <div class="flex flex-1 items-center">
                            <div class="flex flex-col items-center">
                                <div @class([
                                    'flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-semibold transition',
                                    'border-orange-500 bg-orange-500 text-white' => $currentStep === $step,
                                    'border-orange-300 bg-orange-50 text-orange-600' => $currentStep > $step,
                                    'border-slate-300 bg-white text-slate-400' => $currentStep < $step,
                                ])>
                                    @if($currentStep > $step)
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    @else
                                        {{ $step }}
                                    @endif
                                </div>
                                <span @class([
                                    'mt-2 text-xs font-medium',
                                    'text-orange-600' => $currentStep >= $step,
                                    'text-slate-400' => $currentStep < $step,
                                ])>{{ $label }}</span>
                            </div>
                            @if($step < 6)
                                <div @class([
                                    'mx-2 h-0.5 flex-1',
                                    'bg-orange-500' => $currentStep > $step,
                                    'bg-slate-200' => $currentStep <= $step,
                                ])></div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Step Content -->
            <div class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow-sm">
                @if(session('message'))
                    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        {{ session('message') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                        {{ session('error') }}
                    </div>
                @endif

                <!-- Step 1: Organization -->
                @if($currentStep === 1)
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Organization Details') }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Create the organization that will use this system.') }}</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Name') }} *</label>
                                <input type="text" wire:model="org_name" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                @error('org_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Primary Contact') }} *</label>
                                <input type="text" wire:model="org_primary_contact" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                @error('org_primary_contact') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Email') }}</label>
                                <input type="email" wire:model="org_email" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Telephone') }}</label>
                                <input type="text" wire:model="org_telephone" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Street') }}</label>
                                <input type="text" wire:model="org_street" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('City') }}</label>
                                <input type="text" wire:model="org_city" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('State') }}</label>
                                <input type="text" wire:model="org_state" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Zip Code') }}</label>
                                <input type="text" wire:model="org_zip" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Owner Email') }}</label>
                                <input type="email" wire:model="org_owner_email" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <p class="mt-1 text-xs text-slate-500">{{ __('Email of existing user to set as owner. Leave blank to use super user.') }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Step 2: CSV Import -->
                @if($currentStep === 2)
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Import Existing Data') }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Optionally import jobs, customers, and logs from a CSV file exported from Google Sheets.') }}</p>
                        </div>

                        @if($csv_imported)
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                                {{ __('CSV imported successfully!') }}
                            </div>
                        @else
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('CSV File') }}</label>
                                <input type="file" wire:model="csv_file" accept=".csv,.txt" class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-full file:border-0 file:bg-orange-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-orange-600 hover:file:bg-orange-100">
                                @error('csv_file') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                @if($csv_file)
                                    <p class="mt-2 text-xs text-slate-500">{{ __('Selected') }}: {{ $csv_file->getClientOriginalName() }}</p>
                                    <button type="button" wire:click="importCsv" class="mt-3 inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600">
                                        {{ __('Import CSV') }}
                                    </button>
                                @endif
                            </div>
                        @endif

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                            <p class="font-semibold">{{ __('Skip this step?') }}</p>
                            <p class="mt-1">{{ __('You can import data later from the organization page.') }}</p>
                        </div>
                    </div>
                @endif

                <!-- Step 3: Users -->
                @if($currentStep === 3)
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Create Users') }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Add team members who will use the system. You can add more later.') }}</p>
                        </div>

                        @if(count($users) > 0)
                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Added Users') }}</p>
                                @foreach($users as $index => $user)
                                    <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-2">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ $user['name'] }}</p>
                                            <p class="text-xs text-slate-600">{{ $user['email'] }} • {{ User::ROLE_LABELS[$user['role']] ?? $user['role'] }}</p>
                                        </div>
                                        <button type="button" wire:click="removeUser({{ $index }})" class="text-xs font-medium text-red-600 hover:text-red-700">
                                            {{ __('Remove') }}
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Add New User') }}</p>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Name') }} *</label>
                                    <input type="text" wire:model="new_user_name" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    @error('new_user_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Email') }} *</label>
                                    <input type="email" wire:model="new_user_email" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    @error('new_user_email') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Password') }} *</label>
                                    <input type="password" wire:model="new_user_password" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    @error('new_user_password') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Confirm Password') }} *</label>
                                    <input type="password" wire:model="new_user_password_confirmation" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Role') }} *</label>
                                    <select wire:model="new_user_role" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                        @foreach($roles as $role)
                                            <option value="{{ $role['id'] }}">{{ $role['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <button type="button" wire:click="addUser" class="mt-4 inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600">
                                {{ __('Add User') }}
                            </button>
                        </div>
                    </div>
                @endif

                <!-- Step 4: Vehicles -->
                @if($currentStep === 4)
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Add Vehicles') }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Register vehicles used for pilot car services. You can add more later.') }}</p>
                        </div>

                        @if(count($vehicles) > 0)
                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Added Vehicles') }}</p>
                                @foreach($vehicles as $index => $vehicle)
                                    <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-2">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ $vehicle['name'] }}</p>
                                            @if($vehicle['odometer'])
                                                <p class="text-xs text-slate-600">{{ __('Odometer') }}: {{ number_format($vehicle['odometer']) }}</p>
                                            @endif
                                        </div>
                                        <button type="button" wire:click="removeVehicle({{ $index }})" class="text-xs font-medium text-red-600 hover:text-red-700">
                                            {{ __('Remove') }}
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Add New Vehicle') }}</p>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Name') }} *</label>
                                    <input type="text" wire:model="new_vehicle_name" placeholder="Car 001" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    @error('new_vehicle_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Odometer') }}</label>
                                    <input type="number" wire:model="new_vehicle_odometer" min="0" class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    @error('new_vehicle_odometer') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <button type="button" wire:click="addVehicle" class="mt-4 inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600">
                                {{ __('Add Vehicle') }}
                            </button>
                        </div>
                    </div>
                @endif

                <!-- Step 5: Preferences -->
                @if($currentStep === 5)
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Organization Preferences') }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Configure organization-specific settings. These can be changed later.') }}</p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <p>{{ __('Preferences configuration coming soon. You can manage organization settings from the organization edit page.') }}</p>
                        </div>
                    </div>
                @endif

                <!-- Step 6: Welcome Emails -->
                @if($currentStep === 6)
                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Send Welcome Emails') }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Optionally send welcome emails to newly created users. Select which users should receive the email.') }}</p>
                        </div>

                        @if($email_sent)
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                                {{ __('Welcome emails sent successfully!') }}
                            </div>
                        @else
                            @if($allUsers->count() > 0)
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Select Users') }}</p>
                                        <div class="flex gap-2">
                                            <button type="button" wire:click="$set('selected_users_for_email', {{ json_encode($allUsers->pluck('id')->toArray()) }})" class="text-xs font-medium text-orange-600 hover:text-orange-700">
                                                {{ __('Select All') }}
                                            </button>
                                            <button type="button" wire:click="$set('selected_users_for_email', [])" class="text-xs font-medium text-slate-600 hover:text-slate-700">
                                                {{ __('Clear') }}
                                            </button>
                                        </div>
                                    </div>

                                    @foreach($allUsers as $user)
                                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 hover:bg-slate-50">
                                            <input type="checkbox" wire:model="selected_users_for_email" value="{{ $user->id }}" class="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                                            <div class="flex-1">
                                                <p class="text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                                                <p class="text-xs text-slate-600">{{ $user->email }} • {{ User::ROLE_LABELS[$user->organization_role] ?? $user->organization_role }}</p>
                                            </div>
                                        </label>
                                    @endforeach

                                    @if(count($selected_users_for_email) > 0)
                                        <button type="button" wire:click="sendWelcomeEmails" class="w-full rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600">
                                            {{ __('Send Welcome Emails to :count Users', ['count' => count($selected_users_for_email)]) }}
                                        </button>
                                    @endif
                                </div>
                            @else
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                    <p>{{ __('No users created yet. Go back to step 3 to add users.') }}</p>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

                <!-- Navigation Buttons -->
                <div class="mt-8 flex items-center justify-between border-t border-slate-200 pt-6">
                    <button type="button" wire:click="previousStep" @if($currentStep === 1) disabled @endif
                            @class([
                                'inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold shadow-sm transition',
                                'border border-slate-300 bg-white text-slate-600 hover:bg-slate-50' => $currentStep > 1,
                                'border border-slate-200 bg-slate-100 text-slate-400 cursor-not-allowed' => $currentStep === 1,
                            ])>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                        {{ __('Previous') }}
                    </button>

                    @if($currentStep < 6)
                        <button type="button" wire:click="nextStep" 
                                class="inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600">
                            {{ __('Next') }}
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    @else
                        <button type="button" wire:click="complete" 
                                class="inline-flex items-center gap-2 rounded-full bg-emerald-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-emerald-600">
                            {{ __('Complete Onboarding') }}
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </x-app-layout>
</div>

